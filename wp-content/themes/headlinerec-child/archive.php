<?php
/**
 * Tag, author and date archives (MSBJ): page header, lead story and image cards (inc/archive-view.php).
 * Any other archive (a plugin post type without its own template, for instance) keeps the parent theme's view.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! ( is_tag() || is_author() || is_date() ) ) {
    require get_template_directory() . '/archive.php';
    return;
}

require_once __DIR__ . '/inc/archive-view.php';

$hr_no_prefix = static fn() => '';
add_filter( 'get_the_archive_title_prefix', $hr_no_prefix );
$hr_title = wp_strip_all_tags( get_the_archive_title() );
remove_filter( 'get_the_archive_title_prefix', $hr_no_prefix );

$hr_args = array(
    'title' => $hr_title,
    'empty' => 'There are no stories here yet. Search the newsroom or start with the latest coverage below.',
);
if ( is_tag() ) {
    $hr_args['eyebrow']     = 'Topic';
    $hr_args['description'] = tag_description();
} elseif ( is_author() ) {
    $hr_author              = get_queried_object();
    $hr_args['eyebrow']     = 'Author';
    $hr_args['title']       = $hr_author instanceof WP_User ? $hr_author->display_name : $hr_title;
    $hr_args['description'] = $hr_author instanceof WP_User ? wpautop( esc_html( get_the_author_meta( 'description', $hr_author->ID ) ) ) : '';
    $hr_args['avatar']      = $hr_author instanceof WP_User ? get_avatar( $hr_author->ID, 112, '', '', array( 'class' => 'hr-arc-avatar', 'loading' => 'eager' ) ) : '';
} else {
    $hr_args['eyebrow'] = 'Archive';
}

get_header();
hr_archive_view( $hr_args );
get_footer();
