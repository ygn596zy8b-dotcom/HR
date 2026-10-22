<?php
/**
 * Theme assets (MSBJ): the stylesheets that used to be inline <style> blocks, and the deferred site scripts.
 *
 * The old blocks printed after Customizer Additional CSS (wp_head 101), from wp_head 999, the article body and
 * the footer, and many of their !important rules win over it. So the files are printed from wp_head 999 in the
 * same order (template CSS, then base), which keeps the cascade identical while moving them into <head>.
 * The footer rules (hr-footer.css) are printed by footer.php where its <style> was, after any template <style>.
 * Optional design layers (assets/css/hr-elevate.css, assets/js/hr-ui.js) load after them when present.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** URL + filemtime version of a theme file, or null when it does not exist. */
function hr_theme_file( string $rel ): ?array {
    $path = get_stylesheet_directory() . '/' . $rel;
    return is_readable( $path ) ? array( get_stylesheet_directory_uri() . '/' . $rel, (string) filemtime( $path ) ) : null;
}

/** Spotlight posts use their own template (see the single_template filter in functions.php). */
function hr_is_spotlight_post(): bool {
    return is_singular( 'post' ) && in_category( 'executive-spotlight', get_queried_object_id() );
}

add_action( 'wp_enqueue_scripts', 'hr_theme_assets', 20 );
function hr_theme_assets() {
    // The parent's covernews-script is a webpack dev build that runs its code through eval(), which the site CSP
    // (no 'unsafe-eval') blocks, and with it the mobile menu, sticky header and scroll-up button. The parent
    // ships the same code un-bundled in assets/script.js; serve that under the same handle.
    if ( wp_script_is( 'covernews-script', 'registered' ) ) {
        $parent = wp_scripts()->registered['covernews-script'];
        wp_deregister_script( 'covernews-script' );
        wp_enqueue_script( 'covernews-script', get_template_directory_uri() . '/assets/script.js', $parent->deps, $parent->ver, true );
    }

    $styles = array( 'hr-base', 'hr-footer', 'hr-elevate' );
    if ( is_singular( 'post' ) ) {
        $styles[] = hr_is_spotlight_post() ? 'hr-spotlight' : 'hr-single';
    }
    foreach ( $styles as $handle ) {
        $file = hr_theme_file( "assets/css/{$handle}.css" );
        if ( $file ) {
            wp_register_style( $handle, $file[0], array(), $file[1] );
        }
    }

    $scripts = array( 'hr-site', 'hr-ui' );
    if ( is_singular( 'post' ) && ! hr_is_spotlight_post() ) {
        $scripts[] = 'hr-single';
    }
    foreach ( $scripts as $handle ) {
        $file = hr_theme_file( "assets/js/{$handle}.js" );
        if ( $file ) {
            wp_enqueue_script( $handle, $file[0], array(), $file[1], array( 'strategy' => 'defer', 'in_footer' => true ) );
        }
    }
}

// Handles that were not registered on this page are skipped by wp_print_styles().
add_action( 'wp_head', static fn() => wp_print_styles( array( 'hr-spotlight', 'hr-single', 'hr-base', 'hr-elevate' ) ), 999 );

// Desktop search trigger + panel, cloned into the nav bar by hr-site.js (server-rendered, so no HTML in JS).
add_action( 'wp_footer', 'hr_nav_tools_template', 5 );
function hr_nav_tools_template() {
    ?>
<template id="hr-nav-tools">
    <div id="hr-action-group">
        <button type="button" id="hr-search-trigger" aria-label="<?php esc_attr_e( 'Search', 'covernews' ); ?>" aria-expanded="false" aria-controls="hr-navbar-search">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
        </button>
    </div>
    <div id="hr-navbar-search" inert>
        <form role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
            <input type="text" name="s" id="hr-inline-input" placeholder="Type keyword or company to search..." autocomplete="off" aria-label="<?php esc_attr_e( 'Search Headline Recorder', 'covernews' ); ?>">
            <button type="button" class="hr-inline-search-close" aria-label="<?php esc_attr_e( 'Close search', 'covernews' ); ?>">&times;</button>
        </form>
    </div>
</template>
    <?php
}
