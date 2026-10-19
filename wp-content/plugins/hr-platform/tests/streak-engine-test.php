<?php
/**
 * Streak engine tests. Run: php wp-content/plugins/hr-platform/tests/streak-engine-test.php
 */

if ( PHP_SAPI !== 'cli' ) exit;
define( 'ABSPATH', __DIR__ );
require dirname( __DIR__ ) . '/includes/streaks/engine.php';

use function HR\Platform\Streaks\{apply, compute, view, run_days, import_legacy, local_date, local_hour, level, shift_date};
use const HR\Platform\Streaks\BADGES;

$fails = 0;
function check( string $name, $got, $want ): void {
    global $fails;
    if ( $got === $want ) {
        echo "ok   {$name}\n";
        return;
    }
    $fails++;
    echo "FAIL {$name}: got " . var_export( $got, true ) . ', want ' . var_export( $want, true ) . "\n";
}

/** Meet the goal on each listed day (goal 3, three reads). */
function run( array $days, array $s = array(), int $goal = 3 ): array {
    $events = array();
    foreach ( $days as $d ) {
        for ( $i = 1; $i <= $goal; $i++ ) {
            [ $s, $events ] = apply( $s, $d, $i, $goal, array( 'kind' => 'article', 'hour' => 12, 'total' => $i ) );
        }
    }
    return array( $s, $events );
}

// Day boundaries: consecutive days extend, same day does not, a gap resets.
[ $s ] = run( array( '2026-10-01', '2026-10-02', '2026-10-03' ) );
check( 'three consecutive days', $s['current'], 3 );
check( 'best follows', $s['best'], 3 );
[ $s2, $e ] = compute( $s, '2026-10-03', 4, 3 );
check( 'fourth read same day: no change', $s2['current'], 3 );
check( 'fourth read same day: no event', $e['goal_met'], false );
[ $s3 ] = run( array( '2026-10-06' ), $s );
check( 'gap of two days without freezes resets', $s3['current'], 1 );
check( 'best kept after reset', $s3['best'], 3 );
[ $s4, $e4 ] = compute( $s, '2026-10-04', 2, 3 );
check( 'below goal: nothing', array( $s4['current'], $e4['goal_met'] ), array( 3, false ) );
check( 'past date is ignored', compute( $s, '2026-10-02', 3, 3 )[1]['goal_met'], false );

// View: lazy break detection and at-risk.
$v = view( $s, '2026-10-04', 1, 3 );
check( 'next day: at risk, streak shown', array( $v['current'], $v['at_risk'], $v['met_today'] ), array( 3, true, false ) );
$v = view( $s, '2026-10-05', 0, 3 );
check( 'missed a day, no freezes: shown 0', array( $v['current'], $v['at_risk'] ), array( 0, false ) );
check( 'shown best stays', $v['best'], 3 );
check( 'met today', view( $s, '2026-10-03', 3, 3 )['met_today'], true );

// Freezes: earned every 7th day (max 2), spent on missed days.
$days = array();
for ( $i = 0; $i < 14; $i++ ) {
    $days[] = shift_date( '2026-09-01', $i );
}
[ $s, $e ] = run( $days );
check( '14 days: current', $s['current'], 14 );
check( '14 days: two freezes', $s['freezes'], 2 );
check( '14 days: freeze event on 14th', $e['freeze_earned'], true );
check( '14 days: milestone', $e['milestone'], 14 );
check( 'covered by freezes: at risk, still shown', array( view( $s, '2026-09-17', 0, 3 )['current'], view( $s, '2026-09-17', 0, 3 )['at_risk'] ), array( 14, true ) );
[ $f, $fe ] = run( array( '2026-09-17' ), $s );
check( 'two missed days spend two freezes', array( $f['current'], $f['freezes'], $fe['freezes_used'] ), array( 15, 0, 2 ) );
check( 'freeze dates recorded', $f['freeze_used'], array( '2026-09-15', '2026-09-16' ) );
check( 'freeze-saver badge', isset( $f['badges']['freeze-saver'] ), true );
check( 'run_days marks freezes', array_slice( run_days( $f ), 0, 4 ), array( '2026-09-17' => 'met', '2026-09-16' => 'freeze', '2026-09-15' => 'freeze', '2026-09-14' => 'met' ) );
check( 'run_days length', count( run_days( $f ) ), 17 );
[ $g ] = run( array( '2026-09-18' ), $s );
check( 'three missed days with two freezes resets', array( $g['current'], $g['freezes'] ), array( 1, 2 ) );
$cap = $s;
for ( $i = 14; $i < 21; $i++ ) {
    [ $cap ] = run( array( shift_date( '2026-09-01', $i ) ), $cap );
}
check( '21 days: freezes capped at 2', $cap['freezes'], 2 );

// XP and levels: 10 per article, 15 per op-ed, +20 goal, +5 per day after 7.
[ $x, $xe ] = apply( array(), '2026-10-01', 1, 1, array( 'kind' => 'oped', 'hour' => 9, 'total' => 1, 'opeds' => 1 ) );
check( 'op-ed + goal xp', array( $x['xp'], $xe['xp'] ), array( 35, 35 ) );
check( 'first-read badge event', $xe['badges'], array( 'first-read' ) );
check( 'level 1 at 39', level( 39 ), 1 );
check( 'level 2 at 40', level( 40 ), 2 );
check( 'level 3 at 160', level( 160 ), 3 );
$eight = array();
for ( $i = 0; $i < 8; $i++ ) {
    $eight[] = shift_date( '2026-01-01', $i );
}
[ $l, $le ] = run( $eight, array(), 1 );
check( 'day 8 xp = 10 + 20 + 5', $le['xp'], 35 );
check( '8 days xp total', $l['xp'], 8 * 30 + 5 );

// Goal changes: lowering the goal below today's count meets it now; raising after meeting keeps the day.
[ $c, $ce ] = compute( array(), '2026-10-01', 2, 3 );
check( '2 of 3: not met', $ce['goal_met'], false );
[ $c, $ce ] = compute( $c, '2026-10-01', 2, 1 );
check( 'goal lowered to 1: met now', array( $ce['goal_met'], $c['current'] ), array( true, 1 ) );
check( 'goal raised after meeting: still met', view( $c, '2026-10-01', 2, 10 )['met_today'], true );
[ $c ] = compute( $c, '2026-10-02', 5, 10 );
check( 'intense goal not reached next day', $c['current'], 1 );
[ $c ] = compute( $c, '2026-10-02', 10, 10 );
check( 'intense goal reached', $c['current'], 2 );

// Year boundaries and leap day.
[ $y ] = run( array( '2026-12-30', '2026-12-31', '2027-01-01' ) );
check( 'across new year', $y['current'], 3 );
[ $y ] = run( array( '2028-02-28', '2028-02-29', '2028-03-01' ) );
check( 'across leap day', $y['current'], 3 );
check( 'Dec 31 -> Jan 1 is at risk, not broken', view( run( array( '2026-12-31' ) )[0], '2027-01-01', 0, 3 )['at_risk'], true );

// Timezone: Karachi is UTC+5, so 19:30 UTC is already tomorrow there.
$pk = new DateTimeZone( 'Asia/Karachi' );
$ts = gmmktime( 19, 30, 0, 10, 18, 2026 );
check( 'Karachi date after 19:00 UTC', local_date( $ts, $pk ), '2026-10-19' );
check( 'UTC date same instant', local_date( $ts, new DateTimeZone( 'UTC' ) ), '2026-10-18' );
check( 'Karachi hour', local_hour( $ts, $pk ), 0 );
check( 'Karachi 23:59 is still the same day', local_date( gmmktime( 18, 59, 59, 10, 18, 2026 ), $pk ), '2026-10-18' );
[ , $eb ] = apply( array(), '2026-10-19', 1, 3, array( 'kind' => 'article', 'hour' => local_hour( gmmktime( 1, 30, 0, 10, 19, 2026 ), $pk ), 'total' => 1 ) );
check( 'early bird at 06:30 Karachi', in_array( 'early-bird', $eb['badges'], true ), true );
[ , $eb ] = apply( array(), '2026-10-19', 1, 3, array( 'kind' => 'article', 'hour' => 8, 'total' => 1 ) );
check( 'no early bird at 08:00', in_array( 'early-bird', $eb['badges'], true ), false );

// Weekend reader: Saturday 2026-10-17 and Sunday 2026-10-18.
[ $w, $we ] = run( array( '2026-10-17', '2026-10-18' ) );
check( 'weekend reader', isset( $w['badges']['weekend-reader'] ), true );
[ $w ] = run( array( '2026-10-18', '2026-10-19' ) );
check( 'Sunday + Monday is not a weekend', isset( $w['badges']['weekend-reader'] ), false );

// Double counting: replaying the same count changes nothing and earns nothing.
[ $d1 ] = run( array( '2026-10-01' ) );
[ $d2, $de ] = apply( $d1, '2026-10-01', 3, 3, array( 'total' => 3 ) );
check( 'replay: same state', $d2, $d1 );
check( 'replay: no xp', $de['xp'], 0 );
check( 'badges only once', $de['badges'], array() );

// Streak badges and every badge slug is known.
[ $b ] = run( array( '2026-10-01', '2026-10-02', '2026-10-03' ) );
check( 'streak-3 badge', isset( $b['badges']['streak-3'] ), true );
check( 'badge count', count( BADGES ), 16 );

// Legacy import (shapes found in the live database).
[ $ls, $lr ] = import_legacy( array(
    '_hr_reading_history'  => array( 5257, 5248, 4685, 5359 ),
    '_hr_last_read_date'   => '2026-09-27',
    '_hr_today_read_posts' => array( 5359 ),
), '2026-10-19' );
check( 'legacy reads', $lr, array( array( 5359, '2026-09-27' ), array( 5257, '2026-09-26' ), array( 5248, '2026-09-26' ), array( 4685, '2026-09-26' ) ) );
check( 'legacy without streak', array( $ls['current'], $ls['last_goal_date'] ), array( 0, '' ) );
[ $ls ] = import_legacy( array(
    '_hr_streak_count'   => '5',
    '_hr_longest_streak' => '9',
    '_hr_streak_freezes' => '7',
    '_hr_last_read_date' => '2026-10-18',
    '_hr_earned_badges'  => array( 'analyst_initiate', 'market_observer', 'bogus' ),
), '2026-10-19' );
check( 'legacy streak', array( $ls['current'], $ls['best'], $ls['freezes'], $ls['last_goal_date'] ), array( 5, 9, 2, '2026-10-18' ) );
check( 'legacy badges mapped', array_keys( $ls['badges'] ), array( 'streak-3', 'streak-7' ) );
check( 'legacy streak alive today', view( $ls, '2026-10-19', 0, 3 )['current'], 5 );
[ $ls ] = import_legacy( array( '_hr_last_read_date' => 'garbage', '_hr_reading_history' => array( '2026-10-01' => 2 ) ), '2026-10-19' );
check( 'new-format history and bad date are ignored', $ls, \HR\Platform\Streaks\blank_state() );

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
