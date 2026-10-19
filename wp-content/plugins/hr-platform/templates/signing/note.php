<?php
/**
 * Signature note after the article body (#hr-signature).
 * Vars: $state, $sig_path, $sig_url, $verify_url, $about_url.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

use function HR\Platform\icon;
use function HR\Platform\Signing\group_hex;

$hr_state  = (string) ( $state['state'] ?? 'unsigned' );
$hr_signed = 'signed' === $hr_state;
$hr_kid    = (string) ( $state['kid'] ?? '' );
$hr_at     = (int) ( $state['at'] ?? 0 );
// A site set to a UTC offset (not a named zone) prints "GMT+0000"; readers understand "UTC".
$hr_tz = $hr_at ? wp_date( 'T', $hr_at ) : '';
if ( 1 === preg_match( '/^(?:GMT)?[+-]/', $hr_tz ) ) {
    $hr_tz = '+00:00' === wp_date( 'P', $hr_at ) ? 'UTC' : 'UTC' . wp_date( 'P', $hr_at );
}
?>
<aside id="hr-signature" class="hrp hr-pqsig-note hr-pqsig-note--<?php echo esc_attr( $hr_state ); ?>" aria-labelledby="hr-signature-title">
    <div class="hr-pqsig-note__icon"><?php echo icon( $hr_signed ? 'shield-check' : 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG ?></div>
    <div class="hr-pqsig-note__body">
        <p class="hrp-eyebrow">Authenticity</p>
        <?php if ( $hr_signed ) : ?>
            <h2 id="hr-signature-title" class="hr-pqsig-note__title">Post-quantum signed</h2>
            <p class="hr-pqsig-note__text">This article is digitally signed with post-quantum cryptography — ML-DSA-87 (NIST FIPS 204) together with Ed448. Changing even one character after publication breaks the signature.</p>
            <dl class="hr-pqsig-meta">
                <?php if ( $hr_at > 0 ) : ?>
                    <div><dt>Signed</dt><dd><time datetime="<?php echo esc_attr( gmdate( 'c', $hr_at ) ); ?>"><?php echo esc_html( wp_date( 'j M Y, H:i', $hr_at ) . ' ' . $hr_tz ); ?></time></dd></div>
                <?php endif; ?>
                <div><dt>Key ID</dt><dd><code title="<?php echo esc_attr( $hr_kid ); ?>"><?php echo esc_html( group_hex( $hr_kid, 4 ) ); ?></code></dd></div>
                <div><dt>Algorithms</dt><dd>ML-DSA-87 + Ed448 · SHA-512</dd></div>
            </dl>
            <div class="hr-pqsig-actions">
                <a class="hrp-btn" href="<?php echo esc_url( $verify_url ); ?>"><?php echo icon( 'shield-check' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span>Verify this article</span></a>
                <a class="hrp-btn hrp-btn--ghost" href="<?php echo esc_url( $sig_url ); ?>" download="<?php echo esc_attr( basename( $sig_path ) ); ?>" type="application/json"><?php echo icon( 'download' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span>Download signature</span></a>
            </div>
        <?php else : ?>
            <h2 id="hr-signature-title" class="hr-pqsig-note__title">Signature pending</h2>
            <p class="hr-pqsig-note__text">
                Every article on Headline Recorder is digitally signed with post-quantum cryptography — ML-DSA-87 (NIST FIPS 204) together with Ed448.
                <?php if ( 'stale' === $hr_state && ! empty( $state['changed'] ) ) : ?>
                    This article was updated after it was last signed. The new version is signed automatically, usually within 15 minutes.
                <?php elseif ( 'stale' === $hr_state ) : ?>
                    The key that signed this article has been retired. A new signature is added automatically, usually within 15 minutes.
                <?php else : ?>
                    This article has not been signed yet. Signatures are added automatically, usually within 15 minutes of publication.
                <?php endif; ?>
            </p>
            <div class="hr-pqsig-actions">
                <?php if ( 'stale' === $hr_state && ! empty( $state['changed'] ) ) : ?>
                    <a class="hrp-btn hrp-btn--ghost" href="<?php echo esc_url( $verify_url ); ?>"><?php echo icon( 'shield-check' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span>Check the previous version</span></a>
                <?php else : ?>
                    <a class="hrp-btn hrp-btn--ghost" href="<?php echo esc_url( $about_url ); ?>"><span>How verification works</span><?php echo icon( 'arrow-right' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</aside>
