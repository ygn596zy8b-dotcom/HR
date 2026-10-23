<?php
/**
 * "Headline Recorder" admin menu: installs or updates the platform plugin and the must-use security plugins that
 * ship inside this theme (bundled/), merges the PHP hardening settings into the site's .user.ini, then activates the
 * plugin. Works with the existing database: the plugin only adds its own tables/options and never touches posts,
 * users or settings it does not own.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const HR_BUNDLE_DIR = __DIR__ . '/../bundled';

function hr_installer_status(): array {
    $bundled = get_file_data( HR_BUNDLE_DIR . '/hr-platform/hr-platform.php', array( 'v' => 'Version' ) )['v'];
    $file    = WP_PLUGIN_DIR . '/hr-platform/hr-platform.php';
    $current = is_readable( $file ) ? get_file_data( $file, array( 'v' => 'Version' ) )['v'] : '';
    // Every bundled must-use plugin (hr-hardening, hr-sentry, hr-php-guard, any added later) must be in place.
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
        'mu_list'   => $mu,
        'ready'     => $current === $bundled && $mu_ok && is_plugin_active( 'hr-platform/hr-platform.php' ),
    );
}

// ---------------------------------------------------------------------------------------------------------------
// PHP settings in the web root's .user.ini (php2shell mitigation and safe hardening; source: deploy/webroot/.user.ini,
// bundled as bundled/webroot/.user.ini). Merged, never replaced: other lines stay, the original is kept once as
// .user.ini.hr-backup.
// ---------------------------------------------------------------------------------------------------------------

/** The .user.ini file PHP reads in the web root (".user.ini" unless the host renamed it). */
function hr_installer_user_ini_path(): string {
    $name = basename( (string) ini_get( 'user_ini.filename' ) );
    return ABSPATH . ( '' !== $name && '.' !== $name ? $name : '.user.ini' );
}

/**
 * Directives from the bundled file: array( key => array( 'value' => string, 'comment' => string[] ) ), in order.
 * Comment lines directly above a directive travel with it when it has to be appended.
 */
function hr_installer_user_ini_directives( string $file ): array {
    $out     = array();
    $comment = array();
    foreach ( preg_split( '/\R/', (string) @file_get_contents( $file ) ) as $line ) {
        $trim = trim( $line );
        if ( '' === $trim ) {
            $comment = array();
        } elseif ( str_starts_with( $trim, ';' ) || str_starts_with( $trim, '#' ) ) {
            $comment[] = $trim;
        } elseif ( preg_match( '/^([A-Za-z0-9_.]+)\s*=\s*(.*)$/', $trim, $m ) ) {
            $out[ $m[1] ] = array( 'value' => trim( $m[2] ), 'comment' => $comment );
            $comment      = array();
        }
    }
    return $out;
}

/** Pure merge: replaces our directives where they already appear, appends the missing ones in a marked block. */
function hr_installer_merge_user_ini_text( string $existing, array $directives ): string {
    $lines = '' === $existing ? array() : preg_split( '/\R/', rtrim( $existing, "\r\n" ) );
    $seen  = array();
    foreach ( $lines as $i => $line ) {
        if ( preg_match( '/^\s*([A-Za-z0-9_.]+)\s*=/', $line, $m ) && isset( $directives[ $m[1] ] ) ) {
            $lines[ $i ]      = $m[1] . ' = ' . $directives[ $m[1] ]['value'];
            $seen[ $m[1] ] = true;
        }
    }
    $missing = array_diff_key( $directives, $seen );
    if ( $missing ) {
        if ( $lines && '' !== trim( (string) end( $lines ) ) ) {
            $lines[] = '';
        }
        $lines[] = '; BEGIN Headline Recorder PHP settings (added by the theme installer; see bundled/webroot/.user.ini)';
        foreach ( $missing as $key => $d ) {
            foreach ( $d['comment'] as $c ) {
                $lines[] = $c;
            }
            $lines[] = $key . ' = ' . $d['value'];
        }
        $lines[] = '; END Headline Recorder PHP settings';
    }
    return implode( "\n", $lines ) . "\n";
}

/**
 * Writes the merge to $target. Returns array( 'status' => created|updated|unchanged|error, 'message' => string,
 * 'backup' => bool (a backup was made now) ).
 */
function hr_installer_apply_user_ini( string $target, string $bundle ): array {
    $directives = is_readable( $bundle ) ? hr_installer_user_ini_directives( $bundle ) : array();
    if ( ! $directives ) {
        return array( 'status' => 'error', 'message' => 'The bundled .user.ini is missing from the theme (bundled/webroot/.user.ini). Rebuild the theme zip.', 'backup' => false );
    }
    $exists = file_exists( $target );
    if ( ( $exists && ( ! is_readable( $target ) || ! is_writable( $target ) ) ) || ( ! $exists && ! is_writable( dirname( $target ) ) ) ) {
        return array( 'status' => 'error', 'message' => 'WordPress cannot write ' . $target . '. Add the lines from bundled/webroot/.user.ini to it in cPanel File Manager instead.', 'backup' => false );
    }
    $old = $exists ? (string) file_get_contents( $target ) : '';
    $new = hr_installer_merge_user_ini_text( $old, $directives );
    if ( $exists && $new === $old ) {
        return array( 'status' => 'unchanged', 'message' => 'Already up to date.', 'backup' => false );
    }
    $backup = false;
    if ( $exists && ! file_exists( $target . '.hr-backup' ) ) {
        if ( ! @copy( $target, $target . '.hr-backup' ) ) {
            return array( 'status' => 'error', 'message' => 'Could not save the backup ' . $target . '.hr-backup, so nothing was changed.', 'backup' => false );
        }
        @chmod( $target . '.hr-backup', 0640 );
        $backup = true;
    }
    $tmp = $target . '.hr-tmp-' . wp_generate_password( 8, false );
    if ( false === @file_put_contents( $tmp, $new, LOCK_EX ) || ! @rename( $tmp, $target ) ) {
        @unlink( $tmp );
        return array( 'status' => 'error', 'message' => 'Could not write ' . $target . '.', 'backup' => $backup );
    }
    @chmod( $target, 0644 );
    return array(
        'status'  => $exists ? 'updated' : 'created',
        'message' => ( $exists ? 'Merged into ' : 'Created ' ) . $target . ( $backup ? ' (original saved as .user.ini.hr-backup)' : '' ) . '.',
        'backup'  => $backup,
    );
}

function hr_installer_apply_bundled_user_ini(): array {
    $result = hr_installer_apply_user_ini( hr_installer_user_ini_path(), HR_BUNDLE_DIR . '/webroot/.user.ini' );
    set_transient( 'hr_install_userini', $result + array( 'at' => time() ), DAY_IN_SECONDS );
    return $result;
}

function hr_installer_check_request( string $action, string $field = '_wpnonce' ): void {
    if ( ! current_user_can( 'install_plugins' ) || ! current_user_can( 'activate_plugins' ) ) {
        wp_die( 'Not allowed.', 403 );
    }
    check_admin_referer( $action, $field );
    $source = (string) ( $_SERVER['HTTP_ORIGIN'] ?? ( $_SERVER['HTTP_REFERER'] ?? '' ) );
    $from   = wp_parse_url( $source );
    $admin  = wp_parse_url( admin_url() );
    if ( ! is_array( $from ) || strtolower( (string) ( $from['host'] ?? '' ) ) !== strtolower( (string) ( $admin['host'] ?? '' ) ) || ( $from['scheme'] ?? '' ) !== ( $admin['scheme'] ?? '' ) ) {
        wp_die( 'This request did not come from this site.', 403 );
    }
}

add_action( 'admin_menu', function () {
    add_menu_page( 'Headline Recorder', 'Headline Recorder', 'install_plugins', 'hr-install', 'hr_installer_page', 'dashicons-shield-alt', 2 );
} );

// Nudge admins until the platform is installed.
add_action( 'admin_notices', function () {
    if ( ! current_user_can( 'install_plugins' ) || ( isset( $_GET['page'] ) && 'hr-install' === $_GET['page'] ) || hr_installer_status()['ready'] ) {
        return;
    }
    printf( '<div class="notice notice-warning"><p><strong>Headline Recorder:</strong> finish setup: <a href="%s">install the platform</a> (accounts, podcast, submissions, signing, security).</p></div>', esc_url( admin_url( 'admin.php?page=hr-install' ) ) );
} );

add_action( 'admin_post_hr_install', function () {
    hr_installer_check_request( 'hr_install' );
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

    // Must-use plugins (load before everything else: cookies, headers, Sentry, the PHP security check).
    if ( ! $wp_filesystem->is_dir( WPMU_PLUGIN_DIR ) ) {
        $wp_filesystem->mkdir( WPMU_PLUGIN_DIR );
    }
    foreach ( glob( HR_BUNDLE_DIR . '/mu-plugins/*.php' ) ?: array() as $src ) {
        if ( ! $wp_filesystem->copy( $src, WPMU_PLUGIN_DIR . '/' . basename( $src ), true, FS_CHMOD_FILE ) ) {
            $errors[] = 'Could not copy ' . basename( $src ) . ' to ' . WPMU_PLUGIN_DIR;
        }
    }

    // PHP settings (php2shell mitigation). A failure here is reported on the page but does not stop the install.
    hr_installer_apply_bundled_user_ini();

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

// "Apply PHP settings" on its own, without reinstalling the plugin.
add_action( 'admin_post_hr_install_userini', function () {
    hr_installer_check_request( 'hr_install_userini', '_hr_userini_nonce' );
    hr_installer_apply_bundled_user_ini();
    wp_safe_redirect( admin_url( 'admin.php?page=hr-install#hr-php-settings' ) );
    exit;
} );

function hr_installer_page() {
    if ( ! current_user_can( 'install_plugins' ) ) {
        return;
    }
    $s       = hr_installer_status();
    $result  = get_transient( 'hr_install_result' );
    delete_transient( 'hr_install_result' );
    $ini     = get_transient( 'hr_install_userini' );
    $wanted  = hr_installer_user_ini_directives( HR_BUNDLE_DIR . '/webroot/.user.ini' );
    $ttl     = (int) ini_get( 'user_ini.cache_ttl' );
    // .user.ini is read by the CGI/FastCGI handlers only: PHP-FPM, CGI and LiteSpeed LSAPI (not mod_php, not php -S).
    $reads   = '' !== (string) ini_get( 'user_ini.filename' ) && in_array( PHP_SAPI, array( 'cgi-fcgi', 'fpm-fcgi', 'cgi', 'litespeed' ), true );
    $flags   = array( '1', 'on', 'true', 'yes', '0', 'off', 'false', 'no', '' );
    $as_bool = static fn( $v ) => in_array( strtolower( trim( (string) $v ) ), array( '1', 'on', 'true', 'yes' ), true );
    ?>
    <div class="wrap">
        <h1>Headline Recorder</h1>
        <?php if ( is_array( $result ) ) : ?>
            <div class="notice <?php echo $result ? 'notice-error' : 'notice-success'; ?>"><p>
                <?php echo $result ? esc_html( implode( ' | ', $result ) ) : 'Installed and activated. The site is live with the new platform.'; ?>
            </p></div>
        <?php endif; ?>
        <table class="widefat striped" style="max-width:640px">
            <tr><td>Platform plugin (bundled)</td><td><?php echo esc_html( $s['bundled'] ); ?></td></tr>
            <tr><td>Platform plugin (installed)</td><td><?php echo esc_html( $s['installed'] ?: 'not installed' ); ?><?php echo $s['active'] ? ' · active' : ''; ?></td></tr>
            <tr><td>Security must-use plugins</td><td><?php echo $s['mu'] ? 'installed' : 'not installed'; ?><?php echo $s['mu_list'] ? ' (' . esc_html( implode( ', ', $s['mu_list'] ) ) . ')' : ''; ?></td></tr>
        </table>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:16px">
            <input type="hidden" name="action" value="hr_install">
            <?php wp_nonce_field( 'hr_install' ); ?>
            <?php submit_button( $s['ready'] ? 'Reinstall / update platform' : 'Install Headline Recorder platform', 'primary', 'submit', false ); ?>
        </form>

        <h2 id="hr-php-settings">PHP security settings (.user.ini)</h2>
        <p>Turns off PHP's session upload-progress hook, the entry point of the php2shell flaw (CVE-2026-109618), and sets
            safe session and error-display defaults. This is a stopgap: only a patched PHP (8.4.27 or later) closes both
            php2shell flaws. The installer merges these lines into <code><?php echo esc_html( hr_installer_user_ini_path() ); ?></code>,
            keeps every other line and saves the original once as <code>.user.ini.hr-backup</code>.</p>
        <?php if ( is_array( $ini ) ) : ?>
            <div class="notice inline <?php echo 'error' === $ini['status'] ? 'notice-error' : 'notice-success'; ?>"><p>
                <?php echo esc_html( sprintf( 'Last run %s ago: %s', human_time_diff( (int) $ini['at'] ), $ini['message'] ) ); ?>
            </p></div>
        <?php endif; ?>
        <table class="widefat striped" style="max-width:760px">
            <thead><tr><th>Setting</th><th>Wanted</th><th>Live value now</th><th></th></tr></thead>
            <tbody>
            <?php if ( ! $wanted ) : ?>
                <tr><td colspan="4">The bundled settings file (bundled/webroot/.user.ini) is missing from this theme copy. Upload the theme zip built by scripts/build-theme-zip.sh.</td></tr>
            <?php endif; ?>
            <?php foreach ( $wanted as $key => $d ) :
                $live = ini_get( $key );
                $ok   = false !== $live && ( in_array( strtolower( $d['value'] ), $flags, true ) ? $as_bool( $live ) === $as_bool( $d['value'] ) : strtolower( (string) $live ) === strtolower( $d['value'] ) );
                ?>
                <tr>
                    <td><code><?php echo esc_html( $key ); ?></code></td>
                    <td><?php echo esc_html( $d['value'] ); ?></td>
                    <td><?php echo esc_html( false === $live ? 'not available' : ( '' === (string) $live ? 'Off / empty' : (string) $live ) ); ?></td>
                    <td><?php echo $ok ? 'OK' : '<strong>waiting</strong>'; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="description" style="max-width:760px">
            PHP <?php echo esc_html( PHP_VERSION ); ?> (<?php echo esc_html( PHP_SAPI ); ?>).
            <?php if ( $reads ) : ?>
                PHP rereads <code>.user.ini</code> every <?php echo (int) $ttl; ?> seconds (user_ini.cache_ttl), so "waiting" rows can take up to <?php echo (int) ceil( $ttl / 60 ); ?> minute(s) to turn OK. Reload this page then.
            <?php else : ?>
                This PHP handler does not read <code>.user.ini</code>. Use the php_flag block in <code>bundled/htaccess-hardening.conf</code>, or cPanel MultiPHP INI Editor, instead.
            <?php endif; ?>
            display_errors may show On while WordPress debugging is switched on in wp-config.php.
        </p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="hr_install_userini">
            <?php wp_nonce_field( 'hr_install_userini', '_hr_userini_nonce' ); ?>
            <?php submit_button( 'Apply PHP settings', 'secondary', 'submit', false ); ?>
        </form>

        <?php if ( $s['ready'] ) : ?>
            <h2>Next steps</h2>
            <ol>
                <li><a href="<?php echo esc_url( admin_url( 'options-general.php?page=hr-platform' ) ); ?>">Settings &gt; Headline Recorder</a>: paste the signing keyset and the submissions public key, set the notify email, podcast and Sentry options.</li>
                <li>Reader submissions arrive in <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=hr_submission' ) ); ?>">Submissions</a>. Podcast episodes: <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=hr_episode' ) ); ?>">Episodes</a>. Logos: <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=hr_client' ) ); ?>">Client logos</a>.</li>
                <li>Paste <code>bundled/htaccess-hardening.conf</code> (in this theme) at the top of <code>.htaccess</code> and copy <code>bundled/webroot/robots.txt</code> + <code>.well-known/security.txt</code> to the site root.</li>
                <li>Check <a href="<?php echo esc_url( admin_url( 'site-health.php' ) ); ?>">Tools &gt; Site Health</a>: it says whether this server's PHP is patched against php2shell.</li>
            </ol>
        <?php endif; ?>
    </div>
    <?php
}
