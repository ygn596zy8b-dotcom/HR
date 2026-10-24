<?php
/**
 * /verify/: reader and partner verification. All checking runs in the browser (assets/src/verify.mjs).
 * Vars: $keyset (normalised keyset or null), $rest (hr/v1 REST root), $keys_url, $feeds (keys of signed feeds).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

use function HR\Platform\icon;
use function HR\Platform\Signing\group_hex;

$hr_keys  = (array) ( $keyset['keys'] ?? array() );
$hr_feeds = array_values( array_filter( (array) ( $feeds ?? array() ), static fn( $k ) => is_string( $k ) && 1 === preg_match( '/^[a-z]+$/', $k ) ) );
?>
<div id="hr-verify" class="hrp hr-verify" data-rest="<?php echo esc_url( $rest ); ?>" data-keys="<?php echo esc_url( $keys_url ); ?>">
    <header class="hr-verify-hero">
        <p class="hrp-eyebrow">Content integrity</p>
        <h1 class="hrp-title">Verify authenticity</h1>
        <p class="hrp-lede"><?php echo $hr_feeds ? 'Every article and every feed we publish is digitally signed.' : 'Every article we publish is digitally signed.'; ?> If even one character is changed after publication, verification fails. Checking happens entirely in your browser; nothing you paste or upload is sent anywhere.</p>
        <ul class="hr-verify-facts" aria-label="How it is signed">
            <li><strong>ML-DSA-87</strong><span>NIST FIPS 204, security category 5: a post-quantum signature that a future quantum computer cannot forge.</span></li>
            <li><strong>Ed448</strong><span>RFC 8032: a classical signature alongside it. Both must be valid, so breaking one algorithm is not enough.</span></li>
            <li><strong>SHA-512</strong><span>Fingerprint of the exact published bytes, covered by both signatures with the date and address.</span></li>
        </ul>
    </header>

    <section class="hr-verify-trust" aria-labelledby="hr-verify-trust-h">
        <h2 id="hr-verify-trust-h" class="hr-verify-sr">Publisher key check</h2>
        <ul class="hr-verify-trust__list">
            <li data-hr-trust="cert" data-state="busy"><span class="hr-verify-trust__dot" aria-hidden="true"></span><span><strong>Key list certified</strong><span data-hr-trust-text>Checking the offline signature on our key list…</span></span></li>
            <li data-hr-trust="dns" data-state="busy"><span class="hr-verify-trust__dot" aria-hidden="true"></span><span><strong>Key published in DNS</strong><span data-hr-trust-text>Looking up <code>_pqsig</code>…</span></span></li>
            <li data-hr-trust="dnssec" data-state="busy"><span class="hr-verify-trust__dot" aria-hidden="true"></span><span><strong>DNSSEC</strong><span data-hr-trust-text>Checking…</span></span></li>
        </ul>
    </section>

    <div class="hr-verify-body">
        <section class="hr-verify-section" aria-labelledby="hr-verify-check-h">
            <p class="hrp-eyebrow">Check something</p>
            <h2 id="hr-verify-check-h" class="hr-verify-h2">Is this exactly what Headline Recorder published?</h2>
            <div class="hr-verify-cards">
                <form id="hr-verify-by-link" class="hrp-card hrp-card--accent hr-verify-card" novalidate>
                    <h3><?php echo icon( 'external-link' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG ?>By link</h3>
                    <div class="hrp-field">
                        <label class="hrp-label" for="hr-verify-sig-url">Signature link on this site</label>
                        <input id="hr-verify-sig-url" class="hrp-input" name="sig" type="text" inputmode="url" autocomplete="off" spellcheck="false" maxlength="2048" placeholder="/signed/1234.sig.json" aria-describedby="hr-verify-sig-url-hint" required>
                        <span id="hr-verify-sig-url-hint" class="hrp-hint">Every article links to its signature under “Verify this article”.<?php if ( $hr_feeds ) : ?> Feeds: <?php echo implode( ', ', array_map( static fn( $k ) => '<code>' . esc_html( '/signed/' . $k . '.xml.sig.json' ) . '</code>', $hr_feeds ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped per item ?><?php endif; ?></span>
                    </div>
                    <button type="submit" class="hrp-btn">Verify</button>
                </form>
                <form id="hr-verify-by-file" class="hrp-card hrp-card--accent hr-verify-card" novalidate>
                    <h3><?php echo icon( 'upload' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG ?>By file</h3>
                    <div class="hrp-field">
                        <label class="hrp-label" for="hr-verify-sig-file">Signature file (.sig.json)</label>
                        <input id="hr-verify-sig-file" class="hr-verify-file" type="file" accept=".json,application/json" required>
                    </div>
                    <div class="hrp-field">
                        <label class="hrp-label" for="hr-verify-content-file">Content it covers <span class="hrp-hint">(only for detached signatures, such as a saved feed)</span></label>
                        <input id="hr-verify-content-file" class="hr-verify-file" type="file">
                    </div>
                    <button type="submit" class="hrp-btn">Verify</button>
                </form>
            </div>

            <?php // Focus moves here after a check and announces the verdict (its label); the full report is not a live region, so a long article is never read out unasked. ?>
            <section id="hr-verify-result" class="hr-verify-result" tabindex="-1" aria-labelledby="hr-verify-verdict" hidden></section>
            <p id="hr-verify-status" class="hr-verify-sr" role="status"></p>

            <section id="hr-verify-compare" class="hrp-card hr-verify-compare" aria-labelledby="hr-verify-compare-h" hidden>
                <h2 id="hr-verify-compare-h" class="hr-verify-h2">Was a copy you received changed?</h2>
                <form id="hr-verify-compare-form" class="hrp-form" novalidate>
                    <div class="hrp-field">
                        <label class="hrp-label" for="hr-verify-received">Paste the text you were sent, quoted or reposted</label>
                        <textarea id="hr-verify-received" class="hrp-textarea" rows="6" maxlength="200000"></textarea>
                    </div>
                    <div><button type="submit" class="hrp-btn hrp-btn--ghost">Compare with the signed original</button></div>
                </form>
                <p id="hr-verify-compare-result" class="hr-verify-compare-result" aria-live="polite"></p>
            </section>
        </section>

        <section class="hr-verify-section" aria-labelledby="hr-verify-keys-h">
            <p class="hrp-eyebrow">Publisher keys</p>
            <h2 id="hr-verify-keys-h" class="hr-verify-h2">Our signing keys</h2>
            <p class="hrp-muted">Our key list is signed with an offline key whose ID we publish in DNS (<code>_pqsig</code> TXT record); the check above confirms both automatically. Partners should pin it. Machine-readable: <a href="<?php echo esc_url( $keys_url ); ?>">/.well-known/pqsig-keys.json</a></p>
            <ul class="hr-verify-keys">
                <?php if ( ! $hr_keys ) : ?>
                    <li>No signing key is published yet.</li>
                <?php endif; ?>
                <?php foreach ( $hr_keys as $hr_key ) : ?>
                    <li class="<?php echo empty( $hr_key['revoked'] ) ? '' : 'is-revoked'; ?>">
                        <code><?php echo esc_html( group_hex( (string) $hr_key['kid'] ) ); ?></code>
                        <span class="hrp-small">
                            <?php
                            $hr_bits = array( (string) $hr_key['alg'] );
                            if ( ! empty( $hr_key['created'] ) ) {
                                $hr_bits[] = 'created ' . substr( (string) $hr_key['created'], 0, 10 );
                            }
                            if ( ! empty( $hr_key['revoked'] ) ) {
                                $hr_bits[] = 'REVOKED ' . substr( (string) $hr_key['revoked'], 0, 10 ) . ': content it signed no longer verifies';
                            }
                            echo esc_html( implode( ' · ', $hr_bits ) );
                            ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="hr-verify-section" aria-labelledby="hr-verify-how-h">
            <p class="hrp-eyebrow">How it works</p>
            <h2 id="hr-verify-how-h" class="hr-verify-h2">What a signature proves</h2>
            <ol class="hr-verify-how">
                <li>When an article is published or edited, our publishing system signs the exact stored article (address, headline, byline, dates and text) at that moment, with a key kept encrypted and separate from the website files.</li>
                <li>The signature file holds a SHA-512 fingerprint of those bytes, the time and the key ID, signed by <strong>both</strong> ML-DSA-87 and Ed448. Both must verify.</li>
                <li>This page checks both signatures with our published keys, then compares the signed version with the article as it is on the site right now.</li>
            </ol>
        </section>

        <div class="hr-verify-cli">
            Prefer the command line?
            <code>pq verify article.sig.json --keys pqsig-keys.json --pin &lt;key-id&gt;</code>
        </div>
    </div>
</div>
