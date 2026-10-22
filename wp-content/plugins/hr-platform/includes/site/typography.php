<?php
/**
 * House style: no em dashes (MSBJ).
 *
 * The owner's rule is that Headline Recorder never uses an em dash (U+2014, or the horizontal bar U+2015, in any
 * spelling: the character itself or its named, decimal or hex HTML entity). This file:
 *
 *   replace_em_dashes()       context-aware rewrite of em dashes in HTML; only text nodes change
 *   replace_em_dashes_text()  the same for plain text (meta descriptions, document titles, alt text)
 *   on save                   posts, pages and podcast episodes ("Replace em dashes when saving", default on)
 *   on display                text printed by WordPress, the theme and Yoast, including the em dashes wptexturize
 *                             makes from "---" and " -- " ("Replace em dashes on the live site", default on)
 *   Tools > Remove em dashes  counts, previews and rewrites what is already stored, in batches, through
 *                             wp_update_post() so every changed post keeps a revision of its previous text
 *
 * Rules, in the order they are tried for each dash (a run of dashes counts as one):
 *   - at the start of a line or paragraph, or after a full stop (an attribution: "- Reuters"): a plain hyphen;
 *   - at the end of a line: removed;
 *   - a range ("2019-2021", "Rs5-7bn", "FY24-FY25", "July-September") or two place names written without spaces
 *     ("Pakistan-China", "Karachi-Lahore"): a hyphen;
 *   - after a dateline ("KARACHI", "MANILA, PHILIPPINES (16 July 2026)"): a colon, or a comma before a date;
 *   - before closing punctuation, or after a comma, semicolon, colon or opening bracket: removed;
 *   - two dashes around an aside in one sentence: a pair of commas, or brackets when the aside is a list with its
 *     own commas ("three crops (rice, corn and cotton) will");
 *   - any other dash: ", " before a lowercase word, ": " before a capital letter, a digit, a currency sign, a quote or
 *     a list ("sectors: housing, exports and SMEs"), and "; " before a new clause led by a pronoun ("not reform; it
 *     simply postpones"), to avoid a comma splice.
 *
 * Tags, attributes, comments (including block settings), URLs, shortcodes and the contents of code, pre, script,
 * style, textarea, svg and similar elements are never changed. Literal dash characters in this file are written as
 * escapes so the file itself follows the house rule.
 */

namespace HR\Platform\Site;

use function HR\Platform\same_origin_request;
use function HR\Platform\setting;

if ( ! defined( 'ABSPATH' ) ) exit;

/** Post types whose title, content and excerpt are rewritten. */
const EMDASH_POST_TYPES = array( 'post', 'page', 'hr_episode' );

/** Reader-facing text kept in post meta (SEO titles and descriptions, the GEO summary), rewritten with the post. */
const EMDASH_META_KEYS = array(
    '_yoast_wpseo_title',
    '_yoast_wpseo_metadesc',
    '_yoast_wpseo_opengraph-title',
    '_yoast_wpseo_opengraph-description',
    '_yoast_wpseo_twitter-title',
    '_yoast_wpseo_twitter-description',
    '_hr_geo_summary',
);

/** Previous values of fields WordPress does not keep revisions for (meta, and post types without revisions). */
const EMDASH_BACKUP_META = '_hr_emdash_backup';

/** Elements whose text is part of the running sentence (anything else starts a new line for these rules). */
const EMDASH_INLINE_TAGS = array( 'a', 'abbr', 'b', 'bdi', 'bdo', 'cite', 'data', 'del', 'dfn', 'em', 'font', 'i', 'ins', 'label', 'mark', 'q', 's', 'small', 'span', 'strong', 'sub', 'sup', 'time', 'u' );

/** Elements whose contents are left exactly as they are; the inline ones read as a single word. */
const EMDASH_RAW_TAGS    = array( 'script', 'style', 'textarea', 'title', 'xmp', 'iframe', 'noembed', 'noframes' );
const EMDASH_NESTED_TAGS = array( 'pre', 'svg', 'math', 'template', 'code', 'kbd', 'samp', 'var' );
const EMDASH_OPAQUE_TAGS = array( 'code', 'kbd', 'samp', 'var' );

/** Whitespace that may sit around a dash: spaces, tabs, no-break, thin, hair and en/em spaces, raw or as entities. */
const EMDASH_WS = '(?:[ \t\f\x{00A0}\x{2002}\x{2003}\x{2009}\x{200A}\x{202F}]|&(?:nbsp|ensp|emsp|thinsp|hairsp|#0*(?:160|8194|8195|8201|8202|8239)|#[xX]0*(?:[aA]0|200[239aA]|202[fF]));)';

/** An em dash or horizontal bar: the character, or its named, decimal or hex entity. */
const EMDASH_DASH = '(?:\x{2014}|\x{2015}|&(?:mdash|horbar|#0*821[23]|#[xX]0*201[45]);)';

/**
 * Place names that form a compound when joined without spaces ("Pakistan-China talks", "Karachi-Lahore motorway").
 * Deliberately short: in this archive an unspaced dash between two capitalised words is almost always an aside
 * ("the United Arab Emirates{dash}OPEC's third-largest producer{dash}reported"), so only known places are joined.
 */
const EMDASH_PLACES = array( 'afghanistan', 'africa', 'america', 'asia', 'bangladesh', 'beijing', 'china', 'delhi', 'doha', 'dubai', 'eu', 'europe', 'faisalabad', 'gulf', 'gwadar', 'hyderabad', 'india', 'iran', 'iraq', 'islamabad', 'israel', 'japan', 'kabul', 'karachi', 'lahore', 'london', 'moscow', 'multan', 'pakistan', 'peshawar', 'qatar', 'quetta', 'rawalpindi', 'riyadh', 'russia', 'saudi', 'sukkur', 'tehran', 'turkey', 'uae', 'uk', 'us', 'usa', 'washington' );

/** Abbreviations whose full stop does not end a sentence (single capitals cover initials and "U.S."). */
const EMDASH_ABBREVIATIONS = '(?:\\p{Lu}|Mr|Mrs|Ms|Dr|Prof|Sr|Jr|St|Rs|No|Nos|vs|etc|Inc|Ltd|Co|Corp|Gen|Lt|Col|Capt|Sen|Rep|Gov|Govt|Pvt|Dept|approx|est|Jan|Feb|Mar|Apr|Jun|Jul|Aug|Sep|Sept|Oct|Nov|Dec)';

const EMDASH_MONTHS = '(?:jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|june?|july?|aug(?:ust)?|sept?(?:ember)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?|mon(?:day)?|tue(?:sday)?|wed(?:nesday)?|thu(?:rsday)?|fri(?:day)?|sat(?:urday)?|sun(?:day)?)';

// ---------------------------------------------------------------------------------------------------------------
// The replacement
// ---------------------------------------------------------------------------------------------------------------

/** True when the string holds an em dash in any spelling we replace (a byte-level check, cheap enough for every filter call). */
function has_em_dash( string $s ): bool {
    return '' !== $s && 1 === preg_match( '/\xE2\x80[\x94\x95]|&(?:mdash|horbar|#0*821[23]|#[xX]0*201[45]);/', $s );
}

/**
 * Rewrites the em dashes in an HTML fragment (post content, a title, an excerpt) following the house rules above.
 * Only text nodes change. Returns the input unchanged if it is not valid UTF-8.
 *
 * @param array|null $changes Filled with one entry per rewritten dash: before, old, new, after (visible text, for previews).
 */
function replace_em_dashes( string $html, ?array &$changes = null ): string {
    $changes = array();
    if ( ! has_em_dash( $html ) || ! emdash_safe_input( $html ) ) {
        return $html;
    }
    [ $flat, $parts ] = emdash_flatten( emdash_segments( $html ) );
    return emdash_rewrite( $flat, $parts, $changes );
}

/** The same rewrite for plain text, where "<" is just a character. */
function replace_em_dashes_text( string $text, ?array &$changes = null ): string {
    $changes = array();
    if ( ! has_em_dash( $text ) || ! emdash_safe_input( $text ) ) {
        return $text;
    }
    [ $flat, $parts ] = emdash_flatten( array( array( 'text', $text ) ) );
    return emdash_rewrite( $flat, $parts, $changes );
}

/** Valid UTF-8 and free of the private-use characters the rewrite uses as placeholders. */
function emdash_safe_input( string $s ): bool {
    return 1 === preg_match( '//u', $s ) && 0 === preg_match( '/\xEE\x80[\x80-\x9F]/', $s );
}

/**
 * Splits HTML into text and markup. Markup kinds: open / close / void (inline elements that stay inside a sentence),
 * break (everything that ends a line: block tags, <br>, images, comments including block settings, raw elements)
 * and opaque (inline code, which reads as one word). A "<" that does not start markup is text, as in a browser.
 */
function emdash_segments( string $html ): array {
    $out   = array();
    $len   = strlen( $html );
    $pos   = 0;
    $start = 0;
    while ( $pos < $len ) {
        $lt = strpos( $html, '<', $pos );
        if ( false === $lt ) {
            break;
        }
        $markup = emdash_markup_at( $html, $lt );
        if ( null === $markup ) {
            $pos = $lt + 1;
            continue;
        }
        if ( $lt > $start ) {
            $out[] = array( 'text', substr( $html, $start, $lt - $start ) );
        }
        $out[] = array( $markup[0], substr( $html, $lt, $markup[1] - $lt ) );
        $pos   = $markup[1];
        $start = $markup[1];
    }
    if ( $start < $len ) {
        $out[] = array( 'text', substr( $html, $start ) );
    }
    return $out;
}

/** The markup starting at $lt as [kind, end offset], or null when the "<" is a literal character. */
function emdash_markup_at( string $html, int $lt ): ?array {
    $len  = strlen( $html );
    $rest = substr( $html, $lt, 9 );
    if ( str_starts_with( $rest, '<!--' ) ) {
        $end = strpos( $html, '-->', $lt + 4 );
        return array( 'break', false === $end ? $len : $end + 3 );
    }
    if ( str_starts_with( $rest, '<![CDATA[' ) ) {
        $end = strpos( $html, ']]>', $lt + 9 );
        return array( 'break', false === $end ? $len : $end + 3 );
    }
    if ( str_starts_with( $rest, '<!' ) || str_starts_with( $rest, '<?' ) ) {
        $end = strpos( $html, '>', $lt + 2 );
        return array( 'break', false === $end ? $len : $end + 1 );
    }
    if ( ! preg_match( '/\G<(\/?)([a-zA-Z][a-zA-Z0-9:-]*)/', $html, $m, 0, $lt ) ) {
        return null;
    }
    $name = strtolower( $m[2] );
    if ( '/' === $m[1] ) {
        $end = strpos( $html, '>', $lt );
        return array( in_array( $name, EMDASH_INLINE_TAGS, true ) ? 'close' : 'break', false === $end ? $len : $end + 1 );
    }

    // Find the end of the start tag; ">" inside a quoted attribute value does not end it.
    $end      = $len;
    $after_eq = false;
    for ( $i = $lt + strlen( $m[0] ); $i < $len; $i++ ) {
        $c = $html[ $i ];
        if ( $after_eq && ( '"' === $c || "'" === $c ) ) {
            $close = strpos( $html, $c, $i + 1 );
            if ( false === $close ) {
                break;
            }
            $i        = $close;
            $after_eq = false;
            continue;
        }
        if ( '>' === $c ) {
            $end = $i + 1;
            break;
        }
        if ( '=' === $c ) {
            $after_eq = true;
        } elseif ( ! ctype_space( $c ) ) {
            $after_eq = false;
        }
    }
    $self_closing = $end - $lt >= 3 && '/>' === substr( $html, $end - 2, 2 );

    if ( 'plaintext' === $name ) {
        return array( 'break', $len );
    }
    if ( in_array( $name, EMDASH_RAW_TAGS, true ) ) {
        if ( ! preg_match( '/<\/' . $name . '(?=[\s\/>])/i', $html, $c, PREG_OFFSET_CAPTURE, $end ) ) {
            return array( 'break', $len );
        }
        $close = strpos( $html, '>', $c[0][1] );
        return array( 'break', false === $close ? $len : $close + 1 );
    }
    if ( in_array( $name, EMDASH_NESTED_TAGS, true ) && ! $self_closing ) {
        $kind  = in_array( $name, EMDASH_OPAQUE_TAGS, true ) ? 'opaque' : 'break';
        $depth = 1;
        $at    = $end;
        while ( preg_match( '/<(\/?)' . $name . '(?=[\s\/>])/i', $html, $c, PREG_OFFSET_CAPTURE, $at ) ) {
            $depth += '/' === $c[1][0] ? -1 : 1;
            $at     = $c[0][1] + strlen( $c[0][0] );
            if ( 0 === $depth ) {
                $close = strpos( $html, '>', $at );
                return array( $kind, false === $close ? $len : $close + 1 );
            }
        }
        return array( $kind, $len ); // never closed: leave the rest alone
    }
    if ( 'wbr' === $name ) {
        return array( 'void', $end );
    }
    return array( in_array( $name, EMDASH_INLINE_TAGS, true ) ? 'open' : 'break', $end );
}

/**
 * Joins the segments into one string in which every piece of markup, URL and shortcode is a private-use placeholder,
 * so the rules can look across inline tags ("<strong>KARACHI</strong> {dash} Pakistan") without ever touching them.
 * Returns [flat string, list of [kind, original bytes]].
 */
function emdash_flatten( array $segments ): array {
    $parts = array();
    $flat  = '';
    $add   = static function ( string $kind, string $raw ) use ( &$parts ): string {
        $parts[] = array( $kind, $raw );
        return emdash_placeholder( $parts, count( $parts ) - 1 );
    };
    $protect = emdash_protect_pattern();
    foreach ( $segments as [ $kind, $raw ] ) {
        if ( 'text' !== $kind ) {
            $flat .= $add( $kind, $raw );
            continue;
        }
        $flat .= preg_replace_callback( $protect, static fn( $m ) => $add( 'opaque', $m[0] ), $raw );
    }
    return array( $flat, $parts );
}

/** URLs and shortcode tags inside text, which are never changed. */
function emdash_protect_pattern(): string {
    static $cache = array();
    $tags = isset( $GLOBALS['shortcode_tags'] ) && is_array( $GLOBALS['shortcode_tags'] ) ? array_keys( $GLOBALS['shortcode_tags'] ) : array();
    $key  = implode( '|', $tags );
    if ( ! isset( $cache[ $key ] ) ) {
        $alts = array(
            '(?:https?:\/\/|www\.)[^\s<>"\'\x{2014}\x{2015}]+',
            // Generic shortcode shapes: [name], [/name], [name /] and [name key=value ...].
            '\[\[?\/?[a-zA-Z][\w-]*(?:\s*\/|\s+[^\]\[]*=[^\]\[]*)?\]\]?',
        );
        if ( $tags ) {
            $alts[] = '\[\[?\/?(?:' . implode( '|', array_map( static fn( $t ) => preg_quote( (string) $t, '/' ), $tags ) ) . ')(?![\w-])[^\]\[]*\]\]?';
        }
        $cache[ $key ] = '/' . implode( '|', $alts ) . '/u';
    }
    return $cache[ $key ];
}

/**
 * One unit of the flat string at byte offset $pos: [type, byte length, character, placeholder index].
 * Types: end, open, close, void, break, opaque, ws, nl, dash, char. Entities are decoded to their character.
 */
function emdash_unit_at( string $s, int $pos ): array {
    $len = strlen( $s );
    if ( $pos >= $len ) {
        return array( 'end', 0, '', -1 );
    }
    $b = ord( $s[ $pos ] );
    if ( 0xEE === $b && preg_match( '/\G\xEE\x80([\x80-\x84])((?:\xEE\x80[\x90-\x99])+)\xEE\x80\x8F/', $s, $m, 0, $pos ) ) {
        $index = '';
        for ( $i = 2, $n = strlen( $m[2] ); $i < $n; $i += 3 ) {
            $index .= (string) ( ord( $m[2][ $i ] ) - 0x90 );
        }
        $types = array( 'open', 'close', 'void', 'break', 'opaque' );
        return array( $types[ ord( $m[1] ) - 0x80 ], strlen( $m[0] ), '', (int) $index );
    }
    if ( 0x26 === $b && preg_match( '/\G&(?:#[0-9]{1,7}|#[xX][0-9a-fA-F]{1,6}|[a-zA-Z][a-zA-Z0-9]{1,31});/', $s, $m, 0, $pos ) ) {
        $ch = html_entity_decode( $m[0], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        return array( emdash_char_type( $ch ), strlen( $m[0] ), $ch, -1 );
    }
    $n  = $b < 0x80 ? 1 : ( $b >= 0xF0 ? 4 : ( $b >= 0xE0 ? 3 : ( $b >= 0xC0 ? 2 : 1 ) ) );
    $ch = substr( $s, $pos, $n );
    return array( emdash_char_type( $ch ), strlen( $ch ), $ch, -1 );
}

/** The unit that ends at byte offset $pos (see emdash_unit_at), or "bof" at the start. */
function emdash_unit_before( string $s, int $pos ): array {
    if ( $pos <= 0 ) {
        return array( 'bof', 0, '', -1 );
    }
    if ( $pos >= 6 && "\xEE\x80\x8F" === substr( $s, $pos - 3, 3 ) ) {
        $p = $pos - 3;
        while ( $p >= 3 && "\xEE\x80" === substr( $s, $p - 3, 2 ) && ord( $s[ $p - 1 ] ) >= 0x90 && ord( $s[ $p - 1 ] ) <= 0x99 ) {
            $p -= 3;
        }
        $unit = emdash_unit_at( $s, $p - 3 );
        if ( $unit[1] === $pos - $p + 3 ) {
            return $unit;
        }
    }
    if ( ';' === $s[ $pos - 1 ] ) {
        $from = max( 0, $pos - 40 );
        $amp  = strrpos( substr( $s, $from, $pos - $from ), '&' );
        if ( false !== $amp ) {
            $unit = emdash_unit_at( $s, $from + $amp );
            if ( $unit[1] === $pos - $from - $amp ) {
                return $unit;
            }
        }
    }
    $p = $pos - 1;
    while ( $p > 0 && 0x80 === ( ord( $s[ $p ] ) & 0xC0 ) ) {
        $p--;
    }
    $ch = substr( $s, $p, $pos - $p );
    return array( emdash_char_type( $ch ), strlen( $ch ), $ch, -1 );
}

function emdash_char_type( string $ch ): string {
    if ( "\n" === $ch || "\r" === $ch ) {
        return 'nl';
    }
    if ( in_array( $ch, array( ' ', "\t", "\f", "\u{00A0}", "\u{2002}", "\u{2003}", "\u{2009}", "\u{200A}", "\u{202F}" ), true ) ) {
        return 'ws';
    }
    if ( "\u{2014}" === $ch || "\u{2015}" === $ch ) {
        return 'dash';
    }
    return 'char';
}

/**
 * Visible text next to $pos, reading away from it ($dir -1 = left, 1 = right) for up to $max characters, with inline
 * tags skipped, entities decoded and runs of whitespace as one space. Stops at a line break, block tag or the end.
 * Returns [text, reached a line boundary].
 */
function emdash_visible( string $s, int $pos, int $dir, int $max = 80, ?int $limit = null ): array {
    $chars = array();
    $count = 0;
    while ( $count < $max ) {
        if ( null !== $limit && ( $dir > 0 ? $pos >= $limit : $pos <= $limit ) ) {
            return array( implode( '', $dir > 0 ? $chars : array_reverse( $chars ) ), false );
        }
        $unit = $dir > 0 ? emdash_unit_at( $s, $pos ) : emdash_unit_before( $s, $pos );
        $type = $unit[0];
        if ( in_array( $type, array( 'end', 'bof', 'break', 'nl' ), true ) ) {
            return array( implode( '', $dir > 0 ? $chars : array_reverse( $chars ) ), true );
        }
        $pos += $dir * $unit[1];
        if ( in_array( $type, array( 'open', 'close', 'void' ), true ) ) {
            continue;
        }
        if ( 'ws' === $type ) {
            if ( $chars && ' ' !== end( $chars ) ) {
                $chars[] = ' ';
                $count++;
            }
            continue;
        }
        $chars[] = 'opaque' === $type ? "\u{FFFC}" : $unit[2];
        $count++;
    }
    return array( implode( '', $dir > 0 ? $chars : array_reverse( $chars ) ), false );
}

/** The rewrite proper, on the flat string from emdash_flatten(). */
function emdash_rewrite( string $flat, array $parts, ?array &$changes ): string {
    if ( ! preg_match_all( '/' . EMDASH_DASH . '(?:' . EMDASH_WS . '*' . EMDASH_DASH . ')*/u', $flat, $found, PREG_OFFSET_CAPTURE ) ) {
        return emdash_restore( $flat, $parts );
    }

    // 1. Where each dash sits: its surrounding whitespace and inline tags, and the visible text on either side.
    $tokens = array();
    $floor  = 0;
    $runs   = $found[0];
    foreach ( $runs as $i => [ $text, $start ] ) {
        $end     = $start + strlen( $text );
        $ceiling = isset( $runs[ $i + 1 ] ) ? $runs[ $i + 1 ][1] : strlen( $flat );
        $tok     = array( 'text' => $text, 'start' => $start, 'end' => $end, 'l0' => $start, 'r1' => $end, 'ph' => array(), 'lws' => false, 'rws' => false );
        while ( $tok['l0'] > $floor ) {
            $unit = emdash_unit_before( $flat, $tok['l0'] );
            if ( 'ws' === $unit[0] ) {
                $tok['lws'] = true;
            } elseif ( ! in_array( $unit[0], array( 'open', 'close', 'void' ), true ) ) {
                break;
            } else {
                array_unshift( $tok['ph'], $unit[3] );
            }
            $tok['l0'] -= $unit[1];
        }
        while ( $tok['r1'] < $ceiling ) {
            $unit = emdash_unit_at( $flat, $tok['r1'] );
            if ( 'ws' === $unit[0] ) {
                $tok['rws'] = true;
            } elseif ( ! in_array( $unit[0], array( 'open', 'close', 'void' ), true ) ) {
                break;
            } else {
                $tok['ph'][] = $unit[3];
            }
            $tok['r1'] += $unit[1];
        }
        [ $tok['left'], $tok['lbound'] ]   = emdash_visible( $flat, $tok['l0'], -1 );
        [ $tok['right'], $tok['rbound'] ] = emdash_visible( $flat, $tok['r1'], 1 );
        $tok['kind'] = emdash_classify( $tok );
        $tokens[]    = $tok;
        $floor       = $tok['r1'];
    }

    // 2. Two clause dashes in one sentence enclose an aside.
    for ( $i = 0, $n = count( $tokens ); $i < $n - 1; $i++ ) {
        if ( 'clause' !== $tokens[ $i ]['kind'] || 'clause' !== $tokens[ $i + 1 ]['kind'] ) {
            continue;
        }
        [ $inner, $bound ] = emdash_visible( $flat, $tokens[ $i ]['r1'], 1, 300, $tokens[ $i + 1 ]['l0'] );
        $inner = trim( $inner );
        if ( $bound || '' === $inner || mb_strlen( $inner ) > 250 || emdash_has_sentence_end( $inner ) ) {
            continue;
        }
        // Brackets for a list ("rice, corn and cotton"), but not around text that has brackets of its own, and a
        // date's comma ("June 12, 2026") does not make a list.
        $list                     = preg_match( '/;|,\s(?!\d{4}\b)/u', $inner ) && ! preg_match( '/[()\[\]]/u', $inner );
        $mode                     = $list ? 'paren' : 'comma';
        $tokens[ $i ]['kind']     = 'open_' . $mode;
        $tokens[ $i + 1 ]['kind'] = 'close_' . $mode;
        $i++;
    }

    // 3. Write the replacements.
    $out    = '';
    $cursor = 0;
    foreach ( $tokens as $tok ) {
        $kind = $tok['kind'];
        if ( 'hyphen' === $kind ) {
            // Attribution or line-leading dash: only the dash changes, spacing and tags stay as they were.
            [ $from, $to, $new ] = array( $tok['start'], $tok['end'], '-' );
        } else {
            [ $punct, $after ] = match ( $kind ) {
                'join'        => array( '-', '' ),
                'colon'       => array( ':', ' ' ),
                'comma'       => array( ',', ' ' ),
                'space'       => array( '', ' ' ),
                'open_comma', 'close_comma' => array( ',', ' ' ),
                'open_paren'  => array( ' (', '' ),
                'close_paren' => array( ')', ' ' ),
                'clause'      => array( emdash_clause_punct( $tok['right'] ), ' ' ),
                default       => array( '', '' ), // drop, none, trail
            };
            [ $from, $to, $new ] = array( $tok['l0'], $tok['r1'], emdash_emit( $tok['ph'], $parts, $punct, $after ) );
        }
        if ( null !== $changes ) {
            $dash      = (string) preg_replace( '/' . EMDASH_WS . '+/u', ' ', html_entity_decode( $tok['text'], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
            $spaces    = 'hyphen' !== $kind;
            $changes[] = array(
                'before' => emdash_visible( $flat, $from, -1, 60 )[0],
                'old'    => ( $spaces && $tok['lws'] ? ' ' : '' ) . $dash . ( $spaces && $tok['rws'] ? ' ' : '' ),
                'new'    => (string) preg_replace( '/\xEE\x80[\x80-\x84](?:\xEE\x80[\x90-\x99])+\xEE\x80\x8F/', '', $new ),
                'after'  => emdash_visible( $flat, $to, 1, 60 )[0],
                'at'     => strlen( $out ) + $from - $cursor, // where the replacement starts in the output, for its context
                'len'    => strlen( $new ),
            );
        }
        $out   .= substr( $flat, $cursor, $from - $cursor ) . $new;
        $cursor = $to;
    }
    $out .= substr( $flat, $cursor );
    if ( null !== $changes ) {
        // The "after" view reads the rewritten text, so other dashes near this one show as rewritten too.
        foreach ( $changes as &$row ) {
            $row['new_before'] = emdash_visible( $out, $row['at'], -1, 60 )[0];
            $row['new_after']  = emdash_visible( $out, $row['at'] + $row['len'], 1, 60 )[0];
            unset( $row['at'], $row['len'] );
        }
        unset( $row );
    }
    return emdash_restore( $out, $parts );
}

/** Decides what a dash becomes; pairs are found afterwards among the "clause" dashes. */
function emdash_classify( array $tok ): string {
    $left    = rtrim( $tok['left'] );
    $right   = ltrim( $tok['right'] );
    $spaced  = $tok['lws'] || $tok['rws'];
    $prev    = preg_match( '/(.)$/us', $left, $m ) ? $m[1] : '';
    $before  = preg_match( '/(.).$/us', $left, $m ) ? $m[1] : '';
    $next    = preg_match( '/^(.)/us', $right, $m ) ? $m[1] : '';
    $after   = preg_match( '/^.(.)/us', $right, $m ) ? $m[1] : '';

    if ( '' === $left ) {
        return $tok['lbound'] ? 'hyphen' : 'clause';
    }
    if ( '' === $right && $tok['rbound'] ) {
        return 'trail';
    }
    if ( emdash_is_range( $left, $right, $spaced ) ) {
        return 'join';
    }
    if ( $tok['lbound'] && mb_strlen( $left ) <= 80 && preg_match( '/^\p{Lu}{2,}[\p{Lu}\s,.\'\x{2019}-]*(?:\s*\([^()]{1,40}\))?$/u', $left ) ) {
        // Dateline: "KARACHI: Pakistan will...", but "KARACHI, September 30, 2026:".
        return preg_match( '/^(?:\d|' . EMDASH_MONTHS . '(?![\p{L}\p{M}]))/iu', $right ) ? 'comma' : 'colon';
    }
    if ( ! $spaced && preg_match( '/(?<![\p{L}\p{M}])(\p{Lu}[\p{L}\p{M}]*)$/u', $left, $l ) && preg_match( '/^(\p{Lu}[\p{L}\p{M}]*)(?![\p{L}\p{M}\x{2019}\'])/u', $right, $r )
        && in_array( mb_strtolower( $l[1] ), EMDASH_PLACES, true ) && in_array( mb_strtolower( $r[1] ), EMDASH_PLACES, true ) ) {
        return 'join'; // Pakistan-China, Karachi-Lahore
    }
    $closing_quote = static fn( string $q, string $then ): bool => in_array( $q, array( '"', "'" ), true ) && ( '' === $then || 1 === preg_match( '/^[\s\p{P}]$/u', $then ) );
    if ( preg_match( '/^[,.;:!?)\]}\x{2026}\x{201D}\x{2019}]$/u', $next ) || $closing_quote( $next, $after ) ) {
        return 'drop';
    }
    if ( preg_match( '/^[,;:]$/u', $prev ) ) {
        return 'space';
    }
    $opening_quote = in_array( $prev, array( '"', "'" ), true ) && ( '' === $before || ' ' === $before );
    if ( preg_match( '/^[(\[{\x{201C}\x{2018}\x{00AB}]$/u', $prev ) || $opening_quote ) {
        return 'none';
    }
    $ends_sentence = preg_match( '/[.!?\x{2026}]["\'\x{201D}\x{2019})]*$/u', emdash_without_abbreviations( $left ) );
    if ( $ends_sentence && ! preg_match( '/^\p{Ll}/u', $right ) ) {
        return 'hyphen'; // "...he said. - APP", a closing quote followed by the speaker's name
    }
    return 'clause';
}

function emdash_is_range( string $left, string $right, bool $spaced ): bool {
    if ( preg_match( '/(?<!\d)(?:1[89]|20)\d\d$/', $left ) && preg_match( '/^(?:1[89]|20)\d\d(?!\d)/', $right ) ) {
        return true; // 2019-2021, spaced or not
    }
    $month = '(?<![\p{L}\p{M}])' . EMDASH_MONTHS;
    if ( preg_match( '/' . $month . '\.?$/iu', $left ) && preg_match( '/^' . EMDASH_MONTHS . '(?![\p{L}\p{M}])/iu', $right ) ) {
        return true; // July-September, Monday-Friday
    }
    return ! $spaced
        && 1 === preg_match( '/\d(?:[.,]\d+)*(?:%|bn|mn|m|k|tr|pc|cr|s|x|th|st|nd|rd)?$/u', $left )
        && 1 === preg_match( '/^(?:(?:Rs|PKR|USD|US\$|\$|\x{20AC}|\x{00A3}|FY|CY|Q|H)\.?\s?)?\d/u', $right );
}

/** ", " before a lowercase word; ": " before a capital, digit, currency sign or opening quote; "; " before "it is", "they have"... */
function emdash_clause_punct( string $right ): string {
    $right = ltrim( $right );
    if ( preg_match( '/^[\p{Lu}\p{Lt}\p{N}\p{Sc}"\x{201C}\x{2018}\x{00AB}\x{201E}]/u', $right ) || preg_match( "/^'\\p{L}/u", $right ) ) {
        return ':';
    }
    $verbs = 'is|are|was|were|has|have|had|will|would|can|could|should|may|might|must|does|do|did|cannot|isn\'t|aren\'t|wasn\'t|doesn\'t|don\'t|won\'t|can\'t';
    if ( preg_match( '/^(?:(?:it|they|we|he|she|you)\s+(?:\p{Ll}+ly\s+)?(?:' . $verbs . ')\b|(?:it|he|she)\s+(?:\p{Ll}+ly\s+)?\p{Ll}+s\b|(?:it|they|we|he|she|you)[\'\x{2019}](?:s|re|ll|ve|d)\b)/u', str_replace( "\u{2019}", "'", $right ) ) ) {
        return ';'; // a second independent clause: a comma here would be a comma splice
    }
    // A list of short items: "housing, exports, technology and SMEs", but not an aside that holds one ("including
    // rice, corn and cotton", "which covers X, Y and Z", "a suite of A, B and C").
    $lead = 'a|an|the|including|includes|included|such|which|who|whose|where|when|while|though|although|by|from|with|without|for|of|in|on|at|to|as|like|and|or|but|nor|especially|particularly|namely|notably|mainly|mostly|largely|primarily|specifically|plus|even|not|only|also';
    if ( preg_match( '/^(?!(?:' . $lead . ')\b)\p{Ll}[\p{L}\p{M}-]*(?: [\p{L}\p{M}-]+){0,2}, [^,.;:!?()]{1,30}, [^.;:!?()]*?\b(?:and|or)\b/u', $right ) ) {
        return ':'; // a list follows ("sectors: housing, exports, technology and SMEs"); a comma would merge it into the sentence
    }
    return ',';
}

/** True when the text holds a full stop, question or exclamation mark that ends a sentence (not "U.S." or "Dr."). */
function emdash_has_sentence_end( string $text ): bool {
    return 1 === preg_match( '/[.!?\x{2026}]["\'\x{201D}\x{2019})\]]*(?:\s|$)/u', emdash_without_abbreviations( $text ) );
}

/** The text with the full stops of abbreviations and initials removed. */
function emdash_without_abbreviations( string $text ): string {
    return (string) preg_replace( '/(?<![\p{L}\p{M}])' . EMDASH_ABBREVIATIONS . '\K\./u', '', $text );
}

/**
 * The replacement text with the inline tags that sat around the dash kept: closing tags go before the punctuation
 * and opening tags after the space ("billion</strong>, well"), unless that would reorder a tag pair.
 */
function emdash_emit( array $ph, array $parts, string $punct, string $after ): string {
    $kinds   = array_map( static fn( $i ) => $parts[ $i ][0], $ph );
    $encoded = array();
    foreach ( $ph as $i ) {
        $encoded[ $i ] = emdash_placeholder( $parts, $i );
    }
    $first_open = array_search( 'open', $kinds, true );
    $ordered    = false === $first_open || ! in_array( 'close', array_slice( $kinds, (int) $first_open ), true );
    if ( ! $ordered ) {
        return implode( '', $encoded ) . $punct . $after;
    }
    $closers = '';
    $openers = '';
    foreach ( $ph as $n => $i ) {
        if ( 'open' === $kinds[ $n ] ) {
            $openers .= $encoded[ $i ];
        } else {
            $closers .= $encoded[ $i ];
        }
    }
    return $closers . $punct . $after . $openers;
}

function emdash_placeholder( array $parts, int $i ): string {
    $types  = array( 'open' => 0, 'close' => 1, 'void' => 2, 'break' => 3, 'opaque' => 4 );
    $digits = '';
    foreach ( str_split( (string) $i ) as $d ) {
        $digits .= "\xEE\x80" . chr( 0x90 + (int) $d );
    }
    return "\xEE\x80" . chr( 0x80 + $types[ $parts[ $i ][0] ] ) . $digits . "\xEE\x80\x8F";
}

/** Puts the original markup, URLs and shortcodes back. */
function emdash_restore( string $flat, array $parts ): string {
    return (string) preg_replace_callback(
        '/\xEE\x80[\x80-\x84]((?:\xEE\x80[\x90-\x99])+)\xEE\x80\x8F/',
        static function ( $m ) use ( $parts ) {
            $index = '';
            for ( $i = 2, $n = strlen( $m[1] ); $i < $n; $i += 3 ) {
                $index .= (string) ( ord( $m[1][ $i ] ) - 0x90 );
            }
            return $parts[ (int) $index ][1] ?? '';
        },
        $flat
    );
}

// ---------------------------------------------------------------------------------------------------------------
// Settings (Settings > Headline Recorder). A later priority than site/settings.php, so the fields join its
// "site" section if one exists and otherwise get a section of their own.
// ---------------------------------------------------------------------------------------------------------------

add_filter( 'hr_settings_schema', function ( array $schema ): array {
    $fields = array(
        'emdash_on_save'    => array(
            'label'       => 'Replace em dashes when saving',
            'type'        => 'checkbox',
            'default'     => 1,
            'description' => 'Rewrites em dashes in the headline, text and excerpt of articles, pages and podcast episodes, and in their SEO title, meta description and summary, each time they are saved. Restoring an earlier revision brings that version back exactly as it was.',
        ),
        'emdash_on_display' => array(
            'label'       => 'Replace em dashes on the live site',
            'type'        => 'checkbox',
            'default'     => 1,
            'description' => 'Covers text that WordPress and plugins print as pages are built: dashes typed as "---" or " -- ", headlines, menus, widgets, comments, image alt text and the titles and descriptions shown in search results. This does not change what is stored.',
        ),
    );
    if ( isset( $schema['site']['fields'] ) && is_array( $schema['site']['fields'] ) ) {
        $schema['site']['fields'] += $fields;
        return $schema;
    }
    $schema['typography'] = array(
        'title'       => 'House style',
        'description' => sprintf(
            'Headline Recorder does not use em dashes. To rewrite the ones already stored in published articles, use <a href="%s">Tools &gt; Remove em dashes</a>.',
            esc_url( admin_url( 'tools.php?page=hr-em-dashes' ) )
        ),
        'fields'      => $fields,
    );
    return $schema;
}, 20 );

// ---------------------------------------------------------------------------------------------------------------
// On save
// ---------------------------------------------------------------------------------------------------------------

add_filter( 'wp_insert_post_data', __NAMESPACE__ . '\\emdash_on_insert', 20, 2 );

/** wp_insert_post_data: the title, content and excerpt arrive slashed and leave slashed. */
function emdash_on_insert( $data, $postarr = array() ) {
    if ( ! is_array( $data ) || ! in_array( $data['post_type'] ?? '', EMDASH_POST_TYPES, true ) || in_array( $data['post_status'] ?? '', array( 'auto-draft', 'trash' ), true ) ) {
        return $data;
    }
    $fields = array_filter( array( 'post_title', 'post_content', 'post_excerpt' ), static fn( $f ) => isset( $data[ $f ] ) && is_string( $data[ $f ] ) && has_em_dash( $data[ $f ] ) );
    if ( ! $fields || empty( setting( 'emdash_on_save' ) ) || emdash_restoring_revision() ) {
        return $data;
    }
    foreach ( $fields as $field ) {
        $data[ $field ] = wp_slash( replace_em_dashes( wp_unslash( $data[ $field ] ) ) );
    }
    return $data;
}

// SEO titles, meta descriptions and summaries (sanitize_meta passes them unslashed). Only the generic hook: adding a
// "_for_{post type}" variant would stop WordPress running the generic one, which Yoast's own sanitising uses.
foreach ( EMDASH_META_KEYS as $hr_emdash_key ) {
    add_filter( "sanitize_post_meta_{$hr_emdash_key}", __NAMESPACE__ . '\\emdash_on_meta_save', 20, 4 );
}
unset( $hr_emdash_key );

function emdash_on_meta_save( $value, $key = '', $type = '', $subtype = '' ) {
    if ( ! is_string( $value ) || ! has_em_dash( $value ) || ( '' !== $subtype && ! in_array( $subtype, EMDASH_POST_TYPES, true ) ) || empty( setting( 'emdash_on_save' ) ) ) {
        return $value;
    }
    return replace_em_dashes_text( $value );
}

/** True while WordPress restores a revision: that is an undo, so it must bring the old text back unchanged. */
function emdash_restoring_revision(): bool {
    foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 25 ) as $frame ) { // phpcs:ignore PHPCompatibility -- only reached when the text has an em dash
        if ( 'wp_restore_post_revision' === ( $frame['function'] ?? '' ) && ! isset( $frame['class'] ) ) {
            return true;
        }
    }
    return false;
}

// ---------------------------------------------------------------------------------------------------------------
// On display: what WordPress, the theme and plugins print. wptexturize (priority 10) turns "---" and " -- " into
// em dash entities; these filters run after it. wp-admin keeps showing the stored text so editors see what is saved.
// ---------------------------------------------------------------------------------------------------------------

function emdash_display_on(): bool {
    return ! empty( setting( 'emdash_on_display' ) );
}

function emdash_front_end(): bool {
    return ( ! is_admin() || wp_doing_ajax() ) && emdash_display_on();
}

function emdash_display_html( $value ) {
    return is_string( $value ) && has_em_dash( $value ) && emdash_front_end() ? replace_em_dashes( $value ) : $value;
}

function emdash_display_text( $value ) {
    return is_string( $value ) && has_em_dash( $value ) && emdash_front_end() ? replace_em_dashes_text( $value ) : $value;
}

foreach ( array(
    'the_content', 'the_excerpt', 'get_the_excerpt', 'the_title', 'single_post_title', 'single_cat_title', 'single_tag_title',
    'single_term_title', 'get_the_archive_title', 'get_the_archive_description', 'term_description', 'the_post_thumbnail_caption',
    'nav_menu_item_title', 'nav_menu_description', 'widget_title', 'widget_text', 'widget_text_content', 'widget_block_content',
    'widget_custom_html_content', 'comment_text', 'comment_excerpt', 'comment_text_rss',
) as $hr_emdash_hook ) {
    add_filter( $hr_emdash_hook, __NAMESPACE__ . '\\emdash_display_html', 50 );
}
foreach ( array(
    'document_title', 'wpseo_title', 'wpseo_opengraph_title', 'wpseo_twitter_title', 'wpseo_metadesc', 'wpseo_opengraph_desc',
    'wpseo_twitter_description', 'wpseo_opengraph_site_name',
) as $hr_emdash_hook ) {
    add_filter( $hr_emdash_hook, __NAMESPACE__ . '\\emdash_display_text', 50 );
}
unset( $hr_emdash_hook );

add_filter( 'document_title_parts', static fn( $parts ) => is_array( $parts ) ? array_map( __NAMESPACE__ . '\\emdash_display_text', $parts ) : $parts, 50 );

add_filter( 'bloginfo', static function ( $value, $show = '' ) {
    return in_array( $show, array( 'name', 'description' ), true ) ? emdash_display_text( $value ) : $value;
}, 50, 2 );

// Image alt text printed by wp_get_attachment_image() and the featured image (after alt-text.php's fallback at 20).
add_filter( 'wp_get_attachment_image_attributes', static function ( $attr ) {
    if ( is_array( $attr ) && isset( $attr['alt'] ) ) {
        $attr['alt'] = emdash_display_text( $attr['alt'] );
    }
    return $attr;
}, 30 );

// Structured data: names, headlines, descriptions and captions are reader-facing text too.
add_filter( 'wpseo_schema_graph', static function ( $graph ) {
    if ( ! is_array( $graph ) || ! emdash_front_end() ) {
        return $graph;
    }
    array_walk_recursive( $graph, static function ( &$value, $key ) {
        if ( is_string( $value ) && in_array( $key, array( 'name', 'headline', 'alternativeHeadline', 'description', 'caption', 'abstract' ), true ) && has_em_dash( $value ) ) {
            $value = replace_em_dashes_text( $value );
        }
    } );
    return $graph;
}, 50 );

// llms.txt is written by Yoast in the background, so this one does not depend on being on the front end.
add_filter( 'wpseo_llmstxt_link_description', static fn( $text ) => is_string( $text ) && has_em_dash( $text ) && emdash_display_on() ? replace_em_dashes_text( $text ) : $text, 50 );

// Title separators: Yoast's "Em dash" choice (sc-mdash) and any theme that sets one for wp_get_document_title().
$hr_emdash_separator = static fn( $sep ) => is_string( $sep ) && has_em_dash( $sep ) && emdash_display_on() ? '-' : $sep;
add_filter( 'wpseo_replacements_filter_sep', $hr_emdash_separator, 50 );
add_filter( 'document_title_separator', $hr_emdash_separator, 50 );
add_filter( 'wpseo_breadcrumb_separator', static fn( $sep ) => is_string( $sep ) && has_em_dash( $sep ) && emdash_display_on() ? '&raquo;' : $sep, 50 );
unset( $hr_emdash_separator );

// ---------------------------------------------------------------------------------------------------------------
// Tools > Remove em dashes: counts, a preview, and a batched rewrite of what is already stored.
// ---------------------------------------------------------------------------------------------------------------

/** Every spelling of the dash the database is searched for, assembled so this file contains none of them. */
function emdash_like_forms(): array {
    return array( "\u{2014}", "\u{2015}", '&' . 'mdash;', '&' . 'horbar;', '&#' . '8212;', '&#' . '8213;', '&#x' . '2014;', '&#x' . '2015;' );
}

/** "( column LIKE %s OR ... )" for every spelling; appends the LIKE arguments to $args. Columns are fixed names. */
function emdash_sql_match( string $column, array &$args ): string {
    global $wpdb;
    $or = array();
    foreach ( emdash_like_forms() as $form ) {
        $or[]   = "{$column} LIKE %s";
        $args[] = '%' . $wpdb->esc_like( $form ) . '%';
    }
    return '(' . implode( ' OR ', $or ) . ')';
}

function emdash_placeholders( array $values ): string {
    return implode( ', ', array_fill( 0, count( $values ), '%s' ) );
}

/**
 * IDs of everything the tool may change, in ID order: published posts, pages and episodes with an em dash in their
 * title, content, excerpt or reader-facing meta, and images whose alt text has one.
 */
function emdash_candidate_ids( int $after = 0, int $limit = 0, string $order = 'ASC' ): array {
    global $wpdb;
    $args  = array();
    $core  = emdash_sql_match( 'p.post_title', $args ) . ' OR ' . emdash_sql_match( 'p.post_content', $args ) . ' OR ' . emdash_sql_match( 'p.post_excerpt', $args );
    $args  = array_merge( $args, EMDASH_POST_TYPES );
    $sql   = "SELECT p.ID FROM {$wpdb->posts} p WHERE ( {$core} ) AND p.post_status = 'publish' AND p.post_type IN (" . emdash_placeholders( EMDASH_POST_TYPES ) . ')';
    $margs = array();
    $meta  = emdash_sql_match( 'm.meta_value', $margs );
    $sql  .= " UNION SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID WHERE {$meta}"
        . ' AND p.post_status = \'publish\' AND p.post_type IN (' . emdash_placeholders( EMDASH_POST_TYPES ) . ') AND m.meta_key IN (' . emdash_placeholders( EMDASH_META_KEYS ) . ')';
    $args  = array_merge( $args, $margs, EMDASH_POST_TYPES, EMDASH_META_KEYS );
    $aargs = array();
    $alt   = emdash_sql_match( 'm.meta_value', $aargs );
    $sql  .= " UNION SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID WHERE {$alt}"
        . " AND p.post_type = 'attachment' AND m.meta_key = '_wp_attachment_image_alt'";
    $args  = array_merge( $args, $aargs );
    $sql   = "SELECT ID FROM ( {$sql} ) hr_emdash WHERE ID > %d ORDER BY ID " . ( 'DESC' === $order ? 'DESC' : 'ASC' );
    $args[] = $after;
    if ( $limit > 0 ) {
        $sql   .= ' LIMIT %d';
        $args[] = $limit;
    }
    return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( $sql, $args ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders only
}

/** Published totals and em dash counts per post type, for the screen. */
function emdash_counts(): array {
    global $wpdb;
    $types = EMDASH_POST_TYPES;
    $out   = array();
    foreach ( $types as $type ) {
        $out[ $type ] = array( 'published' => 0, 'any' => 0, 'title' => 0, 'content' => 0, 'excerpt' => 0, 'meta' => 0 );
    }
    foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT post_type, COUNT(*) AS n FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN (" . emdash_placeholders( $types ) . ') GROUP BY post_type', $types ) ) as $row ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $out[ $row->post_type ]['published'] = (int) $row->n;
    }
    $args = array();
    $t    = emdash_sql_match( 'post_title', $args );
    $c    = emdash_sql_match( 'post_content', $args );
    $e    = emdash_sql_match( 'post_excerpt', $args );
    $args = array_merge( $args, $args, $types );
    $rows = $wpdb->get_results( $wpdb->prepare( "SELECT post_type, {$t} AS t, {$c} AS c, {$e} AS e FROM {$wpdb->posts} WHERE ( {$t} OR {$c} OR {$e} ) AND post_status = 'publish' AND post_type IN (" . emdash_placeholders( $types ) . ')', $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    foreach ( (array) $rows as $row ) {
        $out[ $row->post_type ]['any']++;
        $out[ $row->post_type ]['title']   += (int) $row->t;
        $out[ $row->post_type ]['content'] += (int) $row->c;
        $out[ $row->post_type ]['excerpt'] += (int) $row->e;
    }
    $margs = array();
    $m     = emdash_sql_match( 'm.meta_value', $margs );
    $margs = array_merge( $margs, $types, EMDASH_META_KEYS );
    $rows  = $wpdb->get_results( $wpdb->prepare( "SELECT p.post_type, COUNT( DISTINCT p.ID ) AS n FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID WHERE {$m} AND p.post_status = 'publish' AND p.post_type IN (" . emdash_placeholders( $types ) . ') AND m.meta_key IN (' . emdash_placeholders( EMDASH_META_KEYS ) . ') GROUP BY p.post_type', $margs ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    foreach ( (array) $rows as $row ) {
        $out[ $row->post_type ]['meta'] = (int) $row->n;
    }
    $aargs = array();
    $a     = emdash_sql_match( 'm.meta_value', $aargs );
    $out['attachment'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT( DISTINCT m.post_id ) FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE {$a} AND p.post_type = 'attachment' AND m.meta_key = '_wp_attachment_image_alt'", $aargs ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    return $out;
}

function emdash_field_label( string $field ): string {
    $labels = array(
        'post_title'                         => 'Headline',
        'post_content'                       => 'Text',
        'post_excerpt'                       => 'Excerpt',
        '_yoast_wpseo_title'                 => 'SEO title',
        '_yoast_wpseo_metadesc'              => 'Meta description',
        '_yoast_wpseo_opengraph-title'       => 'Social title',
        '_yoast_wpseo_opengraph-description' => 'Social description',
        '_yoast_wpseo_twitter-title'         => 'X title',
        '_yoast_wpseo_twitter-description'   => 'X description',
        '_hr_geo_summary'                    => 'Summary',
        '_wp_attachment_image_alt'           => 'Alt text',
    );
    return $labels[ $field ] ?? $field;
}

/**
 * What the tool would change on one post: ['post', 'fields' => [field => [old, new]], 'meta' => [key => [old, new]],
 * 'changes' => preview rows]. Null for anything outside the tool's scope.
 */
function emdash_plan( int $post_id ): ?array {
    $post = get_post( $post_id );
    if ( ! $post instanceof \WP_Post ) {
        return null;
    }
    $fields  = array();
    $meta    = array();
    $changes = array();
    if ( 'attachment' === $post->post_type ) {
        $keys = array( '_wp_attachment_image_alt' );
    } elseif ( in_array( $post->post_type, EMDASH_POST_TYPES, true ) && 'publish' === $post->post_status ) {
        $keys = EMDASH_META_KEYS;
        foreach ( array( 'post_title', 'post_content', 'post_excerpt' ) as $field ) {
            $old = (string) $post->$field;
            $new = replace_em_dashes( $old, $rows );
            if ( $new !== $old ) {
                $fields[ $field ] = array( $old, $new );
                foreach ( $rows as $row ) {
                    $changes[] = array( 'field' => emdash_field_label( $field ) ) + $row;
                }
            }
        }
    } else {
        return null;
    }
    foreach ( $keys as $key ) {
        $old = get_post_meta( $post_id, $key, true );
        if ( ! is_string( $old ) || '' === $old ) {
            continue;
        }
        $new = replace_em_dashes_text( $old, $rows );
        if ( $new !== $old ) {
            $meta[ $key ] = array( $old, $new );
            foreach ( $rows as $row ) {
                $changes[] = array( 'field' => emdash_field_label( $key ) ) + $row;
            }
        }
    }
    return array( 'post' => $post, 'fields' => $fields, 'meta' => $meta, 'changes' => $changes );
}

/** Keeps the first stored value of each field, so running the tool twice never overwrites the original. */
function emdash_backup( int $post_id, array $values ): void {
    $existing = get_post_meta( $post_id, EMDASH_BACKUP_META, true );
    $existing = is_array( $existing ) ? $existing : array();
    $fields   = (array) ( $existing['fields'] ?? array() ) + $values;
    update_post_meta( $post_id, EMDASH_BACKUP_META, wp_slash( array( 'saved' => (int) ( $existing['saved'] ?? time() ), 'fields' => $fields ) ) );
}

/**
 * Rewrites one post. Returns changed, unchanged, locked (open in the editor), skipped (out of scope) or failed.
 * Title, content and excerpt go through wp_update_post(), so WordPress keeps a revision of the previous text and
 * every save hook runs (cache purge, SEO index, the signing module's change detection).
 */
function emdash_apply_post( int $post_id ): string {
    $plan = emdash_plan( $post_id );
    if ( null === $plan ) {
        return 'skipped';
    }
    if ( ! $plan['fields'] && ! $plan['meta'] ) {
        return 'unchanged';
    }
    $post = $plan['post'];
    if ( 'attachment' !== $post->post_type ) {
        if ( ! function_exists( 'wp_check_post_lock' ) ) {
            require_once ABSPATH . 'wp-admin/includes/post.php';
        }
        if ( wp_check_post_lock( $post_id ) ) {
            return 'locked';
        }
    }

    // Keep the previous text: a revision where WordPress keeps them, otherwise a backup on the post itself.
    $backup = array_map( static fn( $pair ) => $pair[0], $plan['meta'] );
    if ( $plan['fields'] ) {
        if ( post_type_supports( $post->post_type, 'revisions' ) && wp_revisions_enabled( $post ) ) {
            wp_save_post_revision( $post_id ); // only adds one if the latest revision differs from the stored post
        } else {
            $backup += array_map( static fn( $pair ) => $pair[0], $plan['fields'] );
        }
    }
    if ( $backup ) {
        emdash_backup( $post_id, $backup );
    }

    foreach ( $plan['meta'] as $key => $pair ) {
        update_post_meta( $post_id, $key, wp_slash( $pair[1] ) );
    }
    if ( $plan['fields'] ) {
        $update = array( 'ID' => $post_id );
        foreach ( $plan['fields'] as $field => $pair ) {
            $update[ $field ] = $pair[1];
        }
        // Only punctuation changes. Without this, a user without unfiltered_html would have kses strip embeds and
        // scripts that an administrator added to the post.
        $kses = false !== has_filter( 'content_save_pre', 'wp_filter_post_kses' );
        if ( $kses ) {
            kses_remove_filters();
        }
        $result = wp_update_post( wp_slash( $update ), true );
        if ( $kses ) {
            kses_init_filters();
        }
        if ( ! $result || is_wp_error( $result ) ) {
            return 'failed';
        }
    }
    clean_post_cache( $post_id );
    if ( ! $plan['fields'] ) {
        do_action( 'litespeed_purge_post', $post_id ); // only meta changed, so no save hook purged the cached page
    }
    return 'changed';
}

/** One batch of the rewrite: the next $size candidates after ID $after, within about 20 seconds. */
function emdash_apply_batch( int $after, int $size = 10 ): array {
    $ids     = emdash_candidate_ids( $after, $size );
    $out     = array( 'processed' => 0, 'changed' => 0, 'unchanged' => 0, 'locked' => array(), 'failed' => array(), 'next' => $after, 'done' => false );
    $started = microtime( true );
    $item    = static fn( int $id ) => array(
        'id'    => $id,
        'title' => html_entity_decode( wp_strip_all_tags( get_the_title( $id ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ?: sprintf( '#%d', $id ),
        'edit'  => esc_url_raw( (string) get_edit_post_link( $id, 'raw' ) ),
    );
    foreach ( $ids as $id ) {
        $result = emdash_apply_post( $id );
        $out['processed']++;
        $out['next'] = $id;
        if ( 'changed' === $result ) {
            $out['changed']++;
        } elseif ( 'locked' === $result || 'failed' === $result ) {
            $out[ $result ][] = $item( $id );
        } else {
            $out['unchanged']++;
        }
        if ( microtime( true ) - $started > 20 ) {
            break;
        }
    }
    $out['done'] = count( $ids ) < $size && $out['processed'] === count( $ids );
    if ( $out['done'] ) {
        delete_transient( 'hr_llms_full' ); // /llms-full.txt quotes titles and summaries; rebuild it from the new text
    }
    return $out;
}

add_action( 'admin_menu', function () {
    add_management_page( 'Remove em dashes', 'Remove em dashes', 'manage_options', 'hr-em-dashes', __NAMESPACE__ . '\\emdash_screen' );
} );

add_action( 'wp_ajax_hr_emdash_apply', function () {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'You are not allowed to do this.' ), 403 );
    }
    if ( ! check_ajax_referer( 'hr_emdash_apply', 'nonce', false ) || ! same_origin_request() ) {
        wp_send_json_error( array( 'message' => 'This page has expired. Reload it and try again.' ), 403 );
    }
    $after = isset( $_POST['after'] ) ? absint( wp_unslash( $_POST['after'] ) ) : 0;
    $size  = isset( $_POST['size'] ) ? min( 25, max( 1, absint( wp_unslash( $_POST['size'] ) ) ) ) : 10;
    if ( function_exists( 'set_time_limit' ) ) {
        @set_time_limit( 60 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- not allowed on some hosts
    }
    wp_send_json_success( emdash_apply_batch( $after, $size ) );
} );

/** "...before" + [old -> new] + "after..." as escaped HTML for the preview. */
function emdash_diff_html( array $row, bool $after ): string {
    $before = (string) ( $after ? ( $row['new_before'] ?? $row['before'] ) : $row['before'] );
    $next   = (string) ( $after ? ( $row['new_after'] ?? $row['after'] ) : $row['after'] );
    $mark   = $after ? '<ins>' . esc_html( (string) $row['new'] ) . '</ins>' : '<del>' . esc_html( (string) $row['old'] ) . '</del>';
    return '&hellip;' . esc_html( ltrim( mb_substr( $before, -60 ), ' ,' ) ) . $mark . esc_html( mb_substr( $next, 0, 60 ) ) . '&hellip;';
}

function emdash_screen(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $counts   = emdash_counts();
    $labels   = array( 'post' => 'Articles', 'page' => 'Pages', 'hr_episode' => 'Podcast episodes' );
    $preview  = array();
    $to_change = 0;
    $dashes   = 0;
    $total    = 0;
    foreach ( emdash_candidate_ids( 0, 0, 'DESC' ) as $id ) {
        $total++;
        $plan = emdash_plan( $id );
        if ( ! $plan || ! $plan['changes'] ) {
            continue;
        }
        $to_change++;
        $dashes += count( $plan['changes'] );
        if ( count( $preview ) < 20 ) {
            $preview[] = $plan;
        }
    }
    ?>
    <div class="wrap hr-emdash">
        <h1>Remove em dashes</h1>
        <p class="hr-emdash-lead">
            Headline Recorder&rsquo;s house style does not use em dashes. This tool rewrites the ones already stored in published
            articles, pages and podcast episodes (headline, text, excerpt, SEO title, meta description and summary) and in image alt text.
            New and edited posts are handled when they are saved, and text that WordPress or plugins add is handled as pages are shown:
            both switches are under <a href="<?php echo esc_url( admin_url( 'options-general.php?page=hr-platform' ) ); ?>">Settings &gt; Headline Recorder</a>.
        </p>

        <h2>How each dash is rewritten</h2>
        <ul class="hr-emdash-rules">
            <li>Ranges of numbers, years or months become a hyphen, as in 2019-2021, Rs5-7bn or July-September.</li>
            <li>A pair of dashes around an aside becomes a pair of commas, or brackets when the aside is a list with commas of its own.</li>
            <li>A single dash becomes a comma before a lowercase word, and a colon before a capital letter, a number or a quotation. Before a new clause such as &ldquo;it is&rdquo; it becomes a semicolon.</li>
            <li>A dateline such as KARACHI is followed by a colon. A dash at the start of a line, such as the name under a quotation, becomes a hyphen.</li>
            <li>Links, image details, embeds, code, shortcodes and block settings are never changed.</li>
        </ul>

        <h2>What is stored now</h2>
        <div class="hr-emdash-scroll" role="region" aria-label="Counts" tabindex="0">
        <table class="widefat striped hr-emdash-counts">
            <thead>
                <tr><th scope="col">Published</th><th scope="col">Total</th><th scope="col">With em dashes</th><th scope="col">In headline</th><th scope="col">In text</th><th scope="col">In excerpt</th><th scope="col">In SEO fields or summary</th></tr>
            </thead>
            <tbody>
                <?php foreach ( $labels as $type => $label ) : $row = $counts[ $type ]; ?>
                    <tr>
                        <th scope="row"><?php echo esc_html( $label ); ?></th>
                        <td><?php echo esc_html( number_format_i18n( $row['published'] ) ); ?></td>
                        <td><?php echo esc_html( number_format_i18n( $row['any'] ) ); ?></td>
                        <td><?php echo esc_html( number_format_i18n( $row['title'] ) ); ?></td>
                        <td><?php echo esc_html( number_format_i18n( $row['content'] ) ); ?></td>
                        <td><?php echo esc_html( number_format_i18n( $row['excerpt'] ) ); ?></td>
                        <td><?php echo esc_html( number_format_i18n( $row['meta'] ) ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <p>
            <?php
            echo esc_html( sprintf(
                'Images with an em dash in their alt text: %1$s. In all, %2$s %3$s will change (%4$s %5$s).',
                number_format_i18n( $counts['attachment'] ),
                number_format_i18n( $to_change ),
                1 === $to_change ? 'item' : 'items',
                number_format_i18n( $dashes ),
                1 === $dashes ? 'dash' : 'dashes'
            ) );
            if ( $total > $to_change ) {
                echo ' ' . esc_html( sprintf( '%s more contain em dashes only inside links, image details or code, which are left alone.', number_format_i18n( $total - $to_change ) ) );
            }
            ?>
        </p>

        <?php if ( ! $to_change ) : ?>
            <div class="notice notice-success inline"><p>Nothing to do: no published article, page, podcast episode or image alt text needs rewriting.</p></div>
        <?php else : ?>
            <h2>Preview</h2>
            <p>The <?php echo esc_html( number_format_i18n( count( $preview ) ) ); ?> most recent items that will change, with up to five changes each.</p>
            <?php foreach ( $preview as $plan ) : $post = $plan['post']; $edit = get_edit_post_link( $post->ID ); ?>
                <div class="hr-emdash-item">
                    <h3>
                        <?php
                        $name = esc_html( html_entity_decode( wp_strip_all_tags( get_the_title( $post ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ?: sprintf( '#%d', $post->ID ) );
                        echo $edit ? '<a href="' . esc_url( $edit ) . '">' . $name . '</a>' : $name; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above
                        ?>
                        <span class="hr-emdash-meta"><?php echo esc_html( sprintf( '%s, %d %s', 'attachment' === $post->post_type ? 'Image' : ( $labels[ $post->post_type ] ?? $post->post_type ), count( $plan['changes'] ), 1 === count( $plan['changes'] ) ? 'change' : 'changes' ) ); ?></span>
                    </h3>
                    <ol class="hr-emdash-diffs">
                        <?php foreach ( array_slice( $plan['changes'], 0, 5 ) as $row ) : ?>
                            <li>
                                <span class="hr-emdash-field"><?php echo esc_html( $row['field'] ); ?></span>
                                <span class="hr-emdash-line"><span class="hr-emdash-tag">Before</span> <?php echo emdash_diff_html( $row, false ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in emdash_diff_html() ?></span>
                                <span class="hr-emdash-line"><span class="hr-emdash-tag">After</span> <?php echo emdash_diff_html( $row, true ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in emdash_diff_html() ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            <?php endforeach; ?>

            <h2>Apply</h2>
            <ul class="hr-emdash-rules">
                <li>Each post is saved through WordPress, so its previous text stays in its revision history. To undo one post, open it in the editor, choose Revisions and restore the earlier version.</li>
                <li>Podcast episodes, SEO fields, summaries and alt text have no revision history; their previous text is kept in a hidden field on the same item (<code><?php echo esc_html( EMDASH_BACKUP_META ); ?></code>).</li>
                <li>Anything someone has open in the editor is skipped and listed below. Run the tool again later to finish it.</li>
                <li>Changed articles get a new &ldquo;last modified&rdquo; time. The signing module notices the change and the article is re-signed automatically on the signer&rsquo;s next run; until then its signature notice says the article was updated after signing.</li>
            </ul>
            <div id="hr-emdash-apply" data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'hr_emdash_apply' ) ); ?>" data-total="<?php echo esc_attr( (string) $total ); ?>">
                <p>
                    <button type="button" class="button button-primary" data-hr-run>Rewrite <?php echo esc_html( sprintf( '%s %s', number_format_i18n( $to_change ), 1 === $to_change ? 'item' : 'items' ) ); ?></button>
                    <button type="button" class="button" data-hr-stop hidden>Stop after this batch</button>
                </p>
                <progress max="<?php echo esc_attr( (string) max( 1, $total ) ); ?>" value="0" aria-label="Progress" hidden></progress>
                <p class="hr-emdash-status" role="status" aria-live="polite"></p>
                <ul class="hr-emdash-skipped" data-hr-skipped></ul>
                <noscript><p>This tool needs JavaScript to run in batches.</p></noscript>
            </div>
        <?php endif; ?>
    </div>
    <style>
        .hr-emdash { max-width: 1100px; }
        .hr-emdash-lead { font-size: 14px; max-width: 75ch; }
        .hr-emdash-rules { list-style: disc; padding-left: 20px; max-width: 75ch; }
        .hr-emdash-scroll { max-width: 900px; overflow-x: auto; }
        .hr-emdash-scroll:focus-visible { outline: 2px solid #2271b1; outline-offset: 2px; }
        .hr-emdash-counts { min-width: 640px; }
        .hr-emdash-counts td, .hr-emdash-counts th { font-variant-numeric: tabular-nums; }
        .hr-emdash-item { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 12px 16px; margin: 12px 0; }
        .hr-emdash-item h3 { margin: 0 0 8px; font-size: 14px; line-height: 1.4; }
        .hr-emdash-meta { font-weight: 400; color: #50575e; margin-left: 6px; }
        .hr-emdash-diffs { margin: 0; padding-left: 20px; }
        .hr-emdash-diffs li { margin: 0 0 10px; overflow-wrap: anywhere; }
        .hr-emdash-field { display: block; font-size: 12px; color: #50575e; text-transform: uppercase; letter-spacing: .04em; }
        .hr-emdash-line { display: block; line-height: 1.6; }
        .hr-emdash-tag { display: inline-block; min-width: 4.5em; font-weight: 600; }
        .hr-emdash del, .hr-emdash ins { white-space: pre-wrap; padding: 1px 3px; border-radius: 3px; text-decoration: none; font-weight: 600; }
        .hr-emdash del { background: #fcf0f1; color: #8a2424; outline: 1px solid #e6b8bb; }
        .hr-emdash ins { background: #edfaef; color: #00450c; outline: 1px solid #a7d7af; }
        .hr-emdash progress { width: 100%; max-width: 480px; height: 16px; }
        .hr-emdash-skipped a { display: inline-block; min-height: 24px; }
    </style>
    <script>
    (function () {
        var box = document.getElementById('hr-emdash-apply');
        if (!box || !window.fetch) return;
        var run = box.querySelector('[data-hr-run]'), stop = box.querySelector('[data-hr-stop]');
        var bar = box.querySelector('progress'), status = box.querySelector('[role="status"]'), skipped = box.querySelector('[data-hr-skipped]');
        var total = parseInt(box.getAttribute('data-total'), 10) || 0, checked = 0, changed = 0, stopping = false;
        function say(text) { status.textContent = text; }
        function finish(text) { say(text); run.disabled = false; stop.hidden = true; stop.disabled = false; }
        function list(items, note) {
            (items || []).forEach(function (p) {
                var li = document.createElement('li'), a = document.createElement('a');
                a.href = p.edit || '#'; a.textContent = p.title;
                li.appendChild(a); li.appendChild(document.createTextNode(' ' + note)); skipped.appendChild(li);
            });
        }
        function step(after) {
            var body = new FormData();
            body.append('action', 'hr_emdash_apply'); body.append('nonce', box.getAttribute('data-nonce')); body.append('after', String(after));
            fetch(box.getAttribute('data-ajax'), { method: 'POST', body: body, credentials: 'same-origin' })
                .then(function (r) { return r.json().catch(function () { throw new Error('The server sent an unexpected reply (HTTP ' + r.status + ').'); }); })
                .then(function (res) {
                    if (!res || !res.success) throw new Error((res && res.data && res.data.message) || 'The server refused the request.');
                    var d = res.data;
                    checked += d.processed; changed += d.changed; bar.value = Math.min(total, checked);
                    list(d.locked, '(open in the editor, skipped)'); list(d.failed, '(could not be saved)');
                    if (d.done) return finish('Finished. ' + changed + ' changed, ' + checked + ' checked.' + (skipped.children.length ? ' Run the tool again later for the items listed below.' : ''));
                    if (stopping) return finish('Stopped. ' + changed + ' changed, ' + checked + ' checked. Reload the page to see what is left.');
                    say('Working: ' + changed + ' changed, ' + checked + ' of ' + total + ' checked.');
                    step(d.next);
                })
                .catch(function (e) { finish('Stopped: ' + e.message + ' Reload the page and run the tool again to continue.'); });
        }
        run.addEventListener('click', function () {
            if (!window.confirm('Rewrite the em dashes now? Each post keeps a revision of its previous text.')) return;
            run.disabled = true; stop.hidden = false; bar.hidden = false; bar.value = 0; checked = 0; changed = 0; stopping = false;
            while (skipped.firstChild) skipped.removeChild(skipped.firstChild);
            say('Working...'); step(0);
        });
        stop.addEventListener('click', function () { stopping = true; stop.disabled = true; say('Stopping after this batch...'); });
    })();
    </script>
    <?php
}
