<?php
/**
 * After registering: identical for new and existing addresses. @var string $email masked address
 */

use function HR\Platform\icon;
use function HR\Platform\Accounts\account_url;

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="hra-state">
    <span class="hra-state__icon" aria-hidden="true"><?php echo icon( 'mail' ); // phpcs:ignore -- static SVG ?></span>
    <p class="hrp-eyebrow">Almost there</p>
    <h1 class="hra-title" tabindex="-1" data-autofocus>Check your inbox</h1>
    <p class="hra-lede">
        <?php if ( '' !== $email ) : ?>
            We sent an email to <strong><?php echo esc_html( $email ); ?></strong>. Open the link inside to confirm your address and finish setting up your account.
        <?php else : ?>
            We sent you an email. Open the link inside to confirm your address and finish setting up your account.
        <?php endif; ?>
    </p>
    <ol class="hra-steps">
        <li><span>1</span>Open the email from Headline Recorder (check spam or Promotions if it is not there in a minute).</li>
        <li><span>2</span>Press <strong>Confirm my email</strong>. The link works for 48 hours.</li>
        <li><span>3</span>Start your reading streak: three stories a day.</li>
    </ol>
    <p class="hra-note">Already have an account with this address? We emailed you a link to sign in instead, and nothing has changed.</p>
    <p class="hra-actions">
        <a class="hrp-btn hrp-btn--ghost" href="<?php echo esc_url( account_url( 'login' ) ); ?>">Go to sign in</a>
        <a class="hrp-btn--link hrp-btn" href="<?php echo esc_url( home_url( '/' ) ); ?>">Read today’s top stories <?php echo icon( 'arrow-right' ); // phpcs:ignore -- static SVG ?></a>
    </p>
</div>
