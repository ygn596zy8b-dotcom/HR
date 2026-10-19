<?php
/**
 * /account/submit/ — the sealed submission form. seal.js validates it, encrypts everything except the triage fields
 * (type, headline, section, contact email, word count) and uploads the envelope. Without JavaScript nothing can be
 * encrypted, so the submit button stays disabled.
 * Theme override: headlinerec-child/hr/submissions/submit.php
 *
 * @var WP_User  $user
 * @var array    $key         editors' public key {alg, kid, key}
 * @var array    $categories  term_id => name
 * @var string[] $guidelines
 * @var array    $profile     remembered {name, designation, bio} (only if the writer opted in)
 * @var array    $words       type => [min, max]
 */

use function HR\Platform\icon;
use function HR\Platform\Accounts\error_summary;
use function HR\Platform\Accounts\page_url;

if ( ! defined( 'ABSPATH' ) ) exit;

$hrs_error = static function ( string $id ): void {
    printf( '<p class="hrp-error hra-error" id="%s-error" hidden>%s<span></span></p>', esc_attr( $id ), icon( 'x' ) ); // phpcs:ignore -- static SVG
};
$hrs_range = static fn( string $type ): string => 'oped' === $type
    ? sprintf( '%s–%s words', number_format( $words['oped'][0] ), number_format( $words['oped'][1] ) )
    : sprintf( 'up to %s words', number_format( $words['article'][1] ) );
$hrs_config = array(
    'key'      => $key,
    'words'    => $words,
    'endpoint' => esc_url_raw( rest_url( 'hr/v1/submissions' ) ),
    'session'  => esc_url_raw( admin_url( 'admin-ajax.php?action=hr_session' ) ),
);
?>
<div class="hrs" data-hrs-root>
    <p class="hra-lede hrs-lede">Pitch our editors an article or an op-ed on Pakistan's business, economy and markets. Your piece is encrypted on this device before it is sent.</p>

    <details class="hrs-guidelines">
        <summary class="hrs-guidelines__summary">
            <?php echo icon( 'book-open' ); // phpcs:ignore -- static SVG ?>
            <span>Guidelines for writers</span>
            <?php echo icon( 'chevron-right', '', 'hrs-guidelines__chev' ); // phpcs:ignore -- static SVG ?>
        </summary>
        <ul class="hrs-guidelines__list">
            <?php foreach ( $guidelines as $hrs_line ) : ?>
                <li><?php echo esc_html( $hrs_line ); ?></li>
            <?php endforeach; ?>
        </ul>
    </details>

    <noscript>
        <div class="hrp-alert hrp-alert--warn hrs-noscript"><p>Submitting needs JavaScript: your piece is encrypted in your browser before it is sent, so nothing readable ever reaches our server. Please turn JavaScript on for this site.</p></div>
    </noscript>

    <form class="hra-form hrs-form" id="hrs-form" novalidate data-hrs-form>
        <script type="application/json" id="hrs-config"><?php echo wp_json_encode( $hrs_config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES ); ?></script>
        <?php error_summary( array(), 'hrs-' ); ?>

        <fieldset class="hrs-step">
            <legend class="hrs-step__title"><span class="hrs-step__num" aria-hidden="true">1</span>About you</legend>

            <div class="hrp-field hra-field hrs-headshot" data-hrs-headshot>
                <label class="hrp-label" for="hrs-headshot">Headshot</label>
                <p class="hrp-hint" id="hrs-headshot-hint">A clear, recent photo of you. JPEG, PNG or WebP, at least 400 × 400 pixels, up to 5 MB. We crop it to a square and strip camera and location data.</p>
                <div class="hrs-headshot__row">
                    <div class="hrs-headshot__frame">
                        <canvas class="hrs-headshot__canvas" width="800" height="800" tabindex="0" role="img" aria-label="Headshot preview. Drag, or use the arrow keys, to reposition." hidden data-hrs-canvas></canvas>
                        <span class="hrs-headshot__empty" data-hrs-empty><?php echo icon( 'user' ); // phpcs:ignore -- static SVG ?></span>
                    </div>
                    <div class="hrs-headshot__controls">
                        <input class="hrs-file" type="file" id="hrs-headshot" accept="image/jpeg,image/png,image/webp" aria-describedby="hrs-headshot-hint" data-hrs-required="Add a headshot.">
                        <label class="hrp-btn hrp-btn--ghost hrs-file__btn" for="hrs-headshot"><?php echo icon( 'upload' ); // phpcs:ignore -- static SVG ?><span data-hrs-pick>Choose photo</span></label>
                        <div class="hrs-zoom" hidden data-hrs-zoom-wrap>
                            <label class="hrp-label" for="hrs-zoom">Zoom</label>
                            <input class="hrs-zoom__range" type="range" id="hrs-zoom" min="1" max="3" step="0.01" value="1">
                            <p class="hrp-hint">Drag the photo, or use the arrow keys, to position it.</p>
                        </div>
                    </div>
                </div>
                <?php $hrs_error( 'hrs-headshot' ); ?>
            </div>

            <div class="hrs-pair">
                <div class="hrp-field hra-field">
                    <label class="hrp-label" for="hrs-name">Your name</label>
                    <input class="hrp-input" type="text" id="hrs-name" maxlength="100" autocomplete="name" value="<?php echo esc_attr( $profile['name'] ?? $user->display_name ); ?>" data-hrs-required="Enter your name as it should appear.">
                    <?php $hrs_error( 'hrs-name' ); ?>
                </div>
                <div class="hrp-field hra-field">
                    <label class="hrp-label" for="hrs-designation">Title or designation</label>
                    <input class="hrp-input" type="text" id="hrs-designation" maxlength="120" autocomplete="organization-title" placeholder="Senior Economist, ABC Bank" value="<?php echo esc_attr( $profile['designation'] ?? '' ); ?>" data-hrs-required="Enter your title or designation.">
                    <?php $hrs_error( 'hrs-designation' ); ?>
                </div>
            </div>

            <div class="hrp-field hra-field">
                <label class="hrp-label" for="hrs-email">Email for updates</label>
                <p class="hrp-hint" id="hrs-email-hint">We send status updates here. Stored unencrypted so editors can reply.</p>
                <input class="hrp-input" type="email" id="hrs-email" maxlength="100" autocomplete="email" inputmode="email" aria-describedby="hrs-email-hint" value="<?php echo esc_attr( $user->user_email ); ?>" data-hrs-required="Enter your email address.">
                <?php $hrs_error( 'hrs-email' ); ?>
            </div>

            <div class="hrp-field hra-field">
                <label class="hrp-label" for="hrs-bio">Short intro</label>
                <p class="hrp-hint" id="hrs-bio-hint">Two or three sentences about you, printed with your piece.</p>
                <textarea class="hrp-input hrp-textarea hrs-textarea--short" id="hrs-bio" maxlength="600" rows="4" aria-describedby="hrs-bio-hint hrs-bio-count" data-hrs-required="Write a short intro about yourself." data-hrs-count="600"><?php echo esc_textarea( $profile['bio'] ?? '' ); ?></textarea>
                <p class="hrs-counter" id="hrs-bio-count" data-hrs-counter="hrs-bio"></p>
                <?php $hrs_error( 'hrs-bio' ); ?>
            </div>

            <label class="hrp-check hra-check hrs-remember">
                <input type="checkbox" id="hrs-remember" <?php checked( ! empty( $profile ) ); ?>>
                <span>Remember my name, title and intro for my next submission. <span class="hra-optional">They are then stored unencrypted on our server.</span></span>
            </label>
        </fieldset>

        <fieldset class="hrs-step">
            <legend class="hrs-step__title"><span class="hrs-step__num" aria-hidden="true">2</span>Your piece</legend>

            <fieldset class="hrp-field hrs-types" id="hrs-type" tabindex="-1" aria-describedby="hrs-type-error">
                <legend class="hrp-label">Type</legend>
                <div class="hrs-types__grid">
                    <?php foreach ( array( 'article' => array( 'Article', 'Reporting or analysis' ), 'oped' => array( 'Op-ed', 'An argued opinion' ) ) as $hrs_type => [ $hrs_name, $hrs_desc ] ) : ?>
                        <label class="hrs-type">
                            <input type="radio" name="hrs-type" value="<?php echo esc_attr( $hrs_type ); ?>">
                            <span class="hrs-type__body">
                                <span class="hrs-type__name"><?php echo esc_html( $hrs_name ); ?></span>
                                <span class="hrs-type__meta"><?php echo esc_html( $hrs_desc . ' · ' . $hrs_range( $hrs_type ) ); ?></span>
                            </span>
                            <?php echo icon( 'check', '', 'hrs-type__tick' ); // phpcs:ignore -- static SVG ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php $hrs_error( 'hrs-type' ); ?>
            </fieldset>

            <div class="hrp-field hra-field">
                <label class="hrp-label" for="hrs-title">Headline</label>
                <input class="hrp-input" type="text" id="hrs-title" maxlength="160" aria-describedby="hrs-title-count" data-hrs-required="Enter a headline." data-hrs-count="160">
                <p class="hrs-counter" id="hrs-title-count" data-hrs-counter="hrs-title"></p>
                <?php $hrs_error( 'hrs-title' ); ?>
            </div>

            <div class="hrp-field hra-field">
                <label class="hrp-label" for="hrs-category">Section</label>
                <select class="hrp-input hrp-select" id="hrs-category" data-hrs-required="Choose a section.">
                    <option value="">Choose a section</option>
                    <?php foreach ( $categories as $hrs_id => $hrs_cat ) : ?>
                        <option value="<?php echo (int) $hrs_id; ?>"><?php echo esc_html( $hrs_cat ); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php $hrs_error( 'hrs-category' ); ?>
            </div>

            <div class="hrp-field hra-field">
                <label class="hrp-label" for="hrs-standfirst">Standfirst <span class="hra-optional">(optional)</span></label>
                <p class="hrp-hint" id="hrs-standfirst-hint">One or two sentences that sum up your piece.</p>
                <textarea class="hrp-input hrp-textarea hrs-textarea--short" id="hrs-standfirst" maxlength="400" rows="3" aria-describedby="hrs-standfirst-hint hrs-standfirst-count" data-hrs-count="400"></textarea>
                <p class="hrs-counter" id="hrs-standfirst-count" data-hrs-counter="hrs-standfirst"></p>
                <?php $hrs_error( 'hrs-standfirst' ); ?>
            </div>

            <div class="hrp-field hra-field">
                <label class="hrp-label" for="hrs-body">Your article</label>
                <p class="hrp-hint" id="hrs-body-hint">Paste or type the full text. Leave a blank line between paragraphs.</p>
                <textarea class="hrp-input hrp-textarea hrs-body" id="hrs-body" rows="18" maxlength="100000" aria-describedby="hrs-body-hint hrs-body-count" data-hrs-required="Write or paste your article."></textarea>
                <p class="hrs-counter hrs-counter--words" id="hrs-body-count" data-hrs-words>Choose Article or Op-ed to see the word limit.</p>
                <?php $hrs_error( 'hrs-body' ); ?>
            </div>
        </fieldset>

        <fieldset class="hrs-step">
            <legend class="hrs-step__title"><span class="hrs-step__num" aria-hidden="true">3</span>Sources and disclosures</legend>

            <div class="hrp-field hra-field">
                <label class="hrp-label" for="hrs-references">References <span class="hra-optional">(optional)</span></label>
                <p class="hrp-hint" id="hrs-references-hint">Links or citations for the figures you use, one per line.</p>
                <textarea class="hrp-input hrp-textarea hrs-textarea--short" id="hrs-references" maxlength="4000" rows="4" aria-describedby="hrs-references-hint"></textarea>
                <?php $hrs_error( 'hrs-references' ); ?>
            </div>

            <div class="hrp-field hra-field">
                <label class="hrp-label" for="hrs-coi">Conflicts of interest <span class="hra-optional">(optional)</span></label>
                <p class="hrp-hint" id="hrs-coi-hint">Your employer, clients, holdings or advocacy connected to the topic. Write "None" if there are none.</p>
                <textarea class="hrp-input hrp-textarea hrs-textarea--short" id="hrs-coi" maxlength="1000" rows="3" aria-describedby="hrs-coi-hint"></textarea>
                <?php $hrs_error( 'hrs-coi' ); ?>
            </div>

            <div class="hrp-field hra-field">
                <span class="hrp-label" id="hrs-attachment-label">Attachment <span class="hra-optional">(optional)</span></span>
                <p class="hrp-hint" id="hrs-attachment-hint">Charts, tables or your original file. Word (.docx), PDF, OpenDocument (.odt), RTF or plain text, up to 8 MB. It is encrypted with everything else.</p>
                <div class="hrs-attach">
                    <input class="hrs-file" type="file" id="hrs-attachment" accept=".docx,.pdf,.odt,.rtf,.txt" aria-labelledby="hrs-attachment-label" aria-describedby="hrs-attachment-hint">
                    <label class="hrp-btn hrp-btn--ghost hrs-file__btn" for="hrs-attachment"><?php echo icon( 'upload' ); // phpcs:ignore -- static SVG ?><span>Choose file</span></label>
                    <p class="hrs-attach__name" data-hrs-attach-name hidden></p>
                    <button type="button" class="hrp-btn hrp-btn--link hrs-attach__remove" data-hrs-attach-remove hidden>Remove</button>
                </div>
                <?php $hrs_error( 'hrs-attachment' ); ?>
            </div>
        </fieldset>

        <fieldset class="hrs-step">
            <legend class="hrs-step__title"><span class="hrs-step__num" aria-hidden="true">4</span>Declarations</legend>
            <?php
            foreach ( array(
                'original' => 'This piece is my own original work and has not been published anywhere else, in print or online.',
                'rights'   => 'I have the right to publish everything in it, including any quotes, data and images I supplied.',
                'editing'  => 'I accept that Headline Recorder may edit it for length, clarity, accuracy and style before publishing.',
            ) as $hrs_key => $hrs_text ) :
                ?>
                <div class="hrp-field hra-field">
                    <label class="hrp-check hra-check">
                        <input type="checkbox" id="hrs-decl-<?php echo esc_attr( $hrs_key ); ?>" data-hrs-required="Tick this box to confirm.">
                        <span><?php echo esc_html( $hrs_text ); ?></span>
                    </label>
                    <?php $hrs_error( 'hrs-decl-' . $hrs_key ); ?>
                </div>
            <?php endforeach; ?>
        </fieldset>

        <div class="hrs-seal">
            <span class="hrs-seal__icon" aria-hidden="true"><?php echo icon( 'lock' ); // phpcs:ignore -- static SVG ?></span>
            <div>
                <p class="hrs-seal__title">Encrypted before it leaves your device</p>
                <p>Your article is encrypted in your browser with post-quantum ML-KEM-1024 + AES-256-GCM before it leaves your device. Only our editors' offline key can open it. Your title, type and category are stored unencrypted so editors can triage.</p>
                <p class="hrs-seal__fine">So are your contact email and the word count. Editors' key ID <code class="hrs-kid"><?php echo esc_html( substr( $key['kid'], 0, 16 ) ); ?>…</code></p>
            </div>
        </div>

        <div class="hrs-submit">
            <button type="submit" class="hrp-btn hrs-submit__btn" disabled data-hrs-submit><?php echo icon( 'lock' ); // phpcs:ignore -- static SVG ?><span data-hrs-submit-label>Encrypt and submit</span></button>
            <p class="hrs-status" role="status" aria-live="polite" data-hrs-status></p>
        </div>
    </form>

    <section class="hrp-card hrp-card--accent hrs-done" tabindex="-1" aria-labelledby="hrs-done-title" hidden data-hrs-done>
        <span class="hrs-done__mark" aria-hidden="true">
            <svg viewBox="0 0 52 52" width="52" height="52"><circle cx="26" cy="26" r="24" fill="none" stroke="currentColor" stroke-width="3"/><path data-hrs-tick d="M15 27l7 7 15-16" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="square"/></svg>
        </span>
        <p class="hrp-eyebrow">Thank you</p>
        <h2 class="hra-h2" id="hrs-done-title" data-hrs-done-title>Submission received</h2>
        <p class="hra-muted">It is with our editors, encrypted. A receipt is on its way to <strong><?php echo esc_html( $user->user_email ); ?></strong>.</p>
        <div class="hra-kv hrs-fp">
            <p class="hra-kv__key">Fingerprint (SHA-256)</p>
            <p class="hra-kv__val"><code class="hrs-fp__hex" data-hrs-fingerprint></code></p>
            <p class="hrs-fp__note" data-hrs-fp-note>This identifies exactly what you sent. Keep it if you ever need to prove it.</p>
        </div>
        <h3 class="hra-h3">What happens next</h3>
        <ol class="hrs-next">
            <li>An editor opens your piece with our offline key and reads it.</li>
            <li>We reply within 7 days, by email and on My submissions.</li>
            <li>If we run it, we send you the edited version before it goes live.</li>
        </ol>
        <div class="hra-actions">
            <a class="hrp-btn" href="<?php echo esc_url( page_url( 'submissions' ) ); ?>"><?php echo icon( 'file-text' ); // phpcs:ignore -- static SVG ?><span>My submissions</span></a>
            <?php if ( page_url( 'reading' ) ) : ?>
                <a class="hrp-btn hrp-btn--ghost" href="<?php echo esc_url( page_url( 'reading' ) ); ?>"><?php echo icon( 'flame' ); // phpcs:ignore -- static SVG ?><span>Your reading streak</span></a>
            <?php endif; ?>
        </div>
    </section>
</div>
