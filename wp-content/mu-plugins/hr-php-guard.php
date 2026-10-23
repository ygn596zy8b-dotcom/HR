<?php
/**
 * Plugin Name: Headline Recorder PHP Guard
 * Description: Tells administrators whether this server's PHP is patched against php2shell (CVE-2026-109618, CVE-2026-109619) and whether WordPress itself is current. Admin notice plus Site Health checks. Makes no external requests.
 * Version:     1.0.0
 * Author:      MSBJ
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** Advisory id: bump it when a new PHP advisory is added, so a dismissed notice comes back once. */
const HR_PHP_GUARD_ADVISORY = 'php2shell-2026-10';

/** First fixed release per supported branch (21 October 2026). */
const HR_PHP_GUARD_FIXED = array( '8.2' => '8.2.35', '8.3' => '8.3.36', '8.4' => '8.4.27', '8.5' => '8.5.12' );

/**
 * State of this server: array( version, branch, fixed, status, upload_progress ).
 * status: patched | vulnerable | unsupported (branch older than 8.2, no fix exists).
 */
function hr_php_guard_state( ?string $version = null, ?bool $upload_progress = null ): array {
    $version = hr_php_guard_normalise( $version ?? sprintf( '%d.%d.%d', PHP_MAJOR_VERSION, PHP_MINOR_VERSION, PHP_RELEASE_VERSION ) );
    $parts   = array_map( 'intval', explode( '.', $version ) );
    $branch  = $parts[0] . '.' . $parts[1];
    $fixed   = HR_PHP_GUARD_FIXED[ $branch ] ?? '';
    if ( '' !== $fixed ) {
        $status = version_compare( $version, $fixed, '>=' ) ? 'patched' : 'vulnerable';
    } else {
        $status = version_compare( $branch, '8.2', '<' ) ? 'unsupported' : 'patched'; // a branch newer than 8.5 shipped after the fix
    }
    $upload_progress = $upload_progress ?? filter_var( ini_get( 'session.upload_progress.enabled' ), FILTER_VALIDATE_BOOLEAN );
    return array(
        'version'         => $version,
        'branch'          => $branch,
        'fixed'           => $fixed,
        'status'          => $status,
        'upload_progress' => (bool) $upload_progress,
    );
}

/**
 * "8.4.27-1+ubuntu24.04.1+deb.sury.org+1", "8.4.27RC1", "7.0" -> "8.4.27", "8.4.27", "7.0.0": only the numeric
 * major.minor.release part counts (distribution suffixes would otherwise make version_compare() call a patched
 * build older, and a bare "7.0" sorts before "7.0.0").
 */
function hr_php_guard_normalise( string $version ): string {
    return preg_match( '/^\s*(\d+)(?:\.(\d+))?(?:\.(\d+))?/', $version, $m )
        ? sprintf( '%d.%d.%d', $m[1], $m[2] ?? 0, $m[3] ?? 0 )
        : '0.0.0';
}

/** Plain-language steps to get a patched PHP on cPanel / LiteSpeed hosting. */
function hr_php_guard_steps_html( array $s ): string {
    $stay = '' !== $s['fixed'] && '8.4' !== $s['branch'] ? ' Staying on PHP ' . esc_html( $s['branch'] ) . ' is fine only with ' . esc_html( $s['fixed'] ) . ' or later.' : '';
    return '<ol>'
        . '<li>In cPanel open <strong>Select PHP Version</strong> (CloudLinux) or <strong>MultiPHP Manager</strong> and pick PHP 8.4, newest build: it must be <strong>8.4.27 or later</strong>.' . $stay . '</li>'
        . '<li>If no patched build is listed yet, open a ticket with the host: "Please install the PHP release that fixes CVE-2026-109618 and CVE-2026-109619 (php2shell) for our lsphp / ea-php / alt-php 8.4 (8.4.27 or later)."</li>'
        . '<li>Until then keep <code>session.upload_progress.enabled = Off</code> (the theme installer writes it to <code>.user.ini</code>; cPanel: <strong>MultiPHP INI Editor</strong> or <strong>Select PHP Version &gt; Options</strong>). This blocks the upload-progress path of CVE-2026-109618 only, not the multipart parser flaw.</li>'
        . '<li>Check again under <strong>Tools &gt; Site Health</strong>. Some hosts backport fixes without changing the version number; if yours says so in writing, the warning can be dismissed.</li>'
        . '</ol>';
}

// ---------------------------------------------------------------------------------------------------------------
// Admin notice (administrators only), dismissible until the PHP version or the advisory changes.
// ---------------------------------------------------------------------------------------------------------------
add_action( 'admin_notices', static function () {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $s = hr_php_guard_state();
    if ( 'patched' === $s['status'] ) {
        return;
    }
    $key = HR_PHP_GUARD_ADVISORY . '|' . $s['version'];
    if ( get_user_meta( get_current_user_id(), 'hr_php_guard_dismissed', true ) === $key ) {
        return;
    }
    $headline = 'unsupported' === $s['status']
        ? sprintf( 'This server runs PHP %s, a branch that no longer gets security fixes. It is exposed to php2shell (CVE-2026-109618, CVE-2026-109619), a remote takeover flaw with public exploits, and no fix will come for it. Move to PHP 8.4.', $s['version'] )
        : sprintf( 'This server runs PHP %s, which is not patched against php2shell (CVE-2026-109618, CVE-2026-109619): a remote takeover flaw in PHP\'s upload handling with public exploits. PHP %s needs %s or later.', $s['version'], $s['branch'], $s['fixed'] );
    $mitigation = $s['upload_progress']
        ? 'Interim mitigation is <strong>not</strong> active: session.upload_progress.enabled is On.'
        : 'Interim mitigation is active (session.upload_progress.enabled is Off), but only a PHP upgrade closes both flaws.';
    ?>
    <div class="notice notice-error">
        <p><strong>Security update needed:</strong> <?php echo esc_html( $headline ); ?></p>
        <p><?php echo wp_kses( $mitigation, array( 'strong' => array() ) ); ?></p>
        <?php echo wp_kses_post( hr_php_guard_steps_html( $s ) ); ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0 0 10px">
            <input type="hidden" name="action" value="hr_php_guard_dismiss">
            <?php wp_nonce_field( 'hr_php_guard_dismiss', '_hr_php_guard_nonce' ); ?>
            <button type="submit" class="button button-secondary">Hide until PHP changes</button>
            <a class="button-link" style="margin-left:8px" href="<?php echo esc_url( admin_url( 'site-health.php' ) ); ?>">Open Site Health</a>
        </form>
    </div>
    <?php
} );

add_action( 'admin_post_hr_php_guard_dismiss', static function () {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Not allowed.', 403 );
    }
    check_admin_referer( 'hr_php_guard_dismiss', '_hr_php_guard_nonce' );
    // Same-origin check (the platform plugin may not be loaded, so it is done here).
    $source = (string) ( $_SERVER['HTTP_ORIGIN'] ?? ( $_SERVER['HTTP_REFERER'] ?? '' ) );
    $admin  = wp_parse_url( admin_url() );
    $from   = wp_parse_url( $source );
    if ( ! is_array( $from ) || strtolower( (string) ( $from['host'] ?? '' ) ) !== strtolower( (string) ( $admin['host'] ?? '' ) ) || ( $from['scheme'] ?? '' ) !== ( $admin['scheme'] ?? '' ) ) {
        wp_die( 'This request did not come from this site.', 403 );
    }
    $s = hr_php_guard_state();
    update_user_meta( get_current_user_id(), 'hr_php_guard_dismissed', HR_PHP_GUARD_ADVISORY . '|' . $s['version'] );
    wp_safe_redirect( wp_get_referer() ?: admin_url() );
    exit;
} );

// ---------------------------------------------------------------------------------------------------------------
// Site Health (Tools > Site Health > Status): PHP patch level, the interim mitigation, and WordPress core.
// ---------------------------------------------------------------------------------------------------------------
add_filter( 'site_status_tests', static function ( $tests ) {
    $tests['direct']['hr_php2shell'] = array( 'label' => 'PHP is patched against php2shell', 'test' => 'hr_php_guard_test_php' );
    $tests['direct']['hr_upload_progress'] = array( 'label' => 'PHP session upload progress is off', 'test' => 'hr_php_guard_test_upload_progress' );
    $tests['direct']['hr_wp_core_current'] = array( 'label' => 'WordPress core is current', 'test' => 'hr_php_guard_test_wp_core' );
    return $tests;
} );

function hr_php_guard_test_php(): array {
    $s     = hr_php_guard_state();
    $badge = array( 'label' => 'Security', 'color' => 'patched' === $s['status'] ? 'blue' : 'red' );
    if ( 'patched' === $s['status'] ) {
        return array(
            'label'       => sprintf( 'PHP %s is patched against php2shell', $s['version'] ),
            'status'      => 'good',
            'badge'       => $badge,
            'description' => '<p>CVE-2026-109618 and CVE-2026-109619 (php2shell) were fixed on 21 October 2026 in PHP 8.2.35, 8.3.36, 8.4.27 and 8.5.12. This server\'s PHP includes the fix.</p>',
            'test'        => 'hr_php2shell',
        );
    }
    $why = 'unsupported' === $s['status']
        ? sprintf( '<p>PHP %s is no longer maintained, so it will never receive the php2shell fix. Move to PHP 8.4 (8.4.27 or later).</p>', esc_html( $s['version'] ) )
        : sprintf( '<p>PHP %1$s is older than %2$s, the first %3$s release with the php2shell fix.</p>', esc_html( $s['version'] ), esc_html( $s['fixed'] ), esc_html( $s['branch'] ) );
    return array(
        'label'       => sprintf( 'PHP %s is exposed to php2shell (CVE-2026-109618, CVE-2026-109619)', $s['version'] ),
        'status'      => 'critical',
        'badge'       => $badge,
        'description' => '<p>php2shell is two use-after-free flaws in how PHP parses file uploads (multipart/form-data) and in the session upload-progress hook. An attacker needs no account; public exploits exist and were used before the fix. Only a PHP upgrade fixes both.</p>' . $why,
        'actions'     => hr_php_guard_steps_html( $s ),
        'test'        => 'hr_php2shell',
    );
}

function hr_php_guard_test_upload_progress(): array {
    $s      = hr_php_guard_state();
    $badge  = array( 'label' => 'Security', 'color' => 'blue' );
    $result = array( 'badge' => $badge, 'test' => 'hr_upload_progress' );
    if ( ! $s['upload_progress'] ) {
        return $result + array(
            'label'       => 'PHP session upload progress is off',
            'status'      => 'good',
            'description' => '<p><code>session.upload_progress.enabled</code> is Off. WordPress does not use it, and switching it off removes the entry point of CVE-2026-109618.</p>',
        );
    }
    return $result + array(
        'label'       => 'PHP session upload progress is on',
        'status'      => 'patched' === $s['status'] ? 'recommended' : 'critical',
        'description' => '<p><code>session.upload_progress.enabled</code> is On. Nothing on this site needs it, and on unpatched PHP it is the entry point of CVE-2026-109618 (php2shell).</p>',
        'actions'     => '<p>Run <strong>Headline Recorder &gt; Install &gt; Apply PHP settings</strong> (writes it to <code>.user.ini</code>), or set it Off in cPanel <strong>MultiPHP INI Editor</strong>. A <code>.user.ini</code> change can take up to ' . (int) ini_get( 'user_ini.cache_ttl' ) . ' seconds to apply.</p>',
    );
}

/**
 * WordPress core: flags the versions hit by "wp2shell" (CVE-2026-63030, REST batch endpoint; 6.9.0-6.9.4 and
 * 7.0.0-7.0.1, fixed in 6.9.5 and 7.0.2) and any update WordPress already knows about (no request made here).
 */
function hr_php_guard_wp_affected( string $version ): bool {
    $version = hr_php_guard_normalise( $version );
    return ( version_compare( $version, '6.9.0', '>=' ) && version_compare( $version, '6.9.5', '<' ) )
        || ( version_compare( $version, '7.0.0', '>=' ) && version_compare( $version, '7.0.2', '<' ) );
}

function hr_php_guard_test_wp_core(): array {
    $version = (string) get_bloginfo( 'version' );
    $badge   = array( 'label' => 'Security', 'color' => 'blue' );
    if ( hr_php_guard_wp_affected( $version ) ) {
        return array(
            'label'       => sprintf( 'WordPress %s is exposed to wp2shell (CVE-2026-63030)', $version ),
            'status'      => 'critical',
            'badge'       => array( 'label' => 'Security', 'color' => 'red' ),
            'description' => '<p>The REST API batch endpoint flaw affects WordPress 6.9.0 to 6.9.4 and 7.0.0 to 7.0.1. It is fixed in 6.9.5 and 7.0.2.</p>',
            'actions'     => '<p><a href="' . esc_url( admin_url( 'update-core.php' ) ) . '">Dashboard &gt; Updates</a>: update WordPress now.</p>',
            'test'        => 'hr_wp_core_current',
        );
    }
    $offer = '';
    $core  = get_site_transient( 'update_core' );
    foreach ( (array) ( is_object( $core ) ? ( $core->updates ?? array() ) : array() ) as $update ) {
        if ( is_object( $update ) && 'upgrade' === ( $update->response ?? '' ) && version_compare( (string) ( $update->current ?? '' ), $version, '>' ) ) {
            $offer = (string) $update->current;
            break;
        }
    }
    if ( '' !== $offer ) {
        return array(
            'label'       => sprintf( 'WordPress %s is available', $offer ),
            'status'      => 'recommended',
            'badge'       => $badge,
            'description' => sprintf( '<p>This site runs WordPress %s, which is not affected by wp2shell (CVE-2026-63030), but a newer release is out.</p>', esc_html( $version ) ),
            'actions'     => '<p><a href="' . esc_url( admin_url( 'update-core.php' ) ) . '">Dashboard &gt; Updates</a></p>',
            'test'        => 'hr_wp_core_current',
        );
    }
    return array(
        'label'       => sprintf( 'WordPress %s is current and not affected by wp2shell', $version ),
        'status'      => 'good',
        'badge'       => $badge,
        'description' => '<p>wp2shell (CVE-2026-63030) affected WordPress 6.9.0 to 6.9.4 and 7.0.0 to 7.0.1 only.</p>',
        'test'        => 'hr_wp_core_current',
    );
}
