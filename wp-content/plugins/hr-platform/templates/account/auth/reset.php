<?php
/**
 * Choose a new password (the key travels in an HttpOnly cookie, not the URL).
 * @var bool $done  @var array $errors  @var WP_User $user
 */

use function HR\Platform\icon;
use function HR\Platform\Accounts\field;
use function HR\Platform\Accounts\error_summary;
use function HR\Platform\Accounts\hidden_fields;
use function HR\Platform\Accounts\account_url;
use function HR\Platform\Accounts\mask_email;

if ( ! defined( 'ABSPATH' ) ) exit;

if ( $done ) : ?>
    <div class="hra-state hra-state--ok">
        <span class="hra-state__icon" aria-hidden="true"><?php echo icon( 'check' ); // phpcs:ignore -- static SVG ?></span>
        <p class="hrp-eyebrow">All set</p>
        <h1 class="hra-title" tabindex="-1" data-autofocus>Password updated</h1>
        <p class="hra-lede">Your new password is active and every device that was signed in has been signed out.</p>
        <p class="hra-actions"><a class="hrp-btn" href="<?php echo esc_url( account_url( 'login' ) ); ?>"><?php echo icon( 'log-in' ); // phpcs:ignore -- static SVG ?><span>Sign in</span></a></p>
        <p class="hra-note">Tip: add a passkey in Settings → Security and you will never need to type a password again.</p>
    </div>
<?php else : ?>
    <p class="hrp-eyebrow">Account help</p>
    <h1 class="hra-title">Choose a new password</h1>
    <p class="hra-lede">For <strong><?php echo esc_html( mask_email( $user->user_email ) ); ?></strong>. Every other session will be signed out.</p>
    <form class="hrp-form hra-form" method="post" action="<?php echo esc_url( account_url( 'reset' ) ); ?>" novalidate data-hra-form>
        <?php error_summary( $errors ); ?>
        <?php hidden_fields( 'reset' ); ?>
        <input type="text" name="username" value="<?php echo esc_attr( $user->user_email ); ?>" autocomplete="username" hidden>
        <?php
        field( array(
            'name'         => 'password',
            'label'        => 'New password',
            'hint'         => 'At least 12 characters. A short sentence you will remember works well.',
            'type'         => 'password',
            'error'        => $errors['password'] ?? '',
            'autocomplete' => 'new-password',
            'maxlength'    => 1024,
            'minlength'    => 12,
            'reveal'       => true,
            'meter'        => true,
            'attrs'        => array( 'data-hra-required' => 'Choose a new password.', 'data-hra-user-inputs' => 'username' ),
        ) );
        ?>
        <button type="submit" class="hrp-btn hrp-btn--block hra-submit"><span>Save new password</span></button>
    </form>
<?php endif;
