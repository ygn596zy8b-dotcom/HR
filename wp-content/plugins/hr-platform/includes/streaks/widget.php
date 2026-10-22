<?php
/**
 * Streaks: the floating streak widget, bottom left on every front-end page (assets/src/streak-widget.mjs).
 *
 * Guest HTML is cached (LiteSpeed, and later the Cloudflare edge), so the server prints nothing personal here: only
 * a static JSON config that is identical for every visitor. The script mounts the widget after the page has loaded,
 * from window.HR.session (the hr_session payload: 'streak' from module.php plus 'streak_ui' below). Guests see an
 * invitation to start a streak; signed-in readers see their live counter. Without JavaScript nothing is shown.
 *
 * Not loaded in wp-admin, feeds, embeds, or on the sign-in, register, confirm, reset and sign-out screens.
 * Filter 'hr_streak_widget_enabled' ( bool $on ) switches it off for other pages.
 */

namespace HR\Platform\Streaks;

use function HR\Platform\asset_url;
use function HR\Platform\asset_ver;

if ( ! defined( 'ABSPATH' ) ) exit;

/** /account/<sub>/ screens where the widget would only repeat what the page is for. */
const WIDGET_SKIP_ACCOUNT = array( 'login', 'register', 'verify', 'forgot', 'reset', 'two-factor', 'logout' );

function widget_enabled(): bool {
    if ( is_admin() || is_feed() || is_embed() || ! function_exists( 'HR\Platform\Accounts\login_url' ) ) {
        return false;
    }
    if ( 'account' === \HR\Platform\current_route() && in_array( strtolower( (string) get_query_var( 'hr_sub' ) ), WIDGET_SKIP_ACCOUNT, true ) ) {
        return false;
    }
    return (bool) apply_filters( 'hr_streak_widget_enabled', true );
}

add_action( 'wp_enqueue_scripts', function () {
    if ( ! widget_enabled() ) {
        return;
    }
    // Reads window.HR.session, which account-chip.js (also deferred, in <head>) sets up.
    $deps = wp_script_is( 'hr-account-chip', 'registered' ) ? array( 'hr-account-chip' ) : array();
    wp_enqueue_script( 'hr-streak-widget', asset_url( 'js/streak-widget.js' ), $deps, asset_ver( 'js/streak-widget.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
}, 20 );

/** Static, identical for everyone: safe in cached HTML. Non-executable JSON, so no inline script is needed. */
add_action( 'wp_footer', function () {
    if ( ! wp_script_is( 'hr-streak-widget', 'enqueued' ) ) {
        return;
    }
    $config = array(
        'css'       => asset_url( 'css/streak-widget.css' ) . '?ver=' . asset_ver( 'css/streak-widget.css' ),
        'login'     => \HR\Platform\Accounts\login_url(),
        'register'  => \HR\Platform\Accounts\account_url( 'register' ),
        'dashboard' => dashboard_url(),
        'goal'      => DEFAULT_GOAL,
    );
    printf( '<script type="application/json" id="hr-streak-widget-config">%s</script>' . "\n", wp_json_encode( array_map( static fn( $v ) => is_string( $v ) ? esc_url_raw( $v ) : $v, $config ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES ) );
}, 5 );

// What the signed-in widget needs beyond the streak view (the hr_session response is no-store and per user).
add_filter( 'hr_session_payload', function ( array $payload, \WP_User $user ) {
    $payload['streak_ui'] = array(
        'max_freezes' => MAX_FREEZES,
        'tour'        => tour_pending( (int) $user->ID ),
    );
    return $payload;
}, 11, 2 );
