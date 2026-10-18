<?php
/**
 * Template Name: Terms of Use
 */
get_header();
?>

<style>
/* ==========================================================================
   HEADLINE RECORDER - PREMIUM TERMS OF USE PAGE UI
   ========================================================================== */

/* Force theme wrappers to full width */
#primary, #main, .site-main, .content-area {
    width: 100% !important; max-width: 100% !important; float: none !important; margin: 0 !important; padding: 0 !important;
}
#secondary, .sidebar, .widget-area { display: none !important; }
.twp-breadcrumb-wrapper, .breadcrumb-trail, #breadcrumb, .breadcrumbs, .page-header { display: none !important; }

/* Enable smooth scrolling for sidebar anchor links */
html { scroll-behavior: smooth; }

/* --- MASTER CONTAINER --- */
.hr-term-master {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    background-color: #FFFFFF;
    color: #0A0A0A;
    line-height: 1.5;
    margin: 0; padding: 0;
}
.hr-term-master * { box-sizing: border-box; }

/* --- PREMIUM BREADCRUMB --- */
.es-top-nav-path {
    display: block !important; max-width: 1200px; margin: 0 auto; padding: 24px 48px; font-size: 11px; color: #9CA3AF; letter-spacing: 1px; text-transform: uppercase; background: #FFFFFF;
}
.es-top-nav-path a { color: #9CA3AF; text-decoration: none; transition: color 0.2s; }
.es-top-nav-path a:hover { color: #C0001A; }
.es-top-nav-path span { margin: 0 8px; }

/* --- HERO SECTION --- */
.hr-hero-section {
    background-color: #0A0A0A;
    background-image: radial-gradient(ellipse at 70% 50%, rgba(192,0,26,0.15) 0%, transparent 60%);
    padding: 80px 48px; position: relative; overflow: hidden;
}
.hr-hero-inner {
    max-width: 1200px; margin: 0 auto; position: relative; z-index: 1;
}
.hr-eyebrow { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; }
.hr-eyebrow .hr-line { width: 32px; height: 2px; background-color: #C0001A; }
.hr-eyebrow span { font-size: 12px; font-weight: 800; letter-spacing: 4px; color: #C0001A; text-transform: uppercase; }
.hr-hero-inner h1 {
    font-size: 48px; font-weight: 800; color: #FFFFFF; margin: 0 0 16px 0; line-height: 1.1; letter-spacing: -1px;
}
.hr-hero-inner p {
    font-size: 15px; color: #9CA3AF; margin: 0; line-height: 1.6; max-width: 720px;
}
.hr-hero-meta {
    font-size: 12px; color: #6B7280; margin: 16px 0 0 0;
}

/* --- MAIN CONTENT --- */
.hr-main-content {
    max-width: 1200px; margin: 0 auto; padding: 60px 48px; display: grid; grid-template-columns: 1fr 320px; gap: 64px;
}

/* --- SECTIONS --- */
.hr-term-section { margin-bottom: 60px; scroll-margin-top: 40px; }
.hr-term-header { display: flex; align-items: flex-start; gap: 16px; margin-bottom: 24px; }
.hr-term-number {
    width: 40px; height: 40px; background: #C0001A; color: #FFFFFF; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 18px; flex-shrink: 0; border-radius: 2px;
}
.hr-term-header-text { flex: 1; }
.hr-term-header-text h2 { font-size: 22px; font-weight: 800; color: #0A0A0A; margin: 0 0 12px 0; }
.hr-term-text { font-size: 14px; color: #6B7280; line-height: 1.7; margin: 0 0 16px 0; }

/* List styles for standard text */
.hr-term-list { margin: 0 0 16px 0; padding-left: 20px; font-size: 13px; color: #6B7280; line-height: 1.8; }
.hr-term-list li { margin-bottom: 10px; }
.hr-term-list li:last-child { margin-bottom: 0; }

/* Highlight Box */
.hr-highlight-box { background: #F9F9F9; padding: 20px; border-left: 3px solid #C0001A; margin-top: 16px; }
.hr-highlight-title { font-size: 13px; font-weight: 700; color: #0A0A0A; margin: 0 0 12px 0; text-transform: uppercase; letter-spacing: 1px; }

/* --- SIDEBAR --- */
.hr-sidebar { position: sticky; top: 24px; display: flex; flex-direction: column; gap: 0; }
.hr-sb-nav-box { background: #F9F9F9; padding: 24px; border-left: 4px solid #C0001A; }
.hr-sb-title { font-size: 12px; font-weight: 800; letter-spacing: 2px; color: #C0001A; text-transform: uppercase; margin-bottom: 16px; }
.hr-sb-nav { display: flex; flex-direction: column; gap: 0; }
.hr-sb-link {
    font-size: 12px; font-weight: 500; color: #6B7280; text-decoration: none; padding: 12px 0; border-bottom: 1px solid #E5E7EB; cursor: pointer; transition: all 0.2s;
}
.hr-sb-link:last-child { border-bottom: none; }
.hr-sb-link:hover { color: #0A0A0A; }

.hr-sb-notice { background: #0A0A0A; color: #FFFFFF; padding: 24px; margin-top: 32px; }
.hr-sb-notice p { font-size: 12px; color: #9CA3AF; line-height: 1.6; margin: 0; }

.hr-cta-btn {
    display: block; background: #C0001A; color: #FFFFFF; padding: 16px 24px; font-size: 11px; font-weight: 800; letter-spacing: 1.5px; text-transform: uppercase; text-decoration: none; text-align: center; margin-top: 32px; transition: opacity 0.2s;
}
.hr-cta-btn:hover { opacity: 0.85; color: #FFFFFF; }

/* --- RESPONSIVE --- */
@media (max-width: 1024px) {
    .es-top-nav-path, .hr-hero-section, .hr-main-content { padding-left: 24px; padding-right: 24px; }
    .hr-main-content { grid-template-columns: 1fr; gap: 40px; }
    .hr-sidebar { position: static; }
}
@media (max-width: 768px) {
    .hr-hero-inner h1 { font-size: 32px; }
    .hr-term-header-text h2 { font-size: 18px; }
    .hr-term-header { flex-direction: column; }
}
</style>

<div class="hr-term-master">

    <!-- BREADCRUMB -->
    <div class="es-top-nav-path">
        <a href="/">Home</a>
        <span>·</span>
        <span style="color: #6B7280;">Terms of Use</span>
    </div>

    <!-- HERO SECTION -->
    <div class="hr-hero-section">
        <div class="hr-hero-inner">
            <div class="hr-eyebrow">
                <div class="hr-line"></div>
                <span>Legal</span>
            </div>
            <h1>Terms of Use</h1>
            <p>Please read these terms and conditions carefully before accessing or using Headline Recorder. By accessing our website, you agree to be bound by these terms.</p>
            <p class="hr-hero-meta">Last updated: January 2026 · Effective date: January 1, 2026</p>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="hr-main-content">

        <!-- LEFT: CONTENT -->
        <div>

            <!-- INTRODUCTION -->
            <section id="intro" class="hr-term-section">
                <div class="hr-term-header">
                    <div class="hr-term-number">1</div>
                    <div class="hr-term-header-text">
                        <h2>Introduction</h2>
                        <p class="hr-term-text" style="margin-bottom: 0;">
                            By accessing or using the Headline Recorder website, you agree to be bound by the terms and conditions set forth below. These terms and conditions may be modified at any time, and such modifications shall be effective immediately upon posting on the site. You agree to review these terms periodically to remain aware of any such modifications, and your continued access or use of the site shall be deemed your conclusive acceptance of the modified agreement.
                        </p>
                    </div>
                </div>
            </section>

            <!-- COPYRIGHT AND INTELLECTUAL PROPERTY -->
            <section id="copyright" class="hr-term-section">
                <div class="hr-term-header">
                    <div class="hr-term-number">2</div>
                    <div class="hr-term-header-text">
                        <h2 style="margin-bottom: 20px;">Copyright & Intellectual Property</h2>
                        <p class="hr-term-text">
                            The entire contents of Headline Recorder (including but not limited to the "look and feel" of the site, color combinations, layout, logos, and all other graphical elements) are protected under domestic (Pakistan) and international copyright and trademark laws.
                        </p>
                        
                        <div class="hr-highlight-box">
                            <h3 class="hr-highlight-title">Key Points</h3>
                            <ul class="hr-term-list" style="margin: 0; padding-left: 20px;">
                                <li><strong style="color: #0A0A0A;">Ownership:</strong> The owners of the intellectual property, copyrights, and trademarks are the founders of Headline Recorder, its affiliates, or third party licensors.</li>
                                <li><strong style="color: #0A0A0A;">Restrictions:</strong> You may not modify, copy, reproduce, republish, upload, post, transmit, or distribute, in any manner, the material on the site, including text, graphics, code, and/or software.</li>
                                <li><strong style="color: #0A0A0A;">Personal Use:</strong> You may print and download portions of material from different areas of the site solely for your own non-commercial use, provided that you agree not to change or delete any copyright or proprietary notices.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>

            <!-- USER SUBMISSIONS AND LICENSING -->
            <section id="submissions" class="hr-term-section">
                <div class="hr-term-header">
                    <div class="hr-term-number">3</div>
                    <div class="hr-term-header-text">
                        <h2 style="margin-bottom: 20px;">User Submissions & Licensing</h2>
                        <p class="hr-term-text">
                            For any information, content, or material that you submit to public areas of Headline Recorder (such as comment sections, forums, or bulletin board or via email):
                        </p>
                        
                        <ul class="hr-term-list">
                            <li>You grant Headline Recorder a non-exclusive, royalty-free, worldwide, perpetual license to reproduce, distribute, transmit, create derivative works of and publicly display said material.</li>
                            <li>You grant Headline Recorder the right to use your name in connection with the submitted materials for marketing or promotional purposes.</li>
                            <li>Copyright Infringement: If you believe any content on Headline Recorder constitutes a copyright infringement of your rights, you agree to contact us and allow reasonable time to investigate and remove the alleged infringement if justified.</li>
                        </ul>
                    </div>
                </div>
            </section>

            <!-- NO COMMERCIAL USE OR SCRAPING -->
            <section id="commercial" class="hr-term-section">
                <div class="hr-term-header">
                    <div class="hr-term-number">4</div>
                    <div class="hr-term-header-text">
                        <h2 style="margin-bottom: 20px;">Commercial Use & Scraping</h2>
                        
                        <ul class="hr-term-list">
                            <li><strong style="color: #0A0A0A;">Commercial Use:</strong> Any commercial use of Headline Recorder content is strictly prohibited without express written permission.</li>
                            <li><strong style="color: #0A0A0A;">Automated Access:</strong> You agree not to use any robot, spider, scraper, or other automated devices to monitor or copy our web pages or content for any purpose (including training AI models) without our prior express written permission.</li>
                            <li><strong style="color: #0A0A0A;">Rate Limits:</strong> Even with permission, automated access must respect rate limits and not interfere with the site's performance or user experience.</li>
                        </ul>
                    </div>
                </div>
            </section>

            <!-- DISCLAIMER OF WARRANTIES -->
            <section id="disclaimer" class="hr-term-section">
                <div class="hr-term-header">
                    <div class="hr-term-number">5</div>
                    <div class="hr-term-header-text">
                        <h2 style="margin-bottom: 20px;">Disclaimer of Warranties</h2>
                        <p class="hr-term-text">
                            All information posted on Headline Recorder is obtained from sources believed to be reliable. However:
                        </p>
                        
                        <ul class="hr-term-list">
                            <li>We do not make any representation or warranty that the information provided is absolutely accurate or complete.</li>
                            <li>Headline Recorder will not be responsible for any error of fact, opinion, or recommendation, nor for any financial or other results resulting from business, trade, or investments made based on the information posted here.</li>
                            <li>We do not warrant that files available for download will be free of viruses, worms, or other destructive code. You are responsible for maintaining your own data protection and reconstruction procedures.</li>
                        </ul>
                    </div>
                </div>
            </section>

            <!-- VISITOR RESTRICTIONS -->
            <section id="restrictions" class="hr-term-section">
                <div class="hr-term-header">
                    <div class="hr-term-number">6</div>
                    <div class="hr-term-header-text">
                        <h2 style="margin-bottom: 20px;">Visitor Restrictions</h2>
                        <p class="hr-term-text">
                            In connection with your use of the site, you agree you will not:
                        </p>
                        
                        <ul class="hr-term-list">
                            <li>Transmit any content that is unlawful, harmful, threatening, abusive, harassing, defamatory, vulgar, obscene, or libelous.</li>
                            <li>Impersonate any person or entity, including officials or hosts of Headline Recorder.</li>
                            <li>Post unsolicited advertising, "junk mail," "spam" or any other form of commercial solicitation.</li>
                            <li>Violate any applicable local, provincial, national, or international law.</li>
                        </ul>
                    </div>
                </div>
            </section>

            <!-- INDEMNIFICATION -->
            <section id="indemnification" class="hr-term-section" style="margin-bottom: 0;">
                <div class="hr-term-header">
                    <div class="hr-term-number">7</div>
                    <div class="hr-term-header-text">
                        <h2>Indemnification</h2>
                        <p class="hr-term-text" style="margin-bottom: 0;">
                            By using this website, you agree to indemnify, defend, and hold harmless the editors, writers, designers, owners, directors, and employees of Headline Recorder against all losses, expenses, damages, and costs (including attorneys' fees) arising from your use of the site or any violation of this agreement.
                        </p>
                    </div>
                </div>
            </section>

        </div>

        <!-- RIGHT: SIDEBAR -->
        <aside class="hr-sidebar">
            
            <div class="hr-sb-nav-box">
                <div class="hr-sb-title">Table of Contents</div>
                <nav class="hr-sb-nav">
                    <a href="#intro" class="hr-sb-link">Introduction</a>
                    <a href="#copyright" class="hr-sb-link">Copyright & IP</a>
                    <a href="#submissions" class="hr-sb-link">User Submissions</a>
                    <a href="#commercial" class="hr-sb-link">Commercial Use</a>
                    <a href="#disclaimer" class="hr-sb-link">Disclaimer</a>
                    <a href="#restrictions" class="hr-sb-link">Restrictions</a>
                    <a href="#indemnification" class="hr-sb-link">Indemnification</a>
                    <a href="#liability" class="hr-sb-link">Liability</a>
                    <a href="#privacy" class="hr-sb-link">Privacy</a>
                    <a href="#contact" class="hr-sb-link">Contact</a>
                </nav>
            </div>

            <div class="hr-sb-notice">
                <div class="hr-sb-title">Important Notice</div>
                <p>
                    These terms may be updated without notice. Continue accessing the site constitutes acceptance of changes. For questions, contact contact@headlinerecorder.com
                </p>
            </div>

            <a href="mailto:contact@headlinerecorder.com" class="hr-cta-btn">Questions? Contact Us</a>

        </aside>

    </div>
</div>

<?php 
get_footer(); 
?>