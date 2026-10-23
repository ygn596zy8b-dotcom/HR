<?php
/**
 * Automatic server-side signing.
 *
 *   On publish or edit: wp_after_insert_post signs the article in the same request (a few hundred milliseconds),
 *   within a per-request time budget; anything over budget, or a failure, is retried by WP-Cron a minute later.
 *   Every 5 minutes: a WP-Cron sweep signs every pending or stale article, newest first, for up to about 20 seconds
 *   per run under an expiring lock, then re-signs the site and podcast feed snapshots when their bytes changed.
 *   Backlog: the "Sign all pending now" button (admin.php) and `wp hr sign` (cli.php) run the same batches.
 *
 * Every signature goes through store_article_signature() / store_feed_snapshot() (rest.php), exactly like an upload
 * from the offline signer, and is verified with the PHP verifier before it is stored.
 */

namespace HR\Platform\Signing;

use function HR\Platform\log_security;
use function HR\Platform\setting;

if ( ! defined( 'ABSPATH' ) ) exit;

const SWEEP_EVENT     = 'hr_pqsig_autosign';
const RETRY_EVENT     = 'hr_pqsig_autosign_retry';
const SWEEP_SCHEDULE  = 'hr_every_five_minutes';
const SWEEP_CRONTAB   = '*/5 * * * *';
const SWEEP_BUDGET    = 20.0;  // seconds of signing per cron run
const SAVE_BUDGET     = 3.0;   // seconds of signing an editor's request may spend in total
const LOCK_OPTION     = 'hr_pqsig_autosign_lock';
const STATUS_OPTION   = 'hr_pqsig_autosign_status';
const ERRORS_OPTION   = 'hr_pqsig_autosign_errors';
const FEEDS_DIRTY     = 'hr_pqsig_feeds_dirty';
const FEEDS_CHECKED   = 'hr_pqsig_feeds_checked';
const FEEDS_RECHECK   = 30 * MINUTE_IN_SECONDS;

add_filter( 'cron_schedules', function ( $schedules ) {
    if ( ! isset( $schedules[ SWEEP_SCHEDULE ] ) ) {
        $schedules[ SWEEP_SCHEDULE ] = array( 'interval' => 5 * MINUTE_IN_SECONDS, 'display' => 'Every five minutes' );
    }
    return $schedules;
} );

function schedule_autosign(): void {
    if ( ! wp_next_scheduled( SWEEP_EVENT ) ) {
        wp_schedule_event( time() + MINUTE_IN_SECONDS, SWEEP_SCHEDULE, SWEEP_EVENT );
    }
}
add_action( 'hr_install', __NAMESPACE__ . '\\schedule_autosign' );
add_action( 'admin_init', __NAMESPACE__ . '\\schedule_autosign' );

/** A cron run soon (about a minute), for retries and feed changes. Duplicates within ten minutes are ignored by WP. */
function schedule_retry(): void {
    if ( ! wp_next_scheduled( RETRY_EVENT ) ) {
        wp_schedule_single_event( time() + MINUTE_IN_SECONDS, RETRY_EVENT );
    }
}

/** The owner's switch (Settings > Headline Recorder > Content signing), on by default. */
function autosign_enabled(): bool {
    return ! empty( setting( 'pq_autosign' ) );
}

// ---------------------------------------------------------------------------------------------------------------
// One article
// ---------------------------------------------------------------------------------------------------------------

/**
 * Sign one post if it needs it. Returns 'stored', 'current' (already validly signed), 'not_found' (not public),
 * 'no_key' (no usable key; see server_key()) or another store_article_signature() status on failure.
 */
function sign_post( int $id ): string {
    $post = signable_post( $id );
    if ( ! $post ) {
        return 'not_found';
    }
    $stored = (string) get_post_meta( $id, META_SHA, true );
    if ( '' !== $stored && hash_equals( $stored, hash( 'sha512', canonical_bytes( $post ) ) ) && kid_is_valid( (string) get_post_meta( $id, META_KID, true ) ) ) {
        if ( '' !== (string) get_post_meta( $id, META_STALE, true ) ) {
            delete_post_meta( $id, META_STALE ); // a false alarm
        }
        return 'current';
    }
    $key = server_key();
    if ( is_wp_error( $key ) ) {
        return 'no_key';
    }
    try {
        $doc = make_signature( canonical_bytes( $post ), $key, 'article', post_path( $post ), true );
    } catch ( \Throwable $e ) {
        return 'sign_failed';
    }
    return store_article_signature( $id, $doc );
}

/** Plain-language reason for a sign_post() status. */
function status_message( string $status ): string {
    $key = server_key();
    $map = array(
        'no_key'        => is_wp_error( $key ) ? $key->get_error_message() : 'No usable signing key.',
        'sign_failed'   => 'The signer stopped with an internal error.',
        'stale'         => 'The article changed while it was being signed; it will be signed again shortly.',
        'bad_signature' => 'The new signature did not verify, so it was not stored.',
        'bad_key'       => 'The signing key is not in the published keyset (or was revoked).',
        'invalid'       => 'The signature was malformed, so it was not stored.',
        'not_found'     => 'The article is not public.',
    );
    return $map[ $status ] ?? 'Unknown problem (' . $status . ').';
}

function remember_error( int $id, string $status ): void {
    $errors = get_option( ERRORS_OPTION );
    $errors = is_array( $errors ) ? $errors : array();
    unset( $errors[ $id ] );
    $errors[ $id ] = array( 'status' => $status, 'message' => status_message( $status ), 'at' => time() );
    update_option( ERRORS_OPTION, array_slice( $errors, -20, null, true ), false );
}

function forget_error( int $id ): void {
    $errors = get_option( ERRORS_OPTION );
    if ( is_array( $errors ) && isset( $errors[ $id ] ) ) {
        unset( $errors[ $id ] );
        update_option( ERRORS_OPTION, $errors, false );
    }
}

// ---------------------------------------------------------------------------------------------------------------
// On save: sign in the same request
// ---------------------------------------------------------------------------------------------------------------

/** Seconds of signing this request has spent, and posts left for the end of the request. */
function save_budget_spent( float $add = 0.0 ): float {
    static $spent = 0.0;
    return $spent += $add;
}

function autosign_now( int $id ): void {
    if ( ! autosign_enabled() || ! pq_ready() ) {
        return;
    }
    // Drafts, auto-drafts and private posts are never signed: return before the key is decrypted and checked, so
    // "Add New", autosaves of drafts and submission drafts cost nothing.
    if ( ! signable_post( $id ) ) {
        forget_error( $id );
        return;
    }
    // Without a usable key nothing can be signed; the status screens report that once, not per article.
    if ( is_wp_error( server_key() ) ) {
        return;
    }
    // Imports and over-budget bulk edits are left to the sweep instead of slowing the request down.
    if ( ( defined( 'WP_IMPORTING' ) && WP_IMPORTING ) || save_budget_spent() >= SAVE_BUDGET ) {
        schedule_retry();
        return;
    }
    $start  = microtime( true );
    $status = sign_post( $id );
    save_budget_spent( microtime( true ) - $start );
    if ( 'stored' === $status || 'current' === $status || 'not_found' === $status ) {
        forget_error( $id );
        return;
    }
    remember_error( $id, $status );
    schedule_retry();
}

add_action( 'wp_after_insert_post', function ( $post_id, $post ) {
    if ( $post instanceof \WP_Post && 'post' === $post->post_type ) {
        autosign_now( (int) $post_id );
    }
}, 30, 2 );

/** Changes that land after the post itself (the op-ed byline meta) are signed at the end of the request. */
function autosign_at_shutdown( int $id ): void {
    static $ids = array();
    if ( ! $ids ) {
        add_action( 'shutdown', static function () use ( &$ids ) {
            foreach ( array_keys( $ids ) as $queued ) {
                autosign_now( (int) $queued );
            }
            $ids = array();
        }, 5 );
    }
    $ids[ $id ] = true;
}

$hr_pqsig_byline_changed = function ( $meta_id, $object_id, $meta_key ) {
    if ( 'guest_author_name' === $meta_key && 'post' === get_post_type( (int) $object_id ) ) {
        autosign_at_shutdown( (int) $object_id );
    }
};
add_action( 'added_post_meta', $hr_pqsig_byline_changed, 30, 3 );
add_action( 'updated_post_meta', $hr_pqsig_byline_changed, 30, 3 );
add_action( 'deleted_post_meta', $hr_pqsig_byline_changed, 30, 3 );
unset( $hr_pqsig_byline_changed );

// Feeds change whenever an article or episode is published, edited or withdrawn (transition_post_status fires on
// every save, publish to publish included): re-check them a minute later.
function mark_feeds_dirty(): void {
    update_option( FEEDS_DIRTY, time(), false );
    if ( autosign_enabled() ) {
        schedule_retry();
    }
}
add_action( 'transition_post_status', function ( $new_status, $old_status, $post ) {
    if ( $post instanceof \WP_Post && in_array( $post->post_type, array( 'post', 'hr_episode' ), true ) && ( 'publish' === $new_status || 'publish' === $old_status ) ) {
        mark_feeds_dirty();
    }
}, 30, 3 );

// ---------------------------------------------------------------------------------------------------------------
// Batches: shared by the cron sweep, the backlog button and WP-CLI
// ---------------------------------------------------------------------------------------------------------------

/**
 * Sign pending articles newest first until the time budget or the limit runs out.
 * $cursor ( array( post_date_gmt, ID ) or null ) continues after the last post a previous batch looked at, so failures
 * are skipped rather than retried forever within one pass. Returns counts, failures and the next cursor.
 */
function sign_pending( float $budget, int $limit = 0, ?array $cursor = null ): array {
    $start  = microtime( true );
    $out    = array( 'signed' => 0, 'current' => 0, 'failed' => array(), 'cursor' => $cursor, 'done' => false, 'stopped' => '' );
    $key    = server_key();
    if ( is_wp_error( $key ) ) {
        $out['stopped'] = $key->get_error_message();
        return $out;
    }
    while ( true ) {
        $ids = queue_candidate_ids( 20, 0, false, $out['cursor'] );
        if ( ! $ids ) {
            $out['done'] = true;
            break;
        }
        _prime_post_caches( $ids, false, true );
        foreach ( $ids as $id ) {
            if ( microtime( true ) - $start >= $budget || ( $limit > 0 && $out['signed'] >= $limit ) ) {
                break 2;
            }
            $post          = get_post( $id );
            $out['cursor'] = array( $post ? (string) $post->post_date_gmt : '0000-00-00 00:00:00', (int) $id );
            $status        = sign_post( (int) $id );
            if ( 'stored' === $status ) {
                $out['signed']++;
                forget_error( (int) $id );
            } elseif ( 'current' === $status || 'not_found' === $status ) {
                $out['current']++;
            } else {
                if ( 'no_key' === $status ) {
                    $out['stopped'] = status_message( $status );
                    break 2;
                }
                $out['failed'][] = array( 'id' => (int) $id, 'status' => $status, 'message' => status_message( $status ) );
                remember_error( (int) $id, $status );
            }
        }
    }
    return $out;
}

/** An expiring lock (a row in wp_options, claimed with one atomic INSERT or UPDATE). Returns the token or ''. */
function acquire_lock( int $ttl ): string {
    global $wpdb;
    $now   = time();
    $token = ( $now + $ttl ) . '.' . bin2hex( random_bytes( 6 ) );
    $ok    = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')", LOCK_OPTION, $token ) );
    if ( ! $ok ) {
        $ok = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND CAST(SUBSTRING_INDEX(option_value, '.', 1) AS UNSIGNED) < %d", $token, LOCK_OPTION, $now ) );
    }
    wp_cache_delete( LOCK_OPTION, 'options' );
    return $ok ? $token : '';
}

/** Extend a lock this process holds (no gap in which another run could start). Returns the new token or ''. */
function refresh_lock( string $token, int $ttl ): string {
    global $wpdb;
    $next = ( time() + $ttl ) . '.' . bin2hex( random_bytes( 6 ) );
    $ok   = '' !== $token && $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", $next, LOCK_OPTION, $token ) );
    wp_cache_delete( LOCK_OPTION, 'options' );
    return $ok ? $next : '';
}

function release_lock( string $token ): void {
    global $wpdb;
    if ( '' !== $token ) {
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", LOCK_OPTION, $token ) );
        wp_cache_delete( LOCK_OPTION, 'options' );
    }
}

/** Last run summary for the settings screens. */
function record_run( string $source, array $result, float $seconds ): void {
    $failed = count( $result['failed'] ?? array() );
    $feeds  = (array) ( $result['feeds'] ?? array() );
    $feed_errors = array_filter( $feeds, static fn( $s ) => ! in_array( $s, array( 'stored', 'unchanged', 'skipped' ), true ) );
    $ok     = '' === ( $result['stopped'] ?? '' ) && 0 === $failed && ! $feed_errors;
    $errors = array();
    if ( '' !== ( $result['stopped'] ?? '' ) ) {
        $errors[] = $result['stopped'];
    }
    foreach ( array_slice( $result['failed'] ?? array(), 0, 5 ) as $f ) {
        $errors[] = sprintf( 'Article %d: %s', $f['id'], $f['message'] );
    }
    foreach ( $feed_errors as $feed => $message ) {
        $errors[] = sprintf( 'Feed %s: %s', $feed, $message );
    }
    update_option( STATUS_OPTION, array(
        'at'        => time(),
        'source'    => $source,
        'ok'        => $ok,
        'signed'    => (int) ( $result['signed'] ?? 0 ),
        'failed'    => $failed,
        'feeds'     => $feeds,
        'remaining' => queue_candidate_ids( 0, 0, true ),
        'seconds'   => round( $seconds, 1 ),
        'errors'    => $errors,
    ), false );
}

/** The cron sweep. $source is 'cron' or 'cli'. Returns the result, or null when another run holds the lock. */
function run_sweep( string $source = 'cron', float $budget = SWEEP_BUDGET, int $limit = 0 ): ?array {
    if ( ! pq_ready() ) {
        return null;
    }
    $token = acquire_lock( (int) ceil( $budget ) + 100 );
    if ( '' === $token ) {
        return null;
    }
    if ( function_exists( 'set_time_limit' ) ) {
        @set_time_limit( (int) ceil( $budget ) + 60 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- not allowed on some hosts
    }
    $start = microtime( true );
    try {
        $result          = sign_pending( $budget, $limit );
        $result['feeds'] = '' === $result['stopped'] ? sign_feeds( false ) : array();
    } catch ( \Throwable $e ) {
        error_log( 'hr-platform: signing sweep failed: ' . $e->getMessage() );
        $result = array( 'signed' => 0, 'failed' => array(), 'stopped' => 'The signer stopped with an internal error (see the PHP error log).', 'feeds' => array() );
    } finally {
        release_lock( $token );
    }
    record_run( $source, $result, microtime( true ) - $start );
    if ( $result['signed'] > 0 || $result['failed'] ) {
        log_security( 'pqsig_autosign_run', array( 'source' => $source, 'signed' => $result['signed'], 'failed' => count( $result['failed'] ) ) );
    }
    return $result;
}

function cron_sweep(): void {
    if ( ! autosign_enabled() ) {
        // Switched off on purpose (for example in favour of the offline signer): a quiet check-in, so the
        // monitor does not report missed runs every five minutes.
        do_action( 'hr_monitor_cron', 'pq-autosign', 'ok', SWEEP_CRONTAB );
        return;
    }
    if ( ! pq_ready() ) {
        do_action( 'hr_monitor_cron', 'pq-autosign', 'error', SWEEP_CRONTAB );
        return;
    }
    do_action( 'hr_monitor_cron', 'pq-autosign', 'in_progress', SWEEP_CRONTAB );
    $result = run_sweep( 'cron' );
    if ( null === $result ) {
        do_action( 'hr_monitor_cron', 'pq-autosign', 'ok', SWEEP_CRONTAB ); // another run is busy; that one reports
        return;
    }
    $status = get_option( STATUS_OPTION );
    do_action( 'hr_monitor_cron', 'pq-autosign', is_array( $status ) && ! empty( $status['ok'] ) ? 'ok' : 'error', SWEEP_CRONTAB );
}
add_action( SWEEP_EVENT, __NAMESPACE__ . '\\cron_sweep' );
add_action( RETRY_EVENT, __NAMESPACE__ . '\\cron_sweep' );

// ---------------------------------------------------------------------------------------------------------------
// Feeds
// ---------------------------------------------------------------------------------------------------------------

/** The same check wp-sign.mjs makes before signing a feed: UTF-8 RSS, no DTD, a UTF-8 declaration if any. */
function looks_like_rss( string $xml ): bool {
    if ( str_contains( $xml, "\0" ) || 1 !== preg_match( '//u', $xml ) || 1 === preg_match( '/<!(?:DOCTYPE|ENTITY)/i', $xml ) ) {
        return false;
    }
    if ( 1 === preg_match( '/^\x{FEFF}?\s*<\?xml\b[^>]*?\bencoding=["\']([^"\']+)["\']/u', $xml, $m ) && 'UTF-8' !== strtoupper( $m[1] ) ) {
        return false;
    }
    return 1 === preg_match( '/^\x{FEFF}?\s*(?:<\?[\s\S]*?\?>\s*|<!--[\s\S]*?-->\s*)*<rss[\s>]/u', $xml );
}

/** Fetch a feed as the public gets it: no cookies, no credentials, redirects not followed. */
function fetch_feed_bytes( string $source ) {
    $home   = wp_parse_url( home_url() );
    $origin = ( $home['scheme'] ?? 'https' ) . '://' . ( $home['host'] ?? '' ) . ( isset( $home['port'] ) ? ':' . $home['port'] : '' );
    $res    = wp_remote_get( $origin . $source, array(
        'timeout'             => 15,
        'redirection'         => 0,
        'limit_response_size' => FEED_MAX_BYTES + 1,
        'user-agent'          => 'HeadlineRecorder-Signer/1; ' . home_url( '/' ),
        'headers'             => array( 'Accept' => 'application/rss+xml, application/xml;q=0.9, */*;q=0.1' ),
    ) );
    if ( is_wp_error( $res ) ) {
        return new \WP_Error( 'hr_pqsig_fetch', 'the site could not fetch its own feed (' . $res->get_error_message() . '). If a firewall or Cloudflare blocks the server\'s own requests, allow them.' );
    }
    $code = (int) wp_remote_retrieve_response_code( $res );
    if ( 200 !== $code ) {
        return new \WP_Error( 'hr_pqsig_fetch', sprintf( 'the feed answered HTTP %d instead of the feed%s.', $code, $code >= 300 && $code < 400 ? ' (a redirect, not followed)' : '' ) );
    }
    $body = (string) wp_remote_retrieve_body( $res );
    if ( '' === $body || strlen( $body ) > FEED_MAX_BYTES ) {
        return new \WP_Error( 'hr_pqsig_fetch', 'the feed was empty or larger than 5 MB.' );
    }
    return $body;
}

/**
 * Re-sign the feed snapshots whose bytes changed. Unless $force, only runs when a post or episode changed since the
 * last check or the last check is over half an hour old. Returns key => 'stored' | 'unchanged' | 'skipped' | message.
 */
function sign_feeds( bool $force ): array {
    $dirty   = (int) get_option( FEEDS_DIRTY, 0 );
    $checked = (int) get_option( FEEDS_CHECKED, 0 );
    $out     = array();
    if ( ! $force && $dirty <= $checked && time() - $checked < FEEDS_RECHECK ) {
        return array_map( static fn() => 'skipped', feed_sources() );
    }
    $key = server_key();
    if ( is_wp_error( $key ) ) {
        return array_map( static fn() => $key->get_error_message(), feed_sources() );
    }
    update_option( FEEDS_CHECKED, time(), false );
    foreach ( feed_sources() as $feed => $source ) {
        $bytes = fetch_feed_bytes( $source );
        if ( is_wp_error( $bytes ) ) {
            $out[ $feed ] = $bytes->get_error_message();
            continue;
        }
        if ( ! looks_like_rss( $bytes ) ) {
            $out[ $feed ] = $source . ' did not return a UTF-8 RSS feed without a DTD, so it was not signed.';
            continue;
        }
        $stored = stored_feed( $feed );
        if ( $stored && hash_equals( (string) $stored['sha512'], hash( 'sha512', $bytes ) ) && kid_is_valid( (string) ( $stored['kid'] ?? '' ) ) ) {
            $out[ $feed ] = 'unchanged';
            continue;
        }
        try {
            $doc    = make_signature( $bytes, $key, 'feed', '/signed/' . $feed . '.xml', false );
            $status = store_feed_snapshot( $feed, $bytes, $doc );
        } catch ( \Throwable $e ) {
            $status = 'sign_failed';
        }
        $out[ $feed ] = 'stored' === $status ? 'stored' : ( FEED_ERRORS[ $status ] ?? 'The signer stopped with an internal error.' );
    }
    if ( array_filter( $out, static fn( $s ) => ! in_array( $s, array( 'stored', 'unchanged' ), true ) ) ) {
        update_option( FEEDS_CHECKED, 0, false ); // try again on the next run
    }
    return $out;
}
