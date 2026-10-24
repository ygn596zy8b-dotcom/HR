<?php
/**
 * Episode editing: one classic meta box (shown by the block editor too) for audio, duration, numbering,
 * related articles and the AI flag; plus the "Generate today's briefing draft" button and review notice.
 */

namespace HR\Platform\Podcast;

use function HR\Platform\same_origin_request;
use function HR\Platform\rate_limit;

if ( ! defined( 'ABSPATH' ) ) exit;

const NONCE = 'hr_podcast_episode';

add_action( 'add_meta_boxes_' . CPT, function () {
    add_meta_box( 'hr-episode', 'Episode audio and details', __NAMESPACE__ . '\\meta_box', CPT, 'normal', 'high' );
} );

function meta_box( \WP_Post $post ): void {
    $audio_id = (int) get_post_meta( $post->ID, '_hr_audio_id', true );
    $file     = $audio_id ? wp_basename( (string) get_attached_file( $audio_id ) ) : '';
    $related  = implode( ', ', array_map( 'intval', (array) get_post_meta( $post->ID, '_hr_related_posts', true ) ) );
    $num      = static fn( $key ) => (int) get_post_meta( $post->ID, $key, true ) ?: '';
    wp_nonce_field( NONCE, NONCE . '_nonce' );
    ?>
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row"><label for="hr-audio-id">Audio file</label></th>
            <td>
                <input type="hidden" id="hr-audio-id" name="hr_audio_id" value="<?php echo esc_attr( $audio_id ?: '' ); ?>">
                <button type="button" class="button" id="hr-audio-pick">Choose audio</button>
                <button type="button" class="button-link" id="hr-audio-clear"<?php echo $audio_id ? '' : ' hidden'; ?>>Remove</button>
                <span id="hr-audio-name" class="description"><?php echo esc_html( $file ); ?></span>
                <p class="description">An audio file from the Media Library (MP3 or M4A). Without audio, readers can listen with their device's voice.</p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="hr-audio-url">…or external audio URL</label></th>
            <td>
                <input type="url" id="hr-audio-url" name="hr_audio_url" class="large-text" maxlength="2048" placeholder="https://" value="<?php echo esc_attr( (string) get_post_meta( $post->ID, '_hr_audio_url', true ) ); ?>">
                <p class="description">HTTPS only. Used when no Media Library file is chosen.</p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="hr-duration">Duration (seconds)</label></th>
            <td><input type="number" min="0" max="86400" id="hr-duration" name="hr_duration" class="small-text" value="<?php echo esc_attr( (string) $num( '_hr_duration' ) ); ?>"> <span class="description">Filled in from the audio file when left empty.</span></td>
        </tr>
        <tr>
            <th scope="row"><label for="hr-episode-number">Episode / season</label></th>
            <td>
                <input type="number" min="0" max="99999" id="hr-episode-number" name="hr_episode_number" class="small-text" aria-label="Episode number" value="<?php echo esc_attr( (string) $num( '_hr_episode_number' ) ); ?>">
                <label for="hr-season">Season</label>
                <input type="number" min="0" max="999" id="hr-season" name="hr_season" class="small-text" value="<?php echo esc_attr( (string) $num( '_hr_season' ) ); ?>">
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="hr-related">Related article IDs</label></th>
            <td>
                <input type="text" id="hr-related" name="hr_related_posts" class="regular-text" inputmode="numeric" maxlength="300" value="<?php echo esc_attr( $related ); ?>">
                <p class="description">Comma-separated post IDs (up to 20). These articles show a "Listen" button that plays this episode.</p>
            </td>
        </tr>
        <tr>
            <th scope="row">Flags</th>
            <td>
                <label><input type="checkbox" name="hr_ai_generated" value="1" <?php checked( (bool) get_post_meta( $post->ID, '_hr_ai_generated', true ) ); ?>> Script drafted with AI (shows a disclosure badge)</label><br>
                <label><input type="checkbox" name="hr_explicit" value="1" <?php checked( (bool) get_post_meta( $post->ID, '_hr_explicit', true ) ); ?>> Explicit</label>
            </td>
        </tr>
    </table>
    <?php
}

add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) || CPT !== get_current_screen()->post_type ) {
        return;
    }
    wp_enqueue_media();
    wp_add_inline_script( 'media-editor', <<<'JS'
(function () {
    var id = document.getElementById('hr-audio-id'), name = document.getElementById('hr-audio-name'),
        clear = document.getElementById('hr-audio-clear'), dur = document.getElementById('hr-duration'), frame;
    if (!id) return;
    document.getElementById('hr-audio-pick').addEventListener('click', function () {
        frame = frame || wp.media({ title: 'Choose episode audio', library: { type: 'audio' }, button: { text: 'Use this audio' }, multiple: false });
        frame.off('select').on('select', function () {
            var a = frame.state().get('selection').first().toJSON();
            if (!a.mime || a.mime.indexOf('audio/') !== 0) return;
            id.value = a.id; name.textContent = a.filename || a.title || ''; clear.hidden = false;
            if (!dur.value && a.fileLength) {
                dur.value = a.fileLength.split(':').reduce(function (t, p) { return t * 60 + (parseInt(p, 10) || 0); }, 0);
            }
        });
        frame.open();
    });
    clear.addEventListener('click', function () { id.value = ''; name.textContent = ''; clear.hidden = true; });
})();
JS
    );
} );

add_action( 'save_post_' . CPT, function ( $post_id ) {
    if ( ! isset( $_POST[ NONCE . '_nonce' ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ NONCE . '_nonce' ] ) ), NONCE ) ) {
        return;
    }
    if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }
    $in       = wp_unslash( $_POST );
    $audio_id = absint( $in['hr_audio_id'] ?? 0 );
    if ( $audio_id && ( 'attachment' !== get_post_type( $audio_id ) || ! str_starts_with( (string) get_post_mime_type( $audio_id ), 'audio/' ) ) ) {
        $audio_id = 0;
    }
    $duration = min( 86400, absint( $in['hr_duration'] ?? 0 ) );
    if ( ! $duration && $audio_id ) {
        // Local metadata WordPress read on upload; nothing is fetched.
        $duration = (int) ( wp_get_attachment_metadata( $audio_id )['length'] ?? 0 );
    }
    update_post_meta( $post_id, '_hr_audio_id', $audio_id );
    update_post_meta( $post_id, '_hr_audio_url', sanitize_audio_url( (string) ( $in['hr_audio_url'] ?? '' ) ) );
    update_post_meta( $post_id, '_hr_duration', $duration );
    update_post_meta( $post_id, '_hr_episode_number', min( 99999, absint( $in['hr_episode_number'] ?? 0 ) ) );
    update_post_meta( $post_id, '_hr_season', min( 999, absint( $in['hr_season'] ?? 0 ) ) );
    update_post_meta( $post_id, '_hr_related_posts', sanitize_ids( substr( (string) ( $in['hr_related_posts'] ?? '' ), 0, 300 ) ) );
    update_post_meta( $post_id, '_hr_ai_generated', ! empty( $in['hr_ai_generated'] ) );
    update_post_meta( $post_id, '_hr_explicit', ! empty( $in['hr_explicit'] ) );
} );

// ---------------------------------------------------------------------------------------------------------------
// AI briefing: manual button, result notices, review reminder
// ---------------------------------------------------------------------------------------------------------------

add_action( 'admin_notices', function () {
    $screen = get_current_screen();
    if ( ! $screen || ! current_user_can( 'edit_others_posts' ) || ! in_array( $screen->id, array( 'edit-' . CPT, 'dashboard' ), true ) ) {
        return;
    }
    $drafts = get_posts( array( 'post_type' => CPT, 'post_status' => array( 'draft', 'pending' ), 'numberposts' => 3, 'no_found_rows' => true, 'meta_key' => '_hr_ai_generated', 'meta_value' => '1' ) );
    foreach ( $drafts as $draft ) {
        printf(
            '<div class="notice notice-info"><p><strong>AI draft ready for review:</strong> %s. <a href="%s">Review and edit</a>. It stays unpublished until an editor publishes it.</p></div>',
            esc_html( get_the_title( $draft ) ),
            esc_url( (string) get_edit_post_link( $draft->ID ) )
        );
    }

    if ( 'edit-' . CPT !== $screen->id ) {
        return;
    }
    $messages = array(
        'created'     => array( 'success', 'Today’s briefing draft was created. Review it below before publishing.' ),
        'exists'      => array( 'warning', 'Today’s briefing draft already exists.' ),
        'too_few'     => array( 'warning', 'Fewer than two articles were published in the last 24 hours, so no briefing was drafted.' ),
        'unavailable' => array( 'error', 'The AI briefing is not configured.' ),
        'busy'        => array( 'warning', 'Please wait a few minutes before generating again.' ),
        'failed'      => array( 'error', 'The briefing could not be drafted. Details are in the PHP error log.' ),
    );
    $code = isset( $_GET['hr_ai'] ) ? sanitize_key( wp_unslash( $_GET['hr_ai'] ) ) : '';
    if ( isset( $messages[ $code ] ) ) {
        printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $messages[ $code ][0] ), esc_html( $messages[ $code ][1] ) );
    }
    ?>
    <div class="notice notice-info hr-ai-briefing">
        <?php if ( ai_enabled() ) : ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;padding:8px 0">
                <input type="hidden" name="action" value="hr_podcast_generate">
                <?php wp_nonce_field( 'hr_podcast_generate' ); ?>
                <button type="submit" class="button button-primary">Generate today’s briefing draft</button>
                <span class="description">Drafts a Daily Briefing from the last 24 hours of articles (takes up to two minutes). Nothing is published automatically.</span>
            </form>
        <?php else : ?>
            <p>AI daily briefing is off. Turn it on under <a href="<?php echo esc_url( admin_url( 'options-general.php?page=hr-platform#hr-podcast' ) ); ?>">Settings → Headline Recorder → Podcast</a> (needs <code>ANTHROPIC_API_KEY</code> in wp-config.php).</p>
        <?php endif; ?>
    </div>
    <?php
} );

add_action( 'admin_post_hr_podcast_generate', function () {
    if ( ! current_user_can( 'edit_others_posts' ) ) {
        wp_die( 'Sorry, you are not allowed to do that.', 403 );
    }
    check_admin_referer( 'hr_podcast_generate' );
    if ( ! same_origin_request() ) {
        wp_die( 'Sorry, you are not allowed to do that.', 403 );
    }
    $back = admin_url( 'edit.php?post_type=' . CPT );
    if ( ! ai_enabled() ) {
        wp_safe_redirect( add_query_arg( 'hr_ai', 'unavailable', $back ) );
        exit;
    }
    // Each run costs an API call; a handful per hour is plenty for editors.
    if ( ! rate_limit( 'podcast_ai', (string) get_current_user_id(), 5, HOUR_IN_SECONDS ) ) {
        wp_safe_redirect( add_query_arg( 'hr_ai', 'busy', $back ) );
        exit;
    }
    $result = generate_briefing( get_current_user_id() );
    if ( is_wp_error( $result ) ) {
        $code = str_replace( 'hr_ai_', '', $result->get_error_code() );
        wp_safe_redirect( add_query_arg( 'hr_ai', in_array( $code, array( 'exists', 'too_few', 'unavailable', 'busy' ), true ) ? $code : 'failed', $back ) );
        exit;
    }
    wp_safe_redirect( add_query_arg( 'hr_ai', 'created', $back ) );
    exit;
} );

// The block editor's empty body says "Type / to choose a block", which reads as if an episode were text: point to the
// audio box instead (the body is optional show notes).
add_filter( 'write_your_story', static fn( $text, $post ) => CPT === ( $post->post_type ?? '' ) ? 'Optional show notes or script. Upload the episode audio in "Episode audio and details" below.' : $text, 10, 2 );
add_filter( 'enter_title_here', static fn( $text, $post ) => CPT === ( $post->post_type ?? '' ) ? 'Episode title' : $text, 10, 2 );
