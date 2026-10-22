<?php
/**
 * Edge cache headers.
 *
 * The decision is taken at the last possible moment, when PHP is about to send the response headers
 * (header_register_callback): by then every module has had its say (no_store(), nocache_headers(), Set-Cookie,
 * LiteSpeed Cache's own X-LiteSpeed-Cache-Control), the query is known and the status code is final.
 *
 * A response is marked shareable only when ALL of these hold:
 *   - GET or HEAD front-end request (not wp-admin, AJAX, cron, REST, XML-RPC, wp-login.php, CLI);
 *   - no personal cookie in the request (wordpress_logged_in_*, wordpress_sec_*, wp-postpass_*, comment_author_*,
 *     hr_li) and nobody is signed in;
 *   - no Set-Cookie on the response, no no_store() / DONOTCACHEPAGE, LiteSpeed Cache did not say no-cache;
 *   - not an account, dashboard, thank-you or reading route, not search, preview, Customizer or a password post;
 *   - no query string other than tracking tags (utm_*, fbclid, gclid ...);
 *   - status 200 (or 404/410 with a short lifetime).
 * Everything else keeps the headers WordPress and the modules set; personal requests without any Cache-Control
 * get "private, no-cache" so no shared cache can keep them.
 *
 * Shareable responses get:
 *   Cache-Control: public, max-age=<browser>, s-maxage=<edge>, stale-while-revalidate=<swr>, stale-if-error=<sie>
 *   CDN-Cache-Control: max-age=<edge>, stale-while-revalidate=<swr>, stale-if-error=<sie>
 *   X-HR-Cache: public; kind=<single|list|feed|signed|gone>; ttl=<edge>   (or "bypass; reason=<why>")
 */

namespace HR\Platform\Edge;

use function HR\Platform\current_route;

if ( ! defined( 'ABSPATH' ) ) exit;

/** Request cookies that mean the page may be personal. Matched as name prefixes. */
const PERSONAL_COOKIES = array( 'wordpress_logged_in_', 'wordpress_sec_', 'wp-postpass_', 'comment_author_', 'hr_li' );

/** Paths that are never shared (matched as whole path segments, relative to the site root). */
const BYPASS_PATHS = array( '/account', '/dashboard', '/thank-you', '/reading', '/wp-json', '/wp-admin', '/wp-login.php', '/wp-signup.php', '/wp-activate.php', '/wp-comments-post.php', '/wp-cron.php', '/xmlrpc.php' );

/** Virtual routes (includes/core/routes.php) that are personal. */
const BYPASS_ROUTES = array( 'account', 'thank-you', 'reading' );

/** Query arguments that do not change the page (campaign and click tags). Anything else bypasses the cache. */
const IGNORED_QUERY_ARGS = array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id', 'fbclid', 'gclid', 'gbraid', 'wbraid', 'msclkid', 'twclid', 'igshid', 'mc_cid', 'mc_eid', 'ref' );

// no_store() (includes/core/helpers.php) announces itself through LiteSpeed Cache's action; remember it here too.
add_action( 'litespeed_control_set_nocache', static function () {
    $GLOBALS['hr_edge_no_store'] = true;
} );

// Registered as soon as the request is parsed (sitemaps are served from parse_request). The callback leaves REST,
// AJAX, cron and wp-admin responses alone.
add_action( 'parse_request', static function () {
    if ( ! is_admin() && 'cli' !== PHP_SAPI && ! headers_sent() ) {
        header_register_callback( __NAMESPACE__ . '\\on_send_headers' );
    }
}, -1000 );

function on_send_headers(): void {
    $context = request_context();
    if ( null === $context ) {
        return;
    }
    apply_decision( decide( $context ), $context );
}

/** Everything decide() needs, read from the live request. Null when this is not a front-end response. */
function request_context(): ?array {
    $sitemap = ! did_action( 'wp' ) && ! empty( $GLOBALS['wp']->query_vars['sitemap'] );
    if ( ! function_exists( 'is_user_logged_in' ) || ! ( did_action( 'wp' ) || $sitemap ) || is_admin() || wp_doing_ajax() || wp_doing_cron()
        || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
        return null;
    }
    $headers = array();
    foreach ( headers_list() as $line ) {
        $parts = explode( ':', $line, 2 );
        $headers[ strtolower( trim( $parts[0] ) ) ][] = trim( $parts[1] ?? '' );
    }
    $uri       = (string) ( $_SERVER['REQUEST_URI'] ?? '/' );
    $home_path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
    $path      = (string) wp_parse_url( $uri, PHP_URL_PATH );
    if ( '' !== $home_path && '/' !== $home_path && str_starts_with( $path, untrailingslashit( $home_path ) ) ) {
        $path = substr( $path, strlen( untrailingslashit( $home_path ) ) ) ?: '/';
    }
    $object   = get_queried_object();
    $password = is_singular() && $object instanceof \WP_Post && '' !== (string) $object->post_password;
    return array(
        'method'       => strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ),
        'path'         => $path,
        'query'        => (string) wp_parse_url( $uri, PHP_URL_QUERY ),
        'cookies'      => array_map( 'strval', array_keys( (array) $_COOKIE ) ),
        'logged_in'    => is_user_logged_in(),
        'login_page'   => 'wp-login.php' === ( $GLOBALS['pagenow'] ?? '' ),
        'status'       => (int) http_response_code(),
        'headers'      => $headers,
        'no_store'     => ! empty( $GLOBALS['hr_edge_no_store'] ) || ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE ),
        'route'        => function_exists( 'HR\\Platform\\current_route' ) ? current_route() : '',
        'search'       => is_search(),
        'preview'      => is_preview() || is_customize_preview() || isset( $_GET['preview'] ) || isset( $_GET['_ppp'] ),
        'trackback'    => is_trackback(),
        'password'     => $password,
        'feed'         => $sitemap || is_feed(),
        'singular'     => is_singular() && ! is_front_page(),
        'front'        => is_front_page() || is_home(),
    );
}

/**
 * Pure policy: returns array( 'cache' => bool, 'kind' => string, 'ttl' => int, 'reason' => string, 'personal' => bool,
 * 'html' => bool ). 'kind' is single|list|feed|signed|gone when cache is true.
 */
function decide( array $c ): array {
    $no  = static fn( string $reason, bool $personal = false ) => array( 'cache' => false, 'kind' => '', 'ttl' => 0, 'reason' => $reason, 'personal' => $personal, 'html' => is_html( $c ) );
    if ( ! headers_enabled() ) {
        return $no( 'disabled' );
    }
    if ( ! in_array( $c['method'], array( 'GET', 'HEAD' ), true ) ) {
        return $no( 'method' );
    }
    foreach ( $c['cookies'] as $name ) {
        foreach ( (array) apply_filters( 'hr_edge_personal_cookies', PERSONAL_COOKIES ) as $prefix ) {
            if ( '' !== $prefix && str_starts_with( $name, $prefix ) ) {
                return $no( 'cookie', true );
            }
        }
    }
    if ( $c['logged_in'] ) {
        return $no( 'signed-in', true );
    }
    if ( $c['login_page'] || is_bypass_path( $c['path'] ) || in_array( $c['route'], (array) apply_filters( 'hr_edge_bypass_routes', BYPASS_ROUTES ), true ) ) {
        return $no( 'path', true );
    }
    if ( ! empty( $c['headers']['set-cookie'] ) ) {
        return $no( 'set-cookie', true );
    }
    if ( $c['no_store'] ) {
        return $no( 'no-store', true );
    }
    foreach ( $c['headers']['x-litespeed-cache-control'] ?? array() as $value ) {
        if ( false !== stripos( $value, 'no-cache' ) ) {
            return $no( 'litespeed-no-cache' );
        }
    }
    if ( ! query_is_ignorable( $c['query'] ) ) {
        return $no( 'query' );
    }
    foreach ( array( 'search', 'preview', 'trackback', 'password' ) as $flag ) {
        if ( ! empty( $c[ $flag ] ) ) {
            return $no( $flag, 'password' === $flag );
        }
    }

    $status = (int) $c['status'];
    if ( in_array( $status, array( 404, 410 ), true ) ) {
        $kind = 'gone'; // WordPress sends no-cache on every 404 by default; a short shared lifetime is safe for guests.
    } elseif ( 200 !== $status ) {
        return $no( 'status-' . $status );
    } else {
        foreach ( $c['headers']['cache-control'] ?? array() as $value ) {
            if ( preg_match( '/\b(no-store|private|no-cache)\b/i', $value ) ) {
                return $no( 'origin-no-cache' );
            }
        }
        if ( in_array( $c['route'], array( 'pqsig_signed', 'pqsig_keys' ), true ) ) {
            $kind = 'signed';
        } elseif ( $c['feed'] || preg_match( '#^(application|text)/((rss|atom)\+)?xml\b#i', content_type( $c ) ) ) {
            $kind = 'feed';
        } elseif ( is_html( $c ) ) {
            $kind = $c['singular'] && ! $c['front'] ? 'single' : 'list';
        } else {
            return $no( 'type' ); // llms-full.txt, the manifest, share cards: they send their own public headers
        }
    }
    $ttl = ttl( $kind );
    if ( $ttl <= 0 ) {
        return $no( 'ttl-zero' );
    }
    $decision = array( 'cache' => true, 'kind' => $kind, 'ttl' => $ttl, 'reason' => '', 'personal' => false, 'html' => is_html( $c ) );
    return (array) apply_filters( 'hr_edge_cache_decision', $decision, $c );
}

function content_type( array $c ): string {
    $types = $c['headers']['content-type'] ?? array();
    return $types ? (string) end( $types ) : (string) ( ini_get( 'default_mimetype' ) ?: 'text/html' );
}

function is_html( array $c ): bool {
    return 1 === preg_match( '#^text/html\b#i', content_type( $c ) );
}

function is_bypass_path( string $path ): bool {
    foreach ( (array) apply_filters( 'hr_edge_bypass_paths', BYPASS_PATHS ) as $prefix ) {
        $prefix = '/' . trim( (string) $prefix, '/' );
        if ( '/' !== $prefix && ( $path === $prefix || str_starts_with( $path, $prefix . '/' ) ) ) {
            return true;
        }
    }
    return false;
}

function query_is_ignorable( string $query ): bool {
    if ( '' === $query ) {
        return true;
    }
    $ignored = (array) apply_filters( 'hr_edge_ignored_query_args', IGNORED_QUERY_ARGS );
    foreach ( explode( '&', $query ) as $pair ) {
        $name = strtolower( rawurldecode( explode( '=', $pair, 2 )[0] ) );
        if ( '' !== $name && ! in_array( $name, $ignored, true ) ) {
            return false;
        }
    }
    return true;
}

/** Cache-Control values for a shareable response. */
function cache_control_values( array $decision ): array {
    $edge    = (int) $decision['ttl'];
    $browser = in_array( $decision['kind'], array( 'feed', 'signed' ), true ) ? min( 300, $edge ) : 0;
    $stale   = sprintf( 'stale-while-revalidate=%d, stale-if-error=%d', ttl( 'swr' ), ttl( 'sie' ) );
    return array(
        'cache-control'     => sprintf( 'public, max-age=%d, s-maxage=%d, %s', $browser, $edge, $stale ),
        'cdn-cache-control' => sprintf( 'max-age=%d, %s', $edge, $stale ),
    );
}

function apply_decision( array $decision, array $c ): void {
    if ( $decision['cache'] ) {
        $values = cache_control_values( $decision );
        header( 'Cache-Control: ' . $values['cache-control'] );
        header( 'CDN-Cache-Control: ' . $values['cdn-cache-control'] );
        header_remove( 'Expires' );
        header_remove( 'Pragma' );
        header( sprintf( 'X-HR-Cache: public; kind=%s; ttl=%d', $decision['kind'], $decision['ttl'] ) );
    } else {
        if ( $decision['personal'] && empty( $c['headers']['cache-control'] ) ) {
            header( 'Cache-Control: private, no-cache, max-age=0' );
        }
        header( 'X-HR-Cache: bypass; reason=' . preg_replace( '/[^a-z0-9-]/', '', $decision['reason'] ) );
    }
    if ( $decision['html'] && 200 === (int) $c['status'] && 'GET' === $c['method'] ) {
        foreach ( early_hints() as $link ) {
            header( 'Link: ' . $link, false );
        }
    }
}

/**
 * Link headers for Cloudflare Early Hints (103): Cloudflare remembers them and sends them to the browser while the
 * page itself is still on its way. Only the two self-hosted text faces used on the first screen.
 */
function early_hints(): array {
    $links = array();
    if ( function_exists( 'hr_perf_fonts' ) ) {
        foreach ( array( 400, 700 ) as $weight ) {
            $rel = "/assets/fonts/hr-sans-{$weight}.woff2";
            if ( is_readable( get_stylesheet_directory() . $rel ) ) {
                $links[] = sprintf( '<%s>; rel=preload; as=font; type="font/woff2"; crossorigin', esc_url_raw( get_stylesheet_directory_uri() . $rel ) );
            }
        }
    }
    return (array) apply_filters( 'hr_edge_early_hints', $links );
}
