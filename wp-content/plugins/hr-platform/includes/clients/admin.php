<?php
/**
 * wp-admin for client logos: website meta box, list columns (logo, order), menu-order sorting.
 */

namespace HR\Platform\Clients;

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'add_meta_boxes_' . CPT, function () {
    add_meta_box( 'hr-client-url', 'Website', function ( \WP_Post $post ) {
        wp_nonce_field( 'hr_client_url_' . $post->ID, 'hr_client_url_nonce' );
        ?>
        <p>
            <label for="hr-client-url">Client website (https only, optional)</label>
            <input type="url" id="hr-client-url" name="hr_client_url" class="widefat" maxlength="2048" placeholder="https://"
                value="<?php echo esc_attr( (string) get_post_meta( $post->ID, META_URL, true ) ); ?>">
        </p>
        <p class="description">The logo links here in a new tab. Upload the logo as the featured image; use Order to position it.</p>
        <?php
    }, CPT, 'normal', 'high' );
} );

add_action( 'save_post_' . CPT, function ( int $post_id ) {
    if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! isset( $_POST['hr_client_url_nonce'] ) ) {
        return;
    }
    if ( ! wp_verify_nonce( sanitize_key( $_POST['hr_client_url_nonce'] ), 'hr_client_url_' . $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }
    $raw = substr( trim( (string) wp_unslash( $_POST['hr_client_url'] ?? '' ) ), 0, 2048 );
    $url = '' === $raw ? '' : esc_url_raw( $raw, array( 'https' ) );
    if ( '' === $url ) {
        delete_post_meta( $post_id, META_URL );
    } else {
        update_post_meta( $post_id, META_URL, $url );
    }
} );

add_filter( 'manage_' . CPT . '_posts_columns', function ( array $cols ): array {
    $cb = isset( $cols['cb'] ) ? array( 'cb' => $cols['cb'] ) : array();
    unset( $cols['cb'] );
    return $cb + array( 'hr_logo' => 'Logo' ) + $cols + array( 'menu_order' => 'Order' );
} );

add_action( 'manage_' . CPT . '_posts_custom_column', function ( string $col, int $post_id ) {
    if ( 'hr_logo' === $col ) {
        echo get_the_post_thumbnail( $post_id, 'medium', array( 'style' => 'height:40px;width:auto;max-width:120px;object-fit:contain' ) ) ?: '—';
    } elseif ( 'menu_order' === $col ) {
        echo (int) get_post_field( 'menu_order', $post_id );
    }
}, 10, 2 );

add_filter( 'manage_edit-' . CPT . '_sortable_columns', function ( array $cols ): array {
    $cols['menu_order'] = 'menu_order';
    return $cols;
} );

// List in the same order as the home page unless the editor picked a column to sort by.
add_action( 'pre_get_posts', function ( \WP_Query $q ) {
    if ( $q->is_main_query() && CPT === $q->get( 'post_type' ) && ! $q->get( 'orderby' ) ) {
        $q->set( 'orderby', array( 'menu_order' => 'ASC', 'title' => 'ASC' ) );
    }
} );
