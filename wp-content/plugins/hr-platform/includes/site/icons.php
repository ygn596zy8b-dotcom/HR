<?php
/**
 * Favicon set, web app manifest and browser theme colour.
 *
 * WordPress's Site Icon (Settings → General) prints the 32/192 icons and the 180px apple-touch icon. This file makes
 * sure every size the manifest and those tags need exists (missing ones are generated with the image editor), adds a
 * maskable 512px icon on the site's black, and serves /manifest.webmanifest.
 */

namespace HR\Platform\Site;

use function HR\Platform\add_route;

if ( ! defined( 'ABSPATH' ) ) exit;

const THEME_COLOR = '#050505';
const ICON_SIZES  = array( 32, 180, 192, 512 );

// Ask WordPress to cut 512px too whenever a new site icon is chosen.
add_filter( 'site_icon_image_sizes', static fn( $sizes ) => array_values( array_unique( array_merge( (array) $sizes, ICON_SIZES ) ) ) );

add_action( 'wp_head', function () {
    printf( "<meta name=\"theme-color\" content=\"%s\">\n", esc_attr( THEME_COLOR ) );
    printf( "<link rel=\"manifest\" href=\"%s\">\n", esc_url( home_url( '/manifest.webmanifest' ) ) );
}, 98 );

/**
 * URLs of the site icon at each size plus the maskable variant, generating any that are missing.
 * Cached in an option keyed by the icon's attachment ID and file modification time.
 *
 * @return array<string,string> keys: '32', '180', '192', '512', 'maskable'
 */
function site_icons(): array {
    $id   = (int) get_option( 'site_icon' );
    $file = $id ? get_attached_file( $id ) : '';
    if ( ! $file || ! is_readable( $file ) ) {
        return array();
    }
    $sig    = $id . ':' . filemtime( $file ) . ':v1';
    $cached = get_option( 'hr_site_icons' );
    if ( is_array( $cached ) && ( $cached['sig'] ?? '' ) === $sig && ! empty( $cached['urls'] ) ) {
        return $cached['urls'];
    }

    $urls    = array();
    $changed = false;
    $meta    = wp_get_attachment_metadata( $id );
    $dir  = dirname( $file );
    $base = trailingslashit( dirname( (string) wp_get_attachment_url( $id ) ) );
    foreach ( ICON_SIZES as $size ) {
        $found = '';
        if ( (int) ( $meta['width'] ?? 0 ) === $size && (int) ( $meta['height'] ?? 0 ) === $size ) {
            $found = (string) wp_get_attachment_url( $id );
        }
        foreach ( (array) ( $meta['sizes'] ?? array() ) as $data ) {
            if ( ! $found && (int) $data['width'] === $size && (int) $data['height'] === $size && is_readable( $dir . '/' . $data['file'] ) ) {
                $found = $base . $data['file'];
            }
        }
        if ( ! $found ) {
            $found   = generate_icon_size( $id, $file, $size, $meta );
            $changed = $changed || '' !== $found;
        }
        if ( $found ) {
            $urls[ (string) $size ] = $found;
        }
    }
    if ( $changed && is_array( $meta ) ) {
        wp_update_attachment_metadata( $id, $meta );
    }
    $maskable = generate_maskable( $file );
    if ( $maskable ) {
        $urls['maskable'] = $maskable;
    }
    update_option( 'hr_site_icons', array( 'sig' => $sig, 'urls' => $urls ), false );
    return $urls;
}

/** Square resize next to the original, recorded in the attachment's metadata as site_icon-<size>. */
function generate_icon_size( int $id, string $file, int $size, &$meta ): string {
    $editor = wp_get_image_editor( $file );
    if ( is_wp_error( $editor ) ) {
        return '';
    }
    $editor->resize( $size, $size, true );
    $saved = $editor->save( $editor->generate_filename( "{$size}x{$size}" ) );
    if ( is_wp_error( $saved ) || empty( $saved['file'] ) ) {
        return '';
    }
    if ( is_array( $meta ) ) {
        $meta['sizes'][ 'site_icon-' . $size ] = array(
            'file'      => $saved['file'],
            'width'     => $size,
            'height'    => $size,
            'mime-type' => $saved['mime-type'] ?? 'image/png',
        );
    }
    return trailingslashit( dirname( (string) wp_get_attachment_url( $id ) ) ) . $saved['file'];
}

/**
 * Maskable icon: full-bleed #050505 square with the mark inside the central 80% safe zone, so Android's circle or
 * squircle masks never clip the letters. Written to uploads/hr-icons/.
 */
function generate_maskable( string $file ): string {
    if ( ! function_exists( 'imagecreatetruecolor' ) ) {
        return '';
    }
    $uploads = wp_upload_dir( null, false );
    $dir     = trailingslashit( $uploads['basedir'] ) . 'hr-icons';
    $name    = 'maskable-512-' . substr( md5( $file . filemtime( $file ) ), 0, 8 ) . '.png';
    $url     = trailingslashit( $uploads['baseurl'] ) . 'hr-icons/' . $name;
    if ( is_readable( $dir . '/' . $name ) ) {
        return $url;
    }
    if ( ! wp_mkdir_p( $dir ) || filesize( $file ) > 4 * MB_IN_BYTES ) {
        return '';
    }
    $src = @imagecreatefromstring( (string) file_get_contents( $file ) );
    if ( ! $src ) {
        return '';
    }
    if ( ! imageistruecolor( $src ) ) {
        imagepalettetotruecolor( $src );
    }
    imagealphablending( $src, false );
    imagesavealpha( $src, true );
    $dst = imagecreatetruecolor( 512, 512 );
    imagefilledrectangle( $dst, 0, 0, 511, 511, imagecolorallocate( $dst, 5, 5, 5 ) );
    imagealphablending( $dst, true );
    imagecopyresampled( $dst, $src, 51, 51, 0, 0, 410, 410, imagesx( $src ), imagesy( $src ) );
    foreach ( (array) glob( $dir . '/maskable-512-*.png' ) as $old ) {
        @unlink( $old );
    }
    $ok = imagepng( $dst, $dir . '/' . $name, 9 );
    imagedestroy( $src );
    imagedestroy( $dst );
    return $ok ? $url : '';
}

// A new site icon invalidates the cached set.
add_action( 'update_option_site_icon', static fn() => delete_option( 'hr_site_icons' ) );

add_route( 'manifest', '^manifest\.webmanifest$', function () {
    $icons    = site_icons();
    $manifest = array(
        'id'               => '/',
        'name'             => site_name(),
        'short_name'       => 'Headline Recorder',
        'description'      => html_entity_decode( get_bloginfo( 'description' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
        'lang'             => get_bloginfo( 'language' ),
        'dir'              => is_rtl() ? 'rtl' : 'ltr',
        'start_url'        => '/',
        'scope'            => '/',
        'display'          => 'minimal-ui',
        'background_color' => THEME_COLOR,
        'theme_color'      => THEME_COLOR,
        'categories'       => array( 'news', 'business' ),
        'icons'            => array(),
    );
    foreach ( array( '192', '512' ) as $size ) {
        if ( isset( $icons[ $size ] ) ) {
            $manifest['icons'][] = array( 'src' => $icons[ $size ], 'sizes' => "{$size}x{$size}", 'type' => 'image/png', 'purpose' => 'any' );
        }
    }
    if ( isset( $icons['maskable'] ) ) {
        $manifest['icons'][] = array( 'src' => $icons['maskable'], 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable' );
    }
    header( 'Content-Type: application/manifest+json; charset=utf-8' );
    header( 'Cache-Control: public, max-age=86400' );
    header( 'X-Robots-Tag: noindex' );
    echo wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
} );
