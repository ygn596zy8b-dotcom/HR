<?php
/**
 * Core XML sitemaps (served by "XML Sitemap & Google News", which uses the core wp-sitemap.xml server):
 *   - private types (submissions, client logos, Blockspare templates) and media pages never appear;
 *   - anything Yoast marks noindex (single pages, whole taxonomies such as tags) is left out, so Search Console
 *     never reports "submitted URL marked noindex";
 *   - personal pages (the reading dashboard, /account/…) are left out;
 *   - the podcast archive and the public /verify/ page are listed by a small "hr" provider (core lists post type
 *     archives and virtual routes nowhere). Podcast episodes are a public post type, so core lists them itself;
 *   - the Google News sitemap (/sitemap-news.xml, articles of the last 48 hours) skips the same noindex and
 *     password-protected posts;
 *   - the reading dashboard page is marked noindex in Yoast's own index too, so Yoast's robots tag and its
 *     /llms.txt page list leave it out.
 */

namespace HR\Platform\Site;

if ( ! defined( 'ABSPATH' ) ) exit;

const SITEMAP_PRIVATE_TYPES = array( 'hr_submission', 'hr_client', 'bs_templates', 'attachment' );

add_filter( 'wp_sitemaps_post_types', static function ( $types ) {
    foreach ( SITEMAP_PRIVATE_TYPES as $type ) {
        unset( $types[ $type ] );
    }
    return $types;
}, 20 );

/** Yoast's "show in search results = no" for a whole taxonomy. */
function yoast_noindex_taxonomy( string $taxonomy ): bool {
    return class_exists( '\WPSEO_Options' ) && true === (bool) \WPSEO_Options::get( 'noindex-tax-' . $taxonomy, false );
}

add_filter( 'wp_sitemaps_taxonomies', static function ( $taxonomies ) {
    foreach ( array_keys( (array) $taxonomies ) as $name ) {
        $object_types = (array) ( get_taxonomy( $name )->object_type ?? array() );
        if ( yoast_noindex_taxonomy( $name ) || ( $object_types && ! array_diff( $object_types, SITEMAP_PRIVATE_TYPES ) ) ) {
            unset( $taxonomies[ $name ] );
        }
    }
    return $taxonomies;
}, 20 );

/** Posts and pages an editor set to noindex in Yoast, plus personal pages. */
function sitemap_excluded_ids(): array {
    static $ids = null;
    if ( null !== $ids ) {
        return $ids;
    }
    global $wpdb;
    $ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s",
        '_yoast_wpseo_meta-robots-noindex',
        '1'
    ) ) );
    $ids = array_values( array_unique( array_merge( $ids, dashboard_page_ids() ) ) );
    $ids = array_map( 'intval', (array) apply_filters( 'hr_sitemap_excluded_ids', $ids ) );
    return $ids;
}

/** Pages using the personal reading dashboard template (they redirect guests to sign in). */
function dashboard_page_ids(): array {
    static $ids = null;
    if ( null === $ids ) {
        global $wpdb;
        $ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s",
            '_wp_page_template',
            'page-dashboard.php'
        ) ) );
    }
    return $ids;
}

// Google News sitemap ("XML Sitemap & Google News"): no noindex, dashboard or password-protected posts.
add_filter( 'xmlsf_news_excluded', static function ( $excluded, $post_id = 0 ) {
    $post_id = (int) $post_id;
    return $excluded || ( $post_id && ( in_array( $post_id, sitemap_excluded_ids(), true ) || post_password_required( $post_id ) ) );
}, 20, 2 );

// Yoast's index: the dashboard is personal, so its indexable carries noindex (Yoast's robots tag, its llms.txt
// page list and its internal-linking tools then all treat it as not for search engines).
add_filter( 'wpseo_should_save_indexable', static function ( $save, $indexable = null ) {
    if ( is_object( $indexable ) && 'post' === ( $indexable->object_type ?? '' ) && in_array( (int) ( $indexable->object_id ?? 0 ), dashboard_page_ids(), true ) ) {
        $indexable->is_robots_noindex = true;
    }
    return $save;
}, 20, 2 );

// Once per site: correct the dashboard indexables Yoast built before the filter above existed.
add_action( 'admin_init', static function () {
    if ( 'v1' === get_option( 'hr_site_dashboard_noindex' ) || ! function_exists( 'YoastSEO' ) ) {
        return;
    }
    update_option( 'hr_site_dashboard_noindex', 'v1', false );
    try {
        $repo = YoastSEO()->classes->get( \Yoast\WP\SEO\Repositories\Indexable_Repository::class );
        foreach ( dashboard_page_ids() as $id ) {
            $indexable = $repo->find_by_id_and_type( $id, 'post', false );
            if ( $indexable && ! $indexable->is_robots_noindex ) {
                $indexable->is_robots_noindex = true;
                $indexable->save();
            }
        }
    } catch ( \Throwable $e ) {
        // Yoast's internals changed: the filter above still applies the next time the page is saved.
    }
} );

// After the sitemap plugin (priority 10), which replaces meta_query; post__not_in is left alone by it.
add_filter( 'wp_sitemaps_posts_query_args', static function ( $args ) {
    $exclude = sitemap_excluded_ids();
    if ( $exclude ) {
        $args['post__not_in'] = array_values( array_unique( array_merge( (array) ( $args['post__not_in'] ?? array() ), $exclude ) ) );
    }
    return $args;
}, 20 );

/** Extra public URLs core does not know about. Modules can add theirs through the hr_sitemap_urls filter. */
function sitemap_extra_urls(): array {
    $urls = array();
    if ( post_type_exists( 'hr_episode' ) && get_post_type_object( 'hr_episode' )->has_archive && ! yoast_noindex_post_type_archive( 'hr_episode' ) ) {
        $latest = get_posts( array( 'post_type' => 'hr_episode', 'posts_per_page' => 1, 'orderby' => 'modified', 'no_found_rows' => true ) );
        if ( $latest ) {
            $urls[] = array( 'loc' => (string) get_post_type_archive_link( 'hr_episode' ), 'lastmod' => get_post_modified_time( DATE_W3C, true, $latest[0] ) );
        }
    }
    if ( isset( $GLOBALS['hr_platform_routes']['verify'] ) ) {
        $urls[] = array( 'loc' => home_url( '/verify/' ) );
    }
    $urls = (array) apply_filters( 'hr_sitemap_urls', $urls );
    return array_values( array_filter( $urls, static fn( $u ) => is_array( $u ) && ! empty( $u['loc'] ) && str_starts_with( (string) $u['loc'], home_url( '/' ) ) ) );
}

function yoast_noindex_post_type_archive( string $type ): bool {
    return class_exists( '\WPSEO_Options' ) && true === (bool) \WPSEO_Options::get( 'noindex-ptarchive-' . $type, false );
}

add_action( 'wp_sitemaps_init', static function ( $sitemaps ) {
    if ( ! class_exists( '\WP_Sitemaps_Provider' ) ) {
        return;
    }
    $provider = new class() extends \WP_Sitemaps_Provider {
        public function __construct() {
            $this->name        = 'hr';
            $this->object_type = 'hr';
        }
        public function get_url_list( $page_num, $object_subtype = '' ) {
            return 1 === (int) $page_num ? sitemap_extra_urls() : array();
        }
        public function get_max_num_pages( $object_subtype = '' ) {
            return sitemap_extra_urls() ? 1 : 0;
        }
    };
    $sitemaps->registry->add_provider( 'hr', $provider );
}, 20 );

// WordPress's virtual robots.txt, used only when the static deploy/webroot/robots.txt is not in the web root:
// keep internal search out of crawlers' queues and point them at the Google News sitemap too.
add_filter( 'robots_txt', static function ( $output, $public = true ) {
    if ( ! $public || ! is_string( $output ) ) {
        return $output;
    }
    if ( ! str_contains( $output, 'Disallow: /?s=' ) ) {
        $output = preg_replace( '/^(User-agent: \*\R)/m', "$1Disallow: /?s=\nDisallow: /*?s=\nDisallow: /search/\nDisallow: /verify/?\n", $output, 1 );
    }
    if ( class_exists( 'XMLSF\\Sitemap_News' ) || function_exists( 'xmlsf' ) || get_option( 'xmlsf_sitemaps' ) ) {
        $news = home_url( '/sitemap-news.xml' );
        if ( ! str_contains( $output, $news ) ) {
            $output = rtrim( $output ) . "\nSitemap: {$news}\n";
        }
    }
    return $output;
}, 20, 2 );
