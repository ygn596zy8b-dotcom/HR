<?php
if (!function_exists('covernews_front_page_main_section_1')) :
  function covernews_front_page_main_section_1()
  {
    $covernews_enable_main_slider = covernews_get_option('show_main_news_section');
?>
    <!-- 1. TOP DARK HERO SECTION -->
    <section class="af-blocks sbp-premium-hero-dark">
      <div class="container">
        <div class="row">
          <?php if ($covernews_enable_main_slider): ?>
            <div class="for-main-row sbp-flex-row">
              <!-- Left Column: Latest News -->
              <div class="col-sm-3 sbp-sidebar-picks">
                <div class="sbp-sidebar-header"><h2 class="sbp-sidebar-title"><span class="sbp-red-line">&mdash;</span> Latest News</h2></div>
                <div class="sbp-text-stories-list">
                  <?php
                  $sidebar_query = new WP_Query(['post_type'=>'post', 'posts_per_page'=>3, 'offset'=>1, 'ignore_sticky_posts'=>true, 'post_status'=>'publish', 'no_found_rows'=>true]);
                  if ($sidebar_query->have_posts()) : while ($sidebar_query->have_posts()) : $sidebar_query->the_post();
                  ?>
                      <div class="sbp-text-story-item">
                        <span class="sbp-story-cat"><?php $cats = get_the_category(); if(!empty($cats)){ foreach($cats as $c){ if(strtolower($c->name)!=="editor's picks"){ echo esc_html($c->name); break; } } } ?></span>
                        <h3 class="sbp-story-title"><a href="<?php echo esc_url(get_permalink()); ?>"><?php echo esc_html(get_the_title()); ?></a></h3>
                        <span class="sbp-story-date"><?php echo esc_html(get_the_date('M d, Y')); ?></span>
                      </div>
                  <?php endwhile; endif; wp_reset_postdata(); ?>
                </div>
                <?php hr_home_cta(); ?>
              </div>

              <!-- Right Column Area: Absolute Latest News & Ticker -->
              <div class="col-sm-9 sbp-main-stage-container">
                <div class="main-story-wrapper sbp-main-text-stage">
                    <?php
                    $main_query = new WP_Query(['post_type'=>'post', 'posts_per_page'=>1, 'ignore_sticky_posts'=>true, 'post_status'=>'publish', 'no_found_rows'=>true]);
                    if ($main_query->have_posts()) : while ($main_query->have_posts()) : $main_query->the_post();
                    ?>
                        <?php $hr_lead_id = get_the_ID(); ?>
                        <div class="sbp-mockup-feature">
                            <span class="sbp-feature-cat"><?php $cats = get_the_category(); if(!empty($cats)){ foreach($cats as $c){ if(strtolower($c->name)!=="editor's picks"){ echo esc_html($c->name); break; } } } ?></span>
                            <h2 class="sbp-feature-title"><a href="<?php echo esc_url(get_permalink()); ?>"><?php echo esc_html(get_the_title()); ?></a></h2>
                            <p class="sbp-feature-excerpt"><?php echo esc_html(wp_trim_words(get_the_excerpt(), 35, '...')); ?></p>
                            <div class="sbp-feature-meta-row">
                                <span class="sbp-feature-date"><?php echo esc_html(get_the_date('F d, Y')); ?></span>
                                <a href="<?php echo esc_url(get_permalink()); ?>" class="sbp-readmore">Read More →</a>
                            </div>
                        </div>
                    <?php endwhile; endif; wp_reset_postdata(); ?>
                </div>
                <?php
                // Lead image, desktop only: fills the column beside "Latest News". Phones get a 1px GIF (no download).
                $hr_lead_img = empty( $hr_lead_id ) ? 0 : get_post_thumbnail_id( $hr_lead_id );
                $hr_lead_src = $hr_lead_img ? wp_get_attachment_image_src( $hr_lead_img, 'large' ) : false;
                if ( $hr_lead_src ) :
                ?>
                <a class="hr-lead-media" href="<?php echo esc_url( get_permalink( $hr_lead_id ) ); ?>" tabindex="-1" aria-hidden="true">
                    <picture>
                        <source media="(min-width: 769px)" srcset="<?php echo esc_attr( wp_get_attachment_image_srcset( $hr_lead_img, 'large' ) ?: $hr_lead_src[0] ); ?>" sizes="(min-width: 1440px) 860px, 62vw">
                        <img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" alt="" width="<?php echo (int) $hr_lead_src[1]; ?>" height="<?php echo (int) $hr_lead_src[2]; ?>" fetchpriority="high" decoding="async">
                    </picture>
                </a>
                <?php endif; ?>
                <div class="sbp-horizontal-macro-row"><?php do_action('covernews_action_banner_trending_posts'); ?></div>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <?php do_action( 'hr_home_after_banner' ); // Podcast section (hr-platform) ?>

    <!-- 2. LIGHT THEME "TODAY'S BRIEFING" GRID -->
    <section class="sbp-light-section">
      <div class="container container-full-width">
        <div class="sbp-section-header"><span class="sbp-section-title"><span class="sbp-red-line">&mdash;</span> TODAY'S BRIEFING</span></div>
        <div class="row sbp-remaining-grid">
            <?php
            $remaining_query = new WP_Query(['post_type'=>'post', 'posts_per_page'=>4, 'offset'=>4, 'ignore_sticky_posts'=>true, 'post_status'=>'publish', 'no_found_rows'=>true]);
            
            if ($remaining_query->have_posts()) : while ($remaining_query->have_posts()) : $remaining_query->the_post();
            ?>
                <div class="col-sm-3 sbp-remaining-item">
                    <figure class="sbp-briefing-thumb">
                        <span class="sbp-card-badge"><?php $cats = get_the_category(); if(!empty($cats)){ foreach($cats as $c){ if(strtolower($c->name)!=="editor's picks"){ echo esc_html($c->name); break; } } } ?></span>
                        <a href="<?php echo esc_url(get_permalink()); ?>">
                            <?php 
                            // Below the fold on every width (the lead story above is the LCP): lazy like the rest.
                            if (has_post_thumbnail()) {
                                the_post_thumbnail('medium');
                            }
                            ?>
                        </a>
                    </figure>
                    <h3 class="sbp-card-title"><a href="<?php echo esc_url(get_permalink()); ?>"><?php echo esc_html(get_the_title()); ?></a></h3>
                    <span class="sbp-card-date"><?php echo esc_html(get_the_date('M d, Y')); ?></span>
                </div>
            <?php endwhile; endif; wp_reset_postdata(); ?>
        </div>
      </div>
    </section>

    <!-- 3. DARK EXECUTIVE SPOTLIGHT SECTION -->
    <section class="sbp-executive-spotlight-section">
      <div class="container container-full-width">
        <div class="sbp-exec-container">
            <div class="sbp-exec-header">
                <span class="sbp-section-title sbp-dark-title"><span class="sbp-red-line">&mdash;</span> EXECUTIVE SPOTLIGHT</span>
                <a href="<?php echo esc_url(home_url('/category/executive-spotlight/')); ?>" class="sbp-view-all">VIEW ALL &rarr;</a>
            </div>
            <?php
            $exec_query = new WP_Query(['category_name'=>'executive-spotlight', 'posts_per_page'=>1, 'ignore_sticky_posts'=>true, 'post_status'=>'publish', 'no_found_rows'=>true]);
            if ($exec_query->have_posts()) : while ($exec_query->have_posts()) : $exec_query->the_post();
            ?>
            <div class="sbp-exec-card">
                
               <div class="sbp-exec-avatar-col">
    <div class="sbp-circle-avatar">
        <?php
        $featured_img = has_post_thumbnail() ? hr_post_image(get_the_ID(), 'large') : '';
        if ($featured_img) :
            echo $featured_img; // phpcs:ignore WordPress.Security.EscapeOutput -- built with esc_url/esc_attr
        else :
            echo esc_html(mb_strtoupper(mb_substr(wp_strip_all_tags(get_the_title()), 0, 2)));
        endif; ?>
    </div>
    
    <?php 
    // Fetch custom executive meta fields
    $exec_name  = get_post_meta(get_the_ID(), '_hr_exec_name', true);
    $exec_title = get_post_meta(get_the_ID(), '_hr_exec_title', true);

    if (!empty($exec_name)) : ?>
        <div class="hr-exec-person">
            <div class="hr-exec-person-name">
                <?php echo esc_html($exec_name); ?>
            </div>
            <?php if (!empty($exec_title)) : ?>
                <div class="hr-exec-person-title">
                    <?php echo esc_html($exec_title); ?>
                </div>
            <?php endif; ?>
        </div>
    <?php else : ?>
        <div class="sbp-exec-badge-text hr-exec-badge-text">EXECUTIVE SPOTLIGHT</div>
    <?php endif; ?>
</div>
                <div class="sbp-exec-content-col">
                    <span class="sbp-exec-meta"><?php $tags = get_the_tags(); if ($tags && count($tags) >= 2) { echo esc_html(strtoupper($tags[0]->name) . ' &middot; ' . strtoupper($tags[1]->name)); } elseif ($tags) { echo esc_html(strtoupper($tags[0]->name)); } else { echo 'LEADERSHIP &middot; STRATEGY'; } ?></span>
                    <h3 class="sbp-exec-title"><a href="<?php echo esc_url(get_permalink()); ?>"><?php echo esc_html(get_the_title()); ?></a></h3>
                    <div class="sbp-exec-quote"><p>"<?php echo esc_html(wp_trim_words(get_the_excerpt(), 20, '...')); ?>"</p></div>
                    <a href="<?php echo esc_url(get_permalink()); ?>" class="sbp-read-interview">READ THE INTERVIEW &rarr;</a>
                </div>
            </div>
            <?php endwhile; endif; wp_reset_postdata(); ?>
        </div>
      </div>
    </section>

    <!-- 4. BUSINESS SECTION -->
    <section class="sbp-light-section sbp-business-section">
      <div class="container container-full-width">
        <div class="sbp-section-header"><span class="sbp-section-title"><span class="sbp-red-line">&mdash;</span> BUSINESS</span></div>
        <div class="row">
            <?php
            $biz_query = new WP_Query(['category_name' => 'business', 'posts_per_page' => 4, 'no_found_rows' => true]);
            if ($biz_query->have_posts()) :
                $biz_query->the_post(); // Main feature (Left)
                ?>
                <div class="col-sm-7 sbp-biz-main">
                    <figure class="sbp-briefing-thumb">
                        <span class="sbp-card-badge">BUSINESS</span>
                        <a href="<?php echo esc_url(get_permalink()); ?>"><?php the_post_thumbnail('large'); ?></a>
                    </figure>
                    <h2 class="sbp-biz-main-title"><a href="<?php echo esc_url(get_permalink()); ?>"><?php echo esc_html(get_the_title()); ?></a></h2>
                    <p class="sbp-biz-main-excerpt"><?php echo esc_html(wp_trim_words(get_the_excerpt(), 25, '...')); ?></p>
                    <span class="sbp-card-date"><?php echo esc_html(get_the_date('M d, Y')); ?></span>
                </div>
                <div class="col-sm-5 sbp-biz-list">
                <?php while ($biz_query->have_posts()) : $biz_query->the_post(); // Next 3 list items (Right) ?>
                    <div class="sbp-biz-list-item">
                        <span class="sbp-story-cat">BUSINESS</span>
                        <h3 class="sbp-card-title"><a href="<?php echo esc_url(get_permalink()); ?>"><?php echo esc_html(get_the_title()); ?></a></h3>
                        <span class="sbp-card-date"><?php echo esc_html(get_the_date('M d, Y')); ?></span>
                    </div>
                <?php endwhile; ?>
                </div>
            <?php endif; wp_reset_postdata(); ?>
        </div>
      </div>
    </section>

    <!-- 5. MARKETS INTELLIGENCE SECTION (DARK TERMINAL UI) -->
    <section class="sbp-dark-terminal-section">
      <div class="container container-full-width">
        <div class="sbp-exec-header hr-flush">
            <span class="sbp-section-title sbp-dark-title">
                <span class="sbp-red-line">&mdash;</span> MARKETS INTELLIGENCE
                <!-- New Live Indicator -->
                <span class="sbp-live-indicator"><span class="sbp-pulse-dot"></span> LIVE</span>
            </span>
            <a href="<?php echo esc_url(home_url('/markets/')); ?>" class="sbp-view-all">FULL MARKETS &rarr;</a>
        </div>
        <div class="row sbp-terminal-grid-wrapper">
            
            <!-- Left: Currencies & Indices -->
            <div class="col-sm-6 sbp-terminal-col sbp-currency-col hr-flush">
                <div class="sbp-native-tv-mask">
                    
                    <!-- TradingView Widget BEGIN -->
<div class="tradingview-widget-container hr-tv-quotes">
  <div class="tradingview-widget-container__widget"></div>
  <script type="text/plain" data-hr-tv="https://s3.tradingview.com/external-embedding/embed-widget-market-quotes.js">
  {
  "width": "100%",
  "height": 420,
  "symbolsGroups": [
    {
      "name": "",
      "originalName": "",
      "symbols": [
        { "name": "FX_IDC:USDPKR", "displayName": "USD/PKR" },
        { "name": "FX_IDC:SARPKR", "displayName": "SAR/PKR" },
        { "name": "FX_IDC:AEDPKR", "displayName": "AED/PKR" },
        { "name": "FX_IDC:GBPPKR", "displayName": "GBP/PKR" },
        { "name": "FX_IDC:EURPKR", "displayName": "EUR/PKR" },
        { "name": "TVC:GOLD", "displayName": "Gold (USD/oz)" },
        { "name": "TVC:USOIL", "displayName": "WTI Crude Oil" },
        { "name": "TVC:UKOIL", "displayName": "Brent Crude" }
      ]
    }
  ],
  "showSymbolLogo": false,
  "colorTheme": "dark",
  "isTransparent": true,
  "locale": "en",
  "backgroundColor": "#0a0a0c"
}
  </script>
</div>
<!-- TradingView Widget END -->
                    </div>
            </div>

            <!-- Right: SBP Data API -->
            <div class="col-sm-6 sbp-terminal-col sbp-sbp-col">
                <div class="sbp-terminal-indicators">
                    <?php
                    // 1. Fetch the live data array from your functions.php
                    $sbp_data = function_exists('get_headline_recorder_sbp_data') ? get_headline_recorder_sbp_data() : [];
                    
                    // 2. Define the exact keys that match your API fetcher
                    $ind_keys = [
                        'policy_rate' => ['label'=>'CENTRAL POLICY RATE', 'sub'=>'Monetary policy committee'],
                        'inflation'   => ['label'=>'NATIONAL CPI INFLATION', 'sub'=>'Macro target sector · YoY'],
                        'kibor'       => ['label'=>'6-MONTH KIBOR', 'sub'=>'Interbank offered rate'],
                        'gdp_growth'  => ['label'=>'REAL GDP GROWTH', 'sub'=>'Annual estimate'],
                        'remittances' => ['label'=>'WORKERS REMITTANCES', 'sub'=>'Monthly liquidity inflow'],
                        'ca_balance'  => ['label'=>'CURRENT ACCOUNT BALANCE', 'sub'=>'Monthly reported']
                    ];
                    
                    // 3. Loop through and output the premium UI cards
                    foreach($ind_keys as $k => $meta) {
                        // Check if data exists for this key, otherwise default to N/A
                        $val = (isset($sbp_data[$k]) && isset($sbp_data[$k]['value'])) ? $sbp_data[$k]['value'] : 'N/A';
                        
                        // Instantly strip the $ sign from the cached API string
                        $val = str_replace('$', '', $val);
                        
                        $unit = (isset($sbp_data[$k]) && isset($sbp_data[$k]['unit'])) ? $sbp_data[$k]['unit'] : '';
                        
                        echo '<div class="sbp-term-card">';
                        echo '<span class="sbp-term-label">' . esc_html($meta['label']) . '</span>';
                        echo '<div class="sbp-term-val sbp-mono">' . esc_html($val) . ' <span class="sbp-term-unit">' . esc_html($unit) . '</span></div>';
                        echo '<span class="sbp-term-sub">' . esc_html($meta['sub']) . '</span>';
                        echo '</div>';
                    }
                    ?>
                </div>
            </div>

        </div>
      </div>
    </section>

    <!-- 6 & 7. MERGED DUAL LIST SECTION (PAKISTAN & TECH) -->
    <section class="sbp-light-section sbp-dual-list-section">
      <div class="container container-full-width">
        <div class="row">

            <!-- LEFT COLUMN: PAKISTAN FOCUS -->
            <div class="col-sm-6 sbp-list-column">
                <div class="sbp-section-header sbp-header-split sbp-border-bottom">
                    <span class="sbp-section-title">PAKISTAN FOCUS</span>
                    <a href="<?php echo esc_url(home_url('/category/pakistan/')); ?>" class="sbp-view-all">MORE</a>
                </div>
                <div class="sbp-list-items-wrapper">
                    <?php
                    $pak_query = new WP_Query(['category_name' => 'pakistan', 'posts_per_page' => 3, 'ignore_sticky_posts' => true, 'no_found_rows' => true]);
                    if ($pak_query->have_posts()) : while ($pak_query->have_posts()) : $pak_query->the_post();
                    ?>
                        <div class="sbp-list-item">
                            <figure class="sbp-list-thumb"><a href="<?php echo esc_url(get_permalink()); ?>"><?php the_post_thumbnail('thumbnail'); ?></a></figure>
                            <div class="sbp-list-content">
                                <span class="sbp-tag-bordered">PAKISTAN</span>
                                <h3 class="sbp-list-title"><a href="<?php echo esc_url(get_permalink()); ?>"><?php echo esc_html(get_the_title()); ?></a></h3>
                                <span class="sbp-card-date"><?php echo esc_html(get_the_date('M d, Y')); ?></span>
                            </div>
                        </div>
                    <?php endwhile; endif; wp_reset_postdata(); ?>
                </div>
            </div>

            <!-- RIGHT COLUMN: TECH & STARTUPS -->
            <div class="col-sm-6 sbp-list-column">
                <div class="sbp-section-header sbp-header-split sbp-border-bottom">
                    <span class="sbp-section-title">TECH &amp; STARTUPS</span>
                    <a href="<?php echo esc_url(home_url('/category/tech-and-startups/')); ?>" class="sbp-view-all">MORE TECH &rarr;</a>
                </div>
                <div class="sbp-list-items-wrapper">
                    <?php
                    // Preserving the Tag+Category safety query we built earlier
$tech_query = new WP_Query([
    'tax_query' => [
        'relation' => 'OR',
        ['taxonomy' => 'category', 'field' => 'slug', 'terms' => ['tech-and-startups', 'tech-startups', 'tech', 'startups', 'technology']],
        ['taxonomy' => 'post_tag', 'field' => 'slug', 'terms' => ['tech-and-startups', 'tech-startups', 'tech', 'startups', 'technology']]
    ],
    'posts_per_page' => 3,
    'ignore_sticky_posts' => true,
    'no_found_rows' => true
]);
                    if ($tech_query->have_posts()) : while ($tech_query->have_posts()) : $tech_query->the_post();
                    ?>
                        <div class="sbp-list-item">
                            <figure class="sbp-list-thumb"><a href="<?php echo esc_url(get_permalink()); ?>"><?php the_post_thumbnail('thumbnail'); ?></a></figure>
                            <div class="sbp-list-content">
                                <span class="sbp-tag-bordered">TECH</span>
                                <h3 class="sbp-list-title"><a href="<?php echo esc_url(get_permalink()); ?>"><?php echo esc_html(get_the_title()); ?></a></h3>
                                <span class="sbp-card-date"><?php echo esc_html(get_the_date('M d, Y')); ?></span>
                            </div>
                        </div>
                    <?php endwhile; endif; wp_reset_postdata(); ?>
                </div>
            </div>

        </div>
      </div>
    </section>

    <!-- 8. WORLD NEWS SECTION (DARK 4-COLUMN GRID) -->
    <section class="sbp-executive-spotlight-section sbp-world-news-section">
      <div class="container container-full-width">
        <div class="sbp-exec-header">
            <span class="sbp-section-title sbp-dark-title">WORLD NEWS</span>
            <a href="<?php echo esc_url(home_url('/category/global/')); ?>" class="sbp-view-all">VIEW ALL</a>
        </div>
        <div class="row sbp-remaining-grid">
            <?php
            $world_query = new WP_Query(['category_name' => 'global', 'posts_per_page' => 4, 'no_found_rows' => true]);
            if ($world_query->have_posts()) : while ($world_query->have_posts()) : $world_query->the_post();
            ?>
                <div class="col-sm-3 sbp-remaining-item sbp-world-card">
                    <figure class="sbp-briefing-thumb">
                        <a href="<?php echo esc_url(get_permalink()); ?>"><?php if (has_post_thumbnail()) { the_post_thumbnail('medium'); } ?></a>
                    </figure>
                    <!-- Tag moved below image, above title -->
                    <span class="sbp-tag-bordered hr-world-tag">GLOBAL</span>
                    <h3 class="sbp-card-title"><a href="<?php echo esc_url(get_permalink()); ?>" class="hr-world-link"><?php echo esc_html(get_the_title()); ?></a></h3>
                    <span class="sbp-card-date hr-world-date"><?php echo esc_html(get_the_date('M d, Y')); ?></span>
                </div>
            <?php endwhile; endif; wp_reset_postdata(); ?>
        </div>
      </div>
    </section>

    <!-- 9. OPINION & EDITORIAL SECTION (LIGHT 4-COLUMN GRID) -->
    <section class="sbp-light-section sbp-opinion-section hr-opinion-section">
      <div class="container container-full-width">
        <div class="sbp-section-header hr-opinion-header">
            <span class="sbp-section-title hr-opinion-title"><span class="sbp-red-line hr-opinion-dash">&mdash;</span> OPINION &amp; EDITORIAL</span>
            <a href="<?php echo esc_url(home_url('/category/opinion/')); ?>" class="sbp-view-all hr-opinion-more">MORE OPINIONS &rarr;</a>
        </div>
        <div class="row sbp-remaining-grid">
            <?php
            $opinion_query = new WP_Query(['category_name' => 'opinion', 'posts_per_page' => 4, 'ignore_sticky_posts'=>true, 'post_status'=>'publish', 'no_found_rows'=>true]);
            if ($opinion_query->have_posts()) : while ($opinion_query->have_posts()) : $opinion_query->the_post();
                // Fetch the guest author name, fallback to standard WP author if empty
                $guest_name = get_post_meta(get_the_ID(), 'guest_author_name', true);
                $author_display = !empty($guest_name) ? $guest_name : get_the_author();
                $guest_avatar   = !empty($guest_name) ? get_post_meta(get_the_ID(), 'guest_author_avatar', true) : '';
            ?>
                <div class="col-sm-3 sbp-remaining-item">
                    <figure class="sbp-briefing-thumb hr-opinion-thumb">
                        <a href="<?php echo esc_url(get_permalink()); ?>"><?php if (has_post_thumbnail()) { the_post_thumbnail('medium'); } ?></a>
                    </figure>
                    <div class="sbp-opinion-author hr-opinion-author">
                        <?php if ($guest_avatar) : ?>
                            <img class="hr-opinion-avatar" src="<?php echo esc_url($guest_avatar); ?>" alt="" width="28" height="28" loading="lazy" decoding="async">
                        <?php else : ?>
                            <span class="hr-opinion-avatar" aria-hidden="true"><?php echo esc_html(mb_substr($author_display, 0, 1)); ?></span>
                        <?php endif; ?>
                        <span class="hr-opinion-author-name">
                            <?php echo esc_html($author_display); ?>
                        </span>
                    </div>
                    <h3 class="sbp-card-title"><a href="<?php echo esc_url(get_permalink()); ?>" class="hr-opinion-link"><?php echo esc_html(get_the_title()); ?></a></h3>
                    <span class="sbp-card-date hr-opinion-date"><?php echo esc_html(get_the_date('M d, Y')); ?></span>
                </div>
            <?php endwhile; endif; wp_reset_postdata(); ?>
        </div>
      </div>
    </section>

<?php
  }
endif;
add_action('covernews_action_front_page_main_section_1', 'covernews_front_page_main_section_1', 40);
?>