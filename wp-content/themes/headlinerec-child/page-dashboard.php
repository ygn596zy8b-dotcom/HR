<?php
/**
 * Template Name: Intelligence Dashboard
 */

// 1. Instantly bounce unauthenticated users back to your custom login
if (!is_user_logged_in()) {
    wp_redirect(home_url());
    exit;
}

get_header();

// 2. Fetch User Gamification State
$user_id = get_current_user_id();
$current_user = wp_get_current_user();
$first_name = $current_user->user_firstname ?: $current_user->display_name;

$streak = (int) get_user_meta($user_id, '_hr_streak_count', true);
$longest_streak = (int) get_user_meta($user_id, '_hr_longest_streak', true);
$total_read = (int) get_user_meta($user_id, '_hr_total_articles_read', true);
$freezes = (int) get_user_meta($user_id, '_hr_streak_freezes', true);

// Today's reading progress
$today_read_posts = get_user_meta($user_id, '_hr_today_read_posts', true) ?: array();
$today_count = min(count($today_read_posts), 3);
$progress_percent = ($today_count / 3) * 100;

// Badges
$earned_badges = get_user_meta($user_id, '_hr_earned_badges', true) ?: array();
$all_badges = [
    'analyst_initiate' => ['title' => 'Analyst Initiate', 'icon' => '🏅', 'req' => '3-Day Streak'],
    'market_observer'  => ['title' => 'Market Observer', 'icon' => '👁️', 'req' => '7-Day Streak'],
    'macro_specialist' => ['title' => 'Macro Specialist', 'icon' => '🌍', 'req' => '30-Day Streak']
];
?>

<style>
/* ================================================
   HR INTELLIGENCE DASHBOARD STYLES
================================================ */
* { box-sizing: border-box; }
#primary, .content-area, .site-main { width: 100% !important; max-width: 100% !important; padding: 0 !important; margin: 0 !important; }
#secondary, [class*="breadcrumb"] { display: none !important; }

.hr-dash-wrapper {
    background: #0A0A0A;
    min-height: 80vh;
    padding: 60px 40px 100px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    color: #fff;
}
.hr-dash-container {
    max-width: 1200px;
    margin: 0 auto;
}

/* Header & Progress */
.hr-dash-header {
    margin-bottom: 40px;
}
.hr-dash-header h1 {
    font-size: 36px;
    font-weight: 800;
    color: #fff;
    margin: 0 0 10px;
    letter-spacing: -0.5px;
}
.hr-dash-progress-card {
    background: #141414;
    border: 1px solid #222;
    padding: 24px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 40px;
}
.hr-dash-bar-wrap {
    flex: 1;
    margin: 0 30px;
    background: #222;
    height: 8px;
    border-radius: 4px;
    overflow: hidden;
}
.hr-dash-bar-fill {
    height: 100%;
    background: #E1261C;
    transition: width 0.5s ease;
}

/* Metrics Grid */
.hr-dash-metrics {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 50px;
}
.hr-metric-card {
    background: #111;
    border: 1px solid #222;
    padding: 24px;
    border-radius: 6px;
    border-top: 3px solid #E1261C;
}
.hr-metric-label {
    font-size: 11px;
    font-weight: 800;
    color: #888;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    margin-bottom: 8px;
    display: block;
}
.hr-metric-value {
    font-size: 32px;
    font-weight: 800;
    color: #fff;
    font-family: 'Courier New', monospace;
}

/* Trophy Cabinet */
.hr-dash-section-title {
    font-size: 18px;
    font-weight: 800;
    margin: 0 0 20px;
    color: #fff;
    text-transform: uppercase;
    letter-spacing: 1px;
    border-bottom: 1px solid #222;
    padding-bottom: 10px;
}
.hr-badge-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
}
.hr-badge-card {
    background: #111;
    border: 1px solid #222;
    padding: 20px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    gap: 15px;
}
.hr-badge-locked {
    opacity: 0.3;
    filter: grayscale(100%);
}
.hr-badge-icon {
    font-size: 32px;
}
.hr-badge-info h4 {
    margin: 0 0 4px;
    font-size: 14px;
    color: #fff;
}
.hr-badge-info p {
    margin: 0;
    font-size: 11px;
    color: #888;
    text-transform: uppercase;
}
/* Force parent theme wrappers to full-width dark mode */
body.page-template-page-dashboard,
.site-content, 
#content, 
.wrapper, 
.container-fluid {
    background-color: #0A0A0A !important;
    padding: 0 !important;
    margin: 0 !important;
    max-width: 100% !important;
    width: 100% !important;
}

/* Ensure the inner wrapper overrides the theme's container limits */
.hr-dash-wrapper {
    width: 100% !important;
    max-width: 100% !important;
}
@media (max-width: 900px) {
    .hr-dash-metrics { grid-template-columns: repeat(2, 1fr); }
    .hr-dash-progress-card { flex-direction: column; align-items: flex-start; gap: 20px; }
    .hr-dash-bar-wrap { margin: 0; width: 100%; }
}
</style>

<div class="hr-dash-wrapper">
    <div class="hr-dash-container">
        
        <div class="hr-dash-header">
            <h1>Welcome back, <?php echo esc_html($first_name); ?>.</h1>
            <p style="color: #888; font-size: 15px;">Your personal intelligence and reading dashboard.</p>
        </div>

        <!-- 1. Daily Progress Tracker -->
        <div class="hr-dash-progress-card">
            <div>
                <strong style="display: block; font-size: 16px; margin-bottom: 4px;">Daily Briefing Status</strong>
                <span style="font-size: 13px; color: #aaa;"><?php echo $today_count; ?> of 3 articles read today</span>
            </div>
            <div class="hr-dash-bar-wrap">
                <div class="hr-dash-bar-fill" style="width: <?php echo $progress_percent; ?>%;"></div>
            </div>
            <div>
                <?php if ($today_count >= 3): ?>
                    <span style="background: rgba(16, 185, 129, 0.15); color: #10B981; border: 1px solid #10B981; padding: 6px 12px; border-radius: 4px; font-size: 12px; font-weight: 700;">✓ Streak Locked</span>
                <?php else: ?>
                    <a href="/" style="background: #E1261C; color: #fff; text-decoration: none; padding: 8px 16px; border-radius: 4px; font-size: 12px; font-weight: 700;">Resume Reading →</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- 2. Analytics Row -->
        <div class="hr-dash-metrics">
            <div class="hr-metric-card">
                <span class="hr-metric-label">Active Streak</span>
                <span class="hr-metric-value"><?php echo $streak; ?> <span style="font-size:16px; color:#E1261C;">🔥</span></span>
            </div>
            <div class="hr-metric-card">
                <span class="hr-metric-label">Longest Streak</span>
                <span class="hr-metric-value"><?php echo $longest_streak; ?></span>
            </div>
            <div class="hr-metric-card">
                <span class="hr-metric-label">Articles Consumed</span>
                <span class="hr-metric-value"><?php echo $total_read; ?></span>
            </div>
            <div class="hr-metric-card" style="border-top-color: #10B981;">
                <span class="hr-metric-label">Streak Freezes</span>
                <span class="hr-metric-value"><?php echo $freezes; ?> <span style="font-size:16px; color:#10B981;">❄️</span></span>
            </div>
        </div>

        <!-- 3. Trophy Cabinet -->
        <h3 class="hr-dash-section-title">Earned Badges</h3>
        <div class="hr-badge-grid">
            <?php foreach ($all_badges as $key => $badge): 
                $is_unlocked = in_array($key, $earned_badges);
            ?>
                <div class="hr-badge-card <?php echo $is_unlocked ? '' : 'hr-badge-locked'; ?>">
                    <div class="hr-badge-icon"><?php echo $is_unlocked ? $badge['icon'] : '🔒'; ?></div>
                    <div class="hr-badge-info">
                        <h4><?php echo esc_html($badge['title']); ?></h4>
                        <p>Requires: <?php echo esc_html($badge['req']); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </div>
</div>

<?php get_footer(); ?>