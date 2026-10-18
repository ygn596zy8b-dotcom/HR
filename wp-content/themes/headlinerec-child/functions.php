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
        wp_enqueue_style( 'chld_thm_cfg_child', trailingslashit( get_stylesheet_directory_uri() ) . 'style.css', array( 'covernews-icons','bootstrap','covernews-style','covernews-style' ) );
    }
endif;
add_action( 'wp_enqueue_scripts', 'child_theme_configurator_css', 10 );

// END ENQUEUE PARENT ACTION


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

// =========================================================================
// INJECT PREMIUM TAGLINE & FLAT MASTHEAD STYLING (TICKERS UNTOUCHED)
// =========================================================================
add_action('wp_footer', 'force_inject_header_text');
function force_inject_header_text() {
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        if (window.innerWidth < 769) {
            return;
        }
        var fullHeader = document.querySelector('.site-header') || document.querySelector('.wrapper-header');
        if (fullHeader) {
            fullHeader.style.position = 'relative'; 
            
            var spotlight = document.createElement('div');
            spotlight.id = 'hr-premium-tagline';
            spotlight.innerHTML = '<div id="hr-tag-top">The Intelligence Layer</div><div id="hr-tag-bottom">Behind Pakistan\'s Business Conversations.</div>';
            
            fullHeader.appendChild(spotlight);

            var canvases = fullHeader.querySelectorAll('canvas');
            canvases.forEach(function(c){ c.remove(); });

            var siteTitle = document.querySelector('.site-title a') || document.querySelector('.site-title');
            if (siteTitle) {
                var originalText = siteTitle.textContent.trim();
                var words = originalText.split(' ');
                if (words.length >= 2) {
                    var firstWord = words[0];
                    var secondWord = words.slice(1).join(' ');
                    siteTitle.innerHTML = '<span class="hr-word-top">' + firstWord + '</span><span class="hr-word-bottom">' + secondWord + '</span>';
                    siteTitle.classList.add('hr-stacked-wordmark');
                }
            }
        }
    });

    window.addEventListener('resize', function() {
        var tagline = document.getElementById('hr-premium-tagline');
        if (window.innerWidth < 769 && tagline) {
            tagline.remove();
        }
    });
    </script>

    <style>
    .site-header,
    .wrapper-header,
    header.site-header,
    .ac-banner-main-section,
    .covernews-banner-section {
        background: #0A0A0A !important;
        background-image: none !important;
        background-color: #0A0A0A !important;
        animation: none !important;
        -webkit-animation: none !important;
    }

    .site-header canvas,
    .wrapper-header canvas,
    header.site-header canvas,
    .ac-banner-main-section canvas {
        display: none !important;
    }

    .site-header::before,
    .site-header::after,
    .wrapper-header::before,
    .wrapper-header::after,
    .ac-banner-main-section::before,
    .ac-banner-main-section::after {
        background: none !important;
        background-image: none !important;
    }

    @media only screen and (min-width: 769px) {

        .site-header,
        .wrapper-header,
        header.site-header,
        .ac-banner-main-section {
            min-height: 110px !important;
        }
        
        .masthead-banner.data-bg {
            padding: 65px 0 !important;
        }

        .site-branding {
            display: flex !important;
            align-items: center !important;
        }

        .site-branding .custom-logo,
        .site-branding img.custom-logo {
            height: 64px !important;
            width: auto !important;
            max-height: 64px !important;
        }

        .site-title,
        .site-title a {
            margin: 0 !important;
            padding: 0 !important;
            text-decoration: none !important;
            display: block !important;
            line-height: 1 !important;
        }

        .hr-stacked-wordmark {
            display: flex !important;
            flex-direction: column !important;
            gap: 2px !important;
        }

        .hr-word-top,
        .hr-word-bottom {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif !important;
            font-size: 20px !important;
            font-weight: 800 !important;
            letter-spacing: 0.01em !important;
            text-transform: uppercase !important;
            line-height: 1.08 !important;
            display: block !important;
        }

        .hr-word-top {
            color: #ffffff !important;
            opacity: 0.82 !important;
        }

        .hr-word-bottom {
            color: #C8102E !important;
            opacity: 0.82 !important;
        }

        #hr-premium-tagline {
            position: absolute;
            top: 44%; 
            transform: translateY(-50%);
            right: 48px; 
            text-align: right;
            pointer-events: none;
            z-index: 99;
        }

        #hr-tag-top {
            color: #C8102E !important;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif !important;
            font-size: 11px !important;
            font-weight: 800 !important;
            letter-spacing: 4px !important;
            text-transform: uppercase !important;
            margin-bottom: 4px !important;
        }

        #hr-tag-bottom {
            color: #FFFFFF !important;
            font-family: Georgia, "Times New Roman", serif !important;
            font-size: 22px !important;
            font-weight: 400 !important;
            font-style: italic !important;
            letter-spacing: 0.5px !important;
        }

        .site-description, 
        .site-branding p, 
        .custom-logo-link + p,
        p[class*="description"] {
            display: none !important;
        }
    }

    @media only screen and (max-width: 768px) {
        #hr-premium-tagline {
            display: none !important;
            visibility: hidden !important;
        }

        .masthead-banner.data-bg {
            padding: 25px 0 !important;
        }

        .masthead-banner .site-branding,
        .site-branding {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 2px !important;
        }

        .site-branding .custom-logo,
        .site-branding img.custom-logo {
            height: 46px !important;
            width: auto !important;
            max-height: 46px !important;
        }

        .masthead-banner .site-branding .site-title,
        header#masthead .site-title,
        .site-title {
            display: block !important;
            margin: 0 !important;
            padding: 0 !important;
            text-decoration: none !important;
            line-height: 1 !important;
            font-size: 16px !important;
            width: 50px !important;
            max-width: none !important;
        }

        .masthead-banner .site-branding .site-title a,
        header#masthead .site-title a,
        .site-title a {
            text-decoration: none !important;
            display: block !important;
        }

        .hr-stacked-wordmark {
            display: flex !important;
            flex-direction: column !important;
            gap: 1px !important;
        }

        .hr-stacked-wordmark .hr-word-top,
        .hr-stacked-wordmark .hr-word-bottom,
        .hr-word-top,
        .hr-word-bottom {
            display: block !important;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif !important;
            font-size: 13px !important;
            font-weight: 800 !important;
            letter-spacing: 0.01em !important;
            text-transform: uppercase !important;
            line-height: 1.1 !important;
        }

        .hr-word-top {
            color: #ffffff !important;
            opacity: 0.82 !important;
        }

        .hr-word-bottom {
            color: #C8102E !important;
            opacity: 0.82 !important;
        }

        .masthead-banner .site-branding .site-description,
        header#masthead .site-description,
        .site-description {
            display: none !important;
        }
    }
</style>
<?php
}

// =========================================================================
// DESKTOP NAV UPGRADE
// =========================================================================
add_action('wp_footer', 'hr_desktop_nav_upgrade');
function hr_desktop_nav_upgrade() {
    ?>
    <style>
    @media only screen and (min-width: 769px) {

        .main-navigation,
        nav.main-navigation,
        #site-navigation {
            background-color: #C0001A !important; 
            position: relative !important;
            z-index: 100;
            width: 100% !important;
            display: block !important;
            border: none !important;
            text-align: center !important; 
            overflow: hidden !important; 
        }
        
        .main-navigation > div:not(#hr-action-group):not(#hr-navbar-search),
        .menu-primary-container {
            display: flex !important;
            justify-content: center !important;
            width: 100% !important;
        }

        .main-navigation ul.nav-menu,
        .main-navigation ul.menu,
        #site-navigation ul {
            display: flex !important;
            justify-content: center !important;
            align-items: center !important;
            margin: 0 auto !important;
            padding: 0 !important;
            width: max-content !important; 
        }

        .main-navigation ul > li {
            float: none !important;
            display: inline-block !important;
        }

        .main-navigation ul.nav-menu > li > a,
        .main-navigation ul.menu > li > a,
        #site-navigation ul > li > a {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif !important;
            font-size: 13px !important;
            font-weight: 700 !important;
            letter-spacing: 0.5px !important;
            color: rgba(255, 255, 255, 0.9) !important;
            padding: 16px 20px !important;
            position: relative !important;
            text-transform: capitalize !important;
            transition: color 0.2s ease, background 0.2s ease !important;
        }

        #hr-action-group {
            position: absolute !important;
            right: 0 !important;
            top: 0 !important;
            bottom: 0 !important;
            display: flex !important;
            align-items: stretch !important;
            background: transparent !important; 
            width: auto !important;
            z-index: 101 !important;
            border-left: 1px solid rgba(255, 255, 255, 0.15) !important;
        }

        #hr-action-group a {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            color: #fff !important;
            text-decoration: none !important;
            transition: background 0.2s ease !important;
            height: 100% !important;
            padding: 0 24px !important;
            cursor: pointer;
        }

        #hr-action-group a:hover {
            background: rgba(0, 0, 0, 0.15) !important; 
        }
        
        .header-search-wrapper, .search-icon { display: none !important; }

        #hr-navbar-search {
            position: absolute !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 100% !important;
            background-color: #141414 !important;
            z-index: 105 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-100%);
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.3s ease;
        }

        #hr-navbar-search.active {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        #hr-navbar-search form {
            width: 100% !important;
            max-width: 800px !important;
            display: flex !important;
            align-items: center !important;
            padding: 0 24px !important;
            margin: 0 !important;
        }

        #hr-navbar-search input[type="text"] {
            flex-grow: 1 !important;
            background: transparent !important;
            border: none !important;
            color: #fff !important;
            font-size: 15px !important;
            font-weight: 700 !important;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif !important;
            outline: none !important;
            box-shadow: none !important;
            padding: 0 !important;
        }

        #hr-navbar-search input[type="text"]::placeholder {
            color: rgba(255, 255, 255, 0.4) !important;
            font-weight: 400 !important;
        }

        .hr-inline-search-close {
            color: rgba(255, 255, 255, 0.5) !important;
            font-size: 28px !important;
            font-weight: 300 !important;
            cursor: pointer !important;
            line-height: 1 !important;
            padding-left: 20px !important;
            transition: color 0.2s ease !important;
            font-family: -apple-system, sans-serif !important;
        }

        .hr-inline-search-close:hover {
            color: #fff !important;
        }
    }

    @media only screen and (max-width: 768px) {
        #hr-action-group, #hr-navbar-search { display: none !important; }
    }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        if (window.innerWidth < 769) return;

        var nav = document.querySelector('.main-navigation') || 
                  document.querySelector('#site-navigation') ||
                  document.querySelector('nav.navbar');
        if (!nav) return;

        if(!document.getElementById('hr-action-group')) {
            var actionWrap = document.createElement('div');
            actionWrap.id = 'hr-action-group';
            actionWrap.innerHTML = `
                <a id="hr-search-trigger" aria-label="Search">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                </a>
            `;
            nav.appendChild(actionWrap);
        }

        if(!document.getElementById('hr-navbar-search')) {
            var searchPanel = document.createElement('div');
            searchPanel.id = 'hr-navbar-search';
            searchPanel.innerHTML = `
                <form role="search" method="get" action="/">
                    <input type="text" name="s" id="hr-inline-input" placeholder="Type keyword or company to search..." autocomplete="off" />
                    <div class="hr-inline-search-close">&times;</div>
                </form>
            `;
            nav.appendChild(searchPanel);
        }

        var trigger = document.getElementById('hr-search-trigger');
        var searchPanelElem = document.getElementById('hr-navbar-search');
        var closeBtn = document.querySelector('.hr-inline-search-close');
        var searchInput = document.getElementById('hr-inline-input');

        function openSearch() {
            searchPanelElem.classList.add('active');
            setTimeout(function() { searchInput.focus(); }, 300);
        }

        function closeSearch() {
            searchPanelElem.classList.remove('active');
            searchInput.value = '';
            searchInput.blur();
        }

        if(trigger) {
            trigger.addEventListener('click', function(e) {
                e.preventDefault();
                openSearch();
            });
        }

        if(closeBtn) {
            closeBtn.addEventListener('click', closeSearch);
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === "Escape" && searchPanelElem.classList.contains('active')) {
                closeSearch();
            }
        });
    });
    </script>
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
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
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


// =========================================================================
// INJECT CUSTOM PNG FAVICON
// =========================================================================
add_action('wp_head', 'add_custom_png_favicon');
function add_custom_png_favicon() {
    echo '<link rel="icon" type="image/png" href="https://headlinerecorder.com/wp-content/uploads/2026/08/headline-recorder-faviconn.png">' . "\n";
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
add_action('admin_enqueue_scripts', function() { wp_enqueue_media(); });

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
function hr_save_guest_author_meta($post_id) {
    if (!isset($_POST['hr_guest_author_nonce']) || !wp_verify_nonce($_POST['hr_guest_author_nonce'], 'hr_guest_author_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    // A. Save to the Post Meta (Keeps your editor meta box populated)
    $fields = ['guest_author_name', 'guest_author_bio'];
    foreach ($fields as $field) {
        if (array_key_exists($field, $_POST)) update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
    }
    
    $url_fields = ['guest_author_linkedin', 'guest_author_twitter', 'guest_author_facebook', 'guest_author_avatar'];
    foreach ($url_fields as $field) {
        if (array_key_exists($field, $_POST)) update_post_meta($post_id, $field, esc_url_raw($_POST[$field]));
    }

    // B. Sync Author Name to the Archive Taxonomy & PUSH Meta to the Term
    if (!empty($_POST['guest_author_name'])) {
        $author_name = sanitize_text_field($_POST['guest_author_name']);
        
        // 1. Assign the term to the post
        wp_set_object_terms($post_id, $author_name, 'guest_author', false);
        
        // 2. Fetch the term object we just assigned/created
        $term = get_term_by('name', $author_name, 'guest_author');
        
        // 3. Push the bio, avatar, and socials into the actual TERM META
        if ($term && !is_wp_error($term)) {
            if (isset($_POST['guest_author_bio'])) {
                update_term_meta($term->term_id, 'guest_author_bio', sanitize_textarea_field($_POST['guest_author_bio']));
            }
            if (isset($_POST['guest_author_avatar'])) {
                update_term_meta($term->term_id, 'guest_author_avatar', esc_url_raw($_POST['guest_author_avatar']));
            }
            if (isset($_POST['guest_author_linkedin'])) {
                update_term_meta($term->term_id, 'guest_author_linkedin', esc_url_raw($_POST['guest_author_linkedin']));
            }
            if (isset($_POST['guest_author_twitter'])) {
                update_term_meta($term->term_id, 'guest_author_twitter', esc_url_raw($_POST['guest_author_twitter']));
            }
            if (isset($_POST['guest_author_facebook'])) {
                update_term_meta($term->term_id, 'guest_author_facebook', esc_url_raw($_POST['guest_author_facebook']));
            }
        }
    } else {
        // If name is cleared, remove the term from the post
        wp_set_object_terms($post_id, array(), 'guest_author');
    }
}
add_action('save_post', 'hr_save_guest_author_meta');

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

    <style>

        .sbp-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.08) !important;
            border-top-color: #e3000f !important;
        }

    </style>

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
                $data['url'] = get_term_link( $term );
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
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

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

// =========================================================================
// FORCE 48PX MOBILE TAP TARGETS
// =========================================================================
add_action('wp_head', 'hr_force_mobile_tap_targets', 999);
function hr_force_mobile_tap_targets() {
    ?>
    <style>
    @media only screen and (max-width: 768px) {
        .aft-void-menu, 
        .menu-toggle, 
        .mobile-menu-toggle, 
        .header-search-icon, 
        .offcanvas-menu-toggler {
            min-width: 48px !important;
            min-height: 48px !important;
            width: 48px !important;
            height: 48px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }
    }
    </style>
    <?php
}

$hr_banner_hook = get_stylesheet_directory() . '/hook-front-page-main-banner-section-1.php';
if ( file_exists( $hr_banner_hook ) ) {
    require $hr_banner_hook;
}

// =========================================================================
// INJECT CUSTOM UIVERSE HAMBURGER ICON (UPDATED SELECTOR)
// =========================================================================
function headline_recorder_inject_custom_hamburger() {
    ?>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        // Updated to target the exact classes shown in your DevTools
        const toggleBtn = document.querySelector('.toggle-menu .aft-void-menu') || document.querySelector('.toggle-menu');
        if (toggleBtn) {
            toggleBtn.innerHTML = `
                <label class="hamburger" style="cursor: pointer; display: flex; align-items: center; justify-content: center; width: 100%; height: 100%;">
                  <input type="checkbox" style="display: none;" />
                  <svg viewBox="0 0 32 32" style="height: 48em; transition: transform 600ms cubic-bezier(0.4, 0, 0.2, 1);">
                    <path d="M27 10 13 10C10.8 10 9 8.2 9 6 9 3.5 10.8 2 13 2 15.2 2 17 3.8 17 6L17 26C17 28.2 18.8 30 21 30 23.2 30 25 28.2 25 26 25 23.8 23.2 22 21 22L7 22" class="line line-top-bottom" style="fill: none; stroke: #ffffff; stroke-linecap: round; stroke-linejoin: round; stroke-width: 3; transition: stroke-dasharray 600ms cubic-bezier(0.4, 0, 0.2, 1), stroke-dashoffset 600ms cubic-bezier(0.4, 0, 0.2, 1); stroke-dasharray: 12 63;"></path>
                    <path d="M7 16 27 16" class="line" style="fill: none; stroke: #ffffff; stroke-linecap: round; stroke-linejoin: round; stroke-width: 3;"></path>
                  </svg>
                </label>
            `;
            
            const checkbox = toggleBtn.querySelector('input');
            toggleBtn.addEventListener('click', function(e) {
                if (e.target.tagName !== 'INPUT') {
                   checkbox.checked = !checkbox.checked;
                }
                const svg = toggleBtn.querySelector('svg');
                const path = toggleBtn.querySelector('.line-top-bottom');
                if (checkbox.checked) {
                    svg.style.transform = 'rotate(-45deg)';
                    path.style.strokeDasharray = '20 300';
                    path.style.strokeDashoffset = '-32.42';
                } else {
                    svg.style.transform = 'rotate(0deg)';
                    path.style.strokeDasharray = '12 63';
                    path.style.strokeDashoffset = '0';
                }
            });
        }
    });
    </script>
    <?php
}
add_action('wp_footer', 'headline_recorder_inject_custom_hamburger');

// =========================================================================
// FORCE REMOVE MOBILE MENU FOCUS OUTLINE
// =========================================================================
add_action('wp_head', 'hr_force_remove_focus_outline', 999);
function hr_force_remove_focus_outline() {
    ?>
    <style>
    /* Target exact parent theme classes to strip the dotted outline */
    .main-navigation .toggle-menu a.aft-void-menu,
    .main-navigation .toggle-menu a.aft-void-menu:focus,
    .main-navigation .toggle-menu a.aft-void-menu:active,
    .main-navigation .toggle-menu a.aft-void-menu:focus-visible,
    .hamburger:focus {
        outline: none !important;
        outline-width: 0 !important;
        box-shadow: none !important;
        -webkit-tap-highlight-color: transparent !important;
    }
    </style>
    <?php
}

