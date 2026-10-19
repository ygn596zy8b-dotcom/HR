<?php
/**
 * Client & partner logos: the hr_client CPT (managed in wp-admin) and the logo band at the end of the home page.
 */

namespace HR\Platform\Clients;

use function HR\Platform\asset_url;
use function HR\Platform\asset_ver;
use function HR\Platform\enqueue_base_style;
use function HR\Platform\render_to_string;
use function HR\Platform\setting;

if ( ! defined( 'ABSPATH' ) ) exit;

const CPT = 'hr_client';
const META_URL = '_hr_client_url';
// Below this a marquee would loop the same few logos past the reader; a static row reads better.
const MARQUEE_MIN = 6;

function register(): void {
    register_post_type( CPT, array(
        'labels'        => array(
            'name'                  => 'Client logos',
            'singular_name'         => 'Client logo',
            'menu_name'             => 'Client logos',
            'add_new_item'          => 'Add client logo',
            'edit_item'             => 'Edit client logo',
            'all_items'             => 'All client logos',
            'not_found'             => 'No client logos yet.',
            'not_found_in_trash'    => 'No client logos in Trash.',
            'featured_image'        => 'Logo',
            'set_featured_image'    => 'Set logo',
            'remove_featured_image' => 'Remove logo',
            'use_featured_image'    => 'Use as logo',
        ),
        'public'        => false,
        'show_ui'       => true,
        'show_in_rest'  => false,
        'rewrite'       => false,
        'query_var'     => false,
        'supports'      => array( 'title', 'thumbnail', 'page-attributes' ),
        'menu_icon'     => 'dashicons-groups',
        'menu_position' => 25,
    ) );
}
add_action( 'init', __NAMESPACE__ . '\\register' );
add_action( 'hr_register_rewrites', __NAMESPACE__ . '\\register' );

add_filter( 'hr_settings_schema', function ( array $s ): array {
    $s['clients'] = array(
        'title'  => 'Client logos',
        'description' => 'Logos are managed under <a href="' . esc_url( admin_url( 'edit.php?post_type=' . CPT ) ) . '">Client logos</a> (title = client name, featured image = logo, order = position).',
        'fields' => array(
            'clients_eyebrow' => array( 'label' => 'Eyebrow', 'type' => 'text', 'default' => 'Our clients & partners' ),
            'clients_heading' => array( 'label' => 'Heading', 'type' => 'text', 'default' => "Trusted by organisations shaping Pakistan's economy" ),
            'clients_style'   => array( 'label' => 'Layout', 'type' => 'select', 'default' => 'marquee', 'options' => array( 'marquee' => 'Scrolling marquee', 'grid' => 'Static grid' ) ),
        ),
    );
    return $s;
} );

/** Published clients that have a logo, in menu order. */
function clients(): array {
    static $out = null;
    if ( null !== $out ) {
        return $out;
    }
    $q = new \WP_Query( array(
        'post_type'      => CPT,
        'post_status'    => 'publish',
        'posts_per_page' => 60,
        'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
        'meta_key'       => '_thumbnail_id',
        'no_found_rows'  => true,
    ) );
    update_post_thumbnail_cache( $q );
    $out = array();
    foreach ( $q->posts as $post ) {
        $name = wp_strip_all_tags( get_the_title( $post ) );
        $img  = wp_get_attachment_image( (int) get_post_thumbnail_id( $post ), 'medium', false, array( 'loading' => 'lazy', 'decoding' => 'async', 'alt' => $name, 'class' => 'hr-clients__logo' ) );
        if ( '' !== $img ) {
            $out[] = array( 'name' => $name, 'url' => (string) get_post_meta( $post->ID, META_URL, true ), 'img' => $img );
        }
    }
    return $out;
}

function enqueue(): void {
    enqueue_base_style();
    wp_enqueue_style( 'hr-clients', asset_url( 'css/clients.css' ), array( 'hr-platform' ), asset_ver( 'css/clients.css' ) );
    wp_enqueue_script( 'hr-clients', asset_url( 'js/clients.js' ), array(), asset_ver( 'js/clients.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
}

function visible(): bool {
    return clients() || current_user_can( 'edit_posts' );
}

// Enqueue in <head> where the band will render (no late, shifting stylesheet); the shortcode also enqueues as a fallback.
add_action( 'wp_enqueue_scripts', function () {
    $here = is_front_page() || ( is_singular() && has_shortcode( (string) get_post_field( 'post_content', get_queried_object_id() ), 'hr_client_logos' ) );
    if ( $here && visible() ) {
        enqueue();
    }
} );

function band(): string {
    if ( ! visible() ) {
        return '';
    }
    $clients = clients();
    enqueue();
    // Lets LiteSpeed purge every cached page showing the band when a client changes.
    do_action( 'litespeed_tag_add', 'hr_clients' );
    return render_to_string( 'clients/band', array(
        'clients' => $clients,
        'marquee' => 'marquee' === setting( 'clients_style' ) && count( $clients ) >= MARQUEE_MIN,
        'eyebrow' => (string) setting( 'clients_eyebrow' ),
        'heading' => (string) setting( 'clients_heading' ),
    ) );
}

add_action( 'hr_home_before_footer', function () {
    echo band(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the template.
} );

add_shortcode( 'hr_client_logos', __NAMESPACE__ . '\\band' );

add_action( 'save_post_' . CPT, function () {
    do_action( 'litespeed_purge', 'hr_clients' );
} );

if ( is_admin() ) {
    require_once __DIR__ . '/admin.php';
}
