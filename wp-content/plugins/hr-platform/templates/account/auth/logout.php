<?php
/**
 * Sign out confirmation (a link alone can never sign anyone out). @var string $error  @var WP_User $user
 */

use function HR\Platform\icon;
use function HR\Platform\Accounts\hidden_fields;
use function HR\Platform\Accounts\account_url;

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="hra-state">
    <span class="hra-state__icon" aria-hidden="true"><?php echo icon( 'log-out' ); // phpcs:ignore -- static SVG ?></span>
    <p class="hrp-eyebrow">Signed in as <?php echo esc_html( $user->display_name ); ?></p>
    <h1 class="hra-title">Sign out?</h1>
    <p class="hra-lede">Your reading streak is saved to your account and will be waiting when you come back.</p>
    <?php if ( '' !== $error ) : ?>
        <div class="hrp-alert hrp-alert--error" role="alert"><p><?php echo esc_html( $error ); ?></p></div>
    <?php endif; ?>
    <form class="hra-form" method="post" action="<?php echo esc_url( account_url( 'logout' ) ); ?>" data-hra-form>
        <?php hidden_fields( 'logout' ); ?>
        <label class="hrp-check hra-check">
            <input type="checkbox" name="everywhere" value="1">
            <span>Also sign out on every other device</span>
        </label>
        <p class="hra-actions">
            <button type="submit" class="hrp-btn hra-submit"><?php echo icon( 'log-out' ); // phpcs:ignore -- static SVG ?><span>Sign out</span></button>
            <a class="hrp-btn hrp-btn--ghost" href="<?php echo esc_url( account_url() ); ?>">Stay signed in</a>
        </p>
    </form>
</div>
