<?php
/**
 * Template Name: About Us
 */
get_header();
?>

<style>
/* ==========================================================================
   HEADLINE RECORDER - PREMIUM ABOUT PAGE UI
   ========================================================================== */

/* Force theme wrappers to full width */
#primary, #main, .site-main, .content-area {
    width: 100% !important; max-width: 100% !important; float: none !important; margin: 0 !important; padding: 0 !important;
}
#secondary, .sidebar, .widget-area { display: none !important; }
.twp-breadcrumb-wrapper, .breadcrumb-trail, #breadcrumb, .breadcrumbs, .page-header { display: none !important; }

/* --- MASTER CONTAINER --- */
.hr-about-master {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    background-color: #FFFFFF;
    color: #0A0A0A;
    line-height: 1.5;
    margin: 0; padding: 0;
}
.hr-about-master * { box-sizing: border-box; }

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
    padding: 100px 48px; /* Reduced height to match contact page */
    position: relative;
    overflow: hidden;
}
.hr-hero-inner {
    max-width: 1200px; /* Expanded to match the full grid width for left alignment */
    margin: 0 auto;
    position: relative;
    z-index: 1;
}
.hr-hero-inner h1 {
    font-size: 56px; font-weight: 800; color: #FFFFFF; margin: 0 0 20px 0; line-height: 1.1; letter-spacing: -1px;
}
.hr-hero-inner p {
    font-size: 18px; color: #9CA3AF; margin: 0; line-height: 1.6; max-width: 720px;
}

/* --- COMMON COMPONENTS --- */
.hr-eyebrow { display: flex; align-items: center; gap: 10px; margin-bottom: 24px; }
.hr-eyebrow .hr-line { width: 32px; height: 2px; background-color: #C0001A; }
.hr-eyebrow span { font-size: 12px; font-weight: 800; letter-spacing: 4px; color: #C0001A; text-transform: uppercase; }

.hr-section-header { display: flex; align-items: center; gap: 12px; margin-bottom: 28px; }
.hr-section-header .hr-line { width: 28px; height: 2px; background-color: #C0001A; }
.hr-section-header span { font-size: 12px; font-weight: 800; letter-spacing: 4px; text-transform: uppercase; color: #6B7280; }

/* --- MAIN CONTENT --- */
.hr-main-content { max-width: 1200px; margin: 0 auto; padding: 80px 48px; }
.hr-two-column { display: grid; grid-template-columns: 1fr 380px; gap: 64px; margin-bottom: 80px; }

/* --- VISION & MISSION --- */
.hr-vision-block { margin-bottom: 60px; }
.hr-vision-block h2 { font-size: 32px; font-weight: 800; color: #0A0A0A; margin: 0 0 20px 0; line-height: 1.2; }
.hr-vision-block p { font-size: 16px; color: #6B7280; line-height: 1.8; margin: 0 0 16px 0; }
.hr-vision-block p:last-child { margin-bottom: 0; }

.hr-mission-block { background: #F9F9F9; padding: 32px; border-left: 4px solid #C0001A; margin-bottom: 60px; }
.hr-m-head { font-size: 12px; font-weight: 800; letter-spacing: 2px; color: #C0001A; text-transform: uppercase; margin-bottom: 12px; }
.hr-m-lead { font-size: 15px; font-weight: 700; color: #0A0A0A; margin: 0 0 12px 0; }
.hr-m-text { font-size: 14px; color: #6B7280; margin: 0; line-height: 1.6; }

/* --- SIDEBAR --- */
.hr-sidebar { position: sticky; top: 24px; display: flex; flex-direction: column; gap: 32px; }
.hr-sb-heading { font-size: 10px; font-weight: 800; letter-spacing: 2px; color: #C0001A; text-transform: uppercase; margin-bottom: 18px; }
.hr-stat-item { margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #E5E7EB; }
.hr-stat-item:last-child { border-bottom: none; padding-bottom: 0; margin-bottom: 0; }
.hr-stat-label { font-size: 11px; font-weight: 700; color: #6B7280; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; }
.hr-stat-val { font-size: 24px; font-weight: 800; color: #0A0A0A; }

.hr-values-block { background: #0A0A0A; padding: 24px; color: #FFFFFF; }
.hr-value-item { font-size: 12px; font-weight: 600; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid rgba(255,255,255,0.1); }
.hr-value-item:last-child { margin-bottom: 0; padding-bottom: 0; border-bottom: none; }

/* --- LEADERSHIP --- */
.hr-leader-wrap { margin-bottom: 80px; }
.hr-leader-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 280px), 1fr)); gap: 32px; }
.hr-leader-card { border: 1px solid #E5E7EB; padding: 28px 24px; text-align: center; }
.hr-leader-avatar { width: 100px; height: 100px; background: #0A0A0A; border-radius: 50%; margin: 0 auto 20px auto; display: flex; align-items: center; justify-content: center; font-size: 32px; color: #6B7280; }
.hr-leader-card h3 { font-size: 16px; font-weight: 700; color: #0A0A0A; margin: 0 0 6px 0; }
.hr-l-role { font-size: 12px; font-weight: 600; color: #C0001A; margin: 0 0 12px 0; text-transform: uppercase; letter-spacing: 1px; }
.hr-leader-card p { font-size: 13px; color: #6B7280; margin: 0; line-height: 1.6; }

/* --- STANDARDS --- */
.hr-standards-section { background: #F9F9F9; padding: 60px 48px; margin-left: -48px; margin-right: -48px; margin-bottom: 80px; }
.hr-standards-inner { max-width: 1200px; margin: 0 auto; }
.hr-standards-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 300px), 1fr)); gap: 40px; }
.hr-std-icon { font-size: 32px; margin-bottom: 16px; }
.hr-std-card h3 { font-size: 16px; font-weight: 700; color: #0A0A0A; margin: 0 0 10px 0; }
.hr-std-card p { font-size: 14px; color: #6B7280; margin: 0; line-height: 1.6; }

/* --- RESPONSIVE --- */
@media (max-width: 1024px) {
    .es-top-nav-path, .hr-hero-section, .hr-main-content { padding-left: 24px; padding-right: 24px; }
    .hr-two-column { grid-template-columns: 1fr; }
    .hr-sidebar { position: static; }
    .hr-standards-section { margin-left: -24px; margin-right: -24px; padding-left: 24px; padding-right: 24px; }
}
@media (max-width: 768px) {
    .hr-hero-inner h1 { font-size: 36px; }
    .hr-vision-block h2 { font-size: 24px; }
    .hr-main-content { padding-top: 40px; padding-bottom: 40px; }
}
</style>

<div class="hr-about-master">

    <!-- BREADCRUMB -->
    <div class="es-top-nav-path">
        <a href="/">Home</a>
        <span>·</span>
        <span style="color: #6B7280;">About Us</span>
    </div>

    <!-- HERO SECTION -->
    <div class="hr-hero-section">
        <div class="hr-hero-inner">
            <div class="hr-eyebrow">
                <div class="hr-line"></div>
                <span>Our Story</span>
            </div>
            <h1>Clarity through the noise.</h1>
            <p>Headline Recorder is a digital-first news publication dedicated to providing accurate, timely, and comprehensive coverage of global events, technology, and business. Founded in 2023, our mission is to cut through the noise and deliver the core facts that matter to our readers.</p>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="hr-main-content">

        <!-- TWO COLUMN: Vision + Sidebar -->
        <div class="hr-two-column">
            
            <!-- LEFT: Vision & Mission -->
            <div>
                <!-- Vision -->
                <div class="hr-vision-block">
                    <div class="hr-section-header" style="margin-bottom: 28px;">
                        <div class="hr-line"></div>
                        <span>Our Vision</span>
                    </div>
                    <h2>We believe in the power of clarity.</h2>
                    <p>In an era of rapid information exchange, clarity is currency. Headline Recorder was built to serve as a reliable archive and real-time update hub for news that shapes our world.</p>
                    <p>Whether it's breaking political shifts or the latest breakthroughs in AI and startups, we ensure our audience stays informed with integrity.</p>
                </div>

                <!-- Mission -->
                <div class="hr-mission-block">
                    <div class="hr-m-head">Our Mission</div>
                    <p class="hr-m-lead">Cut through the noise. Deliver the facts that matter.</p>
                    <p class="hr-m-text">We provide decision-makers across banking, business, and technology with intelligence that's accurate, verified, and immediately actionable.</p>
                </div>
            </div>

            <!-- RIGHT: SIDEBAR -->
            <aside class="hr-sidebar">
                
                <!-- Key Metrics -->
                <div>
                    <div class="hr-sb-heading">By The Numbers</div>
                    <div class="hr-stat-item">
                        <div class="hr-stat-label">Founded</div>
                        <div class="hr-stat-val">2023</div>
                    </div>
                    <div class="hr-stat-item">
                        <div class="hr-stat-label">Articles</div>
                        <div class="hr-stat-val">
                        <?php 
    $published_posts = wp_count_posts()->publish;
    echo esc_html( $published_posts ) . '+'; 
    ?></div>
                    </div>
                    <div class="hr-stat-item">
                        <div class="hr-stat-label">Readers</div>
                        <div class="hr-stat-val">5K+</div>
                    </div>
                    <div class="hr-stat-item" style="border-bottom: none; padding-bottom: 0; margin-bottom: 0;">
                        <div class="hr-stat-label">Countries</div>
                        <div class="hr-stat-val">3</div>
                    </div>
                </div>

                <!-- Core Values -->
                <div class="hr-values-block">
                    <div class="hr-sb-heading" style="margin-bottom: 16px;">Core Values</div>
                    <div class="hr-value-item">✓ Accuracy</div>
                    <div class="hr-value-item">✓ Speed</div>
                    <div class="hr-value-item">✓ Clarity</div>
                    <div class="hr-value-item" style="border-bottom: none; padding-bottom: 0; margin-bottom: 0;">✓ Integrity</div>
                </div>

            </aside>
        </div>

        <!-- LEADERSHIP SECTION -->
        <!--<div class="hr-leader-wrap">-->
        <!--    <div class="hr-section-header" style="margin-bottom: 40px;">-->
        <!--        <div class="hr-line"></div>-->
        <!--        <span>Leadership</span>-->
        <!--    </div>-->

        <!--    <div class="hr-leader-grid">-->
        <!--        <div class="hr-leader-card">-->
        <!--            <div class="hr-leader-avatar"></div>-->
        <!--            <h3>Soban Hanif</h3>-->
        <!--            <p class="hr-l-role">Editor & Co-Founder</p>-->
        <!--            <p>Editorial direction and brand strategy. Previously worked in business journalism.</p>-->
        <!--        </div>-->
        <!--        <div class="hr-leader-card">-->
        <!--            <div class="hr-leader-avatar"></div>-->
        <!--            <h3>Co-Founder</h3>-->
        <!--            <p class="hr-l-role">Business Development</p>-->
        <!--            <p>Leads growth, partnerships, and commercial strategy for Headline Recorder.</p>-->
        <!--        </div>-->
        <!--        <div class="hr-leader-card">-->
        <!--            <div class="hr-leader-avatar"></div>-->
        <!--            <h3>Editorial Team</h3>-->
        <!--            <p class="hr-l-role">Reporters & Analysts</p>-->
        <!--            <p>A diverse team of journalists and analysts covering business, tech, and emerging markets.</p>-->
        <!--        </div>-->
        <!--    </div>-->
        <!--</div>-->

        <!-- EDITORIAL INTEGRITY SECTION -->
        <div class="hr-standards-section">
            <div class="hr-standards-inner">
                <div class="hr-section-header" style="margin-bottom: 40px;">
                    <div class="hr-line"></div>
                    <span>Editorial Standards</span>
                </div>

                <div class="hr-standards-grid">
                    <div class="hr-std-card">
                        <div class="hr-std-icon">✓</div>
                        <h3>Verification First</h3>
                        <p>Every story undergoes rigorous fact-checking. We cite sources, verify data, and correct errors immediately.</p>
                    </div>
                    <div class="hr-std-card">
                        <div class="hr-std-icon">👤</div>
                        <h3>Human-Monitored</h3>
                        <p>While we use AI to assist in research and summarization, every story is human-reviewed before publication.</p>
                    </div>
                    <div class="hr-std-card">
                        <div class="hr-std-icon">📰</div>
                        <h3>No Clickbait</h3>
                        <p>Our headlines reflect the actual content. We don't sensationalize; we clarify.</p>
                    </div>
                    <div class="hr-std-card">
                        <div class="hr-std-icon">🔍</div>
                        <h3>Transparent Sourcing</h3>
                        <p>We disclose our sources when possible and explain why we can't when we can't.</p>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php 
get_footer(); 
?>