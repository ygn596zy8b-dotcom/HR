<?php
/**
 * House style tests inside WordPress: on save, on display (wptexturize), title separators and revision restore.
 * Run: wp --path=<webroot> eval-file wp-content/plugins/hr-platform/tests/typography/emdash-wp-test.php
 * Creates one draft post titled "typography test ..." and deletes it at the end.
 */

use function HR\Platform\Site\has_em_dash;

if ( ! defined( 'ABSPATH' ) || ! function_exists( 'HR\\Platform\\Site\\replace_em_dashes' ) ) {
    echo "Run this with wp eval-file, with the hr-platform plugin active.\n";
    return;
}

$hr_d     = "\u{2014}";
$hr_fails = 0;
$hr_total = 0;
$hr_check = static function ( string $name, $got, $want ) use ( &$hr_fails, &$hr_total ): void {
    $hr_total++;
    if ( $got === $want ) {
        echo "ok   {$name}\n";
        return;
    }
    $hr_fails++;
    echo "FAIL {$name}\n     got:  " . var_export( $got, true ) . "\n     want: " . var_export( $want, true ) . "\n";
};

// On save: title, content and excerpt (wp_insert_post_data), for a post the setting covers.
$hr_id = wp_insert_post( wp_slash( array(
    'post_title'   => "typography test {$hr_d} PSX gains",
    'post_content' => "<!-- wp:paragraph -->\n<p>The KSE-100 rose{$hr_d}sharply{$hr_d}on Monday. <a href=\"https://example.com/a{$hr_d}b\">Link</a></p>\n<!-- /wp:paragraph -->",
    'post_excerpt' => "Rates held{$hr_d}for now.",
    'post_status'  => 'draft',
    'post_type'    => 'post',
) ), true );
if ( is_wp_error( $hr_id ) ) {
    echo 'FAIL could not create the test post: ' . $hr_id->get_error_message() . "\n";
    return;
}
$hr_post = get_post( $hr_id );
$hr_check( 'title rewritten on save', $hr_post->post_title, 'typography test: PSX gains' );
$hr_check( 'content rewritten on save, link kept', $hr_post->post_content, "<!-- wp:paragraph -->\n<p>The KSE-100 rose, sharply, on Monday. <a href=\"https://example.com/a{$hr_d}b\">Link</a></p>\n<!-- /wp:paragraph -->" );
$hr_check( 'excerpt rewritten on save', $hr_post->post_excerpt, 'Rates held, for now.' );

// Revision restore brings the old text back unchanged (it is an undo).
wp_update_post( array( 'ID' => $hr_id, 'post_title' => 'typography test second' ) );
$hr_revs = wp_get_post_revisions( $hr_id, array( 'order' => 'ASC' ) );
$hr_rev  = $hr_revs ? reset( $hr_revs ) : null;
if ( $hr_rev ) {
    global $wpdb;
    $wpdb->update( $wpdb->posts, array( 'post_title' => "typography test {$hr_d} old" ), array( 'ID' => $hr_rev->ID ) ); // a revision made before the rule existed
    clean_post_cache( $hr_rev->ID );
    wp_restore_post_revision( $hr_rev->ID );
    $hr_check( 'restoring a revision keeps its text', get_post_field( 'post_title', $hr_id, 'raw' ), "typography test {$hr_d} old" );
    $hr_check( 'the restored title is still shown without the dash', get_the_title( $hr_id ), 'typography test, old' );
} else {
    $hr_check( 'revisions are kept for posts', 'none', 'at least one' );
}

// On display: wptexturize makes em dashes of "---" and " -- "; they are rewritten after it.
$hr_html = apply_filters( 'the_content', "Rates rose -- and banks led. The year 2019---2021 was hard." );
$hr_check( 'the_content has no em dash after wptexturize', has_em_dash( $hr_html ), false );
$hr_check( 'the_content text', trim( wp_strip_all_tags( $hr_html ) ), 'Rates rose, and banks led. The year 2019-2021 was hard.' );
$hr_check( 'the_title', apply_filters( 'the_title', 'PSX -- a record close', 0 ), 'PSX: a record close' );
$hr_check( 'the_excerpt', has_em_dash( apply_filters( 'the_excerpt', 'Rates held -- for now.' ) ), false );
$hr_check( 'widget_text', has_em_dash( apply_filters( 'widget_text', 'Markets -- live', array(), null ) ), false );
$hr_check( 'comment_text', has_em_dash( apply_filters( 'comment_text', 'Good piece -- thanks', null, array() ) ), false );

// Separators: Yoast's "sc-mdash" choice and the document title separator.
$hr_check( 'Yoast separator (entity)', apply_filters( 'wpseo_replacements_filter_sep', '&mdash;' ), '-' );
$hr_check( 'Yoast separator (character)', apply_filters( 'wpseo_replacements_filter_sep', $hr_d ), '-' );
$hr_check( 'Yoast separator, en dash left alone', apply_filters( 'wpseo_replacements_filter_sep', '&ndash;' ), '&ndash;' );
$hr_check( 'document_title_separator', apply_filters( 'document_title_separator', '&#8212;' ), '-' );
$hr_check( 'document_title', apply_filters( 'document_title', "PSX {$hr_d} Headline Recorder" ), 'PSX: Headline Recorder' );

// Settings are registered with their defaults.
$hr_check( 'setting: replace on save (default on)', (int) \HR\Platform\setting( 'emdash_on_save' ), 1 );
$hr_check( 'setting: replace on display (default on)', (int) \HR\Platform\setting( 'emdash_on_display' ), 1 );

wp_delete_post( $hr_id, true );
$hr_check( 'test post removed', null === get_post( $hr_id ), true );

echo "\n{$hr_total} checks, " . ( $hr_total - $hr_fails ) . " passed, {$hr_fails} failed.\n";
