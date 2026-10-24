<?php
/**
 * Publisher keyset: the public half of the signing key(s), in Settings > Headline Recorder > Content signing.
 */

namespace HR\Platform\Signing;

use function HR\Platform\b64url_decode;
use function HR\Platform\is_b64url;
use function HR\Platform\setting;

if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'hr_settings_schema', function ( $schema ) {
    $screen = esc_url( admin_url( 'options-general.php?page=hr-signing' ) );
    $schema['signing'] = array(
        'title'       => 'Content signing',
        'description' => 'Every published article and the feeds are signed with post-quantum <strong>ML-DSA-87 + Ed448</strong> on this server, '
            . 'as soon as they are published or edited. The signing key is kept encrypted outside the web root and is managed in '
            . '<a href="' . $screen . '">Settings &gt; Content signing</a>, which also has the &ldquo;Sign all pending now&rdquo; button. '
            . 'The offline signer (<code>security/pq/wp-sign.mjs</code>, run by hand with a <em>Content signer</em> user and an application password) still works as an alternative.',
        'fields'      => array(
            'pq_autosign' => array(
                'label'       => 'Automatic signing',
                'type'        => 'checkbox',
                'default'     => 1,
                'description' => 'Sign new and edited articles straight away, and sweep for anything pending every 5 minutes. Needs a server signing key (see Settings &gt; Content signing).',
            ),
            'pq_keyset' => array(
                'label'       => 'Public signing keyset',
                'type'        => 'json',
                'default'     => '',
                'validate'    => __NAMESPACE__ . '\\is_valid_keyset',
                'description' => 'The public keys readers and partners verify against (published at <code>/.well-known/pqsig-keys.json</code>). '
                    . 'Generating a server key in Settings &gt; Content signing adds its public key here automatically. '
                    . 'For a key made on an offline machine (<code>cd security/pq &amp;&amp; node cli.mjs keygen sign signing</code>), paste the contents of <code>signing.public.json</code> here. '
                    . 'To rotate, add the new key to the list; to revoke one, add <code>"revoked": "YYYY-MM-DD"</code> to it and everything it signed is signed again with a current key.',
            ),
            'pq_keyset_sig' => array(
                'label'       => 'Keyset certificate (offline signature)',
                'type'        => 'json',
                'default'     => '',
                'validate'    => __NAMESPACE__ . '\\is_valid_keyset_sig',
                'description' => 'Proves the key list above was approved with your offline key, so a compromised server cannot add its own key unnoticed. '
                    . 'On your PC: download <code>/.well-known/pqsig-keys.json</code> from the site, run <code>node cli.mjs sign pqsig-keys.json --key signing.secret.json --type keyset --url /.well-known/pqsig-keys.json</code> in <code>security/pq</code>, and paste the contents of <code>pqsig-keys.json.sig.json</code> here. '
                    . 'Repeat whenever the key list changes. ' . ( is_admin() && 'hr-platform' === ( $_GET['page'] ?? '' ) ? 'Status: <strong>' . ( keyset_certified_by() ? 'certified by key ' . esc_html( substr( keyset_certified_by(), 0, 16 ) ) . '…' : 'not certified' ) . '</strong>.' : '' ),
            ),
        ),
    );
    return $schema;
} );

function is_valid_keyset_sig( $data ): bool {
    return is_array( $data ) && is_string( $data['protected'] ?? null ) && is_string( $data['mldsa87'] ?? null ) && is_string( $data['ed448'] ?? null ) && strlen( (string) wp_json_encode( $data ) ) < 16384;
}

/** The stored offline signature over the exact keyset bytes, or null. */
function keyset_signature(): ?array {
    $doc = json_decode( (string) setting( 'pq_keyset_sig' ), true );
    return is_array( $doc ) && is_valid_keyset_sig( $doc ) ? $doc : null;
}

/** Key ID that certified the current keyset (a non-revoked key in it signed type "keyset" over keyset_json()), or ''. */
function keyset_certified_by(): string {
    static $memo = null;
    if ( null !== $memo ) {
        return $memo;
    }
    $memo = ''; // re-entrancy guard: reading settings rebuilds the schema, whose description asks for this status
    $doc  = keyset_signature();
    $json = $doc ? keyset_json() : '';
    if ( ! $doc || '' === $json ) {
        return $memo = '';
    }
    // Verification costs ~30 ms; cache the answer for this exact keyset + signature pair.
    $cache = 'hr_pqsig_cert_' . md5( $json . wp_json_encode( $doc ) );
    $kid   = get_transient( $cache );
    if ( false === $kid ) {
        $kid = '';
        if ( function_exists( __NAMESPACE__ . '\\verify_document' ) && verify_document( $doc, $json ) ) {
            $header = json_decode( (string) \HR\Platform\b64url_decode( $doc['protected'] ), true );
            $kid    = 'keyset' === ( $header['type'] ?? '' ) ? (string) $header['kid'] : '';
        }
        set_transient( $cache, $kid, DAY_IN_SECONDS );
    }
    return $memo = (string) $kid;
}

// The verify page checks the DNS pin over DNS-over-HTTPS (a reader's browser cannot query TXT records directly).
add_filter( 'hr_csp_sources', function ( array $sources ): array {
    $sources['connect-src'][] = 'https://cloudflare-dns.com';
    return $sources;
} );

function is_valid_keyset( $data ): bool {
    return null !== normalize_keyset( $data );
}

/**
 * Strict structural check of {"keys":[{alg,kid,mldsa87,ed448,created?,revoked?}]}. The key ID must be the SHA-256 of
 * both public keys (as pq.mjs computes it), so a typo or a spliced entry is caught before anything is published.
 */
function normalize_keyset( $data ): ?array {
    if ( ! is_array( $data ) || array( 'keys' ) !== array_keys( $data ) || ! is_array( $data['keys'] ) || ! array_is_list( $data['keys'] ) ) {
        return null;
    }
    $count = count( $data['keys'] );
    if ( $count < 1 || $count > 16 ) {
        return null;
    }
    $out  = array();
    $seen = array();
    foreach ( $data['keys'] as $key ) {
        if ( ! is_array( $key ) || array_diff( array_keys( $key ), array( 'alg', 'kid', 'mldsa87', 'ed448', 'created', 'revoked' ) ) ) {
            return null;
        }
        $kid = $key['kid'] ?? null;
        if ( ALG !== ( $key['alg'] ?? null ) || ! is_string( $kid ) || 1 !== preg_match( '/^[0-9a-f]{64}$/', $kid ) || isset( $seen[ $kid ] ) ) {
            return null;
        }
        if ( ! is_b64url( $key['mldsa87'] ?? null, 4000 ) || ! is_b64url( $key['ed448'] ?? null, 100 ) ) {
            return null;
        }
        $pk = b64url_decode( $key['mldsa87'] );
        $ek = b64url_decode( $key['ed448'] );
        if ( MLDSA87_PK_BYTES !== strlen( $pk ) || ED448_PK_BYTES !== strlen( $ek ) || ! hash_equals( $kid, hash( 'sha256', $pk . $ek ) ) ) {
            return null;
        }
        $entry = array( 'alg' => ALG, 'kid' => $kid, 'mldsa87' => $key['mldsa87'], 'ed448' => $key['ed448'] );
        foreach ( array( 'created', 'revoked' ) as $field ) {
            if ( ! array_key_exists( $field, $key ) ) {
                continue;
            }
            if ( ! is_string( $key[ $field ] ) || strlen( $key[ $field ] ) > 40 || 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}(?:[T ][0-9:.]+(?:Z|[+-]\d{2}:?\d{2})?)?$/', $key[ $field ] ) ) {
                return null;
            }
            $entry[ $field ] = $key[ $field ];
        }
        $seen[ $kid ] = true;
        $out[]        = $entry;
    }
    return array( 'keys' => $out );
}

/** The configured keyset, normalised, or null when none (or an invalid one) is configured. */
function keyset(): ?array {
    static $cache = array();
    $raw = (string) setting( 'pq_keyset' );
    if ( ! array_key_exists( $raw, $cache ) ) {
        $cache = array( $raw => '' === $raw ? null : normalize_keyset( json_decode( $raw, true ) ) );
    }
    return $cache[ $raw ];
}

/** The public keyset document exactly as published, or '' when none is configured. */
function keyset_json(): string {
    $keyset = keyset();
    return $keyset ? wp_json_encode( $keyset, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) . "\n" : '';
}

/**
 * Many hosts (cPanel/LiteSpeed AutoSSL) answer /.well-known/ themselves, so the rewrite route never runs there.
 * Keep a static copy in the web root instead; it is also faster. The REST route hr/v1/signing/keyset always works.
 */
function sync_keyset_file(): void {
    $file    = ABSPATH . '.well-known/pqsig-keys.json';
    $json    = keyset_json();
    $current = is_readable( $file ) ? (string) file_get_contents( $file ) : null;
    if ( '' === $json ) {
        null !== $current && @unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- best effort
        return;
    }
    if ( $current === $json ) {
        return;
    }
    if ( ! wp_mkdir_p( dirname( $file ) ) || false === file_put_contents( $file, $json, LOCK_EX ) ) {
        error_log( 'hr-platform: could not write ' . $file . ' (the REST keyset URL still works)' );
        return;
    }
    @chmod( $file, 0644 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
}
add_action( 'update_option_hr_platform_settings', __NAMESPACE__ . '\\sync_keyset_file', 20 ); // after option() drops its cache
add_action( 'add_option_hr_platform_settings', __NAMESPACE__ . '\\sync_keyset_file', 20 );
add_action( 'admin_init', __NAMESPACE__ . '\\sync_keyset_file' ); // a keyset saved before this existed

/** Key IDs that may sign new content (present and not revoked). */
function valid_kids(): array {
    $kids = array();
    foreach ( keyset()['keys'] ?? array() as $key ) {
        if ( empty( $key['revoked'] ) ) {
            $kids[] = $key['kid'];
        }
    }
    return $kids;
}

function kid_is_valid( string $kid ): bool {
    return '' !== $kid && in_array( $kid, valid_kids(), true );
}

/** "a1b2 c3d4 ..." for people comparing key IDs by eye. */
function group_hex( string $hex, int $groups = 0 ): string {
    $parts = str_split( $hex, 4 );
    if ( $groups > 0 && count( $parts ) > $groups ) {
        return implode( ' ', array_slice( $parts, 0, $groups ) ) . ' …';
    }
    return implode( ' ', $parts );
}
