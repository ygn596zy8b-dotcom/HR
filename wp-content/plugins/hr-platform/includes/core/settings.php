<?php
/**
 * Settings → Headline Recorder. Modules declare their fields through the `hr_settings_schema` filter:
 *
 *   add_filter( 'hr_settings_schema', function ( $s ) {
 *       $s['podcast'] = array( 'title' => 'Podcast', 'fields' => array(
 *           'podcast_title' => array( 'label' => 'Show name', 'type' => 'text', 'default' => 'Podcast' ),
 *       ) );
 *       return $s;
 *   } );
 *
 * Types: text, textarea, url, email, number, checkbox, select (with 'options'), json, image (attachment ID).
 * Optional per field: 'max_length' (characters; defaults below), 'sanitize' (callable run last), 'validate' (json).
 * Secrets never go here: they are wp-config.php constants.
 */

namespace HR\Platform;

if ( ! defined( 'ABSPATH' ) ) exit;

function settings_schema(): array {
    return (array) apply_filters( 'hr_settings_schema', array() );
}

/** Setting value with the schema default as fallback. */
function setting( string $key ) {
    foreach ( settings_schema() as $section ) {
        if ( isset( $section['fields'][ $key ] ) ) {
            return option( $key, $section['fields'][ $key ]['default'] ?? null );
        }
    }
    return option( $key );
}

add_action( 'admin_menu', function () {
    add_options_page( 'Headline Recorder', 'Headline Recorder', 'manage_options', 'hr-platform', __NAMESPACE__ . '\\settings_page' );
} );

add_action( 'admin_init', function () {
    register_setting( 'hr_platform', 'hr_platform_settings', array(
        'type'              => 'array',
        'sanitize_callback' => __NAMESPACE__ . '\\sanitize_settings',
        'show_in_rest'      => false,
    ) );
} );

/** add_settings_error() lives in wp-admin; the sanitizer also runs for update_option() calls from cron or WP-CLI. */
function settings_error( string $key, string $message ): void {
    if ( function_exists( 'add_settings_error' ) ) {
        add_settings_error( 'hr_platform_settings', $key, $message );
    } else {
        error_log( 'hr-platform settings: ' . $message );
    }
}

/** Default length caps per field type, in characters (json: bytes). */
const SETTING_MAX_LENGTH = array( 'text' => 500, 'textarea' => 10000, 'url' => 2048, 'email' => 254, 'json' => 262144 );

// options.php has already unslashed the posted values; unslashing again would eat backslashes (JSON escapes).
function sanitize_settings( $input ): array {
    $input = is_array( $input ) ? $input : array();
    $old   = (array) get_option( 'hr_platform_settings', array() );
    $out   = $old;
    foreach ( settings_schema() as $section ) {
        foreach ( (array) ( $section['fields'] ?? array() ) as $key => $field ) {
            $type = $field['type'] ?? 'text';
            $raw  = $input[ $key ] ?? null;
            if ( 'checkbox' === $type ) {
                $out[ $key ] = empty( $raw ) ? 0 : 1;
                continue;
            }
            if ( null === $raw ) {
                continue;
            }
            $raw = is_string( $raw ) || is_int( $raw ) ? trim( (string) $raw ) : '';
            $max = (int) ( $field['max_length'] ?? SETTING_MAX_LENGTH[ $type ] ?? SETTING_MAX_LENGTH['text'] );
            if ( 'json' === $type && strlen( $raw ) > $max ) {
                settings_error( $key, sprintf( '%s: too long. The previous value was kept.', $field['label'] ?? $key ) );
                continue;
            }
            if ( 'json' !== $type && mb_strlen( $raw ) > $max ) {
                $raw = mb_substr( $raw, 0, $max );
            }
            switch ( $type ) {
                case 'textarea':
                    $out[ $key ] = sanitize_textarea_field( $raw );
                    break;
                case 'url':
                    $out[ $key ] = esc_url_raw( $raw, array( 'https', 'http' ) );
                    break;
                case 'email':
                    $out[ $key ] = sanitize_email( $raw );
                    break;
                case 'number':
                    $out[ $key ] = '' === $raw ? '' : (int) $raw;
                    break;
                case 'image':
                    $out[ $key ] = '' === $raw ? '' : absint( $raw );
                    break;
                case 'select':
                    $out[ $key ] = array_key_exists( $raw, (array) ( $field['options'] ?? array() ) ) ? $raw : ( $field['default'] ?? '' );
                    break;
                case 'json':
                    if ( '' === $raw ) {
                        $out[ $key ] = '';
                        break;
                    }
                    $decoded = json_decode( $raw, true );
                    $valid   = is_array( $decoded ) && ( ! isset( $field['validate'] ) || true === call_user_func( $field['validate'], $decoded ) );
                    if ( $valid ) {
                        $out[ $key ] = wp_json_encode( $decoded, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
                    } else {
                        settings_error( $key, sprintf( '%s: not valid. The previous value was kept.', $field['label'] ?? $key ) );
                    }
                    break;
                default:
                    $out[ $key ] = sanitize_text_field( $raw );
            }
            if ( isset( $field['sanitize'] ) && is_callable( $field['sanitize'] ) ) {
                $out[ $key ] = call_user_func( $field['sanitize'], $out[ $key ] );
            }
        }
    }
    return $out;
}

function settings_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    wp_enqueue_media();
    $values = (array) get_option( 'hr_platform_settings', array() );
    ?>
    <div class="wrap hr-settings">
        <h1>Headline Recorder</h1>
        <?php settings_errors( 'hr_platform_settings' ); ?>
        <form method="post" action="options.php">
            <?php settings_fields( 'hr_platform' ); ?>
            <?php foreach ( settings_schema() as $id => $section ) : ?>
                <h2 id="<?php echo esc_attr( 'hr-' . $id ); ?>"><?php echo esc_html( $section['title'] ?? $id ); ?></h2>
                <?php if ( ! empty( $section['description'] ) ) : ?>
                    <p><?php echo wp_kses_post( $section['description'] ); ?></p>
                <?php endif; ?>
                <table class="form-table" role="presentation">
                    <?php foreach ( (array) ( $section['fields'] ?? array() ) as $key => $field ) :
                        $type  = $field['type'] ?? 'text';
                        $name  = 'hr_platform_settings[' . $key . ']';
                        $value = $values[ $key ] ?? ( $field['default'] ?? '' );
                        $fid   = 'hr-field-' . $key;
                        ?>
                        <tr>
                            <th scope="row"><label for="<?php echo esc_attr( $fid ); ?>"><?php echo esc_html( $field['label'] ?? $key ); ?></label></th>
                            <td>
                                <?php if ( 'textarea' === $type || 'json' === $type ) : ?>
                                    <textarea id="<?php echo esc_attr( $fid ); ?>" name="<?php echo esc_attr( $name ); ?>" rows="<?php echo 'json' === $type ? 8 : 4; ?>" class="large-text<?php echo 'json' === $type ? ' code' : ''; ?>" spellcheck="false"><?php echo esc_textarea( (string) $value ); ?></textarea>
                                <?php elseif ( 'checkbox' === $type ) : ?>
                                    <input id="<?php echo esc_attr( $fid ); ?>" type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( ! empty( $value ) ); ?>>
                                <?php elseif ( 'select' === $type ) : ?>
                                    <select id="<?php echo esc_attr( $fid ); ?>" name="<?php echo esc_attr( $name ); ?>">
                                        <?php foreach ( (array) $field['options'] as $opt => $label ) : ?>
                                            <option value="<?php echo esc_attr( $opt ); ?>" <?php selected( (string) $value, (string) $opt ); ?>><?php echo esc_html( $label ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php elseif ( 'image' === $type ) : ?>
                                    <input id="<?php echo esc_attr( $fid ); ?>" type="number" min="0" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) $value ); ?>" class="small-text hr-image-id">
                                    <button type="button" class="button hr-pick-image" data-target="<?php echo esc_attr( $fid ); ?>">Choose image</button>
                                    <span class="hr-image-preview"><?php echo $value ? wp_get_attachment_image( (int) $value, array( 64, 64 ) ) : ''; ?></span>
                                <?php else : ?>
                                    <input id="<?php echo esc_attr( $fid ); ?>" type="<?php echo esc_attr( in_array( $type, array( 'url', 'email', 'number' ), true ) ? $type : 'text' ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) $value ); ?>" class="regular-text"<?php echo 'number' === $type ? '' : ' maxlength="' . (int) ( $field['max_length'] ?? SETTING_MAX_LENGTH[ $type ] ?? SETTING_MAX_LENGTH['text'] ) . '"'; ?>>
                                <?php endif; ?>
                                <?php if ( ! empty( $field['description'] ) ) : ?>
                                    <p class="description"><?php echo wp_kses_post( $field['description'] ); ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <?php do_action( 'hr_settings_section_after', $id ); ?>
            <?php endforeach; ?>
            <?php submit_button(); ?>
        </form>
        <?php do_action( 'hr_settings_page_after' ); ?>
    </div>
    <script>
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.hr-pick-image');
        if (!btn || !window.wp || !wp.media) return;
        var frame = wp.media({ title: 'Choose image', library: { type: 'image' }, multiple: false });
        frame.on('select', function () {
            var att = frame.state().get('selection').first().toJSON();
            var input = document.getElementById(btn.dataset.target);
            input.value = att.id;
            var preview = input.parentNode.querySelector('.hr-image-preview');
            var src = att.sizes && (att.sizes.thumbnail || att.sizes.full) ? (att.sizes.thumbnail || att.sizes.full).url : att.url;
            if (preview && src) {
                var img = document.createElement('img');
                img.src = src; img.alt = ''; img.width = 64; img.height = 64; img.style.objectFit = 'contain';
                preview.replaceChildren(img);
            }
        });
        frame.open();
    });
    </script>
    <?php
}
