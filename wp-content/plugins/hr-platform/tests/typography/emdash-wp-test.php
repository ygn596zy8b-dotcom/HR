<?php
/**
 * House style tests inside WordPress. Scope: the website's own text (pages, podcast episodes, menus, widgets, site
 * titles and separators) is cleaned; articles, op-eds and comments are saved and shown exactly as written.
 * Run: wp --path=<webroot> eval-file wp-content/plugins/hr-platform/tests/typography/emdash-wp-test.php
 * Creates a draft article and a draft page titled "typography test ..." and deletes both at the end.
 */

use function HR\Platform\Site\{has_em_dash, emdash_plan, emdash_candidate_ids};

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
$hr_make = static function ( string $type, string $title ) use ( $hr_d ) {
    return wp_insert_post( wp_slash( array(
        'post_title'   => $title,
        'post_content' => "<!-- wp:paragraph -->\n<p>The KSE-100 rose{$hr_d}sharply{$hr_d}on Monday. <a href=\"https://example.com/a{$hr_d}b\">Link</a></p>\n<!-- /wp:paragraph -->",
        'post_excerpt' => "Rates held{$hr_d}for now.",
        'post_status'  => 'draft',
        'post_type'    => $type,
    ) ), true );
};

$hr_article = $hr_make( 'post', "typography test article {$hr_d} PSX gains" );
$hr_page    = $hr_make( 'page', "typography test page {$hr_d} PSX gains" );
if ( is_wp_error( $hr_article ) || is_wp_error( $hr_page ) ) {
    echo "FAIL could not create the test posts\n";
    return;
}

// On save: an article is stored exactly as written; a page is cleaned (links kept).
$hr_a = get_post( $hr_article );
$hr_check( 'article title saved as written', $hr_a->post_title, "typography test article {$hr_d} PSX gains" );
$hr_check( 'article content saved as written', $hr_a->post_content, "<!-- wp:paragraph -->\n<p>The KSE-100 rose{$hr_d}sharply{$hr_d}on Monday. <a href=\"https://example.com/a{$hr_d}b\">Link</a></p>\n<!-- /wp:paragraph -->" );
$hr_check( 'article excerpt saved as written', $hr_a->post_excerpt, "Rates held{$hr_d}for now." );
$hr_p = get_post( $hr_page );
$hr_check( 'page title cleaned on save', $hr_p->post_title, 'typography test page: PSX gains' );
$hr_check( 'page content cleaned on save, link kept', $hr_p->post_content, "<!-- wp:paragraph -->\n<p>The KSE-100 rose, sharply, on Monday. <a href=\"https://example.com/a{$hr_d}b\">Link</a></p>\n<!-- /wp:paragraph -->" );
$hr_check( 'page excerpt cleaned on save', $hr_p->post_excerpt, 'Rates held, for now.' );

// SEO fields: an article's are kept; a page's are cleaned (Yoast's own sanitising still runs).
update_post_meta( $hr_article, '_yoast_wpseo_metadesc', "Article desc {$hr_d} as written" );
update_post_meta( $hr_page, '_yoast_wpseo_metadesc', "Page desc {$hr_d} cleaned" );
$hr_check( 'article meta description kept', get_post_meta( $hr_article, '_yoast_wpseo_metadesc', true ), "Article desc {$hr_d} as written" );
$hr_check( 'page meta description cleaned', get_post_meta( $hr_page, '_yoast_wpseo_metadesc', true ), 'Page desc, cleaned' );

// Revision restore brings a page's old text back unchanged (it is an undo).
wp_update_post( array( 'ID' => $hr_page, 'post_title' => 'typography test page second' ) );
$hr_revs = wp_get_post_revisions( $hr_page, array( 'order' => 'ASC' ) );
$hr_rev  = $hr_revs ? reset( $hr_revs ) : null;
if ( $hr_rev ) {
    global $wpdb;
    $wpdb->update( $wpdb->posts, array( 'post_title' => "typography test page {$hr_d} old" ), array( 'ID' => $hr_rev->ID ) );
    clean_post_cache( $hr_rev->ID );
    wp_restore_post_revision( $hr_rev->ID );
    $hr_check( 'restoring a page revision keeps its text', get_post_field( 'post_title', $hr_page, 'raw' ), "typography test page {$hr_d} old" );
    $hr_check( 'the restored page title is shown without the dash', get_the_title( $hr_page ), 'typography test page, old' );
} else {
    $hr_check( 'revisions are kept for pages', 'none', 'at least one' );
}

// On display: an article's title, content and excerpt as written (wptexturize's own output included).
$hr_check( 'article title keeps its dash', has_em_dash( get_the_title( $hr_article ) ), true );
$GLOBALS['post'] = get_post( $hr_article );
setup_postdata( $GLOBALS['post'] );
$hr_check( 'article content keeps its dashes', substr_count( apply_filters( 'the_content', $GLOBALS['post']->post_content ), $hr_d ), 3 );
$hr_check( 'article "---" left to wptexturize', has_em_dash( apply_filters( 'the_content', 'The year 2019---2021 was hard.' ) ), true );
$hr_check( 'article excerpt keeps its dash', has_em_dash( apply_filters( 'the_excerpt', get_the_excerpt( $hr_article ) ) ), true );
$hr_check( 'comment text shown as written', has_em_dash( apply_filters( 'comment_text', "Good piece {$hr_d} thanks", null, array() ) ), true );

// On display: a page is cleaned, including what wptexturize makes of "---" and " -- ", but never a shortcode's output.
add_shortcode( 'typography_test_list', static fn() => "<span>Listed article {$hr_d} as written</span>" );
$GLOBALS['post'] = get_post( $hr_page );
setup_postdata( $GLOBALS['post'] );
$hr_html = apply_filters( 'the_content', "Rates rose -- and banks led. The year 2019---2021 was hard. [typography_test_list]" );
$hr_check( 'page content text', trim( wp_strip_all_tags( preg_replace( '/<span>.*<\/span>/', '', $hr_html ) ) ), 'Rates rose, and banks led. The year 2019-2021 was hard.' );
$hr_check( 'shortcode output (an article list) kept', false !== strpos( $hr_html, "Listed article {$hr_d} as written" ), true );
$hr_check( 'page title shown cleaned', apply_filters( 'the_title', 'PSX -- a record close', $hr_page ), 'PSX: a record close' );
$hr_check( 'page excerpt cleaned', has_em_dash( apply_filters( 'the_excerpt', 'Rates held -- for now.' ) ), false );
wp_reset_postdata();
unset( $GLOBALS['post'] );
remove_shortcode( 'typography_test_list' );

// Site-owned text: widgets, menus, the site title.
$hr_check( 'widget text cleaned', has_em_dash( apply_filters( 'widget_text_content', 'Markets -- live', array(), null ) ), false );
$hr_check( 'block widget cleaned', has_em_dash( apply_filters( 'widget_block_content', "<p>Markets {$hr_d} live</p>", array(), null ) ), false );
$hr_check( 'menu label cleaned', apply_filters( 'nav_menu_item_title', "Markets {$hr_d} live", (object) array( 'object' => 'page' ), null, 0 ), 'Markets, live' );
$hr_check( 'menu link to an article kept', apply_filters( 'nav_menu_item_title', "PSX {$hr_d} gains", (object) array( 'object' => 'post' ), null, 0 ), "PSX {$hr_d} gains" );

// Separators: Yoast's "sc-mdash" choice and the document title separator, on every page.
$hr_check( 'Yoast separator (entity)', apply_filters( 'wpseo_replacements_filter_sep', '&' . 'mdash;' ), '-' );
$hr_check( 'Yoast separator (character)', apply_filters( 'wpseo_replacements_filter_sep', $hr_d ), '-' );
$hr_check( 'Yoast separator, en dash left alone', apply_filters( 'wpseo_replacements_filter_sep', '&ndash;' ), '&ndash;' );
$hr_check( 'document_title_separator', apply_filters( 'document_title_separator', '&#' . '8212;' ), '-' );

// SEO strings: cleaned on the home page and archives, kept on a single article.
$hr_check( 'home document title cleaned', apply_filters( 'document_title', "PSX {$hr_d} Headline Recorder" ), 'PSX: Headline Recorder' );
$hr_check( 'home Yoast description cleaned', apply_filters( 'wpseo_metadesc', "Business news {$hr_d} daily" ), 'Business news, daily' );
global $wp_query, $wp_the_query;
$hr_saved_query = $wp_query;
$wp_query       = new WP_Query( array( 'p' => $hr_article, 'post_type' => 'post', 'post_status' => 'any' ) ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
$hr_check( 'article: Yoast title kept', apply_filters( 'wpseo_title', "PSX {$hr_d} gains - Headline Recorder" ), "PSX {$hr_d} gains - Headline Recorder" );
$hr_check( 'article: document title parts kept', apply_filters( 'document_title_parts', array( 'title' => "PSX {$hr_d} gains" ) ), array( 'title' => "PSX {$hr_d} gains" ) );
$hr_graph = apply_filters( 'wpseo_schema_graph', array(
    array( '@type' => 'NewsArticle', 'headline' => "PSX {$hr_d} gains" ),
    array( '@type' => 'Organization', 'name' => "Headline Recorder {$hr_d} News" ),
), null );
$hr_check( 'article: schema headline kept', $hr_graph[0]['headline'], "PSX {$hr_d} gains" );
$hr_check( 'article: schema Organization cleaned', $hr_graph[1]['name'], 'Headline Recorder: News' );
$wp_query = $hr_saved_query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride

// Tools > Remove em dashes never lists or changes an article.
$hr_check( 'tool plan skips articles', emdash_plan( $hr_article ), null );
$hr_ids   = emdash_candidate_ids();
$hr_types = array_unique( array_map( 'get_post_type', $hr_ids ) );
$hr_check( 'tool candidates are pages and episodes only', array_values( array_diff( $hr_types, array( 'page', 'hr_episode' ) ) ), array() );
$hr_check( 'tool on an article: skipped', \HR\Platform\Site\emdash_apply_post( $hr_article ), 'skipped' );
$hr_check( 'article untouched by the tool', get_post_field( 'post_content', $hr_article, 'raw' ), $hr_a->post_content );

// Settings are registered with their defaults.
$hr_check( 'setting: replace on save (default on)', (int) \HR\Platform\setting( 'emdash_on_save' ), 1 );
$hr_check( 'setting: replace on display (default on)', (int) \HR\Platform\setting( 'emdash_on_display' ), 1 );

wp_delete_post( $hr_article, true );
wp_delete_post( $hr_page, true );
$hr_check( 'test posts removed', null === get_post( $hr_article ) && null === get_post( $hr_page ), true );

echo "\n{$hr_total} checks, " . ( $hr_total - $hr_fails ) . " passed, {$hr_fails} failed.\n";
