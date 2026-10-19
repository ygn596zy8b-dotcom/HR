<?php
/**
 * Shell for the signed-out pages (sign in, create account, verify, forgot, reset, two-step, sign out).
 * Theme override: headlinerec-child/hr/account/auth.php
 *
 * @var string $view  login|register|inbox|forgot|reset|verify|two-factor|logout
 * @var array  $vars  view data
 * @var string $sub   route
 */

use function HR\Platform\icon;
use function HR\Platform\render;
use function HR\Platform\Accounts\render_flash;

if ( ! defined( 'ABSPATH' ) ) exit;

$hra_view  = preg_replace( '/[^a-z-]/', '', (string) $view );
$hra_brand = in_array( $hra_view, array( 'login', 'register', 'two-factor' ), true );
?>
<div class="hrp hra hra-auth hra-auth--<?php echo esc_attr( $hra_view ); ?><?php echo $hra_brand ? ' hra-auth--brand' : ''; ?>">
    <div class="hra-auth__grid">
        <main class="hra-auth__main" id="hra-main">
            <div class="hra-auth__card" data-hra-reveal>
                <?php render_flash(); ?>
                <?php render( 'account/auth/' . $hra_view, (array) $vars ); ?>
            </div>
        </main>
        <?php if ( $hra_brand ) : ?>
            <aside class="hra-auth__brand" aria-label="Why create an account">
                <p class="hra-wordmark" aria-hidden="true"><span>Headline</span> <span>Recorder</span></p>
                <p class="hra-auth__pitch">Your daily edge on Pakistan’s business and economy.</p>
                <ul class="hra-perks" role="list">
                    <li><?php echo icon( 'flame' ); // phpcs:ignore -- static SVG ?><span><strong>Build a reading streak.</strong> Three stories a day keeps you ahead of the market.</span></li>
                    <li><?php echo icon( 'pen' ); // phpcs:ignore -- static SVG ?><span><strong>Write for us.</strong> Submit articles and op-eds straight to our editors.</span></li>
                    <li><?php echo icon( 'shield-check' ); // phpcs:ignore -- static SVG ?><span><strong>Secure by design.</strong> Passkeys, two-step verification and signed journalism.</span></li>
                </ul>
                <p class="hra-auth__fine">Free forever. We never sell your data.</p>
            </aside>
        <?php endif; ?>
    </div>
</div>
