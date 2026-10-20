<?php
/**
 * Structured data on top of Yoast's graph:
 *   - Organization / NewsMediaOrganization: legal name, postal address, email/phone, contact point, extra profiles
 *     (only the parts that are filled in under Settings → Headline Recorder → Organisation);
 *   - PodcastSeries on /podcast/ and PodcastEpisode on episodes (when the podcast module's hr_episode type exists);
 *   - Yoast's graph on Executive Spotlight interviews, whose template prints no structured data of its own.
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
    return $data;
}, 20 );

/**
 * The theme turns Yoast's graph off on every is_single() page (single.php prints a hand-built NewsArticle instead).
 * Executive Spotlight interviews and podcast episodes print no article JSON-LD, so turn Yoast's graph (WebPage,
 * Organization, breadcrumbs) back on for them; the PodcastEpisode below points at its Organization @id.
 * Filterable so the theme can take over once it prints its own.
 */
add_filter( 'wpseo_json_ld_output', function ( $data ) {
    $own = is_singular( 'hr_episode' ) || ( is_singular( 'post' ) && in_category( 'executive-spotlight', get_queried_object_id() ) );
    if ( false === $data && $own && apply_filters( 'hr_site_yoast_graph_on_spotlight', true ) ) {
        return array( '_deprecated' => '' );
    }
    return $data;
}, 20 );

// The address block for the footer (and anywhere else the theme calls the action). Only filled parts are printed.
add_action( 'hr_footer_contact', function () {
    render( 'site/footer-contact', array( 'org' => org() ) );
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
