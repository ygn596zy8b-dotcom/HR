<?php
/**
 * Submissions: sealed envelopes on disk. One JSON file per submission with a random name, in a directory that is
 * never served: HR_PRIVATE_DIR, else hr-private/ next to the WordPress folder, else wp-content/uploads/hr-sealed/
 * with deny rules (see storage_candidates()).
 */

namespace HR\Platform\Submissions;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Where envelopes may live, newest choice first. Every path is built from code, never from the database:
 * HR_PRIVATE_DIR/submissions (the owner's explicit choice), else <parent of the WordPress folder>/hr-private/submissions
 * (outside the web root on cPanel, next to the signing key), else wp-content/uploads/hr-sealed/ with deny rules
 * (the only option on hosts that forbid writing above the web root, and where earlier versions stored them).
 * $for_read adds the older folders, so envelopes saved before a folder change are still found where they were written.
 */
function storage_candidates( bool $for_read = false ): array {
    $legacy = wp_upload_dir( null, false )['basedir'] . '/hr-sealed';
    if ( defined( 'HR_PRIVATE_DIR' ) && is_string( HR_PRIVATE_DIR ) && '' !== HR_PRIVATE_DIR ) {
        $out = array( rtrim( HR_PRIVATE_DIR, '/\\' ) . '/submissions' ); // never silently skipped
    } else {
        $parent = rtrim( dirname( rtrim( ABSPATH, '/\\' ) ), '/\\' );
        $out    = is_served( $parent ) ? array() : array( $parent . '/hr-private/submissions' );
        $out[]  = $legacy;
    }
    return $for_read ? array_values( array_unique( array_merge( $out, array( $legacy ) ) ) ) : $out;
}

/** Is this folder the WordPress folder, the document root, or inside either? */
function is_served( string $dir ): bool {
    $real = @realpath( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- open_basedir warnings
    $real = $real ? $real : $dir;
    foreach ( array( ABSPATH, $_SERVER['DOCUMENT_ROOT'] ?? '' ) as $root ) {
        $root = '' !== (string) $root ? realpath( (string) $root ) : false;
        if ( $root && ( $real === $root || str_starts_with( $real . DIRECTORY_SEPARATOR, rtrim( $root, '/\\' ) . DIRECTORY_SEPARATOR ) ) ) {
            return true;
        }
    }
    return false;
}

/** The directory new envelopes go to (created and locked down on first use), or '' when none can be created. */
function storage_dir(): string {
    static $dir = null;
    if ( null !== $dir ) {
        return $dir;
    }
    foreach ( storage_candidates() as $candidate ) {
        if ( prepare_dir( $candidate ) ) {
            return $dir = $candidate;
        }
    }
    return $dir = '';
}

/** Creates the folder (0750) with deny-all guards for any web server that could still reach it. Quiet under open_basedir. */
function prepare_dir( string $dir ): bool {
    if ( ! @is_dir( $dir ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors -- open_basedir warnings
        if ( ! @wp_mkdir_p( $dir ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
            return false;
        }
        @chmod( $dir, 0750 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
    }
    if ( ! @is_writable( $dir ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
        return false;
    }
    $guards = array(
        '.htaccess'  => "# Sealed submissions are never served.\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n",
        'index.php'  => "<?php\n// Silence.\n",
        'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n",
    );
    foreach ( $guards as $name => $body ) {
        if ( ! file_exists( "{$dir}/{$name}" ) ) {
            @file_put_contents( "{$dir}/{$name}", $body ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- best effort
        }
    }
    return true;
}

/** Writes an envelope; returns its file name (32 hex + .json) or '' on failure. */
function write_envelope( string $json ): string {
    $dir = storage_dir();
    if ( '' === $dir ) {
        return '';
    }
    $name = bin2hex( random_bytes( 16 ) ) . '.json';
    $file = "{$dir}/{$name}";
    if ( strlen( $json ) !== file_put_contents( $file, $json, LOCK_EX ) ) {
        @unlink( $file );
        return '';
    }
    chmod( $file, 0640 );
    return $name;
}

/** Absolute path of a stored envelope, only if the name is well-formed and the real path stays inside a storage folder. */
function envelope_path( string $name ): string {
    if ( ! preg_match( '/^[0-9a-f]{32}\.json$/', $name ) ) {
        return '';
    }
    foreach ( storage_candidates( true ) as $dir ) {
        $base = @realpath( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- open_basedir warnings
        $real = $base ? realpath( "{$base}/{$name}" ) : false;
        if ( $real && str_starts_with( $real, $base . DIRECTORY_SEPARATOR ) && is_file( $real ) ) {
            return $real;
        }
    }
    return '';
}

// Withdrawn, erased or permanently deleted submissions take their envelope with them.
add_action( 'before_delete_post', function ( $post_id, $post = null ) {
    if ( $post instanceof \WP_Post && CPT === $post->post_type ) {
        $path = envelope_path( meta( (int) $post_id, 'file' ) );
        if ( '' !== $path ) {
            unlink( $path );
        }
    }
}, 10, 2 );
