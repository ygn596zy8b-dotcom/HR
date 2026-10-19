<?php
/**
 * Second sign-in step. @var array $errors  @var string $method totp|recovery  @var int $attempts_left
 */

use function HR\Platform\icon;
use function HR\Platform\Accounts\field;
use function HR\Platform\Accounts\error_summary;
use function HR\Platform\Accounts\hidden_fields;
use function HR\Platform\Accounts\account_url;

if ( ! defined( 'ABSPATH' ) ) exit;

$hra_recovery = 'recovery' === $method;
?>
<p class="hrp-eyebrow">Two-step verification</p>
<h1 class="hra-title"><?php echo $hra_recovery ? 'Use a recovery code' : 'Enter your code'; ?></h1>
<p class="hra-lede">
    <?php echo $hra_recovery
        ? 'Enter one of the recovery codes you saved when you turned on two-step verification. Each code works once.'
        : 'Open your authenticator app and enter the 6-digit code for Headline Recorder.'; ?>
</p>
<form class="hrp-form hra-form" method="post" action="<?php echo esc_url( account_url( 'two-factor' ) ); ?>" novalidate data-hra-form>
    <?php error_summary( $errors ); ?>
    <?php hidden_fields( 'two_factor' ); ?>
    <input type="hidden" name="method" value="<?php echo esc_attr( $hra_recovery ? 'recovery' : 'totp' ); ?>">
    <?php
    if ( $hra_recovery ) {
        field( array(
            'name'         => 'code',
            'label'        => 'Recovery code',
            'error'        => $errors['code'] ?? '',
            'autocomplete' => 'off',
            'maxlength'    => 20,
            'placeholder'  => 'xxxx-xxxx',
            'class'        => 'hra-field--code',
            'attrs'        => array( 'autocapitalize' => 'none', 'spellcheck' => 'false', 'autofocus' => true, 'data-hra-required' => 'Enter one of your recovery codes.' ),
        ) );
    } else {
        field( array(
            'name'         => 'code',
            'label'        => 'Authentication code',
            'error'        => $errors['code'] ?? '',
            'autocomplete' => 'one-time-code',
            'inputmode'    => 'numeric',
            'maxlength'    => 7,
            'pattern'      => '[0-9 ]*',
            'placeholder'  => '123 456',
            'class'        => 'hra-field--code',
            'attrs'        => array( 'autofocus' => true, 'data-hra-required' => 'Enter the 6-digit code from your authenticator app.', 'data-hra-otp' => '1' ),
        ) );
    }
    ?>
    <button type="submit" class="hrp-btn hrp-btn--block hra-submit"><?php echo icon( 'shield-check' ); // phpcs:ignore -- static SVG ?><span>Verify and sign in</span></button>
    <?php if ( $attempts_left > 0 && $attempts_left < 5 ) : ?>
        <p class="hra-note hra-center"><?php echo esc_html( sprintf( _n( '%d attempt left before you need to sign in again.', '%d attempts left before you need to sign in again.', $attempts_left ), $attempts_left ) ); ?></p>
    <?php endif; ?>
</form>
<p class="hra-switch">
    <?php if ( $hra_recovery ) : ?>
        <a href="<?php echo esc_url( account_url( 'two-factor' ) ); ?>">Use your authenticator app instead</a>
    <?php else : ?>
        Lost your phone? <a href="<?php echo esc_url( add_query_arg( 'method', 'recovery', account_url( 'two-factor' ) ) ); ?>">Use a recovery code</a>
    <?php endif; ?>
</p>
<p class="hra-note hra-center">This step expires 5 minutes after you entered your password. <a href="<?php echo esc_url( account_url( 'login' ) ); ?>">Start over</a></p>
