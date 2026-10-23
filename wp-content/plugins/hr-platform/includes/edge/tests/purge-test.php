<?php
/**
 * Edge module self-test: purge batching (mocked Cloudflare API) and the cache-header policy.
 * Run on a development copy only:  wp eval-file wp-content/plugins/hr-platform/includes/edge/tests/purge-test.php
 * It creates posts/terms prefixed "edge-test", sends nothing to the network (pre_http_request mock) and removes
 * everything it created. Not shipped: build scripts exclude tests/.
 */

namespace HR\Platform\Edge;

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) ) {
    exit;
}

$fails = 0;
$check = static function ( bool $ok, string $what ) use ( &$fails ) {
    echo ( $ok ? 'PASS ' : 'FAIL ' ) . $what . "\n";
    $fails += $ok ? 0 : 1;
};
$calls   = array();
$respond = array( 'code' => 200, 'body' => '{"success":true,"errors":[],"result":{"id":"x"}}' );
add_filter( 'pre_http_request', static function ( $pre, $args, $url ) use ( &$calls, &$respond ) {
    if ( str_starts_with( (string) $url, 'https://api.cloudflare.com/' ) ) {
        $calls[] = array( 'url' => $url, 'args' => $args, 'body' => json_decode( (string) $args['body'], true ) );
        return array( 'headers' => array(), 'body' => $respond['body'], 'response' => array( 'code' => $respond['code'], 'message' => '' ), 'cookies' => array(), 'filename' => null );
    }
    return new \WP_Error( 'blocked', 'Network blocked by the edge self-test.' );
}, 10, 3 );
$created_posts = array();
$created_terms = array();
$reset         = static function () use ( &$calls ) {
    $calls = array();
    unset( $GLOBALS['hr_edge_pending'] );
    delete_option( QUEUE_OPTION );
    delete_transient( LOCK_TRANSIENT );
    wp_clear_scheduled_hook( PURGE_HOOK );
};
$pending = static fn() => array_keys( (array) ( $GLOBALS['hr_edge_pending']['urls'] ?? array() ) );

// ---- 1. Without the wp-config constants nothing is queued or sent ------------------------------------------------
$reset();
$check( ! purge_configured(), 'constants absent: purge not configured' );
$id0 = wp_insert_post( array( 'post_title' => 'edge-test unconfigured', 'post_status' => 'publish', 'post_type' => 'post' ) );
$created_posts[] = $id0;
flush_pending();
$check( array() === $pending() && array() === $calls && false === get_option( QUEUE_OPTION ), 'constants absent: nothing queued, no API call' );

$check( ttl( 'single' ) === min( TTL_DEFAULTS['single'], ttl( 'list' ) ) && ttl( 'signed' ) <= ttl( 'list' ), 'constants absent: article and signature lifetimes capped at the home page value (' . ttl( 'single' ) . 's)' );

define( 'HR_CF_ZONE_ID', str_repeat( 'ab12', 8 ) );
define( 'HR_CF_API_TOKEN', 'edgeTestToken_0123456789abcdefABCDEF' );
$check( purge_configured(), 'constants present: purge configured' );
$check( ttl( 'single' ) === (int) ( is_numeric( \HR\Platform\setting( 'edge_ttl_single' ) ) ? \HR\Platform\setting( 'edge_ttl_single' ) : TTL_DEFAULTS['single'] ), 'constants present: full article lifetime (' . ttl( 'single' ) . 's)' );

// ---- 2. Publish a post with a category and 40 tags: exact batches of <= 30 ----------------------------------------
$reset();
$cat = wp_insert_term( 'edge-test-cat', 'category' );
$created_terms[] = array( $cat['term_id'], 'category' );
$tags = array();
for ( $i = 1; $i <= 40; $i++ ) {
    $t = wp_insert_term( 'edge-test-tag-' . $i, 'post_tag' );
    $created_terms[] = array( $t['term_id'], 'post_tag' );
    $tags[] = (int) $t['term_id'];
}
$reset(); // term creation queued URLs too; measure the publish on its own
$id = wp_insert_post( array( 'post_title' => 'edge-test article', 'post_name' => 'edge-test-article', 'post_status' => 'publish', 'post_type' => 'post', 'post_category' => array( $cat['term_id'] ), 'tags_input' => $tags, 'post_author' => 1 ) );
$created_posts[] = $id;
$queued = $pending();
$must   = array(
    home_url( '/' ), get_permalink( $id ), get_feed_link(), get_term_link( (int) $cat['term_id'], 'category' ), get_term_feed_link( (int) $cat['term_id'], 'category' ),
    get_term_link( $tags[0], 'post_tag' ), get_term_link( $tags[39], 'post_tag' ), get_author_posts_url( 1 ),
    home_url( "/signed/{$id}.sig.json" ), home_url( '/signed/feed.xml' ), home_url( '/signed/feed.xml.sig.json' ), home_url( '/sitemap.xml' ),
);
$check( ! array_diff( $must, $queued ), 'publish queues home, permalink, category + feed, tags, author, main feed, signature files, sitemaps' );
$check( count( $queued ) === count( array_unique( $queued ) ), 'queued URLs are unique (' . count( $queued ) . ')' );
$host = wp_parse_url( home_url(), PHP_URL_HOST );
$check( ! array_filter( $queued, static fn( $u ) => wp_parse_url( $u, PHP_URL_HOST ) !== $host ), 'only this site\'s host is queued' );
flush_pending();
$sizes    = array_map( static fn( $c ) => count( $c['body']['files'] ?? array() ), $calls );
$expected = array_map( 'count', array_chunk( $queued, PURGE_BATCH ) );
$sent     = array_merge( ...array_map( static fn( $c ) => $c['body']['files'] ?? array(), $calls ) );
echo '     batches: ' . implode( ' + ', $sizes ) . ' = ' . array_sum( $sizes ) . " URLs\n";
$check( $sizes === $expected && max( $sizes ) <= 30 && count( $calls ) === (int) ceil( count( $queued ) / 30 ), 'batches of at most 30, in ' . count( $calls ) . ' calls' );
$check( count( $sent ) === count( array_unique( $sent ) ) && ! array_diff( $queued, $sent ) && ! array_diff( $sent, $queued ), 'every queued URL sent exactly once' );
$c0 = $calls[0];
$check( sprintf( PURGE_ENDPOINT, HR_CF_ZONE_ID ) === $c0['url'] && 'POST' === $c0['args']['method'] && 'Bearer ' . HR_CF_API_TOKEN === $c0['args']['headers']['Authorization'], 'POST to /zones/<zone>/purge_cache with the bearer token' );
$check( false === get_option( QUEUE_OPTION ) && ! wp_next_scheduled( PURGE_HOOK ), 'queue empty, nothing scheduled after success' );
$status = get_option( STATUS_OPTION );
$check( is_array( $status ) && $status['ok'] && count( $queued ) === (int) $status['count'], 'last-purge status recorded' );

// ---- 3. Slug change purges old and new address; trash purges the live address ----------------------------------
$reset();
$old = get_permalink( $id );
wp_update_post( array( 'ID' => $id, 'post_name' => 'edge-test-article-renamed' ) );
$new = get_permalink( $id );
$check( $old !== $new && in_array( $old, $pending(), true ) && in_array( $new, $pending(), true ), 'slug change: old and new permalink queued' );
$reset();
$live = get_permalink( $id );
wp_trash_post( $id );
$check( in_array( $live, $pending(), true ), 'trash: the published permalink is queued' );
$reset();
wp_untrash_post( $id );
wp_publish_post( $id );

// ---- 4. Stored signature and signed feed snapshot -------------------------------------------------------------
delete_post_meta( $id, '_hr_pqsig_at' ); // the signer may already have stored the same value this second
$reset();
update_post_meta( $id, '_hr_pqsig_at', time() );
$check( in_array( home_url( "/signed/{$id}.sig.json" ), $pending(), true ) && in_array( get_permalink( $id ), $pending(), true ), 'signature meta: /signed/<id>.sig.json and the article queued' );
$reset();
update_option( 'hr_pqsig_feed_edgetest', array( 'at' => time() ), false );
$check( array( home_url( '/signed/edgetest.xml' ), home_url( '/signed/edgetest.xml.sig.json' ) ) === $pending(), 'signed feed snapshot: its .xml and .xml.sig.json queued' );
delete_option( 'hr_pqsig_feed_edgetest' );

// ---- 5. Podcast episode -----------------------------------------------------------------------------------------
if ( post_type_exists( 'hr_episode' ) ) {
    $reset();
    $ep = wp_insert_post( array( 'post_title' => 'edge-test episode', 'post_status' => 'publish', 'post_type' => 'hr_episode' ) );
    $created_posts[] = $ep;
    $q = $pending();
    $check( in_array( get_post_type_archive_link( 'hr_episode' ), $q, true ) && in_array( get_post_type_archive_feed_link( 'hr_episode' ), $q, true ) && in_array( home_url( '/signed/podcast.xml' ), $q, true ) && in_array( get_permalink( $ep ), $q, true ), 'episode: /podcast/, /podcast/feed/, episode page, /signed/podcast.xml queued' );
}

// ---- 6. Retry with backoff on a 5xx, then success ---------------------------------------------------------------
$reset();
queue_urls( array( home_url( '/edge-retry-a/' ), home_url( '/edge-retry-b/' ) ) );
$respond = array( 'code' => 503, 'body' => '{"success":false,"errors":[{"code":10000,"message":"Service unavailable"}]}' );
flush_pending();
$q = get_queue();
$check( 1 === count( $calls ) && 2 === count( $q['urls'] ) && 1 === $q['attempt'] && $q['next'] >= time() + 50 && wp_next_scheduled( PURGE_HOOK ), '503: kept in the queue, retry 1 scheduled about a minute later' );
$calls = array();
process_queue();
$check( array() === $calls, 'retry is not sent before its time' );
$respond = array( 'code' => 200, 'body' => '{"success":true,"errors":[]}' );
$q['next'] = time() - 1;
update_option( QUEUE_OPTION, $q, false );
process_queue();
$check( 1 === count( $calls ) && false === get_option( QUEUE_OPTION ), 'retry sent once due, queue empty' );

// ---- 7. A bad token (403) is not retried and stops further calls --------------------------------------------------
$reset();
queue_urls( array_map( static fn( $n ) => home_url( "/edge-bad-{$n}/" ), range( 1, 45 ) ) );
$respond = array( 'code' => 403, 'body' => '{"success":false,"errors":[{"code":10000,"message":"Authentication error"}]}' );
flush_pending();
$status = get_option( STATUS_OPTION );
$check( 1 === count( $calls ) && false === get_option( QUEUE_OPTION ) && ! $status['ok'] && str_contains( $status['message'], 'HTTP 403' ) && ! str_contains( $status['message'], HR_CF_API_TOKEN ), '403: one call only, no retry, failure recorded without the token' );

// ---- 8. Purge everything ----------------------------------------------------------------------------------------
$reset();
$respond = array( 'code' => 200, 'body' => '{"success":true,"errors":[]}' );
queue_urls( array( home_url( '/' ) ) );
do_action( 'customize_save_after' );
flush_pending();
$check( 1 === count( $calls ) && array( 'purge_everything' => true ) === $calls[0]['body'], 'site-wide change: one purge_everything call (single URLs folded in)' );

// ---- 9. Cache-header policy (decide) ----------------------------------------------------------------------------
$base = array( 'method' => 'GET', 'path' => '/2026/10/17/story/', 'query' => '', 'cookies' => array(), 'logged_in' => false, 'login_page' => false, 'status' => 200,
    'headers' => array( 'content-type' => array( 'text/html; charset=UTF-8' ) ), 'no_store' => false, 'route' => '', 'search' => false, 'preview' => false,
    'trackback' => false, 'password' => false, 'feed' => false, 'singular' => true, 'front' => false, 'form' => false );
$cases = array(
    'guest article'                => array( array(), true, 'single' ),
    'guest home'                   => array( array( 'singular' => false, 'front' => true, 'path' => '/' ), true, 'list' ),
    'utm link'                     => array( array( 'query' => 'utm_source=x&fbclid=y' ), true, 'single' ),
    'contact form page'            => array( array( 'form' => true ), true, 'form' ),
    'other query'                  => array( array( 'query' => 'p=1' ), false, 'query' ),
    'logged-in cookie'             => array( array( 'cookies' => array( 'wordpress_logged_in_abc' ) ), false, 'cookie' ),
    'hr_li cookie'                 => array( array( 'cookies' => array( 'hr_li' ) ), false, 'cookie' ),
    'secure auth cookie'           => array( array( 'cookies' => array( 'wordpress_sec_abc' ) ), false, 'cookie' ),
    'post password cookie'         => array( array( 'cookies' => array( 'wp-postpass_abc' ) ), false, 'cookie' ),
    'commenter cookie'             => array( array( 'cookies' => array( 'comment_author_abc' ) ), false, 'cookie' ),
    'consent cookie only'          => array( array( 'cookies' => array( 'hr_consent', 'wordpress_test_cookie' ) ), true, 'single' ),
    'signed in'                    => array( array( 'logged_in' => true ), false, 'signed-in' ),
    'POST'                         => array( array( 'method' => 'POST' ), false, 'method' ),
    'account path'                 => array( array( 'path' => '/account/login/' ), false, 'path' ),
    'account-like article slug'    => array( array( 'path' => '/accounting-rules-explained/' ), true, 'single' ),
    'REST path'                    => array( array( 'path' => '/wp-json/hr/v1/signing/keyset' ), false, 'path' ),
    'dashboard'                    => array( array( 'path' => '/dashboard/' ), false, 'path' ),
    'wp-login.php'                 => array( array( 'login_page' => true, 'path' => '/wp-login.php' ), false, 'path' ),
    'Set-Cookie on response'       => array( array( 'headers' => array( 'content-type' => array( 'text/html' ), 'set-cookie' => array( 'x=1' ) ) ), false, 'set-cookie' ),
    'no_store()'                   => array( array( 'no_store' => true ), false, 'no-store' ),
    'LiteSpeed says no-cache'      => array( array( 'headers' => array( 'content-type' => array( 'text/html' ), 'x-litespeed-cache-control' => array( 'no-cache' ) ) ), false, 'litespeed-no-cache' ),
    'origin private 200'           => array( array( 'headers' => array( 'content-type' => array( 'text/html' ), 'cache-control' => array( 'no-cache, must-revalidate, max-age=0, no-store, private' ) ) ), false, 'origin-no-cache' ),
    'WordPress 404'                => array( array( 'status' => 404, 'headers' => array( 'content-type' => array( 'text/html' ), 'cache-control' => array( 'no-cache, must-revalidate, max-age=0, no-store, private' ) ) ), true, 'gone' ),
    'search'                       => array( array( 'search' => true ), false, 'search' ),
    'preview'                      => array( array( 'preview' => true ), false, 'preview' ),
    'password post'                => array( array( 'password' => true ), false, 'password' ),
    'redirect'                     => array( array( 'status' => 301 ), false, 'status-301' ),
    'feed'                         => array( array( 'feed' => true, 'singular' => false, 'headers' => array( 'content-type' => array( 'application/rss+xml' ) ) ), true, 'feed' ),
    'signature file'               => array( array( 'route' => 'pqsig_signed', 'headers' => array( 'content-type' => array( 'application/json' ), 'cache-control' => array( 'public, max-age=300' ) ) ), true, 'signed' ),
    'account route'                => array( array( 'route' => 'account' ), false, 'path' ),
    'plain text route'             => array( array( 'headers' => array( 'content-type' => array( 'text/markdown' ) ) ), false, 'type' ),
);
foreach ( $cases as $name => [ $over, $cache, $expect ] ) {
    $d = decide( array_merge( $base, $over ) );
    $check( $d['cache'] === $cache && ( $cache ? $d['kind'] : $d['reason'] ) === $expect, sprintf( 'policy: %s -> %s', $name, $cache ? "public ({$expect})" : "bypass ({$expect})" ) );
}
$check( decide( array_merge( $base, array( 'query' => 'utm_source=x' ) ) )['ttl'] <= ttl( 'list' ), 'policy: tracking-tagged copy (cannot be purged) lives no longer than a home page' );
$check( decide( array_merge( $base, array( 'form' => true ) ) )['ttl'] === ttl( 'form' ), 'policy: contact form page gets the form lifetime (' . ttl( 'form' ) . 's)' );
$cc = cache_control_values( array( 'kind' => 'single', 'ttl' => 3600 ) );
$check( 'public, max-age=0, s-maxage=3600, stale-while-revalidate=' . ttl( 'swr' ) . ', stale-if-error=' . ttl( 'sie' ) === $cc['cache-control'], 'Cache-Control format: ' . $cc['cache-control'] );

// ---- Clean up ---------------------------------------------------------------------------------------------------
foreach ( $created_posts as $pid ) {
    wp_delete_post( (int) $pid, true );
}
foreach ( $created_terms as [ $tid, $tax ] ) {
    wp_delete_term( (int) $tid, $tax );
}
$reset();
remove_action( 'shutdown', __NAMESPACE__ . '\\flush_pending', 100 );
delete_option( STATUS_OPTION );
echo $fails ? "\n{$fails} check(s) failed\n" : "\nAll checks passed\n";
