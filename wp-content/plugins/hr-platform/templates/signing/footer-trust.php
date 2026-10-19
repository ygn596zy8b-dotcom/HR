<?php
/**
 * One line in the footer bottom bar. Vars: $verify_url, $has_feeds.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

use function HR\Platform\icon;
?>
<p class="hr-pqsig-trust">
    <?php echo icon( 'shield-check' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG ?>
    <span><?php echo empty( $has_feeds ) ? 'Every article is signed' : 'Every article and feed is signed'; ?> with post-quantum ML-DSA-87 + Ed448</span>
    <span aria-hidden="true">·</span>
    <a href="<?php echo esc_url( $verify_url ); ?>">Verify authenticity&nbsp;<span aria-hidden="true">→</span></a>
</p>
