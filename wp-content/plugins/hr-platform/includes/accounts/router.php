<?php
/**
 * Accounts: the /account/<sub>/ dispatcher, the overview page, gates for contributed pages and the tighter
 * Content-Security-Policy that every account page gets.
 */

namespace HR\Platform\Accounts;

use function HR\Platform\no_store;
use function HR\Platform\render;
use function HR\Platform\same_origin_request;
use function HR\Platform\log_security;

if ( ! defined( 'ABSPATH' ) ) exit;

/** Pages that work signed out. Everything else under /account/ needs a session. */
const PUBLIC_ROUTES = array( 'login', 'register', 'verify', 'forgot', 'reset', 'two-factor' );

function dispatch( string $sub ): void {
    no_store();
    $sub = strtolower( $sub );
    $GLOBALS['hr_accounts_sub'] = $sub; // where step_up_panel() posts back to
    if ( ! headers_sent() ) {
        // These pages carry one-time links and personal data: never send their URL to another site. (Not
        // "no-referrer": browsers then send "Origin: null" on our own form posts and the origin check fails.)
        header( 'Referrer-Policy: same-origin' );
    }
    switch ( $sub ) {
        case 'login':
            route_login();
            return;
        case 'register':
            route_register();
            return;
        case 'verify':
            route_verify();
            return;
        case 'forgot':
            route_forgot();
            return;
        case 'reset':
            route_reset();
            return;
        case 'two-factor':
            route_two_factor();
            return;
        case 'logout':
            route_logout();
            return;
        case 'overview':
            redirect( account_url(), 301 );
    }

    if ( ! is_user_logged_in() ) {
        flash( 'login_needed' );
        redirect( login_url( '' === $sub ? '' : current_account_url( $sub ) ) );
    }
    $user = wp_get_current_user();

    if ( '' === $sub ) {
        route_overview( $user );
        return;
    }
    if ( 'settings' === $sub ) {
        route_settings( $user );
        return;
    }
    $pages = account_pages();
    if ( isset( $pages[ $sub ] ) ) {
        route_contributed( $user, $sub, $pages[ $sub ] );
        return;
    }
    status_header( 404 );
    render_app( $user, '', 'Page not found', static function () {
        render( 'account/not-found' );
    } );
}

/** The URL of the account page being requested (validated later by login_url()). */
function current_account_url( string $sub ): string {
    $url = account_url( $sub );
    // Keep a short, harmless query string (e.g. ?tab=drafts) but nothing that looks like a secret.
    $query = array();
    foreach ( $_GET as $k => $v ) {
        if ( is_string( $k ) && is_string( $v ) && preg_match( '/^[a-z_]{1,20}$/', $k ) && ! in_array( $k, array( 'key', 'token', 't', 'u', 'login', 'redirect_to' ), true ) && strlen( $v ) <= 60 ) {
            $query[ $k ] = sanitize_text_field( wp_unslash( $v ) );
        }
    }
    return $query ? add_query_arg( array_map( 'rawurlencode', array_slice( $query, 0, 5, true ) ), $url ) : $url;
}

// ---------------------------------------------------------------------------------------------------------------
// Overview
// ---------------------------------------------------------------------------------------------------------------

function route_overview( \WP_User $user ): void {
    if ( is_post() ) {
        if ( 'resend_verify' === posted_action() ) {
            handle_resend( $user );
        } else {
            flash( 'csrf' );
        }
        redirect( account_url() );
    }
    render_app( $user, 'overview', 'Your account', static function () use ( $user ) {
        render( 'account/overview', overview_data( $user ) );
    } );
}

function overview_data( \WP_User $user ): array {
    $uid       = (int) $user->ID;
    $checklist = security_checklist( $user );
    $score     = count( array_filter( array_column( $checklist, 'done' ) ) );
    $cards     = array();
    foreach ( (array) apply_filters( 'hr_account_overview_cards', array(), $user ) as $card ) {
        if ( ! is_array( $card ) || empty( $card['title'] ) ) {
            continue;
        }
        $link = $card['link'] ?? null;
        if ( is_string( $link ) ) {
            $link = array( 'label' => 'Open', 'url' => $link );
        }
        $cards[] = array(
            'title'     => wp_strip_all_tags( (string) $card['title'] ),
            'value'     => wp_strip_all_tags( (string) ( $card['value'] ?? '' ) ),
            'body_html' => wp_kses_post( (string) ( $card['body_html'] ?? '' ) ),
            'link'      => is_array( $link ) && ! empty( $link['url'] ) ? array( 'label' => wp_strip_all_tags( (string) ( $link['label'] ?? 'Open' ) ), 'url' => esc_url_raw( (string) $link['url'] ) ) : null,
            'icon'      => sanitize_key( (string) ( $card['icon'] ?? 'file-text' ) ),
            'order'     => (int) ( $card['order'] ?? 50 ),
        );
    }
    usort( $cards, static fn( $a, $b ) => $a['order'] <=> $b['order'] );
    $flash = take_flash();
    return array(
        'user'      => $user,
        'greeting'  => greeting(),
        'first'     => first_name( $user ),
        'verified'  => is_verified( $uid ),
        'welcome'   => $flash && 'welcome' === $flash['key'],
        'cards'     => $cards,
        'checklist' => $checklist,
        'score'     => $score,
        'level'     => security_level( $score ),
        'since'     => strtotime( $user->user_registered . ' UTC' ) ?: 0,
        'pages'     => account_pages(),
        'last'      => (int) get_user_meta( $uid, 'hr_prev_login', true ),
    );
}

function greeting(): string {
    $hour = (int) wp_date( 'G' );
    return $hour < 5 ? 'Good evening' : ( $hour < 12 ? 'Good morning' : ( $hour < 17 ? 'Good afternoon' : 'Good evening' ) );
}

// ---------------------------------------------------------------------------------------------------------------
// Pages contributed through hr_account_pages
// ---------------------------------------------------------------------------------------------------------------

function route_contributed( \WP_User $user, string $slug, array $page ): void {
    if ( $page['url'] && ! $page['render'] ) {
        redirect( $page['url'], 302 );
    }
    $gated = $page['requires_verified'] && ! is_verified( (int) $user->ID );
    if ( is_post() ) {
        $action = posted_action();
        if ( 'resend_verify' === $action ) {
            handle_resend( $user );
            redirect( account_url( $slug ) );
        }
        if ( ! same_origin_request() ) {
            log_security( 'account_page_origin', array( 'page' => $slug ) );
            flash( 'csrf' );
            redirect( account_url( $slug ) );
        }
        if ( in_array( $action, array( 'stepup_password', 'stepup_totp' ), true ) ) {
            // step_up_panel() on a contributed page: confirm here and come back to this page. On a wrong password
            // the page renders again with the error inside its panel; the module's handler is not called.
            if ( ! verify_form( $action ) ) {
                flash( 'csrf' );
                redirect( account_url( $slug ) );
            }
            handle_stepup( $user, $action, account_url( $slug ) );
        } elseif ( ! $gated && $page['handle'] ) {
            call_user_func( $page['handle'], $user );
        }
    }
    render_app( $user, $slug, $page['title'], static function () use ( $user, $page, $gated ) {
        if ( $gated ) {
            render( 'account/gate', array( 'user' => $user, 'title' => $page['title'] ) );
            return;
        }
        call_user_func( $page['render'], $user );
    } );
}

// ---------------------------------------------------------------------------------------------------------------
// Content-Security-Policy: account pages hold credentials, so they load no third-party code at all.
// The CSP header is sent on send_headers, before the main query, so the route comes from the parsed request.
// ---------------------------------------------------------------------------------------------------------------

function is_account_request(): bool {
    global $wp;
    return isset( $wp->query_vars['hr_route'] ) && 'account' === $wp->query_vars['hr_route'];
}

add_filter( 'hr_csp_sources', function ( array $sources ) {
    if ( ! is_account_request() ) {
        return $sources;
    }
    $sources['script-src']      = array( "'self'", "'unsafe-inline'" );
    $sources['connect-src']     = array( "'self'" );
    $sources['frame-src']       = array( "'none'" );
    $sources['child-src']       = array( "'none'" );
    $sources['form-action']     = array( "'self'" );
    $sources['frame-ancestors'] = array( "'none'" );
    return $sources;
}, 99 );

// Third-party widgets the theme or other plugins print site-wide would only trip the CSP above; leave them out,
// together with any local script that depends on one (e.g. a reCAPTCHA bootstrap without reCAPTCHA).
add_action( 'wp_enqueue_scripts', function () {
    if ( ! is_account_request() ) {
        return;
    }
    $scripts = wp_scripts();
    $home    = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
    $foreign = static function ( string $handle, array $seen = array() ) use ( &$foreign, $scripts, $home ): bool {
        $dep = $scripts->registered[ $handle ] ?? null;
        if ( ! $dep || isset( $seen[ $handle ] ) ) {
            return false;
        }
        $host = strtolower( (string) wp_parse_url( (string) $dep->src, PHP_URL_HOST ) );
        if ( '' !== $host && $host !== $home ) {
            return true;
        }
        $seen[ $handle ] = true;
        foreach ( (array) $dep->deps as $child ) {
            if ( $foreign( (string) $child, $seen ) ) {
                return true;
            }
        }
        return false;
    };
    foreach ( (array) $scripts->queue as $handle ) {
        if ( $foreign( (string) $handle ) ) {
            wp_dequeue_script( $handle );
        }
    }
    // The parent theme's bundle is built with eval() (a dev build); the CSP blocks it on every page, so do not even
    // load it where it can only produce errors.
    wp_dequeue_script( 'covernews-script' );
}, 999 );

// Snippets and widgets printed straight into wp_head/wp_footer (not enqueued) get the same treatment: on account
// pages any <script> from another host is dropped before it reaches the browser (the CSP would block it anyway).
function strip_foreign_scripts( string $html ): string {
    $home = preg_quote( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ), '#' );
    return (string) preg_replace( '#<script\b[^>]*\bsrc=["\']?(?:https?:)?//(?!' . $home . '[:/"\'])[^>]*>.*?</script\s*>#is', '', $html );
}

foreach ( array( 'wp_head', 'wp_body_open', 'wp_footer' ) as $hr_accounts_hook ) {
    add_action( $hr_accounts_hook, function () {
        if ( is_account_request() ) {
            ob_start();
        }
    }, PHP_INT_MIN );
    add_action( $hr_accounts_hook, function () {
        if ( is_account_request() && ob_get_level() > 0 ) {
            echo strip_foreign_scripts( (string) ob_get_clean() ); // phpcs:ignore -- markup produced by WordPress hooks, only filtered here
        }
    }, PHP_INT_MAX );
}
unset( $hr_accounts_hook );

add_action( 'template_redirect', function () {
    if ( is_account_request() && ! headers_sent() ) {
        header( 'X-Frame-Options: DENY' );
    }
}, 0 );
