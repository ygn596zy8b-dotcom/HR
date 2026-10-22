<?php
/**
 * Follow links: the podcast feed first (works in every podcast app), then Apple Podcasts / Spotify / YouTube when
 * their URLs are set. The feed button carries a short visible explanation, also used as its accessible description.
 * Theme override: headlinerec-child/hr/podcast/subscribe.php
 */

use function HR\Platform\icon;
use function HR\Platform\Podcast\subscribe_links;

if ( ! defined( 'ABSPATH' ) ) exit;

static $hrp_follow_count = 0;
$hrp_follow_count++;
$hint = 'hrp-follow-hint-' . $hrp_follow_count;
?>
<div class="hrp-follow">
    <ul class="hrp-subscribe" aria-label="Follow the Headline Recorder Podcast">
        <?php foreach ( subscribe_links() as $key => [ $label, $url ] ) : ?>
            <li>
                <?php if ( 'rss' === $key ) : ?>
                    <a class="hrp-subscribe__link hrp-subscribe__link--feed" href="<?php echo esc_url( $url ); ?>" aria-describedby="<?php echo esc_attr( $hint ); ?>"><?php echo icon( 'rss' ); // phpcs:ignore -- static SVG ?><span><?php echo esc_html( $label ); ?></span></a>
                <?php else : ?>
                    <a class="hrp-subscribe__link" href="<?php echo esc_url( $url ); ?>" rel="noopener"><?php echo icon( 'headphones' ); // phpcs:ignore ?><span><?php echo esc_html( $label ); ?></span></a>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <p class="hrp-follow__hint" id="<?php echo esc_attr( $hint ); ?>">Opens the podcast's feed link. Add it to Apple Podcasts, Pocket Casts or any podcast app and every new episode arrives automatically.</p>
</div>
