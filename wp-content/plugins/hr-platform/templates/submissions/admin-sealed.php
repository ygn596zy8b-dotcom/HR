<?php
/**
 * wp-admin "Sealed submission" box: the unencrypted facts and the panel where an editor opens the submission with
 * submissions.secret.json. submission-open.js does the work in the browser; the key never leaves it.
 *
 * @var WP_Post $post
 * @var array   $facts   label => value (plain text)
 * @var string  $sha256
 * @var int     $draft   linked draft post id (0 when none)
 * @var array   $config  for submission-open.js
 */

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="hrs-admin" data-hrs-open>
    <script type="application/json" id="hrs-open-config"><?php echo wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES ); ?></script>
    <table class="hrs-admin__facts widefat striped" role="presentation">
        <tbody>
            <?php foreach ( $facts as $hrs_label => $hrs_value ) : ?>
                <tr><th scope="row"><?php echo esc_html( $hrs_label ); ?></th><td><?php echo esc_html( (string) $hrs_value ); ?></td></tr>
            <?php endforeach; ?>
            <tr><th scope="row">Fingerprint (SHA-256)</th><td><code class="hrs-admin__fp"><?php echo esc_html( trim( chunk_split( $sha256, 8, ' ' ) ) ); ?></code></td></tr>
            <?php if ( $draft ) : ?>
                <tr><th scope="row">Draft post</th><td><a href="<?php echo esc_url( (string) get_edit_post_link( $draft ) ); ?>"><?php echo esc_html( get_the_title( $draft ) ?: '#' . $draft ); ?></a></td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="hrs-admin__open">
        <h3 class="hrs-admin__h">Open with the editors' key</h3>
        <p>Choose <code>submissions.secret.json</code>. It is read in this browser only, kept in memory, never uploaded, and forgotten when you leave this page.</p>
        <p class="hrs-admin__pick">
            <input type="file" id="hrs-key" class="hrs-admin__file" accept=".json,application/json">
            <label class="button button-primary" for="hrs-key">Choose key file…</label>
            <button type="button" class="button" data-hrs-forget hidden>Close and forget key</button>
        </p>
        <p class="hrs-admin__status" role="status" aria-live="polite" data-hrs-status></p>
    </div>

    <div class="hrs-admin__out" data-hrs-out hidden></div>
</div>
