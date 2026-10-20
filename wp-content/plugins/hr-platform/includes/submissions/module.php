<?php
/**
 * Submissions — signed-in readers send articles and op-eds from /account/submit/.
 *
 * Everything the writer types (and their headshot and attachment) is sealed in the browser to the editors' public key
 * with ML-KEM-1024 + P-384 → AES-256-GCM (security/pq/pq.mjs). The server stores only that envelope, in a private
 * directory, plus the triage fields editors need without the key: type, title, category, reported word count and
 * contact email. Editors open a submission in wp-admin with the offline secret key, in their own browser.
 *
 * Contracts:
 *   Account pages  /account/submit/ ("Submit an article") and /account/submissions/ ("My submissions").
 *   REST (hr/v1, cookie auth + X-WP-Nonce):
 *     POST submissions                {type,title,category,words,email,has_attachment,envelope{protected,kem,iv,ct},
 *                                      profile?{name,designation,bio}} → 201 {id, sha256}   (signed-in, verified)
 *     GET  submissions/<id>/envelope  → {protected,kem,iv,ct}                             (edit_hr_submission)
 *     POST submissions/<id>/draft     {title,standfirst,body_text,guest_author_name,guest_author_bio,avatar_id?}
 *                                      → 201 {draft_id, edit_url}                          (edit_hr_submission + edit_posts)
 *   Settings → Headline Recorder → Submissions; CPT hr_submission (wp-admin → Submissions, editors + administrators).
 *   Storage: define( 'HR_PRIVATE_DIR', '/home/<user>/hr-private' ) in wp-config.php (outside the web root), else
 *   wp-content/uploads/hr-sealed/ with deny rules.
 *   Privacy: hr_account_export, hr_account_deleted, WordPress personal-data exporter and eraser.
 */

namespace HR\Platform\Submissions;

use function HR\Platform\setting;
use function HR\Platform\is_b64url;
use function HR\Platform\b64url_decode;

if ( ! defined( 'ABSPATH' ) ) exit;

// Submissions are an account feature; without the accounts module there is nowhere to submit from.
if ( ! function_exists( 'HR\Platform\Accounts\is_verified' ) ) {
    return;
}

const VERSION      = 1;
const CPT          = 'hr_submission';
const SEAL_ALG     = 'ML-KEM-1024+P-384/AES-256-GCM';
const KEM_BYTES    = 1665;              // ml_kem1024_p384: public key and KEM ciphertext are both 1568 + 97 bytes
const MAX_CT       = 12 * 1024 * 1024;
// The ciphertext already carries the files as base64, and the request carries it as base64url again: an 8 MB
// attachment makes ~11 MB of ciphertext and a ~15 MB request.
const MAX_BODY     = 16 * 1024 * 1024;
const TYPES        = array( 'article' => 'Article', 'oped' => 'Op-ed' );
const WORDS        = array( 'article' => array( 300, 1500 ), 'oped' => array( 800, 1200 ) );
const STATUSES     = array( 'received' => 'Received', 'in_review' => 'In review', 'accepted' => 'Accepted', 'declined' => 'Declined', 'published' => 'Published' );
const CAT_SLUGS    = array( 'business', 'economy', 'markets', 'investments', 'pakistan', 'global', 'opinion', 'tech', 'tech-and-startups' );
const CAPS         = array(
    'edit_hr_submissions', 'edit_others_hr_submissions', 'edit_private_hr_submissions', 'edit_published_hr_submissions',
    'publish_hr_submissions', 'read_private_hr_submissions', 'delete_hr_submissions', 'delete_others_hr_submissions',
    'delete_private_hr_submissions', 'delete_published_hr_submissions',
);
const GUIDELINES = "We publish original reporting and analysis on Pakistan's business, economy and markets. Your piece must be original and not published anywhere else, in print or online, including your own blog or LinkedIn.
Op-eds: 800–1,200 words, arguing one clear point. Articles: up to 1,500 words (at least 300).
Back every figure with a source: name the dataset, report or official release, and add links under References.
Disclose any conflict of interest: your employer, clients, holdings or advocacy connected to the topic.
Send a short intro, your title or designation and a clear, recent headshot (square works best). We print these with your piece.
Our editors may edit for length, clarity, accuracy and house style, and may change the headline. We send you the final version before it goes live.
We reply within 7 days. If you have not heard from us by then, you are free to offer the piece elsewhere.";

foreach ( array( 'storage', 'rest', 'account', 'admin' ) as $hr_submissions_file ) {
    require_once __DIR__ . "/{$hr_submissions_file}.php";
}
unset( $hr_submissions_file );

// ---------------------------------------------------------------------------------------------------------------
// Settings
// ---------------------------------------------------------------------------------------------------------------

add_filter( 'hr_settings_schema', function ( array $schema ) {
    $schema['submissions'] = array(
        'title'       => 'Submissions',
        'description' => 'Readers submit articles and op-eds from their account. Each submission is encrypted in the writer\'s browser, so the server never sees the text. Create the editors\' key pair on a trusted computer with <code>cd security/pq &amp;&amp; node cli.mjs keygen seal submissions</code>, paste <code>submissions.public.json</code> below, and keep <code>submissions.secret.json</code> offline (a password manager or an encrypted USB key). Editors load it in their browser to open a submission; it is never uploaded.',
        'fields'      => array(
            'submissions_enabled'      => array( 'label' => 'Accept submissions', 'type' => 'checkbox', 'default' => 0, 'description' => 'Needs a valid public key below. Otherwise the submit page shows "Submissions open soon".' ),
            'submissions_public_key'   => array( 'label' => 'Editors\' public key', 'type' => 'json', 'default' => '', 'validate' => __NAMESPACE__ . '\\valid_public_key', 'description' => 'The contents of <code>submissions.public.json</code>: {"alg":"ML-KEM-1024+P-384/AES-256-GCM","kid":"…","key":"…"}. Never paste the secret key here.' ),
            'submissions_notify_email' => array( 'label' => 'Notify', 'type' => 'email', 'default' => '', 'description' => 'Who gets an email for each new submission (no content, just the number and title). Defaults to the site admin email.' ),
            'submissions_guidelines'   => array( 'label' => 'Guidelines', 'type' => 'textarea', 'default' => GUIDELINES, 'description' => 'Shown on the submit page. One guideline per line.' ),
        ),
    );
    return $schema;
} );

/** The configured sealing key: right algorithm, a 1665-byte key and a kid that is its SHA-256 (catches paste errors). */
function valid_public_key( $key ): bool {
    if ( ! is_array( $key ) || SEAL_ALG !== ( $key['alg'] ?? null ) || ! is_string( $key['kid'] ?? null ) || ! preg_match( '/^[0-9a-f]{64}$/', $key['kid'] ) || ! is_b64url( $key['key'] ?? null, 4096 ) ) {
        return false;
    }
    $raw = b64url_decode( $key['key'] );
    return KEM_BYTES === strlen( $raw ) && hash_equals( $key['kid'], hash( 'sha256', $raw ) );
}

function public_key(): ?array {
    $key = json_decode( (string) setting( 'submissions_public_key' ), true );
    return valid_public_key( $key ) ? array( 'alg' => $key['alg'], 'kid' => $key['kid'], 'key' => $key['key'] ) : null;
}

function is_open(): bool {
    return (bool) setting( 'submissions_enabled' ) && null !== public_key();
}

function notify_email(): string {
    $to = (string) setting( 'submissions_notify_email' );
    return is_email( $to ) ? $to : (string) get_option( 'admin_email' );
}

// ---------------------------------------------------------------------------------------------------------------
// Post type and capabilities
// ---------------------------------------------------------------------------------------------------------------

add_action( 'init', function () {
    register_post_type( CPT, array(
        'labels'              => array(
            'name'               => 'Submissions',
            'singular_name'      => 'Submission',
            'menu_name'          => 'Submissions',
            'all_items'          => 'All submissions',
            'edit_item'          => 'Submission',
            'search_items'       => 'Search submissions',
            'not_found'          => 'No submissions yet.',
            'not_found_in_trash' => 'No submissions in the bin.',
        ),
        'public'              => false,
        'publicly_queryable'  => false,
        'exclude_from_search' => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'show_in_nav_menus'   => false,
        'show_in_admin_bar'   => false,
        'show_in_rest'        => false,
        'menu_position'       => 26,
        'menu_icon'           => 'dashicons-edit-page',
        'capability_type'     => 'hr_submission',
        'capabilities'        => array( 'create_posts' => 'do_not_allow' ), // they only arrive sealed, from readers
        'map_meta_cap'        => true,
        'supports'            => array( 'title' ),
        'rewrite'             => false,
        'query_var'           => false,
        'can_export'          => false,
        'delete_with_user'    => true,
    ) );
} );

/** Only editors and administrators get the primitive caps, so authors and subscribers cannot list submissions. */
function install(): void {
    foreach ( array( 'administrator', 'editor' ) as $name ) {
        $role = get_role( $name );
        foreach ( $role ? CAPS : array() as $cap ) {
            $role->add_cap( $cap );
        }
    }
    storage_dir();
    update_option( 'hr_submissions_version', VERSION, false );
}
add_action( 'hr_install', __NAMESPACE__ . '\\install' );
// The platform runs hr_install only when its own DB version changes; a module added later installs itself once.
add_action( 'init', function () {
    if ( (int) get_option( 'hr_submissions_version' ) !== VERSION ) {
        install();
    }
}, 98 );

/** Allowed categories that exist on this site: term_id => name. */
function categories(): array {
    static $out = null;
    if ( null === $out ) {
        $out   = array();
        $terms = get_terms( array( 'taxonomy' => 'category', 'slug' => CAT_SLUGS, 'hide_empty' => false, 'orderby' => 'name' ) );
        foreach ( is_array( $terms ) ? $terms : array() as $term ) {
            $out[ (int) $term->term_id ] = html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' );
        }
    }
    return $out;
}

/** Word count limits for a type: [min, max]. The browser counts; the server can only check the reported number. */
function word_range( string $type ): array {
    return WORDS[ $type ] ?? WORDS['article'];
}

function meta( int $id, string $key ): string {
    return (string) get_post_meta( $id, '_hr_sub_' . $key, true );
}

/** A submission post by id, or null. */
function get_submission( int $id ): ?\WP_Post {
    $post = $id > 0 ? get_post( $id ) : null;
    return $post && CPT === $post->post_type ? $post : null;
}

// ---------------------------------------------------------------------------------------------------------------
// Privacy: account export/deletion and the WordPress personal-data tools
// ---------------------------------------------------------------------------------------------------------------

function user_submissions( int $uid, string $email = '' ): array {
    // 'any' alone skips the bin, and a binned submission still holds the writer's sealed headshot and details.
    $query = array( 'post_type' => CPT, 'post_status' => array( 'any', 'trash' ), 'numberposts' => -1, 'fields' => 'ids' );
    $ids   = $uid > 0 ? get_posts( $query + array( 'author' => $uid ) ) : array();
    if ( '' !== $email ) {
        $ids = array_merge( $ids, get_posts( $query + array( 'meta_key' => '_hr_sub_email', 'meta_value' => $email ) ) );
    }
    return array_values( array_unique( array_map( 'intval', $ids ) ) );
}

/** Metadata only: the sealed content is unreadable without the editors' key, so it is not exported. */
function export_row( int $id ): array {
    $post = get_post( $id );
    $cat  = get_term( (int) meta( $id, 'category' ), 'category' );
    return array(
        'id'             => $id,
        'title'          => $post ? $post->post_title : '',
        'type'           => TYPES[ meta( $id, 'type' ) ] ?? '',
        'category'       => $cat && ! is_wp_error( $cat ) ? html_entity_decode( $cat->name ) : '',
        'words'          => (int) meta( $id, 'words' ),
        'contact_email'  => meta( $id, 'email' ),
        'status'         => STATUSES[ meta( $id, 'status' ) ] ?? '',
        'editor_note'    => meta( $id, 'note' ),
        'submitted'      => $post ? get_post_time( 'c', true, $post ) : '',
        'sha256'         => meta( $id, 'sha256' ),
        'bytes'          => (int) meta( $id, 'bytes' ),
        'has_attachment' => '1' === meta( $id, 'has_attachment' ),
    );
}

add_filter( 'hr_account_export', function ( array $data, int $uid ) {
    $data['submissions'] = array(
        'note'  => 'Your submissions are stored encrypted to our editors\' key; only these details are readable on our server.',
        'items' => array_map( __NAMESPACE__ . '\\export_row', user_submissions( $uid ) ),
    );
    $profile = get_user_meta( $uid, '_hr_sub_profile', true );
    if ( is_array( $profile ) ) {
        $data['submissions']['remembered_profile'] = $profile;
    }
    return $data;
}, 10, 2 );

add_action( 'hr_account_deleted', function ( int $uid ) {
    foreach ( user_submissions( $uid ) as $id ) {
        wp_delete_post( $id, true ); // the envelope file goes with it (before_delete_post)
    }
    delete_user_meta( $uid, '_hr_sub_profile' );
} );

add_filter( 'wp_privacy_personal_data_exporters', function ( array $exporters ) {
    $exporters['hr-submissions'] = array(
        'exporter_friendly_name' => 'Headline Recorder submissions',
        'callback'               => static function ( string $email ) {
            $user  = get_user_by( 'email', $email );
            $items = array();
            foreach ( user_submissions( $user ? (int) $user->ID : 0, $email ) as $id ) {
                $data = array();
                foreach ( export_row( $id ) as $name => $value ) {
                    $data[] = array( 'name' => $name, 'value' => is_bool( $value ) ? ( $value ? 'yes' : 'no' ) : (string) $value );
                }
                $items[] = array( 'group_id' => 'hr-submissions', 'group_label' => 'Submissions', 'item_id' => 'hr-submission-' . $id, 'data' => $data );
            }
            return array( 'data' => $items, 'done' => true );
        },
    );
    return $exporters;
} );

add_filter( 'wp_privacy_personal_data_erasers', function ( array $erasers ) {
    $erasers['hr-submissions'] = array(
        'eraser_friendly_name' => 'Headline Recorder submissions',
        'callback'             => static function ( string $email ) {
            $user = get_user_by( 'email', $email );
            $ids  = user_submissions( $user ? (int) $user->ID : 0, $email );
            foreach ( $ids as $id ) {
                wp_delete_post( $id, true );
            }
            if ( $user ) {
                delete_user_meta( (int) $user->ID, '_hr_sub_profile' );
            }
            return array( 'items_removed' => count( $ids ) > 0, 'items_retained' => false, 'messages' => array(), 'done' => true );
        },
    );
    return $erasers;
} );
