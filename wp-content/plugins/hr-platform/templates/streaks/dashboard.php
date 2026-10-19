<?php
/**
 * Reading streak dashboard (/dashboard/). Theme override: headlinerec-child/hr/streaks/dashboard.php
 * Variables come from HR\Platform\Streaks\dashboard_data().
 *
 * @var WP_User $user  @var array $view  @var string $today  @var array $week  @var array $heat
 * @var int $heat_reads  @var int $heat_days  @var int $heat_goals  @var int $xp_pct  @var array $badges  @var int $total
 * @var array $reads  @var WP_Post[] $suggestions  @var bool $goal_locked  @var bool $reminders  @var bool $verified
 * @var string $saved  @var string $nonce
 */

use function HR\Platform\icon;
use function HR\Platform\Streaks\flame_svg;
use function HR\Platform\Streaks\kind_of;
use function HR\Platform\Streaks\minutes;
use function HR\Platform\Streaks\dashboard_url;
use const HR\Platform\Streaks\BADGES;
use const HR\Platform\Streaks\GOALS;
use const HR\Platform\Streaks\MAX_FREEZES;

if ( ! defined( 'ABSPATH' ) ) exit;

$hrs_cur   = (int) $view['current'];
$hrs_goal  = (int) $view['goal'];
$hrs_done  = min( (int) $view['today'], $hrs_goal );
$hrs_left  = $hrs_goal - $hrs_done;
$hrs_c     = 2 * M_PI * 52;
$hrs_date  = static fn( string $d, string $f = 'j M Y' ) => wp_date( $f, strtotime( $d . ' 12:00:00 UTC' ), new DateTimeZone( 'UTC' ) );
$hrs_plural = static fn( int $n, string $one, string $many ) => sprintf( 1 === $n ? $one : $many, number_format_i18n( $n ) );

if ( $view['met_today'] ) {
    $hrs_banner = array( 'done', 'check', sprintf( "Today's goal is met. Your %s is safe — come back tomorrow to keep it going.", number_format_i18n( $hrs_cur ) . '-day streak' ) );
} elseif ( $view['at_risk'] ) {
    $hrs_banner = array( 'risk', 'clock', sprintf( 'Read %s before midnight to keep your %s-day streak.', $hrs_plural( $hrs_left, '%s more article', '%s more articles' ), number_format_i18n( $hrs_cur ) ) );
} elseif ( $view['best'] > 0 ) {
    $hrs_banner = array( 'start', 'flame', sprintf( 'Your last streak ended. Read %s today to start a new one.', $hrs_plural( $hrs_left, '%s article', '%s articles' ) ) );
} else {
    $hrs_banner = array( 'start', 'flame', sprintf( 'Read %s today to start your first streak.', $hrs_plural( $hrs_left, '%s article', '%s articles' ) ) );
}
$hrs_icons = array( 'first' => 'sparkles', 'streak' => 'flame', 'reads' => 'book-open', 'opeds' => 'pen', 'early' => 'clock', 'weekend' => 'calendar', 'freeze' => 'snowflake' );
$hrs_saved = array(
    'goal'          => array( 'ok', 'Daily goal updated.' ),
    'goal-locked'   => array( 'warn', 'You have already changed your goal today. You can change it again tomorrow.' ),
    'reminders-on'  => array( 'ok', 'Reminders are on. We will email you at 7 pm on days your streak is about to end.' ),
    'reminders-off' => array( 'ok', 'Reminders are off.' ),
    'expired'       => array( 'error', 'That form expired. Please try again.' ),
    'busy'          => array( 'error', 'Too many changes in a short time. Please try again later.' ),
    'invalid'       => array( 'error', 'Please choose one of the options.' ),
);
?>
<div class="hrp hrs">
    <section class="hrs-hero" aria-labelledby="hrs-title">
        <div class="hrs-hero__grid">
            <div class="hrs-hero__main">
                <p class="hrp-eyebrow">Reading streak</p>
                <h1 class="hrs-hero__title" id="hrs-title">
                    <?php echo flame_svg( $hrs_cur > 0, 'hrs-hero__flame' ); // phpcs:ignore -- static SVG ?>
                    <span class="hrs-hero__num"><?php echo esc_html( number_format_i18n( $hrs_cur ) ); ?></span>
                    <span class="hrs-hero__unit">day streak</span>
                </h1>
                <dl class="hrs-stats">
                    <div><dt>Best</dt><dd><?php echo esc_html( $hrs_plural( (int) $view['best'], '%s day', '%s days' ) ); ?></dd></div>
                    <div>
                        <dt>Freezes</dt>
                        <dd class="hrs-freezes">
                            <span class="hrp-sr"><?php echo esc_html( sprintf( '%d of %d', $view['freezes'], MAX_FREEZES ) ); ?></span>
                            <?php for ( $hrs_i = 0; $hrs_i < MAX_FREEZES; $hrs_i++ ) : ?>
                                <?php echo icon( 'snowflake', '', $hrs_i < $view['freezes'] ? 'is-on' : '' ); // phpcs:ignore -- static SVG ?>
                            <?php endfor; ?>
                        </dd>
                    </div>
                    <div><dt>Articles read</dt><dd><?php echo esc_html( number_format_i18n( $total ) ); ?></dd></div>
                    <div><dt>Level</dt><dd><?php echo esc_html( number_format_i18n( $view['level'] ) ); ?></dd></div>
                </dl>
            </div>
            <div class="hrs-ring<?php echo $view['met_today'] ? ' is-met' : ''; ?>" role="img" aria-label="<?php echo esc_attr( sprintf( '%d of %d articles read today', $hrs_done, $hrs_goal ) ); ?>">
                <svg viewBox="0 0 120 120" aria-hidden="true" focusable="false">
                    <circle class="hrs-ring__track" cx="60" cy="60" r="52"/>
                    <circle class="hrs-ring__bar" cx="60" cy="60" r="52" style="--hrs-c:<?php echo esc_attr( round( $hrs_c, 2 ) ); ?>px;--hrs-off:<?php echo esc_attr( round( $hrs_c * ( 1 - $hrs_done / $hrs_goal ), 2 ) ); ?>px"/>
                </svg>
                <p class="hrs-ring__label" aria-hidden="true"><strong><?php echo esc_html( $hrs_done ); ?></strong><span>of <?php echo esc_html( $hrs_goal ); ?> today</span></p>
            </div>
        </div>
        <p class="hrs-banner hrs-banner--<?php echo esc_attr( $hrs_banner[0] ); ?>">
            <?php echo icon( $hrs_banner[1] ); // phpcs:ignore -- static SVG ?>
            <span><?php echo esc_html( $hrs_banner[2] ); ?></span>
        </p>
    </section>

    <section class="hrs-section hrs-week" aria-labelledby="hrs-week-title">
        <h2 class="hrs-h2" id="hrs-week-title">This week</h2>
        <ol class="hrs-week__days" role="list">
            <?php foreach ( $week as $hrs_d ) :
                $hrs_label = array( 'met' => 'goal met', 'freeze' => 'streak freeze used', 'partial' => sprintf( '%d read', $hrs_d['count'] ), 'none' => 'no reading', 'future' => 'still to come' )[ $hrs_d['status'] ];
                if ( $hrs_d['today'] && 'met' !== $hrs_d['status'] ) {
                    $hrs_label = sprintf( 'today, %d of %d', $hrs_done, $hrs_goal );
                }
                ?>
                <li class="hrs-day is-<?php echo esc_attr( $hrs_d['status'] ); ?><?php echo $hrs_d['today'] ? ' is-today' : ''; ?>">
                    <span class="hrs-day__name" aria-hidden="true"><?php echo esc_html( $hrs_date( $hrs_d['date'], 'D' ) ); ?></span>
                    <span class="hrs-day__mark" aria-hidden="true">
                        <?php if ( 'met' === $hrs_d['status'] ) : ?>
                            <?php echo icon( 'flame' ); // phpcs:ignore -- static SVG ?>
                        <?php elseif ( 'freeze' === $hrs_d['status'] ) : ?>
                            <?php echo icon( 'snowflake' ); // phpcs:ignore -- static SVG ?>
                        <?php else : ?>
                            <?php echo esc_html( $hrs_date( $hrs_d['date'], 'j' ) ); ?>
                        <?php endif; ?>
                    </span>
                    <span class="hrp-sr"><?php echo esc_html( $hrs_date( $hrs_d['date'], 'l j F' ) . ': ' . $hrs_label ); ?></span>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>

    <div class="hrs-grid">
        <div class="hrs-pane hrs-pane--top">
            <section class="hrs-section hrs-level" aria-labelledby="hrs-level-title">
                <div class="hrs-head">
                    <h2 class="hrs-h2" id="hrs-level-title">Level <?php echo esc_html( number_format_i18n( $view['level'] ) ); ?></h2>
                    <p class="hrs-meta hrp-mono"><?php echo esc_html( number_format_i18n( $view['xp'] ) ); ?> XP</p>
                </div>
                <div class="hrs-bar" role="progressbar" aria-label="Progress to the next level" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo (int) $xp_pct; ?>">
                    <span style="--hrs-pct:<?php echo (int) $xp_pct; ?>%"></span>
                </div>
                <p class="hrs-meta"><?php echo esc_html( sprintf( '%s XP to level %s. Each article is worth 10 XP, an op-ed 15, and meeting your daily goal 20 more.', number_format_i18n( $view['next_level_xp'] - $view['xp'] ), number_format_i18n( $view['level'] + 1 ) ) ); ?></p>
            </section>

            <section class="hrs-section" aria-labelledby="hrs-next-title">
                <h2 class="hrs-h2" id="hrs-next-title">Pick up where you left off</h2>
                <?php if ( $suggestions ) : ?>
                    <ul class="hrs-next" role="list">
                        <?php foreach ( $suggestions as $hrs_post ) :
                            $hrs_oped = 'oped' === kind_of( $hrs_post );
                            ?>
                            <li class="hrs-next__item<?php echo $hrs_oped ? ' is-oped' : ''; ?>">
                                <span class="hrs-next__kind"><?php echo esc_html( $hrs_oped ? 'Op-ed · 15 XP' : 'Article · 10 XP' ); ?></span>
                                <a class="hrs-next__title" href="<?php echo esc_url( get_permalink( $hrs_post ) ); ?>"><?php echo esc_html( html_entity_decode( get_the_title( $hrs_post ), ENT_QUOTES, 'UTF-8' ) ); ?></a>
                                <span class="hrs-meta"><?php echo esc_html( minutes( $hrs_post ) . ' min read' ); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <p class="hrs-empty">You have read everything we have published. Impressive — check back soon.</p>
                <?php endif; ?>
            </section>

        </div>

        <div class="hrs-pane hrs-pane--main">
            <section class="hrs-section" aria-labelledby="hrs-heat-title">
                <div class="hrs-head">
                    <h2 class="hrs-h2" id="hrs-heat-title">Last 16 weeks</h2>
                    <p class="hrs-meta"><?php echo esc_html( $hrs_plural( $heat_goals, '%s goal day', '%s goal days' ) ); ?></p>
                </div>
                <div class="hrs-heat-wrap">
                    <div class="hrs-heat" role="img" aria-label="<?php echo esc_attr( sprintf( 'Reading activity, last 16 weeks: %s on %s; daily goal met on %s.', $hrs_plural( $heat_reads, '%s article', '%s articles' ), $hrs_plural( $heat_days, '%s day', '%s days' ), $hrs_plural( $heat_goals, '%s day', '%s days' ) ) ); ?>">
                        <span class="hrs-heat__wd" style="grid-row:2" aria-hidden="true">Mon</span>
                        <span class="hrs-heat__wd" style="grid-row:4" aria-hidden="true">Wed</span>
                        <span class="hrs-heat__wd" style="grid-row:6" aria-hidden="true">Fri</span>
                        <?php $hrs_month = ''; ?>
                        <?php foreach ( array_chunk( $heat, 7 ) as $hrs_w => $hrs_cells ) :
                            $hrs_m = $hrs_date( $hrs_cells[0]['date'], 'M' );
                            ?>
                            <span class="hrs-heat__mo" style="grid-column:<?php echo (int) $hrs_w + 2; ?>" aria-hidden="true"><?php echo $hrs_m !== $hrs_month ? esc_html( $hrs_m ) : ''; ?></span>
                            <?php $hrs_month = $hrs_m; ?>
                            <?php foreach ( $hrs_cells as $hrs_r => $hrs_cell ) : ?>
                                <span class="hrs-cell l<?php echo (int) $hrs_cell['level']; ?> is-<?php echo esc_attr( $hrs_cell['status'] ); ?>" style="grid-column:<?php echo (int) $hrs_w + 2; ?>;grid-row:<?php echo (int) $hrs_r + 2; ?>" title="<?php echo esc_attr( 'future' === $hrs_cell['status'] ? '' : $hrs_date( $hrs_cell['date'], 'D j M' ) . ': ' . ( 'freeze' === $hrs_cell['status'] ? 'streak freeze' : $hrs_plural( $hrs_cell['count'], '%s article', '%s articles' ) ) ); ?>"></span>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <p class="hrs-legend" aria-hidden="true">
                    <span>Less</span><span class="hrs-cell l0"></span><span class="hrs-cell l1"></span><span class="hrs-cell l2"></span><span class="hrs-cell l3"></span><span class="hrs-cell l4"></span><span>Goal met</span>
                    <span class="hrs-cell is-freeze"></span><span>Freeze</span>
                </p>
                <p class="hrs-meta"><?php echo esc_html( sprintf( '%s on %s in the last 16 weeks.', $hrs_plural( $heat_reads, '%s article', '%s articles' ), $hrs_plural( $heat_days, '%s day', '%s days' ) ) ); ?></p>
            </section>

            <section class="hrs-section" aria-labelledby="hrs-badges-title">
                <div class="hrs-head">
                    <h2 class="hrs-h2" id="hrs-badges-title">Badges</h2>
                    <p class="hrs-meta"><?php echo esc_html( sprintf( '%d of %d earned', count( array_intersect_key( $badges, BADGES ) ), count( BADGES ) ) ); ?></p>
                </div>
                <ul class="hrs-badges" role="list">
                    <?php foreach ( BADGES as $hrs_slug => [ $hrs_title, $hrs_how ] ) :
                        $hrs_on = isset( $badges[ $hrs_slug ] );
                        ?>
                        <li class="hrs-badge<?php echo $hrs_on ? ' is-earned' : ''; ?>">
                            <span class="hrs-badge__icon" aria-hidden="true"><?php echo icon( $hrs_icons[ strtok( $hrs_slug, '-' ) ] ?? 'award' ); // phpcs:ignore -- static SVG ?></span>
                            <span class="hrs-badge__title"><?php echo esc_html( $hrs_title ); ?></span>
                            <span class="hrs-badge__text">
                                <?php if ( $hrs_on ) : ?>
                                    <?php echo esc_html( 'Earned ' . $hrs_date( (string) $badges[ $hrs_slug ] ) ); ?>
                                <?php else : ?>
                                    <span class="hrp-sr">Locked. </span><?php echo esc_html( $hrs_how ); ?>
                                <?php endif; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>

            <section class="hrs-section" aria-labelledby="hrs-reads-title">
                <h2 class="hrs-h2" id="hrs-reads-title">Recently read</h2>
                <?php if ( $reads ) : ?>
                    <ol class="hrs-reads" role="list">
                        <?php foreach ( $reads as $hrs_read ) : ?>
                            <li>
                                <?php if ( 'publish' === get_post_status( (int) $hrs_read['post_id'] ) ) : ?>
                                    <a href="<?php echo esc_url( get_permalink( (int) $hrs_read['post_id'] ) ); ?>"><?php echo esc_html( $hrs_read['title'] ); ?></a>
                                <?php else : ?>
                                    <span class="hrs-meta">An article that is no longer published</span>
                                <?php endif; ?>
                                <span class="hrs-reads__meta">
                                    <?php if ( 'oped' === $hrs_read['kind'] ) : ?><span class="hrp-badge hrp-badge--red">Op-ed</span><?php endif; ?>
                                    <time datetime="<?php echo esc_attr( $hrs_read['read_on'] ); ?>"><?php echo esc_html( $hrs_date( $hrs_read['read_on'] ) ); ?></time>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php else : ?>
                    <p class="hrs-empty">Articles you finish will appear here. Read to the end of a story and it counts towards your streak.</p>
                <?php endif; ?>
            </section>
        </div>

        <div class="hrs-pane hrs-pane--bottom">
            <section class="hrs-section hrs-prefs" id="hrs-prefs" aria-labelledby="hrs-prefs-title">
                <h2 class="hrs-h2" id="hrs-prefs-title">Your goal and reminders</h2>
                <?php if ( isset( $hrs_saved[ $saved ] ) ) : ?>
                    <div class="hrp-alert hrp-alert--<?php echo esc_attr( $hrs_saved[ $saved ][0] ); ?> hrs-flash" role="<?php echo 'error' === $hrs_saved[ $saved ][0] ? 'alert' : 'status'; ?>" tabindex="-1">
                        <p><?php echo esc_html( $hrs_saved[ $saved ][1] ); ?></p>
                    </div>
                <?php endif; ?>
                <form class="hrs-form" method="post" action="<?php echo esc_url( dashboard_url() ); ?>#hrs-prefs">
                    <?php wp_nonce_field( $nonce, '_hrsnonce', false ); ?>
                    <input type="hidden" name="hr_action" value="goal">
                    <fieldset class="hrs-goals"<?php echo $goal_locked ? ' disabled aria-describedby="hrs-goal-note"' : ''; ?>>
                        <legend class="hrs-h3">Daily goal</legend>
                        <?php foreach ( GOALS as $hrs_n => $hrs_name ) : ?>
                            <label class="hrs-goal">
                                <input type="radio" name="goal" value="<?php echo (int) $hrs_n; ?>"<?php checked( $hrs_n, $hrs_goal ); ?>>
                                <span><strong><?php echo esc_html( $hrs_name ); ?></strong><span><?php echo esc_html( $hrs_plural( $hrs_n, '%s article a day', '%s articles a day' ) ); ?></span></span>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                    <?php if ( $goal_locked ) : ?>
                        <p class="hrs-meta" id="hrs-goal-note">You changed your goal today. You can change it again tomorrow.</p>
                    <?php else : ?>
                        <p class="hrs-meta">You can change your goal once a day.</p>
                        <button type="submit" class="hrp-btn">Save goal</button>
                    <?php endif; ?>
                </form>
                <form class="hrs-form hrs-form--rem" method="post" action="<?php echo esc_url( dashboard_url() ); ?>#hrs-prefs">
                    <?php wp_nonce_field( $nonce, '_hrsnonce', false ); ?>
                    <input type="hidden" name="hr_action" value="reminders">
                    <label class="hrp-check">
                        <input type="checkbox" name="reminders" value="1"<?php checked( $reminders ); ?> aria-describedby="hrs-rem-hint">
                        <span>Email me at 7 pm when my streak is about to end</span>
                    </label>
                    <p class="hrs-meta" id="hrs-rem-hint">At most one email a day, only when you have not met your goal yet. <?php echo $verified ? '' : esc_html( 'Confirm your email address first; we only email confirmed addresses.' ); ?></p>
                    <button type="submit" class="hrp-btn hrp-btn--ghost">Save reminder setting</button>
                </form>
            </section>
        </div>
    </div>
</div>
