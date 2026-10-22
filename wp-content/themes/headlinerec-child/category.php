<?php
/**
 * Category Archive Template: Custom Premium Layout
 * File: category.php (place in child theme root)
 */
get_header();

// Get the current category object
$current_category = get_queried_object();
$cat_name = $current_category->name;
$cat_desc = category_description($current_category->term_id);

// ==========================================
// HR SEO: INJECT ITEMLIST SCHEMA FOR CRAWLERS
// ==========================================
global $wp_query;
$schema_data = array(
    "@context" => "https://schema.org",
    "@type" => "ItemList",
    "name" => $cat_name . " News",
    "description" => wp_trim_words(strip_tags($cat_desc), 40, ''),
    "url" => get_category_link( $current_category->term_id ),
    "numberOfItems" => $wp_query->post_count,
    "itemListElement" => array()
);

$position = 1;
if ( have_posts() ) {
    while ( have_posts() ) {
        the_post();
        $schema_data['itemListElement'][] = array(
            "@type" => "ListItem",
            "position" => $position,
            "url" => get_permalink(),
            "name" => get_the_title()
        );
        $position++;
    }
    rewind_posts(); // CRUCIAL: Resets the loop so the visual grid below still loads
}
echo '<script type="application/ld+json">' . wp_json_encode( $schema_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP ) . '</script>';
// ==========================================
?>

<style>
/* ================================================
   HR PREMIUM CATEGORY PAGE: FULL OVERRIDE
================================================ */
* { box-sizing: border-box; }

/* Force full width, kill theme layout */
.section-block-upper, .section-block-upper.row { display: block !important; margin: 0 !important; padding: 0 !important; width: 100% !important; max-width: 100% !important; }
#secondary, .widget-area { display: none !important; }
#primary, .content-area { width: 100% !important; max-width: 100% !important; float: none !important; padding: 0 !important; margin: 0 !important; }
[class*="breadcrumb"], .covernews-breadcrumb { display: none !important; }
/* Aggressively kill parent theme injections at the bottom of the page */
.hr-category-wrapper ~ *:not(.site-footer):not(#colophon),
.af-latest-posts,
.site-main > section { 
    display: none !important; 
}

/* ── WRAPPER & HERO ── */
.hr-category-wrapper { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #ffffff; color: #111111; }
.hr-cat-hero { background: #0A0A0C; padding: 48px; border-top: 1px solid rgba(255,255,255,0.05); }
.hr-cat-hero-inner { max-width: 1200px; margin: 0 auto; }
.hr-hero-eyebrow { display: flex; align-items: center; gap: 12px; margin-bottom: 24px; }
.hr-hero-eyebrow::before { content: ''; display: block; width: 28px; height: 2px; background: #C0001A; }
.hr-hero-eyebrow span { font-size: 12px; font-weight: 800; letter-spacing: 4px; color: #C0001A; text-transform: uppercase; }
.hr-hero-title { font-size: 48px; font-weight: 800; color: #ffffff; margin: 0 0 16px; letter-spacing: -1px; }
.hr-hero-desc { font-size: 16px; color: #9CA3AF; max-width: 600px; line-height: 1.6; margin: 0; }
/* Sleek Category Description Clamping */
.hr-hero-desc-container { max-width: 650px; }
.hr-hero-desc.hr-hero-clamp {
    display: -webkit-box;
    -webkit-line-clamp: 2; /* Shows only 2 lines by default */
    -webkit-box-orient: vertical;
    overflow: hidden;
    margin-bottom: 8px;
}
.hr-cat-toggle {
    background: none;
    border: none;
    color: #C0001A;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    cursor: pointer;
    padding: 0;
    margin-top: 6px;
}


/* ── MAIN GRID ── */
.hr-cat-page-grid { max-width: 1200px; margin: 0 auto; padding: 56px 48px 80px; display: grid; grid-template-columns: 1fr 340px; gap: 64px; align-items: start; }

/* ── SECTION LABELS ── */
.hr-section-label { display: flex; align-items: center; gap: 12px; margin-bottom: 24px; border-top: 1px solid #eee; padding-top: 24px; }
.hr-section-label::before { content: ''; display: block; width: 28px; height: 2px; background: #C0001A; }
.hr-section-label span { font-size: 12px; font-weight: 800; letter-spacing: 3px; color: #6B7280; text-transform: uppercase; }
.hr-section-label.no-border { border-top: none; padding-top: 0; }

/* ── FEATURED STORY ── */
.hr-cat-featured { display: grid; grid-template-columns: 1.4fr 1fr; gap: 40px; margin-bottom: 48px; align-items: start; }
.hr-feat-img { position: relative; width: 100%; aspect-ratio: 16/9; background: #111; overflow: hidden; }
.hr-feat-img img { width: 100%; height: 100%; object-fit: cover; display: block; }
.hr-feat-content { padding-right: 20px; }
.hr-feat-cat { font-size: 12px; font-weight: 800; color: #C0001A; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 12px; display: block; }
.hr-feat-title { font-size: 28px; font-weight: 800; line-height: 1.2; color: #000; margin: 0 0 16px; letter-spacing: -0.5px; text-decoration: none; display: block; }
.hr-feat-title:hover { color: #C0001A; }
.hr-feat-excerpt { font-size: 14px; color: #666; line-height: 1.6; margin: 0 0 20px; }
.hr-feat-meta { display: flex; align-items: center; gap: 16px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
.hr-feat-date { color: #999; }
.hr-feat-read { color: #C0001A; text-decoration: none; border-bottom: 1px solid #C0001A; padding-bottom: 2px; }

/* ── LATEST GRID ── */
.hr-latest-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 32px 24px; }
.hr-grid-card { text-decoration: none; display: block; }
.hr-grid-img { position: relative; width: 100%; aspect-ratio: 16/9; background: #111; margin-bottom: 16px; overflow: hidden; }
.hr-grid-img img { width: 100%; height: 100%; object-fit: cover; }
.hr-grid-badge { position: absolute; top: 10px; left: 10px; background: #C0001A; color: #fff; font-size: 9px; font-weight: 800; padding: 4px 8px; letter-spacing: 1.5px; text-transform: uppercase; }
.hr-grid-title { font-size: 16px; font-weight: 700; color: #000; line-height: 1.4; margin: 0 0 8px; }
.hr-grid-card:hover .hr-grid-title { color: #C0001A; }
.hr-grid-date { font-size: 11px; color: #999; }

/* ── PAGINATION ── */
.hr-pagination { margin-top: 48px; display: flex; gap: 8px; justify-content: center; }
.hr-pagination .page-numbers { display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border: 1px solid #eaeaea; font-size: 13px; font-weight: 700; color: #111; text-decoration: none; transition: 0.2s; }
.hr-pagination .page-numbers.current, .hr-pagination .page-numbers:hover { background: #C0001A; color: #fff; border-color: #C0001A; }

/* ── SIDEBAR ── */
.hr-cat-sidebar { display: flex; flex-direction: column; gap: 40px; }

/* Dark Desk Newsletter Card */
.hr-desk-newsletter { background: #0A0A0C; padding: 32px 24px; border-top: 3px solid #C0001A; }
.hr-dn-eyebrow { font-size: 10px; font-weight: 800; color: #C0001A; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 8px; }
.hr-dn-title { font-size: 18px; font-weight: 800; color: #fff; line-height: 1.3; margin: 0 0 12px; }
.hr-dn-desc { font-size: 13px; color: #9CA3AF; margin: 0 0 20px; line-height: 1.5; }
.hr-dn-link { display: inline-block; font-size: 11px; font-weight: 800; color: #C0001A; text-transform: uppercase; letter-spacing: 1.5px; text-decoration: none; border-bottom: 1px solid #C0001A; padding-bottom: 2px; }

/* Trending List */
.hr-trending-item { display: flex; gap: 16px; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #eaeaea; text-decoration: none; }
.hr-trending-item:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
.hr-trending-img { width: 80px; height: 60px; background: #111; flex-shrink: 0; }
.hr-trending-img img { width: 100%; height: 100%; object-fit: cover; }
.hr-trending-title { font-size: 14px; font-weight: 700; color: #111; line-height: 1.4; margin: 0 0 6px; }
.hr-trending-item:hover .hr-trending-title { color: #C0001A; }
.hr-trending-date { font-size: 11px; color: #999; }

/* Responsive */
@media (max-width: 1024px) { .hr-cat-page-grid { grid-template-columns: 1fr; } .hr-cat-featured { grid-template-columns: 1fr; } }
@media (max-width: 768px) { .hr-cat-hero { padding: 48px 24px; } .hr-cat-page-grid { padding: 32px 24px; } .hr-latest-grid { grid-template-columns: 1fr; } .hr-hero-title { font-size: 32px; } }

/* ================================================
   NUKE PARENT THEME INJECTIONS (SCORCHED EARTH)
================================================ */
/* 1. Target AF-Framework specific classes */
div[class*="af-latest"],
section[class*="af-latest"],
div[class*="latest-news"],
div[class*="bottom-latest"],
.af-sec-latest-posts,
.af-container-row-latest {
    display: none !important;
}

/* 2. Target anything that loads between our layout and the Footer */
.hr-category-wrapper ~ div:not(.site-footer):not(#colophon):not(.hr-footer),
.hr-category-wrapper ~ section:not(.site-footer):not(#colophon):not(.hr-footer),
.site-content ~ div:not(.site-footer):not(#colophon),
#content ~ div:not(.site-footer):not(#colophon) {
    display: none !important;
}
</style>

<div class="hr-category-wrapper">

    <!-- 1. DARK HERO HEADER -->
    <div class="hr-cat-hero">
    <div class="hr-cat-hero-inner">
        <div class="hr-hero-eyebrow"><span>Category</span></div>
        <h1 class="hr-hero-title"><?php echo esc_html($cat_name); ?></h1>
        <?php if ($cat_desc) : ?>
            <div class="hr-hero-desc-container">
                <div class="hr-hero-desc hr-hero-clamp"><?php echo wp_kses_post($cat_desc); ?></div>
                <button class="hr-cat-toggle" onclick="this.previousElementSibling.classList.toggle('hr-hero-clamp'); this.innerText = this.previousElementSibling.classList.contains('hr-hero-clamp') ? 'Read Overview ↓' : 'Show Less ↑';">Read Overview ↓</button>
            </div>
        <?php endif; ?>
    </div>
</div>

    <div class="hr-cat-page-grid">
        
        <!-- 2. MAIN COLUMN -->
        <main class="hr-cat-main">
            <?php if (have_posts()) : ?>
                
                <!-- Extract First Post as Featured -->
                <?php the_post(); ?>
                <div class="hr-section-label no-border"><span>Featured Story</span></div>
                <div class="hr-cat-featured">
                    <div class="hr-feat-img">
                        <a href="<?php echo esc_url(get_permalink()); ?>">
                            <?php if (has_post_thumbnail()) { the_post_thumbnail('large'); } ?>
                        </a>
                    </div>
                    <div class="hr-feat-content">
                        <span class="hr-feat-cat"><?php echo esc_html($cat_name); ?></span>
                        <a href="<?php echo esc_url(get_permalink()); ?>" class="hr-feat-title"><?php echo esc_html(get_the_title()); ?></a>
                        <p class="hr-feat-excerpt"><?php echo wp_trim_words(get_the_excerpt(), 20, '...'); ?></p>
                        <div class="hr-feat-meta">
                            <span class="hr-feat-date"><?php echo get_the_date('F j, Y'); ?></span>
                            <a href="<?php echo esc_url(get_permalink()); ?>" class="hr-feat-read">Read Article →</a>
                        </div>
                    </div>
                </div>

                <!-- Remaining Posts Grid -->
                <?php if (have_posts()) : ?>
                    <div class="hr-section-label"><span>Latest from <?php echo esc_html($cat_name); ?></span></div>
                    <div class="hr-latest-grid">
                        <?php while (have_posts()) : the_post(); ?>
                            <a href="<?php echo esc_url(get_permalink()); ?>" class="hr-grid-card">
                                <div class="hr-grid-img">
                                    <span class="hr-grid-badge"><?php echo esc_html($cat_name); ?></span>
                                    <?php if (has_post_thumbnail()) { the_post_thumbnail('medium_large'); } ?>
                                </div>
                                <h3 class="hr-grid-title"><?php echo esc_html(get_the_title()); ?></h3>
                                <span class="hr-grid-date"><?php echo get_the_date('F j, Y'); ?></span>
                            </a>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>

                <!-- Pagination -->
                <div class="hr-pagination">
                    <?php 
                    echo paginate_links(array(
                        'prev_text' => '←',
                        'next_text' => '→',
                    )); 
                    ?>
                </div>

            <?php else: ?>
                <p>No stories found in this category yet.</p>
            <?php endif; ?>
        </main>

        <!-- 3. RIGHT SIDEBAR -->
        <aside class="hr-cat-sidebar">
            
            <!-- Desk-Specific Newsletter (Fix #1 applied) -->
            <div class="hr-desk-newsletter">
                <div class="hr-dn-eyebrow"><?php echo esc_html($cat_name); ?> Insider</div>
                <h4 class="hr-dn-title">Get the intelligence driving Pakistan's <?php echo strtolower(esc_html($cat_name)); ?> sector.</h4>
                <p class="hr-dn-desc">Join executives receiving our dedicated desk briefings.</p>
                <a href="#newsletter" class="hr-dn-link">Subscribe to <?php echo esc_html($cat_name); ?> →</a>
            </div>

            
            <!-- Macro Indicators API -->
            <div>
                <div class="hr-section-label no-border"><span>Macro Indicators</span></div>
                
                <!-- NEW SYMMETRICAL GRID SYSTEM -->
                <div class="hr-macro-box-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1px; background: #1a1a1a; border: 1px solid #1a1a1a;">
                    <?php
                    $sbp_data = function_exists('get_headline_recorder_sbp_data') ? get_headline_recorder_sbp_data() : [];
                    $ind_keys = [
                        'policy_rate' => ['label'=>'POLICY RATE', 'sub'=>'Monetary Stance'],
                        'inflation'   => ['label'=>'CPI INFLATION', 'sub'=>'Target Vector'],
                        'remittances' => ['label'=>'REMITTANCES', 'sub'=>'Monthly Inflow'],
                        'gdp_growth'  => ['label'=>'GDP GROWTH', 'sub'=>'Expansion Rate']
                    ];
                    
                    foreach($ind_keys as $k => $meta) {
                        $val = (isset($sbp_data[$k]['value'])) ? str_replace('$', '', $sbp_data[$k]['value']) : 'N/A';
                        $unit = (isset($sbp_data[$k]['unit'])) ? $sbp_data[$k]['unit'] : '';
                        
                        echo '<div class="hr-macro-item" style="background: #0A0A0C; padding: 20px;">';
                        echo '<span style="font-size:10px; color:#666; letter-spacing:1px; display:block; margin-bottom:4px; text-transform:uppercase;">' . esc_html($meta['label']) . '</span>';
                        echo '<div style="font-size:16px; font-weight:800; color:#fff; margin-bottom:2px;">' . esc_html($val) . ' <span style="font-size:10px; color:#666;">' . esc_html($unit) . '</span></div>';
                        echo '<span style="font-size:9px; color:#444;">' . esc_html($meta['sub']) . '</span>';
                        echo '</div>';
                    }
                    ?>
                </div>
                
                <!-- Anchored CTA Footer -->
                <div style="background: #0A0A0C; padding: 16px 20px; text-align: right; border: 1px solid #1a1a1a; border-top: none;">
                    <a href="/markets" style="color: #C0001A; font-weight: 800; font-size: 10px; text-transform: uppercase; letter-spacing: 1px; text-decoration: none;">Full Markets →</a>
                </div>
            </div>

            <!-- Category-Specific Trending Box (Fix #2 applied) -->
            <div>
                <div class="hr-section-label no-border"><span>Trending in <?php echo esc_html($cat_name); ?></span></div>
                <div class="hr-trending-list">
                    <?php
                    // Query for most commented posts in this category as a fallback for "trending"
                    $trending_query = new WP_Query(array(
                        'cat'            => $current_category->term_id,
                        'posts_per_page' => 4,
                        'orderby'        => 'comment_count', // Change this to 'meta_value_num' if using a views plugin
                        'order'          => 'DESC'
                    ));
                    if ($trending_query->have_posts()) : while ($trending_query->have_posts()) : $trending_query->the_post();
                    ?>
                        <a href="<?php echo esc_url(get_permalink()); ?>" class="hr-trending-item">
                            <div class="hr-trending-img">
                                <?php if (has_post_thumbnail()) { the_post_thumbnail('thumbnail'); } ?>
                            </div>
                            <div>
                                <h4 class="hr-trending-title"><?php echo wp_trim_words(get_the_title(), 10, '...'); ?></h4>
                                <span class="hr-trending-date"><?php echo get_the_date('M j, Y'); ?></span>
                            </div>
                        </a>
                    <?php endwhile; wp_reset_postdata(); endif; ?>
                </div>
            </div>

        </aside>
    </div>
</div>

<?php get_footer(); ?>