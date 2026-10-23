<?php
/**
 * hr-maintenance-dropin: Headline Recorder page shown while WordPress updates core, plugins or themes.
 *
 * WordPress loads wp-content/maintenance.php by itself, very early (before plugins and the database), whenever an
 * update has left the ".maintenance" file in the site folder, and stops right after it. So this file can use plain
 * PHP only. It answers HTTP 503 with Retry-After so search engines treat the pause as temporary.
 *
 * Source: deploy/wp-content/maintenance.php in the repository. The theme installer copies it to
 * wp-content/maintenance.php, but only when that file is missing or is this one (it never replaces another
 * drop-in). The design comes from hr_maintenance_document() in the hr-maintenance must-use plugin; if that file is
 * missing or broken, the static maintenance.html in the site folder is used, then a minimal built-in page.
 * Author: MSBJ.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! headers_sent() ) {
    $hr_protocol = (string) ( $_SERVER['SERVER_PROTOCOL'] ?? '' );
    if ( ! in_array( $hr_protocol, array( 'HTTP/1.0', 'HTTP/1.1', 'HTTP/2', 'HTTP/2.0', 'HTTP/3' ), true ) ) {
        $hr_protocol = 'HTTP/1.1';
    }
    header( $hr_protocol . ' 503 Service Unavailable', true, 503 );
    header( 'Content-Type: text/html; charset=utf-8' );
    header( 'Retry-After: 600' ); // updates usually finish within minutes; WordPress itself gives up after 10
    header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
    header( 'CDN-Cache-Control: no-store' );
    header( 'X-Robots-Tag: noindex' );
}

if ( ! function_exists( 'hr_maintenance_document' ) ) {
    $hr_mu = ( defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins' ) . '/hr-maintenance.php';
    if ( is_readable( $hr_mu ) ) {
        if ( ! defined( 'HR_MAINTENANCE_TEMPLATE_ONLY' ) ) {
            define( 'HR_MAINTENANCE_TEMPLATE_ONLY', true );
        }
        try {
            include_once $hr_mu;
        } catch ( \Throwable $e ) {
            // A half-copied file during an update: fall through to the static page.
        }
    }
}

if ( 'HEAD' === strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) ) {
    return;
}

if ( function_exists( 'hr_maintenance_document' ) ) {
    echo hr_maintenance_document(); // escaped inside
    return;
}

if ( is_readable( ABSPATH . 'maintenance.html' ) ) {
    readfile( ABSPATH . 'maintenance.html' );
    return;
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Back shortly | Headline Recorder</title>
<style>
body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:16px;background:#000;color:#fff;font:400 1.0625rem/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;text-align:center}
p{color:#c7c7cc}
.k{color:#ff001c;font-weight:700;letter-spacing:.18em;text-transform:uppercase;font-size:.8125rem}
h1{font:700 clamp(1.75rem,1.1rem + 2.9vw,3rem)/1.15 Georgia,"Times New Roman",serif;margin:0 0 16px}
a{color:#fff}
</style>
</head>
<body>
<main>
<p class="k">Headline Recorder</p>
<h1>We are setting the type for what comes next.</h1>
<p>We are briefly offline for scheduled maintenance. Thank you for waiting.</p>
<p>Newsroom: <a href="mailto:contact@headlinerecorder.com">contact@headlinerecorder.com</a></p>
</main>
</body>
</html>
