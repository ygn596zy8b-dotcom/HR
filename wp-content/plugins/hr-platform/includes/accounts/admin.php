<?php
/**
 * Accounts: wp-admin integration. Readers stay on the front end, staff must enrol a passkey or two-step
 * verification, Users list columns, an admin-only "reset security" control for locked-out users, WordPress
 * privacy exporters/erasers, and core registration routed to /account/register/.
 */

namespace HR\Platform\Accounts;

use function HR\Platform\log_security;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Must this staff account enrol a passkey or two-step verification before using wp-admin?
 * Opt-in: define( 'HR_ENFORCE_STAFF_MFA', true ) in wp-config.php once every staff member has set one up.
 * Administrators are never redirected (so the site owner can't be locked out); they get a reminder instead.
 */
function staff_mfa_enforced( ?\WP_User $user = null ): bool {
    if ( $user && user_can( $user, 'manage_options' ) ) {
        return false;
    }
    $on = defined( 'HR_ENFORCE_STAFF_MFA' ) && true === HR_ENFORCE_STAFF_MFA;
    return (bool) apply_filters( 'hr_enforce_staff_mfa', $on, $user );
}

add_action( 'admin_notices', function () {
    $user = wp_get_current_user();
    if ( ! is_staff( $user ) || has_mfa( (int) $user->ID ) ) {
        return;
    }
    printf( '<div class="notice notice-warning"><p><strong>Protect your account:</strong> add a passkey or two-step verification in <a href="%s">your account security settings</a>.</p></div>', esc_url( account_url( 'settings' ) . '#security' ) );
} );

add_action( 'admin_init', function () {
    if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) || ! is_user_logged_in() ) {
        return;
    }
    global $pagenow;
    $user = wp_get_current_user();
    // Readers have nothing to do in wp-admin. define( 'HR_READERS_IN_ADMIN', true ) in wp-config.php turns this off.
    if ( ! is_staff( $user ) && ! ( defined( 'HR_READERS_IN_ADMIN' ) && HR_READERS_IN_ADMIN ) ) {
        if ( 'admin-post.php' !== $pagenow ) {
            wp_safe_redirect( add_query_arg( 'notice', 'reader-admin', 'profile.php' === $pagenow ? account_url( 'settings' ) : account_url() ) );
            exit;
        }
        return;
    }
    if ( staff_mfa_enforced( $user ) && ! has_mfa( (int) $user->ID ) && ! in_array( $pagenow, array( 'profile.php', 'admin-post.php' ), true ) ) {
        log_security( 'staff_mfa_redirect', array( 'uid' => (int) $user->ID ) );
        wp_safe_redirect( add_query_arg( 'enrol', 'staff', account_url( 'settings' ) ) . '#security' );
        exit;
    }
}, 1 );

// The same policy for the REST API: a browser session of a staff account that has not enrolled could otherwise still
// change content through /wp/v2/ with a REST nonce (e.g. the one hr_session hands out). Reads, our own hr/v1 routes
// (passkey enrolment) and application-password requests (no browser session) are unaffected.
add_filter( 'rest_pre_dispatch', function ( $result, $server, $request ) {
    if ( null !== $result || ! is_user_logged_in() || ! $request instanceof \WP_REST_Request ) {
        return $result;
    }
    if ( in_array( $request->get_method(), array( 'GET', 'HEAD', 'OPTIONS' ), true ) || ! str_starts_with( strtolower( $request->get_route() ), '/wp/v2/' ) ) {
        return $result;
    }
    if ( function_exists( 'rest_get_authenticated_app_password' ) && rest_get_authenticated_app_password() ) {
        return $result;
    }
    $user = wp_get_current_user();
    if ( ! is_staff( $user ) || has_mfa( (int) $user->ID ) || ! staff_mfa_enforced( $user ) ) {
        return $result;
    }
    log_security( 'staff_mfa_rest_blocked', array( 'uid' => (int) $user->ID ) );
    return new \WP_Error( 'hr_staff_mfa_required', 'Add a passkey or two-step verification to your account (Account → Settings → Security) before editing the site.', array( 'status' => 403 ) );
}, 10, 3 );

// Core's REST users endpoint lets anyone change their own email address and password with just a session and a REST
// nonce: no current password, no step-up, no confirmation of the new address. That would turn any hijacked session
// into a full account takeover, so self-service email/password changes only go through Account → Settings.
// (Administrators editing other users are unaffected.)
// Routes match case-insensitively (/WP/V2/USERS/ME reaches the same handler), so compare the lowercased route.
add_filter( 'rest_pre_dispatch', function ( $result, $server, $request ) {
    if ( null !== $result || ! is_user_logged_in() || ! $request instanceof \WP_REST_Request ) {
        return $result;
    }
    if ( ! in_array( $request->get_method(), array( 'POST', 'PUT', 'PATCH' ), true ) || ! preg_match( '#^/wp/v2/users/(me|\d+)/?$#', strtolower( $request->get_route() ), $m ) ) {
        return $result;
    }
    if ( ( 'me' === $m[1] ? get_current_user_id() : (int) $m[1] ) !== get_current_user_id() ) {
        return $result;
    }
    if ( null === $request->get_param( 'email' ) && null === $request->get_param( 'password' ) ) {
        return $result;
    }
    log_security( 'rest_self_credentials_blocked', array( 'uid' => get_current_user_id() ) );
    return new \WP_Error( 'hr_use_account_settings', 'Change your email address or password in Account → Settings.', array( 'status' => 403 ) );
}, 9, 3 );

add_filter( 'show_admin_bar', function ( $show ) {
    return is_user_logged_in() && ! is_staff( wp_get_current_user() ) ? false : $show;
}, 20 );

add_action( 'admin_notices', function () {
    $user = wp_get_current_user();
    if ( ! is_staff( $user ) ) {
        return;
    }
    if ( ! has_mfa( (int) $user->ID ) ) {
        printf(
            '<div class="notice notice-warning"><p><strong>Protect your staff account.</strong> %s <a href="%s">Add a passkey or turn on two-step verification</a>. It takes about a minute.</p></div>',
            staff_mfa_enforced( $user ) ? 'Accounts that can edit content must use a passkey or two-step verification before using the dashboard.' : 'Passkeys and two-step verification stop anyone who learns your password.',
            esc_url( account_url( 'settings' ) . '#security' )
        );
        return;
    }
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( $screen && 'users' === $screen->id && current_user_can( 'list_users' ) ) {
        $missing = staff_without_mfa();
        printf(
            '<div class="notice notice-info"><p><strong>Staff sign-in policy:</strong> anyone who can edit posts %s use a passkey or two-step verification (Settings → Security on their account page). %s</p></div>',
            staff_mfa_enforced() ? 'must' : 'should (enforcement is switched off on this site)',
            $missing ? esc_html( sprintf( _n( '%d staff account has not enrolled yet.', '%d staff accounts have not enrolled yet.', $missing ), $missing ) ) : 'Every staff account has enrolled.'
        );
    }
} );

function staff_without_mfa(): int {
    $missing = 0;
    foreach ( get_users( array( 'capability' => 'edit_posts', 'fields' => 'ID', 'number' => 200 ) ) as $uid ) {
        if ( ! has_mfa( (int) $uid ) ) {
            $missing++;
        }
    }
    return $missing;
}

// ---------------------------------------------------------------------------------------------------------------
// Users list
// ---------------------------------------------------------------------------------------------------------------

add_filter( 'manage_users_columns', function ( $columns ) {
    $columns['hr_verified'] = 'Email verified';
    $columns['hr_mfa']      = '2FA/Passkey';
    return $columns;
} );

add_filter( 'manage_users_custom_column', function ( $output, $column, $uid ) {
    $uid = (int) $uid;
    if ( 'hr_verified' === $column ) {
        return is_verified( $uid ) ? '<span style="color:#00875a">Yes</span>' : '<span style="color:#b45309">No</span>';
    }
    if ( 'hr_mfa' === $column ) {
        $parts = array();
        if ( has_totp( $uid ) ) {
            $parts[] = 'Authenticator';
        }
        $count = passkey_count( $uid );
        if ( $count ) {
            $parts[] = sprintf( _n( '%d passkey', '%d passkeys', $count ), $count );
        }
        return $parts ? esc_html( implode( ' + ', $parts ) ) : 'None';
    }
    return $output;
}, 10, 3 );

// ---------------------------------------------------------------------------------------------------------------
// Support: an administrator can clear a locked-out user's second factors from their profile screen.
// ---------------------------------------------------------------------------------------------------------------

add_action( 'edit_user_profile', function ( $profile ) {
    if ( ! current_user_can( 'edit_users' ) || ! $profile instanceof \WP_User ) {
        return;
    }
    $uid = (int) $profile->ID;
    ?>
    <h2>Headline Recorder security</h2>
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row">Status</th>
            <td>
                Email <?php echo is_verified( $uid ) ? 'verified' : 'not verified'; ?> ·
                two-step verification <?php echo has_totp( $uid ) ? 'on (' . (int) recovery_remaining( $uid ) . ' recovery codes left)' : 'off'; ?> ·
                <?php echo (int) passkey_count( $uid ); ?> passkey(s)
            </td>
        </tr>
        <tr>
            <th scope="row">Reset sign-in methods</th>
            <td>
                <label><input type="checkbox" name="hr_reset_totp" value="1"> Turn off two-step verification and cancel recovery codes</label><br>
                <label><input type="checkbox" name="hr_reset_passkeys" value="1"> Remove all passkeys</label>
                <p class="description">Only after confirming the person's identity outside this site. They are emailed about the change.</p>
            </td>
        </tr>
    </table>
    <?php
} );

add_action( 'edit_user_profile_update', function ( $uid ) {
    $uid = (int) $uid;
    // Core has already checked the update-user_{id} nonce and edit_user for this screen.
    if ( ! current_user_can( 'edit_users' ) || ! current_user_can( 'edit_user', $uid ) ) {
        return;
    }
    $user = get_userdata( $uid );
    if ( ! $user ) {
        return;
    }
    if ( ! empty( $_POST['hr_reset_totp'] ) && has_totp( $uid ) ) {
        totp_disable( $uid );
        mail_security_notice( $user, 'Two-step verification was turned off', 'An administrator turned off two-step verification for your account at your request.' );
        log_security( 'admin_reset_totp', array( 'target' => $uid ) );
    }
    if ( ! empty( $_POST['hr_reset_passkeys'] ) && passkey_count( $uid ) ) {
        passkeys_delete_all( $uid );
        mail_security_notice( $user, 'Your passkeys were removed', 'An administrator removed the passkeys from your account at your request.' );
        log_security( 'admin_reset_passkeys', array( 'target' => $uid ) );
    }
} );

// ---------------------------------------------------------------------------------------------------------------
// Core registration is closed; send anyone looking for it to ours.
// ---------------------------------------------------------------------------------------------------------------

add_action( 'login_form_register', function () {
    wp_safe_redirect( account_url( 'register' ) );
    exit;
} );
add_filter( 'register_url', fn() => account_url( 'register' ) );

// Same for "Lost your password?": core's form has no rate limit (anyone could flood an inbox with reset emails); ours
// has, and its links work for staff too. Reset links already issued by core (wp-login.php?action=rp) keep working.
foreach ( array( 'login_form_lostpassword', 'login_form_retrievepassword' ) as $hr_accounts_hook ) {
    add_action( $hr_accounts_hook, function () {
        if ( isset( $_GET['error'] ) && in_array( $_GET['error'], array( 'expiredkey', 'invalidkey' ), true ) ) {
            flash( 'reset_expired' ); // core's own reset link was stale
        }
        wp_safe_redirect( account_url( 'forgot' ), 303 );
        exit;
    } );
}
unset( $hr_accounts_hook );
add_filter( 'lostpassword_url', fn() => account_url( 'forgot' ) );

// A password reset through core's wp-login.php signs out every session too, as ours does.
add_action( 'after_password_reset', function ( $user ) {
    if ( $user instanceof \WP_User ) {
        \WP_Session_Tokens::get_instance( $user->ID )->destroy_all();
    }
}, 5 );

// An administrator deleting a user (Users screen, WP-CLI) is an account deletion too: let modules purge that person's
// data first, as they do for self-service deletion (which has already fired the action).
add_action( 'delete_user', function ( $uid ) {
    $uid = (int) $uid;
    if ( empty( $GLOBALS['hr_accounts_deleted'][ $uid ] ) ) {
        $GLOBALS['hr_accounts_deleted'][ $uid ] = true;
        do_action( 'hr_account_deleted', $uid );
    }
}, 1 );

// ---------------------------------------------------------------------------------------------------------------
// Tools → Export/Erase Personal Data
// ---------------------------------------------------------------------------------------------------------------

add_filter( 'wp_privacy_personal_data_exporters', function ( $exporters ) {
    $exporters['hr-accounts'] = array(
        'exporter_friendly_name' => 'Headline Recorder account security',
        'callback'               => __NAMESPACE__ . '\\privacy_export',
    );
    return $exporters;
} );

function privacy_export( string $email, int $page = 1 ): array {
    $user = get_user_by( 'email', $email );
    if ( ! $user ) {
        return array( 'data' => array(), 'done' => true );
    }
    $uid  = (int) $user->ID;
    $data = array(
        array( 'name' => 'Email verified', 'value' => is_verified( $uid ) ? 'Yes' : 'No' ),
        array( 'name' => 'Two-step verification', 'value' => has_totp( $uid ) ? 'On' : 'Off' ),
        array( 'name' => 'Recovery codes remaining', 'value' => (string) recovery_remaining( $uid ) ),
        array( 'name' => 'Known devices', 'value' => (string) count( (array) get_user_meta( $uid, 'hr_devices', true ) ) ),
    );
    $items = array( array( 'group_id' => 'hr-account-security', 'group_label' => 'Account security', 'item_id' => 'security-' . $uid, 'data' => $data ) );
    foreach ( passkeys_for_user( $uid ) as $p ) {
        $items[] = array(
            'group_id'    => 'hr-passkeys',
            'group_label' => 'Passkeys',
            'item_id'     => 'passkey-' . (int) $p->id,
            'data'        => array(
                array( 'name' => 'Name', 'value' => $p->name ),
                array( 'name' => 'Created', 'value' => $p->created_at ),
                array( 'name' => 'Last used', 'value' => $p->last_used_at ?: 'Never' ),
            ),
        );
    }
    return array( 'data' => $items, 'done' => true );
}

add_filter( 'wp_privacy_personal_data_erasers', function ( $erasers ) {
    $erasers['hr-accounts'] = array(
        'eraser_friendly_name' => 'Headline Recorder account security',
        'callback'             => __NAMESPACE__ . '\\privacy_erase',
    );
    return $erasers;
} );

function privacy_erase( string $email, int $page = 1 ): array {
    $user = get_user_by( 'email', $email );
    if ( ! $user ) {
        return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true );
    }
    $uid = (int) $user->ID;
    passkeys_delete_all( $uid );
    totp_disable( $uid );
    foreach ( array( 'hr_devices', 'hr_stepup', 'hr_verify', 'hr_email_change', 'hr_last_login', 'hr_prev_login', 'hr_pw_checked', 'hr_pw_policy' ) as $key ) {
        delete_user_meta( $uid, $key );
    }
    return array( 'items_removed' => true, 'items_retained' => false, 'messages' => array( 'Passkeys, two-step verification and known devices were removed.' ), 'done' => true );
}

// Readers who sign in through wp-login.php go to their account, not to a bounce through wp-admin.
add_filter( 'login_redirect', function ( $redirect, $requested, $user ) {
    if ( $user instanceof \WP_User && ! is_staff( $user ) && ( '' === $requested || str_contains( (string) $redirect, '/wp-admin' ) ) ) {
        return account_url();
    }
    return $redirect;
}, 20, 3 );
