<?php
/**
 * Generative-engine optimisation (MSBJ). Yoast writes /llms.txt (a short index); this adds /llms-full.txt (who
 * publishes the site, how to cite it, and the latest articles with their editor-written summaries) so answer
 * engines can quote Headline Recorder accurately and link back. Summaries also become the llms.txt link text, and
 * /llms.txt itself is served by a route here with cache headers (see below).
 */

namespace HR\Platform\Site;

use function HR\Platform\add_route;
use function HR\Platform\route_url;

if ( ! defined( 'ABSPATH' ) ) exit;

const LLMS_FULL_POSTS = 50;

/** The article's own summary: the editor-written GEO summary, else its excerpt. */
function summary( \WP_Post $post ): string {
    $text = (string) get_post_meta( $post->ID, '_hr_geo_summary', true );
    return plain_text( '' !== trim( $text ) ? $text : get_the_excerpt( $post ) );
}

add_filter( 'wpseo_llmstxt_link_description', function ( $description, $post_id = 0, $post_type = '' ) {
    $post = 'post' === $post_type ? get_post( (int) $post_id ) : null;
    $text = $post ? summary( $post ) : '';
    return '' !== $text ? shorten( $text, 300 ) : $description; // an index entry: one or two sentences
}, 10, 3 );

function llms_full(): string {
    $md   = static fn( string $s ): string => trim( str_replace( array( '[', ']', "\r", "\n" ), array( '(', ')', ' ', ' ' ), $s ) );
    $org  = org();
    $addr = implode( ', ', array_filter( array( $org['street_address'] ?? '', $org['locality'] ?? '', trim( ( $org['region'] ?? '' ) . ' ' . ( $org['postal_code'] ?? '' ) ), isset( $org['country'] ) ? country_name( $org['country'] ) : '' ) ) );
    $out  = array(
        '# ' . site_name(),
        '',
        '> ' . $md( plain_text( get_bloginfo( 'description' ) ) ),
        '',
        'Headline Recorder is a Pakistani business and economy news publication: markets, the economy, investment, technology and startups, executive interviews and opinion. News is published by its newsroom; opinion pieces carry the name of their guest author.',
        '',
        '## Publisher facts',
        '- Publisher: ' . ( $org['legal_name'] ?? site_name() ) . ( $addr ? " ({$addr})" : '' ),
        '- Website: ' . home_url( '/' ),
        '- Editorial and corrections policy: ' . home_url( '/editorial-corrections-policy/' ),
        '- About: ' . home_url( '/about-us/' ) . ' · Contact: ' . home_url( '/contact-us/' ) . ( ! empty( $org['email'] ) ? ' (' . $org['email'] . ')' : '' ),
        '- Founded: 2023 · Language: English · Based in: ' . ( $org['locality'] ?? 'Karachi' ) . ', ' . ( isset( $org['country'] ) ? country_name( $org['country'] ) : 'Pakistan' ),
        '- Coverage: Pakistan\'s economy, the State Bank of Pakistan and monetary policy, the Pakistan Stock Exchange (PSX), the federal budget and taxes (FBR), the IMF programme, banking, energy, agriculture, telecoms, technology and startups, plus global business news that matters to Pakistan',
        '- Authenticity: every article is signed (ML-DSA-87 + Ed448); check a copy at ' . home_url( '/verify/' ),
        '- How to cite: "<headline>", Headline Recorder, <date published>, <article URL>. Please link to the article.',
        '- Headlines, dates and bylines below are as published; each summary is the article\'s own summary or excerpt.',
        '',
        '## Key pages',
        '- [Latest news](' . home_url( '/' ) . ')',
        '- [Markets](' . home_url( '/markets/' ) . '): PSX, currency and commodity data',
        '- [Opinion](' . home_url( '/category/opinion/' ) . '): signed op-eds by guest experts',
        '- [Authors and experts](' . home_url( '/authors-experts/' ) . ')',
    );
    if ( post_type_exists( 'hr_episode' ) ) {
        $out[] = '- [Podcast](' . podcast_archive_url() . '): daily audio briefings, RSS feed ' . route_url( 'podcast/feed' );
    }
    array_push( $out, '- [RSS feed of all articles](' . get_feed_link() . ')', '', '## Sections' );
    foreach ( get_categories( array( 'orderby' => 'count', 'order' => 'DESC', 'number' => 12, 'hide_empty' => true, 'exclude' => array( (int) get_option( 'default_category' ) ) ) ) as $cat ) {
        $out[] = sprintf( '- [%s](%s)', $md( plain_text( $cat->name ) ), get_category_link( $cat ) );
    }
    $out[] = '';
    $out[] = '## Latest articles';
    $posts = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => LLMS_FULL_POSTS, 'no_found_rows' => true, 'suppress_filters' => false ) );
    foreach ( $posts as $post ) {
        $author = (string) get_post_meta( $post->ID, 'guest_author_name', true ) ?: get_the_author_meta( 'display_name', (int) $post->post_author );
        $meta   = array( 'Published ' . get_the_date( 'Y-m-d', $post ) );
        if ( get_post_modified_time( 'U', true, $post ) - get_post_time( 'U', true, $post ) > DAY_IN_SECONDS ) {
            $meta[] = 'Updated ' . get_the_modified_date( 'Y-m-d', $post );
        }
        $meta[] = 'By ' . $md( plain_text( $author ) );
        $cats   = array_values( array_filter( get_the_category( $post->ID ), static fn( $c ) => (int) $c->term_id !== (int) get_option( 'default_category' ) ) );
        if ( $cats ) {
            $meta[] = 'Section: ' . $md( plain_text( $cats[0]->name ) );
        }
        array_push( $out, '', sprintf( '### [%s](%s)', $md( plain_text( get_the_title( $post ) ) ), get_permalink( $post ) ), implode( ' · ', $meta ), '', $md( summary( $post ) ) );
    }
    return implode( "\n", $out ) . "\n";
}

add_route( 'llms-full', '^llms-full\.txt$', function () {
    $text = get_transient( 'hr_llms_full' );
    if ( false === $text ) {
        $text = llms_full();
        set_transient( 'hr_llms_full', $text, DAY_IN_SECONDS );
    }
    llms_headers();
    echo $text; // phpcs:ignore WordPress.Security.EscapeOutput -- plain-text Markdown response, not HTML
} );

/** Response headers for /llms.txt and /llms-full.txt: plain text, cacheable for an hour at the edge and in browsers. */
function llms_headers(): void {
    header( 'Content-Type: text/plain; charset=utf-8' );
    header( 'Cache-Control: public, max-age=3600, s-maxage=3600, stale-while-revalidate=86400' );
    header( 'X-Robots-Tag: noindex' ); // for answer engines, not a page for search results
    header( 'X-Content-Type-Options: nosniff' );
}

// ---------------------------------------------------------------------------------------------------------------
// /llms.txt: Yoast writes it as a static file in the web root, which the web server sends with no Cache-Control
// (so the edge never caches it) and a guessed content type. Yoast writes it to uploads/hr-llms/ instead, and the
// route below serves it with proper headers. A copy Yoast left in the web root is removed once (only when it is
// still exactly what Yoast wrote; a hand-made llms.txt is never touched and keeps winning, as static files do).
// ---------------------------------------------------------------------------------------------------------------

function llms_dir(): string {
    $uploads = wp_upload_dir( null, false );
    return trailingslashit( $uploads['basedir'] ) . 'hr-llms';
}

add_filter( 'wpseo_llmstxt_filesystem_path', static function ( $path ) {
    $dir = llms_dir();
    if ( wp_mkdir_p( $dir ) ) {
        if ( ! file_exists( $dir . '/index.php' ) ) {
            @file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
        }
        return $dir;
    }
    return $path; // uploads not writable: Yoast keeps its own location
}, 20 );

add_route( 'llms', '^llms\.txt$', static function () {
    $file = llms_dir() . '/llms.txt';
    if ( ! is_readable( $file ) ) {
        status_header( 404 );
        nocache_headers();
        header( 'Content-Type: text/plain; charset=utf-8' );
        echo 'Not found';
        return;
    }
    llms_headers();
    header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', (int) filemtime( $file ) ) . ' GMT' );
    readfile( $file );
} );

add_action( 'admin_init', static function () {
    if ( 'v1' === get_option( 'hr_site_llms_moved' ) || ! current_user_can( 'manage_options' ) ) {
        return;
    }
    update_option( 'hr_site_llms_moved', 'v1', false );
    if ( ! function_exists( 'get_home_path' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    $old  = trailingslashit( get_home_path() ) . 'llms.txt';
    $hash = (string) get_option( 'wpseo_llms_txt_content_hash', '' );
    $new  = apply_filters( 'wpseo_llmstxt_filesystem_path', '' ) . '/llms.txt'; // creates the folder
    if ( '' !== $hash && $old !== $new && is_readable( $old ) && md5( (string) file_get_contents( $old ) ) === $hash ) {
        // Copy first, so /llms.txt never goes missing, then drop the root copy that would shadow the route.
        if ( is_readable( $new ) || @copy( $old, $new ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
            @unlink( $old ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
        }
    }
    wp_schedule_single_event( time() + 30, 'wpseo_llms_txt_population' ); // Yoast refreshes it in the new place
} );

foreach ( array( 'clean_post_cache', 'clean_term_cache', 'update_option_hr_platform_settings', 'update_option_blogdescription' ) as $hr_hook ) {
    add_action( $hr_hook, static fn() => delete_transient( 'hr_llms_full' ) );
}
unset( $hr_hook );
