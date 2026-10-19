<?php
/**
 * Monitoring: browser Sentry SDK + same-origin tunnel, CSP violation reports, cron check-ins, health endpoint and the
 * settings status. PHP-side Sentry is initialised earlier by mu-plugins/hr-sentry.php.
 */

namespace HR\Platform\Monitoring;

use function HR\Platform\asset_url;
use function HR\Platform\asset_ver;
use function HR\Platform\client_ip;
use function HR\Platform\rate_limit;
use function HR\Platform\same_origin_request;

if ( ! defined( 'ABSPATH' ) ) exit;

const TUNNEL_MAX_BYTES = 204800;
const DEFAULT_RELEASE  = 'headline-recorder@1.0.0';

function release(): string {
    return defined( 'HR_RELEASE' ) && is_string( HR_RELEASE ) && '' !== HR_RELEASE ? HR_RELEASE : DEFAULT_RELEASE;
}

function const_string( string $name ): string {
    return defined( $name ) && is_string( constant( $name ) ) ? trim( constant( $name ) ) : '';
}

function const_rate( string $name ): float {
    return defined( $name ) ? max( 0.0, min( 1.0, (float) constant( $name ) ) ) : 0.05;
}

/** True when the mu-plugin initialised the PHP SDK. */
function php_active(): bool {
    return function_exists( 'HR\\Sentry\\active' ) && \HR\Sentry\active();
}

/**
 * Splits a DSN (scheme://publickey@host[:port][/path]/project) into parts, or null when it is not one.
 *
 * @return array{scheme:string,host:string,port:string,path:string,key:string,project:string}|null
 */
function parse_dsn( string $dsn ): ?array {
    if ( '' === $dsn || strlen( $dsn ) > 512 ) {
        return null;
    }
    $p = wp_parse_url( $dsn );
    if ( ! is_array( $p ) || empty( $p['host'] ) || empty( $p['user'] ) || empty( $p['path'] ) || ! in_array( $p['scheme'] ?? '', array( 'https', 'http' ), true ) ) {
        return null;
    }
    $path    = rtrim( (string) $p['path'], '/' );
    $slash   = strrpos( $path, '/' );
    $project = false === $slash ? '' : substr( $path, $slash + 1 );
    if ( ! preg_match( '/^\d{1,20}$/', $project ) || ! preg_match( '/^[A-Za-z0-9]{1,64}$/', (string) $p['user'] ) || ! preg_match( '/^[A-Za-z0-9.\-]{1,253}$/', (string) $p['host'] ) ) {
        return null;
    }
    return array(
        'scheme'  => (string) $p['scheme'],
        'host'    => strtolower( (string) $p['host'] ),
        'port'    => isset( $p['port'] ) ? (string) (int) $p['port'] : '',
        'path'    => substr( $path, 0, $slash ),
        'key'     => (string) $p['user'],
        'project' => $project,
    );
}

/** The DSN the browser reports to: its own project if configured, otherwise the PHP one. */
function browser_dsn(): ?array {
    static $dsn = false;
    if ( false === $dsn ) {
        $dsn = parse_dsn( const_string( 'HR_SENTRY_BROWSER_DSN' ) ?: const_string( 'HR_SENTRY_DSN' ) );
    }
    return $dsn;
}

function dsn_string( array $d ): string {
    return $d['scheme'] . '://' . $d['key'] . '@' . $d['host'] . ( '' !== $d['port'] ? ':' . $d['port'] : '' ) . $d['path'] . '/' . $d['project'];
}

function api_base( array $d ): string {
    return $d['scheme'] . '://' . $d['host'] . ( '' !== $d['port'] ? ':' . $d['port'] : '' ) . $d['path'] . '/api/' . $d['project'];
}

function csp_report_url(): string {
    $d = browser_dsn();
    if ( ! $d ) {
        return '';
    }
    return api_base( $d ) . '/security/?sentry_key=' . rawurlencode( $d['key'] )
        . '&sentry_environment=' . rawurlencode( wp_get_environment_type() )
        . '&sentry_release=' . rawurlencode( release() );
}

// ---------------------------------------------------------------------------------------------------------------
// Browser SDK. The config is a non-executable JSON block; the bundle reads it and posts envelopes to our tunnel,
// so CSP connect-src stays 'self' and ad blockers that drop sentry.io requests do not hide errors.
// ---------------------------------------------------------------------------------------------------------------
function browser_bundle_ready(): bool {
    return null !== browser_dsn() && is_readable( HR_PLATFORM_DIR . '/assets/js/sentry.js' );
}

add_action( 'wp_head', function () {
    if ( ! browser_bundle_ready() ) {
        return;
    }
    $config = array(
        'dsn'              => dsn_string( browser_dsn() ),
        'environment'      => wp_get_environment_type(),
        'release'          => release(),
        'tracesSampleRate' => const_rate( 'HR_SENTRY_BROWSER_TRACES' ),
        'tunnel'           => rest_url( 'hr/v1/monitoring/tunnel' ),
    );
    // JSON_HEX_TAG keeps "</script>" from ever closing the block.
    echo '<script type="application/json" id="hr-sentry-config">' . wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES ) . "</script>\n";
}, 1 );

add_action( 'wp_enqueue_scripts', function () {
    if ( browser_bundle_ready() ) {
        wp_enqueue_script( 'hr-sentry', asset_url( 'js/sentry.js' ), array(), asset_ver( 'js/sentry.js' ), array( 'in_footer' => false, 'strategy' => 'async' ) );
    }
}, 1 );

// ---------------------------------------------------------------------------------------------------------------
// REST: tunnel + health.
// ---------------------------------------------------------------------------------------------------------------
add_action( 'rest_api_init', function () {
    register_rest_route( 'hr/v1', '/monitoring/health', array(
        'methods'             => 'GET',
        'callback'            => __NAMESPACE__ . '\\health',
        'permission_callback' => '__return_true',
    ) );
    if ( browser_dsn() ) {
        register_rest_route( 'hr/v1', '/monitoring/tunnel', array(
            'methods'             => 'POST',
            'callback'            => __NAMESPACE__ . '\\tunnel',
            'permission_callback' => '__return_true',
        ) );
    }
} );

function plain_response( $data, int $status ): \WP_REST_Response {
    $response = new \WP_REST_Response( $data, $status );
    $response->header( 'Cache-Control', 'no-store' );
    return $response;
}

/**
 * Forwards a browser envelope to Sentry. Only envelopes addressed to our own browser DSN are accepted, and the
 * upstream URL is built from configuration, never from the request — so this cannot be used as an open relay.
 */
function tunnel( \WP_REST_Request $request ): \WP_REST_Response {
    $dsn = browser_dsn();
    if ( ! $dsn ) {
        return plain_response( array( 'error' => 'not_found' ), 404 );
    }
    if ( ! rate_limit( 'sentry-tunnel', client_ip(), 60, MINUTE_IN_SECONDS ) ) {
        $response = plain_response( array( 'error' => 'rate_limited' ), 429 );
        $response->header( 'Retry-After', '60' );
        return $response;
    }
    // Browsers always send Origin on a POST fetch; a foreign one means another site is posting through us.
    $origin = get_http_origin();
    if ( $origin && ! same_origin_request() ) {
        return plain_response( array( 'error' => 'forbidden' ), 403 );
    }
    $length = (int) $request->get_header( 'content_length' );
    if ( $length > TUNNEL_MAX_BYTES ) {
        return plain_response( array( 'error' => 'too_large' ), 413 );
    }
    $body = $request->get_body();
    if ( strlen( $body ) > TUNNEL_MAX_BYTES ) {
        return plain_response( array( 'error' => 'too_large' ), 413 );
    }
    $newline = strpos( $body, "\n" );
    $first   = false === $newline ? $body : substr( $body, 0, $newline );
    $header  = '' !== $first && strlen( $first ) <= 8192 ? json_decode( $first, true ) : null;
    $sent    = is_array( $header ) && is_string( $header['dsn'] ?? null ) ? parse_dsn( $header['dsn'] ) : null;
    if ( ! $sent || $sent !== $dsn ) {
        return plain_response( array( 'error' => 'bad_request' ), 400 );
    }

    // Only the envelope travels upstream: no cookies, no client IP, no forwarded headers.
    $upstream = wp_remote_post( api_base( $dsn ) . '/envelope/', array(
        'timeout'     => 3,
        'redirection' => 0,
        'user-agent'  => 'HeadlineRecorder-SentryTunnel/1.0',
        'headers'     => array( 'Content-Type' => 'application/x-sentry-envelope' ),
        'body'        => $body,
    ) );
    if ( is_wp_error( $upstream ) ) {
        error_log( 'hr-sentry tunnel: upstream unreachable' );
    } elseif ( 429 === (int) wp_remote_retrieve_response_code( $upstream ) ) {
        // Pass Sentry's back-off to the browser SDK so it stops sending for a while.
        $response = plain_response( new \stdClass(), 429 );
        $retry    = wp_remote_retrieve_header( $upstream, 'retry-after' );
        $limits   = wp_remote_retrieve_header( $upstream, 'x-sentry-rate-limits' );
        $response->header( 'Retry-After', preg_match( '/^\d{1,6}$/', (string) $retry ) ? (string) $retry : '60' );
        if ( is_string( $limits ) && preg_match( '/^[A-Za-z0-9:;,._\- ]{1,512}$/', $limits ) ) {
            $response->header( 'X-Sentry-Rate-Limits', $limits );
        }
        return $response;
    }
    return plain_response( new \stdClass(), 200 );
}

function health(): \WP_REST_Response {
    if ( ! rate_limit( 'health', client_ip(), 30, MINUTE_IN_SECONDS ) ) {
        $response = plain_response( array( 'error' => 'rate_limited' ), 429 );
        $response->header( 'Retry-After', '60' );
        return $response;
    }
    global $wpdb;
    $db = '1' === (string) $wpdb->get_var( 'SELECT 1' );
    return plain_response( array( 'ok' => $db, 'db' => $db, 'time' => time() ), $db ? 200 : 503 );
}

// ---------------------------------------------------------------------------------------------------------------
// CSP violation reports go straight from the browser to Sentry's security endpoint.
// ---------------------------------------------------------------------------------------------------------------
add_filter( 'hr_csp_directives', function ( $parts ) {
    $url = csp_report_url();
    if ( '' !== $url && is_array( $parts ) ) {
        $parts[] = 'report-uri ' . $url;
        $parts[] = 'report-to csp-endpoint';
    }
    return $parts;
} );

add_action( 'hr_security_headers', function ( $front_end ) {
    $url = csp_report_url();
    if ( $front_end && '' !== $url && ! headers_sent() ) {
        header( 'Reporting-Endpoints: csp-endpoint="' . $url . '"' );
    }
} );

// ---------------------------------------------------------------------------------------------------------------
// Cron check-ins:  do_action( 'hr_monitor_cron', 'signing-queue', 'in_progress', '*/15 * * * *' ); … 'ok' | 'error'
// ---------------------------------------------------------------------------------------------------------------
add_action( 'hr_monitor_cron', __NAMESPACE__ . '\\cron_check_in', 10, 3 );

function cron_check_in( $slug, $status, $crontab = '' ): void {
    static $open = array();
    if ( ! php_active() || ! is_string( $slug ) || ! is_string( $status ) ) {
        return;
    }
    $slug = substr( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $slug ) ), 0, 50 );
    $map  = array( 'in_progress' => 'inProgress', 'ok' => 'ok', 'error' => 'error' );
    if ( '' === $slug || ! isset( $map[ $status ] ) ) {
        return;
    }
    try {
        $config  = null;
        $crontab = is_string( $crontab ) ? trim( $crontab ) : '';
        if ( strlen( $crontab ) <= 100 && preg_match( '/^[0-9A-Za-z*\/,\-]+(\s+[0-9A-Za-z*\/,\-]+){4}$/', $crontab ) ) {
            $config = new \Sentry\MonitorConfig( \Sentry\MonitorSchedule::crontab( $crontab ), 10, 15, sentry_timezone() );
        }
        $check_status = call_user_func( array( \Sentry\CheckInStatus::class, $map[ $status ] ) );
        if ( 'in_progress' === $status ) {
            $open[ $slug ] = array( 'id' => \Sentry\captureCheckIn( $slug, $check_status, null, $config ), 'start' => microtime( true ) );
            return;
        }
        $previous = $open[ $slug ] ?? null;
        unset( $open[ $slug ] );
        \Sentry\captureCheckIn( $slug, $check_status, $previous ? microtime( true ) - $previous['start'] : null, $config, $previous['id'] ?? null );
    } catch ( \Throwable $e ) {
        // Monitoring is best effort; a failed check-in must not break the job.
    }
}

/** Sentry wants an IANA zone; WordPress may hold a bare UTC offset ("+05:00"), which maps to Etc/GMT∓N. */
function sentry_timezone(): string {
    $tz = wp_timezone_string();
    if ( 'UTC' === $tz || str_contains( $tz, '/' ) ) {
        return $tz;
    }
    if ( preg_match( '/^([+-])(\d{2}):00$/', $tz, $m ) ) {
        $hours = (int) $m[2];
        return 0 === $hours ? 'UTC' : 'Etc/GMT' . ( '+' === $m[1] ? '-' : '+' ) . $hours;
    }
    return 'UTC';
}

// ---------------------------------------------------------------------------------------------------------------
// Settings → Headline Recorder → Monitoring: status only (the DSNs are wp-config.php constants) + a test event.
// ---------------------------------------------------------------------------------------------------------------
add_filter( 'hr_settings_schema', function ( $schema ) {
    if ( ! is_admin() ) {
        return $schema;
    }
    $yes      = static fn( bool $v ) => $v ? '<strong>yes</strong>' : '<strong>no</strong>';
    $server   = '' !== const_string( 'HR_SENTRY_DSN' );
    $own      = null !== parse_dsn( const_string( 'HR_SENTRY_BROWSER_DSN' ) );
    $browser  = $own ? '<strong>yes</strong>' : ( browser_dsn() ? '<strong>yes</strong> (uses the server DSN)' : '<strong>no</strong>' );
    $pct      = static fn( string $c ) => esc_html( rtrim( rtrim( number_format( const_rate( $c ) * 100, 2 ), '0' ), '.' ) . '%' );
    $schema['monitoring'] = array(
        'title'       => 'Monitoring',
        'description' => 'PHP and browser errors, performance traces, CSP violation reports and cron check-ins go to Sentry. '
            . 'Configure it in <code>wp-config.php</code> with <code>HR_SENTRY_DSN</code>, <code>HR_SENTRY_BROWSER_DSN</code>, <code>HR_SENTRY_TRACES</code> and <code>HR_SENTRY_BROWSER_TRACES</code> — never here.<br>'
            . 'Server DSN configured: ' . $yes( $server ) . ' &middot; PHP SDK running: ' . $yes( php_active() )
            . ' &middot; Browser DSN: ' . $browser
            . ' &middot; Traces: ' . $pct( 'HR_SENTRY_TRACES' ) . ' server, ' . $pct( 'HR_SENTRY_BROWSER_TRACES' ) . ' browser.<br>'
            . 'Uptime check: <code>' . esc_html( rest_url( 'hr/v1/monitoring/health' ) ) . '</code>',
        'fields'      => array(),
    );
    return $schema;
} );

// The settings page is one big <form>; the test button belongs to a separate form rendered after it.
add_action( 'hr_settings_section_after', function ( $id ) {
    if ( 'monitoring' !== $id ) {
        return;
    }
    $on = php_active();
    echo '<p><button type="submit" class="button" form="hr-monitoring-test"' . ( $on ? '' : ' disabled' ) . '>Send test event</button>';
    if ( ! $on ) {
        echo ' <span class="description">Available once HR_SENTRY_DSN is set.</span>';
    }
    echo '</p>';
} );

add_action( 'hr_settings_page_after', function () {
    ?>
    <form id="hr-monitoring-test" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" hidden>
        <input type="hidden" name="action" value="hr_monitoring_test">
        <?php wp_nonce_field( 'hr_monitoring_test', 'hr_mon_nonce', false ); ?>
    </form>
    <?php
} );

add_action( 'admin_post_hr_monitoring_test', function () {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'You are not allowed to do that.', '', array( 'response' => 403 ) );
    }
    check_admin_referer( 'hr_monitoring_test', 'hr_mon_nonce' );
    if ( ! same_origin_request() ) {
        wp_die( 'You are not allowed to do that.', '', array( 'response' => 403 ) );
    }
    $user   = get_current_user_id();
    $result = 'off';
    if ( php_active() ) {
        if ( ! rate_limit( 'sentry-test', (string) $user, 5, MINUTE_IN_SECONDS ) ) {
            $result = 'limited';
        } else {
            $id     = \Sentry\withScope( static function ( \Sentry\State\Scope $scope ) {
                $scope->setTag( 'hr_test', 'admin' );
                return \Sentry\captureMessage( 'Headline Recorder test event', \Sentry\Severity::info() );
            } );
            $result = $id ? (string) $id : 'failed';
        }
    }
    set_transient( 'hr_mon_test_' . $user, $result, 5 * MINUTE_IN_SECONDS );
    wp_safe_redirect( admin_url( 'options-general.php?page=hr-platform#hr-monitoring' ) );
    exit;
} );

add_action( 'admin_notices', function () {
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( ! $screen || 'settings_page_hr-platform' !== $screen->id || ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $key    = 'hr_mon_test_' . get_current_user_id();
    $result = get_transient( $key );
    if ( false === $result ) {
        return;
    }
    delete_transient( $key );
    $messages = array(
        'off'     => array( 'warning', 'Sentry is not configured: define HR_SENTRY_DSN in wp-config.php.' ),
        'limited' => array( 'warning', 'Too many test events. Wait a minute and try again.' ),
        'failed'  => array( 'error', 'Sentry did not accept the test event. Check the DSN and that this server can reach Sentry.' ),
    );
    if ( isset( $messages[ $result ] ) ) {
        [ $type, $text ] = $messages[ $result ];
        printf( '<div class="notice notice-%s is-dismissible" role="status"><p>%s</p></div>', esc_attr( $type ), esc_html( $text ) );
        return;
    }
    printf( '<div class="notice notice-success is-dismissible" role="status"><p>Test event sent to Sentry. Event ID: <code>%s</code></p></div>', esc_html( (string) $result ) );
} );
