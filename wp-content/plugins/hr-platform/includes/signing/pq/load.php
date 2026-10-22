<?php
/**
 * Pure-PHP post-quantum signer: ML-DSA-87 (FIPS 204) and Ed448 (RFC 8032), plus SHAKE128/SHAKE256 (FIPS 202).
 *
 * Why pure PHP: articles are signed on the server when they are published, and the production host (shared
 * hosting, PHP 8.4) cannot be assumed to have GMP, BCMath, FFI or an OpenSSL build with ML-DSA, and PHP's
 * openssl_sign() cannot pass the Ed448 context string. Signatures are byte-compatible with the JavaScript
 * toolkit in security/pq/pq.mjs (@noble/post-quantum ml_dsa87, @noble/curves ed448), which signs the protected
 * header bytes with { context: 'pqsig-v1' } for both algorithms. The interop test is
 * security/pq/php-interop.test.mjs.
 *
 * Side channels, stated plainly: PHP is interpreted, so none of this is constant-time. Integer operations, array
 * reads and the allocator all have data-dependent timing that PHP gives no control over. Where it was easy the
 * code avoids secret-dependent branches and table indexes (masked table reads and a fixed operation sequence
 * for Ed448 scalar multiplication by secret scalars, a fixed number of scalar reduction steps, masked final
 * subtractions) and wipes secret strings with sodium_memzero() when it exists (PHP arrays cannot be reliably
 * wiped). That is acceptable here because signing runs only in an admin request or WP-Cron, never on input an
 * outsider controls or can time at volume: the signed bytes are content the publisher chose, so an attacker
 * cannot run the chosen-input timing campaigns these attacks need. Verification handles only public data.
 *
 * Requirements: 64-bit PHP (8.2+). No extensions beyond what PHP always ships (random_bytes, pack/unpack);
 * sodium is used only for wiping, when present.
 *
 * Public API (namespace HR\Platform\PQ):
 *   mldsa87_keygen( ?string $seed = null ): array{pk: string, sk: string}
 *   mldsa87_sign( string $sk, string $msg, string $ctx, ?string $rnd = null ): string
 *   mldsa87_verify( string $pk, string $msg, string $sig, string $ctx ): bool
 *   mldsa87_key_matches( string $sk, string $pk ): bool
 *   ed448_keygen(): array{pk: string, sk: string}
 *   ed448_public_key( string $sk ): string
 *   ed448_sign( string $sk, string $msg, string $ctx ): string
 *   ed448_verify( string $pk, string $msg, string $sig, string $ctx ): bool
 *   shake128( string $in, int $len ): string
 *   shake256( string $in, int $len ): string
 *   supported(): bool
 *   self_test(): bool
 *   SIGNING_CONTEXT = 'pqsig-v1'
 * All keys, messages and signatures are raw binary strings. Keygen and sign throw \InvalidArgumentException on
 * wrong input lengths; the verify functions return false for any malformed input and never throw.
 */

namespace HR\Platform\PQ;

if ( ! defined( 'ABSPATH' ) ) exit;

require_once __DIR__ . '/keccak.php';
require_once __DIR__ . '/mldsa87.php';
require_once __DIR__ . '/ed448.php';

/** Context string for both algorithms; must match CTX in security/pq/pq.mjs. */
const SIGNING_CONTEXT = 'pqsig-v1';

/** Whether this PHP build can run the signer (64-bit integers and a working Keccak). Cheap; cached. */
function supported(): bool {
    static $ok = null;
    if ( null === $ok ) {
        $ok = PHP_INT_SIZE >= 8
            && function_exists( 'random_bytes' )
            && '7f9c2ba4e88f827d616045507605853ed73b8093f6efbc88eb1a6eacfa66ef26' === bin2hex( Keccak::shake128( '', 32 ) )
            && '46b9dd2b0ba88d13233b3feb743eeb243fcd52ea62b81b82b50c27646ed5762f' === bin2hex( Keccak::shake256( '', 32 ) );
    }
    return $ok;
}

/**
 * Known-answer self test of every primitive (about 0.2 s): RFC 8032 Ed448 test vector with context "foo", and an
 * ML-DSA-87 keygen + deterministic signature checked against digests of @noble/post-quantum output.
 * Meant for a settings screen or a health check, not for every request.
 */
function self_test(): bool {
    if ( ! supported() ) {
        return false;
    }
    try {
        $sk  = hex2bin( 'c4eab05d357007c632f3dbb48489924d552b08fe0c353a0d4a1f00acda2c463afbea67c5e8d2877c5e3bc397a659949ef8021e954e0a12274e' );
        $pk  = '43ba28f430cdff456ae531545f7ecd0ac834a55d9358c0372bfa0c6c6798c0866aea01eb00742802b8438ea4cb82169c235160627b4c3a9480';
        $sig = 'd4f8f6131770dd46f40867d6fd5d5055de43541f8c5e35abbcd001b32a89f7d2151f7647f11d8ca2ae279fb842d607217fce6e042f6815ea000c85741de5c8da1144a6a1aba7f96de42505d7a7298524fda538fccbbb754f578c1cad10d54d0d5428407e85dcbc98a49155c13764e66c3c00';
        if ( ed448_public_key( $sk ) !== hex2bin( $pk ) || bin2hex( ed448_sign( $sk, "\x03", 'foo' ) ) !== $sig
            || ! ed448_verify( hex2bin( $pk ), "\x03", hex2bin( $sig ), 'foo' ) || ed448_verify( hex2bin( $pk ), "\x03", hex2bin( $sig ), 'bar' ) ) {
            return false;
        }
        $keys = mldsa87_keygen( str_repeat( "\x2a", 32 ) );
        $msig = mldsa87_sign( $keys['sk'], 'Headline Recorder', SIGNING_CONTEXT, str_repeat( "\0", 32 ) );
        return KAT_MLDSA_PK === hash( 'sha256', $keys['pk'] )
            && KAT_MLDSA_SK === hash( 'sha256', $keys['sk'] )
            && KAT_MLDSA_SIG === hash( 'sha256', $msig )
            && mldsa87_verify( $keys['pk'], 'Headline Recorder', $msig, SIGNING_CONTEXT )
            && ! mldsa87_verify( $keys['pk'], 'Headline Recorder.', $msig, SIGNING_CONTEXT );
    } catch ( \Throwable $e ) {
        return false;
    }
}

/** SHA-256 of noble ml_dsa87.keygen(32 x 0x2a) pk / sk and of sign('Headline Recorder', { context: 'pqsig-v1', extraEntropy: false }). */
const KAT_MLDSA_PK  = 'd43128fa8a8c785c1d44c9e7db538dbf9dd88fe6c8ad911a344bcec1017c6d54';
const KAT_MLDSA_SK  = '58d8e69824b61cc3e0e7e470f14d64c5a3db23edddefa0714fed1c5e1318c71e';
const KAT_MLDSA_SIG = 'c7752803deada724aa3cf3acc94f2c5f26736650cf428c81ca2681d02a9522a5';

/* ---------------------------------------------------------------------- ML-DSA-87 */

/**
 * Key pair from a 32-byte seed (FIPS 204 xi), or from a fresh random seed.
 *
 * @return array{pk: string, sk: string} pk 2592 bytes, sk 4896 bytes.
 */
function mldsa87_keygen( ?string $seed = null ): array {
    $own = null === $seed;
    if ( $own ) {
        $seed = random_bytes( 32 );
    }
    if ( strlen( $seed ) !== 32 ) {
        throw new \InvalidArgumentException( 'ML-DSA-87 seed must be 32 bytes.' );
    }
    $keys = MLDSA87::keygen( $seed );
    if ( $own && function_exists( 'sodium_memzero' ) ) {
        sodium_memzero( $seed );
    }
    return $keys;
}

/**
 * Signature (4627 bytes) over $msg with context $ctx (at most 255 bytes). Hedged with 32 fresh random bytes
 * unless $rnd is given; pass 32 zero bytes for the deterministic variant.
 */
function mldsa87_sign( string $sk, string $msg, string $ctx, ?string $rnd = null ): string {
    if ( strlen( $sk ) !== MLDSA87::SK_BYTES ) {
        throw new \InvalidArgumentException( 'ML-DSA-87 secret key must be 4896 bytes.' );
    }
    if ( strlen( $ctx ) > 255 ) {
        throw new \InvalidArgumentException( 'ML-DSA-87 context must be at most 255 bytes.' );
    }
    if ( null !== $rnd && strlen( $rnd ) !== 32 ) {
        throw new \InvalidArgumentException( 'ML-DSA-87 signing randomness must be 32 bytes.' );
    }
    $own = null === $rnd;
    if ( $own ) {
        $rnd = random_bytes( 32 );
    }
    $sig = MLDSA87::sign( $sk, "\x00" . chr( strlen( $ctx ) ) . $ctx . $msg, $rnd );
    if ( $own && function_exists( 'sodium_memzero' ) ) {
        sodium_memzero( $rnd );
    }
    return $sig;
}

/** Whether $sig is a valid ML-DSA-87 signature of $msg under $pk with context $ctx. Never throws. */
function mldsa87_verify( string $pk, string $msg, string $sig, string $ctx ): bool {
    if ( strlen( $ctx ) > 255 ) {
        return false;
    }
    try {
        return MLDSA87::verify( $pk, "\x00" . chr( strlen( $ctx ) ) . $ctx . $msg, $sig );
    } catch ( \Throwable $e ) {
        return false;
    }
}

/** Whether $sk is the secret key that belongs to $pk (same rho, and tr in sk = SHAKE256(pk, 64)). */
function mldsa87_key_matches( string $sk, string $pk ): bool {
    try {
        return MLDSA87::key_matches( $sk, $pk );
    } catch ( \Throwable $e ) {
        return false;
    }
}

/* ---------------------------------------------------------------------- Ed448 */

/** @return array{pk: string, sk: string} 57-byte public and secret keys. */
function ed448_keygen(): array {
    $sk = random_bytes( Ed448::KEY_BYTES );
    return array( 'pk' => Ed448::public_key( $sk ), 'sk' => $sk );
}

/** Public key (57 bytes) for a 57-byte secret key. */
function ed448_public_key( string $sk ): string {
    return Ed448::public_key( $sk );
}

/** Deterministic RFC 8032 Ed448 signature (114 bytes) with context $ctx (at most 255 bytes). */
function ed448_sign( string $sk, string $msg, string $ctx ): string {
    return Ed448::sign( $sk, $msg, $ctx );
}

/** Strict Ed448 verification, same acceptance rules as @noble/curves. Never throws. */
function ed448_verify( string $pk, string $msg, string $sig, string $ctx ): bool {
    try {
        return Ed448::verify( $pk, $msg, $sig, $ctx );
    } catch ( \Throwable $e ) {
        return false;
    }
}

/* ---------------------------------------------------------------------- SHAKE */

/** SHAKE128 (FIPS 202), $len bytes of output. */
function shake128( string $in, int $len ): string {
    if ( $len < 0 ) {
        throw new \InvalidArgumentException( 'Output length must not be negative.' );
    }
    return Keccak::shake128( $in, $len );
}

/** SHAKE256 (FIPS 202), $len bytes of output. */
function shake256( string $in, int $len ): string {
    if ( $len < 0 ) {
        throw new \InvalidArgumentException( 'Output length must not be negative.' );
    }
    return Keccak::shake256( $in, $len );
}
