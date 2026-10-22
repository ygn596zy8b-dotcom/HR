<?php
/**
 * Streaks: the /dashboard/ page (WordPress page 5289; the theme's page-dashboard.php calls dashboard_page()).
 * Preferences (daily goal, reminder emails) are plain POST forms back to the page: nonce + same origin, then a
 * redirect with ?saved=<code> so a reload never re-submits. The tutorial's "Got it" / "Don't show again" post the same
 * way (hr_action=tour) from streak-dashboard.js, asking for JSON instead of the redirect.
 */

namespace HR\Platform\Streaks;

use function HR\Platform\no_store;
use function HR\Platform\render;
use function HR\Platform\rate_limit;
use function HR\Platform\same_origin_request;
use function HR\Platform\set_page_title;
use function HR\Platform\asset_url;
use function HR\Platform\asset_ver;
use function HR\Platform\enqueue_base_style;

if ( ! defined( 'ABSPATH' ) ) exit;

const PREFS_NONCE = 'hr_streak_prefs';

function dashboard_page(): void {
    no_store();
    if ( ! is_user_logged_in() ) {
        $login = function_exists( 'HR\Platform\Accounts\login_url' ) ? \HR\Platform\Accounts\login_url( dashboard_url() ) : wp_login_url( dashboard_url() );
        wp_safe_redirect( $login );
        exit;
    }
    $uid = get_current_user_id();
    if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
        $code = save_prefs( $uid );
        if ( str_contains( (string) ( $_SERVER['HTTP_ACCEPT'] ?? '' ), 'application/json' ) ) {
            $ok = ! in_array( $code, array( 'expired', 'busy', 'invalid', 'goal-locked' ), true );
            wp_send_json( array( 'ok' => $ok, 'code' => $code ), $ok ? 200 : 400 );
        }
        wp_safe_redirect( 'tour' === $code ? dashboard_url() : add_query_arg( 'saved', $code, dashboard_url() ) . '#hrs-prefs', 303 );
        exit;
    }
    set_page_title( 'Reading streak' );
    enqueue_base_style();
    wp_enqueue_style( 'hr-streaks', asset_url( 'css/streaks.css' ), array( 'hr-platform' ), asset_ver( 'css/streaks.css' ) );
    wp_enqueue_script( 'hr-streak-dashboard', asset_url( 'js/streak-dashboard.js' ), array(), asset_ver( 'js/streak-dashboard.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
    get_header();
    render( 'streaks/dashboard', dashboard_data( $uid ) );
    get_footer();
}

/** Returns the ?saved= code for the result message. */
function save_prefs( int $uid ): string {
    $nonce = isset( $_POST['_hrsnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_hrsnonce'] ) ) : '';
    if ( ! current_user_can( 'read' ) || ! same_origin_request() || ! wp_verify_nonce( $nonce, PREFS_NONCE ) ) {
        return 'expired';
    }
    if ( ! rate_limit( 'streak_prefs', (string) $uid, 20, HOUR_IN_SECONDS ) ) {
        return 'busy';
    }
    $action = isset( $_POST['hr_action'] ) ? sanitize_key( wp_unslash( $_POST['hr_action'] ) ) : '';
    if ( 'goal' === $action ) {
        $goal = isset( $_POST['goal'] ) ? (int) $_POST['goal'] : 0;
        if ( ! isset( GOALS[ $goal ] ) ) {
            return 'invalid';
        }
        return set_goal( $uid, $goal ) ? 'goal' : 'goal-locked';
    }
    if ( 'tour' === $action ) {
        tour_done( $uid );
        return 'tour';
    }
    if ( 'reminders' === $action ) {
        update_user_meta( $uid, '_hr_streak_reminders', empty( $_POST['reminders'] ) ? '0' : '1' );
        return empty( $_POST['reminders'] ) ? 'reminders-off' : 'reminders-on';
    }
    return 'invalid';
}

function dashboard_data( int $uid ): array {
    $today   = today();
    $view    = summary( $uid );
    $state   = state( $uid );
    $goal    = $view['goal'];
    $counts  = history( $uid, 16 * 7 + 7 );
    $run     = run_days( $state );
    $frozen  = array_flip( $state['freeze_used'] );
    $monday  = shift_date( $today, 1 - (int) gmdate( 'N', strtotime( $today . ' 00:00:00 UTC' ) ) );
    $status  = static function ( string $d ) use ( $today, $counts, $run, $frozen, $goal, $view ): string {
        $n = $counts[ $d ] ?? 0;
        if ( $d > $today ) {
            return 'future';
        }
        if ( isset( $frozen[ $d ] ) ) {
            return 'freeze';
        }
        if ( ( $run[ $d ] ?? '' ) === 'met' || $n >= $goal || ( $d === $today && $view['met_today'] ) ) {
            return 'met';
        }
        return $n > 0 ? 'partial' : 'none';
    };

    $week = array();
    for ( $i = 0; $i < 7; $i++ ) {
        $d      = shift_date( $monday, $i );
        $week[] = array( 'date' => $d, 'status' => $status( $d ), 'today' => $d === $today, 'count' => $counts[ $d ] ?? 0 );
    }

    // 16 weeks, Monday-first columns, ending with this week.
    $heat  = array();
    $start = shift_date( $monday, -15 * 7 );
    for ( $i = 0; $i < 16 * 7; $i++ ) {
        $d = shift_date( $start, $i );
        $n = $counts[ $d ] ?? 0;
        $s = $status( $d );
        $heat[] = array(
            'date'   => $d,
            'count'  => $n,
            'status' => $s,
            'level'  => 'met' === $s ? 4 : ( $n > 0 ? max( 1, min( 3, (int) ceil( $n / $goal * 3 ) ) ) : 0 ),
        );
    }
    $past = array_filter( $heat, static fn( $c ) => 'future' !== $c['status'] );

    $level = $view['level'];
    $floor = level_floor( $level );
    return array(
        'user'        => get_userdata( $uid ),
        'view'        => $view,
        'today'       => $today,
        'week'        => $week,
        'heat'        => $heat,
        'heat_reads'  => array_sum( array_column( $past, 'count' ) ),
        'heat_days'   => count( array_filter( $past, static fn( $c ) => $c['count'] > 0 ) ),
        'heat_goals'  => count( array_filter( $past, static fn( $c ) => 'met' === $c['status'] ) ),
        'xp_pct'      => (int) floor( 100 * ( $view['xp'] - $floor ) / max( 1, $view['next_level_xp'] - $floor ) ),
        'badges'      => $state['badges'],
        'total'       => (int) get_user_meta( $uid, '_hr_total_articles_read', true ),
        'reads'       => reads( $uid, 20 ),
        'suggestions' => suggestions( $uid, 3 ),
        'goal_locked' => get_user_meta( $uid, '_hr_goal_changed_on', true ) === $today,
        'reminders'   => '1' === get_user_meta( $uid, '_hr_streak_reminders', true ),
        'verified'    => ! function_exists( 'HR\Platform\Accounts\is_verified' ) || \HR\Platform\Accounts\is_verified( $uid ),
        'saved'       => isset( $_GET['saved'] ) ? sanitize_key( wp_unslash( $_GET['saved'] ) ) : '',
        'nonce'       => PREFS_NONCE,
        'steps'       => tour_steps( $goal ),
        'tour_auto'   => tour_pending( $uid ),
    );
}

/** The newest published articles this reader has not finished yet. */
function suggestions( int $uid, int $limit ): array {
    global $wpdb;
    $ids = $wpdb->get_col( $wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->posts} p LEFT JOIN " . table() . " r ON r.post_id = p.ID AND r.user_id = %d
         WHERE p.post_type = 'post' AND p.post_status = 'publish' AND p.post_password = '' AND r.id IS NULL
         ORDER BY p.post_date DESC LIMIT %d",
        $uid,
        $limit
    ) );
    return array_values( array_filter( array_map( 'get_post', array_map( 'intval', $ids ) ) ) );
}

/** Rough reading time in minutes, for suggestion cards and reminder emails. */
function minutes( \WP_Post $post ): int {
    return max( 1, (int) round( word_count( $post ) / READ_WPM ) );
}

/** The filled two-tone flame (same shapes as streak.mjs). Grey and still when there is no streak. */
function flame_svg( bool $lit, string $class = '' ): string {
    return sprintf(
        '<svg class="hrs-flame %1$s%2$s" viewBox="0 0 64 64" aria-hidden="true" focusable="false"><path class="hrs-flame__outer" d="%3$s"/><path class="hrs-flame__inner" d="%4$s"/></svg>',
        esc_attr( $class ),
        $lit ? ' is-lit' : '',
        FLAME_OUTER,
        FLAME_INNER
    );
}

const FLAME_OUTER = 'M32 3C34 13 50 22 50 39C50 52 42 61 32 61C22 61 14 52 14 40C14 31 19 25 23 20C24 27 27 31 31 32C27 22 29 11 32 3Z';
const FLAME_INNER = 'M32 30C34 37 42 41 42 49C42 55 37 59 32 59C27 59 22 55 22 49C22 44 25 41 27 38C28 42 30 44 32 44C30 39 31 34 32 30Z';
