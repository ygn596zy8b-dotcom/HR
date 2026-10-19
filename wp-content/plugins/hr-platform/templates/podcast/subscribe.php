<?php
/** Subscribe links (RSS always, Apple/Spotify/YouTube when set). Theme override: headlinerec-child/hr/podcast/subscribe.php */

use function HR\Platform\icon;
use function HR\Platform\Podcast\subscribe_links;

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<ul class="hrp-subscribe" aria-label="Subscribe to the podcast">
    <?php foreach ( subscribe_links() as $key => [ $label, $url ] ) : ?>
        <li><a class="hrp-subscribe__link" href="<?php echo esc_url( $url ); ?>"><?php echo icon( 'rss' === $key ? 'rss' : 'headphones' ); // phpcs:ignore ?><span><?php echo esc_html( $label ); ?></span></a></li>
    <?php endforeach; ?>
</ul>
