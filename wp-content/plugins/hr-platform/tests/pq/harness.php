<?php
/**
 * CLI harness (MSBJ) for the pure-PHP signer in includes/signing/pq/. Used by security/pq/php-interop.test.mjs.
 *
 *   php harness.php < jobs.json        jobs.json = {"ops": [ {"op": "...", ...}, ... ]}, binary fields as hex
 *   php harness.php --bench [runs]     median timings (ms) as JSON
 *   php harness.php --selftest         runs HR\Platform\PQ\self_test()
 *
 * Every result carries "notices": any PHP warning or notice raised while that op ran (expected to be empty).
 */

if ( PHP_SAPI !== 'cli' ) {
    exit( 1 );
}
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}
error_reporting( E_ALL );
ini_set( 'display_errors', 'stderr' );

require dirname( __DIR__, 2 ) . '/includes/signing/pq/load.php';

use HR\Platform\PQ;

$notices = array();
set_error_handler(
    function ( int $no, string $str, string $file, int $line ) use ( &$notices ) {
        $notices[] = "{$str} at " . basename( $file ) . ":{$line}";
        return true;
    }
);

$hx = static fn( $v ) => hex2bin( (string) $v );

$run = static function ( array $job ) use ( $hx ) {
    $opt = static fn( string $k ) => array_key_exists( $k, $job ) && null !== $job[ $k ] ? hex2bin( (string) $job[ $k ] ) : null;
    switch ( $job['op'] ) {
        case 'shake128':
            return array( 'out' => bin2hex( PQ\shake128( $hx( $job['in'] ), (int) $job['len'] ) ) );
        case 'shake256':
            return array( 'out' => bin2hex( PQ\shake256( $hx( $job['in'] ), (int) $job['len'] ) ) );
        case 'mldsa_keygen':
            $k = PQ\mldsa87_keygen( $opt( 'seed' ) );
            return array( 'pk' => bin2hex( $k['pk'] ), 'sk' => bin2hex( $k['sk'] ) );
        case 'mldsa_sign':
            return array( 'sig' => bin2hex( PQ\mldsa87_sign( $hx( $job['sk'] ), $hx( $job['msg'] ), $hx( $job['ctx'] ), $opt( 'rnd' ) ) ) );
        case 'mldsa_verify':
            return array( 'ok' => PQ\mldsa87_verify( $hx( $job['pk'] ), $hx( $job['msg'] ), $hx( $job['sig'] ), $hx( $job['ctx'] ) ) );
        case 'mldsa_key_matches':
            return array( 'ok' => PQ\mldsa87_key_matches( $hx( $job['sk'] ), $hx( $job['pk'] ) ) );
        case 'ed_keygen':
            $k = PQ\ed448_keygen();
            return array( 'pk' => bin2hex( $k['pk'] ), 'sk' => bin2hex( $k['sk'] ) );
        case 'ed_pub':
            return array( 'pk' => bin2hex( PQ\ed448_public_key( $hx( $job['sk'] ) ) ) );
        case 'ed_sign':
            return array( 'sig' => bin2hex( PQ\ed448_sign( $hx( $job['sk'] ), $hx( $job['msg'] ), $hx( $job['ctx'] ) ) ) );
        case 'ed_verify':
            return array( 'ok' => PQ\ed448_verify( $hx( $job['pk'] ), $hx( $job['msg'] ), $hx( $job['sig'] ), $hx( $job['ctx'] ) ) );
        case 'supported':
            return array( 'ok' => PQ\supported() );
        case 'self_test':
            return array( 'ok' => PQ\self_test() );
        case 'ed_fe': // test-only: one private Ed448 field operation on 56-byte little-endian inputs
            $call = static fn( string $m, ...$args ) => ( new \ReflectionMethod( PQ\Ed448::class, $m ) )->invoke( null, ...$args );
            $fe   = static fn( string $k ) => $call( 'fe_from_bytes', $hx( $job[ $k ] ) );
            switch ( $job['fn'] ) {
                case 'mul': $r = $call( 'fe_mul', $fe( 'a' ), $fe( 'b' ) ); break;
                case 'sqr': $r = $call( 'fe_sqr', $fe( 'a' ) ); break;
                case 'add': $r = $call( 'fe_add', $fe( 'a' ), $fe( 'b' ) ); break;
                case 'sub': $r = $call( 'fe_sub', $fe( 'a' ), $fe( 'b' ) ); break;
                case 'inv': $r = $call( 'fe_inv', $fe( 'a' ) ); break;
                case 'chain': // (a * b + a) * (a - b), squared: exercises carried outputs fed back into multiplies
                    $a = $fe( 'a' );
                    $b = $fe( 'b' );
                    $r = $call( 'fe_sqr', $call( 'fe_mul', $call( 'fe_add', $call( 'fe_mul', $a, $b ), $a ), $call( 'fe_sub', $a, $b ) ) );
                    break;
                default: throw new \RuntimeException( 'unknown fe fn' );
            }
            return array( 'out' => bin2hex( $call( 'fe_to_bytes', $r ) ) );
        case 'ed_sc': // test-only: scalar arithmetic mod L
            $call = static fn( string $m, ...$args ) => ( new \ReflectionMethod( PQ\Ed448::class, $m ) )->invoke( null, ...$args );
            $sc   = static fn( string $k ) => $call( 'sc_reduce', $call( 'limbs_from_bytes', $hx( $job[ $k ] ) ) );
            $r    = 'muladd' === $job['fn'] ? $call( 'sc_muladd', $sc( 'a' ), $sc( 'b' ), $sc( 'c' ) ) : $sc( 'a' );
            return array( 'out' => bin2hex( $call( 'limbs_to_bytes', $r, 57 ) ) );
        case 'info':
            return array( 'php' => PHP_VERSION, 'int' => PHP_INT_SIZE, 'opcache' => (bool) ini_get( 'opcache.enable_cli' ), 'context' => PQ\SIGNING_CONTEXT );
    }
    throw new \RuntimeException( 'unknown op ' . $job['op'] );
};

function hr_pq_median( array $v ): float {
    sort( $v );
    $n = count( $v );
    return $n % 2 ? $v[ intdiv( $n, 2 ) ] : ( $v[ $n / 2 - 1 ] + $v[ $n / 2 ] ) / 2;
}

if ( in_array( '--selftest', $argv, true ) ) {
    $ok = PQ\self_test();
    echo $ok ? "self_test: ok\n" : "self_test: FAILED\n";
    exit( $ok ? 0 : 1 );
}

if ( in_array( '--bench', $argv, true ) ) {
    $runs  = max( 1, (int) ( $argv[ array_search( '--bench', $argv, true ) + 1 ] ?? 10 ) );
    $time  = static function ( callable $f ) {
        $t = hrtime( true );
        $f();
        return ( hrtime( true ) - $t ) / 1e6;
    };
    $msg   = random_bytes( 600 ); // about the size of a protected header
    $res   = array( 'kg' => array(), 'sign' => array(), 'verify' => array(), 'edsign' => array(), 'edverify' => array(), 'edkg' => array() );
    for ( $i = 0; $i < $runs; $i++ ) {
        $k = null;
        $res['kg'][]   = $time( function () use ( &$k ) { $k = PQ\mldsa87_keygen(); } );
        $sig           = '';
        $res['sign'][] = $time( function () use ( $k, $msg, &$sig ) { $sig = PQ\mldsa87_sign( $k['sk'], $msg, PQ\SIGNING_CONTEXT ); } );
        $ok            = false;
        $res['verify'][] = $time( function () use ( $k, $msg, $sig, &$ok ) { $ok = PQ\mldsa87_verify( $k['pk'], $msg, $sig, PQ\SIGNING_CONTEXT ); } );
        if ( ! $ok ) {
            fwrite( STDERR, "ML-DSA-87 verify failed in bench\n" );
            exit( 1 );
        }
        $e = null;
        $res['edkg'][]   = $time( function () use ( &$e ) { $e = PQ\ed448_keygen(); } );
        $res['edsign'][] = $time( function () use ( $e, $msg, &$sig ) { $sig = PQ\ed448_sign( $e['sk'], $msg, PQ\SIGNING_CONTEXT ); } );
        $res['edverify'][] = $time( function () use ( $e, $msg, $sig, &$ok ) { $ok = PQ\ed448_verify( $e['pk'], $msg, $sig, PQ\SIGNING_CONTEXT ); } );
        if ( ! $ok ) {
            fwrite( STDERR, "Ed448 verify failed in bench\n" );
            exit( 1 );
        }
    }
    $out = array( 'runs' => $runs, 'php' => PHP_VERSION, 'opcache_cli' => (bool) ini_get( 'opcache.enable_cli' ), 'peak_mb' => round( memory_get_peak_usage() / 1048576, 1 ) );
    foreach ( $res as $k => $v ) {
        $out[ $k ] = array( 'median' => round( hr_pq_median( $v ), 1 ), 'min' => round( min( $v ), 1 ), 'max' => round( max( $v ), 1 ) );
    }
    echo json_encode( $out, JSON_PRETTY_PRINT ), "\n";
    exit( 0 );
}

$input = json_decode( (string) stream_get_contents( STDIN ), true );
if ( ! is_array( $input ) || ! isset( $input['ops'] ) || ! is_array( $input['ops'] ) ) {
    fwrite( STDERR, "expected {\"ops\": [...]} on stdin\n" );
    exit( 2 );
}
$out = array();
foreach ( $input['ops'] as $job ) {
    $notices = array();
    try {
        $r = $run( $job );
    } catch ( \Throwable $e ) {
        $r = array( 'error' => get_class( $e ), 'message' => $e->getMessage() );
    }
    $r['notices'] = $notices;
    $out[]        = $r;
}
echo json_encode( $out );
