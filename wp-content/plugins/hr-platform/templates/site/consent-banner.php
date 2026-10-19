<?php
/**
 * Cookie banner (bottom sheet). Identical for every visitor; assets/js/consent.js decides whether to show it.
 * It is visible at first paint for new visitors through the hr-consent-pending class set in <head>.
 *
 * @var string $policy_url Link to the cookie section of the privacy policy.
 * @var string $mode       'basic' or 'advanced' (Google tag loading before consent).
 */

use function HR\Platform\icon;

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<section id="hr-cc" class="hrp hr-cc" role="region" aria-label="Cookie preferences" data-hr-cc data-mode="<?php echo esc_attr( $mode ); ?>">
    <div class="hr-cc__inner">
        <div class="hr-cc__intro">
            <h2 class="hr-cc__title" id="hr-cc-title">Cookies on Headline Recorder</h2>
            <p class="hr-cc__text" id="hr-cc-text">
                We use necessary cookies to run the site and keep your account secure, and — with your permission — analytics to understand what readers value.
                <a class="hr-cc__link" href="<?php echo esc_url( $policy_url ); ?>">How we use cookies</a>
            </p>
        </div>
        <div class="hr-cc__actions">
            <button type="button" class="hr-cc__btn" data-hr-cc-action="accept">Accept all</button>
            <button type="button" class="hr-cc__btn" data-hr-cc-action="reject">Reject all</button>
            <button type="button" class="hr-cc__btn" data-hr-cc-action="choose" aria-expanded="false" aria-controls="hr-cc-panel">Choose</button>
        </div>
    </div>

    <div class="hr-cc__panel" id="hr-cc-panel" hidden>
        <fieldset class="hr-cc__options">
            <legend class="hr-cc__legend" tabindex="-1">Choose which cookies we may use</legend>

            <div class="hr-cc__opt">
                <div class="hr-cc__opt-text">
                    <span class="hr-cc__opt-name" id="hr-cc-nec-name">Necessary</span>
                    <span class="hr-cc__opt-desc" id="hr-cc-nec-desc">Sign-in and security, form protection, and remembering this choice. Always on.</span>
                </div>
                <span class="hr-cc__switch">
                    <input type="checkbox" role="switch" id="hr-cc-nec" checked disabled aria-labelledby="hr-cc-nec-name" aria-describedby="hr-cc-nec-desc">
                    <span class="hr-cc__track" aria-hidden="true"></span>
                </span>
            </div>

            <div class="hr-cc__opt">
                <div class="hr-cc__opt-text">
                    <label class="hr-cc__opt-name" for="hr-cc-analytics">Analytics</label>
                    <span class="hr-cc__opt-desc" id="hr-cc-analytics-desc">Google Analytics counts visits and the articles people read, so we know what is useful. No advertising.</span>
                    <span class="hr-cc__opt-note" data-hr-cc-gpc hidden>Your browser sends Global Privacy Control, so analytics stay off.</span>
                </div>
                <span class="hr-cc__switch">
                    <input type="checkbox" role="switch" id="hr-cc-analytics" name="analytics" aria-describedby="hr-cc-analytics-desc">
                    <span class="hr-cc__track" aria-hidden="true"></span>
                </span>
            </div>

            <div class="hr-cc__opt">
                <div class="hr-cc__opt-text">
                    <label class="hr-cc__opt-name" for="hr-cc-embeds">Embedded content</label>
                    <span class="hr-cc__opt-desc" id="hr-cc-embeds-desc">YouTube videos and TradingView market widgets. These services may set their own cookies.</span>
                </div>
                <span class="hr-cc__switch">
                    <input type="checkbox" role="switch" id="hr-cc-embeds" name="embeds" aria-describedby="hr-cc-embeds-desc">
                    <span class="hr-cc__track" aria-hidden="true"></span>
                </span>
            </div>
        </fieldset>
        <div class="hr-cc__panel-actions">
            <button type="button" class="hr-cc__btn hr-cc__btn--save" data-hr-cc-action="save">Save choices</button>
            <button type="button" class="hr-cc__close" data-hr-cc-action="close" aria-label="Close cookie choices"><?php echo icon( 'x' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG ?></button>
        </div>
    </div>
</section>
<p class="hrp-sr" role="status" aria-live="polite" data-hr-cc-status></p><?php // Outside the banner so the confirmation is still announced after it hides. ?>

