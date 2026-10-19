<?php
/**
 * Accounts: the header account chip and admin-ajax.php?action=hr_session.
 *
 * Pages are cached for everyone, so the header always prints the same guest links. account-chip.js asks
 * hr_session for the signed-in state only when the non-secret hr_li hint cookie says it is worth asking.
 *
 * Response (JSON, no-store):
 *   signed out: { logged_in: false }
 *   signed in:  { logged_in: true, nonce, logout_nonce, user: { name, initials, verified },
 *                 links: { account, dashboard, settings, logout, submit? } } + whatever 'hr_session_payload' adds
 */

namespace HR\Platform\Accounts;

use function HR\Platform\no_store;
use function HR\Platform\render;
use function HR\Platform\asset_url;
use function HR\Platform\asset_ver;

if ( ! defined( 'ABSPATH' ) ) exit;

function session_payload( \WP_User $user ): array {
    $links = array(
        'account'   => account_url(),
        'dashboard' => page_url( 'dashboard' ) ?: home_url( '/dashboard/' ),
        'settings'  => account_url( 'settings' ),
        'logout'    => account_url( 'logout' ),
    );
    if ( $submit = page_url( 'submit' ) ) {
        $links['submit'] = $submit;
    }
    $payload = array(
        'logged_in'    => true,
        'nonce'        => wp_create_nonce( 'wp_rest' ),
        'logout_nonce' => wp_create_nonce( 'hr_account_logout' ),
        'user'         => array(
            'name'     => $user->display_name,
            'initials' => initials( $user->display_name ),
            'verified' => is_verified( (int) $user->ID ),
        ),
        'links'        => array_map( 'esc_url_raw', $links ),
    );
    return (array) apply_filters( 'hr_session_payload', $payload, $user );
}

add_action( 'wp_ajax_hr_session', function () {
    no_store();
    wp_send_json( session_payload( wp_get_current_user() ) );
} );

add_action( 'wp_ajax_nopriv_hr_session', function () {
    no_store();
    wp_send_json( array( 'logged_in' => false ) );
} );

/** Slot inside the theme's primary navigation. Identical for every visitor, so it is safe in cached pages. */
add_action( 'hr_header_account', function () {
    static $done = false;
    if ( $done ) {
        return;
    }
    $done = true;
    render( 'account/chip', array() );
} );

add_action( 'wp_enqueue_scripts', function () {
    wp_register_style( 'hr-account-chip', false, array(), asset_ver( 'js/account-chip.js' ) );
    wp_enqueue_style( 'hr-account-chip' );
    $css = file_get_contents( __DIR__ . '/chip.css' );
    if ( $css ) {
        wp_add_inline_style( 'hr-account-chip', $css );
    }
    wp_enqueue_script( 'hr-account-chip', asset_url( 'js/account-chip.js' ), array(), asset_ver( 'js/account-chip.js' ), array( 'in_footer' => false, 'strategy' => 'defer' ) );
} );

/** Endpoint for the chip, as non-executable JSON (no inline script needed). */
add_action( 'wp_head', function () {
    $config = array( 'session' => admin_url( 'admin-ajax.php?action=hr_session' ) );
    printf( '<script type="application/json" id="hr-account-chip-config">%s</script>' . "\n", wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES ) );
}, 2 );
