<?php
/**
 * Cookie consent with Google Consent Mode v2.
 *
 * - wp_head priority 1: Consent Mode defaults (everything denied, wait_for_update 500) and the reader's stored choice
 *   (cookie hr_consent) applied immediately; Global Privacy Control counts as "reject".
 * - "basic" mode (default): Site Kit's gtag.js is printed inert (type="text/plain") and only loaded by
 *   assets/js/consent.js after the reader accepts analytics. "advanced" mode loads it at once in Consent Mode.
 * - The banner is a bottom sheet, identical markup for every visitor (safe for LiteSpeed page cache); its state is
 *   applied client-side. A "Cookie settings" link on hr_footer_trust reopens it.
 * - Embedded content (YouTube, TradingView): YouTube embeds use youtube-nocookie.com and are switched off with a
 *   "load video" placeholder when the reader rejects embedded content. Any element marked
 *   data-hr-consent="embeds" (iframe with data-hr-src, or <script type="text/plain" data-hr-src>) is handled the same way.
 */

namespace HR\Platform\Site;

use function HR\Platform\asset_url;
use function HR\Platform\asset_ver;
use function HR\Platform\enqueue_base_style;
use function HR\Platform\render;
use function HR\Platform\setting;

if ( ! defined( 'ABSPATH' ) ) exit;

const CONSENT_COOKIE = 'hr_consent';

function consent_enabled(): bool {
    return (bool) setting( 'consent_banner' ) && ! is_admin();
}

add_action( 'wp_head', function () {
    if ( ! consent_enabled() ) {
        return;
    }
    // Kept tiny and dependency-free: it must run before any Google tag.
    $script = <<<'JS'
(function(w,d){w.dataLayer=w.dataLayer||[];if(typeof w.gtag!=='function'){w.gtag=function(){w.dataLayer.push(arguments);};}
var c=null,g=w.navigator.globalPrivacyControl===true;try{var m=d.cookie.match(/(?:^|;\s*)hr_consent=([^;]+)/);if(m){c=JSON.parse(decodeURIComponent(m[1]));if(!c||c.v!==1){c=null;}}}catch(e){c=null;}
w.gtag('consent','default',{ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',analytics_storage:'denied',functionality_storage:'granted',security_storage:'granted',wait_for_update:500});
w.gtag('set','ads_data_redaction',true);
if(c&&c.analytics===true&&!g){w.gtag('consent','update',{analytics_storage:'granted'});}
w.hrConsentBoot={choice:c,gpc:g};d.documentElement.classList.add(c||g?'hr-consent-set':'hr-consent-pending');})(window,document);
JS;
    wp_print_inline_script_tag( $script, array( 'id' => 'hr-consent-mode' ) );
}, 1 );

// Basic mode: Site Kit's gtag.js stays inert until the reader accepts analytics (consent.js activates it).
add_filter( 'script_loader_tag', function ( $tag, $handle ) {
    if ( 'google_gtagjs' !== $handle || ! consent_enabled() || ! analytics_waits_for_consent() || ! class_exists( '\WP_HTML_Tag_Processor' ) ) {
        return $tag;
    }
    $p = new \WP_HTML_Tag_Processor( $tag );
    while ( $p->next_tag( 'script' ) ) {
        $src = $p->get_attribute( 'src' );
        if ( is_string( $src ) && '' !== $src ) {
            $p->set_attribute( 'type', 'text/plain' );
            $p->set_attribute( 'data-hr-consent', 'analytics' );
            $p->set_attribute( 'data-hr-src', $src );
            $p->remove_attribute( 'src' );
        }
    }
    return $p->get_updated_html();
}, 30, 2 );

add_action( 'wp_enqueue_scripts', function () {
    if ( ! consent_enabled() ) {
        return;
    }
    enqueue_base_style();
    wp_enqueue_style( 'hr-site', asset_url( 'css/site.css' ), array( 'hr-platform' ), asset_ver( 'css/site.css' ) );
    if ( is_readable( HR_PLATFORM_DIR . '/assets/js/consent.js' ) ) {
        wp_enqueue_script( 'hr-consent', asset_url( 'js/consent.js' ), array(), asset_ver( 'js/consent.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
    }
} );

/** The banner prints right after <body> so keyboard and screen-reader users reach it first (it sits at the bottom visually). */
function print_banner(): void {
    static $printed = false;
    if ( $printed || ! consent_enabled() ) {
        return;
    }
    $printed = true;
    render( 'site/consent-banner', array(
        'policy_url' => home_url( '/privacy-policy/#cookies' ),
        'mode'       => analytics_waits_for_consent() ? 'basic' : 'advanced',
    ) );
}
add_action( 'wp_body_open', __NAMESPACE__ . '\\print_banner', 5 );
add_action( 'wp_footer', __NAMESPACE__ . '\\print_banner', 5 ); // themes without wp_body_open

// "Cookie settings" link for the footer's trust bar. Without JavaScript it simply opens the cookie section of the policy.
add_action( 'hr_footer_trust', function () {
    if ( ! consent_enabled() ) {
        return;
    }
    printf(
        '<a class="hr-cookie-settings" href="%s" data-hr-consent-open>Cookie settings</a>',
        esc_url( home_url( '/privacy-policy/#cookies' ) )
    );
}, 20 );

// ---------------------------------------------------------------------------------------------------------------
// YouTube embeds in articles: privacy-enhanced domain, lazy, titled, and switchable by the embedded-content choice.
// ---------------------------------------------------------------------------------------------------------------
add_filter( 'embed_oembed_html', function ( $html, $url ) {
    if ( ! is_string( $html ) || ! preg_match( '#^https?://(?:www\.|m\.)?(?:youtube\.com|youtu\.be)/#i', (string) $url ) || ! class_exists( '\WP_HTML_Tag_Processor' ) ) {
        return $html;
    }
    $p = new \WP_HTML_Tag_Processor( $html );
    if ( ! $p->next_tag( 'iframe' ) ) {
        return $html;
    }
    $src = (string) $p->get_attribute( 'src' );
    $src = preg_replace( '#^(https?:)?//(?:www\.)?youtube\.com/embed/#i', 'https://www.youtube-nocookie.com/embed/', $src );
    $p->set_attribute( 'src', $src );
    $p->set_attribute( 'loading', 'lazy' );
    $p->set_attribute( 'referrerpolicy', 'strict-origin-when-cross-origin' );
    $p->set_attribute( 'data-hr-consent', 'embeds' );
    if ( '' === trim( (string) $p->get_attribute( 'title' ) ) ) {
        $p->set_attribute( 'title', 'YouTube video' );
    }
    return $p->get_updated_html();
}, 20, 2 );
