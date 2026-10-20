<?php
/**
 * Custom Taxonomy Template for Guest Authors
 * File: taxonomy-guest_author.php (place in child theme root)
 */
get_header();

// 1. Get the current guest author's term object
$term = get_queried_object();
$author_name = $term->name;

// 2. Fetch the author's avatar and bio DIRECTLY from the Term Meta
$author_avatar = get_term_meta($term->term_id, 'guest_author_avatar', true);
$author_bio    = get_term_meta($term->term_id, 'guest_author_bio', true);

// 3. Generate Initials for the Fallback UI
$name_parts = explode(' ', trim($author_name));
if (count($name_parts) >= 2) {
    $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1));
} else {
    $initials = strtoupper(substr($author_name, 0, 2));
}
?>

<style>
/* ================================================
   HR PREMIUM GUEST AUTHOR ARCHIVE OVERRIDE
================================================ */
* { box-sizing: border-box; }

/* Force full width, kill theme layout for this specific page */
#primary, .content-area, .site-main { width: 100% !important; max-width: 100% !important; padding: 0 !important; margin: 0 !important; float: none !important; }
#secondary, .widget-area, aside#secondary { display: none !important; }
.section-block-upper, .section-block-upper.row { display: block !important; margin: 0 !important; padding: 0 !important; width: 100% !important; max-width: 100% !important; }
[class*="breadcrumb"], .covernews-breadcrumb, .breadcrumbs, #breadcrumbs { display: none !important; }

/* Wrapper */
.hr-archive-wrapper {
    max-width: 1200px;
    margin: 0 auto;
    padding: 60px 48px 100px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    background: #ffffff;
}

/* Author Header */
.hr-author-header {
    display: flex;
    align-items: center;
    gap: 32px;
    margin-bottom: 60px;
    padding-bottom: 40px;
    border-bottom: 1px solid #E5E7EB;
}
.hr-author-header-img {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
    border: 3px solid #E5E7EB;
}
.hr-author-header-initials {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: #F3F4F6;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 38px;
    font-weight: 700;
    color: #111111;
    flex-shrink: 0;
    letter-spacing: 1px;
}
.hr-author-label {
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 3px;
    color: #C0001A;
    text-transform: uppercase;
    margin-bottom: 10px;
    display: block;
}
.hr-author-header-info h1 {
    font-size: 42px;
    font-weight: 800;
    color: #0A0A0A;
    margin: 0 0 12px;
    letter-spacing: -1px;
    line-height: 1.1;
}
.hr-author-header-info p {
    font-size: 15px;
    color: #6B7280;
    line-height: 1.6;
    margin: 0;
    max-width: 700px;
}

/* Grid */
.hr-archive-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 40px;
}

/* Post Card */
.hr-card {
    text-decoration: none;
    display: block;
    transition: transform 0.2s ease;
}
.hr-card:hover .hr-card-title {
    color: #C0001A;
}
.hr-card-thumb {
    width: 100%;
    height: 240px;
    object-fit: cover;
    margin-bottom: 16px;
    background: #0A0A0A;
}
.hr-card-placeholder {
    width: 100%;
    height: 240px;
    margin-bottom: 16px;
    background: #F3F4F6;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #9CA3AF;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
}
.hr-card-meta {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}
.hr-card-tag {
    font-size: 10px;
    font-weight: 800;
    color: #C0001A;
    text-transform: uppercase;
    letter-spacing: 1.5px;
}
.hr-card-date {
    font-size: 11px;
    color: #9CA3AF;
}
.hr-card-title {
    font-size: 20px;
    font-weight: 700;
    color: #0A0A0A;
    margin: 0 0 10px;
    line-height: 1.35;
    transition: color 0.2s;
}
.hr-card-excerpt {
    font-size: 14px;
    color: #6B7280;
    line-height: 1.6;
    margin: 0;
}

/* Pagination */
.hr-pagination {
    margin-top: 60px;
    padding-top: 30px;
    border-top: 1px solid #E5E7EB;
    text-align: center;
}
.hr-pagination .nav-links {
    display: inline-flex;
    gap: 12px;
    align-items: center;
    justify-content: center;
}
.hr-pagination .nav-links a, 
.hr-pagination .nav-links span {
    padding: 10px 18px;
    border: 1px solid #E5E7EB;
    color: #111111;
    text-decoration: none;
    font-weight: 700;
    font-size: 13px;
    transition: all 0.2s;
}
.hr-pagination .nav-links a:hover {
    border-color: #C0001A;
    color: #C0001A;
}
.hr-pagination .nav-links .current {
    background: #C0001A;
    color: #ffffff;
    border-color: #C0001A;
}

/* Responsive */
@media (max-width: 1024px) {
    .hr-archive-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 768px) {
    .hr-archive-wrapper { padding: 40px 24px 80px; }
    .hr-author-header { flex-direction: column; text-align: center; gap: 20px; }
    .hr-archive-grid { grid-template-columns: 1fr; gap: 32px; }
    .hr-author-header-info h1 { font-size: 32px; }
    .hr-card-thumb, .hr-card-placeholder { height: 200px; }
}
</style>

<div class="hr-archive-wrapper">
    
    <!-- 1. AUTHOR PROFILE HEADER -->
    <div class="hr-author-header">
        <?php if ($author_avatar): ?>
            <img src="<?php echo esc_url($author_avatar); ?>" alt="<?php echo esc_attr($author_name); ?>" class="hr-author-header-img" width="120" height="120" decoding="async">
        <?php else: ?>
            <div class="hr-author-header-initials" style="background: #F3F4F6; display: flex; align-items: center; justify-content: center;">
                <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
            </div>
        <?php endif; ?>
        
        <div class="hr-author-header-info">
            <span class="hr-author-label">Opinion Contributor</span>
            <h1><?php echo esc_html($author_name); ?></h1>
            <?php if ($author_bio): ?>
                <p><?php echo esc_html($author_bio); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- 2. ARTICLE GRID -->
    <div class="hr-archive-grid">
        <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
            
            <a href="<?php echo esc_url(get_permalink()); ?>" class="hr-card">
                <?php if (has_post_thumbnail()) : ?>
                    <?php echo hr_post_image(get_the_ID(), 'medium_large', array('class' => 'hr-card-thumb')); // phpcs:ignore WordPress.Security.EscapeOutput -- built with esc_url/esc_attr ?>
                <?php else: ?>
                    <div class="hr-card-placeholder">No Image</div>
                <?php endif; ?>
                
                <div class="hr-card-meta">
                    <span class="hr-card-tag">OPINION</span>
                    <span style="color: #E5E7EB;">|</span>
                    <span class="hr-card-date"><?php echo get_the_date('M d, Y'); ?></span>
                </div>
                
                <h3 class="hr-card-title"><?php echo esc_html(get_the_title()); ?></h3>
                <p class="hr-card-excerpt"><?php echo wp_trim_words(get_the_excerpt(), 22, '...'); ?></p>
            </a>

        <?php endwhile; else : ?>
            <p style="font-size: 16px; color: #6B7280; font-style: italic;">No articles found for this author.</p>
        <?php endif; ?>
    </div>
    
    <!-- 3. PAGINATION -->
    <div class="hr-pagination">
        <?php
        the_posts_pagination(array(
            'mid_size'  => 2,
            'prev_text' => '← Prev',
            'next_text' => 'Next →',
            'screen_reader_text' => ' '
        ));
        ?>
    </div>

</div>

<?php
get_footer();
?>