<?php
/**
 * Template Name: Privacy Policy
 */
get_header();
?>

<style>
/* ==========================================================================
   HEADLINE RECORDER - PREMIUM PRIVACY POLICY PAGE UI
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
.hr-priv-master {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    background-color: #FFFFFF;
    color: #0A0A0A;
    line-height: 1.5;
    margin: 0; padding: 0;
}
.hr-priv-master * { box-sizing: border-box; }

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
.hr-priv-section { margin-bottom: 60px; scroll-margin-top: 40px; }
.hr-priv-header { display: flex; align-items: flex-start; gap: 16px; margin-bottom: 24px; }
.hr-priv-number {
    width: 40px; height: 40px; background: #C0001A; color: #FFFFFF; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 18px; flex-shrink: 0; border-radius: 2px;
}
.hr-priv-header-text { flex: 1; }
.hr-priv-header-text h2 { font-size: 22px; font-weight: 800; color: #0A0A0A; margin: 0 0 12px 0; }
.hr-priv-text { font-size: 14px; color: #6B7280; line-height: 1.7; margin: 0 0 16px 0; }

/* --- ALERT BOXES --- */
.hr-alert { padding: 16px; margin-top: 16px; }
.hr-alert-title { font-size: 13px; font-weight: 700; margin: 0 0 12px 0; text-transform: uppercase; letter-spacing: 1px; }
.hr-alert-text { font-size: 12px; margin: 0; line-height: 1.6; }
.hr-alert-list { margin: 0; padding-left: 20px; font-size: 13px; line-height: 1.7; }
.hr-alert-list li { margin-bottom: 8px; }
.hr-alert-list li:last-child { margin-bottom: 0; }

/* Specific Alerts */
.hr-alert-gray { background: #F9F9F9; border-left: 3px solid #C0001A; color: #6B7280; padding: 20px; }
.hr-alert-gray .hr-alert-title { color: #0A0A0A; }
.hr-alert-yellow { background: #FFF3CD; border: 1px solid #FFE69C; border-left: 3px solid #FF9800; color: #856404; }
.hr-alert-yellow .hr-alert-title { color: #856404; font-size: 12px; }
.hr-alert-green { background: #E8F5E9; border: 1px solid #C8E6C9; border-left: 3px solid #4CAF50; color: #1B5E20; }
.hr-alert-green .hr-alert-title { color: #1B5E20; font-size: 12px; }

/* List styles for standard text */
.hr-priv-list { margin: 0 0 16px 0; padding-left: 20px; font-size: 13px; color: #6B7280; line-height: 1.8; }
.hr-priv-list li { margin-bottom: 10px; }
.hr-priv-list li:last-child { margin-bottom: 0; }

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

.hr-sb-key-points { background: #0A0A0A; color: #FFFFFF; padding: 24px; margin-top: 32px; }
.hr-sb-key-points ul { margin: 0; padding-left: 14px; font-size: 11px; color: #9CA3AF; line-height: 1.7; }
.hr-sb-key-points li { margin-bottom: 10px; }
.hr-sb-key-points li:last-child { margin-bottom: 0; }

.hr-cta-btn {
    display: block; background: #C0001A; color: #FFFFFF; padding: 16px 24px; font-size: 11px; font-weight: 800; letter-spacing: 1.5px; text-transform: uppercase; text-decoration: none; text-align: center; margin-top: 32px; transition: opacity 0.2s;
}
.hr-cta-btn:hover { opacity: 0.85; color: #FFFFFF; }

/* --- RESPONSIVE --- */
@media (max-width: 1024px) {
    .hr-hero-section, .hr-main-content { padding-left: 24px; padding-right: 24px; }
    .hr-main-content { grid-template-columns: 1fr; gap: 40px; }
    .hr-sidebar { position: static; }
}
@media (max-width: 768px) {
    .hr-hero-inner h1 { font-size: 32px; }
    .hr-priv-header-text h2 { font-size: 18px; }
    .hr-priv-header { flex-direction: column; }
}
</style>

<div class="hr-priv-master">

    <!-- BREADCRUMB -->
    <div class="es-top-nav-path">
        <a href="/">Home</a>
        <span>·</span>
        <span style="color: #6B7280;">Privacy Policy</span>
    </div>

    <!-- HERO SECTION -->
    <div class="hr-hero-section">
        <div class="hr-hero-inner">
            <div class="hr-eyebrow">
                <div class="hr-line"></div>
                <span>Privacy</span>
            </div>
            <h1>Privacy Policy</h1>
            <p>At Headline Recorder, your privacy is paramount. We're transparent about how we collect, use, and protect your data. This policy explains our practices in clear, human language.</p>
            <p class="hr-hero-meta">Last updated: March 2026 · Effective date: January 1, 2026</p>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="hr-main-content">

        <!-- LEFT: CONTENT -->
        <div>

            <!-- INTRODUCTION -->
            <section id="intro" class="hr-priv-section">
                <div class="hr-priv-header">
                    <div class="hr-priv-number">1</div>
                    <div class="hr-priv-header-text">
                        <h2>Introduction</h2>
                        <p class="hr-priv-text" style="margin-bottom: 0;">
                            We believe in transparency. This Privacy Policy explains how Headline Recorder collects, uses, discloses, and safeguards your information when you visit our website. Please read this policy carefully. If you do not agree with our policies and practices, please do not use our site.
                        </p>
                    </div>
                </div>
            </section>

            <!-- DATA WE COLLECT -->
            <section id="data" class="hr-priv-section">
                <div class="hr-priv-header">
                    <div class="hr-priv-number">2</div>
                    <div class="hr-priv-header-text">
                        <h2 style="margin-bottom: 20px;">Data We Collect Automatically</h2>
                        <p class="hr-priv-text">
                            Like most websites, Headline Recorder gathers non-identifying information automatically. This helps us understand how our visitors use the site and optimize performance.
                        </p>
                        
                        <div class="hr-alert hr-alert-gray">
                            <p class="hr-alert-title">Collected Data</p>
                            <ul class="hr-alert-list">
                                <li>Browser type and version</li>
                                <li>Operating system and device type</li>
                                <li>Referring site and landing page</li>
                                <li>Pages visited and time spent</li>
                                <li>Date and time of each request</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ANALYTICS -->
            <section id="analytics" class="hr-priv-section">
                <div class="hr-priv-header">
                    <div class="hr-priv-number">3</div>
                    <div class="hr-priv-header-text">
                        <h2 style="margin-bottom: 20px;">Third-Party Analytics</h2>
                        <p class="hr-priv-text">
                            We use Google Analytics (via Google Site Kit) to track visitor behavior and understand how users navigate our site. Google processes this data according to their own privacy policies.
                        </p>
                        
                        <div class="hr-alert hr-alert-yellow">
                            <p class="hr-alert-title">💡 You Have Control</p>
                            <p class="hr-alert-text">
                                You can opt-out of Google Analytics by installing the <a href="#" style="color: #FF9800; text-decoration: underline;">Google Analytics Opt-Out Browser Add-on</a>. This prevents your data from being sent to Google Analytics.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- INFORMATION YOU PROVIDE -->
            <section id="provided" class="hr-priv-section">
                <div class="hr-priv-header">
                    <div class="hr-priv-number">4</div>
                    <div class="hr-priv-header-text">
                        <h2 style="margin-bottom: 20px;">Information You Provide</h2>
                        <p class="hr-priv-text">
                            Headline Recorder does not currently require user accounts. However, when you voluntarily contact us, we collect information such as:
                        </p>
                        
                        <ul class="hr-priv-list">
                            <li>Name and email address (when you contact us)</li>
                            <li>Comments on our articles or forums</li>
                            <li>Newsletter subscription information</li>
                            <li>Story pitches and inquiry details</li>
                        </ul>

                        <p class="hr-priv-text" style="margin-bottom: 0;">
                            <strong style="color: #0A0A0A;">We use this information only to:</strong> respond to your inquiries, send newsletters you've subscribed to, and improve our services. We never sell your data to third parties.
                        </p>
                    </div>
                </div>
            </section>

            <!-- PUBLIC INTERACTIONS -->
            <section id="public" class="hr-priv-section">
                <div class="hr-priv-header">
                    <div class="hr-priv-number">5</div>
                    <div class="hr-priv-header-text">
                        <h2 style="margin-bottom: 20px;">Public Interactions</h2>
                        <p class="hr-priv-text">
                            If you post comments on our articles or participate in our forums, that information becomes public. We cannot control how others use that information, so please be cautious about sharing personal details publicly.
                        </p>
                        
                        <div class="hr-alert hr-alert-gray" style="padding: 16px;">
                            <p class="hr-alert-text">
                                <strong style="color: #0A0A0A;">Example:</strong> If you leave a comment with your name, that comment and your name will be visible to all site visitors. Avoid sharing sensitive information in public sections.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- AI TRANSPARENCY -->
            <section id="ai" class="hr-priv-section">
                <div class="hr-priv-header">
                    <div class="hr-priv-number">6</div>
                    <div class="hr-priv-header-text">
                        <h2 style="margin-bottom: 20px;">AI Transparency</h2>
                        <p class="hr-priv-text">
                            To ensure timely news coverage, Headline Recorder may utilize AI-assisted technologies for content summarization or optimization. All AI-assisted content is reviewed by human editors to maintain our standards of accuracy and integrity.
                        </p>
                        
                        <div class="hr-alert hr-alert-green">
                            <p class="hr-alert-title">🤖 How We Use AI</p>
                            <ul class="hr-alert-list" style="font-size: 12px;">
                                <li>Content summarization and headline optimization</li>
                                <li>Automated fact-checking and context enhancement</li>
                                <li>All AI output reviewed by human editors before publication</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>

            <!-- LEGAL DISCLOSURE -->
            <section id="disclosure" class="hr-priv-section">
                <div class="hr-priv-header">
                    <div class="hr-priv-number">7</div>
                    <div class="hr-priv-header-text">
                        <h2 style="margin-bottom: 20px;">Legal Disclosure</h2>
                        <p class="hr-priv-text" style="margin-bottom: 0;">
                            We will only share your personal data if required to do so by a competent court of law or an enforceable governmental request. We will notify you of any such disclosure unless legally prohibited from doing so.
                        </p>
                    </div>
                </div>
            </section>

            <!-- YOUR RIGHTS -->
            <section id="rights" class="hr-priv-section">
                <div class="hr-priv-header">
                    <div class="hr-priv-number">8</div>
                    <div class="hr-priv-header-text">
                        <h2 style="margin-bottom: 20px;">Your Rights</h2>
                        <p class="hr-priv-text">You have the right to:</p>
                        
                        <ul class="hr-priv-list">
                            <li>Request access to the data we hold about you</li>
                            <li>Request correction of inaccurate data</li>
                            <li>Request deletion of your data</li>
                            <li>Opt out of marketing communications at any time</li>
                            <li>Port your data to another service</li>
                        </ul>

                        <p class="hr-priv-text" style="margin-bottom: 0;">
                            To exercise any of these rights, please contact us at <strong style="color: #0A0A0A;">contact@headlinerecorder.com</strong>
                        </p>
                    </div>
                </div>
            </section>

            <!-- COOKIES -->
            <section id="cookies" class="hr-priv-section" style="margin-bottom: 0;">
                <div class="hr-priv-header">
                    <div class="hr-priv-number">9</div>
                    <div class="hr-priv-header-text">
                        <h2 style="margin-bottom: 20px;">Cookies & Tracking</h2>
                        <p class="hr-priv-text">
                            Our website uses cookies to enhance your experience. These small files help us remember your preferences and track how you use our site. You can control cookie settings in your browser preferences.
                        </p>
                        
                        <div class="hr-alert hr-alert-gray" style="padding: 16px;">
                            <p class="hr-alert-text">
                                <strong style="color: #0A0A0A;">Cookie Types:</strong> Essential cookies (for site functionality), Analytics cookies (via Google Analytics), and optional preference cookies (remember your settings).
                            </p>
                        </div>
                    </div>
                </div>
            </section>

        </div>

        <!-- RIGHT: SIDEBAR -->
        <aside class="hr-sidebar">
            
            <div class="hr-sb-nav-box">
                <div class="hr-sb-title">Quick Navigation</div>
                <nav class="hr-sb-nav">
                    <a href="#intro" class="hr-sb-link">Introduction</a>
                    <a href="#data" class="hr-sb-link">Data We Collect</a>
                    <a href="#analytics" class="hr-sb-link">Analytics</a>
                    <a href="#provided" class="hr-sb-link">Information You Provide</a>
                    <a href="#public" class="hr-sb-link">Public Interactions</a>
                    <a href="#ai" class="hr-sb-link">AI Transparency</a>
                    <a href="#disclosure" class="hr-sb-link">Legal Disclosure</a>
                    <a href="#rights" class="hr-sb-link">Your Rights</a>
                    <a href="#cookies" class="hr-sb-link">Cookies</a>
                    <a href="#contact" class="hr-sb-link">Contact</a>
                </nav>
            </div>

            <div class="hr-sb-key-points">
                <div class="hr-sb-title">Key Points</div>
                <ul>
                    <li>We don't sell your data</li>
                    <li>All AI content reviewed by humans</li>
                    <li>You can opt-out anytime</li>
                    <li>Full transparency on tracking</li>
                </ul>
            </div>

            <a id="contact" href="mailto:contact@headlinerecorder.com" class="hr-cta-btn">Privacy Questions?</a>

        </aside>

    </div>
</div>

<?php 
get_footer(); 
?>