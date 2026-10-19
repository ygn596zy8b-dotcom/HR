<?php
/**
 * Accounts: registration and email verification (sign-up confirmation and email-change confirmation).
 *
 * Registration always ends on the same "Check your inbox" page, whether or not the address already has an
 * account (the owner of an existing address gets a "you already have an account" email instead), so the form
 * cannot be used to find out who reads Headline Recorder.
 */

namespace HR\Platform\Accounts;

use function HR\Platform\rate_limit;
use function HR\Platform\client_ip;
use function HR\Platform\log_security;

if ( ! defined( 'ABSPATH' ) ) exit;

const VERIFY_TTL = 2 * DAY_IN_SECONDS;

function route_register(): void {
    if ( is_user_logged_in() ) {
        redirect( account_url() );
    }
    $flash = take_flash();
    if ( $flash && 'registered' === $flash['key'] ) {
        render_auth( 'inbox', array( 'email' => (string) ( $flash['data']['e'] ?? '' ) ), 'Check your inbox', 'register' );
        return;
    }
    $errors = array();
    $values = array( 'display_name' => '', 'email' => '', 'terms' => false );
    if ( is_post() ) {
        $result = handle_register( $values );
        if ( true === $result ) {
            flash( 'registered', array( 'e' => mask_email( $values['email'] ) ) );
            redirect( account_url( 'register' ) );
        }
        $errors = $result;
        status_header( 400 );
    }
    render_auth( 'register', array( 'errors' => $errors, 'values' => $values, 'ts' => signed_time() ), 'Create your account', 'register', array( 'strength' ) );
}

/** @return true|array<string,string> */
function handle_register( array &$values ) {
    if ( ! verify_form( 'register' ) ) {
        return array( '_form' => flash_messages()['csrf'][1] );
    }
    if ( ! rate_limit( 'register-post', client_ip(), 30, HOUR_IN_SECONDS ) ) {
        return array( '_form' => 'Too many attempts from your network. Please try again in an hour.' );
    }
    $name     = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( post_str( 'display_name', 60 ) ) ) );
    $email    = strtolower( trim( post_str( 'email', 100 ) ) );
    $password = post_str( 'password', 1024 );
    $values   = array( 'display_name' => mb_substr( $name, 0, 60 ), 'email' => substr( $email, 0, 100 ), 'terms' => ! empty( $_POST['terms'] ) );

    // Bots: the honeypot is invisible to people; pretend everything worked.
    if ( '' !== post_str( 'website', 200 ) ) {
        log_security( 'register_honeypot' );
        return true;
    }
    $age = signed_time_age( post_str( 'hr_ts', 80 ) );
    if ( null === $age || $age > 2 * HOUR_IN_SECONDS ) {
        return array( '_form' => 'This form was open for a long time, so we refreshed it. Please submit it again.' );
    }
    if ( $age < 3 ) {
        log_security( 'register_too_fast' );
        return array( '_form' => 'That was quick! Please check your details and submit the form again.' );
    }

    $errors = array();
    $len    = mb_strlen( $name );
    if ( $len < 2 ) {
        $errors['display_name'] = 'Enter your name (at least 2 characters).';
    } elseif ( $len > 60 ) {
        $errors['display_name'] = 'Your name can be up to 60 characters.';
    } elseif ( preg_match( '#https?://|www\.|[<>{}]|\b[a-z0-9-]+\.(?:com|net|org|io|ru|xyz|top|info|biz|pk|co|app|link|site|online|shop|click)\b#i', $name ) ) {
        $errors['display_name'] = 'Your name cannot contain links or special characters.';
    }
    if ( '' === $email ) {
        $errors['email'] = 'Enter your email address.';
    } elseif ( strlen( $email ) > 100 || ! is_email( $email ) || sanitize_email( $email ) !== $email ) {
        $errors['email'] = 'Enter a valid email address, like name@example.com.';
    }
    $problem = '' === $password ? 'Choose a password.' : password_problem( $password, null );
    if ( null === $problem && '' !== $email && 0 === strcasecmp( $password, $email ) ) {
        $problem = 'Your password cannot be your email address.';
    }
    if ( $problem ) {
        $errors['password'] = $problem;
    }
    if ( empty( $_POST['terms'] ) ) {
        $errors['terms'] = 'Please agree to the Terms of Use and Privacy Policy.';
    }
    if ( $errors ) {
        return $errors;
    }
    if ( ! rate_limit( 'register', client_ip(), 5, HOUR_IN_SECONDS ) || ! rate_limit( 'register-site', 'all', 200, HOUR_IN_SECONDS ) ) {
        return array( '_form' => 'Too many accounts have been created from your network. Please try again in an hour.' );
    }

    $existing = get_user_by( 'email', $email );
    if ( $existing ) {
        // Same work and the same single email as a new sign-up, so timing does not tell the two apart.
        burn_password_check( $password );
        if ( rate_limit( 'register-exists', hash( 'sha256', $email ), 3, DAY_IN_SECONDS ) ) {
            mail_register_exists( $existing );
        }
        log_security( 'register_existing' );
        return true;
    }

    $role = (string) get_option( 'default_role', 'subscriber' );
    $obj  = get_role( $role );
    if ( ! $obj || $obj->has_cap( 'edit_posts' ) ) {
        $role = 'subscriber'; // a misconfigured default role must never hand out staff powers
    }
    $uid = wp_insert_user( array(
        'user_login'           => unique_login( $email ),
        'user_email'           => $email,
        'user_pass'            => $password,
        'display_name'         => $name,
        'nickname'             => $name,
        'role'                 => $role,
        'show_admin_bar_front' => 'false',
    ) );
    if ( is_wp_error( $uid ) ) {
        log_security( 'register_insert_failed', array( 'code' => $uid->get_error_code() ) );
        return array( '_form' => 'We could not create your account just now. Please try again in a minute.' );
    }
    update_user_meta( $uid, 'hr_email_verified', '0' );
    update_user_meta( $uid, 'hr_pw_checked', time() );
    send_verification( get_userdata( $uid ) );
    log_security( 'register', array( 'uid' => (int) $uid ) );
    return true;
}

/** People sign in with their email; the username is never shown but must still be unique. */
function unique_login( string $email ): string {
    $local = strtolower( sanitize_user( (string) strstr( $email, '@', true ), true ) );
    $local = substr( preg_replace( '/[^a-z0-9._-]/', '', $local ), 0, 40 ) ?: 'reader';
    do {
        $login = $local . '-' . bin2hex( random_bytes( 3 ) );
    } while ( username_exists( $login ) );
    return $login;
}

// ---------------------------------------------------------------------------------------------------------------
// Verification tokens: 32 random bytes in the link, only sha256(token) + expiry stored.
// ---------------------------------------------------------------------------------------------------------------

function issue_token( int $uid, string $meta_key, array $extra = array() ): string {
    $token = random_token();
    update_user_meta( $uid, $meta_key, array( 'h' => hash( 'sha256', $token ), 'exp' => time() + VERIFY_TTL ) + $extra );
    return $token;
}

function token_record( int $uid, string $meta_key, string $token ): ?array {
    if ( $uid <= 0 || 43 !== strlen( $token ) || ! \HR\Platform\is_b64url( $token ) ) {
        return null;
    }
    $rec = get_user_meta( $uid, $meta_key, true );
    if ( ! is_array( $rec ) || empty( $rec['h'] ) || (int) ( $rec['exp'] ?? 0 ) < time() ) {
        return null;
    }
    return hash_equals( (string) $rec['h'], hash( 'sha256', $token ) ) ? $rec : null;
}

function send_verification( $user ): void {
    if ( ! $user instanceof \WP_User ) {
        return;
    }
    $token = issue_token( (int) $user->ID, 'hr_verify' );
    mail_verify( $user, add_query_arg( array( 'u' => (int) $user->ID, 't' => $token ), account_url( 'verify' ) ) );
}

/** "Resend the link" (signed in, unverified). 3 per hour per account. Used from overview and gate pages. */
function handle_resend( \WP_User $user ): void {
    if ( ! verify_form( 'resend_verify' ) ) {
        flash( 'csrf' );
        return;
    }
    if ( is_verified( (int) $user->ID ) ) {
        return;
    }
    if ( ! rate_limit( 'verify-resend', (string) $user->ID, 3, HOUR_IN_SECONDS ) ) {
        flash( 'verify_limited' );
        return;
    }
    send_verification( $user );
    flash( 'verify_sent' );
}

// ---------------------------------------------------------------------------------------------------------------
// /account/verify/?u=&t=[&a=email] — GET shows a button (link scanners only GET), POST confirms.
// ---------------------------------------------------------------------------------------------------------------

function route_verify(): void {
    $is_post = is_post();
    $uid     = (int) ( $is_post ? post_str( 'u', 20 ) : get_str( 'u', 20 ) );
    $token   = $is_post ? post_str( 't', 64 ) : get_str( 't', 64 );
    $kind    = 'email' === ( $is_post ? post_str( 'a', 10 ) : get_str( 'a', 10 ) ) ? 'email' : 'signup';
    $meta    = 'email' === $kind ? 'hr_email_change' : 'hr_verify';
    $current = get_current_user_id();

    if ( $is_post ) {
        if ( ! verify_form( 'verify' ) ) {
            render_verify_state( 'error', $kind, array( 'message' => flash_messages()['csrf'][1], 'u' => $uid, 't' => $token ) );
            return;
        }
        if ( ! rate_limit( 'verify-ip', client_ip(), 20, HOUR_IN_SECONDS ) ) {
            render_verify_state( 'error', $kind, array( 'message' => 'Too many attempts. Please try again in an hour.' ) );
            return;
        }
    }
    $record = token_record( $uid, $meta, $token );
    $user   = $record ? get_userdata( $uid ) : false;
    if ( ! $record || ! $user ) {
        render_verify_state( 'invalid', $kind, array() );
        return;
    }
    if ( $current && $current !== $uid ) {
        render_verify_state( 'other_user', $kind, array() );
        return;
    }
    if ( ! $is_post ) {
        render_verify_state( 'confirm', $kind, array( 'u' => $uid, 't' => $token, 'email' => 'email' === $kind ? (string) $record['email'] : $user->user_email ) );
        return;
    }

    delete_user_meta( $uid, $meta ); // single use, before anything else can fail
    if ( 'email' === $kind ) {
        $new = (string) ( $record['email'] ?? '' );
        if ( ! is_email( $new ) || ( email_exists( $new ) && email_exists( $new ) !== $uid ) ) {
            render_verify_state( 'invalid', $kind, array() );
            return;
        }
        $old = $user->user_email;
        add_filter( 'send_email_change_email', '__return_false', 99 );
        $res = wp_update_user( array( 'ID' => $uid, 'user_email' => $new ) );
        remove_filter( 'send_email_change_email', '__return_false', 99 );
        if ( is_wp_error( $res ) ) {
            render_verify_state( 'invalid', $kind, array() );
            return;
        }
        update_user_meta( $uid, 'hr_email_verified', '1' );
        $fresh = get_userdata( $uid );
        mail_security_notice( $fresh, 'Your email address was changed', 'The email address on your Headline Recorder account is now ' . mask_email( $new ) . '. We will send account emails there from now on.' );
        send_mail( $old, 'Your email address was changed', array(
            'heading'    => 'Your email address was changed',
            'paragraphs' => array( 'The email address on your Headline Recorder account was changed to ' . mask_email( $new ) . '. This address will no longer receive account emails.' ),
            'button'     => array( 'label' => 'Wasn\'t you? Contact us', 'url' => home_url( '/contact-us/' ) ),
        ) );
        log_security( 'email_changed', array( 'uid' => $uid ) );
        flash( 'email_changed' );
        redirect( $current ? account_url( 'settings' ) . '#email' : login_url() );
    }

    update_user_meta( $uid, 'hr_email_verified', '1' );
    log_security( 'email_verified', array( 'uid' => $uid ) );
    if ( $current ) {
        flash( 'welcome' );
        redirect( account_url() );
    }
    // The link proves the inbox; sign in only accounts without a second factor (never bypass 2FA/passkeys).
    if ( has_mfa( $uid ) ) {
        flash( 'verified_signin' );
        redirect( login_url() );
    }
    complete_login( $user, false, 'email-link' );
    flash( 'welcome' );
    redirect( account_url() );
}

function render_verify_state( string $state, string $kind, array $vars ): void {
    if ( in_array( $state, array( 'invalid', 'error' ), true ) ) {
        status_header( 400 );
    }
    render_auth( 'verify', array( 'state' => $state, 'kind' => $kind ) + $vars, 'email' === $kind ? 'Confirm your new email' : 'Confirm your email', 'verify' );
}
