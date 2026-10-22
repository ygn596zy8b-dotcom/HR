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
<?php if ( is_front_page() ) { do_action( 'hr_home_before_footer' ); } // Client logos (hr-platform) ?>

<?php wp_print_styles( 'hr-footer' ); // was an inline <style>; same position keeps the cascade (inc/assets.php) ?>

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
        
        <!-- Column 1: Brand, address, socials -->
        <div class="hr-footer-col hr-footer-col--brand">
            <div class="hr-footer-logo">
                <img src="<?php echo esc_url(wp_get_upload_dir()['baseurl'] . '/2026/01/headline_recorder_logo.png'); ?>" alt="Headline Recorder" class="hr-logo-img" width="100" height="100" loading="lazy" decoding="async">
            </div>
            <p class="hr-footer-desc">Pakistan’s premier intelligence layer for business executives, capital markets, and macro decision-makers. Delivering rigorous journalism and independent market analysis.</p>
            <?php do_action( 'hr_footer_contact' ); // Registered address and newsroom email (hr-platform) ?>
            <div class="hr-social-boxes">
                <a href="https://www.instagram.com/headlinerecorder/" class="hr-social-box" aria-label="Headline Recorder on Instagram" rel="noopener" data-abbr="ig"></a>
                <a href="https://www.facebook.com/profile.php?id=61573634501379" class="hr-social-box" aria-label="Headline Recorder on Facebook" rel="noopener" data-abbr="fb"></a>
                <a href="https://x.com/headlinerecoder" class="hr-social-box" aria-label="Headline Recorder on X" rel="noopener" data-abbr="x"></a>
                <a href="https://www.linkedin.com/company/headlinerecorder/" class="hr-social-box" aria-label="Headline Recorder on LinkedIn" rel="noopener" data-abbr="in"></a>
                <a href="https://www.youtube.com/@headlinerecorder" class="hr-social-box" aria-label="Headline Recorder on YouTube" rel="noopener" data-abbr="yt"></a>
            </div>
        </div>

        <!-- Column 2: Dynamic Latest Updates -->
        <div class="hr-footer-col hr-footer-col--latest">
            <div class="hr-footer-col-title">Latest Updates</div>
            <?php
            // Fetch the 4 most recent posts globally
            $footer_query = new WP_Query(array(
                'posts_per_page' => 4,
                'post_status'    => 'publish',
                'no_found_rows'  => true,
            ));

            if ($footer_query->have_posts()) :
                while ($footer_query->have_posts()) : $footer_query->the_post();
                    ?>
                    <a href="<?php echo esc_url(get_permalink()); ?>" class="hr-latest-link">
                        <?php echo esc_html(get_the_title()); ?>
                    </a>
                    <?php
                endwhile;
                wp_reset_postdata();
            else :
                echo '<p class="hr-footer-empty">No updates available.</p>';
            endif;
            ?>
        </div>

        <!-- Column 3: Company Links -->
        <div class="hr-footer-col hr-footer-col--company">
            <div class="hr-footer-col-title">Company</div>
            <ul class="hr-company-menu">
                <li><a href="<?php echo esc_url(home_url('/about-us/')); ?>">About Us</a></li>
                <li><a href="<?php echo esc_url(home_url('/authors-experts/')); ?>">Authors &amp; Experts</a></li>
                <li><a href="<?php echo esc_url(home_url('/disclaimer/')); ?>">Disclaimer</a></li>
                <li><a href="<?php echo esc_url(home_url('/privacy-policy/')); ?>">Privacy Policy</a></li>
                <li><a href="<?php echo esc_url(home_url('/terms-of-use/')); ?>">Terms of Use</a></li>
                <li><a href="<?php echo esc_url(home_url('/contact-us/')); ?>">Contact Us</a></li>
                <li><a href="<?php echo esc_url(home_url('/pr-media/')); ?>">PR &amp; Media</a></li>
                <li><a href="<?php echo esc_url(home_url('/editorial-corrections-policy/')); ?>">Editorial &amp; Corrections Policy</a></li>
            </ul>
        </div>

    </div>

    <!-- 3. BOTTOM BAR -->
    <div class="hr-footer-bottom">
        <div class="hr-bottom-inner">
            <div class="hr-copyright">
                Copyright &copy; <?php echo esc_html( date( 'Y' ) ); ?> All rights reserved. | Headline Recorder
            </div>
            <div class="hr-footer-trust"><?php do_action( 'hr_footer_trust' ); ?></div>
            <div class="hr-bottom-socials">
                <a href="https://www.instagram.com/headlinerecorder/" aria-label="Headline Recorder on Instagram" rel="noopener" data-abbr="ig"></a>
                <a href="https://www.facebook.com/profile.php?id=61573634501379" aria-label="Headline Recorder on Facebook" rel="noopener" data-abbr="fb"></a>
                <a href="https://x.com/headlinerecoder" aria-label="Headline Recorder on X" rel="noopener" data-abbr="x"></a>
                <a href="https://www.linkedin.com/company/headlinerecorder/" aria-label="Headline Recorder on LinkedIn" rel="noopener" data-abbr="in"></a>
                <a href="https://www.youtube.com/@headlinerecorder" aria-label="Headline Recorder on YouTube" rel="noopener" data-abbr="yt"></a>
            </div>
        </div>
    </div>

</footer>

</div><!-- Closes #page -->

<a id="scroll-up" class="secondary-color" href="#top" aria-label="<?php esc_attr_e('Scroll to top', 'covernews'); ?>">
</a>

<?php wp_footer(); ?>

</body>
</html>