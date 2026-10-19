<?php
/**
 * Home-page Podcast section (hr_home_after_banner). Inline SVG only, so it never competes for LCP.
 * Theme override: headlinerec-child/hr/podcast/hero.php
 *
 * @var array|null $episode latest episode (episode() data) or null
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
        <div class="hr-podcast-hero__disc">
            <span class="hr-podcast-hero__ring" aria-hidden="true"></span>
            <span class="hr-podcast-hero__ring" aria-hidden="true"></span>
            <span class="hr-podcast-hero__ring" aria-hidden="true"></span>
            <?php if ( $playable ) : ?>
                <button type="button" class="hr-podcast-hero__orb" data-hrp-target="hr-podcast-hero-player" aria-pressed="false">
                    <?php echo icon( 'play', '', 'hrp-i-play' ) . icon( 'pause', '', 'hrp-i-pause' ); // phpcs:ignore -- static SVG ?>
                    <span class="hrp-sr">Play the latest episode</span>
                </button>
            <?php else : ?>
                <a class="hr-podcast-hero__orb" href="<?php echo esc_url( $episode ? $episode['url'] : $archive ); ?>">
                    <?php echo icon( 'headphones' ); // phpcs:ignore ?>
                    <span class="hrp-sr"><?php echo $episode ? 'Listen to the latest episode' : 'Go to the Podcast'; ?></span>
                </a>
            <?php endif; ?>
        </div>
        <div class="hr-podcast-hero__copy">
            <p class="hrp-eyebrow"><?php echo esc_html( (string) setting( 'podcast_eyebrow' ) ); ?></p>
            <h2 class="hr-podcast-hero__title" id="hr-podcast-hero-title"><?php echo esc_html( show_name() ); ?></h2>
            <p class="hr-podcast-hero__tagline"><?php echo esc_html( (string) setting( 'podcast_tagline' ) ); ?></p>
            <?php if ( $about ) : ?>
                <p class="hr-podcast-hero__about"><?php echo esc_html( $about ); ?></p>
            <?php endif; ?>
            <?php if ( $episode ) : ?>
                <div class="hr-podcast-hero__latest">
                    <p class="hr-podcast-hero__label">Latest episode</p>
                    <p class="hr-podcast-hero__ep"><a href="<?php echo esc_url( $episode['url'] ); ?>"><?php echo esc_html( $episode['title'] ); ?></a></p>
                    <p class="hr-podcast-hero__meta">
                        <?php if ( $episode['duration'] ) : ?><span><?php echo icon( 'clock' ); // phpcs:ignore ?><?php echo esc_html( minutes( $episode['duration'] ) ); ?></span><?php endif; ?>
                        <span><?php echo icon( 'calendar' ); // phpcs:ignore ?><time datetime="<?php echo esc_attr( $episode['iso'] ); ?>"><?php echo esc_html( $episode['date'] ); ?></time></span>
                    </p>
                </div>
            <?php endif; ?>
            <div class="hr-podcast-hero__actions">
                <?php if ( $playable ) : ?>
                    <button type="button" class="hrp-btn" data-hrp-target="hr-podcast-hero-player" aria-pressed="false"><?php echo icon( 'play', '', 'hrp-i-play' ) . icon( 'pause', '', 'hrp-i-pause' ); // phpcs:ignore ?>Play latest episode</button>
                <?php elseif ( $episode ) : ?>
                    <a class="hrp-btn" href="<?php echo esc_url( $episode['url'] ); ?>"><?php echo icon( 'headphones' ); // phpcs:ignore ?>Listen to the latest episode</a>
                <?php endif; ?>
                <a class="hrp-btn hrp-btn--ghost" href="<?php echo esc_url( $archive ); ?>">All episodes<?php echo icon( 'arrow-right' ); // phpcs:ignore ?></a>
            </div>
            <?php if ( $playable ) : ?>
                <?php render( 'podcast/player', array( 'episode' => $episode, 'id' => 'hr-podcast-hero-player' ) ); ?>
            <?php endif; ?>
            <?php render( 'podcast/subscribe' ); ?>
        </div>
    </div>
</section>
