<?php
/**
 * Template Name: Intelligence Dashboard
 *
 * The reading streak dashboard is rendered by the hr-platform plugin (streaks module). Without the plugin, a plain
 * summary from the streak user meta the plugin keeps up to date.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( function_exists( 'HR\Platform\Streaks\dashboard_page' ) ) {
    HR\Platform\Streaks\dashboard_page();
    return;
}

if ( ! is_user_logged_in() ) {
    wp_safe_redirect( wp_login_url( get_permalink() ) );
    exit;
}
nocache_headers();
get_header();

$hr_uid   = get_current_user_id();
$hr_today = get_user_meta( $hr_uid, '_hr_today_read_posts', true );
$hr_rows  = array(
    'Current streak' => (int) get_user_meta( $hr_uid, '_hr_streak_count', true ) . ' days',
    'Best streak'    => (int) get_user_meta( $hr_uid, '_hr_longest_streak', true ) . ' days',
    'Read today'     => ( is_array( $hr_today ) ? count( $hr_today ) : 0 ) . ' articles',
    'Articles read'  => (int) get_user_meta( $hr_uid, '_hr_total_articles_read', true ),
    'Streak freezes' => (int) get_user_meta( $hr_uid, '_hr_streak_freezes', true ),
);
?>
<main class="hr-dash-fallback" style="max-width:720px;margin:0 auto;padding:40px 16px 80px;">
    <h1>Reading streak</h1>
    <dl>
        <?php foreach ( $hr_rows as $hr_label => $hr_value ) : ?>
            <dt style="font-weight:700;"><?php echo esc_html( $hr_label ); ?></dt>
            <dd style="margin:0 0 12px;"><?php echo esc_html( (string) $hr_value ); ?></dd>
        <?php endforeach; ?>
    </dl>
</main>
<?php
get_footer();
