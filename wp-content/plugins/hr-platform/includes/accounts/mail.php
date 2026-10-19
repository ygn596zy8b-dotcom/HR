<?php
/**
 * Accounts: branded transactional email (HTML + plain-text alternative).
 *
 * Mail is sent after the response has been flushed where the server supports it (LiteSpeed/PHP-FPM), so
 * "an email was sent" and "no email was sent" take the same time from the visitor's point of view.
 * Emails never contain passwords, and links are the only secrets they carry.
 */

namespace HR\Platform\Accounts;

use function HR\Platform\render_to_string;
use function HR\Platform\log_security;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Queue a message. $content keys: preheader, heading, paragraphs (string[]), button ['label','url'],
 * after (string[]), note (small print). All values are plain text; the layout escapes them.
 */
function send_mail( string $to, string $subject, array $content ): void {
    if ( ! is_email( $to ) ) {
        return;
    }
    $GLOBALS['hr_accounts_mail_queue'][] = array( $to, $subject, $content );
    static $hooked = false;
    if ( ! $hooked ) {
        $hooked = true;
        add_action( 'shutdown', __NAMESPACE__ . '\\flush_mail', PHP_INT_MAX );
    }
}

function flush_mail(): void {
    $queue = $GLOBALS['hr_accounts_mail_queue'] ?? array();
    $GLOBALS['hr_accounts_mail_queue'] = array();
    if ( ! $queue ) {
        return;
    }
    if ( function_exists( 'litespeed_finish_request' ) ) {
        litespeed_finish_request();
    } elseif ( function_exists( 'fastcgi_finish_request' ) ) {
        fastcgi_finish_request();
    }
    foreach ( $queue as [ $to, $subject, $content ] ) {
        deliver( $to, $subject, $content );
    }
}

function deliver( string $to, string $subject, array $content ): bool {
    $content += array( 'preheader' => '', 'heading' => $subject, 'paragraphs' => array(), 'button' => null, 'after' => array(), 'note' => '' );
    $html = render_to_string( 'account/emails/layout', array( 'subject' => $subject, 'c' => $content ) );
    $GLOBALS['hr_accounts_mail_alt'] = text_version( $content );
    $ok = wp_mail( $to, $subject, $html, array(
        'Content-Type: text/html; charset=UTF-8',
        'Auto-Submitted: auto-generated',
        'X-Auto-Response-Suppress: All',
    ) );
    unset( $GLOBALS['hr_accounts_mail_alt'] );
    if ( ! $ok ) {
        log_security( 'account_mail_failed', array( 'subject' => $subject ) );
    }
    return (bool) $ok;
}

function text_version( array $c ): string {
    $lines   = array( 'HEADLINE RECORDER', '', (string) $c['heading'], '' );
    foreach ( (array) $c['paragraphs'] as $p ) {
        $lines[] = wordwrap( (string) $p, 76 );
        $lines[] = '';
    }
    if ( ! empty( $c['button']['url'] ) ) {
        $lines[] = $c['button']['label'] . ':';
        $lines[] = $c['button']['url'];
        $lines[] = '';
    }
    foreach ( (array) $c['after'] as $p ) {
        $lines[] = wordwrap( (string) $p, 76 );
        $lines[] = '';
    }
    if ( '' !== (string) $c['note'] ) {
        $lines[] = wordwrap( (string) $c['note'], 76 );
        $lines[] = '';
    }
    $lines[] = '—';
    $lines[] = 'Headline Recorder · ' . home_url( '/' );
    return implode( "\n", $lines );
}

add_action( 'phpmailer_init', function ( $phpmailer ) {
    if ( ! empty( $GLOBALS['hr_accounts_mail_alt'] ) ) {
        $phpmailer->AltBody = (string) $GLOBALS['hr_accounts_mail_alt'];
    }
} );

// ---------------------------------------------------------------------------------------------------------------
// Messages
// ---------------------------------------------------------------------------------------------------------------

function mail_verify( \WP_User $user, string $url ): void {
    send_mail( $user->user_email, 'Confirm your email address', array(
        'preheader'  => 'One click and your Headline Recorder account is ready.',
        'heading'    => 'Confirm your email address',
        'paragraphs' => array(
            'Welcome to Headline Recorder. Confirm this address to finish setting up your account, keep your reading streak and submit articles and op-eds.',
        ),
        'button'     => array( 'label' => 'Confirm my email', 'url' => $url ),
        'after'      => array( 'This link works once and expires in 48 hours.' ),
        'note'       => 'If you did not create an account, you can ignore this email — nothing happens unless the link is opened and confirmed.',
    ) );
}

function mail_register_exists( \WP_User $user ): void {
    send_mail( $user->user_email, 'Someone tried to create an account with your email', array(
        'preheader'  => 'You already have a Headline Recorder account.',
        'heading'    => 'You already have an account',
        'paragraphs' => array(
            'Someone (hopefully you) just tried to create a Headline Recorder account with this email address. You already have one, so nothing has changed.',
            'If it was you, sign in instead. Forgotten your password? You can reset it in a few seconds.',
        ),
        'button'     => array( 'label' => 'Sign in', 'url' => login_url() ),
        'after'      => array( 'Reset your password: ' . account_url( 'forgot' ) ),
        'note'       => 'If this was not you, you can ignore this email. Your account is safe.',
    ) );
}

function mail_reset( \WP_User $user, string $url ): void {
    send_mail( $user->user_email, 'Reset your Headline Recorder password', array(
        'preheader'  => 'Choose a new password for your account.',
        'heading'    => 'Reset your password',
        'paragraphs' => array( 'We received a request to reset the password for your Headline Recorder account. Choose a new one with the button below.' ),
        'button'     => array( 'label' => 'Choose a new password', 'url' => $url ),
        'after'      => array( 'The link works once. If two-step verification is on, you will still need your authenticator code to sign in.' ),
        'note'       => 'Did not ask for this? Ignore this email and your password stays the same.',
    ) );
}

function mail_new_device( \WP_User $user, string $device ): void {
    send_mail( $user->user_email, 'New sign-in to your Headline Recorder account', array(
        'preheader'  => 'Your account was signed in from ' . $device . '.',
        'heading'    => 'New sign-in to your account',
        'paragraphs' => array(
            'Your Headline Recorder account was just signed in from a device we have not seen before.',
            'Device: ' . $device,
            'Time: ' . wp_date( 'l j F Y, H:i T' ),
            'If this was you, there is nothing to do.',
        ),
        'button'     => array( 'label' => 'Wasn\'t you? Reset your password', 'url' => account_url( 'forgot' ) ),
        'after'      => array( 'Then open Settings → Security to sign out every other session and add a passkey or two-step verification.' ),
    ) );
}

function mail_security_notice( \WP_User $user, string $subject, string $what ): void {
    send_mail( $user->user_email, $subject, array(
        'preheader'  => $what,
        'heading'    => $subject,
        'paragraphs' => array( $what, 'Time: ' . wp_date( 'l j F Y, H:i T' ) . ' · ' . describe_ua( current_ua() ) ),
        'button'     => array( 'label' => 'Review your security settings', 'url' => account_url( 'settings' ) . '#security' ),
        'after'      => array( 'Not you? Reset your password straight away: ' . account_url( 'forgot' ) ),
    ) );
}

function mail_email_change( \WP_User $user, string $new_email, string $url ): void {
    send_mail( $new_email, 'Confirm your new email address', array(
        'preheader'  => 'Confirm this address for your Headline Recorder account.',
        'heading'    => 'Confirm your new email address',
        'paragraphs' => array( 'You asked to use this address for your Headline Recorder account. Confirm it and we will switch your account over.' ),
        'button'     => array( 'label' => 'Confirm new email', 'url' => $url ),
        'after'      => array( 'This link works once and expires in 48 hours. Until then your account keeps its current address.' ),
        'note'       => 'If you did not ask for this, ignore this email.',
    ) );
    send_mail( $user->user_email, 'Your email address is about to change', array(
        'preheader'  => 'A change of email address was requested for your account.',
        'heading'    => 'Email change requested',
        'paragraphs' => array(
            'Someone signed in to your Headline Recorder account asked to change its email address to ' . mask_email( $new_email ) . '.',
            'Nothing changes until the new address is confirmed.',
        ),
        'button'     => array( 'label' => 'Wasn\'t you? Reset your password', 'url' => account_url( 'forgot' ) ),
    ) );
}

function mail_goodbye( string $email ): void {
    send_mail( $email, 'Your Headline Recorder account has been deleted', array(
        'preheader'  => 'Your account and its data have been removed.',
        'heading'    => 'Your account has been deleted',
        'paragraphs' => array(
            'As you asked, we deleted your Headline Recorder account together with your reading history, passkeys and security settings.',
            'You are always welcome back. Our journalism stays free to read.',
        ),
        'button'     => array( 'label' => 'Visit Headline Recorder', 'url' => home_url( '/' ) ),
    ) );
}
