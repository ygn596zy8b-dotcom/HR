<?php
/**
 * Story lists for tag, author and date archives (archive.php) and search (search.php), MSBJ.
 *
 * The parent theme's list was a three-up masonry beside an empty sidebar, with 300 px thumbnails stretched to the
 * card and unclosed markup. This view gives every list the same editorial pattern as the category pages: a page
 * header (eyebrow, h1, description, count), a lead story on the first page, then a responsive grid of cards that
 * all carry their featured image (or the branded fallback), category, headline and date.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * One story card. The image link repeats the headline link, so it is hidden from assistive tech and the tab order.
 *
 * @param bool   $lead First story on page 1: larger, loaded first (it is the page's LCP image).
 * @param string $h    Heading element for the headline (h3 under a section heading).
 */
function hr_archive_card( bool $lead = false, string $h = 'h2' ): void {
    $h     = 'h3' === $h ? 'h3' : 'h2';
    $id    = get_the_ID();
    $url   = get_permalink();
    $cat   = hr_primary_category_name( $id );
    $sizes = $lead ? '(min-width: 1024px) 700px, (min-width: 769px) 58vw, calc(100vw - 32px)' : '(min-width: 1280px) 380px, (min-width: 769px) 30vw, (min-width: 561px) 46vw, calc(100vw - 32px)';
    $attr  = $lead ? array( 'alt' => '', 'loading' => 'eager', 'fetchpriority' => 'high' ) : array( 'alt' => '' );
    ?>
    <article class="hr-arc-card<?php echo $lead ? ' hr-arc-card--lead' : ''; ?>">
        <a class="hr-arc-media" href="<?php echo esc_url( $url ); ?>" tabindex="-1" aria-hidden="true">
            <?php echo hr_card_image( $id, $lead ? 'large' : 'medium_large', $sizes, $attr ); // phpcs:ignore WordPress.Security.EscapeOutput -- built by wp_get_attachment_image() ?>
        </a>
        <div class="hr-arc-body">
            <?php if ( $cat ) : ?><span class="hr-arc-cat"><?php echo esc_html( $cat ); ?></span><?php endif; ?>
            <<?php echo $h; // phpcs:ignore WordPress.Security.EscapeOutput -- h2 or h3 ?> class="hr-arc-card-title"><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( get_the_title() ); ?></a></<?php echo $h; // phpcs:ignore WordPress.Security.EscapeOutput -- h2 or h3 ?>>
            <?php if ( $lead ) : ?>
                <p class="hr-arc-excerpt"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( get_the_excerpt() ), 32, '...' ) ); ?></p>
            <?php endif; ?>
            <time class="hr-arc-date" datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date( 'F j, Y' ) ); ?></time>
        </div>
    </article>
    <?php
}

/** Search form used in the search page header and in the empty states. */
function hr_archive_search_form( string $id ): void {
    ?>
    <form role="search" method="get" class="hr-arc-search" action="<?php echo esc_url( home_url( '/' ) ); ?>">
        <label class="screen-reader-text" for="<?php echo esc_attr( $id ); ?>">Search Headline Recorder</label>
        <input type="search" id="<?php echo esc_attr( $id ); ?>" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="Company, sector or keyword" autocomplete="off">
        <button type="submit">Search</button>
    </form>
    <?php
}

/**
 * The whole list page between get_header() and get_footer().
 *
 * @param array $args eyebrow, title (plain text), description (HTML, already safe), avatar (HTML) and empty (text).
 */
function hr_archive_view( array $args ): void {
    global $wp_query;
    $args  = wp_parse_args( $args, array( 'eyebrow' => '', 'title' => '', 'description' => '', 'avatar' => '', 'empty' => '' ) );
    $total = (int) $wp_query->found_posts;
    $paged = max( 1, (int) get_query_var( 'paged' ) );
    ?>
    <div class="section-block-upper row hr-arc-wrap">
        <div id="primary" class="content-area hr-arc">
            <main id="main" class="site-main">
                <header class="hr-arc-head<?php echo $args['avatar'] ? ' hr-arc-head--person' : ''; ?>">
                    <?php echo $args['avatar']; // phpcs:ignore WordPress.Security.EscapeOutput -- get_avatar() markup ?>
                    <div class="hr-arc-head-text">
                        <?php if ( $args['eyebrow'] ) : ?><p class="hr-arc-eyebrow"><?php echo esc_html( $args['eyebrow'] ); ?></p><?php endif; ?>
                        <h1 class="hr-arc-title"><?php echo esc_html( $args['title'] ); ?></h1>
                        <?php if ( $args['description'] ) : ?><div class="hr-arc-desc"><?php echo wp_kses_post( $args['description'] ); ?></div><?php endif; ?>
                        <?php if ( $total ) : ?>
                            <p class="hr-arc-count"><?php echo esc_html( sprintf( 1 === $total ? '%s story' : '%s stories', number_format_i18n( $total ) ) . ( $paged > 1 ? sprintf( ', page %d', $paged ) : '' ) ); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php if ( is_search() ) { hr_archive_search_form( 'hr-arc-s' ); } ?>
                </header>

                <?php if ( have_posts() ) : ?>
                    <div class="hr-arc-grid">
                        <?php
                        $hr_first = 1 === $paged;
                        while ( have_posts() ) :
                            the_post();
                            hr_archive_card( $hr_first );
                            $hr_first = false;
                        endwhile;
                        ?>
                    </div>
                    <?php
                    the_posts_pagination( array(
                        'mid_size'           => 1,
                        'prev_text'          => '<span aria-hidden="true">&larr;</span> Previous',
                        'next_text'          => 'Next <span aria-hidden="true">&rarr;</span>',
                        'screen_reader_text' => 'More stories',
                        'class'              => 'hr-arc-pages',
                    ) );
                    ?>
                <?php else : ?>
                    <div class="hr-arc-empty">
                        <p class="hr-arc-empty-text"><?php echo esc_html( $args['empty'] ); ?></p>
                        <?php if ( ! is_search() ) { hr_archive_search_form( 'hr-arc-s' ); } ?>
                    </div>
                    <?php
                    $hr_latest = new WP_Query( array( 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 6, 'ignore_sticky_posts' => true, 'no_found_rows' => true ) );
                    if ( $hr_latest->have_posts() ) :
                        ?>
                        <h2 class="hr-arc-section">Latest stories</h2>
                        <div class="hr-arc-grid hr-arc-grid--plain">
                            <?php
                            while ( $hr_latest->have_posts() ) :
                                $hr_latest->the_post();
                                hr_archive_card( false, 'h3' );
                            endwhile;
                            wp_reset_postdata();
                            ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </main>
        </div>
    </div>
    <?php
}
