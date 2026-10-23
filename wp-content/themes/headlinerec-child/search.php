<?php
/**
 * Search results (MSBJ): the query, a refine-search field, a lead result and image cards (inc/archive-view.php).
 * With no results: what to try next, the search field again and the latest stories.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

require_once __DIR__ . '/inc/archive-view.php';

$hr_q = trim( get_search_query( false ) );

get_header();
hr_archive_view( array(
    'eyebrow' => 'Search',
    'title'   => '' !== $hr_q ? sprintf( 'Results for “%s”', $hr_q ) : 'Search the newsroom',
    'empty'   => '' !== $hr_q
        ? sprintf( 'Nothing matched “%s”. Check the spelling, try a company or sector name, or use fewer words.', $hr_q )
        : 'Type a company, sector or keyword to search Pakistan business, economy and markets coverage.',
) );
get_footer();
