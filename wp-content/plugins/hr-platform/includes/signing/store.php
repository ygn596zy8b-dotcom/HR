<?php
/**
 * Where article signatures live: the table {prefix}hr_pqsig, one row per signed article.
 *
 * Why a table and not post meta: WordPress loads every meta row of every post a query returns (update_post_meta_cache),
 * so a signature document in post meta (about 11 KB with its attached article) was read from the database for every
 * post in every archive, home page section and feed, and never used there. In this table the documents are read only
 * by /signed/<id>.sig.json, and the small facts (key ID, hash, time) only by the article page, the feeds and the signer.
 *
 * Post meta keeps one small marker, META_STALE ('1' on articles whose signature is out of date), because the signer's
 * queue query and the edge purge module (includes/edge/purge.php, which watches _hr_pqsig* meta) rely on it.
 *
 * Older versions kept everything in post meta (META_SIG, META_SHA, META_KID, META_AT). A one-time migration moves those
 * rows here in batches (WP-Cron, or `wp hr sign migrate`); it is safe to interrupt and to run twice, and it deletes a
 * post's old meta only after reading the new row back and finding it identical. Until it has finished, every read
 * falls back to the old meta, so nothing changes for readers in between.
 */

namespace HR\Platform\Signing;

if ( ! defined( 'ABSPATH' ) ) exit;

const STORE_DB_VERSION   = 1;
const STORE_DB_OPTION    = 'hr_pqsig_db_version';
const MIGRATED_OPTION    = 'hr_pqsig_migrated';   // '0' while old meta may remain, else the Unix time it finished
const MIGRATE_EVENT      = 'hr_pqsig_migrate';
const MIGRATE_LOCK       = 'hr_pqsig_migrate_lock';
const MIGRATE_BATCH      = 50;
const CACHE_GROUP        = 'hr_pqsig';
const LEGACY_META        = array( META_SIG, META_SHA, META_KID, META_AT );

function table(): string {
    global $wpdb;
    return $wpdb->prefix . 'hr_pqsig';
}

// ---------------------------------------------------------------------------------------------------------------
// Install
// ---------------------------------------------------------------------------------------------------------------

/** Create (or upgrade) the table, then start the migration if older versions left signatures in post meta. */
function install_store(): void {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    // The document is stored off-page by InnoDB (it is longer than a page fragment), so reading the other columns
    // never touches it.
    dbDelta( 'CREATE TABLE ' . table() . " (
        post_id bigint(20) unsigned NOT NULL,
        kid varchar(64) NOT NULL DEFAULT '',
        sha512 varchar(128) NOT NULL DEFAULT '',
        signed_at bigint(20) NOT NULL DEFAULT 0,
        doc longtext NOT NULL,
        PRIMARY KEY  (post_id),
        KEY kid (kid)
    ) " . $wpdb->get_charset_collate() . ';' );
    if ( ! table_exists() ) {
        error_log( 'hr-platform: the signature table ' . table() . ' could not be created; signatures stay in post meta until it can.' );
        return;
    }
    update_option( STORE_DB_OPTION, STORE_DB_VERSION, true ); // read on every request: keep it in the autoloaded set
    if ( legacy_rows_exist() ) {
        set_migration_state( false );
        schedule_migration( 5 );
    } elseif ( ! migration_done() ) {
        set_migration_state( true );
    }
}
add_action( 'hr_install', __NAMESPACE__ . '\\install_store' );

// Versioned separately from the platform, so deploying this module alone still creates the table.
add_action( 'init', function () {
    if ( (int) get_option( STORE_DB_OPTION ) !== STORE_DB_VERSION ) {
        install_store();
    }
}, 98 );

function table_exists(): bool {
    global $wpdb;
    return table() === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( table() ) ) );
}

function legacy_rows_exist(): bool {
    global $wpdb;
    return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$wpdb->postmeta} WHERE meta_key IN (%s, %s, %s, %s) LIMIT 1", LEGACY_META ) );
}

/** True once no signature is left in post meta. Read from the autoloaded options: costs no query. */
function migration_done( bool $refresh = false ): bool {
    static $done = null;
    if ( null === $done || $refresh ) {
        $all  = wp_load_alloptions();
        $done = isset( $all[ MIGRATED_OPTION ] ) && (int) $all[ MIGRATED_OPTION ] > 0;
    }
    return $done;
}

/** Record the migration state (autoloaded, so migration_done() stays free) and refresh this request's copy. */
function set_migration_state( bool $done ): void {
    update_option( MIGRATED_OPTION, $done ? (string) time() : '0', true );
    migration_done( true );
}

// ---------------------------------------------------------------------------------------------------------------
// Reads
// ---------------------------------------------------------------------------------------------------------------

/**
 * The stored signature facts of a post: array( kid, sha512, at ) or null when it has no signature.
 * The document itself is not loaded (see signature_doc()). In a loop, the first call fetches the rows of every post
 * of the main query in one query, so a feed of 10 items costs one query, not ten.
 */
function signature_record( int $post_id ): ?array {
    if ( $post_id < 1 ) {
        return null;
    }
    $found = false;
    $row   = wp_cache_get( $post_id, CACHE_GROUP, false, $found );
    if ( ! $found ) {
        $ids = array( $post_id );
        if ( isset( $GLOBALS['wp_query'] ) && $GLOBALS['wp_query'] instanceof \WP_Query && is_array( $GLOBALS['wp_query']->posts ) ) {
            $loop = array_map( static fn( $p ) => (int) ( is_object( $p ) ? $p->ID : $p ), $GLOBALS['wp_query']->posts );
            if ( in_array( $post_id, $loop, true ) ) {
                $ids = $loop;
            }
        }
        prime_signature_records( $ids );
        $row = wp_cache_get( $post_id, CACHE_GROUP );
    }
    if ( is_array( $row ) ) {
        return $row;
    }
    return migration_done() ? null : legacy_record( $post_id );
}

/** Fetch the signature facts of several posts in one query (posts already cached are skipped). */
function prime_signature_records( array $ids ): void {
    global $wpdb;
    $ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ), static fn( $id ) => $id > 0 ) ) );
    if ( ! $ids ) {
        return;
    }
    $missing = array();
    foreach ( wp_cache_get_multiple( $ids, CACHE_GROUP ) as $id => $value ) {
        if ( false === $value ) {
            $missing[] = (int) $id;
        }
    }
    if ( ! $missing ) {
        return;
    }
    $rows = $wpdb->get_results( $wpdb->prepare(
        'SELECT post_id, kid, sha512, signed_at FROM ' . table() . ' WHERE post_id IN (' . implode( ',', array_fill( 0, count( $missing ), '%d' ) ) . ')',
        $missing
    ) );
    $found = array_fill_keys( $missing, 0 ); // 0: known to have no row (false would mean "not looked up")
    foreach ( (array) $rows as $r ) {
        $found[ (int) $r->post_id ] = array( 'kid' => (string) $r->kid, 'sha512' => (string) $r->sha512, 'at' => (int) $r->signed_at );
    }
    wp_cache_set_multiple( $found, CACHE_GROUP );
}

/** The signature document exactly as stored (and served), or '' when there is none. */
function signature_doc( int $post_id ): string {
    global $wpdb;
    if ( $post_id < 1 ) {
        return '';
    }
    $doc = $wpdb->get_var( $wpdb->prepare( 'SELECT doc FROM ' . table() . ' WHERE post_id = %d', $post_id ) );
    if ( null !== $doc ) {
        return (string) $doc;
    }
    return migration_done() ? '' : (string) get_post_meta( $post_id, META_SIG, true );
}

/** A signature an older version stored in post meta (read only until the migration has finished). */
function legacy_record( int $post_id ): ?array {
    $sha = (string) get_post_meta( $post_id, META_SHA, true );
    if ( '' === $sha ) {
        return null;
    }
    return array( 'kid' => (string) get_post_meta( $post_id, META_KID, true ), 'sha512' => $sha, 'at' => (int) get_post_meta( $post_id, META_AT, true ) );
}

/**
 * SQL pieces for queries over posts aliased "p": a join, and expressions for the stored hash and key ID (NULL when the
 * post has no signature). Until the migration has finished they also look at the old post meta.
 * Returns array( join, sha_expr, kid_expr ). Everything in them is a constant: no user input.
 */
function signature_sql(): array {
    global $wpdb;
    $join = 'LEFT JOIN ' . table() . ' t ON t.post_id = p.ID';
    if ( migration_done() ) {
        return array( $join, 't.sha512', 't.kid' );
    }
    $join .= " LEFT JOIN {$wpdb->postmeta} lh ON lh.post_id = p.ID AND lh.meta_key = '" . META_SHA . "'"
        . " LEFT JOIN {$wpdb->postmeta} lk ON lk.post_id = p.ID AND lk.meta_key = '" . META_KID . "'";
    return array( $join, 'IF(t.post_id IS NULL, lh.meta_value, t.sha512)', 'IF(t.post_id IS NULL, lk.meta_value, t.kid)' );
}

// ---------------------------------------------------------------------------------------------------------------
// Writes
// ---------------------------------------------------------------------------------------------------------------

/** Store (or replace) a post's signature. Only store_article_signature() calls this, after full verification. */
function put_signature( int $post_id, string $kid, string $sha512, int $signed_at, string $doc ): bool {
    global $wpdb;
    $ok = $wpdb->query( $wpdb->prepare(
        'INSERT INTO ' . table() . ' (post_id, kid, sha512, signed_at, doc) VALUES (%d, %s, %s, %d, %s)
         ON DUPLICATE KEY UPDATE kid = VALUES(kid), sha512 = VALUES(sha512), signed_at = VALUES(signed_at), doc = VALUES(doc)',
        $post_id, $kid, $sha512, $signed_at, $doc
    ) );
    wp_cache_delete( $post_id, CACHE_GROUP );
    if ( false === $ok ) {
        return false;
    }
    if ( ! migration_done() ) {
        delete_legacy_meta( $post_id ); // the table row wins from now on; nothing old is left to fall back to
    }
    return true;
}

function delete_signature( int $post_id ): void {
    global $wpdb;
    $wpdb->delete( table(), array( 'post_id' => $post_id ), array( '%d' ) );
    wp_cache_delete( $post_id, CACHE_GROUP );
}
// The old meta went with the post; the row does too.
add_action( 'deleted_post', static fn( $post_id ) => delete_signature( (int) $post_id ) );

/**
 * Remove a post's old signature meta (not the stale marker). Direct SQL on purpose: the bytes served do not change
 * (the same document now comes from the table), so the meta hooks' cache purges would be wasted work.
 */
function delete_legacy_meta( int $post_id ): void {
    global $wpdb;
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key IN (%s, %s, %s, %s)", array_merge( array( $post_id ), LEGACY_META ) ) );
    wp_cache_delete( $post_id, 'post_meta' );
}

// ---------------------------------------------------------------------------------------------------------------
// Migration from post meta
// ---------------------------------------------------------------------------------------------------------------

function schedule_migration( int $delay = 60 ): void {
    if ( ! wp_next_scheduled( MIGRATE_EVENT ) ) {
        wp_schedule_single_event( time() + $delay, MIGRATE_EVENT );
    }
}

/**
 * Move one batch of old signatures (post ID order) into the table. Each post's old meta is deleted only after its
 * row has been read back and matches. A row the signer stored meanwhile is newer and is kept.
 * Returns array( moved, kept, failed (ids), done ).
 */
function migrate_batch( int $limit = MIGRATE_BATCH, int $after = 0 ): array {
    global $wpdb;
    $out = array( 'moved' => 0, 'kept' => 0, 'failed' => array(), 'last' => $after, 'done' => false );
    $ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare(
        "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key IN (%s, %s, %s, %s) AND post_id > %d ORDER BY post_id ASC LIMIT %d",
        array_merge( LEGACY_META, array( $after, $limit ) )
    ) ) );
    if ( ! $ids ) {
        $out['done'] = true;
        return $out;
    }
    $legacy = array();
    $rows   = $wpdb->get_results( $wpdb->prepare(
        "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_key IN (%s, %s, %s, %s) AND post_id IN (" . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ') ORDER BY meta_id ASC',
        array_merge( LEGACY_META, $ids )
    ) );
    foreach ( (array) $rows as $r ) {
        $legacy[ (int) $r->post_id ][ $r->meta_key ] ??= (string) $r->meta_value; // the first value, as get_post_meta( ..., true )
    }
    foreach ( $ids as $id ) {
        $out['last'] = $id;
        $old         = $legacy[ $id ] ?? array();
        $want        = array(
            'kid'       => (string) ( $old[ META_KID ] ?? '' ),
            'sha512'    => (string) ( $old[ META_SHA ] ?? '' ),
            'signed_at' => (int) ( $old[ META_AT ] ?? 0 ),
            'doc'       => (string) ( $old[ META_SIG ] ?? '' ),
        );
        if ( '' === $want['sha512'] ) {
            // Without a hash an old signature never counted as one (the article showed as unsigned and was queued),
            // so there is nothing to carry over.
            delete_legacy_meta( $id );
            $out['kept']++;
            continue;
        }
        $wpdb->query( $wpdb->prepare(
            'INSERT IGNORE INTO ' . table() . ' (post_id, kid, sha512, signed_at, doc) VALUES (%d, %s, %s, %d, %s)',
            $id, $want['kid'], $want['sha512'], $want['signed_at'], $want['doc']
        ) );
        wp_cache_delete( $id, CACHE_GROUP );
        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT kid, sha512, signed_at, doc FROM ' . table() . ' WHERE post_id = %d', $id ), ARRAY_A );
        if ( ! is_array( $row ) ) {
            $out['failed'][] = $id; // the old meta stays and is still read; the next run tries again
            continue;
        }
        $same = hash_equals( $want['doc'], (string) $row['doc'] ) && hash_equals( $want['sha512'], (string) $row['sha512'] )
            && hash_equals( $want['kid'], (string) $row['kid'] ) && $want['signed_at'] === (int) $row['signed_at'];
        // Not the same: the signer stored a newer signature after this batch read the old meta. Either way the row
        // is complete and is what readers get, so the old meta can go.
        delete_legacy_meta( $id );
        $same ? $out['moved']++ : $out['kept']++;
    }
    return $out;
}

/**
 * Run batches until done or out of time, under its own lock (the signer keeps working meanwhile).
 * Returns the totals, or null when another migration run holds the lock or the table is missing.
 */
function run_migration( float $budget = 10.0 ): ?array {
    if ( migration_done() ) {
        return array( 'moved' => 0, 'kept' => 0, 'failed' => array(), 'done' => true );
    }
    if ( ! table_exists() ) {
        return null;
    }
    $token = acquire_lock( (int) ceil( $budget ) + 60, MIGRATE_LOCK );
    if ( '' === $token ) {
        return null;
    }
    $start = microtime( true );
    $total = array( 'moved' => 0, 'kept' => 0, 'failed' => array(), 'done' => false );
    $after = 0;
    try {
        while ( microtime( true ) - $start < $budget ) {
            $batch           = migrate_batch( MIGRATE_BATCH, $after );
            $after           = $batch['last'];
            $total['moved'] += $batch['moved'];
            $total['kept']  += $batch['kept'];
            $total['failed'] = array_merge( $total['failed'], $batch['failed'] );
            if ( $batch['done'] ) {
                break;
            }
        }
        // Finished only when a fresh look finds nothing left (failures and rows written meanwhile included).
        if ( ! legacy_rows_exist() ) {
            set_migration_state( true );
            $total['done'] = true;
        }
    } finally {
        release_lock( $token, MIGRATE_LOCK );
    }
    return $total;
}

add_action( MIGRATE_EVENT, function () {
    $result = run_migration();
    if ( null === $result || ! $result['done'] ) {
        schedule_migration();
    }
} );

// Hosts with a broken WP-Cron: an administrator's visit to wp-admin keeps it going (one cheap check while pending).
add_action( 'admin_init', function () {
    if ( ! migration_done() && (int) get_option( STORE_DB_OPTION ) === STORE_DB_VERSION ) {
        schedule_migration( 5 );
    }
} );

// Old signature meta can come back (a database restored from an older backup, or meta imported from another site):
// the daily stale sweep notices and runs the migration again.
add_action( 'hr_pqsig_sweep', function () {
    if ( migration_done() && legacy_rows_exist() ) {
        set_migration_state( false );
        schedule_migration( 5 );
    }
}, 5 );
