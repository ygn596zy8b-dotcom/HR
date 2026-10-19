<?php
/**
 * One episode: player (or device voice), AI disclosure, show notes / transcript, related articles, subscribe.
 * Theme override: headlinerec-child/single-hr_episode.php (whole page) or hr/podcast/single.php.
 */

use function HR\Platform\icon;
use function HR\Platform\render;
use function HR\Platform\Podcast\episode;
use function HR\Platform\Podcast\clock;

if ( ! defined( 'ABSPATH' ) ) exit;

get_header();
while ( have_posts() ) :
    the_post();
    $e       = episode( get_post() );
    $related = array_filter( array_map( 'get_post', (array) get_post_meta( $e['id'], '_hr_related_posts', true ) ), static fn( $p ) => $p instanceof WP_Post && 'publish' === $p->post_status && 'post' === $p->post_type );
    $archive = (string) get_post_type_archive_link( 'hr_episode' );
    ?>
<article <?php post_class( 'hrp hrp-podcast hrp-episode' ); ?>>
    <header class="hrp-dark hrp-podcast-band hrp-podcast-band--single">
        <a class="hrp-podcast-back" href="<?php echo esc_url( $archive ); ?>"><?php echo icon( 'chevron-right', '', 'hrp-flip' ); // phpcs:ignore ?>All episodes</a>
        <p class="hrp-eyebrow">The Headline Recorder Podcast<?php echo $e['number'] ? esc_html( ' · Episode ' . $e['number'] ) : ''; ?></p>
        <h1 class="hrp-title"><?php echo esc_html( $e['title'] ); ?></h1>
        <p class="hrp-podcast-meta">
            <time datetime="<?php echo esc_attr( $e['iso'] ); ?>"><?php echo esc_html( $e['date'] ); ?></time>
            <?php if ( $e['duration'] ) : ?><span aria-hidden="true">·</span><span><?php echo esc_html( clock( $e['duration'] ) ); ?></span><?php endif; ?>
        </p>
        <?php if ( $e['ai'] ) : ?>
            <p class="hrp-podcast-ai"><?php echo icon( 'sparkles' ); // phpcs:ignore ?>Script drafted with AI from Headline Recorder reporting · reviewed by our editors</p>
        <?php endif; ?>
        <?php
        if ( $e['audio'] ) {
            render( 'podcast/player', array( 'episode' => $e ) );
        } else {
            render( 'podcast/voice', array( 'source' => '.hrp-episode__notes' ) );
        }
        ?>
    </header>

    <div class="hrp-episode__body">
        <h2 class="hrp-episode__heading">Show notes and transcript</h2>
        <div class="hrp-episode__notes"><?php the_content(); ?></div>

        <?php if ( $related ) : ?>
            <section class="hrp-related" aria-labelledby="hrp-related-title">
                <h2 class="hrp-episode__heading" id="hrp-related-title">Read the reporting</h2>
                <ul class="hrp-related__grid" role="list">
                    <?php foreach ( array_slice( $related, 0, 6 ) as $p ) : $cat = get_the_category( $p->ID ); ?>
                        <li>
                            <a class="hrp-related__card" href="<?php echo esc_url( get_permalink( $p ) ); ?>">
                                <?php echo get_the_post_thumbnail( $p, 'medium_large', array( 'alt' => '', 'loading' => 'lazy', 'sizes' => '(max-width: 640px) 100vw, 360px' ) ); // phpcs:ignore -- core-escaped ?>
                                <span class="hrp-related__text">
                                    <?php if ( $cat ) : ?><span class="hrp-related__cat"><?php echo esc_html( $cat[0]->name ); ?></span><?php endif; ?>
                                    <span class="hrp-related__title"><?php echo esc_html( get_the_title( $p ) ); ?></span>
                                    <span class="hrp-related__date hrp-small"><?php echo esc_html( get_the_date( '', $p ) ); ?></span>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <section class="hrp-episode__subscribe" aria-labelledby="hrp-subscribe-title">
            <h2 class="hrp-episode__heading" id="hrp-subscribe-title">Subscribe to the Podcast</h2>
            <?php render( 'podcast/subscribe' ); ?>
        </section>
        <p><a class="hrp-btn hrp-btn--ghost" href="<?php echo esc_url( $archive ); ?>"><?php echo icon( 'chevron-right', '', 'hrp-flip' ); // phpcs:ignore ?>All episodes</a></p>
    </div>
</article>
    <?php
endwhile;
get_footer();
