<?php
/**
 * ML-DSA-87 (FIPS 204, final), pure PHP on native 64-bit ints: KeyGen_internal, Sign_internal, Verify_internal and
 * the FIPS 204 encodings (pk 2592 B, sk 4896 B, signature 4627 B). The caller formats M' (pure mode with a
 * context, see load.php), exactly as @noble/post-quantum does for { context }.
 *
 * Arithmetic: q = 8380417 < 2^23, so a product of two reduced coefficients is below 2^46 and sums of a few such
 * products stay far below 2^63; reductions use PHP's integer % and never touch floats. The NTT keeps lazy
 * (unreduced) values inside the butterflies and reduces once at the end. Sampling, rounding, hints and
 * bit-packing follow FIPS 204 Algorithms 14-36 and match noble byte for byte (tested in
 * security/pq/php-interop.test.mjs). MakeHint uses the Section 6.2 alternative computation, as noble and the
 * reference implementation do.
 */

namespace HR\Platform\PQ;

if ( ! defined( 'ABSPATH' ) ) exit;

final class MLDSA87 {

    public const PK_BYTES  = 2592;
    public const SK_BYTES  = 4896;
    public const SIG_BYTES = 4627;

    private const Q      = 8380417;
    private const K      = 8;
    private const L      = 7;
    private const ETA    = 2;
    private const TAU    = 60;
    private const BETA   = 120;            // TAU * ETA
    private const GAMMA1 = 524288;         // 2^19
    private const GAMMA2 = 261888;         // (q - 1) / 32
    private const OMEGA  = 75;
    private const F      = 8347681;        // 256^-1 mod q

    /** zeta^brv8(i) mod q, zeta = 1753 (FIPS 204 Appendix B). */
    private const ZETAS = array(
        1, 4808194, 3765607, 3761513, 5178923, 5496691, 5234739, 5178987, 7778734, 3542485, 2682288, 2129892, 3764867, 7375178, 557458, 7159240,
        5010068, 4317364, 2663378, 6705802, 4855975, 7946292, 676590, 7044481, 5152541, 1714295, 2453983, 1460718, 7737789, 4795319, 2815639, 2283733,
        3602218, 3182878, 2740543, 4793971, 5269599, 2101410, 3704823, 1159875, 394148, 928749, 1095468, 4874037, 2071829, 4361428, 3241972, 2156050,
        3415069, 1759347, 7562881, 4805951, 3756790, 6444618, 6663429, 4430364, 5483103, 3192354, 556856, 3870317, 2917338, 1853806, 3345963, 1858416,
        3073009, 1277625, 5744944, 3852015, 4183372, 5157610, 5258977, 8106357, 2508980, 2028118, 1937570, 4564692, 2811291, 5396636, 7270901, 4158088,
        1528066, 482649, 1148858, 5418153, 7814814, 169688, 2462444, 5046034, 4213992, 4892034, 1987814, 5183169, 1736313, 235407, 5130263, 3258457,
        5801164, 1787943, 5989328, 6125690, 3482206, 4197502, 7080401, 6018354, 7062739, 2461387, 3035980, 621164, 3901472, 7153756, 2925816, 3374250,
        1356448, 5604662, 2683270, 5601629, 4912752, 2312838, 7727142, 7921254, 348812, 8052569, 1011223, 6026202, 4561790, 6458164, 6143691, 1744507,
        1753, 6444997, 5720892, 6924527, 2660408, 6600190, 8321269, 2772600, 1182243, 87208, 636927, 4415111, 4423672, 6084020, 5095502, 4663471,
        8352605, 822541, 1009365, 5926272, 6400920, 1596822, 4423473, 4620952, 6695264, 4969849, 2678278, 4611469, 4829411, 635956, 8129971, 5925040,
        4234153, 6607829, 2192938, 6653329, 2387513, 4768667, 8111961, 5199961, 3747250, 2296099, 1239911, 4541938, 3195676, 2642980, 1254190, 8368000,
        2998219, 141835, 8291116, 2513018, 7025525, 613238, 7070156, 6161950, 7921677, 6458423, 4040196, 4908348, 2039144, 6500539, 7561656, 6201452,
        6757063, 2105286, 6006015, 6346610, 586241, 7200804, 527981, 5637006, 6903432, 1994046, 2491325, 6987258, 507927, 7192532, 7655613, 6545891,
        5346675, 8041997, 2647994, 3009748, 5767564, 4148469, 749577, 4357667, 3980599, 2569011, 6764887, 1723229, 1665318, 2028038, 1163598, 5011144,
        3994671, 8368538, 7009900, 3020393, 3363542, 214880, 545376, 7609976, 3105558, 7277073, 508145, 7826699, 860144, 3430436, 140244, 6866265,
        6195333, 3123762, 2358373, 6187330, 5365997, 6663603, 2926054, 7987710, 8077412, 3531229, 4405932, 4606686, 1900052, 7598542, 1054478, 7648983,
    );

    /** CoefFromHalfByte for eta = 2: 2 - (b mod 5) for b < 15 (15 is rejected). */
    private const ETA_COEF = array( 2, 1, 0, -1, -2, 2, 1, 0, -1, -2, 2, 1, 0, -1, -2 );

    /* ------------------------------------------------------------------ algorithms */

    /**
     * ML-DSA.KeyGen_internal (Algorithm 6) from a 32-byte seed xi.
     *
     * @return array{pk: string, sk: string}
     */
    public static function keygen( string $xi ): array {
        $h    = Keccak::shake256( $xi . chr( self::K ) . chr( self::L ), 128 );
        $rho  = substr( $h, 0, 32 );
        $rhop = substr( $h, 32, 64 );
        $key  = substr( $h, 96, 32 );
        self::wipe( $h );

        [ $s1, $s2 ] = self::expand_s( $rhop );
        self::wipe( $rhop );
        $a    = self::expand_a( $rho );
        $s1h  = array();
        foreach ( $s1 as $p ) {
            $s1h[] = self::ntt( $p );
        }
        $t1 = array();
        $t0 = array();
        for ( $i = 0; $i < self::K; $i++ ) {
            $t   = self::intt( self::row_times( $a[ $i ], $s1h ) );
            $s2i = $s2[ $i ];
            $r1  = array();
            $r0  = array();
            for ( $n = 0; $n < 256; $n++ ) {
                $v = $t[ $n ] + $s2i[ $n ];
                $v = ( $v + 8380417 ) % 8380417;
                // Power2Round: v = r1 * 2^13 + r0 with r0 in (-2^12, 2^12].
                $hi    = ( $v + 4095 ) >> 13;
                $r1[]  = $hi;
                $r0[]  = $v - ( $hi << 13 );
            }
            $t1[] = $r1;
            $t0[] = $r0;
        }

        $pk = $rho;
        foreach ( $t1 as $p ) {
            $pk .= self::pack_bits( $p, 10 );
        }
        $tr = Keccak::shake256( $pk, 64 );
        $sk = $rho . $key . $tr;
        foreach ( $s1 as $p ) {
            $sk .= self::pack_eta( $p );
        }
        foreach ( $s2 as $p ) {
            $sk .= self::pack_eta( $p );
        }
        foreach ( $t0 as $p ) {
            $tmp = array();
            foreach ( $p as $v ) {
                $tmp[] = 4096 - $v;
            }
            $sk .= self::pack_bits( $tmp, 13 );
        }
        self::wipe( $key );
        $s1 = $s2 = $s1h = $t0 = array();
        return array( 'pk' => $pk, 'sk' => $sk );
    }

    /**
     * ML-DSA.Sign_internal (Algorithm 7). $mp is M' (already formatted with the context), $rnd is 32 bytes
     * (random for the hedged variant, all zero for the deterministic variant).
     */
    public static function sign( string $sk, string $mp, string $rnd ): string {
        $dec = self::decode_sk( $sk );
        if ( null === $dec ) {
            throw new \InvalidArgumentException( 'ML-DSA-87 secret key is malformed.' );
        }
        [ $rho, $key, $tr, $s1, $s2, $t0 ] = $dec;
        $s1h = array();
        $s2h = array();
        $t0h = array();
        foreach ( $s1 as $p ) {
            $s1h[] = self::ntt( $p );
        }
        foreach ( $s2 as $p ) {
            $s2h[] = self::ntt( $p );
        }
        foreach ( $t0 as $p ) {
            $t0h[] = self::ntt( $p );
        }
        $s1 = $s2 = $t0 = array();
        $a    = self::expand_a( $rho );
        $mu   = Keccak::shake256( $tr . $mp, 64 );
        $seed = $key . $rnd . $mu;
        $rpp  = Keccak::shake256( $seed, 64 );
        self::wipe( $key );
        self::wipe( $seed );

        $g1b = self::GAMMA1 - self::BETA;
        $g2b = self::GAMMA2 - self::BETA;
        for ( $kappa = 0; $kappa < 0x10000 - self::L; $kappa += self::L ) {
            // y <- ExpandMask(rho'', kappa); w <- NTT^-1(A o NTT(y)); w1 <- HighBits(w)
            $y  = self::expand_mask( $rpp, $kappa );
            $yh = array();
            foreach ( $y as $p ) {
                $yh[] = self::ntt( $p );
            }
            $w  = array();
            $w1 = array();
            for ( $i = 0; $i < self::K; $i++ ) {
                $wi    = self::intt( self::row_times( $a[ $i ], $yh ) );
                $w[]   = $wi;
                $w1[]  = self::high_bits( $wi );
            }
            $ct = Keccak::shake256( $mu . self::w1_encode( $w1 ), 64 );
            $ch = self::ntt( self::sample_in_ball( $ct ) );

            // z <- y + <<c s1>>, rejected if ||z|| >= gamma1 - beta.
            $z  = array();
            $ok = true;
            for ( $i = 0; $i < self::L && $ok; $i++ ) {
                $cs1 = self::intt( self::pointwise( $ch, $s1h[ $i ] ) );
                $yi  = $y[ $i ];
                $zi  = array();
                for ( $n = 0; $n < 256; $n++ ) {
                    $v = ( $cs1[ $n ] + $yi[ $n ] + 8380417 ) % 8380417;
                    // |v mod+- q| without branching on the sign (the reference code takes the same care).
                    $c = $v - ( ( ( 4190208 - $v ) >> 63 ) & 8380417 );
                    $m = $c >> 63;
                    if ( ( ( $c ^ $m ) - $m ) >= $g1b ) {
                        $ok = false;
                        break;
                    }
                    $zi[] = $v;
                }
                $z[] = $zi;
            }
            if ( ! $ok ) {
                continue;
            }

            // r0 <- LowBits(w - <<c s2>>), <<c t0>>, hints.
            $hints = array();
            $count = 0;
            for ( $i = 0; $i < self::K && $ok; $i++ ) {
                $cs2 = self::intt( self::pointwise( $ch, $s2h[ $i ] ) );
                $ct0 = self::intt( self::pointwise( $ch, $t0h[ $i ] ) );
                $wi  = $w[ $i ];
                $w1i = $w1[ $i ];
                $hi  = array();
                for ( $n = 0; $n < 256; $n++ ) {
                    $r = $wi[ $n ] - $cs2[ $n ];
                    $r += ( $r >> 63 ) & 8380417;
                    // Decompose (FIPS 204 Algorithm 36), branch-free form of the reference implementation.
                    $r1  = ( ( ( ( $r + 127 ) >> 7 ) * 1025 + 2097152 ) >> 22 ) & 15;
                    $r0  = $r - $r1 * 523776;
                    $r0 -= ( ( 4190208 - $r0 ) >> 31 ) & 8380417;
                    $m = $r0 >> 63;
                    if ( ( ( $r0 ^ $m ) - $m ) >= $g2b ) {
                        $ok = false;
                        break;
                    }
                    $t = $ct0[ $n ];
                    $c = $t - ( ( ( 4190208 - $t ) >> 63 ) & 8380417 );
                    $m = $c >> 63;
                    if ( ( ( $c ^ $m ) - $m ) >= 261888 ) {
                        $ok = false;
                        break;
                    }
                    // MakeHint, Section 6.2 form (as noble): h = 0 iff v <= gamma2, v > q - gamma2, or
                    // (v == q - gamma2 and w1 == 0), with v = r0 + ct0 mod q. Computed without branches; only the
                    // resulting hint bit (which is published in the signature) decides the branch below.
                    $v  = ( $r0 + $t + 8380417 ) % 8380417;
                    $hb = ( ( ( 261888 - $v ) >> 63 ) & 1 )                                // v > gamma2
                        & ( ( ( $v - 8118530 ) >> 63 ) & 1 )                               // v <= q - gamma2
                        & ~( ( ( ( $v ^ 8118529 ) | $w1i[ $n ] ) - 1 ) >> 63 );            // not (v == q - gamma2 and w1 == 0)
                    if ( 1 === $hb ) {
                        $hi[] = $n;
                        $count++;
                    }
                }
                $hints[] = $hi;
            }
            if ( ! $ok || $count > self::OMEGA ) {
                continue;
            }

            $sig = $ct;
            foreach ( $z as $p ) {
                $tmp = array();
                foreach ( $p as $v ) {
                    $tmp[] = ( 524288 - $v + 8380417 ) % 8380417; // gamma1 - z, as a 20-bit value
                }
                $sig .= self::pack_bits( $tmp, 20 );
            }
            $hb  = array_fill( 0, self::OMEGA + self::K, 0 );
            $idx = 0;
            foreach ( $hints as $i => $list ) {
                foreach ( $list as $n ) {
                    $hb[ $idx++ ] = $n;
                }
                $hb[ self::OMEGA + $i ] = $idx;
            }
            $sig .= pack( 'C*', ...$hb );
            self::wipe( $rpp );
            $s1h = $s2h = $t0h = $y = $yh = $z = array();
            return $sig;
        }
        throw new \RuntimeException( 'ML-DSA-87 signing did not converge.' ); // probability far below 2^-256
    }

    /** ML-DSA.Verify_internal (Algorithm 8). Never throws for malformed input. */
    public static function verify( string $pk, string $mp, string $sig ): bool {
        if ( strlen( $pk ) !== self::PK_BYTES || strlen( $sig ) !== self::SIG_BYTES ) {
            return false;
        }
        $ct = substr( $sig, 0, 64 );
        // z: 7 x 640 bytes of 20-bit values gamma1 - z.
        $z   = array();
        $g1b = self::GAMMA1 - self::BETA;
        for ( $i = 0; $i < self::L; $i++ ) {
            $raw = self::unpack20( substr( $sig, 64 + 640 * $i, 640 ) );
            $zi  = array();
            foreach ( $raw as $v ) {
                $c = 524288 - $v; // in (-2^19, 2^19]
                if ( $c >= $g1b || -$c >= $g1b ) {
                    return false;
                }
                $zi[] = $c < 0 ? $c + 8380417 : $c;
            }
            $z[] = $zi;
        }
        $h = self::decode_hints( substr( $sig, 64 + 640 * self::L ) );
        if ( null === $h ) {
            return false;
        }

        $rho = substr( $pk, 0, 32 );
        $t1  = array();
        for ( $i = 0; $i < self::K; $i++ ) {
            $t1[] = self::unpack_bits( substr( $pk, 32 + 320 * $i, 320 ), 10 );
        }
        $tr = Keccak::shake256( $pk, 64 );
        $mu = Keccak::shake256( $tr . $mp, 64 );
        $ch = self::ntt( self::sample_in_ball( $ct ) );
        $zh = array();
        foreach ( $z as $p ) {
            $zh[] = self::ntt( $p );
        }
        $a  = self::expand_a( $rho );
        $w1 = array();
        for ( $i = 0; $i < self::K; $i++ ) {
            $t1d = array();
            foreach ( $t1[ $i ] as $v ) {
                $t1d[] = $v << 13;
            }
            $ct1 = self::pointwise( $ch, self::ntt( $t1d ) );
            $az  = self::row_times( $a[ $i ], $zh );
            $d   = array();
            for ( $n = 0; $n < 256; $n++ ) {
                $d[] = $az[ $n ] - $ct1[ $n ] + 8380417;
            }
            $wa   = self::intt( $d );
            $hset = $h[ $i ];
            $u    = array();
            for ( $n = 0; $n < 256; $n++ ) {
                $r   = $wa[ $n ];
                $r1  = ( ( ( ( $r + 127 ) >> 7 ) * 1025 + 2097152 ) >> 22 ) & 15;
                if ( isset( $hset[ $n ] ) ) {
                    $r0  = $r - $r1 * 523776;
                    $r0 -= ( ( 4190208 - $r0 ) >> 31 ) & 8380417;
                    $r1  = ( $r0 > 0 ? $r1 + 1 : $r1 - 1 ) & 15;
                }
                $u[] = $r1;
            }
            $w1[] = $u;
        }
        $ct2 = Keccak::shake256( $mu . self::w1_encode( $w1 ), 64 );
        return hash_equals( $ct, $ct2 );
    }

    /**
     * Whether $sk is the secret key of $pk: well-formed, same rho, and tr inside sk equals SHAKE256(pk, 64).
     */
    public static function key_matches( string $sk, string $pk ): bool {
        if ( strlen( $sk ) !== self::SK_BYTES || strlen( $pk ) !== self::PK_BYTES ) {
            return false;
        }
        if ( null === self::decode_sk( $sk ) ) {
            return false;
        }
        return hash_equals( substr( $pk, 0, 32 ), substr( $sk, 0, 32 ) )
            && hash_equals( Keccak::shake256( $pk, 64 ), substr( $sk, 64, 64 ) );
    }

    /* ------------------------------------------------------------------ NTT */

    /**
     * Forward NTT (Algorithm 41). Accepts coefficients in (-2^31, 2^31); returns them reduced to [0, q).
     *
     * @param int[] $w
     * @return int[]
     */
    private static function ntt( array $w ): array {
        $m = 0;
        for ( $len = 128; $len >= 1; $len >>= 1 ) {
            for ( $start = 0; $start < 256; $start += $len << 1 ) {
                $z   = self::ZETAS[ ++$m ];
                $end = $start + $len;
                for ( $j = $start; $j < $end; $j++ ) {
                    $t              = ( $z * $w[ $j + $len ] ) % 8380417;
                    $w[ $j + $len ] = $w[ $j ] - $t;
                    $w[ $j ]       += $t;
                }
            }
        }
        foreach ( $w as $j => $v ) {
            $v      %= 8380417;
            $w[ $j ] = $v + ( ( $v >> 63 ) & 8380417 );
        }
        return $w;
    }

    /**
     * Inverse NTT (Algorithm 42), including the factor 256^-1. Accepts non-negative coefficients below 2^26
     * (sums of up to eight reduced products); returns them reduced to [0, q).
     *
     * @param int[] $w
     * @return int[]
     */
    private static function intt( array $w ): array {
        $m = 256;
        for ( $len = 1; $len < 256; $len <<= 1 ) {
            for ( $start = 0; $start < 256; $start += $len << 1 ) {
                $z   = self::ZETAS[ --$m ];
                $end = $start + $len;
                for ( $j = $start; $j < $end; $j++ ) {
                    $t              = $w[ $j ];
                    $u              = $w[ $j + $len ];
                    $w[ $j ]        = $t + $u;
                    $w[ $j + $len ] = ( $z * ( $u - $t ) ) % 8380417;
                }
            }
        }
        foreach ( $w as $j => $v ) {
            $v       = ( 8347681 * $v ) % 8380417;
            $w[ $j ] = $v + ( ( $v >> 63 ) & 8380417 );
        }
        return $w;
    }

    /** Coefficient-wise product of two NTT-domain polynomials. */
    private static function pointwise( array $a, array $b ): array {
        $r = array();
        foreach ( $a as $n => $v ) {
            $r[] = ( $v * $b[ $n ] ) % 8380417;
        }
        return $r;
    }

    /** sum_j A[i][j] o v[j] for one matrix row (L = 7 terms), reduced mod q. */
    private static function row_times( array $row, array $v ): array {
        [ $a0, $a1, $a2, $a3, $a4, $a5, $a6 ] = $row;
        [ $v0, $v1, $v2, $v3, $v4, $v5, $v6 ] = $v;
        $r = array();
        for ( $n = 0; $n < 256; $n++ ) {
            $r[] = ( $a0[ $n ] * $v0[ $n ] + $a1[ $n ] * $v1[ $n ] + $a2[ $n ] * $v2[ $n ] + $a3[ $n ] * $v3[ $n ]
                + $a4[ $n ] * $v4[ $n ] + $a5[ $n ] * $v5[ $n ] + $a6[ $n ] * $v6[ $n ] ) % 8380417;
        }
        return $r;
    }

    /* ------------------------------------------------------------------ sampling */

    /** ExpandA (Algorithm 32): A[i][j] = RejNTTPoly(rho || j || i). */
    private static function expand_a( string $rho ): array {
        $a = array();
        for ( $i = 0; $i < self::K; $i++ ) {
            $row = array();
            for ( $j = 0; $j < self::L; $j++ ) {
                $row[] = self::rej_ntt_poly( $rho . chr( $j ) . chr( $i ) );
            }
            $a[] = $row;
        }
        return $a;
    }

    /** RejNTTPoly (Algorithm 30): 23-bit candidates from SHAKE128, kept when below q. */
    private static function rej_ntt_poly( string $seed ): array {
        $xof = new Xof( Keccak::RATE128, $seed );
        $r   = array();
        $n   = 0;
        while ( true ) {
            // 168 bytes = 56 three-byte groups; a zero byte after each group lets unpack read them as 32-bit words.
            foreach ( unpack( 'V*', implode( "\0", str_split( $xof->block(), 3 ) ) . "\0" ) as $v ) {
                $v &= 0x7FFFFF;
                if ( $v < 8380417 ) {
                    $r[] = $v;
                    if ( 256 === ++$n ) {
                        return $r;
                    }
                }
            }
        }
    }

    /** ExpandS (Algorithm 33). Coefficients are returned in [0, q) (negative values as q + v). */
    private static function expand_s( string $rhop ): array {
        $s = array();
        for ( $r = 0; $r < self::L + self::K; $r++ ) {
            $xof  = new Xof( Keccak::RATE256, $rhop . pack( 'v', $r ) );
            $poly = array();
            $n    = 0;
            while ( $n < 256 ) {
                foreach ( unpack( 'C*', $xof->block() ) as $b ) {
                    $lo = $b & 15;
                    $hi = $b >> 4;
                    if ( $lo < 15 ) {
                        $poly[] = self::ETA_COEF[ $lo ];
                        $n++;
                    }
                    if ( $n < 256 && $hi < 15 ) {
                        $poly[] = self::ETA_COEF[ $hi ];
                        $n++;
                    }
                    if ( 256 === $n ) {
                        break;
                    }
                }
            }
            $xof->wipe();
            $s[] = $poly;
        }
        return array( array_slice( $s, 0, self::L ), array_slice( $s, self::L ) );
    }

    /** ExpandMask (Algorithm 34): y in (-gamma1, gamma1], returned as signed ints. */
    private static function expand_mask( string $rpp, int $kappa ): array {
        $y = array();
        for ( $r = 0; $r < self::L; $r++ ) {
            $buf  = Keccak::shake256( $rpp . pack( 'v', $kappa + $r ), 640 );
            $poly = array();
            foreach ( self::unpack20( $buf ) as $v ) {
                $poly[] = 524288 - $v;
            }
            self::wipe( $buf );
            $y[] = $poly;
        }
        return $y;
    }

    /** SampleInBall (Algorithm 29) from the 64-byte commitment hash. Returns coefficients in [0, q). */
    private static function sample_in_ball( string $ct ): array {
        $xof   = new Xof( Keccak::RATE256, $ct );
        $buf   = unpack( 'C*', $xof->block() );
        $signs = unpack( 'P', pack( 'C*', ...array_slice( $buf, 0, 8 ) ) )[1];
        $pos   = 9;
        $c     = array_fill( 0, 256, 0 );
        $bit   = 0;
        for ( $i = 256 - self::TAU; $i < 256; $i++ ) {
            do {
                if ( $pos > Keccak::RATE256 ) {
                    $buf = unpack( 'C*', $xof->block() );
                    $pos = 1;
                }
                $j = $buf[ $pos++ ];
            } while ( $j > $i );
            $c[ $i ] = $c[ $j ];
            $c[ $j ] = ( ( $signs >> $bit ) & 1 ) ? 8380416 : 1;
            $bit++;
        }
        return $c;
    }

    /* ------------------------------------------------------------------ rounding and encodings */

    /** HighBits of every coefficient (in [0, q)). */
    private static function high_bits( array $w ): array {
        $r = array();
        foreach ( $w as $v ) {
            $r[] = ( ( ( ( $v + 127 ) >> 7 ) * 1025 + 2097152 ) >> 22 ) & 15;
        }
        return $r;
    }

    /** w1Encode: 4-bit values, two per byte. */
    private static function w1_encode( array $w1 ): string {
        $out = '';
        foreach ( $w1 as $p ) {
            $b = array();
            for ( $n = 0; $n < 256; $n += 2 ) {
                $b[] = $p[ $n ] | ( $p[ $n + 1 ] << 4 );
            }
            $out .= pack( 'C*', ...$b );
        }
        return $out;
    }

    /** SimpleBitPack of non-negative values below 2^$bits, little-endian bit order. */
    private static function pack_bits( array $vals, int $bits ): string {
        $out = array();
        $acc = 0;
        $n   = 0;
        foreach ( $vals as $v ) {
            $acc |= $v << $n;
            $n   += $bits;
            while ( $n >= 8 ) {
                $out[] = $acc & 0xFF;
                $acc >>= 8;
                $n    -= 8;
            }
        }
        return pack( 'C*', ...$out );
    }

    /** Inverse of pack_bits. */
    private static function unpack_bits( string $bytes, int $bits ): array {
        $out  = array();
        $acc  = 0;
        $n    = 0;
        $mask = ( 1 << $bits ) - 1;
        foreach ( unpack( 'C*', $bytes ) as $b ) {
            $acc |= $b << $n;
            $n   += 8;
            while ( $n >= $bits ) {
                $out[] = $acc & $mask;
                $acc >>= $bits;
                $n    -= $bits;
            }
        }
        return $out;
    }

    /** 640 bytes to 256 values of 20 bits (two per 5-byte group). */
    private static function unpack20( string $bytes ): array {
        $out = array();
        foreach ( unpack( 'P*', implode( "\0\0\0", str_split( $bytes, 5 ) ) . "\0\0\0" ) as $v ) {
            $out[] = $v & 0xFFFFF;
            $out[] = $v >> 20;
        }
        return $out;
    }

    /** s1/s2 packing: eta - s in 3 bits. Input coefficients are signed in [-2, 2]. */
    private static function pack_eta( array $p ): string {
        $tmp = array();
        foreach ( $p as $v ) {
            $tmp[] = 2 - $v;
        }
        return self::pack_bits( $tmp, 3 );
    }

    /**
     * skDecode (Algorithm 25) with the range check noble applies: eta - s must be in [0, 4].
     *
     * @return array|null [ rho, K, tr, s1[], s2[], t0[] ] with s and t0 as signed ints, or null if malformed.
     */
    private static function decode_sk( string $sk ): ?array {
        if ( strlen( $sk ) !== self::SK_BYTES ) {
            return null;
        }
        $off = 128;
        $s   = array();
        for ( $i = 0; $i < self::L + self::K; $i++ ) {
            $poly = array();
            foreach ( self::unpack_bits( substr( $sk, $off, 96 ), 3 ) as $v ) {
                if ( $v > 4 ) {
                    return null;
                }
                $poly[] = 2 - $v;
            }
            $s[]  = $poly;
            $off += 96;
        }
        $t0 = array();
        for ( $i = 0; $i < self::K; $i++ ) {
            $poly = array();
            foreach ( self::unpack_bits( substr( $sk, $off, 416 ), 13 ) as $v ) {
                $poly[] = 4096 - $v;
            }
            $t0[] = $poly;
            $off += 416;
        }
        return array( substr( $sk, 0, 32 ), substr( $sk, 32, 32 ), substr( $sk, 64, 64 ), array_slice( $s, 0, self::L ), array_slice( $s, self::L ), $t0 );
    }

    /**
     * HintBitUnpack (Algorithm 21). Returns, per polynomial, a set of indices (as array keys), or null if malformed:
     * counts must be non-decreasing and at most omega, indices strictly increasing within a polynomial,
     * unused slots zero.
     */
    private static function decode_hints( string $y ): ?array {
        $b     = array_values( unpack( 'C*', $y ) );
        $h     = array();
        $index = 0;
        for ( $i = 0; $i < self::K; $i++ ) {
            $end = $b[ self::OMEGA + $i ];
            if ( $end < $index || $end > self::OMEGA ) {
                return null;
            }
            $set   = array();
            $first = $index;
            for ( ; $index < $end; $index++ ) {
                if ( $index > $first && $b[ $index - 1 ] >= $b[ $index ] ) {
                    return null;
                }
                $set[ $b[ $index ] ] = true;
            }
            $h[] = $set;
        }
        for ( ; $index < self::OMEGA; $index++ ) {
            if ( 0 !== $b[ $index ] ) {
                return null;
            }
        }
        return $h;
    }

    private static function wipe( string &$s ): void {
        if ( function_exists( 'sodium_memzero' ) ) {
            sodium_memzero( $s );
        }
        $s = '';
    }
}
