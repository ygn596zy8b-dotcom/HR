<?php
/**
 * The server signing key at rest.
 *
 * The secret key (the same JSON as signing.secret.json) is encrypted with AES-256-GCM and written to one file outside
 * the web root. It is never stored in the database, a transient, a log or a response. The database only holds
 * non-secret facts about it (key ID, where the file is, how it is wrapped) in the option hr_pqsig_server_key.
 *
 *   Key-encryption key (KEK): HR_SIGNING_KEK in wp-config.php (base64 of 32 random bytes) when defined, otherwise
 *   HKDF-SHA256 of AUTH_KEY, SECURE_AUTH_KEY, AUTH_SALT and SECURE_AUTH_SALT. Changing those salts (or the constant)
 *   makes the file unreadable: the key must then be imported or generated again.
 *   Additional data: a version tag, the key ID, home_url(), the source and the KEK kind, so a file cannot be swapped
 *   between sites or relabelled without failing to decrypt.
 *   Location: HR_PRIVATE_DIR/signing/ if defined, else <parent of ABSPATH>/hr-private/signing/ when that can be
 *   created, else wp-content/hr-private-<random>/signing/ with deny-all guards (the screen warns about this).
 *   Directory 0700, file 0600, written atomically (temp file + rename).
 */

namespace HR\Platform\Signing;

use function HR\Platform\b64url_decode;
use function HR\Platform\is_b64url;

if ( ! defined( 'ABSPATH' ) ) exit;

const KEY_OPTION   = 'hr_pqsig_server_key';
const KEY_FILE     = 'server-key.json';
const KEY_AAD_TAG  = 'hr-pqsig-server-key/v1';
const KEK_HKDF_TAG = 'hr-pqsig-kek/v1';

// ---------------------------------------------------------------------------------------------------------------
// Key-encryption key
// ---------------------------------------------------------------------------------------------------------------

/** 'constant' when HR_SIGNING_KEK is defined (valid or not), else 'salts'. */
function kek_kind(): string {
    return defined( 'HR_SIGNING_KEK' ) ? 'constant' : 'salts';
}

/** Whether HR_SIGNING_KEK is defined and is base64 of exactly 32 bytes. */
function kek_constant_valid(): bool {
    if ( ! defined( 'HR_SIGNING_KEK' ) || ! is_string( HR_SIGNING_KEK ) ) {
        return false;
    }
    $raw = base64_decode( trim( HR_SIGNING_KEK ), true );
    return is_string( $raw ) && 32 === strlen( $raw );
}

/** The 32-byte KEK of a kind, or null when it is not available. */
function kek( string $kind ): ?string {
    if ( 'constant' === $kind ) {
        return kek_constant_valid() ? (string) base64_decode( trim( HR_SIGNING_KEK ), true ) : null;
    }
    $parts = array();
    foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT' ) as $name ) {
        $value = defined( $name ) ? (string) constant( $name ) : '';
        if ( strlen( $value ) < 32 || 'put your unique phrase here' === $value ) {
            return null; // default or missing salts would make the "encryption" a public constant
        }
        $parts[] = $value;
    }
    return hash_hkdf( 'sha256', implode( '', $parts ), 32, KEK_HKDF_TAG );
}

/** A fresh line for wp-config.php. Shown on the signing screen; never stored. */
function kek_define_line(): string {
    return "define( 'HR_SIGNING_KEK', '" . base64_encode( random_bytes( 32 ) ) . "' );";
}

function aes_available(): bool {
    return function_exists( 'openssl_encrypt' ) && in_array( 'aes-256-gcm', array_map( 'strtolower', (array) openssl_get_cipher_methods() ), true );
}

function key_aad( string $kid, string $source, string $kek_kind ): string {
    return implode( "\n", array( KEY_AAD_TAG, $kid, untrailingslashit( home_url() ), $source, $kek_kind ) );
}

// ---------------------------------------------------------------------------------------------------------------
// Location
// ---------------------------------------------------------------------------------------------------------------

/** Folders that are served to the public: the WordPress folder and, in a web request, the document root. */
function web_roots(): array {
    $roots = array();
    foreach ( array( ABSPATH, $_SERVER['DOCUMENT_ROOT'] ?? '' ) as $dir ) {
        $real = '' !== (string) $dir ? realpath( (string) $dir ) : false;
        if ( $real ) {
            $roots[] = rtrim( $real, '/\\' );
        }
    }
    return array_unique( $roots );
}

function is_inside_web_root( string $path ): bool {
    $real = realpath( $path );
    $real = $real ? $real : $path;
    foreach ( web_roots() as $root ) {
        if ( $real === $root || str_starts_with( $real, $root . DIRECTORY_SEPARATOR ) ) {
            return true;
        }
    }
    return false;
}

/**
 * The key directory for a location kind. Rebuilt from code every time (only the random suffix of the wp-content
 * fallback is stored), so a database edit cannot point the signer at another file.
 */
function key_dir_for( string $location, string $slug = '' ): string {
    switch ( $location ) {
        case 'private':
            return defined( 'HR_PRIVATE_DIR' ) && is_string( HR_PRIVATE_DIR ) && '' !== HR_PRIVATE_DIR
                ? rtrim( HR_PRIVATE_DIR, '/\\' ) . '/signing' : '';
        case 'parent':
            return rtrim( dirname( rtrim( ABSPATH, '/\\' ) ), '/\\' ) . '/hr-private/signing';
        case 'content':
            return 1 === preg_match( '/^[0-9a-f]{24}$/', $slug ) ? WP_CONTENT_DIR . '/hr-private-' . $slug . '/signing' : '';
    }
    return '';
}

/** Deny-all guards, for any web server that could still reach the folder. */
function write_guards( string $dir ): void {
    $guards = array(
        '.htaccess'  => "# Private signing key: never served.\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n",
        'index.php'  => "<?php\n// Silence.\n",
        'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n",
    );
    foreach ( $guards as $name => $body ) {
        if ( ! file_exists( "{$dir}/{$name}" ) ) {
            @file_put_contents( "{$dir}/{$name}", $body ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- best effort
        }
    }
}

/** Create a 0700 directory (and its parents). Quiet under open_basedir; true when it exists and is writable. */
function make_private_dir( string $dir ): bool {
    if ( '' === $dir ) {
        return false;
    }
    if ( ! @is_dir( $dir ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors -- open_basedir warnings
        $old = umask( 0077 );
        $ok  = @mkdir( $dir, 0700, true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
        umask( $old );
        if ( ! $ok && ! @is_dir( $dir ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
            return false;
        }
    }
    @chmod( $dir, 0700 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
    if ( ! @is_writable( $dir ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
        return false;
    }
    write_guards( $dir );
    return true;
}

/**
 * Pick where a new key file goes. Returns array( location, slug, dir ) or a WP_Error.
 * HR_PRIVATE_DIR is the owner's explicit choice, so it is never silently skipped.
 */
function choose_key_dir() {
    $current = get_option( KEY_OPTION );
    if ( defined( 'HR_PRIVATE_DIR' ) ) {
        $dir = key_dir_for( 'private' );
        return make_private_dir( $dir ) ? array( 'private', '', $dir )
            : new \WP_Error( 'hr_pqsig_dir', 'HR_PRIVATE_DIR is set in wp-config.php, but its signing folder could not be created or is not writable.' );
    }
    $dir = key_dir_for( 'parent' );
    if ( ! is_inside_web_root( dirname( $dir, 2 ) ) && make_private_dir( $dir ) ) {
        return array( 'parent', '', $dir );
    }
    // Reuse an earlier random folder rather than leaving several behind.
    $slug = is_array( $current ) && 'content' === ( $current['location'] ?? '' ) ? (string) ( $current['slug'] ?? '' ) : '';
    if ( 1 !== preg_match( '/^[0-9a-f]{24}$/', $slug ) ) {
        $slug = bin2hex( random_bytes( 12 ) );
    }
    $dir = key_dir_for( 'content', $slug );
    if ( make_private_dir( dirname( $dir ) ) && make_private_dir( $dir ) ) {
        return array( 'content', $slug, $dir );
    }
    return new \WP_Error( 'hr_pqsig_dir', 'No private folder could be created for the signing key. Set HR_PRIVATE_DIR in wp-config.php to a writable folder outside public_html.' );
}

/** Metadata of the installed key (no secrets), or null. */
function key_meta(): ?array {
    $meta = get_option( KEY_OPTION );
    if ( ! is_array( $meta ) || ! is_string( $meta['kid'] ?? null ) || 1 !== preg_match( '/^[0-9a-f]{64}$/', $meta['kid'] ) ) {
        return null;
    }
    $meta['dir']  = key_dir_for( (string) ( $meta['location'] ?? '' ), (string) ( $meta['slug'] ?? '' ) );
    $meta['file'] = '' === $meta['dir'] ? '' : $meta['dir'] . '/' . KEY_FILE;
    return $meta;
}

// ---------------------------------------------------------------------------------------------------------------
// Files
// ---------------------------------------------------------------------------------------------------------------

/** Write bytes to $file atomically with mode 0600: a random temp file in the same folder, then rename. */
function atomic_write( string $file, string $bytes ): bool {
    $dir  = dirname( $file );
    $tmp  = $dir . '/.tmp-' . bin2hex( random_bytes( 8 ) );
    $old  = umask( 0077 );
    $fh   = @fopen( $tmp, 'xb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
    umask( $old );
    if ( ! $fh ) {
        return false;
    }
    $ok = strlen( $bytes ) === fwrite( $fh, $bytes ) && fflush( $fh );
    fclose( $fh );
    @chmod( $tmp, 0600 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
    if ( ! $ok || ! @rename( $tmp, $file ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
        shred_file( $tmp );
        return false;
    }
    return true;
}

/** Overwrite a file with random bytes, then delete it. */
function shred_file( string $file ): bool {
    if ( ! @is_file( $file ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
        return true;
    }
    $size = (int) @filesize( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
    $fh   = @fopen( $file, 'r+b' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
    if ( $fh ) {
        fwrite( $fh, random_bytes( max( 1, $size ) ) );
        fflush( $fh );
        fclose( $fh );
    }
    return @unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
}

function wipe( ?string &$secret ): void {
    if ( is_string( $secret ) && '' !== $secret && function_exists( 'sodium_memzero' ) ) {
        sodium_memzero( $secret );
    }
    $secret = null;
}

/**
 * Encrypt and save a secret key (raw bytes). $source is 'imported' or 'generated'. Replaces any installed key.
 * Returns true or a WP_Error with a plain-language message.
 */
function save_server_key( string $kid, string $mldsa_sk, string $ed_sk, string $source, ?string $kek_kind = null ) {
    if ( ! aes_available() ) {
        return new \WP_Error( 'hr_pqsig_aes', 'This server\'s PHP has no AES-256-GCM (the OpenSSL extension is missing), so the key cannot be stored safely.' );
    }
    $kek_kind = $kek_kind ?? kek_kind();
    $kek      = kek( $kek_kind );
    if ( null === $kek ) {
        return new \WP_Error( 'hr_pqsig_kek', 'constant' === $kek_kind
            ? 'HR_SIGNING_KEK in wp-config.php is not valid: it must be the base64 of 32 random bytes. Use the line shown on this screen.'
            : 'The WordPress security keys in wp-config.php are missing or still the defaults, so the key cannot be encrypted. Add HR_SIGNING_KEK instead.' );
    }
    $where = choose_key_dir();
    if ( is_wp_error( $where ) ) {
        return $where;
    }
    [ $location, $slug, $dir ] = $where;
    $plain = (string) wp_json_encode( array( 'alg' => ALG, 'kid' => $kid, 'mldsa87' => b64url( $mldsa_sk ), 'ed448' => b64url( $ed_sk ) ), JSON_UNESCAPED_SLASHES );
    $iv    = random_bytes( 12 );
    $tag   = '';
    $ct    = openssl_encrypt( $plain, 'aes-256-gcm', $kek, OPENSSL_RAW_DATA, $iv, $tag, key_aad( $kid, $source, $kek_kind ), 16 );
    wipe( $plain );
    wipe( $kek );
    if ( false === $ct || 16 !== strlen( $tag ) ) {
        return new \WP_Error( 'hr_pqsig_encrypt', 'The key could not be encrypted.' );
    }
    $created = iso_now_ms();
    $file    = (string) wp_json_encode( array(
        'v'       => 1,
        'alg'     => 'AES-256-GCM',
        'kid'     => $kid,
        'source'  => $source,
        'kek'     => $kek_kind,
        'created' => $created,
        'iv'      => b64url( $iv ),
        'tag'     => b64url( $tag ),
        'ct'      => b64url( $ct ),
    ), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) . "\n";
    if ( ! atomic_write( $dir . '/' . KEY_FILE, $file ) ) {
        return new \WP_Error( 'hr_pqsig_write', 'The key file could not be written to the private folder.' );
    }
    // A key moved to a new folder must not leave its old copy behind.
    $previous = key_meta();
    if ( $previous && '' !== $previous['file'] && realpath( $previous['file'] ) !== realpath( $dir . '/' . KEY_FILE ) ) {
        shred_file( $previous['file'] );
    }
    update_option( KEY_OPTION, array(
        'kid'      => $kid,
        'source'   => $source,
        'kek'      => $kek_kind,
        'location' => $location,
        'slug'     => $slug,
        'created'  => $created,
    ), false );
    reset_server_key_cache();
    return true;
}

/**
 * Decrypt the installed key. Returns array( kid, mldsa87, ed448 ) with raw secret bytes, or a WP_Error.
 * Does not check it against the keyset; server_key() does.
 */
function read_server_key() {
    $meta = key_meta();
    if ( ! $meta ) {
        return new \WP_Error( 'hr_pqsig_no_key', 'No server signing key is installed.' );
    }
    $raw = '' !== $meta['file'] && @is_readable( $meta['file'] ) ? (string) @file_get_contents( $meta['file'] ) : ''; // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
    if ( '' === $raw ) {
        return new \WP_Error( 'hr_pqsig_missing', 'The signing key file is missing or cannot be read. Import or generate the key again.' );
    }
    $doc = json_decode( $raw, true );
    if ( ! is_array( $doc ) || 1 !== ( $doc['v'] ?? null ) || ! hash_equals( $meta['kid'], (string) ( $doc['kid'] ?? '' ) )
        || ! in_array( $doc['source'] ?? '', array( 'imported', 'generated' ), true ) || ! in_array( $doc['kek'] ?? '', array( 'constant', 'salts' ), true )
        || ! is_b64url( $doc['iv'] ?? null, 32 ) || ! is_b64url( $doc['tag'] ?? null, 32 ) || ! is_b64url( $doc['ct'] ?? null, 20000 ) ) {
        return new \WP_Error( 'hr_pqsig_corrupt', 'The signing key file is damaged. Import or generate the key again.' );
    }
    $kek = kek( $doc['kek'] );
    if ( null === $kek ) {
        return new \WP_Error( 'hr_pqsig_kek', 'constant' === $doc['kek']
            ? 'The key was encrypted with HR_SIGNING_KEK, which is no longer valid in wp-config.php. Put the same line back, or import or generate the key again.'
            : 'The WordPress security keys in wp-config.php are missing, so the key cannot be decrypted.' );
    }
    $plain = openssl_decrypt( b64url_decode( $doc['ct'] ), 'aes-256-gcm', $kek, OPENSSL_RAW_DATA, b64url_decode( $doc['iv'] ), b64url_decode( $doc['tag'] ), key_aad( $meta['kid'], $doc['source'], $doc['kek'] ) );
    wipe( $kek );
    if ( false === $plain ) {
        return new \WP_Error( 'hr_pqsig_decrypt', 'constant' === $doc['kek']
            ? 'The signing key could not be decrypted: HR_SIGNING_KEK in wp-config.php or the site address has changed, or the key file was altered. Import or generate the key again.'
            : 'The signing key could not be decrypted: the WordPress security keys (salts) in wp-config.php or the site address have changed, or the key file was altered. Import or generate the key again.' );
    }
    $secret = json_decode( $plain, true );
    wipe( $plain );
    $key = array(
        'kid'     => $meta['kid'],
        'mldsa87' => is_array( $secret ) && is_string( $secret['mldsa87'] ?? null ) ? b64url_decode( $secret['mldsa87'] ) : '',
        'ed448'   => is_array( $secret ) && is_string( $secret['ed448'] ?? null ) ? b64url_decode( $secret['ed448'] ) : '',
        'source'  => $doc['source'],
        'kek'     => $doc['kek'],
    );
    if ( is_array( $secret ) ) {
        foreach ( array( 'mldsa87', 'ed448' ) as $field ) {
            if ( isset( $secret[ $field ] ) && is_string( $secret[ $field ] ) ) {
                wipe( $secret[ $field ] );
            }
        }
    }
    if ( MLDSA87_SK_BYTES !== strlen( $key['mldsa87'] ) || ED448_SK_BYTES !== strlen( $key['ed448'] ) ) {
        return new \WP_Error( 'hr_pqsig_corrupt', 'The signing key file is damaged. Import or generate the key again.' );
    }
    return $key;
}

/**
 * The installed key, checked against the published keyset once per request (present, not revoked, both halves match).
 * Returns the key array or a WP_Error with a plain-language reason. The secret is wiped at the end of the request.
 */
function server_key( bool $reset = false ) {
    static $cache = null;
    if ( $reset ) {
        if ( is_array( $cache ) ) {
            wipe( $cache['mldsa87'] );
            wipe( $cache['ed448'] );
        }
        $cache = null;
        return null;
    }
    if ( null !== $cache ) {
        return $cache;
    }
    if ( ! pq_ready() ) {
        return $cache = new \WP_Error( 'hr_pqsig_php', 'This server cannot run the signer (it needs 64-bit PHP 8.2 or newer).' );
    }
    if ( false === self_test_result( false ) ) {
        return $cache = new \WP_Error( 'hr_pqsig_selftest', 'The signer\'s self-test failed on this server, so nothing is signed. Open Settings > Content signing for details.' );
    }
    $key = read_server_key();
    if ( is_wp_error( $key ) ) {
        return $cache = $key;
    }
    $problem = check_secret_key( $key['kid'], $key['mldsa87'], $key['ed448'] );
    if ( '' !== $problem ) {
        wipe( $key['mldsa87'] );
        wipe( $key['ed448'] );
        return $cache = new \WP_Error( 'hr_pqsig_mismatch', 'The installed signing key cannot be used: ' . lcfirst( $problem ) );
    }
    static $wipe_hooked = false;
    if ( ! $wipe_hooked ) {
        $wipe_hooked = true;
        add_action( 'shutdown', __NAMESPACE__ . '\\reset_server_key_cache', 999 );
    }
    return $cache = $key;
}

function reset_server_key_cache(): void {
    server_key( true );
}

/** Overwrite and delete the key file and forget it. */
function remove_server_key(): bool {
    $meta = key_meta();
    $ok   = true;
    if ( $meta && '' !== $meta['file'] ) {
        $ok = shred_file( $meta['file'] );
    }
    delete_option( KEY_OPTION );
    reset_server_key_cache();
    return $ok;
}

/** Non-secret status for the screens. */
function key_status(): array {
    $meta = key_meta();
    if ( ! $meta ) {
        return array( 'installed' => false );
    }
    $exists = '' !== $meta['file'] && @is_file( $meta['file'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
    $perms  = $exists ? ( @fileperms( $meta['file'] ) & 0777 ) : 0; // phpcs:ignore WordPress.PHP.NoSilencedErrors
    return array(
        'installed'   => true,
        'kid'         => $meta['kid'],
        'source'      => (string) ( $meta['source'] ?? '' ),
        'created'     => (string) ( $meta['created'] ?? '' ),
        'kek'         => (string) ( $meta['kek'] ?? '' ),
        'location'    => (string) ( $meta['location'] ?? '' ),
        'exists'      => $exists,
        'outside'     => $exists && ! is_inside_web_root( $meta['file'] ),
        'file_mode'   => $perms,
        'can_rewrap'  => 'salts' === ( $meta['kek'] ?? '' ) && kek_constant_valid(),
    );
}
