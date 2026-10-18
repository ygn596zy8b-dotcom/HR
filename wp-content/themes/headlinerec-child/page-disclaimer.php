<?php
/**
 * Template Name: Disclaimer
 */
get_header();
?>

<style>
/* ==========================================================================
   HEADLINE RECORDER - PREMIUM DISCLAIMER PAGE UI
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
.hr-disc-master {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    background-color: #FFFFFF;
    color: #0A0A0A;
    line-height: 1.5;
    margin: 0; padding: 0;
}
.hr-disc-master * { box-sizing: border-box; }

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

/* --- MAIN CONTENT --- */
.hr-main-content {
    max-width: 1200px; margin: 0 auto; padding: 60px 48px; display: grid; grid-template-columns: 1fr 320px; gap: 64px;
}

/* --- SECTIONS --- */
.hr-disc-section { margin-bottom: 60px; scroll-margin-top: 40px; }
.hr-disc-header { display: flex; align-items: flex-start; gap: 16px; margin-bottom: 24px; }
.hr-disc-number {
    width: 40px; height: 40px; background: #C0001A; color: #FFFFFF; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 18px; flex-shrink: 0; border-radius: 2px;
}
.hr-disc-header-text { flex: 1; }
.hr-disc-header-text h2 { font-size: 22px; font-weight: 800; color: #0A0A0A; margin: 0 0 12px 0; }
.hr-disc-text { font-size: 14px; color: #6B7280; line-height: 1.7; margin: 0 0 16px 0; }

/* --- ALERT BOXES --- */
.hr-alert { padding: 16px; margin-top: 16px; }
.hr-alert-title { font-size: 12px; font-weight: 700; margin: 0 0 8px 0; text-transform: uppercase; letter-spacing: 1px; }
.hr-alert-text { font-size: 12px; margin: 0; line-height: 1.6; }
.hr-alert-list { margin: 0; padding-left: 16px; font-size: 13px; line-height: 1.8; }
.hr-alert-list li { margin-bottom: 8px; }
.hr-alert-list li:last-child { margin-bottom: 0; }

/* Specific Alerts */
.hr-alert-yellow { background: #FFF3CD; border: 1px solid #FFE69C; border-left: 4px solid #FF9800; color: #856404; }
.hr-alert-red { background: #FFEBEE; border: 1px solid #FFCDD2; border-left: 4px solid #F44336; color: #C62828; }
.hr-alert-green { background: #E8F5E9; border: 1px solid #C8E6C9; border-left: 4px solid #4CAF50; color: #1B5E20; }
.hr-alert-gray { background: #F9F9F9; border: none; border-left: 3px solid #C0001A; color: #6B7280; }
.hr-alert-pink { background: #FCE4EC; border: 1px solid #F8BBD0; border-left: 4px solid #E91E63; color: #880E4F; }
.hr-alert-black { background: #0A0A0A; border-radius: 2px; color: #9CA3AF; }
.hr-alert-black .hr-alert-title { color: #FFFFFF; }

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

.hr-sb-must-know { background: #FFEBEE; border: 1px solid #FFCDD2; padding: 24px; margin-top: 32px; }
.hr-sb-must-know .hr-sb-title { color: #C62828; margin-bottom: 14px; }
.hr-sb-must-know ul { margin: 0; padding-left: 14px; font-size: 11px; color: #C62828; line-height: 1.8; }
.hr-sb-must-know li { margin-bottom: 10px; }

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
    .hr-disc-header-text h2 { font-size: 18px; }
    .hr-disc-header { flex-direction: column; }
}
</style>

<div class="hr-disc-master">

    <!-- BREADCRUMB -->
    <div class="es-top-nav-path">
        <a href="/">Home</a>
        <span>·</span>
        <span style="color: #6B7280;">Disclaimer</span>
    </div>

    <!-- HERO SECTION -->
    <div class="hr-hero-section">
        <div class="hr-hero-inner">
            <div class="hr-eyebrow">
                <div class="hr-line"></div>
                <span>Important Notice</span>
            </div>
            <h1>Disclaimer</h1>
            <p>Please read this disclaimer carefully before using Headline Recorder. This document outlines important legal notices, limitations of liability, and the conditions under which you use our services.</p>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="hr-main-content">

        <!-- LEFT: CONTENT -->
        <div>

            <!-- INTRODUCTION -->
            <section id="intro" class="hr-disc-section">
                <div class="hr-disc-header">
                    <div class="hr-disc-number">1</div>
                    <div class="hr-disc-header-text">
                        <h2>Introduction</h2>
                        <p class="hr-disc-text" style="margin-bottom: 0;">
                            Headline Recorder provides news, analysis, and commentary on business, technology, and markets. This disclaimer applies to all content, services, and features available on our website and mobile applications. By accessing or using Headline Recorder, you acknowledge that you have read and understood this disclaimer in its entirety.
                        </p>
                    </div>
                </div>
            </section>

            <!-- GENERAL INFORMATION -->
            <section id="information" class="hr-disc-section">
                <div class="hr-disc-header">
                    <div class="hr-disc-number">2</div>
                    <div class="hr-disc-header-text">
                        <h2 style="margin-bottom: 20px;">General Information</h2>
                        <p class="hr-disc-text">
                            All content on Headline Recorder is published in good faith for general informational and news reporting purposes only. While we strive for excellence, we do not make any warranties about the completeness, reliability, or absolute accuracy of this information. Any action you take based on the information found on our website is strictly at your own risk.
                        </p>
                        
                        <div class="hr-alert hr-alert-yellow">
                            <p class="hr-alert-title">⚠️ Important</p>
                            <p class="hr-alert-text">
                                Information provided should not be construed as professional financial, legal, or investment advice. Always consult with qualified professionals before making decisions based on our content.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- LIABILITY -->
            <section id="liability" class="hr-disc-section">
                <div class="hr-disc-header">
                    <div class="hr-disc-number">3</div>
                    <div class="hr-disc-header-text">
                        <h2 style="margin-bottom: 20px;">Limitation of Liability</h2>
                        <p class="hr-disc-text">
                            Headline Recorder, its owners, and its staff will not be liable for any losses or damages (financial or otherwise) in connection with the use of our website. This includes but is not limited to:
                        </p>
                        
                        <div class="hr-alert hr-alert-red">
                            <ul class="hr-alert-list">
                                <li>Results of business trades, investments, or financial decisions</li>
                                <li>Loss of profits, data, or business opportunities</li>
                                <li>Investment speculation or trading based on our content</li>
                                <li>Technical errors or website downtime</li>
                            </ul>
                        </div>

                        <p class="hr-disc-text" style="margin-top: 16px;">
                            Even if we have been advised of the possibility of such damages, Headline Recorder shall not be liable to you for any loss or damage of any kind arising out of or in connection with your use of our website.
                        </p>
                    </div>
                </div>
            </section>

            <!-- AI DISCLOSURE -->
            <section id="ai" class="hr-disc-section">
                <div class="hr-disc-header">
                    <div class="hr-disc-number">4</div>
                    <div class="hr-disc-header-text">
                        <h2 style="margin-bottom: 20px;">AI Disclosure</h2>
                        <p class="hr-disc-text">
                            To provide timely updates, some of our content may be assisted by automated technologies or AI. However, all AI-assisted content is human-monitored and verified for accuracy, context, and integrity before publication.
                        </p>
                        
                        <div class="hr-alert hr-alert-green">
                            <p class="hr-alert-title">🤖 How AI Is Used</p>
                            <ul class="hr-alert-list" style="font-size: 12px;">
                                <li>Content summarization and optimization</li>
                                <li>Headline generation and context enhancement</li>
                                <li>All output reviewed by human editors before publication</li>
                            </ul>
                        </div>

                        <p class="hr-disc-text" style="margin-top: 16px;">
                            We maintain full editorial control and do not rely on AI to make final editorial decisions. Our commitment to accuracy and integrity remains paramount.
                        </p>
                    </div>
                </div>
            </section>

            <!-- EXTERNAL LINKS & ADVERTISERS -->
            <section id="external" class="hr-disc-section">
                <div class="hr-disc-header">
                    <div class="hr-disc-number">5</div>
                    <div class="hr-disc-header-text">
                        <h2 style="margin-bottom: 20px;">External Links & Advertisers</h2>
                        <p class="hr-disc-text">
                            Our website may contain links to external sites or advertisements. We have no control over the content, nature, or veracity of these third-party sites. A link does not imply a recommendation for all content found on these sites.
                        </p>
                        
                        <div class="hr-alert hr-alert-gray">
                            <p class="hr-alert-text">
                                <strong style="color: #0A0A0A;">Third-Party Content:</strong> Headline Recorder is not responsible for the accuracy, completeness, or reliability of content on external websites. Visit these sites at your own discretion and risk.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- INVESTMENT DISCLAIMER -->
            <section id="investment" class="hr-disc-section">
                <div class="hr-disc-header">
                    <div class="hr-disc-number">6</div>
                    <div class="hr-disc-header-text">
                        <h2 style="margin-bottom: 20px;">Investment & Financial Disclaimer</h2>
                        <p class="hr-disc-text">
                            Headline Recorder provides news and analysis for informational purposes only. We are NOT a registered investment advisor or broker. Nothing on our site should be construed as financial advice or a recommendation to buy, sell, or hold any security.
                        </p>
                        
                        <div class="hr-alert hr-alert-pink">
                            <p class="hr-alert-title">⚠️ Before Investing</p>
                            <ul class="hr-alert-list" style="font-size: 12px;">
                                <li>Consult with a qualified financial advisor</li>
                                <li>Do your own research and due diligence</li>
                                <li>Never invest money you cannot afford to lose</li>
                            </ul>
                        </div>

                        <p class="hr-disc-text" style="margin-top: 16px;">
                            All investment decisions carry risk. Past performance does not guarantee future results. The markets are volatile and unpredictable.
                        </p>
                    </div>
                </div>
            </section>

            <!-- CONSENT -->
            <section id="consent" class="hr-disc-section" style="margin-bottom: 0;">
                <div class="hr-disc-header">
                    <div class="hr-disc-number">7</div>
                    <div class="hr-disc-header-text">
                        <h2 style="margin-bottom: 20px;">Acceptance & Consent</h2>
                        <p class="hr-disc-text">
                            By using Headline Recorder, you hereby consent to our disclaimer and agree to its terms. If you do not agree with our disclaimer, please do not use this website. Your continued use of the site following the posting of revised disclaimer terms means that you accept and agree to the changes.
                        </p>
                        
                        <div class="hr-alert hr-alert-black">
                            <p class="hr-alert-title">You Agree To:</p>
                            <ul class="hr-alert-list" style="font-size: 12px;">
                                <li>Indemnify Headline Recorder for all claims arising from your use</li>
                                <li>Accept full responsibility for your actions and decisions</li>
                                <li>Not hold Headline Recorder liable for any consequences</li>
                            </ul>
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
                    <a href="#information" class="hr-sb-link">General Information</a>
                    <a href="#liability" class="hr-sb-link">Liability</a>
                    <a href="#ai" class="hr-sb-link">AI Disclosure</a>
                    <a href="#external" class="hr-sb-link">External Links</a>
                    <a href="#investment" class="hr-sb-link">Investment Disclaimer</a>
                    <a href="#consent" class="hr-sb-link">Consent</a>
                </nav>
            </div>

            <div class="hr-sb-must-know">
                <div class="hr-sb-title">Must Know</div>
                <ul>
                    <li>Not investment advice</li>
                    <li>No warranties on accuracy</li>
                    <li>Use at your own risk</li>
                    <li style="margin-bottom: 0;">Always consult professionals</li>
                </ul>
            </div>

            <a href="mailto:contact@headlinerecorder.com" class="hr-cta-btn">Legal Questions?</a>

        </aside>

    </div>
</div>

<?php 
get_footer(); 
?>