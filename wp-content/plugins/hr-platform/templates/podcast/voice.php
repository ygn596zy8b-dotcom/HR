<?php
/**
 * "Listen with device voice": reads text from the page with the browser's speechSynthesis. The controls stay
 * hidden (and the note below them shows) until podcast.js confirms the browser can speak.
 * Theme override: headlinerec-child/hr/podcast/voice.php
 *
 * @var string $source CSS selector of the element whose text is read
 */

use function HR\Platform\icon;

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="hrp-voice" data-hrp-voice data-source="<?php echo esc_attr( $source ); ?>">
    <div class="hrp-voice__buttons" hidden>
        <button type="button" class="hrp-btn" data-hrp-voice-play>
            <?php echo icon( 'play', '', 'hrp-i-play' ) . icon( 'pause', '', 'hrp-i-pause' ); // phpcs:ignore -- static SVG ?>
            <span data-hrp-voice-label>Listen with device voice</span>
        </button>
        <button type="button" class="hrp-btn hrp-btn--ghost" data-hrp-voice-stop disabled>Stop</button>
        <span class="hrp-eq" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
    </div>
    <p class="hrp-voice__note hrp-small" data-hrp-voice-note><?php echo icon( 'audio-lines' ); // phpcs:ignore ?> <span>Read aloud by your device’s built-in synthetic voice, not a recording. Needs a browser that can read aloud.</span></p>
    <p class="hrp-sr" aria-live="polite" data-hrp-voice-status></p>
</div>
