<?php
/**
 * Second sign-in step. @var array $errors  @var string $method totp|passkey|recovery  @var string[] $methods
 * @var string $options passkey request options (JSON) when $method is passkey  @var int $attempts_left
 */

use function HR\Platform\icon;
use function HR\Platform\Accounts\field;
use function HR\Platform\Accounts\error_summary;
use function HR\Platform\Accounts\hidden_fields;
use function HR\Platform\Accounts\account_url;

if ( ! defined( 'ABSPATH' ) ) exit;

$hra_copy = array(
    'totp'     => array( 'Enter your code', 'Open your authenticator app and enter the 6-digit code for Headline Recorder.' ),
    'passkey'  => array( 'Confirm with your passkey', 'Your password was right. To finish signing in, use the passkey saved on your phone, computer or security key.' ),
    'recovery' => array( 'Use a recovery code', 'Enter one of the recovery codes you saved when you turned on two-step verification. Each code works once.' ),
);
$hra_alt = array(
    'totp'     => 'Use your authenticator app instead',
    'passkey'  => 'Use your passkey instead',
    'recovery' => 'Lost your phone? Use a recovery code',
);
$hra_url = account_url( 'two-factor' );
?>
<p class="hrp-eyebrow">Two-step verification</p>
<h1 class="hra-title"><?php echo esc_html( $hra_copy[ $method ][0] ); ?></h1>
<p class="hra-lede"><?php echo esc_html( $hra_copy[ $method ][1] ); ?></p>
<form class="hrp-form hra-form" method="post" action="<?php echo esc_url( $hra_url ); ?>" novalidate data-hra-form<?php echo 'passkey' === $method ? ' data-hra-2fa-passkey' : ''; ?>>
    <?php error_summary( $errors ); ?>
    <?php hidden_fields( 'two_factor' ); ?>
    <input type="hidden" name="method" value="<?php echo esc_attr( $method ); ?>">
    <?php if ( 'passkey' === $method ) : ?>
        <?php foreach ( array( 'id', 'clientDataJSON', 'authenticatorData', 'signature' ) as $hra_f ) : ?>
            <input type="hidden" name="<?php echo esc_attr( 'wa_' . $hra_f ); ?>" value="">
        <?php endforeach; ?>
        <script type="application/json" id="hra-2fa-options"><?php echo $options; // phpcs:ignore -- wp_json_encode() with JSON_HEX_TAG|JSON_HEX_AMP, non-executable ?></script>
        <button type="button" class="hrp-btn hrp-btn--block" data-hra-2fa-passkey-go><?php echo icon( 'key' ); // phpcs:ignore -- static SVG ?><span>Use my passkey</span></button>
        <p class="hrp-error hra-inline-error" data-hra-2fa-passkey-error role="alert" hidden></p>
        <p class="hra-note" data-hra-passkey-unsupported hidden>This browser cannot use passkeys. Use another way to confirm below, or sign in on a device with your passkey.</p>
    <?php else : ?>
        <?php
        if ( 'recovery' === $method ) {
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
    <?php endif; ?>
    <?php if ( $attempts_left > 0 && $attempts_left < 5 ) : ?>
        <p class="hra-note hra-center"><?php echo esc_html( sprintf( _n( '%d attempt left before you need to sign in again.', '%d attempts left before you need to sign in again.', $attempts_left ), $attempts_left ) ); ?></p>
    <?php endif; ?>
</form>
<?php if ( count( $methods ) > 1 ) : ?>
    <ul class="hra-alt-methods" role="list">
        <?php foreach ( $methods as $hra_m ) : ?>
            <?php if ( $hra_m !== $method ) : ?>
                <li><a href="<?php echo esc_url( add_query_arg( 'method', $hra_m, $hra_url ) ); ?>"><?php echo esc_html( $hra_alt[ $hra_m ] ); ?></a></li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
<p class="hra-note hra-center">This step expires 5 minutes after you entered your password. <a href="<?php echo esc_url( account_url( 'login' ) ); ?>">Start over</a></p>
