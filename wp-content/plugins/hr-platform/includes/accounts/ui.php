<?php
/**
 * Accounts: the small rendering kit the templates share — fields with inline errors, error summary, notices,
 * flash messages, page shells and asset loading.
 */

namespace HR\Platform\Accounts;

use function HR\Platform\icon;
use function HR\Platform\render;
use function HR\Platform\asset_url;
use function HR\Platform\asset_ver;
use function HR\Platform\enqueue_base_style;
use function HR\Platform\set_page_title;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Labelled input with hint and inline error (aria-invalid + aria-describedby). The error element always exists so
 * account.js can fill it in without moving the layout around.
 *
 * Keys: name, label, type, value, id, hint, error, autocomplete, required, maxlength, minlength, inputmode,
 * pattern, placeholder, attrs (extra attributes), reveal (password show/hide), meter (strength meter), optional.
 */
function field( array $a ): void {
    $a  += array(
        'type' => 'text', 'name' => '', 'label' => '', 'value' => '', 'id' => '', 'hint' => '', 'error' => '',
        'autocomplete' => '', 'required' => true, 'maxlength' => 0, 'minlength' => 0, 'inputmode' => '', 'pattern' => '',
        'placeholder' => '', 'attrs' => array(), 'reveal' => false, 'meter' => false, 'optional' => false, 'class' => '',
    );
    $id       = $a['id'] ?: 'hra-' . $a['name'];
    $hint_id  = $id . '-hint';
    $error_id = $id . '-error';
    $describe = array();
    if ( '' !== $a['hint'] ) {
        $describe[] = $hint_id;
    }
    if ( '' !== $a['error'] ) {
        $describe[] = $error_id;
    }
    if ( $a['meter'] ) {
        $describe[] = $id . '-meter';
    }
    $attrs = array(
        'class'        => 'hrp-input',
        'type'         => $a['type'],
        'id'           => $id,
        'name'         => $a['name'],
        'autocomplete' => $a['autocomplete'],
        'inputmode'    => $a['inputmode'],
        'pattern'      => $a['pattern'],
        'placeholder'  => $a['placeholder'],
        'maxlength'    => $a['maxlength'] ?: '',
        'minlength'    => $a['minlength'] ?: '',
    ) + $a['attrs'];
    if ( 'password' !== $a['type'] ) {
        $attrs['value'] = (string) $a['value'];
    }
    if ( $a['required'] ) {
        $attrs['required'] = true;
    }
    if ( '' !== $a['error'] ) {
        $attrs['aria-invalid'] = 'true';
    }
    if ( $describe ) {
        $attrs['aria-describedby'] = implode( ' ', $describe );
    }
    ?>
    <div class="hrp-field hra-field <?php echo esc_attr( $a['class'] ); ?>" data-hra-field>
        <label class="hrp-label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $a['label'] ); ?><?php if ( $a['optional'] ) : ?> <span class="hra-optional">(optional)</span><?php endif; ?></label>
        <?php if ( '' !== $a['hint'] ) : ?>
            <p class="hrp-hint" id="<?php echo esc_attr( $hint_id ); ?>"><?php echo esc_html( $a['hint'] ); ?></p>
        <?php endif; ?>
        <?php if ( $a['reveal'] ) : ?><div class="hrp-password"><?php endif; ?>
        <input <?php echo attrs( $attrs ); // phpcs:ignore -- attrs() escapes ?>>
        <?php if ( $a['reveal'] ) : ?>
            <button type="button" class="hrp-reveal" data-hra-reveal="<?php echo esc_attr( $id ); ?>" aria-controls="<?php echo esc_attr( $id ); ?>" aria-pressed="false" aria-label="Show password"><?php echo icon( 'eye' ); ?></button>
            </div>
        <?php endif; ?>
        <?php if ( $a['meter'] ) : ?>
            <div class="hra-meter" id="<?php echo esc_attr( $id . '-meter' ); ?>" data-hra-meter="<?php echo esc_attr( $id ); ?>" data-score="-1">
                <div class="hra-meter__bars" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
                <p class="hra-meter__text" aria-live="polite"><span class="hra-meter__label">Use at least 12 characters.</span> <span class="hra-meter__tip"></span></p>
            </div>
        <?php endif; ?>
        <p class="hrp-error hra-error" id="<?php echo esc_attr( $error_id ); ?>" <?php echo '' === $a['error'] ? 'hidden' : ''; ?>><?php echo icon( 'x' ); ?><span><?php echo esc_html( $a['error'] ); ?></span></p>
    </div>
    <?php
}

function attrs( array $attrs ): string {
    $out = array();
    foreach ( $attrs as $name => $value ) {
        if ( false === $value || null === $value || '' === $value ) {
            continue;
        }
        $out[] = true === $value ? esc_attr( $name ) : sprintf( '%s="%s"', esc_attr( $name ), esc_attr( (string) $value ) );
    }
    return implode( ' ', $out );
}

/**
 * Error summary at the top of a form; account.js focuses it (server errors on load, client errors on submit).
 * $errors: field name => message; '_form' => message that belongs to no field.
 */
function error_summary( array $errors, string $form_id = '' ): void {
    $field_errors = array_diff_key( $errors, array( '_form' => 1 ) );
    $title        = $field_errors ? ( 1 === count( $field_errors ) ? 'There is a problem with one field' : 'There are problems with ' . count( $field_errors ) . ' fields' ) : 'Something went wrong';
    ?>
    <div class="hra-summary" data-hra-summary tabindex="-1" role="alert" aria-labelledby="<?php echo esc_attr( $form_id . 'hra-summary-title' ); ?>" <?php echo $errors ? 'data-autofocus' : 'hidden'; ?>>
        <p class="hra-summary__title" id="<?php echo esc_attr( $form_id . 'hra-summary-title' ); ?>"><?php echo icon( 'x' ); ?><span><?php echo esc_html( $title ); ?></span></p>
        <?php if ( isset( $errors['_form'] ) ) : ?>
            <p class="hra-summary__lead"><?php echo esc_html( $errors['_form'] ); ?></p>
        <?php endif; ?>
        <ul class="hra-summary__list">
            <?php foreach ( $field_errors as $name => $message ) : ?>
                <li><a href="#<?php echo esc_attr( 'hra-' . $name ); ?>"><?php echo esc_html( $message ); ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
}

function notice( string $type, string $text, string $title = '' ): void {
    $icon = array( 'ok' => 'check', 'error' => 'x', 'warn' => 'shield-check', 'info' => 'mail' )[ $type ] ?? 'mail';
    ?>
    <div class="hrp-alert hrp-alert--<?php echo esc_attr( $type ); ?> hra-notice" role="<?php echo 'error' === $type ? 'alert' : 'status'; ?>" data-hra-reveal>
        <span class="hra-notice__icon"><?php echo icon( $icon ); ?></span>
        <div>
            <?php if ( '' !== $title ) : ?><p class="hra-notice__title"><?php echo esc_html( $title ); ?></p><?php endif; ?>
            <p><?php echo esc_html( $text ); ?></p>
        </div>
    </div>
    <?php
}

/** Messages that can be shown after a redirect. Text lives here, never in the cookie. */
function flash_messages(): array {
    return array(
        'signed_out'      => array( 'ok', 'You have signed out.', '' ),
        'signed_out_all'  => array( 'ok', 'You have signed out on every device.', '' ),
        'login_timeout'   => array( 'warn', 'Your sign-in timed out. Please enter your details again.', '' ),
        'login_needed'    => array( 'info', 'Please sign in to continue.', '' ),
        'reset_done'      => array( 'ok', 'Your password has been changed and every other session was signed out. Sign in with your new password.', 'Password updated' ),
        'reset_expired'   => array( 'error', 'That reset link is invalid or has expired. Request a new one below.', '' ),
        'verified_signin' => array( 'ok', 'Your email address is confirmed. Sign in to continue.', 'Email confirmed' ),
        'email_changed'   => array( 'ok', 'Your email address has been changed.', 'Email updated' ),
        'deleted'         => array( 'ok', 'Your account and its data have been deleted. Thank you for reading Headline Recorder.', 'Account deleted' ),
        'profile_saved'   => array( 'ok', 'Your profile has been updated.', '' ),
        'password_saved'  => array( 'ok', 'Your password has been changed. Other devices have been signed out.', 'Password updated' ),
        'email_sent'      => array( 'ok', 'We sent a confirmation link to the new address. Your email changes once you open it.', 'Check your inbox' ),
        'verify_sent'     => array( 'ok', 'We sent you a new confirmation link. It works for 48 hours.', 'Check your inbox' ),
        'verify_limited'  => array( 'warn', 'You have asked for several links already. Please wait an hour and try again.', '' ),
        'stepup_ok'       => array( 'ok', 'Thanks — you can make security changes for the next 10 minutes.', '' ),
        'passkey_added'   => array( 'ok', 'Your passkey is ready. Next time, sign in with your fingerprint, face or screen lock.', 'Passkey added' ),
        'passkey_renamed' => array( 'ok', 'Passkey renamed.', '' ),
        'passkey_removed' => array( 'ok', 'Passkey removed.', '' ),
        'totp_disabled'   => array( 'ok', 'Two-step verification is off. Your recovery codes no longer work.', '' ),
        'totp_expired'    => array( 'warn', 'The setup took too long. Start again to get a fresh QR code.', '' ),
        'others_out'      => array( 'ok', 'Every other session has been signed out.', '' ),
        'session_out'     => array( 'ok', 'That session has been signed out.', '' ),
        'staff_mfa'       => array( 'warn', 'Staff accounts need a passkey or two-step verification before opening the dashboard. Add one below — it takes a minute.', 'One more step' ),
        'csrf'            => array( 'error', 'This page was open for too long or the request could not be verified. Please try again.', '' ),
        'rate_limited'    => array( 'error', 'Too many attempts. Please wait a while and try again.', '' ),
    );
}

function render_flash(): void {
    $flash = take_flash();
    if ( ! $flash ) {
        return;
    }
    $messages = flash_messages();
    if ( isset( $messages[ $flash['key'] ] ) ) {
        [ $type, $text, $title ] = $messages[ $flash['key'] ];
        notice( $type, $text, $title );
    }
}

function enqueue_assets( array $extra = array() ): void {
    enqueue_base_style();
    wp_enqueue_style( 'hr-account', asset_url( 'css/account.css' ), array( 'hr-platform' ), asset_ver( 'css/account.css' ) );
    $deps = array();
    if ( in_array( 'strength', $extra, true ) ) {
        wp_enqueue_script( 'password-strength-meter' );
        $deps[] = 'password-strength-meter';
    }
    wp_enqueue_script( 'hr-account', asset_url( 'js/account.js' ), $deps, asset_ver( 'js/account.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
    if ( in_array( 'totp', $extra, true ) ) {
        wp_enqueue_script( 'hr-account-totp', asset_url( 'js/account-totp.js' ), array(), asset_ver( 'js/account-totp.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
    }
}

/** Non-executable JSON for account.js (REST base and the nonce for this signed-in session). */
function print_config(): void {
    $config = array(
        'rest'  => esc_url_raw( rest_url( 'hr/v1/' ) ),
        'nonce' => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
    );
    printf( '<script type="application/json" id="hra-config">%s</script>', wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) );
}

function begin_page( string $title, string $sub, array $extra = array() ): void {
    set_page_title( $title );
    enqueue_assets( $extra );
    add_filter( 'body_class', static fn( $c ) => array_merge( $c, array( 'hra-page', 'hra-page--' . sanitize_html_class( $sub ) ) ) );
    if ( in_array( $sub, array( 'reset', 'verify' ), true ) ) {
        add_action( 'wp_head', static function () {
            echo '<meta name="referrer" content="no-referrer">' . "\n";
        }, 1 );
    }
    add_action( 'wp_footer', __NAMESPACE__ . '\\print_config', 1 );
    get_header();
}

/** Signed-out pages: login, register, forgot, reset, verify, two-factor. */
function render_auth( string $view, array $vars, string $title, string $sub, array $extra = array() ): void {
    begin_page( $title, $sub, $extra );
    render( 'account/auth', array( 'view' => $view, 'vars' => $vars, 'sub' => $sub ) );
    get_footer();
}

/** Signed-in pages inside the account shell (navigation + content). */
function render_app( \WP_User $user, string $active, string $title, callable $content, array $extra = array() ): void {
    begin_page( $title, $active, $extra );
    render( 'account/app', array( 'user' => $user, 'active' => $active, 'title' => $title, 'content' => $content ) );
    get_footer();
}

/** Navigation items for the account shell. */
function nav_items(): array {
    $items = array( 'overview' => array( 'title' => 'Overview', 'icon' => 'user', 'url' => account_url() ) );
    foreach ( account_pages() as $slug => $page ) {
        if ( $page['nav'] ) {
            $items[ $slug ] = array( 'title' => $page['title'], 'icon' => $page['icon'], 'url' => $page['url'] ?: account_url( $slug ), 'external' => (bool) $page['url'] );
        }
    }
    $items['settings'] = array( 'title' => 'Settings', 'icon' => 'sliders', 'url' => account_url( 'settings' ) );
    return $items;
}

/** Inline re-authentication panel shown in place of a sensitive form. */
function step_up_panel( string $anchor = '' ): void {
    $user = wp_get_current_user();
    render( 'account/step-up', array(
        'anchor'      => sanitize_html_class( $anchor ),
        'has_totp'    => has_totp( (int) $user->ID ),
        'has_passkey' => passkey_count( (int) $user->ID ) > 0,
        'errors'      => $GLOBALS['hr_accounts_stepup_errors'][ $anchor ] ?? array(),
    ) );
}
