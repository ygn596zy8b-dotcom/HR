<?php
/**
 * Plugin Name:       Headline Recorder Platform
 * Description:       Reader accounts and reading streaks, sealed article/op-ed submissions, the Headline Recorder Podcast, client logos, post-quantum article signing and Sentry monitoring.
 * Version:           1.0.0
 * Requires at least: 6.8
 * Requires PHP:      8.2
 * Author:            Headline Recorder
 * Text Domain:       hr-platform
 */

namespace HR\Platform;

if ( ! defined( 'ABSPATH' ) ) exit;

const VERSION = '1.0.0';
const DB_VERSION = 1;
define( 'HR_PLATFORM_FILE', __FILE__ );
define( 'HR_PLATFORM_DIR', __DIR__ );
define( 'HR_PLATFORM_URL', plugin_dir_url( __FILE__ ) );

// Third-party libraries (Sentry, Anthropic SDK, WebAuthn). Built by `composer install --no-dev`; see docs/DEPLOY.md.
// A half-uploaded vendor/ or Composer's platform check (it throws on an unsupported PHP) must not take the site down:
// the modules that need a library check for its classes and switch themselves off.
if ( is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
    try {
        require_once __DIR__ . '/vendor/autoload.php';
    } catch ( \Throwable $e ) {
        error_log( 'hr-platform: vendor/autoload.php failed: ' . $e->getMessage() );
        $GLOBALS['hr_platform_failed_modules'][] = 'vendor libraries';
        if ( ! headers_sent() ) {
            http_response_code( 200 ); // Composer's platform check already set 500; WordPress sets the real status later
        }
    }
}

require_once __DIR__ . '/includes/core/helpers.php';
require_once __DIR__ . '/includes/core/icons.php';
require_once __DIR__ . '/includes/core/routes.php';
require_once __DIR__ . '/includes/core/settings.php';

// Each module is self-contained and optional; a missing module simply does nothing, and a module that fails to
// load is skipped (and reported) instead of taking the whole news site down.
//
// Exceptions and ParseErrors are caught below. A compile error (for example "Cannot redeclare" or a syntax PHP only
// reports as a fatal) cannot be caught: the request that hits it records the module in `hr_platform_quarantine` from a
// shutdown handler, and every later request skips that module until one of its files changes (then it is retried).
const MODULES = array( 'monitoring', 'accounts', 'streaks', 'submissions', 'podcast', 'clients', 'signing', 'site', 'feeds', 'edge' );

/** Fingerprint of a module's PHP files (path, mtime, size); changes with every deploy of that module. */
function module_signature( string $module ): string {
    $parts = array();
    try {
        $files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( __DIR__ . "/includes/{$module}", \FilesystemIterator::SKIP_DOTS ) );
        foreach ( $files as $f ) {
            if ( $f->isFile() && 'php' === $f->getExtension() ) {
                $parts[] = $f->getPathname() . ':' . $f->getMTime() . ':' . $f->getSize();
            }
        }
    } catch ( \Throwable $e ) {
        return '';
    }
    sort( $parts );
    return md5( implode( '|', $parts ) );
}

$GLOBALS['hr_platform_loading_module'] = '';
register_shutdown_function( static function () {
    $module = (string) ( $GLOBALS['hr_platform_loading_module'] ?? '' );
    $error  = error_get_last();
    if ( '' === $module || ! is_array( $error ) || ! ( (int) $error['type'] & ( E_COMPILE_ERROR | E_PARSE ) )
        || ! str_starts_with( wp_normalize_path( (string) $error['file'] ), wp_normalize_path( __DIR__ . "/includes/{$module}/" ) ) ) {
        return;
    }
    $quarantine            = (array) get_option( 'hr_platform_quarantine', array() );
    $quarantine[ $module ] = array(
        'sig'   => module_signature( $module ),
        'at'    => time(),
        'error' => substr( (string) $error['message'], 0, 300 ) . ' (' . basename( (string) $error['file'] ) . ':' . (int) $error['line'] . ')',
    );
    update_option( 'hr_platform_quarantine', $quarantine, true );
    error_log( sprintf( 'hr-platform: module "%s" quarantined after a fatal compile error; it is skipped until its files change.', $module ) );
} );

// Read from the autoloaded set: a plain get_option() of a missing option costs a query on every request.
$hr_quarantine = wp_load_alloptions();
$hr_quarantine = isset( $hr_quarantine['hr_platform_quarantine'] ) ? (array) maybe_unserialize( $hr_quarantine['hr_platform_quarantine'] ) : array();
foreach ( MODULES as $module ) {
    $file = __DIR__ . "/includes/{$module}/module.php";
    if ( ! is_readable( $file ) ) {
        continue;
    }
    if ( isset( $hr_quarantine[ $module ] ) ) {
        if ( hash_equals( (string) ( $hr_quarantine[ $module ]['sig'] ?? '' ), module_signature( $module ) ) ) {
            $GLOBALS['hr_platform_failed_modules'][] = $module;
            continue;
        }
        unset( $hr_quarantine[ $module ] ); // its files changed: try it again
        update_option( 'hr_platform_quarantine', $hr_quarantine, true );
    }
    $GLOBALS['hr_platform_loading_module'] = $module;
    try {
        require_once $file;
    } catch ( \Throwable $e ) {
        error_log( sprintf( 'hr-platform: module "%s" failed to load: %s in %s:%d', $module, $e->getMessage(), $e->getFile(), $e->getLine() ) );
        if ( function_exists( '\Sentry\captureException' ) ) {
            \Sentry\captureException( $e );
        }
        $GLOBALS['hr_platform_failed_modules'][] = $module;
    }
}
$GLOBALS['hr_platform_loading_module'] = '';
unset( $hr_quarantine, $module, $file );

add_action( 'admin_notices', function () {
    if ( empty( $GLOBALS['hr_platform_failed_modules'] ) || ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $details = array();
    foreach ( (array) get_option( 'hr_platform_quarantine', array() ) as $module => $info ) {
        $details[] = $module . ': ' . ( $info['error'] ?? 'fatal error' );
    }
    printf(
        '<div class="notice notice-error"><p>%s</p>%s</div>',
        esc_html( 'Headline Recorder Platform: these modules failed to load and are switched off: ' . implode( ', ', array_unique( $GLOBALS['hr_platform_failed_modules'] ) ) . '. See the PHP error log or Sentry. A module switched off after a fatal error comes back by itself once its files are fixed.' ),
        $details ? '<p><code>' . implode( '</code><br><code>', array_map( 'esc_html', $details ) ) . '</code></p>' : ''
    );
} );

/**
 * Activation and upgrades share one path: register rewrites, let modules create tables, flush rules.
 * Upgrades also run when files are replaced without re-activating (common on shared hosting).
 */
function install() {
    do_action( 'hr_register_rewrites' );
    do_action( 'hr_install' );
    update_option( 'hr_platform_db_version', DB_VERSION, true ); // read on every request: keep it in the autoloaded set
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\\install' );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

add_action( 'init', function () {
    if ( (int) get_option( 'hr_platform_db_version' ) !== DB_VERSION ) {
        install();
    } elseif ( ! isset( wp_load_alloptions()['hr_platform_db_version'] ) ) {
        wp_set_option_autoload( 'hr_platform_db_version', true ); // older installs stored it unautoloaded (one query per page)
    }
}, 99 );
