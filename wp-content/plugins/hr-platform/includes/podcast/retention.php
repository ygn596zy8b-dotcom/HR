<?php
/**
 * Episode retention: the website keeps only the newest N published episodes (setting podcast_keep, default 15).
 *
 *   - Whenever an episode is published, and once a day by WP-Cron as a safety net, every published episode older
 *     than the newest N is moved to the Trash. WordPress empties the Trash after EMPTY_TRASH_DAYS (30 by default),
 *     so an editor can restore an episode until then.
 *   - Drafts, pending, scheduled and AI drafts awaiting review are never touched: only status "publish" counts.
 *   - When an episode is deleted permanently (by hand or when the Trash is emptied), its audio file and cover image
 *     are deleted too, unless another post, episode or setting still uses them.
 *   - Until retention has run, queries (archive, feed, sitemaps) are limited to the kept episodes anyway.
 */

namespace HR\Platform\Podcast;

use function HR\Platform\setting;

if ( ! defined( 'ABSPATH' ) ) exit;

const RETENTION_CRON = 'hr_podcast_retention_daily';
const KEEP_DEFAULT   = 15;
const KEEP_MIN       = 5;
const KEEP_MAX       = 100;

function keep_count(): int {
    $keep = (int) setting( 'podcast_keep' );
    return $keep ? max( KEEP_MIN, min( KEEP_MAX, $keep ) ) : KEEP_DEFAULT;
}

/**
 * IDs of the published episodes the website keeps, newest first. Cached per request; the cache is dropped whenever
 * an episode changes status.
 */
function kept_ids(): array {
    static $cache = null, $version = -1;
    $current = (int) ( $GLOBALS['hr_podcast_kept_version'] ?? 0 );
    if ( null !== $cache && $version === $current ) {
        return $cache;
    }
    $version = $current;
    $cache   = array_map( 'intval', get_posts( array(
        'post_type'        => CPT,
        'post_status'      => 'publish',
        'numberposts'      => keep_count(),
        'orderby'          => array( 'date' => 'DESC', 'ID' => 'DESC' ),
        'fields'           => 'ids',
        'no_found_rows'    => true,
        'suppress_filters' => true,
    ) ) );
    return $cache;
}

function kept_changed(): void {
    $GLOBALS['hr_podcast_kept_version'] = (int) ( $GLOBALS['hr_podcast_kept_version'] ?? 0 ) + 1;
}

/** Published episodes beyond the newest N (the ones retention takes off the website). */
function expired_ids( int $limit = 200 ): array {
    global $wpdb;
    $ids = $wpdb->get_col( $wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' ORDER BY post_date DESC, ID DESC LIMIT %d, %d",
        CPT,
        keep_count(),
        $limit
    ) );
    return array_map( 'intval', (array) $ids );
}

/** Moves every published episode older than the newest N to the Trash. Returns the trashed IDs. */
function enforce_retention(): array {
    // One run at a time (publish hook and cron can overlap); a crashed run frees the lock after a minute.
    if ( ! add_option( 'hr_podcast_retention_lock', time(), '', false ) ) {
        if ( time() - (int) get_option( 'hr_podcast_retention_lock' ) < MINUTE_IN_SECONDS ) {
            return array();
        }
        update_option( 'hr_podcast_retention_lock', time(), false );
    }
    $trashed = array();
    try {
        foreach ( expired_ids() as $id ) {
            if ( 'publish' === get_post_status( $id ) && wp_trash_post( $id ) ) {
                $trashed[] = $id;
            }
        }
    } finally {
        delete_option( 'hr_podcast_retention_lock' );
    }
    if ( $trashed ) {
        kept_changed();
        error_log( sprintf( 'hr-podcast: retention moved %d episode(s) to the Trash (keeping the newest %d): %s', count( $trashed ), keep_count(), implode( ', ', $trashed ) ) );
        do_action( 'hr_podcast_retention_trashed', $trashed );
    }
    return $trashed;
}

// Publishing an episode (editor, REST, scheduled post going live, restore from Trash): enforce once the post and
// its meta are saved. wp_after_insert_post fires after the meta box and REST meta are stored.
add_action( 'transition_post_status', function ( $new, $old, $post ) {
    if ( $post instanceof \WP_Post && CPT === $post->post_type && $new !== $old ) {
        kept_changed();
        if ( 'publish' === $new ) {
            $GLOBALS['hr_podcast_enforce'] = true;
        }
    }
}, 10, 3 );

add_action( 'wp_after_insert_post', function ( $post_id, $post ) {
    if ( empty( $GLOBALS['hr_podcast_enforce'] ) || ! ( $post instanceof \WP_Post ) || CPT !== $post->post_type ) {
        return;
    }
    $GLOBALS['hr_podcast_enforce'] = false;
    enforce_retention();
}, 20, 2 );

// Daily safety net (e.g. episodes published straight into the database or by an import).
add_action( 'init', function () {
    if ( ! wp_next_scheduled( RETENTION_CRON ) ) {
        wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', RETENTION_CRON );
    }
}, 25 );

add_action( RETENTION_CRON, function () {
    do_action( 'hr_monitor_cron', 'podcast-retention', 'in_progress', '@daily' );
    enforce_retention();
    do_action( 'hr_monitor_cron', 'podcast-retention', 'ok', '@daily' );
} );

// ---------------------------------------------------------------------------------------------------------------
// Permanent deletion: remove the episode's audio and cover when nothing else uses them.
// ---------------------------------------------------------------------------------------------------------------

add_action( 'before_delete_post', function ( $post_id, $post = null ) {
    $post = $post instanceof \WP_Post ? $post : get_post( $post_id );
    if ( ! $post || CPT !== $post->post_type ) {
        return;
    }
    $ids = array_filter( array(
        (int) get_post_meta( $post->ID, '_hr_audio_id', true ),
        (int) get_post_meta( $post->ID, '_thumbnail_id', true ),
    ) );
    if ( $ids ) {
        $GLOBALS['hr_podcast_orphans'][ $post->ID ] = array_values( array_unique( $ids ) );
    }
}, 10, 2 );

add_action( 'deleted_post', function ( $post_id ) {
    $ids = $GLOBALS['hr_podcast_orphans'][ $post_id ] ?? array();
    unset( $GLOBALS['hr_podcast_orphans'][ $post_id ] );
    foreach ( $ids as $attachment_id ) {
        if ( 'attachment' === get_post_type( $attachment_id ) && ! attachment_in_use( $attachment_id, (int) $post_id ) ) {
            wp_delete_attachment( $attachment_id, true );
        }
    }
} );

/** True when any other post, episode, page or setting still refers to this attachment. */
function attachment_in_use( int $attachment_id, int $deleted_post_id ): bool {
    global $wpdb;
    // Featured image or episode audio of any other post (any status, revisions included).
    $meta = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key IN ('_thumbnail_id', '_hr_audio_id') AND meta_value = %s AND post_id <> %d",
        (string) $attachment_id,
        $deleted_post_id
    ) );
    if ( $meta ) {
        return true;
    }
    // Show cover, site icon, logo.
    if ( in_array( $attachment_id, array( (int) setting( 'podcast_cover' ), (int) get_option( 'site_icon' ), (int) get_theme_mod( 'custom_logo' ) ), true ) ) {
        return true;
    }
    // Embedded in any other post's content (image block class or the file name of the upload).
    $file    = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
    $needles = array( 'wp-image-' . $attachment_id . '"', 'wp-image-' . $attachment_id . ' ', '"id":' . $attachment_id . ',', '"id":' . $attachment_id . '}' );
    if ( '' !== $file ) {
        $needles[] = $file;
    }
    foreach ( $needles as $needle ) {
        $used = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE ID <> %d AND post_type NOT IN ('attachment', 'revision') AND post_content LIKE %s",
            $deleted_post_id,
            '%' . $wpdb->esc_like( $needle ) . '%'
        ) );
        if ( $used ) {
            return true;
        }
    }
    return false;
}

// ---------------------------------------------------------------------------------------------------------------
// Sitemaps list kept episodes only (core sitemaps and Yoast).
// ---------------------------------------------------------------------------------------------------------------

add_filter( 'wp_sitemaps_posts_query_args', function ( $args, $post_type ) {
    if ( CPT === $post_type ) {
        $args['post__in'] = kept_ids() ?: array( 0 );
    }
    return $args;
}, 10, 2 );

add_filter( 'wpseo_exclude_from_sitemap_by_post_ids', function ( $ids ) {
    return array_values( array_unique( array_merge( array_map( 'intval', (array) $ids ), expired_ids() ) ) );
} );
