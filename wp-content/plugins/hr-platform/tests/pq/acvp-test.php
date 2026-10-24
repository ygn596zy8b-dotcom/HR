<?php
/**
 * NIST ACVP known-answer tests for ML-DSA-87 (FIPS 204), from usnistgov/ACVP-Server gen-val/json-files:
 * keyGen (seed -> pk, sk), sigGen (external, pure, deterministic) and sigVer (external, pure).
 * Run: php tests/pq/acvp-test.php   (exits 1 on any failure)
 */
if ( PHP_SAPI !== 'cli' ) {
    exit;
}
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}
require dirname( __DIR__, 2 ) . '/includes/signing/pq/load.php';

use function HR\Platform\PQ\mldsa87_keygen;
use function HR\Platform\PQ\mldsa87_sign;
use function HR\Platform\PQ\mldsa87_verify;

$v    = json_decode( (string) file_get_contents( __DIR__ . '/acvp-mldsa87.json' ), true );
$h    = static fn( string $x ): string => (string) hex2bin( $x );
$fail = 0;
$pass = 0;
$ok   = static function ( bool $cond, string $what ) use ( &$fail, &$pass ) {
    $cond ? $pass++ : ( $fail++ && false );
    if ( ! $cond ) {
        fwrite( STDERR, "FAIL {$what}\n" );
    }
};
foreach ( $v['keyGen'] as $i => $t ) {
    $k = mldsa87_keygen( $h( $t['seed'] ) );
    $ok( strtoupper( bin2hex( $k['pk'] ) ) === strtoupper( $t['pk'] ) && strtoupper( bin2hex( $k['sk'] ) ) === strtoupper( $t['sk'] ), "keyGen #{$i}" );
}
foreach ( $v['sigGen'] as $i => $t ) {
    $sig = mldsa87_sign( $h( $t['sk'] ), $h( $t['message'] ), $h( $t['context'] ), str_repeat( "\0", 32 ) );
    $ok( strtoupper( bin2hex( $sig ) ) === strtoupper( $t['signature'] ), "sigGen #{$i}" );
}
foreach ( $v['sigVer'] as $i => $t ) {
    $ok( mldsa87_verify( $h( $t['pk'] ), $h( $t['message'] ), $h( $t['signature'] ), $h( $t['context'] ) ) === (bool) $t['valid'], "sigVer #{$i}" );
}
echo "ACVP ML-DSA-87: {$pass} passed, {$fail} failed\n";
exit( $fail ? 1 : 0 );
