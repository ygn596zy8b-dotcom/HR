<?php
/**
 * Accounts — reader accounts under /account/: register, verify email, sign in (password, passkeys, two-step
 * verification), forgot/reset, settings, sessions, data export and deletion, the header account chip.
 *
 * ---------------------------------------------------------------------------------------------------------------
 * EXTENSION CONTRACT (for the streaks, submissions and other modules)
 * ---------------------------------------------------------------------------------------------------------------
 *
 * Account pages — apply_filters( 'hr_account_pages', array $pages ):
 *
 *     add_filter( 'hr_account_pages', function ( array $pages ) {
 *         $pages['submit'] = array(
 *             'title'             => 'Submit an article',      // nav label and page title
 *             'icon'              => 'pen',                    // HR\Platform\icon() name
 *             'order'             => 20,                       // nav position (Overview is first, Settings last)
 *             'nav'               => true,                     // show in the account navigation
 *             'render'            => function ( WP_User $user ) { ... }, // echo the page body (escape everything)
 *             'handle'            => function ( WP_User $user ) { ... }, // optional; called on POST before render.
 *                                                              // Same-origin is already checked; verify your own nonce.
 *             'url'               => null,                     // or a URL: an external nav item (e.g. /dashboard/)
 *             'requires_verified' => true,                     // unverified readers see a "confirm your email" gate
 *         );
 *         return $pages;
 *     } );
 *
 *   The page is served at /account/<slug>/. Slugs: [a-z0-9-]{1,40}. Reserved: login, register, verify, forgot,
 *   reset, logout, two-factor, settings, overview. Logged-out visitors are sent to
 *   /account/login/?redirect_to=<that page>. Every account response is no-store and noindex.
 *
 * Overview cards — apply_filters( 'hr_account_overview_cards', array $cards, WP_User $user ):
 *   each card: array( 'title' => string, 'value' => string, 'body_html' => string (passed through wp_kses_post),
 *   'link' => array( 'label' => string, 'url' => string ) | string URL, 'icon' => icon name, 'order' => int ).
 *
 * Session payload — apply_filters( 'hr_session_payload', array $payload, WP_User $user ): add keys to the JSON that
 *   admin-ajax.php?action=hr_session returns to account-chip.js (e.g. 'streak' => array( current, best, today, goal,
 *   met_today, at_risk, freezes )). In the browser: window.HR.session (Promise<payload|null>), event 'hr:session'
 *   on document; dispatch 'hr:streak' (detail = streak object) to update the chip live.
 *
 * Account deletion — do_action( 'hr_account_deleted', int $user_id ): fired once, BEFORE the user is deleted, both when
 *   readers delete their own account and when an administrator deletes a user; purge your module's rows for that user.
 *   (Self-service deletion then deletes the reader's unpublished posts/uploads; published ones go to an administrator.)
 *
 * Data export — apply_filters( 'hr_account_export', array $data, int $user_id ): add a top-level key with that
 *   user's data (no secrets). Returned to the reader as a JSON download.
 *
 * Helpers (namespace HR\Platform\Accounts):
 *   is_verified( int $uid ): bool          email address confirmed
 *   require_recent_auth(): bool            password/passkey/authenticator code confirmed within 10 minutes in this
 *                                          session; when false, render step_up_panel( $return_anchor ) instead of
 *                                          the sensitive form
 *   step_up_panel( string $anchor ): void  inline re-authentication panel (password, passkey or code). Works on
 *                                          Settings and on contributed pages: it posts back to the same page
 *                                          (handled before your 'handle' callback, which is not called for it) and
 *                                          returns to #<anchor>; the panel's element id is "stepup-<anchor>"
 *   login_url( string $redirect = '' ): string   redirect is validated (same site, never back into an auth page)
 *   account_url( string $slug = '' ): string
 *   has_mfa( int $uid ): bool              authenticator app or at least one passkey
 *
 * Browser (assets/js/account-chip.js, loaded site-wide): window.HR.session is a Promise of the hr_session payload
 *   (or null when signed out) and document fires 'hr:session' once with the same detail. Dispatch
 *   document.dispatchEvent( new CustomEvent( 'hr:streak', { detail: streak } ) ) to update the header chip.
 *   The REST nonce in the payload lives in memory only — never copy it to localStorage/sessionStorage.
 *
 * Staff sign-in policy — apply_filters( 'hr_enforce_staff_mfa', bool $on, ?WP_User $user ): anyone who can
 *   edit_posts must have a passkey or two-step verification before using wp-admin. On by default; off with
 *   define( 'HR_ENFORCE_STAFF_MFA', false ); opt-in (filter) on WP_ENVIRONMENT_TYPE 'local' copies.
 *
 * Policies outside /account/: wp-login.php needs the authentication code for two-step accounts (staff with only a
 *   passkey are sent to /account/login/); wp-login.php?action=register|lostpassword redirect to our pages; core
 *   password resets sign out every session; REST: nobody changes their own email/password through /wp/v2/users
 *   (Settings does it with step-up + confirmation), and unenrolled staff cannot write through /wp/v2/ while
 *   enforcement is on.
 *
 * Routes: /account/{,login,register,verify,forgot,reset,logout,two-factor,settings,<contributed slug>}/
 * REST:   hr/v1/passkeys/{register,login,stepup}/{options,verify} (see passkeys.php)
 * AJAX:   admin-ajax.php?action=hr_session (see session.php)
 * ---------------------------------------------------------------------------------------------------------------
 */

namespace HR\Platform\Accounts;

if ( ! defined( 'ABSPATH' ) ) exit;

const DB_VERSION = 1;
const STEPUP_TTL = 600;
const RESERVED   = array( 'login', 'register', 'verify', 'forgot', 'reset', 'logout', 'two-factor', 'settings', 'overview' );

foreach ( array( 'helpers', 'ui', 'mail', 'passwords', 'totp', 'passkeys', 'login', 'register', 'reset', 'settings', 'session', 'admin', 'router' ) as $hr_accounts_file ) {
    require_once __DIR__ . "/{$hr_accounts_file}.php";
}
unset( $hr_accounts_file );

\HR\Platform\add_route( 'account', '^account(?:/([a-z0-9-]+))?/?$', __NAMESPACE__ . '\\dispatch' );

/** Tables. Versioned separately from the core so a deploy of this module alone still creates them. */
function install(): void {
    passkeys_install_table();
    update_option( 'hr_accounts_db_version', DB_VERSION, false );
}
add_action( 'hr_install', __NAMESPACE__ . '\\install' );
add_action( 'init', function () {
    if ( (int) get_option( 'hr_accounts_db_version' ) !== DB_VERSION ) {
        install();
    }
}, 98 );

add_filter( 'hr_settings_schema', function ( array $schema ) {
    $schema['accounts'] = array(
        'title'       => 'Reader accounts',
        'description' => 'Sign-in, passkeys and two-step verification for readers and staff. Staff are reminded to add a passkey or two-step verification; requiring it for editors and authors is opt-in with <code>define( \'HR_ENFORCE_STAFF_MFA\', true );</code> in wp-config.php. Administrators are never locked out.',
        'fields'      => array(
            'accounts_hibp'               => array(
                'label'       => 'Reject breached passwords',
                'type'        => 'checkbox',
                'default'     => 1,
                'description' => 'Checks new passwords against Have I Been Pwned using k-anonymity: only the first 5 characters of the password\'s SHA-1 hash leave the server. If the service is unreachable the check is skipped.',
            ),
            'accounts_new_device_alerts'  => array(
                'label'       => 'New-device sign-in emails',
                'type'        => 'checkbox',
                'default'     => 1,
                'description' => 'Email readers when their account is signed in from a browser it has not seen before.',
            ),
        ),
    );
    return $schema;
} );
