<?php
/**
 * Plugin Name: Headline Recorder — Sentry
 * Description: PHP error and performance monitoring. Loads before normal plugins so their fatal errors are caught. Does nothing unless HR_SENTRY_DSN is defined in wp-config.php.
 * Version:     1.0.0
 */

namespace HR\Sentry;

if ( ! defined( 'ABSPATH' ) ) exit;

/** Parameter/header names whose values never leave the server. */
const SENSITIVE = '/pass|pwd|token|key|nonce|secret|sig|code|email|auth|login/i';
/** Plain and URL-encoded email addresses. */
const EMAIL = '/[A-Z0-9._%+\-]+(?:@|%40)[A-Z0-9.\-]+\.[A-Z]{2,}/i';
const DROP_HEADERS = array( 'authorization', 'proxy-authorization', 'cookie', 'set-cookie', 'x-wp-nonce', 'x-forwarded-for', 'x-real-ip', 'cf-connecting-ip', 'true-client-ip', 'x-client-ip', 'forwarded' );
/** Upper bound on error events per request: every event is a synchronous HTTP call. */
const MAX_EVENTS = 10;

function release(): string {
    return defined( 'HR_RELEASE' ) && is_string( HR_RELEASE ) && '' !== HR_RELEASE ? HR_RELEASE : 'headline-recorder@1.0.0';
}

/** True once the SDK is initialised. Other code checks this instead of probing Sentry classes. */
function active( ?bool $set = null ): bool {
    static $on = false;
    if ( null !== $set ) {
        $on = $set;
    }
    return $on;
}

function boot(): void {
    if ( ! defined( 'HR_SENTRY_DSN' ) || ! is_string( HR_SENTRY_DSN ) || '' === HR_SENTRY_DSN || PHP_VERSION_ID < 80200 ) {
        return;
    }
    $autoload = WP_PLUGIN_DIR . '/hr-platform/vendor/autoload.php';
    if ( ! is_readable( $autoload ) ) {
        return;
    }
    try {
        require_once $autoload;
        if ( ! function_exists( '\Sentry\init' ) ) {
            return;
        }
        $rate = defined( 'HR_SENTRY_TRACES' ) ? max( 0.0, min( 1.0, (float) HR_SENTRY_TRACES ) ) : 0.05;

        // Only our code is "in app"; WordPress core and third-party plugins are grouped as library frames.
        $in_app = array();
        foreach ( array( WP_CONTENT_DIR . '/themes/headlinerec-child', WP_PLUGIN_DIR . '/hr-platform/includes', WP_PLUGIN_DIR . '/hr-platform/templates', WP_PLUGIN_DIR . '/hr-platform/hr-platform.php', WPMU_PLUGIN_DIR ) as $path ) {
            $in_app[] = $path;
            $real = realpath( $path );
            if ( $real ) {
                $in_app[] = $real;
            }
        }
        $root = realpath( ABSPATH ) ?: ABSPATH;

        \Sentry\init( array(
            'dsn'                     => HR_SENTRY_DSN,
            'environment'             => wp_get_environment_type(),
            'release'                 => release(),
            'send_default_pii'        => false,
            'max_request_body_size'   => 'none',
            'attach_stacktrace'       => true,
            'error_types'             => E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED & ~E_NOTICE & ~E_USER_NOTICE,
            'traces_sample_rate'      => $rate,
            'in_app_include'          => array_values( array_unique( $in_app ) ),
            'in_app_exclude'          => array( rtrim( $root, '/' ) . '/' ),
            'prefixes'                => array_values( array_unique( array( ABSPATH, rtrim( $root, '/' ) . '/' ) ) ),
            'tags'                    => array( 'wp_version' => (string) ( $GLOBALS['wp_version'] ?? '' ), 'php_version' => PHP_VERSION ),
            // A slow or unreachable Sentry must not hold pages hostage.
            'http_connect_timeout'    => 1,
            'http_timeout'            => 2,
            'before_send'             => static fn( \Sentry\Event $event ) => scrub_event( $event, true ),
            'before_send_transaction' => static fn( \Sentry\Event $event ) => scrub_event( $event, false ),
        ) );
        active( true );
        add_hooks();
        start_transaction( $rate );
    } catch ( \Throwable $e ) {
        // Monitoring must never take the site down. The message may echo the DSN, so log only the class.
        error_log( 'hr-sentry: init failed (' . get_class( $e ) . ')' );
    }
}

function add_hooks(): void {
    // The user is identified by ID only — never email, login or IP.
    add_action( 'set_current_user', static function () {
        try {
            $id = get_current_user_id();
            \Sentry\configureScope( static function ( \Sentry\State\Scope $scope ) use ( $id ) {
                $scope->removeUser();
                if ( $id ) {
                    $scope->setUser( array( 'id' => (string) $id ) );
                }
            } );
        } catch ( \Throwable $e ) {
            // Ignore: monitoring is best effort.
        }
    } );

    // WordPress registers its fatal-error page handler before mu-plugins load, and it ends the request with wp_die(),
    // so Sentry's own shutdown handler never runs. Capture the fatal while WordPress builds its error page.
    add_filter( 'wp_php_error_message', static function ( $message, $error ) {
        capture_fatal( is_array( $error ) ? $error : array() );
        return $message;
    }, 1, 2 );
}

function capture_fatal( array $error ): void {
    $labels = array( E_ERROR => 'Error', E_PARSE => 'Parse Error', E_CORE_ERROR => 'Core Error', E_CORE_WARNING => 'Core Warning', E_COMPILE_ERROR => 'Compile Error', E_COMPILE_WARNING => 'Compile Warning' );
    $type   = (int) ( $error['type'] ?? 0 );
    if ( ! isset( $labels[ $type ] ) ) {
        return; // E_USER_ERROR / E_RECOVERABLE_ERROR already went through Sentry's error handler.
    }
    try {
        // Same shape as Sentry's FatalErrorListenerIntegration, so the per-request dedupe drops a second copy.
        $exception = new \Sentry\Exception\FatalErrorException( $labels[ $type ] . ': ' . (string) ( $error['message'] ?? '' ), 0, $type, (string) ( $error['file'] ?? '' ), (int) ( $error['line'] ?? 0 ) );
        ( new \ReflectionProperty( \Exception::class, 'trace' ) )->setValue( $exception, array() );
        \Sentry\captureException( $exception, \Sentry\EventHint::fromArray( array(
            'mechanism' => new \Sentry\ExceptionMechanism( \Sentry\ExceptionMechanism::TYPE_GENERIC, false ),
        ) ) );
        finish_transaction( 500 );
    } catch ( \Throwable $e ) {
        // Ignore.
    }
}

// -----------------------------------------------------------------------------------------------------------------
// Performance: one http.server transaction per sampled request.
// -----------------------------------------------------------------------------------------------------------------

/** Holder for the current request's transaction. */
function transaction( $set = null ): ?\Sentry\Tracing\Transaction {
    static $tx = null;
    if ( false === $set ) {
        $tx = null;
    } elseif ( $set instanceof \Sentry\Tracing\Transaction ) {
        $tx = $set;
    }
    return $tx;
}

function start_transaction( float $rate ): void {
    if ( $rate <= 0 || 'cli' === PHP_SAPI ) {
        return;
    }
    // Only our own pages' fetches may continue a browser trace; trace headers from elsewhere cannot force sampling.
    $same_origin = 'same-origin' === ( $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '' );
    $context     = \Sentry\continueTrace(
        $same_origin ? substr( (string) ( $_SERVER['HTTP_SENTRY_TRACE'] ?? '' ), 0, 128 ) : '',
        $same_origin ? substr( (string) ( $_SERVER['HTTP_BAGGAGE'] ?? '' ), 0, 2048 ) : ''
    );
    $context->setOp( 'http.server' );
    $context->setName( 'request' );
    $context->setSource( \Sentry\Tracing\TransactionSource::route() );
    $context->setStartTimestamp( (float) ( $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime( true ) ) );
    $context->setData( array( 'http.request.method' => strtoupper( substr( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ), 0, 10 ) ) ) );

    $tx = \Sentry\startTransaction( $context );
    if ( ! $tx->getSampled() ) {
        return;
    }
    \Sentry\SentrySdk::getCurrentHub()->setSpan( $tx );
    transaction( $tx );

    add_action( 'wp', static function () {
        name_transaction( front_end_name() );
    }, 0 );
    add_filter( 'rest_dispatch_request', static function ( $result, $request, $route ) {
        // The route pattern, not the URL: "/wp/v2/posts/{id}" groups every post.
        $route = preg_replace( '/\(\?P<([a-zA-Z0-9_]+)>[^)]*\)/', '{$1}', (string) $route );
        name_transaction( 'rest ' . substr( (string) $route, 0, 200 ) );
        return $result;
    }, 0, 3 );
    add_action( 'shutdown', static function () {
        finish_transaction( (int) http_response_code() ?: 200 );
    }, PHP_INT_MAX );
}

function name_transaction( string $name ): void {
    $tx = transaction();
    if ( $tx && '' !== $name ) {
        $tx->setName( $name );
    }
}

function front_end_name(): string {
    $route = (string) get_query_var( 'hr_route' );
    if ( '' !== $route ) {
        return 'route ' . sanitize_key( $route );
    }
    $checks = array(
        'robots'      => 'is_robots',
        'feed'        => 'is_feed',
        'embed'       => 'is_embed',
        '404'         => 'is_404',
        'search'      => 'is_search',
        'front page'  => 'is_front_page',
        'blog home'   => 'is_home',
        'attachment'  => 'is_attachment',
        'page'        => 'is_page',
    );
    foreach ( $checks as $label => $fn ) {
        if ( $fn() ) {
            return $label;
        }
    }
    if ( is_singular() ) {
        return 'single ' . sanitize_key( (string) get_post_type() );
    }
    if ( is_post_type_archive() ) {
        $type = get_query_var( 'post_type' );
        return 'archive ' . sanitize_key( is_array( $type ) ? (string) reset( $type ) : (string) $type );
    }
    if ( is_category() ) {
        return 'archive category';
    }
    if ( is_tag() ) {
        return 'archive tag';
    }
    if ( is_tax() ) {
        $term = get_queried_object();
        return 'archive ' . sanitize_key( $term instanceof \WP_Term ? $term->taxonomy : 'taxonomy' );
    }
    if ( is_author() ) {
        return 'archive author';
    }
    if ( is_date() ) {
        return 'archive date';
    }
    if ( get_query_var( 'sitemap' ) ) {
        return 'sitemap';
    }
    return is_archive() ? 'archive' : 'front other';
}

/** Name for requests that never reach the 'wp' action or the REST dispatcher. */
function context_name(): string {
    if ( wp_doing_cron() ) {
        return 'cron';
    }
    if ( wp_doing_ajax() ) {
        $action = isset( $_REQUEST['action'] ) ? sanitize_key( substr( (string) wp_unslash( $_REQUEST['action'] ), 0, 64 ) ) : '';
        // Only registered actions become names, so junk values cannot flood Sentry with unique transactions.
        $known = '' !== $action && ( has_action( 'wp_ajax_' . $action ) || has_action( 'wp_ajax_nopriv_' . $action ) );
        return 'ajax ' . ( $known ? $action : 'unknown' );
    }
    if ( is_admin() ) {
        return 'admin ' . sanitize_file_name( (string) ( $GLOBALS['pagenow'] ?? '' ) );
    }
    if ( 'wp-login.php' === ( $GLOBALS['pagenow'] ?? '' ) ) {
        return 'login';
    }
    return defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ? 'xmlrpc' : 'request';
}

function finish_transaction( int $status ): void {
    $tx = transaction();
    if ( ! $tx ) {
        return;
    }
    transaction( false );
    try {
        if ( 'request' === $tx->getName() ) {
            $tx->setName( context_name() );
        }
        $tx->setHttpStatus( $status );
        $tx->finish();
    } catch ( \Throwable $e ) {
        // Ignore.
    }
}

// -----------------------------------------------------------------------------------------------------------------
// Scrubbing. Runs on every event and transaction before it leaves the server; on any failure the event is dropped.
// -----------------------------------------------------------------------------------------------------------------

function scrub_event( \Sentry\Event $event, bool $is_error ): ?\Sentry\Event {
    try {
        if ( $is_error && ! first_occurrence( $event ) ) {
            return null;
        }
        if ( $event->getRequest() ) {
            $event->setRequest( scrub_request( $event->getRequest() ) );
        }
        $user = $event->getUser();
        if ( null !== $user ) {
            $id = $user->getId();
            $event->setUser( null !== $id && '' !== (string) $id ? new \Sentry\UserDataBag( (string) $id ) : null );
        }
        if ( null !== $event->getMessage() ) {
            $formatted = $event->getMessageFormatted();
            $event->setMessage( scrub_text( $event->getMessage() ), scrub_value( $event->getMessageParams(), false ), null !== $formatted ? scrub_text( $formatted ) : null );
        }
        foreach ( $event->getExceptions() as $exception ) {
            $exception->setValue( scrub_text( $exception->getValue() ) );
            strip_frame_vars( $exception->getStacktrace() );
        }
        strip_frame_vars( $event->getStacktrace() );
        $event->setExtra( scrub_value( $event->getExtra(), true ) );
        foreach ( $event->getContexts() as $name => $data ) {
            if ( is_array( $data ) ) {
                $event->setContext( (string) $name, scrub_value( $data, false ) );
            }
        }
        $event->setTags( array_map( static fn( $v ) => scrub_text( (string) $v ), $event->getTags() ) );
        $crumbs = array();
        foreach ( $event->getBreadcrumbs() as $crumb ) {
            $message  = $crumb->getMessage();
            $crumbs[] = new \Sentry\Breadcrumb( $crumb->getLevel(), $crumb->getType(), $crumb->getCategory(), null !== $message ? scrub_text( $message ) : null, scrub_value( $crumb->getMetadata(), true ), $crumb->getTimestamp() );
        }
        $event->setBreadcrumb( $crumbs );
        return $event;
    } catch ( \Throwable $e ) {
        return null;
    }
}

/** Drops repeats of the same error within one request (a warning in a loop) and caps the total. */
function first_occurrence( \Sentry\Event $event ): bool {
    static $seen = array();
    if ( count( $seen ) >= MAX_EVENTS ) {
        return false;
    }
    $exceptions = $event->getExceptions();
    if ( $exceptions ) {
        $last   = end( $exceptions );
        $frames = $last->getStacktrace() ? $last->getStacktrace()->getFrames() : array();
        $top    = $frames ? end( $frames ) : null;
        $key    = $last->getValue() . '|' . ( $top ? $top->getAbsoluteFilePath() . ':' . $top->getLine() : '' );
    } else {
        $key = (string) $event->getMessage();
    }
    $key = md5( $key );
    if ( isset( $seen[ $key ] ) ) {
        return false;
    }
    $seen[ $key ] = true;
    return true;
}

function strip_frame_vars( ?\Sentry\Stacktrace $stacktrace ): void {
    if ( ! $stacktrace ) {
        return;
    }
    // Function arguments can hold passwords (wp_authenticate( $user, $pass )) or tokens.
    foreach ( $stacktrace->getFrames() as $frame ) {
        $frame->setVars( array() );
    }
}

function scrub_request( array $request ): array {
    unset( $request['cookies'], $request['env'] );
    if ( isset( $request['data'] ) ) {
        $request['data'] = is_array( $request['data'] ) ? scrub_value( $request['data'], true ) : '[Filtered]';
    }
    if ( isset( $request['headers'] ) && is_array( $request['headers'] ) ) {
        foreach ( $request['headers'] as $name => $values ) {
            $lower = strtolower( (string) $name );
            if ( in_array( $lower, DROP_HEADERS, true ) ) {
                unset( $request['headers'][ $name ] );
            } elseif ( preg_match( SENSITIVE, $lower ) ) {
                $request['headers'][ $name ] = '[Filtered]';
            } else {
                $request['headers'][ $name ] = scrub_value( $values, false );
            }
        }
    }
    if ( isset( $request['query_string'] ) ) {
        $request['query_string'] = is_string( $request['query_string'] )
            ? substr( scrub_text( '?' . $request['query_string'] ), 1 )
            : scrub_value( (array) $request['query_string'], true );
    }
    if ( isset( $request['url'] ) && is_string( $request['url'] ) ) {
        $request['url'] = scrub_text( $request['url'] );
    }
    return $request;
}

/** Removes email addresses and masks sensitive query parameters (?token=… &key=…) anywhere in a string. */
function scrub_text( string $text ): string {
    $text = (string) preg_replace( EMAIL, '[email]', $text );
    return (string) preg_replace_callback( '/([?&;])([^=&#\s;]+)=([^&#\s;]*)/', static function ( $m ) {
        return preg_match( SENSITIVE, rawurldecode( $m[2] ) ) ? $m[1] . $m[2] . '=[Filtered]' : $m[0];
    }, $text );
}

function scrub_value( $value, bool $mask_keys, int $depth = 0 ) {
    if ( is_string( $value ) ) {
        return scrub_text( $value );
    }
    if ( ! is_array( $value ) ) {
        return $value;
    }
    if ( $depth > 10 ) {
        return '[Filtered]';
    }
    foreach ( $value as $key => $item ) {
        $value[ $key ] = $mask_keys && is_string( $key ) && preg_match( SENSITIVE, $key ) ? '[Filtered]' : scrub_value( $item, $mask_keys, $depth + 1 );
    }
    return $value;
}

boot();
