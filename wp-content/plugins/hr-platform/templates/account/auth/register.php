<?php
/**
 * Create an account. @var array $errors  @var array $values  @var string $ts signed render time
 */

use function HR\Platform\icon;
use function HR\Platform\Accounts\field;
use function HR\Platform\Accounts\error_summary;
use function HR\Platform\Accounts\hidden_fields;
use function HR\Platform\Accounts\account_url;

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<p class="hrp-eyebrow">Free account</p>
<h1 class="hra-title">Create your account</h1>
<p class="hra-lede">Takes under a minute. No card, no spam.</p>

<form class="hrp-form hra-form" method="post" action="<?php echo esc_url( account_url( 'register' ) ); ?>" novalidate data-hra-form>
    <?php error_summary( $errors ); ?>
    <?php hidden_fields( 'register' ); ?>
    <input type="hidden" name="hr_ts" value="<?php echo esc_attr( $ts ); ?>">
    <div class="hrp-hp" aria-hidden="true">
        <label for="hra-website">Website (leave empty)</label>
        <input type="text" id="hra-website" name="website" value="" tabindex="-1" autocomplete="off">
    </div>
    <?php
    field( array(
        'name'         => 'display_name',
        'label'        => 'Your name',
        'hint'         => 'Shown on anything you submit. You can change it later.',
        'value'        => $values['display_name'],
        'error'        => $errors['display_name'] ?? '',
        'autocomplete' => 'name',
        'maxlength'    => 60,
        'minlength'    => 2,
        'attrs'        => array( 'data-hra-required' => 'Enter your name (at least 2 characters).' ),
    ) );
    field( array(
        'name'         => 'email',
        'label'        => 'Email address',
        'hint'         => 'We will send a link to confirm it.',
        'type'         => 'email',
        'value'        => $values['email'],
        'error'        => $errors['email'] ?? '',
        'autocomplete' => 'email',
        'maxlength'    => 100,
        'attrs'        => array( 'autocapitalize' => 'none', 'spellcheck' => 'false', 'data-hra-required' => 'Enter your email address.' ),
    ) );
    field( array(
        'name'         => 'password',
        'label'        => 'Password',
        'hint'         => 'At least 12 characters. A short sentence you will remember works well.',
        'type'         => 'password',
        'error'        => $errors['password'] ?? '',
        'autocomplete' => 'new-password',
        'maxlength'    => 1024,
        'minlength'    => 12,
        'reveal'       => true,
        'meter'        => true,
        'attrs'        => array( 'data-hra-required' => 'Choose a password.', 'data-hra-user-inputs' => 'email display_name' ),
    ) );
    ?>
    <div class="hrp-field hra-field" data-hra-field>
        <label class="hrp-check hra-check">
            <input type="checkbox" id="hra-terms" name="terms" value="1" required <?php checked( ! empty( $values['terms'] ) ); ?> <?php echo isset( $errors['terms'] ) ? 'aria-invalid="true" aria-describedby="hra-terms-error"' : ''; ?> data-hra-required="Please agree to the Terms of Use and Privacy Policy.">
            <span>I agree to the <a href="<?php echo esc_url( home_url( '/terms-of-use/' ) ); ?>" target="_blank" rel="noopener">Terms of Use</a> and <a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>" target="_blank" rel="noopener">Privacy Policy</a></span>
        </label>
        <p class="hrp-error hra-error" id="hra-terms-error" <?php echo isset( $errors['terms'] ) ? '' : 'hidden'; ?>><?php echo icon( 'x' ); // phpcs:ignore -- static SVG ?><span><?php echo esc_html( $errors['terms'] ?? '' ); ?></span></p>
    </div>
    <button type="submit" class="hrp-btn hrp-btn--block hra-submit"><span>Create account</span><?php echo icon( 'arrow-right' ); // phpcs:ignore -- static SVG ?></button>
</form>
<p class="hra-switch">Already have an account? <a href="<?php echo esc_url( account_url( 'login' ) ); ?>">Sign in</a></p>
