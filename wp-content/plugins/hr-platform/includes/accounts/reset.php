<?php
/**
 * Accounts: forgot password and reset.
 *
 * The emailed link carries WordPress's own reset key. On arrival the key moves into an HttpOnly SameSite=Strict
 * cookie and the browser is redirected to a clean /account/reset/, so the key never sits in history, analytics or
 * a Referer header. Resetting signs out every session and never bypasses two-step verification.
 */

namespace HR\Platform\Accounts;

use function HR\Platform\rate_limit;
use function HR\Platform\client_ip;
use function HR\Platform\log_security;

if ( ! defined( 'ABSPATH' ) ) exit;

function route_forgot(): void {
    $flash = take_flash();
    if ( $flash && 'reset_sent' === $flash['key'] ) {
        render_auth( 'forgot', array( 'sent' => true, 'email' => (string) ( $flash['data']['e'] ?? '' ), 'errors' => array(), 'value' => '' ), 'Reset your password', 'forgot' );
        return;
    }
    $errors = array();
    $value  = '';
    if ( is_post() ) {
        if ( ! verify_form( 'forgot' ) ) {
            $errors['_form'] = flash_messages()['csrf'][1];
        } else {
            $value = strtolower( trim( post_str( 'email', 100 ) ) );
            if ( '' === $value || strlen( $value ) > 100 || ! is_email( $value ) ) {
                $errors['email'] = 'Enter the email address you signed up with.';
            } elseif ( ! rate_limit( 'forgot-ip', client_ip(), 3, HOUR_IN_SECONDS ) || ! rate_limit( 'forgot-email', hash( 'sha256', $value ), 3, HOUR_IN_SECONDS ) ) {
                $errors['_form'] = 'You have asked for several reset links already. Please check your inbox (and spam folder) or try again in an hour.';
            } else {
                $user = get_user_by( 'email', $value );
                if ( $user ) {
                    $key = get_password_reset_key( $user );
                    if ( ! is_wp_error( $key ) ) {
                        mail_reset( $user, add_query_arg( array( 'key' => rawurlencode( $key ), 'login' => rawurlencode( $user->user_login ) ), account_url( 'reset' ) ) );
                    }
                }
                log_security( 'password_reset_requested' );
                flash( 'reset_sent', array( 'e' => mask_email( $value ) ) );
                redirect( account_url( 'forgot' ) );
            }
        }
        status_header( 400 );
    }
    render_auth( 'forgot', array( 'sent' => false, 'errors' => $errors, 'value' => $value ), 'Reset your password', 'forgot' );
}

function reset_cookie_user(): ?\WP_User {
    $raw = read_cookie( 'hr_rp', 400 );
    [ $login, $key ] = array_pad( explode( ':', base64_decode( $raw, true ) ?: '', 2 ), 2, '' );
    if ( '' === $login || '' === $key ) {
        return null;
    }
    $user = check_password_reset_key( $key, $login );
    return $user instanceof \WP_User ? $user : null;
}

function route_reset(): void {
    $path = cookie_path( 'account/' );
    // Step 1: link from the email → validate, stash in a cookie, redirect to the clean URL.
    if ( ! is_post() && ( isset( $_GET['key'] ) || isset( $_GET['login'] ) ) ) {
        $key   = get_str( 'key', 100 );
        $login = get_str( 'login', 60 );
        $user  = ( '' !== $key && '' !== $login && strlen( $key ) <= 100 ) ? check_password_reset_key( $key, $login ) : null;
        if ( ! $user instanceof \WP_User ) {
            set_cookie( 'hr_rp', '', 0, $path, 'Strict' );
            flash( 'reset_expired' );
            redirect( account_url( 'forgot' ) );
        }
        set_cookie( 'hr_rp', base64_encode( $login . ':' . $key ), 15 * MINUTE_IN_SECONDS, $path, 'Strict' );
        redirect( account_url( 'reset' ) );
    }

    $user = reset_cookie_user();
    if ( ! $user ) {
        if ( 'reset_done' === ( take_flash()['key'] ?? '' ) ) {
            render_auth( 'reset', array( 'done' => true ), 'Password updated', 'reset' );
            return;
        }
        set_cookie( 'hr_rp', '', 0, $path, 'Strict' );
        flash( 'reset_expired' );
        redirect( account_url( 'forgot' ) );
    }

    $errors = array();
    if ( is_post() ) {
        if ( ! verify_form( 'reset' ) ) {
            $errors['_form'] = flash_messages()['csrf'][1];
        } elseif ( ! rate_limit( 'reset-post', client_ip(), 10, HOUR_IN_SECONDS ) ) {
            $errors['_form'] = flash_messages()['rate_limited'][1];
        } else {
            $password = post_str( 'password', 1024 );
            $problem  = '' === $password ? 'Choose a new password.' : password_problem( $password, $user );
            if ( $problem ) {
                $errors['password'] = $problem;
            } else {
                // Core emails the site admin about every reset; readers resetting is not news for the admin.
                remove_action( 'after_password_reset', 'wp_password_change_notification' );
                reset_password( $user, $password );
                \WP_Session_Tokens::get_instance( $user->ID )->destroy_all();
                delete_user_meta( $user->ID, 'hr_stepup' );
                update_user_meta( $user->ID, 'hr_pw_checked', time() );
                update_user_meta( $user->ID, 'hr_email_verified', '1' ); // they just proved the inbox
                set_cookie( 'hr_rp', '', 0, $path, 'Strict' );
                mail_security_notice( $user, 'Your password was changed', 'The password for your Headline Recorder account was just reset, and every device was signed out.' );
                log_security( 'password_reset', array( 'uid' => (int) $user->ID ) );
                flash( 'reset_done' );
                redirect( account_url( 'reset' ) );
            }
        }
        status_header( 400 );
    }
    render_auth( 'reset', array( 'done' => false, 'errors' => $errors, 'user' => $user ), 'Choose a new password', 'reset', array( 'strength' ) );
}
