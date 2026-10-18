<?php
/**
 * The template for displaying the footer
 *
 * Contains the closing of the #content div and all content after.
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package CoverNews
 */
?>

</div><!-- Closes #content -->

<?php do_action('covernews_action_full_width_upper_footer_section'); ?>

<style>
    /* =========================================================
       CUSTOM PREMIUM FOOTER
    ========================================================= */
    .hr-footer-wrapper {
        background-color: #0A0A0A;
        color: #9CA3AF;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        margin: 0;
        padding: 0;
        width: 100%;
    }
    
    .hr-footer-wrapper * {
        box-sizing: border-box;
    }

    /* ── 1. NEWSLETTER SECTION ── */
    .hr-nl-section {
        padding: 80px 20px;
        text-align: center;
        border-bottom: 1px solid #1A1A1A;
    }
    .hr-nl-eyebrow {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 15px;
        margin-bottom: 20px;
    }
    .hr-nl-eyebrow::before,
    .hr-nl-eyebrow::after {
        content: '';
        height: 2px;
        width: 30px;
        background-color: #E31837;
    }
    .hr-nl-eyebrow span {
        color: #E31837;
        font-size: 12px; /* Bumped from 10px */
        font-weight: 800;
        letter-spacing: 4px;
        text-transform: uppercase;
    }
    .hr-nl-heading {
        color: #FFFFFF;
        font-size: 42px; /* Bumped from 36px */
        font-weight: 800;
        line-height: 1.2;
        margin: 0 0 15px 0;
        letter-spacing: -0.5px;
    }
    .hr-nl-sub {
        font-size: 17px; /* Bumped from 15px */
        color: #9CA3AF;
        max-width: 600px;
        margin: 0 auto 30px auto;
        line-height: 1.6;
    }
    .hr-nl-form {
        display: flex;
        max-width: 500px;
        margin: 0 auto;
    }
    .hr-nl-input {
        flex: 1;
        background: #111111;
        border: 1px solid #222222;
        padding: 16px 20px;
        color: #FFFFFF;
        font-size: 16px; /* Bumped from 14px */
        outline: none;
        transition: border 0.3s;
    }
    .hr-nl-input:focus {
        border-color: #E31837;
    }
    .hr-nl-btn {
        background: #E31837;
        color: #FFFFFF;
        border: none;
        padding: 0 32px;
        font-size: 14px; /* Bumped from 12px */
        font-weight: 800;
        letter-spacing: 1.5px;
        text-transform: uppercase;
        cursor: pointer;
        transition: background 0.3s;
    }
    .hr-nl-btn:hover {
        background: #b51029;
    }

    /* ── 2. MAIN FOOTER GRID ── */
    .hr-footer-main {
        max-width: 1200px;
        margin: 0 auto;
        padding: 80px 40px;
        display: grid;
        grid-template-columns: 1.2fr 2fr 0.8fr;
        gap: 60px;
    }
    .hr-footer-col-title {
        color: #E31837;
        font-size: 13px; /* Bumped from 11px */
        font-weight: 800;
        letter-spacing: 2px;
        text-transform: uppercase;
        margin-bottom: 30px;
    }

    /* Col 1: Brand */
    .hr-footer-logo {
        margin-bottom: 0px;
        display: block;
    }
    .hr-logo-img {
        height: 72px; /* Change this to make it bigger or smaller */
        width: auto;
        display: block;
    }
    .hr-footer-logo span:first-child { color: #FFFFFF; }
    .hr-footer-logo span:last-child { color: #E31837; }
    .hr-footer-desc {
        font-size: 15px; /* Bumped from 13px */
        line-height: 1.8;
        color: #6B7280;
        margin-bottom: 30px;
        max-width: 320px;
    }
    .hr-social-boxes {
        display: flex;
        gap: 8px;
    }
    .hr-social-box {
        width: 40px; /* Scaled up from 36px */
        height: 40px; /* Scaled up from 36px */
        background: #1A1A1A;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #9CA3AF;
        text-decoration: none;
        font-size: 13px; /* Bumped from 11px */
        font-weight: 600;
        transition: all 0.3s;
    }
    .hr-social-box:hover {
        background: #E31837;
        color: #FFFFFF;
    }

    /* Col 2: Latest Updates */
    .hr-latest-link {
        display: block;
        color: #6B7280;
        text-decoration: none;
        font-size: 15px; /* Bumped from 13px */
        line-height: 1.6;
        padding: 16px 0;
        border-bottom: 1px solid #1A1A1A;
        transition: color 0.2s;
    }
    .hr-latest-link:first-of-type {
        padding-top: 0;
    }
    .hr-latest-link:last-of-type {
        border-bottom: none;
    }
    .hr-latest-link:hover {
        color: #FFFFFF;
    }

    /* Col 3: Company */
    .hr-company-menu {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .hr-company-menu li {
        margin-bottom: 16px;
    }
    .hr-company-menu a {
        color: #9CA3AF;
        text-decoration: none;
        font-size: 15px; /* Bumped from 13px */
        transition: color 0.2s;
    }
    .hr-company-menu a:hover {
        color: #E31837;
    }

    /* ── 3. BOTTOM BAR ── */
    .hr-footer-bottom {
        border-top: 1px solid #1A1A1A;
        padding: 24px 40px;
    }
    .hr-bottom-inner {
        max-width: 1200px;
        margin: 0 auto;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 13px; /* Bumped from 11px */
        color: #6B7280;
    }
    .hr-bottom-socials {
        display: flex;
        gap: 15px;
    }
    .hr-bottom-socials a {
        color: #6B7280;
        text-decoration: none;
        transition: color 0.2s;
    }
    .hr-bottom-socials a:hover {
        color: #FFFFFF;
    }

    /* ── RESTYLE COVERNEWS SCROLL UP BUTTON ── */
    #scroll-up {
        background-color: #E31837 !important;
        color: #ffffff !important;
        border: none !important;
    }
    #scroll-up:hover {
        background-color: #b51029 !important;
    }

    /* ── RESPONSIVE ── */
    @media (max-width: 900px) {
        .hr-footer-main {
            grid-template-columns: 1fr;
            gap: 40px;
            padding: 60px 20px;
        }
        .hr-nl-heading {
            font-size: 32px; /* Bumped from 28px */
        }
        .hr-nl-form {
            flex-direction: column;
            gap: 10px;
        }
        .hr-nl-btn {
            padding: 16px;
        }
        .hr-bottom-inner {
            flex-direction: column;
            gap: 15px;
            text-align: center;
        }
    }
</style>

<footer class="hr-footer-wrapper">
    
    <!-- 1. INTELLIGENCE BRIEFING (NEWSLETTER) -->
    <div class="hr-nl-section">
        <div class="hr-nl-eyebrow"><span>Intelligence Briefing</span></div>
        <h2 class="hr-nl-heading">Pakistan's sharpest business briefing.<br>Weekly.</h2>
        <p class="hr-nl-sub">Join decision-makers who read Headline Recorder to stay ahead of Pakistan's economy.</p>
        
        <!-- Mailchimp Plugin Engine -->
        <div class="hr-nl-form">
             <?php echo do_shortcode('[mc4wp_form id="2953"]'); ?>
        </div>
    </div>

    <!-- 2. MAIN GRID -->
    <div class="hr-footer-main">
        
        <!-- Column 1: Brand -->
        <div class="hr-footer-col">
            <div class="hr-footer-logo">
                <img src="https://headlinerecorder.com/wp-content/uploads/2026/01/headline_recorder_logo.png" alt="Headline Recorder" class="hr-logo-img">
            </div>
            <p class="hr-footer-desc">Pakistan’s premier intelligence layer for business executives, capital markets, and macro decision-makers. Delivering rigorous journalism and independent market analysis.</p>
            <div class="hr-social-boxes">
                <a href="https://www.instagram.com/headlinerecorder/" class="hr-social-box">ig</a>
                <a href="https://www.facebook.com/profile.php?id=61573634501379" class="hr-social-box">fb</a>
                <a href="https://x.com/headlinerecoder" class="hr-social-box">x</a>
                <a href="https://www.linkedin.com/company/headlinerecorder/" class="hr-social-box">in</a>
                <a href="https://www.youtube.com/@headlinerecorder" class="hr-social-box">yt</a>
            </div>
        </div>

        <!-- Column 2: Dynamic Latest Updates -->
        <div class="hr-footer-col">
            <div class="hr-footer-col-title">Latest Updates</div>
            <?php
            // Fetch the 4 most recent posts globally
            $footer_query = new WP_Query(array(
                'posts_per_page' => 4,
                'post_status'    => 'publish'
            ));

            if ($footer_query->have_posts()) :
                while ($footer_query->have_posts()) : $footer_query->the_post();
                    ?>
                    <a href="<?php the_permalink(); ?>" class="hr-latest-link">
                        <?php the_title(); ?>
                    </a>
                    <?php
                endwhile;
                wp_reset_postdata();
            else :
                echo '<p style="font-size:15px; color:#6B7280;">No updates available.</p>';
            endif;
            ?>
        </div>

        <!-- Column 3: Company Links -->
        <div class="hr-footer-col">
            <div class="hr-footer-col-title">Company</div>
            <ul class="hr-company-menu">
                <li><a href="/about">About Us</a></li>
                <li><a href="/authors-experts">Authors & Experts</a></li>
                <li><a href="/disclaimer">Disclaimer</a></li>
                <li><a href="/privacy-policy">Privacy Policy</a></li>
                <li><a href="/terms-of-use">Terms of Use</a></li>
                <li><a href="/contact">Contact Us</a></li>
                <li><a href="/pr-media">PR & Media</a></li>
                <li><a href="/editorial-corrections-policy">Editorial & Corrections Policy</a></li>
            </ul>
        </div>

    </div>

    <!-- 3. BOTTOM BAR -->
    <div class="hr-footer-bottom">
        <div class="hr-bottom-inner">
            <div class="hr-copyright">
                Copyright &copy; <?php echo date('Y'); ?> All rights reserved. | Headline Recorder
            </div>
            <div class="hr-bottom-socials">
                <a href="https://www.instagram.com/headlinerecorder/">ig</a>
                <a href="https://www.facebook.com/profile.php?id=61573634501379">fb</a>
                <a href="https://x.com/headlinerecoder">x</a>
                <a href="https://www.linkedin.com/company/headlinerecorder/">in</a>
                <a href="https://www.youtube.com/@headlinerecorder">yt</a>
            </div>
        </div>
    </div>

</footer>

</div><!-- Closes #page -->

<a id="scroll-up" class="secondary-color" href="#top" aria-label="<?php echo esc_attr('Scroll to top', 'covernews'); ?>">
  <i class="fa fa-angle-up" aria-hidden="true"></i>
</a>

<?php wp_footer(); ?>

</body>
</html>