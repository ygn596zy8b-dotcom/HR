<?php
/**
 * Public files: /signed/<id>.sig.json, /signed/{feed,podcast}.xml(.sig.json), /.well-known/pqsig-keys.json
 * and the /verify/ page.
 */

namespace HR\Platform\Signing;

use function HR\Platform\add_route;
use function HR\Platform\asset_url;
use function HR\Platform\asset_ver;
use function HR\Platform\enqueue_base_style;
use function HR\Platform\render;
use function HR\Platform\set_page_title;

if ( ! defined( 'ABSPATH' ) ) exit;

add_route( 'pqsig_signed', '^signed/((?:[0-9]{1,20}\.sig\.json)|(?:(?:feed|podcast)\.xml(?:\.sig\.json)?))$', __NAMESPACE__ . '\\serve_signed' );
add_route( 'pqsig_keys', '^\.well-known/pqsig-keys\.json$', __NAMESPACE__ . '\\serve_keyset' );
add_route( 'verify', '^verify/?$', __NAMESPACE__ . '\\serve_verify_page' );

/** Signatures are public, cacheable and meant to be fetched by anyone, from anywhere. */
function send_file( string $body, string $type, int $status = 200 ): void {
    status_header( $status );
    if ( ! headers_sent() ) {
        header( 'Content-Type: ' . $type );
        header( 'Access-Control-Allow-Origin: *' );
        header( 'Cross-Origin-Resource-Policy: cross-origin' );
        header( 'X-Content-Type-Options: nosniff' );
        header( 'X-Robots-Tag: noindex' );
        header( 'Cache-Control: public, max-age=' . ( 200 === $status ? 300 : 60 ) );
        header( 'Content-Length: ' . strlen( $body ) );
    }
    if ( 'HEAD' !== ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) {
        echo $body; // phpcs:ignore WordPress.Security.EscapeOutput -- stored signature/feed bytes, served with a non-HTML type and nosniff
    }
}

function send_not_found(): void {
    send_file( '{"error":"not_found"}', 'application/json; charset=utf-8', 404 );
}

function serve_signed( string $sub ): void {
    if ( 1 === preg_match( '/^([0-9]{1,20})\.sig\.json$/', $sub, $m ) ) {
        $doc = signable_post( (int) $m[1] ) ? (string) get_post_meta( (int) $m[1], META_SIG, true ) : '';
        '' === $doc ? send_not_found() : send_file( $doc, 'application/json; charset=utf-8' );
        return;
    }
    if ( 1 === preg_match( '/^(feed|podcast)\.xml(\.sig\.json)?$/', $sub, $m ) ) {
        $feed = stored_feed( $m[1] );
        if ( ! $feed ) {
            send_not_found();
        } elseif ( empty( $m[2] ) ) {
            send_file( (string) $feed['xml'], 'application/rss+xml; charset=utf-8' );
        } else {
            send_file( (string) $feed['sig'], 'application/json; charset=utf-8' );
        }
        return;
    }
    send_not_found();
}

function serve_keyset(): void {
    $keyset = keyset();
    if ( ! $keyset ) {
        send_not_found();
        return;
    }
    send_file( wp_json_encode( $keyset, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) . "\n", 'application/json; charset=utf-8' );
}

function serve_verify_page(): void {
    set_page_title( 'Verify authenticity', true );
    $description = 'Check that a Headline Recorder article or feed is exactly what we published, using post-quantum ML-DSA-87 and Ed448 signatures. Runs in your browser.';
    add_filter( 'wpseo_metadesc', static fn() => $description, 20 );
    add_filter( 'wpseo_opengraph_desc', static fn() => $description, 20 );
    add_action( 'wp_head', static function () use ( $description ) {
        if ( ! defined( 'WPSEO_VERSION' ) ) {
            echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
        }
    }, 2 );
    add_action( 'wp_enqueue_scripts', static function () {
        enqueue_base_style();
        wp_enqueue_style( 'hr-signing', asset_url( 'css/signing.css' ), array( 'hr-platform' ), asset_ver( 'css/signing.css' ) );
        wp_enqueue_script( 'hr-verify', asset_url( 'js/verify.js' ), array(), asset_ver( 'js/verify.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
    } );
    get_header();
    render( 'signing/verify', array(
        'keyset'   => keyset(),
        'rest'     => rest_url( 'hr/v1/' ),
        'keys_url' => home_url( '/.well-known/pqsig-keys.json' ),
    ) );
    get_footer();
}
