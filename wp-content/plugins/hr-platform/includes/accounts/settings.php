<?php
/**
 * Accounts: /account/settings/ — profile, email change, password, security (passkeys, two-step verification,
 * recovery codes, sessions, score) and privacy (export, delete). Every handler acts on the signed-in user only;
 * object ids from the form (passkey id, session id) are always matched together with that user's id.
 */

namespace HR\Platform\Accounts;

use function HR\Platform\no_store;
use function HR\Platform\rate_limit;
use function HR\Platform\log_security;
use function HR\Platform\render;

if ( ! defined( 'ABSPATH' ) ) exit;

const SETTINGS_SECTIONS = array( 'profile', 'email', 'password', 'security', 'privacy' );

/** Actions that need a password/passkey/code confirmation from the last 10 minutes. */
const STEPUP_ACTIONS = array( 'email', 'totp_start', 'totp_confirm', 'totp_disable', 'recovery_regen', 'passkey_delete', 'export', 'delete_account' );

function route_settings( \WP_User $user ): void {
    $state = array( 'errors' => array(), 'codes' => null, 'values' => array() );
    if ( is_post() ) {
        $state = handle_settings_post( $user ) + $state;
        $user  = get_userdata( $user->ID ) ?: $user;
    }
    if ( 'staff' === get_str( 'enrol', 10 ) && is_staff( $user ) && ! has_mfa( (int) $user->ID ) ) {
        $state['staff_notice'] = true;
    }
    render_app( $user, 'settings', 'Settings', static function () use ( $user, $state ) {
        render( 'account/settings', settings_view_data( $user, $state ) );
    }, array( 'strength', 'totp' ) );
}

function settings_redirect( string $anchor, string $flash_key = '' ): void {
    if ( '' !== $flash_key ) {
        flash( $flash_key );
    }
    redirect( account_url( 'settings' ) . '#' . $anchor );
}

function handle_settings_post( \WP_User $user ): array {
    $action = posted_action();
    $uid    = (int) $user->ID;
    if ( 'resend_verify' === $action ) {
        handle_resend( $user );
        settings_redirect( 'email' );
    }
    if ( ! verify_form( $action ) ) {
        return array( 'errors' => array( 'csrf' => array( '_form' => flash_messages()['csrf'][1] ) ) );
    }
    if ( in_array( $action, array( 'stepup_password', 'stepup_totp' ), true ) ) {
        return handle_stepup( $user, $action );
    }
    if ( in_array( $action, STEPUP_ACTIONS, true ) && ! require_recent_auth() ) {
        $anchor = section_for( $action );
        return array( 'errors' => array( $anchor => array( '_form' => 'For your security, confirm it is you first.' ) ) );
    }
    switch ( $action ) {
        case 'profile':
            return handle_profile( $user );
        case 'email':
            return handle_email_change( $user );
        case 'email_cancel':
            delete_user_meta( $uid, 'hr_email_change' );
            settings_redirect( 'email' );
            break;
        case 'password':
            return handle_password_change( $user );
        case 'totp_start':
            totp_begin( $uid );
            settings_redirect( 'two-step' );
            break;
        case 'totp_cancel':
            delete_user_meta( $uid, 'hr_totp_pending' );
            settings_redirect( 'two-step' );
            break;
        case 'totp_confirm':
            if ( null === totp_pending_secret( $uid ) ) {
                settings_redirect( 'two-step', 'totp_expired' );
            }
            if ( ! rate_limit( 'totp-setup', (string) $uid, 10, 15 * MINUTE_IN_SECONDS ) ) {
                return array( 'errors' => array( 'totp' => array( 'totp_code' => 'Too many attempts. Please wait 15 minutes.' ) ) );
            }
            $codes = totp_activate( $uid, post_str( 'totp_code', 20 ) );
            if ( null === $codes ) {
                return array( 'errors' => array( 'totp' => array( 'totp_code' => 'That code did not match. Enter the newest 6-digit code from your app.' ) ) );
            }
            mail_security_notice( $user, 'Two-step verification is on', 'Two-step verification was turned on for your Headline Recorder account. You will be asked for a code from your authenticator app when you sign in with your password.' );
            log_security( 'totp_enabled', array( 'uid' => $uid ) );
            return array( 'codes' => $codes, 'codes_new' => true );
        case 'totp_disable':
            totp_disable( $uid );
            mail_security_notice( $user, 'Two-step verification was turned off', 'Two-step verification was turned off for your Headline Recorder account, and its recovery codes were cancelled.' );
            log_security( 'totp_disabled', array( 'uid' => $uid ) );
            settings_redirect( 'two-step', 'totp_disabled' );
            break;
        case 'recovery_regen':
            if ( ! has_totp( $uid ) ) {
                settings_redirect( 'two-step' );
            }
            $codes = recovery_generate( $uid );
            mail_security_notice( $user, 'New recovery codes were created', 'A new set of recovery codes was created for your account. The old codes no longer work.' );
            return array( 'codes' => $codes );
        case 'passkey_rename':
            $ok = passkey_rename( $uid, (int) post_str( 'passkey_id', 20 ), post_str( 'passkey_name', 80 ) );
            settings_redirect( 'passkeys', $ok ? 'passkey_renamed' : '' );
            break;
        case 'passkey_delete':
            if ( passkey_delete( $uid, (int) post_str( 'passkey_id', 20 ) ) ) {
                mail_security_notice( $user, 'A passkey was removed from your account', 'A passkey was removed from your Headline Recorder account and can no longer be used to sign in.' );
                log_security( 'passkey_removed', array( 'uid' => $uid ) );
                settings_redirect( 'passkeys', 'passkey_removed' );
            }
            settings_redirect( 'passkeys' );
            break;
        case 'session_revoke':
            revoke_session( $uid, post_str( 'session', 40 ) );
            settings_redirect( 'sessions', 'session_out' );
            break;
        case 'sessions_others':
            \WP_Session_Tokens::get_instance( $uid )->destroy_others( current_session_token() );
            log_security( 'sessions_destroy_others', array( 'uid' => $uid ) );
            settings_redirect( 'sessions', 'others_out' );
            break;
        case 'export':
            if ( ! rate_limit( 'export', (string) $uid, 5, HOUR_IN_SECONDS ) ) {
                return array( 'errors' => array( 'privacy' => array( '_form' => flash_messages()['rate_limited'][1] ) ) );
            }
            stream_export( $user );
            break;
        case 'delete_account':
            return handle_delete( $user );
    }
    return array();
}

function section_for( string $action ): string {
    if ( 'email' === $action ) {
        return 'email';
    }
    if ( in_array( $action, array( 'export', 'delete_account' ), true ) ) {
        return 'privacy';
    }
    return 'security';
}

function handle_stepup( \WP_User $user, string $action ): array {
    $anchor = post_str( 'return', 20 );
    $anchor = in_array( $anchor, array( 'email', 'security', 'privacy', 'passkeys', 'two-step' ), true ) ? $anchor : 'security';
    $uid    = (int) $user->ID;
    if ( 'stepup_password' === $action ) {
        $pwd = post_str( 'stepup_pwd', 1024 );
        if ( ! rate_limit( 'stepup', (string) $uid, 5, 15 * MINUTE_IN_SECONDS ) ) {
            $GLOBALS['hr_accounts_stepup_errors'][ $anchor ] = array( 'stepup_pwd' => 'Too many attempts. Please wait 15 minutes.' );
            return array();
        }
        if ( '' !== $pwd && wp_check_password( $pwd, $user->user_pass, $uid ) ) {
            mark_recent_auth( $uid );
            settings_redirect( $anchor, 'stepup_ok' );
        }
        log_security( 'stepup_failed', array( 'uid' => $uid ) );
        $GLOBALS['hr_accounts_stepup_errors'][ $anchor ] = array( 'stepup_pwd' => 'That password is not right.' );
        return array();
    }
    $result = verify_second_factor( $uid, post_str( 'stepup_code', 40 ) );
    if ( $result && 'limited' !== $result ) {
        mark_recent_auth( $uid );
        settings_redirect( $anchor, 'stepup_ok' );
    }
    $GLOBALS['hr_accounts_stepup_errors'][ $anchor ] = array( 'stepup_code' => 'limited' === $result ? 'Too many attempts. Please wait 15 minutes.' : 'That code is not right.' );
    return array();
}

function validate_display_name( string $name ): ?string {
    $len = mb_strlen( $name );
    if ( $len < 2 ) {
        return 'Enter your name (at least 2 characters).';
    }
    if ( $len > 60 ) {
        return 'Your name can be up to 60 characters.';
    }
    if ( preg_match( '#https?://|www\.|[<>{}]|\b[a-z0-9-]+\.(?:com|net|org|io|ru|xyz|top|info|biz|pk|co|app|link|site|online|shop|click)\b#i', $name ) ) {
        return 'Your name cannot contain links or special characters.';
    }
    return null;
}

function handle_profile( \WP_User $user ): array {
    $name = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( post_str( 'display_name', 60 ) ) ) );
    if ( $problem = validate_display_name( $name ) ) {
        return array( 'errors' => array( 'profile' => array( 'display_name' => $problem ) ), 'values' => array( 'display_name' => mb_substr( $name, 0, 60 ) ) );
    }
    if ( ! rate_limit( 'profile', (string) $user->ID, 20, HOUR_IN_SECONDS ) ) {
        return array( 'errors' => array( 'profile' => array( '_form' => flash_messages()['rate_limited'][1] ) ) );
    }
    wp_update_user( array( 'ID' => $user->ID, 'display_name' => $name, 'nickname' => $name ) );
    settings_redirect( 'profile', 'profile_saved' );
    return array();
}

function handle_email_change( \WP_User $user ): array {
    $email = strtolower( trim( post_str( 'new_email', 100 ) ) );
    $err   = null;
    if ( '' === $email || strlen( $email ) > 100 || ! is_email( $email ) || sanitize_email( $email ) !== $email ) {
        $err = 'Enter a valid email address, like name@example.com.';
    } elseif ( 0 === strcasecmp( $email, $user->user_email ) ) {
        $err = 'That is already your email address.';
    }
    if ( $err ) {
        return array( 'errors' => array( 'email' => array( 'new_email' => $err ) ), 'values' => array( 'new_email' => substr( $email, 0, 100 ) ) );
    }
    if ( ! rate_limit( 'email-change', (string) $user->ID, 3, HOUR_IN_SECONDS ) ) {
        return array( 'errors' => array( 'email' => array( '_form' => flash_messages()['rate_limited'][1] ) ) );
    }
    // Same answer whether or not the address is taken, so this form cannot be used to look people up.
    if ( ! email_exists( $email ) ) {
        $token = issue_token( (int) $user->ID, 'hr_email_change', array( 'email' => $email ) );
        mail_email_change( $user, $email, add_query_arg( array( 'a' => 'email', 'u' => (int) $user->ID, 't' => $token ), account_url( 'verify' ) ) );
    } else {
        log_security( 'email_change_taken', array( 'uid' => (int) $user->ID ) );
    }
    settings_redirect( 'email', 'email_sent' );
    return array();
}

/** Did this session start with "Keep me signed in"? Keeps that choice when the cookie is re-issued. */
function session_remembered( int $uid ): bool {
    $session = \WP_Session_Tokens::get_instance( $uid )->get( current_session_token() );
    return is_array( $session ) && ( (int) $session['expiration'] - (int) $session['login'] ) > 2 * DAY_IN_SECONDS + HOUR_IN_SECONDS;
}

function handle_password_change( \WP_User $user ): array {
    $uid     = (int) $user->ID;
    $current = post_str( 'current_password', 1024 );
    $new     = post_str( 'new_password', 1024 );
    $errors  = array();
    if ( ! rate_limit( 'pw-change', (string) $uid, 5, 15 * MINUTE_IN_SECONDS ) ) {
        return array( 'errors' => array( 'password' => array( '_form' => flash_messages()['rate_limited'][1] ) ) );
    }
    if ( '' === $current || ! wp_check_password( $current, $user->user_pass, $uid ) ) {
        $errors['current_password'] = 'That is not your current password.';
    }
    $problem = '' === $new ? 'Choose a new password.' : password_problem( $new, $user );
    if ( ! $problem && $new === $current ) {
        $problem = 'Choose a password that is different from your current one.';
    }
    if ( $problem ) {
        $errors['new_password'] = $problem;
    }
    if ( $errors ) {
        return array( 'errors' => array( 'password' => $errors ) );
    }
    $remember = session_remembered( $uid );
    wp_set_password( $new, $uid );
    \WP_Session_Tokens::get_instance( $uid )->destroy_all();
    unset( $GLOBALS['hr_accounts_new_token'] );
    $GLOBALS['hr_accounts_login_method'] = 'password';
    wp_set_auth_cookie( $uid, $remember, is_ssl() ); // a new session for this browser only
    wp_set_current_user( $uid );
    mark_recent_auth( $uid );
    note_password_set( $uid );
    mail_security_notice( $user, 'Your password was changed', 'The password for your Headline Recorder account was changed, and your other devices were signed out.' );
    log_security( 'password_changed', array( 'uid' => $uid ) );
    settings_redirect( 'password', 'password_saved' );
    return array();
}

// ---------------------------------------------------------------------------------------------------------------
// Sessions (WP_Session_Tokens)
// ---------------------------------------------------------------------------------------------------------------

function sessions_list( int $uid ): array {
    $raw     = get_user_meta( $uid, 'session_tokens', true );
    $current = session_key();
    $out     = array();
    foreach ( is_array( $raw ) ? $raw : array() as $verifier => $s ) {
        if ( ! is_array( $s ) || (int) ( $s['expiration'] ?? 0 ) < time() ) {
            continue;
        }
        $out[] = array(
            'id'         => substr( mac( 'session', (string) $verifier ), 0, 24 ),
            'login'      => (int) ( $s['login'] ?? 0 ),
            'expiration' => (int) $s['expiration'],
            'device'     => describe_ua( (string) ( $s['ua'] ?? '' ) ),
            'method'     => (string) ( $s['hr_method'] ?? '' ),
            'current'    => '' !== $current && hash_equals( (string) $verifier, $current ),
        );
    }
    usort( $out, static fn( $a, $b ) => array( $b['current'], $b['login'] ) <=> array( $a['current'], $a['login'] ) );
    return $out;
}

/** Sign out one other session. Matching is by keyed hash of the verifier, inside this user's own sessions. */
function revoke_session( int $uid, string $id ): void {
    if ( ! \WP_Session_Tokens::get_instance( $uid ) instanceof \WP_User_Meta_Session_Tokens ) {
        return;
    }
    $raw = get_user_meta( $uid, 'session_tokens', true );
    if ( ! is_array( $raw ) ) {
        return;
    }
    foreach ( array_keys( $raw ) as $verifier ) {
        if ( hash_equals( substr( mac( 'session', (string) $verifier ), 0, 24 ), $id ) && ! hash_equals( (string) $verifier, session_key() ) ) {
            unset( $raw[ $verifier ] );
            $raw ? update_user_meta( $uid, 'session_tokens', $raw ) : delete_user_meta( $uid, 'session_tokens' );
            log_security( 'session_revoked', array( 'uid' => $uid ) );
            return;
        }
    }
}

// ---------------------------------------------------------------------------------------------------------------
// Security score — honest: each item is either verifiably done or not.
// ---------------------------------------------------------------------------------------------------------------

function security_checklist( \WP_User $user ): array {
    $uid      = (int) $user->ID;
    $settings = account_url( 'settings' );
    $pw_at    = (int) get_user_meta( $uid, 'hr_pw_checked', true );
    $pw_ok    = (int) get_user_meta( $uid, 'hr_pw_policy', true );
    $hibp     = (bool) \HR\Platform\setting( 'accounts_hibp' );
    if ( $pw_at > 0 ) {
        $pw_detail = 'Checked against breach lists on ' . fmt_time( $pw_at, false ) . '.';
    } elseif ( $pw_ok > 0 && ! $hibp ) {
        $pw_detail = 'Meets our password rules (set ' . fmt_time( $pw_ok, false ) . ').';
    } elseif ( $pw_ok > 0 ) {
        $pw_detail = 'Meets our rules, but the breach check was unavailable when you set it. Change it once to run the check.';
    } else {
        $pw_detail = 'We have not checked this password yet. Change it once to make sure it is not in a breach list.';
    }
    return array(
        array(
            'key'    => 'email',
            'done'   => is_verified( $uid ),
            'title'  => 'Confirm your email',
            'detail' => is_verified( $uid ) ? 'Confirmed — you can always recover your account.' : 'So you can recover your account and get security alerts.',
            'url'    => $settings . '#email',
            'cta'    => 'Confirm',
        ),
        array(
            'key'    => 'password',
            'done'   => $pw_at > 0 || ( $pw_ok > 0 && ! $hibp ),
            'title'  => 'Strong, unique password',
            'detail' => $pw_detail,
            'url'    => $settings . '#password',
            'cta'    => 'Update',
        ),
        array(
            'key'    => 'passkey',
            'done'   => passkey_count( $uid ) > 0,
            'title'  => 'Add a passkey',
            'detail' => passkey_count( $uid ) > 0 ? sprintf( _n( '%d passkey — phishing-proof sign-in.', '%d passkeys — phishing-proof sign-in.', passkey_count( $uid ) ), passkey_count( $uid ) ) : 'Sign in with your fingerprint, face or screen lock. Cannot be phished.',
            'url'    => $settings . '#passkeys',
            'cta'    => 'Add',
        ),
        array(
            'key'    => 'totp',
            'done'   => has_totp( $uid ),
            'title'  => 'Two-step verification',
            'detail' => has_totp( $uid ) ? 'On — a code from your phone protects password sign-ins.' : 'A code from your phone stops anyone who learns your password.',
            'url'    => $settings . '#two-step',
            'cta'    => 'Turn on',
        ),
    );
}

function security_level( int $score ): array {
    return array(
        array( 'At risk', 'Start with the first step below — each one takes about a minute.' ),
        array( 'Basic', 'A good start. A passkey is the single biggest upgrade you can make.' ),
        array( 'Good', 'Solid. One or two more steps and your account is very hard to break into.' ),
        array( 'Strong', 'Excellent protection. One last step for a perfect score.' ),
        array( 'Fortress', 'Every protection is on. Your account is as safe as we can make it.' ),
    )[ max( 0, min( 4, $score ) ) ];
}

// ---------------------------------------------------------------------------------------------------------------
// Privacy: export and delete
// ---------------------------------------------------------------------------------------------------------------

function build_export( \WP_User $user ): array {
    $uid  = (int) $user->ID;
    $data = array(
        'generated_at' => gmdate( 'c' ),
        'site'         => home_url( '/' ),
        'profile'      => array(
            'id'             => $uid,
            'username'       => $user->user_login,
            'email'          => $user->user_email,
            'display_name'   => $user->display_name,
            'registered'     => mysql2date( 'c', $user->user_registered, false ),
            'email_verified' => is_verified( $uid ),
        ),
        'security'     => array(
            'two_step_verification'    => has_totp( $uid ),
            'two_step_enabled_at'      => has_totp( $uid ) ? gmdate( 'c', (int) get_user_meta( $uid, 'hr_totp_on', true ) ) : null,
            'recovery_codes_remaining' => recovery_remaining( $uid ),
            'passkeys'                 => array_map( static fn( $p ) => array(
                'name'      => $p->name,
                'provider'  => aaguid_name( (string) $p->aaguid ) ?: null,
                'created'   => mysql2date( 'c', $p->created_at, false ),
                'last_used' => $p->last_used_at ? mysql2date( 'c', $p->last_used_at, false ) : null,
            ), passkeys_for_user( $uid ) ),
            'known_devices'            => count( (array) get_user_meta( $uid, 'hr_devices', true ) ),
            'sessions'                 => array_map( static fn( $s ) => array(
                'device'  => $s['device'],
                'started' => gmdate( 'c', $s['login'] ),
                'expires' => gmdate( 'c', $s['expiration'] ),
            ), sessions_list( $uid ) ),
        ),
    );
    return (array) apply_filters( 'hr_account_export', $data, $uid );
}

function stream_export( \WP_User $user ): void {
    $json = wp_json_encode( build_export( $user ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
    log_security( 'account_export', array( 'uid' => (int) $user->ID ) );
    no_store();
    header( 'Content-Type: application/json; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="headline-recorder-account-' . gmdate( 'Y-m-d' ) . '.json"' );
    header( 'Content-Length: ' . strlen( (string) $json ) );
    echo $json; // phpcs:ignore -- JSON download, served as an attachment
    exit;
}

function handle_delete( \WP_User $user ): array {
    $uid = (int) $user->ID;
    if ( is_staff( $user ) ) {
        return array( 'errors' => array( 'privacy' => array( '_form' => 'Staff accounts are closed by an administrator.' ) ) );
    }
    if ( 'DELETE' !== trim( post_str( 'confirm_delete', 20 ) ) ) {
        return array( 'errors' => array( 'privacy' => array( 'confirm_delete' => 'Type DELETE in capital letters to confirm.' ) ) );
    }
    $email = $user->user_email;
    do_action( 'hr_account_deleted', $uid ); // modules purge their rows while the user still exists
    passkeys_delete_all( $uid );
    \WP_Session_Tokens::get_instance( $uid )->destroy_all();
    wp_clear_auth_cookie();
    require_once ABSPATH . 'wp-admin/includes/user.php';
    // Anything already published (e.g. an accepted op-ed) stays on the site, credited to the publication;
    // wp_delete_user() would otherwise delete those posts too.
    $reassign = null;
    if ( count_user_posts( $uid, get_post_types( array( 'public' => true ) ) ) > 0 ) {
        $admins   = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID', 'orderby' => 'ID' ) );
        $reassign = $admins ? (int) $admins[0] : null;
    }
    wp_delete_user( $uid, $reassign );
    wp_set_current_user( 0 );
    mail_goodbye( $email );
    log_security( 'account_deleted', array( 'uid' => $uid ) );
    flash( 'deleted' );
    redirect( login_url() );
    return array();
}

function settings_view_data( \WP_User $user, array $state ): array {
    $uid     = (int) $user->ID;
    $pending = get_user_meta( $uid, 'hr_email_change', true );
    $pending = is_array( $pending ) && (int) ( $pending['exp'] ?? 0 ) > time() ? (string) $pending['email'] : '';
    $setup   = null;
    if ( ! has_totp( $uid ) && require_recent_auth() ) {
        $secret = totp_pending_secret( $uid );
        if ( null !== $secret ) {
            $setup = array( 'secret' => base32_encode( $secret ), 'account' => $user->user_email, 'issuer' => 'Headline Recorder' );
            sodium_memzero( $secret );
        }
    }
    $checklist = security_checklist( $user );
    $score     = count( array_filter( array_column( $checklist, 'done' ) ) );
    return array(
        'user'          => $user,
        'errors'        => $state['errors'],
        'values'        => $state['values'],
        'codes'         => $state['codes'],
        'codes_new'     => ! empty( $state['codes_new'] ),
        'staff_notice'  => ! empty( $state['staff_notice'] ),
        'verified'      => is_verified( $uid ),
        'pending_email' => $pending,
        'recent'        => require_recent_auth(),
        'recent_left'   => recent_auth_remaining(),
        'passkeys'      => passkeys_for_user( $uid ),
        'totp_on'       => has_totp( $uid ),
        'totp_since'    => (int) get_user_meta( $uid, 'hr_totp_on', true ),
        'recovery_left' => recovery_remaining( $uid ),
        'setup'         => $setup,
        'sessions'      => sessions_list( $uid ),
        'checklist'     => $checklist,
        'score'         => $score,
        'level'         => security_level( $score ),
        'staff'         => is_staff( $user ),
    );
}
