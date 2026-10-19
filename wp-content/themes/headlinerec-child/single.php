<?php
/**
 * Single Post Template — Custom Premium Layout
 * File: single.php (place in child theme root)
 * Overrides CoverNews parent single.php
 */
get_header();

while (have_posts()) : the_post();
    $post_id     = get_the_ID();
    $thumb_url   = get_the_post_thumbnail_url($post_id, 'full');
    $thumb_alt   = get_post_meta(get_post_thumbnail_id($post_id), '_wp_attachment_image_alt', true);
    $post_date   = get_the_date('F j, Y');
    
    // Native author fallback
    $author_name   = get_the_author();
    $author_bio    = get_the_author_meta('description');
    $author_id     = get_the_author_meta('ID');
    $author_link   = get_author_posts_url($author_id);
    $author_avatar = get_avatar_url($author_id, array('size' => 72));
    $is_guest      = false;

    // Intercept Guest Author data for the 'Opinion' category
    $guest_name     = get_post_meta($post_id, 'guest_author_name', true);
    $guest_avatar_m = get_post_meta($post_id, 'guest_author_avatar', true);
    $guest_bio      = get_post_meta($post_id, 'guest_author_bio', true);
    $guest_linkedin = get_post_meta($post_id, 'guest_author_linkedin', true);
    $guest_twitter  = get_post_meta($post_id, 'guest_author_twitter', true);
    $guest_facebook = get_post_meta($post_id, 'guest_author_facebook', true);

    if ( !empty($guest_name) && has_category('opinion', $post_id) ) {
        $author_name = $guest_name;
        $author_bio  = $guest_bio;
        $is_guest    = true;
        
        $author_avatar = !empty($guest_avatar_m) ? $guest_avatar_m : false;
        
        // Generate Taxonomy Link for the Archive
        $term = get_term_by('name', $guest_name, 'guest_author');
        
        if ($term && !is_wp_error($term)) {
            $author_link = get_term_link($term);
            
            // --- SMART FALLBACK SYSTEM ---
            // If the editor left the bio, avatar, or socials blank on THIS post, 
            // look for them in the author's previous posts so it carries over perfectly.
            if (empty($author_avatar) || empty($author_bio) || empty($guest_linkedin) || empty($guest_twitter)) {
                $fallback_posts = get_posts(array(
                    'numberposts' => 5, // Check up to 5 recent posts by this author
                    'tax_query' => array(
                        array(
                            'taxonomy' => 'guest_author',
                            'field'    => 'term_id',
                            'terms'    => $term->term_id,
                        )
                    )
                ));
                foreach ($fallback_posts as $fp) {
                    if (empty($author_avatar)) {
                        $f_av = get_post_meta($fp->ID, 'guest_author_avatar', true);
                        if (!empty($f_av)) $author_avatar = $f_av;
                    }
                    if (empty($author_bio)) {
                        $f_bio = get_post_meta($fp->ID, 'guest_author_bio', true);
                        if (!empty($f_bio)) $author_bio = $f_bio;
                    }
                    if (empty($guest_linkedin)) {
                        $f_li = get_post_meta($fp->ID, 'guest_author_linkedin', true);
                        if (!empty($f_li)) $guest_linkedin = $f_li;
                    }
                    if (empty($guest_twitter)) {
                        $f_tw = get_post_meta($fp->ID, 'guest_author_twitter', true);
                        if (!empty($f_tw)) $guest_twitter = $f_tw;
                    }
                    if (empty($guest_facebook)) {
                        $f_fb = get_post_meta($fp->ID, 'guest_author_facebook', true);
                        if (!empty($f_fb)) $guest_facebook = $f_fb;
                    }
                    
                    // Stop searching if we found at least the avatar and bio
                    if (!empty($author_avatar) && !empty($author_bio)) break;
                }
            }
        } else {
            // Fallback generated URL if term hasn't registered yet
            $author_link = home_url('/opinion-author/' . sanitize_title($guest_name) . '/');
        }
    }

    // Determine Initials for Fallback UI (e.g., "Shahid Anwar" -> "SA")
    $name_parts = explode(' ', trim($author_name));
    if (count($name_parts) >= 2) {
        $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1));
    } else {
        $initials = strtoupper(substr($author_name, 0, 2));
    }

    $permalink   = get_permalink();
    $post_title  = get_the_title();

    // Reading time estimate
    $content_text    = strip_tags(get_the_content());
    $word_count      = str_word_count($content_text);
    $reading_minutes = max(1, ceil($word_count / 200));

    // Primary category
    $categories   = get_the_category();
    $primary_cat  = !empty($categories) ? $categories[0] : null;
    $cat_name     = $primary_cat ? $primary_cat->name : '';
    $cat_url      = $primary_cat ? get_category_link($primary_cat->term_id) : '#';

    // Tags
    $tags = get_the_tags();

    // Sidebar: latest posts
    $latest_posts = new WP_Query(array(
        'posts_per_page' => 4,
        'post__not_in'   => array($post_id),
        'orderby'        => 'date',
        'order'          => 'DESC',
    ));

    // More stories (same category)
    $more_posts = new WP_Query(array(
        'posts_per_page' => 3,
        'post__not_in'   => array($post_id),
        'category__in'   => $primary_cat ? array($primary_cat->term_id) : array(),
        'orderby'        => 'date',
        'order'          => 'DESC',
    ));

    // Executive Spotlight sidebar promo
    $spotlight_post = new WP_Query(array(
        'posts_per_page' => 1,
        'category_name'  => 'executive-spotlight',
        'post__not_in'   => array($post_id),
    ));
?>

<!-- ========================================================= -->
<!-- HR SEO: DYNAMIC NEWSARTICLE & AUTHOR SCHEMA (E-E-A-T FIX) -->
<!-- ========================================================= -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "NewsArticle",
  "mainEntityOfPage": {
    "@type": "WebPage",
    "@id": "<?php echo esc_url($permalink); ?>"
  },
  "headline": "<?php echo esc_js($post_title); ?>",
  "datePublished": "<?php echo get_the_date('c'); ?>",
  "dateModified": "<?php echo get_the_modified_date('c'); ?>",
  <?php if (!empty($thumb_url)) : ?>
  "image": [
    "<?php echo esc_url($thumb_url); ?>"
  ],
  <?php endif; ?>
  "author": {
    "@type": "Person",
    "name": "<?php echo esc_js($author_name); ?>",
    "url": "<?php echo esc_url($author_link); ?>"
    <?php if (!empty($author_bio)) : ?>,
    "description": "<?php echo esc_js(strip_tags($author_bio)); ?>"
    <?php endif; ?>
  },
  "publisher": {
    "@type": "Organization",
    "name": "Headline Recorder",
    "url": "<?php echo esc_url(home_url('/')); ?>",
    "logo": {
      "@type": "ImageObject",
      "url": "<?php echo esc_url(get_stylesheet_directory_uri() . '/assets/logo.png'); ?>"
    }
  }
}
</script>

<style>
/* ================================================
   HR PREMIUM SINGLE ARTICLE — FULL OVERRIDE
   Scoped to .hr-single-wrapper
================================================ */

* { box-sizing: border-box; }

/* Force full width, kill theme layout */
.section-block-upper,
.section-block-upper.row {
    display: block !important;
    margin: 0 !important;
    padding: 0 !important;
    width: 100% !important;
    max-width: 100% !important;
}

#secondary,
.widget-area,
aside#secondary {
    display: none !important;
}

#primary,
.content-area {
    width: 100% !important;
    max-width: 100% !important;
    float: none !important;
    padding: 0 !important;
    margin: 0 !important;
}

#main,
.site-main {
    margin: 0 !important;
    padding: 0 !important;
}

/* Kill theme article padding/margin */
.af-single-article,
.entry-content-wrap {
    padding: 0 !important;
    margin: 0 !important;
}

/* Kill theme breadcrumb */
[class*="breadcrumb"],
.covernews-breadcrumb,
.breadcrumbs,
#breadcrumbs {
    display: none !important;
}

/* Kill comments */
#comments,
.comment-respond,
.comments-area {
    display: none !important;
}

/* Kill related posts default */
.related-posts-wrap,
.covernews-related-posts {
    display: none !important;
}

/* ── WRAPPER ── */
.hr-single-wrapper {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    background: #ffffff;
    color: #111111;
}

/* ── BREADCRUMB ── */
.hr-breadcrumb {
    max-width: 1200px;
    margin: 0 auto;
    padding: 18px 48px 0;
    font-size: 11px;
    color: #9CA3AF;
    letter-spacing: 1px;
    text-transform: uppercase;
}
.hr-breadcrumb a {
    color: #9CA3AF;
    text-decoration: none;
    transition: color 0.2s;
}
.hr-breadcrumb a:hover { color: #C0001A; }
.hr-breadcrumb span { margin: 0 8px; }

/* ── MAIN GRID ── */
.hr-page-grid {
    max-width: 1200px;
    margin: 0 auto;
    padding: 32px 48px 80px;
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 64px;
    align-items: start;
}

/* ── ARTICLE LEFT ── */
.hr-article {}

/* Series / Category badge */
.hr-cat-badge {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
}
.hr-cat-badge::before {
    content: '';
    display: block;
    width: 20px; height: 2px;
    background: #C0001A;
    flex-shrink: 0;
}
.hr-cat-badge a {
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 3px;
    color: #C0001A;
    text-transform: uppercase;
    text-decoration: none;
}

/* Headline */
.hr-headline {
    font-size: 38px;
    font-weight: 800;
    color: #0A0A0A;
    margin: 0 0 24px;
    line-height: 1.15;
    letter-spacing: -0.8px;
}

/* Meta strip */
.hr-meta-strip {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 16px 0;
    border-top: 1px solid #E5E7EB;
    border-bottom: 1px solid #E5E7EB;
    margin-bottom: 32px;
    flex-wrap: wrap;
}
.hr-meta-author {
    display: flex;
    align-items: center;
    gap: 12px;
}
.hr-meta-avatar {
    width: 36px; height: 36px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #E5E7EB;
    flex-shrink: 0;
}
.hr-meta-avatar-placeholder {
    width: 36px; height: 36px;
    border-radius: 50%;
    background: #F3F4F6;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 700;
    color: #6B7280;
    flex-shrink: 0;
}
.hr-meta-name {
    font-size: 13px;
    font-weight: 700;
    color: #0A0A0A;
}
.hr-meta-desk {
    font-size: 11px;
    color: #6B7280;
}
.hr-meta-divider {
    width: 1px; height: 28px;
    background: #E5E7EB;
    flex-shrink: 0;
}
.hr-meta-date,
.hr-meta-readtime {
    font-size: 12px;
    color: #9CA3AF;
}
.hr-meta-share {
    margin-left: auto;
    display: flex;
    gap: 8px;
}
.hr-share-btn {
    width: 30px; height: 30px;
    border: 1px solid #E5E7EB;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 11px;
    color: #6B7280;
    text-decoration: none;
    transition: all 0.2s;
}
.hr-share-btn:hover {
    border-color: #C0001A;
    color: #C0001A;
}

/* Hero image */
.hr-hero-image-wrap {
    margin-bottom: 32px;
}
.hr-hero-image-wrap img {
    width: 100%;
    height: auto;
    display: block;
}
.hr-hero-caption {
    font-size: 12px;
    color: #9CA3AF;
    font-style: italic;
    padding: 10px 0;
    border-bottom: 1px solid #F3F4F6;
}

/* Article body */
.hr-body {
    font-size: 17px;
    line-height: 1.85;
    color: #1F2937;
}
.hr-body p { margin: 0 0 24px; }
.hr-body h2 {
    font-size: 24px;
    font-weight: 800;
    color: #0A0A0A;
    margin: 48px 0 16px;
    letter-spacing: -0.3px;
    line-height: 1.25;
}
.hr-body h3 {
    font-size: 20px;
    font-weight: 700;
    color: #0A0A0A;
    margin: 36px 0 12px;
}
.hr-body strong { color: #0A0A0A; font-weight: 700; }
.hr-body a { color: #C0001A; text-decoration: underline; }
.hr-body a:hover { opacity: 0.8; }
.hr-body ul,
.hr-body ol { margin: 0 0 24px 24px; padding: 0; }
.hr-body li { margin-bottom: 8px; }
.hr-body img { width: 100%; height: auto; display: block; margin: 32px 0; }
.hr-body figure { margin: 32px 0; }
.hr-body figcaption {
    font-size: 12px;
    color: #9CA3AF;
    text-align: center;
    margin-top: 8px;
    font-style: italic;
}

/* Auto-style blockquotes */
.hr-body blockquote,
.hr-body .wp-block-quote {
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
.hr-body blockquote p,
.hr-body .wp-block-quote p {
    margin: 0 0 12px !important;
    font-size: 20px !important;
    font-style: italic;
    color: #0A0A0A !important;
}
.hr-body blockquote p:last-child,
.hr-body .wp-block-quote p:last-child { margin-bottom: 0 !important; }
.hr-body blockquote cite,
.hr-body .wp-block-quote cite,
.hr-body .wp-block-quote__citation {
    display: block;
    font-size: 12px !important;
    font-weight: 700;
    font-style: normal !important;
    color: #6B7280 !important;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    margin-top: 12px;
}

/* Tags */
.hr-tags {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin: 40px 0;
    padding-top: 24px;
    border-top: 1px solid #E5E7EB;
}
.hr-tag {
    font-size: 11px;
    font-weight: 600;
    padding: 6px 14px;
    border: 1px solid #E5E7EB;
    color: #6B7280;
    cursor: pointer;
    letter-spacing: 0.5px;
    text-decoration: none;
    transition: all 0.2s;
}
.hr-tag:hover {
    border-color: #C0001A;
    color: #C0001A;
}

/* Author box */
.hr-author-box {
    display: flex;
    gap: 24px;
    padding: 28px 32px;
    border: 1px solid #E5E7EB;
    margin: 0 0 40px;
}
.hr-author-avatar {
    width: 64px; height: 64px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
    border: 2px solid #E5E7EB;
}
.hr-author-avatar-placeholder {
    width: 64px; height: 64px;
    border-radius: 50%;
    background: #F3F4F6;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    font-weight: 700;
    color: #9CA3AF;
    flex-shrink: 0;
}
.hr-author-label {
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 3px;
    color: #C0001A;
    text-transform: uppercase;
    margin-bottom: 6px;
}
.hr-author-name {
    font-size: 15px;
    font-weight: 700;
    color: #0A0A0A;
    margin-bottom: 8px;
}
/* Fix the flexbox container from blowing out */
.hr-author-box > div:last-child {
    flex: 1;
    min-width: 0; /* This forces the flex child to shrink and wrap text */
}
.hr-author-bio {
    font-size: 13px;
    color: #6B7280;
    line-height: 1.65;
    margin: 0;
    word-wrap: break-word !important; 
    overflow-wrap: break-word !important;
    word-break: break-word !important;
}

/* Prev/Next */
.hr-post-nav {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 56px;
    padding-top: 32px;
    border-top: 1px solid #E5E7EB;
}
.hr-nav-item {
    padding: 20px 24px;
    border: 1px solid #E5E7EB;
    cursor: pointer;
    text-decoration: none;
    display: block;
    transition: box-shadow 0.2s;
}
.hr-nav-item:hover { box-shadow: 0 4px 20px rgba(0,0,0,0.06); }
.hr-nav-item.next { text-align: right; }
.hr-nav-direction {
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 2px;
    color: #9CA3AF;
    text-transform: uppercase;
    margin-bottom: 10px;
    display: block;
}
.hr-nav-title {
    font-size: 13px;
    font-weight: 700;
    color: #0A0A0A;
    line-height: 1.4;
}

/* More Stories */
.hr-more-stories { margin-top: 8px; }
.hr-section-label {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 28px;
}
.hr-section-label::before {
    content: '';
    display: block;
    width: 28px; height: 2px;
    background: #C0001A;
    flex-shrink: 0;
}
.hr-section-label span {
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 4px;
    color: #6B7280;
    text-transform: uppercase;
}
.hr-more-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}
.hr-more-card {
    cursor: pointer;
    text-decoration: none;
    display: block;
}
.hr-more-thumb {
    height: 130px;
    background: #0A0A0A;
    margin-bottom: 14px;
    overflow: hidden;
    position: relative;
}
.hr-more-thumb img {
    width: 100%; height: 100%;
    object-fit: cover;
    display: block;
}
.hr-more-thumb-cat {
    position: absolute;
    top: 10px; left: 10px;
    font-size: 9px;
    font-weight: 800;
    color: #fff;
    background: #C0001A;
    padding: 3px 8px;
    letter-spacing: 1.5px;
    text-transform: uppercase;
}
.hr-more-title {
    font-size: 13px;
    font-weight: 700;
    color: #0A0A0A;
    margin: 0 0 6px;
    line-height: 1.4;
}
.hr-more-date {
    font-size: 11px;
    color: #9CA3AF;
}

/* ── SIDEBAR ── */
.hr-sidebar {
    position: sticky;
    top: 24px;
    display: flex;
    flex-direction: column;
    gap: 40px;
}

/* Sidebar section label */
.hr-sb-label {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 18px;
}
.hr-sb-label::before {
    content: '';
    display: block;
    width: 20px; height: 2px;
    background: #C0001A;
    flex-shrink: 0;
}
.hr-sb-label span {
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 3px;
    color: #6B7280;
    text-transform: uppercase;
}

/* Macro indicators */
.hr-macro-box {
    background: #0A0A0A;
    padding: 20px 24px;
}
.hr-macro-item {
    padding: 14px 0;
    border-bottom: 1px solid rgba(255,255,255,0.06);
}
.hr-macro-item:last-child { border-bottom: none; padding-bottom: 0; }
.hr-macro-item:first-child { padding-top: 0; }
.hr-macro-item-label {
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 2px;
    color: #6B7280;
    text-transform: uppercase;
    margin-bottom: 4px;
}
.hr-macro-item-val {
    font-size: 20px;
    font-weight: 800;
    color: #ffffff;
    margin-bottom: 2px;
}
.hr-macro-item-sub {
    font-size: 10px;
    color: #6B7280;
}
.hr-macro-link {
    display: inline-block;
    margin-top: 16px;
    font-size: 11px;
    font-weight: 700;
    color: #C0001A;
    letter-spacing: 1px;
    text-transform: uppercase;
    text-decoration: none;
    border-bottom: 1px solid #C0001A;
    padding-bottom: 1px;
}

/* Latest posts sidebar */
.hr-sb-post {
    display: flex;
    gap: 14px;
    cursor: pointer;
    padding-bottom: 16px;
    margin-bottom: 16px;
    border-bottom: 1px solid #F3F4F6;
    text-decoration: none;
}
.hr-sb-post:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
.hr-sb-thumb {
    flex: 0 0 72px;
    height: 56px;
    background: #0A0A0A;
    overflow: hidden;
    position: relative;
    flex-shrink: 0;
}
.hr-sb-thumb img {
    width: 100%; height: 100%;
    object-fit: cover;
    display: block;
}
.hr-sb-cat {
    font-size: 9px;
    font-weight: 800;
    letter-spacing: 1.5px;
    color: #C0001A;
    text-transform: uppercase;
    margin-bottom: 5px;
}
.hr-sb-title {
    font-size: 12px;
    font-weight: 700;
    color: #0A0A0A;
    line-height: 1.4;
    margin-bottom: 4px;
}
.hr-sb-date { font-size: 11px; color: #9CA3AF; }

/* Spotlight promo */
.hr-spotlight-promo {
    background: #0A0A0A;
    padding: 24px;
}
.hr-spotlight-label {
    font-size: 9px;
    font-weight: 800;
    letter-spacing: 3px;
    color: #C0001A;
    text-transform: uppercase;
    margin-bottom: 12px;
}
.hr-spotlight-title {
    font-size: 13px;
    font-weight: 700;
    color: #ffffff;
    margin: 0 0 12px;
    line-height: 1.4;
}
.hr-spotlight-quote {
    border-left: 2px solid #C0001A;
    padding-left: 12px;
    margin: 0 0 16px;
    font-size: 12px;
    font-style: italic;
    color: #9CA3AF;
    line-height: 1.5;
}
.hr-spotlight-link {
    font-size: 10px;
    font-weight: 700;
    color: #C0001A;
    letter-spacing: 1px;
    text-transform: uppercase;
    text-decoration: none;
    border-bottom: 1px solid #C0001A;
    padding-bottom: 1px;
}

/* Newsletter sidebar */
.hr-sb-newsletter {
    border: 1px solid #E5E7EB;
    padding: 24px;
}
.hr-sb-newsletter-label {
    font-size: 9px;
    font-weight: 800;
    letter-spacing: 3px;
    color: #C0001A;
    text-transform: uppercase;
    margin-bottom: 10px;
}
.hr-sb-newsletter-heading {
    font-size: 14px;
    font-weight: 700;
    color: #0A0A0A;
    margin: 0 0 8px;
    line-height: 1.4;
}
.hr-sb-newsletter-sub {
    font-size: 12px;
    color: #6B7280;
    margin: 0 0 16px;
    line-height: 1.5;
}
.hr-sb-newsletter form {
    display: flex;
    gap: 0;
}
.hr-sb-newsletter form {
    display: flex;
    gap: 0;
}
.hr-sb-newsletter input {
    max-width: 160px;
    flex: 0 0 160px;
    padding: 10px 8px;
    border: 1px solid #E5E7EB;
    font-size: 13px;
    outline: none;
    color: #111;
    font-family: inherit;
}
.hr-sb-newsletter button {
    flex: 1;
    background: #C0001A;
    color: #fff;
    border: none;
    padding: 10px 14px;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    cursor: pointer;
    font-family: inherit;
    transition: opacity 0.2s;
}
.hr-sb-newsletter button:hover { opacity: 0.85; }

/* ── NEWSLETTER BANNER ── */
.hr-newsletter-banner {
    background: #0A0A0A;
    padding: 56px 48px;
}
.hr-newsletter-banner-inner {
    max-width: 560px;
    margin: 0 auto !important;
    text-align: center !important;
}
.hr-nl-eyebrow {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    margin-bottom: 16px;
}
.hr-nl-eyebrow::before,
.hr-nl-eyebrow::after {
    content: '';
    display: block;
    width: 28px; height: 2px;
    background: #C0001A;
}
.hr-nl-eyebrow span {
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 4px;
    color: #C0001A;
    text-transform: uppercase;
}
.hr-nl-heading {
    font-size: 28px;
    font-weight: 800;
    color: #ffffff;
    margin: 0 0 10px;
    letter-spacing: -0.5px;
}
.hr-nl-sub {
    font-size: 14px;
    color: #9CA3AF;
    margin: 0 auto 28px auto !important;
    line-height: 1.6;
    text-align: center !important;
    display: block !important;
}
.hr-nl-form {
    display: flex;
    justify-content: center; /* This pushes the input and button to the center */
    margin: 0 auto; /* Centers the flex container itself */
    gap: 0;
}
.hr-nl-form input {
    flex: 1;
    padding: 13px 18px;
    background: #1a1a1a;
    border: 1px solid rgba(255,255,255,0.1);
    border-right: none;
    color: #fff;
    font-size: 14px;
    outline: none;
    font-family: inherit;
}
.hr-nl-form button {
    background: #C0001A;
    color: #fff;
    border: none;
    padding: 13px 24px;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    cursor: pointer;
    flex-shrink: 0;
    font-family: inherit;
    transition: opacity 0.2s;
}
.hr-nl-form button:hover { opacity: 0.85; }

/* Truncation CSS */
.hr-author-bio-text {
    display: -webkit-box;
    -webkit-line-clamp: 3; /* Change this number to show more/less lines */
    -webkit-box-orient: vertical;
    overflow: hidden;
    transition: all 0.3s ease;
}

/* Class added by JS when expanded */
.hr-author-bio-text.is-expanded {
    -webkit-line-clamp: unset;
    display: block;
}

/* The See More Button */
.hr-bio-toggle {
    background: none;
    border: none;
    color: #cc0000; /* Matching your Headline Recorder red */
    font-size: 13px;
    font-weight: 700;
    padding: 6px 0 0 0;
    margin: 0;
    cursor: pointer;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
/* Make single post nav span full width if there's only one item */
.hr-post-nav {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 56px;
    padding-top: 32px;
    border-top: 1px solid #E5E7EB;
}
/* If the first item is empty, make the second item span both columns */
.hr-post-nav > div:first-child:empty + a {
    grid-column: 1 / -1;
}
/* If there is only one item and it's on the left */
.hr-post-nav > a:only-child {
    grid-column: 1 / -1;
}
/* ── RESPONSIVE ── */
@media (max-width: 1024px) {
    .hr-page-grid {
        grid-template-columns: 1fr;
        gap: 48px;
        padding: 24px 32px 60px;
    }
    .hr-sidebar { position: static; }
}
@media (max-width: 768px) {
    .hr-breadcrumb { padding: 14px 24px 0; }
    .hr-page-grid { padding: 20px 24px 48px; }
    .hr-headline { font-size: 26px; }
    .hr-body { font-size: 16px; }
    .hr-body blockquote,
    .hr-body .wp-block-quote { font-size: 17px !important; padding: 18px 20px !important; }
    .hr-body blockquote p,
    .hr-body .wp-block-quote p { font-size: 17px !important; }
    .hr-post-nav { grid-template-columns: 1fr; }
    .hr-more-grid { grid-template-columns: 1fr 1fr; }
    .hr-meta-strip { gap: 12px; }
    .hr-meta-share { display: none; }
    .hr-author-box {
        padding: 20px 16px !important;
        gap: 16px !important;
    }
    .hr-nl-heading {
        font-size: 22px !important; /* Scales down smoothly on phones so it doesn't break awkwardly */
        line-height: 1.3 !important;
    }
}
@media (max-width: 480px) {
    .hr-more-grid { grid-template-columns: 1fr; }
    .hr-nl-form { flex-direction: column; }
    .hr-nl-form input { border-right: 1px solid rgba(255,255,255,0.1); border-bottom: none; }
}


</style>

<div class="hr-single-wrapper">

    <!-- BREADCRUMB -->
    <div class="hr-breadcrumb">
        <a href="<?php echo home_url(); ?>">Home</a>
        <?php if ($primary_cat) : ?>
        <span>·</span>
        <a href="<?php echo esc_url($cat_url); ?>"><?php echo esc_html($cat_name); ?></a>
        <?php endif; ?>
    </div>

    <!-- PAGE GRID -->
    <div class="hr-page-grid">

        <!-- ── LEFT: ARTICLE ── -->
        <article class="hr-article">

            <!-- Category badge -->
            <?php if ($primary_cat) : ?>
            <div class="hr-cat-badge">
                <a href="<?php echo esc_url($cat_url); ?>"><?php echo esc_html($cat_name); ?></a>
            </div>
            <?php endif; ?>

            <!-- Headline -->
            <h1 class="hr-headline"><?php the_title(); ?></h1>

            <!-- Meta strip -->
            <div class="hr-meta-strip">
                

                <div class="hr-meta-author">
                    <a href="<?php echo esc_url($author_link); ?>" style="display: flex; align-items: center; gap: 12px; text-decoration: none;">
                <?php if ($author_avatar) : ?>
                    <img src="<?php echo esc_url($author_avatar); ?>" alt="<?php echo esc_attr($author_name); ?>" class="hr-meta-avatar">
                <?php else : ?>
                        <div class="hr-meta-avatar-placeholder" style="background: #F3F4F6; display: flex; align-items: center; justify-content: center;">
                     <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                        </div>
                <?php endif; ?>
                    <div>
                        <div class="hr-meta-name" style="color: #0A0A0A; transition: color 0.2s;" onmouseover="this.style.color='#C0001A'" onmouseout="this.style.color='#0A0A0A'"><?php echo esc_html($author_name); ?></div>
                        <div class="hr-meta-desk"><?php echo $primary_cat ? esc_html($cat_name) . ' Desk' : 'Headline Recorder'; ?></div>
                    </div>
                    </a>
                </div>
                  <div class="hr-meta-divider"></div>
                         <span class="hr-meta-date"><?php echo esc_html($post_date); ?></span>
                        <div class="hr-meta-divider"></div>
                     <span class="hr-meta-readtime"><?php echo (int) $reading_minutes; ?> min read</span>
                     <?php do_action( 'hr_single_after_byline', get_the_ID() ); ?>
                        <div class="hr-meta-share">
                    <?php if (isset($is_guest) && $is_guest) : ?>
                        <!-- Guest Author Social Profiles -->
                        <?php if (!empty($guest_linkedin)) : ?>
                            <a href="<?php echo esc_url($guest_linkedin); ?>" target="_blank" rel="noopener" class="hr-share-btn">in</a>
                        <?php endif; ?>
                        <?php if (!empty($guest_twitter)) : ?>
                            <a href="<?php echo esc_url($guest_twitter); ?>" target="_blank" rel="noopener" class="hr-share-btn">𝕏</a>
                        <?php endif; ?>
                        <?php if (!empty($guest_facebook)) : ?>
                            <a href="<?php echo esc_url($guest_facebook); ?>" target="_blank" rel="noopener" class="hr-share-btn">fb</a>
                        <?php endif; ?>
                    <?php else : ?>
                        <!-- Default Article Share Buttons -->
                        <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo urlencode($permalink); ?>" target="_blank" rel="noopener" class="hr-share-btn">in</a>
                        <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode($permalink); ?>&text=<?php echo urlencode($post_title); ?>" target="_blank" rel="noopener" class="hr-share-btn">𝕏</a>
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($permalink); ?>" target="_blank" rel="noopener" class="hr-share-btn">fb</a>
                    <?php endif; ?>
                </div>
            </div>

            <?php do_action( 'hr_single_before_content', get_the_ID() ); ?>

            <!-- Hero image -->
            <?php if ( has_post_thumbnail() ) : ?>
            <div class="hr-hero-image-wrap">
                <?php the_post_thumbnail('full', array('data-no-lazy' => '1', 'class' => 'skip-lazy attachment-full size-full wp-post-image', 'loading' => 'eager')); ?>
                <?php
                $caption = get_post_field('post_excerpt', get_post_thumbnail_id($post_id));
                if ($caption) : ?>
                <div class="hr-hero-caption"><?php echo esc_html($caption); ?></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- ========================================== -->
            <!-- GOOGLE PREFERRED SOURCE (TEMPORARILY HIDDEN) -->
            <!-- ========================================== -->
            <!-- 
            <div style="margin-bottom: 12px;">
                <a href="https://www.google.com/preferences/source?q=headlinerecorder.com" target="_blank" rel="noopener" style="display: flex; align-items: center; justify-content: flex-start; background-color: #0A0A0A; color: #ffffff; padding: 16px 24px; border-left: 4px solid #4285F4; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 2px; text-decoration: none; gap: 16px; transition: background-color 0.2s; box-sizing: border-box; width: 100%;" onmouseover="this.style.backgroundColor='#141414'" onmouseout="this.style.backgroundColor='#0A0A0A'">
                    <svg width="18" height="18" viewBox="0 0 48 48" style="flex-shrink: 0;">
                        <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"></path>
                        <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"></path>
                        <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"></path>
                        <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"></path>
                        <path fill="none" d="M0 0h48v48H0z"></path>
                    </svg>
                    <span>Add as a preferred source on Google</span>
                </a>
            </div>
            -->

<!-- ========================================== -->
        <!-- GEO AI BRIEFING INJECTION (TYPING EFFECT)  -->
        <!-- ========================================== -->
        <?php 
        $geo_summary = get_post_meta(get_the_ID(), '_hr_geo_summary', true);
        if (!empty($geo_summary)) : 
        ?>
            <style>
                .hr-ai-details summary { list-style: none; }
                .hr-ai-details summary::-webkit-details-marker { display: none; }
                
                /* Blinking cursor for the AI typing effect */
                .ai-cursor::after {
                    content: ' █';
                    color: #C0001A;
                    animation: blink 0.8s step-start infinite;
                }
                @keyframes blink { 50% { opacity: 0; } }
                
                /* Smooth Text Shimmer Effect */
.ai-shimmer {
    background: linear-gradient(
        90deg, 
        #C0001A 0%, 
        #ffffff 50%, 
        #C0001A 100%
    );
    background-size: 200% auto;
    color: transparent !important;
    -webkit-background-clip: text !important;
    background-clip: text !important;
    animation: textShimmer 1.8s linear infinite;
    display: inline-block;
}

@keyframes textShimmer {
    0% {
        background-position: 200% center;
    }
    100% {
        background-position: -200% center;
    }
}
            </style>

            <details id="ai-briefing-details" class="hr-ai-details" style="background: #0A0A0A; padding: 18px 24px; border-left: 4px solid #C0001A; margin-bottom: 35px; cursor: pointer; transition: all 0.3s ease;">
                <summary id="ai-briefing-summary" style="font-size: 11px; font-weight: 800; letter-spacing: 2.5px; color: #C0001A; text-transform: uppercase; outline: none; display: flex; justify-content: space-between; align-items: center;">
                    <span id="ai-status-text">✧ GENERATE BRIEFING</span>
                    <span id="ai-toggle-icon" style="font-size: 18px; font-weight: 400;">+</span>
                </summary>
                
                <!-- The raw text is loaded here for bots, but JS manipulates it for humans -->
                <p id="ai-content-box" style="font-size: 15px; color: #E5E7EB; line-height: 1.7; margin-top: 16px; margin-bottom: 0; font-weight: 500;">
                    <?php echo esc_html($geo_summary); ?>
                </p>
            </details>

            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const details = document.getElementById('ai-briefing-details');
                const summary = document.getElementById('ai-briefing-summary');
                const contentBox = document.getElementById('ai-content-box');
                const statusText = document.getElementById('ai-status-text');
                const icon = document.getElementById('ai-toggle-icon');
                
                // Store the original text for the typing effect
                const fullText = contentBox.innerHTML.trim();
                let hasGenerated = false;

                summary.addEventListener('click', function(e) {
                    // If it has already generated, let it function as a normal open/close toggle
                    if (hasGenerated) {
                        setTimeout(() => { icon.innerHTML = details.open ? '−' : '+'; }, 10);
                        return; 
                    }
                    
                    // 1. Intercept the very first click to trigger the AI sequence
                    e.preventDefault(); 
                    hasGenerated = true; 

                    // 2. Trigger Smooth Shimmer Loading State
statusText.innerHTML = '✧ ANALYZING REPORT...';
statusText.classList.add('ai-shimmer');
icon.innerHTML = '⟳'; 
contentBox.innerHTML = ''; 

setTimeout(() => {
    // 3. Open the block and restore standard styling
    details.open = true;
    statusText.classList.remove('ai-shimmer');
    statusText.innerHTML = '✧ EXECUTIVE BRIEFING';
    icon.innerHTML = '−';
                        
                        // 4. Start Typing Effect (Word by Word)
                        contentBox.classList.add('ai-cursor');
                        const words = fullText.split(' ');
                        let currentWord = 0;
                        
                        const typingInterval = setInterval(() => {
                            if (currentWord < words.length) {
                                contentBox.innerHTML += words[currentWord] + ' ';
                                currentWord++;
                            } else {
                                clearInterval(typingInterval);
                                contentBox.classList.remove('ai-cursor'); // Remove cursor when finished
                            }
                        }, 35); // Adjust this number to make typing faster or slower (35ms is optimal)
                        
                    }, 2000); // 2-second simulated delay
                });
            });
            </script>
        <?php endif; ?>
        <!-- ========================================== -->


            <!-- Article body -->
            <div class="hr-body">
                <?php the_content(); ?>
            </div>
            <?php do_action( 'hr_single_after_content', get_the_ID() ); ?>

            <!-- Tags -->
            <?php if ($tags) : ?>
            <div class="hr-tags">
                <?php foreach ($tags as $tag) : ?>
                <a href="<?php echo get_tag_link($tag->term_id); ?>" class="hr-tag"><?php echo esc_html($tag->name); ?></a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Author box -->
        <div class="hr-author-box">
         <?php if ($author_avatar) : ?>
        <img src="<?php echo esc_url($author_avatar); ?>" alt="<?php echo esc_attr($author_name); ?>" class="hr-author-avatar">
        <?php else : ?>
                 <div class="hr-author-avatar-placeholder" style="background: #F3F4F6; display: flex; align-items: center; justify-content: center;">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                </div>
        <?php endif; ?>
        <div>
        <div class="hr-author-label">Written by</div>
        <div class="hr-author-name">
            <a href="<?php echo esc_url($author_link); ?>" style="color: inherit; text-decoration: none; transition: color 0.2s;" onmouseover="this.style.color='#C0001A'" onmouseout="this.style.color='inherit'">
                <?php echo esc_html($author_name); ?>
            </a>
        </div>
        
        <?php if ($author_bio) : ?>
        <div class="hr-author-bio-container">
            <div class="hr-author-bio-text hr-author-bio">
                <?php echo wpautop( make_clickable( esc_html( $author_bio ) ) ); ?>
            </div>
            <button class="hr-bio-toggle" style="display: none;">See More</button>
        </div>
        <?php endif; ?>
        
        <?php if (isset($is_guest) && $is_guest && (!empty($guest_linkedin) || !empty($guest_twitter) || !empty($guest_facebook))) : ?>
        <div style="display: flex; gap: 8px; margin-top: 14px;">
            <?php if (!empty($guest_linkedin)) : ?><a href="<?php echo esc_url($guest_linkedin); ?>" target="_blank" rel="noopener" class="hr-share-btn">in</a><?php endif; ?>
            <?php if (!empty($guest_twitter)) : ?><a href="<?php echo esc_url($guest_twitter); ?>" target="_blank" rel="noopener" class="hr-share-btn">𝕏</a><?php endif; ?>
            <?php if (!empty($guest_facebook)) : ?><a href="<?php echo esc_url($guest_facebook); ?>" target="_blank" rel="noopener" class="hr-share-btn">fb</a><?php endif; ?>
              </div>
            <?php endif; ?>
             </div>
    </div>

            <!-- Prev / Next navigation (Opinion-Locked) -->
            <?php
            // Pull adjacent posts only within the same category to keep the editorial context clean
            $prev_post = get_previous_post(true); 
            $next_post = get_next_post(true);
            
            if ($prev_post || $next_post) : ?>
            <div class="hr-post-nav">
                <?php if ($prev_post) : ?>
                <a href="<?php echo get_permalink($prev_post->ID); ?>" class="hr-nav-item prev">
                    <span class="hr-nav-direction">← Previous Opinion</span>
                    <span class="hr-nav-title"><?php echo esc_html(get_the_title($prev_post->ID)); ?></span>
                </a>
                <?php else : ?>
                <div></div>
                <?php endif; ?>
                
                <?php if ($next_post) : ?>
                <a href="<?php echo get_permalink($next_post->ID); ?>" class="hr-nav-item next">
                    <span class="hr-nav-direction">Next Opinion →</span>
                    <span class="hr-nav-title"><?php echo esc_html(get_the_title($next_post->ID)); ?></span>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- More Stories -->
            <?php if ($more_posts->have_posts()) : ?>
            <div class="hr-more-stories">
                <div class="hr-section-label"><span>More Stories</span></div>
                <div class="hr-more-grid">
                    <?php while ($more_posts->have_posts()) : $more_posts->the_post();
                        $m_thumb = get_the_post_thumbnail_url(get_the_ID(), 'medium');
                        $m_cats  = get_the_category();
                        $m_cat   = !empty($m_cats) ? $m_cats[0]->name : '';
                    ?>
                    <a href="<?php the_permalink(); ?>" class="hr-more-card">
                        <div class="hr-more-thumb">
                            <?php if ($m_thumb) : ?>
                            <img src="<?php echo esc_url($m_thumb); ?>" alt="<?php the_title_attribute(); ?>">
                            <?php endif; ?>
                            <?php if ($m_cat) : ?>
                            <span class="hr-more-thumb-cat"><?php echo esc_html($m_cat); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="hr-more-title"><?php the_title(); ?></div>
                        <div class="hr-more-date"><?php echo get_the_date('F j, Y'); ?></div>
                    </a>
                    <?php endwhile; wp_reset_postdata(); ?>
                </div>
            </div>
            <?php endif; ?>

        </article>

        <!-- ── RIGHT: SIDEBAR ── -->
        <aside class="hr-sidebar">

            <!-- Macro Indicators -->
            <div>
                <div class="hr-sb-label"><span>Macro Indicators</span></div>
                <div class="hr-macro-box">
                    <?php
                    $sbp_live_data = function_exists('get_headline_recorder_sbp_data') ? get_headline_recorder_sbp_data() : [];
                    
                    $macro = array(
                        array("Central Policy Rate",    isset($sbp_live_data['policy_rate']) ? $sbp_live_data['policy_rate']['value'] . (isset($sbp_live_data['policy_rate']['unit']) ? $sbp_live_data['policy_rate']['unit'] : '%') : "11.5%", "Official Monetary Stance"),
                        array("National CPI Inflation", isset($sbp_live_data['inflation']) ? $sbp_live_data['inflation']['value'] . (isset($sbp_live_data['inflation']['unit']) ? $sbp_live_data['inflation']['unit'] : '%') : "11.7%", "Macro Target Vector"),
                        array("Workers Remittances",    isset($sbp_live_data['remittances']) ? $sbp_live_data['remittances']['value'] . (isset($sbp_live_data['remittances']['unit']) ? $sbp_live_data['remittances']['unit'] : 'M') : "$4,251M", "Monthly Liquidity Inflow"),
                        array("Real GDP Growth",        isset($sbp_live_data['gdp_growth']) ? $sbp_live_data['gdp_growth']['value'] . (isset($sbp_live_data['gdp_growth']['unit']) ? $sbp_live_data['gdp_growth']['unit'] : '%') : "3.7%", "Annual Expansion Rate"),
                    );
                    foreach ($macro as $m) : ?>
                    <div class="hr-macro-item">
                        <div class="hr-macro-item-label"><?php echo esc_html($m[0]); ?></div>
                        <div class="hr-macro-item-val"><?php echo esc_html($m[1]); ?></div>
                        <div class="hr-macro-item-sub"><?php echo esc_html($m[2]); ?></div>
                    </div>
                    <?php endforeach; ?>
                    <a href="<?php echo home_url('/markets'); ?>" class="hr-macro-link">Full Markets →</a>
                </div>
            </div>

            <!-- Latest Posts -->
            <?php if ($latest_posts->have_posts()) : ?>
            <div>
                <div class="hr-sb-label"><span>Latest</span></div>
                <?php while ($latest_posts->have_posts()) : $latest_posts->the_post();
                    $sb_thumb = get_the_post_thumbnail_url(get_the_ID(), 'thumbnail');
                    $sb_cats  = get_the_category();
                    $sb_cat   = !empty($sb_cats) ? $sb_cats[0]->name : '';
                ?>
                <a href="<?php the_permalink(); ?>" class="hr-sb-post">
                    <div class="hr-sb-thumb">
                        <?php if ($sb_thumb) : ?>
                        <img src="<?php echo esc_url($sb_thumb); ?>" alt="<?php the_title_attribute(); ?>">
                        <?php endif; ?>
                    </div>
                    <div>
                        <?php if ($sb_cat) : ?>
                        <div class="hr-sb-cat"><?php echo esc_html($sb_cat); ?></div>
                        <?php endif; ?>
                        <div class="hr-sb-title"><?php the_title(); ?></div>
                        <div class="hr-sb-date"><?php echo get_the_date('F j, Y'); ?></div>
                    </div>
                </a>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>
            <?php endif; ?>

            <!-- Executive Spotlight Promo -->
            <?php if ($spotlight_post->have_posts()) : $spotlight_post->the_post(); ?>
            <div class="hr-spotlight-promo">
                <div class="hr-spotlight-label">Executive Spotlight</div>
                <p class="hr-spotlight-title"><?php the_title(); ?></p>
                <?php
                $spot_quote = get_post_meta(get_the_ID(), 'es_pull_quote', true);
                if (!$spot_quote) $spot_quote = wp_trim_words(get_the_excerpt(), 16, '...');
                ?>
                <div class="hr-spotlight-quote"><?php echo esc_html($spot_quote); ?></div>
                <a href="<?php the_permalink(); ?>" class="hr-spotlight-link">Read the Interview →</a>
            </div>
            <?php wp_reset_postdata(); endif; ?>

            

        </aside>

    </div><!-- .hr-page-grid -->

    

</div><!-- .hr-single-wrapper -->

<?php
endwhile;
?>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const bioText = document.querySelector('.hr-author-bio-text');
    const toggleBtn = document.querySelector('.hr-bio-toggle');

    if (bioText && toggleBtn) {
        // Check if the actual text height is larger than the clamped height
        if (bioText.scrollHeight > bioText.clientHeight) {
            toggleBtn.style.display = 'inline-block';
        }

        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            bioText.classList.toggle('is-expanded');
            
            if (bioText.classList.contains('is-expanded')) {
                toggleBtn.textContent = 'See Less';
            } else {
                toggleBtn.textContent = 'See More';
            }
        });
    }
});
</script>

<?php
get_footer();
?>