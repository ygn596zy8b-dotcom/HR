<?php
/**
 * Category Template: Executive Spotlight
 * Filename: category-executive-spotlight.php
 */
get_header();
?>

<style>
/* ================================================
   EXECUTIVE SPOTLIGHT: LISTING PAGE
   Scoped to .es-wrapper only
================================================ */

* { box-sizing: border-box; }
/* ── NUKE PARENT THEME BREADCRUMBS ── */
.covernews-breadcrumb-wrapper,
.covernews-breadcrumb,
.breadcrumb-trail,
.breadcrumbs,
#breadcrumb {
    display: none !important;
}

/* ── CUSTOM BREADCRUMB ── */
.es-top-nav-path {
    display: block !important;
    max-width: 1100px;
    margin: 0 auto;
    padding: 24px 40px 24px;
    font-size: 11px;
    color: #9CA3AF;
    letter-spacing: 1px;
    text-transform: uppercase;
}
.es-top-nav-path a {
    color: #9CA3AF;
    text-decoration: none;
    transition: color 0.2s;
}
.es-top-nav-path a:hover { color: #C0001A; }
.es-top-nav-path span { margin: 0 8px; }

/* Mobile adjustment for breadcrumb */
@media (max-width: 900px) {
    .es-top-nav-path { padding: 20px 24px 20px; }
}

.es-wrapper {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    background: #ffffff;
    color: #111111;
    margin: 0;
    padding: 0;
}

/* ── HERO ── */
.es-hero {
    background: #0A0A0A;
    padding: 72px 40px 140px;
    position: relative;
    overflow: hidden;
}
.es-hero::before {
    content: '';
    position: absolute;
    left: -40px; top: -40px;
    width: 300px; height: 300px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(192,0,26,0.15) 0%, transparent 70%);
    pointer-events: none;
}
.es-hero::after {
    content: '';
    position: absolute;
    right: 80px; bottom: -60px;
    width: 420px; height: 420px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(192,0,26,0.08) 0%, transparent 70%);
    pointer-events: none;
}
.es-hero-inner {
    max-width: 1100px;
    margin: 0 auto;
    position: relative;
    z-index: 1;
}
.es-eyebrow {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 22px;
}
.es-eyebrow::before {
    content: '';
    display: block;
    width: 32px; height: 2px;
    background: #C0001A;
    flex-shrink: 0;
}
.es-eyebrow span {
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 4px;
    color: #C0001A;
    text-transform: uppercase;
}
.es-hero h1 {
    font-size: 60px;
    font-weight: 800;
    color: #ffffff;
    margin: 0 0 18px;
    line-height: 1.05;
    letter-spacing: -1.5px;
}
.es-hero h1 span { color: #C0001A; }
.es-hero-sub {
    font-size: 18px;
    color: #9CA3AF;
    font-weight: 300;
    margin: 0;
    line-height: 1.65;
    max-width: 520px;
}

/* ── MAIN WRAPPER ── */
.es-main {
    max-width: 1100px;
    margin: -70px auto 0;
    padding: 0 40px 100px;
    position: relative;
    z-index: 10;
}

/* ── SECTION LABEL ── */
.es-label {
    display: block;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 3px;
    color: #6B7280;
    text-transform: uppercase;
    margin-bottom: 20px;
}

/* ── FEATURED CARD ── */
.es-featured-card {
    display: flex;
    border: 1px solid #E5E7EB;
    box-shadow: 0 4px 30px rgba(0,0,0,0.08);
    background: #ffffff;
    text-decoration: none;
    color: inherit;
    transition: box-shadow 0.25s ease;
    overflow: hidden;
}
.es-featured-card:hover {
    box-shadow: 0 12px 50px rgba(0,0,0,0.14);
}

/* Left dark panel */
.es-card-left {
    flex: 0 0 400px;
    background: #111111;
    position: relative;
    min-height: 440px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}
.es-card-left::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at 55% 45%, rgba(192,0,26,0.28) 0%, transparent 65%);
}
/* The executive's portrait fills the panel; the crop keeps the top of the frame, so heads are never cut. */
.es-card-left img.es-card-portrait {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    max-width: none;
    margin: 0;
    object-fit: cover;
    object-position: 50% 20%;
    display: block;
    transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}
.es-card-left.has-portrait::before {
    z-index: 1;
    background: linear-gradient(to top, rgba(10,10,10,0.94) 0%, rgba(10,10,10,0.6) 30%, rgba(10,10,10,0) 58%);
}
@media (hover: hover) and (prefers-reduced-motion: no-preference) {
    .es-featured-card:hover .es-card-left img.es-card-portrait { transform: scale(1.03); }
}
.es-card-left-badge {
    position: absolute;
    top: 24px;
    left: 28px;
    padding: 6px 10px 5px;
    background: #C0001A;
    font-size: 10px;
    font-weight: 800;
    line-height: 1;
    letter-spacing: 2px;
    color: #ffffff;
    text-transform: uppercase;
    z-index: 2;
}
.es-card-person { position: relative; z-index: 2; padding: 0 28px; text-align: center; }
.es-card-left.has-portrait .es-card-person { position: absolute; left: 0; right: 0; bottom: 28px; text-align: left; }
.es-card-person-name { margin: 0 0 4px; font-size: 20px; font-weight: 800; line-height: 1.25; color: #ffffff; }
.es-card-person-title { margin: 0; font-size: 13px; line-height: 1.45; color: #D1D1D6; }

/* Right white panel */
.es-card-right {
    flex: 1;
    padding: 56px 52px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    background: #ffffff;
}
.es-card-meta {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 2px;
    color: #6B7280;
    text-transform: uppercase;
    margin-bottom: 16px;
}
.es-card-title {
    font-size: 30px;
    font-weight: 800;
    color: #0A0A0A;
    margin: 0 0 22px;
    line-height: 1.2;
    letter-spacing: -0.5px;
}
.es-card-quote {
    border-left: 3px solid #C0001A;
    padding-left: 20px;
    margin: 0 0 32px;
    font-size: 15px;
    font-style: italic;
    color: #374151;
    line-height: 1.75;
}
.es-card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.es-card-date { font-size: 12px; color: #6B7280; }
.es-btn {
    display: inline-block;
    background: #0A0A0A;
    color: #ffffff;
    padding: 13px 30px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: background 0.2s;
}
.es-btn:hover { background: #C0001A; color: #fff; }

/* ── INFO SPLIT ── */
.es-info-split {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 56px;
    margin-top: 80px;
    padding: 64px 0;
    border-top: 1px solid #E5E7EB;
    border-bottom: 1px solid #E5E7EB;
}
.es-info-heading {
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 3px;
    color: #C0001A;
    text-transform: uppercase;
    margin: 0 0 18px;
}
.es-info-text {
    font-size: 15px;
    color: #374151;
    line-height: 1.85;
    margin: 0 0 22px;
}
.es-info-link {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 1px;
    color: #C0001A;
    text-transform: uppercase;
    text-decoration: none;
    border-bottom: 1px solid #C0001A;
    padding-bottom: 2px;
}

/* ── COMING SOON ── */
.es-coming { margin-top: 80px; }
.es-coming-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-top: 24px;
}
@media (min-width: 901px) { .es-main > .es-label { color: #8E8E93; } } /* overlaps the dark hero here: 6:1 */
.es-coming-item {
    border: 1px solid #E5E7EB;
    padding: 36px 28px;
    background: #F9F9F9;
}
.es-coming-dot {
    width: 40px; height: 40px;
    border-radius: 50%;
    background: #E5E7EB;
    margin-bottom: 18px;
}
.es-coming-tag {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 2px;
    color: #6B7280;
    text-transform: uppercase;
    margin-bottom: 10px;
}
.es-coming-text {
    font-size: 14px;
    color: #6B7280;
    line-height: 1.6;
    margin: 0;
}

/* Archive cards: the full portrait on top, 3:2 like the uploads, cropped from the top if ever needed */
.es-coming-item.es-archive-card { display: flex; flex-direction: column; padding: 0; background: #ffffff; text-decoration: none; color: inherit; transition: box-shadow 0.25s ease, border-color 0.25s ease; }
.es-archive-card:hover { border-color: #D1D5DB; box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
.es-archive-media { aspect-ratio: 3 / 2; overflow: hidden; background: #111111; }
.es-archive-media img { display: block; width: 100%; height: 100%; max-width: none; margin: 0; object-fit: cover; object-position: 50% 20%; transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1); }
@media (hover: hover) and (prefers-reduced-motion: no-preference) { .es-archive-card:hover .es-archive-media img { transform: scale(1.04); } }
.es-archive-card .es-coming-tag { margin: 20px 24px 8px; color: #C0001A; }
.es-archive-title { margin: 0 24px 10px; font-size: 17px; font-weight: 700; line-height: 1.4; color: #0A0A0A; }
.es-archive-date { margin: auto 24px 24px; font-size: 12px; color: #6B7280; }

/* ── RESPONSIVE ── */
@media (max-width: 900px) {
    .es-hero { padding: 56px 24px 100px; }
    .es-hero h1 { font-size: 42px; }
    .es-main { margin-top: 0; padding: 40px 24px 60px; }
    .es-featured-card { flex-direction: column; }
    .es-card-left { flex: 0 0 auto; min-height: 280px; width: 100%; }
    /* phones and tablets: the whole portrait, then the name below it (never over the face) */
    .es-card-left.has-portrait { min-height: 0; display: block; }
    .es-card-left.has-portrait::before { content: none; }
    .es-card-left img.es-card-portrait { position: relative; inset: auto; height: auto; aspect-ratio: 4 / 3; }
    .es-card-left.has-portrait .es-card-person { position: relative; bottom: auto; padding: 18px 24px 22px; border-top: 3px solid #C0001A; }
    .es-card-right { padding: 36px 28px; }
    .es-card-title { font-size: 22px; }
    .es-card-footer { flex-direction: column; align-items: flex-start; gap: 16px; }
    .es-info-split { grid-template-columns: 1fr; gap: 36px; padding: 40px 0; }
    .es-coming-grid { grid-template-columns: 1fr; }
}
@media (min-width: 601px) and (max-width: 900px) {
    .es-coming-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
</style>

<div class="es-wrapper">

    <!-- BREADCRUMB -->
    <div class="es-top-nav-path">
        <a href="/">Home</a>
        <span>·</span>
        <span style="color: #6B7280;">Executive Spotlight</span>
    </div>

    <!-- HERO -->
    <section class="es-hero">
        <div class="es-hero-inner">
            <div class="es-eyebrow">
                <span>An Exclusive Series by Headline Recorder</span>
            </div>
            <h1>Executive<br><span>Spotlight</span></h1>
            <p class="es-hero-sub">Conversations with the minds shaping Pakistan's economy.<br>Unfiltered. In depth. On the record.</p>
        </div>
    </section>

    <!-- MAIN -->
    <div class="es-main">

        <span class="es-label">Latest Interview</span>

        <?php
        // 1. Fetch the absolute newest post for the big featured card
        $featured_q = new WP_Query(array(
            'posts_per_page' => 1,
            'category_name'  => 'executive-spotlight',
        ));

        // Store the ID so we don't duplicate it in the archive grid below
        $featured_post_id = 0; 

        if ($featured_q->have_posts()) :
            while ($featured_q->have_posts()) : $featured_q->the_post();
                $featured_post_id = get_the_ID();
                // The LCP of this page: fetched first, sized to the panel (portrait crop on desktop, full frame on phones).
                $img     = has_post_thumbnail() ? hr_card_image($featured_post_id, 'large', '(max-width: 900px) calc(100vw - 48px), 400px', array('loading' => 'eager', 'fetchpriority' => 'high', 'class' => 'es-card-portrait')) : '';
                $quote   = get_post_meta($featured_post_id, 'es_pull_quote', true);
                if (!$quote) $quote = wp_trim_words(get_the_excerpt(), 22, '...');
                $exec_name  = get_post_meta($featured_post_id, '_hr_exec_name', true);
                $exec_title = get_post_meta($featured_post_id, '_hr_exec_title', true);
        ?>
        <a href="<?php echo esc_url(get_permalink()); ?>" class="es-featured-card">
            <div class="es-card-left<?php echo $img ? ' has-portrait' : ''; ?>">
                <?php echo $img; // phpcs:ignore WordPress.Security.EscapeOutput -- built by wp_get_attachment_image() ?>
                <span class="es-card-left-badge">Executive Spotlight</span>
                <?php if ($exec_name || $exec_title) : ?>
                <div class="es-card-person">
                    <?php if ($exec_name) : ?><p class="es-card-person-name"><?php echo esc_html($exec_name); ?></p><?php endif; ?>
                    <?php if ($exec_title) : ?><p class="es-card-person-title"><?php echo esc_html($exec_title); ?></p><?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="es-card-right">
                <div class="es-card-meta">Featured Executive</div>
                <h2 class="es-card-title"><?php echo esc_html(get_the_title()); ?></h2>
                <blockquote class="es-card-quote"><?php echo esc_html($quote); ?></blockquote>
                <div class="es-card-footer">
                    <span class="es-card-date"><?php echo esc_html(get_the_date('F j, Y')); ?></span>
                    <span class="es-btn">Read the Interview &rarr;</span>
                </div>
            </div>
        </a>
        <?php
            endwhile;
            wp_reset_postdata();
        else : ?>
            <p style="color:#6B7280;font-size:14px;padding:40px 0;">No interviews published yet. Check back soon.</p>
        <?php endif; ?>

        <!-- INFO SPLIT -->
        <div class="es-info-split">
            <div>
                <h3 class="es-info-heading">About the Series</h3>
                <p class="es-info-text">Executive Spotlight is Headline Recorder's flagship interview series: a space where Pakistan's top business leaders speak without filters. Each conversation pairs a video interview with an in-depth written profile, distributed across web, LinkedIn, Instagram, and Facebook.</p>
            </div>
            <div>
                <h3 class="es-info-heading">Want to be Featured?</h3>
                <p class="es-info-text">Executive Spotlight is open to C-suite leaders, founders, and policymakers driving change in Pakistan's economy. We handle everything: video production, editorial writing, and full multi-platform distribution.</p>
                <a href="/contact" class="es-info-link">Get in Touch &rarr;</a>
            </div>
        </div>

        <!-- PREVIOUS CONVERSATIONS ARCHIVE -->
        <div class="es-coming">
            <span class="es-label">The Spotlight Archive</span>
            <div class="es-coming-grid">
                <?php 
                // 2. Fetch older posts, explicitly excluding the one we just showed above
                $archive_q = new WP_Query(array(
                    'posts_per_page' => -1, // Pulls all remaining interviews
                    'category_name'  => 'executive-spotlight',
                    'post__not_in'   => array($featured_post_id) // Don't repeat the hero post
                ));

                // Counter to see how many real posts we rendered
                $rendered_count = 0; 

                if ($archive_q->have_posts()) :
                    while ($archive_q->have_posts()) : $archive_q->the_post();
                        $rendered_count++;
                        $archive_img = has_post_thumbnail() ? hr_card_image(get_the_ID(), 'medium', '(max-width: 900px) calc(100vw - 96px), 300px', array('alt' => '')) : '';
                ?>
                <!-- Reusing the coming-item styling for the real archive cards to keep the grid perfect -->
                <a href="<?php echo esc_url(get_permalink()); ?>" class="es-coming-item es-archive-card">
                    <?php if ($archive_img) : ?>
                    <div class="es-archive-media">
                        <?php echo $archive_img; // phpcs:ignore WordPress.Security.EscapeOutput -- built by wp_get_attachment_image() ?>
                    </div>
                    <?php endif; ?>
                    <?php $hr_exec_name = get_post_meta(get_the_ID(), '_hr_exec_name', true); ?>
                    <div class="es-coming-tag"><?php echo $hr_exec_name ? esc_html($hr_exec_name) : 'Interview Archive'; ?></div>
                    <h3 class="es-archive-title"><?php echo esc_html(get_the_title()); ?></h3>
                    <div class="es-archive-date"><?php echo esc_html(get_the_date('F j, Y')); ?></div>
                </a>
                <?php 
                    endwhile; 
                    wp_reset_postdata(); 
                endif; 

                // 3. Fill the remaining grid space with "Coming Soon" blocks if there are fewer than 3 total items
                $empty_slots = 3 - $rendered_count;
                if ($empty_slots > 0) {
                    for ($i = 0; $i < $empty_slots; $i++) : ?>
                        <div class="es-coming-item">
                            <div class="es-coming-dot"></div>
                            <div class="es-coming-tag">Coming Soon</div>
                            <p class="es-coming-text">Next Executive Spotlight interview in preparation.</p>
                        </div>
                    <?php endfor; 
                } ?>
            </div>
        </div>

    </div>
</div>

<?php get_footer(); ?>