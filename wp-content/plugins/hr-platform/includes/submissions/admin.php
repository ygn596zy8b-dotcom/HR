<?php
/**
 * Submissions: wp-admin → Submissions. List columns and status filter, the "Sealed submission" box where editors
 * open a submission with the offline key (submission-open.js), and the "Status & note to author" box.
 */

namespace HR\Platform\Submissions;

use HR\Platform\Accounts;
use function HR\Platform\render;
use function HR\Platform\asset_url;
use function HR\Platform\asset_ver;

if ( ! defined( 'ABSPATH' ) ) exit;

// ---------------------------------------------------------------------------------------------------------------
// List screen
// ---------------------------------------------------------------------------------------------------------------

add_filter( 'manage_' . CPT . '_posts_columns', function ( array $cols ) {
    return array(
        'cb'          => $cols['cb'] ?? '',
        'title'       => 'Title',
        'hr_type'     => 'Type',
        'hr_category' => 'Section',
        'hr_author'   => 'Author',
        'hr_status'   => 'Status',
        'hr_size'     => 'Size',
        'date'        => 'Date',
    );
} );

add_action( 'manage_' . CPT . '_posts_custom_column', function ( string $col, int $id ) {
    switch ( $col ) {
        case 'hr_type':
            echo esc_html( TYPES[ meta( $id, 'type' ) ] ?? '—' );
            break;
        case 'hr_category':
            echo esc_html( categories()[ (int) meta( $id, 'category' ) ] ?? '—' );
            break;
        case 'hr_author':
            author_cell( $id );
            break;
        case 'hr_status':
            echo esc_html( STATUSES[ meta( $id, 'status' ) ] ?? '—' );
            break;
        case 'hr_size':
            echo esc_html( size_format( (int) meta( $id, 'bytes' ), 1 ) );
            break;
    }
}, 10, 2 );

function author_cell( int $id ): void {
    $user = get_userdata( (int) get_post_field( 'post_author', $id ) );
    if ( ! $user ) {
        echo '—';
        return;
    }
    $verified = Accounts\is_verified( (int) $user->ID );
    printf(
        '%s <span class="hrs-admin-badge%s">%s</span><br><a href="mailto:%s">%s</a>',
        esc_html( $user->display_name ),
        $verified ? ' is-ok' : '',
        $verified ? 'Verified' : 'Unverified',
        esc_attr( meta( $id, 'email' ) ),
        esc_html( meta( $id, 'email' ) )
    );
}

add_action( 'restrict_manage_posts', function ( string $post_type ) {
    if ( CPT !== $post_type ) {
        return;
    }
    $current = current_status_filter();
    echo '<label class="screen-reader-text" for="hr-sub-status">Filter by status</label><select name="hr_sub_status" id="hr-sub-status"><option value="">All statuses</option>';
    foreach ( STATUSES as $value => $label ) {
        printf( '<option value="%s"%s>%s</option>', esc_attr( $value ), selected( $current, $value, false ), esc_html( $label ) );
    }
    echo '</select>';
} );

function current_status_filter(): string {
    $value = isset( $_GET['hr_sub_status'] ) && is_string( $_GET['hr_sub_status'] ) ? sanitize_key( $_GET['hr_sub_status'] ) : '';
    return isset( STATUSES[ $value ] ) ? $value : '';
}

add_action( 'pre_get_posts', function ( \WP_Query $q ) {
    if ( is_admin() && $q->is_main_query() && CPT === $q->get( 'post_type' ) && '' !== current_status_filter() ) {
        $q->set( 'meta_key', '_hr_sub_status' );
        $q->set( 'meta_value', current_status_filter() );
    }
} );

// ---------------------------------------------------------------------------------------------------------------
// Edit screen
// ---------------------------------------------------------------------------------------------------------------

add_action( 'add_meta_boxes_' . CPT, function ( \WP_Post $post ) {
    add_meta_box( 'hr-sealed', 'Sealed submission', __NAMESPACE__ . '\\sealed_box', CPT, 'normal', 'high' );
    add_meta_box( 'hr-sub-status', 'Status & note to author', __NAMESPACE__ . '\\status_box', CPT, 'side', 'high' );
} );

function sealed_box( \WP_Post $post ): void {
    $id    = (int) $post->ID;
    $draft = (int) meta( $id, 'draft' );
    $draft = $draft && get_post( $draft ) && 'trash' !== get_post_status( $draft ) ? $draft : 0;
    $type  = meta( $id, 'type' );
    $user  = get_userdata( (int) $post->post_author );
    render( 'submissions/admin-sealed', array(
        'post'   => $post,
        'facts'  => array(
            'Type'           => TYPES[ $type ] ?? '—',
            'Section'        => categories()[ (int) meta( $id, 'category' ) ] ?? '—',
            'Words (stated)' => number_format( (int) meta( $id, 'words' ) ),
            'Account'        => $user ? $user->display_name . ( Accounts\is_verified( (int) $user->ID ) ? ' (verified)' : ' (unverified)' ) : '—',
            'Contact email'  => meta( $id, 'email' ),
            'Received'       => get_the_date( 'j M Y, H:i', $post ),
            'Size'           => size_format( (int) meta( $id, 'bytes' ), 1 ),
            'Attachment'     => '1' === meta( $id, 'has_attachment' ) ? 'Yes' : 'No',
            'Key ID'         => meta( $id, 'kid' ),
        ),
        'sha256' => meta( $id, 'sha256' ),
        'draft'  => $draft,
        'config' => array(
            'id'        => $id,
            'kid'       => meta( $id, 'kid' ),
            'sha256'    => meta( $id, 'sha256' ),
            'type'      => $type,
            'words'     => word_range( $type ),
            'envelope'  => esc_url_raw( rest_url( "hr/v1/submissions/{$id}/envelope" ) ),
            'draft'     => esc_url_raw( rest_url( "hr/v1/submissions/{$id}/draft" ) ),
            'media'     => current_user_can( 'upload_files' ) ? esc_url_raw( rest_url( 'wp/v2/media' ) ) : '',
            'nonce'     => wp_create_nonce( 'wp_rest' ),
            'canDraft'  => current_user_can( 'edit_posts' ) && ! $draft,
        ),
    ) );
}

function status_box( \WP_Post $post ): void {
    $id = (int) $post->ID;
    wp_nonce_field( 'hr_sub_status_' . $id, 'hr_sub_status_nonce' );
    ?>
    <p><label for="hr-sub-status-select"><strong>Status</strong></label><br>
        <select name="hr_sub_status" id="hr-sub-status-select" class="widefat">
            <?php foreach ( STATUSES as $value => $label ) : ?>
                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( meta( $id, 'status' ), $value ); ?>><?php echo esc_html( $label ); ?></option>
            <?php endforeach; ?>
        </select></p>
    <p><label for="hr-sub-note"><strong>Note to the author</strong></label><br>
        <textarea name="hr_sub_note" id="hr-sub-note" rows="5" maxlength="1000" class="widefat"><?php echo esc_textarea( meta( $id, 'note' ) ); ?></textarea>
        <span class="description">Shown on their My submissions page. Plain text.</span></p>
    <p><label><input type="checkbox" name="hr_sub_email_author" value="1" checked> Email the author when this changes</label></p>
    <?php
}

add_action( 'save_post_' . CPT, function ( int $post_id ) {
    $nonce = isset( $_POST['hr_sub_status_nonce'] ) && is_string( $_POST['hr_sub_status_nonce'] ) ? sanitize_key( wp_unslash( $_POST['hr_sub_status_nonce'] ) ) : '';
    if ( ! $nonce || ! wp_verify_nonce( $nonce, 'hr_sub_status_' . $post_id ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_hr_submission', $post_id ) ) {
        return;
    }
    $status = isset( $_POST['hr_sub_status'] ) && is_string( $_POST['hr_sub_status'] ) ? sanitize_key( $_POST['hr_sub_status'] ) : '';
    $note   = isset( $_POST['hr_sub_note'] ) && is_string( $_POST['hr_sub_note'] ) ? mb_substr( sanitize_textarea_field( wp_unslash( $_POST['hr_sub_note'] ) ), 0, 1000 ) : '';
    if ( ! isset( STATUSES[ $status ] ) ) {
        return;
    }
    $changed = meta( $post_id, 'status' ) !== $status || meta( $post_id, 'note' ) !== $note;
    update_post_meta( $post_id, '_hr_sub_status', $status );
    update_post_meta( $post_id, '_hr_sub_note', wp_slash( $note ) );
    if ( $changed && ! empty( $_POST['hr_sub_email_author'] ) ) {
        mail_status( $post_id );
    }
} );

function mail_status( int $id ): void {
    $post   = get_post( $id );
    $status = STATUSES[ meta( $id, 'status' ) ] ?? '';
    $note   = meta( $id, 'note' );
    $to     = meta( $id, 'email' );
    if ( ! is_email( $to ) ) {
        $user = get_userdata( (int) $post->post_author );
        $to   = $user ? $user->user_email : '';
    }
    Accounts\send_mail( $to, sprintf( 'Update on your submission #%d', $id ), array(
        'preheader'  => sprintf( 'Status: %s', $status ),
        'heading'    => sprintf( 'Your submission is now: %s', $status ),
        'paragraphs' => array_filter( array( sprintf( '"%s" (#%d)', $post->post_title, $id ), '' !== $note ? 'Note from the editors: ' . $note : '' ) ),
        'button'     => array( 'label' => 'My submissions', 'url' => Accounts\account_url( 'submissions' ) ),
    ) );
}

add_action( 'admin_enqueue_scripts', function ( string $hook ) {
    $screen = get_current_screen();
    if ( ! $screen || CPT !== $screen->post_type ) {
        return;
    }
    wp_enqueue_style( 'hr-submissions-admin', asset_url( 'css/submissions.css' ), array(), asset_ver( 'css/submissions.css' ) );
    if ( 'post.php' === $hook ) {
        wp_enqueue_script( 'hr-submission-open', asset_url( 'js/submission-open.js' ), array(), asset_ver( 'js/submission-open.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
    }
} );

// The edit screen holds the editors' secret key and decrypted text in memory, so other plugins' and
// themes' scripts (which also throw here: no block editor on this screen) are not loaded on our screens.
add_action( 'admin_enqueue_scripts', function () {
    $screen = get_current_screen();
    if ( ! $screen || CPT !== $screen->post_type ) {
        return;
    }
    $ours    = HR_PLATFORM_URL;
    $foreign = array( content_url( '/plugins/' ), content_url( '/themes/' ) );
    foreach ( wp_scripts()->queue as $handle ) {
        $src = (string) ( wp_scripts()->registered[ $handle ]->src ?? '' );
        foreach ( $foreign as $prefix ) {
            if ( str_starts_with( $src, $prefix ) && ! str_starts_with( $src, $ours ) ) {
                wp_dequeue_script( $handle );
            }
        }
    }
}, PHP_INT_MAX );
