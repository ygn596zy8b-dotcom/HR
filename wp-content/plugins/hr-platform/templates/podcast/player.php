<?php
/**
 * Audio player: a native <audio controls> that works without JavaScript, enhanced by podcast.js into the custom
 * controls below (play, scrubber, ±15 s, speed, mute). Theme override: headlinerec-child/hr/podcast/player.php
 *
 * @var array  $episode episode() data with audio
 * @var string $id      optional DOM id, for buttons elsewhere that control this player (data-hrp-target)
 */

use function HR\Platform\icon;
use function HR\Platform\Podcast\artwork;
use function HR\Platform\Podcast\clock;
use function HR\Platform\Podcast\feed_title;

if ( ! defined( 'ABSPATH' ) ) exit;

$duration = (int) $episode['duration'];
?>
<div class="hrp-player"<?php echo empty( $id ) ? '' : ' id="' . esc_attr( $id ) . '"'; ?> data-hrp-player
    data-key="<?php echo esc_attr( 'ep-' . $episode['id'] ); ?>"
    data-title="<?php echo esc_attr( $episode['title'] ); ?>"
    data-album="<?php echo esc_attr( feed_title() ); ?>"
    data-artwork="<?php echo esc_url( artwork() ); ?>"
    data-duration="<?php echo esc_attr( (string) $duration ); ?>">
    <audio class="hrp-player__audio" controls preload="none" src="<?php echo esc_url( $episode['audio']['url'] ); ?>"></audio>
    <div class="hrp-player__ui" hidden>
        <button type="button" class="hrp-player__play" data-hrp-play aria-pressed="false">
            <?php echo icon( 'play', '', 'hrp-i-play' ) . icon( 'pause', '', 'hrp-i-pause' ); // phpcs:ignore -- static SVG ?>
            <span class="hrp-sr">Play <span data-hrp-title><?php echo esc_html( $episode['title'] ); ?></span></span>
        </button>
        <div class="hrp-player__main">
            <div class="hrp-player__track">
                <span class="hrp-eq" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
                <input type="range" class="hrp-player__seek" min="0" max="<?php echo esc_attr( (string) max( 1, $duration ) ); ?>" step="1" value="0" aria-label="Seek">
            </div>
            <div class="hrp-player__bar">
                <span class="hrp-player__time hrp-mono"><span data-hrp-elapsed>0:00</span> <span class="hrp-sr">elapsed,</span></span>
                <div class="hrp-player__controls">
                    <button type="button" class="hrp-player__btn" data-hrp-skip="-15" aria-label="Back 15 seconds"><?php echo icon( 'back-15' ); // phpcs:ignore ?><span aria-hidden="true">15</span></button>
                    <button type="button" class="hrp-player__btn" data-hrp-skip="15" aria-label="Forward 15 seconds"><span aria-hidden="true">15</span><?php echo icon( 'forward-15' ); // phpcs:ignore ?></button>
                    <button type="button" class="hrp-player__btn hrp-mono" data-hrp-speed aria-label="Playback speed: 1×">1×</button>
                    <button type="button" class="hrp-player__btn" data-hrp-mute aria-pressed="false" aria-label="Mute"><?php echo icon( 'volume', '', 'hrp-i-vol' ) . icon( 'volume-x', '', 'hrp-i-muted' ); // phpcs:ignore ?></button>
                </div>
                <span class="hrp-player__time hrp-mono"><span data-hrp-remaining><?php echo $duration ? '-' . esc_html( clock( $duration ) ) : '--:--'; ?></span> <span class="hrp-sr">remaining</span></span>
            </div>
        </div>
    </div>
</div>
