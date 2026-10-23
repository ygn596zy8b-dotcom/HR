<?php
/**
 * NewsArticle (OpinionNewsArticle for Opinion) JSON-LD for single posts and Executive Spotlight posts (MSBJ). Yoast's graph is switched off on
 * singles (functions.php), so this is the only article schema. Built as an array and printed with wp_json_encode.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** Plain text for schema values: tags stripped, HTML entities (&#8217; …) decoded. */
function hr_schema_text( $text ): string {
    return trim( html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
}

/** Publisher logo: Yoast's organisation logo (the same image as the site-wide graph), else the Customizer logo, else the site icon. */
function hr_publisher_logo(): ?array {
    $yoast = class_exists( 'WPSEO_Options' ) ? (int) WPSEO_Options::get( 'company_logo_id' ) : 0;
    $id    = $yoast ?: ( (int) get_theme_mod( 'custom_logo' ) ?: (int) get_option( 'site_icon' ) );
    $src = $id ? wp_get_attachment_image_src( $id, 'full' ) : false;
    return $src ? array( '@type' => 'ImageObject', 'url' => $src[0], 'width' => (int) $src[1], 'height' => (int) $src[2] ) : null;
}

/** Featured image as an ImageObject (with its size), or an empty array. */
function hr_schema_image( int $attachment_id ): array {
    $src = $attachment_id ? wp_get_attachment_image_src( $attachment_id, 'full' ) : false;
    return $src ? array( '@type' => 'ImageObject', 'url' => $src[0], 'width' => (int) $src[1], 'height' => (int) $src[2] ) : array();
}

/**
 * @param int    $post_id
 * @param array  $author    name, url and (optional) description and same_as profile URLs of the byline shown on the page.
 * @param string $scope     CSS class of the template's headline/body ("hr" → .hr-headline / .hr-body).
 */
/** The post's SEO meta description (Yoast, variables replaced), else its excerpt. */
function hr_schema_description( int $post_id ): string {
    $meta = (string) get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
    if ( '' !== trim( $meta ) && function_exists( 'wpseo_replace_vars' ) ) {
        $meta = wpseo_replace_vars( $meta, get_post( $post_id ) );
    }
    $meta = hr_schema_text( $meta );
    return '' !== $meta ? $meta : hr_schema_text( get_the_excerpt( $post_id ) );
}

function hr_print_article_schema( int $post_id, array $author, string $scope ) {
    $summary = hr_schema_text( get_post_meta( $post_id, '_hr_geo_summary', true ) );
    $schema  = array(
        '@context'            => 'https://schema.org',
        '@type'               => has_category( 'opinion', $post_id ) ? 'OpinionNewsArticle' : 'NewsArticle',
        'mainEntityOfPage'    => array( '@type' => 'WebPage', '@id' => get_permalink( $post_id ) ),
        'headline'            => hr_schema_text( get_the_title( $post_id ) ),
        'description'         => hr_schema_description( $post_id ),
        'datePublished'       => get_the_date( 'c', $post_id ),
        'dateModified'        => get_the_modified_date( 'c', $post_id ),
        'image'               => hr_schema_image( (int) get_post_thumbnail_id( $post_id ) ),
        'inLanguage'          => get_bloginfo( 'language' ),
        'articleSection'      => hr_schema_text( get_the_category( $post_id )[0]->name ?? '' ),
        'keywords'            => implode( ', ', wp_list_pluck( get_the_tags( $post_id ) ?: array(), 'name' ) ),
        'wordCount'           => str_word_count( wp_strip_all_tags( (string) get_post_field( 'post_content', $post_id ) ) ),
        // The editor-written GEO summary (shown as the "briefing" above the body): the article's own abstract.
        'abstract'            => $summary,
        'author'              => array_filter( array(
            '@type'       => 'Person',
            'name'        => hr_schema_text( $author['name'] ?? '' ),
            'url'         => $author['url'] ?? '',
            'description' => hr_schema_text( $author['description'] ?? '' ),
            'sameAs'      => array_values( array_filter( array_map( 'esc_url_raw', (array) ( $author['same_as'] ?? array() ) ) ) ),
        ) ),
        'publisher'           => array_filter( array(
            '@type' => 'NewsMediaOrganization',
            '@id'   => home_url( '/#organization' ), // the same node as the site-wide Organization graph (Yoast)
            'name'  => 'Headline Recorder',
            'url'   => home_url( '/' ),
            'logo'  => hr_publisher_logo(),
        ) ),
        'isAccessibleForFree' => true,
        'speakable'           => array(
            '@type'       => 'SpeakableSpecification',
            'cssSelector' => array_values( array_filter( array( ".{$scope}-headline", ( $summary && 'hr' === $scope ) ? '#ai-content-box' : '', ".{$scope}-body > p:first-of-type", ".{$scope}-body > p:nth-of-type(2)" ) ) ),
        ),
    );
    $schema = array_filter( $schema, static fn( $v ) => '' !== $v && array() !== $v );
    echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP ) . "</script>\n";
}

// Guest author archives: the author as a Person (bio, photo, profiles) on a ProfilePage, so the byline on their
// op-eds resolves to a real expert (E-E-A-T). The term meta is saved by the Guest Author box (functions.php).
add_action( 'wp_head', static function () {
    if ( ! is_tax( 'guest_author' ) ) {
        return;
    }
    $term    = get_queried_object();
    $profile = array_values( array_filter( array_map( static fn( $key ) => esc_url_raw( (string) get_term_meta( $term->term_id, $key, true ) ), array( 'guest_author_linkedin', 'guest_author_twitter', 'guest_author_facebook' ) ) ) );
    $schema  = array(
        '@context'   => 'https://schema.org',
        '@type'      => 'ProfilePage',
        'url'        => get_term_link( $term ),
        'mainEntity' => array_filter( array(
            '@type'       => 'Person',
            'name'        => hr_schema_text( $term->name ),
            'url'         => get_term_link( $term ),
            'description' => hr_schema_text( get_term_meta( $term->term_id, 'guest_author_bio', true ) ),
            'image'       => esc_url_raw( (string) get_term_meta( $term->term_id, 'guest_author_avatar', true ) ),
            'sameAs'      => $profile,
        ) ),
    );
    echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP ) . "</script>\n";
}, 20 );
