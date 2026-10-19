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
if ( is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

require_once __DIR__ . '/includes/core/helpers.php';
require_once __DIR__ . '/includes/core/icons.php';
require_once __DIR__ . '/includes/core/routes.php';
require_once __DIR__ . '/includes/core/settings.php';

// Each module is self-contained and optional; a missing module simply does nothing, and a module that fails to
// load is skipped (and reported) instead of taking the whole news site down.
foreach ( array( 'monitoring', 'accounts', 'streaks', 'submissions', 'podcast', 'clients', 'signing', 'site' ) as $module ) {
    $file = __DIR__ . "/includes/{$module}/module.php";
    if ( ! is_readable( $file ) ) {
        continue;
    }
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

add_action( 'admin_notices', function () {
    if ( empty( $GLOBALS['hr_platform_failed_modules'] ) || ! current_user_can( 'manage_options' ) ) {
        return;
    }
    printf(
        '<div class="notice notice-error"><p>%s</p></div>',
        esc_html( 'Headline Recorder Platform: these modules failed to load and are switched off: ' . implode( ', ', $GLOBALS['hr_platform_failed_modules'] ) . '. See the PHP error log or Sentry.' )
    );
} );

/**
 * Activation and upgrades share one path: register rewrites, let modules create tables, flush rules.
 * Upgrades also run when files are replaced without re-activating (common on shared hosting).
 */
function install() {
    do_action( 'hr_register_rewrites' );
    do_action( 'hr_install' );
    update_option( 'hr_platform_db_version', DB_VERSION, false );
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\\install' );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

add_action( 'init', function () {
    if ( (int) get_option( 'hr_platform_db_version' ) !== DB_VERSION ) {
        install();
    }
}, 99 );
