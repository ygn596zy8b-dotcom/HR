<?php
/**
 * 404: page not found (MSBJ). WordPress sends the 404 status; Yoast marks it noindex.
 * Gives readers (and crawlers) a way back in: search, the latest stories, the main sections, Podcast and Home.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

$hr_latest = new WP_Query( array(
    'post_type'           => 'post',
    'post_status'         => 'publish',
    'posts_per_page'      => 6,
    'ignore_sticky_posts' => true,
    'no_found_rows'       => true,
) );
$hr_sections = get_categories( array(
    'orderby'    => 'count',
    'order'      => 'DESC',
    'number'     => 8,
    'hide_empty' => true,
    'exclude'    => array( (int) get_option( 'default_category' ) ),
) );
?>

<section class="hr-404" aria-labelledby="hr-404-title">

    <div class="hr-404-hero">
        <p class="hr-404-code" aria-hidden="true">4<span>0</span>4</p>
        <h1 class="hr-404-title" id="hr-404-title">This page is not on the record</h1>
        <p class="hr-404-text">The link may be old, mistyped or the story has moved. Search the newsroom, or pick up from today's latest coverage below.</p>

        <form role="search" method="get" class="hr-404-search" action="<?php echo esc_url( home_url( '/' ) ); ?>">
            <label class="hr-404-label" for="hr-404-s">Search Headline Recorder</label>
            <div class="hr-404-field">
                <input type="search" id="hr-404-s" name="s" placeholder="Company, sector or keyword" autocomplete="off" value="<?php echo esc_attr( get_search_query() ); ?>">
                <button type="submit">Search</button>
            </div>
        </form>

        <div class="hr-404-links">
            <a class="hr-cta-action hr-cta-action--primary" href="<?php echo esc_url( home_url( '/' ) ); ?>">Back to the home page</a>
            <a class="hr-cta-action hr-cta-action--ghost" href="<?php echo esc_url( home_url( '/podcast/' ) ); ?>">Listen to the Podcast</a>
        </div>
    </div>

    <div class="hr-404-grid">
        <?php if ( $hr_latest->have_posts() ) : ?>
        <div>
            <h2 class="hr-404-heading">Latest stories</h2>
            <ul class="hr-404-stories">
                <?php while ( $hr_latest->have_posts() ) : $hr_latest->the_post(); $hr_cat = get_the_category(); ?>
                <li class="hr-404-story">
                    <a href="<?php echo esc_url( get_permalink() ); ?>">
                        <?php echo hr_post_image( get_the_ID(), 'medium' ); // phpcs:ignore WordPress.Security.EscapeOutput -- built with esc_url/esc_attr ?>
                        <?php if ( $hr_cat ) : ?><span class="hr-404-story-cat"><?php echo esc_html( $hr_cat[0]->name ); ?></span><?php endif; ?>
                        <span class="hr-404-story-title"><?php echo esc_html( get_the_title() ); ?></span>
                        <time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date( 'F j, Y' ) ); ?></time>
                    </a>
                </li>
                <?php endwhile; wp_reset_postdata(); ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if ( $hr_sections ) : ?>
        <nav aria-labelledby="hr-404-sections">
            <h2 class="hr-404-heading" id="hr-404-sections">Popular sections</h2>
            <ul class="hr-404-cats">
                <?php foreach ( $hr_sections as $hr_section ) : ?>
                <li><a href="<?php echo esc_url( get_category_link( $hr_section ) ); ?>"><?php echo esc_html( $hr_section->name ); ?> <span class="count"><?php echo esc_html( number_format_i18n( $hr_section->count ) ); ?></span></a></li>
                <?php endforeach; ?>
                <li><a href="<?php echo esc_url( home_url( '/podcast/' ) ); ?>">Podcast <span class="count" aria-hidden="true">&rarr;</span></a></li>
                <li><a href="<?php echo esc_url( home_url( '/markets/' ) ); ?>">Markets <span class="count" aria-hidden="true">&rarr;</span></a></li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>

</section>

<?php
get_footer();
