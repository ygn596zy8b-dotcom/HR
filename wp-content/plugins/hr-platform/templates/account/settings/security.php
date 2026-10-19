<?php
/** Settings → Security: score, passkeys, two-step verification, recovery codes, sessions. */

use function HR\Platform\icon;
use function HR\Platform\render;
use function HR\Platform\Accounts\field;
use function HR\Platform\Accounts\error_summary;
use function HR\Platform\Accounts\hidden_fields;
use function HR\Platform\Accounts\account_url;
use function HR\Platform\Accounts\step_up_panel;
use function HR\Platform\Accounts\aaguid_name;
use function HR\Platform\Accounts\fmt_time;
use function HR\Platform\Accounts\rel_time;

if ( ! defined( 'ABSPATH' ) ) exit;

$hra_action   = account_url( 'settings' );
$hra_sec_err  = $errors['security'] ?? array();
$hra_totp_err = $errors['totp'] ?? array();
$hra_count    = count( $passkeys );
$hra_methods  = array( 'password' => 'password', 'passkey' => 'passkey', 'password+totp' => 'password + code', 'password+recovery' => 'password + recovery code', 'email-link' => 'email link' );
?>
<section class="hra-section" id="security" aria-labelledby="security-title">
    <header class="hra-section__head">
        <h2 class="hra-h2" id="security-title">Security</h2>
        <p class="hra-muted">Each step makes your account harder to break into. Most take under a minute.</p>
    </header>

    <?php render( 'account/checklist', array( 'checklist' => $checklist, 'score' => $score, 'level' => $level ) ); ?>

    <?php if ( isset( $hra_sec_err['_form'] ) ) : ?>
        <p class="hrp-error hra-inline-error" role="alert"><?php echo icon( 'x' ); // phpcs:ignore -- static SVG ?><span><?php echo esc_html( $hra_sec_err['_form'] ); ?></span></p>
    <?php endif; ?>
    <?php if ( $recent ) : ?>
        <p class="hra-unlocked" data-hra-countdown="<?php echo (int) $recent_left; ?>"><?php echo icon( 'shield-check' ); // phpcs:ignore -- static SVG ?><span>Identity confirmed — you can change security settings for <strong data-hra-countdown-text><?php echo esc_html( (string) max( 1, (int) ceil( $recent_left / 60 ) ) ); ?> min</strong>.</span></p>
    <?php else : ?>
        <?php step_up_panel( 'security' ); ?>
    <?php endif; ?>

    <!-- Passkeys -->
    <div class="hra-block" id="passkeys">
        <div class="hra-block__head">
            <span class="hra-block__icon" aria-hidden="true"><?php echo icon( 'key' ); // phpcs:ignore -- static SVG ?></span>
            <div>
                <h3 class="hra-h3">Passkeys</h3>
                <p class="hra-muted">Sign in with your fingerprint, face or screen lock. Passkeys cannot be phished or leaked, and they count as two-step verification.</p>
            </div>
            <?php if ( $hra_count ) : ?><span class="hrp-badge hrp-badge--ok"><?php echo icon( 'check' ); // phpcs:ignore -- static SVG ?>On</span><?php endif; ?>
        </div>
        <?php if ( $passkeys ) : ?>
            <ul class="hra-list" role="list">
                <?php foreach ( $passkeys as $hra_pk ) : ?>
                    <?php $hra_provider = aaguid_name( (string) $hra_pk->aaguid ); ?>
                    <li class="hra-list__item">
                        <div class="hra-list__main">
                            <p class="hra-list__title"><?php echo esc_html( $hra_pk->name ); ?></p>
                            <p class="hra-list__meta">
                                <?php if ( $hra_provider && $hra_provider !== $hra_pk->name ) : ?><?php echo esc_html( $hra_provider ); ?> · <?php endif; ?>
                                Added <?php echo esc_html( fmt_time( (int) strtotime( $hra_pk->created_at . ' UTC' ), false ) ); ?> ·
                                <?php echo $hra_pk->last_used_at ? 'Last used ' . esc_html( rel_time( (int) strtotime( $hra_pk->last_used_at . ' UTC' ) ) ) : 'Not used yet'; ?>
                            </p>
                        </div>
                        <div class="hra-list__actions">
                            <details class="hra-pop">
                                <summary class="hrp-btn hrp-btn--link">Rename</summary>
                                <form class="hra-pop__panel hra-form" method="post" action="<?php echo esc_url( $hra_action . '#passkeys' ); ?>" data-hra-form>
                                    <?php hidden_fields( 'passkey_rename' ); ?>
                                    <input type="hidden" name="passkey_id" value="<?php echo (int) $hra_pk->id; ?>">
                                    <label class="hrp-label" for="<?php echo esc_attr( 'hra-pk-name-' . (int) $hra_pk->id ); ?>">Passkey name</label>
                                    <div class="hra-inline-fields">
                                        <input class="hrp-input" id="<?php echo esc_attr( 'hra-pk-name-' . (int) $hra_pk->id ); ?>" name="passkey_name" value="<?php echo esc_attr( $hra_pk->name ); ?>" maxlength="80" required>
                                        <button type="submit" class="hrp-btn hrp-btn--ghost hra-submit"><span>Save</span></button>
                                    </div>
                                </form>
                            </details>
                            <?php if ( $recent ) : ?>
                                <form method="post" action="<?php echo esc_url( $hra_action . '#passkeys' ); ?>" class="hra-inline-form" data-hra-form data-hra-confirm="<?php echo esc_attr( 'Remove the passkey “' . $hra_pk->name . '”? You will not be able to sign in with it any more.' ); ?>">
                                    <?php hidden_fields( 'passkey_delete' ); ?>
                                    <input type="hidden" name="passkey_id" value="<?php echo (int) $hra_pk->id; ?>">
                                    <button type="submit" class="hrp-btn hrp-btn--link hra-danger-link hra-submit"><span>Remove</span><span class="hrp-sr"> <?php echo esc_html( $hra_pk->name ); ?></span></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <div class="hra-block__foot">
            <button type="button" class="hrp-btn<?php echo $hra_count ? ' hrp-btn--ghost' : ''; ?>" data-hra-passkey-add data-needs-stepup="<?php echo $hra_count && ! $recent ? '1' : '0'; ?>">
                <?php echo icon( 'key' ); // phpcs:ignore -- static SVG ?><span><?php echo $hra_count ? 'Add another passkey' : 'Add a passkey'; ?></span>
            </button>
            <p class="hrp-error hra-inline-error" data-hra-passkey-add-error role="alert" hidden></p>
            <p class="hra-note" data-hra-passkey-unsupported hidden>This browser does not support passkeys. Try Chrome, Safari, Edge or Firefox on an up-to-date device.</p>
            <?php if ( $hra_count && ! $recent ) : ?><p class="hra-note">To add or remove passkeys, confirm it’s you above.</p><?php endif; ?>
        </div>
    </div>

    <!-- Two-step verification -->
    <div class="hra-block" id="two-step">
        <div class="hra-block__head">
            <span class="hra-block__icon" aria-hidden="true"><?php echo icon( 'shield-check' ); // phpcs:ignore -- static SVG ?></span>
            <div>
                <h3 class="hra-h3">Two-step verification</h3>
                <p class="hra-muted">After your password, enter a 6-digit code from an authenticator app (Google Authenticator, Microsoft Authenticator, 1Password, Authy…).</p>
            </div>
            <?php if ( $totp_on ) : ?><span class="hrp-badge hrp-badge--ok"><?php echo icon( 'check' ); // phpcs:ignore -- static SVG ?>On</span><?php endif; ?>
        </div>

        <?php if ( null !== $codes ) : ?>
            <div class="hra-codes" data-hra-codes tabindex="-1" data-autofocus>
                <p class="hra-codes__title"><?php echo icon( 'lock' ); // phpcs:ignore -- static SVG ?><?php echo $codes_new ? 'Two-step verification is on. Save your recovery codes.' : 'Your new recovery codes'; ?></p>
                <p>If you lose your phone, each code lets you sign in once. <strong>This is the only time we will show them</strong> — store them in a password manager or print them.</p>
                <ol class="hra-codes__list" data-hra-code-list>
                    <?php foreach ( $codes as $hra_code ) : ?><li><code><?php echo esc_html( $hra_code ); ?></code></li><?php endforeach; ?>
                </ol>
                <div class="hra-actions">
                    <button type="button" class="hrp-btn" data-hra-download-codes><?php echo icon( 'download' ); // phpcs:ignore -- static SVG ?><span>Download .txt</span></button>
                    <button type="button" class="hrp-btn hrp-btn--ghost" data-hra-copy-codes><?php echo icon( 'copy' ); // phpcs:ignore -- static SVG ?><span>Copy</span></button>
                </div>
            </div>
        <?php endif; ?>

        <?php if ( $totp_on ) : ?>
            <p class="hra-status">On since <?php echo esc_html( fmt_time( $totp_since, false ) ); ?> · <strong><?php echo (int) $recovery_left; ?></strong> of 10 recovery codes left<?php echo $recovery_left <= 3 ? ' — generate a new set soon' : ''; ?>.</p>
            <?php if ( $recent ) : ?>
                <div class="hra-actions">
                    <form method="post" action="<?php echo esc_url( $hra_action . '#two-step' ); ?>" class="hra-inline-form" data-hra-form data-hra-confirm="Create new recovery codes? Your current codes will stop working.">
                        <?php hidden_fields( 'recovery_regen' ); ?>
                        <button type="submit" class="hrp-btn hrp-btn--ghost hra-submit"><span>New recovery codes</span></button>
                    </form>
                    <form method="post" action="<?php echo esc_url( $hra_action . '#two-step' ); ?>" class="hra-inline-form" data-hra-form data-hra-confirm="Turn off two-step verification? Your password alone will be enough to sign in.">
                        <?php hidden_fields( 'totp_disable' ); ?>
                        <button type="submit" class="hrp-btn hrp-btn--link hra-danger-link hra-submit"><span>Turn off</span></button>
                    </form>
                </div>
            <?php else : ?>
                <p class="hra-note">To turn it off or create new recovery codes, confirm it’s you above.</p>
            <?php endif; ?>
        <?php elseif ( $setup ) : ?>
            <?php $hra_grouped = trim( chunk_split( $setup['secret'], 4, ' ' ) ); ?>
            <div class="hra-setup" data-hra-totp-setup data-secret="<?php echo esc_attr( $setup['secret'] ); ?>" data-account="<?php echo esc_attr( $setup['account'] ); ?>" data-issuer="<?php echo esc_attr( $setup['issuer'] ); ?>">
                <ol class="hra-setup__steps" role="list">
                    <li class="hra-setup__step">
                        <p class="hra-setup__label"><span>1</span>Scan this QR code with your authenticator app</p>
                        <div class="hra-qr" data-hra-qr role="img" aria-label="QR code for your authenticator app. If you cannot scan it, enter the setup key shown below.">
                            <div class="hra-skeleton hra-qr__skeleton" aria-hidden="true"></div>
                        </div>
                    </li>
                    <li class="hra-setup__step">
                        <p class="hra-setup__label"><span>2</span>Can’t scan? Enter this setup key</p>
                        <p class="hra-secret"><code data-hra-secret><?php echo esc_html( $hra_grouped ); ?></code>
                            <button type="button" class="hrp-btn hrp-btn--link" data-hra-copy="<?php echo esc_attr( $setup['secret'] ); ?>"><?php echo icon( 'copy' ); // phpcs:ignore -- static SVG ?><span>Copy key</span></button>
                        </p>
                        <p class="hra-note">Account: <?php echo esc_html( $setup['account'] ); ?> · Time-based · 6 digits</p>
                    </li>
                    <li class="hra-setup__step">
                        <p class="hra-setup__label"><span>3</span>Enter the 6-digit code your app shows</p>
                        <form class="hrp-form hra-form" method="post" action="<?php echo esc_url( $hra_action . '#two-step' ); ?>" novalidate data-hra-form>
                            <?php error_summary( $hra_totp_err, 'totp-' ); ?>
                            <?php hidden_fields( 'totp_confirm' ); ?>
                            <div class="hra-inline-fields">
                                <?php
                                field( array(
                                    'name'         => 'totp_code',
                                    'label'        => 'Code from the app',
                                    'error'        => $hra_totp_err['totp_code'] ?? '',
                                    'autocomplete' => 'one-time-code',
                                    'inputmode'    => 'numeric',
                                    'maxlength'    => 7,
                                    'pattern'      => '[0-9 ]*',
                                    'placeholder'  => '123 456',
                                    'class'        => 'hra-field--code',
                                    'attrs'        => array( 'data-hra-required' => 'Enter the 6-digit code from your app.', 'data-hra-otp' => '1' ),
                                ) );
                                ?>
                                <button type="submit" class="hrp-btn hra-submit"><span>Turn on</span></button>
                            </div>
                        </form>
                    </li>
                </ol>
                <form method="post" action="<?php echo esc_url( $hra_action . '#two-step' ); ?>" class="hra-inline-form" data-hra-form>
                    <?php hidden_fields( 'totp_cancel' ); ?>
                    <button type="submit" class="hrp-btn hrp-btn--link hra-submit"><span>Cancel setup</span></button>
                </form>
            </div>
        <?php elseif ( $recent ) : ?>
            <form method="post" action="<?php echo esc_url( $hra_action . '#two-step' ); ?>" class="hra-inline-form" data-hra-form>
                <?php hidden_fields( 'totp_start' ); ?>
                <button type="submit" class="hrp-btn hra-submit"><?php echo icon( 'shield-check' ); // phpcs:ignore -- static SVG ?><span>Set up two-step verification</span></button>
            </form>
        <?php else : ?>
            <p class="hra-note">To set it up, confirm it’s you above.</p>
        <?php endif; ?>
    </div>

    <!-- Sessions -->
    <div class="hra-block" id="sessions">
        <div class="hra-block__head">
            <span class="hra-block__icon" aria-hidden="true"><?php echo icon( 'user' ); // phpcs:ignore -- static SVG ?></span>
            <div>
                <h3 class="hra-h3">Where you’re signed in</h3>
                <p class="hra-muted">Don’t recognise a device? Sign it out, then change your password.</p>
            </div>
        </div>
        <ul class="hra-list" role="list">
            <?php foreach ( $sessions as $hra_s ) : ?>
                <li class="hra-list__item<?php echo $hra_s['current'] ? ' is-current' : ''; ?>">
                    <div class="hra-list__main">
                        <p class="hra-list__title"><?php echo esc_html( $hra_s['device'] ); ?>
                            <?php if ( $hra_s['current'] ) : ?><span class="hrp-badge hrp-badge--red">This device</span><?php endif; ?>
                        </p>
                        <p class="hra-list__meta">
                            Signed in <?php echo esc_html( rel_time( $hra_s['login'] ) ); ?><?php echo isset( $hra_methods[ $hra_s['method'] ] ) ? ' with ' . esc_html( $hra_methods[ $hra_s['method'] ] ) : ''; ?> ·
                            expires <?php echo esc_html( fmt_time( $hra_s['expiration'], false ) ); ?>
                        </p>
                    </div>
                    <?php if ( ! $hra_s['current'] ) : ?>
                        <form method="post" action="<?php echo esc_url( $hra_action . '#sessions' ); ?>" class="hra-list__actions hra-inline-form" data-hra-form>
                            <?php hidden_fields( 'session_revoke' ); ?>
                            <input type="hidden" name="session" value="<?php echo esc_attr( $hra_s['id'] ); ?>">
                            <button type="submit" class="hrp-btn hrp-btn--link hra-submit"><span>Sign out</span><span class="hrp-sr"> <?php echo esc_html( $hra_s['device'] ); ?></span></button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ( count( $sessions ) > 1 ) : ?>
            <form method="post" action="<?php echo esc_url( $hra_action . '#sessions' ); ?>" class="hra-block__foot" data-hra-form>
                <?php hidden_fields( 'sessions_others' ); ?>
                <button type="submit" class="hrp-btn hrp-btn--ghost hra-submit"><?php echo icon( 'log-out' ); // phpcs:ignore -- static SVG ?><span>Sign out everywhere else</span></button>
            </form>
        <?php endif; ?>
    </div>
</section>
