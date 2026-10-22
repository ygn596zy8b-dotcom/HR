<?php
/**
 * /podcast/feed/ (the hr_episode archive feed) as an Apple Podcasts / Podcasting 2.0 feed on top of core RSS2.
 * It lists the episodes the website keeps (see retention.php) that have playable audio: directories reject items
 * without an enclosure. The browser view (XSLT) and the main-site feed fixes live in includes/feeds/.
 */

namespace HR\Platform\Podcast;

use function HR\Platform\setting;

if ( ! defined( 'ABSPATH' ) ) exit;

function is_podcast_feed(): bool {
    return is_feed() && is_post_type_archive( CPT );
}

add_action( 'pre_get_posts', function ( \WP_Query $query ) {
    if ( $query->is_main_query() && $query->is_feed() && $query->is_post_type_archive( CPT ) ) {
        $query->set( 'meta_query', HAS_AUDIO );
        $query->set( 'post__in', kept_ids() ?: array( 0 ) );
        $query->set( 'posts_per_rss', keep_count() );
        $query->set( 'orderby', array( 'date' => 'DESC', 'ID' => 'DESC' ) );
    }
} );

// Yoast's "Remove post type feeds" crawl setting (on for this site) would redirect /podcast/feed/ to /podcast/,
// but podcast apps need this feed. Yoast reads that setting just after this, at priority -10000 on 'wp'.
add_action( 'wp', function () {
    if ( ! is_podcast_feed() ) {
        return;
    }
    if ( class_exists( '\WPSEO_Options' ) ) {
        add_filter( 'option_wpseo', static fn( $o ) => array( 'remove_feed_post_types' => false ) + (array) $o, 20 );
        \WPSEO_Options::clear_cache();
    }
    // The show cover replaces the 32 px site icon as the channel <image>.
    remove_action( 'rss2_head', 'rss2_site_icon' );
}, -10001 );

// Episode descriptions are show notes; Yoast's "originally published at" RSS lines don't belong in podcast apps.
add_filter( 'wpseo_include_rss_footer', fn( $include ) => $include && ! is_podcast_feed() );

/** "Podcast" alone is not a useful show name in a podcast app; there it is the Headline Recorder Podcast. */
function feed_title(): string {
    return 'podcast' === strtolower( show_name() ) ? 'Headline Recorder Podcast' : show_name();
}

add_filter( 'get_wp_title_rss', fn( $title ) => is_podcast_feed() ? esc_html( feed_title() ) : $title );

add_filter( 'bloginfo_rss', function ( $value, $show ) {
    if ( ! is_podcast_feed() ) {
        return $value;
    }
    if ( 'description' === $show ) {
        return esc_html( (string) setting( 'podcast_tagline' ) );
    }
    if ( 'language' === $show ) {
        return esc_html( (string) setting( 'podcast_language' ) ?: 'en' );
    }
    return $value;
}, 10, 2 );

add_action( 'rss2_ns', function () {
    if ( is_podcast_feed() ) {
        echo ' xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd" xmlns:podcast="https://podcastindex.org/namespace/1.0"' . "\n";
    }
} );

add_action( 'rss2_head', function () {
    if ( ! is_podcast_feed() ) {
        return;
    }
    $cover    = (int) setting( 'podcast_cover' );
    $image    = $cover ? (string) wp_get_attachment_image_url( $cover, 'full' ) : (string) get_site_icon_url( 512 );
    $email    = (string) setting( 'podcast_owner_email' );
    $author   = (string) setting( 'podcast_author' );
    $category = (string) setting( 'podcast_category' );
    $sub      = (string) setting( 'podcast_subcategory' );
    $category = isset( APPLE_CATEGORIES[ $category ] ) ? $category : 'News';

    printf( "\t<itunes:author>%s</itunes:author>\n", esc_html( $author ) );
    printf( "\t<itunes:summary>%s</itunes:summary>\n", esc_html( (string) setting( 'podcast_description' ) ) );
    echo "\t<itunes:type>episodic</itunes:type>\n";
    printf( "\t<itunes:explicit>%s</itunes:explicit>\n", setting( 'podcast_explicit' ) ? 'true' : 'false' );
    // The email is public in the feed, so it is only included when an owner address has been set on purpose.
    printf( "\t<itunes:owner><itunes:name>%s</itunes:name>%s</itunes:owner>\n", esc_html( $author ), $email ? '<itunes:email>' . esc_html( $email ) . '</itunes:email>' : '' );
    if ( $image ) {
        printf( "\t<itunes:image href=\"%s\"/>\n", esc_url( $image ) );
        printf( "\t<image>\n\t\t<url>%s</url>\n\t\t<title>%s</title>\n\t\t<link>%s</link>\n\t</image>\n", esc_url( $image ), esc_html( feed_title() ), esc_url( (string) get_post_type_archive_link( CPT ) ) );
    }
    if ( in_array( $sub, APPLE_CATEGORIES[ $category ], true ) ) {
        printf( "\t<itunes:category text=\"%s\"><itunes:category text=\"%s\"/></itunes:category>\n", esc_attr( $category ), esc_attr( $sub ) );
    } else {
        printf( "\t<itunes:category text=\"%s\"/>\n", esc_attr( $category ) );
    }
} );

add_action( 'rss2_item', function () {
    if ( ! is_podcast_feed() ) {
        return;
    }
    $post  = get_post();
    $audio = audio( $post->ID );
    if ( ! $audio ) {
        return;
    }
    $meta = static fn( $key ) => (int) get_post_meta( $post->ID, $key, true );
    printf( "\t\t<enclosure url=\"%s\" length=\"%d\" type=\"%s\"/>\n", esc_url( $audio['url'] ), $audio['length'], esc_attr( $audio['type'] ) );
    if ( $meta( '_hr_duration' ) ) {
        printf( "\t\t<itunes:duration>%d</itunes:duration>\n", $meta( '_hr_duration' ) );
    }
    if ( $meta( '_hr_episode_number' ) ) {
        printf( "\t\t<itunes:episode>%d</itunes:episode>\n", $meta( '_hr_episode_number' ) );
    }
    if ( $meta( '_hr_season' ) ) {
        printf( "\t\t<itunes:season>%d</itunes:season>\n", $meta( '_hr_season' ) );
    }
    echo "\t\t<itunes:episodeType>full</itunes:episodeType>\n";
    // Episode artwork only when it meets Apple's rules (square, 1400 to 3000 px); otherwise apps show the show cover.
    $thumb = (int) get_post_thumbnail_id( $post );
    $src   = $thumb ? wp_get_attachment_image_src( $thumb, 'full' ) : false;
    if ( $src && (int) $src[1] === (int) $src[2] && $src[1] >= 1400 && $src[1] <= 3000 ) {
        printf( "\t\t<itunes:image href=\"%s\"/>\n", esc_url( $src[0] ) );
    }
    printf( "\t\t<itunes:explicit>%s</itunes:explicit>\n", get_post_meta( $post->ID, '_hr_explicit', true ) ? 'true' : 'false' );
} );
