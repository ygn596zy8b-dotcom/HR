<?php
/** Settings → Privacy & data: export (JSON) and delete account. Both need a recent sign-in. */

use function HR\Platform\icon;
use function HR\Platform\Accounts\field;
use function HR\Platform\Accounts\error_summary;
use function HR\Platform\Accounts\hidden_fields;
use function HR\Platform\Accounts\account_url;
use function HR\Platform\Accounts\step_up_panel;

if ( ! defined( 'ABSPATH' ) ) exit;

$hra_err    = $errors['privacy'] ?? array();
$hra_action = account_url( 'settings' ) . '#privacy';
?>
<section class="hra-section" id="privacy" aria-labelledby="privacy-title">
    <header class="hra-section__head">
        <h2 class="hra-h2" id="privacy-title">Privacy &amp; data</h2>
        <p class="hra-muted">Your data is yours. Take a copy or delete it whenever you like. See our <a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>">Privacy Policy</a>.</p>
    </header>
    <?php if ( ! $recent ) : ?>
        <?php if ( isset( $hra_err['_form'] ) ) : ?><p class="hrp-error hra-inline-error" role="alert"><?php echo esc_html( $hra_err['_form'] ); ?></p><?php endif; ?>
        <?php step_up_panel( 'privacy' ); ?>
    <?php endif; ?>

    <div class="hra-block">
        <div class="hra-block__head">
            <span class="hra-block__icon" aria-hidden="true"><?php echo icon( 'download' ); // phpcs:ignore -- static SVG ?></span>
            <div>
                <h3 class="hra-h3">Download your data</h3>
                <p class="hra-muted">A JSON file with your profile, security settings, reading history and submissions’ status. No passwords or keys.</p>
            </div>
        </div>
        <?php if ( $recent ) : ?>
            <form method="post" action="<?php echo esc_url( $hra_action ); ?>" class="hra-block__foot" data-hra-download>
                <?php hidden_fields( 'export' ); ?>
                <button type="submit" class="hrp-btn hrp-btn--ghost"><?php echo icon( 'download' ); // phpcs:ignore -- static SVG ?><span>Download my data</span></button>
            </form>
        <?php endif; ?>
    </div>

    <div class="hra-block hra-block--danger">
        <div class="hra-block__head">
            <span class="hra-block__icon" aria-hidden="true"><?php echo icon( 'trash' ); // phpcs:ignore -- static SVG ?></span>
            <div>
                <h3 class="hra-h3">Delete your account</h3>
                <p class="hra-muted">Permanently removes your account, reading streak, passkeys and settings. Published articles stay published under your name. This cannot be undone.</p>
            </div>
        </div>
        <?php if ( $staff ) : ?>
            <p class="hra-note">Staff accounts are closed by an administrator.</p>
        <?php elseif ( $recent ) : ?>
            <form class="hrp-form hra-form hra-narrow" method="post" action="<?php echo esc_url( $hra_action ); ?>" novalidate data-hra-form data-hra-confirm="Delete your Headline Recorder account permanently?">
                <?php error_summary( array_diff_key( $hra_err, array( '_form' => 1 ) ) + ( isset( $hra_err['_form'] ) ? array( '_form' => $hra_err['_form'] ) : array() ), 'privacy-' ); ?>
                <?php hidden_fields( 'delete_account' ); ?>
                <?php
                field( array(
                    'name'         => 'confirm_delete',
                    'label'        => 'Type DELETE to confirm',
                    'error'        => $hra_err['confirm_delete'] ?? '',
                    'autocomplete' => 'off',
                    'maxlength'    => 20,
                    'attrs'        => array( 'autocapitalize' => 'characters', 'spellcheck' => 'false', 'data-hra-required' => 'Type DELETE in capital letters to confirm.', 'data-hra-equals' => 'DELETE' ),
                ) );
                ?>
                <div><button type="submit" class="hrp-btn hra-btn-danger hra-submit"><?php echo icon( 'trash' ); // phpcs:ignore -- static SVG ?><span>Delete my account</span></button></div>
            </form>
        <?php endif; ?>
    </div>
</section>
