<?php
/**
 * Speculation Rules and resource hints.
 *
 * WordPress core prints the speculation rules (wp_print_speculation_rules) for signed-out readers on sites with
 * pretty permalinks. The theme (inc/perf.php) switches them to prefetch on hover/touch; this keeps that setting even
 * without the theme and adds every path that must never be fetched ahead of a click: personal pages, logout, the API,
 * feeds, signature files and machine-readable files. Core already excludes /wp-admin/*, /wp-*.php (login, logout),
 * uploads, plugin and theme files, links with a query string, rel=nofollow links and anything inside .no-prefetch.
 *
 * Resource hints: the site has no third-party origin on its first paint (fonts are self-hosted and preloaded by the
 * theme, TradingView/YouTube/Google load after interaction or consent), so hints that point at this site itself or
 * at font CDNs the site does not use are dropped.
 */

namespace HR\Platform\Edge;

if ( ! defined( 'ABSPATH' ) ) exit;

const SPECULATION_EXCLUDES = array(
    '/account/*', '/dashboard/*', '/thank-you/*', '/reading/*',
    '/wp-json/*', '/feed/*', '/*/feed/*', '/*/feed', '/comments/feed/*',
    '/signed/*', '/.well-known/*', '/og/*',
    '/*.xml', '/*.txt', '/*.json', '/*.webmanifest',
);

add_filter( 'wp_speculation_rules_configuration', static function ( $config ) {
    // null means core decided against speculation (signed-in reader, plain permalinks): keep it that way.
    return is_array( $config ) ? array( 'mode' => 'prefetch', 'eagerness' => 'moderate' ) : $config;
}, 20 );

// Core prints the rules in the footer; in <head> the browser knows them before the article body has been parsed, so a
// reader who hovers a headline early still gets the prefetch. Same output, it is a non-blocking JSON script.
add_action( 'init', static function () {
    if ( function_exists( 'wp_print_speculation_rules' ) && false !== has_action( 'wp_footer', 'wp_print_speculation_rules' ) ) {
        remove_action( 'wp_footer', 'wp_print_speculation_rules' );
        add_action( 'wp_head', 'wp_print_speculation_rules', 90 );
    }
} );

add_filter( 'wp_speculation_rules_href_exclude_paths', static function ( $paths ) {
    return array_values( array_unique( array_merge( (array) $paths, (array) apply_filters( 'hr_edge_speculation_excludes', SPECULATION_EXCLUDES ) ) ) );
}, 20 );

add_filter( 'wp_resource_hints', static function ( $urls, $relation ) {
    if ( ! in_array( $relation, array( 'dns-prefetch', 'preconnect' ), true ) || ! is_array( $urls ) ) {
        return $urls;
    }
    $own    = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
    $unused = (array) apply_filters( 'hr_edge_unused_hint_hosts', array( 's.w.org', 'fonts.googleapis.com', 'fonts.gstatic.com' ) );
    return array_values( array_filter( $urls, static function ( $hint ) use ( $own, $unused ) {
        $href = is_array( $hint ) ? (string) ( $hint['href'] ?? '' ) : (string) $hint;
        $host = strtolower( (string) wp_parse_url( str_starts_with( $href, '//' ) ? 'https:' . $href : $href, PHP_URL_HOST ) );
        if ( '' === $host ) {
            $host = strtolower( trim( $href, '/' ) );
        }
        $is_own = $host === $own || ( isset( $_SERVER['SERVER_NAME'] ) && $host === strtolower( (string) $_SERVER['SERVER_NAME'] ) );
        return ! $is_own && ! in_array( $host, $unused, true );
    } ) );
}, 20, 2 );
