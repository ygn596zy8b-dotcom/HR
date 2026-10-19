<?php
/**
 * Submissions: the account pages — /account/submit/ (sealed form) and /account/submissions/ (list + withdraw).
 */

namespace HR\Platform\Submissions;

use HR\Platform\Accounts;
use function HR\Platform\render;
use function HR\Platform\asset_url;
use function HR\Platform\asset_ver;
use function HR\Platform\log_security;

if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'hr_account_pages', function ( array $pages ) {
    $pages['submit']      = array(
        'title'             => 'Submit an article',
        'icon'              => 'pen',
        'order'             => 30,
        'requires_verified' => true,
        'render'            => __NAMESPACE__ . '\\render_submit',
    );
    $pages['submissions'] = array(
        'title'  => 'My submissions',
        'icon'   => 'file-text',
        'order'  => 40,
        'render' => __NAMESPACE__ . '\\render_list',
        'handle' => __NAMESPACE__ . '\\handle_withdraw',
    );
    return $pages;
} );

// Enqueued from the head (the account router sets the current page before the theme header runs).
add_action( 'wp_enqueue_scripts', function () {
    $sub = (string) ( $GLOBALS['hr_accounts_sub'] ?? '' );
    if ( ! is_user_logged_in() || ! in_array( $sub, array( 'submit', 'submissions' ), true ) ) {
        return;
    }
    wp_enqueue_style( 'hr-submissions', asset_url( 'css/submissions.css' ), array( 'hr-account' ), asset_ver( 'css/submissions.css' ) );
    if ( 'submit' === $sub && is_open() && Accounts\is_verified( get_current_user_id() ) ) {
        wp_enqueue_script( 'hr-seal', asset_url( 'js/seal.js' ), array(), asset_ver( 'js/seal.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
    }
}, 20 );

function render_submit( \WP_User $user ): void {
    if ( ! is_open() ) {
        render( 'submissions/closed' );
        return;
    }
    $profile = get_user_meta( $user->ID, '_hr_sub_profile', true );
    render( 'submissions/submit', array(
        'user'       => $user,
        'key'        => public_key(),
        'categories' => categories(),
        'guidelines' => array_filter( array_map( 'trim', explode( "\n", (string) \HR\Platform\setting( 'submissions_guidelines' ) ) ) ),
        'profile'    => is_array( $profile ) ? $profile : array(),
        'words'      => WORDS,
    ) );
}

function render_list( \WP_User $user ): void {
    $posts = get_posts( array(
        'post_type'   => CPT,
        'post_status' => 'any', // editors can change it from private (Quick Edit, Visibility); the reader still sees it
        'author'      => (int) $user->ID, // object-level: only this reader's own submissions
        'numberposts' => 100,
        'orderby'     => 'date',
        'order'       => 'DESC',
    ) );
    render( 'submissions/list', array(
        'items'     => $posts,
        'withdrawn' => isset( $_GET['withdrawn'] ),
        'error'     => $GLOBALS['hr_submissions_error'] ?? '',
    ) );
}

/** Withdraw: POST + nonce + origin (verify_form), and the submission must be this reader's and still unread. */
function handle_withdraw( \WP_User $user ): void {
    if ( ! Accounts\verify_form( 'withdraw' ) ) {
        $GLOBALS['hr_submissions_error'] = 'This page was open for too long. Please try again.';
        return;
    }
    $id   = isset( $_POST['submission'] ) && is_string( $_POST['submission'] ) && ctype_digit( $_POST['submission'] ) ? (int) $_POST['submission'] : 0;
    $post = get_submission( $id );
    if ( ! $post || (int) $post->post_author !== (int) $user->ID ) {
        log_security( 'submission_withdraw_denied', array( 'id' => $id ) );
        $GLOBALS['hr_submissions_error'] = 'That submission was not found.';
        return;
    }
    if ( 'received' !== meta( $id, 'status' ) ) {
        $GLOBALS['hr_submissions_error'] = 'An editor is already working on that submission, so it can no longer be withdrawn. Reply to their email instead.';
        return;
    }
    wp_delete_post( $id, true );
    log_security( 'submission_withdrawn', array( 'id' => $id ) );
    Accounts\redirect( add_query_arg( 'withdrawn', '1', Accounts\account_url( 'submissions' ) ) );
}

/** Status badge class for a submission status. */
function status_class( string $status ): string {
    return array( 'accepted' => 'hrp-badge--ok', 'published' => 'hrp-badge--ok', 'declined' => 'hrp-badge--red' )[ $status ] ?? 'hrp-badge--muted';
}
