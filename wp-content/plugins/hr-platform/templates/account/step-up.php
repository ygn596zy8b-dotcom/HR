<?php
/**
 * Inline re-authentication before a sensitive change (valid 10 minutes for this session).
 * @var string $anchor  section to come back to  @var bool $has_totp  @var bool $has_passkey  @var array $errors
 */

use function HR\Platform\icon;
use function HR\Platform\Accounts\field;
use function HR\Platform\Accounts\hidden_fields;
use function HR\Platform\Accounts\account_url;

if ( ! defined( 'ABSPATH' ) ) exit;

$hra_anchor = $anchor ?: 'security';
$hra_action = account_url( 'settings' ) . '#' . $hra_anchor;
$hra_open   = ! empty( $errors );
?>
<div class="hra-stepup<?php echo $hra_open ? ' is-open' : ''; ?>" id="<?php echo esc_attr( 'stepup-' . $hra_anchor ); ?>" data-hra-stepup>
    <div class="hra-stepup__head">
        <span class="hra-stepup__icon" aria-hidden="true"><?php echo icon( 'lock' ); // phpcs:ignore -- static SVG ?></span>
        <div>
            <p class="hra-stepup__title">Confirm it’s you</p>
            <p class="hra-stepup__text">For your security, confirm your identity before changing this. We won’t ask again for 10 minutes.</p>
        </div>
    </div>
    <div class="hra-stepup__body">
        <?php if ( $has_passkey ) : ?>
            <button type="button" class="hrp-btn hra-stepup__passkey" data-hra-stepup-passkey="<?php echo esc_attr( $hra_anchor ); ?>"><?php echo icon( 'key' ); // phpcs:ignore -- static SVG ?><span>Use your passkey</span></button>
            <p class="hrp-error hra-inline-error" data-hra-stepup-error role="alert" hidden></p>
            <div class="hra-or"><span>or use your password</span></div>
        <?php endif; ?>
        <form class="hrp-form hra-form hra-stepup__form" method="post" action="<?php echo esc_url( $hra_action ); ?>" novalidate data-hra-form>
            <?php hidden_fields( 'stepup_password' ); ?>
            <input type="hidden" name="return" value="<?php echo esc_attr( $hra_anchor ); ?>">
            <input type="text" name="username" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" autocomplete="username" hidden>
            <div class="hra-inline-fields">
                <?php
                field( array(
                    'name'         => 'stepup_pwd',
                    'id'           => 'hra-stepup-pwd-' . $hra_anchor,
                    'label'        => 'Your password',
                    'type'         => 'password',
                    'error'        => $errors['stepup_pwd'] ?? '',
                    'autocomplete' => 'current-password',
                    'maxlength'    => 1024,
                    'reveal'       => true,
                    'attrs'        => array( 'data-hra-required' => 'Enter your password.' ),
                ) );
                ?>
                <button type="submit" class="hrp-btn hrp-btn--ghost hra-submit"><span>Confirm</span></button>
            </div>
        </form>
        <?php if ( $has_totp ) : ?>
            <details class="hra-stepup__alt" <?php echo isset( $errors['stepup_code'] ) ? 'open' : ''; ?>>
                <summary>Use an authenticator or recovery code instead</summary>
                <form class="hrp-form hra-form" method="post" action="<?php echo esc_url( $hra_action ); ?>" novalidate data-hra-form>
                    <?php hidden_fields( 'stepup_totp' ); ?>
                    <input type="hidden" name="return" value="<?php echo esc_attr( $hra_anchor ); ?>">
                    <div class="hra-inline-fields">
                        <?php
                        field( array(
                            'name'         => 'stepup_code',
                            'id'           => 'hra-stepup-code-' . $hra_anchor,
                            'label'        => 'Code',
                            'error'        => $errors['stepup_code'] ?? '',
                            'autocomplete' => 'one-time-code',
                            'inputmode'    => 'numeric',
                            'maxlength'    => 20,
                            'attrs'        => array( 'data-hra-required' => 'Enter a code.', 'autocapitalize' => 'none', 'spellcheck' => 'false' ),
                        ) );
                        ?>
                        <button type="submit" class="hrp-btn hrp-btn--ghost hra-submit"><span>Confirm</span></button>
                    </div>
                </form>
            </details>
        <?php endif; ?>
    </div>
</div>
