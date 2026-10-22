<?php
/**
 * Signature documents in PHP, byte-compatible with security/pq/pq.mjs sign() and verify().
 *
 * A document is { protected, mldsa87, ed448[, payload] }: protected = base64url of the header JSON bytes
 * {"v":1,"alg":"ML-DSA-87+Ed448","kid":…,"type":…,"url":…,"sha512":…,"signedAt":…} (that key order, slashes and
 * Unicode unescaped, as JSON.stringify writes it), and both signatures are over those header bytes with the
 * context 'pqsig-v1'. The primitives are the pure-PHP ML-DSA-87 and Ed448 in pq/.
 */

namespace HR\Platform\Signing;

use function HR\Platform\b64url_decode;
use function HR\Platform\is_b64url;

if ( ! defined( 'ABSPATH' ) ) exit;

const MLDSA87_SK_BYTES = 4896;
const ED448_SK_BYTES   = 57;

/** Whether this PHP can sign and verify (64-bit, working Keccak). Cheap. */
function pq_ready(): bool {
    return function_exists( '\HR\Platform\PQ\supported' ) && \HR\Platform\PQ\supported();
}

function b64url( string $bytes ): string {
    return rtrim( strtr( base64_encode( $bytes ), '+/', '-_' ), '=' );
}

/** Current time as JavaScript's Date.prototype.toISOString() writes it: UTC, milliseconds, "Z". */
function iso_now_ms(): string {
    $now = microtime( true );
    $sec = (int) floor( $now );
    $ms  = min( 999, (int) floor( ( $now - $sec ) * 1000 ) );
    return gmdate( 'Y-m-d\TH:i:s', $sec ) . sprintf( '.%03dZ', $ms );
}

/** JSON exactly as JSON.stringify() writes it for our headers: no escaped slashes, Unicode or line separators. */
function header_json( array $header ): string {
    return (string) json_encode( $header, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS | JSON_THROW_ON_ERROR );
}

/** Keyset entry for a key ID (revoked or not), or null. */
function keyset_entry( string $kid ): ?array {
    foreach ( keyset()['keys'] ?? array() as $key ) {
        if ( hash_equals( $key['kid'], $kid ) ) {
            return $key;
        }
    }
    return null;
}

/**
 * Sign bytes with a raw secret key array( kid, mldsa87 => 4896 raw bytes, ed448 => 57 raw bytes ).
 * Same header fields, order and encoding as pq.mjs sign(); $attach embeds the content as "payload".
 */
function make_signature( string $bytes, array $key, string $type, string $url, bool $attach ): array {
    $header = array(
        'v'        => 1,
        'alg'      => ALG,
        'kid'      => (string) $key['kid'],
        'type'     => $type,
        'url'      => $url,
        'sha512'   => hash( 'sha512', $bytes ),
        'signedAt' => iso_now_ms(),
    );
    $hdr = header_json( $header );
    $doc = array(
        'protected' => b64url( $hdr ),
        'mldsa87'   => b64url( \HR\Platform\PQ\mldsa87_sign( (string) $key['mldsa87'], $hdr, \HR\Platform\PQ\SIGNING_CONTEXT ) ),
        'ed448'     => b64url( \HR\Platform\PQ\ed448_sign( (string) $key['ed448'], $hdr, \HR\Platform\PQ\SIGNING_CONTEXT ) ),
    );
    if ( $attach ) {
        $doc['payload'] = b64url( $bytes );
    }
    return $doc;
}

/**
 * Full cryptographic check of a signature document against content bytes, as pq.mjs verify() does: the key is in the
 * keyset, not revoked and matches its key ID; ML-DSA-87 AND Ed448 verify over the header bytes; the header's SHA-512
 * covers the bytes. Never throws; false for anything malformed.
 */
function verify_document( $doc, string $bytes ): bool {
    if ( ! pq_ready() || ! is_array( $doc ) || ! is_b64url( $doc['protected'] ?? null, 8192 )
        || ! is_b64url( $doc['mldsa87'] ?? null, 6200 ) || ! is_b64url( $doc['ed448'] ?? null, 200 ) ) {
        return false;
    }
    $hdr    = b64url_decode( $doc['protected'] );
    $header = json_decode( $hdr, true, 4 );
    if ( ! is_array( $header ) || 1 !== ( $header['v'] ?? null ) || ALG !== ( $header['alg'] ?? null )
        || ! is_string( $header['kid'] ?? null ) || ! is_string( $header['sha512'] ?? null ) ) {
        return false;
    }
    $key = keyset_entry( $header['kid'] );
    if ( ! $key || ! empty( $key['revoked'] ) ) {
        return false;
    }
    $pk = b64url_decode( $key['mldsa87'] );
    $ek = b64url_decode( $key['ed448'] );
    if ( ! hash_equals( $key['kid'], hash( 'sha256', $pk . $ek ) ) || ! hash_equals( hash( 'sha512', $bytes ), $header['sha512'] ) ) {
        return false;
    }
    try {
        return \HR\Platform\PQ\mldsa87_verify( $pk, $hdr, b64url_decode( $doc['mldsa87'] ), \HR\Platform\PQ\SIGNING_CONTEXT )
            && \HR\Platform\PQ\ed448_verify( $ek, $hdr, b64url_decode( $doc['ed448'] ), \HR\Platform\PQ\SIGNING_CONTEXT );
    } catch ( \Throwable $e ) {
        return false;
    }
}

/**
 * Check a secret key (raw bytes) against the published keyset entry for its key ID: present, not revoked, both
 * halves match the public keys, and a test signature verifies. Returns '' when usable, else a plain-language reason.
 */
function check_secret_key( string $kid, string $mldsa_sk, string $ed_sk ): string {
    if ( ! pq_ready() ) {
        return 'This server cannot run the signer (it needs 64-bit PHP 8.2 or newer).';
    }
    if ( MLDSA87_SK_BYTES !== strlen( $mldsa_sk ) || ED448_SK_BYTES !== strlen( $ed_sk ) ) {
        return 'The key file is damaged: one of its two secret keys has the wrong length.';
    }
    $entry = keyset_entry( $kid );
    if ( ! $entry ) {
        return 'This key is not in the published keyset. Add its public key to the keyset first.';
    }
    if ( ! empty( $entry['revoked'] ) ) {
        return sprintf( 'This key was revoked on %s and can no longer sign.', substr( (string) $entry['revoked'], 0, 10 ) );
    }
    try {
        if ( ! \HR\Platform\PQ\mldsa87_key_matches( $mldsa_sk, b64url_decode( $entry['mldsa87'] ) ) ) {
            return 'The ML-DSA-87 half of this key does not match the published key with the same key ID.';
        }
        if ( ! hash_equals( b64url_decode( $entry['ed448'] ), \HR\Platform\PQ\ed448_public_key( $ed_sk ) ) ) {
            return 'The Ed448 half of this key does not match the published key with the same key ID.';
        }
        $probe = 'hr-pqsig key check ' . bin2hex( random_bytes( 16 ) );
        $doc   = make_signature( $probe, array( 'kid' => $kid, 'mldsa87' => $mldsa_sk, 'ed448' => $ed_sk ), 'file', '/key-check', false );
        if ( ! verify_document( $doc, $probe ) ) {
            return 'A test signature made with this key did not verify.';
        }
    } catch ( \Throwable $e ) {
        return 'The key file is damaged: it could not be used to sign.';
    }
    return '';
}

/**
 * Known-answer self test of the PHP signer, cached per PHP version and signer code version, so it runs once after an
 * install or upgrade (on the signing screen, at key setup or in Site Health), never on every request.
 */
function self_test_result( bool $run_if_missing = true ): ?bool {
    $files = glob( __DIR__ . '/pq/*.php' ) ?: array();
    $stamp = PHP_VERSION . '|' . md5( implode( '|', array_map( static fn( $f ) => basename( $f ) . ':' . filesize( $f ), $files ) ) );
    $saved = get_option( 'hr_pqsig_selftest' );
    if ( is_array( $saved ) && ( $saved['stamp'] ?? '' ) === $stamp ) {
        return (bool) $saved['ok'];
    }
    if ( ! $run_if_missing ) {
        return null;
    }
    $ok = pq_ready() && \HR\Platform\PQ\self_test();
    update_option( 'hr_pqsig_selftest', array( 'stamp' => $stamp, 'ok' => $ok, 'at' => time() ), false );
    return $ok;
}

/** A safe-to-show one-liner for an exception, without paths or secrets. */
function plain_error( \Throwable $e ): string {
    return $e instanceof \InvalidArgumentException ? 'The key is malformed.' : 'An internal error stopped the signer.';
}
