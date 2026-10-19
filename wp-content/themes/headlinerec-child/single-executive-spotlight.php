<?php
/**
 * Single Post Template: Executive Spotlight
 * Filename: single-executive-spotlight.php
 * Loaded via functions.php filter for executive-spotlight category
 */
get_header();

// Get post data
while (have_posts()) : the_post();
    $post_id      = get_the_ID();
    $thumb_url    = get_the_post_thumbnail_url($post_id, 'full');
    $post_date    = get_the_date('F j, Y');
    $author_name  = get_the_author();
    $author_id     = get_the_author_meta('ID');
    $author_avatar = get_avatar_url($author_id, array('size' => 104)); // Grab a crisp, high-res avatar
    $post_content = get_the_content();
    $permalink    = get_permalink();
    $post_title   = get_the_title();

    // YouTube embed — get first YouTube URL from content
    $youtube_embed = '';
    preg_match('/(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $post_content, $yt_matches);
    if (!empty($yt_matches[1])) {
        $youtube_embed = 'https://www.youtube.com/embed/' . $yt_matches[1];
    }

    // Clean and process content
    $post_content = apply_filters('the_content', $post_content);
?>

<style>
/* ================================================
   EXECUTIVE SPOTLIGHT — SINGLE ARTICLE PAGE
   Scoped to .esa-wrapper
================================================ */

* { box-sizing: border-box; }

/* ── OVERRIDES & BUG FIXES ── */

/* Force full width — kill sidebar/theme constraints */
.esa-wrapper,
.esa-wrapper * {
    max-width: none;
}

/* Hide theme sidebar, "Latest" widgets, and adjacent post junk (PROTECTS FOOTER) */
.esa-wrapper ~ aside,
.esa-wrapper ~ .sidebar,
#secondary,
.site-content .widget-area,
.site-content .widget,
.related-posts-wrapper,
.related-posts,
.post-navigation,
.entry-header {
    display: none !important;
}

/* Nuke parent theme breadcrumbs */
.covernews-breadcrumb-wrapper,
.covernews-breadcrumb,
.breadcrumb-trail,
.breadcrumbs,
#breadcrumb {
    display: none !important;
}

/* Fix the massive gap: Hide the native Gutenberg video wrapper inside the body */
.esa-body figure.wp-block-embed-youtube,
.esa-body .wp-block-embed,
.esa-body p:empty {
    display: none !important;
}

/* Force content area full width */
#primary,
#main,
.site-main,
.content-area {
    width: 100% !important;
    max-width: 100% !important;
    float: none !important;
    margin: 0 !important;
    padding: 0 !important;
}

.esa-wrapper {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    background: #ffffff;
    color: #111111;
    margin: 0;
    padding: 0;
}

/* ── BREADCRUMB (Custom) ── */
.esa-breadcrumb {
    max-width: 820px;
    margin: 0 auto;
    padding: 20px 40px 0;
    font-size: 11px;
    color: #9CA3AF;
    letter-spacing: 1px;
    text-transform: uppercase;
}
.esa-breadcrumb a {
    color: #9CA3AF;
    text-decoration: none;
}
.esa-breadcrumb a:hover { color: #C0001A; }
.esa-breadcrumb span { margin: 0 8px; }

/* ── ARTICLE CONTAINER ── */
.esa-article {
    max-width: 820px;
    margin: 0 auto;
    padding: 32px 40px 100px;
}

/* ── SERIES BADGE ── */
.esa-series-badge {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 24px;
}
.esa-series-badge::before {
    content: '';
    display: block;
    width: 24px;
    height: 2px;
    background: #C0001A;
    flex-shrink: 0;
}
.esa-series-badge span {
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 4px;
    color: #C0001A;
    text-transform: uppercase;
}

/* ── HEADLINE ── */
.esa-headline {
    font-size: 40px;
    font-weight: 800;
    color: #0A0A0A;
    margin: 0 0 32px;
    line-height: 1.15;
    letter-spacing: -0.8px;
}

/* ── EXECUTIVE STRIP ── */
.esa-exec-strip {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px 0;
    border-top: 1px solid #E5E7EB;
    border-bottom: 1px solid #E5E7EB;
    margin-bottom: 40px;
}
.esa-exec-avatar {
    width: 52px;
    height: 52px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
    border: 2px solid #E5E7EB;
}
.esa-exec-info { flex: 1; }
.esa-exec-name {
    font-size: 14px;
    font-weight: 700;
    color: #0A0A0A;
    margin: 0 0 3px;
}
.esa-exec-role {
    font-size: 12px;
    color: #6B7280;
    margin: 0;
}
.esa-exec-date {
    font-size: 12px;
    color: #9CA3AF;
    margin-left: auto;
    flex-shrink: 0;
}

/* ── VIDEO EMBED ── */
.esa-video-wrapper {
    margin-bottom: 48px;
    border: 1px solid #E5E7EB;
    overflow: hidden;
}
.esa-video-label {
    background: #0A0A0A;
    padding: 12px 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.esa-video-label::before {
    content: '';
    display: block;
    width: 16px;
    height: 2px;
    background: #C0001A;
    flex-shrink: 0;
}
.esa-video-label span {
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 3px;
    color: #C0001A;
    text-transform: uppercase;
}
.esa-video-frame {
    position: relative;
    padding-bottom: 56.25%;
    height: 0;
    background: #111;
}
.esa-video-frame iframe {
    position: absolute;
    top: 0; left: 0;
    width: 100%; height: 100%;
    border: none;
    display: block;
}

/* ── ARTICLE BODY ── */
.esa-body {
    font-size: 17px;
    line-height: 1.85;
    color: #1F2937;
}
.esa-body p {
    margin: 0 0 24px;
}
.esa-body h2 {
    font-size: 24px;
    font-weight: 800;
    color: #0A0A0A;
    margin: 48px 0 16px;
    letter-spacing: -0.3px;
    line-height: 1.25;
}
.esa-body h3 {
    font-size: 20px;
    font-weight: 700;
    color: #0A0A0A;
    margin: 36px 0 12px;
}

/* Auto-style ALL blockquotes with red border */
.esa-body blockquote,
.esa-body .wp-block-quote {
    border: none !important;
    border-left: 4px solid #C0001A !important;
    padding: 24px 32px !important;
    margin: 40px 0 !important;
    background: #FAFAFA !important;
    font-style: italic;
    font-size: 20px !important;
    color: #0A0A0A !important;
    line-height: 1.65 !important;
    quotes: none !important;
}
.esa-body blockquote p,
.esa-body .wp-block-quote p {
    margin: 0 0 12px !important;
    font-size: 20px !important;
    font-style: italic;
    color: #0A0A0A !important;
}
.esa-body blockquote cite,
.esa-body .wp-block-quote cite,
.esa-body blockquote footer,
.esa-body .wp-block-quote .wp-block-quote__citation {
    display: block;
    font-size: 12px !important;
    font-weight: 700;
    font-style: normal !important;
    color: #6B7280 !important;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    margin-top: 12px;
}

/* Lists */
.esa-body ul,
.esa-body ol {
    margin: 0 0 24px 24px;
    padding: 0;
}
.esa-body li { margin-bottom: 8px; }

/* Bold & links */
.esa-body strong { color: #0A0A0A; font-weight: 700; }
.esa-body a { color: #C0001A; text-decoration: underline; }
.esa-body a:hover { opacity: 0.8; }

/* Images inside content */
.esa-body img {
    width: 100%;
    height: auto;
    display: block;
    margin: 32px 0;
}
.esa-body figure { margin: 32px 0; }
.esa-body figcaption {
    font-size: 12px;
    color: #9CA3AF;
    text-align: center;
    margin-top: 8px;
    font-style: italic;
}

/* ── SHARE STRIP ── */
.esa-share-strip {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #0A0A0A;
    padding: 28px 36px;
    margin-top: 64px;
}
.esa-share-left {}
.esa-share-eyebrow {
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 3px;
    color: #C0001A;
    text-transform: uppercase;
    margin-bottom: 6px;
}
.esa-share-name {
    font-size: 15px;
    color: #ffffff;
    font-weight: 500;
}
.esa-linkedin-btn {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: #0A66C2;
    color: #ffffff;
    padding: 13px 24px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 1px;
    text-transform: uppercase;
    text-decoration: none;
    flex-shrink: 0;
    transition: opacity 0.2s;
}
.esa-linkedin-btn:hover { opacity: 0.85; color: #fff; }
.esa-linkedin-icon {
    font-size: 14px;
    font-weight: 900;
    font-style: italic;
}

/* ── FEATURE CTA ── */
.esa-feature-cta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 32px;
    border: 1px solid #E5E7EB;
    padding: 36px 40px;
    margin-top: 24px;
}
.esa-cta-text {}
.esa-cta-heading {
    font-size: 17px;
    font-weight: 700;
    color: #0A0A0A;
    margin: 0 0 6px;
}
.esa-cta-sub {
    font-size: 13px;
    color: #6B7280;
    margin: 0;
}
.esa-cta-btn {
    display: inline-block;
    background: #C0001A;
    color: #ffffff;
    padding: 13px 30px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    text-decoration: none;
    white-space: nowrap;
    flex-shrink: 0;
    transition: opacity 0.2s;
}
.esa-cta-btn:hover { opacity: 0.85; color: #fff; }

/* ── MORE FROM SPOTLIGHT ── */
.esa-more {
    margin-top: 64px;
    padding-top: 32px;
    border-top: 1px solid #E5E7EB;
}
.esa-more-label {
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 3px;
    color: #6B7280;
    text-transform: uppercase;
    margin-bottom: 28px;
    display: block;
}
.esa-more-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}
.esa-more-item {
    border: 1px solid #E5E7EB;
    padding: 28px 24px;
    text-decoration: none;
    color: inherit;
    display: block;
    transition: box-shadow 0.2s;
}
.esa-more-item:hover { box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
.esa-more-item-badge {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 2px;
    color: #C0001A;
    text-transform: uppercase;
    margin-bottom: 10px;
}
.esa-more-item-title {
    font-size: 15px;
    font-weight: 700;
    color: #0A0A0A;
    line-height: 1.4;
    margin: 0;
}
.esa-more-item-date {
    font-size: 12px;
    color: #9CA3AF;
    margin-top: 10px;
}
.esa-more-coming {
    background: #F9F9F9;
}
.esa-more-coming .esa-more-item-title {
    color: #D1D5DB;
    font-weight: 400;
}

/* ── RESPONSIVE ── */
@media (max-width: 768px) {
    .esa-breadcrumb { padding: 16px 24px 0; }
    .esa-article { padding: 24px 24px 60px; }
    .esa-headline { font-size: 28px; }
    .esa-body { font-size: 16px; }
    .esa-body blockquote,
    .esa-body .wp-block-quote { font-size: 17px !important; padding: 20px 20px !important; }
    .esa-body blockquote p,
    .esa-body .wp-block-quote p { font-size: 17px !important; }
    .esa-share-strip { flex-direction: column; align-items: flex-start; gap: 20px; padding: 24px; }
    .esa-feature-cta { flex-direction: column; align-items: flex-start; padding: 28px 24px; }
    .esa-more-grid { grid-template-columns: 1fr; }
}
</style>

<div class="esa-wrapper">

    <!-- BREADCRUMB -->
    <div class="esa-breadcrumb">
        <a href="/">Home</a>
        <span>·</span>
        <a href="/category/executive-spotlight/">Executive Spotlight</a>
    </div>

    <article class="esa-article">

        <!-- SERIES BADGE -->
        <div class="esa-series-badge">
            <span>Executive Spotlight</span>
        </div>

        <!-- HEADLINE -->
        <h1 class="esa-headline"><?php echo esc_html($post_title); ?></h1>

        <!-- EXECUTIVE STRIP -->
        <!-- EXECUTIVE STRIP -->
        <div class="esa-exec-strip">
            <?php if ($author_avatar) : ?>
            <img src="<?php echo esc_url($author_avatar); ?>"
                 alt="<?php echo esc_attr($author_name); ?>"
                 class="esa-exec-avatar">
            <?php else : ?>
            <div class="esa-exec-avatar" style="background:#F3F4F6; display:flex; align-items:center; justify-content:center; font-size:18px; font-weight:700; color:#6B7280;">
                <?php echo esc_html(strtoupper(substr($author_name, 0, 2))); ?>
            </div>
            <?php endif; ?>
            <div class="esa-exec-info">
                <p class="esa-exec-name"><?php echo esc_html($author_name); ?></p>
                <p class="esa-exec-role">Executive Spotlight — Headline Recorder</p>
            </div>
            <?php do_action( 'hr_single_after_byline', get_the_ID() ); ?>
            <span class="esa-exec-date"><?php echo esc_html($post_date); ?></span>
        </div>

        <?php do_action( 'hr_single_before_content', get_the_ID() ); ?>

        <!-- VIDEO EMBED (if YouTube URL found in content) -->
        <?php if ($youtube_embed) : ?>
        <div class="esa-video-wrapper">
            <div class="esa-video-label">
                <span>Watch the Full Interview</span>
            </div>
            <div class="esa-video-frame">
                <iframe src="<?php echo esc_url($youtube_embed); ?>"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen
                        loading="lazy"></iframe>
            </div>
        </div>
        <?php endif; ?>

        <!-- ARTICLE BODY -->
        <div class="esa-body">
            <?php echo $post_content; ?>
        </div>
        <?php do_action( 'hr_single_after_content', get_the_ID() ); ?>

        <!-- SHARE STRIP -->
        <div class="esa-share-strip">
            <div class="esa-share-left">
                <div class="esa-share-eyebrow">Featured in Executive Spotlight</div>
                <div class="esa-share-name"><?php echo esc_html($post_title); ?></div>
            </div>
            <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo urlencode($permalink); ?>"
               target="_blank"
               rel="noopener"
               class="esa-linkedin-btn">
                <span class="esa-linkedin-icon">in</span>
                Share on LinkedIn
            </a>
        </div>

        <!-- FEATURE CTA -->
        <div class="esa-feature-cta">
            <div class="esa-cta-text">
                <p class="esa-cta-heading">Want to be featured in Executive Spotlight?</p>
                <p class="esa-cta-sub">We handle video production, editorial writing, and full multi-platform distribution.</p>
            </div>
            <a href="/contact" class="esa-cta-btn">Get in Touch &rarr;</a>
        </div>

        <!-- MORE FROM SPOTLIGHT -->
        <div class="esa-more">
            <span class="esa-more-label">More from Executive Spotlight</span>
            <div class="esa-more-grid">
                <?php
                $more_q = new WP_Query(array(
                    'posts_per_page' => 2,
                    'category_name'  => 'executive-spotlight',
                    'post__not_in'   => array($post_id),
                ));

                if ($more_q->have_posts()) :
                    while ($more_q->have_posts()) : $more_q->the_post(); ?>
                    <a href="<?php the_permalink(); ?>" class="esa-more-item">
                        <div class="esa-more-item-badge">Executive Spotlight</div>
                        <p class="esa-more-item-title"><?php the_title(); ?></p>
                        <div class="esa-more-item-date"><?php echo get_the_date('F j, Y'); ?></div>
                    </a>
                    <?php endwhile;
                    wp_reset_postdata();
                endif;

                // Fill remaining slots with coming soon
                $published = $more_q->post_count;
                for ($i = $published; $i < 2; $i++) : ?>
                <div class="esa-more-item esa-more-coming">
                    <div class="esa-more-item-badge">Coming Soon</div>
                    <p class="esa-more-item-title">Next Executive Spotlight interview in preparation.</p>
                </div>
                <?php endfor; ?>
            </div>
        </div>

    </article>
</div>

<?php
endwhile;
get_footer();
?>