<?php
/**
 * Structured data on top of Yoast's graph:
 *   - Organization / NewsMediaOrganization: legal name, postal address, email/phone, contact point, extra profiles
 *     (only the parts that are filled in under Settings → Headline Recorder → Organisation);
 *     plus a plain description and the subjects the newsroom covers (knowsAbout), which answer engines use to
 *     decide what the publisher is an authority on;
 *   - WebSite and Organization text decoded (the tagline is stored with HTML entities such as &#039;);
 *   - PodcastSeries on /podcast/ and PodcastEpisode on episodes (when the podcast module's hr_episode type exists);
 *   - Yoast's graph (WebPage, BreadcrumbList, WebSite, Organization) on every article, without Yoast's own Article
 *     node: the theme prints the article's NewsArticle (inc/schema.php), which points at the same @id values.
 * Plus the footer address block on do_action( 'hr_footer_contact' ).
 */

namespace HR\Platform\Site;

use function HR\Platform\render;
use function HR\Platform\route_url;

if ( ! defined( 'ABSPATH' ) ) exit;

const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP;

function org_id(): string {
    return home_url( '/#organization' );
}

// Runs after the theme's filters (10: foundingDate, 11: NewsMediaOrganization + editorial policy URLs).
add_filter( 'wpseo_schema_organization', function ( $data ) {
    if ( ! is_array( $data ) ) {
        return $data;
    }
    $org = org();
    if ( ! empty( $org['legal_name'] ) ) {
        $data['legalName'] = $org['legal_name'];
    }
    $address = array_filter( array(
        'streetAddress'   => $org['street_address'] ?? '',
        'addressLocality' => $org['locality'] ?? '',
        'addressRegion'   => $org['region'] ?? '',
        'postalCode'      => $org['postal_code'] ?? '',
        'addressCountry'  => $org['country'] ?? '',
    ) );
    if ( $address ) {
        $data['address'] = array( '@type' => 'PostalAddress' ) + $address;
    }
    if ( ! empty( $org['email'] ) ) {
        $data['email'] = $org['email'];
    }
    if ( ! empty( $org['phone'] ) ) {
        $data['telephone'] = $org['phone'];
    }
    if ( ! empty( $org['email'] ) || ! empty( $org['phone'] ) ) {
        $data['contactPoint'] = array( array_filter( array(
            '@type'             => 'ContactPoint',
            'contactType'       => 'editorial',
            'email'             => $org['email'] ?? '',
            'telephone'         => $org['phone'] ?? '',
            'areaServed'        => $org['country'] ?? '',
            'availableLanguage' => 'English',
            'url'               => home_url( '/contact-us/' ),
        ) ) );
    }
    if ( ! empty( $org['same_as'] ) ) {
        $extra          = preg_split( '/\R/', $org['same_as'] );
        $data['sameAs'] = array_values( array_unique( array_filter( array_merge( (array) ( $data['sameAs'] ?? array() ), $extra ) ) ) );
    }
    if ( empty( $data['description'] ) ) {
        $data['description'] = 'Headline Recorder is a Karachi-based digital news publication covering Pakistan’s economy, business, financial markets, technology and startups, with executive interviews and signed opinion pieces.';
    }
    if ( empty( $data['knowsAbout'] ) ) {
        $data['knowsAbout'] = array( 'Pakistan economy', 'Pakistan Stock Exchange (PSX)', 'State Bank of Pakistan monetary policy', 'Federal budget and taxation (FBR)', 'IMF programme', 'Banking and finance', 'Energy and commodities', 'Technology and startups', 'Corporate news' );
    }
    if ( empty( $data['areaServed'] ) && ! empty( $org['country'] ) ) {
        $data['areaServed'] = $org['country'];
    }
    return decode_schema_text( $data );
}, 20 );

/** Decodes HTML entities in the name and description of a schema node (WordPress stores the tagline encoded). */
function decode_schema_text( $data ) {
    if ( ! is_array( $data ) ) {
        return $data;
    }
    foreach ( array( 'name', 'alternateName', 'description', 'legalName' ) as $key ) {
        if ( isset( $data[ $key ] ) && is_string( $data[ $key ] ) ) {
            $data[ $key ] = trim( html_entity_decode( wp_strip_all_tags( $data[ $key ] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
        }
    }
    return $data;
}
add_filter( 'wpseo_schema_website', __NAMESPACE__ . '\\decode_schema_text', 20 );
add_filter( 'wpseo_schema_webpage', __NAMESPACE__ . '\\decode_schema_text', 20 );

// Breadcrumb names come from texturized titles ("Pakistan&#8217;s"); JSON-LD wants the plain characters.
add_filter( 'wpseo_schema_breadcrumb', static function ( $data ) {
    if ( is_array( $data ) && isset( $data['itemListElement'] ) && is_array( $data['itemListElement'] ) ) {
        $data['itemListElement'] = array_map( __NAMESPACE__ . '\\decode_schema_text', $data['itemListElement'] );
    }
    return $data;
}, 20 );

/**
 * The theme turns Yoast's graph off on every is_single() page because single.php prints a hand-built NewsArticle
 * (inc/schema.php). That also removed the page's WebPage, BreadcrumbList, WebSite and Organization nodes (address,
 * profiles, policies), so the graph is turned back on for articles and podcast episodes, and Yoast's own Article and
 * author nodes are left out on articles: the theme's NewsArticle is the only article, and its mainEntityOfPage and
 * publisher point at the same WebPage and #organization @id values. Filterable so the theme can take over.
 */
function yoast_graph_on_single(): bool {
    $own = is_singular( 'hr_episode' ) || is_singular( 'post' );
    return $own && (bool) apply_filters( 'hr_site_yoast_graph_on_singles', apply_filters( 'hr_site_yoast_graph_on_spotlight', true ) );
}

add_filter( 'wpseo_json_ld_output', function ( $data ) {
    if ( false === $data && yoast_graph_on_single() ) {
        return array( '_deprecated' => '' );
    }
    return $data;
}, 20 );

add_filter( 'wpseo_schema_article_type', static fn( $type ) => is_singular( 'post' ) && yoast_graph_on_single() ? 'None' : $type, 20 );

// The address block for the footer (and anywhere else the theme calls the action). Only filled parts are printed.
// Compact: the address on two lines beside a pin, then the email (and phone, when set) on one line each beside an
// icon. Plain semantic <address>; the structured data is in the page's JSON-LD graph.
add_action( 'hr_footer_contact', function () {
    $org      = org();
    $street   = (string) ( $org['street_address'] ?? '' );
    $locality = trim( implode( ' ', array_filter( array(
        implode( ', ', array_filter( array( $org['locality'] ?? '', $org['region'] ?? '' ) ) ),
        $org['postal_code'] ?? '',
    ) ) ) );
    $city     = implode( ', ', array_filter( array( $locality, ! empty( $org['country'] ) ? country_name( $org['country'] ) : '' ) ) );
    $phone    = (string) ( $org['phone'] ?? '' );
    $email    = (string) ( $org['email'] ?? '' );
    if ( '' === $street && '' === $city && '' === $phone && '' === $email ) {
        return;
    }
    $icon = static fn( string $path ): string => '<svg class="hr-fc__icon" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path d="' . $path . '"/></svg>';
    $pin  = 'M12 2.5a7 7 0 0 0-7 7c0 5.2 7 12 7 12s7-6.8 7-12a7 7 0 0 0-7-7Zm0 9.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5Z';
    $mail = 'M3 5.5h18v13H3v-13Zm1.8 1.6v.3L12 12.6l7.2-5.2v-.3H4.8Zm14.4 2.3L12 14.6 4.8 9.4v7.5h14.4V9.4Z';
    $tel  = 'M6.6 3h3l1.5 4.5-2 1.3a11 11 0 0 0 6.1 6.1l1.3-2 4.5 1.5v3A2.6 2.6 0 0 1 18.4 20 15.4 15.4 0 0 1 4 5.6 2.6 2.6 0 0 1 6.6 3Z';

    echo '<address class="hr-footer-address hr-fc">';
    if ( '' !== $street || '' !== $city ) {
        echo '<span class="hr-fc__row hr-fc__row--addr">' . $icon( $pin ) . '<span class="hr-fc__text">'; // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG
        if ( ! empty( $org['legal_name'] ) ) {
            echo '<span class="screen-reader-text">' . esc_html( $org['legal_name'] ) . ', </span>';
        }
        echo implode( '<br>', array_map( 'esc_html', array_filter( array( $street, $city ) ) ) );
        echo '</span></span>';
    }
    if ( '' !== $email ) {
        printf(
            '<a class="hr-fc__row hr-fc__row--mail" href="%s">%s<span class="hr-fc__text">%s</span></a>',
            esc_url( 'mailto:' . $email, array( 'mailto' ) ),
            $icon( $mail ), // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG
            esc_html( $email )
        );
    }
    if ( '' !== $phone ) {
        printf(
            '<a class="hr-fc__row hr-fc__row--tel" href="%s">%s<span class="hr-fc__text">%s</span></a>',
            esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $phone ), array( 'tel' ) ),
            $icon( $tel ), // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG
            esc_html( $phone )
        );
    }
    echo '</address>';
} );

// ---------------------------------------------------------------------------------------------------------------
// Podcast
// ---------------------------------------------------------------------------------------------------------------

/** "PT1H2M3S" from seconds, "mm:ss" or "hh:mm:ss"; '' when unknown. */
function iso_duration( $value ): string {
    $value = trim( (string) $value );
    if ( '' === $value ) {
        return '';
    }
    if ( ctype_digit( $value ) ) {
        $seconds = (int) $value;
    } elseif ( preg_match( '/^(?:(\d+):)?(\d{1,2}):(\d{2})$/', $value, $m ) ) {
        $seconds = (int) $m[1] * 3600 + (int) $m[2] * 60 + (int) $m[3];
    } elseif ( preg_match( '/^PT(?:\d+H)?(?:\d+M)?(?:\d+S)?$/', $value ) && 'PT' !== $value ) {
        return $value;
    } else {
        return '';
    }
    if ( $seconds <= 0 ) {
        return '';
    }
    $h = intdiv( $seconds, 3600 );
    $m = intdiv( $seconds % 3600, 60 );
    $s = $seconds % 60;
    return 'PT' . ( $h ? $h . 'H' : '' ) . ( $m ? $m . 'M' : '' ) . ( $s ? $s . 'S' : '' );
}

function podcast_archive_url(): string {
    $link = get_post_type_archive_link( 'hr_episode' );
    return $link ? (string) $link : route_url( 'podcast' );
}

function podcast_series(): array {
    $info  = podcast_info();
    $name  = 'podcast' === strtolower( $info['title'] ) ? site_name() . ' Podcast' : $info['title'];
    $image = $info['image'] ? wp_get_attachment_image_url( $info['image'], 'full' ) : '';
    if ( ! $image ) {
        $icons = site_icons();
        $image = $icons['512'] ?? '';
    }
    return array_filter( array(
        '@type'       => 'PodcastSeries',
        '@id'         => podcast_archive_url() . '#series',
        'name'        => $name,
        'description' => $info['description'],
        'url'         => podcast_archive_url(),
        'webFeed'     => route_url( 'podcast/feed' ),
        'image'       => $image,
        'inLanguage'  => get_bloginfo( 'language' ),
        'author'      => array( '@id' => org_id() ),
        'publisher'   => array( '@id' => org_id() ),
    ) );
}

function podcast_episode( \WP_Post $post ): array {
    $audio_id  = (int) get_post_meta( $post->ID, '_hr_audio_id', true );
    $audio_url = $audio_id ? (string) wp_get_attachment_url( $audio_id ) : '';
    $audio_url = $audio_url ?: esc_url_raw( (string) get_post_meta( $post->ID, '_hr_audio_url', true ) );
    $duration  = iso_duration( get_post_meta( $post->ID, '_hr_duration', true ) );
    $number    = (int) get_post_meta( $post->ID, '_hr_episode_number', true );
    $summary   = plain_text( $post->post_excerpt ?: $post->post_content );
    $image     = has_post_thumbnail( $post ) ? (string) get_the_post_thumbnail_url( $post, 'full' ) : ( card_available() ? card_url( $post ) : '' );
    $episode   = array(
        '@type'         => 'PodcastEpisode',
        '@id'           => get_permalink( $post ) . '#episode',
        'url'           => get_permalink( $post ),
        'name'          => html_entity_decode( wp_strip_all_tags( get_the_title( $post ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
        'datePublished' => get_post_time( 'c', true, $post ),
        'dateModified'  => get_post_modified_time( 'c', true, $post ),
        'description'   => '' !== $summary ? shorten( $summary, 300 ) : '',
        'episodeNumber' => $number > 0 ? $number : '',
        'duration'      => $duration,
        'image'         => $image,
        'inLanguage'    => get_bloginfo( 'language' ),
        'partOfSeries'  => array( '@type' => 'PodcastSeries', '@id' => podcast_archive_url() . '#series', 'name' => podcast_series()['name'], 'url' => podcast_archive_url() ),
        'publisher'     => array( '@id' => org_id() ),
    );
    if ( $audio_url ) {
        $episode['associatedMedia'] = array_filter( array(
            '@type'          => 'AudioObject',
            'contentUrl'     => $audio_url,
            'encodingFormat' => $audio_id ? (string) get_post_mime_type( $audio_id ) : ( wp_check_filetype( (string) wp_parse_url( $audio_url, PHP_URL_PATH ) )['type'] ?: '' ),
            'duration'       => $duration,
        ) );
    }
    return array_filter( $episode, static fn( $v ) => '' !== $v && null !== $v && array() !== $v );
}

add_action( 'wp_head', function () {
    if ( ! post_type_exists( 'hr_episode' ) ) {
        return;
    }
    $data = null;
    if ( is_post_type_archive( 'hr_episode' ) ) {
        $data = array( '@context' => 'https://schema.org' ) + podcast_series();
    } elseif ( is_singular( 'hr_episode' ) && get_queried_object() instanceof \WP_Post ) {
        $data = array( '@context' => 'https://schema.org' ) + podcast_episode( get_queried_object() );
    }
    if ( $data ) {
        echo '<script type="application/ld+json" class="hr-podcast-schema">' . wp_json_encode( $data, JSON_FLAGS ) . "</script>\n";
    }
}, 30 );
