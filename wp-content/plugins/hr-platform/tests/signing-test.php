<?php
/**
 * Server-side signing test (MSBJ). Adds a throwaway key to the keyset for the length of the run, signs a throwaway
 * post and checks the store paths, then restores the keyset and deletes the post. Leaves the installed server key
 * alone. Run: wp eval-file wp-content/plugins/hr-platform/tests/signing-test.php
 */

namespace HR\Platform\Signing;

use function HR\Platform\b64url_decode;

if ( ! defined( 'ABSPATH' ) ) exit;

require_once HR_PLATFORM_DIR . '/includes/signing/admin.php';

$fails = 0;
$ok    = function ( bool $cond, string $label ) use ( &$fails ) {
    $fails += $cond ? 0 : 1;
    echo ( $cond ? 'PASS ' : 'FAIL ' ), $label, "\n";
};

$ok( pq_ready() && true === self_test_result( true ), 'PHP signer self test' );
$ok( 1 === preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/', iso_now_ms() ), 'signedAt is ISO-8601 UTC with milliseconds' );
$ok( "{\"url\":\"/a/ü\u{2028}/\"}" === header_json( array( 'url' => "/a/ü\u{2028}/" ) ), 'header JSON keeps slashes, Unicode and U+2028 unescaped (as JSON.stringify)' );

$raw_keyset = (string) \HR\Platform\setting( 'pq_keyset' );
$post_id    = 0;
try {
    $pq  = \HR\Platform\PQ\mldsa87_keygen();
    $ec  = \HR\Platform\PQ\ed448_keygen();
    $kid = hash( 'sha256', $pq['pk'] . $ec['pk'] );
    $key = array( 'kid' => $kid, 'mldsa87' => $pq['sk'], 'ed448' => $ec['sk'] );
    $set = keyset() ?? array( 'keys' => array() );
    if ( count( $set['keys'] ) >= 15 ) {
        echo "SKIP keyset is full\n";
        return;
    }
    $set['keys'][] = array( 'alg' => ALG, 'kid' => $kid, 'mldsa87' => b64url( $pq['pk'] ), 'ed448' => b64url( $ec['pk'] ), 'created' => iso_now_ms() );
    $ok( save_keyset( $set ), 'throwaway key added to the keyset' );

    $ok( '' === check_secret_key( $kid, $pq['sk'], $ec['sk'] ), 'matching key accepted' );
    $other = \HR\Platform\PQ\ed448_keygen();
    $ok( '' !== check_secret_key( $kid, $pq['sk'], $other['sk'] ), 'mismatched Ed448 half refused' );
    $ok( '' !== check_secret_key( str_repeat( 'a', 64 ), $pq['sk'], $ec['sk'] ), 'key not in the keyset refused' );

    $post_id = wp_insert_post( array( 'post_title' => 'signing-test throwaway', 'post_content' => '<p>Test.</p>', 'post_status' => 'publish' ) );
    $post    = get_post( $post_id );
    $bytes   = canonical_bytes( $post );
    $doc     = make_signature( $bytes, $key, 'article', post_path( $post ), true );
    $header  = json_decode( b64url_decode( $doc['protected'] ), true );
    $ok( array( 'v', 'alg', 'kid', 'type', 'url', 'sha512', 'signedAt' ) === array_keys( $header ), 'header fields in the Node signer order' );
    $ok( verify_document( $doc, $bytes ), 'own signature verifies' );

    $bad = $doc;
    $sig = b64url_decode( $bad['mldsa87'] );
    $sig[10] = chr( ord( $sig[10] ) ^ 1 );
    $bad['mldsa87'] = b64url( $sig );
    $ok( 'bad_signature' === store_article_signature( $post_id, $bad ), 'corrupted ML-DSA signature refused' );
    $bad = $doc;
    $sig = b64url_decode( $bad['ed448'] );
    $sig[3] = chr( ord( $sig[3] ) ^ 1 );
    $bad['ed448'] = b64url( $sig );
    $ok( 'bad_signature' === store_article_signature( $post_id, $bad ), 'corrupted Ed448 signature refused' );
    $ok( 'stale' === store_article_signature( $post_id, make_signature( $bytes . ' ', $key, 'article', post_path( $post ), true ) ), 'signature over other bytes refused' );
    $ok( 'stored' === store_article_signature( $post_id, $doc ), 'genuine signature stored' );
    $ok( $kid === get_post_meta( $post_id, META_KID, true ) && '' === (string) get_post_meta( $post_id, META_STALE, true ), 'meta and stale flag as the upload path sets them' );
    $ok( 'current' === sign_post( $post_id ), 'sign_post() leaves a current signature alone' );

    $feed = '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel><title>t</title></channel></rss>';
    $ok( looks_like_rss( $feed ) && ! looks_like_rss( '<!DOCTYPE x><rss/>' ) && ! looks_like_rss( '<html></html>' ), 'feed sanity check' );
    if ( array_key_exists( 'feed', feed_sources() ) ) {
        $fdoc = make_signature( $feed, $key, 'feed', '/signed/feed.xml', false );
        $fbad = $fdoc;
        $fbad['ed448'] = b64url( str_repeat( "\0", 114 ) );
        $ok( 'bad_signature' === store_feed_snapshot( 'feed', $feed, $fbad ), 'forged feed signature refused' );
        $ok( 'mismatch' === store_feed_snapshot( 'feed', $feed . ' ', $fdoc ), 'feed signature over other bytes refused' );
    }

    $token = acquire_lock( 30 );
    $ok( '' !== $token && '' === acquire_lock( 30 ), 'lock is exclusive' );
    release_lock( $token );
    $token = acquire_lock( 30 );
    $ok( '' !== $token, 'lock is released' );
    release_lock( $token );
} finally {
    if ( $post_id ) {
        wp_delete_post( $post_id, true );
    }
    write_keyset_setting( $raw_keyset );
    echo 'keyset restored: ', ( (string) \HR\Platform\setting( 'pq_keyset' ) === $raw_keyset ? 'yes' : 'NO' ), "\n";
}
echo $fails ? "{$fails} FAILED\n" : "ALL PASSED\n";
