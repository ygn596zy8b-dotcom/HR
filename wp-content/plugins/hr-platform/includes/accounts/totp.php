<?php
/**
 * Accounts: two-step verification — TOTP (RFC 6238: SHA-1, 30 s, 6 digits, ±1 step, replay-protected), single-use
 * recovery codes, and the "Authentication code" field on wp-login.php for staff.
 *
 * Storage (user meta): hr_totp = sealed secret (helpers.php seal()), hr_totp_on = enabled timestamp,
 * hr_totp_step = last accepted time step, hr_totp_pending = array( s => sealed secret, exp => ts ) during setup,
 * hr_recovery = one row per unused code: HMAC-SHA256 (key derived from the WordPress salts) of the code.
 */

namespace HR\Platform\Accounts;

use function HR\Platform\rate_limit;
use function HR\Platform\rate_limit_reset;
use function HR\Platform\log_security;

if ( ! defined( 'ABSPATH' ) ) exit;

const TOTP_PERIOD = 30;
const TOTP_DIGITS = 6;
const RECOVERY_ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

// ---------------------------------------------------------------------------------------------------------------
// RFC 4648 base32 and RFC 4226/6238
// ---------------------------------------------------------------------------------------------------------------

function base32_encode( string $bin ): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits     = '';
    foreach ( str_split( $bin ) as $c ) {
        $bits .= str_pad( decbin( ord( $c ) ), 8, '0', STR_PAD_LEFT );
    }
    $out = '';
    foreach ( str_split( $bits, 5 ) as $chunk ) {
        $out .= $alphabet[ bindec( str_pad( $chunk, 5, '0' ) ) ];
    }
    return $out;
}

function hotp( string $key, int $counter ): string {
    $hash   = hash_hmac( 'sha1', pack( 'J', $counter ), $key, true );
    $offset = ord( $hash[19] ) & 0x0f;
    $value  = ( ( ord( $hash[ $offset ] ) & 0x7f ) << 24 ) | ( ord( $hash[ $offset + 1 ] ) << 16 ) | ( ord( $hash[ $offset + 2 ] ) << 8 ) | ord( $hash[ $offset + 3 ] );
    return str_pad( (string) ( $value % ( 10 ** TOTP_DIGITS ) ), TOTP_DIGITS, '0', STR_PAD_LEFT );
}

/** The matching time step (previous, current or next), or null. Steps at or before $after are refused (replay). */
function totp_match( string $key, string $code, int $after = -1 ): ?int {
    if ( ! preg_match( '/^\d{6}$/', $code ) ) {
        return null;
    }
    $now   = intdiv( time(), TOTP_PERIOD );
    $match = null;
    foreach ( array( -1, 0, 1 ) as $drift ) {
        $step = $now + $drift;
        // Compare every candidate (no early exit) so timing does not reveal which step matched.
        if ( hash_equals( hotp( $key, $step ), $code ) && $step > $after && null === $match ) {
            $match = $step;
        }
    }
    return $match;
}

// ---------------------------------------------------------------------------------------------------------------
// Per-user state
// ---------------------------------------------------------------------------------------------------------------

function totp_secret( int $uid ): ?string {
    $sealed = (string) get_user_meta( $uid, 'hr_totp', true );
    if ( '' === $sealed ) {
        return null;
    }
    $secret = unseal( $sealed, 'totp', $uid );
    if ( null === $secret ) {
        log_security( 'totp_unseal_failed', array( 'uid' => $uid ) );
    }
    return $secret;
}

/** Atomically move the "last accepted step" forward; false if another request already used this step. */
function claim_step( int $uid, int $step ): bool {
    global $wpdb;
    if ( '' === (string) get_user_meta( $uid, 'hr_totp_step', true ) ) {
        return (bool) add_user_meta( $uid, 'hr_totp_step', (string) $step, true );
    }
    $updated = $wpdb->query( $wpdb->prepare(
        "UPDATE {$wpdb->usermeta} SET meta_value = %s WHERE user_id = %d AND meta_key = 'hr_totp_step' AND CAST(meta_value AS UNSIGNED) < %d",
        (string) $step,
        $uid,
        $step
    ) );
    wp_cache_delete( $uid, 'user_meta' );
    return 1 === (int) $updated;
}

function totp_check( int $uid, string $code ): bool {
    $secret = totp_secret( $uid );
    if ( null === $secret ) {
        return false;
    }
    $step = totp_match( $secret, $code, (int) get_user_meta( $uid, 'hr_totp_step', true ) );
    sodium_memzero( $secret );
    return null !== $step && claim_step( $uid, $step );
}

function recovery_key( int $uid ): string {
    return hash_hkdf( 'sha256', wp_salt( 'auth' ), 32, 'hr-accounts:recovery:' . $uid );
}

function normalize_recovery( string $code ): string {
    return strtolower( preg_replace( '/[\s\-]+/', '', $code ) );
}

/** Ten fresh codes (xxxx-xxxx). Replaces any previous set. Returns the plain codes to show once. */
function recovery_generate( int $uid ): array {
    delete_user_meta( $uid, 'hr_recovery' );
    $codes = array();
    $key   = recovery_key( $uid );
    for ( $i = 0; $i < 10; $i++ ) {
        $raw = '';
        for ( $j = 0; $j < 8; $j++ ) {
            $raw .= RECOVERY_ALPHABET[ random_int( 0, strlen( RECOVERY_ALPHABET ) - 1 ) ];
        }
        $codes[] = substr( $raw, 0, 4 ) . '-' . substr( $raw, 4 );
        add_user_meta( $uid, 'hr_recovery', hash_hmac( 'sha256', $raw, $key ) );
    }
    return $codes;
}

function recovery_remaining( int $uid ): int {
    return count( (array) get_user_meta( $uid, 'hr_recovery' ) );
}

/** Consume a recovery code. The DELETE is atomic, so two requests cannot both spend the same code. */
function recovery_use( int $uid, string $code ): bool {
    global $wpdb;
    $code = normalize_recovery( $code );
    if ( ! preg_match( '/^[' . RECOVERY_ALPHABET . ']{8}$/', $code ) ) {
        return false;
    }
    $hash    = hash_hmac( 'sha256', $code, recovery_key( $uid ) );
    $deleted = $wpdb->query( $wpdb->prepare(
        "DELETE FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = 'hr_recovery' AND meta_value = %s LIMIT 1",
        $uid,
        $hash
    ) );
    wp_cache_delete( $uid, 'user_meta' );
    return 1 === (int) $deleted;
}

/**
 * Check an authenticator code or a recovery code for a user (rate limited: 10 per 15 minutes per account).
 * Returns 'totp', 'recovery', 'limited' or false.
 */
function verify_second_factor( int $uid, string $input ) {
    $input = trim( substr( $input, 0, 40 ) );
    if ( '' === $input ) {
        return false;
    }
    if ( ! rate_limit( 'totp-user', (string) $uid, 10, 15 * MINUTE_IN_SECONDS ) ) {
        return 'limited';
    }
    $digits = preg_replace( '/\s+/', '', $input );
    if ( preg_match( '/^\d{6}$/', $digits ) ) {
        if ( totp_check( $uid, $digits ) ) {
            rate_limit_reset( 'totp-user', (string) $uid );
            return 'totp';
        }
    } elseif ( recovery_use( $uid, $input ) ) {
        rate_limit_reset( 'totp-user', (string) $uid );
        $user = get_userdata( $uid );
        if ( $user ) {
            $left = recovery_remaining( $uid );
            mail_security_notice( $user, 'A recovery code was used', sprintf( 'A recovery code was just used to confirm a sign-in to your account. You have %d left%s.', $left, $left <= 3 ? ' — generate a new set in Settings → Security' : '' ) );
        }
        log_security( 'recovery_code_used', array( 'uid' => $uid ) );
        return 'recovery';
    }
    log_security( 'second_factor_failed', array( 'uid' => $uid ) );
    return false;
}

// ---------------------------------------------------------------------------------------------------------------
// Setup and removal
// ---------------------------------------------------------------------------------------------------------------

/** Start (or continue) setup: a pending secret valid for 15 minutes. Returns the base32 secret to display. */
function totp_begin( int $uid ): string {
    $pending = get_user_meta( $uid, 'hr_totp_pending', true );
    if ( is_array( $pending ) && (int) ( $pending['exp'] ?? 0 ) > time() ) {
        $secret = unseal( (string) $pending['s'], 'totp-pending', $uid );
        if ( null !== $secret ) {
            return base32_encode( $secret );
        }
    }
    $secret = random_bytes( 20 );
    update_user_meta( $uid, 'hr_totp_pending', array( 's' => seal( $secret, 'totp-pending', $uid ), 'exp' => time() + 15 * MINUTE_IN_SECONDS ) );
    return base32_encode( $secret );
}

function totp_pending_secret( int $uid ): ?string {
    $pending = get_user_meta( $uid, 'hr_totp_pending', true );
    if ( ! is_array( $pending ) || (int) ( $pending['exp'] ?? 0 ) < time() ) {
        return null;
    }
    return unseal( (string) ( $pending['s'] ?? '' ), 'totp-pending', $uid );
}

/** Confirm setup with a code from the app. Returns the recovery codes on success, null on a wrong code. */
function totp_activate( int $uid, string $code ): ?array {
    $secret = totp_pending_secret( $uid );
    if ( null === $secret ) {
        return null;
    }
    $step = totp_match( $secret, preg_replace( '/\s+/', '', $code ) );
    if ( null === $step ) {
        return null;
    }
    update_user_meta( $uid, 'hr_totp', seal( $secret, 'totp', $uid ) );
    update_user_meta( $uid, 'hr_totp_on', time() );
    update_user_meta( $uid, 'hr_totp_step', (string) $step );
    delete_user_meta( $uid, 'hr_totp_pending' );
    sodium_memzero( $secret );
    return recovery_generate( $uid );
}

function totp_disable( int $uid ): void {
    foreach ( array( 'hr_totp', 'hr_totp_on', 'hr_totp_step', 'hr_totp_pending', 'hr_recovery' ) as $key ) {
        delete_user_meta( $uid, $key );
    }
}

// ---------------------------------------------------------------------------------------------------------------
// wp-login.php (staff): an "Authentication code" field, enforced for every account with TOTP on.
// Our own /account/login/ runs the second step itself and sets hr_accounts_internal_auth around wp_authenticate().
// ---------------------------------------------------------------------------------------------------------------

add_action( 'login_form', function () {
    ?>
    <p class="hr-otp-field">
        <label for="hr_otp">Authentication code</label>
        <input type="text" name="hr_otp" id="hr_otp" class="input" value="" size="20" maxlength="20" inputmode="numeric" autocomplete="one-time-code" spellcheck="false" autocapitalize="off">
        <span class="description" style="display:block;margin:-8px 0 16px;font-size:12px;color:#646970">Only if two-step verification is on. A recovery code works too.</span>
    </p>
    <?php
} );

add_filter( 'authenticate', function ( $user ) {
    if ( ! $user instanceof \WP_User || ! empty( $GLOBALS['hr_accounts_internal_auth'] ) ) {
        return $user;
    }
    if ( ! has_totp( (int) $user->ID ) ) {
        if ( is_staff( $user ) && passkey_count( (int) $user->ID ) > 0 ) {
            // No code field can carry a passkey: send them to the sign-in page that can.
            return new \WP_Error( 'hr_use_passkey', sprintf( '<strong>This account signs in with a passkey.</strong> <a href="%s">Continue on the Headline Recorder sign-in page</a>.', esc_url( login_url( admin_url() ) ) ) );
        }
        return $user;
    }
    $code   = isset( $_POST['hr_otp'] ) && is_string( $_POST['hr_otp'] ) ? sanitize_text_field( wp_unslash( $_POST['hr_otp'] ) ) : '';
    $result = '' === $code ? false : verify_second_factor( (int) $user->ID, $code );
    if ( 'limited' === $result ) {
        return new \WP_Error( 'hr_rate_limited', '<strong>Error:</strong> Too many sign-in attempts. Please wait 15 minutes and try again.' );
    }
    if ( ! $result ) {
        // Same wording as a wrong password, so this is not an oracle for "the password was right".
        return new \WP_Error( 'hr_otp_invalid', '<strong>Error:</strong> The sign-in details are incorrect.' );
    }
    $GLOBALS['hr_accounts_login_method'] = 'password-' . $result;
    return $user;
}, 100 );

add_filter( 'login_errors', function ( $error ) {
    global $errors;
    $codes = is_wp_error( $errors ) ? $errors->get_error_codes() : array();
    if ( array_intersect( $codes, array( 'invalid_username', 'invalid_email', 'incorrect_password', 'hr_otp_invalid' ) ) ) {
        return '<strong>Error:</strong> The sign-in details are incorrect. If two-step verification is on, include your authentication code.';
    }
    return $error;
}, 20 );
