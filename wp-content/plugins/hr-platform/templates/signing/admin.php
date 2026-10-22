<?php
/**
 * Settings > Content signing. Variables from includes/signing/admin.php admin_screen(). Nothing here is secret
 * except the suggested HR_SIGNING_KEK line, which is freshly random on every view and stored nowhere.
 *
 * @var array       $summary
 * @var bool|null   $self_test
 * @var array|null  $notice
 * @var array|null  $keyset
 * @var string      $kek_kind
 * @var bool        $kek_valid
 * @var string      $kek_line
 * @var bool        $aes
 * @var bool        $totp
 * @var string      $post_url
 * @var string      $ajax_url
 * @var string      $nonce
 * @var string      $settings
 * @var string      $dns_host
 */

namespace HR\Platform\Signing;

if ( ! defined( 'ABSPATH' ) ) exit;

$hr_key     = $summary['key'];
$hr_run     = $summary['run'];
$hr_pct     = $summary['public'] > 0 ? (int) floor( 100 * $summary['signed'] / $summary['public'] ) : 100;
$hr_sources = array( 'cron' => 'Automatic run', 'backlog' => 'Sign all pending now', 'cli' => 'WP-CLI' );
$hr_when    = static fn( int $ts ) => sprintf( '%s (%s ago)', wp_date( 'j M Y, H:i', $ts ), human_time_diff( $ts ) );

/** Password (and two-step code) fields every key action asks for. */
$hr_reauth = static function ( string $op ) use ( $totp ) {
    ?>
    <div class="hr-sig-reauth">
        <p class="hr-sig-reauth__lead">Confirm it is you.</p>
        <p>
            <label for="<?php echo esc_attr( 'hr-sig-pw-' . $op ); ?>">Your WordPress password</label>
            <input type="password" id="<?php echo esc_attr( 'hr-sig-pw-' . $op ); ?>" name="hr_pqsig_password" autocomplete="current-password" required class="regular-text">
        </p>
        <?php if ( $totp ) : ?>
            <p>
                <label for="<?php echo esc_attr( 'hr-sig-code-' . $op ); ?>">Two-step code</label>
                <input type="text" id="<?php echo esc_attr( 'hr-sig-code-' . $op ); ?>" name="hr_pqsig_code" autocomplete="one-time-code" inputmode="numeric" required class="regular-text hr-sig-code" aria-describedby="<?php echo esc_attr( 'hr-sig-code-help-' . $op ); ?>">
                <span class="description" id="<?php echo esc_attr( 'hr-sig-code-help-' . $op ); ?>">From your authenticator app, or one of your recovery codes.</span>
            </p>
        <?php endif; ?>
    </div>
    <?php
};
$hr_form_open = static function ( string $op, bool $multipart = false ) use ( $post_url ) {
    printf( '<form method="post" action="%s" class="hr-sig-form"%s autocomplete="off">', esc_url( $post_url ), $multipart ? ' enctype="multipart/form-data"' : '' );
    echo '<input type="hidden" name="action" value="hr_pqsig_key"><input type="hidden" name="op" value="' . esc_attr( $op ) . '">';
    wp_nonce_field( 'hr_pqsig_key_' . $op, 'hr_pqsig_nonce' );
};
?>
<div class="wrap hr-sig">
    <h1>Content signing</h1>
    <p class="hr-sig-lead">
        Every published article and the site and podcast feeds are signed on this server with post-quantum
        <strong>ML-DSA-87 + Ed448</strong> the moment they are published or edited. Readers and partners check them at
        <a href="<?php echo esc_url( home_url( '/verify/' ) ); ?>">/verify/</a>. How it works and what to do if something goes wrong:
        <code>docs/SIGNING.md</code>.
    </p>

    <?php if ( $notice ) : ?>
        <div class="notice notice-<?php echo 'success' === $notice['type'] ? 'success' : 'error'; ?> is-dismissible" role="<?php echo 'success' === $notice['type'] ? 'status' : 'alert'; ?>"><p><?php echo esc_html( (string) $notice['message'] ); ?></p></div>
    <?php endif; ?>
    <?php if ( ! $summary['ready'] ) : ?>
        <div class="notice notice-error inline"><p>This server&rsquo;s PHP cannot run the signer: it needs 64-bit PHP 8.2 or newer. Use the offline signer until the hosting is upgraded.</p></div>
    <?php elseif ( false === $self_test ) : ?>
        <div class="notice notice-error inline"><p>The signer&rsquo;s self-test failed on this PHP build, so nothing is signed here. Use the offline signer and report the PHP version: <?php echo esc_html( PHP_VERSION ); ?>.</p></div>
    <?php endif; ?>

    <section class="hr-sig-card" aria-labelledby="hr-sig-status-h">
        <h2 id="hr-sig-status-h">Status</h2>
        <dl class="hr-sig-stats">
            <div class="hr-sig-stat">
                <dt>Automatic signing</dt>
                <dd>
                    <?php if ( ! $summary['enabled'] ) : ?>
                        <span class="hr-sig-badge hr-sig-badge--off">Off</span>
                    <?php elseif ( $summary['usable'] ) : ?>
                        <span class="hr-sig-badge hr-sig-badge--ok">On</span>
                    <?php else : ?>
                        <span class="hr-sig-badge hr-sig-badge--warn">Waiting for a key</span>
                    <?php endif; ?>
                    <a class="hr-sig-small" href="<?php echo esc_url( $settings ); ?>">Change</a>
                </dd>
            </div>
            <div class="hr-sig-stat">
                <dt>Articles signed</dt>
                <dd><span class="hr-sig-num" data-hr-sig="signed"><?php echo esc_html( number_format_i18n( $summary['signed'] ) ); ?></span> <span class="hr-sig-of">of <span data-hr-sig="public"><?php echo esc_html( number_format_i18n( $summary['public'] ) ); ?></span></span></dd>
            </div>
            <div class="hr-sig-stat">
                <dt>Pending</dt>
                <dd><span class="hr-sig-num" data-hr-sig="pending"><?php echo esc_html( number_format_i18n( $summary['pending'] ) ); ?></span></dd>
            </div>
            <div class="hr-sig-stat">
                <dt>Last run</dt>
                <dd>
                    <?php if ( $hr_run ) : ?>
                        <?php echo esc_html( $hr_when( (int) $hr_run['at'] ) ); ?><br>
                        <span class="hr-sig-small"><?php echo esc_html( sprintf( '%s: %s, %s signed', $hr_sources[ $hr_run['source'] ] ?? $hr_run['source'], empty( $hr_run['ok'] ) ? 'problems' : 'OK', number_format_i18n( (int) $hr_run['signed'] ) ) ); ?></span>
                    <?php else : ?>
                        No run yet
                    <?php endif; ?>
                </dd>
            </div>
        </dl>
        <div class="hr-sig-meter" aria-hidden="true"><span style="width:<?php echo esc_attr( (string) $hr_pct ); ?>%"></span></div>

        <?php
        $hr_problems = array();
        if ( $summary['enabled'] && ! $summary['usable'] ) {
            $hr_problems[] = $summary['problem'];
        }
        foreach ( (array) ( $hr_run['errors'] ?? array() ) as $hr_error ) {
            $hr_problems[] = $hr_error;
        }
        if ( $summary['enabled'] && $summary['cron_off'] ) {
            $hr_problems[] = 'WP-Cron is switched off in wp-config.php (DISABLE_WP_CRON). Make sure the host runs wp-cron.php every 5 minutes, or the sweep and retries will not run.';
        }
        $hr_problems = array_values( array_unique( array_filter( $hr_problems ) ) );
        ?>
        <?php if ( $hr_problems ) : ?>
            <div class="hr-sig-problems" role="status">
                <h3>Needs attention</h3>
                <ul><?php foreach ( $hr_problems as $hr_problem ) : ?><li><?php echo esc_html( $hr_problem ); ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>
        <?php if ( $summary['errors'] ) : ?>
            <details class="hr-sig-details">
                <summary>Recent articles that could not be signed (<?php echo esc_html( number_format_i18n( count( $summary['errors'] ) ) ); ?>)</summary>
                <ul>
                    <?php foreach ( array_slice( $summary['errors'], 0, 10, true ) as $hr_id => $hr_error ) : $hr_edit = get_edit_post_link( (int) $hr_id ); ?>
                        <li>
                            <?php if ( $hr_edit ) : ?><a href="<?php echo esc_url( $hr_edit ); ?>"><?php endif; ?><?php echo esc_html( get_the_title( (int) $hr_id ) ?: sprintf( 'Article %d', $hr_id ) ); ?><?php if ( $hr_edit ) : ?></a><?php endif; ?>:
                            <?php echo esc_html( sprintf( '%s (%s ago)', $hr_error['message'], human_time_diff( (int) $hr_error['at'] ) ) ); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </details>
        <?php endif; ?>
    </section>

    <section class="hr-sig-card" aria-labelledby="hr-sig-backlog-h" id="hr-sig-backlog"
        data-ajax="<?php echo esc_url( $ajax_url ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>"
        data-pending="<?php echo esc_attr( (string) $summary['pending'] ); ?>" data-public="<?php echo esc_attr( (string) $summary['public'] ); ?>">
        <h2 id="hr-sig-backlog-h">Sign all pending now</h2>
        <p>Signs every article that is waiting, newest first, in small batches while this page stays open, then signs the feeds. You can leave and come back: nothing is lost if you stop.</p>
        <?php if ( ! $summary['usable'] ) : ?>
            <p class="hr-sig-small"><strong>Install a signing key below first.</strong></p>
        <?php endif; ?>
        <div class="hr-sig-progress" hidden>
            <progress class="hr-sig-progress__bar" max="100" value="0" aria-labelledby="hr-sig-progress-label"></progress>
            <p class="hr-sig-progress__label" id="hr-sig-progress-label" aria-live="polite"></p>
        </div>
        <p class="hr-sig-actions">
            <button type="button" class="button button-primary hr-sig-run" <?php disabled( ! $summary['usable'] || ! $summary['ready'] ); ?>>
                <?php echo esc_html( $summary['pending'] > 0 ? sprintf( 'Sign %s pending %s', number_format_i18n( $summary['pending'] ), 1 === $summary['pending'] ? 'article' : 'articles' ) : 'Check everything and re-sign the feeds' ); ?>
            </button>
            <button type="button" class="button hr-sig-stop" hidden>Stop</button>
        </p>
        <div class="hr-sig-failures" hidden>
            <h3>Could not be signed</h3>
            <ul></ul>
        </div>
    </section>

    <section class="hr-sig-card" aria-labelledby="hr-sig-key-h" id="hr-pqsig-key">
        <h2 id="hr-sig-key-h">Signing key</h2>
        <?php if ( ! empty( $hr_key['installed'] ) ) : ?>
            <table class="hr-sig-table">
                <tbody>
                    <tr><th scope="row">Key ID</th><td><code class="hr-sig-kid"><?php echo esc_html( group_hex( $hr_key['kid'] ) ); ?></code></td></tr>
                    <tr><th scope="row">Source</th><td><?php echo esc_html( 'generated' === $hr_key['source'] ? 'Generated on this server (a dedicated server key)' : 'Imported (your existing key)' ); ?></td></tr>
                    <tr><th scope="row">Stored</th><td>
                        <?php if ( ! $hr_key['exists'] ) : ?>
                            <span class="hr-sig-badge hr-sig-badge--warn">File missing</span> Import or generate the key again.
                        <?php elseif ( $hr_key['outside'] ) : ?>
                            <span class="hr-sig-badge hr-sig-badge--ok">Outside the web root</span>
                            <?php echo esc_html( 'private' === $hr_key['location'] ? 'in HR_PRIVATE_DIR' : 'next to the site folder (hr-private)' ); ?>, file mode <?php echo esc_html( sprintf( '%04o', (int) $hr_key['file_mode'] ) ); ?>.
                        <?php else : ?>
                            <span class="hr-sig-badge hr-sig-badge--warn">Inside the web root</span>
                            In a randomly named folder with deny-all rules. Safer: set <code>HR_PRIVATE_DIR</code> in wp-config.php to a folder outside public_html, then import or generate the key again.
                        <?php endif; ?>
                    </td></tr>
                    <tr><th scope="row">Encryption</th><td>
                        AES-256-GCM with
                        <?php echo esc_html( 'constant' === $hr_key['kek'] ? 'the dedicated HR_SIGNING_KEK constant in wp-config.php.' : 'a key derived from the WordPress salts in wp-config.php.' ); ?>
                    </td></tr>
                    <tr><th scope="row">Usable</th><td>
                        <?php if ( $summary['usable'] ) : ?>
                            <span class="hr-sig-badge hr-sig-badge--ok">Yes</span> It matches the published keyset and is not revoked.
                        <?php else : ?>
                            <span class="hr-sig-badge hr-sig-badge--warn">No</span> <?php echo esc_html( $summary['problem'] ); ?>
                        <?php endif; ?>
                    </td></tr>
                </tbody>
            </table>
            <div class="hr-sig-dns">
                <p><strong>DNS pin.</strong> Partners pin the key ID through a DNSSEC-signed TXT record. Make sure this record exists (one per active key):</p>
                <p><code class="hr-sig-code-block"><?php echo esc_html( $dns_host . '. TXT "v=pqsig1; alg=' . ALG . '; kid=' . $hr_key['kid'] . '"' ); ?></code></p>
            </div>
        <?php else : ?>
            <p>No signing key is installed on this server yet, so new articles wait in the queue. Choose one of the two options below.</p>
        <?php endif; ?>

        <?php if ( ! $aes ) : ?>
            <div class="notice notice-error inline"><p>This server&rsquo;s PHP has no AES-256-GCM (OpenSSL), so a key cannot be stored safely here.</p></div>
        <?php endif; ?>

        <div class="hr-sig-options">
            <div class="hr-sig-option hr-sig-option--recommended">
                <h3>Generate a server signing key <span class="hr-sig-badge hr-sig-badge--ok">Recommended</span></h3>
                <p>The site makes its own key and adds its public half to the keyset for you. Your existing offline key stays offline. If this server were ever broken into, you would revoke only the server key; everything signed with the offline key stays trusted.</p>
                <?php $hr_form_open( 'generate' ); ?>
                    <?php $hr_reauth( 'generate' ); ?>
                    <?php if ( ! empty( $hr_key['installed'] ) ) : ?>
                        <p class="hr-sig-small">This replaces the installed key. Articles it signed keep their valid signatures.</p>
                    <?php endif; ?>
                    <p><button type="submit" class="button button-primary" <?php disabled( ! $aes || ! $summary['ready'] ); ?>><?php echo esc_html( empty( $hr_key['installed'] ) ? 'Generate key' : 'Generate a new key' ); ?></button></p>
                </form>
            </div>

            <div class="hr-sig-option">
                <h3>Import your existing key</h3>
                <p>Use the key that signed the earlier articles (<code>signing.secret.json</code>). It must already be in the published keyset. The file is checked, encrypted and stored outside the web root; it is never shown again.</p>
                <?php $hr_form_open( 'import', true ); ?>
                    <p>
                        <label for="hr-sig-file">signing.secret.json</label>
                        <input type="file" id="hr-sig-file" name="hr_pqsig_file" accept=".json,application/json">
                    </p>
                    <details class="hr-sig-details">
                        <summary>Or paste its contents</summary>
                        <p><label for="hr-sig-paste" class="screen-reader-text">Contents of signing.secret.json</label>
                        <textarea id="hr-sig-paste" name="hr_pqsig_paste" rows="4" class="large-text code" spellcheck="false" autocomplete="off" autocapitalize="off"></textarea></p>
                    </details>
                    <?php $hr_reauth( 'import' ); ?>
                    <p><button type="submit" class="button" <?php disabled( ! $aes || ! $summary['ready'] ); ?>>Import key</button></p>
                </form>
            </div>
        </div>

        <h3>Key encryption</h3>
        <?php if ( 'constant' === $kek_kind && ! $kek_valid ) : ?>
            <div class="notice notice-error inline"><p><code>HR_SIGNING_KEK</code> is in wp-config.php but is not valid: it must be the base64 of 32 random bytes. Replace it with the line below.</p></div>
        <?php endif; ?>
        <?php if ( 'constant' === $kek_kind && $kek_valid ) : ?>
            <p><span class="hr-sig-badge hr-sig-badge--ok">Dedicated key</span> <code>HR_SIGNING_KEK</code> is set in wp-config.php. Keep a copy of that line with your offline backups: without it the stored key cannot be decrypted.</p>
        <?php else : ?>
            <p>
                The key file is encrypted with a key derived from the WordPress security keys (salts) in wp-config.php. If those salts are ever changed (some security plugins do this), the file can no longer be read and the key must be imported or generated again.
                A dedicated key avoids that. Add this line to wp-config.php, above &ldquo;That&rsquo;s all, stop editing!&rdquo;, then come back and press <em>Re-wrap</em>:
            </p>
            <p class="hr-sig-copy">
                <label for="hr-sig-kek" class="screen-reader-text">Line for wp-config.php</label>
                <input type="text" id="hr-sig-kek" class="large-text code" readonly value="<?php echo esc_attr( $kek_line ); ?>">
                <button type="button" class="button hr-sig-copy-btn" data-target="hr-sig-kek">Copy</button>
            </p>
            <p class="hr-sig-small">This line is new each time the page loads and is not saved anywhere until you put it in wp-config.php.</p>
        <?php endif; ?>
        <?php if ( ! empty( $hr_key['can_rewrap'] ) ) : ?>
            <?php $hr_form_open( 'rewrap' ); ?>
                <p>HR_SIGNING_KEK is set, but the installed key is still encrypted with the salts.</p>
                <?php $hr_reauth( 'rewrap' ); ?>
                <p><button type="submit" class="button">Re-wrap with HR_SIGNING_KEK</button></p>
            </form>
        <?php endif; ?>

        <?php if ( ! empty( $hr_key['installed'] ) ) : ?>
            <h3>Remove the key</h3>
            <p>Overwrites the key file and deletes it. Automatic signing stops; signatures already made stay valid. If you think the server was compromised, also mark the key <code>"revoked"</code> in the keyset in <a href="<?php echo esc_url( $settings ); ?>">Settings &gt; Headline Recorder</a>.</p>
            <?php $hr_form_open( 'remove' ); ?>
                <?php $hr_reauth( 'remove' ); ?>
                <p><button type="submit" class="button hr-sig-danger">Remove key from this server</button></p>
            </form>
        <?php endif; ?>
    </section>

    <?php if ( $keyset ) : ?>
        <section class="hr-sig-card" aria-labelledby="hr-sig-keyset-h">
            <h2 id="hr-sig-keyset-h">Published keyset</h2>
            <table class="widefat striped">
                <thead><tr><th scope="col">Key ID</th><th scope="col">Created</th><th scope="col">Status</th></tr></thead>
                <tbody>
                    <?php foreach ( $keyset['keys'] as $hr_entry ) : ?>
                        <tr>
                            <td><code><?php echo esc_html( group_hex( $hr_entry['kid'], 4 ) ); ?></code></td>
                            <td><?php echo esc_html( isset( $hr_entry['created'] ) ? substr( (string) $hr_entry['created'], 0, 10 ) : '' ); ?></td>
                            <td><?php
                                if ( ! empty( $hr_entry['revoked'] ) ) {
                                    echo esc_html( 'Revoked ' . substr( (string) $hr_entry['revoked'], 0, 10 ) );
                                } elseif ( ! empty( $hr_key['installed'] ) && $hr_key['kid'] === $hr_entry['kid'] ) {
                                    echo 'Active, on this server';
                                } else {
                                    echo 'Active (held offline)';
                                }
                            ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="hr-sig-small">Edit or revoke keys in <a href="<?php echo esc_url( $settings ); ?>">Settings &gt; Headline Recorder &gt; Content signing</a>.</p>
        </section>
    <?php endif; ?>
</div>
