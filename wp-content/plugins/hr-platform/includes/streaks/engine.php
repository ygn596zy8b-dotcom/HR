<?php
/**
 * Streaks: the pure streak engine. No WordPress calls, so tests/streak-engine-test.php runs it with plain PHP.
 *
 * State: current, best, freezes, last_goal_date ('Y-m-d' or ''), freeze_used (dates), xp, badges (slug => date).
 * Dates are local calendar days (site timezone); the caller converts timestamps with local_date().
 */

namespace HR\Platform\Streaks;

if ( ! defined( 'ABSPATH' ) ) exit;

const GOALS        = array( 1 => 'Casual', 3 => 'Regular', 5 => 'Serious', 10 => 'Intense' );
const DEFAULT_GOAL = 3;
const MAX_FREEZES  = 2;
const MILESTONES   = array( 3, 7, 14, 30, 50, 100, 365 );
const XP_ARTICLE   = 10;
const XP_OPED      = 15;
const XP_GOAL      = 20;
const XP_LONG      = 5;
const LONG_STREAK  = 7; // goal days earn XP_LONG on top of XP_GOAL once the streak is longer than this
const FREEZE_EVERY = 7; // a freeze is earned each time the streak reaches a multiple of this (up to MAX_FREEZES held)

/** slug => [title, how to earn]. Order = display order and the priority of the badge a celebration names. */
const BADGES = array(
    'first-read'     => array( 'First read', 'Finish your first article.' ),
    'streak-3'       => array( 'Three in a row', 'Meet your daily goal 3 days in a row.' ),
    'streak-7'       => array( 'One week', 'Keep a 7-day streak.' ),
    'streak-14'      => array( 'Fortnight', 'Keep a 14-day streak.' ),
    'streak-30'      => array( 'Month of news', 'Keep a 30-day streak.' ),
    'streak-50'      => array( 'Fifty days', 'Keep a 50-day streak.' ),
    'streak-100'     => array( 'Century', 'Keep a 100-day streak.' ),
    'streak-365'     => array( 'Year of reading', 'Keep a 365-day streak.' ),
    'reads-10'       => array( 'Ten articles', 'Finish 10 articles.' ),
    'reads-50'       => array( 'Fifty articles', 'Finish 50 articles.' ),
    'reads-100'      => array( 'Hundred articles', 'Finish 100 articles.' ),
    'reads-500'      => array( 'Five hundred', 'Finish 500 articles.' ),
    'opeds-10'       => array( 'Opinion reader', 'Finish 10 op-eds.' ),
    'early-bird'     => array( 'Early bird', 'Finish an article between 5 and 8 in the morning.' ),
    'weekend-reader' => array( 'Weekend reader', 'Meet your goal on a Saturday and the Sunday after it.' ),
    'freeze-saver'   => array( 'Saved by a freeze', 'Have a streak freeze keep your streak alive.' ),
);

// Badges from the old dashboard template.
const LEGACY_BADGES = array( 'analyst_initiate' => 'streak-3', 'market_observer' => 'streak-7', 'macro_specialist' => 'streak-30' );

function blank_state(): array {
    return array( 'current' => 0, 'best' => 0, 'freezes' => 0, 'last_goal_date' => '', 'freeze_used' => array(), 'xp' => 0, 'badges' => array() );
}

function is_date( $value ): bool {
    return is_string( $value ) && 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) && checkdate( (int) substr( $value, 5, 2 ), (int) substr( $value, 8, 2 ), (int) substr( $value, 0, 4 ) );
}

/** Calendar days from $a to $b (negative when $b is earlier). */
function days_between( string $a, string $b ): int {
    return (int) round( ( strtotime( $b . ' 00:00:00 UTC' ) - strtotime( $a . ' 00:00:00 UTC' ) ) / 86400 );
}

function shift_date( string $date, int $days ): string {
    return gmdate( 'Y-m-d', strtotime( $date . ' 00:00:00 UTC' ) + $days * 86400 );
}

function local_date( int $ts, \DateTimeZone $tz ): string {
    return ( new \DateTimeImmutable( '@' . $ts ) )->setTimezone( $tz )->format( 'Y-m-d' );
}

function local_hour( int $ts, \DateTimeZone $tz ): int {
    return (int) ( new \DateTimeImmutable( '@' . $ts ) )->setTimezone( $tz )->format( 'G' );
}

function level( int $xp ): int {
    return (int) floor( sqrt( max( 0, $xp ) / 40 ) ) + 1;
}

/** XP at which $level starts (level 1 = 0, 2 = 40, 3 = 160 …). */
function level_floor( int $level ): int {
    return 40 * ( $level - 1 ) ** 2;
}

/**
 * Apply today's read count to the streak. Only the moment the count first reaches the goal changes anything.
 * Returns array( new state, events ).
 */
function compute( array $s, string $today, int $today_count, int $goal ): array {
    $s     += blank_state();
    $events = array( 'goal_met' => false, 'milestone' => null, 'new_best' => false, 'freeze_earned' => false, 'freezes_used' => 0, 'weekend' => false, 'xp' => 0 );
    $last   = (string) $s['last_goal_date'];
    if ( $today_count < $goal || ( '' !== $last && $last >= $today ) ) {
        return array( $s, $events );
    }
    $gap = '' === $last ? PHP_INT_MAX : days_between( $last, $today );
    if ( 1 === $gap ) {
        $s['current']++;
    } elseif ( $gap - 1 <= $s['freezes'] ) {
        $missed          = $gap - 1;
        $s['freezes']   -= $missed;
        for ( $i = 1; $i <= $missed; $i++ ) {
            $s['freeze_used'][] = shift_date( $last, $i );
        }
        $s['current']++;
        $events['freezes_used'] = $missed;
    } else {
        $s['current'] = 1;
    }
    $s['last_goal_date'] = $today;
    $s['freeze_used']    = array_values( array_filter( $s['freeze_used'], static fn( $d ) => is_date( $d ) && days_between( $d, $today ) <= 120 ) );
    $events['goal_met']  = true;
    if ( $s['current'] > $s['best'] ) {
        $events['new_best'] = $s['best'] > 0;
        $s['best']          = $s['current'];
    }
    if ( 0 === $s['current'] % FREEZE_EVERY && $s['freezes'] < MAX_FREEZES ) {
        $s['freezes']++;
        $events['freeze_earned'] = true;
    }
    if ( in_array( $s['current'], MILESTONES, true ) ) {
        $events['milestone'] = $s['current'];
    }
    $events['weekend'] = '7' === gmdate( 'N', strtotime( $today . ' 00:00:00 UTC' ) ) && $last === shift_date( $today, -1 );
    $events['xp']      = XP_GOAL + ( $s['current'] > LONG_STREAK ? XP_LONG : 0 );
    $s['xp']          += $events['xp'];
    return array( $s, $events );
}

/**
 * One state transition: optionally a counted read ($ctx['kind'] 'article'|'oped', $ctx['hour'] local hour),
 * then the streak rules, then badges. $ctx['total'] / $ctx['opeds'] are counts from the reads table.
 * Events gain 'badges' (newly earned slugs) and 'xp' (everything gained).
 */
function apply( array $s, string $today, int $today_count, int $goal, array $ctx ): array {
    $s       += blank_state();
    $read_xp  = 0;
    if ( ! empty( $ctx['kind'] ) ) {
        $read_xp  = 'oped' === $ctx['kind'] ? XP_OPED : XP_ARTICLE;
        $s['xp'] += $read_xp;
    }
    [ $s, $events ] = compute( $s, $today, $today_count, $goal );
    $events['xp']  += $read_xp;

    $total = (int) ( $ctx['total'] ?? 0 );
    $earn  = array(
        'first-read'     => $total >= 1,
        'reads-10'       => $total >= 10,
        'reads-50'       => $total >= 50,
        'reads-100'      => $total >= 100,
        'reads-500'      => $total >= 500,
        'opeds-10'       => (int) ( $ctx['opeds'] ?? 0 ) >= 10,
        'early-bird'     => ! empty( $ctx['kind'] ) && isset( $ctx['hour'] ) && $ctx['hour'] >= 5 && $ctx['hour'] < 8,
        'weekend-reader' => $events['weekend'],
        'freeze-saver'   => $events['freezes_used'] > 0,
    );
    foreach ( MILESTONES as $n ) {
        $earn[ 'streak-' . $n ] = $s['best'] >= $n;
    }
    $events['badges'] = array();
    foreach ( array_keys( BADGES ) as $slug ) {
        if ( ! empty( $earn[ $slug ] ) && ! isset( $s['badges'][ $slug ] ) ) {
            $s['badges'][ $slug ] = $today;
            $events['badges'][]   = $slug;
        }
    }
    return array( $s, $events );
}

/** What readers see. The stored streak only resets when the goal is next met, so check here whether it is still alive. */
function view( array $s, string $today, int $today_count, int $goal ): array {
    $s     += blank_state();
    $last   = (string) $s['last_goal_date'];
    $met    = '' !== $last && $last === $today;
    $gap    = '' === $last ? PHP_INT_MAX : days_between( $last, $today );
    $alive  = $met || ( $gap >= 1 && $gap - 1 <= $s['freezes'] );
    $cur    = $alive ? (int) $s['current'] : 0;
    $level  = level( (int) $s['xp'] );
    return array(
        'current'       => $cur,
        'best'          => max( (int) $s['best'], $cur ),
        'today'         => $today_count,
        'goal'          => $goal,
        'met_today'     => $met,
        'at_risk'       => $cur > 0 && ! $met,
        'freezes'       => (int) $s['freezes'],
        'xp'            => (int) $s['xp'],
        'level'         => $level,
        'next_level_xp' => level_floor( $level + 1 ),
    );
}

/**
 * Days of the current run (ending on last_goal_date), newest first, at most $limit days back:
 * date => 'met' | 'freeze'. Days outside it are judged by their read count.
 */
function run_days( array $s, int $limit = 120 ): array {
    $s    += blank_state();
    $days  = array();
    $left  = (int) $s['current'];
    $date  = (string) $s['last_goal_date'];
    $used  = array_flip( (array) $s['freeze_used'] );
    for ( $i = 0; '' !== $date && $left > 0 && $i < $limit; $i++, $date = shift_date( $date, -1 ) ) {
        if ( isset( $used[ $date ] ) && $i > 0 ) {
            $days[ $date ] = 'freeze';
        } else {
            $days[ $date ] = 'met';
            $left--;
        }
    }
    return $days;
}

/**
 * State from the meta the old theme dashboard used. Returns array( state, reads ), reads = list of [post_id, date].
 * The old _hr_reading_history is a list of post ids without dates: today's list was read on _hr_last_read_date.
 * shortcut: older legacy reads are dated the day before the last legacy read (true date unknown); fine for a one-off import.
 */
function import_legacy( array $m, string $today ): array {
    $s            = blank_state();
    $s['current'] = max( 0, (int) ( $m['_hr_streak_count'] ?? 0 ) );
    $s['best']    = max( $s['current'], (int) ( $m['_hr_longest_streak'] ?? 0 ) );
    $s['freezes'] = min( MAX_FREEZES, max( 0, (int) ( $m['_hr_streak_freezes'] ?? 0 ) ) );
    $s['xp']      = max( 0, (int) ( $m['_hr_xp'] ?? 0 ) );
    $last_read    = is_date( $m['_hr_last_read_date'] ?? null ) && $m['_hr_last_read_date'] <= $today ? $m['_hr_last_read_date'] : '';
    if ( is_date( $m['_hr_last_goal_date'] ?? null ) && $m['_hr_last_goal_date'] <= $today ) {
        $s['last_goal_date'] = $m['_hr_last_goal_date'];
    } elseif ( $s['current'] > 0 && '' !== $last_read ) {
        $s['last_goal_date'] = $last_read;
    }
    $date = '' !== $last_read ? $last_read : shift_date( $today, -1 );
    foreach ( (array) ( $m['_hr_earned_badges'] ?? array() ) as $slug ) {
        $slug = is_string( $slug ) ? ( LEGACY_BADGES[ $slug ] ?? $slug ) : '';
        if ( isset( BADGES[ $slug ] ) ) {
            $s['badges'][ $slug ] = $date;
        }
    }
    $todays = array_map( 'intval', array_filter( (array) ( $m['_hr_today_read_posts'] ?? array() ), 'is_numeric' ) );
    $reads  = array();
    foreach ( $todays as $id ) {
        $reads[ $id ] = array( $id, $date );
    }
    $history = $m['_hr_reading_history'] ?? array();
    if ( is_array( $history ) && array_is_list( $history ) ) {
        foreach ( array_map( 'intval', array_filter( $history, 'is_numeric' ) ) as $id ) {
            $reads[ $id ] ??= array( $id, shift_date( $date, -1 ) );
        }
    }
    return array( $s, array_values( array_filter( $reads, static fn( $r ) => $r[0] > 0 ) ) );
}
