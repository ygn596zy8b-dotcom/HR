<?php
// Smoke test (MSBJ): guest-author meta box save rules. Run: wp eval-file wp-content/themes/headlinerec-child/tests/guest-author-save-test.php
if ( ! defined( 'ABSPATH' ) ) exit;
$ok = function ( $c, $m ) { echo ( $c ? 'PASS ' : 'FAIL ' ) . $m . "\n"; };
$author = get_users( array( 'role' => 'author', 'number' => 1 ) )[0];
$admin  = get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0];
$term   = get_term_by( 'name', 'Dr. Iftikhar Yasin', 'guest_author' );
$bio0   = get_term_meta( $term->term_id, 'guest_author_bio', true );
$save = function ( $user, $post_id, $fields ) {
    wp_set_current_user( $user->ID );
    $_POST = array_merge( array( 'hr_guest_author_nonce' => wp_create_nonce( 'hr_guest_author_save' ) ), wp_slash( $fields ) );
    hr_save_guest_author_meta( $post_id );
    $_POST = array();
};
$p = wp_insert_post( array( 'post_title' => 'ga-test', 'post_status' => 'draft', 'post_author' => $author->ID ) );
remove_all_actions( 'save_post' ); // only call the handler directly below
$save( $author, $p, array( 'guest_author_name' => 'Dr. Iftikhar Yasin', 'guest_author_bio' => "line1\nline2 <b>x</b>", 'guest_author_avatar' => 'https://evil.example/pixel.png' ) );
$ok( "line1\nline2 x" === get_post_meta( $p, 'guest_author_bio', true ), 'author: bio saved with newlines (sanitize_textarea_field)' );
$ok( '' === get_post_meta( $p, 'guest_author_avatar', true ), 'author: off-site avatar dropped' );
$ok( has_term( $term->term_id, 'guest_author', $p ), 'author: existing guest author assigned' );
$ok( $bio0 === get_term_meta( $term->term_id, 'guest_author_bio', true ), 'author: shared term profile untouched' );
$save( $author, $p, array( 'guest_author_name' => 'Brand New Person ZZ' ) );
$ok( ! get_term_by( 'name', 'Brand New Person ZZ', 'guest_author' ), 'author: cannot create a guest author term' );
$other = wp_insert_post( array( 'post_title' => 'ga-test-2', 'post_status' => 'draft', 'post_author' => $admin->ID ) );
$save( $author, $other, array( 'guest_author_name' => 'X', 'guest_author_bio' => 'should not save' ) );
$ok( '' === get_post_meta( $other, 'guest_author_bio', true ), 'author: cannot save on a post they cannot edit' );
$up = wp_get_upload_dir()['baseurl'] . '/2026/01/a.png';
$save( $admin, $other, array( 'guest_author_name' => 'Brand New Person ZZ', 'guest_author_bio' => 'bio', 'guest_author_avatar' => $up ) );
$t = get_term_by( 'name', 'Brand New Person ZZ', 'guest_author' );
$ok( $t && 'bio' === get_term_meta( $t->term_id, 'guest_author_bio', true ) && $up === get_term_meta( $t->term_id, 'guest_author_avatar', true ), 'admin: creates term and writes profile (same-site avatar kept)' );
$rev = wp_save_post_revision( $other );
if ( $rev ) {
    $save( $admin, $rev, array( 'guest_author_name' => 'Rev Person', 'guest_author_bio' => 'rev' ) );
    $ok( '' === get_post_meta( $rev, 'guest_author_bio', true ), 'revision ignored' );
}
wp_delete_post( $p, true ); wp_delete_post( $other, true ); if ( $t ) wp_delete_term( $t->term_id, 'guest_author' );
