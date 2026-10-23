<?php
/**
 * Plugin Name: Headline Recorder Maintenance
 * Description: Maintenance mode with its own "Maintenance" menu in wp-admin. Visitors get the designed maintenance page with HTTP 503 and Retry-After (search rankings are kept); editors and administrators keep using the site. Optional server-level page that keeps working even when PHP is down.
 * Version:     1.0.0
 * Author:      MSBJ
 *
 * Three layers (docs/MAINTENANCE.md):
 *   1. This file: the switch in wp-admin > Maintenance. Gate runs inside WordPress (template_redirect, REST, AJAX).
 *   2. wp-content/maintenance.php (source: deploy/wp-content/maintenance.php): what WordPress shows while it updates
 *      core, plugins or themes. It reuses hr_maintenance_document() from this file when it can.
 *   3. maintenance.html in the web root (source: deploy/webroot/maintenance.html) served by .htaccess rules with 503
 *      while the file hr-maintenance.on exists (infra/litespeed/maintenance-mode.conf). No PHP involved.
 *
 * hr_maintenance_document() is the single source of the page design. deploy/webroot/maintenance.html is its output
 * with the default copy; scripts/build-theme-zip.sh refuses to build when the two differ.
 *
 * Who still gets through while the WordPress switch is on: signed-in editors and administrators (front end, with
 * a "Maintenance mode is ON" notice), wp-admin, wp-login.php, WP-Cron, WP-CLI, REST and admin-ajax for signed-in
 * users (cookie + nonce or application password, e.g. the hr_signer account), the public signing endpoints
 * (/wp-json/hr/v1/signing/*, /wp-json/hr/v1/canonical*, /signed/*) and /.well-known/. Everything else, feeds
 * included, answers 503.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** Default copy and settings. Pure: also used by the update-time drop-in and the static page build. */
function hr_maintenance_defaults(): array {
    return array(
        'enabled'  => false,
        'headline' => 'We are setting the type for what comes next.',
        'message'  => "Headline Recorder is briefly offline for scheduled maintenance. Our coverage of Pakistan’s economy, markets and policy returns shortly.\n\nThank you for waiting.",
        'email'    => 'contact@headlinerecorder.com',
        'retry'    => 3600,
    );
}

/**
 * The full maintenance page. Pure PHP, no WordPress functions, no external requests: inline CSS and an inline
 * logo, so it renders the same from PHP, from the WordPress update drop-in and as a static file.
 * $c: headline, message (plain text; a blank line starts a new paragraph), email.
 */
function hr_maintenance_document( array $c = array() ): string {
    $d        = hr_maintenance_defaults();
    $e        = static fn( $s ) => htmlspecialchars( (string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
    $headline = trim( (string) ( $c['headline'] ?? '' ) ) ?: $d['headline'];
    $message  = trim( str_replace( array( "\r\n", "\r" ), "\n", (string) ( $c['message'] ?? '' ) ) ) ?: $d['message'];
    $email    = trim( (string) ( $c['email'] ?? '' ) );
    $email    = 1 === preg_match( '/^[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}$/', $email ) ? $email : $d['email'];
    $paras    = '';
    foreach ( preg_split( '/\n\s*\n/', $message ) as $p ) {
        $paras .= '<p>' . nl2br( $e( trim( $p ) ), false ) . "</p>\n";
    }
    $logo = 'data:image/webp;base64,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';

    return '<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<!-- hr-maintenance-page: Headline Recorder maintenance page. Generated by hr_maintenance_document() in wp-content/mu-plugins/hr-maintenance.php. -->
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<meta name="color-scheme" content="dark">
<meta name="theme-color" content="#000000">
<title>Back shortly | Headline Recorder</title>
<link rel="icon" href="data:,">
<style>
:root{--hr-red:#ff001c;--hr-black:#000;--hr-paper:#fff;--hr-muted-dark:#a1a1a8;--hr-line-dark:#1a1a1e;--hr-font-sans:"Source Sans 3","Source Sans Pro",-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;--hr-font-serif:Georgia,"Times New Roman",serif}
*,*::before,*::after{box-sizing:border-box}
html{background:var(--hr-black);color:var(--hr-paper);-webkit-text-size-adjust:100%;text-size-adjust:100%}
body{margin:0;min-height:100vh;min-height:100dvh;display:flex;flex-direction:column;background:var(--hr-black);color:var(--hr-paper);font:400 1.0625rem/1.6 var(--hr-font-sans);-webkit-font-smoothing:antialiased}
main{flex:1;display:flex;align-items:center;justify-content:center;padding:56px max(16px,env(safe-area-inset-right)) 40px max(16px,env(safe-area-inset-left))}
.hr-m{width:100%;max-width:38rem;text-align:center}
.hr-m__logo{display:block;width:120px;height:auto;margin:0 auto}
.hr-m__name{margin:20px 0 0;font-size:.8125rem;font-weight:700;letter-spacing:.28em;text-transform:uppercase;color:var(--hr-paper)}
.hr-m__rule{position:relative;width:112px;height:2px;margin:32px auto;background:var(--hr-line-dark);overflow:hidden}
.hr-m__rule::after{content:"";position:absolute;inset:0;background:var(--hr-red);transform-origin:left center;animation:hr-m-rule 2.8s cubic-bezier(.65,0,.35,1) infinite alternate}
@keyframes hr-m-rule{from{transform:scaleX(.12)}to{transform:scaleX(1)}}
.hr-m__kicker{margin:0 0 14px;color:var(--hr-red);font-size:.8125rem;font-weight:700;letter-spacing:.18em;text-transform:uppercase}
h1{margin:0;font:700 clamp(1.75rem,1.1rem + 2.9vw,3rem)/1.15 var(--hr-font-serif);letter-spacing:-.01em;text-wrap:balance}
.hr-m__msg{margin:22px auto 0;max-width:32rem;color:#c7c7cc;font-size:clamp(1rem,.95rem + .3vw,1.1875rem)}
.hr-m__msg p{margin:0 0 12px;text-wrap:pretty}
.hr-m__msg p:last-child{margin-bottom:0}
.hr-m__contact{margin:36px 0 0;font-size:.9375rem;color:var(--hr-muted-dark)}
a{display:inline-block;padding:10px 4px;margin:-10px -4px;color:var(--hr-paper);text-decoration:underline;text-decoration-color:var(--hr-red);text-decoration-thickness:2px;text-underline-offset:4px;overflow-wrap:anywhere}
a:hover{color:var(--hr-red)}
a:focus-visible{outline:2px solid var(--hr-red);outline-offset:2px;border-radius:2px}
footer{padding:20px max(16px,env(safe-area-inset-right)) max(20px,env(safe-area-inset-bottom)) max(16px,env(safe-area-inset-left));border-top:1px solid var(--hr-line-dark);text-align:center;font-size:.8125rem;color:var(--hr-muted-dark)}
footer p{margin:0}
@media (prefers-reduced-motion:reduce){.hr-m__rule::after{animation:none;transform:none}}
</style>
</head>
<body>
<main>
<div class="hr-m">
<img class="hr-m__logo" src="' . $logo . '" width="120" height="97" alt="Headline Recorder">
<p class="hr-m__name" aria-hidden="true">Headline Recorder</p>
<div class="hr-m__rule" aria-hidden="true"></div>
<p class="hr-m__kicker">Scheduled maintenance</p>
<h1>' . $e( $headline ) . '</h1>
<div class="hr-m__msg">
' . $paras . '</div>
<p class="hr-m__contact">Newsroom: <a href="mailto:' . $e( $email ) . '">' . $e( $email ) . '</a></p>
</div>
</main>
<footer><p>Headline Recorder &middot; Pakistan business and economy news &middot; Karachi</p></footer>
</body>
</html>
';
}

// The update-time drop-in and the static page build only need the template above.
if ( defined( 'HR_MAINTENANCE_TEMPLATE_ONLY' ) ) {
    return;
}

/** Choices for the expected duration (Retry-After, seconds). */
function hr_maintenance_retry_choices(): array {
    return array(
        900   => '15 minutes',
        1800  => '30 minutes',
        3600  => '1 hour',
        7200  => '2 hours',
        14400 => '4 hours',
        43200 => '12 hours',
        86400 => '24 hours',
    );
}

function hr_maintenance_settings(): array {
    $saved = get_option( 'hr_maintenance', array() );
    $s     = array_merge( hr_maintenance_defaults(), is_array( $saved ) ? $saved : array() );
    $s['enabled'] = (bool) $s['enabled'];
    $s['retry']   = isset( hr_maintenance_retry_choices()[ (int) $s['retry'] ] ) ? (int) $s['retry'] : 3600;
    return $s;
}

function hr_maintenance_is_on(): bool {
    return hr_maintenance_settings()['enabled'];
}

/** Signed-in editors and administrators see the site normally. */
function hr_maintenance_can_bypass(): bool {
    return is_user_logged_in() && ( current_user_can( 'edit_others_posts' ) || current_user_can( 'manage_options' ) );
}

/** Request path relative to the site root ("/feed/", "/.well-known/pqsig-keys.json"). */
function hr_maintenance_request_path(): string {
    $path = (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '/' ), PHP_URL_PATH );
    $base = rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
    if ( '' !== $base && str_starts_with( $path, $base ) ) {
        $path = substr( $path, strlen( $base ) );
    }
    return '/' . ltrim( rawurldecode( $path ), '/' );
}

/** Machine endpoints that keep answering during maintenance (signing and ACME/security.txt). */
function hr_maintenance_path_allowed( string $path ): bool {
    if ( str_contains( $path, '..' ) || str_contains( $path, "\0" ) ) {
        return false; // "/.well-known/../2026/story/" must not borrow the allowlist
    }
    $allowed = (array) apply_filters( 'hr_maintenance_allowed_paths', array( '/.well-known/', '/signed/', '/robots.txt' ) );
    foreach ( $allowed as $prefix ) {
        $prefix = (string) $prefix;
        if ( '' !== $prefix && ( $path === $prefix || ( str_ends_with( $prefix, '/' ) && str_starts_with( $path, $prefix ) ) ) ) {
            return true;
        }
    }
    return false;
}

/** Headers that keep the 503 out of every cache (LiteSpeed, Cloudflare, browsers). */
function hr_maintenance_no_store(): void {
    if ( headers_sent() ) {
        return;
    }
    header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
    header( 'CDN-Cache-Control: no-store' );
    header_remove( 'Last-Modified' );
    header_remove( 'ETag' );
    do_action( 'litespeed_control_set_nocache', 'hr-maintenance' );
    if ( ! defined( 'DONOTCACHEPAGE' ) ) {
        define( 'DONOTCACHEPAGE', true );
    }
}

/** Sends the maintenance page and ends the request. */
function hr_maintenance_send( int $status = 503 ): void {
    $s = hr_maintenance_settings();
    if ( ! headers_sent() ) {
        status_header( $status );
        header( 'Content-Type: text/html; charset=utf-8' );
        if ( 503 === $status ) {
            header( 'Retry-After: ' . (int) $s['retry'] );
        }
        header( 'X-Robots-Tag: noindex' );
        hr_maintenance_no_store();
    }
    if ( 'HEAD' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) ) {
        echo hr_maintenance_document( $s ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside
    }
    exit;
}

function hr_maintenance_log( string $event, array $context = array() ): void {
    if ( function_exists( 'HR\\Platform\\log_security' ) ) {
        \HR\Platform\log_security( $event, $context );
        return;
    }
    error_log( 'hr-security ' . wp_json_encode( array( 'hr_event' => $event, 'user' => get_current_user_id() ) + $context ) );
}

/** LiteSpeed Cache and the Cloudflare purge queue (hr-platform edge module), when present. */
function hr_maintenance_purge_caches(): void {
    do_action( 'litespeed_purge_all' );
    if ( function_exists( 'HR\\Platform\\Edge\\queue_everything' ) ) {
        \HR\Platform\Edge\queue_everything();
    }
}

// ---------------------------------------------------------------------------------------------------------------
// The gate
// ---------------------------------------------------------------------------------------------------------------

// Front end: before the platform's virtual routes (priority 1), canonical redirects (10) and feeds/templates.
add_action( 'template_redirect', static function () {
    if ( ! hr_maintenance_is_on() || hr_maintenance_can_bypass() || is_robots() ) {
        return;
    }
    if ( hr_maintenance_path_allowed( hr_maintenance_request_path() ) ) {
        return;
    }
    hr_maintenance_send( 503 );
}, -10000 );

// REST: signed-in users (cookie + nonce, or application password) and the public signing endpoints only.
// Priority 110: after core's application-password (90) and cookie-nonce (100) checks have settled the user.
add_filter( 'rest_authentication_errors', static function ( $result ) {
    // Core returns true for "no credentials, carry on as a guest", so only an error short-circuits here.
    if ( is_wp_error( $result ) || ! hr_maintenance_is_on() || is_user_logged_in() ) {
        return $result;
    }
    $route   = '/' . ltrim( (string) ( $GLOBALS['wp']->query_vars['rest_route'] ?? '' ), '/' );
    $allowed = (array) apply_filters( 'hr_maintenance_allowed_rest', array( '/hr/v1/signing/', '/hr/v1/canonical' ) );
    foreach ( $allowed as $prefix ) {
        if ( '' !== (string) $prefix && str_starts_with( $route, (string) $prefix ) ) {
            return $result;
        }
    }
    if ( ! headers_sent() ) {
        header( 'Retry-After: ' . (int) hr_maintenance_settings()['retry'] );
        hr_maintenance_no_store();
    }
    return new WP_Error( 'hr_maintenance', 'Headline Recorder is briefly offline for scheduled maintenance.', array( 'status' => 503 ) );
}, 110 );

// admin-ajax: signed-in users only (plus the login screen, so no login add-on can lock an administrator out).
add_action( 'admin_init', static function () {
    if ( ! wp_doing_ajax() || ! hr_maintenance_is_on() || is_user_logged_in() ) {
        return;
    }
    $action  = sanitize_key( (string) ( $_REQUEST['action'] ?? '' ) );
    $referer = (string) wp_parse_url( (string) ( $_SERVER['HTTP_REFERER'] ?? '' ), PHP_URL_PATH );
    if ( in_array( $action, (array) apply_filters( 'hr_maintenance_allowed_ajax', array() ), true ) || str_ends_with( $referer, '/wp-login.php' ) ) {
        return;
    }
    if ( ! headers_sent() ) {
        header( 'Retry-After: ' . (int) hr_maintenance_settings()['retry'] );
        hr_maintenance_no_store();
    }
    wp_send_json( array( 'success' => false, 'data' => array( 'code' => 'maintenance' ) ), 503 );
}, 0 );

// ---------------------------------------------------------------------------------------------------------------
// Notices for the people who still see the site
// ---------------------------------------------------------------------------------------------------------------

add_action( 'admin_bar_menu', static function ( WP_Admin_Bar $bar ) {
    if ( ! hr_maintenance_is_on() || ! hr_maintenance_can_bypass() ) {
        return;
    }
    $bar->add_node( array(
        'id'     => 'hr-maintenance',
        'parent' => 'top-secondary',
        'title'  => 'Maintenance mode is ON',
        'href'   => current_user_can( 'manage_options' ) ? admin_url( 'admin.php?page=hr-maintenance' ) : false,
        'meta'   => array( 'class' => 'hr-maintenance-on', 'title' => 'Visitors see the maintenance page. Editors and administrators see the site.' ),
    ) );
}, 5 );

$hr_maintenance_bar_css = static function () {
    if ( hr_maintenance_is_on() && is_admin_bar_showing() ) {
        wp_add_inline_style( 'admin-bar', '#wpadminbar .hr-maintenance-on > .ab-item{background:#c0001a;color:#fff;font-weight:600}#wpadminbar .hr-maintenance-on > .ab-item:hover,#wpadminbar .hr-maintenance-on > .ab-item:focus{background:#ff001c;color:#fff}' );
    }
};
add_action( 'wp_enqueue_scripts', $hr_maintenance_bar_css );
add_action( 'admin_enqueue_scripts', $hr_maintenance_bar_css );
unset( $hr_maintenance_bar_css );

// Editors who hide the toolbar still get a visible reminder on the front end.
add_action( 'wp_footer', static function () {
    if ( hr_maintenance_is_on() && hr_maintenance_can_bypass() && ! is_admin_bar_showing() ) {
        echo '<div role="status" style="position:fixed;left:16px;bottom:16px;z-index:99999;padding:10px 14px;background:#c0001a;color:#fff;font:600 14px/1.4 -apple-system,BlinkMacSystemFont,\'Segoe UI\',sans-serif;border-radius:4px">Maintenance mode is ON: visitors see the maintenance page.</div>';
    }
} );

add_action( 'admin_notices', static function () {
    if ( ! hr_maintenance_is_on() || ! current_user_can( 'edit_others_posts' ) || 'hr-maintenance' === ( $_GET['page'] ?? '' ) ) {
        return;
    }
    $link = current_user_can( 'manage_options' ) ? sprintf( ' <a href="%s">Open Maintenance</a>', esc_url( admin_url( 'admin.php?page=hr-maintenance' ) ) ) : '';
    echo '<div class="notice notice-warning"><p><strong>Maintenance mode is ON.</strong> Visitors see the maintenance page; editors and administrators see the site.' . $link . '</p></div>'; // phpcs:ignore -- static text and escaped URL
} );

add_filter( 'login_message', static function ( $message ) {
    if ( hr_maintenance_is_on() ) {
        $message .= '<p class="message">Maintenance mode is ON. Editors and administrators can sign in and use the site as usual.</p>';
    }
    return $message;
} );

// ---------------------------------------------------------------------------------------------------------------
// wp-admin > Maintenance (a top-level menu of its own)
// ---------------------------------------------------------------------------------------------------------------

add_action( 'admin_menu', static function () {
    $title = 'Maintenance' . ( hr_maintenance_is_on() ? ' <span class="awaiting-mod">ON</span>' : '' );
    add_menu_page( 'Maintenance', $title, 'manage_options', 'hr-maintenance', 'hr_maintenance_admin_page', 'dashicons-hammer', 3 );
} );

/** State changes must come from this site's wp-admin (nonces are the primary check). */
function hr_maintenance_check_request( string $action ): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Not allowed.', 403 );
    }
    check_admin_referer( $action );
    $from  = wp_parse_url( (string) ( $_SERVER['HTTP_ORIGIN'] ?? ( $_SERVER['HTTP_REFERER'] ?? '' ) ) );
    $admin = wp_parse_url( admin_url() );
    $port  = static fn( $u ) => (int) ( $u['port'] ?? ( 'https' === ( $u['scheme'] ?? '' ) ? 443 : 80 ) );
    if ( ! is_array( $from ) || strtolower( (string) ( $from['host'] ?? '' ) ) !== strtolower( (string) ( $admin['host'] ?? '' ) )
        || ( $from['scheme'] ?? '' ) !== ( $admin['scheme'] ?? '' ) || $port( $from ) !== $port( $admin ) ) {
        hr_maintenance_log( 'maintenance_cross_origin', array( 'action' => $action ) );
        wp_die( 'This request did not come from this site.', 403 );
    }
}

/** Validates the posted form into a settings array. */
function hr_maintenance_sanitize( array $in, array $current ): array {
    $d        = hr_maintenance_defaults();
    $headline = mb_substr( sanitize_text_field( (string) ( $in['headline'] ?? '' ) ), 0, 120 );
    $message  = mb_substr( sanitize_textarea_field( (string) ( $in['message'] ?? '' ) ), 0, 600 );
    $email    = sanitize_email( (string) ( $in['email'] ?? '' ) );
    $retry    = (int) ( $in['retry'] ?? 3600 );
    return array(
        'enabled'    => ! empty( $in['enabled'] ),
        'headline'   => '' !== trim( $headline ) ? $headline : $d['headline'],
        'message'    => '' !== trim( $message ) ? $message : $d['message'],
        'email'      => is_email( $email ) ? $email : $d['email'],
        'retry'      => isset( hr_maintenance_retry_choices()[ $retry ] ) ? $retry : 3600,
        'changed_by' => get_current_user_id(),
        'changed_at' => time(),
    ) + $current;
}

/** Turns the WordPress switch on or off and logs it. Used by the screen and by WP-CLI. */
function hr_maintenance_save( array $new ): void {
    $old = hr_maintenance_settings();
    update_option( 'hr_maintenance', $new, true );
    if ( (bool) $old['enabled'] !== (bool) $new['enabled'] ) {
        hr_maintenance_purge_caches();
        hr_maintenance_log( $new['enabled'] ? 'maintenance_on' : 'maintenance_off', array( 'retry' => (int) $new['retry'] ) );
    } else {
        hr_maintenance_log( 'maintenance_updated', array( 'enabled' => (bool) $new['enabled'] ) );
    }
}

add_action( 'admin_post_hr_maintenance_save', static function () {
    hr_maintenance_check_request( 'hr_maintenance_save' );
    $new = hr_maintenance_sanitize( wp_unslash( $_POST ), hr_maintenance_settings() );
    hr_maintenance_save( $new );
    set_transient( 'hr_maintenance_notice_' . get_current_user_id(), $new['enabled'] ? 'Maintenance mode is ON. Visitors now get the maintenance page.' : 'Maintenance mode is OFF. The site is open to everyone.', 60 );
    wp_safe_redirect( admin_url( 'admin.php?page=hr-maintenance' ) );
    exit;
} );

// Preview: the page exactly as visitors would see it (HTTP 200 here), without switching anything on.
add_action( 'admin_post_hr_maintenance_preview', static function () {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Not allowed.', 403 );
    }
    check_admin_referer( 'hr_maintenance_preview' );
    hr_maintenance_send( 200 );
} );

// ---------------------------------------------------------------------------------------------------------------
// Server-level page (no PHP): .htaccess rules + flag file, managed from the same screen
// ---------------------------------------------------------------------------------------------------------------

const HR_MAINTENANCE_MARKER = 'Headline Recorder maintenance';

function hr_maintenance_paths(): array {
    return array(
        'htaccess' => ABSPATH . '.htaccess',
        'flag'     => ABSPATH . 'hr-maintenance.on',
        'static'   => ABSPATH . 'maintenance.html',
    );
}

/**
 * The current administrator's address as visitors' requests will carry it: Cloudflare's CF-Connecting-IP when the
 * request came through Cloudflare, otherwise REMOTE_ADDR. Never both: behind Cloudflare REMOTE_ADDR is a Cloudflare
 * edge address shared by thousands of readers, and allowlisting it would show them the real site.
 */
function hr_maintenance_admin_ips(): array {
    foreach ( array( 'HTTP_CF_CONNECTING_IP', 'REMOTE_ADDR' ) as $key ) {
        $ip = trim( (string) ( $_SERVER[ $key ] ?? '' ) );
        if ( false !== filter_var( $ip, FILTER_VALIDATE_IP ) && 1 === preg_match( '/^[0-9A-Fa-f:.]+$/', $ip ) ) {
            return array( strtolower( $ip ) );
        }
    }
    return array();
}

/**
 * The .htaccess block (without BEGIN/END markers). Inert unless the flag file exists. Same rules as
 * infra/litespeed/maintenance-mode.conf, with this site's absolute flag path and the administrator's IP.
 * Only plain directives that LiteSpeed and every Apache 2.2/2.4 accept (no "expr=", no <If>).
 */
function hr_maintenance_htaccess_lines( array $ips, int $retry, string $flag, string $base = '/' ): array {
    $base  = '/' . trim( $base, '/' );
    $base  = '/' === $base ? '/' : $base . '/';
    $q     = preg_quote( $base, '#' );
    $lines = array(
        '# Managed in wp-admin > Maintenance (server-level page). Inert while the file below does not exist:',
        '#   ' . $flag,
        '# Create that file (cPanel File Manager: + File) to show maintenance.html with HTTP 503 to every visitor;',
        '# delete it to reopen the site. docs/MAINTENANCE.md explains each step.',
        '<IfModule mod_rewrite.c>',
        'RewriteEngine On',
        'RewriteRule ^maintenance\.html$ - [L]',
        'RewriteCond ' . $flag . ' -f',
        'RewriteCond %{REQUEST_URI} !^' . $q . '(wp-admin/|wp-includes/|wp-content/|\.well-known/|signed/|wp-json/hr/v1/(signing/|canonical)|wp-login\.php|wp-cron\.php|robots\.txt)',
    );
    foreach ( $ips as $ip ) {
        $ip = (string) $ip;
        if ( 1 !== preg_match( '/^[0-9A-Fa-f:.]+$/', $ip ) || false === filter_var( $ip, FILTER_VALIDATE_IP ) ) {
            continue; // only hex digits, dots and colons ever reach .htaccess
        }
        $re      = '^' . str_replace( '.', '\\.', $ip ) . '$';
        $lines[] = 'RewriteCond %{REMOTE_ADDR} !' . $re . ' [NC]';
        $lines[] = 'RewriteCond %{HTTP:CF-Connecting-IP} !' . $re . ' [NC]';
    }
    return array_merge( $lines, array(
        'RewriteRule ^ - [R=503,L]',
        '</IfModule>',
        'ErrorDocument 503 ' . $base . 'maintenance.html',
        '<IfModule mod_headers.c>',
        '<Files "maintenance.html">',
        'Header always set Retry-After "' . max( 60, (int) $retry ) . '"',
        'Header always set Cache-Control "no-store"',
        'Header always set X-Robots-Tag "noindex"',
        '</Files>',
        '</IfModule>',
    ) );
}

/** Splits a leading UTF-8 byte order mark off, so it stays the first thing in the file. */
function hr_maintenance_split_bom( string $text ): array {
    return str_starts_with( $text, "\xEF\xBB\xBF" ) ? array( "\xEF\xBB\xBF", substr( $text, 3 ) ) : array( '', $text );
}

/**
 * Pure: puts the marked block at the very top (rules below "# BEGIN WordPress" would never run). Everything else in
 * the file (LiteSpeed Cache, All-In-One WP Security, WordPress blocks) is kept byte for byte, a byte order mark stays
 * first and the block uses the file's own line endings. hr_maintenance_htaccess_without_block() undoes it exactly.
 */
function hr_maintenance_htaccess_with_block( string $existing, array $lines ): string {
    [ $bom, $body ] = hr_maintenance_split_bom( $existing );
    $body = hr_maintenance_htaccess_without_block( $body );
    $eol  = str_contains( $body, "\r\n" ) ? "\r\n" : "\n";
    return $bom . '# BEGIN ' . HR_MAINTENANCE_MARKER . $eol . implode( $eol, $lines ) . $eol . '# END ' . HR_MAINTENANCE_MARKER . $eol . $eol . $body;
}

function hr_maintenance_htaccess_without_block( string $existing ): string {
    [ $bom, $body ] = hr_maintenance_split_bom( $existing );
    $m  = preg_quote( HR_MAINTENANCE_MARKER, '/' );
    $re = '/^# BEGIN ' . $m . '[ \t]*\R.*?^# END ' . $m . '[ \t]*(?:\R\R?|\z)/sm'; // the block and the blank line we added
    return $bom . (string) preg_replace( $re, '', $body, 1 );
}

/** Writes a file atomically (static page). */
function hr_maintenance_write( string $file, string $content ): bool {
    $tmp = $file . '.hr-tmp-' . wp_generate_password( 8, false );
    if ( false === @file_put_contents( $tmp, $content, LOCK_EX ) || ! @rename( $tmp, $file ) ) {
        @unlink( $tmp );
        return false;
    }
    @chmod( $file, 0644 );
    return true;
}

/**
 * Read-modify-write of .htaccess in place under an exclusive flock, the way WordPress core (insert_with_markers),
 * LiteSpeed Cache and All-In-One WP Security write it, so concurrent writers wait for each other instead of losing
 * changes. Follows a symlink, keeps owner and permissions. Returns array( old, new ) or an error string.
 */
function hr_maintenance_edit_htaccess( string $file, callable $change ) {
    if ( file_exists( $file ) && ( ! is_readable( $file ) || ! is_writable( $file ) ) ) {
        return 'WordPress cannot write .htaccess (its permissions do not allow it).';
    }
    $fp = @fopen( $file, 'c+' ); // creates it when missing, never truncates on open
    if ( ! $fp ) {
        return 'Could not open .htaccess for writing.';
    }
    if ( ! flock( $fp, LOCK_EX ) ) {
        fclose( $fp );
        return 'Could not lock .htaccess.';
    }
    $old = (string) stream_get_contents( $fp );
    $new = (string) $change( $old );
    $ok  = true;
    if ( $new !== $old ) {
        $put = static function ( string $data ) use ( $fp ): bool {
            if ( ! rewind( $fp ) || ! ftruncate( $fp, 0 ) ) {
                return false;
            }
            for ( $done = 0, $len = strlen( $data ); $done < $len; $done += $n ) {
                $n = fwrite( $fp, substr( $data, $done ) );
                if ( ! $n ) {
                    return false;
                }
            }
            return fflush( $fp );
        };
        $ok = $put( $new );
        if ( ! $ok ) {
            $put( $old ); // disk full or quota: put the previous text back
        }
    }
    flock( $fp, LOCK_UN );
    fclose( $fp );
    return $ok ? array( $old, $new ) : 'Could not write .htaccess (disk full or quota reached?). It was left as it was.';
}

/** One server-level change at a time (two administrators, or a double click). */
function hr_maintenance_lock() {
    $fp = @fopen( trailingslashit( get_temp_dir() ) . 'hr-maintenance-' . md5( ABSPATH ) . '.lock', 'c' );
    if ( $fp && flock( $fp, LOCK_EX | LOCK_NB ) ) {
        return $fp;
    }
    if ( $fp ) {
        fclose( $fp );
    }
    return false;
}

/**
 * Requests $path from this web server and returns the answers: 'origin' straight to this server's own address
 * (Cloudflare, its cache and its bot checks are not in the way) and 'public' through the site's address. A random
 * query string keeps every cache out. 0 = no answer (loopback blocked, timeout, DNS).
 */
function hr_maintenance_probe( string $path ): array {
    $url  = add_query_arg( 'hr-maint-check', wp_generate_password( 12, false ), site_url( $path ) );
    $args = array( 'timeout' => 5, 'redirection' => 0, 'sslverify' => false, 'reject_unsafe_urls' => false, 'headers' => array( 'Cache-Control' => 'no-cache' ) );
    $code = static function ( $r ): int {
        return is_wp_error( $r ) ? 0 : (int) wp_remote_retrieve_response_code( $r );
    };
    $out  = array( 'origin' => 0, 'public' => 0, 'body' => '' );
    $addr = trim( (string) ( $_SERVER['SERVER_ADDR'] ?? '' ) );
    $host = (string) wp_parse_url( $url, PHP_URL_HOST );
    if ( '' !== $host && false !== filter_var( $addr, FILTER_VALIDATE_IP ) && defined( 'CURLOPT_RESOLVE' ) ) {
        $port    = (int) ( wp_parse_url( $url, PHP_URL_PORT ) ?: ( 'https' === wp_parse_url( $url, PHP_URL_SCHEME ) ? 443 : 80 ) );
        $resolve = static function ( $handle ) use ( $host, $port, $addr ) {
            curl_setopt( $handle, CURLOPT_RESOLVE, array( $host . ':' . $port . ':' . ( str_contains( $addr, ':' ) ? '[' . $addr . ']' : $addr ) ) );
        };
        add_action( 'http_api_curl', $resolve );
        $r = wp_remote_get( $url, $args );
        remove_action( 'http_api_curl', $resolve );
        $out['origin'] = $code( $r );
        $out['body']   = is_wp_error( $r ) ? '' : (string) wp_remote_retrieve_body( $r );
    }
    $r = wp_remote_get( $url, $args );
    $out['public'] = $code( $r );
    if ( '' === $out['body'] && ! is_wp_error( $r ) ) {
        $out['body'] = (string) wp_remote_retrieve_body( $r );
    }
    return $out;
}

/**
 * Did the server read the new .htaccess without complaint? 'ok' only on proof: the origin answered a static file
 * with anything but a server error, or the public address answered it with 200. 'broken' on a 500. Anything else
 * (loopback blocked, Cloudflare challenge, timeouts) is 'unknown', and unknown is treated like broken.
 */
function hr_maintenance_verdict( array $probe ): string {
    if ( 500 === $probe['origin'] || 500 === $probe['public'] ) {
        return 'broken';
    }
    if ( ( $probe['origin'] >= 200 && $probe['origin'] < 500 ) || 200 === $probe['public'] ) {
        return 'ok';
    }
    return 'unknown';
}

/** Takes our block out again (used when the self-check fails). Other writers' changes since then are kept. */
function hr_maintenance_revert_rules( string $file ): bool {
    return is_array( hr_maintenance_edit_htaccess( $file, 'hr_maintenance_htaccess_without_block' ) );
}

/**
 * Turns the server-level page on: static page, rules at the top of .htaccess (self-checked, removed again unless
 * the server provably still works), then the flag file (checked again: removed if the site answers 500 or ignores it).
 */
function hr_maintenance_server_on(): string {
    $p = hr_maintenance_paths();
    if ( 1 !== preg_match( '#^[A-Za-z0-9_./\-]+$#', $p['flag'] ) ) {
        return 'error:The site path contains characters .htaccess cannot use safely. Use the manual steps in docs/MAINTENANCE.md.';
    }
    $lock = hr_maintenance_lock();
    if ( ! $lock ) {
        return 'error:Another maintenance change is running. Wait a minute and reload this page.';
    }
    try {
        if ( hr_maintenance_is_ours( $p['static'], 'hr-maintenance-page' ) && ! hr_maintenance_write( $p['static'], hr_maintenance_document( hr_maintenance_settings() ) ) ) {
            return 'error:Could not write ' . $p['static'] . '. Nothing changed.';
        }
        $base   = (string) wp_parse_url( site_url( '/' ), PHP_URL_PATH );
        $lines  = hr_maintenance_htaccess_lines( hr_maintenance_admin_ips(), (int) hr_maintenance_settings()['retry'], $p['flag'], $base ?: '/' );
        $backup = $p['htaccess'] . '.hr-maintenance-backup';
        $saved  = true;
        $edit   = hr_maintenance_edit_htaccess( $p['htaccess'], static function ( string $old ) use ( $lines, $backup, &$saved ) {
            $new = hr_maintenance_htaccess_with_block( $old, $lines );
            if ( $new !== $old && '' !== $old ) {
                // The file exactly as it was before this change; overwritten on each change, readable by the owner only.
                $saved = false !== @file_put_contents( $backup, $old, LOCK_EX );
                @chmod( $backup, 0600 );
                if ( ! $saved ) {
                    return $old;
                }
            }
            return $new;
        } );
        if ( ! $saved ) {
            return 'error:Could not save the backup ' . $backup . ', so .htaccess was not changed.';
        }
        if ( ! is_array( $edit ) ) {
            return 'error:' . $edit . ' Nothing changed. Paste bundled/maintenance-mode.conf from the theme at the top of .htaccess in cPanel File Manager instead (docs/MAINTENANCE.md).';
        }
        if ( $edit[0] !== $edit[1] ) {
            // Self-check on a static file the block never touches: the server must still answer it.
            $verdict = hr_maintenance_verdict( hr_maintenance_probe( '/wp-includes/images/blank.gif' ) );
            if ( 'ok' !== $verdict ) {
                $undone = hr_maintenance_revert_rules( $p['htaccess'] );
                hr_maintenance_log( 'maintenance_server_rules_reverted', array( 'verdict' => $verdict, 'undone' => $undone ) );
                if ( ! $undone ) {
                    return 'error:The server could not be checked after the change and the block could not be taken out again. In cPanel File Manager, open .htaccess now and delete everything from "# BEGIN ' . HR_MAINTENANCE_MARKER . '" to "# END ' . HR_MAINTENANCE_MARKER . '" (a copy of the previous file is .htaccess.hr-maintenance-backup).';
                }
                return 'broken' === $verdict
                    ? 'error:The server rejected the new .htaccess rules (HTTP 500), so they were taken out again at once. Nothing changed.'
                    : 'error:WordPress could not confirm that the server still works after the change (this server does not answer requests from itself, or Cloudflare stopped them), so the rules were taken out again. Nothing changed. Use the manual steps in docs/MAINTENANCE.md ("With cPanel only") instead.';
            }
        }
        if ( ! @touch( $p['flag'] ) ) {
            return 'error:The rules are in place, but ' . $p['flag'] . ' could not be created. Create that empty file in cPanel File Manager to switch the page on.';
        }
        // Second check, now that the page is on: the home page must answer 503 (and never 500).
        $live = hr_maintenance_probe( '/' );
        if ( 500 === $live['origin'] || 500 === $live['public'] || 200 === $live['origin'] ) {
            @unlink( $p['flag'] );
            hr_maintenance_log( 'maintenance_server_on_failed', array( 'origin' => $live['origin'], 'public' => $live['public'] ) );
            return 500 === $live['origin'] || 500 === $live['public']
                ? 'error:With the page switched on the server answered HTTP 500, so it was switched off again at once. The site is open as before.'
                : 'error:This server ignores the rules (the home page still opened normally), so the switch was turned off again. Use the WordPress switch above instead.';
        }
        hr_maintenance_purge_caches();
        hr_maintenance_log( 'maintenance_server_on', array( 'allowlisted_ips' => count( hr_maintenance_admin_ips() ), 'checked' => 503 === $live['origin'] || 503 === $live['public'] ) );
        $note = 503 === $live['origin'] || 503 === $live['public'] ? '' : ' WordPress could not double-check it from the server: open the site in a private window to confirm.';
        return 'ok:Server-level maintenance page is ON. It stays up even if PHP stops working. Your current IP address still sees the site; wp-admin and wp-login.php stay reachable for everyone.' . $note;
    } finally {
        flock( $lock, LOCK_UN );
        fclose( $lock );
    }
}

function hr_maintenance_server_off(): string {
    $p = hr_maintenance_paths();
    if ( file_exists( $p['flag'] ) && ! @unlink( $p['flag'] ) ) {
        return 'error:Could not delete ' . $p['flag'] . '. Delete it in cPanel File Manager.';
    }
    hr_maintenance_purge_caches();
    hr_maintenance_log( 'maintenance_server_off' );
    return 'ok:Server-level maintenance page is OFF. The rules stay in .htaccess but do nothing until it is switched on again.';
}

function hr_maintenance_server_remove(): string {
    $p = hr_maintenance_paths();
    $lock = hr_maintenance_lock();
    if ( ! $lock ) {
        return 'error:Another maintenance change is running. Wait a minute and reload this page.';
    }
    try {
        if ( file_exists( $p['flag'] ) && ! @unlink( $p['flag'] ) ) {
            return 'error:Could not delete ' . $p['flag'] . '. Delete it in cPanel File Manager first.';
        }
        if ( file_exists( $p['htaccess'] ) ) {
            $edit = hr_maintenance_edit_htaccess( $p['htaccess'], 'hr_maintenance_htaccess_without_block' );
            if ( ! is_array( $edit ) ) {
                return 'error:' . $edit;
            }
        }
        hr_maintenance_log( 'maintenance_server_rules_removed' );
        return 'ok:The server-level rules were removed from .htaccess.';
    } finally {
        flock( $lock, LOCK_UN );
        fclose( $lock );
    }
}

add_action( 'admin_post_hr_maintenance_server', static function () {
    hr_maintenance_check_request( 'hr_maintenance_server' );
    $do  = sanitize_key( (string) ( $_POST['do'] ?? '' ) );
    $msg = match ( $do ) {
        'on'     => hr_maintenance_server_on(),
        'off'    => hr_maintenance_server_off(),
        'remove' => hr_maintenance_server_remove(),
        default  => 'error:Unknown action.',
    };
    set_transient( 'hr_maintenance_notice_' . get_current_user_id(), $msg, 60 );
    wp_safe_redirect( admin_url( 'admin.php?page=hr-maintenance#hr-server-level' ) );
    exit;
} );

// ---------------------------------------------------------------------------------------------------------------
// Screen
// ---------------------------------------------------------------------------------------------------------------

function hr_maintenance_admin_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $s      = hr_maintenance_settings();
    $server = hr_maintenance_server_state();
    $notice = (string) get_transient( 'hr_maintenance_notice_' . get_current_user_id() );
    delete_transient( 'hr_maintenance_notice_' . get_current_user_id() );
    $is_err = str_starts_with( $notice, 'error:' );
    $notice = preg_replace( '/^(ok|error):/', '', $notice );
    $post   = esc_url( admin_url( 'admin-post.php' ) );
    $prev   = wp_nonce_url( admin_url( 'admin-post.php?action=hr_maintenance_preview' ), 'hr_maintenance_preview' );
    ?>
    <div class="wrap hr-maint">
        <style>
            .hr-maint .hr-maint-state{display:flex;flex-wrap:wrap;align-items:center;gap:12px 20px;max-width:760px;margin:16px 0 8px;padding:16px 20px;background:#fff;border:1px solid #dcdcde;border-left:4px solid <?php echo $s['enabled'] ? '#c0001a' : '#00875a'; ?>}
            .hr-maint .hr-maint-state strong{font-size:15px}
            .hr-maint .hr-switch{display:inline-flex;align-items:center;gap:10px;min-height:44px;cursor:pointer;font-weight:600}
            .hr-maint .hr-switch input{appearance:none;-webkit-appearance:none;position:relative;width:46px;height:26px;margin:0;border-radius:13px;background:#8c8f94;cursor:pointer;transition:background .15s}
            .hr-maint .hr-switch input::before{content:"";position:absolute;top:3px;left:3px;width:20px;height:20px;border-radius:50%;background:#fff;transition:transform .15s}
            .hr-maint .hr-switch input:checked{background:#c0001a}
            .hr-maint .hr-switch input:checked::before{transform:translateX(20px)}
            .hr-maint .hr-switch input:focus-visible{outline:2px solid #2271b1;outline-offset:2px}
            .hr-maint .form-table{max-width:760px}
            .hr-maint .hr-maint-list{list-style:disc;margin-left:20px;max-width:760px}
            .hr-maint .hr-maint-actions{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
            .hr-maint .hr-maint-actions .button{min-height:36px}
            @media (prefers-reduced-motion:reduce){.hr-maint .hr-switch input,.hr-maint .hr-switch input::before{transition:none}}
        </style>
        <h1>Maintenance</h1>
        <?php if ( '' !== $notice ) : ?>
            <div class="notice <?php echo $is_err ? 'notice-error' : 'notice-success'; ?> is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
        <?php endif; ?>

        <div class="hr-maint-state" role="status">
            <strong><?php echo $s['enabled'] ? 'Maintenance mode is ON.' : 'Maintenance mode is OFF.'; ?></strong>
            <span><?php echo $s['enabled'] ? 'Visitors get the maintenance page (HTTP 503, so search rankings are kept). Editors and administrators see the site.' : 'The site is open to everyone.'; ?></span>
            <a class="button" href="<?php echo esc_url( $prev ); ?>" target="_blank" rel="noopener">Preview the page</a>
        </div>

        <form method="post" action="<?php echo $post; ?>">
            <input type="hidden" name="action" value="hr_maintenance_save">
            <?php wp_nonce_field( 'hr_maintenance_save' ); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Maintenance mode</th>
                    <td>
                        <label class="hr-switch"><input type="checkbox" role="switch" name="enabled" value="1" <?php checked( $s['enabled'] ); ?>> <span>Show the maintenance page to visitors</span></label>
                        <p class="description">Takes effect when you press Save. LiteSpeed Cache and the Cloudflare queue are purged automatically.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="hr-maint-headline">Headline</label></th>
                    <td><input type="text" class="large-text" id="hr-maint-headline" name="headline" maxlength="120" value="<?php echo esc_attr( $s['headline'] ); ?>">
                        <p class="description">Up to 120 characters.</p></td>
                </tr>
                <tr>
                    <th scope="row"><label for="hr-maint-message">Message</label></th>
                    <td><textarea class="large-text" rows="4" id="hr-maint-message" name="message" maxlength="600"><?php echo esc_textarea( $s['message'] ); ?></textarea>
                        <p class="description">Plain text, up to 600 characters. A blank line starts a new paragraph. Keep it free of technical detail.</p></td>
                </tr>
                <tr>
                    <th scope="row"><label for="hr-maint-email">Contact email</label></th>
                    <td><input type="email" class="regular-text" id="hr-maint-email" name="email" maxlength="190" value="<?php echo esc_attr( $s['email'] ); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="hr-maint-retry">Expected to take</label></th>
                    <td><select id="hr-maint-retry" name="retry">
                        <?php foreach ( hr_maintenance_retry_choices() as $sec => $label ) : ?>
                            <option value="<?php echo (int) $sec; ?>" <?php selected( $s['retry'], $sec ); ?>><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">Tells search engines when to come back (the Retry-After header). Not shown to visitors.</p></td>
                </tr>
            </table>
            <?php submit_button( 'Save' ); ?>
        </form>

        <h2>What keeps working while it is on</h2>
        <ul class="hr-maint-list">
            <li>Signed-in editors and administrators see the whole site, with a red "Maintenance mode is ON" label in the toolbar.</li>
            <li>wp-admin, the sign-in page (wp-login.php), scheduled tasks (WP-Cron) and WP-CLI.</li>
            <li>The article signer and the public signing endpoints (/.well-known/, /signed/, the signing REST API).</li>
            <li>Feeds, pages and articles answer 503 for visitors, which tells Google the outage is temporary.</li>
        </ul>

        <h2 id="hr-server-level">Server-level page (works even if PHP fails)</h2>
        <p style="max-width:760px">For PHP update day. Switch this on before changing the PHP version in cPanel: the web server then shows a static
            copy of the maintenance page by itself, so visitors see it even if PHP or WordPress stops responding.
            Your current IP address (<code><?php echo esc_html( implode( ', ', hr_maintenance_admin_ips() ) ?: 'unknown' ); ?></code>) still sees the
            real site, and wp-admin stays reachable. If wp-admin is down, the same switch is a file: create or delete
            <code>hr-maintenance.on</code> in the site folder with cPanel File Manager.</p>
        <table class="widefat striped" style="max-width:760px">
            <tr><td>Rules in .htaccess</td><td><?php echo $server['rules'] ? 'installed' : 'not installed'; ?></td></tr>
            <tr><td>Static page (maintenance.html)</td><td><?php echo $server['static'] ? ( $server['ours'] ? 'installed' : 'present (not ours: left untouched)' ) : 'missing (created when you switch on)'; ?></td></tr>
            <tr><td>Server-level page</td><td><strong><?php echo $server['flag'] && $server['rules'] ? 'ON' : 'OFF'; ?></strong></td></tr>
        </table>
        <form method="post" action="<?php echo $post; ?>" class="hr-maint-actions" style="margin-top:12px">
            <input type="hidden" name="action" value="hr_maintenance_server">
            <?php wp_nonce_field( 'hr_maintenance_server' ); ?>
            <?php if ( $server['flag'] && $server['rules'] ) : ?>
                <button type="submit" class="button button-primary" name="do" value="off">Turn server-level page off</button>
            <?php else : ?>
                <button type="submit" class="button" name="do" value="on">Turn server-level page on</button>
            <?php endif; ?>
            <?php if ( $server['rules'] ) : ?>
                <button type="submit" class="button-link" name="do" value="remove">Remove the rules from .htaccess</button>
            <?php endif; ?>
        </form>
        <p class="description" style="max-width:760px">Uses mod_rewrite and mod_headers (LiteSpeed and Apache). WordPress checks the site right after writing
            .htaccess and puts the old file back if the server answers with an error. The local PHP development server ignores .htaccess.</p>
    </div>
    <?php
}

// ---------------------------------------------------------------------------------------------------------------
// WP-CLI: wp hr-maintenance on|off|status
// ---------------------------------------------------------------------------------------------------------------

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    WP_CLI::add_command( 'hr-maintenance', static function ( array $args ) {
        $do = $args[0] ?? 'status';
        $s  = hr_maintenance_settings();
        if ( in_array( $do, array( 'on', 'off' ), true ) ) {
            hr_maintenance_save( array( 'enabled' => 'on' === $do, 'changed_at' => time() ) + $s );
            WP_CLI::success( 'Maintenance mode is ' . strtoupper( $do ) . '.' );
            return;
        }
        if ( 'status' !== $do ) {
            WP_CLI::error( 'Usage: wp hr-maintenance on|off|status' );
        }
        $server = hr_maintenance_server_state();
        WP_CLI::line( 'WordPress switch: ' . ( $s['enabled'] ? 'ON' : 'OFF' ) . ' (Retry-After ' . $s['retry'] . 's)' );
        WP_CLI::line( 'Server-level page: ' . ( $server['flag'] && $server['rules'] ? 'ON' : 'OFF' ) . ( $server['rules'] ? '' : ' (rules not installed)' ) );
    }, array( 'shortdesc' => 'Turns Headline Recorder maintenance mode on or off, or shows its state.' ) );
}
