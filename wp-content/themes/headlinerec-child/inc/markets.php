<?php
/**
 * Markets (MSBJ): the placeholder every TradingView panel ships with, and the State Bank figures it shows.
 *
 * TradingView widgets are printed inert (<script type="text/plain" data-hr-tv>) inside a .hr-tv frame and started by
 * hr-ui.js once the page has loaded and the panel is within 400px of the viewport. Until the widget's frame has
 * loaded, and for good when it cannot load (blocked, offline, embedded content switched off in the cookie settings),
 * the frame shows this placeholder at the widget's own size: a status line, the latest SBP figures and the
 * instruments the live panel tracks. The HTML is the same for every reader, so pages stay cacheable.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Latest SBP figures for a placeholder, from get_headline_recorder_sbp_data() (functions.php).
 *
 * @param string[] $keys Indicator keys in display order (policy_rate, kibor, pkr_usd, ...).
 * @return array<int,array{label:string,value:string,unit:string}>
 */
function hr_sbp_figures( array $keys ): array {
    $data = function_exists( 'get_headline_recorder_sbp_data' ) ? get_headline_recorder_sbp_data() : array();
    $out  = array();
    foreach ( $keys as $key ) {
        $value = trim( str_replace( '$', '', (string) ( $data[ $key ]['value'] ?? '' ) ) );
        if ( '' !== $value ) {
            $out[] = array(
                'label' => (string) ( $data[ $key ]['label'] ?? '' ),
                'value' => $value,
                'unit'  => (string) ( $data[ $key ]['unit'] ?? '' ),
            );
        }
    }
    return $out;
}

/** "September 3, 2026" from the SBP data set, or ''. */
function hr_sbp_updated(): string {
    $data = function_exists( 'get_headline_recorder_sbp_data' ) ? get_headline_recorder_sbp_data() : array();
    return (string) ( $data['last_updated'] ?? '' );
}

/**
 * Placeholder markup for a TradingView frame.
 *
 * @param array $args {
 *     @type string   $loading Status while the widget loads, e.g. "Live quotes load here".
 *     @type string   $error   Status when it cannot load.
 *     @type string   $off     Status when the reader switched embedded content off.
 *     @type string   $button  Label of the "load anyway" button shown in the $off state.
 *     @type array    $figures Rows from hr_sbp_figures().
 *     @type string[] $symbols Instruments the live widget tracks.
 *     @type string   $variant '' (panel) or 'tape' (one-line ticker).
 * }
 */
function hr_tv_placeholder( array $args ): string {
    $args = array_merge(
        array(
            'loading' => 'Live market data loads here',
            'error'   => 'Live market data is unavailable right now.',
            'off'     => 'Live market data comes from TradingView, which may set its own cookies. Embedded content is switched off in your cookie settings.',
            'button'  => 'Load live data',
            'figures' => array(),
            'symbols' => array(),
            'variant' => '',
        ),
        $args
    );
    $tape = 'tape' === $args['variant'];

    $figs = '';
    foreach ( $args['figures'] as $fig ) {
        $figs .= sprintf(
            '<div class="hr-tv-ph__fig"><dt>%s</dt><dd><span class="hr-tv-ph__num">%s</span>%s</dd></div>',
            esc_html( $fig['label'] ),
            esc_html( $fig['value'] ),
            '' !== $fig['unit'] ? ' <span class="hr-tv-ph__unit">' . esc_html( $fig['unit'] ) . '</span>' : ''
        );
    }
    $updated = hr_sbp_updated();

    $html  = sprintf(
        '<div class="hr-tv-ph%s" data-hr-tv-ph data-msg-loading="%s" data-msg-error="%s" data-msg-off="%s">',
        $tape ? ' hr-tv-ph--tape' : '',
        esc_attr( $args['loading'] ),
        esc_attr( $args['error'] ),
        esc_attr( $args['off'] )
    );
    $html .= sprintf(
        '<p class="hr-tv-ph__status%s" role="status"><span class="hr-tv-ph__pulse" aria-hidden="true"></span><span data-hr-tv-msg>%s</span></p>',
        $tape ? ' screen-reader-text' : '',
        esc_html( $args['loading'] )
    );
    if ( '' !== $figs ) {
        if ( ! $tape ) {
            $html .= '<p class="hr-tv-ph__caption">' . esc_html( $updated ? sprintf( 'Latest State Bank figures, updated %s', $updated ) : 'Latest State Bank figures' ) . '</p>';
        }
        $html .= '<dl class="hr-tv-ph__figs">' . $figs . '</dl>';
    }
    if ( $args['symbols'] && ! $tape ) {
        $html .= '<p class="hr-tv-ph__caption">Tracked live</p><ul class="hr-tv-ph__symbols">';
        foreach ( $args['symbols'] as $symbol ) {
            $html .= '<li>' . esc_html( $symbol ) . '</li>';
        }
        $html .= '</ul>';
    }
    if ( ! $tape ) {
        $html .= '<button type="button" class="hr-tv-ph__load" data-hr-tv-load hidden>' . esc_html( $args['button'] ) . '</button>';
    }
    return $html . '</div>';
}
