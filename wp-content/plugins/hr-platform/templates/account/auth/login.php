<?php
/**
 * Sign in. @var array $errors  @var array $values  @var string $redirect_to
 */

use function HR\Platform\icon;
use function HR\Platform\Accounts\field;
use function HR\Platform\Accounts\error_summary;
use function HR\Platform\Accounts\hidden_fields;
use function HR\Platform\Accounts\account_url;

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<p class="hrp-eyebrow">Welcome back</p>
<h1 class="hra-title">Sign in</h1>
<p class="hra-lede">Pick up your reading streak where you left off.</p>

<form class="hrp-form hra-form" method="post" action="<?php echo esc_url( account_url( 'login' ) ); ?>" novalidate data-hra-form data-hra-passkey-login="<?php echo esc_attr( $redirect_to ); ?>">
    <?php error_summary( $errors ); ?>
    <?php hidden_fields( 'login' ); ?>
    <?php if ( '' !== $redirect_to ) : ?>
        <input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>">
    <?php endif; ?>
    <?php
    field( array(
        'name'         => 'hr_log',
        'label'        => 'Email address',
        'type'         => 'text',
        'value'        => $values['log'],
        'error'        => $errors['log'] ?? '',
        'autocomplete' => 'username webauthn',
        'maxlength'    => 100,
        'inputmode'    => 'email',
        'attrs'        => array( 'autocapitalize' => 'none', 'spellcheck' => 'false', 'data-hra-required' => 'Enter your email address.' ),
    ) );
    ?>
    <div class="hra-field-row">
        <?php
        field( array(
            'name'         => 'hr_pwd',
            'label'        => 'Password',
            'type'         => 'password',
            'error'        => $errors['pwd'] ?? '',
            'autocomplete' => 'current-password',
            'maxlength'    => 1024,
            'reveal'       => true,
            'attrs'        => array( 'data-hra-required' => 'Enter your password.' ),
        ) );
        ?>
        <a class="hra-aside-link" href="<?php echo esc_url( account_url( 'forgot' ) ); ?>">Forgot password?</a>
    </div>
    <label class="hrp-check hra-check">
        <input type="checkbox" name="remember" value="1" <?php checked( ! empty( $values['remember'] ) ); ?>>
        <span>Keep me signed in on this device</span>
    </label>
    <button type="submit" class="hrp-btn hrp-btn--block hra-submit"><?php echo icon( 'log-in' ); // phpcs:ignore -- static SVG ?><span>Sign in</span></button>
</form>

<div class="hra-or" data-hra-passkey-only hidden><span>or</span></div>
<button type="button" class="hrp-btn hrp-btn--ghost hrp-btn--block hra-passkey-btn" data-hra-passkey-signin data-hra-passkey-only hidden>
    <?php echo icon( 'key' ); // phpcs:ignore -- static SVG ?><span>Sign in with a passkey</span>
</button>
<p class="hra-inline-error hrp-error" data-hra-passkey-error role="alert" hidden></p>

<p class="hra-switch">New to Headline Recorder? <a href="<?php echo esc_url( account_url( 'register' ) ); ?>">Create a free account</a></p>
