<?php
/**
 * Settings > Content signing (options-general.php?page=hr-signing): server key setup and the backlog button.
 *
 * Key actions (import, generate, re-wrap, remove) need manage_options, a nonce, a same-origin request and the
 * admin's current WordPress password (plus their two-step code when they have one); they are rate limited and
 * logged. The backlog loop (admin-ajax.php?action=hr_pqsig_backlog) needs manage_options, a nonce and same origin.
 * The secret key is never echoed, logged, or kept in the database or a transient.
 */

namespace HR\Platform\Signing;

use function HR\Platform\asset_url;
use function HR\Platform\asset_ver;
use function HR\Platform\b64url_decode;
use function HR\Platform\is_b64url;
use function HR\Platform\log_security;
use function HR\Platform\no_store;
use function HR\Platform\rate_limit;
use function HR\Platform\render;
use function HR\Platform\same_origin_request;

if ( ! defined( 'ABSPATH' ) ) exit;

const ADMIN_SLUG       = 'hr-signing';
const KEY_ACTIONS      = array( 'import', 'generate', 'rewrap', 'remove' );
const IMPORT_MAX_BYTES = 65536;

function admin_page_url( array $args = array() ): string {
    return add_query_arg( $args, admin_url( 'options-general.php?page=' . ADMIN_SLUG ) );
}

add_action( 'admin_menu', function () {
    $hook = add_options_page( 'Content signing', 'Content signing', 'manage_options', ADMIN_SLUG, __NAMESPACE__ . '\\admin_screen' );
    add_action( 'admin_enqueue_scripts', static function ( $current ) use ( $hook ) {
        if ( $current !== $hook ) {
            return;
        }
        wp_enqueue_style( 'hr-signing-admin', asset_url( 'css/signing-admin.css' ), array(), asset_ver( 'css/signing-admin.css' ) );
        wp_enqueue_script( 'hr-signing-admin', asset_url( 'js/signing-admin.js' ), array(), asset_ver( 'js/signing-admin.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
    } );
} );

// ---------------------------------------------------------------------------------------------------------------
// Re-authentication
// ---------------------------------------------------------------------------------------------------------------

function user_has_totp( int $uid ): bool {
    return function_exists( '\HR\Platform\Accounts\has_totp' ) ? \HR\Platform\Accounts\has_totp( $uid ) : '' !== (string) get_user_meta( $uid, 'hr_totp', true );
}

/** '' when the admin proved who they are again, else a plain-language reason. */
function reauthenticate( \WP_User $user ): string {
    $password = isset( $_POST['hr_pqsig_password'] ) && is_string( $_POST['hr_pqsig_password'] ) ? (string) wp_unslash( $_POST['hr_pqsig_password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- a password is compared, never stored or shown
    if ( '' === $password || strlen( $password ) > 4096 ) {
        return 'Enter your WordPress password to confirm.';
    }
    unset( $_POST['hr_pqsig_password'], $_REQUEST['hr_pqsig_password'] ); // nothing later in the request (error handlers included) sees it
    $ok = wp_check_password( $password, $user->user_pass, $user->ID );
    wipe( $password );
    if ( ! $ok ) {
        return 'That password is not correct.';
    }
    if ( ! user_has_totp( (int) $user->ID ) ) {
        return '';
    }
    if ( ! function_exists( '\HR\Platform\Accounts\verify_second_factor' ) ) {
        return 'Your account has two-step verification, but it cannot be checked right now. Nothing was changed.';
    }
    $code = isset( $_POST['hr_pqsig_code'] ) && is_string( $_POST['hr_pqsig_code'] ) ? sanitize_text_field( wp_unslash( $_POST['hr_pqsig_code'] ) ) : '';
    if ( '' === $code ) {
        return 'Enter the code from your authenticator app (or a recovery code).';
    }
    $result = \HR\Platform\Accounts\verify_second_factor( (int) $user->ID, $code );
    if ( 'limited' === $result ) {
        return 'Too many codes were tried. Wait 15 minutes and try again.';
    }
    return $result ? '' : 'That two-step code is not correct.';
}

// ---------------------------------------------------------------------------------------------------------------
// Notices (one per admin, no secrets)
// ---------------------------------------------------------------------------------------------------------------

function set_notice( string $type, string $message ): void {
    set_transient( 'hr_pqsig_notice_' . get_current_user_id(), array( 'type' => $type, 'message' => $message ), 5 * MINUTE_IN_SECONDS );
}

function take_notice(): ?array {
    $key    = 'hr_pqsig_notice_' . get_current_user_id();
    $notice = get_transient( $key );
    delete_transient( $key );
    return is_array( $notice ) ? $notice : null;
}

function finish( string $type, string $message ): void {
    set_notice( $type, $message );
    wp_safe_redirect( admin_page_url() . '#hr-pqsig-key' );
    exit;
}

// ---------------------------------------------------------------------------------------------------------------
// Key actions
// ---------------------------------------------------------------------------------------------------------------

add_action( 'admin_post_hr_pqsig_key', function () {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'You are not allowed to do that.', '', array( 'response' => 403 ) );
    }
    // An uploaded key file is overwritten before PHP deletes it, on every path (wrong password, rate limit, error).
    register_shutdown_function( static function () {
        $tmp = $_FILES['hr_pqsig_file']['tmp_name'] ?? ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        if ( is_string( $tmp ) && '' !== $tmp && is_uploaded_file( $tmp ) ) {
            shred_file( $tmp );
        }
    } );
    $op = isset( $_POST['op'] ) && is_string( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';
    if ( ! in_array( $op, KEY_ACTIONS, true ) ) {
        wp_die( 'Unknown action.', '', array( 'response' => 400 ) );
    }
    check_admin_referer( 'hr_pqsig_key_' . $op, 'hr_pqsig_nonce' );
    if ( ! same_origin_request() ) {
        log_security( 'pqsig_key_cross_origin', array( 'op' => $op ) );
        wp_die( 'You are not allowed to do that.', '', array( 'response' => 403 ) );
    }
    no_store();
    $user = wp_get_current_user();
    $uid  = (int) $user->ID;
    if ( ! rate_limit( 'pqsig-key-admin', (string) $uid, 10, 15 * MINUTE_IN_SECONDS ) ) {
        log_security( 'pqsig_key_rate_limited', array( 'op' => $op ) );
        finish( 'error', 'Too many attempts. Wait 15 minutes and try again.' );
    }
    $problem = reauthenticate( $user );
    if ( '' !== $problem ) {
        log_security( 'pqsig_key_reauth_failed', array( 'op' => $op ) );
        finish( 'error', $problem . ' Nothing was changed.' );
    }
    if ( function_exists( 'set_time_limit' ) ) {
        @set_time_limit( 60 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- not allowed on some hosts
    }
    if ( ! self_test_result( true ) ) {
        log_security( 'pqsig_key_selftest_failed', array( 'op' => $op ) );
        finish( 'error', 'The signer\'s self-test failed on this server, so no key was set up. Nothing was changed.' );
    }
    switch ( $op ) {
        case 'import':
            $result = import_key_from_request();
            break;
        case 'generate':
            $result = generate_server_key();
            break;
        case 'rewrap':
            $result = rewrap_server_key();
            break;
        default:
            $result = remove_server_key() ? 'The signing key was overwritten and deleted from this server. Automatic signing stops until a key is installed again.'
                : new \WP_Error( 'hr_pqsig_remove', 'The key file could not be deleted. Remove it by hand (see docs/SIGNING.md).' );
    }
    if ( is_wp_error( $result ) ) {
        log_security( 'pqsig_key_' . $op . '_failed', array( 'reason' => $result->get_error_code() ) );
        finish( 'error', $result->get_error_message() . ( 'remove' === $op ? '' : ' Nothing was changed.' ) );
    }
    $meta = key_meta();
    log_security( 'pqsig_key_' . ( 'import' === $op ? 'imported' : ( 'generate' === $op ? 'generated' : ( 'rewrap' === $op ? 'rewrapped' : 'removed' ) ) ), array( 'kid' => $meta ? substr( $meta['kid'], 0, 16 ) : '' ) );
    if ( 'remove' !== $op ) {
        schedule_retry(); // start on the backlog right away
    }
    finish( 'success', (string) $result );
} );

/** The pasted or uploaded signing.secret.json, or a WP_Error. */
function read_import_input() {
    $paste = isset( $_POST['hr_pqsig_paste'] ) && is_string( $_POST['hr_pqsig_paste'] ) ? trim( (string) wp_unslash( $_POST['hr_pqsig_paste'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- parsed as JSON and validated, never stored or shown
    unset( $_POST['hr_pqsig_paste'], $_REQUEST['hr_pqsig_paste'] ); // nothing later in the request sees the secret
    $file = $_FILES['hr_pqsig_file'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- checked below
    if ( is_array( $file ) && isset( $file['error'], $file['tmp_name'] ) && UPLOAD_ERR_NO_FILE !== (int) $file['error'] ) {
        if ( UPLOAD_ERR_OK !== (int) $file['error'] || ! is_uploaded_file( (string) $file['tmp_name'] ) ) {
            return new \WP_Error( 'hr_pqsig_upload', 'The file could not be uploaded.' );
        }
        $size = (int) filesize( (string) $file['tmp_name'] );
        $raw  = $size > 0 && $size <= IMPORT_MAX_BYTES ? (string) file_get_contents( (string) $file['tmp_name'] ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
        shred_file( (string) $file['tmp_name'] );
        return '' === $raw ? new \WP_Error( 'hr_pqsig_upload', 'That file is empty or too large to be a signing key.' ) : $raw;
    }
    $raw = $paste;
    if ( '' === $raw ) {
        return new \WP_Error( 'hr_pqsig_empty', 'Choose signing.secret.json or paste its contents.' );
    }
    return strlen( $raw ) > IMPORT_MAX_BYTES ? new \WP_Error( 'hr_pqsig_upload', 'That is too large to be a signing key.' ) : $raw;
}

/** Validate and store an existing key (signing.secret.json). Returns a success message or a WP_Error. */
function import_key_from_request() {
    $raw = read_import_input();
    if ( is_wp_error( $raw ) ) {
        return $raw;
    }
    $data = json_decode( $raw, true, 4 );
    wipe( $raw );
    $result = import_key( $data );
    if ( is_array( $data ) ) {
        foreach ( array( 'mldsa87', 'ed448' ) as $field ) {
            if ( isset( $data[ $field ] ) && is_string( $data[ $field ] ) ) {
                wipe( $data[ $field ] );
            }
        }
    }
    return $result;
}

function import_key( $data ) {
    if ( is_array( $data ) && isset( $data['keys'] ) ) {
        return new \WP_Error( 'hr_pqsig_public', 'This is the public key file (signing.public.json). Import signing.secret.json instead.' );
    }
    $fields = is_array( $data ) ? array_keys( $data ) : array();
    sort( $fields );
    if ( array( 'alg', 'ed448', 'kid', 'mldsa87' ) !== $fields ) {
        return new \WP_Error( 'hr_pqsig_shape', 'This is not a signing.secret.json file (it should hold alg, kid, mldsa87 and ed448 and nothing else).' );
    }
    if ( ALG !== $data['alg'] ) {
        return new \WP_Error( 'hr_pqsig_alg', 'This key is not an ML-DSA-87+Ed448 signing key.' );
    }
    if ( ! is_string( $data['kid'] ) || 1 !== preg_match( '/^[0-9a-f]{64}$/', $data['kid'] ) || ! is_b64url( $data['mldsa87'], 7000 ) || ! is_b64url( $data['ed448'], 100 ) ) {
        return new \WP_Error( 'hr_pqsig_shape', 'The key file is damaged.' );
    }
    $sk      = b64url_decode( $data['mldsa87'] );
    $ek      = b64url_decode( $data['ed448'] );
    $problem = check_secret_key( $data['kid'], $sk, $ek );
    if ( '' !== $problem ) {
        wipe( $sk );
        wipe( $ek );
        return new \WP_Error( 'hr_pqsig_check', $problem );
    }
    $saved = save_server_key( $data['kid'], $sk, $ek, 'imported' );
    wipe( $sk );
    wipe( $ek );
    if ( is_wp_error( $saved ) ) {
        return $saved;
    }
    return sprintf( 'Key %s imported, checked against the published keyset and stored encrypted. New and edited articles are now signed automatically.', group_hex( $data['kid'], 4 ) );
}

/** Store a keyset (normalised) without running the whole settings form sanitizer over the other settings. */
function save_keyset( array $keyset ): bool {
    $normal = normalize_keyset( $keyset );
    if ( ! $normal ) {
        return false;
    }
    write_keyset_setting( (string) wp_json_encode( $normal, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) );
    return keyset() === $normal;
}

/**
 * Write only pq_keyset. The settings form's sanitizer expects a full form post (it would switch unposted checkboxes
 * off), so it is bypassed for this one already-validated value.
 */
function write_keyset_setting( string $json ): void {
    $settings              = (array) get_option( 'hr_platform_settings', array() );
    $settings['pq_keyset'] = $json;
    $callback              = 'HR\\Platform\\sanitize_settings';
    $priority              = has_filter( 'sanitize_option_hr_platform_settings', $callback );
    if ( false !== $priority ) {
        remove_filter( 'sanitize_option_hr_platform_settings', $callback, $priority );
    }
    update_option( 'hr_platform_settings', $settings );
    if ( false !== $priority ) {
        add_filter( 'sanitize_option_hr_platform_settings', $callback, $priority );
    }
    sync_keyset_file();
}

/** Make a dedicated server key, publish its public half in the keyset and store the secret. */
function generate_server_key() {
    $current = keyset() ?? array( 'keys' => array() );
    if ( count( $current['keys'] ) >= 16 ) {
        return new \WP_Error( 'hr_pqsig_full', 'The keyset already holds 16 keys. Remove an old one in Settings > Headline Recorder first.' );
    }
    try {
        $pq = \HR\Platform\PQ\mldsa87_keygen();
        $ec = \HR\Platform\PQ\ed448_keygen();
    } catch ( \Throwable $e ) {
        return new \WP_Error( 'hr_pqsig_keygen', 'A key could not be generated on this server.' );
    }
    $kid    = hash( 'sha256', $pq['pk'] . $ec['pk'] );
    $next   = $current;
    $next['keys'][] = array( 'alg' => ALG, 'kid' => $kid, 'mldsa87' => b64url( $pq['pk'] ), 'ed448' => b64url( $ec['pk'] ), 'created' => iso_now_ms() );
    if ( ! save_keyset( $next ) ) {
        return new \WP_Error( 'hr_pqsig_keyset', 'The new public key could not be added to the keyset.' );
    }
    $problem = check_secret_key( $kid, $pq['sk'], $ec['sk'] );
    $saved   = '' === $problem ? save_server_key( $kid, $pq['sk'], $ec['sk'], 'generated' ) : new \WP_Error( 'hr_pqsig_check', $problem );
    wipe( $pq['sk'] );
    wipe( $ec['sk'] );
    if ( is_wp_error( $saved ) ) {
        if ( $current['keys'] ) {
            save_keyset( $current );
        } else {
            write_keyset_setting( '' ); // the new key was the only one
        }
        return $saved;
    }
    return sprintf( 'New server key %s generated and published in the keyset. Add it to the DNS _pqsig record (shown below) so partners can pin it.', group_hex( $kid, 4 ) );
}

/** Re-encrypt the installed key under HR_SIGNING_KEK once it is defined. */
function rewrap_server_key() {
    if ( ! kek_constant_valid() ) {
        return new \WP_Error( 'hr_pqsig_kek', 'HR_SIGNING_KEK is not defined (or not valid) in wp-config.php yet.' );
    }
    $key = read_server_key();
    if ( is_wp_error( $key ) ) {
        return $key;
    }
    $saved = save_server_key( $key['kid'], $key['mldsa87'], $key['ed448'], $key['source'], 'constant' );
    wipe( $key['mldsa87'] );
    wipe( $key['ed448'] );
    return is_wp_error( $saved ) ? $saved : 'The key is now encrypted with HR_SIGNING_KEK from wp-config.php. Changing the WordPress salts no longer affects it.';
}

// ---------------------------------------------------------------------------------------------------------------
// Backlog: "Sign all pending now"
// ---------------------------------------------------------------------------------------------------------------

function parse_cursor( string $raw ): ?array {
    return 1 === preg_match( '/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\|(\d{1,20})$/', $raw, $m ) ? array( $m[1], (int) $m[2] ) : null;
}

function public_post_count(): int {
    global $wpdb;
    return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish' AND post_password = ''" );
}

add_action( 'wp_ajax_hr_pqsig_backlog', function () {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'You are not allowed to do this.' ), 403 );
    }
    if ( ! check_ajax_referer( 'hr_pqsig_backlog', 'nonce', false ) || ! same_origin_request() ) {
        wp_send_json_error( array( 'message' => 'This page has expired. Reload it and try again.' ), 403 );
    }
    no_store();
    if ( ! rate_limit( 'pqsig-backlog', (string) get_current_user_id(), 1200, HOUR_IN_SECONDS ) ) {
        wp_send_json_error( array( 'message' => 'Too many batches in an hour. Wait a while, then continue.' ), 429 );
    }
    $cursor = isset( $_POST['cursor'] ) && is_string( $_POST['cursor'] ) ? parse_cursor( sanitize_text_field( wp_unslash( $_POST['cursor'] ) ) ) : null;
    $token  = acquire_lock( 90 );
    if ( '' === $token ) {
        wp_send_json_success( array( 'busy' => true ) );
    }
    if ( function_exists( 'set_time_limit' ) ) {
        @set_time_limit( 90 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- not allowed on some hosts
    }
    $start = microtime( true );
    try {
        $result          = sign_pending( 8.0, 0, $cursor );
        $result['feeds'] = $result['done'] && '' === $result['stopped'] ? sign_feeds( true ) : array();
    } catch ( \Throwable $e ) {
        error_log( 'hr-platform: signing backlog batch failed: ' . $e->getMessage() );
        $result = array( 'signed' => 0, 'current' => 0, 'failed' => array(), 'cursor' => $cursor, 'done' => false, 'stopped' => 'The signer stopped with an internal error (see the PHP error log).', 'feeds' => array() );
    } finally {
        release_lock( $token );
    }
    record_run( 'backlog', $result, microtime( true ) - $start );
    if ( $result['signed'] > 0 ) {
        log_security( 'pqsig_backlog_batch', array( 'signed' => $result['signed'], 'failed' => count( $result['failed'] ) ) );
    }
    $failed = array_map( static function ( $f ) {
        return array(
            'id'      => $f['id'],
            'title'   => html_entity_decode( wp_strip_all_tags( get_the_title( $f['id'] ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ?: sprintf( 'Article %d', $f['id'] ),
            'edit'    => esc_url_raw( (string) get_edit_post_link( $f['id'], 'raw' ) ),
            'message' => $f['message'],
        );
    }, $result['failed'] );
    $public  = public_post_count();
    $pending = queue_candidate_ids( 0, 0, true );
    wp_send_json_success( array(
        'busy'      => false,
        'signed'    => (int) $result['signed'],
        'checked'   => (int) $result['signed'] + (int) $result['current'] + count( $failed ),
        'failed'    => $failed,
        'cursor'    => $result['cursor'] ? $result['cursor'][0] . '|' . $result['cursor'][1] : '',
        'done'      => (bool) $result['done'],
        'stopped'   => (string) $result['stopped'],
        'feeds'     => $result['feeds'],
        'pending'   => $pending,
        'public'    => $public,
        'signedAll' => max( 0, $public - $pending ),
    ) );
} );

// ---------------------------------------------------------------------------------------------------------------
// Screen
// ---------------------------------------------------------------------------------------------------------------

/** Everything the screens show, none of it secret. */
function status_summary(): array {
    $public  = public_post_count();
    $pending = queue_candidate_ids( 0, 0, true );
    $key     = key_meta() ? server_key() : new \WP_Error( 'hr_pqsig_no_key', 'No server signing key is installed.' );
    $run     = get_option( STATUS_OPTION );
    $errors  = get_option( ERRORS_OPTION );
    $next    = wp_next_scheduled( SWEEP_EVENT );
    return array(
        'enabled'  => autosign_enabled(),
        'ready'    => pq_ready(),
        'public'   => $public,
        'pending'  => $pending,
        'signed'   => max( 0, $public - $pending ),
        'key'      => key_status(),
        'usable'   => ! is_wp_error( $key ),
        'problem'  => is_wp_error( $key ) ? $key->get_error_message() : '',
        'run'      => is_array( $run ) ? $run : null,
        'errors'   => is_array( $errors ) ? array_reverse( $errors, true ) : array(),
        'next'     => $next ? (int) $next : 0,
        'cron_off' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
    );
}

function admin_screen(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $self_test = self_test_result( true );
    $summary   = status_summary();
    render( 'signing/admin', array(
        'summary'    => $summary,
        'self_test'  => $self_test,
        'notice'     => take_notice(),
        'keyset'     => keyset(),
        'kek_kind'   => kek_kind(),
        'kek_valid'  => kek_constant_valid(),
        'kek_line'   => kek_define_line(),
        'aes'        => aes_available(),
        'totp'       => user_has_totp( get_current_user_id() ),
        'post_url'   => admin_url( 'admin-post.php' ),
        'ajax_url'   => admin_url( 'admin-ajax.php' ),
        'nonce'      => wp_create_nonce( 'hr_pqsig_backlog' ),
        'settings'   => admin_url( 'options-general.php?page=hr-platform#hr-signing' ),
        'dns_host'   => '_pqsig.' . (string) wp_parse_url( home_url(), PHP_URL_HOST ),
    ) );
}

// A reminder where it is seen, while articles wait for a signature that will not come.
add_action( 'admin_notices', function () {
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( ! current_user_can( 'manage_options' ) || ! $screen || ! in_array( $screen->id, array( 'dashboard', 'edit-post' ), true ) || ! autosign_enabled() ) {
        return;
    }
    if ( key_meta() && ! is_wp_error( server_key() ) ) {
        return;
    }
    $pending = queue_candidate_ids( 0, 0, true );
    if ( $pending < 1 ) {
        return;
    }
    printf(
        '<div class="notice notice-warning"><p><strong>Content signing:</strong> %s <a href="%s">Set up signing</a></p></div>',
        esc_html( sprintf( '%s %s waiting for a signature because no usable server signing key is installed.', number_format_i18n( $pending ), 1 === $pending ? 'article is' : 'articles are' ) ),
        esc_url( admin_page_url() )
    );
} );

// Site Health: the signer's known-answer self test, run once per PHP and signer version.
add_filter( 'site_status_tests', function ( $tests ) {
    $tests['direct']['hr_pqsig_signer'] = array(
        'label' => 'Content signer',
        'test'  => static function () {
            $ok = self_test_result( true );
            return array(
                'label'       => $ok ? 'The post-quantum content signer works on this server' : 'The post-quantum content signer failed its self-test',
                'status'      => $ok ? 'good' : 'critical',
                'badge'       => array( 'label' => 'Security', 'color' => $ok ? 'blue' : 'red' ),
                'description' => '<p>' . esc_html( $ok ? 'ML-DSA-87 and Ed448 produce the expected known-answer results, so articles can be signed on this server.' : 'ML-DSA-87 or Ed448 gave a wrong result on this PHP build. Automatic signing is paused; use the offline signer (security/pq/wp-sign.mjs) and report this.' ) . '</p>',
                'actions'     => '<p><a href="' . esc_url( admin_page_url() ) . '">Settings &gt; Content signing</a></p>',
                'test'        => 'hr_pqsig_signer',
            );
        },
    );
    return $tests;
} );
