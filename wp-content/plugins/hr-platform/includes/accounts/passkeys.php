<?php
/**
 * Accounts: passkeys (WebAuthn, lbuchs/webauthn).
 *
 * REST (namespace hr/v1, JSON bodies, binary fields base64url):
 *   POST /passkeys/register/options   signed in + X-WP-Nonce. Step-up needed when the account already has a passkey.
 *                                     → PublicKeyCredentialCreationOptions (base64url binary fields)
 *   POST /passkeys/register/verify    { clientDataJSON, attestationObject, transports[], name? } → { ok: true }
 *   POST /passkeys/login/options      public. Sets HttpOnly SameSite=Strict cookie hr_wa (5 min) that binds the
 *                                     ceremony to this browser. → PublicKeyCredentialRequestOptions (discoverable)
 *   POST /passkeys/login/verify       { id, clientDataJSON, authenticatorData, signature, userHandle, remember,
 *                                     redirect_to } → { ok: true, redirect } and the auth cookies
 *   POST /passkeys/stepup/options     signed in + X-WP-Nonce → request options limited to this user's passkeys
 *   POST /passkeys/stepup/verify      { id, clientDataJSON, authenticatorData, signature } → { ok: true }
 *
 * Errors: WP_Error JSON { code, message, data: { status } }; codes hr_step_up_required, hr_passkey_failed,
 * hr_passkey_expired, hr_rate_limited, hr_bad_request.
 */

namespace HR\Platform\Accounts;

use lbuchs\WebAuthn\WebAuthn;
use lbuchs\WebAuthn\WebAuthnException;
use function HR\Platform\json_body;
use function HR\Platform\is_b64url;
use function HR\Platform\b64url_decode;
use function HR\Platform\no_store;
use function HR\Platform\rate_limit;
use function HR\Platform\client_ip;
use function HR\Platform\log_security;

if ( ! defined( 'ABSPATH' ) ) exit;

const PASSKEY_MAX = 20;

function passkeys_table(): string {
    global $wpdb;
    return $wpdb->prefix . 'hr_passkeys';
}

function passkeys_install_table(): void {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table   = passkeys_table();
    $charset = $wpdb->get_charset_collate();
    dbDelta( "CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        credential_id_hash char(64) NOT NULL,
        credential_id text NOT NULL,
        public_key text NOT NULL,
        sign_count bigint(20) unsigned NOT NULL DEFAULT 0,
        name varchar(80) NOT NULL DEFAULT '',
        transports varchar(100) NOT NULL DEFAULT '',
        aaguid char(36) NOT NULL DEFAULT '',
        created_at datetime NOT NULL,
        last_used_at datetime DEFAULT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY credential_id_hash (credential_id_hash),
        KEY user_id (user_id)
    ) {$charset};" );
}

function rp_id(): string {
    return strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
}

/** The one origin our pages are served from; the library's own check accepts any host ending in the RP ID. */
function expected_origin(): string {
    $p = wp_parse_url( home_url() );
    return strtolower( ( $p['scheme'] ?? 'https' ) . '://' . ( $p['host'] ?? '' ) ) . ( isset( $p['port'] ) ? ':' . (int) $p['port'] : '' );
}

function webauthn(): ?WebAuthn {
    if ( ! class_exists( WebAuthn::class ) ) {
        return null;
    }
    try {
        return new WebAuthn( 'Headline Recorder', rp_id(), null, true );
    } catch ( WebAuthnException $e ) {
        log_security( 'webauthn_init_failed', array( 'code' => $e->getCode() ) );
        return null;
    }
}

function user_handle( int $uid ): string {
    $handle = (string) get_user_meta( $uid, 'hr_webauthn_handle', true );
    if ( ! is_b64url( $handle ) ) {
        $handle = b64url( random_bytes( 16 ) );
        update_user_meta( $uid, 'hr_webauthn_handle', $handle );
    }
    return $handle;
}

function passkey_count( int $uid ): int {
    static $cache = array();
    if ( $uid <= 0 ) {
        return 0;
    }
    if ( ! isset( $cache[ $uid ] ) || ! empty( $GLOBALS['hr_accounts_passkeys_dirty'] ) ) {
        global $wpdb;
        unset( $GLOBALS['hr_accounts_passkeys_dirty'] );
        $table = passkeys_table();
        $cache[ $uid ] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE user_id = %d", $uid ) );
    }
    return $cache[ $uid ];
}

function passkeys_for_user( int $uid ): array {
    global $wpdb;
    $table = passkeys_table();
    return (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, name, aaguid, transports, created_at, last_used_at FROM {$table} WHERE user_id = %d ORDER BY created_at ASC", $uid ) );
}

function passkey_rename( int $uid, int $id, string $name ): bool {
    global $wpdb;
    $name = mb_substr( trim( sanitize_text_field( $name ) ), 0, 80 );
    if ( '' === $name ) {
        return false;
    }
    // Ownership is part of the WHERE clause: an id from someone else's account matches nothing.
    return false !== $wpdb->update( passkeys_table(), array( 'name' => $name ), array( 'id' => $id, 'user_id' => $uid ), array( '%s' ), array( '%d', '%d' ) );
}

function passkey_delete( int $uid, int $id ): bool {
    global $wpdb;
    $GLOBALS['hr_accounts_passkeys_dirty'] = true;
    return 1 === (int) $wpdb->delete( passkeys_table(), array( 'id' => $id, 'user_id' => $uid ), array( '%d', '%d' ) );
}

function passkeys_delete_all( int $uid ): void {
    global $wpdb;
    $GLOBALS['hr_accounts_passkeys_dirty'] = true;
    $wpdb->delete( passkeys_table(), array( 'user_id' => $uid ), array( '%d' ) );
    delete_user_meta( $uid, 'hr_webauthn_handle' );
}
add_action( 'delete_user', fn( $uid ) => passkeys_delete_all( (int) $uid ) );

/** Friendly names for common passkey providers (AAGUID → name); everything else gets "Passkey on <browser>". */
function aaguid_name( string $aaguid ): string {
    $known = array(
        'ea9b8d66-4d01-1d21-3ce4-b6b48cb575d4' => 'Google Password Manager',
        'adce0002-35bc-c60a-648b-0b25f1f05503' => 'Chrome on Mac',
        'fbfc3007-154e-4ecc-8c0b-6e020557d7bd' => 'iCloud Keychain',
        'dd4ec289-e01d-41c9-bb89-70fa845d4bf2' => 'iCloud Keychain',
        '08987058-cadc-4b81-b6e1-30de50dcbe96' => 'Windows Hello',
        '9ddd1817-af5a-4672-a2b9-3e3dd95000a9' => 'Windows Hello',
        '6028b017-b1d4-4c02-b4b3-afcdafc96bb2' => 'Windows Hello',
        'bada5566-a7aa-401f-bd96-45619a55120d' => '1Password',
        'd548826e-79b4-db40-a3d8-11116f7e8349' => 'Bitwarden',
        '531126d6-e717-415c-9320-3d9aa6981239' => 'Dashlane',
        '53414d53-554e-4700-0000-000000000000' => 'Samsung Pass',
        'b84e4048-15dc-4dd0-8640-f4f60813c8af' => 'NordPass',
        'cb69481e-8ff7-4039-93ec-0a2729a154a8' => 'YubiKey',
        'ee882879-721c-4913-9775-3dfcce97072a' => 'YubiKey',
        'fa2b99dc-9e39-4257-8f92-4a30d23c4118' => 'YubiKey',
    );
    return $known[ strtolower( $aaguid ) ] ?? '';
}

function uuid_from_bin( string $bin ): string {
    if ( 16 !== strlen( $bin ) ) {
        return '';
    }
    $h = bin2hex( $bin );
    return substr( $h, 0, 8 ) . '-' . substr( $h, 8, 4 ) . '-' . substr( $h, 12, 4 ) . '-' . substr( $h, 16, 4 ) . '-' . substr( $h, 20 );
}

// ---------------------------------------------------------------------------------------------------------------
// REST
// ---------------------------------------------------------------------------------------------------------------

add_action( 'rest_api_init', function () {
    $signed_in = static fn() => is_user_logged_in();
    $routes    = array(
        '/passkeys/register/options' => array( 'rest_register_options', $signed_in ),
        '/passkeys/register/verify'  => array( 'rest_register_verify', $signed_in ),
        // Public by design: these are how a signed-out visitor signs in. The hr_wa cookie binds the two calls.
        '/passkeys/login/options'    => array( 'rest_login_options', '__return_true' ),
        '/passkeys/login/verify'     => array( 'rest_login_verify', '__return_true' ),
        '/passkeys/stepup/options'   => array( 'rest_stepup_options', $signed_in ),
        '/passkeys/stepup/verify'    => array( 'rest_stepup_verify', $signed_in ),
    );
    foreach ( $routes as $route => [ $callback, $permission ] ) {
        register_rest_route( 'hr/v1', $route, array(
            'methods'             => 'POST',
            'callback'            => __NAMESPACE__ . '\\' . $callback,
            'permission_callback' => $permission,
        ) );
    }
} );

function rest_error( string $code, string $message, int $status = 400 ): \WP_Error {
    return new \WP_Error( $code, $message, array( 'status' => $status ) );
}

function generic_passkey_error(): \WP_Error {
    return rest_error( 'hr_passkey_failed', 'We could not sign you in with that passkey. Try again, or use your email and password.', 400 );
}

/** Decode and size-check the binary fields of a WebAuthn response. */
function bin_fields( ?array $body, array $fields ): ?array {
    if ( null === $body ) {
        return null;
    }
    $out = array();
    foreach ( $fields as $name => $max ) {
        $value = $body[ $name ] ?? '';
        if ( ! is_b64url( $value, $max ) ) {
            return null;
        }
        $out[ $name ] = b64url_decode( $value );
        if ( '' === $out[ $name ] ) {
            return null;
        }
    }
    return $out;
}

function origin_ok( string $client_data_json ): bool {
    $data = json_decode( $client_data_json, true );
    return is_array( $data ) && isset( $data['origin'] ) && is_string( $data['origin'] ) && hash_equals( expected_origin(), strtolower( $data['origin'] ) );
}

function challenge_key( string $kind, int $uid ): string {
    return 'hr_wa_' . $kind . '_' . substr( hash( 'sha256', $uid . '|' . session_key() ), 0, 40 );
}

function rest_register_options( \WP_REST_Request $request ) {
    no_store();
    $user = wp_get_current_user();
    $uid  = (int) $user->ID;
    if ( passkey_count( $uid ) > 0 && ! require_recent_auth() ) {
        return rest_error( 'hr_step_up_required', 'For your security, confirm it is you before adding another passkey.', 403 );
    }
    if ( passkey_count( $uid ) >= PASSKEY_MAX ) {
        return rest_error( 'hr_bad_request', 'You have reached the maximum number of passkeys. Remove one first.', 400 );
    }
    if ( ! rate_limit( 'pk-register', (string) $uid, 10, HOUR_IN_SECONDS ) ) {
        return rest_error( 'hr_rate_limited', 'Too many attempts. Please try again later.', 429 );
    }
    $wa = webauthn();
    if ( ! $wa ) {
        return rest_error( 'hr_passkey_failed', 'Passkeys are not available right now.', 503 );
    }
    global $wpdb;
    $table   = passkeys_table();
    $exclude = array_map( 'HR\Platform\b64url_decode', (array) $wpdb->get_col( $wpdb->prepare( "SELECT credential_id FROM {$table} WHERE user_id = %d", $uid ) ) );
    $args    = $wa->getCreateArgs( b64url_decode( user_handle( $uid ) ), $user->user_email, $user->display_name, 120, true, true, null, $exclude );
    $args->publicKey->attestation = 'none'; // We do not need (or want) to know the device model.
    $args->publicKey->extensions  = (object) array( 'credProps' => true );
    set_transient( challenge_key( 'reg', $uid ), b64url( $wa->getChallenge()->getBinaryString() ), 5 * MINUTE_IN_SECONDS );
    return rest_ensure_response( $args->publicKey );
}

function rest_register_verify( \WP_REST_Request $request ) {
    no_store();
    $user = wp_get_current_user();
    $uid  = (int) $user->ID;
    $body = json_body( $request, 65536 );
    $bin  = bin_fields( $body, array( 'clientDataJSON' => 4096, 'attestationObject' => 32768 ) );
    $key  = challenge_key( 'reg', $uid );
    $challenge = (string) get_transient( $key );
    delete_transient( $key );
    if ( null === $bin ) {
        return rest_error( 'hr_bad_request', 'The passkey response was not valid.', 400 );
    }
    if ( '' === $challenge ) {
        return rest_error( 'hr_passkey_expired', 'That took a little too long. Please try again.', 400 );
    }
    if ( passkey_count( $uid ) > 0 && ! require_recent_auth() ) {
        return rest_error( 'hr_step_up_required', 'For your security, confirm it is you before adding another passkey.', 403 );
    }
    $wa = webauthn();
    if ( ! $wa || ! origin_ok( $bin['clientDataJSON'] ) ) {
        return rest_error( 'hr_passkey_failed', 'We could not add that passkey. Please try again.', 400 );
    }
    try {
        $data = $wa->processCreate( $bin['clientDataJSON'], $bin['attestationObject'], b64url_decode( $challenge ), true, true, false, false );
    } catch ( WebAuthnException $e ) {
        log_security( 'passkey_register_failed', array( 'code' => $e->getCode() ) );
        return rest_error( 'hr_passkey_failed', 'We could not add that passkey. Please try again.', 400 );
    }
    $cred_id    = (string) $data->credentialId;
    $aaguid     = uuid_from_bin( is_string( $data->AAGUID ) ? $data->AAGUID : '' );
    $transports = array_values( array_intersect( array_map( 'strval', (array) ( $body['transports'] ?? array() ) ), array( 'usb', 'nfc', 'ble', 'hybrid', 'internal', 'smart-card' ) ) );
    $name       = isset( $body['name'] ) && is_string( $body['name'] ) ? mb_substr( trim( sanitize_text_field( $body['name'] ) ), 0, 80 ) : '';
    if ( '' === $name ) {
        $name = aaguid_name( $aaguid ) ?: 'Passkey on ' . describe_ua( current_ua() );
    }
    global $wpdb;
    $ok = $wpdb->insert( passkeys_table(), array(
        'user_id'            => $uid,
        'credential_id_hash' => hash( 'sha256', $cred_id ),
        'credential_id'      => b64url( $cred_id ),
        'public_key'         => (string) $data->credentialPublicKey,
        'sign_count'         => (int) ( $data->signatureCounter ?? 0 ),
        'name'               => mb_substr( $name, 0, 80 ),
        'transports'         => substr( implode( ',', $transports ), 0, 100 ),
        'aaguid'             => $aaguid,
        'created_at'         => current_time( 'mysql', true ),
    ), array( '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' ) );
    if ( ! $ok ) {
        return rest_error( 'hr_bad_request', 'That passkey is already registered.', 409 );
    }
    $GLOBALS['hr_accounts_passkeys_dirty'] = true;
    mark_recent_auth( $uid );
    mail_security_notice( $user, 'A passkey was added to your account', 'A new passkey ("' . $name . '") can now be used to sign in to your Headline Recorder account.' );
    log_security( 'passkey_added', array( 'uid' => $uid ) );
    flash( 'passkey_added' );
    return rest_ensure_response( array( 'ok' => true ) );
}

function rest_login_options( \WP_REST_Request $request ) {
    no_store();
    if ( ! rate_limit( 'pk-login-options', client_ip(), 30, 10 * MINUTE_IN_SECONDS ) ) {
        return rest_error( 'hr_rate_limited', 'Too many attempts. Please wait a few minutes.', 429 );
    }
    $wa = webauthn();
    if ( ! $wa ) {
        return rest_error( 'hr_passkey_failed', 'Passkeys are not available right now.', 503 );
    }
    $args = $wa->getGetArgs( array(), 120, true, true, true, true, true, 'required' );
    $id   = random_token();
    set_transient( 'hr_wa_' . substr( hash( 'sha256', $id ), 0, 40 ), b64url( $wa->getChallenge()->getBinaryString() ), 5 * MINUTE_IN_SECONDS );
    set_cookie( 'hr_wa', $id, 5 * MINUTE_IN_SECONDS, '/', 'Strict' );
    return rest_ensure_response( $args->publicKey );
}

/** Shared by sign-in and step-up: verify an assertion against a stored credential row; updates the counter. */
function verify_assertion( array $bin, object $row, string $challenge ): bool {
    $wa = webauthn();
    if ( ! $wa || ! origin_ok( $bin['clientDataJSON'] ) ) {
        return false;
    }
    try {
        $wa->processGet( $bin['clientDataJSON'], $bin['authenticatorData'], $bin['signature'], (string) $row->public_key, b64url_decode( $challenge ), (int) $row->sign_count, true, true );
    } catch ( WebAuthnException $e ) {
        $event = WebAuthnException::SIGNATURE_COUNTER === $e->getCode() ? 'passkey_counter_regression' : 'passkey_assertion_failed';
        log_security( $event, array( 'uid' => (int) $row->user_id, 'code' => $e->getCode() ) );
        return false;
    }
    global $wpdb;
    $wpdb->update( passkeys_table(), array( 'sign_count' => (int) ( $wa->getSignatureCounter() ?? 0 ), 'last_used_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $row->id ), array( '%d', '%s' ), array( '%d' ) );
    return true;
}

function find_credential( string $raw_id, int $uid = 0 ): ?object {
    global $wpdb;
    $table = passkeys_table();
    $sql   = $uid
        ? $wpdb->prepare( "SELECT * FROM {$table} WHERE credential_id_hash = %s AND user_id = %d", hash( 'sha256', $raw_id ), $uid )
        : $wpdb->prepare( "SELECT * FROM {$table} WHERE credential_id_hash = %s", hash( 'sha256', $raw_id ) );
    $row = $wpdb->get_row( $sql );
    return $row ?: null;
}

function rest_login_verify( \WP_REST_Request $request ) {
    no_store();
    if ( ! rate_limit( 'pk-login-verify', client_ip(), 30, 10 * MINUTE_IN_SECONDS ) ) {
        return rest_error( 'hr_rate_limited', 'Too many attempts. Please wait a few minutes.', 429 );
    }
    $ceremony = read_cookie( 'hr_wa', 64 );
    set_cookie( 'hr_wa', '', 0, '/', 'Strict' );
    $key       = 'hr_wa_' . substr( hash( 'sha256', $ceremony ), 0, 40 );
    $challenge = is_b64url( $ceremony ) ? (string) get_transient( $key ) : '';
    delete_transient( $key ); // single use, whatever happens next
    if ( '' === $challenge ) {
        return rest_error( 'hr_passkey_expired', 'Your passkey sign-in expired. Please try again.', 400 );
    }
    $body = json_body( $request, 32768 );
    $bin  = bin_fields( $body, array( 'id' => 1400, 'clientDataJSON' => 4096, 'authenticatorData' => 4096, 'signature' => 2048, 'userHandle' => 128 ) );
    if ( null === $bin ) {
        return generic_passkey_error();
    }
    $row  = find_credential( $bin['id'] );
    $user = $row ? get_userdata( (int) $row->user_id ) : false;
    // The authenticator says which account this passkey belongs to; it must be the account that registered it.
    if ( ! $row || ! $user || ! hash_equals( (string) get_user_meta( $user->ID, 'hr_webauthn_handle', true ), b64url( $bin['userHandle'] ) ) ) {
        log_security( 'passkey_login_unknown' );
        return generic_passkey_error();
    }
    if ( ! verify_assertion( $bin, $row, $challenge ) ) {
        return generic_passkey_error();
    }
    $remember = ! empty( $body['remember'] );
    $redirect = safe_redirect_target( is_string( $body['redirect_to'] ?? null ) ? $body['redirect_to'] : '', account_url() );
    complete_login( $user, $remember, 'passkey' );
    return rest_ensure_response( array( 'ok' => true, 'redirect' => esc_url_raw( $redirect ) ) );
}

function rest_stepup_options( \WP_REST_Request $request ) {
    no_store();
    $uid = get_current_user_id();
    if ( ! rate_limit( 'pk-stepup', (string) $uid, 20, HOUR_IN_SECONDS ) ) {
        return rest_error( 'hr_rate_limited', 'Too many attempts. Please try again later.', 429 );
    }
    global $wpdb;
    $table = passkeys_table();
    $ids   = array_map( 'HR\Platform\b64url_decode', (array) $wpdb->get_col( $wpdb->prepare( "SELECT credential_id FROM {$table} WHERE user_id = %d", $uid ) ) );
    $wa    = webauthn();
    if ( ! $ids || ! $wa ) {
        return rest_error( 'hr_bad_request', 'There is no passkey on this account.', 400 );
    }
    $args = $wa->getGetArgs( $ids, 120, true, true, true, true, true, 'required' );
    set_transient( challenge_key( 'su', $uid ), b64url( $wa->getChallenge()->getBinaryString() ), 5 * MINUTE_IN_SECONDS );
    return rest_ensure_response( $args->publicKey );
}

function rest_stepup_verify( \WP_REST_Request $request ) {
    no_store();
    $uid       = get_current_user_id();
    $key       = challenge_key( 'su', $uid );
    $challenge = (string) get_transient( $key );
    delete_transient( $key );
    $bin = bin_fields( json_body( $request, 32768 ), array( 'id' => 1400, 'clientDataJSON' => 4096, 'authenticatorData' => 4096, 'signature' => 2048 ) );
    if ( '' === $challenge || null === $bin ) {
        return rest_error( 'hr_passkey_expired', 'That took a little too long. Please try again.', 400 );
    }
    $row = find_credential( $bin['id'], $uid );
    if ( ! $row || ! verify_assertion( $bin, $row, $challenge ) ) {
        return rest_error( 'hr_passkey_failed', 'That passkey could not be verified. Try again or use your password.', 400 );
    }
    mark_recent_auth( $uid );
    flash( 'stepup_ok' );
    return rest_ensure_response( array( 'ok' => true ) );
}
