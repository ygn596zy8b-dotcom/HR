<?php
// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

// BEGIN ENQUEUE PARENT ACTION
// AUTO GENERATED - Do not modify or remove comment markers above or below:

if ( !function_exists( 'chld_thm_cfg_locale_css' ) ):
    function chld_thm_cfg_locale_css( $uri ){
        if ( empty( $uri ) && is_rtl() && file_exists( get_template_directory() . '/rtl.css' ) )
            $uri = get_template_directory_uri() . '/rtl.css';
        return $uri;
    }
endif;
add_filter( 'locale_stylesheet_uri', 'chld_thm_cfg_locale_css' );
          
if ( !function_exists( 'child_theme_configurator_css' ) ):
    function child_theme_configurator_css() {
        wp_enqueue_style( 'chld_thm_cfg_child', trailingslashit( get_stylesheet_directory_uri() ) . 'style.css', array( 'covernews-icons','bootstrap','covernews-style' ), (string) filemtime( get_stylesheet_directory() . '/style.css' ) );
    }
endif;
add_action( 'wp_enqueue_scripts', 'child_theme_configurator_css', 10 );

// END ENQUEUE PARENT ACTION

// Theme assets, header ticker, calls to action and article schema (MSBJ).
require_once __DIR__ . '/inc/assets.php';
require_once __DIR__ . '/inc/site.php';
require_once __DIR__ . '/inc/schema.php';


// ==============================================================================
// CUSTOM OVERRIDE: Replace "Trending Story" 3rd Column
// ==============================================================================

function covernews_banner_trending_posts() {
    $data = get_headline_recorder_sbp_data();
    
    $indicators = [
        'policy_rate' => 'Official Monetary Stance',
        'inflation'   => 'Macro Target Vector',
        'remittances' => 'Monthly Liquidity Inflow'
    ];
    ?>
    <div class="sbp-ticker-inner-wrap">
        <?php foreach ($indicators as $key => $sublabel): ?>
            <div class="sbp-ticker-item">
                <span class="sbp-ticker-label">
                    <?php echo esc_html( $data[$key]['label'] ); ?>
                </span>
                <div class="sbp-ticker-value-row">
                    <?php echo esc_html( $data[$key]['value'] ); ?>
                    <?php if (isset($data[$key]['unit']) && $data[$key]['unit']) echo '<span class="sbp-ticker-unit">' . esc_html($data[$key]['unit']) . '</span>'; ?>
                </div>
                <span class="sbp-ticker-sublabel">
                    <?php echo esc_html($sublabel); ?>
                </span>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}

/* ==========================================================================
   EXECUTIVE SPOTLIGHT: CUSTOM META FIELDS
   ========================================================================== */

add_action('add_meta_boxes', 'hr_register_exec_spotlight_meta');
function hr_register_exec_spotlight_meta() {
    add_meta_box(
        'hr_exec_spotlight_details',
        'Executive Spotlight Details',
        'hr_exec_spotlight_callback',
        'post',
        'normal',
        'high'
    );
}

function hr_exec_spotlight_callback($post) {
    wp_nonce_field('hr_exec_spotlight_save', 'hr_exec_spotlight_nonce');

    $exec_name = get_post_meta($post->ID, '_hr_exec_name', true);
    $exec_title = get_post_meta($post->ID, '_hr_exec_title', true);

    echo '<div style="display: flex; flex-direction: column; gap: 15px; margin-top: 10px;">';
    
    echo '<div>';
    echo '<label for="hr_exec_name" style="font-weight: 600; display: block; margin-bottom: 5px;">Executive Name</label>';
    echo '<input type="text" id="hr_exec_name" name="hr_exec_name" value="' . esc_attr($exec_name) . '" style="width: 100%; max-width: 400px;" placeholder="e.g., Shadi Saba" />';
    echo '</div>';

    echo '<div>';
    echo '<label for="hr_exec_title" style="font-weight: 600; display: block; margin-bottom: 5px;">Executive Designation/Title</label>';
    echo '<input type="text" id="hr_exec_title" name="hr_exec_title" value="' . esc_attr($exec_title) . '" style="width: 100%; max-width: 400px;" placeholder="e.g., Regional Director, Fortinet" />';
    echo '</div>';

    echo '</div>';
}

add_action('save_post', 'hr_save_exec_spotlight_meta');
function hr_save_exec_spotlight_meta($post_id) {
    if (!isset($_POST['hr_exec_spotlight_nonce']) || !wp_verify_nonce($_POST['hr_exec_spotlight_nonce'], 'hr_exec_spotlight_save')) {
        return;
    }
    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id)) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    if (isset($_POST['hr_exec_name'])) {
        update_post_meta($post_id, '_hr_exec_name', sanitize_text_field($_POST['hr_exec_name']));
    }

    if (isset($_POST['hr_exec_title'])) {
        update_post_meta($post_id, '_hr_exec_title', sanitize_text_field($_POST['hr_exec_title']));
    }
}


add_filter('single_template', function($template) {
    if (in_category('executive-spotlight')) {
        $custom = get_stylesheet_directory() . '/single-executive-spotlight.php';
        if (file_exists($custom)) return $custom;
    }
    return $template;
});

/* ==========================================================================
   OPINION ENGINE: GUEST AUTHORS & ARCHIVES
   ========================================================================== */

// 1. Register Guest Author Archive Taxonomy
add_action( 'init', 'hr_register_guest_author_taxonomy' );
function hr_register_guest_author_taxonomy() {
    register_taxonomy( 'guest_author', 'post', array(
        'label'        => 'Guest Authors',
        'public'       => true,
        'rewrite'      => array( 'slug' => 'opinion-author' ),
        'hierarchical' => false,
        'show_ui'      => true,
        'show_in_rest' => true,
    ) );
}

// 2. Ensure Media Manager Scripts Load for the Avatar Uploader
add_action('admin_enqueue_scripts', function( $hook ) {
    if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
        wp_enqueue_media();
    }
});

// 3. Register the Meta Box
function hr_add_guest_author_meta_box() {
    add_meta_box(
        'hr_guest_author_box',
        'Guest Author Details (Opinion Category)',
        'hr_guest_author_box_html',
        'post',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'hr_add_guest_author_meta_box');

// 4. Build the HTML for the Editor
function hr_guest_author_box_html($post) {
    $guest_name     = get_post_meta($post->ID, 'guest_author_name', true);
    $guest_bio      = get_post_meta($post->ID, 'guest_author_bio', true);
    $guest_avatar   = get_post_meta($post->ID, 'guest_author_avatar', true);
    $guest_linkedin = get_post_meta($post->ID, 'guest_author_linkedin', true);
    $guest_twitter  = get_post_meta($post->ID, 'guest_author_twitter', true);
    $guest_facebook = get_post_meta($post->ID, 'guest_author_facebook', true);
    
    wp_nonce_field('hr_guest_author_save', 'hr_guest_author_nonce');
    ?>
    <p style="color: #666; margin-bottom: 15px;">If this is an Opinion piece by an external writer, enter their details here. This will override the default system author.</p>
    
    <label for="guest_author_name" style="display:block; font-weight: 600; margin-bottom: 5px;">Guest Name:</label>
    <input type="text" id="guest_author_name" name="guest_author_name" value="<?php echo esc_attr($guest_name); ?>" style="width:100%; margin-bottom: 15px; padding: 5px;">

    <label for="guest_author_bio" style="display:block; font-weight: 600; margin-bottom: 5px;">Guest Bio:</label>
    <textarea id="guest_author_bio" name="guest_author_bio" style="width:100%; height: 80px; margin-bottom: 20px; padding: 5px;"><?php echo esc_textarea($guest_bio); ?></textarea>

    <label for="guest_author_avatar" style="display:block; font-weight: 600; margin-bottom: 5px;">Guest Avatar Image:</label>
    <div style="display: flex; gap: 10px; margin-bottom: 25px;">
        <input type="text" id="guest_author_avatar" name="guest_author_avatar" value="<?php echo esc_url($guest_avatar); ?>" style="width: 70%; padding: 5px;" placeholder="Upload or paste image URL...">
        <button type="button" class="button hr-upload-avatar-btn">Upload Image</button>
    </div>

    <script>
    jQuery(document).ready(function($){
        $('.hr-upload-avatar-btn').click(function(e){
            e.preventDefault();
            var custom_uploader = wp.media({
                title: 'Select Guest Avatar',
                library : { type : 'image' },
                button: { text: 'Use this image' },
                multiple: false
            }).on('select', function() {
                var attachment = custom_uploader.state().get('selection').first().toJSON();
                $('#guest_author_avatar').val(attachment.url);
            }).open();
        });
    });
    </script>

    <h4 style="margin: 0 0 10px; border-bottom: 1px solid #ddd; padding-bottom: 5px;">Social Profiles (Optional)</h4>
    <p style="font-size: 11px; color: #666; margin-bottom: 15px;">Paste full URLs (e.g., https://linkedin.com/in/username). Icons will automatically hide if left blank.</p>

    <label for="guest_author_linkedin" style="display:block; font-weight: 600; margin-bottom: 5px;">LinkedIn URL:</label>
    <input type="url" id="guest_author_linkedin" name="guest_author_linkedin" value="<?php echo esc_url($guest_linkedin); ?>" style="width:100%; margin-bottom: 15px; padding: 5px;">

    <label for="guest_author_twitter" style="display:block; font-weight: 600; margin-bottom: 5px;">X (Twitter) URL:</label>
    <input type="url" id="guest_author_twitter" name="guest_author_twitter" value="<?php echo esc_url($guest_twitter); ?>" style="width:100%; margin-bottom: 15px; padding: 5px;">

    <label for="guest_author_facebook" style="display:block; font-weight: 600; margin-bottom: 5px;">Facebook URL:</label>
    <input type="url" id="guest_author_facebook" name="guest_author_facebook" value="<?php echo esc_url($guest_facebook); ?>" style="width:100%; padding: 5px;">
    <?php
}

// 5. Save the Data and Sync to Taxonomy Archive (Architecture Fix)
// Only users who can edit the post save it. Creating a guest author or changing the shared term profile
// (bio, avatar, socials) needs the taxonomy's edit_terms capability; anyone else can only pick an existing one.
function hr_save_guest_author_meta($post_id) {
    if (!isset($_POST['hr_guest_author_nonce']) || !wp_verify_nonce($_POST['hr_guest_author_nonce'], 'hr_guest_author_save')) return;
    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id)) return;
    if (!current_user_can('edit_post', $post_id)) return;

    // A. Save to the Post Meta (Keeps your editor meta box populated)
    $clean = array();
    foreach (array('guest_author_name', 'guest_author_bio', 'guest_author_avatar', 'guest_author_linkedin', 'guest_author_twitter', 'guest_author_facebook') as $field) {
        if (!array_key_exists($field, $_POST)) continue;
        $raw = wp_unslash($_POST[$field]);
        if ('guest_author_name' === $field) {
            $clean[$field] = sanitize_text_field($raw);
        } elseif ('guest_author_bio' === $field) {
            $clean[$field] = sanitize_textarea_field($raw);
        } elseif ('guest_author_avatar' === $field) {
            $clean[$field] = $raw === get_post_meta($post_id, $field, true) ? $raw : hr_guest_avatar_url($raw);
        } else {
            $clean[$field] = esc_url_raw($raw, array('https', 'http'));
        }
        update_post_meta($post_id, $field, $clean[$field]);
    }

    // B. Sync Author Name to the Archive Taxonomy & PUSH Meta to the Term
    if (empty($clean['guest_author_name'])) {
        // If name is cleared, remove the term from the post
        wp_set_object_terms($post_id, array(), 'guest_author');
        return;
    }
    $taxonomy  = get_taxonomy('guest_author');
    $can_terms = $taxonomy && current_user_can($taxonomy->cap->edit_terms);
    $term      = get_term_by('name', $clean['guest_author_name'], 'guest_author');
    if (!$term && !$can_terms) return;

    wp_set_object_terms($post_id, $term ? (int) $term->term_id : $clean['guest_author_name'], 'guest_author', false);
    $term = $term ?: get_term_by('name', $clean['guest_author_name'], 'guest_author');
    if (!$can_terms || !$term || is_wp_error($term)) return;

    foreach (array('guest_author_bio', 'guest_author_avatar', 'guest_author_linkedin', 'guest_author_twitter', 'guest_author_facebook') as $field) {
        if (array_key_exists($field, $clean)) {
            update_term_meta($term->term_id, $field, $clean[$field]);
        }
    }
}
add_action('save_post', 'hr_save_guest_author_meta');

/**
 * Guest avatars must be images from this site's uploads (no third-party tracking pixels), served over the
 * uploads URL's scheme (https in production). Anything else is dropped.
 */
function hr_guest_avatar_url($url) {
    $url  = esc_url_raw(trim((string) $url), array('https', 'http'));
    $base = wp_get_upload_dir()['baseurl'];
    if ('' === $url || wp_parse_url($url, PHP_URL_HOST) !== wp_parse_url($base, PHP_URL_HOST) || !preg_match('#\.(?:jpe?g|png|gif|webp|avif)$#i', (string) wp_parse_url($url, PHP_URL_PATH))) {
        return '';
    }
    return set_url_scheme($url, wp_parse_url($base, PHP_URL_SCHEME));
}

// =========================================================================
// CENTRALIZED SBP MACRO INDICATORS ENGINE
// =========================================================================

function hr_get_central_sbp_data() {

    $defaults = array(

        'policy_rate' => array(
            'value' => '11.50',
            'unit'  => '%',
            'sub'   => 'Official Monetary Stance'
        ),

        'inflation' => array(
            'value' => '11.10',
            'unit'  => '%',
            'sub'   => 'National CPI Inflation'
        ),

        'remittances' => array(
            'value' => '3,631.04',
            'unit'  => 'USD (M)',
            'sub'   => 'Workers Remittances'
        ),

        'kibor' => array(
            'value' => '11.84',
            'unit'  => '%',
            'sub'   => '6-Month KIBOR'
        ),

        'ca_balance' => array(
            'value' => '-328.00',
            'unit'  => 'USD (M)',
            'sub'   => 'Current Account Balance'
        ),

        'gdp_growth' => array(
            'value' => '3.70',
            'unit'  => '%',
            'sub'   => 'Real GDP Growth'
        ),

        'credit_priv' => array(
            'value' => '13.17',
            'unit'  => '%',
            'sub'   => 'Credit to Private Sector'
        ),

        'deposits' => array(
            'value' => '12.87',
            'unit'  => '%',
            'sub'   => 'Total Deposits YoY'
        ),

        'pkr_usd' => array(
            'value' => '277.92',
            'unit'  => 'PKR',
            'sub'   => 'Average PKR/USD'
        )

    );

    return get_option( 'hr_central_sbp_indicators', $defaults );
}


/**
 * Global master SBP data function.
 *
 * All SBP displays across Headline Recorder
 * should use this single function.
 */
function get_headline_recorder_sbp_data() {

    $data = hr_get_central_sbp_data();

    return array(

        'policy_rate' => array(
            'label' => 'Central Policy Rate',
            'value' => $data['policy_rate']['value'],
            'unit'  => $data['policy_rate']['unit']
        ),

        'inflation' => array(
            'label' => 'National CPI Inflation',
            'value' => $data['inflation']['value'],
            'unit'  => $data['inflation']['unit']
        ),

        'remittances' => array(
            'label' => 'Workers Remittances',
            'value' => $data['remittances']['value'],
            'unit'  => $data['remittances']['unit']
        ),

        'kibor' => array(
            'label' => '6-Month KIBOR',
            'value' => $data['kibor']['value'],
            'unit'  => $data['kibor']['unit']
        ),

        'ca_balance' => array(
            'label' => 'Current Account Balance',
            'value' => $data['ca_balance']['value'],
            'unit'  => $data['ca_balance']['unit']
        ),

        'gdp_growth' => array(
            'label' => 'Real GDP Growth',
            'value' => $data['gdp_growth']['value'],
            'unit'  => $data['gdp_growth']['unit']
        ),

        'credit_priv' => array(
            'label' => 'Credit to Private Sector',
            'value' => $data['credit_priv']['value'],
            'unit'  => $data['credit_priv']['unit']
        ),

        'deposits' => array(
            'label' => 'Total Deposits YoY',
            'value' => $data['deposits']['value'],
            'unit'  => $data['deposits']['unit']
        ),

        'pkr_usd' => array(
            'label' => 'Average PKR/USD',
            'value' => $data['pkr_usd']['value'],
            'unit'  => $data['pkr_usd']['unit']
        ),

        'last_updated' => 'September 3, 2026'

    );
}


/**
 * SBP Macro Indicators Grid Shortcode
 *
 * Usage:
 * [sbp_macro_grid]
 */
add_shortcode( 'sbp_macro_grid', function() {

    $data = get_headline_recorder_sbp_data();

    ob_start();
    ?>

    <div style="margin-top: 35px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">

        <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #e3000f; padding-bottom: 10px; margin-bottom: 25px;">

            <h3 style="font-size: 16px; text-transform: uppercase; letter-spacing: 1px; color: #111111; margin: 0; font-weight: 800;">
                State Bank Economic Indicators
            </h3>

            <span style="font-size: 11px; color: #888888; letter-spacing: 0.5px; font-weight: 600; text-transform: uppercase;">
                Last Updated: <?php echo esc_html( $data['last_updated'] ); ?>
            </span>

        </div>

        <div style="display: flex; gap: 20px; justify-content: space-between; flex-wrap: wrap;">

            <?php foreach ( $data as $slug => $meta ) :

                if ( $slug === 'last_updated' ) {
                    continue;
                }

                $show_unit = ! empty( $meta['unit'] ) ? $meta['unit'] : '';

            ?>

                <div
                    class="sbp-card"
                    style="flex: 1 1 30%; min-width: 250px; background: #fafafa; border: 1px solid #eaeaea; border-top: 3px solid #111; padding: 20px; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); margin-bottom: 10px; transition: transform 0.2s ease, box-shadow 0.2s ease;"
                >

                    <span style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #666; font-weight: 700; display: block; margin-bottom: 8px;">
                        <?php echo esc_html( $meta['label'] ); ?>
                    </span>

                    <div style="font-size: 26px; font-weight: 800; color: #000; line-height: 1.1; font-family: 'Courier New', monospace;">

                        <?php echo esc_html( $meta['value'] ); ?>

                        <?php if ( $show_unit ) : ?>

                            <span style="font-size: 14px; font-weight: 700; color: #999; font-family: -apple-system, sans-serif;">
                                <?php echo esc_html( $show_unit ); ?>
                            </span>

                        <?php endif; ?>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

        <p style="font-size: 10px; color: #aaaaaa; margin-top: 15px; letter-spacing: 0.3px; text-align: right; border-top: 1px solid #eee; padding-top: 10px;">
            * Source Architecture: SBP EasyData Web Services. Updated periodically.
        </p>

    </div>

    <?php

    return ob_get_clean();

} );

// =========================================================================
// SEO AUDIT FIX: CORRECT YOAST SCHEMA MISATTRIBUTION FOR GUEST AUTHORS
// =========================================================================

// 1. Intercept the Article Schema to fix the inline author name
add_filter( 'wpseo_schema_article', 'hr_yoast_fix_article_guest_author' );
function hr_yoast_fix_article_guest_author( $data ) {
    if ( is_single() && has_category('opinion') ) {
        $guest_name = get_post_meta( get_the_ID(), 'guest_author_name', true );
        
        if ( ! empty( $guest_name ) ) {
            $data['author']['name'] = sanitize_text_field( $guest_name );
        }
    }
    return $data;
}

// 2. Intercept the Person Schema to build the full Knowledge Graph entity
add_filter( 'wpseo_schema_person', 'hr_yoast_fix_person_guest_author' );
function hr_yoast_fix_person_guest_author( $data ) {
    if ( is_single() && has_category('opinion') ) {
        $post_id = get_the_ID();
        $guest_name = get_post_meta( $post_id, 'guest_author_name', true );
        
        if ( ! empty( $guest_name ) ) {
            // Swap Name
            $data['name'] = sanitize_text_field( $guest_name );
            
            // Swap Bio (Clear it if empty)
            $guest_bio = get_post_meta( $post_id, 'guest_author_bio', true );
            if ( ! empty( $guest_bio ) ) {
                $data['description'] = sanitize_textarea_field( $guest_bio );
            } else {
                unset( $data['description'] );
            }
            
            // Swap Avatar
            $guest_avatar = get_post_meta( $post_id, 'guest_author_avatar', true );
            if ( ! empty( $guest_avatar ) ) {
                $data['image']['url'] = esc_url( $guest_avatar );
                $data['image']['contentUrl'] = esc_url( $guest_avatar );
                $data['image']['caption'] = sanitize_text_field( $guest_name );
            }
            
            // FORCE OVERRIDE Socials (sameAs array)
            $sameAs = [];
            $linkedin = get_post_meta( $post_id, 'guest_author_linkedin', true );
            $twitter  = get_post_meta( $post_id, 'guest_author_twitter', true );
            $facebook = get_post_meta( $post_id, 'guest_author_facebook', true );
            
            if ( ! empty( $linkedin ) ) $sameAs[] = esc_url( $linkedin );
            if ( ! empty( $twitter ) )  $sameAs[] = esc_url( $twitter );
            if ( ! empty( $facebook ) ) $sameAs[] = esc_url( $facebook );
            
            // If we have guest links, use them. If we don't, completely delete the default links.
            if ( ! empty( $sameAs ) ) {
                $data['sameAs'] = $sameAs;
            } else {
                unset( $data['sameAs'] );
            }
            
            // Bonus Fix: Change the schema URL to the Guest Author Archive
            $term = get_term_by( 'name', $guest_name, 'guest_author' );
            if ( $term ) {
                $term_link = get_term_link( $term );
                if ( ! is_wp_error( $term_link ) ) {
                    $data['url'] = $term_link;
                }
            }
        }
    }
    return $data;
}

// Completely disable blog pagination archives and force 404
function hr_disable_blog_pagination($query) {
    if (!is_admin() && $query->is_main_query() && is_home() && get_query_var('paged') > 1) {
        $query->set_404();
        status_header(404);
        nocache_headers();
    }
}
add_action('pre_get_posts', 'hr_disable_blog_pagination');


// =========================================================================
// GEO AI EXTRACTION BLOCK (EXECUTIVE BRIEFING)
// =========================================================================

add_action('add_meta_boxes', 'hr_register_geo_summary_meta');
function hr_register_geo_summary_meta() {
    add_meta_box(
        'hr_geo_summary_box',
        'GEO AI Extraction Summary (134-167 Words)',
        'hr_geo_summary_callback',
        'post',
        'normal',
        'high'
    );
}

function hr_geo_summary_callback($post) {
    wp_nonce_field('hr_geo_summary_save', 'hr_geo_summary_nonce');
    $geo_summary = get_post_meta($post->ID, '_hr_geo_summary', true);
    ?>
    <p style="color: #666; font-size: 13px;">Write a standalone, self-contained summary answering the core premise of the article. AI engines prioritize extracting passages that are strictly between <strong>134 and 167 words</strong>.</p>
    <textarea id="hr_geo_summary" name="hr_geo_summary" style="width:100%; height: 120px; padding: 10px;"><?php echo esc_textarea($geo_summary); ?></textarea>
    <p id="geo-word-count" style="font-weight: 600; color: #C0001A; margin-top: 5px;">Word Count: <span>0</span></p>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const textarea = document.getElementById('hr_geo_summary');
        const countSpan = document.querySelector('#geo-word-count span');
        const countText = document.getElementById('geo-word-count');

        function updateCount() {
            let text = textarea.value.trim();
            let words = text ? text.split(/\s+/).length : 0;
            countSpan.textContent = words;

            if (words >= 134 && words <= 167) {
                countText.style.color = '#10B981'; // Green for optimal
            } else {
                countText.style.color = '#C0001A'; // Red for outside optimal range
            }
        }
        
        textarea.addEventListener('input', updateCount);
        updateCount();
    });
    </script>
    <?php
}

add_action('save_post', 'hr_save_geo_summary_meta');
function hr_save_geo_summary_meta($post_id) {
    if (!isset($_POST['hr_geo_summary_nonce']) || !wp_verify_nonce($_POST['hr_geo_summary_nonce'], 'hr_geo_summary_save')) return;
    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id)) return;
    if (!current_user_can('edit_post', $post_id)) return;

    if (isset($_POST['hr_geo_summary'])) {
        update_post_meta($post_id, '_hr_geo_summary', sanitize_textarea_field($_POST['hr_geo_summary']));
    }
}

// =========================================================================
// OVERRIDE YOAST ORGANIZATION SCHEMA (ADD FOUNDING DATE)
// =========================================================================

add_filter( 'wpseo_schema_organization', 'hr_force_founding_date_schema' );

function hr_force_founding_date_schema( $data ) {
    // Hardcoding the canonical founding year for AI verification
    $data['foundingDate'] = '2023'; 
    
    return $data;
}

// =========================================================================
// SEO AUDIT FIX: DISABLE YOAST SCHEMA ON SINGLE POSTS (PREVENT DUPLICATES)
// =========================================================================
add_filter( 'wpseo_json_ld_output', function( $data ) {
    if ( is_single() ) {
        return false;
    }
    return $data;
});

add_filter( 'wpseo_schema_organization', 'upgrade_to_news_media_organization', 11, 1 );

function upgrade_to_news_media_organization( $data ) {
    // 1. Upgrade type to NewsMediaOrganization
    $data['@type'] = array( 'Organization', 'NewsMediaOrganization' );

    // 2. Wire up your exact live Editorial & Corrections Policy page URL
    $data['publishingPrinciples'] = 'https://headlinerecorder.com/editorial-corrections-policy/';
    $data['correctionsPolicy']     = 'https://headlinerecorder.com/editorial-corrections-policy/';

    return $data;
}

$hr_banner_hook = get_stylesheet_directory() . '/hook-front-page-main-banner-section-1.php';
if ( file_exists( $hr_banner_hook ) ) {
    require $hr_banner_hook;
}



// ==============================================================================
// HEADLINE RECORDER PLATFORM: account slot at the end of the primary menu
// (the hr-platform plugin fills it; with the plugin off the item is simply absent)
// ==============================================================================
add_filter( 'wp_nav_menu_items', 'hr_nav_account_slot', 20, 2 );
function hr_nav_account_slot( $items, $args ) {
    if ( empty( $args->theme_location ) || 'aft-primary-nav' !== $args->theme_location || ! has_action( 'hr_header_account' ) ) {
        return $items;
    }
    ob_start();
    do_action( 'hr_header_account' );
    $slot = trim( (string) ob_get_clean() );
    return '' === $slot ? $items : $items . '<li class="menu-item hr-nav-account">' . $slot . '</li>';
}
