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
    $post_date    = get_the_date('F j, Y');
    $author_name  = get_the_author();
    $author_id     = get_the_author_meta('ID');
    $author_avatar = get_avatar_url($author_id, array('size' => 104)); // Grab a crisp, high-res avatar
    $author_link   = get_author_posts_url($author_id);
    $post_content = get_the_content();
    $permalink    = get_permalink();
    $post_title   = get_the_title();

    // YouTube embed — get first YouTube URL from content
    $youtube_embed = '';
    preg_match('/(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $post_content, $yt_matches);
    if (!empty($yt_matches[1])) {
        $youtube_embed = 'https://www.youtube-nocookie.com/embed/' . $yt_matches[1];
    }

    // The video plays in the template's own player, so drop the YouTube embed blocks from the body
    // (they used to be hidden with CSS but still loaded a second iframe).
    $post_content = preg_replace('#<!-- wp:embed (\{[^\n]*?"providerNameSlug":"youtube"[^\n]*?\}) -->.*?<!-- /wp:embed -->\s*#s', '', $post_content);

    // Clean and process content
    $post_content = apply_filters('the_content', $post_content);

    hr_print_article_schema($post_id, array('name' => $author_name, 'url' => $author_link, 'description' => get_the_author_meta('description')), 'esa');
?>

<main class="esa-wrapper">

    <!-- BREADCRUMB -->
    <div class="esa-breadcrumb">
        <a href="<?php echo esc_url(home_url('/')); ?>">Home</a>
        <span>·</span>
        <a href="<?php echo esc_url(home_url('/category/executive-spotlight/')); ?>">Executive Spotlight</a>
    </div>

    <article class="esa-article">

        <!-- SERIES BADGE -->
        <div class="esa-series-badge">
            <span>Executive Spotlight</span>
        </div>

        <!-- HEADLINE -->
        <h1 class="esa-headline"><?php echo esc_html($post_title); ?></h1>

        <!-- EXECUTIVE STRIP -->
        <div class="esa-exec-strip">
            <?php if ($author_avatar) : ?>
            <img src="<?php echo esc_url($author_avatar); ?>"
                 alt="<?php echo esc_attr($author_name); ?>"
                 class="esa-exec-avatar" width="52" height="52" decoding="async">
            <?php else : ?>
            <div class="esa-exec-avatar esa-exec-avatar--initials">
                <?php echo esc_html(mb_strtoupper(mb_substr($author_name, 0, 2))); ?>
            </div>
            <?php endif; ?>
            <div class="esa-exec-info">
                <p class="esa-exec-name"><?php echo esc_html($author_name); ?></p>
                <p class="esa-exec-role">Executive Spotlight — Headline Recorder</p>
            </div>
            <?php do_action( 'hr_single_after_byline', get_the_ID() ); ?>
            <time class="esa-exec-date" datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html($post_date); ?></time>
        </div>

        <?php do_action( 'hr_single_before_content', get_the_ID() ); ?>

        <!-- VIDEO EMBED (if YouTube URL found in content) -->
        <?php if ($youtube_embed) : ?>
        <div class="esa-video-wrapper">
            <div class="esa-video-label">
                <span>Watch the Full Interview</span>
            </div>
            <div class="esa-video-frame">
                <?php echo hr_youtube_facade( $yt_matches[1], sprintf( 'Video: %s', wp_strip_all_tags( $post_title ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?>
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
            <a href="<?php echo esc_url(add_query_arg('url', rawurlencode($permalink), 'https://www.linkedin.com/sharing/share-offsite/')); ?>"
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
            <a href="<?php echo esc_url(home_url('/contact-us/')); ?>" class="esa-cta-btn">Get in Touch &rarr;</a>
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
                    'no_found_rows'  => true,
                ));

                if ($more_q->have_posts()) :
                    while ($more_q->have_posts()) : $more_q->the_post(); ?>
                    <a href="<?php echo esc_url(get_permalink()); ?>" class="esa-more-item">
                        <div class="esa-more-item-badge">Executive Spotlight</div>
                        <p class="esa-more-item-title"><?php echo esc_html(get_the_title()); ?></p>
                        <div class="esa-more-item-date"><?php echo esc_html(get_the_date('F j, Y')); ?></div>
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
</main>

<?php
endwhile;
get_footer();
?>