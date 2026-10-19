<?php
/**
 * /thank-you/?for=newsletter|contact|media-kit|generic — confirmation page after a form, with next steps.
 *
 * Readers land here after a successful newsletter sign-up (Mailchimp for WP form 2953, plain POST) and after the
 * contact and media-kit forms (Contact Form 7 posts normally because its JavaScript is switched off site-wide).
 * The page is the same for everyone (cache-safe) and kept out of search results.
 */

namespace HR\Platform\Site;

use function HR\Platform\add_route;
use function HR\Platform\enqueue_base_style;
use function HR\Platform\asset_url;
use function HR\Platform\asset_ver;
use function HR\Platform\render;
use function HR\Platform\route_url;
use function HR\Platform\set_page_title;

if ( ! defined( 'ABSPATH' ) ) exit;

const NEWSLETTER_FORM_ID = 2953;

function thank_you_variants(): array {
    return array(
        'newsletter' => array(
            'eyebrow' => 'Intelligence Briefing',
            'title'   => 'Almost there — check your inbox',
            'lede'    => 'We have sent you an email to confirm your subscription. Click the link inside and the weekly Intelligence Briefing will start arriving.',
            'hint'    => 'Nothing yet? Check your spam or promotions folder, or sign up again with a different address.',
        ),
        'contact'    => array(
            'eyebrow' => 'Message received',
            'title'   => 'Thank you for getting in touch',
            'lede'    => 'Your message is with the Headline Recorder team. We read every message and will reply by email.',
            'hint'    => 'Sending a story tip or a correction? Include links or documents in your email to contact@headlinerecorder.com.',
        ),
        'media-kit'  => array(
            'eyebrow' => 'Request received',
            'title'   => 'Your media kit request is in',
            'lede'    => 'Thank you for your interest in partnering with Headline Recorder. Our team will email you the media kit and next steps.',
            'hint'    => '',
        ),
        'generic'    => array(
            'eyebrow' => 'Thank you',
            'title'   => 'Thank you',
            'lede'    => 'We have received your request.',
            'hint'    => '',
        ),
    );
}

add_route( 'thank-you', '^thank-you/?$', function () {
    $variants = thank_you_variants();
    $for      = isset( $_GET['for'] ) ? sanitize_key( wp_unslash( $_GET['for'] ) ) : 'generic';
    $for      = isset( $variants[ $for ] ) ? $for : 'generic';

    set_page_title( 'newsletter' === $for ? 'Confirm your subscription' : 'Thank you', false );
    add_action( 'wp_enqueue_scripts', function () {
        enqueue_base_style();
        wp_enqueue_style( 'hr-site', asset_url( 'css/site.css' ), array( 'hr-platform' ), asset_ver( 'css/site.css' ) );
    } );
    add_filter( 'body_class', static fn( $classes ) => array_merge( $classes, array( 'hr-thank-you-page' ) ) );

    $latest = get_posts( array(
        'post_type'           => 'post',
        'posts_per_page'      => 3,
        'ignore_sticky_posts' => true,
        'no_found_rows'       => true,
        'suppress_filters'    => false,
    ) );

    get_header();
    render( 'site/thank-you', array(
        'for'          => $for,
        'copy'         => $variants[ $for ],
        'latest'       => $latest,
        'podcast_url'  => post_type_exists( 'hr_episode' ) ? (string) get_post_type_archive_link( 'hr_episode' ) : '',
        'podcast_name' => 'podcast' === strtolower( podcast_info()['title'] ) ? 'the Podcast' : podcast_info()['title'],
        'account_url'  => isset( $GLOBALS['hr_platform_routes']['account'] ) && ! is_user_logged_in() ? route_url( 'account/register' ) : '',
        'streak_url'   => isset( $GLOBALS['hr_platform_routes']['account'] ) && is_user_logged_in() ? route_url( 'account' ) : '',
    ) );
    get_footer();
} );

// Newsletter: send successful plain-POST sign-ups of the footer form here (unless an editor set another redirect).
add_filter( 'mc4wp_form_redirect_url', function ( $url, $form ) {
    if ( '' === trim( (string) $url ) && is_object( $form ) && (int) ( $form->ID ?? 0 ) === NEWSLETTER_FORM_ID ) {
        return add_query_arg( 'for', 'newsletter', route_url( 'thank-you' ) );
    }
    return $url;
}, 20, 2 );

// Contact Form 7: remember which form sent mail, then redirect once CF7 has finished (wpcf7_submit runs last).
add_action( 'wpcf7_mail_sent', function ( $contact_form ) {
    $GLOBALS['hr_site_cf7_sent'] = is_object( $contact_form ) && method_exists( $contact_form, 'id' ) ? (int) $contact_form->id() : 0;
} );

add_action( 'wpcf7_submit', function ( $contact_form, $result ) {
    if ( empty( $GLOBALS['hr_site_cf7_sent'] ) || 'mail_sent' !== ( $result['status'] ?? '' ) ) {
        return;
    }
    // AJAX submissions (REST) show CF7's own message; only plain form posts are redirected.
    if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || wp_doing_ajax() || ( class_exists( '\WPCF7_Submission' ) && \WPCF7_Submission::is_restful() ) ) {
        return;
    }
    $title = is_object( $contact_form ) && method_exists( $contact_form, 'title' ) ? strtolower( (string) $contact_form->title() ) : '';
    $for   = str_contains( $title, 'media kit' ) ? 'media-kit' : 'contact';
    $for   = (string) apply_filters( 'hr_thank_you_variant', $for, $contact_form );
    wp_safe_redirect( add_query_arg( 'for', $for, route_url( 'thank-you' ) ), 303 );
    exit;
}, 99, 2 );
