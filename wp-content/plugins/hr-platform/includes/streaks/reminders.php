<?php
/**
 * Streaks: opt-in reminder emails (user meta _hr_streak_reminders = '1'; off by default).
 *
 * Daily at 19:00 in the streak day zone (Pakistan time, store.php day_zone()): every opted-in reader with a confirmed address whose streak is at risk gets at most one
 * email a day, 50 readers per run (a follow-up run is queued while more are left). Each email carries
 * List-Unsubscribe + List-Unsubscribe-Post (one-click, RFC 8058) pointing at /reading/unsubscribe/?u=&t=, where
 * t is an HMAC of the user id: GET shows a confirm button, POST turns reminders off.
 */

namespace HR\Platform\Streaks;

use function HR\Platform\add_route;
use function HR\Platform\no_store;
use function HR\Platform\rate_limit;
use function HR\Platform\ip_hash;
use function HR\Platform\render;
use function HR\Platform\render_to_string;
use function HR\Platform\set_page_title;
use function HR\Platform\enqueue_base_style;
use function HR\Platform\asset_url;
use function HR\Platform\asset_ver;
use function HR\Platform\log_security;

if ( ! defined( 'ABSPATH' ) ) exit;

const REMINDER_HOOK  = 'hr_streak_reminders';
const REMINDER_BATCH = 50;

add_action( 'init', function () {
    $scheduled = wp_next_scheduled( REMINDER_HOOK );
    // An event scheduled for another hour (an older version used the site timezone) is moved to 19:00.
    if ( $scheduled && 19 !== local_hour( $scheduled, day_zone() ) ) {
        wp_clear_scheduled_hook( REMINDER_HOOK );
        $scheduled = false;
    }
    if ( ! $scheduled ) {
        $next = new \DateTimeImmutable( 'today 19:00', day_zone() );
        if ( $next->getTimestamp() <= time() ) {
            $next = $next->modify( '+1 day' );
        }
        wp_schedule_event( $next->getTimestamp(), 'daily', REMINDER_HOOK );
    }
} );

add_action( REMINDER_HOOK, __NAMESPACE__ . '\\send_reminders' );

function send_reminders(): void {
    do_action( 'hr_monitor_cron', 'streak-reminders', 'in_progress', '0 19 * * *' );
    try {
        // A run that slipped past midnight would promise "ends at midnight" a whole day early.
        if ( local_hour( time(), day_zone() ) < 12 ) {
            do_action( 'hr_monitor_cron', 'streak-reminders', 'ok', '0 19 * * *' );
            return;
        }
        global $wpdb;
        $today = today();
        $uids  = array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
            "SELECT r.user_id FROM {$wpdb->usermeta} r
             JOIN {$wpdb->usermeta} g ON g.user_id = r.user_id AND g.meta_key = '_hr_last_goal_date' AND g.meta_value >= %s AND g.meta_value < %s
             LEFT JOIN {$wpdb->usermeta} s ON s.user_id = r.user_id AND s.meta_key = '_hr_reminder_sent'
             WHERE r.meta_key = '_hr_streak_reminders' AND r.meta_value = '1' AND ( s.meta_value IS NULL OR s.meta_value <> %s )
             LIMIT %d",
            shift_date( $today, -1 - MAX_FREEZES ),
            $today,
            $today,
            REMINDER_BATCH
        ) ) );
        $sent = 0;
        foreach ( $uids as $uid ) {
            // Marked first, so a failure or a crash never sends twice.
            update_user_meta( $uid, '_hr_reminder_sent', $today );
            $view = summary( $uid );
            $user = get_userdata( $uid );
            if ( $user && $view['at_risk'] && ( ! function_exists( 'HR\Platform\Accounts\is_verified' ) || \HR\Platform\Accounts\is_verified( $uid ) ) ) {
                $sent += (int) send_reminder( $user, $view );
            }
        }
        if ( count( $uids ) === REMINDER_BATCH ) {
            wp_schedule_single_event( time() + MINUTE_IN_SECONDS, REMINDER_HOOK );
        }
        error_log( 'hr-streaks reminders: checked ' . count( $uids ) . ', sent ' . $sent );
        do_action( 'hr_monitor_cron', 'streak-reminders', 'ok', '0 19 * * *' );
    } catch ( \Throwable $e ) {
        do_action( 'hr_monitor_cron', 'streak-reminders', 'error', '0 19 * * *' );
        throw $e;
    }
}

function unsubscribe_token( int $uid ): string {
    return rtrim( strtr( base64_encode( hash_hmac( 'sha256', "streak-unsubscribe|{$uid}", wp_salt( 'auth' ), true ) ), '+/', '-_' ), '=' );
}

function unsubscribe_url( int $uid ): string {
    return add_query_arg( array( 'u' => $uid, 't' => unsubscribe_token( $uid ) ), home_url( '/reading/unsubscribe/' ) );
}

function send_reminder( \WP_User $user, array $view ): bool {
    $left    = max( 1, $view['goal'] - $view['today'] );
    $reads   = suggestions( (int) $user->ID, 3 );
    $state   = state( (int) $user->ID );
    // A missed day is covered when the freezes held can bridge every day since the goal was last met, today included.
    $covered = '' !== $state['last_goal_date'] && days_between( $state['last_goal_date'], today() ) <= $state['freezes'];
    $more    = sprintf( 1 === $left ? '%d more article' : '%d more articles', $left );
    if ( $covered ) {
        $subject = sprintf( 'Keep your %d-day streak: read %s today', $view['current'], $more );
        $lead    = sprintf( 'Read %s before midnight to keep your %d-day streak going without using a streak freeze.', $more, $view['current'] );
    } else {
        $subject = sprintf( 'Your %d-day streak ends at midnight: read %s to keep it', $view['current'], $more );
        $lead    = sprintf( 'Read %s before midnight to keep your %d-day streak going.', $more, $view['current'] );
    }
    $vars = array(
        'subject' => $subject,
        'lead'    => $lead,
        'reads'   => array_map( static fn( $p ) => array( 'title' => html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' ), 'url' => get_permalink( $p ), 'minutes' => minutes( $p ), 'oped' => 'oped' === kind_of( $p ) ), $reads ),
        'unsub'   => unsubscribe_url( (int) $user->ID ),
    );
    $text = $vars['lead'] . "\n\n";
    foreach ( $vars['reads'] as $r ) {
        $text .= "{$r['title']} ({$r['minutes']} min)\n{$r['url']}\n\n";
    }
    $text .= 'Your streak: ' . dashboard_url() . "\n\nYou asked for streak reminders. Turn them off: {$vars['unsub']}\n\nHeadline Recorder · " . home_url( '/' );
    $alt = static function ( $mailer ) use ( $text ) {
        $mailer->AltBody = $text;
    };
    add_action( 'phpmailer_init', $alt );
    $ok = wp_mail( $user->user_email, $subject, render_to_string( 'streaks/email', $vars ), array(
        'Content-Type: text/html; charset=UTF-8',
        'Auto-Submitted: auto-generated',
        'List-Unsubscribe: <' . esc_url_raw( $vars['unsub'] ) . '>',
        'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
    ) );
    remove_action( 'phpmailer_init', $alt );
    return (bool) $ok;
}

add_route( 'reading', '^reading/(unsubscribe)/?$', __NAMESPACE__ . '\\route_unsubscribe' );

function route_unsubscribe(): void {
    no_store();
    $raw_u = isset( $_GET['u'] ) ? (string) wp_unslash( $_GET['u'] ) : '';
    $raw_t = isset( $_GET['t'] ) ? (string) wp_unslash( $_GET['t'] ) : '';
    $uid   = ctype_digit( $raw_u ) && strlen( $raw_u ) < 20 ? (int) $raw_u : 0;
    $valid = $uid > 0 && \HR\Platform\is_b64url( $raw_t, 64 ) && hash_equals( unsubscribe_token( $uid ), $raw_t ) && get_userdata( $uid );
    if ( ! rate_limit( 'streak_unsub', ip_hash(), 30, 10 * MINUTE_IN_SECONDS ) ) {
        status_header( 429 );
        $state = 'busy';
    } elseif ( ! $valid ) {
        if ( '' !== $raw_t ) {
            log_security( 'streak_unsubscribe_invalid' );
        }
        status_header( 400 );
        $state = 'invalid';
    } elseif ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
        // No nonce: mail providers POST here without cookies (one-click); the per-user HMAC is the authorisation.
        update_user_meta( $uid, '_hr_streak_reminders', '0' );
        $state = 'done';
    } else {
        $state = 'confirm';
    }
    set_page_title( 'Streak reminders' );
    enqueue_base_style();
    wp_enqueue_style( 'hr-streaks', asset_url( 'css/streaks.css' ), array( 'hr-platform' ), asset_ver( 'css/streaks.css' ) );
    get_header();
    render( 'streaks/unsubscribe', array( 'state' => $state ) );
    get_footer();
}
