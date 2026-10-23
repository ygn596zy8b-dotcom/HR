<?php
/**
 * Titles, meta descriptions and Open Graph, layered on top of Yoast SEO and never replacing it.
 *
 * Yoast stays the source of truth (its templates and every per-post / per-term setting an editor typed in win).
 * This file only fills gaps:
 *   - descriptions that are empty, carry raw URLs / mojibake, or run far past what search engines show;
 *   - titles over 60 characters lose the " - Headline Recorder" suffix (the headline matters more);
 *   - paginated archives always say which page they are, so every title is unique;
 *   - posts without any image get a branded 1200×630 card (see og-card.php);
 *   - virtual routes (accounts, verify, thank-you…) describe themselves instead of inheriting the home page's tags.
 */

namespace HR\Platform\Site;

use function HR\Platform\current_route;
use function HR\Platform\route_url;
use function HR\Platform\setting;

if ( ! defined( 'ABSPATH' ) ) exit;

const TITLE_MAX = 60;
const DESC_MAX  = 160;

/** Plain text from editor HTML: no tags, shortcodes, blocks, entities, bare URLs, mojibake or doubled spaces. */
function plain_text( string $text ): string {
    $text = excerpt_remove_blocks( strip_shortcodes( $text ) );
    $text = wp_strip_all_tags( $text, true );
    $text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
    $text = wp_check_invalid_utf8( $text, true );
    // A replacement character between two letters is a curly apostrophe lost in a copy/paste (Pakistan�s).
    $text = preg_replace( '/(?<=\p{L})\x{FFFD}(?=\p{L})/u', '’', $text );
    $text = str_replace( "\u{FFFD}", '', $text );
    $text = preg_replace( '#\b(?:https?://|www\.)\S+#iu', '', $text );
    $text = preg_replace( '/\s+/u', ' ', $text );
    return trim( (string) $text );
}

/** Shortens to what a results page shows: a whole sentence when one fits, else a word boundary plus an ellipsis. */
function shorten( string $text, int $limit = DESC_MAX ): string {
    if ( mb_strlen( $text ) <= $limit ) {
        return $text;
    }
    $cut = mb_substr( $text, 0, $limit );
    if ( preg_match( '/^(.{90,}[.!?])(?=\s|$)/su', $cut, $m ) ) {
        return $m[1];
    }
    $cut = mb_substr( $text, 0, $limit - 4 );
    $cut = preg_replace( '/\s+\S*$/u', '', $cut );
    return rtrim( $cut, " ,;:-\u{2013}\u{2014}" ) . '…';
}

function site_name(): string {
    return html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
}

/** Podcast show details from the podcast module's settings, with safe fallbacks (owner decision: the show is "Podcast"). */
function podcast_info(): array {
    $title = trim( (string) setting( 'podcast_title' ) );
    $desc  = trim( (string) ( setting( 'podcast_description' ) ?: setting( 'podcast_tagline' ) ) );
    return array(
        'title'       => '' !== $title ? $title : 'Podcast',
        'description' => '' !== $desc ? plain_text( $desc ) : 'The Headline Recorder Podcast: Pakistan’s business and economy news, briefed in minutes.',
        'image'       => (int) ( setting( 'podcast_cover' ) ?: setting( 'podcast_image' ) ),
    );
}

function is_podcast_archive(): bool {
    return post_type_exists( 'hr_episode' ) && is_post_type_archive( 'hr_episode' );
}

/** Descriptions for virtual routes; modules can add theirs through the hr_route_description filter. */
function route_description( string $route ): string {
    $defaults = array(
        'verify'    => 'Check that a Headline Recorder article is genuine and unchanged: verify its post-quantum signature (ML-DSA-87 + Ed448) in your browser.',
        'thank-you' => 'Thank you from Headline Recorder: Pakistan business, economy, markets and policy news from Karachi.',
    );
    $desc = $defaults[ $route ] ?? '';
    if ( '' === $desc && str_starts_with( $route, 'account' ) ) {
        $desc = 'Your Headline Recorder account: reading streaks, saved progress and submissions.';
    }
    return (string) apply_filters( 'hr_route_description', $desc, $route );
}

/**
 * The description for the current request. $yoast is what Yoast generated from its templates and the editor's settings.
 * Computed once per request so the meta description and og:description always agree.
 */
function description( string $yoast ): string {
    static $done = array();
    $key = current_route() . '|' . get_queried_object_id() . '|' . ( is_search() ? 's' : '' ) . ( is_404() ? '404' : '' );
    if ( isset( $done[ $key ] ) ) {
        return $done[ $key ];
    }
    $clean = plain_text( $yoast );
    $desc  = $clean;

    if ( '' !== current_route() ) {
        $desc = route_description( current_route() ) ?: $clean;
    } elseif ( is_singular() ) {
        $post = get_queried_object();
        if ( $post instanceof \WP_Post && '' === trim( (string) get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true ) ) ) {
            // No hand-written description: summary → excerpt → body, whichever is substantial first.
            foreach ( array( get_post_meta( $post->ID, '_hr_geo_summary', true ), $post->post_excerpt, $post->post_content ) as $candidate ) {
                $candidate = plain_text( (string) $candidate );
                if ( mb_strlen( $candidate ) >= 50 ) {
                    $desc = $candidate;
                    break;
                }
            }
        }
    } elseif ( '' === $clean && ( is_category() || is_tag() || is_tax() ) ) {
        $term = get_queried_object();
        $name = $term instanceof \WP_Term ? html_entity_decode( $term->name, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) : '';
        if ( is_category() ) {
            $desc = sprintf( 'Latest %s news and analysis from Pakistan by %s: markets, the economy, companies and policy.', $name, site_name() );
        } elseif ( is_tax( 'guest_author' ) ) {
            $desc = sprintf( 'Op-eds and analysis by %s on Pakistan’s economy, business and policy, published by %s.', $name, site_name() );
        } elseif ( is_tag() ) {
            $desc = sprintf( 'News and analysis on %s from %s, Pakistan’s business and economy newsroom.', $name, site_name() );
        } else {
            $desc = sprintf( 'Latest coverage of %s from %s, Pakistan business and economy news.', $name, site_name() );
        }
    } elseif ( '' === $clean && is_author() ) {
        $user = get_queried_object();
        $desc = sprintf( 'Reporting and analysis by %s for %s, covering Pakistan’s business, economy and markets.', $user instanceof \WP_User ? $user->display_name : 'our writers', site_name() );
    } elseif ( is_podcast_archive() && '' === $clean ) {
        $desc = podcast_info()['description'];
    } elseif ( '' === $clean && is_search() ) {
        $desc = sprintf( 'Search results for “%s” across %s’s coverage of Pakistan’s business and economy.', get_search_query( false ), site_name() );
    } elseif ( '' === $clean && is_404() ) {
        $desc = sprintf( 'This page could not be found. Browse the latest Pakistan business and economy news from %s.', site_name() );
    }

    return $done[ $key ] = shorten( $desc );
}

add_filter( 'wpseo_metadesc', static fn( $desc ) => description( (string) $desc ), 15 );

add_filter( 'wpseo_opengraph_desc', function ( $desc, $presentation = null ) {
    if ( is_singular() && '' !== trim( (string) get_post_meta( get_queried_object_id(), '_yoast_wpseo_opengraph-description', true ) ) ) {
        return $desc; // The editor wrote a social description on purpose.
    }
    $meta = is_object( $presentation ) && isset( $presentation->meta_description ) ? (string) $presentation->meta_description : (string) $desc;
    return description( $meta );
}, 15, 2 );

/** Titles: unique page numbers on archives, and the brand suffix only when the whole title stays readable in results. */
function title( string $title ): string {
    $title  = trim( $title );
    $site   = preg_quote( site_name(), '/' );
    $suffix = '/\s+[-|:~\x{2013}\x{2014}\x{00B7}\x{2022}]\s+' . $site . '\s*$/u';
    $paged  = max( (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );

    if ( $paged > 1 && ! is_singular() && ! preg_match( '/\bpage\s+' . $paged . '\b/i', $title ) ) {
        $label = sprintf( 'Page %d', $paged );
        $title = preg_match( $suffix, $title ) ? preg_replace( $suffix, ' - ' . $label . '$0', $title, 1 ) : $title . ' - ' . $label;
    }
    if ( mb_strlen( $title ) > TITLE_MAX && preg_match( $suffix, $title ) ) {
        $short = trim( preg_replace( $suffix, '', $title ) );
        if ( '' !== $short ) {
            $title = $short;
        }
    }
    return $title;
}

add_filter( 'wpseo_title', function ( $title ) {
    if ( is_podcast_archive() ) {
        $configured = class_exists( '\WPSEO_Options' ) ? (string) \WPSEO_Options::get( 'title-ptarchive-hr_episode', '' ) : '';
        if ( '' === $configured || str_contains( $configured, '%%pt_plural%% Archive' ) ) {
            $show  = podcast_info()['title'];
            $title = 'podcast' === strtolower( $show )
                ? sprintf( '%s Podcast: Pakistan Business News Briefings', site_name() )
                : sprintf( '%s - %s Podcast', $show, site_name() );
        }
    }
    return title( (string) $title );
}, 15 );

// Virtual routes (core/routes.php set_page_title) join the page name and the brand with an em dash; use the same
// " - " separator as Yoast's titles so every title on the site reads alike (and the house style holds).
$hr_route_title = static function ( $title ) {
    if ( '' === current_route() || ! is_string( $title ) ) {
        return $title;
    }
    return (string) preg_replace( '/\s+[\x{2013}\x{2014}\x{2015}|]\s+(?=' . preg_quote( site_name(), '/' ) . '\s*$)/u', ' - ', $title );
};
add_filter( 'pre_get_document_title', $hr_route_title, 30 );
add_filter( 'wpseo_title', $hr_route_title, 30 );
unset( $hr_route_title );

// og:title is the headline itself; og:site_name already carries the brand. The front page keeps Yoast's own title
// (its page is called "Home", which says nothing in a share preview).
add_filter( 'wpseo_opengraph_title', function ( $title ) {
    if ( '' !== current_route() ) {
        return trim( preg_replace( '/\s+[-|:\x{2013}\x{2014}]\s+' . preg_quote( site_name(), '/' ) . '\s*$/u', '', wp_get_document_title() ) );
    }
    if ( is_singular() && ! is_front_page() ) {
        $id = get_queried_object_id();
        if ( '' === trim( (string) get_post_meta( $id, '_yoast_wpseo_opengraph-title', true ) ) ) {
            return html_entity_decode( wp_strip_all_tags( get_the_title( $id ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        }
    }
    return $title;
}, 15 );

add_filter( 'wpseo_twitter_card_type', static fn() => 'summary_large_image' );

// Virtual routes are their own pages, not the home page.
add_filter( 'wpseo_opengraph_url', static fn( $url ) => '' !== current_route() ? current_url() : $url, 15 );
add_filter( 'wpseo_opengraph_type', static fn( $type ) => '' !== current_route() ? 'website' : $type, 15 );
// Yoast has no indexable for virtual routes, so its graph holds only WebSite and Organization there. Add the page
// itself, so /verify/ and the other routes are described as their own WebPage within the site.
add_filter( 'wpseo_schema_graph', function ( $graph ) {
    if ( '' === current_route() || ! is_array( $graph ) ) {
        return $graph;
    }
    foreach ( $graph as $node ) {
        if ( is_array( $node ) && in_array( 'WebPage', (array) ( $node['@type'] ?? array() ), true ) ) {
            return $graph;
        }
    }
    $url     = current_url();
    $graph[] = array_filter( array(
        '@type'       => 'WebPage',
        '@id'         => $url,
        'url'         => $url,
        'name'        => wp_get_document_title(),
        'description' => plain_text( (string) apply_filters( 'wpseo_metadesc', description( '' ), null ) ), // the same text as the meta description
        'isPartOf'    => array( '@id' => home_url( '/#website' ) ),
        'publisher'   => array( '@id' => home_url( '/#organization' ) ),
        'inLanguage'  => get_bloginfo( 'language' ),
    ) );
    return $graph;
}, 15 );

/** Canonical URL of the current virtual route (path only, no query string). */
function current_url(): string {
    global $wp;
    $path = isset( $wp->request ) ? (string) $wp->request : current_route();
    return home_url( user_trailingslashit( '/' . ltrim( $path, '/' ) ) );
}

// Posts without any image: branded card instead of the generic fallback. The podcast archive uses the show cover.
add_filter( 'wpseo_add_opengraph_additional_images', function ( $container ) {
    if ( ! is_object( $container ) || ! method_exists( $container, 'has_images' ) || $container->has_images() ) {
        return $container;
    }
    if ( is_podcast_archive() && podcast_info()['image'] && method_exists( $container, 'add_image_by_id' ) ) {
        $container->add_image_by_id( podcast_info()['image'] );
        return $container;
    }
    if ( ! is_singular( card_post_types() ) ) {
        return $container;
    }
    $post = get_queried_object();
    if ( $post instanceof \WP_Post && ! has_post_thumbnail( $post ) && card_available() ) {
        $container->add_image( array(
            'url'    => card_url( $post ),
            'width'  => CARD_W,
            'height' => CARD_H,
            'type'   => 'image/png',
            'alt'    => html_entity_decode( wp_strip_all_tags( get_the_title( $post ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
        ) );
    }
    return $container;
} );

// The reading dashboard is personal (it redirects guests); keep it out of search results.
add_filter( 'wpseo_robots', static fn( $robots ) => is_page( 'dashboard' ) ? 'noindex, nofollow' : $robots, 15 );
