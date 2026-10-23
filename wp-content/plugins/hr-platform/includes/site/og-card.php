<?php
/**
 * Branded 1200×630 share cards for posts that have no image of their own: /og/<post-id>-<version>.png
 *
 * Black background, red rule, the category in red, the headline in white (Source Sans 3 Bold, SIL OFL 1.1, see
 * fonts/OFL-SourceSans3.md) and the HR mark with the wordmark. Rendered with GD on first request, cached in
 * uploads/hr-og/, and re-rendered automatically when the post changes (the version in the URL is a hash of the
 * post's modified time and title, so social networks fetch the new card too).
 */

namespace HR\Platform\Site;

use function HR\Platform\add_route;

if ( ! defined( 'ABSPATH' ) ) exit;

const CARD_W       = 1200;
const CARD_H       = 630;
const CARD_VERSION = 1;

function card_post_types(): array {
    return array_values( array_filter( array( 'post', 'hr_episode' ), 'post_type_exists' ) );
}

function card_font(): string {
    return __DIR__ . '/fonts/SourceSans3-Bold.ttf';
}

function card_available(): bool {
    return function_exists( 'imagettftext' ) && function_exists( 'imagepng' ) && is_readable( card_font() );
}

function card_hash( \WP_Post $post ): string {
    return substr( md5( CARD_VERSION . '|' . $post->post_modified_gmt . '|' . $post->post_title . '|' . (int) get_option( 'site_icon' ) ), 0, 10 );
}

function card_url( \WP_Post $post ): string {
    return home_url( sprintf( '/og/%d-%s.png', $post->ID, card_hash( $post ) ) );
}

function card_dir(): string {
    $uploads = wp_upload_dir( null, false );
    return trailingslashit( $uploads['basedir'] ) . 'hr-og';
}

add_route( 'og-card', '^og/([0-9]+(?:-[a-f0-9]{10})?)\.png$', __NAMESPACE__ . '\\serve_card' );

function serve_card( string $sub ): void {
    [ $id, $requested ] = array_pad( explode( '-', $sub, 2 ), 2, '' );
    $post = get_post( (int) $id );
    if ( ! $post instanceof \WP_Post || 'publish' !== $post->post_status || ! in_array( $post->post_type, card_post_types(), true )
        || post_password_required( $post ) || ! card_available() ) {
        status_header( 404 );
        nocache_headers();
        header( 'Content-Type: text/plain; charset=utf-8' );
        echo 'Not found';
        return;
    }
    $file = card_file( $post );
    if ( '' === $file ) {
        status_header( 503 );
        nocache_headers();
        header( 'Content-Type: text/plain; charset=utf-8' );
        echo 'Card unavailable';
        return;
    }
    // The versioned URL never changes content, so it may be cached for a year; anything else gets a short cache.
    $current = card_hash( $post );
    header( 'Content-Type: image/png' );
    header( 'Cache-Control: ' . ( $requested === $current ? 'public, max-age=31536000, immutable' : 'public, max-age=3600' ) );
    header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', (int) filemtime( $file ) ) . ' GMT' );
    readfile( $file );
}

/** Path of the cached card for the post's current version; renders it when missing. '' on failure. */
function card_file( \WP_Post $post ): string {
    $dir  = card_dir();
    $file = sprintf( '%s/%d-%s.png', $dir, $post->ID, card_hash( $post ) );
    if ( is_readable( $file ) ) {
        return $file;
    }
    if ( ! wp_mkdir_p( $dir ) ) {
        return '';
    }
    if ( ! file_exists( $dir . '/index.php' ) ) {
        @file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" );
    }
    $image = render_card( $post );
    if ( ! $image ) {
        return '';
    }
    // Write to a temporary name and rename, so a concurrent request never serves a half-written file.
    $tmp = $file . '.' . wp_generate_password( 6, false ) . '.tmp';
    $ok  = imagepng( $image, $tmp, 6 );
    imagedestroy( $image );
    if ( ! $ok || ! @rename( $tmp, $file ) ) {
        @unlink( $tmp );
        return '';
    }
    delete_cards( $post->ID, basename( $file ) );
    return $file;
}

/** Removes cached cards for a post (all versions, or all but $keep). */
function delete_cards( int $post_id, string $keep = '' ): void {
    foreach ( (array) glob( sprintf( '%s/%d-*.png', card_dir(), $post_id ) ) as $old ) {
        if ( $old && basename( $old ) !== $keep ) {
            @unlink( $old );
        }
    }
}
add_action( 'save_post', static fn( $post_id ) => delete_cards( (int) $post_id ) );
add_action( 'deleted_post', static fn( $post_id ) => delete_cards( (int) $post_id ) );

/** Eyebrow label: the primary category (Yoast), else the first category; "Podcast" for episodes. */
function card_label( \WP_Post $post ): string {
    if ( 'hr_episode' === $post->post_type ) {
        return 'Podcast';
    }
    $primary = (int) get_post_meta( $post->ID, '_yoast_wpseo_primary_category', true );
    $term    = $primary ? get_term( $primary, 'category' ) : null;
    if ( ! $term instanceof \WP_Term ) {
        $cats = get_the_category( $post->ID );
        $term = $cats[0] ?? null;
    }
    return $term instanceof \WP_Term && 'uncategorized' !== $term->slug ? html_entity_decode( $term->name, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) : 'News';
}

/** Width in pixels of $text at $size points, with $spacing extra pixels after each character. */
function text_width( float $size, string $text, float $spacing = 0 ): float {
    $box = imagettfbbox( $size, 0, card_font(), $text );
    $w   = $box ? abs( $box[2] - $box[0] ) : 0;
    return $w + $spacing * max( 0, mb_strlen( $text ) - 1 );
}

/** Draws text with letter-spacing (GD has none) and returns the x after the last character. */
function draw_text( $image, float $size, int $x, int $y, $color, string $text, float $spacing = 0 ): int {
    if ( 0.0 === (float) $spacing ) {
        $box = imagettftext( $image, $size, 0, $x, $y, $color, card_font(), $text );
        return $box ? (int) $box[2] : $x;
    }
    $cx = (float) $x;
    foreach ( mb_str_split( $text ) as $char ) {
        imagettftext( $image, $size, 0, (int) round( $cx ), $y, $color, card_font(), $char );
        $cx += text_width( $size, $char ) + $spacing;
    }
    return (int) round( $cx - $spacing );
}

/** Greedy word wrap to $max_width. Returns lines, or null when a single word is wider than the line. */
function wrap_words( string $text, float $size, int $max_width ): ?array {
    $lines = array();
    $line  = '';
    foreach ( preg_split( '/\s+/u', $text ) as $word ) {
        $try = '' === $line ? $word : $line . ' ' . $word;
        if ( text_width( $size, $try ) <= $max_width ) {
            $line = $try;
            continue;
        }
        if ( '' === $line ) {
            return null;
        }
        $lines[] = $line;
        $line    = $word;
        if ( text_width( $size, $line ) > $max_width ) {
            return null;
        }
    }
    if ( '' !== $line ) {
        $lines[] = $line;
    }
    return $lines;
}

/** @return \GdImage|false */
function render_card( \WP_Post $post ) {
    $im = imagecreatetruecolor( CARD_W, CARD_H );
    if ( ! $im ) {
        return false;
    }
    imagealphablending( $im, true );
    $black = imagecolorallocate( $im, 5, 5, 5 );          // --hr-black #050505
    $line  = imagecolorallocate( $im, 26, 26, 30 );       // --hr-line-dark #1a1a1e
    $white = imagecolorallocate( $im, 255, 255, 255 );
    $red   = imagecolorallocate( $im, 255, 0, 28 );       // --hr-red #ff001c
    $muted = imagecolorallocate( $im, 161, 161, 168 );    // --hr-muted-dark #a1a1a8
    imagefilledrectangle( $im, 0, 0, CARD_W - 1, CARD_H - 1, $black );

    // Soft red glow in the top-right corner, as on the site's dark heroes (concentric translucent circles).
    for ( $r = 520; $r > 0; $r -= 20 ) {
        $alpha = (int) min( 127, 112 + ( 15 * $r / 520 ) );
        $glow  = imagecolorallocatealpha( $im, 192, 0, 26, $alpha );
        imagefilledellipse( $im, CARD_W - 120, 60, $r * 2, $r * 2, $glow );
    }

    $pad = 80;
    imagefilledrectangle( $im, 0, 0, CARD_W - 1, 9, $red ); // red rule across the top

    // Eyebrow: short red rule + category in spaced capitals.
    imagefilledrectangle( $im, $pad, 104, $pad + 31, 106, $red );
    draw_text( $im, 15, $pad + 46, 113, $red, mb_strtoupper( card_label( $post ) ), 4 );

    // Headline: the largest size (from 50pt down to 32pt) that fits in five lines.
    $title  = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( get_the_title( $post ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
    $title  = '' !== $title ? $title : site_name();
    $width  = CARD_W - 2 * $pad;
    $area   = 330;
    $lines  = null;
    $size   = 50;
    foreach ( array( 50, 46, 42, 38, 35, 32 ) as $size ) {
        $lines = wrap_words( $title, $size, $width );
        if ( $lines && count( $lines ) * $size * 1.62 <= $area ) {
            break;
        }
    }
    if ( ! $lines ) {
        $lines = array( $title );
    }
    $line_h = (int) round( $size * 1.62 );
    $max    = max( 1, (int) floor( $area / $line_h ) );
    if ( count( $lines ) > $max ) {
        $lines = array_slice( $lines, 0, $max );
        $last  = $lines[ $max - 1 ];
        while ( '' !== $last && text_width( $size, $last . '…' ) > $width ) {
            $last = preg_replace( '/\s*\S+$/u', '', $last );
        }
        $lines[ $max - 1 ] = rtrim( $last, " ,;:-\u{2013}\u{2014}" ) . '…';
    }
    $y = 168 + (int) round( $size * 1.33 );
    foreach ( $lines as $text ) {
        imagettftext( $im, $size, 0, $pad, $y, $white, card_font(), $text );
        $y += $line_h;
    }

    // Footer: hairline, HR mark, wordmark, date.
    imagefilledrectangle( $im, $pad, 528, CARD_W - $pad - 1, 528, $line );
    $x    = $pad;
    $icon = (int) get_option( 'site_icon' );
    $path = $icon ? get_attached_file( $icon ) : '';
    if ( $path && is_readable( $path ) && filesize( $path ) < 4 * MB_IN_BYTES ) {
        $src = @imagecreatefromstring( (string) file_get_contents( $path ) );
        if ( $src ) {
            if ( ! imageistruecolor( $src ) ) {
                imagepalettetotruecolor( $src );
            }
            imagealphablending( $src, false );
            imagesavealpha( $src, true );
            imagecopyresampled( $im, $src, $x, 552, 0, 0, 52, 52, imagesx( $src ), imagesy( $src ) );
            imagedestroy( $src );
            $x += 52 + 18;
        }
    }
    $after = draw_text( $im, 17, $x, 588, $white, 'HEADLINE', 3 );
    draw_text( $im, 17, $after + 11, 588, $red, 'RECORDER', 3 );

    $date = mb_strtoupper( wp_date( 'j M Y', strtotime( $post->post_date_gmt . ' UTC' ) ) );
    draw_text( $im, 14, (int) ( CARD_W - $pad - text_width( 14, $date, 2 ) ), 586, $muted, $date, 2 );

    return $im;
}
