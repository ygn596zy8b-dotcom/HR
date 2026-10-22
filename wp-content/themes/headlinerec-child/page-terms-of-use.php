<?php
/**
 * Template Name: Terms of Use
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$hr_org   = function_exists( 'HR\\Platform\\Site\\org' ) ? \HR\Platform\Site\org() : array();
$hr_email = $hr_org['email'] ?? 'contact@headlinerecorder.com';

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
@media (prefers-reduced-motion: reduce) { html { scroll-behavior: auto; } }

/* --- MASTER CONTAINER --- */
.hr-term-master {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    background-color: #FFFFFF;
    color: #0A0A0A;
    line-height: 1.5;
    margin: 0; padding: 0;
    overflow-wrap: break-word;
}
.hr-term-master * { box-sizing: border-box; }
.hr-term-master a { color: #C0001A; text-decoration: underline; text-underline-offset: 2px; }
.hr-term-master a:hover { color: #0A0A0A; }
.hr-term-master a:focus-visible { outline: 2px solid #C0001A; outline-offset: 2px; }

/* --- PREMIUM BREADCRUMB --- */
.es-top-nav-path {
    display: block !important; max-width: 1200px; margin: 0 auto; padding: 14px 48px; font-size: 12px; color: #6B7280; letter-spacing: 1px; text-transform: uppercase; background: #FFFFFF;
}
.hr-term-master .es-top-nav-path a { display: inline-flex; align-items: center; min-height: 44px; color: #6B7280; text-decoration: none; transition: color 0.2s; }
.hr-term-master .es-top-nav-path a:hover { color: #C0001A; }
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
.hr-eyebrow span { font-size: 12px; font-weight: 800; letter-spacing: 4px; color: #FF3B52; text-transform: uppercase; }
.hr-hero-inner h1 {
    font-size: 48px; font-weight: 800; color: #FFFFFF; margin: 0 0 16px 0; line-height: 1.1; letter-spacing: -1px;
}
.hr-hero-inner p {
    font-size: 17px; color: #B4B4BB; margin: 0; line-height: 1.6; max-width: 720px;
}
.hr-hero-inner .hr-hero-meta {
    font-size: 14px; color: #9CA3AF; margin: 16px 0 0 0;
}

/* --- MAIN CONTENT --- */
.hr-main-content {
    max-width: 1200px; margin: 0 auto; padding: 60px 48px; display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 64px;
}

/* --- SECTIONS --- */
.hr-term-section { margin-bottom: 60px; scroll-margin-top: 40px; }
.hr-term-header { display: flex; align-items: flex-start; gap: 16px; margin-bottom: 24px; }
.hr-term-number {
    width: 40px; height: 40px; background: #C0001A; color: #FFFFFF; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 18px; flex-shrink: 0; border-radius: 2px;
}
.hr-term-header-text { flex: 1; min-width: 0; }
.hr-term-header-text h2 { font-size: 22px; font-weight: 800; color: #0A0A0A; margin: 0 0 12px 0; }
.hr-term-text { font-size: 16px; color: #4B5563; line-height: 1.7; margin: 0 0 16px 0; }
.hr-term-text strong, .hr-term-list strong { color: #0A0A0A; }

/* List styles for standard text */
.hr-term-list { margin: 0 0 16px 0; padding-left: 20px; font-size: 16px; color: #4B5563; line-height: 1.7; }
.hr-term-list li { margin-bottom: 10px; }
.hr-term-list li:last-child { margin-bottom: 0; }

/* Highlight Box */
.hr-highlight-box { background: #F9F9F9; padding: 20px; border-left: 3px solid #C0001A; margin-top: 16px; }
.hr-highlight-title { font-size: 13px; font-weight: 700; color: #0A0A0A; margin: 0 0 12px 0; text-transform: uppercase; letter-spacing: 1px; }

/* --- SIDEBAR --- */
.hr-sidebar { position: sticky; top: 24px; align-self: start; display: flex; flex-direction: column; gap: 0; }
.hr-sb-nav-box { background: #F9F9F9; padding: 24px; border-left: 4px solid #C0001A; }
.hr-sb-title { font-size: 12px; font-weight: 800; letter-spacing: 2px; color: #C0001A; text-transform: uppercase; margin-bottom: 12px; }
.hr-sb-nav { display: flex; flex-direction: column; gap: 0; }
.hr-term-master .hr-sb-link {
    display: flex; align-items: center; min-height: 44px; font-size: 14px; font-weight: 500; color: #4B5563; text-decoration: none; padding: 8px 0; border-bottom: 1px solid #E5E7EB; cursor: pointer; transition: all 0.2s;
}
.hr-sb-link:last-child { border-bottom: none; }
.hr-term-master .hr-sb-link:hover { color: #0A0A0A; }

.hr-sb-notice .hr-sb-title { color: #FF001C; } /* red on near-black needs the bright red */
.hr-sb-notice { background: #0A0A0A; color: #FFFFFF; padding: 24px; margin-top: 32px; }
.hr-sb-notice p { font-size: 16px; color: #B4B4BB; line-height: 1.6; margin: 0; }
.hr-term-master .hr-sb-notice a { color: #FFFFFF; }

.hr-term-master .hr-cta-btn {
    display: block; background: #C0001A; color: #FFFFFF; padding: 16px 24px; font-size: 13px; font-weight: 800; letter-spacing: 1.5px; text-transform: uppercase; text-decoration: none; text-align: center; margin-top: 32px; transition: opacity 0.2s;
}
.hr-term-master .hr-cta-btn:hover { opacity: 0.85; color: #FFFFFF; }

/* --- RESPONSIVE --- */
@media (max-width: 1024px) {
    .es-top-nav-path, .hr-hero-section, .hr-main-content { padding-left: max(24px, env(safe-area-inset-left)); padding-right: max(24px, env(safe-area-inset-right)); }
    .hr-main-content { grid-template-columns: minmax(0, 1fr); gap: 40px; }
    .hr-sidebar { position: static; }
}
@media (max-width: 768px) {
    .es-top-nav-path { padding-top: 6px; padding-bottom: 6px; }
    .hr-hero-section { padding-top: 56px; padding-bottom: 56px; }
    .hr-hero-inner h1 { font-size: 34px; }
    .hr-main-content { padding-top: 40px; padding-bottom: 48px; }
    .hr-term-section { margin-bottom: 48px; }
    .hr-term-header-text h2 { font-size: 20px; }
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
            <p class="hr-hero-meta">Last updated: October 2026</p>
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
                            By accessing or using the Headline Recorder website, creating an account or sending us a submission, you agree to be bound by the terms and conditions set forth below. If you do not agree, please do not use the site. How we handle personal data is explained in our <a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>">Privacy Policy</a>.
                        </p>
                    </div>
                </div>
            </section>

            <!-- COPYRIGHT AND INTELLECTUAL PROPERTY -->
            <section id="copyright" class="hr-term-section">
                <div class="hr-term-header">
                    <div class="hr-term-number">2</div>
                    <div class="hr-term-header-text">
                        <h2 style="margin-bottom: 20px;">Copyright &amp; Intellectual Property</h2>
                        <p class="hr-term-text">
                            The entire contents of Headline Recorder (including but not limited to the "look and feel" of the site, color combinations, layout, logos, and all other graphical elements) are protected under domestic (Pakistan) and international copyright and trademark laws.
                        </p>

                        <div class="hr-highlight-box">
                            <h3 class="hr-highlight-title">Key Points</h3>
                            <ul class="hr-term-list" style="margin: 0; padding-left: 20px;">
                                <li><strong>Ownership:</strong> The owners of the intellectual property, copyrights, and trademarks are the founders of Headline Recorder, its affiliates, or third party licensors. Writers whose pieces we publish keep the copyright in their own work (see Submissions).</li>
                                <li><strong>Restrictions:</strong> You may not modify, copy, reproduce, republish, upload, post, transmit, or distribute, in any manner, the material on the site, including text, graphics, code, and/or software.</li>
                                <li><strong>Personal Use:</strong> You may print and download portions of material from different areas of the site solely for your own non-commercial use, provided that you agree not to change or delete any copyright or proprietary notices. Sharing a link, a headline or a short quotation with a link back is always welcome.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ACCOUNTS -->
            <section id="accounts" class="hr-term-section">
                <div class="hr-term-header">
                    <div class="hr-term-number">3</div>
                    <div class="hr-term-header-text">
                        <h2 style="margin-bottom: 20px;">Accounts</h2>
                        <p class="hr-term-text">An account is free and optional. If you create one:</p>
                        <ul class="hr-term-list" style="margin-bottom: 0;">
                            <li><strong>One per person.</strong> An account is for one individual. Do not share it or create accounts for other people.</li>
                            <li><strong>Accurate information.</strong> Use your real name and an email address you control, and keep them up to date.</li>
                            <li><strong>Keep your credentials secure.</strong> Keep your password, passkeys and recovery codes private, and tell us straight away if you think someone else has used your account. We recommend turning on two-step verification.</li>
                            <li><strong>Abuse.</strong> We may suspend or close an account that breaks these terms, is used to attack the site or other people, or was created automatically.</li>
                        </ul>
                    </div>
                </div>
            </section>

            <!-- READING STREAKS -->
            <section id="streaks" class="hr-term-section">
                <div class="hr-term-header">
                    <div class="hr-term-number">4</div>
                    <div class="hr-term-header-text">
                        <h2 style="margin-bottom: 20px;">Reading Streaks</h2>
                        <p class="hr-term-text" style="margin-bottom: 0;">
                            Reading streaks are a motivational feature to help you build a daily reading habit. Streaks, counts and badges have no monetary value, cannot be exchanged or transferred, and may be adjusted if we change how they are calculated or find they were inflated by automated reading. We may change or discontinue the feature.
                        </p>
                    </div>
                </div>
            </section>

            <!-- SUBMISSIONS AND LICENSING -->
            <section id="submissions" class="hr-term-section">
                <div class="hr-term-header">
                    <div class="hr-term-number">5</div>
                    <div class="hr-term-header-text">
                        <h2 style="margin-bottom: 20px;">Submissions &amp; Licensing</h2>
                        <p class="hr-term-text">
                            Signed-in readers can submit articles and op-eds for publication. Each submission needs your headshot, a short intro, your title or designation, your email address, the headline, the type, a category and the text. <strong>Op-eds should be 800–1,200 words; articles at most 1,500 words.</strong>
                        </p>
                        <ul class="hr-term-list">
                            <li><strong>Originality:</strong> Your piece must be your own original work, not published elsewhere (including on your own blog or social media), and must not infringe anyone else’s rights. Credit your sources, tell us about any conflict of interest, and tell us if you used AI tools and how.</li>
                            <li><strong>Licence on acceptance:</strong> You keep the copyright. When we accept a piece, you grant Headline Recorder a non-exclusive, worldwide, royalty-free, perpetual licence to publish, edit, adapt, translate, sign, archive and distribute it, with your byline, headshot and intro, in any format: on the site, in our newsletter, the Podcast, feeds and social media. You may republish it elsewhere after it appears with us, with a credit and link to the original.</li>
                            <li><strong>Editing:</strong> Editors may edit for length, clarity, accuracy, style and legal reasons, and choose the headline and images. We will check substantial changes with you where time allows.</li>
                            <li><strong>No guarantee of publication:</strong> Submitting a piece does not mean we will publish it, and we cannot give detailed feedback on every piece. We do not pay for submissions unless we have agreed so in writing.</li>
                            <li><strong>Response time:</strong> We aim to reply within 10 working days. If you have not heard from us by then, you are free to offer the piece elsewhere; please let us know.</li>
                            <li><strong>Withdrawal:</strong> You can withdraw a piece before an editor starts reviewing it by emailing <a href="<?php echo esc_url( 'mailto:' . $hr_email ); ?>"><?php echo esc_html( $hr_email ); ?></a>. After that, ask us and we will do our best to accommodate you.</li>
                        </ul>
                        <p class="hr-term-text" style="margin-bottom: 0;">
                            For other material you post or send us (comments, emails, pitches), you grant Headline Recorder a non-exclusive, royalty-free licence to use it in connection with our journalism. Copyright Infringement: If you believe any content on Headline Recorder constitutes a copyright infringement of your rights, contact us and allow reasonable time to investigate and remove the alleged infringement if justified.
                        </p>
                    </div>
                </div>
            </section>

            <!-- NO COMMERCIAL USE OR SCRAPING -->
            <section id="commercial" class="hr-term-section">
                <div class="hr-term-header">
                    <div class="hr-term-number">6</div>
                    <div class="hr-term-header-text">
                        <h2 style="margin-bottom: 20px;">Commercial Use &amp; Scraping</h2>

                        <ul class="hr-term-list" style="margin-bottom: 0;">
                            <li><strong>Commercial Use:</strong> Any commercial use of Headline Recorder content is strictly prohibited without express written permission.</li>
                            <li><strong>Automated Access:</strong> Robots, crawlers and scrapers may access the site only as our <a href="<?php echo esc_url( home_url( '/robots.txt' ) ); ?>">robots.txt</a> allows (for example, search engines and the AI search and citation services listed there). Any other automated copying or monitoring of our pages needs our prior written permission.</li>
                            <li><strong>Rate Limits:</strong> Even with permission, automated access must respect rate limits and not interfere with the site's performance or user experience.</li>
                        </ul>
                    </div>
                </div>
            </section>

            <!-- DISCLAIMER OF WARRANTIES -->
            <section id="disclaimer" class="hr-term-section">
                <div class="hr-term-header">
                    <div class="hr-term-number">7</div>
                    <div class="hr-term-header-text">
                        <h2 style="margin-bottom: 20px;">Disclaimer of Warranties</h2>
                        <p class="hr-term-text">
                            All information posted on Headline Recorder is obtained from sources believed to be reliable. However:
                        </p>

                        <ul class="hr-term-list" style="margin-bottom: 0;">
                            <li>We do not make any representation or warranty that the information provided is absolutely accurate or complete.</li>
                            <li>Headline Recorder will not be responsible for any error of fact, opinion, or recommendation, nor for any financial or other results resulting from business, trade, or investments made based on the information posted here.</li>
                            <li>Opinion pieces and op-eds reflect the views of their authors, not necessarily those of Headline Recorder.</li>
                            <li>We do not warrant that files available for download will be free of viruses, worms, or other destructive code. You are responsible for maintaining your own data protection and reconstruction procedures.</li>
                        </ul>
                    </div>
                </div>
            </section>

            <!-- ACCEPTABLE USE -->
            <section id="restrictions" class="hr-term-section">
                <div class="hr-term-header">
                    <div class="hr-term-number">8</div>
                    <div class="hr-term-header-text">
                        <h2 style="margin-bottom: 20px;">Acceptable Use</h2>
                        <p class="hr-term-text">
                            In connection with your use of the site, you agree you will not:
                        </p>

                        <ul class="hr-term-list" style="margin-bottom: 0;">
                            <li>Transmit any content that is unlawful, harmful, threatening, abusive, harassing, defamatory, vulgar, obscene, or libelous.</li>
                            <li>Impersonate any person or entity, including officials or hosts of Headline Recorder.</li>
                            <li>Post unsolicited advertising, "junk mail," "spam" or any other form of commercial solicitation.</li>
                            <li>Scrape or copy the site beyond what our robots.txt allows.</li>
                            <li>Attack or probe the site, for example: try to break in, guess passwords, overload it, get around security or rate limits, or test for vulnerabilities without our written permission. (Found a security issue? Please email us first.)</li>
                            <li>Create accounts automatically or in bulk, or use bots to submit forms, inflate reading streaks or send submissions.</li>
                            <li>Violate any applicable local, provincial, national, or international law.</li>
                        </ul>
                    </div>
                </div>
            </section>

            <!-- INDEMNIFICATION -->
            <section id="indemnification" class="hr-term-section">
                <div class="hr-term-header">
                    <div class="hr-term-number">9</div>
                    <div class="hr-term-header-text">
                        <h2>Indemnification</h2>
                        <p class="hr-term-text" style="margin-bottom: 0;">
                            By using this website, you agree to indemnify, defend, and hold harmless the editors, writers, designers, owners, directors, and employees of Headline Recorder against all losses, expenses, damages, and costs (including attorneys' fees) arising from your use of the site or any violation of this agreement.
                        </p>
                    </div>
                </div>
            </section>

            <!-- LIABILITY -->
            <section id="liability" class="hr-term-section">
                <div class="hr-term-header">
                    <div class="hr-term-number">10</div>
                    <div class="hr-term-header-text">
                        <h2>Limitation of Liability</h2>
                        <p class="hr-term-text" style="margin-bottom: 0;">
                            To the extent the law allows, Headline Recorder is not liable for any indirect or consequential loss, or for loss of profits, data or business opportunity, arising from your use of the site, our content or any interruption of the service. Nothing in these terms limits liability that cannot be limited by law. These terms are governed by the laws of Pakistan.
                        </p>
                    </div>
                </div>
            </section>

            <!-- TERMINATION -->
            <section id="termination" class="hr-term-section">
                <div class="hr-term-header">
                    <div class="hr-term-number">11</div>
                    <div class="hr-term-header-text">
                        <h2>Termination</h2>
                        <p class="hr-term-text" style="margin-bottom: 0;">
                            You can stop using the site and delete your account at any time from your account settings. We may suspend or end your access (including your account) if you break these terms or misuse the site, and we will tell you why unless the law or the security of the site prevents it. The licence you gave us for pieces we have already published continues after your account ends.
                        </p>
                    </div>
                </div>
            </section>

            <!-- PRIVACY -->
            <section id="privacy" class="hr-term-section">
                <div class="hr-term-header">
                    <div class="hr-term-number">12</div>
                    <div class="hr-term-header-text">
                        <h2>Privacy</h2>
                        <p class="hr-term-text" style="margin-bottom: 0;">
                            Our <a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>">Privacy Policy</a> explains what we collect when you read, subscribe, create an account or submit a piece, how submissions are encrypted, and how to export or delete your data.
                        </p>
                    </div>
                </div>
            </section>

            <!-- CHANGES -->
            <section id="changes" class="hr-term-section">
                <div class="hr-term-header">
                    <div class="hr-term-number">13</div>
                    <div class="hr-term-header-text">
                        <h2>Changes to These Terms</h2>
                        <p class="hr-term-text" style="margin-bottom: 0;">
                            We may update these terms from time to time. We will change the date at the top of this page and, for significant changes that affect account holders or writers, tell you by email or on the site before they take effect. If you keep using the site after that, the new terms apply.
                        </p>
                    </div>
                </div>
            </section>

            <!-- CONTACT -->
            <section id="contact" class="hr-term-section" style="margin-bottom: 0;">
                <div class="hr-term-header">
                    <div class="hr-term-number">14</div>
                    <div class="hr-term-header-text">
                        <h2>Contact</h2>
                        <p class="hr-term-text" style="margin-bottom: 0;">
                            Questions about these terms: <a href="<?php echo esc_url( 'mailto:' . $hr_email ); ?>"><?php echo esc_html( $hr_email ); ?></a>.
                        </p>
                    </div>
                </div>
            </section>

        </div>

        <!-- RIGHT: SIDEBAR -->
        <aside class="hr-sidebar" aria-label="Terms of use contents">

            <div class="hr-sb-nav-box">
                <div class="hr-sb-title">Table of Contents</div>
                <nav class="hr-sb-nav" aria-label="Sections">
                    <a href="#intro" class="hr-sb-link">Introduction</a>
                    <a href="#copyright" class="hr-sb-link">Copyright &amp; IP</a>
                    <a href="#accounts" class="hr-sb-link">Accounts</a>
                    <a href="#streaks" class="hr-sb-link">Reading Streaks</a>
                    <a href="#submissions" class="hr-sb-link">Submissions</a>
                    <a href="#commercial" class="hr-sb-link">Commercial Use</a>
                    <a href="#disclaimer" class="hr-sb-link">Disclaimer</a>
                    <a href="#restrictions" class="hr-sb-link">Acceptable Use</a>
                    <a href="#indemnification" class="hr-sb-link">Indemnification</a>
                    <a href="#liability" class="hr-sb-link">Liability</a>
                    <a href="#termination" class="hr-sb-link">Termination</a>
                    <a href="#privacy" class="hr-sb-link">Privacy</a>
                    <a href="#changes" class="hr-sb-link">Changes</a>
                    <a href="#contact" class="hr-sb-link">Contact</a>
                </nav>
            </div>

            <div class="hr-sb-notice">
                <div class="hr-sb-title">Important Notice</div>
                <p>
                    We may update these terms; the date at the top shows the latest version, and we tell account holders about significant changes in advance. For questions, contact <a href="<?php echo esc_url( 'mailto:' . $hr_email ); ?>"><?php echo esc_html( $hr_email ); ?></a>.
                </p>
            </div>

            <a href="<?php echo esc_url( 'mailto:' . $hr_email ); ?>" class="hr-cta-btn">Questions? Contact Us</a>

        </aside>

    </div>
</div>

<?php
get_footer();
