<?php
/**
 * Streaks: a Duolingo-style daily reading habit. Read tracking, streaks, freezes, XP, badges, the /dashboard/ page
 * (with the first sign-in tutorial, see tour.php), the floating streak widget on every page (widget.php) and opt-in
 * reminder emails.
 *
 * How a read counts: single posts print a sentinel after the article body (hr_single_after_content). streak.js
 * (same cached file for everyone) does nothing for guests; for signed-in readers it starts a read
 * (POST hr/v1/reads/start), counts visible time only, waits until the sentinel has been seen, then completes it
 * (POST hr/v1/reads/complete; see rest.php). Each article counts once per reader, ever.
 *
 * Contracts:
 *   Filter 'hr_streak_min_seconds' ( int $seconds, int $post_id ): local copies only (WP_ENVIRONMENT_TYPE local).
 *   Browser: document 'hr:streak' event (detail = streak view) after each counted read; the account chip and the
 *   floating widget listen. window.HR.streakWidget = { visible(), notify( text, short ), celebrate() } (widget).
 *   Streak view shape: { current, best, today, goal, met_today, at_risk, freezes, xp, level, next_level_xp },
 *   also added to the hr_session payload as 'streak', plus 'streak_ui' = { max_freezes, tour } (widget.php).
 *   Helpers (HR\Platform\Streaks): summary( $uid ): array (view), refresh( $uid ), erase( $uid ), dashboard_url(),
 *   dashboard_page(): renders /dashboard/ (called by the theme's page-dashboard.php; see dashboard.php).
 *   Filter 'hr_login_default_redirect' (accounts): a reader's first sign-in lands on /dashboard/ (tour.php).
 *   Cron: 'hr_streak_reminders' daily at 19:00 site time (monitored as 'streak-reminders'); see reminders.php.
 *   Route: /reading/unsubscribe/?u=<id>&t=<token> (reminder unsubscribe; GET confirms, POST unsubscribes).
 * Storage: table {prefix}hr_reads; user meta listed in META_KEYS (includes the old theme dashboard's keys).
 */

namespace HR\Platform\Streaks;

if ( ! defined( 'ABSPATH' ) ) exit;

const DB_VERSION = 1;
const META_KEYS  = array(
    '_hr_streak_count', '_hr_longest_streak', '_hr_total_articles_read', '_hr_streak_freezes', '_hr_today_read_posts',
    '_hr_earned_badges', '_hr_badge_dates', '_hr_last_read_date', '_hr_reading_history', '_hr_last_goal_date',
    '_hr_daily_goal', '_hr_goal_changed_on', '_hr_xp', '_hr_opeds_read', '_hr_freeze_used', '_hr_streak_reminders',
    '_hr_reminder_sent', '_hr_streak_v', '_hr_streak_tour',
);

foreach ( array( 'engine', 'store', 'rest', 'dashboard', 'reminders', 'tour', 'widget' ) as $hr_streaks_file ) {
    require_once __DIR__ . "/{$hr_streaks_file}.php";
}
unset( $hr_streaks_file );

// Versioned separately from the core so deploying this module alone still creates the table.
add_action( 'hr_install', __NAMESPACE__ . '\\install' );
add_action( 'init', function () {
    if ( (int) get_option( 'hr_streaks_db_version' ) !== DB_VERSION ) {
        install();
    }
}, 98 );

function dashboard_url(): string {
    return home_url( '/dashboard/' );
}

// ---------------------------------------------------------------------------------------------------------------
// Article pages: sentinel + tracker
// ---------------------------------------------------------------------------------------------------------------

add_action( 'hr_single_after_content', function ( $post_id = 0 ) {
    static $done = false;
    $post = get_post( (int) $post_id );
    if ( $done || ! $post || 'post' !== $post->post_type || 'publish' !== $post->post_status ) {
        return;
    }
    $done = true;
    printf(
        '<div class="hr-read-sentinel" data-hr-post="%d" data-hr-words="%d" data-hr-rest="%s" data-hr-css="%s" data-hr-dashboard="%s" aria-hidden="true"></div>',
        (int) $post->ID,
        (int) word_count( $post ),
        esc_url( rest_url( 'hr/v1/reads/' ) ),
        esc_url( \HR\Platform\asset_url( 'css/streaks.css' ) . '?ver=' . \HR\Platform\asset_ver( 'css/streaks.css' ) ),
        esc_url( dashboard_url() )
    );
} );

add_action( 'wp_enqueue_scripts', function () {
    if ( is_singular( 'post' ) ) {
        \HR\Platform\enqueue_base_style();
        wp_enqueue_script( 'hr-streak', \HR\Platform\asset_url( 'js/streak.js' ), array(), \HR\Platform\asset_ver( 'js/streak.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
    }
} );

// ---------------------------------------------------------------------------------------------------------------
// Accounts integration
// ---------------------------------------------------------------------------------------------------------------

add_filter( 'hr_session_payload', function ( array $payload, \WP_User $user ) {
    $payload['streak'] = summary( (int) $user->ID );
    return $payload;
}, 10, 2 );

add_filter( 'hr_account_pages', function ( array $pages ) {
    $pages['reading'] = array( 'title' => 'Reading streak', 'icon' => 'flame', 'order' => 20, 'url' => dashboard_url() );
    return $pages;
} );

add_filter( 'hr_account_overview_cards', function ( array $cards, \WP_User $user ) {
    $v = summary( (int) $user->ID );
    if ( $v['met_today'] ) {
        $line = "Today's goal is met. Come back tomorrow.";
    } elseif ( $v['at_risk'] ) {
        $line = sprintf( 'Read %d more today to keep it going.', $v['goal'] - min( $v['today'], $v['goal'] ) );
    } else {
        $line = sprintf( '%d of %d articles today. Best: %d days.', min( $v['today'], $v['goal'] ), $v['goal'], $v['best'] );
    }
    $cards[] = array(
        'title'     => 'Reading streak',
        'value'     => sprintf( '%d-day streak', $v['current'] ),
        'body_html' => '<p>' . esc_html( $line ) . '</p>',
        'link'      => array( 'label' => 'Open your streak', 'url' => dashboard_url() ),
        'icon'      => 'flame',
        'order'     => 10,
    );
    return $cards;
}, 10, 2 );

/** Reads with titles, newest first. */
function reads( int $uid, int $limit, int $offset = 0 ): array {
    global $wpdb;
    $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, post_id, read_on, kind FROM ' . table() . ' WHERE user_id = %d ORDER BY read_on DESC, id DESC LIMIT %d OFFSET %d', $uid, $limit, $offset ), ARRAY_A );
    _prime_post_caches( array_map( 'intval', array_column( $rows, 'post_id' ) ), false, false );
    foreach ( $rows as &$row ) {
        $row['title'] = html_entity_decode( get_the_title( (int) $row['post_id'] ), ENT_QUOTES, 'UTF-8' );
    }
    return $rows;
}

add_filter( 'hr_account_export', function ( array $data, int $uid ) {
    $data['reading'] = array(
        'streak'    => summary( $uid ),
        'goal'      => goal( $uid ),
        'badges'    => state( $uid )['badges'],
        'reminders' => '1' === get_user_meta( $uid, '_hr_streak_reminders', true ),
        'reads'     => array_map( static fn( $r ) => array( 'post_id' => (int) $r['post_id'], 'title' => $r['title'], 'read_on' => $r['read_on'], 'kind' => $r['kind'] ), reads( $uid, 100000 ) ),
    );
    return $data;
}, 10, 2 );

add_action( 'hr_account_deleted', __NAMESPACE__ . '\\erase' );

// WordPress privacy tools (Tools → Export / Erase Personal Data).
add_filter( 'wp_privacy_personal_data_exporters', function ( array $exporters ) {
    $exporters['hr-reading'] = array( 'exporter_friendly_name' => 'Reading streak', 'callback' => __NAMESPACE__ . '\\privacy_export' );
    return $exporters;
} );

add_filter( 'wp_privacy_personal_data_erasers', function ( array $erasers ) {
    $erasers['hr-reading'] = array( 'eraser_friendly_name' => 'Reading streak', 'callback' => __NAMESPACE__ . '\\privacy_erase' );
    return $erasers;
} );

function privacy_export( string $email, int $page = 1 ): array {
    $user  = get_user_by( 'email', $email );
    $items = array();
    if ( ! $user ) {
        return array( 'data' => array(), 'done' => true );
    }
    $per = 500;
    if ( 1 === $page ) {
        $v       = summary( (int) $user->ID );
        $items[] = array(
            'group_id'    => 'hr-reading',
            'group_label' => 'Reading streak',
            'item_id'     => 'hr-streak',
            'data'        => array(
                array( 'name' => 'Current streak', 'value' => $v['current'] ),
                array( 'name' => 'Best streak', 'value' => $v['best'] ),
                array( 'name' => 'Daily goal', 'value' => $v['goal'] ),
                array( 'name' => 'XP', 'value' => $v['xp'] ),
                array( 'name' => 'Badges', 'value' => implode( ', ', array_keys( state( (int) $user->ID )['badges'] ) ) ),
            ),
        );
    }
    $rows = reads( (int) $user->ID, $per, ( $page - 1 ) * $per );
    foreach ( $rows as $r ) {
        $items[] = array(
            'group_id'    => 'hr-reads',
            'group_label' => 'Articles read',
            'item_id'     => 'hr-read-' . (int) $r['id'],
            'data'        => array(
                array( 'name' => 'Article', 'value' => $r['title'] ),
                array( 'name' => 'Read on', 'value' => $r['read_on'] ),
                array( 'name' => 'Type', 'value' => $r['kind'] ),
            ),
        );
    }
    return array( 'data' => $items, 'done' => count( $rows ) < $per );
}

function privacy_erase( string $email, int $page = 1 ): array {
    $user = get_user_by( 'email', $email );
    if ( $user ) {
        erase( (int) $user->ID );
    }
    return array( 'items_removed' => (bool) $user, 'items_retained' => false, 'messages' => array(), 'done' => true );
}
