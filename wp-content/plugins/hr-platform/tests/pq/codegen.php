<?php
/**
 * Code generator (MSBJ) for the unrolled hot loops of the pure-PHP signer: the Keccak-f[1600] round and the
 * Ed448 field multiply / square. The generated code lives between "BEGIN GENERATED <name>" and
 * "END GENERATED <name>" markers in includes/signing/pq/*.php; this script rewrites those regions in place.
 *
 * Run from the plugin directory (CLI only):  php tests/pq/codegen.php
 * Re-run it only if the generator itself changes; the output is committed.
 */

if ( PHP_SAPI !== 'cli' ) {
    exit( 1 );
}

const PQ_DIR = __DIR__ . '/../../includes/signing/pq';

/** Keccak-f[1600]: one round (theta, rho, pi, chi, iota) on 25 local lanes $s0..$s24, lane index x + 5y. */
function gen_keccak_round( string $ind ): string {
    $rot = array(
        0, 1, 62, 28, 27,
        36, 44, 6, 55, 20,
        3, 10, 43, 25, 39,
        41, 45, 15, 21, 8,
        18, 2, 61, 56, 14,
    );
    $o = array();
    for ( $x = 0; $x < 5; $x++ ) {
        $o[] = "\$c{$x} = \$s{$x} ^ \$s" . ( $x + 5 ) . ' ^ $s' . ( $x + 10 ) . ' ^ $s' . ( $x + 15 ) . ' ^ $s' . ( $x + 20 ) . ';';
    }
    for ( $x = 0; $x < 5; $x++ ) {
        $l = ( $x + 4 ) % 5;
        $r = ( $x + 1 ) % 5;
        $o[] = "\$d{$x} = \$c{$l} ^ ( ( \$c{$r} << 1 ) | ( ( \$c{$r} >> 63 ) & 1 ) );";
    }
    for ( $y = 0; $y < 5; $y++ ) {
        for ( $x = 0; $x < 5; $x++ ) {
            $src  = $x + 5 * $y;
            $dest = $y + 5 * ( ( 2 * $x + 3 * $y ) % 5 );
            $n    = $rot[ $src ];
            if ( 0 === $n ) {
                $o[] = "\$b{$dest} = \$s{$src} ^ \$d{$x};";
            } else {
                $mask = sprintf( '0x%X', ( 1 << $n ) - 1 );
                $o[]  = "\$t = \$s{$src} ^ \$d{$x}; \$b{$dest} = ( \$t << {$n} ) | ( ( \$t >> " . ( 64 - $n ) . " ) & {$mask} );";
            }
        }
    }
    for ( $y = 0; $y < 5; $y++ ) {
        for ( $x = 0; $x < 5; $x++ ) {
            $i  = $x + 5 * $y;
            $i1 = ( $x + 1 ) % 5 + 5 * $y;
            $i2 = ( $x + 2 ) % 5 + 5 * $y;
            $o[] = "\$s{$i} = \$b{$i} ^ ( ~\$b{$i1} & \$b{$i2} )" . ( 0 === $i ? ' ^ $rc' : '' ) . ';';
        }
    }
    return $ind . implode( "\n" . $ind, $o ) . "\n";
}

/**
 * Ed448 field element: 16 limbs of 28 bits (value = sum l_i * 2^(28 i)), p = 2^448 - 2^224 - 1.
 * Product coefficient c_k (k = 0..30) folds with 2^448 = 2^224 + 1:
 *   r_i = c_i + c_(i+16) + c_(i+24)        for i = 0..7
 *   r_i = c_i + c_(i+8) + 2 c_(i+16)       for i = 8..15
 */
function fold_terms( array $c ): array {
    $r = array();
    for ( $i = 0; $i < 16; $i++ ) {
        $t = array();
        if ( $i < 8 ) {
            $t[] = $c[ $i ];
            if ( isset( $c[ $i + 16 ] ) ) $t[] = $c[ $i + 16 ];
            if ( isset( $c[ $i + 24 ] ) ) $t[] = $c[ $i + 24 ];
            $r[ $i ] = implode( ' + ', array_filter( $t ) );
        } else {
            $t[] = $c[ $i ];
            if ( isset( $c[ $i + 8 ] ) ) $t[] = $c[ $i + 8 ];
            $s = implode( ' + ', array_filter( $t ) );
            if ( isset( $c[ $i + 16 ] ) && '' !== $c[ $i + 16 ] ) {
                $s .= ' + ( ' . $c[ $i + 16 ] . ' ) * 2';
            }
            $r[ $i ] = $s;
        }
    }
    return $r;
}

function gen_carry( string $ind ): string {
    $o = array();
    for ( $i = 0; $i < 15; $i++ ) {
        $o[] = '$r' . ( $i + 1 ) . " += \$r{$i} >> 28; \$r{$i} &= 0xFFFFFFF;";
    }
    $o[] = '$t = $r15 >> 28; $r15 &= 0xFFFFFFF; $r0 += $t; $r8 += $t;';
    $o[] = '$r1 += $r0 >> 28; $r0 &= 0xFFFFFFF; $r9 += $r8 >> 28; $r8 &= 0xFFFFFFF;';
    return $ind . implode( "\n" . $ind, $o ) . "\n";
}

function limbs_list( string $p ): string {
    $v = array();
    for ( $i = 0; $i < 16; $i++ ) {
        $v[] = '$' . $p . $i;
    }
    return implode( ', ', $v );
}

function gen_fe_mul( string $ind ): string {
    $c = array();
    for ( $k = 0; $k <= 30; $k++ ) {
        $t = array();
        for ( $i = max( 0, $k - 15 ); $i <= min( 15, $k ); $i++ ) {
            $t[] = "\$a{$i} * \$b" . ( $k - $i );
        }
        $c[ $k ] = implode( ' + ', $t );
    }
    $r = fold_terms( $c );
    $o = array( '[ ' . limbs_list( 'a' ) . ' ] = $a;', '[ ' . limbs_list( 'b' ) . ' ] = $b;' );
    for ( $i = 0; $i < 16; $i++ ) {
        $o[] = "\$r{$i} = {$r[$i]};";
    }
    return $ind . implode( "\n" . $ind, $o ) . "\n" . gen_carry( $ind ) . $ind . 'return [ ' . limbs_list( 'r' ) . " ];\n";
}

function gen_fe_sqr( string $ind ): string {
    $c = array();
    for ( $k = 0; $k <= 30; $k++ ) {
        $t = array();
        for ( $i = max( 0, $k - 15 ); $i <= min( 15, $k ); $i++ ) {
            $j = $k - $i;
            if ( $i < $j ) {
                $t[] = "\$d{$i} * \$a{$j}";
            } elseif ( $i === $j ) {
                $t[] = "\$a{$i} * \$a{$i}";
            }
        }
        $c[ $k ] = implode( ' + ', $t );
    }
    $r = fold_terms( $c );
    $o = array( '[ ' . limbs_list( 'a' ) . ' ] = $a;' );
    for ( $i = 0; $i < 15; $i++ ) {
        $o[] = "\$d{$i} = \$a{$i} << 1;";
    }
    for ( $i = 0; $i < 16; $i++ ) {
        $o[] = "\$r{$i} = {$r[$i]};";
    }
    return $ind . implode( "\n" . $ind, $o ) . "\n" . gen_carry( $ind ) . $ind . 'return [ ' . limbs_list( 'r' ) . " ];\n";
}

function replace_region( string $file, string $name, string $code ): void {
    $src = file_get_contents( $file );
    $re  = '/(\/\/ BEGIN GENERATED ' . preg_quote( $name, '/' ) . "[^\n]*\n)(.*?)(\\s*\/\/ END GENERATED " . preg_quote( $name, '/' ) . ')/s';
    if ( ! preg_match( $re, $src ) ) {
        fwrite( STDERR, "marker {$name} not found in {$file}\n" );
        exit( 1 );
    }
    $out = preg_replace_callback( $re, fn( $m ) => $m[1] . rtrim( $code, "\n" ) . $m[3], $src );
    file_put_contents( $file, $out );
    echo "updated {$name} in " . basename( $file ) . "\n";
}

replace_region( PQ_DIR . '/keccak.php', 'keccak-round', gen_keccak_round( '            ' ) );
replace_region( PQ_DIR . '/ed448.php', 'fe-mul', gen_fe_mul( '        ' ) );
replace_region( PQ_DIR . '/ed448.php', 'fe-sqr', gen_fe_sqr( '        ' ) );
