<?php
/**
 * RSS feeds: keeps the site feed (/feed/) and category feeds working, announces the feeds to browsers and readers,
 * adds featured images to feed items, and gives every RSS feed a readable browser view.
 *
 *   - Yoast SEO's crawl settings on this site redirect /feed/ (and /comments/feed/) to the home page
 *     (wpseo.remove_feed_global). The site feed and category feeds are forced back on here; comment, search,
 *     tag, author and Atom/RDF feeds stay removed as configured. The podcast feed /podcast/feed/ is kept on by
 *     includes/podcast/feed.php (it is a post type feed, which the same settings remove).
 *   - <link rel="alternate" type="application/rss+xml"> for the site feed and the podcast feed on every page,
 *     printed once (core's own posts-feed link and the podcast archive link are switched off to avoid duplicates).
 *   - media:content (url, medium, type, width, height) for each item's featured image.
 *   - <?xml-stylesheet?> pointing at assets/xsl/feed.xsl: feed readers ignore it; a browser shows a branded page
 *     that explains the feed, with the link to copy and how-to steps. Feeds are sent as application/xml so that
 *     browsers display them instead of downloading them; readers and podcast apps accept either type.
 */

namespace HR\Platform\Feeds;

if ( ! defined( 'ABSPATH' ) ) exit;

const EPISODE_TYPE = 'hr_episode';

// ---------------------------------------------------------------------------------------------------------------
// Yoast SEO crawl settings: site feed and category feeds on.
// ---------------------------------------------------------------------------------------------------------------

function keep_feeds_on( $options ) {
    if ( ! is_array( $options ) ) {
        return $options;
    }
    $options['remove_feed_global']     = false;
    $options['remove_feed_categories'] = false;
    return $options;
}
add_filter( 'option_wpseo', __NAMESPACE__ . '\\keep_feeds_on', 10 );

// The RSS "before/after" lines in Yoast (Search Appearance, RSS) wrap %%BLOGLINK%% in another link, but the variable
// is already a full <a> element, so every item carried broken markup (<a href="<a href=...>">). Unwrap it.
function fix_rss_links( $titles ) {
    if ( ! is_array( $titles ) ) {
        return $titles;
    }
    foreach ( array( 'rssbefore', 'rssafter' ) as $key ) {
        if ( isset( $titles[ $key ] ) && is_string( $titles[ $key ] ) ) {
            $titles[ $key ] = (string) preg_replace( '#<a\s+href=(["\'])\s*(%%(?:BLOGLINK|POSTLINK|AUTHORLINK)%%)\s*\1[^>]*>.*?</a>#is', '$2', $titles[ $key ] );
        }
    }
    return $titles;
}
add_filter( 'option_wpseo_titles', __NAMESPACE__ . '\\fix_rss_links', 10 );

// Yoast caches its options; make sure the cache is built with the filter above (this plugin loads before Yoast,
// but a cache primed earlier, e.g. by a must-use plugin, would otherwise keep the redirect).
if ( class_exists( '\WPSEO_Options' ) ) {
    \WPSEO_Options::clear_cache();
}
add_action( 'plugins_loaded', static function () {
    if ( class_exists( '\WPSEO_Options' ) ) {
        \WPSEO_Options::clear_cache();
    }
}, 0 );

/** Is the current request a feed that gets the browser view (RSS 2.0, not a comments feed)? */
function is_styled_feed(): bool {
    return is_feed() && ! is_comment_feed() && in_array( get_query_var( 'feed' ), array( 'feed', 'rss2', '' ), true );
}

function is_podcast_feed(): bool {
    return is_feed() && post_type_exists( EPISODE_TYPE ) && is_post_type_archive( EPISODE_TYPE );
}

// ---------------------------------------------------------------------------------------------------------------
// Autodiscovery
// ---------------------------------------------------------------------------------------------------------------

add_filter( 'feed_links_show_posts_feed', '__return_false', 99 );
add_filter( 'feed_links_extra_show_post_type_archive_feed', static fn( $show ) => is_post_type_archive( EPISODE_TYPE ) ? false : $show, 99 );

function discovery_links(): array {
    $name  = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ) ?: 'Headline Recorder';
    $links = array( array( $name . ': latest news (RSS)', (string) get_feed_link() ) );
    if ( post_type_exists( EPISODE_TYPE ) ) {
        $links[] = array( 'Headline Recorder Podcast (RSS)', (string) get_post_type_archive_feed_link( EPISODE_TYPE ) );
    }
    return $links;
}

add_action( 'wp_head', function () {
    foreach ( discovery_links() as [ $title, $url ] ) {
        if ( '' !== $url ) {
            printf( '<link rel="alternate" type="application/rss+xml" title="%s" href="%s" />' . "\n", esc_attr( $title ), esc_url( $url ) );
        }
    }
}, 2 );

// ---------------------------------------------------------------------------------------------------------------
// Featured images in feed items (news feeds; the podcast feed uses itunes:image).
// ---------------------------------------------------------------------------------------------------------------

add_action( 'rss2_ns', function () {
    if ( is_feed() && ! is_comment_feed() && ! is_podcast_feed() ) {
        echo ' xmlns:media="http://search.yahoo.com/mrss/"' . "\n";
    }
}, 20 );

add_action( 'rss2_item', function () {
    if ( is_comment_feed() || is_podcast_feed() ) {
        return;
    }
    $id = (int) get_post_thumbnail_id();
    if ( ! $id ) {
        return;
    }
    // Real pixel size of the file served (wp_get_attachment_image_src() scales 'large' down to the theme's
    // content width, which would misreport it).
    $meta = wp_get_attachment_metadata( $id );
    $size = is_array( $meta['sizes']['large'] ?? null ) ? $meta['sizes']['large'] : $meta;
    $url  = (string) wp_get_attachment_image_url( $id, isset( $meta['sizes']['large'] ) ? 'large' : 'full' );
    if ( '' === $url ) {
        return;
    }
    $src  = array( $url, (int) ( $size['width'] ?? 0 ), (int) ( $size['height'] ?? 0 ) );
    $type = (string) ( $size['mime-type'] ?? get_post_mime_type( $id ) );
    printf(
        "\t\t<media:content url=\"%s\" medium=\"image\"%s%s />\n",
        esc_url( $src[0] ),
        str_starts_with( $type, 'image/' ) ? ' type="' . esc_attr( $type ) . '"' : '',
        ! empty( $src[1] ) && ! empty( $src[2] ) ? sprintf( ' width="%d" height="%d"', (int) $src[1], (int) $src[2] ) : ''
    );
} );

// ---------------------------------------------------------------------------------------------------------------
// Browser view (XSLT)
// ---------------------------------------------------------------------------------------------------------------

/**
 * Site-relative URL of the stylesheet: browsers apply XSLT from the feed's own origin only. The version is a hash of
 * the stylesheet, its CSS and its script (the XSLT passes it on to both), so it changes only when one of them
 * changes, never on a redeploy: the feed bytes, which the signing module signs, stay the same.
 */
function stylesheet_url(): string {
    static $ver = null;
    if ( null === $ver ) {
        $hash = '';
        foreach ( array( 'xsl/feed.xsl', 'css/feed.css', 'js/feed.js' ) as $file ) {
            $path  = HR_PLATFORM_DIR . '/assets/' . $file;
            $hash .= is_readable( $path ) ? (string) md5_file( $path ) : '-';
        }
        $ver = substr( md5( $hash ), 0, 12 );
    }
    return wp_make_link_relative( plugins_url( 'assets/xsl/feed.xsl', HR_PLATFORM_FILE ) ) . '?ver=' . $ver;
}

add_action( 'rss_tag_pre', function ( $context ) {
    if ( 'rss2' !== $context || ! is_styled_feed() ) {
        return;
    }
    $href = stylesheet_url();
    // A processing instruction is not entity-decoded: allow plain URL characters only (no quotes, no closing sequence).
    if ( preg_match( '#^/[A-Za-z0-9/._~%-]+\?ver=[A-Za-z0-9._%-]+$#', $href ) ) {
        echo '<?xml-stylesheet type="text/xsl" href="' . $href . '"?>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- validated above
    }
} );

// Firefox downloads application/rss+xml instead of showing it, and nothing applies the stylesheet then. Only the
// response header changes: autodiscovery links on HTML pages (which also call feed_content_type()) keep the RSS type.
add_filter( 'feed_content_type', function ( $type, $feed ) {
    return 'rss2' === $feed && ! did_action( 'wp_head' ) && is_styled_feed() ? 'application/xml' : $type;
}, 10, 2 );
