<?php
/**
 * Virtual front-end routes (no WordPress pages needed in the database).
 *
 *   add_route( 'account', '^account(?:/([a-z-]+))?/?$', function ( string $sub ) { ... } );
 *
 * The handler runs on template_redirect and must output a full response (or redirect); it is followed by exit.
 * The first regex capture group is passed as $sub ('' when absent).
 */

namespace HR\Platform;

if ( ! defined( 'ABSPATH' ) ) exit;

/** @var array<string, array{regex:string, handler:callable}> */
$GLOBALS['hr_platform_routes'] = array();

function add_route( string $name, string $regex, callable $handler ): void {
    $GLOBALS['hr_platform_routes'][ $name ] = array( 'regex' => $regex, 'handler' => $handler );
}

function route_url( string $path = '' ): string {
    return home_url( user_trailingslashit( ltrim( $path, '/' ) ) );
}

/** The current virtual route name, or '' on normal WordPress pages. */
function current_route(): string {
    return (string) get_query_var( 'hr_route' );
}

function register_rewrites(): void {
    foreach ( $GLOBALS['hr_platform_routes'] as $name => $route ) {
        add_rewrite_rule( $route['regex'], 'index.php?hr_route=' . $name . '&hr_sub=$matches[1]', 'top' );
    }
}
add_action( 'init', __NAMESPACE__ . '\\register_rewrites', 20 );
add_action( 'hr_register_rewrites', __NAMESPACE__ . '\\register_rewrites', 20 );

add_filter( 'query_vars', function ( array $vars ) {
    $vars[] = 'hr_route';
    $vars[] = 'hr_sub';
    return $vars;
} );

// Rewrite rules are cached; flush once when a deploy adds or changes a route.
add_action( 'init', function () {
    $signature = md5( wp_json_encode( array_map( fn( $r ) => $r['regex'], $GLOBALS['hr_platform_routes'] ) ) );
    if ( get_option( 'hr_platform_routes_sig' ) !== $signature ) {
        update_option( 'hr_platform_routes_sig', $signature, true ); // read on every request: autoload it
        flush_rewrite_rules( false );
    } elseif ( ! isset( wp_load_alloptions()['hr_platform_routes_sig'] ) ) {
        wp_set_option_autoload( 'hr_platform_routes_sig', true ); // older installs stored it unautoloaded
    }
}, 100 );

add_action( 'template_redirect', function () {
    $name = current_route();
    if ( '' === $name || ! isset( $GLOBALS['hr_platform_routes'][ $name ] ) ) {
        return;
    }
    $sub = preg_replace( '/[^a-z0-9.\-_]/i', '', (string) get_query_var( 'hr_sub' ) );
    global $wp_query;
    $wp_query->is_404  = false;
    $wp_query->is_home = false;
    status_header( 200 );
    call_user_func( $GLOBALS['hr_platform_routes'][ $name ]['handler'], $sub );
    exit;
}, 1 );

// Virtual routes are not the blog index; keep WordPress from redirecting or canonicalising them.
add_filter( 'redirect_canonical', function ( $redirect ) {
    return '' !== current_route() ? false : $redirect;
} );

/**
 * Title for a virtual page; set by the handler before get_header(). Personal pages stay out of search engines.
 * "Page - Brand", the same separator as the Yoast titles on the rest of the site.
 */
function set_page_title( string $title, bool $indexable = false ): void {
    $full = $title . ' - ' . get_bloginfo( 'name' );
    add_filter( 'pre_get_document_title', fn() => $full, 20 );
    add_filter( 'wpseo_title', fn() => $full, 20 );
    add_filter( 'wpseo_canonical', fn() => $indexable ? route_url( current_route() ) : false, 20 );
    if ( ! $indexable ) {
        add_filter( 'wpseo_robots', fn() => 'noindex, nofollow', 20 );
        add_filter( 'wp_robots', 'wp_robots_no_robots' );
    }
    add_filter( 'body_class', fn( $c ) => array_merge( $c, array( 'hr-virtual', 'hr-route-' . sanitize_html_class( current_route() ) ) ) );
}
