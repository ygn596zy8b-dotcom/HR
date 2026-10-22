<?php
/**
 * Cloudflare purge on change.
 *
 * What triggers a purge (URLs are collected during the request, deduplicated, then sent once at the very end):
 *   - a post, page or podcast episode is published, updated, unpublished, trashed or deleted (old and new address);
 *   - a category or tag is created, renamed or deleted;
 *   - the signer stores an article signature (post meta _hr_pqsig*) or a signed feed snapshot (hr_pqsig_feed_*);
 *   - platform settings change (the public keyset lives there);
 *   - a site-wide change (Customizer, menus, theme switch, LiteSpeed "Purge All"): everything.
 *
 * How it is sent: after the editor's response has been delivered (fastcgi_finish_request / litespeed_finish_request),
 * otherwise from one WP-Cron event. At most 30 URLs per Cloudflare API call. Failed calls are retried with backoff
 * (1, 5, 15, 60 minutes) and then given up and reported in Settings > Headline Recorder. Nothing here can slow down
 * or break saving a post.
 *
 * Without HR_CF_ZONE_ID and HR_CF_API_TOKEN in wp-config.php nothing is queued or sent.
 */

namespace HR\Platform\Edge;

use function HR\Platform\log_security;
use function HR\Platform\rate_limit;
use function HR\Platform\same_origin_request;
use function HR\Platform\setting;

if ( ! defined( 'ABSPATH' ) ) exit;

const PURGE_BATCH     = 30;
const PURGE_HOOK      = 'hr_edge_purge';
const QUEUE_OPTION    = 'hr_edge_purge_queue';
const STATUS_OPTION   = 'hr_edge_purge_status';
const LOCK_TRANSIENT  = 'hr_edge_purge_lock';
const RETRY_DELAYS    = array( 60, 300, 900, 3600 );
const PURGE_ENDPOINT  = 'https://api.cloudflare.com/client/v4/zones/%s/purge_cache';

// ---------------------------------------------------------------------------------------------------------------
// Configuration
// ---------------------------------------------------------------------------------------------------------------

function zone_id(): string {
    $zone = defined( 'HR_CF_ZONE_ID' ) ? strtolower( trim( (string) HR_CF_ZONE_ID ) ) : '';
    return 1 === preg_match( '/^[a-f0-9]{32}$/', $zone ) ? $zone : '';
}

function api_token(): string {
    $token = defined( 'HR_CF_API_TOKEN' ) ? trim( (string) HR_CF_API_TOKEN ) : '';
    return 1 === preg_match( '/^[A-Za-z0-9_\-\.]{20,200}$/', $token ) ? $token : '';
}

function purge_configured(): bool {
    return '' !== zone_id() && '' !== api_token();
}

function purge_enabled(): bool {
    return purge_configured() && (bool) apply_filters( 'hr_edge_purge_enabled', ! empty( setting( 'edge_purge' ) ) );
}

// ---------------------------------------------------------------------------------------------------------------
// Collecting URLs during the request
// ---------------------------------------------------------------------------------------------------------------

/** Adds URLs to this request's purge set. Only http(s) addresses on this site's own host are accepted. */
function queue_urls( array $urls ): void {
    if ( ! purge_enabled() ) {
        return;
    }
    $host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
    foreach ( $urls as $url ) {
        $url = strtok( (string) $url, '#' );
        $p   = wp_parse_url( (string) $url );
        if ( ! is_array( $p ) || empty( $p['host'] ) || strtolower( $p['host'] ) !== $host || ! in_array( $p['scheme'] ?? '', array( 'http', 'https' ), true ) ) {
            continue;
        }
        $GLOBALS['hr_edge_pending']['urls'][ esc_url_raw( (string) $url ) ] = true;
    }
    ensure_shutdown();
}

function queue_everything(): void {
    if ( ! purge_enabled() ) {
        return;
    }
    $GLOBALS['hr_edge_pending']['all'] = true;
    ensure_shutdown();
}

function ensure_shutdown(): void {
    if ( ! has_action( 'shutdown', __NAMESPACE__ . '\\flush_pending' ) ) {
        // After WordPress has flushed its output buffers (priority 1) and LiteSpeed Cache has sent its headers (0).
        add_action( 'shutdown', __NAMESPACE__ . '\\flush_pending', 100 );
    }
}

/** URLs whose cached copy changes when this post changes. */
function post_urls( \WP_Post $post ): array {
    if ( ! is_post_type_viewable( $post->post_type ) || 'attachment' === $post->post_type || wp_is_post_revision( $post ) ) {
        return array();
    }
    $urls = array( home_url( '/' ), get_permalink( $post ), get_feed_link() );
    if ( 'page' === get_option( 'show_on_front' ) && ( $blog = (int) get_option( 'page_for_posts' ) ) ) {
        $urls[] = get_permalink( $blog );
    }
    foreach ( get_object_taxonomies( $post->post_type, 'objects' ) as $taxonomy ) {
        if ( ! $taxonomy->public ) {
            continue;
        }
        foreach ( (array) get_the_terms( $post, $taxonomy->name ) as $term ) {
            if ( $term instanceof \WP_Term ) {
                $urls = array_merge( $urls, term_urls( $term, 'category' === $taxonomy->name ) );
            }
        }
    }
    if ( post_type_supports( $post->post_type, 'author' ) && $post->post_author ) {
        $urls[] = get_author_posts_url( (int) $post->post_author );
    }
    if ( 'post' !== $post->post_type && get_post_type_archive_link( $post->post_type ) ) {
        $urls[] = get_post_type_archive_link( $post->post_type );        // /podcast/
        $urls[] = get_post_type_archive_feed_link( $post->post_type );   // /podcast/feed/
    }
    // Public signature files (includes/signing): the article's signature and the signed feed snapshots.
    $feed_key = 'hr_episode' === $post->post_type ? 'podcast' : 'feed';
    $urls[]   = home_url( '/signed/' . $post->ID . '.sig.json' );
    $urls[]   = home_url( '/signed/' . $feed_key . '.xml' );
    $urls[]   = home_url( '/signed/' . $feed_key . '.xml.sig.json' );
    // Sitemaps and answer-engine files list the latest posts.
    $urls     = array_merge( $urls, array( home_url( '/sitemap.xml' ), home_url( '/sitemap-news.xml' ), home_url( '/wp-sitemap.xml' ), home_url( '/llms.txt' ), home_url( '/llms-full.txt' ) ) );
    return array_values( array_unique( array_filter( (array) apply_filters( 'hr_edge_post_urls', $urls, $post ), 'is_string' ) ) );
}

/** A term's archive (first page) and, for categories, its feed. */
function term_urls( \WP_Term $term, bool $with_feed = true ): array {
    $link = get_term_link( $term );
    if ( is_wp_error( $link ) ) {
        return array();
    }
    $urls = array( $link );
    if ( $with_feed && ( $feed = get_term_feed_link( $term->term_id, $term->taxonomy ) ) ) {
        $urls[] = $feed;
    }
    return $urls;
}

// Post changes. The old address is captured before the update (slug changes, trashing adds "__trashed").
add_action( 'pre_post_update', static function ( $post_id ) {
    $post = get_post( (int) $post_id );
    if ( $post && 'publish' === $post->post_status ) {
        queue_urls( post_urls( $post ) );
    }
} );
add_action( 'wp_trash_post', static function ( $post_id ) {
    $post = get_post( (int) $post_id );
    if ( $post && 'publish' === $post->post_status ) {
        queue_urls( post_urls( $post ) );
    }
} );
add_action( 'before_delete_post', static function ( $post_id ) {
    $post = get_post( (int) $post_id );
    if ( $post && 'publish' === $post->post_status ) {
        queue_urls( post_urls( $post ) );
    }
} );
add_action( 'transition_post_status', static function ( $new, $old, $post ) {
    if ( $post instanceof \WP_Post && ( 'publish' === $new || 'publish' === $old ) ) {
        $live = 'publish' === $new ? $post : ( get_post( $post->ID ) ?: $post );
        // Unpublished posts no longer have a pretty permalink; purge as if still published.
        if ( 'publish' !== $new ) {
            $live              = clone $live;
            $live->post_status = 'publish';
        }
        queue_urls( post_urls( $live ) );
    }
}, 20, 3 );

// Terms: archive before and after a rename, plus the home page (section blocks show category names).
foreach ( array( 'edit_terms', 'pre_delete_term' ) as $hr_edge_hook ) {
    add_action( $hr_edge_hook, static function ( $term_id, $taxonomy ) {
        $term = get_term( (int) $term_id, (string) $taxonomy );
        if ( $term instanceof \WP_Term && is_taxonomy_viewable( $term->taxonomy ) ) {
            queue_urls( array_merge( array( home_url( '/' ) ), term_urls( $term ) ) );
        }
    }, 10, 2 );
}
unset( $hr_edge_hook );
foreach ( array( 'created_term', 'edited_term' ) as $hr_edge_hook ) {
    add_action( $hr_edge_hook, static function ( $term_id, $tt_id, $taxonomy ) {
        $term = get_term( (int) $term_id, (string) $taxonomy );
        if ( $term instanceof \WP_Term && is_taxonomy_viewable( $term->taxonomy ) ) {
            queue_urls( array_merge( array( home_url( '/' ) ), term_urls( $term ) ) );
        }
    }, 10, 3 );
}
unset( $hr_edge_hook );

// Signatures stored by the signer (includes/signing/rest.php): the signature file and the article (its badge).
$hr_edge_sig_meta = static function ( $meta_id, $post_id, $meta_key ) {
    if ( is_string( $meta_key ) && str_starts_with( $meta_key, '_hr_pqsig' ) && ( $post = get_post( (int) $post_id ) ) && 'publish' === $post->post_status ) {
        queue_urls( array( home_url( '/signed/' . $post->ID . '.sig.json' ), get_permalink( $post ) ) );
    }
};
foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $hr_edge_hook ) {
    add_action( $hr_edge_hook, $hr_edge_sig_meta, 10, 3 );
}
unset( $hr_edge_hook, $hr_edge_sig_meta );

// Signed feed snapshots (option hr_pqsig_feed_<key>) and the keyset (platform settings).
$hr_edge_option = static function ( $option ) {
    if ( is_string( $option ) && 1 === preg_match( '/^hr_pqsig_feed_([a-z0-9_-]{1,32})$/', $option, $m ) ) {
        queue_urls( array( home_url( '/signed/' . $m[1] . '.xml' ), home_url( '/signed/' . $m[1] . '.xml.sig.json' ) ) );
    } elseif ( 'hr_platform_settings' === $option ) {
        queue_urls( array( home_url( '/' ), home_url( '/.well-known/pqsig-keys.json' ) ) );
    }
};
add_action( 'added_option', $hr_edge_option );
add_action( 'updated_option', $hr_edge_option );
unset( $hr_edge_option );

// Site-wide changes.
foreach ( array( 'customize_save_after', 'wp_update_nav_menu', 'switch_theme', 'litespeed_purged_all' ) as $hr_edge_hook ) {
    add_action( $hr_edge_hook, __NAMESPACE__ . '\\queue_everything' );
}
unset( $hr_edge_hook );

// Code that already asks LiteSpeed Cache to purge something (signing, client logos) purges Cloudflare too.
add_action( 'litespeed_purge_url', static fn( $url ) => queue_urls( array( (string) $url ) ) );
add_action( 'litespeed_purge_post', static function ( $post_id ) {
    $post = get_post( (int) $post_id );
    if ( $post && 'publish' === $post->post_status ) {
        queue_urls( post_urls( $post ) );
    }
} );
add_action( 'litespeed_purge', static fn() => queue_urls( array( home_url( '/' ) ) ) );

// ---------------------------------------------------------------------------------------------------------------
// Sending
// ---------------------------------------------------------------------------------------------------------------

/** Shutdown: persist this request's set, then send it now (response already delivered) or from WP-Cron. */
function flush_pending(): void {
    $pending = $GLOBALS['hr_edge_pending'] ?? array();
    unset( $GLOBALS['hr_edge_pending'] );
    if ( ( empty( $pending['urls'] ) && empty( $pending['all'] ) ) || ! purge_enabled() ) {
        return;
    }
    merge_into_queue( array_keys( (array) ( $pending['urls'] ?? array() ) ), ! empty( $pending['all'] ) );

    $background = wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI );
    if ( ! $background && function_exists( 'litespeed_finish_request' ) ) {
        $background = (bool) litespeed_finish_request();
    } elseif ( ! $background && function_exists( 'fastcgi_finish_request' ) ) {
        $background = (bool) fastcgi_finish_request();
    }
    if ( $background ) {
        process_queue();
        return;
    }
    schedule_run( time() );
}

function schedule_run( int $when ): void {
    $next = wp_next_scheduled( PURGE_HOOK );
    if ( false === $next || $next > $when ) {
        if ( false !== $next ) {
            wp_unschedule_event( $next, PURGE_HOOK );
        }
        wp_schedule_single_event( $when, PURGE_HOOK );
    }
}
add_action( PURGE_HOOK, __NAMESPACE__ . '\\process_queue' );

function merge_into_queue( array $urls, bool $all, int $attempt = 0, int $not_before = 0 ): void {
    $queue = get_queue();
    $queue['all']     = $queue['all'] || $all;
    $queue['urls']    = $queue['all'] ? array() : array_values( array_unique( array_merge( $queue['urls'], $urls ) ) );
    $queue['attempt'] = max( (int) $queue['attempt'], $attempt );
    $queue['next']    = max( (int) $queue['next'], $not_before );
    update_option( QUEUE_OPTION, $queue, false );
}

function get_queue(): array {
    $queue = get_option( QUEUE_OPTION );
    $queue = is_array( $queue ) ? $queue : array();
    return array(
        'urls'    => array_values( array_filter( (array) ( $queue['urls'] ?? array() ), 'is_string' ) ),
        'all'     => ! empty( $queue['all'] ),
        'attempt' => (int) ( $queue['attempt'] ?? 0 ),
        'next'    => (int) ( $queue['next'] ?? 0 ),
    );
}

/** Sends everything that is queued. One runner at a time. */
function process_queue(): void {
    if ( ! purge_enabled() || get_transient( LOCK_TRANSIENT ) ) {
        return;
    }
    $queue = get_queue();
    if ( ! $queue['all'] && ! $queue['urls'] ) {
        return;
    }
    if ( $queue['next'] > time() ) {
        schedule_run( $queue['next'] );
        return;
    }
    set_transient( LOCK_TRANSIENT, 1, 5 * MINUTE_IN_SECONDS );
    delete_option( QUEUE_OPTION ); // claimed; anything queued from now on is a new set

    $failed = array();
    $error  = '';
    $retry  = true;
    if ( $queue['all'] ) {
        $result = send_purge( array( 'purge_everything' => true ) );
        if ( ! $result['ok'] ) {
            $error = $result['message'];
            $retry = $result['retry'];
        }
        record_status( 'everything', 0, $result );
    } else {
        $sent = 0;
        foreach ( array_chunk( $queue['urls'], PURGE_BATCH ) as $batch ) {
            if ( ! $retry ) {
                $failed = array_merge( $failed, $batch ); // a bad token or zone fails every call the same way: stop calling
                continue;
            }
            $result = send_purge( array( 'files' => array_values( $batch ) ) );
            if ( $result['ok'] ) {
                $sent += count( $batch );
                continue;
            }
            $failed = array_merge( $failed, $batch );
            $error  = $result['message'];
            $retry  = $result['retry'];
        }
        record_status( 'urls', $sent, array( 'ok' => ! $failed, 'message' => $failed ? $error : '' ) );
    }

    $attempt = $queue['attempt'] + 1;
    if ( ( $failed || ( $queue['all'] && '' !== $error ) ) && $retry && $attempt <= count( RETRY_DELAYS ) ) {
        $not_before = time() + RETRY_DELAYS[ $attempt - 1 ];
        merge_into_queue( $failed, $queue['all'], $attempt, $not_before );
        schedule_run( $not_before );
    } elseif ( $failed || ( $queue['all'] && '' !== $error ) ) {
        log_security( 'edge_purge_failed', array( 'urls' => count( $failed ), 'all' => $queue['all'], 'attempts' => $attempt, 'error' => substr( $error, 0, 120 ) ) );
    }
    delete_transient( LOCK_TRANSIENT );
}

/**
 * One Cloudflare API call. Returns array( 'ok' => bool, 'retry' => bool, 'message' => string ).
 * The token is only ever sent to api.cloudflare.com and never logged.
 */
function send_purge( array $body ): array {
    $response = wp_remote_post( sprintf( PURGE_ENDPOINT, zone_id() ), array(
        'timeout'     => 10,
        'redirection' => 0,
        'user-agent'  => 'HeadlineRecorder-Edge/1.0',
        'headers'     => array(
            'Authorization' => 'Bearer ' . api_token(),
            'Content-Type'  => 'application/json',
        ),
        'body'        => wp_json_encode( $body, JSON_UNESCAPED_SLASHES ),
    ) );
    if ( is_wp_error( $response ) ) {
        return array( 'ok' => false, 'retry' => true, 'message' => 'Network error: ' . $response->get_error_message() );
    }
    $code = (int) wp_remote_retrieve_response_code( $response );
    $json = json_decode( (string) wp_remote_retrieve_body( $response ), true );
    if ( 200 === $code && is_array( $json ) && ! empty( $json['success'] ) ) {
        return array( 'ok' => true, 'retry' => false, 'message' => '' );
    }
    $detail = is_array( $json ) && ! empty( $json['errors'][0]['message'] ) ? (string) $json['errors'][0]['message'] : 'no details';
    return array(
        'ok'      => false,
        'retry'   => 429 === $code || $code >= 500 || 0 === $code,
        'message' => sprintf( 'Cloudflare answered HTTP %d: %s', $code, sanitize_text_field( substr( $detail, 0, 200 ) ) ),
    );
}

function record_status( string $what, int $count, array $result ): void {
    update_option( STATUS_OPTION, array(
        'at'      => time(),
        'what'    => $what,
        'count'   => $count,
        'ok'      => ! empty( $result['ok'] ),
        'message' => (string) ( $result['message'] ?? '' ),
    ), false );
}

// ---------------------------------------------------------------------------------------------------------------
// "Purge everything" button (Settings > Headline Recorder > Edge cache)
// ---------------------------------------------------------------------------------------------------------------

add_action( 'admin_post_hr_edge_purge_all', static function () {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Not allowed.', 403 );
    }
    check_admin_referer( 'hr_edge_purge_all', '_hr_edge_nonce' );
    if ( ! same_origin_request() ) {
        wp_die( 'This request did not come from this site.', 403 );
    }
    $back = admin_url( 'options-general.php?page=hr-platform' );
    if ( ! purge_configured() ) {
        wp_safe_redirect( add_query_arg( 'hr_edge', 'not-configured', $back ) . '#hr-edge' );
        exit;
    }
    if ( ! rate_limit( 'edge-purge-all', (string) get_current_user_id(), 5, 10 * MINUTE_IN_SECONDS ) ) {
        wp_safe_redirect( add_query_arg( 'hr_edge', 'limited', $back ) . '#hr-edge' );
        exit;
    }
    $result = send_purge( array( 'purge_everything' => true ) );
    record_status( 'everything', 0, $result );
    if ( $result['ok'] ) {
        delete_option( QUEUE_OPTION ); // everything is fresh; queued single URLs are redundant
    }
    log_security( 'edge_purge_everything', array( 'ok' => $result['ok'] ) );
    wp_safe_redirect( add_query_arg( 'hr_edge', $result['ok'] ? 'purged' : 'failed', $back ) . '#hr-edge' );
    exit;
} );

// Status panel under the Edge cache fields; the button posts a separate form (forms cannot nest).
add_action( 'hr_settings_section_after', static function ( $section ) {
    if ( 'edge' !== $section || ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $status  = get_option( STATUS_OPTION );
    $queue   = get_queue();
    $notices = array(
        'purged'         => array( 'success', 'Cloudflare purged everything. Readers get fresh pages from now on.' ),
        'failed'         => array( 'error', 'Cloudflare did not accept the purge. See "Last purge" below.' ),
        'limited'        => array( 'warning', 'Too many purges in a short time. Wait ten minutes and try again.' ),
        'not-configured' => array( 'warning', 'Cloudflare is not connected yet (see below).' ),
    );
    $flag = isset( $_GET['hr_edge'] ) ? sanitize_key( wp_unslash( $_GET['hr_edge'] ) ) : '';
    if ( isset( $notices[ $flag ] ) ) {
        printf( '<div class="notice notice-%s inline"><p>%s</p></div>', esc_attr( $notices[ $flag ][0] ), esc_html( $notices[ $flag ][1] ) );
    }
    ?>
    <table class="widefat striped" style="max-width:760px" id="hr-edge-status">
        <tbody>
            <tr>
                <td style="width:220px">Cloudflare connection</td>
                <td>
                    <?php if ( purge_configured() ) : ?>
                        Connected (zone <?php echo esc_html( substr( zone_id(), 0, 6 ) ); ?>...). <?php echo setting( 'edge_purge' ) ? 'Changes are purged automatically.' : 'Automatic purge is switched off above.'; ?>
                    <?php else : ?>
                        Not connected. To enable automatic purging, create a Cloudflare API token with the single permission
                        <strong>Zone &gt; Cache Purge &gt; Purge</strong> for this site's zone, then add to <code>wp-config.php</code>:<br>
                        <code>define( 'HR_CF_ZONE_ID', 'zone ID from the Cloudflare Overview page' );</code><br>
                        <code>define( 'HR_CF_API_TOKEN', 'the token' );</code><br>
                        Step-by-step: docs/PERFORMANCE.md, "Connect the purge".
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td>Last purge</td>
                <td>
                    <?php
                    if ( is_array( $status ) && ! empty( $status['at'] ) ) {
                        $what = 'everything' === $status['what'] ? 'Everything' : sprintf( '%d address(es)', (int) $status['count'] );
                        printf(
                            '%s, %s ago: %s%s',
                            esc_html( $what ),
                            esc_html( human_time_diff( (int) $status['at'] ) ),
                            $status['ok'] ? 'done' : '<strong>failed</strong>',
                            $status['message'] ? ' (' . esc_html( $status['message'] ) . ')' : ''
                        );
                    } else {
                        echo 'Nothing purged yet.';
                    }
                    ?>
                </td>
            </tr>
            <tr>
                <td>Waiting to be sent</td>
                <td>
                    <?php
                    if ( $queue['all'] ) {
                        echo 'Everything';
                    } else {
                        printf( '%d address(es)', count( $queue['urls'] ) );
                    }
                    if ( $queue['attempt'] ) {
                        printf( ' (retry %d, next try in %s)', (int) $queue['attempt'], esc_html( human_time_diff( time(), max( time(), $queue['next'] ) ) ) );
                    }
                    ?>
                </td>
            </tr>
        </tbody>
    </table>
    <p>
        <button type="submit" form="hr-edge-purge-form" class="button" <?php disabled( ! purge_configured() ); ?>>Purge everything at Cloudflare</button>
        <span class="description">Use after a design change. Articles are purged automatically when they change.</span>
    </p>
    <?php
} );

add_action( 'hr_settings_page_after', static function () {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    ?>
    <form id="hr-edge-purge-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="hr_edge_purge_all">
        <?php wp_nonce_field( 'hr_edge_purge_all', '_hr_edge_nonce' ); ?>
    </form>
    <?php
} );
