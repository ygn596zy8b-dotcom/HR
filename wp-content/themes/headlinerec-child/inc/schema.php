<?php
/**
 * NewsArticle JSON-LD for single posts and Executive Spotlight posts (MSBJ). Yoast's graph is switched off on
 * singles (functions.php), so this is the only article schema. Built as an array and printed with wp_json_encode.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** Plain text for schema values: tags stripped, HTML entities (&#8217; …) decoded. */
function hr_schema_text( $text ): string {
    return trim( html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
}

/** Publisher logo: the Customizer logo, else the site icon. */
function hr_publisher_logo(): ?array {
    $id  = (int) get_theme_mod( 'custom_logo' ) ?: (int) get_option( 'site_icon' );
    $src = $id ? wp_get_attachment_image_src( $id, 'full' ) : false;
    return $src ? array( '@type' => 'ImageObject', 'url' => $src[0], 'width' => (int) $src[1], 'height' => (int) $src[2] ) : null;
}

/**
 * @param int    $post_id
 * @param array  $author    name, url and (optional) description of the byline shown on the page.
 * @param string $scope     CSS class of the template's headline/body ("hr" → .hr-headline / .hr-body).
 */
function hr_print_article_schema( int $post_id, array $author, string $scope ) {
    $schema = array(
        '@context'            => 'https://schema.org',
        '@type'               => 'NewsArticle',
        'mainEntityOfPage'    => array( '@type' => 'WebPage', '@id' => get_permalink( $post_id ) ),
        'headline'            => hr_schema_text( get_the_title( $post_id ) ),
        'description'         => hr_schema_text( get_the_excerpt( $post_id ) ),
        'datePublished'       => get_the_date( 'c', $post_id ),
        'dateModified'        => get_the_modified_date( 'c', $post_id ),
        'image'               => array_values( array_filter( array( get_the_post_thumbnail_url( $post_id, 'full' ) ) ) ),
        'author'              => array_filter( array(
            '@type'       => 'Person',
            'name'        => hr_schema_text( $author['name'] ?? '' ),
            'url'         => $author['url'] ?? '',
            'description' => hr_schema_text( $author['description'] ?? '' ),
        ) ),
        'publisher'           => array_filter( array(
            '@type' => 'Organization',
            'name'  => 'Headline Recorder',
            'url'   => home_url( '/' ),
            'logo'  => hr_publisher_logo(),
        ) ),
        'isAccessibleForFree' => true,
        'speakable'           => array(
            '@type'       => 'SpeakableSpecification',
            'cssSelector' => array( ".{$scope}-headline", ".{$scope}-body > p:first-of-type", ".{$scope}-body > p:nth-of-type(2)" ),
        ),
    );
    $schema = array_filter( $schema, static fn( $v ) => '' !== $v && array() !== $v );
    echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP ) . "</script>\n";
}
