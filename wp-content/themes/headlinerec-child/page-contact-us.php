<?php
/**
 * Template Name: Contact Us
 * Filename: page-contact-us.php
 */
get_header();
?>

<style>
/* ==========================================================================
   HEADLINE RECORDER - PREMIUM CONTACT PAGE UI
   ========================================================================== */

/* Force theme wrappers to full width to allow our hero to stretch */
#primary, #main, .site-main, .content-area {
    width: 100% !important; max-width: 100% !important; float: none !important; margin: 0 !important; padding: 0 !important;
}
#secondary, .sidebar, .widget-area { display: none !important; }

.hr-contact-master {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    background-color: #FFFFFF;
    color: #0A0A0A;
    line-height: 1.5;
    margin: 0;
    padding: 0;
}
.hr-contact-master * { box-sizing: border-box; }

/* --- HERO SECTION --- */
.hr-hero-section {
    background-color: #0A0A0A;
    background-image: radial-gradient(ellipse at 70% 50%, rgba(192,0,26,0.15) 0%, transparent 60%);
    padding: 100px 48px;
    position: relative;
    overflow: hidden;
}
.hr-hero-inner {
    max-width: 1200px;
    margin: 0 auto;
    position: relative;
    z-index: 1;
}
.hr-eyebrow {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
}
.hr-eyebrow .hr-line {
    width: 32px; height: 2px; background-color: #C0001A;
}
.hr-eyebrow span {
    font-size: 12px; font-weight: 800; letter-spacing: 4px; color: #C0001A; text-transform: uppercase;
}
.hr-hero-inner h1 {
    font-size: 56px; font-weight: 800; color: #FFFFFF; margin: 0 0 16px 0; line-height: 1.1; letter-spacing: -1px;
}
.hr-hero-inner p {
    font-size: 16px; color: #9CA3AF; margin: 0; line-height: 1.6; max-width: 620px;
}

/* --- HIDE DEFAULT THEME BREADCRUMB --- */
.twp-breadcrumb-wrapper, .breadcrumb-trail, #breadcrumb, .breadcrumbs, .page-header {
    display: none !important;
}

/* --- PREMIUM BREADCRUMB --- */
.es-top-nav-path {
    display: block !important;
    max-width: 1200px;
    margin: 0 auto;
    padding: 24px 48px;
    font-size: 11px;
    color: #9CA3AF;
    letter-spacing: 1px;
    text-transform: uppercase;
    background: #FFFFFF;
}
.es-top-nav-path a { color: #9CA3AF; text-decoration: none; transition: color 0.2s; }
.es-top-nav-path a:hover { color: #C0001A; }
.es-top-nav-path span { margin: 0 8px; }

@media (max-width: 768px) {
    .es-top-nav-path { padding: 20px 24px; }
}

/* --- MAIN CONTENT CONTAINER --- */
.hr-main-content {
    max-width: 1200px; margin: 0 auto; padding: 80px 48px;
}

/* --- SECTION HEADERS --- */
.hr-section-header {
    display: flex; align-items: center; gap: 12px; margin-bottom: 40px;
}
.hr-section-header .hr-line {
    width: 28px; height: 2px; background-color: #C0001A;
}
.hr-section-header span {
    font-size: 12px; font-weight: 800; letter-spacing: 4px; text-transform: uppercase; color: #6B7280;
}

/* --- QUICK ACCESS GRID --- */
.hr-qa-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px; margin-bottom: 80px;
}
.hr-qa-card {
    border: 1px solid #E5E7EB; padding: 32px 24px; cursor: pointer; transition: all 0.2s ease; background: #FFFFFF;
}
.hr-qa-card:hover {
    border-color: #C0001A; box-shadow: 0 8px 24px rgba(192,0,26,0.08);
}
.hr-qa-icon { font-size: 32px; margin-bottom: 16px; }
.hr-qa-card h3 {
    font-size: 16px; font-weight: 700; color: #0A0A0A; margin: 0 0 8px 0; line-height: 1.3;
}
.hr-qa-card p {
    font-size: 13px; color: #6B7280; margin: 0 0 14px 0; line-height: 1.5;
}
.hr-qa-link {
    font-size: 12px; font-weight: 700; color: #C0001A; text-decoration: none; border-bottom: 1px solid #C0001A; padding-bottom: 1px; display: inline-block;
}

/* --- TWO COLUMN LAYOUT --- */
.hr-two-column {
    display: grid; grid-template-columns: 1fr 380px; gap: 64px;
}

/* --- CONTACT FORM 7 OVERRIDES --- */
.hr-two-column .wpcf7-form label {
    display: block; font-size: 11px; font-weight: 800; letter-spacing: 2px; text-transform: uppercase; color: #0A0A0A; margin-bottom: 10px; width: 100%;
}
.hr-two-column .wpcf7-form-control-wrap {
    display: block; width: 100%; margin-bottom: 24px;
}
.hr-two-column .wpcf7-text, 
.hr-two-column .wpcf7-select, 
.hr-two-column .wpcf7-textarea {
    width: 100% !important; padding: 14px 16px !important; font-size: 15px !important; border: 1px solid #E5E7EB !important; outline: none !important; font-family: inherit !important; transition: all 0.2s ease !important; color: #0A0A0A !important; background: #FFFFFF !important; box-sizing: border-box !important; border-radius: 0 !important;
}
.hr-two-column .wpcf7-text:focus, 
.hr-two-column .wpcf7-select:focus, 
.hr-two-column .wpcf7-textarea:focus {
    border-color: #C0001A !important;
}
.hr-two-column .wpcf7-submit {
    background: #0A0A0A !important; color: #FFFFFF !important; border: none !important; padding: 16px 32px !important; font-size: 11px !important; font-weight: 800 !important; letter-spacing: 2px !important; text-transform: uppercase !important; cursor: pointer !important; font-family: inherit !important; transition: all 0.2s ease !important; border-radius: 0 !important;
}
.hr-two-column .wpcf7-submit:hover {
    background: #C0001A !important;
}

/* --- SIDEBAR --- */
.hr-sidebar {
    position: sticky; top: 24px; display: flex; flex-direction: column; gap: 40px;
}
.hr-sb-heading {
    font-size: 12px; font-weight: 800; letter-spacing: 2px; color: #C0001A; text-transform: uppercase; margin-bottom: 12px;
}
.hr-sb-title {
    font-size: 14px; font-weight: 700; color: #0A0A0A; margin: 0 0 8px 0;
}
.hr-sb-text {
    font-size: 12px; color: #6B7280; margin: 0; line-height: 1.6;
}
.hr-response-box {
    background: #F9F9F9; padding: 24px; border-top: 3px solid #C0001A;
}

/* --- SOCIAL LINKS --- */
.hr-social-flex { display: flex; gap: 16px; }
.hr-social-icon {
    width: 36px; height: 36px; border: 1px solid #E5E7EB; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; color: #6B7280; text-decoration: none; transition: all 0.2s ease;
}
.hr-social-icon:hover { border-color: #C0001A; color: #C0001A; }

/* --- NEWSLETTER CTA --- */
.hr-newsletter-box {
    background: #0A0A0A; padding: 24px; color: #FFFFFF;
}
.hr-nl-heading {
    font-size: 9px; font-weight: 800; letter-spacing: 2px; color: #C0001A; text-transform: uppercase; margin-bottom: 10px;
}
.hr-nl-title {
    font-size: 13px; font-weight: 700; margin: 0 0 8px 0;
}
.hr-nl-text {
    font-size: 11px; color: #9CA3AF; margin: 0 0 16px 0; line-height: 1.5;
}
.hr-nl-input {
    width: 100%; padding: 10px 12px; font-size: 12px; border: none; outline: none; margin-bottom: 10px; background: #1a1a1a; color: #FFFFFF; font-family: inherit;
}
.hr-nl-btn {
    width: 100%; background: #C0001A; color: #FFFFFF; border: none; padding: 10px; font-size: 10px; font-weight: 800; letter-spacing: 1.5px; text-transform: uppercase; cursor: pointer; font-family: inherit; transition: opacity 0.2s ease;
}
.hr-nl-btn:hover { opacity: 0.85; }

/* --- FAQ SECTION --- */
.hr-faq-section {
    background: #F9F9F9; padding: 80px 48px;
}
.hr-faq-inner {
    max-width: 1200px; margin: 0 auto;
}
.hr-faq-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 32px;
}
.hr-faq-item h3 {
    font-size: 15px; font-weight: 700; color: #0A0A0A; margin: 0 0 12px 0;
}
.hr-faq-item p {
    font-size: 13px; color: #6B7280; margin: 0; line-height: 1.6;
}

/* --- RESPONSIVE --- */
@media (max-width: 1024px) {
    .hr-hero-section, .hr-main-content, .hr-faq-section { padding-left: 24px; padding-right: 24px; }
    .hr-two-column { gap: 40px; }
}
@media (max-width: 768px) {
    .hr-hero-inner h1 { font-size: 32px; }
    .hr-two-column { grid-template-columns: 1fr; }
    .hr-sidebar { position: static; }
    .hr-hero-section, .hr-main-content, .hr-faq-section { padding: 60px 24px; }
}
</style>

<div class="hr-contact-master">

    <!-- PREMIUM BREADCRUMB -->
    <div class="es-top-nav-path">
        <a href="/">Home</a>
        <span>·</span>
        <span style="color: #6B7280;">Contact Us</span>
    </div>

    <!-- HERO SECTION -->
    <div class="hr-hero-section">
        <div class="hr-hero-inner">
            <div class="hr-eyebrow">
                <div class="hr-line"></div>
                <span>Get in Touch</span>
            </div>
            <h1>We're here to help.</h1>
            <p>Have a story to pitch, advertising inquiry, or technical issue? Our teams are ready to connect. We typically respond within 24 hours.</p>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="hr-main-content">

        <!-- CONTACT OPTIONS GRID -->
        <div class="hr-section-header">
            <div class="hr-line"></div>
            <span>Quick Access</span>
        </div>
        
        <div class="hr-qa-grid">
            <div class="hr-qa-card">
                <div class="hr-qa-icon">📰</div>
                <h3>News & Editorial</h3>
                <p>Breaking news, press releases, story pitches</p>
                <a href="mailto:editorial@headlinerecorder.com" class="hr-qa-link">editorial@headlinerecorder.com</a>
            </div>
            <div class="hr-qa-card">
                <div class="hr-qa-icon">💬</div>
                <h3>Opinion & Guest</h3>
                <p>Op-eds and thought leadership pieces</p>
                <a href="mailto:opinion@headlinerecorder.com" class="hr-qa-link">opinion@headlinerecorder.com</a>
            </div>
            <div class="hr-qa-card">
                <div class="hr-qa-icon">📢</div>
                <h3>Advertising</h3>
                <p>Sponsorships and partnerships</p>
                <a href="mailto:partnerships@headlinerecorder.com" class="hr-qa-link">partnerships@headlinerecorder.com</a>
            </div>
            <div class="hr-qa-card">
                <div class="hr-qa-icon">✉️</div>
                <h3>General Inquiry</h3>
                <p>All other inquiries and questions</p>
                <a href="mailto:contact@headlinerecorder.com" class="hr-qa-link">contact@headlinerecorder.com</a>
            </div>
        </div>

        <!-- TWO COLUMN LAYOUT -->
        <div class="hr-two-column">
            
            <!-- LEFT: CONTACT FORM -->
            <div>
                <div class="hr-section-header">
                    <div class="hr-line"></div>
                    <span>Send us a Message</span>
                </div>

                <!-- ⚠️ PASTE YOUR CONTACT FORM 7 SHORTCODE HERE ⚠️ -->
                <?php echo do_shortcode('[contact-form-7 id="554c77d" title="contact form"]'); ?>

            </div>

            <!-- RIGHT: SIDEBAR -->
            <aside class="hr-sidebar">
                
                <!-- Response Time -->
                <div class="hr-response-box">
                    <div class="hr-sb-heading" style="margin-bottom: 12px;">Response Time</div>
                    <p class="hr-sb-title">We aim to respond within 24 hours.</p>
                    <p class="hr-sb-text">Monday–Friday, 9 AM–6 PM PKT. Urgent technical issues: contact@headlinerecorder.com</p>
                </div>

                <!-- Office Info -->
                <div style="margin-bottom: 0;">
                    <div class="hr-sb-heading" style="margin-bottom: 16px;">Based in</div>
                    <p class="hr-sb-title">Karachi, Pakistan</p>
                    <p class="hr-sb-text">Headquarters for Headline Recorder's editorial, strategy, and business operations.</p>
                </div>

                <!-- Social Links -->
                <div style="margin-bottom: 0;">
                    <div class="hr-sb-heading" style="margin-bottom: 16px;">Follow us</div>
                    <div class="hr-social-flex">
                        <a href="https://www.linkedin.com/company/headlinerecorder/" class="hr-social-icon">in</a>
                        <a href="https://x.com/headlinerecoder" class="hr-social-icon">𝕏</a>
                        <a href="https://www.facebook.com/profile.php?id=61573634501379" class="hr-social-icon">fb</a>
                        <a href="https://www.instagram.com/headlinerecorder/" class="hr-social-icon">ig</a>
                    </div>
                </div>

            </aside>
        </div>
    </div>

    <!-- FAQ SECTION -->
    <div class="hr-faq-section">
        <div class="hr-faq-inner">
            
            <div class="hr-section-header">
                <div class="hr-line"></div>
                <span>FAQ</span>
            </div>

            <div class="hr-faq-grid">
                <div class="hr-faq-item">
                    <h3>How do I pitch a story?</h3>
                    <p>Email editorial@headlinerecorder.com with a brief description, sources, and why it matters to Pakistan's business ecosystem.</p>
                </div>
                <div class="hr-faq-item">
                    <h3>What's your advertising model?</h3>
                    <p>We offer sponsored content, native advertising, and partnership opportunities. Contact partnerships@headlinerecorder.com for rates and packages.</p>
                </div>
                <div class="hr-faq-item">
                    <h3>Can I republish your articles?</h3>
                    <p>Our content is copyrighted. Please contact us for syndication inquiries or license agreements.</p>
                </div>
                <div class="hr-faq-item">
                    <h3>How do I report a bug?</h3>
                    <p>Email contact@headlinerecorder.com with screenshots and steps to reproduce. Our technical team will investigate within 24 hours.</p>
                </div>
            </div>

        </div>
    </div>

</div>

<?php 
get_footer(); 
?>