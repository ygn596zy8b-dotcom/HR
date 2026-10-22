<?php
/**
 * The Headline Recorder Podcast: hr_episode episodes, the /podcast/ archive and episode pages, the iTunes feed,
 * the accessible player, the home hero, "Listen" on articles and the AI-drafted daily briefing (drafts only;
 * an editor reviews and publishes).
 *
 *   admin.php  episode meta box, "Generate today's briefing draft" button
 *   feed.php   /podcast/feed/ iTunes + Podcasting 2.0 tags
 *   ai.php     daily briefing drafts via the Claude API (official PHP SDK)
 *   retention.php  only the newest N published episodes stay on the website (older ones go to the Trash)
 */

namespace HR\Platform\Podcast;

use function HR\Platform\setting;
use function HR\Platform\asset_url;
use function HR\Platform\asset_ver;
use function HR\Platform\enqueue_base_style;
use function HR\Platform\render;

if ( ! defined( 'ABSPATH' ) ) exit;

const CPT = 'hr_episode';

require_once __DIR__ . '/retention.php';
require_once __DIR__ . '/admin.php';
require_once __DIR__ . '/feed.php';
require_once __DIR__ . '/ai.php';

function register(): void {
    register_post_type( CPT, array(
        'labels'        => array(
            'name'               => 'Episodes',
            'singular_name'      => 'Episode',
            'add_new_item'       => 'Add new episode',
            'edit_item'          => 'Edit episode',
            'new_item'           => 'New episode',
            'view_item'          => 'View episode',
            'all_items'          => 'All episodes',
            'search_items'       => 'Search episodes',
            'not_found'          => 'No episodes found.',
            'not_found_in_trash' => 'No episodes found in Trash.',
            'archives'           => 'Podcast',
        ),
        'public'        => true,
        'show_in_rest'  => true,
        'has_archive'   => 'podcast',
        'rewrite'       => array( 'slug' => 'podcast', 'with_front' => false ),
        // custom-fields: REST only exposes registered meta on types that support it.
        'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields' ),
        'menu_icon'     => 'dashicons-microphone',
        'menu_position' => 6,
    ) );

    $int  = array( 'type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 0 );
    $bool = array( 'type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean', 'default' => false );
    $meta = array(
        '_hr_audio_id'       => $int,
        '_hr_audio_url'      => array( 'type' => 'string', 'sanitize_callback' => __NAMESPACE__ . '\\sanitize_audio_url', 'default' => '' ),
        '_hr_duration'       => $int,
        '_hr_episode_number' => $int,
        '_hr_season'         => $int,
        '_hr_ai_generated'   => $bool,
        '_hr_explicit'       => $bool,
        '_hr_related_posts'  => array(
            'type'              => 'array',
            'sanitize_callback' => __NAMESPACE__ . '\\sanitize_ids',
            'default'           => array(),
            'show_in_rest'      => array( 'schema' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ) ),
        ),
    );
    foreach ( $meta as $key => $args ) {
        register_post_meta( CPT, $key, $args + array(
            'single'        => true,
            'show_in_rest'  => true,
            'auth_callback' => static fn( $allowed, $meta_key, $post_id ) => current_user_can( 'edit_post', (int) $post_id ),
        ) );
    }
}
add_action( 'init', __NAMESPACE__ . '\\register' );
add_action( 'hr_register_rewrites', __NAMESPACE__ . '\\register' );

function sanitize_audio_url( $url ): string {
    $url = is_string( $url ) ? trim( $url ) : '';
    return strlen( $url ) > 2048 ? '' : (string) esc_url_raw( $url, array( 'https' ) );
}

/** Post IDs from an array or a comma/space separated string; at most 20, no duplicates. */
function sanitize_ids( $value ): array {
    $value = is_string( $value ) ? preg_split( '/[\s,]+/', $value ) : (array) $value;
    return array_slice( array_values( array_unique( array_filter( array_map( 'intval', $value ), static fn( $id ) => $id > 0 ) ) ), 0, 20 );
}

add_filter( 'hr_settings_schema', function ( array $schema ): array {
    $schema['podcast'] = array(
        'title'       => 'Podcast',
        'description' => 'The Headline Recorder Podcast: <code>/podcast/</code>, its feed <code>/podcast/feed/</code> and the home-page section. The AI daily briefing needs <code>define( \'ANTHROPIC_API_KEY\', \'...\' );</code> in wp-config.php; it only ever creates drafts for an editor to review.',
        'fields'      => array(
            'podcast_title'       => array( 'label' => 'Show name', 'type' => 'text', 'default' => 'Podcast' ),
            'podcast_eyebrow'     => array( 'label' => 'Eyebrow', 'type' => 'text', 'default' => 'The Headline Recorder Podcast' ),
            'podcast_tagline'     => array( 'label' => 'Tagline', 'type' => 'text', 'default' => 'Pakistan’s business and economy, explained in minutes.' ),
            'podcast_description' => array(
                'label'   => 'Description',
                'type'    => 'textarea',
                'default' => 'The Headline Recorder Podcast is our AI-assisted show: each episode turns the day’s most important business, markets and policy reporting into a short audio briefing. Scripts are drafted with AI from our journalists’ reporting and reviewed by our editors before they are published.',
            ),
            'podcast_cover'       => array( 'label' => 'Cover art', 'type' => 'image', 'description' => 'Square JPEG or PNG, 1400–3000 px (Apple Podcasts requirement).' ),
            'podcast_author'      => array( 'label' => 'Author', 'type' => 'text', 'default' => 'Headline Recorder' ),
            'podcast_owner_email' => array( 'label' => 'Owner email (feed)', 'type' => 'email', 'description' => 'Published in the feed so podcast directories can verify ownership. Leave empty to omit.' ),
            'podcast_category'    => array( 'label' => 'Apple category', 'type' => 'select', 'default' => 'News', 'options' => array_combine( array_keys( APPLE_CATEGORIES ), array_keys( APPLE_CATEGORIES ) ) ),
            'podcast_subcategory' => array( 'label' => 'Apple subcategory', 'type' => 'select', 'default' => 'Business News', 'options' => array( '' => '(none)' ) + array_combine( array_merge( ...array_values( APPLE_CATEGORIES ) ), array_merge( ...array_values( APPLE_CATEGORIES ) ) ), 'description' => 'Used only when it belongs to the category above.' ),
            'podcast_language'    => array( 'label' => 'Language', 'type' => 'text', 'default' => 'en' ),
            'podcast_explicit'    => array( 'label' => 'Explicit content', 'type' => 'checkbox', 'default' => 0 ),
            'podcast_keep'        => array(
                'label'       => 'Episodes kept on the website',
                'type'        => 'number',
                'default'     => KEEP_DEFAULT,
                'sanitize'    => static fn( $v ) => '' === $v ? '' : max( KEEP_MIN, min( KEEP_MAX, (int) $v ) ),
                'description' => sprintf( 'Only the newest published episodes stay on <code>/podcast/</code>, in the podcast feed and in the sitemap (%1$d to %2$d, default %3$d). Each time an episode is published, and once a day, older published episodes are moved to the Trash; they can be restored from the Trash for %4$d days, after which WordPress deletes them together with their audio file and cover image (unless another post uses them). Drafts and AI drafts waiting for review are never touched.', KEEP_MIN, KEEP_MAX, KEEP_DEFAULT, (int) ( defined( 'EMPTY_TRASH_DAYS' ) ? EMPTY_TRASH_DAYS : 30 ) ),
            ),
            'apple_url'           => array( 'label' => 'Apple Podcasts URL', 'type' => 'url' ),
            'spotify_url'         => array( 'label' => 'Spotify URL', 'type' => 'url' ),
            'youtube_url'         => array( 'label' => 'YouTube URL', 'type' => 'url' ),
            'ai_enabled'          => array( 'label' => 'AI daily briefing', 'type' => 'checkbox', 'default' => 0, 'description' => 'Every day, draft an episode script from the last 24 hours of articles. Drafts are never published automatically.' ),
            'ai_hour'             => array( 'label' => 'Briefing hour (site time, 0–23)', 'type' => 'number', 'default' => 7, 'sanitize' => static fn( $v ) => '' === $v ? '' : max( 0, min( 23, (int) $v ) ) ),
            'ai_model'            => array( 'label' => 'Model', 'type' => 'select', 'default' => 'claude-opus-5-5', 'options' => array(
                'claude-opus-5-5'   => 'Claude Opus 5.5 (best quality)',
                'claude-sonnet-5-5' => 'Claude Sonnet 5.5',
                'claude-haiku-5-5'  => 'Claude Haiku 5.5 (fastest)',
            ) ),
        ),
    );
    return $schema;
} );

/** Apple Podcasts categories that fit a business newsroom (exact Apple spellings). */
const APPLE_CATEGORIES = array(
    'News'     => array( 'Business News', 'Daily News', 'News Commentary', 'Politics', 'Tech News' ),
    'Business' => array( 'Investing', 'Entrepreneurship', 'Management', 'Careers', 'Marketing' ),
);

// ---------------------------------------------------------------------------------------------------------------
// Episode data
// ---------------------------------------------------------------------------------------------------------------

/** Playable audio for an episode: ['url', 'type', 'length'] or [] when there is none. */
function audio( int $post_id ): array {
    $id   = (int) get_post_meta( $post_id, '_hr_audio_id', true );
    $mime = $id ? (string) get_post_mime_type( $id ) : '';
    if ( str_starts_with( $mime, 'audio/' ) && ( $url = wp_get_attachment_url( $id ) ) ) {
        $file = get_attached_file( $id );
        return array( 'url' => $url, 'type' => $mime, 'length' => $file && is_readable( $file ) ? (int) filesize( $file ) : 0 );
    }
    $url = sanitize_audio_url( (string) get_post_meta( $post_id, '_hr_audio_url', true ) );
    if ( '' === $url ) {
        return array();
    }
    // External files are never fetched server-side (no SSRF), so their byte length is unknown: the feed says 0.
    $type = (string) ( wp_check_filetype( (string) wp_parse_url( $url, PHP_URL_PATH ) )['type'] ?? '' );
    return array( 'url' => $url, 'type' => str_starts_with( $type, 'audio/' ) ? $type : 'audio/mpeg', 'length' => 0 );
}

function episode( \WP_Post $post ): array {
    return array(
        'id'       => $post->ID,
        'title'    => get_the_title( $post ),
        'url'      => (string) get_permalink( $post ),
        'date'     => get_the_date( '', $post ),
        'iso'      => get_the_date( 'c', $post ),
        'duration' => (int) get_post_meta( $post->ID, '_hr_duration', true ),
        'number'   => (int) get_post_meta( $post->ID, '_hr_episode_number', true ),
        'season'   => (int) get_post_meta( $post->ID, '_hr_season', true ),
        'ai'       => (bool) get_post_meta( $post->ID, '_hr_ai_generated', true ),
        'excerpt'  => get_the_excerpt( $post ),
        'audio'    => audio( $post->ID ),
    );
}

/** Episodes with playable audio (a Media Library file or an external URL). */
const HAS_AUDIO = array(
    'relation' => 'OR',
    array( 'key' => '_hr_audio_id', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ),
    array( 'key' => '_hr_audio_url', 'value' => '', 'compare' => '!=' ),
);

/** The newest published episode (with or without audio: without it, readers listen with the device voice). */
function latest(): ?array {
    $ids  = kept_ids();
    $post = $ids ? get_post( $ids[0] ) : null;
    return $post instanceof \WP_Post ? episode( $post ) : null;
}

/** The published episode that lists this article among its related posts, if any. */
function episode_for_post( int $post_id ): ?array {
    // Serialized arrays can't be matched exactly in SQL (index keys look like values), so confirm in PHP.
    $candidates = get_posts( array(
        'post_type'     => CPT,
        'post_status'   => 'publish',
        'numberposts'   => 10,
        'no_found_rows' => true,
        'meta_query'    => array( array( 'key' => '_hr_related_posts', 'value' => 'i:' . $post_id . ';', 'compare' => 'LIKE' ) ),
    ) );
    foreach ( $candidates as $post ) {
        if ( in_array( $post_id, array_map( 'intval', (array) get_post_meta( $post->ID, '_hr_related_posts', true ) ), true ) ) {
            $episode = episode( $post );
            if ( $episode['audio'] ) {
                return $episode;
            }
        }
    }
    return null;
}

/** 75 → "1:15", 3725 → "1:02:05". */
function clock( int $seconds ): string {
    $h = intdiv( $seconds, 3600 );
    $m = intdiv( $seconds % 3600, 60 );
    $s = $seconds % 60;
    return $h ? sprintf( '%d:%02d:%02d', $h, $m, $s ) : sprintf( '%d:%02d', $m, $s );
}

function minutes( int $seconds ): string {
    return max( 1, (int) round( $seconds / 60 ) ) . ' min';
}

function show_name(): string {
    $title = trim( (string) setting( 'podcast_title' ) );
    return '' !== $title ? $title : 'Podcast';
}

/** Subscribe links that are configured; the feed (works in every podcast app) always first. */
function subscribe_links(): array {
    $links = array( 'rss' => array( 'Follow in your podcast app', feed_url() ) );
    foreach ( array( 'apple' => 'Apple Podcasts', 'spotify' => 'Spotify', 'youtube' => 'YouTube' ) as $key => $label ) {
        $url = (string) setting( $key . '_url' );
        if ( '' !== $url ) {
            $links[ $key ] = array( $label, $url );
        }
    }
    return $links;
}

function feed_url(): string {
    return (string) get_post_type_archive_feed_link( CPT );
}

function artwork(): string {
    $cover = (int) setting( 'podcast_cover' );
    return $cover ? (string) wp_get_attachment_image_url( $cover, 'medium' ) : (string) get_site_icon_url( 512 );
}

// ---------------------------------------------------------------------------------------------------------------
// Front end
// ---------------------------------------------------------------------------------------------------------------

add_filter( 'template_include', function ( $template ) {
    if ( is_post_type_archive( CPT ) && ! locate_template( 'archive-hr_episode.php' ) ) {
        return HR_PLATFORM_DIR . '/templates/podcast/archive.php';
    }
    if ( is_singular( CPT ) && ! locate_template( 'single-hr_episode.php' ) ) {
        return HR_PLATFORM_DIR . '/templates/podcast/single.php';
    }
    return $template;
}, 20 );

// /podcast/ lists the kept episodes only, on one page: /podcast/page/2/ is a 404.
add_action( 'pre_get_posts', function ( \WP_Query $query ) {
    if ( ! is_admin() && $query->is_main_query() && $query->is_post_type_archive( CPT ) && ! $query->is_feed() ) {
        $query->set( 'posts_per_page', keep_count() );
        $query->set( 'post__in', kept_ids() ?: array( 0 ) );
        $query->set( 'orderby', array( 'date' => 'DESC', 'ID' => 'DESC' ) );
    }
} );

function is_podcast_page(): bool {
    return is_post_type_archive( CPT ) || is_singular( CPT );
}

add_action( 'wp_enqueue_scripts', function () {
    if ( ! ( is_podcast_page() || is_front_page() || is_singular( 'post' ) ) ) {
        return;
    }
    enqueue_base_style();
    wp_enqueue_style( 'hr-podcast', asset_url( 'css/podcast.css' ), array( 'hr-platform' ), asset_ver( 'css/podcast.css' ) );
    wp_enqueue_script( 'hr-podcast', asset_url( 'js/podcast.js' ), array(), asset_ver( 'js/podcast.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
} );

// Episodes may point at audio on a podcast host (https only, validated on save).
add_filter( 'hr_csp_sources', function ( array $sources ): array {
    $sources['media-src'][] = 'https:';
    return $sources;
} );

add_action( 'hr_home_after_banner', function () {
    render( 'podcast/hero', array( 'episode' => latest() ) );
} );

add_action( 'hr_single_before_content', function ( $post_id = 0 ) {
    $post_id = (int) ( $post_id ?: get_the_ID() );
    if ( 'post' !== get_post_type( $post_id ) ) {
        return;
    }
    $episode = episode_for_post( $post_id );
    $words   = str_word_count( wp_strip_all_tags( (string) get_post_field( 'post_content', $post_id ) ) );
    render( 'podcast/listen', array(
        'post_id' => $post_id,
        'episode' => $episode,
        // Device voices read at roughly 160 words a minute.
        'minutes' => $episode ? minutes( $episode['duration'] ) : max( 1, (int) ceil( $words / 160 ) ) . ' min',
    ) );
} );

add_filter( 'wp_nav_menu_items', function ( $items, $args ) {
    if ( 'aft-primary-nav' !== ( $args->theme_location ?? '' ) ) {
        return $items;
    }
    $url = (string) get_post_type_archive_link( CPT );
    if ( '' === $url || preg_match( '#href=["\'][^"\']*/podcast/?["\']#', (string) $items ) ) {
        return $items;
    }
    $current = is_podcast_page();
    return $items . sprintf(
        '<li class="menu-item menu-item-type-post_type_archive hr-nav-podcast%s"><a href="%s"%s>%s</a></li>',
        $current ? ' current-menu-item' : '',
        esc_url( $url ),
        is_post_type_archive( CPT ) ? ' aria-current="page"' : '',
        esc_html( show_name() )
    );
}, 10, 2 );
