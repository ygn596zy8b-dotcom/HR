<?php
/**
 * Plugin Name: Headline Recorder — Hardening
 * Description: Cookie flags, Argon2id password hashing, enumeration and XML-RPC lockdown, security headers and CSP. Loads before all plugins.
 * Version:     1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ---------------------------------------------------------------------------------------------------------------
// Never show PHP errors to visitors outside local development (the live wp-config had WP_DEBUG_DISPLAY on).
// Errors still reach the log and Sentry.
// ---------------------------------------------------------------------------------------------------------------
if ( 'local' !== wp_get_environment_type() ) {
    @ini_set( 'display_errors', '0' );
}
@header_remove( 'X-Powered-By' );

// ---------------------------------------------------------------------------------------------------------------
// Passwords: Argon2id for every new or rehashed password (OWASP minimum profile: 19 MiB, t=2, p=1 — sized for
// shared hosting). Existing bcrypt/phpass hashes keep working and are upgraded transparently at next login.
// ---------------------------------------------------------------------------------------------------------------
if ( defined( 'PASSWORD_ARGON2ID' ) ) {
    add_filter( 'wp_hash_password_algorithm', static fn() => PASSWORD_ARGON2ID );
    add_filter( 'wp_hash_password_options', static function ( $options, $algorithm ) {
        return PASSWORD_ARGON2ID === $algorithm ? array( 'memory_cost' => 19456, 'time_cost' => 2, 'threads' => 1 ) : $options;
    }, 10, 2 );
}

// ---------------------------------------------------------------------------------------------------------------
// Auth cookies: WordPress sets Secure + HttpOnly but no SameSite. We send the same cookies ourselves with
// SameSite=Strict on the admin-path auth cookie (a cross-site link into wp-admin asks for the password again) and
// SameSite=Lax on the site-wide logged-in cookie (arriving from a link keeps you signed in on the front end).
// Plus a non-secret `hr_li` hint cookie so cached pages know, in JavaScript, whether to fetch account data.
// ---------------------------------------------------------------------------------------------------------------
add_action( 'set_auth_cookie', static function ( $cookie, $expire ) {
    $GLOBALS['hr_pending_auth_cookie'] = array( $cookie, (int) $expire );
}, 10, 2 );
add_action( 'set_logged_in_cookie', static function ( $cookie, $expire ) {
    $GLOBALS['hr_pending_logged_in_cookie'] = array( $cookie, (int) $expire );
}, 10, 2 );

add_filter( 'send_auth_cookies', static function ( $send, $expire = 0, $expiration = 0, $user_id = 0, $scheme = '' ) {
    // Clearing (user 0) or another plugin already took over: leave WordPress's behaviour alone.
    if ( ! $send || ! $user_id || empty( $GLOBALS['hr_pending_auth_cookie'] ) || empty( $GLOBALS['hr_pending_logged_in_cookie'] ) || headers_sent() ) {
        return $send;
    }
    [ $auth, $auth_expire ] = $GLOBALS['hr_pending_auth_cookie'];
    [ $logged_in, $li_expire ] = $GLOBALS['hr_pending_logged_in_cookie'];
    unset( $GLOBALS['hr_pending_auth_cookie'], $GLOBALS['hr_pending_logged_in_cookie'] );

    $secure    = 'secure_auth' === $scheme;
    $auth_name = $secure ? SECURE_AUTH_COOKIE : AUTH_COOKIE;
    /** This filter is documented in wp-includes/pluggable.php */
    $secure_li = apply_filters( 'secure_logged_in_cookie', $secure && 'https' === wp_parse_url( get_option( 'home' ), PHP_URL_SCHEME ), $user_id, $secure );

    $opts = static fn( $expires, $path, $is_secure, $samesite, $httponly = true ) => array(
        'expires'  => $expires,
        'path'     => $path,
        'domain'   => COOKIE_DOMAIN ?: '',
        'secure'   => (bool) $is_secure,
        'httponly' => $httponly,
        'samesite' => $samesite,
    );
    setcookie( $auth_name, $auth, $opts( $auth_expire, PLUGINS_COOKIE_PATH, $secure, 'Strict' ) );
    setcookie( $auth_name, $auth, $opts( $auth_expire, ADMIN_COOKIE_PATH, $secure, 'Strict' ) );
    setcookie( LOGGED_IN_COOKIE, $logged_in, $opts( $li_expire, COOKIEPATH, $secure_li, 'Lax' ) );
    if ( COOKIEPATH !== SITECOOKIEPATH ) {
        setcookie( LOGGED_IN_COOKIE, $logged_in, $opts( $li_expire, SITECOOKIEPATH, $secure_li, 'Lax' ) );
    }
    setcookie( 'hr_li', '1', $opts( $li_expire, COOKIEPATH, $secure_li, 'Lax', false ) );
    return false; // We sent them; stop WordPress sending duplicates without SameSite.
}, 99, 5 );

add_action( 'clear_auth_cookie', static function () {
    if ( ! headers_sent() ) {
        setcookie( 'hr_li', '', array( 'expires' => time() - YEAR_IN_SECONDS, 'path' => COOKIEPATH, 'domain' => COOKIE_DOMAIN ?: '', 'samesite' => 'Lax' ) );
    }
} );

// On sign-out, tell the browser to drop anything cached or stored for this site (shared computers).
add_action( 'wp_logout', static function () {
    if ( ! headers_sent() ) {
        header( 'Clear-Site-Data: "cache", "storage"' );
    }
} );

// ---------------------------------------------------------------------------------------------------------------
// Attack surface: XML-RPC (brute-force amplification, pingback DDoS), user enumeration, version disclosure.
// ---------------------------------------------------------------------------------------------------------------
if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
    // Nothing on this site uses XML-RPC (no Jetpack, no mobile app). Refuse before any method, including system.multicall.
    status_header( 403 );
    header( 'Content-Type: text/plain; charset=utf-8' );
    exit( 'XML-RPC is disabled.' );
}
add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter( 'xmlrpc_methods', static fn() => array() );
add_filter( 'wp_headers', static function ( $headers ) {
    unset( $headers['X-Pingback'] );
    return $headers;
} );
add_filter( 'pings_open', '__return_false' );
remove_action( 'wp_head', 'wp_generator' );
add_filter( 'the_generator', '__return_empty_string' );

// /wp-json/wp/v2/users lists usernames to anyone; only staff (the block editor's author picker needs it) may see it.
add_filter( 'rest_endpoints', static function ( $endpoints ) {
    if ( current_user_can( 'edit_posts' ) ) {
        return $endpoints;
    }
    foreach ( array_keys( $endpoints ) as $route ) {
        if ( preg_match( '#^/wp/v2/users(?:/\(\?P<id>[^)]+\)|/me)?$#', $route ) && '/wp/v2/users/me' !== $route ) {
            unset( $endpoints[ $route ] );
        }
    }
    return $endpoints;
} );

// /?author=1 redirects to /author/<username>/, leaking logins. Guests get the home page instead.
add_action( 'template_redirect', static function () {
    if ( ! is_user_logged_in() && isset( $_GET['author'] ) && ! is_admin() ) {
        wp_safe_redirect( home_url( '/' ), 301 );
        exit;
    }
}, 0 );

// oEmbed responses include the author's display name and profile URL; keep the name, drop the URL.
add_filter( 'oembed_response_data', static function ( $data ) {
    unset( $data['author_url'] );
    return $data;
} );

// Same message for "no such user" and "wrong password" on wp-login.php.
add_filter( 'login_errors', static function ( $error ) {
    global $errors;
    $codes = is_wp_error( $errors ) ? $errors->get_error_codes() : array();
    return array_intersect( $codes, array( 'invalid_username', 'invalid_email', 'incorrect_password' ) ) ? '<strong>Error:</strong> The email/username or password is incorrect.' : $error;
} );

// Raw HTML/JS in posts only for administrators. A stolen editor account then cannot plant scripts site-wide.
add_filter( 'map_meta_cap', static function ( $caps, $cap, $user_id ) {
    if ( 'unfiltered_html' === $cap && ! user_can( (int) $user_id, 'manage_options' ) ) {
        $caps[] = 'do_not_allow';
    }
    return $caps;
}, 10, 3 );

// Application passwords (HTTP Basic auth for the REST API) only for the dedicated content-signing account.
add_filter( 'wp_is_application_passwords_available_for_user', static function ( $available, $user ) {
    return $available && $user instanceof WP_User && in_array( 'hr_signer', (array) $user->roles, true );
}, 10, 2 );

// REST CORS: WordPress echoes back ANY Origin with Allow-Credentials. Only this site (plus origins added through
// the hr_cors_allowed_origins filter) gets credentialed CORS; public endpoints that want `*` send it themselves.
add_action( 'rest_api_init', static function () {
    remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );
    add_filter( 'rest_pre_serve_request', static function ( $served ) {
        $origin = get_http_origin();
        header( 'Vary: Origin', false );
        if ( ! $origin ) {
            return $served;
        }
        $home    = wp_parse_url( home_url() );
        $allowed = (array) apply_filters( 'hr_cors_allowed_origins', array( $home['scheme'] . '://' . $home['host'] . ( isset( $home['port'] ) ? ':' . $home['port'] : '' ) ) );
        if ( in_array( $origin, $allowed, true ) ) {
            header( 'Access-Control-Allow-Origin: ' . $origin );
            header( 'Access-Control-Allow-Credentials: true' );
            header( 'Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS' );
            header( 'Access-Control-Allow-Headers: Authorization, X-WP-Nonce, Content-Disposition, Content-MD5, Content-Type' );
            header( 'Access-Control-Expose-Headers: X-WP-Total, X-WP-TotalPages, Link' );
        }
        return $served;
    } );
}, 15 );

// Shorter sessions for people who can change the site: 12 hours, or 3 days with "remember me".
add_filter( 'auth_cookie_expiration', static function ( $length, $user_id, $remember ) {
    if ( user_can( $user_id, 'edit_others_posts' ) ) {
        return $remember ? 3 * DAY_IN_SECONDS : 12 * HOUR_IN_SECONDS;
    }
    return $length;
}, 10, 3 );

// Throttle wp-login.php as well as our own forms (uses the platform plugin's shared limiter when present).
add_filter( 'authenticate', static function ( $user, $username ) {
    if ( empty( $username ) || ! function_exists( 'HR\\Platform\\rate_limit' ) ) {
        return $user;
    }
    $ip = \HR\Platform\client_ip();
    if ( ! \HR\Platform\rate_limit( 'login-ip', $ip, 20, 15 * MINUTE_IN_SECONDS )
        || ! \HR\Platform\rate_limit( 'login-user', strtolower( (string) $username ), 10, 15 * MINUTE_IN_SECONDS ) ) {
        return new WP_Error( 'hr_rate_limited', '<strong>Error:</strong> Too many sign-in attempts. Please wait 15 minutes and try again.' );
    }
    return $user;
}, 1, 2 );

// Password policy for every path that sets a password (profile, wp-login reset, our account forms).
function hr_password_problem( string $password, ?WP_User $user = null ): ?string {
    if ( strlen( $password ) < 12 ) {
        return 'Use at least 12 characters. A short sentence works well.';
    }
    if ( strlen( $password ) > 1024 ) {
        return 'That password is too long.';
    }
    if ( $user && ( 0 === strcasecmp( $password, $user->user_login ) || 0 === strcasecmp( $password, $user->user_email ) ) ) {
        return 'Your password cannot be your username or email.';
    }
    return apply_filters( 'hr_password_problem', null, $password, $user );
}
add_action( 'user_profile_update_errors', static function ( $errors, $update, $user ) {
    if ( ! empty( $user->user_pass ) && ( $problem = hr_password_problem( (string) $user->user_pass, $update ? get_userdata( $user->ID ) ?: null : null ) ) ) {
        $errors->add( 'hr_weak_password', '<strong>Error:</strong> ' . esc_html( $problem ) );
    }
}, 10, 3 );
add_action( 'validate_password_reset', static function ( $errors, $user ) {
    if ( ! empty( $_POST['pass1'] ) && $user instanceof WP_User && ( $problem = hr_password_problem( (string) wp_unslash( $_POST['pass1'] ), $user ) ) ) {
        $errors->add( 'hr_weak_password', esc_html( $problem ) );
    }
}, 10, 2 );

// ---------------------------------------------------------------------------------------------------------------
// Security headers. Sent from PHP for every WordPress response; infra/litespeed/htaccess-hardening.conf sends the
// same set for static files. Modules extend the CSP through the `hr_csp_sources` filter.
// ---------------------------------------------------------------------------------------------------------------
function hr_csp_sources(): array {
    $sources = array(
        'default-src'     => array( "'self'" ),
        // Inline scripts are still needed by WordPress core and plugins; external scripts are allowlisted by host.
        'script-src'      => array( "'self'", "'unsafe-inline'", 'https://s3.tradingview.com', 'https://www.google.com', 'https://www.gstatic.com', 'https://www.googletagmanager.com', 'https://*.google-analytics.com' ),
        'style-src'       => array( "'self'", "'unsafe-inline'" ),
        'img-src'         => array( "'self'", 'data:', 'blob:', 'https:' ),
        'font-src'        => array( "'self'", 'data:' ),
        'connect-src'     => array( "'self'", 'https://*.google-analytics.com', 'https://*.analytics.google.com', 'https://www.google.com', 'https://*.tradingview.com' ),
        'media-src'       => array( "'self'", 'blob:' ),
        'frame-src'       => array( "'self'", 'https://www.youtube.com', 'https://www.youtube-nocookie.com', 'https://www.google.com', 'https://*.tradingview.com', 'https://www.tradingview-widget.com' ),
        'worker-src'      => array( "'self'", 'blob:' ),
        'manifest-src'    => array( "'self'" ),
        'object-src'      => array( "'none'" ),
        'base-uri'        => array( "'self'" ),
        'form-action'     => array( "'self'" ),
        'frame-ancestors' => array( "'self'" ),
    );
    return (array) apply_filters( 'hr_csp_sources', $sources );
}

function hr_csp_header(): string {
    $parts = array();
    foreach ( hr_csp_sources() as $directive => $values ) {
        $parts[] = trim( $directive . ' ' . implode( ' ', array_unique( (array) $values ) ) );
    }
    $parts[] = 'upgrade-insecure-requests';
    return implode( '; ', apply_filters( 'hr_csp_directives', $parts ) );
}

function hr_send_security_headers( bool $front_end ): void {
    if ( headers_sent() ) {
        return;
    }
    $https = is_ssl();
    if ( $https ) {
        header( 'Strict-Transport-Security: max-age=63072000; includeSubDomains; preload' );
    }
    header( 'X-Content-Type-Options: nosniff' );
    header( 'Referrer-Policy: strict-origin-when-cross-origin' );
    header( 'X-Frame-Options: SAMEORIGIN' );
    header( 'Cross-Origin-Opener-Policy: same-origin-allow-popups' );
    header( 'Permissions-Policy: accelerometer=(), autoplay=(self), browsing-topics=(), camera=(), display-capture=(), geolocation=(), gyroscope=(), hid=(), magnetometer=(), microphone=(), midi=(), payment=(), publickey-credentials-create=(self), publickey-credentials-get=(self), serial=(), usb=(), xr-spatial-tracking=(), fullscreen=(self "https://www.youtube.com" "https://www.youtube-nocookie.com"), picture-in-picture=(self "https://www.youtube.com" "https://www.youtube-nocookie.com")' );
    if ( $front_end ) {
        header( 'Content-Security-Policy: ' . hr_csp_header() );
    } else {
        // wp-admin loads assets from many plugins; enforce the directives that cannot break it.
        header( "Content-Security-Policy: object-src 'none'; base-uri 'self'; frame-ancestors 'self'; upgrade-insecure-requests" );
    }
    do_action( 'hr_security_headers', $front_end );
}
// wp-admin list screens (edit.php) also run wp() and fire send_headers: keep the admin policy there.
add_action( 'send_headers', static fn() => hr_send_security_headers( ! is_admin() ) );
add_action( 'admin_init', static fn() => hr_send_security_headers( false ) );
add_action( 'login_init', static fn() => hr_send_security_headers( true ) );
