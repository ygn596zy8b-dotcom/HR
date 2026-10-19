<?php
/**
 * Alternative text everywhere an image is printed.
 *
 * Fallback order when an image has no alt: the attachment's alt text → its caption → its title (unless the title is
 * just a file name) → the title of the post it belongs to. Decorative images (role="presentation"/"none" or
 * aria-hidden) and anything inside <noscript> are left alone.
 *
 * Settings → Headline Recorder also offers a one-time "Fill missing alt text" tool that stores the fallback in the
 * media library, so the text is editable there afterwards.
 */

namespace HR\Platform\Site;

if ( ! defined( 'ABSPATH' ) ) exit;

/** Post ID whose featured image is being printed right now (set around get_the_post_thumbnail()). */
$GLOBALS['hr_site_thumbnail_post'] = 0;
add_action( 'begin_fetch_post_thumbnail_html', static function ( $post_id ) {
    $GLOBALS['hr_site_thumbnail_post'] = (int) $post_id;
} );
add_action( 'end_fetch_post_thumbnail_html', static function () {
    $GLOBALS['hr_site_thumbnail_post'] = 0;
} );

/** True for titles WordPress derived from a file name, e.g. "HR-Website-temp-From-now-on-3-4" or "IMG_1234". */
function looks_like_filename( string $title, int $attachment_id ): bool {
    $norm = strtolower( trim( $title ) );
    if ( '' === $norm ) {
        return true;
    }
    $stem = strtolower( pathinfo( (string) get_post_meta( $attachment_id, '_wp_attached_file', true ), PATHINFO_FILENAME ) );
    $stem = (string) preg_replace( '/-(?:\d+x\d+|scaled|rotated|e\d{10,})$/', '', $stem );
    if ( $norm === $stem || str_replace( array( '-', '_' ), ' ', $stem ) === $norm ) {
        return true;
    }
    // One "word" glued together with dashes, underscores, dots or digits.
    return ! str_contains( $norm, ' ' ) && (bool) preg_match( '/[-_.\d]/', $norm );
}

function clean_alt( string $text ): string {
    $text = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
    return mb_strlen( $text ) > 250 ? shorten( $text, 250 ) : $text;
}

/**
 * Best available alt text for an attachment ('' if nothing sensible exists).
 * $context_post is the post the image appears in (featured image or body) when known.
 */
function fallback_alt( int $attachment_id, int $context_post = 0 ): string {
    $attachment = get_post( $attachment_id );
    if ( ! $attachment instanceof \WP_Post || 'attachment' !== $attachment->post_type ) {
        return '';
    }
    $candidates = array(
        (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
        $attachment->post_excerpt, // caption
    );
    if ( ! looks_like_filename( $attachment->post_title, $attachment_id ) ) {
        $candidates[] = $attachment->post_title;
    }
    foreach ( array_unique( array_filter( array( $context_post, (int) $attachment->post_parent ) ) ) as $post_id ) {
        if ( 'attachment' !== get_post_type( $post_id ) ) {
            $candidates[] = get_the_title( $post_id );
        }
    }
    foreach ( $candidates as $candidate ) {
        $candidate = clean_alt( (string) $candidate );
        if ( '' !== $candidate ) {
            return $candidate;
        }
    }
    return '';
}

function is_decorative( array $attr ): bool {
    $role = strtolower( trim( (string) ( $attr['role'] ?? '' ) ) );
    return in_array( $role, array( 'presentation', 'none' ), true ) || 'true' === strtolower( (string) ( $attr['aria-hidden'] ?? '' ) );
}

// wp_get_attachment_image(), the_post_thumbnail() and everything built on them.
add_filter( 'wp_get_attachment_image_attributes', function ( $attr, $attachment ) {
    if ( ! is_array( $attr ) || ! $attachment instanceof \WP_Post || is_decorative( $attr ) || '' !== trim( (string) ( $attr['alt'] ?? '' ) ) ) {
        return $attr;
    }
    $context = (int) $GLOBALS['hr_site_thumbnail_post'] ?: ( in_the_loop() ? (int) get_the_ID() : 0 );
    $alt     = fallback_alt( $attachment->ID, $context );
    if ( '' !== $alt ) {
        $attr['alt'] = $alt;
    }
    return $attr;
}, 20, 2 );

// Images inside post content (block and classic markup) that carry a wp-image-<ID> class.
add_filter( 'the_content', __NAMESPACE__ . '\\fill_content_alts', 20 );

function fill_content_alts( $content ) {
    if ( ! is_string( $content ) || false === stripos( $content, '<img' ) || ! class_exists( '\WP_HTML_Tag_Processor' ) ) {
        return $content;
    }
    $tags     = new \WP_HTML_Tag_Processor( $content );
    $noscript = 0;
    $changed  = false;
    $context  = in_the_loop() || is_singular() ? (int) get_the_ID() : 0;
    while ( $tags->next_tag() ) {
        $name = $tags->get_tag();
        if ( 'NOSCRIPT' === $name ) {
            $noscript += $tags->is_tag_closer() ? -1 : 1;
            $noscript  = max( 0, $noscript );
            continue;
        }
        if ( 'IMG' !== $name || $noscript > 0 ) {
            continue;
        }
        $alt = $tags->get_attribute( 'alt' );
        if ( is_string( $alt ) && '' !== trim( $alt ) ) {
            continue;
        }
        if ( is_decorative( array( 'role' => (string) $tags->get_attribute( 'role' ), 'aria-hidden' => (string) $tags->get_attribute( 'aria-hidden' ) ) ) ) {
            continue;
        }
        if ( ! preg_match( '/(?:^|\s)wp-image-(\d+)(?:\s|$)/', (string) $tags->get_attribute( 'class' ), $m ) ) {
            continue;
        }
        $fallback = fallback_alt( (int) $m[1], $context );
        if ( '' !== $fallback ) {
            $tags->set_attribute( 'alt', $fallback );
            $changed = true;
        }
    }
    return $changed ? $tags->get_updated_html() : $content;
}

// ---------------------------------------------------------------------------------------------------------------
// One-time tool: write the fallback into the media library for images that have no alt text.
// ---------------------------------------------------------------------------------------------------------------

/** IDs of image attachments without alt text. */
function attachments_missing_alt(): array {
    global $wpdb;
    return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->posts} p
         LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s
         WHERE p.post_type = 'attachment' AND p.post_mime_type LIKE %s AND ( m.meta_value IS NULL OR TRIM( m.meta_value ) = '' )
         ORDER BY p.ID",
        '_wp_attachment_image_alt',
        $wpdb->esc_like( 'image/' ) . '%'
    ) ) );
}

/** First published post using the attachment as its featured image (gives context to unattached images). */
function post_using_thumbnail( int $attachment_id ): int {
    global $wpdb;
    return (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT m.post_id FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id
         WHERE m.meta_key = '_thumbnail_id' AND m.meta_value = %s AND p.post_status = 'publish' ORDER BY m.post_id LIMIT 1",
        (string) $attachment_id
    ) );
}

add_action( 'hr_settings_page_after', function () {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $missing = count( attachments_missing_alt() );
    ?>
    <h2 id="hr-alt-text">Image alt text</h2>
    <p>
        <?php
        echo esc_html( 0 === $missing
            ? 'Every image in the media library has alt text.'
            : sprintf( _n( '%s image in the media library has no alt text.', '%s images in the media library have no alt text.', $missing, 'hr-platform' ), number_format_i18n( $missing ) ) );
        ?>
        Pages already show a fallback (caption, image title or the article’s headline) to screen readers and search engines.
        This tool stores that fallback in the media library so editors can refine it.
    </p>
    <?php if ( $missing ) : ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="hr_fill_alt">
            <?php wp_nonce_field( 'hr_fill_alt' ); ?>
            <?php submit_button( 'Fill missing alt text', 'secondary', 'submit', false ); ?>
        </form>
    <?php endif; ?>
    <?php
} );

add_action( 'admin_post_hr_fill_alt', function () {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'You are not allowed to do this.', 403 );
    }
    check_admin_referer( 'hr_fill_alt' );
    $filled = 0;
    $left   = 0;
    foreach ( attachments_missing_alt() as $id ) {
        $alt = fallback_alt( $id, post_using_thumbnail( $id ) );
        if ( '' === $alt ) {
            $left++;
            continue;
        }
        update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
        $filled++;
    }
    wp_safe_redirect( add_query_arg( array( 'page' => 'hr-platform', 'hr_alt_filled' => $filled, 'hr_alt_left' => $left ), admin_url( 'options-general.php' ) ) . '#hr-alt-text' );
    exit;
} );

add_action( 'admin_notices', function () {
    if ( ! isset( $_GET['hr_alt_filled'] ) || ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $filled = absint( $_GET['hr_alt_filled'] );
    $left   = absint( $_GET['hr_alt_left'] ?? 0 );
    printf(
        '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
        esc_html( sprintf( 'Alt text added to %d image(s).%s', $filled, $left ? sprintf( ' %d image(s) had nothing to go on — add their alt text in the media library.', $left ) : '' ) )
    );
} );
