<?php
/**
 * /account/submit/ while submissions are switched off or no editors' key is configured.
 * Theme override: headlinerec-child/hr/submissions/closed.php
 */

use function HR\Platform\icon;

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="hrp-card hrp-card--accent hra-state hrs-closed">
    <span class="hra-state__icon" aria-hidden="true"><?php echo icon( 'pen' ); // phpcs:ignore -- static SVG ?></span>
    <p class="hrp-eyebrow">Submissions</p>
    <h2 class="hra-h2">Submissions open soon</h2>
    <p class="hra-lede">We are getting ready to take articles and op-eds from readers. Please check back shortly. Until then, keep reading: your streak counts every article.</p>
    <div class="hra-actions">
        <a class="hrp-btn hrp-btn--ghost" href="<?php echo esc_url( home_url( '/' ) ); ?>"><span>Read the latest</span><?php echo icon( 'arrow-right' ); // phpcs:ignore -- static SVG ?></a>
    </div>
</div>
