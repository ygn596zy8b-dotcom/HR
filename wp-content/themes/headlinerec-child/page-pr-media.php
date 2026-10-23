<?php
/**
 * Template Name: PR & Media
 */
get_header();
?>

<style>
/* ==========================================================================
   HEADLINE RECORDER - PREMIUM PR & MEDIA PAGE UI
   ========================================================================== */

/* Force theme wrappers to full width */
#primary, #main, .site-main, .content-area {
    width: 100% !important; max-width: 100% !important; float: none !important; margin: 0 !important; padding: 0 !important;
}
#secondary, .sidebar, .widget-area { display: none !important; }
.twp-breadcrumb-wrapper, .breadcrumb-trail, #breadcrumb, .breadcrumbs, .page-header { display: none !important; }

/* --- MASTER CONTAINER --- */
.hr-prm-master {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    background-color: #FFFFFF;
    color: #0A0A0A;
    line-height: 1.5;
    margin: 0; padding: 0;
}
.hr-prm-master * { box-sizing: border-box; }

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
    padding: 100px 48px; position: relative; overflow: hidden;
}
.hr-hero-inner {
    max-width: 1200px; margin: 0 auto; position: relative; z-index: 1;
}
.hr-hero-inner h1 {
    font-size: 56px; font-weight: 800; color: #FFFFFF; margin: 0 0 16px 0; line-height: 1.1; letter-spacing: -1px;
}
.hr-hero-inner p {
    font-size: 16px; color: #9CA3AF; margin: 0; line-height: 1.6; max-width: 720px;
}

/* --- COMMON COMPONENTS --- */
.hr-eyebrow { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; }
.hr-eyebrow .hr-line { width: 32px; height: 2px; background-color: #C0001A; }
.hr-eyebrow span { font-size: 12px; font-weight: 800; letter-spacing: 4px; color: #C0001A; text-transform: uppercase; }

.hr-section-header { display: flex; align-items: center; gap: 12px; margin-bottom: 40px; }
.hr-section-header .hr-line { width: 28px; height: 2px; background-color: #C0001A; }
.hr-section-header span { font-size: 12px; font-weight: 800; letter-spacing: 4px; text-transform: uppercase; color: #6B7280; }

/* --- MAIN CONTENT --- */
.hr-main-content { max-width: 1200px; margin: 0 auto; padding: 80px 48px; }

/* --- AUDIENCE SNAPSHOT GRID --- */
.hr-stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 240px), 1fr)); gap: 28px; margin-bottom: 80px; }
.hr-stat-card { background: #F9F9F9; padding: 28px 24px; border-top: 3px solid #C0001A; }
.hr-stat-label { font-size: 11px; font-weight: 800; letter-spacing: 2px; color: #6B7280; text-transform: uppercase; margin-bottom: 8px; }
.hr-stat-value { font-size: 28px; font-weight: 800; color: #0A0A0A; }

/* --- OFFERINGS GRID --- */
.hr-offer-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 320px), 1fr)); gap: 24px; margin-bottom: 80px; }
.hr-offer-card { border: 1px solid #E5E7EB; padding: 28px 24px; transition: all 0.2s ease; display: flex; flex-direction: column; background: #FFFFFF; }
.hr-offer-card:hover { border-color: #C0001A; box-shadow: 0 8px 24px rgba(192,0,26,0.08); }
.hr-offer-icon { font-size: 32px; margin-bottom: 16px; }
.hr-offer-card h3 { font-size: 16px; font-weight: 700; color: #0A0A0A; margin: 0 0 10px 0; }
.hr-offer-card p { font-size: 13px; color: #6B7280; line-height: 1.6; margin: 0 0 16px 0; flex: 1; }
.hr-offer-price { font-size: 12px; font-weight: 700; color: #C0001A; border-top: 1px solid #E5E7EB; padding-top: 12px; }

/* --- EDITORIAL INTEGRITY --- */
.hr-integrity-box { background: #F9F9F9; padding: 60px 48px; margin-left: -48px; margin-right: -48px; margin-bottom: 80px; }
.hr-integrity-inner { max-width: 1200px; margin: 0 auto; }
.hr-integrity-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 300px), 1fr)); gap: 32px; }
.hr-integrity-card h3 { font-size: 15px; font-weight: 700; color: #0A0A0A; margin: 0 0 10px 0; }
.hr-integrity-card p { font-size: 13px; color: #6B7280; margin: 0; line-height: 1.6; }

/* --- MEDIA KIT & FORM SECTION --- */
.hr-kit-layout { display: grid; grid-template-columns: 1fr 380px; gap: 64px; }
.hr-kit-checklist { background: #F9F9F9; padding: 28px; margin-bottom: 40px; }
.hr-kit-check-title { font-size: 12px; font-weight: 800; letter-spacing: 2px; color: #C0001A; text-transform: uppercase; margin-bottom: 12px; }
.hr-kit-check-item { font-size: 13px; color: #6B7280; margin-bottom: 10px; display: flex; gap: 10px; }
.hr-kit-check-item:last-child { margin-bottom: 0; }

.hr-download-btn { display: inline-block; background: #0A0A0A; color: #FFFFFF; padding: 14px 28px; font-size: 11px; font-weight: 800; letter-spacing: 2px; text-transform: uppercase; text-decoration: none; transition: background 0.2s; }
.hr-download-btn:hover { background: #C0001A; color: #FFFFFF; }

/* --- CONTACT FORM 7 OVERRIDES --- */
.hr-prm-form .wpcf7-form label {
    display: block; font-size: 12px; font-weight: 800; letter-spacing: 2px; text-transform: uppercase; color: #0A0A0A; margin-bottom: 8px; width: 100%;
}
.hr-prm-form .wpcf7-form-control-wrap {
    display: block; width: 100%; margin-bottom: 16px;
}
.hr-prm-form .wpcf7-text, 
.hr-prm-form .wpcf7-email {
    width: 100% !important; padding: 12px 14px !important; font-size: 13px !important; border: 1px solid #E5E7EB !important; outline: none !important; font-family: inherit !important; transition: all 0.2s ease !important; color: #0A0A0A !important; background: #FFFFFF !important; box-sizing: border-box !important; border-radius: 0 !important;
}
.hr-prm-form .wpcf7-text:focus, 
.hr-prm-form .wpcf7-email:focus {
    border-color: #C0001A !important;
}
.hr-prm-form .wpcf7-submit {
    background: #C0001A !important; color: #FFFFFF !important; border: none !important; padding: 12px 16px !important; font-size: 10px !important; font-weight: 800 !important; letter-spacing: 1.5px !important; text-transform: uppercase !important; cursor: pointer !important; font-family: inherit !important; transition: opacity 0.2s ease !important; border-radius: 0 !important; width: 100%; margin-top: 8px;
}
.hr-prm-form .wpcf7-submit:hover {
    opacity: 0.85 !important;
}

/* --- BOTTOM CTA --- */
.hr-cta-section { background: #0A0A0A; padding: 80px 48px; margin-top: 80px; color: #FFFFFF; text-align: center; }
.hr-cta-inner { max-width: 700px; margin: 0 auto; }
.hr-cta-inner h2 { font-size: 36px; font-weight: 800; margin: 0 0 16px 0; }
.hr-cta-inner p { font-size: 16px; color: #9CA3AF; margin: 0 0 32px 0; line-height: 1.7; }
.hr-cta-btn { display: inline-block; background: #C0001A; color: #FFFFFF; padding: 16px 32px; font-size: 11px; font-weight: 800; letter-spacing: 2px; text-transform: uppercase; text-decoration: none; transition: opacity 0.2s; }
.hr-cta-btn:hover { opacity: 0.85; color: #FFFFFF; }

/* --- RESPONSIVE --- */
@media (max-width: 1024px) {
    .es-top-nav-path, .hr-hero-section, .hr-main-content, .hr-cta-section { padding-left: 24px; padding-right: 24px; }
    .hr-kit-layout { grid-template-columns: 1fr; }
    .hr-integrity-box { margin-left: -24px; margin-right: -24px; padding-left: 24px; padding-right: 24px; }
}
@media (max-width: 768px) {
    .hr-hero-inner h1 { font-size: 36px; }
    .hr-cta-inner h2 { font-size: 24px; }
}
</style>

<div class="hr-prm-master">

    <!-- BREADCRUMB -->
    <div class="es-top-nav-path">
        <a href="/">Home</a>
        <span>·</span>
        <span style="color: #6B7280;">PR & Media</span>
    </div>

    <!-- HERO SECTION -->
    <div class="hr-hero-section">
        <div class="hr-hero-inner">
            <div class="hr-eyebrow">
                <div class="hr-line"></div>
                <span>Advertising & Partnerships</span>
            </div>
            <h1>Partner with Headline Recorder.</h1>
            <p>Reach decision-makers across tech, business, and emerging markets. Our audience consists of tech founders, startup executives, investors, and industry professionals who value sharp, deeply insightful reporting.</p>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="hr-main-content">

        <!-- AUDIENCE SNAPSHOT -->
        <div class="hr-stat-grid">
            <div class="hr-stat-card">
                <div class="hr-stat-label">Monthly Readers</div>
                <div class="hr-stat-value">50,000+</div>
            </div>
            <div class="hr-stat-card">
                <div class="hr-stat-label">Avg. Time on Site</div>
                <div class="hr-stat-value">4.2 min</div>
            </div>
            <div class="hr-stat-card">
                <div class="hr-stat-label">Tech/Startup Focus</div>
                <div class="hr-stat-value">45%</div>
            </div>
            <div class="hr-stat-card">
                <div class="hr-stat-label">Executive Decision-Makers</div>
                <div class="hr-stat-value">78%</div>
            </div>
        </div>

        <!-- OFFERINGS -->
        <div style="margin-bottom: 80px;">
            <div class="hr-section-header">
                <div class="hr-line"></div>
                <span>Advertising Solutions</span>
            </div>

            <div class="hr-offer-grid">
                <div class="hr-offer-card">
                    <div class="hr-offer-icon">📰</div>
                    <h3>Sponsored Features & Editorial</h3>
                    <p>Publish your industry insights, company milestones, or whitepapers. Every piece undergoes our rigorous editorial review to ensure it resonates with our high-end audience.</p>
                    <div class="hr-offer-price">Starting at $2,500</div>
                </div>
                <div class="hr-offer-card">
                    <div class="hr-offer-icon">🎥</div>
                    <h3>Executive Spotlights & Video</h3>
                    <p>Feature your C-suite leadership in our exclusive interview series. We produce highly polished written profiles and dynamic video segments for thought leadership.</p>
                    <div class="hr-offer-price">Starting at $4,000</div>
                </div>
                <div class="hr-offer-card">
                    <div class="hr-offer-icon">📧</div>
                    <h3>Newsletter Sponsorships</h3>
                    <p>Place your brand directly in the inboxes of tech leaders and startup founders through our curated daily briefing. Premium, clutter-free placements.</p>
                    <div class="hr-offer-price">Starting at $3,000/week</div>
                </div>
                <div class="hr-offer-card">
                    <div class="hr-offer-icon">🎯</div>
                    <h3>Display Advertising</h3>
                    <p>Secure premium, clutter-free ad placements across our homepage and article pages. Engineered to maintain visual harmony while maximizing visibility.</p>
                    <div class="hr-offer-price">Starting at $1,500/week</div>
                </div>
                <div class="hr-offer-card">
                    <div class="hr-offer-icon">🔗</div>
                    <h3>Native Content Partnerships</h3>
                    <p>Custom content series integrated seamlessly into our editorial calendar. Fully branded but editorially sound, reaching engaged decision-makers.</p>
                    <div class="hr-offer-price">Custom pricing</div>
                </div>
                <div class="hr-offer-card">
                    <div class="hr-offer-icon">📊</div>
                    <h3>Sponsored Research & Reports</h3>
                    <p>Co-produce market research or industry reports. Your insights, our distribution to thousands of business leaders across South Asia and beyond.</p>
                    <div class="hr-offer-price">Custom pricing</div>
                </div>
            </div>
        </div>

        <!-- EDITORIAL INTEGRITY -->
        <div class="hr-integrity-box">
            <div class="hr-integrity-inner">
                <div class="hr-section-header" style="margin-bottom: 40px;">
                    <div class="hr-line"></div>
                    <span>Our Standards</span>
                </div>

                <h2 style="font-size: 32px; font-weight: 800; color: #0A0A0A; margin: 0 0 32px 0; line-height: 1.2;">
                    Editorial Integrity & Transparency
                </h2>

                <div class="hr-integrity-grid">
                    <div class="hr-integrity-card">
                        <h3>Objective & Well-Researched</h3>
                        <p>All sponsored content must be factual, provide genuine value to our readers, and align with our editorial standards. No misleading claims or low-quality SEO tactics.</p>
                    </div>
                    <div class="hr-integrity-card">
                        <h3>Transparent Disclosure</h3>
                        <p>All sponsored content is clearly labeled as such. Our readers know exactly what is editorial and what is partnership-driven. We never blur these lines.</p>
                    </div>
                    <div class="hr-integrity-card">
                        <h3>Quality First</h3>
                        <p>Sponsored pieces undergo the same editorial review as our core journalism. Your content will be polished, error-free, and aligned with our high-end aesthetic.</p>
                    </div>
                    <div class="hr-integrity-card">
                        <h3>No Sensationalism</h3>
                        <p>We reject keyword stuffing, aggressive sales pitches, and low-quality content. We maintain the credibility that makes our platform valuable for your brand.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- MEDIA KIT & FORM SECTION -->
        <div class="hr-kit-layout">
            
            <!-- LEFT: MEDIA KIT INFO -->
            <div>
                <div class="hr-section-header">
                    <div class="hr-line"></div>
                    <span>Media Kit</span>
                </div>

                <h2 style="font-size: 32px; font-weight: 800; color: #0A0A0A; margin: 0 0 20px 0; line-height: 1.2;">
                    Get Our Full Media Kit
                </h2>
                <p style="font-size: 15px; color: #6B7280; lineHeight: 1.8; margin: 0 0 32px 0;">
                    Download our complete media kit to access detailed audience demographics, traffic metrics, rate card, and case studies from our recent partnerships.
                </p>

                <div class="hr-kit-checklist">
                    <div class="hr-kit-check-title">Media Kit Includes</div>
                    <div class="hr-kit-check-item">
                        <span style="color: #C0001A; font-weight: 700; flex-shrink: 0;">✓</span>
                        <span>Detailed audience demographics & psychographics</span>
                    </div>
                    <div class="hr-kit-check-item">
                        <span style="color: #C0001A; font-weight: 700; flex-shrink: 0;">✓</span>
                        <span>Monthly traffic metrics & engagement rates</span>
                    </div>
                    <div class="hr-kit-check-item">
                        <span style="color: #C0001A; font-weight: 700; flex-shrink: 0;">✓</span>
                        <span>Complete advertising rate card</span>
                    </div>
                    <div class="hr-kit-check-item">
                        <span style="color: #C0001A; font-weight: 700; flex-shrink: 0;">✓</span>
                        <span>Case studies & success stories</span>
                    </div>
                    <div class="hr-kit-check-item">
                        <span style="color: #C0001A; font-weight: 700; flex-shrink: 0;">✓</span>
                        <span>Editorial calendar & upcoming opportunities</span>
                    </div>
                    <div class="hr-kit-check-item">
                        <span style="color: #C0001A; font-weight: 700; flex-shrink: 0;">✓</span>
                        <span>Technical specifications for ad placements</span>
                    </div>
                </div>

                
            </div>

            <!-- RIGHT: REQUEST FORM (Contact Form 7 Integration) -->
            <aside class="hr-prm-form">
                <div class="hr-section-header" style="margin-bottom: 28px;">
                    <div class="hr-line" style="width: 20px;"></div>
                    <span>Request Kit</span>
                </div>

                <?php echo do_shortcode('[contact-form-7 id="7878b22" title="Media Kit Request Form"]'); ?>

                <p style="font-size: 11px; color: #6B7280; margin: 20px 0 0 0; line-height: 1.5; text-align: center;">
                    We'll send your media kit via email within 24 hours.
                </p>
            </aside>

        </div>

    </div>

    

</div>

<?php 
get_footer(); 
?>