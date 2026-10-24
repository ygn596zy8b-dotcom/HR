<?php
/**
 * Research: white papers and reports (hr_paper). Editors upload a PDF with a title and summary from the
 * "Research & Reports" menu; readers browse /research/ and open each paper at /research/<slug>/.
 * The first paper (the post-quantum white paper) ships with the plugin and is published once on install.
 */

namespace HR\Platform\Research;

use function HR\Platform\asset_url;
use function HR\Platform\asset_ver;
use function HR\Platform\enqueue_base_style;
use function HR\Platform\render;

if ( ! defined( 'ABSPATH' ) ) exit;

const CPT      = 'hr_paper';
const META_PDF = '_hr_paper_pdf';

add_action( 'init', function () {
    register_post_type( CPT, array(
        'labels'        => array(
            'name'          => 'Research & Reports',
            'singular_name' => 'Report',
            'menu_name'     => 'Research & Reports',
            'add_new'       => 'Add report',
            'add_new_item'  => 'Add report or white paper',
            'edit_item'     => 'Edit report',
            'all_items'     => 'All reports',
            'not_found'     => 'No reports yet.',
        ),
        'public'        => true,
        'show_in_rest'  => true,
        'has_archive'   => 'research',
        'rewrite'       => array( 'slug' => 'research', 'with_front' => false ),
        'menu_icon'     => 'dashicons-media-document',
        'menu_position' => 6,
        'capability_type' => 'page', // editors and admins only
        'map_meta_cap'  => true,
        'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
    ) );
    register_post_meta( CPT, META_PDF, array( 'type' => 'integer', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'absint', 'auth_callback' => static fn() => current_user_can( 'edit_pages' ) ) );
} );

// The PDF upload sits directly under the title (classic editor), like podcast episodes.
add_filter( 'use_block_editor_for_post_type', static fn( $use, $type ) => CPT === $type ? false : $use, 10, 2 );
add_filter( 'enter_title_here', static fn( $text, $post ) => CPT === ( $post->post_type ?? '' ) ? 'Report title' : $text, 10, 2 );

add_action( 'add_meta_boxes_' . CPT, function () {
    add_meta_box( 'hr-paper', 'PDF file', __NAMESPACE__ . '\\meta_box', CPT, 'hr_paper_top', 'high' );
} );
add_action( 'edit_form_after_title', static function ( $post ) {
    if ( CPT === ( $post->post_type ?? '' ) ) {
        do_meta_boxes( get_current_screen(), 'hr_paper_top', $post );
    }
} );

function meta_box( \WP_Post $post ): void {
    $id = (int) get_post_meta( $post->ID, META_PDF, true );
    wp_nonce_field( 'hr_paper_save', 'hr_paper_nonce' );
    ?>
    <p>
        <input type="hidden" id="hr-paper-pdf" name="hr_paper_pdf" value="<?php echo esc_attr( $id ?: '' ); ?>">
        <button type="button" class="button button-primary" id="hr-paper-pick">Choose or upload PDF</button>
        <button type="button" class="button-link" id="hr-paper-clear"<?php echo $id ? '' : ' hidden'; ?>>Remove</button>
        <span id="hr-paper-name" class="description"><?php echo esc_html( $id ? wp_basename( (string) get_attached_file( $id ) ) : '' ); ?></span>
    </p>
    <p class="description">Upload the report as a PDF. Write the summary or abstract in the box below; it appears on the report page and in search results. Add a cover image as the Featured image (optional).</p>
    <?php
    wp_enqueue_media();
    ?>
    <script>
    (function () {
        var id = document.getElementById('hr-paper-pdf'), name = document.getElementById('hr-paper-name'), clear = document.getElementById('hr-paper-clear'), frame;
        document.getElementById('hr-paper-pick').addEventListener('click', function () {
            frame = frame || wp.media({ title: 'Choose the report PDF', library: { type: 'application/pdf' }, button: { text: 'Use this PDF' }, multiple: false });
            frame.off('select').on('select', function () {
                var a = frame.state().get('selection').first().toJSON();
                if (a.mime !== 'application/pdf') return;
                id.value = a.id; name.textContent = a.filename; clear.hidden = false;
            });
            frame.open();
        });
        clear.addEventListener('click', function () { id.value = ''; name.textContent = ''; clear.hidden = true; });
    })();
    </script>
    <?php
}

add_action( 'save_post_' . CPT, function ( $post_id ) {
    if ( ! isset( $_POST['hr_paper_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['hr_paper_nonce'] ) ), 'hr_paper_save' ) || ! current_user_can( 'edit_post', $post_id ) || wp_is_post_revision( $post_id ) ) {
        return;
    }
    $pdf = absint( $_POST['hr_paper_pdf'] ?? 0 );
    if ( $pdf && 'application/pdf' !== get_post_mime_type( $pdf ) ) {
        $pdf = 0;
    }
    update_post_meta( $post_id, META_PDF, $pdf );
} );

/** ['url', 'size'] of the paper's PDF, or [] when none. */
function pdf( int $post_id ): array {
    $id   = (int) get_post_meta( $post_id, META_PDF, true );
    $url  = $id && 'application/pdf' === get_post_mime_type( $id ) ? (string) wp_get_attachment_url( $id ) : '';
    $file = $id ? (string) get_attached_file( $id ) : '';
    return $url ? array( 'url' => $url, 'size' => is_readable( $file ) ? (int) filesize( $file ) : 0 ) : array();
}

add_filter( 'template_include', function ( $template ) {
    if ( is_post_type_archive( CPT ) && ! locate_template( 'archive-hr_paper.php' ) ) {
        return HR_PLATFORM_DIR . '/templates/research/archive.php';
    }
    if ( is_singular( CPT ) && ! locate_template( 'single-hr_paper.php' ) ) {
        return HR_PLATFORM_DIR . '/templates/research/single.php';
    }
    return $template;
} );

add_action( 'wp_enqueue_scripts', function () {
    if ( is_post_type_archive( CPT ) || is_singular( CPT ) ) {
        enqueue_base_style();
        wp_enqueue_style( 'hr-research', asset_url( 'css/research.css' ), array( 'hr-platform' ), asset_ver( 'css/research.css' ) );
    }
} );

add_filter( 'get_the_archive_title', static fn( $t ) => is_post_type_archive( CPT ) ? 'Research & Reports' : $t );

// Structured data: each paper is a Report with its PDF as an encoding.
add_action( 'wp_head', function () {
    if ( ! is_singular( CPT ) ) {
        return;
    }
    $post = get_queried_object();
    $file = pdf( $post->ID );
    $data = array(
        '@context'      => 'https://schema.org',
        '@type'         => 'Report',
        'headline'      => get_the_title( $post ),
        'description'   => wp_strip_all_tags( get_the_excerpt( $post ) ),
        'datePublished' => get_post_time( 'c', true, $post ),
        'dateModified'  => get_post_modified_time( 'c', true, $post ),
        'url'           => get_permalink( $post ),
        'publisher'     => array( '@type' => 'NewsMediaOrganization', 'name' => 'Headline Recorder', 'url' => home_url( '/' ) ),
        'inLanguage'    => 'en',
    );
    if ( $file ) {
        $data['encoding'] = array( '@type' => 'MediaObject', 'contentUrl' => $file['url'], 'encodingFormat' => 'application/pdf' );
    }
    echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG ) . '</script>' . "\n";
} );

// Publish the bundled white paper once (only if no paper exists yet).
add_action( 'hr_install', __NAMESPACE__ . '\\seed' );
add_action( 'admin_init', static function () {
    if ( ! get_option( 'hr_research_seeded' ) && current_user_can( 'edit_pages' ) ) {
        seed();
    }
} );
function seed(): void {
    if ( get_option( 'hr_research_seeded' ) || ! post_type_exists( CPT ) ) {
        return;
    }
    update_option( 'hr_research_seeded', 1, true );
    if ( get_posts( array( 'post_type' => CPT, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) ) ) {
        return;
    }
    $src = HR_PLATFORM_DIR . '/assets/papers/hr-post-quantum-white-paper.pdf';
    if ( ! is_readable( $src ) ) {
        return;
    }
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $tmp = wp_tempnam( 'hr-paper.pdf' );
    if ( ! $tmp || ! copy( $src, $tmp ) ) {
        return;
    }
    $post_id = wp_insert_post( array(
        'post_type'    => CPT,
        'post_status'  => 'publish',
        'post_title'   => 'Post-Quantum Content Provenance at Headline Recorder',
        'post_excerpt' => 'Design, implementation and evaluation of hybrid ML-DSA-87 + Ed448 article signatures and ML-KEM-1024 + P-384 sealed submissions on a production news platform.',
        'post_content' => '<p>Headline Recorder signs every article and op-ed it publishes with a hybrid post-quantum signature, ML-DSA-87 (FIPS 204) composed with Ed448 (RFC 8032), over a canonical encoding of the stored article and its metadata. Signatures are detached, published at stable addresses and verifiable by anyone in a browser, against a public keyset signed by an offline key whose identifier is published in DNS. Contributor submissions are sealed in the browser with a hybrid ML-KEM-1024 + P-384 key encapsulation feeding AES-256-GCM.</p><p>This white paper covers the threat model, cryptographic design and parameters, the pure-PHP implementation and its validation against NIST ACVP vectors, key management, the signing pipeline, limitations and how to verify an article yourself.</p><p><a href="' . esc_url( home_url( '/verify/' ) ) . '">Verify an article now</a>.</p>',
    ), true );
    if ( is_wp_error( $post_id ) ) {
        return;
    }
    $att = media_handle_sideload( array( 'name' => 'hr-post-quantum-white-paper.pdf', 'tmp_name' => $tmp ), $post_id, 'Post-Quantum Content Provenance at Headline Recorder (PDF)' );
    if ( is_wp_error( $att ) ) {
        @unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
        return;
    }
    update_post_meta( $post_id, META_PDF, (int) $att );
}
