<?php
/**
 * Forgot password. @var bool $sent  @var string $email (masked)  @var array $errors  @var string $value
 */

use function HR\Platform\icon;
use function HR\Platform\Accounts\field;
use function HR\Platform\Accounts\error_summary;
use function HR\Platform\Accounts\hidden_fields;
use function HR\Platform\Accounts\account_url;

if ( ! defined( 'ABSPATH' ) ) exit;

if ( $sent ) : ?>
    <div class="hra-state">
        <span class="hra-state__icon" aria-hidden="true"><?php echo icon( 'mail' ); // phpcs:ignore -- static SVG ?></span>
        <p class="hrp-eyebrow">Reset link</p>
        <h1 class="hra-title" tabindex="-1" data-autofocus>Check your inbox</h1>
        <p class="hra-lede">If <strong><?php echo esc_html( $email ?: 'that address' ); ?></strong> belongs to a Headline Recorder account, a link to choose a new password is on its way. It works once.</p>
        <p class="hra-note">Nothing after a few minutes? Check spam, or make sure you used the address you signed up with.</p>
        <p class="hra-actions"><a class="hrp-btn hrp-btn--ghost" href="<?php echo esc_url( account_url( 'login' ) ); ?>">Back to sign in</a></p>
    </div>
<?php else : ?>
    <p class="hrp-eyebrow">Account help</p>
    <h1 class="hra-title">Reset your password</h1>
    <p class="hra-lede">Enter the email you signed up with and we will send you a link to choose a new password.</p>
    <form class="hrp-form hra-form" method="post" action="<?php echo esc_url( account_url( 'forgot' ) ); ?>" novalidate data-hra-form>
        <?php error_summary( $errors ); ?>
        <?php hidden_fields( 'forgot' ); ?>
        <?php
        field( array(
            'name'         => 'email',
            'label'        => 'Email address',
            'type'         => 'email',
            'value'        => $value,
            'error'        => $errors['email'] ?? '',
            'autocomplete' => 'email',
            'maxlength'    => 100,
            'attrs'        => array( 'autocapitalize' => 'none', 'spellcheck' => 'false', 'data-hra-required' => 'Enter the email address you signed up with.' ),
        ) );
        ?>
        <button type="submit" class="hrp-btn hrp-btn--block hra-submit"><span>Send reset link</span></button>
    </form>
    <p class="hra-switch">Remembered it? <a href="<?php echo esc_url( account_url( 'login' ) ); ?>">Sign in</a></p>
<?php endif;
