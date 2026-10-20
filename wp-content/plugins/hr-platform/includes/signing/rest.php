<?php
/**
 * REST API (namespace hr/v1):
 *   GET  /canonical?url=…  /canonical/<id>   public: exact canonical bytes of a published article
 *   GET  /signing/keyset                     public: the publisher keyset (same as /.well-known/pqsig-keys.json)
 *   GET  /signing/queue                      signer: articles needing a signature, plus the feeds to sign
 *   POST /signing/signatures                 signer: upload article signatures
 *   POST /signing/feeds                      signer: upload a signed feed snapshot
 *
 * Signer endpoints need the hr_sign_content capability (role hr_signer, authenticated with an application password,
 * which the hardening mu-plugin allows for that role only).
 */

namespace HR\Platform\Signing;

use function HR\Platform\b64url_decode;
use function HR\Platform\ip_hash;
use function HR\Platform\is_b64url;
use function HR\Platform\json_body;
use function HR\Platform\log_security;
use function HR\Platform\rate_limit;

if ( ! defined( 'ABSPATH' ) ) exit;

const QUEUE_MAX_ITEMS     = 100;
const QUEUE_MAX_BYTES     = 6 * MB_IN_BYTES;
const UPLOAD_MAX_BYTES    = 8 * MB_IN_BYTES;
const UPLOAD_MAX_ITEMS    = 100;
const FEED_MAX_BYTES      = 5 * MB_IN_BYTES;
const HEADER_MAX_B64      = 4096;

add_action( 'rest_api_init', function () {
    register_rest_route( 'hr/v1', '/signing/keyset', array(
        'methods'             => 'GET',
        'callback'            => __NAMESPACE__ . '\\rest_keyset',
        'permission_callback' => '__return_true',
    ) );
    register_rest_route( 'hr/v1', '/canonical', array(
        'methods'             => 'GET',
        'callback'            => __NAMESPACE__ . '\\rest_canonical',
        'permission_callback' => '__return_true',
        'args'                => array(
            'url' => array(
                'type'              => 'string',
                'required'          => true,
                'validate_callback' => static fn( $v ) => is_string( $v ) && '' !== $v && strlen( $v ) <= 2048,
            ),
        ),
    ) );
    register_rest_route( 'hr/v1', '/canonical/(?P<id>\d{1,20})', array(
        'methods'             => 'GET',
        'callback'            => __NAMESPACE__ . '\\rest_canonical',
        'permission_callback' => '__return_true',
    ) );
    register_rest_route( 'hr/v1', '/signing/queue', array(
        'methods'             => 'GET',
        'callback'            => __NAMESPACE__ . '\\rest_queue',
        'permission_callback' => __NAMESPACE__ . '\\can_sign',
    ) );
    register_rest_route( 'hr/v1', '/signing/signatures', array(
        'methods'             => 'POST',
        'callback'            => __NAMESPACE__ . '\\rest_signatures',
        'permission_callback' => __NAMESPACE__ . '\\can_sign',
    ) );
    register_rest_route( 'hr/v1', '/signing/feeds', array(
        'methods'             => 'POST',
        'callback'            => __NAMESPACE__ . '\\rest_feeds',
        'permission_callback' => __NAMESPACE__ . '\\can_sign',
    ) );
} );

function can_sign(): bool {
    // Application password only: a browser session (cookie + nonce) of the signer account cannot upload signatures.
    return current_user_can( CAP ) && null !== rest_get_authenticated_app_password();
}

function not_found(): \WP_Error {
    return new \WP_Error( 'hr_not_found', 'Not found.', array( 'status' => 404 ) );
}

/** Post ID for a path or an absolute URL on this site. Resolution is a database lookup; nothing is fetched. */
function post_id_from_url( string $url ): int {
    $url  = trim( $url );
    $home = wp_parse_url( home_url() );
    if ( '' === $url || str_starts_with( $url, '//' ) || empty( $home['host'] ) ) {
        return 0;
    }
    if ( str_starts_with( $url, '/' ) ) {
        $url = $home['scheme'] . '://' . $home['host'] . ( isset( $home['port'] ) ? ':' . $home['port'] : '' ) . $url;
    } else {
        $parts = wp_parse_url( $url );
        if ( empty( $parts['host'] ) || ! in_array( $parts['scheme'] ?? '', array( 'http', 'https' ), true )
            || strtolower( $parts['host'] ) !== strtolower( $home['host'] ) || ( $parts['port'] ?? null ) !== ( $home['port'] ?? null ) ) {
            return 0;
        }
    }
    return (int) url_to_postid( $url );
}

function rest_canonical( \WP_REST_Request $request ) {
    if ( ! rate_limit( 'pqsig-canonical', ip_hash(), 300, 5 * MINUTE_IN_SECONDS ) ) {
        return new \WP_Error( 'hr_rate_limited', 'Too many requests. Please try again in a few minutes.', array( 'status' => 429 ) );
    }
    $id   = null !== $request['id'] ? (int) $request['id'] : post_id_from_url( (string) $request['url'] );
    $post = $id > 0 ? signable_post( $id ) : null;
    if ( ! $post ) {
        return not_found();
    }
    $response = new \WP_REST_Response( canonical_bytes( $post ), 200 );
    $response->header( 'Content-Type', 'application/json; charset=utf-8' );
    $response->header( 'Cache-Control', 'public, max-age=60' );
    $response->header( 'X-HR-Raw-Body', '1' );
    return $response;
}

// The canonical endpoint must return the signed bytes exactly, not a JSON string wrapping them.
add_filter( 'rest_pre_serve_request', function ( $served, $result, $request ) {
    if ( $served || ! $result instanceof \WP_HTTP_Response || 200 !== $result->get_status() || ! is_string( $result->get_data() ) ) {
        return $served;
    }
    $headers = $result->get_headers();
    if ( empty( $headers['X-HR-Raw-Body'] ) || ! str_starts_with( (string) $request->get_route(), '/hr/v1/canonical' ) ) {
        return $served;
    }
    header_remove( 'X-HR-Raw-Body' );
    if ( 'HEAD' !== $request->get_method() ) {
        echo $result->get_data(); // phpcs:ignore WordPress.Security.EscapeOutput -- raw JSON bytes served as application/json
    }
    return true;
}, 20, 3 );

// ---------------------------------------------------------------------------------------------------------------
// Queue
// ---------------------------------------------------------------------------------------------------------------

/** IDs of public posts that have no signature, a stale one, or one from a revoked/removed key. Newest first. */
function queue_candidate_ids( int $limit, int $offset, bool $count = false ) {
    global $wpdb;
    $kids = valid_kids();
    $args = array( META_SHA, META_STALE, META_KID );
    $kid_clause = '';
    if ( $kids ) {
        $kid_clause = ' OR k.meta_value NOT IN (' . implode( ',', array_fill( 0, count( $kids ), '%s' ) ) . ')';
        $args       = array_merge( $args, $kids );
    } else {
        $kid_clause = ' OR 1 = 1';
    }
    $from = "FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} h ON h.post_id = p.ID AND h.meta_key = %s
        LEFT JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = %s
        LEFT JOIN {$wpdb->postmeta} k ON k.post_id = p.ID AND k.meta_key = %s
        WHERE p.post_type = 'post' AND p.post_status = 'publish' AND p.post_password = ''
          AND ( h.meta_id IS NULL OR s.meta_value = '1' OR k.meta_id IS NULL{$kid_clause} )";
    if ( $count ) {
        return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT p.ID) {$from}", $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL
    }
    $args[] = $limit;
    $args[] = $offset;
    $rows   = $wpdb->get_results( $wpdb->prepare( "SELECT DISTINCT p.ID, p.post_date_gmt {$from} ORDER BY p.post_date_gmt DESC, p.ID DESC LIMIT %d OFFSET %d", $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL
    return array_map( static fn( $r ) => (int) $r->ID, (array) $rows );
}

/** Feeds the site actually serves (key => site-relative source). Only these keys have /signed/<key>.xml routes. */
function feed_sources(): array {
    $feeds = array();
    // Yoast's "Remove global feed" setting redirects /feed/ to the home page; there is nothing to sign then.
    if ( ! ( class_exists( '\WPSEO_Options' ) && \WPSEO_Options::get( 'remove_feed_global', false ) ) ) {
        $feeds['feed'] = wp_make_link_relative( home_url( '/feed/' ) );
    }
    if ( post_type_exists( 'hr_episode' ) ) {
        $feeds['podcast'] = wp_make_link_relative( home_url( '/podcast/feed/' ) );
    }
    $feeds = (array) apply_filters( 'hr_pqsig_feed_sources', $feeds );
    return array_filter(
        array_intersect_key( $feeds, array( 'feed' => 1, 'podcast' => 1 ) ),
        static fn( $source ) => is_string( $source ) && str_starts_with( $source, '/' ) && ! str_starts_with( $source, '//' )
    );
}

function stored_feed( string $key ): ?array {
    if ( ! array_key_exists( $key, feed_sources() ) ) {
        return null;
    }
    $feed = get_option( FEED_OPTION . $key );
    return is_array( $feed ) && isset( $feed['xml'], $feed['sig'], $feed['sha512'] ) ? $feed : null;
}

function rest_queue( \WP_REST_Request $request ) {
    if ( ! rate_limit( 'pqsig-queue', (string) get_current_user_id(), 600, HOUR_IN_SECONDS ) ) {
        return new \WP_Error( 'hr_rate_limited', 'Too many requests.', array( 'status' => 429 ) );
    }
    $items  = array();
    $bytes  = 0;
    $offset = 0;
    for ( $round = 0; $round < 20 && count( $items ) < QUEUE_MAX_ITEMS; $round++ ) {
        $ids = queue_candidate_ids( 200, $offset );
        if ( ! $ids ) {
            break;
        }
        _prime_post_caches( $ids, false, true );
        $cleared = 0;
        foreach ( $ids as $id ) {
            $post = signable_post( $id );
            if ( ! $post ) {
                continue;
            }
            $canonical = canonical_bytes( $post );
            $sha       = hash( 'sha512', $canonical );
            $stored    = (string) get_post_meta( $id, META_SHA, true );
            // The stale flag is a hint; the hash decides. A false alarm (e.g. an edit that was undone) is cleared here.
            if ( '' !== $stored && hash_equals( $stored, $sha ) && kid_is_valid( (string) get_post_meta( $id, META_KID, true ) ) ) {
                delete_post_meta( $id, META_STALE );
                $cleared++;
                continue;
            }
            $items[] = array(
                'id'        => $id,
                'type'      => 'article',
                'url'       => post_path( $post ),
                'canonical' => rtrim( strtr( base64_encode( $canonical ), '+/', '-_' ), '=' ),
                'sha512'    => $sha,
            );
            $bytes += strlen( $canonical );
            if ( count( $items ) >= QUEUE_MAX_ITEMS || $bytes >= QUEUE_MAX_BYTES ) {
                break 2;
            }
        }
        $offset += count( $ids ) - $cleared;
    }

    $feeds = array();
    foreach ( feed_sources() as $key => $source ) {
        $stored  = stored_feed( $key );
        $feeds[] = array(
            'key'           => $key,
            'source'        => $source,
            'signed_sha512' => $stored['sha512'] ?? null,
            'signed_at'     => isset( $stored['at'] ) ? gmdate( 'c', (int) $stored['at'] ) : null,
        );
    }

    $response = new \WP_REST_Response( array(
        'items'     => $items,
        'remaining' => queue_candidate_ids( 0, 0, true ),
        'feeds'     => $feeds,
    ) );
    $response->header( 'Cache-Control', 'no-store' );
    return $response;
}

// ---------------------------------------------------------------------------------------------------------------
// Uploads
// ---------------------------------------------------------------------------------------------------------------

/**
 * Shape check of an uploaded signature document and decode of its protected header.
 * Returns [header, doc] or a short status string ('invalid', 'bad_key').
 *
 * PHP has no ML-DSA implementation, so the server cannot check the signatures themselves. It checks everything that
 * binds an upload to this exact article (key ID, type, address, SHA-512 of the current canonical bytes, attached
 * payload); readers and partners verify the cryptography with the published keyset, so a forged or mismatched
 * signature is never shown as valid.
 */
function parse_signature( $sig, bool $attached ) {
    $fields = $attached ? array( 'protected', 'mldsa87', 'ed448', 'payload' ) : array( 'protected', 'mldsa87', 'ed448' );
    if ( ! is_array( $sig ) || array_keys( $sig ) !== array_intersect( array_keys( $sig ), $fields ) || count( $sig ) !== count( $fields ) ) {
        return 'invalid';
    }
    if ( ! is_b64url( $sig['protected'], HEADER_MAX_B64 ) || ! is_b64url( $sig['mldsa87'], 6200 ) || ! is_b64url( $sig['ed448'], 200 )
        || MLDSA87_SIG_BYTES !== strlen( b64url_decode( $sig['mldsa87'] ) ) || ED448_SIG_BYTES !== strlen( b64url_decode( $sig['ed448'] ) ) ) {
        return 'invalid';
    }
    if ( $attached && ! is_b64url( $sig['payload'], (int) ceil( UPLOAD_MAX_BYTES * 4 / 3 ) ) ) {
        return 'invalid';
    }
    $header = json_decode( b64url_decode( $sig['protected'] ), true, 4 );
    $allowed = array( 'v', 'alg', 'kid', 'type', 'url', 'sha512', 'signedAt' );
    if ( ! is_array( $header ) || array_diff( array_keys( $header ), $allowed ) ) {
        return 'invalid';
    }
    if ( 1 !== ( $header['v'] ?? null ) || ALG !== ( $header['alg'] ?? null ) || ! is_string( $header['type'] ?? null )
        || ! is_string( $header['url'] ?? null ) || ! is_string( $header['sha512'] ?? null ) || 1 !== preg_match( '/^[0-9a-f]{128}$/', $header['sha512'] )
        || ! is_string( $header['signedAt'] ?? null ) || 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?Z$/', $header['signedAt'] ) ) {
        return 'invalid';
    }
    $signed_at = strtotime( $header['signedAt'] );
    if ( false === $signed_at || $signed_at > time() + 10 * MINUTE_IN_SECONDS ) {
        return 'invalid';
    }
    if ( ! is_string( $header['kid'] ?? null ) || ! kid_is_valid( $header['kid'] ) ) {
        return 'bad_key';
    }
    $header['signedAtTs'] = $signed_at;
    return array( $header, $sig );
}

function rest_signatures( \WP_REST_Request $request ) {
    if ( ! rate_limit( 'pqsig-upload', (string) get_current_user_id(), 600, HOUR_IN_SECONDS ) ) {
        return new \WP_Error( 'hr_rate_limited', 'Too many requests.', array( 'status' => 429 ) );
    }
    if ( ! keyset() ) {
        return new \WP_Error( 'hr_no_keyset', 'No signing keyset is configured.', array( 'status' => 409 ) );
    }
    $body  = json_body( $request, UPLOAD_MAX_BYTES );
    $items = is_array( $body ) && array_is_list( $body ) ? $body : ( $body['items'] ?? null );
    if ( ! is_array( $items ) || ! array_is_list( $items ) || ! $items || count( $items ) > UPLOAD_MAX_ITEMS ) {
        return new \WP_Error( 'hr_bad_request', 'Expected {"items":[…]} with 1–100 signatures, at most 8 MB.', array( 'status' => 400 ) );
    }
    $results = array();
    $stored  = 0;
    foreach ( $items as $item ) {
        $id     = is_array( $item ) && is_int( $item['id'] ?? null ) ? $item['id'] : 0;
        $status = store_article_signature( $id, is_array( $item ) ? ( $item['sig'] ?? null ) : null );
        if ( 'stored' === $status ) {
            $stored++;
        }
        $results[] = array( 'id' => $id, 'status' => $status );
    }
    if ( $stored < count( $items ) ) {
        log_security( 'pqsig_upload_rejected', array( 'stored' => $stored, 'rejected' => count( $items ) - $stored ) );
    }
    return new \WP_REST_Response( array( 'stored' => $stored, 'results' => $results ) );
}

/** Returns 'stored', 'not_found', 'invalid', 'bad_key' or 'stale'. */
function store_article_signature( int $id, $sig ): string {
    $post = $id > 0 ? signable_post( $id ) : null;
    if ( ! $post ) {
        return 'not_found';
    }
    $parsed = parse_signature( $sig, true );
    if ( is_string( $parsed ) ) {
        return $parsed;
    }
    [ $header, $sig ] = $parsed;
    $canonical = canonical_bytes( $post );
    $sha       = hash( 'sha512', $canonical );
    if ( 'article' !== $header['type'] ) {
        return 'invalid';
    }
    if ( ! hash_equals( $sha, $header['sha512'] ) || post_path( $post ) !== $header['url'] ) {
        return 'stale'; // the article changed after the signer fetched it; it stays in the queue
    }
    if ( ! hash_equals( $canonical, b64url_decode( $sig['payload'] ) ) ) {
        return 'invalid';
    }
    $doc = wp_json_encode( array(
        'protected' => $sig['protected'],
        'mldsa87'   => $sig['mldsa87'],
        'ed448'     => $sig['ed448'],
        'payload'   => $sig['payload'],
    ), JSON_UNESCAPED_SLASHES );
    update_post_meta( $id, META_SIG, wp_slash( $doc ) );
    update_post_meta( $id, META_SHA, $sha );
    update_post_meta( $id, META_KID, $header['kid'] );
    update_post_meta( $id, META_AT, (int) $header['signedAtTs'] );
    delete_post_meta( $id, META_STALE );
    do_action( 'litespeed_purge_post', $id );
    do_action( 'litespeed_purge_url', signature_url( $id ) );
    return 'stored';
}

/**
 * Well-formed UTF-8 RSS without a DTD, parsed without network access or entity expansion (no XXE, no billion laughs).
 * Partners' feed readers parse these bytes too, so a DTD is refused outright, not merely left unexpanded.
 */
function is_well_formed_feed( string $xml ): bool {
    // UTF-8 only (it is served as charset=utf-8); this also stops a UTF-16 document hiding a DOCTYPE from the regex.
    if ( str_contains( $xml, "\0" ) || 1 !== preg_match( '//u', $xml ) || 1 === preg_match( '/<!(?:DOCTYPE|ENTITY)/i', $xml ) ) {
        return false;
    }
    $previous = libxml_use_internal_errors( true );
    $doc      = new \DOMDocument();
    $ok       = $doc->loadXML( $xml, LIBXML_NONET | LIBXML_COMPACT );
    libxml_clear_errors();
    libxml_use_internal_errors( $previous );
    return $ok && null === $doc->doctype && 'UTF-8' === strtoupper( (string) ( $doc->xmlEncoding ?? 'UTF-8' ) )
        && $doc->documentElement && 'rss' === $doc->documentElement->nodeName;
}

function rest_feeds( \WP_REST_Request $request ) {
    if ( ! rate_limit( 'pqsig-feeds', (string) get_current_user_id(), 120, HOUR_IN_SECONDS ) ) {
        return new \WP_Error( 'hr_rate_limited', 'Too many requests.', array( 'status' => 429 ) );
    }
    $bad  = static fn( string $msg ) => new \WP_Error( 'hr_bad_request', $msg, array( 'status' => 400 ) );
    $body = json_body( $request, (int) ceil( FEED_MAX_BYTES * 4 / 3 ) + 65536 );
    $key  = is_array( $body ) ? ( $body['key'] ?? null ) : null;
    if ( ! is_string( $key ) || ! array_key_exists( $key, feed_sources() ) ) {
        return $bad( 'Unknown feed.' );
    }
    if ( ! is_b64url( $body['content'] ?? null, (int) ceil( FEED_MAX_BYTES * 4 / 3 ) ) ) {
        return $bad( 'Feed content missing or too large.' );
    }
    $xml = b64url_decode( $body['content'] );
    if ( '' === $xml || strlen( $xml ) > FEED_MAX_BYTES ) {
        return $bad( 'Feed content missing or too large.' );
    }
    $parsed = parse_signature( $body['sig'] ?? null, false );
    if ( is_string( $parsed ) ) {
        return $bad( 'bad_key' === $parsed ? 'Signed with a key that is not in the configured keyset.' : 'Malformed signature.' );
    }
    [ $header, $sig ] = $parsed;
    if ( 'feed' !== $header['type'] || '/signed/' . $key . '.xml' !== $header['url'] || ! hash_equals( hash( 'sha512', $xml ), $header['sha512'] ) ) {
        return $bad( 'Signature does not cover this feed.' );
    }
    if ( ! is_well_formed_feed( $xml ) ) {
        return $bad( 'Feed is not well-formed RSS.' );
    }
    // No rollback: an older snapshot (even a genuinely signed one) must not replace a newer one.
    $current = stored_feed( $key );
    if ( $current && (int) $header['signedAtTs'] < (int) ( $current['at'] ?? 0 ) ) {
        return $bad( 'Older than the stored snapshot.' );
    }
    $doc = wp_json_encode( array( 'protected' => $sig['protected'], 'mldsa87' => $sig['mldsa87'], 'ed448' => $sig['ed448'] ), JSON_UNESCAPED_SLASHES );
    update_option( FEED_OPTION . $key, array(
        'xml'    => $xml,
        'sig'    => $doc,
        'sha512' => $header['sha512'],
        'kid'    => $header['kid'],
        'at'     => (int) $header['signedAtTs'],
    ), false );
    // The server cannot check the signature itself, so every replacement of a public file is on the record.
    log_security( 'pqsig_feed_stored', array( 'key' => $key, 'kid' => substr( $header['kid'], 0, 16 ), 'bytes' => strlen( $xml ) ) );
    do_action( 'litespeed_purge_url', home_url( '/signed/' . $key . '.xml' ) );
    do_action( 'litespeed_purge_url', home_url( '/signed/' . $key . '.xml.sig.json' ) );
    return new \WP_REST_Response( array( 'key' => $key, 'status' => 'stored' ) );
}

function rest_keyset(): \WP_REST_Response {
    $keyset   = keyset();
    $response = new \WP_REST_Response( $keyset ?? array( 'code' => 'not_found' ), $keyset ? 200 : 404 );
    $response->header( 'Access-Control-Allow-Origin', '*' );
    $response->header( 'Cache-Control', 'public, max-age=300' );
    return $response;
}
