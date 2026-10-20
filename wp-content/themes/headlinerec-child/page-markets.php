<?php
/**
 * Template Name: Premium Markets Page
 * Description: A custom 70/30 split financial terminal layout for Headline Recorder.
 */
get_header();

while (have_posts()) : the_post();
?>

<style>
/* ================================================
   HR MARKETS PAGE — CUSTOM TERMINAL UI
================================================ */
* { box-sizing: border-box; }

/* Force full width, kill default theme layout */
.section-block-upper, .section-block-upper.row,
#secondary, .widget-area, aside#secondary,
[class*="breadcrumb"], .covernews-breadcrumb, .breadcrumbs, #breadcrumbs,
#comments, .comment-respond, .comments-area {
    display: none !important;
}
#primary, .content-area, #main, .site-main, .entry-content-wrap, .af-single-article {
    width: 100% !important; max-width: 100% !important;
    float: none !important; padding: 0 !important; margin: 0 !important;
}

/* Base Wrapper */
.hr-single-wrapper {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    background: #ffffff;
    color: #111111;
}

/* Grid Layout matching single.php */
.hr-page-grid {
    max-width: 1200px;
    margin: 0 auto;
    padding: 32px 48px 80px;
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 64px;
    align-items: start;
}

/* Section Headers */
.hr-desk-header {
    display: flex; justify-content: space-between; align-items: flex-end;
    border-bottom: 2px solid #111111; padding-bottom: 15px; margin-bottom: 35px; flex-wrap: wrap; gap: 15px;
}
.hr-desk-label {
    font-size: 11px; text-transform: uppercase; letter-spacing: 2px;
    color: #888888; font-weight: 700; display: block; margin-bottom: 4px;
}
.hr-desk-title {
    font-size: 32px; color: #111111; margin: 0; font-weight: 800;
    letter-spacing: -1px; line-height: 1.1;
}
.hr-live-badge {
    display: inline-flex; align-items: center; background: #f4f4f5;
    padding: 6px 12px; border-radius: 4px;
}
.hr-live-dot {
    width: 8px; height: 8px; background: #10b981; border-radius: 50%;
    display: inline-block; margin-right: 8px; animation: pulse 2s infinite;
}
@keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.4; } 100% { opacity: 1; } }

/* Module Containers */
.hr-module-box {
    background: #ffffff; border: 1px solid #eaeaea; padding: 30px;
    margin-bottom: 40px; box-shadow: 0 4px 20px rgba(0,0,0,0.015); border-radius: 4px;
}
.hr-module-title {
    font-size: 16px; font-weight: 700; text-transform: uppercase;
    border-bottom: 2px solid #C0001A; padding-bottom: 8px; margin: 0 0 20px;
    color: #0A0A0A;
}

/* Sidebar Styling */
.hr-sidebar {
    position: sticky; top: 24px; display: flex; flex-direction: column; gap: 40px;
}
.hr-sb-label {
    display: flex; align-items: center; gap: 10px; margin-bottom: 18px;
}
.hr-sb-label::before {
    content: ''; display: block; width: 20px; height: 2px;
    background: #C0001A; flex-shrink: 0;
}
.hr-sb-label span {
    font-size: 10px; font-weight: 800; letter-spacing: 3px;
    color: #6B7280; text-transform: uppercase;
}

/* Market Commentary Text */
.hr-commentary-text {
    font-size: 14px; line-height: 1.7; color: #1F2937;
    background: #F9FAFB; padding: 20px; border-left: 3px solid #C0001A;
}
.hr-commentary-text p { margin-bottom: 12px; }
.hr-commentary-text p:last-child { margin-bottom: 0; }

/* Spotlight Promo */
.hr-spotlight-promo { background: #0A0A0A; padding: 24px; }
.hr-spotlight-label { font-size: 9px; font-weight: 800; letter-spacing: 3px; color: #C0001A; text-transform: uppercase; margin-bottom: 12px; }
.hr-spotlight-title { font-size: 13px; font-weight: 700; color: #ffffff; margin: 0 0 12px; line-height: 1.4; }
.hr-spotlight-link { font-size: 10px; font-weight: 700; color: #C0001A; letter-spacing: 1px; text-transform: uppercase; text-decoration: none; border-bottom: 1px solid #C0001A; padding-bottom: 1px; }

@media (max-width: 1024px) {
    .hr-page-grid { grid-template-columns: minmax(0, 1fr); gap: 48px; padding: 24px 32px 60px; }
    .hr-sidebar { position: static; }
}
@media (max-width: 768px) {
    .hr-page-grid { padding: 20px 24px 48px; }
    .hr-desk-title { font-size: 26px; }
    .hr-module-box { padding: 15px; }
}
/* Small phones: sector heatmap as two columns (inline grid styles need !important to override) */
@media (max-width: 480px) {
    .hr-heatmap { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; grid-template-rows: none !important; grid-auto-rows: 110px; }
    .hr-heatmap > div:first-child { grid-row: auto !important; grid-column: 1 / -1; }
}
</style>

<div class="hr-single-wrapper">

    <!-- 1. FULL WIDTH TICKER TAPE -->
    <!--<div style="width: 100%; background: #ffffff; border-bottom: 1px solid #f0f0f0;">-->
    <!--    <div class="tradingview-widget-container">-->
    <!--        <div class="tradingview-widget-container__widget"></div>-->
    <!--        <script type="text/javascript" src="https://s3.tradingview.com/external-embedding/embed-widget-ticker-tape.js" async>-->
    <!--        {-->
    <!--        "symbols": [-->
    <!--            { "proName": "FX_IDC:USDPKR", "title": "USD/PKR" },-->
    <!--            { "proName": "FX_IDC:SARPKR", "title": "SAR/PKR" },-->
    <!--            { "proName": "FX_IDC:AEDPKR", "title": "AED/PKR" },-->
    <!--            { "proName": "TVC:UKOIL", "title": "Brent Crude" },-->
    <!--            { "proName": "TVC:GOLD", "title": "Gold Spot" },-->
    <!--            { "proName": "FOREXCOM:SPXUSD", "title": "S&P 500" },-->
    <!--            { "proName": "FOREXCOM:NSXUSD", "title": "Nasdaq 100" }-->
    <!--        ],-->
    <!--        "showSymbolLogo": false, "isTransparent": true, "displayMode": "regular", "colorTheme": "light", "locale": "en"-->
    <!--        }-->
    <!--        </script>-->
    <!--    </div>-->
    <!--</div>-->

    <div class="hr-page-grid">
        <!-- ========================================== -->
        <!-- LEFT COLUMN: MACRO & CHARTS (66%)          -->
        <!-- ========================================== -->
        <main class="hr-article">
            
            <div class="hr-desk-header">
                <div>
                    <span class="hr-desk-label">Intelligence Desk</span>
                    <h1 class="hr-desk-title">Macro & Commodities Hub</h1>
                </div>
                <div style="text-align: right; min-width: 200px;">
                    <div class="hr-live-badge">
                        <span class="hr-live-dot"></span>
                        <span style="font-size: 12px; font-weight: 600; color: #27272a; letter-spacing: 0.5px;">Live Data Feed Active</span>
                    </div>
                </div>
            </div>

            <!-- Main Market Overview -->
            <div class="hr-module-box">
                <div class="tradingview-widget-container" style="width: 100%;">
                    <div class="tradingview-widget-container__widget"></div>
                    <script type="text/javascript" src="https://s3.tradingview.com/external-embedding/embed-widget-market-overview.js" async>
                    {
                    "colorTheme": "light", "dateRange": "1M", "showChart": true, "locale": "en", "isTransparent": true, "showSymbolLogo": false, "showFloatingTooltip": false, "width": "100%", "height": "500",
                    "tabs": [
                        { "title": "PKR Corridors", "symbols": [ { "s": "FX_IDC:USDPKR", "d": "USD / PKR Interbank" }, { "s": "FX_IDC:SARPKR", "d": "SAR / PKR (Riyal)" }, { "s": "FX_IDC:AEDPKR", "d": "AED / PKR (Dirham)" }, { "s": "FX_IDC:GBPPKR", "d": "GBP / PKR" }, { "s": "FX_IDC:EURPKR", "d": "EUR / PKR" } ] },
                        { "title": "Commodities", "symbols": [ { "s": "TVC:UKOIL", "d": "Brent Crude Spot" }, { "s": "TVC:USOIL", "d": "WTI Crude Oil" }, { "s": "TVC:GOLD", "d": "Gold Spot" }, { "s": "TVC:SILVER", "d": "Silver Spot" } ] },
                        { "title": "Global Indices", "symbols": [ { "s": "FOREXCOM:SPXUSD", "d": "S&P 500 Index" }, { "s": "FOREXCOM:NSXUSD", "d": "Nasdaq 100" } ] }
                    ]
                    }
                    </script>
                </div>
            </div>

            <!-- PSX Market Quotes Widget -->
<h3 class="hr-module-title">PSX Sector Performance</h3>
<div class="hr-module-box" style="padding: 12px; background: #ffffff;">
    
    <!-- Legend -->
    <div style="display: flex; justify-content: flex-end; align-items: center; gap: 8px; font-size: 11px; font-weight: 700; color: #6b7280; margin-bottom: 12px;">
        <span>-2%</span>
        <span style="width: 12px; height: 12px; background: #dc2626; border-radius: 2px;"></span>
        <span style="width: 12px; height: 12px; background: #ef4444; border-radius: 2px;"></span>
        <span style="width: 12px; height: 12px; background: #9ca3af; border-radius: 2px;"></span>
        <span style="width: 12px; height: 12px; background: #10b981; border-radius: 2px;"></span>
        <span style="width: 12px; height: 12px; background: #059669; border-radius: 2px;"></span>
        <span>+2%</span>
    </div>

    <!-- Grid -->
    <div class="hr-heatmap" style="display: grid; grid-template-columns: 2fr 1fr 1fr; grid-template-rows: 130px 130px; gap: 6px; font-family: -apple-system, sans-serif;">
        
        <!-- Commercial Banks -->
        <div style="background: #059669; grid-row: 1 / 3; padding: 16px; border-radius: 4px; color: white; display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <span style="font-size: 11px; font-weight: 800; text-transform: uppercase; opacity: 0.8; letter-spacing: 1px; display: block;">Banks</span>
                <span style="font-size: 22px; font-weight: 800;">+1.85%</span>
            </div>
            <span style="font-size: 11px; font-weight: 600; opacity: 0.9;">MEBL · HBL · MCB · UBL</span>
        </div>

        <!-- Cement -->
        <div style="background: #dc2626; padding: 12px; border-radius: 4px; color: white; display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <span style="font-size: 10px; font-weight: 800; text-transform: uppercase; opacity: 0.8; display: block;">Cement</span>
                <span style="font-size: 16px; font-weight: 800;">-1.20%</span>
            </div>
            <span style="font-size: 10px; opacity: 0.9;">LUCK · DGKC</span>
        </div>

        <!-- Fertilizer -->
        <div style="background: #10b981; padding: 12px; border-radius: 4px; color: white; display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <span style="font-size: 10px; font-weight: 800; text-transform: uppercase; opacity: 0.8; display: block;">Fertilizer</span>
                <span style="font-size: 16px; font-weight: 800;">+0.45%</span>
            </div>
            <span style="font-size: 10px; opacity: 0.9;">ENGRO · FFC</span>
        </div>

        <!-- Oil & Gas -->
        <div style="background: #059669; padding: 12px; border-radius: 4px; color: white; display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <span style="font-size: 10px; font-weight: 800; text-transform: uppercase; opacity: 0.8; display: block;">Oil & Gas</span>
                <span style="font-size: 16px; font-weight: 800;">+2.10%</span>
            </div>
            <span style="font-size: 10px; opacity: 0.9;">OGDC · PPL</span>
        </div>

        <!-- Technology -->
        <div style="background: #ef4444; padding: 12px; border-radius: 4px; color: white; display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <span style="font-size: 10px; font-weight: 800; text-transform: uppercase; opacity: 0.8; display: block;">Tech</span>
                <span style="font-size: 16px; font-weight: 800;">-0.85%</span>
            </div>
            <span style="font-size: 10px; opacity: 0.9;">SYS · TRG</span>
        </div>

    </div>
</div>

            <!-- SBP Macro Indicators Shortcode -->
           
            <div style="margin-bottom: 40px;">
                <?php echo do_shortcode('[sbp_macro_grid]'); ?>
            </div>

        </main>

        <!-- ========================================== -->
        <!-- RIGHT COLUMN: SIDEBAR (33%)                -->
        <!-- ========================================== -->
        <aside class="hr-sidebar">
            
            <!-- Daily Commentary (Pulls directly from WordPress editor) -->
            <?php if (get_the_content()) : ?>
            <div>
                <div class="hr-sb-label"><span>Daily Briefing</span></div>
                <div class="hr-commentary-text">
                    <?php the_content(); ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Economic Calendar -->
            <div>
                <div class="hr-sb-label"><span>Economic Calendar</span></div>
                <div class="tradingview-widget-container">
                    <div class="tradingview-widget-container__widget"></div>
                    <script type="text/javascript" src="https://s3.tradingview.com/external-embedding/embed-widget-events.js" async>
                    {
                    "colorTheme": "light", "isTransparent": true, "width": "100%", "height": "350", "locale": "en", "importanceFilter": "-1,0,1", "countryFilter": "pk"
                    }
                    </script>
                </div>
            </div>

            <!-- PSX Top Movers -->
            <div>
                <div class="hr-sb-label"><span>PSX Top Movers</span></div>
                <div class="tradingview-widget-container">
                    <div class="tradingview-widget-container__widget"></div>
                    <script type="text/javascript" src="https://s3.tradingview.com/external-embedding/embed-widget-hotlists.js" async>
                    {
                    "colorTheme": "light", "dateRange": "12M", "exchange": "KARACHI", "showChart": true, "locale": "en", "largeChartUrl": "", "isTransparent": true, "showSymbolLogo": false, "showFloatingTooltip": false, "width": "100%", "height": "400"
                    }
                    </script>
                </div>
            </div>

            <!-- Executive Spotlight Promo -->
            <?php
            $spotlight_post = new WP_Query(array(
                'posts_per_page' => 1,
                'category_name'  => 'executive-spotlight',
            ));
            if ($spotlight_post->have_posts()) : $spotlight_post->the_post(); 
            ?>
            <div>
                <div class="hr-sb-label"><span>Executive Intelligence</span></div>
                <div class="hr-spotlight-promo">
                    <div class="hr-spotlight-label">Executive Spotlight</div>
                    <p class="hr-spotlight-title"><?php the_title(); ?></p>
                    <a href="<?php the_permalink(); ?>" class="hr-spotlight-link">Read the Interview →</a>
                </div>
            </div>
            <?php wp_reset_postdata(); endif; ?>

        </aside>

    </div><!-- .hr-page-grid -->
</div><!-- .hr-single-wrapper -->

<?php
endwhile;
get_footer();
?>