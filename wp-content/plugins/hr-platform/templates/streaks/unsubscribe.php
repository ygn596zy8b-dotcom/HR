<?php
/**
 * /reading/unsubscribe/ — turn off streak reminder emails. Theme override: headlinerec-child/hr/streaks/unsubscribe.php
 *
 * @var string $state confirm | done | invalid | busy
 */

use function HR\Platform\icon;
use function HR\Platform\Streaks\dashboard_url;

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="hrp hrs hrs-unsub">
    <div class="hrp-card hrp-card--accent hrs-unsub__card">
        <p class="hrp-eyebrow">Streak reminders</p>
        <?php if ( 'confirm' === $state ) : ?>
            <h1 class="hrs-unsub__title">Turn off reminder emails?</h1>
            <p class="hrs-meta">You will stop getting the 7 pm email on days your reading streak is about to end. Your streak itself is not affected.</p>
            <form method="post" action="">
                <button type="submit" class="hrp-btn">Turn off reminders</button>
            </form>
        <?php elseif ( 'done' === $state ) : ?>
            <h1 class="hrs-unsub__title"><?php echo icon( 'check' ); // phpcs:ignore -- static SVG ?> Reminders are off</h1>
            <p class="hrs-meta">We will not email you about your streak again. You can turn reminders back on from your reading dashboard at any time.</p>
            <p><a class="hrp-btn hrp-btn--ghost" href="<?php echo esc_url( dashboard_url() ); ?>">Open my reading streak</a></p>
        <?php elseif ( 'busy' === $state ) : ?>
            <h1 class="hrs-unsub__title">Please try again shortly</h1>
            <p class="hrs-meta">Too many requests came from your connection. Wait a few minutes and open the link again.</p>
        <?php else : ?>
            <h1 class="hrs-unsub__title">This link does not work</h1>
            <p class="hrs-meta">It may have been copied incompletely. You can turn reminders off on your reading dashboard after signing in.</p>
            <p><a class="hrp-btn hrp-btn--ghost" href="<?php echo esc_url( dashboard_url() ); ?>">Open my reading streak</a></p>
        <?php endif; ?>
    </div>
</div>
