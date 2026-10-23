<?php
/**
 * WP-CLI: `wp hr sign [--all] [--limit=<n>]`, the backlog from a terminal (or a real cron job on hosts where WP-Cron
 * is switched off).
 */

namespace HR\Platform\Signing;

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    return;
}

/**
 * Signs pending and stale articles with the server signing key, newest first, then the feed snapshots.
 *
 * Without options it does what one automatic run does (about 20 seconds of signing). Every signature is verified
 * before it is stored.
 *
 * ## OPTIONS
 *
 * [<action>]
 * : `migrate` moves signatures stored by older versions in post meta into the signature table (batched, safe to
 * interrupt and to run again) and signs nothing.
 *
 * [--all]
 * : Keep going until every pending article is signed.
 *
 * [--limit=<n>]
 * : Sign at most this many articles.
 *
 * ## EXAMPLES
 *
 *     wp hr sign
 *     wp hr sign --all
 *     wp hr sign --limit=50
 *     wp hr sign migrate
 *
 * @when after_wp_load
 */
function cli_sign( array $args, array $assoc ): void {
    if ( $args ) {
        if ( 'migrate' !== $args[0] ) {
            \WP_CLI::error( 'Unknown action. Use `wp hr sign` or `wp hr sign migrate`.' );
        }
        cli_migrate();
        return;
    }
    $all   = ! empty( $assoc['all'] );
    $limit = isset( $assoc['limit'] ) ? (int) $assoc['limit'] : 0;
    if ( isset( $assoc['limit'] ) && $limit < 1 ) {
        \WP_CLI::error( '--limit must be a positive number.' );
    }
    if ( ! pq_ready() ) {
        \WP_CLI::error( 'This PHP cannot run the signer (it needs 64-bit PHP 8.2 or newer).' );
    }
    if ( ! self_test_result( true ) ) {
        \WP_CLI::error( 'The signer self-test failed on this PHP build. Nothing was signed.' );
    }
    $key = server_key();
    if ( is_wp_error( $key ) ) {
        \WP_CLI::error( $key->get_error_message() . ' Set it up in Settings > Content signing.' );
    }
    $pending = queue_candidate_ids( 0, 0, true );
    \WP_CLI::log( sprintf( 'Key %s, %d article(s) pending.', group_hex( $key['kid'], 4 ), $pending ) );

    $token = acquire_lock( 600 );
    if ( '' === $token ) {
        \WP_CLI::error( 'Another signing run is in progress. Try again in a minute.' );
    }
    $start  = microtime( true );
    $total  = array( 'signed' => 0, 'current' => 0, 'failed' => array(), 'stopped' => '', 'feeds' => array() );
    $cursor = null;
    try {
        do {
            $left   = $limit > 0 ? $limit - $total['signed'] : 0;
            $budget = $all || $limit > 0 ? 30.0 : SWEEP_BUDGET;
            $batch  = sign_pending( $budget, $left, $cursor );
            $cursor = $batch['cursor'];
            $total['signed']  += $batch['signed'];
            $total['current'] += $batch['current'];
            $total['failed']   = array_merge( $total['failed'], $batch['failed'] );
            $total['stopped']  = $batch['stopped'];
            foreach ( $batch['failed'] as $f ) {
                \WP_CLI::warning( sprintf( 'Article %d: %s', $f['id'], $f['message'] ) );
            }
            \WP_CLI::log( sprintf( '  signed %d so far, %d pending', $total['signed'], queue_candidate_ids( 0, 0, true ) ) );
            $more = ! $batch['done'] && '' === $batch['stopped'] && ( $limit < 1 || $total['signed'] < $limit );
            // Refresh the lock between long batches so a cron run cannot start alongside.
            if ( $more ) {
                $token = refresh_lock( $token, 600 );
                if ( '' === $token ) {
                    $total['stopped'] = 'The signing lock was lost (a run took longer than 10 minutes). Run the command again.';
                    break;
                }
            }
        } while ( $more && ( $all || $limit > 0 ) );
        if ( '' === $total['stopped'] ) {
            $total['feeds'] = sign_feeds( true );
        }
    } finally {
        release_lock( $token );
    }
    record_run( 'cli', $total, microtime( true ) - $start );
    foreach ( $total['feeds'] as $feed => $status ) {
        \WP_CLI::log( sprintf( 'Feed %s: %s', $feed, $status ) );
    }
    $remaining = queue_candidate_ids( 0, 0, true );
    $summary   = sprintf( 'Signed %d article(s) in %.1f s; %d failed; %d still pending.', $total['signed'], microtime( true ) - $start, count( $total['failed'] ), $remaining );
    if ( '' !== $total['stopped'] ) {
        \WP_CLI::error( $total['stopped'] . ' ' . $summary );
    }
    $feed_errors = array_filter( $total['feeds'], static fn( $s ) => ! in_array( $s, array( 'stored', 'unchanged' ), true ) );
    if ( $total['failed'] || $feed_errors ) {
        \WP_CLI::error( $summary, false );
        \WP_CLI::halt( 1 );
    }
    \WP_CLI::success( $summary );
}

function cli_migrate(): void {
    if ( (int) get_option( STORE_DB_OPTION ) !== STORE_DB_VERSION ) {
        install_store();
    }
    if ( ! table_exists() ) {
        \WP_CLI::error( 'The signature table could not be created (check the database user can CREATE TABLE).' );
    }
    if ( migration_done() && legacy_rows_exist() ) {
        set_migration_state( false );
    }
    $total = array( 'moved' => 0, 'kept' => 0, 'failed' => array() );
    do {
        $r = run_migration( 30.0 );
        if ( null === $r ) {
            \WP_CLI::error( 'Another migration run is in progress. Try again in a minute.' );
        }
        $total['moved'] += $r['moved'];
        $total['kept']  += $r['kept'];
        $total['failed'] = array_merge( $total['failed'], $r['failed'] );
        \WP_CLI::log( sprintf( '  moved %d, kept %d so far', $total['moved'], $total['kept'] ) );
    } while ( ! $r['done'] && ! $r['failed'] );
    if ( $total['failed'] ) {
        \WP_CLI::error( 'Could not move the signatures of posts ' . implode( ', ', array_unique( $total['failed'] ) ) . '; their old meta is kept and still used.' );
    }
    \WP_CLI::success( sprintf( 'Migration finished: %d signature(s) moved, %d superseded or empty.', $total['moved'], $total['kept'] ) );
}

\WP_CLI::add_command( 'hr sign', __NAMESPACE__ . '\\cli_sign' );
