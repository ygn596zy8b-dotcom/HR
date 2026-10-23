<?php
/**
 * Streaks: first sign-in onboarding ("How streaks work").
 *
 * User meta _hr_streak_tour: '' (has not signed in since this shipped), 'pending' (signed in, has not finished or
 * dismissed the tutorial) or 'done'. A reader's first sign-in on any path (our sign-in form, two-step, passkeys, the
 * email confirmation link, wp-login.php) sets 'pending' and, when no return URL was asked for, lands on /dashboard/
 * (filter 'hr_login_default_redirect' from accounts/login.php). The dashboard opens the tutorial while the state is not
 * 'done', so readers who signed in before this shipped see it once too, and a sign-in that returned to an article
 * shows it the next time the reader opens the streaks page. "Got it" and "Don't show again" set 'done'; closing the
 * tutorial keeps it for next time.
 *
 * Every number in the tutorial comes from the constants the streak rules use (rest.php, engine.php).
 */

namespace HR\Platform\Streaks;

if ( ! defined( 'ABSPATH' ) ) exit;

const TOUR_META = '_hr_streak_tour';

function tour_state( int $uid ): string {
    $state = (string) get_user_meta( $uid, TOUR_META, true );
    return in_array( $state, array( 'pending', 'done' ), true ) ? $state : '';
}

function tour_pending( int $uid ): bool {
    return $uid > 0 && 'done' !== tour_state( $uid );
}

function tour_done( int $uid ): void {
    update_user_meta( $uid, TOUR_META, 'done' );
}

/** Whether $uid signed in for the first time during this request ($mark records it). */
function first_sign_in( int $uid, bool $mark = false ): bool {
    static $ids = array();
    if ( $mark ) {
        $ids[ $uid ] = true;
    }
    return isset( $ids[ $uid ] );
}

add_action( 'wp_login', function ( $login, $user ) {
    if ( $user instanceof \WP_User && '' === tour_state( (int) $user->ID ) ) {
        update_user_meta( (int) $user->ID, TOUR_META, 'pending' );
        first_sign_in( (int) $user->ID, true );
    }
}, 10, 2 );

// Our sign-in paths ask for the default destination either just before or just after wp_login fires.
add_filter( 'hr_login_default_redirect', function ( $url, $user ) {
    // Staff keep their usual destination (they see the tutorial when they open the streaks page).
    $staff = $user instanceof \WP_User && function_exists( 'HR\Platform\Accounts\is_staff' ) && \HR\Platform\Accounts\is_staff( $user );
    if ( $user instanceof \WP_User && ! $staff && ( '' === tour_state( (int) $user->ID ) || first_sign_in( (int) $user->ID ) ) ) {
        return dashboard_url();
    }
    return $url;
}, 10, 2 );

// wp-login.php: accounts sends readers to their account (accounts/admin.php, priority 20); a first sign-in goes to
// the streaks page instead. Staff keep their wp-admin destination.
add_filter( 'login_redirect', function ( $redirect, $requested, $user ) {
    if ( $user instanceof \WP_User && first_sign_in( (int) $user->ID ) && function_exists( 'HR\Platform\Accounts\account_url' )
        && untrailingslashit( (string) $redirect ) === untrailingslashit( \HR\Platform\Accounts\account_url() ) ) {
        return dashboard_url();
    }
    return $redirect;
}, 30, 3 );

/** "Pakistan time" when streak days run on Karachi time (store.php day_zone()), otherwise the zone itself. */
function day_zone_label(): string {
    $tz     = day_zone();
    $offset = $tz->getOffset( new \DateTimeImmutable( 'now', $tz ) );
    if ( 'Asia/Karachi' === $tz->getName() || 5 * HOUR_IN_SECONDS === $offset ) {
        return 'Pakistan time';
    }
    if ( 0 === $offset ) {
        return 'UTC';
    }
    return 'site time (UTC' . ( $offset < 0 ? '-' : '+' ) . gmdate( 'H:i', abs( $offset ) ) . ')';
}

/** "20 seconds", "2 minutes", "90 seconds". */
function seconds_phrase( int $seconds ): string {
    if ( $seconds >= 60 && 0 === $seconds % 60 ) {
        $m = intdiv( $seconds, 60 );
        return sprintf( 1 === $m ? '%d minute' : '%d minutes', $m );
    }
    return sprintf( 1 === $seconds ? '%d second' : '%d seconds', $seconds );
}

/** "1, 3, 5 or 10". */
function list_phrase( array $items, string $last = 'or' ): string {
    $items = array_map( 'strval', array_values( $items ) );
    $tail  = array_pop( $items );
    return $items ? implode( ', ', $items ) . " {$last} " . $tail : (string) $tail;
}

/**
 * The tutorial, one step per card: icon, title, text, optional detail. Rendered as static cards on /dashboard/
 * (works without JavaScript) and turned into the step-through dialog by streak-dashboard.js.
 */
function tour_steps( int $goal ): array {
    $goal       = isset( GOALS[ $goal ] ) ? $goal : DEFAULT_GOAL;
    $articles   = static fn( int $n ) => sprintf( 1 === $n ? '%d article' : '%d articles', $n );
    $milestones = array_map( 'intval', MILESTONES );
    $streak_badges = count( array_filter( array_keys( BADGES ), static fn( $slug ) => str_starts_with( $slug, 'streak-' ) ) );
    return array(
        array(
            'icon'   => 'flame',
            'title'  => 'Your reading streak',
            'text'   => 'Your streak is the number of days in a row you have met your daily reading goal on Headline Recorder. Here is how it is counted, in four short steps.',
            'detail' => '',
        ),
        array(
            'icon'   => 'book-open',
            'title'  => 'What counts as a read',
            'text'   => 'An article or op-ed counts when you are signed in, the page has been open and on screen for its minimum reading time, and you have scrolled to the end of the story. Each article counts once.',
            'detail' => sprintf(
                'The minimum reading time is about %d%% of the time an average reader needs at %d words a minute: never less than %s and never more than %s.',
                (int) round( READ_SHARE * 100 ),
                READ_WPM,
                seconds_phrase( READ_MIN_SECONDS ),
                seconds_phrase( READ_MAX_SECONDS )
            ),
        ),
        array(
            'icon'   => 'target',
            'title'  => 'Meet your daily goal',
            'text'   => sprintf( 'Each day you meet your goal, your streak grows by one day. Your goal is %s a day (%s).', $articles( $goal ), GOALS[ $goal ] ),
            'detail' => sprintf( 'You can choose %s articles a day and change your goal once a day, under "Your goal and reminders" on this page.', list_phrase( array_keys( GOALS ) ) ),
        ),
        array(
            'icon'   => 'snowflake',
            'title'  => 'Keep it alive',
            'text'   => sprintf( 'Miss a day and your streak goes back to zero, unless a streak freeze covers it. Days run from midnight to midnight, %s.', day_zone_label() ),
            'detail' => sprintf( 'You earn a freeze every %d days of streak and can hold up to %d. A freeze covers one missed day and is used automatically.', FREEZE_EVERY, MAX_FREEZES ),
        ),
        array(
            'icon'   => 'award',
            'title'  => 'XP, levels and badges',
            'text'   => sprintf( 'Each article earns %d XP and each op-ed %d. Meeting your daily goal adds %d more (%d once your streak is longer than %d days). XP raises your level.', XP_ARTICLE, XP_OPED, XP_GOAL, XP_GOAL + XP_LONG, LONG_STREAK ),
            'detail' => sprintf( 'Streaks of %s days unlock badges. %d more badges reward other habits, such as finishing your first article or reading 10 op-eds.', list_phrase( $milestones, 'and' ), count( BADGES ) - $streak_badges ),
        ),
    );
}
