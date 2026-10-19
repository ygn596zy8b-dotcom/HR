<?php
/**
 * Publisher keyset: the public half of the signing key(s), pasted into Settings → Headline Recorder.
 */

namespace HR\Platform\Signing;

use function HR\Platform\b64url_decode;
use function HR\Platform\is_b64url;
use function HR\Platform\setting;

if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'hr_settings_schema', function ( $schema ) {
    $schema['signing'] = array(
        'title'       => 'Content signing',
        'description' => 'Every published article and the feeds are signed with post-quantum <strong>ML-DSA-87 + Ed448</strong>. '
            . 'The secret key never goes on this server: a signer (the <code>sign-content</code> GitHub Action, or an editor running '
            . '<code>security/pq/wp-sign.mjs</code>) signs and uploads the signatures using a user with the <em>Content signer</em> role and an application password.',
        'fields'      => array(
            'pq_keyset' => array(
                'label'       => 'Public signing keyset',
                'type'        => 'json',
                'default'     => '',
                'validate'    => __NAMESPACE__ . '\\is_valid_keyset',
                'description' => 'Create a key on an offline or trusted machine: <code>cd security/pq &amp;&amp; node cli.mjs keygen sign signing</code>. '
                    . 'Paste the contents of <code>signing.public.json</code> here (published at <code>/.well-known/pqsig-keys.json</code>). '
                    . 'Keep <code>signing.secret.json</code> offline and in the <code>HR_PQ_SIGNING_KEY</code> GitHub secret only. '
                    . 'To rotate, add the new key to the list; to revoke one, add <code>"revoked": "YYYY-MM-DD"</code> to it and everything it signed is re-signed.',
            ),
        ),
    );
    return $schema;
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

/** "a1b2 c3d4 …" for people comparing key IDs by eye. */
function group_hex( string $hex, int $groups = 0 ): string {
    $parts = str_split( $hex, 4 );
    if ( $groups > 0 && count( $parts ) > $groups ) {
        return implode( ' ', array_slice( $parts, 0, $groups ) ) . ' …';
    }
    return implode( ' ', $parts );
}
