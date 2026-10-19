<?php
/**
 * "Listen · N min" on articles: plays the Podcast episode that covers this article, or reads the article with the
 * device voice. A <details> disclosure, so it opens with mouse, touch and keyboard even before JavaScript loads.
 * Theme override: headlinerec-child/hr/podcast/listen.php
 *
 * @var int        $post_id
 * @var array|null $episode episode() data with audio, or null
 * @var string     $minutes "5 min"
 */

use function HR\Platform\icon;
use function HR\Platform\render;

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<details class="hrp hrp-listen">
    <summary class="hrp-listen__pill"><?php echo icon( 'headphones' ); // phpcs:ignore -- static SVG ?><span>Listen</span><span aria-hidden="true">·</span><span><?php echo esc_html( $minutes ); ?></span></summary>
    <div class="hrp-listen__panel">
        <?php if ( $episode ) : ?>
            <p class="hrp-listen__from hrp-small">From the Podcast: <a href="<?php echo esc_url( $episode['url'] ); ?>"><?php echo esc_html( $episode['title'] ); ?></a></p>
            <?php render( 'podcast/player', array( 'episode' => $episode ) ); ?>
        <?php else : ?>
            <?php render( 'podcast/voice', array( 'source' => '.hr-body, .esa-body, .entry-content' ) ); ?>
        <?php endif; ?>
    </div>
</details>
