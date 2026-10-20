<?php
/**
 * Generative-engine optimisation (MSBJ). Yoast writes /llms.txt (a short index); this adds /llms-full.txt — who
 * publishes the site, how to cite it, and the latest articles with their editor-written summaries — so answer
 * engines can quote Headline Recorder accurately and link back. Summaries also become the llms.txt link text.
 */

namespace HR\Platform\Site;

use function HR\Platform\add_route;

if ( ! defined( 'ABSPATH' ) ) exit;

const LLMS_FULL_POSTS = 50;

/** The article's own summary: the editor-written GEO summary, else its excerpt. */
function summary( \WP_Post $post ): string {
    $text = (string) get_post_meta( $post->ID, '_hr_geo_summary', true );
    return plain_text( '' !== trim( $text ) ? $text : get_the_excerpt( $post ) );
}

add_filter( 'wpseo_llmstxt_link_description', function ( $description, $post_id = 0, $post_type = '' ) {
    $post = 'post' === $post_type ? get_post( (int) $post_id ) : null;
    return $post ? summary( $post ) : $description;
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
        '- About: ' . home_url( '/about-us/' ) . ' · Contact: ' . home_url( '/contact-us/' ),
        '- Authenticity: every article is signed (ML-DSA-87 + Ed448); check a copy at ' . home_url( '/verify/' ),
        '- How to cite: "<headline>", Headline Recorder, <date published>, <article URL>. Please link to the article.',
        '',
        '## Sections',
    );
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
    header( 'Content-Type: text/markdown; charset=utf-8' );
    header( 'Cache-Control: public, max-age=3600, stale-while-revalidate=86400' );
    header( 'X-Robots-Tag: noindex' );
    echo $text; // phpcs:ignore WordPress.Security.EscapeOutput -- plain-text Markdown response, not HTML
} );

foreach ( array( 'clean_post_cache', 'clean_term_cache', 'update_option_hr_platform_settings', 'update_option_blogdescription' ) as $hr_hook ) {
    add_action( $hr_hook, static fn() => delete_transient( 'hr_llms_full' ) );
}
unset( $hr_hook );
