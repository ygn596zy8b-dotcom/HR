<?php
/**
 * Accounts: sign in (password → optional second step), the shared success path, new-device alerts and sign out.
 *
 * wp_authenticate() (not wp_signon) so the throttle, AIOS and other 'authenticate' hooks run but no cookie is set
 * until the second factor (if any) has been checked.
 */

namespace HR\Platform\Accounts;

use function HR\Platform\no_store;
use function HR\Platform\rate_limit;
use function HR\Platform\rate_limit_reset;
use function HR\Platform\log_security;
use function HR\Platform\setting;

if ( ! defined( 'ABSPATH' ) ) exit;

/** Everything that must happen when someone has fully proved who they are. */
function complete_login( \WP_User $user, bool $remember, string $method ): void {
    $GLOBALS['hr_accounts_login_method'] = $method;
    wp_set_auth_cookie( $user->ID, $remember, is_ssl() );
    wp_set_current_user( $user->ID );
    do_action( 'wp_login', $user->user_login, $user );
    foreach ( array_unique( array( strtolower( $user->user_login ), strtolower( $user->user_email ), strtolower( (string) ( $GLOBALS['hr_accounts_typed_login'] ?? '' ) ) ) ) as $key ) {
        if ( '' !== $key ) {
            rate_limit_reset( 'login-user', $key );
        }
    }
    log_security( 'login', array( 'uid' => (int) $user->ID, 'method' => $method ) );
}

// ---------------------------------------------------------------------------------------------------------------
// Known devices: a random id in a long-lived HttpOnly cookie; the account stores keyed hashes of the last 10.
// Runs on every sign-in path (our forms, passkeys, wp-login.php).
// ---------------------------------------------------------------------------------------------------------------

function device_id(): string {
    $id = read_cookie( 'hr_dev', 64 );
    if ( 43 !== strlen( $id ) || ! \HR\Platform\is_b64url( $id ) ) {
        $id = random_token();
        set_cookie( 'hr_dev', $id, YEAR_IN_SECONDS, cookie_path(), 'Lax' );
    }
    return $id;
}

add_action( 'wp_login', function ( $login, $user ) {
    if ( ! $user instanceof \WP_User || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
        return;
    }
    $hash  = substr( mac( 'device:' . $user->ID, device_id() ), 0, 32 );
    $known = get_user_meta( $user->ID, 'hr_devices', true );
    $known = is_array( $known ) ? $known : array();
    $new   = ! isset( $known[ $hash ] );
    $first = ! $known; // the very first device we record is not "new"
    $known[ $hash ] = time();
    arsort( $known );
    update_user_meta( $user->ID, 'hr_devices', array_slice( $known, 0, 10, true ) );
    update_user_meta( $user->ID, 'hr_last_login', time() );
    if ( $new && ! $first && setting( 'accounts_new_device_alerts' ) && rate_limit( 'newdev-mail', (string) $user->ID, 5, DAY_IN_SECONDS ) ) {
        mail_new_device( $user, describe_ua( current_ua() ) );
    }
}, 20, 2 );

// ---------------------------------------------------------------------------------------------------------------
// /account/login/
// ---------------------------------------------------------------------------------------------------------------

function route_login(): void {
    if ( is_user_logged_in() ) {
        redirect( requested_redirect() ?: account_url() );
    }
    $errors = array();
    $values = array( 'log' => '', 'remember' => false );
    if ( is_post() ) {
        if ( ! verify_form( 'login' ) ) {
            $errors['_form'] = flash_messages()['csrf'][1];
        } else {
            $log      = trim( post_str( 'log', 100 ) );
            $pwd      = post_str( 'pwd', 1024 );
            $remember = ! empty( $_POST['remember'] );
            $values   = array( 'log' => $log, 'remember' => $remember );
            if ( '' === $log ) {
                $errors['log'] = 'Enter your email address.';
            } elseif ( strlen( $log ) > 100 ) {
                $errors['log'] = 'That email address is too long.';
            }
            if ( '' === $pwd ) {
                $errors['pwd'] = 'Enter your password.';
            }
            if ( ! $errors ) {
                $result = attempt_password_login( $log, $pwd );
                if ( $result instanceof \WP_User ) {
                    $redirect = requested_redirect() ?: account_url();
                    if ( has_totp( (int) $result->ID ) ) {
                        start_second_factor( $result, $remember, $redirect );
                    }
                    complete_login( $result, $remember, 'password' );
                    redirect( $redirect );
                }
                $errors['_form'] = $result;
            }
        }
        status_header( 400 );
    }
    render_auth( 'login', array( 'errors' => $errors, 'values' => $values, 'redirect_to' => requested_redirect() ), 'Sign in', 'login' );
}

/** WP_User on success, otherwise a message that never reveals whether the account exists. */
function attempt_password_login( string $log, string $pwd ) {
    $GLOBALS['hr_accounts_typed_login']   = $log;
    $GLOBALS['hr_accounts_internal_auth'] = true;
    $user = wp_authenticate( $log, $pwd );
    unset( $GLOBALS['hr_accounts_internal_auth'] );
    if ( $user instanceof \WP_User ) {
        return $user;
    }
    $codes = is_wp_error( $user ) ? $user->get_error_codes() : array();
    if ( array_intersect( $codes, array( 'invalid_username', 'invalid_email' ) ) ) {
        burn_password_check( $pwd ); // same cost as a wrong password for a real account
    }
    if ( in_array( 'hr_rate_limited', $codes, true ) ) {
        return 'Too many sign-in attempts. Please wait 15 minutes and try again, or reset your password.';
    }
    return 'The email or password is incorrect.';
}

/** Password was right and the account has two-step verification: no cookie yet, a 5-minute challenge instead. */
function start_second_factor( \WP_User $user, bool $remember, string $redirect ): void {
    $token = random_token();
    set_transient( 'hr_2fa_' . substr( hash( 'sha256', $token ), 0, 40 ), array(
        'uid'      => (int) $user->ID,
        'remember' => $remember,
        'redirect' => $redirect,
        'attempts' => 0,
        'exp'      => time() + 5 * MINUTE_IN_SECONDS,
    ), 5 * MINUTE_IN_SECONDS );
    set_cookie( 'hr_2fa', $token, 5 * MINUTE_IN_SECONDS, cookie_path( 'account/' ), 'Strict' );
    log_security( 'login_second_step', array( 'uid' => (int) $user->ID ) );
    redirect( account_url( 'two-factor' ) );
}

function pending_challenge(): ?array {
    $token = read_cookie( 'hr_2fa', 64 );
    if ( 43 !== strlen( $token ) ) {
        return null;
    }
    $key   = 'hr_2fa_' . substr( hash( 'sha256', $token ), 0, 40 );
    $state = get_transient( $key );
    if ( ! is_array( $state ) || (int) $state['exp'] < time() ) {
        return null;
    }
    $state['key'] = $key;
    return $state;
}

function end_challenge( ?array $state ): void {
    if ( $state ) {
        delete_transient( $state['key'] );
    }
    set_cookie( 'hr_2fa', '', 0, cookie_path( 'account/' ), 'Strict' );
}

// ---------------------------------------------------------------------------------------------------------------
// /account/two-factor/
// ---------------------------------------------------------------------------------------------------------------

function route_two_factor(): void {
    if ( is_user_logged_in() ) {
        redirect( account_url() );
    }
    $state = pending_challenge();
    if ( ! $state ) {
        end_challenge( null );
        flash( 'login_timeout' );
        redirect( login_url() );
    }
    $method = 'recovery' === get_str( 'method', 10 ) || 'recovery' === post_str( 'method', 10 ) ? 'recovery' : 'totp';
    $errors = array();
    if ( is_post() ) {
        if ( ! verify_form( 'two_factor' ) ) {
            $errors['_form'] = flash_messages()['csrf'][1];
        } else {
            $code = trim( post_str( 'code', 40 ) );
            $state['attempts']++;
            if ( $state['attempts'] > 5 ) {
                end_challenge( $state );
                flash( 'login_timeout' );
                redirect( login_url() );
            }
            set_transient( $state['key'], array_diff_key( $state, array( 'key' => 1 ) ), max( 1, (int) $state['exp'] - time() ) );
            if ( '' === $code ) {
                $errors['code'] = 'recovery' === $method ? 'Enter one of your recovery codes.' : 'Enter the 6-digit code from your authenticator app.';
            } else {
                $result = verify_second_factor( (int) $state['uid'], $code );
                if ( 'limited' === $result ) {
                    $errors['_form'] = 'Too many attempts. Please wait 15 minutes and sign in again.';
                } elseif ( $result ) {
                    $user = get_userdata( (int) $state['uid'] );
                    end_challenge( $state );
                    if ( $user ) {
                        complete_login( $user, ! empty( $state['remember'] ), 'password+' . $result );
                        redirect( safe_redirect_target( (string) $state['redirect'], account_url() ) );
                    }
                    redirect( login_url() );
                } else {
                    $errors['code'] = 'recovery' === $method ? 'That recovery code is not valid or has been used.' : 'That code is not right. Check the time on your phone and try the newest code.';
                }
            }
        }
        status_header( 400 );
    }
    render_auth( 'two-factor', array( 'errors' => $errors, 'method' => $method, 'attempts_left' => max( 0, 5 - (int) $state['attempts'] ) ), 'Two-step verification', 'two-factor' );
}

// ---------------------------------------------------------------------------------------------------------------
// /account/logout/ — GET asks, POST (nonce) signs out. A link alone can never sign anyone out.
// ---------------------------------------------------------------------------------------------------------------

function route_logout(): void {
    if ( ! is_user_logged_in() ) {
        redirect( home_url( '/' ) );
    }
    $error = '';
    if ( is_post() ) {
        if ( verify_form( 'logout' ) ) {
            $user = wp_get_current_user();
            $all  = ! empty( $_POST['everywhere'] );
            if ( $all ) {
                \WP_Session_Tokens::get_instance( $user->ID )->destroy_all();
            }
            wp_logout();
            log_security( 'logout', array( 'uid' => (int) $user->ID, 'all' => $all ) );
            redirect( home_url( '/' ) );
        }
        $error = flash_messages()['csrf'][1];
        status_header( 400 );
    }
    render_auth( 'logout', array( 'error' => $error, 'user' => wp_get_current_user() ), 'Sign out', 'logout' );
}
