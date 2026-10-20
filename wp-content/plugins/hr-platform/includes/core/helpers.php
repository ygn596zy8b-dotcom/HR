<?php
/**
 * Shared helpers used by every module: settings, client IP, rate limiting, origin checks, caching, logging.
 */

namespace HR\Platform;

if ( ! defined( 'ABSPATH' ) ) exit;

/** One option array holds all non-secret settings. Secrets live in wp-config.php constants. */
function option( string $key, $default = null ) {
    static $all = null;
    if ( null === $all ) {
        $all = (array) get_option( 'hr_platform_settings', array() );
        add_action( 'update_option_hr_platform_settings', function () use ( &$all ) { $all = null; } );
        add_action( 'add_option_hr_platform_settings', function () use ( &$all ) { $all = null; } );
    }
    return array_key_exists( $key, $all ) && '' !== $all[ $key ] ? $all[ $key ] : $default;
}

/**
 * The visitor's IP. REMOTE_ADDR unless the request came through a trusted proxy (e.g. Cloudflare),
 * configured in wp-config.php:
 *   define( 'HR_TRUSTED_PROXY_HEADER', 'HTTP_CF_CONNECTING_IP' );
 *   define( 'HR_TRUSTED_PROXIES', '173.245.48.0/20,103.21.244.0/22,...' );
 * A forwarded header from anyone else is ignored, so it cannot be spoofed to dodge rate limits.
 */
function client_ip(): string {
    $remote = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
    if ( defined( 'HR_TRUSTED_PROXY_HEADER' ) && defined( 'HR_TRUSTED_PROXIES' ) && ! empty( $_SERVER[ HR_TRUSTED_PROXY_HEADER ] ) ) {
        foreach ( array_map( 'trim', explode( ',', (string) HR_TRUSTED_PROXIES ) ) as $cidr ) {
            if ( '' !== $cidr && ip_in_cidr( $remote, $cidr ) ) {
                $forwarded = trim( explode( ',', (string) $_SERVER[ HR_TRUSTED_PROXY_HEADER ] )[0] );
                return filter_var( $forwarded, FILTER_VALIDATE_IP ) ? $forwarded : $remote;
            }
        }
    }
    return filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : '0.0.0.0';
}

function ip_in_cidr( string $ip, string $cidr ): bool {
    [ $net, $bits ] = array_pad( explode( '/', $cidr, 2 ), 2, null );
    $ip_bin  = @inet_pton( $ip );
    $net_bin = @inet_pton( (string) $net );
    if ( false === $ip_bin || false === $net_bin || strlen( $ip_bin ) !== strlen( $net_bin ) ) {
        return false;
    }
    $bits = null === $bits ? strlen( $ip_bin ) * 8 : (int) $bits;
    $bytes = intdiv( $bits, 8 );
    if ( substr( $ip_bin, 0, $bytes ) !== substr( $net_bin, 0, $bytes ) ) {
        return false;
    }
    $rem = $bits % 8;
    if ( 0 === $rem ) {
        return true;
    }
    $mask = chr( ( 0xff << ( 8 - $rem ) ) & 0xff );
    return ( $ip_bin[ $bytes ] & $mask ) === ( $net_bin[ $bytes ] & $mask );
}

/** Privacy-preserving identifier for an IP in logs and rate-limit keys (keyed, so it cannot be reversed by brute force). */
function ip_hash( ?string $ip = null ): string {
    return substr( hash_hmac( 'sha256', $ip ?? client_ip(), wp_salt( 'nonce' ) ), 0, 16 );
}

/**
 * Fixed-window rate limit backed by the database (shared across PHP workers).
 * Returns true when the action is allowed and counts it.
 */
function rate_limit( string $bucket, string $key, int $max, int $window_seconds ): bool {
    $name = 'hr_rl_' . substr( hash( 'sha256', $bucket . '|' . $key ), 0, 32 );
    $now  = time();
    $hit  = get_transient( $name );
    if ( ! is_array( $hit ) || $hit['reset'] <= $now ) {
        $hit = array( 'count' => 0, 'reset' => $now + $window_seconds );
    }
    if ( $hit['count'] >= $max ) {
        log_security( 'rate_limited', array( 'bucket' => $bucket ) );
        return false;
    }
    $hit['count']++;
    set_transient( $name, $hit, max( 1, $hit['reset'] - $now ) );
    return true;
}

/** Clears a limiter, e.g. after a successful login. */
function rate_limit_reset( string $bucket, string $key ): void {
    delete_transient( 'hr_rl_' . substr( hash( 'sha256', $bucket . '|' . $key ), 0, 32 ) );
}

/**
 * Defence in depth for custom POST handlers (nonces are the primary CSRF control):
 * the request must come from this site's origin.
 */
function same_origin_request(): bool {
    $home = wp_parse_url( home_url() );
    $src  = $_SERVER['HTTP_ORIGIN'] ?? ( $_SERVER['HTTP_REFERER'] ?? '' );
    if ( '' === $src || 'null' === $src ) {
        return false;
    }
    $p = wp_parse_url( (string) $src );
    if ( empty( $p['host'] ) || strtolower( $p['host'] ) !== strtolower( $home['host'] ?? '' ) ) {
        return false;
    }
    $port = static fn( $u ) => $u['port'] ?? ( 'https' === ( $u['scheme'] ?? '' ) ? 443 : 80 );
    return ( $p['scheme'] ?? '' ) === ( $home['scheme'] ?? '' ) && $port( $p ) === $port( $home );
}

/** Personalised or sensitive responses must never be stored by LiteSpeed, a CDN or the browser. */
function no_store(): void {
    if ( headers_sent() ) {
        return;
    }
    nocache_headers();
    header( 'Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0' );
    header( 'X-Robots-Tag: noindex, nofollow' );
    do_action( 'litespeed_control_set_nocache', 'hr-platform personalised response' );
    if ( ! defined( 'DONOTCACHEPAGE' ) ) {
        define( 'DONOTCACHEPAGE', true );
    }
}

/**
 * Security events go to the PHP error log as one JSON line and to Sentry as a breadcrumb.
 * Never pass passwords, tokens, emails or raw IPs in $context.
 */
function log_security( string $event, array $context = array() ): void {
    $line = array( 'hr_event' => $event, 'ip' => ip_hash(), 'user' => get_current_user_id() ) + $context;
    error_log( 'hr-security ' . wp_json_encode( $line ) );
    if ( class_exists( '\Sentry\Breadcrumb' ) && function_exists( '\Sentry\addBreadcrumb' ) ) {
        \Sentry\addBreadcrumb( new \Sentry\Breadcrumb( \Sentry\Breadcrumb::LEVEL_INFO, \Sentry\Breadcrumb::TYPE_DEFAULT, 'security', $event, $context ) );
    }
}

/** Render a plugin template. A theme can override any of them at headlinerec-child/hr/<name>.php. */
function render( string $name, array $vars = array() ): void {
    $name = preg_replace( '/[^a-z0-9\-\/]/', '', $name );
    $file = locate_template( "hr/{$name}.php" );
    if ( ! $file ) {
        $file = HR_PLATFORM_DIR . "/templates/{$name}.php";
    }
    if ( is_readable( $file ) ) {
        ( static function () use ( $file, $vars ) {
            extract( $vars, EXTR_SKIP );
            include $file;
        } )();
    }
}

/** Template to string, for REST responses and emails. */
function render_to_string( string $name, array $vars = array() ): string {
    ob_start();
    render( $name, $vars );
    return (string) ob_get_clean();
}

function asset_url( string $path ): string {
    return HR_PLATFORM_URL . 'assets/' . ltrim( $path, '/' );
}

/** Cache-busting version from the file's mtime, so deploys never serve stale JS/CSS. */
function asset_ver( string $path ): string {
    $file = HR_PLATFORM_DIR . '/assets/' . ltrim( $path, '/' );
    return is_readable( $file ) ? VERSION . '.' . filemtime( $file ) : VERSION;
}

/** Shared front-end stylesheet for everything the plugin renders. */
function enqueue_base_style(): void {
    wp_enqueue_style( 'hr-platform', asset_url( 'css/hr-platform.css' ), array(), asset_ver( 'css/hr-platform.css' ) );
}

/** Read a JSON request body with a size cap; returns null on anything malformed. */
function json_body( \WP_REST_Request $request, int $max_bytes = 65536 ): ?array {
    $raw = $request->get_body();
    if ( '' === $raw || strlen( $raw ) > $max_bytes ) {
        return null;
    }
    $data = json_decode( $raw, true );
    return is_array( $data ) ? $data : null;
}

function is_b64url( $value, int $max_len = PHP_INT_MAX ): bool {
    return is_string( $value ) && '' !== $value && strlen( $value ) <= $max_len && 1 === preg_match( '/^[A-Za-z0-9_-]+$/', $value );
}

function b64url_decode( string $value ): string {
    return (string) base64_decode( strtr( $value, '-_', '+/' ) . str_repeat( '=', ( 4 - strlen( $value ) % 4 ) % 4 ), true );
}
