<?php
/**
 * Template Name: Authors & Experts Directory
 * File: page-authors-experts.php (place in child theme root)
 */
get_header();
?>

<style>
/* ================================================
   HR CONTRIBUTORS DIRECTORY STYLES
================================================ */
* { box-sizing: border-box; }

/* Nuke theme sidebars/breads for this full-width page */
#primary, .content-area, .site-main { width: 100% !important; max-width: 100% !important; padding: 0 !important; margin: 0 !important; float: none !important; }
#secondary, .widget-area, aside#secondary { display: none !important; }
[class*="breadcrumb"], .covernews-breadcrumb, .breadcrumbs, #breadcrumbs { display: none !important; }

.hr-contrib-wrapper {
    max-width: 1200px;
    margin: 0 auto;
    padding: 60px 48px 100px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    background: #ffffff;
    color: #111111;
}

/* Header Banner */
.hr-contrib-header {
    max-width: 700px;
    margin-bottom: 60px;
    padding-bottom: 40px;
    border-bottom: 1px solid #E5E7EB;
}
.hr-contrib-eyebrow {
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 3px;
    color: #C0001A;
    text-transform: uppercase;
    margin-bottom: 12px;
    display: block;
}
.hr-contrib-header h1 {
    font-size: 42px;
    font-weight: 800;
    color: #0A0A0A;
    margin: 0 0 16px;
    letter-spacing: -1px;
    line-height: 1.1;
}
.hr-contrib-header p {
    font-size: 16px;
    color: #6B7280;
    line-height: 1.6;
    margin: 0;
}

/* Contributors Grid */
.hr-contrib-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 40px;
}

/* Contributor Card */
.hr-contrib-card {
    display: flex;
    gap: 24px;
    padding: 32px;
    border: 1px solid #E5E7EB;
    text-decoration: none;
    background: #FAFAFA;
    transition: all 0.25s ease;
}
.hr-contrib-card:hover {
    border-color: #0A0A0A;
    box-shadow: 0 8px 30px rgba(0,0,0,0.06);
    transform: translateY(-2px);
}
.hr-contrib-avatar {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
    border: 2px solid #E5E7EB;
}
.hr-contrib-avatar-placeholder {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    background: #F3F4F6;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border: 2px solid #E5E7EB;
}
.hr-contrib-info {
    flex: 1;
    min-width: 0;
}
.hr-contrib-name {
    font-size: 20px;
    font-weight: 700;
    color: #0A0A0A;
    margin: 0 0 6px;
    transition: color 0.2s;
}
.hr-contrib-card:hover .hr-contrib-name {
    color: #C0001A;
}
.hr-contrib-meta {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 1.5px;
    color: #C0001A;
    text-transform: uppercase;
    margin-bottom: 12px;
    display: block;
}
.hr-contrib-bio {
    font-size: 13px;
    color: #6B7280;
    line-height: 1.6;
    margin: 0;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Responsive */
@media (max-width: 900px) {
    .hr-contrib-grid { grid-template-columns: 1fr; }
    .hr-contrib-wrapper { padding: 40px 24px 80px; }
    .hr-contrib-header h1 { font-size: 32px; }
}
</style>

<div class="hr-contrib-wrapper">

    <!-- Header Section -->
    <div class="hr-contrib-header">
        <span class="hr-contrib-eyebrow">The Masthead</span>
        <h1>Opinion Contributors</h1>
        <p>Meet the economists, industry veterans, and policy analysts driving Headline Recorder's independent commentary and macro perspective.</p>
    </div>

    <!-- Contributors Grid -->
    <div class="hr-contrib-grid">
        <?php
        // Fetch all terms from our custom guest_author taxonomy
        $terms = get_terms(array(
            'taxonomy'   => 'guest_author',
            'hide_empty' => true, // Only show authors who have published at least 1 article
        ));

        if (!empty($terms) && !is_wp_error($terms)) :
            foreach ($terms as $term) :
                $term_link = get_term_link($term);
                
                // Fetch the author's avatar and bio DIRECTLY from the Term Meta
                $avatar = get_term_meta($term->term_id, 'guest_author_avatar', true);
                $bio    = get_term_meta($term->term_id, 'guest_author_bio', true);
        ?>
            <a href="<?php echo esc_url($term_link); ?>" class="hr-contrib-card">
                <?php if (!empty($avatar)) : ?>
                    <img src="<?php echo esc_url($avatar); ?>" alt="<?php echo esc_attr($term->name); ?>" class="hr-contrib-avatar">
                <?php else : ?>
                    <div class="hr-contrib-avatar-placeholder">
                        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    </div>
                <?php endif; ?>

                <div class="hr-contrib-info">
                    <span class="hr-contrib-meta"><?php echo esc_html($term->count); ?> <?php echo ($term->count == 1) ? 'Article' : 'Articles'; ?> Published</span>
                    <h2 class="hr-contrib-name"><?php echo esc_html($term->name); ?></h2>
                    <?php if (!empty($bio)) : ?>
                        <p class="hr-contrib-bio"><?php echo esc_html($bio); ?></p>
                    <?php endif; ?>
                </div>
            </a>
        <?php 
            endforeach;
        else :
        ?>
            <p style="color: #6B7280; font-style: italic;">No contributors found at this time.</p>
        <?php endif; ?>
    </div>

</div>

<?php
get_footer();
?>