<?php
/**
 * Accounts: public helpers (is_verified, require_recent_auth, login_url), session/step-up bookkeeping, cookies,
 * flash messages, form tokens, encryption at rest, redirect validation and user-agent summaries.
 */

namespace HR\Platform\Accounts;

use function HR\Platform\route_url;
use function HR\Platform\same_origin_request;
use function HR\Platform\is_b64url;
use function HR\Platform\b64url_decode;
use function HR\Platform\log_security;

if ( ! defined( 'ABSPATH' ) ) exit;

// ---------------------------------------------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------------------------------------------

/** Has the reader confirmed their email address? */
function is_verified( int $uid ): bool {
    if ( $uid <= 0 ) {
        return false;
    }
    $flag = (string) get_user_meta( $uid, 'hr_email_verified', true );
    if ( '' === $flag ) {
        // Accounts older than this module: staff addresses were set by an administrator, readers' were never checked.
        return user_can( $uid, 'edit_posts' );
    }
    return '1' === $flag;
}

/** True when this session confirmed a password, passkey or authenticator code in the last 10 minutes. */
function require_recent_auth(): bool {
    $uid = get_current_user_id();
    $key = session_key();
    if ( ! $uid || '' === $key ) {
        return false;
    }
    $map = get_user_meta( $uid, 'hr_stepup', true );
    $at  = is_array( $map ) ? (int) ( $map[ $key ] ?? 0 ) : 0;
    return $at > 0 && ( time() - $at ) < STEPUP_TTL;
}

/** Seconds left in the step-up window (0 when locked). */
function recent_auth_remaining(): int {
    $uid = get_current_user_id();
    $map = $uid ? get_user_meta( $uid, 'hr_stepup', true ) : array();
    $at  = is_array( $map ) ? (int) ( $map[ session_key() ] ?? 0 ) : 0;
    return $at ? max( 0, STEPUP_TTL - ( time() - $at ) ) : 0;
}

function login_url( string $redirect = '' ): string {
    $url    = route_url( 'account/login' );
    $target = '' === $redirect ? '' : safe_redirect_target( $redirect, '' );
    return '' === $target ? $url : add_query_arg( 'redirect_to', rawurlencode( $target ), $url );
}

function account_url( string $slug = '' ): string {
    return route_url( '' === $slug ? 'account' : 'account/' . $slug );
}

function has_totp( int $uid ): bool {
    return '' !== (string) get_user_meta( $uid, 'hr_totp', true );
}

function has_mfa( int $uid ): bool {
    return has_totp( $uid ) || passkey_count( $uid ) > 0;
}

function is_staff( $user ): bool {
    return user_can( $user, 'edit_posts' );
}

// ---------------------------------------------------------------------------------------------------------------
// Sessions and step-up
// ---------------------------------------------------------------------------------------------------------------

// wp_set_auth_cookie() does not return the session token it creates; capture it so a login can be marked
// "recently authenticated" in the same request.
add_action( 'set_logged_in_cookie', function ( $cookie, $expire, $expiration, $user_id, $scheme, $token ) {
    $GLOBALS['hr_accounts_new_token'] = (string) $token;
}, 10, 6 );

function current_session_token(): string {
    if ( ! empty( $GLOBALS['hr_accounts_new_token'] ) ) {
        return (string) $GLOBALS['hr_accounts_new_token'];
    }
    return (string) wp_get_session_token();
}

/** Step-up records are keyed by a hash of the session token, never the token itself. */
function session_key( string $token = '' ): string {
    $token = '' !== $token ? $token : current_session_token();
    return '' === $token ? '' : hash( 'sha256', $token );
}

function mark_recent_auth( int $uid ): void {
    $key = session_key();
    if ( ! $uid || '' === $key ) {
        return;
    }
    $now = time();
    $map = get_user_meta( $uid, 'hr_stepup', true );
    $map = array_filter( is_array( $map ) ? $map : array(), static fn( $t ) => $now - (int) $t < STEPUP_TTL );
    $map[ $key ] = $now;
    arsort( $map );
    update_user_meta( $uid, 'hr_stepup', array_slice( $map, 0, 10, true ) );
}

// Any successful sign-in (our forms, passkeys, wp-login.php) counts as a fresh authentication for that session,
// except the email confirmation link, which proves the inbox, not a password, passkey or code.
add_action( 'wp_login', function ( $login, $user ) {
    if ( $user instanceof \WP_User && 'email-link' !== ( $GLOBALS['hr_accounts_login_method'] ?? '' ) ) {
        mark_recent_auth( (int) $user->ID );
    }
}, 5, 2 );

// Record how a session was created, so the sessions list can say "signed in with a passkey".
add_filter( 'attach_session_information', function ( $info ) {
    if ( ! empty( $GLOBALS['hr_accounts_login_method'] ) ) {
        $info['hr_method'] = substr( sanitize_key( $GLOBALS['hr_accounts_login_method'] ), 0, 20 );
    }
    return $info;
} );

// ---------------------------------------------------------------------------------------------------------------
// Cookies, tokens, flash messages
// ---------------------------------------------------------------------------------------------------------------

function cookie_path( string $sub = '' ): string {
    return trailingslashit( COOKIEPATH ) . ltrim( $sub, '/' );
}

/** HttpOnly cookie, Secure on HTTPS. $ttl <= 0 deletes it. */
function set_cookie( string $name, string $value, int $ttl, string $path, string $samesite = 'Strict' ): void {
    if ( headers_sent() ) {
        return;
    }
    setcookie( $name, $ttl > 0 ? $value : '', array(
        'expires'  => $ttl > 0 ? time() + $ttl : time() - YEAR_IN_SECONDS,
        'path'     => $path,
        'domain'   => COOKIE_DOMAIN ?: '',
        'secure'   => is_ssl(),
        'httponly' => true,
        'samesite' => $samesite,
    ) );
    if ( $ttl > 0 ) {
        $_COOKIE[ $name ] = $value;
    } else {
        unset( $_COOKIE[ $name ] );
    }
}

function read_cookie( string $name, int $max = 256 ): string {
    $value = $_COOKIE[ $name ] ?? '';
    return is_string( $value ) && strlen( $value ) <= $max ? $value : '';
}

function b64url( string $bin ): string {
    return rtrim( strtr( base64_encode( $bin ), '+/', '-_' ), '=' );
}

function random_token( int $bytes = 32 ): string {
    return b64url( random_bytes( $bytes ) );
}

function mac( string $purpose, string $data ): string {
    return hash_hmac( 'sha256', $purpose . '|' . $data, wp_salt( 'auth' ) );
}

/**
 * One-shot notice carried across a redirect (Post/Redirect/Get). Only a message key and a few short strings
 * travel, signed, so nobody can inject text into the page through the cookie.
 */
function flash( string $key, array $data = array() ): void {
    $payload = b64url( (string) wp_json_encode( array( 'k' => $key, 'd' => $data, 't' => time() ) ) );
    if ( strlen( $payload ) > 1500 ) {
        return;
    }
    set_cookie( 'hr_flash', $payload . '.' . mac( 'flash', $payload ), 300, cookie_path( 'account/' ), 'Lax' );
}

function take_flash(): ?array {
    static $taken = false, $value = null;
    if ( $taken ) {
        return $value;
    }
    $taken = true;
    $raw   = read_cookie( 'hr_flash', 2200 );
    if ( '' === $raw ) {
        return null;
    }
    set_cookie( 'hr_flash', '', 0, cookie_path( 'account/' ), 'Lax' );
    [ $payload, $sig ] = array_pad( explode( '.', $raw, 2 ), 2, '' );
    if ( ! is_b64url( $payload ) || ! hash_equals( mac( 'flash', $payload ), $sig ) ) {
        return null;
    }
    $data = json_decode( b64url_decode( $payload ), true );
    if ( ! is_array( $data ) || empty( $data['k'] ) || time() - (int) ( $data['t'] ?? 0 ) > 300 ) {
        return null;
    }
    $value = array( 'key' => sanitize_key( $data['k'] ), 'data' => is_array( $data['d'] ?? null ) ? $data['d'] : array() );
    return $value;
}

// ---------------------------------------------------------------------------------------------------------------
// Forms: nonce + same-origin + (for signed-out visitors) a token bound to this browser
// ---------------------------------------------------------------------------------------------------------------

/**
 * Signed-out WordPress nonces are the same for every visitor, so they cannot stop login CSRF on their own.
 * Signed-out forms also carry an HMAC of a per-browser random id held in an HttpOnly SameSite=Lax cookie, which a
 * cross-site POST never sends.
 */
function browser_id(): string {
    $id = read_cookie( 'hr_fs', 64 );
    if ( ! is_b64url( $id ) || strlen( $id ) !== 43 ) {
        $id = random_token();
        set_cookie( 'hr_fs', $id, WEEK_IN_SECONDS, cookie_path( 'account/' ), 'Lax' );
    }
    return $id;
}

function form_token( string $action ): string {
    return mac( 'form:' . $action, browser_id() );
}

function hidden_fields( string $action ): void {
    wp_nonce_field( 'hr_account_' . $action, '_hrnonce', false );
    printf( '<input type="hidden" name="hr_action" value="%s">', esc_attr( $action ) );
    if ( ! is_user_logged_in() ) {
        printf( '<input type="hidden" name="hr_ft" value="%s">', esc_attr( form_token( $action ) ) );
    }
}

function posted_action(): string {
    return isset( $_POST['hr_action'] ) && is_string( $_POST['hr_action'] ) ? substr( sanitize_key( $_POST['hr_action'] ), 0, 40 ) : '';
}

function is_post(): bool {
    return 'POST' === strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) );
}

function verify_form( string $action ): bool {
    if ( ! is_post() || posted_action() !== $action ) {
        return false;
    }
    if ( ! same_origin_request() ) {
        log_security( 'account_form_origin', array( 'action' => $action ) );
        return false;
    }
    $nonce = isset( $_POST['_hrnonce'] ) && is_string( $_POST['_hrnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_hrnonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'hr_account_' . $action ) ) {
        log_security( 'account_form_nonce', array( 'action' => $action ) );
        return false;
    }
    if ( ! is_user_logged_in() ) {
        $id = read_cookie( 'hr_fs', 64 );
        $ft = isset( $_POST['hr_ft'] ) && is_string( $_POST['hr_ft'] ) ? $_POST['hr_ft'] : '';
        if ( strlen( $id ) !== 43 || ! hash_equals( mac( 'form:' . $action, $id ), $ft ) ) {
            log_security( 'account_form_token', array( 'action' => $action ) );
            return false;
        }
    }
    return true;
}

/** A posted string, unslashed, capped (one byte over the cap so validators can say "too long"). */
function post_str( string $key, int $max = 500 ): string {
    $value = $_POST[ $key ] ?? '';
    if ( ! is_string( $value ) ) {
        return '';
    }
    return substr( wp_unslash( $value ), 0, $max + 1 );
}

function get_str( string $key, int $max = 500 ): string {
    $value = $_GET[ $key ] ?? '';
    return is_string( $value ) ? substr( wp_unslash( $value ), 0, $max + 1 ) : '';
}

/** Signed timestamp for the registration form (bots submit instantly or replay old forms). */
function signed_time(): string {
    $t = (string) time();
    return $t . '.' . substr( mac( 'form-time', $t ), 0, 32 );
}

function signed_time_age( string $value ): ?int {
    [ $t, $sig ] = array_pad( explode( '.', $value, 2 ), 2, '' );
    if ( ! ctype_digit( $t ) || ! hash_equals( substr( mac( 'form-time', $t ), 0, 32 ), $sig ) ) {
        return null;
    }
    return time() - (int) $t;
}

// ---------------------------------------------------------------------------------------------------------------
// Encryption at rest (TOTP secrets). Key derived from SECURE_AUTH_KEY/SALT and bound to the user, so a database
// dump alone reveals nothing and a ciphertext copied to another user's row does not decrypt.
// Rotating those salts invalidates every enrolment (users then sign in with a recovery code… which also depends on
// the salts). Reset affected users from their wp-admin profile ("Security" section).
// ---------------------------------------------------------------------------------------------------------------

function key_for( string $purpose, int $uid ): string {
    return hash_hkdf( 'sha256', wp_salt( 'secure_auth' ), SODIUM_CRYPTO_SECRETBOX_KEYBYTES, 'hr-accounts:' . $purpose . ':' . $uid );
}

function seal( string $plain, string $purpose, int $uid ): string {
    $key   = key_for( $purpose, $uid );
    $nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
    $box   = sodium_crypto_secretbox( $plain, $nonce, $key );
    sodium_memzero( $key );
    return 'v1:' . base64_encode( $nonce . $box );
}

function unseal( string $sealed, string $purpose, int $uid ): ?string {
    if ( ! str_starts_with( $sealed, 'v1:' ) ) {
        return null;
    }
    $raw = base64_decode( substr( $sealed, 3 ), true );
    if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES ) {
        return null;
    }
    $key   = key_for( $purpose, $uid );
    $plain = sodium_crypto_secretbox_open( substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), $key );
    sodium_memzero( $key );
    return false === $plain ? null : $plain;
}

// ---------------------------------------------------------------------------------------------------------------
// Redirects, display helpers
// ---------------------------------------------------------------------------------------------------------------

/** A same-site destination for after sign-in; never back into an auth page (loops, logout CSRF). */
function safe_redirect_target( $raw, string $fallback ): string {
    $raw = is_string( $raw ) ? trim( $raw ) : '';
    if ( '' === $raw || strlen( $raw ) > 2000 ) {
        return $fallback;
    }
    $url = wp_validate_redirect( wp_sanitize_redirect( $raw ), '' );
    if ( '' === $url ) {
        return $fallback;
    }
    $path = (string) wp_parse_url( $url, PHP_URL_PATH );
    if ( preg_match( '#(?:wp-login\.php|/account/(?:login|logout|register|reset|forgot|two-factor|verify)(?:/|$))#', $path ) ) {
        return $fallback;
    }
    return $url;
}

function requested_redirect(): string {
    $raw = $_POST['redirect_to'] ?? ( $_GET['redirect_to'] ?? '' );
    return safe_redirect_target( is_string( $raw ) ? wp_unslash( $raw ) : '', '' );
}

function redirect( string $url, int $status = 303 ): void {
    wp_safe_redirect( $url, $status );
    exit;
}

function mask_email( string $email ): string {
    [ $local, $domain ] = array_pad( explode( '@', $email, 2 ), 2, '' );
    if ( '' === $domain ) {
        return '';
    }
    return mb_substr( $local, 0, 1 ) . str_repeat( '•', max( 2, min( 6, mb_strlen( $local ) - 1 ) ) ) . '@' . $domain;
}

function initials( string $name ): string {
    $parts = preg_split( '/\s+/u', trim( wp_strip_all_tags( $name ) ) ) ?: array();
    $out   = '';
    foreach ( array_slice( array_filter( $parts ), 0, 2 ) as $p ) {
        $out .= mb_strtoupper( mb_substr( $p, 0, 1 ) );
    }
    return '' === $out ? 'HR' : $out;
}

function first_name( \WP_User $user ): string {
    $name  = $user->first_name ?: $user->display_name;
    $parts = preg_split( '/\s+/u', trim( (string) $name ) ) ?: array( '' );
    return mb_substr( (string) $parts[0], 0, 40 );
}

/** "Chrome on macOS": coarse on purpose; enough to recognise a device, too little to fingerprint it. */
function describe_ua( string $ua ): string {
    $ua = substr( $ua, 0, 512 );
    if ( '' === $ua ) {
        return 'Unknown device';
    }
    $browser = 'Browser';
    $map     = array(
        'Edg/' => 'Edge', 'EdgA/' => 'Edge', 'EdgiOS/' => 'Edge', 'OPR/' => 'Opera', 'SamsungBrowser/' => 'Samsung Internet',
        'Firefox/' => 'Firefox', 'FxiOS/' => 'Firefox', 'CriOS/' => 'Chrome', 'Chrome/' => 'Chrome', 'Safari/' => 'Safari',
    );
    foreach ( $map as $needle => $name ) {
        if ( str_contains( $ua, $needle ) ) {
            $browser = $name;
            break;
        }
    }
    $os = 'an unknown system';
    if ( preg_match( '/iPhone|iPod/', $ua ) ) {
        $os = 'iPhone';
    } elseif ( str_contains( $ua, 'iPad' ) ) {
        $os = 'iPad';
    } elseif ( str_contains( $ua, 'Android' ) ) {
        $os = 'Android';
    } elseif ( str_contains( $ua, 'Windows' ) ) {
        $os = 'Windows';
    } elseif ( str_contains( $ua, 'CrOS' ) ) {
        $os = 'ChromeOS';
    } elseif ( str_contains( $ua, 'Macintosh' ) || str_contains( $ua, 'Mac OS X' ) ) {
        $os = 'macOS';
    } elseif ( str_contains( $ua, 'Linux' ) ) {
        $os = 'Linux';
    }
    return $browser . ' on ' . $os;
}

function current_ua(): string {
    return isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( (string) $_SERVER['HTTP_USER_AGENT'], 0, 512 ) : '';
}

/** "12 Oct 2026, 14:05" in the site's timezone. */
function fmt_time( int $ts, bool $with_time = true ): string {
    return $ts > 0 ? wp_date( $with_time ? 'j M Y, H:i' : 'j M Y', $ts ) : 'unknown';
}

function rel_time( int $ts ): string {
    if ( $ts <= 0 ) {
        return 'never';
    }
    $diff = time() - $ts;
    if ( $diff < 90 ) {
        return 'just now';
    }
    return $diff > 0 ? human_time_diff( $ts ) . ' ago' : 'in ' . human_time_diff( $ts );
}

// ---------------------------------------------------------------------------------------------------------------
// Pages contributed by other modules
// ---------------------------------------------------------------------------------------------------------------

function account_pages(): array {
    static $pages = null;
    if ( null !== $pages ) {
        return $pages;
    }
    $pages = array();
    foreach ( (array) apply_filters( 'hr_account_pages', array() ) as $slug => $p ) {
        if ( ! is_string( $slug ) || ! preg_match( '/^[a-z0-9-]{1,40}$/', $slug ) || in_array( $slug, RESERVED, true ) || ! is_array( $p ) ) {
            continue;
        }
        $page = array(
            'title'             => wp_strip_all_tags( (string) ( $p['title'] ?? $slug ) ),
            'icon'              => sanitize_key( (string) ( $p['icon'] ?? 'file-text' ) ),
            'order'             => (int) ( $p['order'] ?? 50 ),
            'nav'               => ! array_key_exists( 'nav', $p ) || (bool) $p['nav'],
            'render'            => isset( $p['render'] ) && is_callable( $p['render'] ) ? $p['render'] : null,
            'handle'            => isset( $p['handle'] ) && is_callable( $p['handle'] ) ? $p['handle'] : null,
            'url'               => ! empty( $p['url'] ) && is_string( $p['url'] ) ? esc_url_raw( $p['url'] ) : null,
            'requires_verified' => ! empty( $p['requires_verified'] ),
        );
        if ( $page['render'] || $page['url'] ) {
            $pages[ $slug ] = $page;
        }
    }
    uasort( $pages, static fn( $a, $b ) => array( $a['order'], $a['title'] ) <=> array( $b['order'], $b['title'] ) );
    return $pages;
}

function page_url( string $slug ): string {
    $pages = account_pages();
    return isset( $pages[ $slug ] ) ? ( $pages[ $slug ]['url'] ?: account_url( $slug ) ) : '';
}
