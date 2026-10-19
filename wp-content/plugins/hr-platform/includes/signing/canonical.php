<?php
/**
 * Canonical article bytes and signature state.
 *
 * What gets signed is the stored article (raw post_content plus its metadata), never the_content() output: filters,
 * shortcodes, ads and lazy-loading change the rendered HTML from request to request, while the stored post is
 * byte-stable until someone edits it.
 */

namespace HR\Platform\Signing;

if ( ! defined( 'ABSPATH' ) ) exit;

/** A post that is publicly readable and therefore signed: published, type 'post', no password. */
function signable_post( $post ): ?\WP_Post {
    $post = get_post( $post );
    if ( ! $post instanceof \WP_Post || 'post' !== $post->post_type || 'publish' !== $post->post_status || '' !== $post->post_password ) {
        return null;
    }
    return $post;
}

function iso_gmt( string $mysql_gmt ): string {
    $date = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $mysql_gmt, new \DateTimeZone( 'UTC' ) );
    return $date && (int) $date->format( 'Y' ) > 1970 ? $date->format( 'Y-m-d\TH:i:s\Z' ) : '';
}

function canonical_author( \WP_Post $post ): string {
    $guest = trim( (string) get_post_meta( $post->ID, 'guest_author_name', true ) );
    if ( '' !== $guest ) {
        return $guest;
    }
    $user = get_userdata( (int) $post->post_author );
    return $user ? (string) $user->display_name : '';
}

/**
 * The exact bytes that are signed. Key order and flags are part of the format; changing either invalidates every
 * signature, so treat this as a frozen wire format (bump "v" for a new one).
 */
function canonical_bytes( \WP_Post $post ): string {
    $data = array(
        'v'         => 1,
        'url'       => (string) get_permalink( $post ),
        'title'     => html_entity_decode( (string) $post->post_title, ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
        'author'    => canonical_author( $post ),
        'published' => iso_gmt( (string) $post->post_date_gmt ),
        'modified'  => iso_gmt( (string) $post->post_modified_gmt ),
        'html'      => (string) $post->post_content,
    );
    return (string) wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
}

/** Site-relative address of a post (what the signature header's "url" holds). */
function post_path( \WP_Post $post ): string {
    return wp_make_link_relative( (string) get_permalink( $post ) );
}

function signature_url( int $post_id ): string {
    return home_url( '/signed/' . $post_id . '.sig.json' );
}

function verify_url( string $sig_path = '' ): string {
    $url = home_url( '/verify/' );
    return '' === $sig_path ? $url : add_query_arg( 'sig', rawurlencode( $sig_path ), $url );
}

/**
 * Signature state of a post for display and the public files:
 *   null for posts that are not signed at all (not public), otherwise
 *   state 'signed'   — stored signature covers the current bytes with a non-revoked key,
 *         'stale'    — a signature exists but the article (or key status) changed since,
 *         'unsigned' — never signed yet.
 */
function signature_state( int $post_id ): ?array {
    static $cache = array();
    if ( array_key_exists( $post_id, $cache ) ) {
        return $cache[ $post_id ];
    }
    $post = signable_post( $post_id );
    if ( ! $post ) {
        return $cache[ $post_id ] = null;
    }
    $stored  = (string) get_post_meta( $post_id, META_SHA, true );
    $kid     = (string) get_post_meta( $post_id, META_KID, true );
    $current = hash( 'sha512', canonical_bytes( $post ) );
    if ( '' === $stored ) {
        $state = 'unsigned';
    } else {
        $state = hash_equals( $stored, $current ) && kid_is_valid( $kid ) ? 'signed' : 'stale';
    }
    // Self-heal: a change the save hooks could not see (direct DB edit, import) still reaches the signer's queue.
    if ( 'stale' === $state && '1' !== get_post_meta( $post_id, META_STALE, true ) ) {
        update_post_meta( $post_id, META_STALE, '1' );
    }
    return $cache[ $post_id ] = array(
        'post_id' => $post_id,
        'state'   => $state,
        'kid'     => $kid,
        'at'      => (int) get_post_meta( $post_id, META_AT, true ),
        'sha512'  => $current,
    );
}

// ---------------------------------------------------------------------------------------------------------------
// Stale flag: a cheap indexed marker so the queue does not hash every post on every poll.
// ---------------------------------------------------------------------------------------------------------------

function refresh_stale_flag( int $post_id ): void {
    $stored = (string) get_post_meta( $post_id, META_SHA, true );
    if ( '' === $stored ) {
        delete_post_meta( $post_id, META_STALE ); // never signed: the queue finds it by the missing hash
        return;
    }
    $post = signable_post( $post_id );
    if ( ! $post ) {
        return; // not public any more; the public files 404 and nothing needs signing
    }
    if ( hash_equals( $stored, hash( 'sha512', canonical_bytes( $post ) ) ) ) {
        delete_post_meta( $post_id, META_STALE );
    } else {
        update_post_meta( $post_id, META_STALE, '1' );
    }
}

add_action( 'wp_after_insert_post', function ( $post_id, $post ) {
    if ( $post instanceof \WP_Post && 'post' === $post->post_type ) {
        refresh_stale_flag( (int) $post_id );
    }
}, 20, 2 );

add_action( 'transition_post_status', function ( $new_status, $old_status, $post ) {
    if ( $post instanceof \WP_Post && 'post' === $post->post_type && $new_status !== $old_status ) {
        refresh_stale_flag( (int) $post->ID );
    }
}, 20, 3 );

// The byline of an op-ed comes from this meta, which the editor may save after the post itself.
$hr_pqsig_meta_changed = function ( $meta_id, $object_id, $meta_key ) {
    if ( 'guest_author_name' === $meta_key && 'post' === get_post_type( (int) $object_id ) ) {
        refresh_stale_flag( (int) $object_id );
    }
};
add_action( 'added_post_meta', $hr_pqsig_meta_changed, 20, 3 );
add_action( 'updated_post_meta', $hr_pqsig_meta_changed, 20, 3 );
add_action( 'deleted_post_meta', $hr_pqsig_meta_changed, 20, 3 );
unset( $hr_pqsig_meta_changed );

/** Flag signed posts as needing a fresh signature; the queue re-checks the hash and clears false alarms. */
function flag_signed_posts_stale( int $author_id = 0 ): void {
    global $wpdb;
    $sql = "SELECT DISTINCT p.ID FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} h ON h.post_id = p.ID AND h.meta_key = %s
        WHERE p.post_type = 'post' AND p.post_status = 'publish'";
    $args = array( META_SHA );
    if ( $author_id > 0 ) {
        $sql   .= ' AND p.post_author = %d';
        $args[] = $author_id;
    }
    foreach ( (array) $wpdb->get_col( $wpdb->prepare( $sql, $args ) ) as $id ) {
        update_post_meta( (int) $id, META_STALE, '1' );
    }
}

// The author's display name is part of the signed bytes for staff bylines.
add_action( 'profile_update', function ( $user_id, $old_user ) {
    $new = get_userdata( (int) $user_id );
    if ( $new && $old_user instanceof \WP_User && $new->display_name !== $old_user->display_name ) {
        flag_signed_posts_stale( (int) $user_id );
    }
}, 20, 2 );

// Every signed URL changes with the permalink structure.
add_action( 'update_option_permalink_structure', function () {
    flag_signed_posts_stale();
} );

// ---------------------------------------------------------------------------------------------------------------
// Daily sweep: catches edits that bypassed WordPress hooks, so nothing stays marked "signed" when it is not.
// ---------------------------------------------------------------------------------------------------------------

function schedule_sweep(): void {
    if ( ! wp_next_scheduled( 'hr_pqsig_sweep' ) ) {
        wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'hr_pqsig_sweep' );
    }
}

add_action( 'hr_pqsig_sweep', function () {
    global $wpdb;
    $last = 0;
    for ( $round = 0; $round < 50; $round++ ) {
        $ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} h ON h.post_id = p.ID AND h.meta_key = %s
             WHERE p.post_type = 'post' AND p.post_status = 'publish' AND p.ID > %d
             ORDER BY p.ID ASC LIMIT 200",
            META_SHA,
            $last
        ) );
        if ( ! $ids ) {
            break;
        }
        update_meta_cache( 'post', $ids );
        foreach ( $ids as $id ) {
            refresh_stale_flag( (int) $id );
            $last = (int) $id;
        }
    }
} );
