<?php
/**
 * "Headline Recorder" admin menu: installs or updates the platform plugin and the must-use security plugins that
 * ship inside this theme (bundled/), then activates the plugin. Works with the existing database: the plugin
 * only adds its own tables/options and never touches posts, users or settings it does not own.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const HR_BUNDLE_DIR = __DIR__ . '/../bundled';

function hr_installer_status(): array {
    $bundled = get_file_data( HR_BUNDLE_DIR . '/hr-platform/hr-platform.php', array( 'v' => 'Version' ) )['v'];
    $file    = WP_PLUGIN_DIR . '/hr-platform/hr-platform.php';
    $current = is_readable( $file ) ? get_file_data( $file, array( 'v' => 'Version' ) )['v'] : '';
    $mu      = array_map( 'basename', glob( HR_BUNDLE_DIR . '/mu-plugins/*.php' ) ?: array() );
    $mu_ok   = array_reduce( $mu, fn( $ok, $f ) => $ok && is_readable( WPMU_PLUGIN_DIR . '/' . $f ), true );
    if ( ! function_exists( 'is_plugin_active' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    return array(
        'bundled'   => $bundled,
        'installed' => $current,
        'active'    => is_plugin_active( 'hr-platform/hr-platform.php' ),
        'mu'        => $mu_ok,
        'ready'     => $current === $bundled && $mu_ok && is_plugin_active( 'hr-platform/hr-platform.php' ),
    );
}

add_action( 'admin_menu', function () {
    add_menu_page( 'Headline Recorder', 'Headline Recorder', 'install_plugins', 'hr-install', 'hr_installer_page', 'dashicons-shield-alt', 2 );
} );

// Nudge admins until the platform is installed.
add_action( 'admin_notices', function () {
    if ( ! current_user_can( 'install_plugins' ) || ( isset( $_GET['page'] ) && 'hr-install' === $_GET['page'] ) || hr_installer_status()['ready'] ) {
        return;
    }
    printf( '<div class="notice notice-warning"><p><strong>Headline Recorder:</strong> finish setup — <a href="%s">install the platform</a> (accounts, podcast, submissions, signing, security).</p></div>', esc_url( admin_url( 'admin.php?page=hr-install' ) ) );
} );

add_action( 'admin_post_hr_install', function () {
    if ( ! current_user_can( 'install_plugins' ) || ! current_user_can( 'activate_plugins' ) ) {
        wp_die( 'Not allowed.', 403 );
    }
    check_admin_referer( 'hr_install' );
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    if ( ! WP_Filesystem() ) {
        wp_die( 'WordPress could not get direct file access. Upload wp-content/plugins/hr-platform and mu-plugins from the theme\'s bundled/ folder by FTP instead.' );
    }
    global $wp_filesystem;
    $errors = array();

    // Plugin: replace the folder so removed files don't linger.
    $dest = WP_PLUGIN_DIR . '/hr-platform';
    if ( $wp_filesystem->exists( $dest ) ) {
        $wp_filesystem->delete( $dest, true );
    }
    $wp_filesystem->mkdir( $dest );
    $copied = copy_dir( HR_BUNDLE_DIR . '/hr-platform', $dest );
    if ( is_wp_error( $copied ) ) {
        $errors[] = $copied->get_error_message();
    }

    // Must-use plugins (load before everything else: cookies, headers, Sentry).
    if ( ! $wp_filesystem->is_dir( WPMU_PLUGIN_DIR ) ) {
        $wp_filesystem->mkdir( WPMU_PLUGIN_DIR );
    }
    foreach ( glob( HR_BUNDLE_DIR . '/mu-plugins/*.php' ) ?: array() as $src ) {
        if ( ! $wp_filesystem->copy( $src, WPMU_PLUGIN_DIR . '/' . basename( $src ), true, FS_CHMOD_FILE ) ) {
            $errors[] = 'Could not copy ' . basename( $src ) . ' to ' . WPMU_PLUGIN_DIR;
        }
    }

    if ( ! $errors ) {
        // Its weaker headers would override ours (see infra/litespeed/htaccess-hardening.conf).
        deactivate_plugins( 'headers-security-advanced-hsts-wp/headers-security-advanced-hsts-wp.php' );
        wp_clean_plugins_cache( false ); // the plugin list was cached before the copy
        $activated = activate_plugin( 'hr-platform/hr-platform.php' );
        if ( is_wp_error( $activated ) ) {
            $errors[] = $activated->get_error_message();
        }
    }
    set_transient( 'hr_install_result', $errors, 120 );
    wp_safe_redirect( admin_url( 'admin.php?page=hr-install' ) );
    exit;
} );

function hr_installer_page() {
    if ( ! current_user_can( 'install_plugins' ) ) {
        return;
    }
    $s      = hr_installer_status();
    $result = get_transient( 'hr_install_result' );
    delete_transient( 'hr_install_result' );
    ?>
    <div class="wrap">
        <h1>Headline Recorder</h1>
        <?php if ( is_array( $result ) ) : ?>
            <div class="notice <?php echo $result ? 'notice-error' : 'notice-success'; ?>"><p>
                <?php echo $result ? esc_html( implode( ' — ', $result ) ) : 'Installed and activated. The site is live with the new platform.'; ?>
            </p></div>
        <?php endif; ?>
        <table class="widefat striped" style="max-width:640px">
            <tr><td>Platform plugin (bundled)</td><td><?php echo esc_html( $s['bundled'] ); ?></td></tr>
            <tr><td>Platform plugin (installed)</td><td><?php echo esc_html( $s['installed'] ?: 'not installed' ); ?><?php echo $s['active'] ? ' · active' : ''; ?></td></tr>
            <tr><td>Security must-use plugins</td><td><?php echo $s['mu'] ? 'installed' : 'not installed'; ?></td></tr>
        </table>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:16px">
            <input type="hidden" name="action" value="hr_install">
            <?php wp_nonce_field( 'hr_install' ); ?>
            <?php submit_button( $s['ready'] ? 'Reinstall / update platform' : 'Install Headline Recorder platform', 'primary', 'submit', false ); ?>
        </form>
        <?php if ( $s['ready'] ) : ?>
            <h2>Next steps</h2>
            <ol>
                <li><a href="<?php echo esc_url( admin_url( 'options-general.php?page=hr-platform' ) ); ?>">Settings → Headline Recorder</a>: paste the signing keyset and the submissions public key, set the notify email, podcast and Sentry options.</li>
                <li>Reader submissions arrive in <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=hr_submission' ) ); ?>">Submissions</a>. Podcast episodes: <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=hr_episode' ) ); ?>">Episodes</a>. Logos: <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=hr_client' ) ); ?>">Client logos</a>.</li>
                <li>Paste <code>bundled/htaccess-hardening.conf</code> (in this theme) at the top of <code>.htaccess</code> and copy <code>bundled/webroot/robots.txt</code> + <code>.well-known/security.txt</code> to the site root.</li>
            </ol>
        <?php endif; ?>
    </div>
    <?php
}
