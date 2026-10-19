<?php
/**
 * Accounts: breached-password check (Have I Been Pwned, k-anonymity) plugged into hr_password_problem(), and a
 * dummy hash so "no such user" costs as much time as "wrong password".
 */

namespace HR\Platform\Accounts;

use function HR\Platform\setting;
use function HR\Platform\log_security;

if ( ! defined( 'ABSPATH' ) ) exit;

/** The shared policy (length, not your email…) plus the breach check; null when the password is acceptable. */
function password_problem( string $password, ?\WP_User $user = null ): ?string {
    if ( function_exists( 'hr_password_problem' ) ) {
        return hr_password_problem( $password, $user );
    }
    // Hardening mu-plugin missing: keep the essentials.
    if ( strlen( $password ) < 12 ) {
        return 'Use at least 12 characters. A short sentence works well.';
    }
    return strlen( $password ) > 1024 ? 'That password is too long.' : apply_filters( 'hr_password_problem', null, $password, $user );
}

add_filter( 'hr_password_problem', function ( $problem, $password, $user = null ) {
    if ( null !== $problem || ! is_string( $password ) || ! setting( 'accounts_hibp' ) ) {
        return $problem;
    }
    return pwned_count( $password ) > 0
        ? 'This password has appeared in a known data breach, so attackers try it first. Please choose a different one.'
        : null;
}, 20, 3 );

/**
 * Number of times the password appears in HIBP; 0 when not found or when the service cannot be reached (fail open,
 * logged). Only the first 5 hex characters of the SHA-1 leave the server; padding hides the response size.
 */
function pwned_count( string $password ): int {
    if ( get_transient( 'hr_hibp_down' ) ) {
        return 0;
    }
    $sha1   = strtoupper( sha1( $password ) );
    $prefix = substr( $sha1, 0, 5 );
    $suffix = substr( $sha1, 5 );
    $res    = wp_remote_get( 'https://api.pwnedpasswords.com/range/' . $prefix, array(
        'timeout'     => 2.5,
        'redirection' => 0,
        'user-agent'  => 'HeadlineRecorder-Accounts/1.0',
        'headers'     => array( 'Add-Padding' => 'true' ),
    ) );
    if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
        // Circuit breaker: do not make every sign-up wait 2.5 s while the API is down.
        set_transient( 'hr_hibp_down', 1, 5 * MINUTE_IN_SECONDS );
        log_security( 'hibp_unavailable', array( 'error' => is_wp_error( $res ) ? $res->get_error_code() : (int) wp_remote_retrieve_response_code( $res ) ) );
        return 0;
    }
    $GLOBALS['hr_accounts_hibp_ok'] = true; // the breach check really ran for this request
    foreach ( preg_split( '/\r?\n/', (string) wp_remote_retrieve_body( $res ) ) as $line ) {
        [ $hash, $count ] = array_pad( explode( ':', trim( $line ), 2 ), 2, '0' );
        if ( hash_equals( $suffix, strtoupper( $hash ) ) ) {
            return (int) $count; // Padding rows have a count of 0.
        }
    }
    return 0;
}

/** Spend the same work as a real password check (same algorithm and cost as new hashes). */
function burn_password_check( string $password ): void {
    $hash = get_option( 'hr_accounts_dummy_hash' );
    if ( ! is_string( $hash ) || '' === $hash ) {
        $hash = wp_hash_password( wp_generate_password( 32, true, true ) );
        update_option( 'hr_accounts_dummy_hash', $hash, false );
    }
    wp_check_password( $password, $hash );
}

/**
 * Remember what we know about the password just set, for the honest security checklist: hr_pw_policy = it met the
 * length/identity rules, hr_pw_checked = it was also checked against breach lists (only when HIBP answered).
 */
function note_password_set( int $uid ): void {
    update_user_meta( $uid, 'hr_pw_policy', time() );
    if ( ! empty( $GLOBALS['hr_accounts_hibp_ok'] ) ) {
        update_user_meta( $uid, 'hr_pw_checked', time() );
    } else {
        delete_user_meta( $uid, 'hr_pw_checked' );
    }
}
