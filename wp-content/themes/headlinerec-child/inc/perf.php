<?php
/**
 * Front-end performance (MSBJ). Every change here was measured with Lighthouse (mobile); the numbers, and what
 * was tried and dropped, are in docs/PERFORMANCE.md.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// The parent forces loading="lazy" on every attachment image (covernews_alt_text_optimized), including the ones a
// template asks to load first with fetchpriority="high" — that held the article hero (the LCP) back ~350 ms.
add_filter( 'wp_get_attachment_image_attributes', static function ( $attr ) {
    if ( 'high' === ( $attr['fetchpriority'] ?? '' ) ) {
        $attr['loading'] = 'eager';
    }
    return $attr;
}, 20 );

// The parent preloads the full 1900 px header PNG (712 KB) at high priority on every page. It is a decorative
// background its script applies after load, so the preload only takes bandwidth from the LCP.
add_action( 'after_setup_theme', static fn() => remove_action( 'wp_head', 'covernews_preload_header_image' ) );

// Source Sans: the parent serves the full 1,800-glyph faces (75 KB each). Latin subsets (15 KB, assets/fonts/OFL.txt)
// are declared inline and preloaded, since both faces are used in the first screen (headlines 700, text 400).
add_action( 'wp_head', 'hr_perf_fonts', 2 );
function hr_perf_fonts() {
    $css = '';
    foreach ( array( 400, 700 ) as $weight ) {
        $url = get_stylesheet_directory_uri() . "/assets/fonts/hr-sans-{$weight}.woff2";
        printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( $url ) );
        $css .= sprintf( "@font-face{font-family:'Source Sans Pro';font-style:normal;font-weight:%d;font-display:swap;src:url(%s) format('woff2');unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20A8,U+20AC,U+20B9,U+2122,U+2190-2193,U+2212,U+2215,U+FEFF,U+FFFD}", $weight, esc_url( $url ) );
    }
    echo '<style id="hr-fonts">' . $css . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- built from integers and esc_url() above
}

add_action( 'wp_enqueue_scripts', 'hr_perf_assets', 100 );
function hr_perf_assets() {
    wp_dequeue_style( 'covernews-source-sans' );

    // jQuery was the only render-blocking script; nothing in <head> or inline in the page needs it before the footer,
    // and any head script that does depend on it pulls it back into <head> (WP_Scripts groups).
    foreach ( array( 'jquery', 'jquery-core', 'jquery-migrate' ) as $handle ) {
        wp_scripts()->add_data( $handle, 'group', 1 );
    }

    // Footer scripts still hold the first paint of a short page (the whole document arrives in one chunk), so every
    // classic footer script is deferred; WordPress keeps blocking any whose dependents or inline "after" code can't wait.
    add_action( 'wp_print_footer_scripts', static function () {
        $scripts = wp_scripts();
        foreach ( $scripts->registered as $handle => $script ) {
            if ( $script->src && 1 === (int) $scripts->get_data( $handle, 'group' ) && ! $scripts->get_data( $handle, 'strategy' ) ) {
                $scripts->add_data( $handle, 'strategy', 'defer' );
            }
        }
    }, 9 );

    $content = is_singular() ? (string) get_post_field( 'post_content', get_queried_object_id() ) : '';

    // Blockspare stays active, but no page uses its blocks: its CSS and Font Awesome only cost render time.
    if ( ! str_contains( $content, '<!-- wp:blockspare/' ) ) {
        foreach ( array( 'blockspare-fontawesome', 'blockspare-common-latest-post', 'blockspare-common-all-blocks', 'blockspare-animation-blocks' ) as $handle ) {
            wp_dequeue_style( $handle );
        }
        wp_dequeue_script( 'block_animation_script' );
    }

    // Contact Form 7 and its reCAPTCHA only where a form is printed: the Contact and PR & Media templates
    // (page-contact-us.php, page-pr-media.php) or content that embeds a form.
    if ( ! is_page( array( 'contact-us', 'pr-media' ) ) && ! has_shortcode( $content, 'contact-form-7' ) && ! str_contains( $content, '<!-- wp:contact-form-7/' ) ) {
        wp_dequeue_style( 'contact-form-7' );
        foreach ( array( 'contact-form-7', 'wpcf7-recaptcha', 'google-recaptcha' ) as $handle ) {
            wp_dequeue_script( $handle );
        }
    }
}

// Home sections (hook-front-page-main-banner-section-1.php, podcast hero included): ~100 queries and ~90 ms per
// render, and the same HTML for every reader (sign-in state is applied in the browser). It is kept until any post,
// term, Customizer or platform setting changes; the 10-minute expiry is only a backstop.
add_action( 'init', static function () {
    if ( remove_action( 'covernews_action_front_page_main_section_1', 'covernews_front_page_main_section_1', 40 ) ) {
        add_action( 'covernews_action_front_page_main_section_1', 'hr_home_sections_cached', 40 );
    }
} );
function hr_home_sections_cached() {
    $html = is_customize_preview() ? false : get_transient( 'hr_home_sections' );
    if ( false === $html ) {
        ob_start();
        covernews_front_page_main_section_1();
        $html = (string) ob_get_clean();
        if ( ! is_customize_preview() ) {
            set_transient( 'hr_home_sections', $html, 10 * MINUTE_IN_SECONDS );
        }
    }
    // The home page had no <main> landmark: the sections are its main content.
    echo '<main id="hr-home-main">' . $html . '</main>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by the template when it was rendered
}
foreach ( array( 'clean_post_cache', 'clean_term_cache', 'customize_save_after', 'update_option_hr_platform_settings' ) as $hr_hook ) {
    add_action( $hr_hook, static fn() => delete_transient( 'hr_home_sections' ) );
}
unset( $hr_hook );

// Instant navigation: prefetch a same-origin page when the reader hovers or starts to touch its link (core's default
// waits for the click). Core already skips signed-in readers, wp-admin, wp-*.php (login/logout) and query URLs.
add_filter( 'wp_speculation_rules_configuration', static fn( $config ) => is_array( $config ) ? array( 'mode' => 'prefetch', 'eagerness' => 'moderate' ) : $config );
add_filter( 'wp_speculation_rules_href_exclude_paths', static fn( $paths ) => array_merge( $paths, array( '/account/*', '/dashboard/*', '/wp-json/*', '/feed/*', '/*/feed/*', '/signed/*', '/og/*' ) ) );

/**
 * YouTube facade: the video's poster and a play button; the privacy-enhanced player (~1 MB of script) is only
 * loaded when the reader presses play (hr-ui.js swaps in the iframe).
 */
function hr_youtube_facade( string $video_id, string $title ): string {
    if ( ! preg_match( '/^[A-Za-z0-9_-]{11}$/', $video_id ) ) {
        return '';
    }
    $title = '' !== trim( $title ) ? $title : 'YouTube video';
    return sprintf(
        '<div class="hr-yt"><button type="button" class="hr-yt__btn" data-hr-yt="%1$s" data-hr-title="%2$s"><img src="%3$s" alt="" width="480" height="360" loading="lazy" decoding="async"><span class="hr-yt__play" aria-hidden="true"></span><span class="screen-reader-text">%4$s</span></button></div>',
        esc_attr( $video_id ),
        esc_attr( $title ),
        esc_url( "https://i.ytimg.com/vi/{$video_id}/hqdefault.jpg" ),
        esc_html( sprintf( 'Play video: %s', $title ) )
    );
}

// Article oEmbeds (after the consent filter has made them youtube-nocookie at priority 20).
add_filter( 'embed_oembed_html', static function ( $html ) {
    if ( is_string( $html ) && ! is_admin() && preg_match( '#<iframe[^>]+src="https://www\.youtube(?:-nocookie)?\.com/embed/([A-Za-z0-9_-]{11})#', $html, $id ) ) {
        preg_match( '#\btitle="([^"]*)"#', $html, $title );
        return hr_youtube_facade( $id[1], html_entity_decode( $title[1] ?? '', ENT_QUOTES ) ) ?: $html;
    }
    return $html;
}, 30 );
