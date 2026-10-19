<?php
/**
 * Byline pill. Vars: $state (from HR\Platform\Signing\signature_state()).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

use function HR\Platform\icon;

$hr_signed = 'signed' === ( $state['state'] ?? '' );
?>
<?php if ( $hr_signed ) : ?>
    <a class="hr-pqsig-pill hr-pqsig-pill--signed" href="#hr-signature" title="This article carries a post-quantum digital signature (ML-DSA-87 + Ed448). See details below.">
        <?php echo icon( 'shield-check' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG ?>
        <span>PQ-signed</span><span class="hr-pqsig-pill__sep" aria-hidden="true">·</span><span class="hr-pqsig-pill__alg">ML-DSA-87</span>
    </a>
<?php else : ?>
    <a class="hr-pqsig-pill hr-pqsig-pill--pending" href="#hr-signature" title="A post-quantum signature for this version of the article is on its way.">
        <?php echo icon( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG ?>
        <span>Signature pending</span>
    </a>
<?php endif; ?>
