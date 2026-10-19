<?php
/** Settings → Email (change needs a recent sign-in; the new address must confirm before anything changes). */

use function HR\Platform\icon;
use function HR\Platform\Accounts\field;
use function HR\Platform\Accounts\error_summary;
use function HR\Platform\Accounts\hidden_fields;
use function HR\Platform\Accounts\account_url;
use function HR\Platform\Accounts\step_up_panel;

if ( ! defined( 'ABSPATH' ) ) exit;

$hra_err    = $errors['email'] ?? array();
$hra_action = account_url( 'settings' ) . '#email';
?>
<section class="hra-section" id="email" aria-labelledby="email-title">
    <header class="hra-section__head">
        <h2 class="hra-h2" id="email-title">Email</h2>
        <p class="hra-muted">Where we send sign-in links and security alerts.</p>
    </header>
    <div class="hra-kv">
        <p class="hra-kv__key">Current address</p>
        <p class="hra-kv__val">
            <span class="hra-break"><?php echo esc_html( $user->user_email ); ?></span>
            <?php if ( $verified ) : ?>
                <span class="hrp-badge hrp-badge--ok"><?php echo icon( 'check' ); // phpcs:ignore -- static SVG ?>Confirmed</span>
            <?php else : ?>
                <span class="hrp-badge hrp-badge--red">Not confirmed</span>
            <?php endif; ?>
        </p>
        <?php if ( ! $verified ) : ?>
            <form method="post" action="<?php echo esc_url( $hra_action ); ?>" class="hra-inline-form" data-hra-form>
                <?php hidden_fields( 'resend_verify' ); ?>
                <button type="submit" class="hrp-btn hrp-btn--link hra-submit"><span>Resend confirmation link</span></button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ( '' !== $pending_email ) : ?>
        <div class="hrp-alert hrp-alert--warn hra-banner" role="status">
            <span class="hra-notice__icon"><?php echo icon( 'mail' ); // phpcs:ignore -- static SVG ?></span>
            <div class="hra-banner__body"><p>Waiting for you to confirm <strong class="hra-break"><?php echo esc_html( $pending_email ); ?></strong>. Open the link we sent there; until then your address stays the same.</p></div>
            <form method="post" action="<?php echo esc_url( $hra_action ); ?>" class="hra-banner__form" data-hra-form>
                <?php hidden_fields( 'email_cancel' ); ?>
                <button type="submit" class="hrp-btn hrp-btn--ghost hra-submit"><span>Cancel change</span></button>
            </form>
        </div>
    <?php endif; ?>

    <?php if ( $recent ) : ?>
        <form class="hrp-form hra-form" method="post" action="<?php echo esc_url( $hra_action ); ?>" novalidate data-hra-form>
            <?php error_summary( $hra_err, 'email-' ); ?>
            <?php hidden_fields( 'email' ); ?>
            <div class="hra-inline-fields">
                <?php
                field( array(
                    'name'         => 'new_email',
                    'label'        => 'New email address',
                    'type'         => 'email',
                    'value'        => $values['new_email'] ?? '',
                    'error'        => $hra_err['new_email'] ?? '',
                    'autocomplete' => 'email',
                    'maxlength'    => 100,
                    'attrs'        => array( 'autocapitalize' => 'none', 'spellcheck' => 'false', 'data-hra-required' => 'Enter your new email address.' ),
                ) );
                ?>
                <button type="submit" class="hrp-btn hra-submit"><span>Send confirmation link</span></button>
            </div>
            <p class="hra-note">We will email a confirmation link to the new address and let your current address know.</p>
        </form>
    <?php else : ?>
        <?php if ( isset( $hra_err['_form'] ) ) : ?><p class="hrp-error hra-inline-error" role="alert"><?php echo esc_html( $hra_err['_form'] ); ?></p><?php endif; ?>
        <p class="hra-subhead">Change your email address</p>
        <?php step_up_panel( 'email' ); ?>
    <?php endif; ?>
</section>
