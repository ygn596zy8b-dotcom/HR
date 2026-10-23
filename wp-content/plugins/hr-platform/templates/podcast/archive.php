<?php
/**
 * /podcast/: show header, the newest episode with the full player, then the other recent episodes. Only the newest
 * N published episodes are on the website (setting podcast_keep), so there is a single page and no pagination.
 * Theme override: headlinerec-child/archive-hr_episode.php (whole page) or hr/podcast/archive.php.
 */

use function HR\Platform\icon;
use function HR\Platform\render;
use function HR\Platform\setting;
use function HR\Platform\Podcast\latest;
use function HR\Platform\Podcast\minutes;
use function HR\Platform\Podcast\show_name;

if ( ! defined( 'ABSPATH' ) ) exit;

// One query for every episode's audio attachment instead of one per episode.
$hrp_audio_ids = array_filter( array_map( static fn( $p ) => (int) get_post_meta( $p->ID, '_hr_audio_id', true ), $GLOBALS['wp_query']->posts ) );
if ( $hrp_audio_ids ) {
    _prime_post_caches( $hrp_audio_ids, false, true );
}
$featured = latest();
$cover    = (int) setting( 'podcast_cover' );
$episodes = array_values( array_filter( array_map( 'HR\Platform\Podcast\episode', $GLOBALS['wp_query']->posts ), static fn( $e ) => ! $featured || $e['id'] !== $featured['id'] ) );
$meta     = static function ( array $e ): string {
    $parts = array( '<time datetime="' . esc_attr( $e['iso'] ) . '">' . esc_html( $e['date'] ) . '</time>' );
    if ( $e['duration'] ) {
        $parts[] = esc_html( minutes( $e['duration'] ) );
    }
    if ( $e['ai'] ) {
        $parts[] = '<span class="hrp-badge hrp-badge--red">' . icon( 'sparkles' ) . 'AI-assisted</span>';
    }
    return implode( '<span aria-hidden="true">·</span>', $parts );
};

get_header();
?>
<div class="hrp hrp-podcast">
    <section class="hrp-dark hrp-podcast-band">
        <div class="hrp-podcast-band__inner">
            <div class="hrp-podcast-art<?php echo $cover ? '' : ' hrp-podcast-art--icon'; ?>">
                <?php echo $cover ? wp_get_attachment_image( $cover, 'medium', false, array( 'alt' => '', 'fetchpriority' => 'high' ) ) : icon( 'headphones' ); // phpcs:ignore -- core-escaped image / static SVG ?>
            </div>
            <div class="hrp-podcast-band__copy">
                <p class="hrp-eyebrow"><?php echo esc_html( (string) setting( 'podcast_eyebrow' ) ); ?></p>
                <h1 class="hrp-title"><?php echo esc_html( show_name() ); ?></h1>
                <p class="hrp-podcast-band__tagline"><?php echo esc_html( (string) setting( 'podcast_tagline' ) ); ?></p>
                <p class="hrp-lede"><?php echo esc_html( (string) setting( 'podcast_description' ) ); ?></p>
                <?php render( 'podcast/subscribe' ); ?>
            </div>
        </div>
    </section>

    <?php if ( ! $featured ) : ?>
        <div class="hrp-card hrp-card--accent hrp-podcast-empty">
            <?php echo icon( 'mic' ); // phpcs:ignore ?>
            <h2>The first episode of the Headline Recorder Podcast is on its way</h2>
            <p class="hrp-muted">Follow the Podcast in your app with the button above and it will arrive as soon as it is published.</p>
        </div>
    <?php else : ?>
        <section class="hrp-card hrp-card--accent hrp-podcast-featured" aria-labelledby="hrp-featured-title">
            <p class="hrp-eyebrow">Latest episode</p>
            <h2 class="hrp-podcast-featured__title" id="hrp-featured-title"><a href="<?php echo esc_url( $featured['url'] ); ?>" data-hrp-featured-link><?php echo esc_html( $featured['title'] ); ?></a></h2>
            <p class="hrp-podcast-meta" data-hrp-featured-meta><?php echo $meta( $featured ); // phpcs:ignore -- escaped above ?></p>
            <?php if ( $featured['excerpt'] ) : ?>
                <p class="hrp-podcast-featured__excerpt" data-hrp-featured-excerpt><?php echo esc_html( $featured['excerpt'] ); ?></p>
            <?php endif; ?>
            <?php if ( $featured['audio'] ) : ?>
                <?php render( 'podcast/player', array( 'episode' => $featured, 'id' => 'hrp-featured-player' ) ); ?>
            <?php else : ?>
                <a class="hrp-btn" href="<?php echo esc_url( $featured['url'] ); ?>"><?php echo icon( 'headphones' ); // phpcs:ignore ?>Listen with device voice</a>
            <?php endif; ?>
        </section>

        <?php if ( $episodes ) : ?>
            <section class="hrp-podcast-list" aria-labelledby="hrp-episodes-title">
                <div class="hrp-podcast-list__head">
                    <h2 class="hrp-podcast-list__title" id="hrp-episodes-title">Recent episodes</h2>
                    <p class="hrp-podcast-list__note"><?php echo esc_html( sprintf( 'The latest %d episodes', count( $episodes ) + 1 ) ); ?></p>
                </div>
                <ol class="hrp-eps" role="list">
                    <?php foreach ( $episodes as $e ) : ?>
                        <li class="hrp-ep">
                            <span class="hrp-ep__num hrp-mono" aria-hidden="true"><?php echo $e['number'] ? esc_html( sprintf( '%02d', $e['number'] ) ) : icon( 'mic' ); // phpcs:ignore ?></span>
                            <div class="hrp-ep__body">
                                <h3 class="hrp-ep__title"><a href="<?php echo esc_url( $e['url'] ); ?>"><?php echo $e['number'] ? '<span class="hrp-sr">Episode ' . (int) $e['number'] . ': </span>' : ''; ?><?php echo esc_html( $e['title'] ); ?></a></h3>
                                <p class="hrp-podcast-meta"><?php echo $meta( $e ); // phpcs:ignore -- escaped above ?></p>
                                <?php if ( $e['excerpt'] ) : ?>
                                    <p class="hrp-ep__excerpt"><?php echo esc_html( wp_trim_words( $e['excerpt'], 40 ) ); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="hrp-ep__actions">
                                <?php if ( $e['audio'] && $featured['audio'] ) : ?>
                                    <button type="button" class="hrp-btn hrp-btn--ghost" data-hrp-load="hrp-featured-player"
                                        data-src="<?php echo esc_url( $e['audio']['url'] ); ?>" data-key="<?php echo esc_attr( 'ep-' . $e['id'] ); ?>"
                                        data-title="<?php echo esc_attr( $e['title'] ); ?>" data-url="<?php echo esc_url( $e['url'] ); ?>"
                                        data-duration="<?php echo esc_attr( (string) $e['duration'] ); ?>" data-excerpt="<?php echo esc_attr( $e['excerpt'] ); ?>"
                                        data-meta="<?php echo esc_attr( wp_strip_all_tags( str_replace( '<span aria-hidden="true">·</span>', ' · ', $meta( $e ) ) ) ); ?>">
                                        <?php echo icon( 'play' ); // phpcs:ignore ?>Play<span class="hrp-sr"> <?php echo esc_html( $e['title'] ); ?></span>
                                    </button>
                                <?php else : ?>
                                    <a class="hrp-btn hrp-btn--ghost" href="<?php echo esc_url( $e['url'] ); ?>"><?php echo icon( 'headphones' ); // phpcs:ignore ?>Listen<span class="hrp-sr"> to <?php echo esc_html( $e['title'] ); ?></span></a>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php
get_footer();
