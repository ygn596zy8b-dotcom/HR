<?php
/**
 * Streaks: read tracking REST (cookie auth + X-WP-Nonce).
 *
 *   POST hr/v1/reads/start    {post_id}                     → {token, issued_at, min_seconds} | {already_read: true}
 *   POST hr/v1/reads/complete {post_id, token, issued_at}   → {counted, already_read, xp_gained, streak, celebrate}
 *
 * The token is an HMAC over "user|post|issued_at", so a read can only be completed by the reader who started it,
 * for that article, after the minimum reading time has passed on the server's clock.
 */

namespace HR\Platform\Streaks;

use function HR\Platform\json_body;
use function HR\Platform\rate_limit;
use function HR\Platform\no_store;
use function HR\Platform\is_b64url;
use function HR\Platform\log_security;

if ( ! defined( 'ABSPATH' ) ) exit;

const MAX_AGE = 12 * HOUR_IN_SECONDS;

function word_count( \WP_Post $post ): int {
    return str_word_count( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ) );
}

/** About 30% of the time an average reader (238 wpm) needs, between 20 seconds and 2 minutes. */
function min_seconds( \WP_Post $post ): int {
    $seconds = (int) max( 20, min( 120, round( word_count( $post ) / 238 * 60 * 0.3 ) ) );
    if ( 'local' === wp_get_environment_type() ) {
        // Local copies only: lets end-to-end tests skip the wait.
        $seconds = max( 0, (int) apply_filters( 'hr_streak_min_seconds', $seconds, $post->ID ) );
    }
    return $seconds;
}

function read_token( int $uid, int $post_id, int $issued ): string {
    return rtrim( strtr( base64_encode( hash_hmac( 'sha256', "{$uid}|{$post_id}|{$issued}", wp_salt( 'nonce' ), true ) ), '+/', '-_' ), '=' );
}

/** A published, readable article from a client-supplied id, or null. */
function trackable_post( $raw ): ?\WP_Post {
    $id   = is_int( $raw ) || ( is_string( $raw ) && ctype_digit( $raw ) && strlen( $raw ) < 20 ) ? (int) $raw : 0;
    $post = $id > 0 ? get_post( $id ) : null;
    return $post && 'post' === $post->post_type && 'publish' === $post->post_status && ! post_password_required( $post ) ? $post : null;
}

function rest_error( string $code, string $message, int $status ): \WP_Error {
    return new \WP_Error( $code, $message, array( 'status' => $status ) );
}

add_action( 'rest_api_init', function () {
    $auth = static fn() => is_user_logged_in();
    register_rest_route( 'hr/v1', '/reads/start', array(
        'methods'             => 'POST',
        'callback'            => __NAMESPACE__ . '\\rest_start',
        'permission_callback' => $auth,
    ) );
    register_rest_route( 'hr/v1', '/reads/complete', array(
        'methods'             => 'POST',
        'callback'            => __NAMESPACE__ . '\\rest_complete',
        'permission_callback' => $auth,
    ) );
} );

function rest_start( \WP_REST_Request $request ) {
    no_store();
    $uid  = get_current_user_id();
    $body = json_body( $request, 512 );
    if ( ! rate_limit( 'reads_start', (string) $uid, 60, MINUTE_IN_SECONDS ) ) {
        return rest_error( 'rate_limited', 'Too many requests. Try again in a minute.', 429 );
    }
    $post = trackable_post( $body['post_id'] ?? null );
    if ( ! $post ) {
        return rest_error( 'invalid_post', 'This page does not count towards your streak.', 400 );
    }
    if ( has_read( $uid, $post->ID ) ) {
        return array( 'already_read' => true );
    }
    $issued = time();
    return array(
        'token'       => read_token( $uid, $post->ID, $issued ),
        'issued_at'   => $issued,
        'min_seconds' => min_seconds( $post ),
    );
}

function rest_complete( \WP_REST_Request $request ) {
    no_store();
    $uid  = get_current_user_id();
    $body = json_body( $request, 1024 );
    if ( ! rate_limit( 'reads_min', (string) $uid, 30, MINUTE_IN_SECONDS ) || ! rate_limit( 'reads_day', (string) $uid, 150, DAY_IN_SECONDS ) ) {
        return rest_error( 'rate_limited', 'Too many requests. Try again later.', 429 );
    }
    $post   = trackable_post( $body['post_id'] ?? null );
    $token  = $body['token'] ?? '';
    $issued = isset( $body['issued_at'] ) && is_int( $body['issued_at'] ) ? $body['issued_at'] : 0;
    if ( ! $post ) {
        return rest_error( 'invalid_post', 'This page does not count towards your streak.', 400 );
    }
    if ( ! is_b64url( $token, 64 ) || $issued <= 0 || ! hash_equals( read_token( $uid, $post->ID, $issued ), $token ) ) {
        log_security( 'read_token_invalid', array( 'post' => $post->ID ) );
        return rest_error( 'invalid_token', 'This read could not be verified. Reload the page and try again.', 403 );
    }
    $elapsed = time() - $issued;
    if ( $elapsed > MAX_AGE ) {
        return rest_error( 'expired', 'This read has expired. Reload the page to count it.', 400 );
    }
    if ( $elapsed < min_seconds( $post ) - 2 ) {
        return rest_error( 'too_fast', 'Keep reading — this article has not been open long enough to count.', 400 );
    }

    $now     = time();
    $counted = insert_read( $uid, $post->ID, today(), kind_of( $post ), gmdate( 'Y-m-d H:i:s', $now ) );
    if ( ! $counted ) {
        return array( 'counted' => false, 'already_read' => true, 'xp_gained' => 0, 'streak' => summary( $uid ), 'celebrate' => null );
    }
    [ $view, $events ] = refresh( $uid, array( 'kind' => kind_of( $post ), 'hour' => local_hour( $now, wp_timezone() ) ) );
    return array(
        'counted'      => true,
        'already_read' => false,
        'xp_gained'    => (int) $events['xp'],
        'streak'       => $view,
        'celebrate'    => array(
            'goal_met'      => (bool) $events['goal_met'],
            'milestone'     => $events['milestone'],
            'new_best'      => (bool) $events['new_best'],
            'freeze_earned' => (bool) $events['freeze_earned'],
            'badge'         => $events['badges'][0] ?? null,
            'badge_title'   => isset( $events['badges'][0] ) ? BADGES[ $events['badges'][0] ][0] : null,
        ),
    );
}
