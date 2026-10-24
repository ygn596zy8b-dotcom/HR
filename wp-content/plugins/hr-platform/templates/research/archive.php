<?php
/**
 * /research/: white papers and reports, newest first.
 */

use function HR\Platform\Research\pdf;

if ( ! defined( 'ABSPATH' ) ) exit;

get_header();
?>
<main id="primary" class="hrp hr-research">
    <header class="hr-research__head">
        <p class="hrp-eyebrow">Research</p>
        <h1 class="hrp-title">Research &amp; Reports</h1>
        <p class="hrp-lede">White papers, technical reports and research from Headline Recorder on Pakistan's economy, markets, policy and the technology behind trustworthy news.</p>
    </header>
    <?php if ( have_posts() ) : ?>
        <ul class="hr-research__list">
            <?php while ( have_posts() ) : the_post(); $file = pdf( get_the_ID() ); ?>
                <li class="hr-research__item">
                    <?php if ( has_post_thumbnail() ) : ?>
                        <a class="hr-research__thumb" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true"><?php the_post_thumbnail( 'medium', array( 'loading' => 'lazy', 'decoding' => 'async', 'alt' => '' ) ); ?></a>
                    <?php endif; ?>
                    <div class="hr-research__body">
                        <p class="hr-research__meta"><time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time><?php echo $file ? ' · PDF' : ''; ?></p>
                        <h2 class="hr-research__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                        <?php if ( has_excerpt() ) : ?><p class="hr-research__excerpt"><?php echo esc_html( get_the_excerpt() ); ?></p><?php endif; ?>
                    </div>
                </li>
            <?php endwhile; ?>
        </ul>
        <?php the_posts_pagination(); ?>
    <?php else : ?>
        <p class="hrp-muted">No reports have been published yet.</p>
    <?php endif; ?>
</main>
<?php
get_footer();
