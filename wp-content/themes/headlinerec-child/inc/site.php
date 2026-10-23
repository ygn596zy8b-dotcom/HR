<?php
/**
 * Site chrome owned by the theme (MSBJ): the TradingView ticker tape (moved out of Code Snippet #7), the
 * sign-up calls to action (home hero + sticky mobile bar), and home-page clean-up.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Pages that hold credentials or personal data: account routes, dashboard, verify, and every plugin
 * virtual page. No third-party script is printed on them.
 */
function hr_is_sensitive_page(): bool {
    if ( in_array( 'hr-virtual', get_body_class(), true ) || is_page( 'dashboard' ) ) {
        return true;
    }
    return (bool) preg_match( '#^(account|dashboard|verify)(/|$)#', hr_request_path() );
}

/** Request path relative to the site root, without slashes at either end ("account/login"). */
function hr_request_path(): string {
    $path = trim( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ), '/' );
    $home = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
    if ( '' !== $home && 0 === strpos( $path . '/', $home . '/' ) ) {
        $path = trim( substr( $path, strlen( $home ) ), '/' );
    }
    return $path;
}

// ---------------------------------------------------------------------------------------------------------------
// Ticker tape. Snippet #7 printed it inside <head> on every page (invalid HTML, third-party JS next to password
// forms). It is switched off once and the same widget is rendered here, at the top of <body>, except on
// sensitive pages.
// ---------------------------------------------------------------------------------------------------------------

add_action( 'init', 'hr_migrate_ticker_snippet' );
function hr_migrate_ticker_snippet() {
    if ( get_option( 'hr_ticker_migrated' ) ) {
        return;
    }
    global $wpdb;
    $table = $wpdb->prefix . 'snippets';
    if ( $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) {
        $wpdb->update( $table, array( 'active' => 0 ), array( 'name' => 'Homepage Live Ticker Tape', 'active' => 1 ), array( '%d' ), array( '%s', '%d' ) );
    }
    update_option( 'hr_ticker_migrated', 1 );
}

add_action( 'wp_body_open', 'hr_ticker_tape', 1 );
function hr_ticker_tape() {
    if ( hr_is_sensitive_page() ) {
        return;
    }
    // Until the tape has loaded (and for good if it cannot), the strip shows the latest SBP figures at the same height.
    echo hr_tv_widget( // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in hr_tv_widget()
        'ticker-tape',
        array(
            'symbols'        => array(
                array( 'proName' => 'FX_IDC:USDPKR', 'title' => 'USD/PKR' ),
                array( 'proName' => 'FX_IDC:SARPKR', 'title' => 'SAR/PKR' ),
                array( 'proName' => 'FX_IDC:AEDPKR', 'title' => 'AED/PKR' ),
                array( 'proName' => 'TVC:UKOIL', 'title' => 'Brent Crude' ),
                array( 'proName' => 'TVC:GOLD', 'title' => 'Gold' ),
                array( 'proName' => 'FOREXCOM:SPXUSD', 'title' => 'S&P 500' ),
            ),
            'showSymbolLogo' => false,
            'isTransparent'  => true,
            'displayMode'    => 'regular',
            'colorTheme'     => 'light',
            'locale'         => 'en',
        ),
        array(
            'class'       => 'hr-ticker-tape hr-tv--tape',
            'label'       => 'Market ticker',
            'autosize'    => false,
            'placeholder' => array(
                'variant' => 'tape',
                'loading' => 'Live market ticker loads here',
                'error'   => 'The live market ticker is unavailable right now.',
                'off'     => 'The live market ticker is switched off in your cookie settings.',
                'figures' => hr_sbp_figures( array( 'policy_rate', 'inflation', 'kibor', 'pkr_usd', 'remittances' ) ),
            ),
        )
    );
}

// ---------------------------------------------------------------------------------------------------------------
// Calls to action. "Join free" is swapped for "My reading streak" by CSS when <html> is marked signed in
// (from the non-secret hr_li cookie, see hr-site.mjs), so cached pages stay identical for every visitor.
// ---------------------------------------------------------------------------------------------------------------

/** Join / streak link pair used by both CTAs. */
function hr_cta_account_links( string $join_label, string $streak_label, string $class ): string {
    return sprintf(
        '<a class="%1$s hr-cta-join" href="%2$s">%3$s</a><a class="%1$s hr-cta-streak" href="%4$s">%5$s</a>',
        esc_attr( $class ),
        esc_url( home_url( '/account/register/' ) ),
        esc_html( $join_label ),
        esc_url( home_url( '/dashboard/' ) ),
        esc_html( $streak_label )
    );
}

/** Above-the-fold CTA at the bottom of the home hero's "Latest News" column. */
function hr_home_cta() {
    ?>
    <div class="hr-cta-hero">
        <p class="hr-cta-hero-title">Never miss Pakistan's biggest business story</p>
        <p class="hr-cta-hero-text">Create a free account to build your daily reading streak.</p>
        <div class="hr-cta-actions">
            <?php echo hr_cta_account_links( 'Join free', 'My reading streak', 'hr-cta-action hr-cta-action--primary' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper ?>
            <a class="hr-cta-action hr-cta-action--ghost" href="<?php echo esc_url( home_url( '/podcast/' ) ); ?>">Listen to the Podcast</a>
        </div>
    </div>
    <?php
}

// Sticky mobile bar (≤ 768px, shown by hr-site.js after the lead hero). Not on account, dashboard, verify or podcast pages.
add_action( 'wp_footer', 'hr_mobile_cta', 5 );
function hr_mobile_cta() {
    if ( hr_is_sensitive_page() || preg_match( '#^podcast(/|$)#', hr_request_path() ) || is_post_type_archive( 'hr_episode' ) || is_singular( 'hr_episode' ) ) {
        return;
    }
    ?>
<div class="hr-mobile-cta" id="hr-mobile-cta" role="region" aria-label="<?php esc_attr_e( 'Quick links', 'covernews' ); ?>">
    <a class="hr-mobile-cta-btn hr-mobile-cta-btn--ghost" href="<?php echo esc_url( home_url( '/podcast/' ) ); ?>">Listen: Podcast</a>
    <?php echo hr_cta_account_links( 'Join free', 'My streak', 'hr-mobile-cta-btn hr-mobile-cta-btn--primary' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper ?>
    <button type="button" class="hr-mobile-cta-close" aria-label="<?php esc_attr_e( 'Dismiss', 'covernews' ); ?>">&times;</button>
</div>
    <?php
}

// ---------------------------------------------------------------------------------------------------------------
// Home: the parent's widget section after "Opinion" renders only empty widget areas. Drop it so no gap remains.
// ---------------------------------------------------------------------------------------------------------------

add_action( 'wp', static function () {
    if ( is_front_page() ) {
        remove_action( 'covernews_front_page_section', 'covernews_front_page_widgets_section', 50 );
    }
} );

// ---------------------------------------------------------------------------------------------------------------
// Images
// ---------------------------------------------------------------------------------------------------------------

/**
 * <img> for a post's featured image (same src as get_the_post_thumbnail_url( $post_id, $size )) with
 * width/height (no layout shift), lazy loading, async decoding, and alt from the attachment or the post title.
 */
function hr_post_image( int $post_id, string $size, array $attr = array() ): string {
    $id  = (int) get_post_thumbnail_id( $post_id );
    $src = $id ? wp_get_attachment_image_src( $id, $size ) : false;
    if ( ! $src ) {
        return '';
    }
    $alt  = trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );
    $attr = array_merge( array( 'alt' => '' !== $alt ? $alt : wp_strip_all_tags( get_the_title( $post_id ) ), 'loading' => 'lazy', 'decoding' => 'async' ), $attr );
    $html = sprintf( '<img src="%s" width="%d" height="%d"', esc_url( $src[0] ), (int) $src[1], (int) $src[2] );
    foreach ( $attr as $name => $value ) {
        $html .= sprintf( ' %s="%s"', esc_attr( $name ), esc_attr( $value ) );
    }
    return $html . '>';
}

/**
 * Featured image for a card or list item, through wp_get_attachment_image() (srcset from the size's family,
 * width/height so nothing shifts), or the branded placeholder when the post has none.
 *
 * @param string $sizes Rendered width for the browser's srcset choice, e.g. "(min-width: 769px) 120px, 112px".
 * @param array  $attr  Extra attributes. Lazy + async by default; pass 'loading' => 'eager' for the first screen
 *                      (the parent theme forces lazy on every attachment image, so the requested value is restored).
 *                      Pass 'alt' => '' when the image sits in a link that repeats the headline link beside it.
 */
function hr_card_image( int $post_id, string $size, string $sizes, array $attr = array() ): string {
    $id = (int) get_post_thumbnail_id( $post_id );
    if ( $id && wp_attachment_is_image( $id ) ) {
        $alt  = trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );
        $attr = array_merge(
            array(
                'alt'      => '' !== $alt ? $alt : wp_strip_all_tags( get_the_title( $post_id ) ),
                'sizes'    => $sizes,
                'loading'  => 'lazy',
                'decoding' => 'async',
            ),
            $attr
        );
        // The parent theme rewrites every attachment image's loading and alt (lazy, and the file name when alt is
        // empty); restore what was asked for. An empty alt is deliberate: the card's headline link names the story.
        $keep = static function ( $a ) use ( $attr ) {
            $a['loading'] = $attr['loading'];
            $a['alt']     = $attr['alt'];
            return $a;
        };
        add_filter( 'wp_get_attachment_image_attributes', $keep, 99 );
        $html = wp_get_attachment_image( $id, $size, false, $attr );
        remove_filter( 'wp_get_attachment_image_attributes', $keep, 99 );
        if ( $html ) {
            return $html;
        }
    }
    return hr_card_image_fallback();
}

/** Placeholder for a post without a featured image: the HR mark on the site's near-black, same box as the image. */
function hr_card_image_fallback(): string {
    return '<span class="hr-img-fallback" aria-hidden="true"><span class="hr-img-fallback__mark">H<span>R</span></span></span>';
}

/** First category of a post that is neither the "Editor's Picks" flag nor the default "Uncategorized", or ''. */
function hr_primary_category_name( int $post_id ): string {
    $default = (int) get_option( 'default_category' );
    foreach ( get_the_category( $post_id ) as $cat ) {
        if ( (int) $cat->term_id !== $default && "editor's picks" !== strtolower( html_entity_decode( $cat->name, ENT_QUOTES ) ) ) {
            return $cat->name;
        }
    }
    return '';
}

// Contact Form 7 frames its empty select option ("Please choose an option") with long dashes; show the plain words.
add_filter( 'gettext_contact-form-7', static function ( $translation, $text ) {
    return str_contains( $text, 'Please choose an option' ) ? 'Please choose an option' : $translation;
}, 10, 2 );
