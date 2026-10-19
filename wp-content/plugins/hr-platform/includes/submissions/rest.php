<?php
/**
 * Submissions: REST endpoints. Readers POST sealed envelopes; editors fetch them and turn an opened submission into
 * a draft post. Contract in module.php.
 */

namespace HR\Platform\Submissions;

use HR\Platform\Accounts;
use function HR\Platform\no_store;
use function HR\Platform\rate_limit;
use function HR\Platform\ip_hash;
use function HR\Platform\log_security;
use function HR\Platform\json_body;
use function HR\Platform\b64url_decode;

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'rest_api_init', function () {
    // Object-level check: the submission must exist and this user must be able to edit it (editors/administrators).
    // These are wp-admin tools, so staff that the accounts module keeps out of wp-admin until they enrol MFA are refused too.
    $can_edit = static fn( \WP_REST_Request $r ) => null !== get_submission( (int) $r['id'] ) && current_user_can( 'edit_hr_submission', (int) $r['id'] )
        && ! ( function_exists( 'HR\Platform\Accounts\staff_mfa_enforced' ) && Accounts\staff_mfa_enforced( wp_get_current_user() ) && ! Accounts\has_mfa( get_current_user_id() ) );
    register_rest_route( 'hr/v1', '/submissions', array(
        'methods'             => 'POST',
        'callback'            => __NAMESPACE__ . '\\rest_submit',
        'permission_callback' => static fn() => is_user_logged_in(),
    ) );
    register_rest_route( 'hr/v1', '/submissions/(?P<id>\d{1,20})/envelope', array(
        'methods'             => 'GET',
        'callback'            => __NAMESPACE__ . '\\rest_envelope',
        'permission_callback' => $can_edit,
    ) );
    register_rest_route( 'hr/v1', '/submissions/(?P<id>\d{1,20})/draft', array(
        'methods'             => 'POST',
        'callback'            => __NAMESPACE__ . '\\rest_draft',
        'permission_callback' => static fn( \WP_REST_Request $r ) => $can_edit( $r ) && current_user_can( 'edit_posts' ),
    ) );
} );

function fail( string $code, string $message, int $status, string $field = '' ): \WP_Error {
    return new \WP_Error( 'hr_submission_' . $code, $message, array_filter( array( 'status' => $status, 'field' => $field ) ) );
}

/** Decoded length of a base64url string, or -1 if it is not one (strspn: no regex over megabytes). */
function b64u_len( $value ): int {
    static $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
    if ( ! is_string( $value ) || '' === $value || strspn( $value, $alphabet ) !== strlen( $value ) || 1 === strlen( $value ) % 4 ) {
        return -1;
    }
    return intdiv( strlen( $value ) * 3, 4 );
}

/** The envelope must be exactly what pq.mjs seal() makes for the configured key. */
function valid_envelope( $env, string $kid ): bool {
    if ( ! is_array( $env ) || 4 !== count( $env ) || 12 !== b64u_len( $env['iv'] ?? null ) || KEM_BYTES !== b64u_len( $env['kem'] ?? null ) ) {
        return false;
    }
    $ct = b64u_len( $env['ct'] ?? null );
    if ( $ct < 16 || $ct > MAX_CT || b64u_len( $env['protected'] ?? null ) < 1 || strlen( $env['protected'] ) > 400 ) {
        return false;
    }
    $header = json_decode( b64url_decode( $env['protected'] ), true );
    return is_array( $header ) && 3 === count( $header ) && 1 === ( $header['v'] ?? null ) && SEAL_ALG === ( $header['alg'] ?? null )
        && is_string( $header['kid'] ?? null ) && hash_equals( $kid, $header['kid'] );
}

/** Plain-text field: a string no longer than $max characters, or null. */
function str_field( array $d, string $key, int $max ): ?string {
    $v = $d[ $key ] ?? '';
    return is_string( $v ) && mb_strlen( $v ) <= $max ? trim( $v ) : null;
}

/** Checks the unencrypted fields of a submission. Returns the clean values or a WP_Error naming the field. */
function validate_submission( array $d, array $key ) {
    $type = is_string( $d['type'] ?? null ) && isset( TYPES[ $d['type'] ] ) ? $d['type'] : '';
    if ( '' === $type ) {
        return fail( 'invalid', 'Choose Article or Op-ed.', 400, 'type' );
    }
    $title = sanitize_text_field( (string) str_field( $d, 'title', 160 ) );
    if ( '' === $title ) {
        return fail( 'invalid', 'Enter a headline of up to 160 characters.', 400, 'title' );
    }
    $category = is_int( $d['category'] ?? null ) || ( is_string( $d['category'] ?? null ) && ctype_digit( $d['category'] ) ) ? (int) $d['category'] : 0;
    if ( ! isset( categories()[ $category ] ) ) {
        return fail( 'invalid', 'Choose a section from the list.', 400, 'category' );
    }
    // The server cannot count sealed text: it checks the count the browser reports, and the editors' tool recounts.
    [ $min, $max ] = word_range( $type );
    $words = is_int( $d['words'] ?? null ) ? $d['words'] : -1;
    if ( $words < $min || $words > $max ) {
        return fail( 'invalid', sprintf( '%s must be %s–%s words.', 'oped' === $type ? 'Op-eds' : 'Articles', number_format( $min ), number_format( $max ) ), 400, 'body' );
    }
    $email = sanitize_email( (string) str_field( $d, 'email', 100 ) );
    if ( ! is_email( $email ) ) {
        return fail( 'invalid', 'Enter a valid email address.', 400, 'email' );
    }
    if ( ! is_bool( $d['has_attachment'] ?? null ) ) {
        return fail( 'invalid', 'Malformed request.', 400 );
    }
    if ( ! valid_envelope( $d['envelope'] ?? null, $key['kid'] ) ) {
        return fail( 'envelope', 'Your submission could not be verified. Reload the page and try again.', 400 );
    }
    $profile = null;
    if ( isset( $d['profile'] ) ) {
        $p       = is_array( $d['profile'] ) ? $d['profile'] : array();
        $profile = array(
            'name'        => sanitize_text_field( (string) str_field( $p, 'name', 100 ) ),
            'designation' => sanitize_text_field( (string) str_field( $p, 'designation', 120 ) ),
            'bio'         => sanitize_textarea_field( (string) str_field( $p, 'bio', 600 ) ),
        );
    }
    return compact( 'type', 'title', 'category', 'words', 'email', 'profile' ) + array( 'has_attachment' => $d['has_attachment'], 'envelope' => $d['envelope'] );
}

// Size cap before WordPress parses the JSON body (that happens before the route's callbacks run). Routes match
// case-insensitively, so /HR/V1/Submissions reaches rest_submit too and must hit this cap.
add_filter( 'rest_pre_dispatch', function ( $result, $server, \WP_REST_Request $request ) {
    if ( null === $result && 0 === strcasecmp( untrailingslashit( $request->get_route() ), '/hr/v1/submissions' ) && 'POST' === $request->get_method()
        && ( (int) $request->get_header( 'content_length' ) > MAX_BODY || strlen( $request->get_body() ) > MAX_BODY ) ) {
        return fail( 'too_large', 'This submission is too large. Keep the attachment under 8 MB.', 413 );
    }
    return $result;
}, 10, 3 );

function rest_submit( \WP_REST_Request $request ) {
    no_store();
    $uid = get_current_user_id();
    $key = public_key();
    if ( ! is_open() || ! $key ) {
        return fail( 'closed', 'Submissions are closed at the moment. Please try again later.', 503 );
    }
    if ( ! current_user_can( 'edit_posts' ) && ! Accounts\is_verified( $uid ) ) {
        return fail( 'unverified', 'Confirm your email address before submitting.', 403 );
    }
    if ( ! rate_limit( 'submission_minute', (string) $uid, 1, MINUTE_IN_SECONDS ) ) {
        return fail( 'rate_limited', 'Please wait a minute before submitting again.', 429 );
    }
    if ( ! rate_limit( 'submission_day', (string) $uid, 5, DAY_IN_SECONDS ) || ! rate_limit( 'submission_ip', ip_hash(), 20, DAY_IN_SECONDS ) ) {
        return fail( 'rate_limited', 'You have reached today\'s limit for submissions. Please try again tomorrow.', 429 );
    }
    wp_raise_memory_limit( 'hr_submissions' );
    $data = $request->get_json_params();
    $f    = validate_submission( is_array( $data ) ? $data : array(), $key );
    if ( is_wp_error( $f ) ) {
        log_security( 'submission_rejected', array( 'reason' => $f->get_error_code(), 'field' => $f->get_error_data()['field'] ?? '' ) );
        return $f;
    }

    // Stored byte-for-byte as the browser serialises it, so both sides compute the same fingerprint.
    $env  = $f['envelope'];
    $json = (string) wp_json_encode( array( 'protected' => $env['protected'], 'kem' => $env['kem'], 'iv' => $env['iv'], 'ct' => $env['ct'] ), JSON_UNESCAPED_SLASHES );
    unset( $data, $env, $f['envelope'] );
    $file = write_envelope( $json );
    if ( '' === $file ) {
        log_security( 'submission_store_failed' );
        return fail( 'storage', 'We could not save your submission. Please try again in a few minutes.', 500 );
    }
    $sha = hash( 'sha256', $json );
    $id  = wp_insert_post( wp_slash( array(
        'post_type'   => CPT,
        'post_status' => 'private',
        'post_author' => $uid,
        'post_title'  => $f['title'],
        'meta_input'  => array(
            '_hr_sub_type'           => $f['type'],
            '_hr_sub_category'       => $f['category'],
            '_hr_sub_status'         => 'received',
            '_hr_sub_file'           => $file,
            '_hr_sub_sha256'         => $sha,
            '_hr_sub_bytes'          => strlen( $json ),
            '_hr_sub_words'          => $f['words'],
            '_hr_sub_has_attachment' => $f['has_attachment'] ? '1' : '0',
            '_hr_sub_email'          => $f['email'],
            '_hr_sub_kid'            => $key['kid'],
        ),
    ) ), true );
    if ( is_wp_error( $id ) || ! $id ) {
        unlink( envelope_path( $file ) );
        log_security( 'submission_store_failed' );
        return fail( 'storage', 'We could not save your submission. Please try again in a few minutes.', 500 );
    }
    if ( $f['profile'] ) {
        update_user_meta( $uid, '_hr_sub_profile', wp_slash( $f['profile'] ) );
    } else {
        delete_user_meta( $uid, '_hr_sub_profile' );
    }

    $type = TYPES[ $f['type'] ];
    Accounts\send_mail( notify_email(), sprintf( 'New %s #%d: %s', strtolower( $type ), $id, $f['title'] ), array(
        'preheader'  => sprintf( '#%d %s — %s', $id, $f['title'], $type ),
        'heading'    => sprintf( 'New submission #%d', $id ),
        'paragraphs' => array( sprintf( '#%d %s — %s — open in wp-admin.', $id, $f['title'], $type ), 'The content is sealed. Open it on the submission screen with the editors\' key.' ),
        'button'     => array( 'label' => 'Open in wp-admin', 'url' => admin_url( 'post.php?post=' . $id . '&action=edit' ) ),
    ) );
    // The receipt goes to the verified account address, so this form cannot be used to mail strangers.
    Accounts\send_mail( wp_get_current_user()->user_email, sprintf( 'We received your submission #%d', $id ), array(
        'preheader'  => 'Your submission is with our editors.',
        'heading'    => sprintf( 'Submission #%d received', $id ),
        'paragraphs' => array(
            sprintf( 'Thank you. Your %s "%s" reached our editors, encrypted in your browser before it left your device.', strtolower( $type ), $f['title'] ),
            'Fingerprint (SHA-256): ' . trim( chunk_split( $sha, 8, ' ' ) ),
            'An editor will reply within 7 days. You can follow its status in your account.',
        ),
        'button'     => array( 'label' => 'My submissions', 'url' => Accounts\account_url( 'submissions' ) ),
    ) );
    log_security( 'submission_received', array( 'id' => $id, 'bytes' => strlen( $json ) ) );
    return new \WP_REST_Response( array( 'id' => $id, 'sha256' => $sha ), 201 );
}

function rest_envelope( \WP_REST_Request $request ) {
    no_store();
    $id   = (int) $request['id'];
    $path = envelope_path( meta( $id, 'file' ) );
    if ( '' === $path ) {
        return fail( 'missing', 'The sealed file for this submission is missing.', 404 );
    }
    wp_raise_memory_limit( 'hr_submissions' );
    $env = json_decode( (string) file_get_contents( $path ), true );
    log_security( 'submission_opened', array( 'id' => $id ) );
    return is_array( $env ) ? $env : fail( 'missing', 'The sealed file for this submission is damaged.', 500 );
}

function rest_draft( \WP_REST_Request $request ) {
    no_store();
    $id    = (int) $request['id'];
    $draft = (int) meta( $id, 'draft' );
    if ( $draft && get_post( $draft ) && 'trash' !== get_post_status( $draft ) ) {
        return fail( 'exists', 'A draft was already created from this submission.', 409 );
    }
    $d = json_body( $request, 512 * 1024 );
    if ( ! $d ) {
        return fail( 'invalid', 'Malformed request.', 400 );
    }
    $title = sanitize_text_field( (string) str_field( $d, 'title', 160 ) );
    $body  = (string) str_field( $d, 'body_text', 100000 );
    $name  = sanitize_text_field( (string) str_field( $d, 'guest_author_name', 100 ) );
    if ( '' === $title || '' === $body || '' === $name ) {
        return fail( 'invalid', 'The headline, body and writer\'s name are required.', 400 );
    }
    $avatar = 0;
    if ( isset( $d['avatar_id'] ) ) {
        $avatar = is_int( $d['avatar_id'] ) ? $d['avatar_id'] : 0;
        if ( ! $avatar || ! wp_attachment_is_image( $avatar ) || ! current_user_can( 'edit_post', $avatar ) ) {
            return fail( 'invalid', 'The headshot upload was not found.', 400 );
        }
    }
    $opinion  = get_category_by_slug( 'opinion' );
    $category = 'oped' === meta( $id, 'type' ) && $opinion ? (int) $opinion->term_id : (int) meta( $id, 'category' );
    $draft    = wp_insert_post( wp_slash( array(
        'post_type'     => 'post',
        'post_status'   => 'draft',
        'post_author'   => get_current_user_id(),
        'post_title'    => $title,
        'post_excerpt'  => sanitize_textarea_field( (string) str_field( $d, 'standfirst', 400 ) ),
        'post_content'  => wp_kses_post( wpautop( esc_html( $body ) ) ),
        'post_category' => $category ? array( $category ) : array(),
        'meta_input'    => array(
            'guest_author_name'   => $name,
            'guest_author_bio'    => sanitize_textarea_field( (string) str_field( $d, 'guest_author_bio', 800 ) ),
            'guest_author_avatar' => $avatar ? (string) wp_get_attachment_url( $avatar ) : '',
        ),
    ) ), true );
    if ( is_wp_error( $draft ) || ! $draft ) {
        return fail( 'draft', 'The draft could not be created.', 500 );
    }
    if ( taxonomy_exists( 'guest_author' ) && current_user_can( 'manage_categories' ) ) {
        wp_set_object_terms( $draft, $name, 'guest_author' );
    }
    update_post_meta( $id, '_hr_sub_draft', $draft );
    if ( in_array( meta( $id, 'status' ), array( 'received', 'in_review' ), true ) ) {
        update_post_meta( $id, '_hr_sub_status', 'accepted' );
    }
    log_security( 'submission_draft_created', array( 'id' => $id, 'draft' => $draft ) );
    return new \WP_REST_Response( array( 'draft_id' => $draft, 'edit_url' => get_edit_post_link( $draft, 'raw' ) ), 201 );
}
