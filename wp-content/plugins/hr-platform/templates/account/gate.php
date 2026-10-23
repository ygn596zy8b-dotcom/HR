<?php
/**
 * Shown instead of a contributed page that needs a confirmed email ('requires_verified').
 * @var WP_User $user  @var string $title
 */

use function HR\Platform\icon;
use function HR\Platform\Accounts\hidden_fields;
use function HR\Platform\Accounts\mask_email;
use function HR\Platform\Accounts\account_url;

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="hrp-card hrp-card--accent hra-gate" data-hra-reveal>
    <span class="hra-state__icon" aria-hidden="true"><?php echo icon( 'mail' ); // phpcs:ignore -- static SVG ?></span>
    <h2 class="hra-h2">Confirm your email to continue</h2>
    <p class="hra-lede"><?php echo esc_html( $title ); ?> is available once your email address is confirmed. We sent a link to <strong><?php echo esc_html( mask_email( $user->user_email ) ); ?></strong> when you signed up.</p>
    <form method="post" class="hra-form hra-inline-form" data-hra-form>
        <?php hidden_fields( 'resend_verify' ); ?>
        <button type="submit" class="hrp-btn hra-submit"><?php echo icon( 'mail' ); // phpcs:ignore -- static SVG ?><span>Send a new link</span></button>
    </form>
    <p class="hra-note">Wrong address? <a href="<?php echo esc_url( account_url( 'settings' ) . '#email' ); ?>">Change it in Settings</a>.</p>
</div>
