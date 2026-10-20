<?php
/**
 * Single Post Template — Custom Premium Layout
 * File: single.php (place in child theme root)
 * Overrides CoverNews parent single.php
 */
get_header();

while (have_posts()) : the_post();
    $post_id     = get_the_ID();
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
        'no_found_rows'  => true,
        'post__not_in'   => array($post_id),
        'orderby'        => 'date',
        'order'          => 'DESC',
    ));

    // More stories (same category)
    $more_posts = new WP_Query(array(
        'posts_per_page' => 3,
        'no_found_rows'  => true,
        'post__not_in'   => array($post_id),
        'category__in'   => $primary_cat ? array($primary_cat->term_id) : array(),
        'orderby'        => 'date',
        'order'          => 'DESC',
    ));

    // Executive Spotlight sidebar promo
    $spotlight_post = new WP_Query(array(
        'posts_per_page' => 1,
        'no_found_rows'  => true,
        'category_name'  => 'executive-spotlight',
        'post__not_in'   => array($post_id),
    ));
?>

<?php // HR SEO: NewsArticle + author schema (E-E-A-T), see inc/schema.php
hr_print_article_schema($post_id, array('name' => $author_name, 'url' => $author_link, 'description' => $author_bio), 'hr'); ?>

<div class="hr-single-wrapper">

    <!-- BREADCRUMB -->
    <div class="hr-breadcrumb">
        <a href="<?php echo esc_url(home_url('/')); ?>">Home</a>
        <?php if ($primary_cat) : ?>
        <span>·</span>
        <a href="<?php echo esc_url($cat_url); ?>"><?php echo esc_html($cat_name); ?></a>
        <?php endif; ?>
    </div>

    <!-- PAGE GRID -->
    <div class="hr-page-grid">

        <!-- ── LEFT: ARTICLE ── -->
        <article class="hr-article<?php echo has_category( 'opinion', $post_id ) ? ' hr-article--opinion' : ''; ?>" data-hr-post="<?php echo (int) $post_id; ?>" data-hr-words="<?php echo (int) $word_count; ?>">

            <!-- Category badge -->
            <?php if ($primary_cat) : ?>
            <div class="hr-cat-badge">
                <a href="<?php echo esc_url($cat_url); ?>"><?php echo esc_html($cat_name); ?></a>
            </div>
            <?php endif; ?>

            <!-- Headline -->
            <h1 class="hr-headline"><?php echo esc_html($post_title); ?></h1>

            <!-- Meta strip -->
            <div class="hr-meta-strip">
                

                <div class="hr-meta-author">
                    <a href="<?php echo esc_url($author_link); ?>" class="hr-meta-author-link">
                <?php if ($author_avatar) : ?>
                    <img src="<?php echo esc_url($author_avatar); ?>" alt="<?php echo esc_attr($author_name); ?>" class="hr-meta-avatar" width="36" height="36" decoding="async">
                <?php else : ?>
                        <div class="hr-meta-avatar-placeholder">
                     <svg aria-hidden="true" focusable="false" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                        </div>
                <?php endif; ?>
                    <div>
                        <div class="hr-meta-name"><?php echo esc_html($author_name); ?></div>
                        <div class="hr-meta-desk"><?php echo $primary_cat ? esc_html($cat_name) . ' Desk' : 'Headline Recorder'; ?></div>
                    </div>
                    </a>
                </div>
                  <div class="hr-meta-divider"></div>
                         <time class="hr-meta-date" datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html($post_date); ?></time>
                        <div class="hr-meta-divider"></div>
                     <span class="hr-meta-readtime"><?php echo (int) $reading_minutes; ?> min read</span>
                     <?php do_action( 'hr_single_after_byline', get_the_ID() ); ?>
                        <div class="hr-meta-share">
                    <?php if (isset($is_guest) && $is_guest) : ?>
                        <!-- Guest Author Social Profiles -->
                        <?php if (!empty($guest_linkedin)) : ?>
                            <a href="<?php echo esc_url($guest_linkedin); ?>" target="_blank" rel="noopener" class="hr-share-btn" aria-label="<?php echo esc_attr($author_name); ?> on LinkedIn">in</a>
                        <?php endif; ?>
                        <?php if (!empty($guest_twitter)) : ?>
                            <a href="<?php echo esc_url($guest_twitter); ?>" target="_blank" rel="noopener" class="hr-share-btn" aria-label="<?php echo esc_attr($author_name); ?> on X">𝕏</a>
                        <?php endif; ?>
                        <?php if (!empty($guest_facebook)) : ?>
                            <a href="<?php echo esc_url($guest_facebook); ?>" target="_blank" rel="noopener" class="hr-share-btn" aria-label="<?php echo esc_attr($author_name); ?> on Facebook">fb</a>
                        <?php endif; ?>
                    <?php else : ?>
                        <!-- Default Article Share Buttons -->
                        <a href="<?php echo esc_url(add_query_arg('url', rawurlencode($permalink), 'https://www.linkedin.com/sharing/share-offsite/')); ?>" target="_blank" rel="noopener" class="hr-share-btn" aria-label="Share on LinkedIn">in</a>
                        <a href="<?php echo esc_url(add_query_arg(array('url' => rawurlencode($permalink), 'text' => rawurlencode(html_entity_decode(wp_strip_all_tags($post_title), ENT_QUOTES | ENT_HTML5, 'UTF-8'))), 'https://twitter.com/intent/tweet')); ?>" target="_blank" rel="noopener" class="hr-share-btn" aria-label="Share on X">𝕏</a>
                        <a href="<?php echo esc_url(add_query_arg('u', rawurlencode($permalink), 'https://www.facebook.com/sharer/sharer.php')); ?>" target="_blank" rel="noopener" class="hr-share-btn" aria-label="Share on Facebook">fb</a>
                    <?php endif; ?>
                </div>
            </div>

            <?php do_action( 'hr_single_before_content', get_the_ID() ); ?>

            <!-- Hero image -->
            <?php if ( has_post_thumbnail() ) : ?>
            <div class="hr-hero-image-wrap">
                <?php the_post_thumbnail('full', array('data-no-lazy' => '1', 'class' => 'skip-lazy attachment-full size-full wp-post-image', 'loading' => 'eager', 'fetchpriority' => 'high')); ?>
                <?php
                $caption = get_post_field('post_excerpt', get_post_thumbnail_id($post_id));
                if ($caption) : ?>
                <div class="hr-hero-caption"><?php echo esc_html($caption); ?></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            

<!-- ========================================== -->
        <!-- GEO AI BRIEFING INJECTION (TYPING EFFECT)  -->
        <!-- ========================================== -->
        <?php 
        $geo_summary = get_post_meta(get_the_ID(), '_hr_geo_summary', true);
        if (!empty($geo_summary)) : 
        ?>

            <details id="ai-briefing-details" class="hr-ai-details">
                <summary id="ai-briefing-summary">
                    <span id="ai-status-text">✧ GENERATE BRIEFING</span>
                    <span id="ai-toggle-icon" class="hr-ai-icon" aria-hidden="true">+</span>
                </summary>
                
                <!-- The raw text is loaded here for bots, but JS manipulates it for humans -->
                <p id="ai-content-box" class="hr-ai-text">
                    <?php echo esc_html($geo_summary); ?>
                </p>
            </details>

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
                <a href="<?php echo esc_url(get_tag_link($tag->term_id)); ?>" class="hr-tag"><?php echo esc_html($tag->name); ?></a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Author box -->
        <div class="hr-author-box">
         <?php if ($author_avatar) : ?>
        <img src="<?php echo esc_url($author_avatar); ?>" alt="<?php echo esc_attr($author_name); ?>" class="hr-author-avatar" width="64" height="64" loading="lazy" decoding="async">
        <?php else : ?>
                 <div class="hr-author-avatar-placeholder">
                <svg aria-hidden="true" focusable="false" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                </div>
        <?php endif; ?>
        <div>
        <div class="hr-author-label">Written by</div>
        <div class="hr-author-name">
            <a href="<?php echo esc_url($author_link); ?>">
                <?php echo esc_html($author_name); ?>
            </a>
        </div>
        
        <?php if ($author_bio) : ?>
        <div class="hr-author-bio-container">
            <div class="hr-author-bio-text hr-author-bio">
                <?php echo wpautop( make_clickable( esc_html( $author_bio ) ) ); ?>
            </div>
            <button type="button" class="hr-bio-toggle" aria-expanded="false" hidden>See More</button>
        </div>
        <?php endif; ?>
        
        <?php if (isset($is_guest) && $is_guest && (!empty($guest_linkedin) || !empty($guest_twitter) || !empty($guest_facebook))) : ?>
        <div class="hr-author-socials">
            <?php if (!empty($guest_linkedin)) : ?><a href="<?php echo esc_url($guest_linkedin); ?>" target="_blank" rel="noopener" class="hr-share-btn" aria-label="<?php echo esc_attr($author_name); ?> on LinkedIn">in</a><?php endif; ?>
            <?php if (!empty($guest_twitter)) : ?><a href="<?php echo esc_url($guest_twitter); ?>" target="_blank" rel="noopener" class="hr-share-btn" aria-label="<?php echo esc_attr($author_name); ?> on X">𝕏</a><?php endif; ?>
            <?php if (!empty($guest_facebook)) : ?><a href="<?php echo esc_url($guest_facebook); ?>" target="_blank" rel="noopener" class="hr-share-btn" aria-label="<?php echo esc_attr($author_name); ?> on Facebook">fb</a><?php endif; ?>
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
                <a href="<?php echo esc_url(get_permalink($prev_post->ID)); ?>" class="hr-nav-item prev">
                    <span class="hr-nav-direction">← Previous Opinion</span>
                    <span class="hr-nav-title"><?php echo esc_html(get_the_title($prev_post->ID)); ?></span>
                </a>
                <?php else : ?>
                <div></div>
                <?php endif; ?>
                
                <?php if ($next_post) : ?>
                <a href="<?php echo esc_url(get_permalink($next_post->ID)); ?>" class="hr-nav-item next">
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
                        $m_thumb = hr_post_image(get_the_ID(), 'medium');
                        $m_cats  = get_the_category();
                        $m_cat   = !empty($m_cats) ? $m_cats[0]->name : '';
                    ?>
                    <a href="<?php echo esc_url(get_permalink()); ?>" class="hr-more-card">
                        <div class="hr-more-thumb">
                            <?php if ($m_thumb) : ?>
                            <?php echo $m_thumb; // phpcs:ignore WordPress.Security.EscapeOutput -- built with esc_url/esc_attr ?>
                            <?php endif; ?>
                            <?php if ($m_cat) : ?>
                            <span class="hr-more-thumb-cat"><?php echo esc_html($m_cat); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="hr-more-title"><?php echo esc_html(get_the_title()); ?></div>
                        <div class="hr-more-date"><?php echo esc_html(get_the_date('F j, Y')); ?></div>
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
                    <a href="<?php echo esc_url(home_url('/markets/')); ?>" class="hr-macro-link">Full Markets →</a>
                </div>
            </div>

            <!-- Latest Posts -->
            <?php if ($latest_posts->have_posts()) : ?>
            <div>
                <div class="hr-sb-label"><span>Latest</span></div>
                <?php while ($latest_posts->have_posts()) : $latest_posts->the_post();
                    $sb_thumb = hr_post_image(get_the_ID(), 'thumbnail');
                    $sb_cats  = get_the_category();
                    $sb_cat   = !empty($sb_cats) ? $sb_cats[0]->name : '';
                ?>
                <a href="<?php echo esc_url(get_permalink()); ?>" class="hr-sb-post">
                    <div class="hr-sb-thumb">
                        <?php if ($sb_thumb) : ?>
                        <?php echo $sb_thumb; // phpcs:ignore WordPress.Security.EscapeOutput -- built with esc_url/esc_attr ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <?php if ($sb_cat) : ?>
                        <div class="hr-sb-cat"><?php echo esc_html($sb_cat); ?></div>
                        <?php endif; ?>
                        <div class="hr-sb-title"><?php echo esc_html(get_the_title()); ?></div>
                        <div class="hr-sb-date"><?php echo esc_html(get_the_date('F j, Y')); ?></div>
                    </div>
                </a>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>
            <?php endif; ?>

            <!-- Executive Spotlight Promo -->
            <?php if ($spotlight_post->have_posts()) : $spotlight_post->the_post(); ?>
            <div class="hr-spotlight-promo">
                <div class="hr-spotlight-label">Executive Spotlight</div>
                <p class="hr-spotlight-title"><?php echo esc_html(get_the_title()); ?></p>
                <?php
                $spot_quote = get_post_meta(get_the_ID(), 'es_pull_quote', true);
                if (!$spot_quote) $spot_quote = wp_trim_words(get_the_excerpt(), 16, '...');
                ?>
                <div class="hr-spotlight-quote"><?php echo esc_html($spot_quote); ?></div>
                <a href="<?php echo esc_url(get_permalink()); ?>" class="hr-spotlight-link">Read the Interview →</a>
            </div>
            <?php wp_reset_postdata(); endif; ?>

            

        </aside>

    </div><!-- .hr-page-grid -->

    

</div><!-- .hr-single-wrapper -->

<?php
endwhile;
?>


<?php
get_footer();
?>