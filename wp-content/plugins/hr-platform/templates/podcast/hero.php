<?php
/**
 * Home-page Podcast section (hr_home_after_banner): the show on the left, the newest published episode (and only
 * that one) on the right with its player. Inline SVG only, so it never competes for LCP.
 * Theme override: headlinerec-child/hr/podcast/hero.php
 *
 * @var array|null $episode newest published episode (episode() data) or null
 */

use function HR\Platform\icon;
use function HR\Platform\render;
use function HR\Platform\setting;
use function HR\Platform\Podcast\minutes;
use function HR\Platform\Podcast\show_name;

if ( ! defined( 'ABSPATH' ) ) exit;

$archive  = (string) get_post_type_archive_link( 'hr_episode' );
$playable = $episode && $episode['audio'];
// First sentence of the show description: what the podcast is about.
$about = preg_split( '/(?<=[.!?])\s+/', trim( (string) setting( 'podcast_description' ) ), 2 )[0];
?>
<section class="hrp hrp-dark hr-podcast-hero" aria-labelledby="hr-podcast-hero-title">
    <div class="hrp-wrap hr-podcast-hero__inner">
        <div class="hr-podcast-hero__copy">
            <p class="hrp-eyebrow"><?php echo esc_html( (string) setting( 'podcast_eyebrow' ) ); ?></p>
            <h2 class="hr-podcast-hero__title" id="hr-podcast-hero-title"><?php echo esc_html( show_name() ); ?></h2>
            <p class="hr-podcast-hero__tagline"><?php echo esc_html( (string) setting( 'podcast_tagline' ) ); ?></p>
            <?php if ( $about ) : ?>
                <p class="hr-podcast-hero__about"><?php echo esc_html( $about ); ?></p>
            <?php endif; ?>
        </div>

        <div class="hr-podcast-hero__actions">
            <a class="hrp-btn hrp-btn--ghost hr-podcast-hero__all" href="<?php echo esc_url( $archive ); ?>">All episodes<?php echo icon( 'arrow-right' ); // phpcs:ignore -- static SVG ?></a>
            <?php render( 'podcast/subscribe' ); ?>
        </div>

        <div class="hr-podcast-hero__episode">
            <?php if ( $episode ) : ?>
                <div class="hr-podcast-hero__head">
                    <div class="hr-podcast-hero__disc">
                        <span class="hr-podcast-hero__ring" aria-hidden="true"></span>
                        <span class="hr-podcast-hero__ring" aria-hidden="true"></span>
                        <?php if ( $playable ) : ?>
                            <button type="button" class="hr-podcast-hero__orb" data-hrp-target="hr-podcast-hero-player" aria-pressed="false">
                                <?php echo icon( 'play', '', 'hrp-i-play' ) . icon( 'pause', '', 'hrp-i-pause' ); // phpcs:ignore -- static SVG ?>
                                <span class="hrp-sr">Play the latest episode: <?php echo esc_html( $episode['title'] ); ?></span>
                            </button>
                        <?php else : ?>
                            <a class="hr-podcast-hero__orb" href="<?php echo esc_url( $episode['url'] ); ?>">
                                <?php echo icon( 'headphones' ); // phpcs:ignore ?>
                                <span class="hrp-sr">Listen to the latest episode: <?php echo esc_html( $episode['title'] ); ?></span>
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="hr-podcast-hero__head-text">
                        <p class="hr-podcast-hero__label"><span class="hr-podcast-hero__live" aria-hidden="true"></span>Latest episode</p>
                        <p class="hr-podcast-hero__meta">
                            <span><?php echo icon( 'calendar' ); // phpcs:ignore ?><time datetime="<?php echo esc_attr( $episode['iso'] ); ?>"><?php echo esc_html( $episode['date'] ); ?></time></span>
                            <?php if ( $episode['duration'] ) : ?><span><?php echo icon( 'clock' ); // phpcs:ignore ?><?php echo esc_html( minutes( $episode['duration'] ) ); ?></span><?php endif; ?>
                        </p>
                    </div>
                </div>
                <h3 class="hr-podcast-hero__ep"><a href="<?php echo esc_url( $episode['url'] ); ?>"><?php echo esc_html( $episode['title'] ); ?></a></h3>
                <?php if ( $episode['excerpt'] ) : ?>
                    <p class="hr-podcast-hero__excerpt"><?php echo esc_html( wp_trim_words( $episode['excerpt'], 32 ) ); ?></p>
                <?php endif; ?>
                <?php if ( $episode['ai'] ) : ?>
                    <p class="hr-podcast-hero__ai"><?php echo icon( 'sparkles' ); // phpcs:ignore ?>Script drafted with AI, reviewed by our editors</p>
                <?php endif; ?>
                <?php if ( $playable ) : ?>
                    <?php render( 'podcast/player', array( 'episode' => $episode, 'id' => 'hr-podcast-hero-player' ) ); ?>
                <?php else : ?>
                    <a class="hrp-btn" href="<?php echo esc_url( $episode['url'] ); ?>"><?php echo icon( 'headphones' ); // phpcs:ignore ?>Listen to the latest episode</a>
                <?php endif; ?>
            <?php else : ?>
                <div class="hr-podcast-hero__head">
                    <div class="hr-podcast-hero__disc">
                        <span class="hr-podcast-hero__ring" aria-hidden="true"></span>
                        <span class="hr-podcast-hero__ring" aria-hidden="true"></span>
                        <a class="hr-podcast-hero__orb" href="<?php echo esc_url( $archive ); ?>"><?php echo icon( 'headphones' ); // phpcs:ignore ?><span class="hrp-sr">Go to the Podcast</span></a>
                    </div>
                    <p class="hr-podcast-hero__label">Coming soon</p>
                </div>
                <p class="hr-podcast-hero__excerpt">The first episode is on its way. Follow the Podcast in your app and it will arrive as soon as it is published.</p>
            <?php endif; ?>
        </div>
    </div>
</section>
