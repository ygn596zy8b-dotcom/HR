<?php
/**
 * Submissions: sealed envelopes on disk. One JSON file per submission with a random name, in a directory that is
 * never served: HR_PRIVATE_DIR (recommended, outside the web root) or wp-content/uploads/hr-sealed/ with deny rules.
 */

namespace HR\Platform\Submissions;

if ( ! defined( 'ABSPATH' ) ) exit;

/** The storage directory (created and locked down on first use), or '' when it cannot be created. */
function storage_dir(): string {
    $dir = defined( 'HR_PRIVATE_DIR' ) && is_string( HR_PRIVATE_DIR ) && '' !== HR_PRIVATE_DIR
        ? rtrim( HR_PRIVATE_DIR, '/\\' ) . '/submissions'
        : wp_upload_dir( null, false )['basedir'] . '/hr-sealed';
    if ( ! is_dir( $dir ) ) {
        if ( ! wp_mkdir_p( $dir ) ) {
            return '';
        }
        chmod( $dir, 0750 );
    }
    $guards = array(
        '.htaccess'  => "# Sealed submissions are never served.\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n",
        'index.php'  => "<?php\n// Silence.\n",
        'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n",
    );
    foreach ( $guards as $name => $body ) {
        if ( ! file_exists( "{$dir}/{$name}" ) ) {
            file_put_contents( "{$dir}/{$name}", $body );
        }
    }
    return $dir;
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

/** Absolute path of a stored envelope, only if the name is well-formed and the real path stays inside the directory. */
function envelope_path( string $name ): string {
    $dir = storage_dir();
    if ( '' === $dir || ! preg_match( '/^[0-9a-f]{32}\.json$/', $name ) ) {
        return '';
    }
    $base = realpath( $dir );
    $real = realpath( "{$dir}/{$name}" );
    return $base && $real && str_starts_with( $real, $base . DIRECTORY_SEPARATOR ) && is_file( $real ) ? $real : '';
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
