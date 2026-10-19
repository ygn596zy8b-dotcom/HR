<?php
/**
 * Streaks: the reads table, per-user state in user meta (the keys the old theme dashboard used, kept up to date
 * for compatibility) and the one write path, refresh().
 */

namespace HR\Platform\Streaks;

if ( ! defined( 'ABSPATH' ) ) exit;

function table(): string {
    global $wpdb;
    return $wpdb->prefix . 'hr_reads';
}

function install(): void {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( 'CREATE TABLE ' . table() . " (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        post_id bigint(20) unsigned NOT NULL,
        read_on date NOT NULL,
        kind varchar(10) NOT NULL DEFAULT 'article',
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY user_post (user_id,post_id),
        KEY user_day (user_id,read_on)
    ) " . $wpdb->get_charset_collate() . ';' );
    update_option( 'hr_streaks_db_version', DB_VERSION, true );
}

function today(): string {
    return local_date( time(), wp_timezone() );
}

function kind_of( \WP_Post $post ): string {
    $guest = taxonomy_exists( 'guest_author' ) && has_term( '', 'guest_author', $post );
    return $guest || has_category( 'opinion', $post ) ? 'oped' : 'article';
}

function goal( int $uid ): int {
    $goal = (int) get_user_meta( $uid, '_hr_daily_goal', true );
    return isset( GOALS[ $goal ] ) ? $goal : DEFAULT_GOAL;
}

/** INSERT IGNORE: an article counts once per reader, ever. Returns true when this call counted it. */
function insert_read( int $uid, int $post_id, string $date, string $kind, string $created_utc ): bool {
    global $wpdb;
    $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO ' . table() . ' (user_id, post_id, read_on, kind, created_at) VALUES (%d, %d, %s, %s, %s)', $uid, $post_id, $date, $kind, $created_utc ) );
    return 1 === (int) $wpdb->rows_affected;
}

function has_read( int $uid, int $post_id ): bool {
    global $wpdb;
    return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM ' . table() . ' WHERE user_id = %d AND post_id = %d', $uid, $post_id ) );
}

function counts( int $uid, string $today ): array {
    global $wpdb;
    $row = $wpdb->get_row( $wpdb->prepare( 'SELECT COUNT(*) AS total, COALESCE(SUM(kind = %s), 0) AS opeds, COALESCE(SUM(read_on = %s), 0) AS today, MAX(read_on) AS last FROM ' . table() . ' WHERE user_id = %d', 'oped', $today, $uid ), ARRAY_A );
    return array( 'total' => (int) $row['total'], 'opeds' => (int) $row['opeds'], 'today' => (int) $row['today'], 'last' => (string) $row['last'] );
}

/** date => count for the last $days days. */
function history( int $uid, int $days ): array {
    global $wpdb;
    $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT read_on, COUNT(*) AS n FROM ' . table() . ' WHERE user_id = %d AND read_on >= %s GROUP BY read_on ORDER BY read_on', $uid, shift_date( today(), 1 - $days ) ), ARRAY_A );
    return array_map( 'intval', array_column( $rows, 'n', 'read_on' ) );
}

function state( int $uid ): array {
    if ( '' === get_user_meta( $uid, '_hr_streak_v', true ) ) {
        import( $uid );
    }
    $badges = get_user_meta( $uid, '_hr_badge_dates', true );
    $used   = get_user_meta( $uid, '_hr_freeze_used', true );
    return array(
        'current'        => (int) get_user_meta( $uid, '_hr_streak_count', true ),
        'best'           => (int) get_user_meta( $uid, '_hr_longest_streak', true ),
        'freezes'        => min( MAX_FREEZES, (int) get_user_meta( $uid, '_hr_streak_freezes', true ) ),
        'last_goal_date' => (string) get_user_meta( $uid, '_hr_last_goal_date', true ),
        'freeze_used'    => is_array( $used ) ? $used : array(),
        'xp'             => (int) get_user_meta( $uid, '_hr_xp', true ),
        'badges'         => is_array( $badges ) ? $badges : array(),
    );
}

/** First visit of a reader the old dashboard tracked: move their reads into the table and keep their streak. */
function import( int $uid ): void {
    $meta = array();
    foreach ( array( '_hr_streak_count', '_hr_longest_streak', '_hr_streak_freezes', '_hr_xp', '_hr_last_read_date', '_hr_last_goal_date', '_hr_earned_badges', '_hr_today_read_posts', '_hr_reading_history' ) as $key ) {
        $meta[ $key ] = get_user_meta( $uid, $key, true );
    }
    // Only the legacy theme wrote these; a reader without any has nothing to import.
    if ( array_filter( $meta ) ) {
        [ $s, $reads ] = import_legacy( $meta, today() );
        foreach ( $reads as [ $post_id, $date ] ) {
            $post = get_post( $post_id );
            if ( $post && 'post' === $post->post_type ) {
                insert_read( $uid, $post_id, $date, kind_of( $post ), $date . ' 00:00:00' );
            }
        }
        $c = counts( $uid, today() );
        if ( ! $s['xp'] ) {
            $s['xp'] = ( $c['total'] - $c['opeds'] ) * XP_ARTICLE + $c['opeds'] * XP_OPED;
        }
        // A count of 0 leaves the streak alone and only awards the badges the imported history already earns.
        [ $s ] = apply( $s, today(), 0, DEFAULT_GOAL, $c );
        save( $uid, $s );
    }
    update_user_meta( $uid, '_hr_streak_v', 1 );
}

/** Writes the state plus the derived keys the old dashboard (the theme fallback) reads. */
function save( int $uid, array $s ): void {
    global $wpdb;
    $today = today();
    $c     = counts( $uid, $today );
    $ids   = $wpdb->get_col( $wpdb->prepare( 'SELECT post_id FROM ' . table() . ' WHERE user_id = %d AND read_on = %s ORDER BY id', $uid, $today ) );
    $meta  = array(
        '_hr_streak_count'        => (int) $s['current'],
        '_hr_longest_streak'      => (int) $s['best'],
        '_hr_streak_freezes'      => (int) $s['freezes'],
        '_hr_last_goal_date'      => (string) $s['last_goal_date'],
        '_hr_freeze_used'         => array_values( (array) $s['freeze_used'] ),
        '_hr_xp'                  => (int) $s['xp'],
        '_hr_badge_dates'         => (array) $s['badges'],
        '_hr_earned_badges'       => array_keys( (array) $s['badges'] ),
        '_hr_total_articles_read' => $c['total'],
        '_hr_opeds_read'          => $c['opeds'],
        '_hr_today_read_posts'    => array_map( 'intval', $ids ),
        '_hr_last_read_date'      => $c['last'],
        '_hr_reading_history'     => history( $uid, 120 ),
    );
    foreach ( $meta as $key => $value ) {
        update_user_meta( $uid, $key, $value );
    }
}

/**
 * Recompute after a counted read ($read = ['kind' => …, 'hour' => …]) or a goal change. Serialised per reader
 * with a MySQL named lock; counts always come from the table, so concurrent reads cannot double-apply.
 * Returns array( view, events ).
 */
function refresh( int $uid, array $read = array() ): array {
    global $wpdb;
    $lock = $wpdb->prefix . 'hr_streak_' . $uid;
    $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) );
    try {
        $today          = today();
        $goal           = goal( $uid );
        $c              = counts( $uid, $today );
        [ $s, $events ] = apply( state( $uid ), $today, $c['today'], $goal, $read + array( 'total' => $c['total'], 'opeds' => $c['opeds'] ) );
        save( $uid, $s );
    } finally {
        $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
    }
    return array( view( $s, $today, $c['today'], $goal ), $events );
}

/** Read-only summary (session payload, dashboard, reminders). */
function summary( int $uid ): array {
    $today = today();
    $s     = state( $uid );
    $c     = counts( $uid, $today );
    // _hr_today_read_posts means "today": empty it once the day has turned (the old dashboard reads it).
    if ( $c['last'] !== $today && get_user_meta( $uid, '_hr_today_read_posts', true ) ) {
        update_user_meta( $uid, '_hr_today_read_posts', array() );
    }
    return view( $s, $today, $c['today'], goal( $uid ) );
}

/** Daily goal; readers may change it once per calendar day. */
function set_goal( int $uid, int $goal ): bool {
    if ( ! isset( GOALS[ $goal ] ) || get_user_meta( $uid, '_hr_goal_changed_on', true ) === today() ) {
        return false;
    }
    if ( goal( $uid ) !== $goal ) {
        update_user_meta( $uid, '_hr_daily_goal', $goal );
        update_user_meta( $uid, '_hr_goal_changed_on', today() );
        refresh( $uid );
    }
    return true;
}

/** Everything this module stores about a reader goes. */
function erase( int $uid ): void {
    global $wpdb;
    $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . table() . ' WHERE user_id = %d', $uid ) );
    foreach ( META_KEYS as $key ) {
        delete_user_meta( $uid, $key );
    }
}
