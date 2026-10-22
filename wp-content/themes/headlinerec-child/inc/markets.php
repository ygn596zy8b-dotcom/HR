<?php
/**
 * Markets (MSBJ): TradingView panels that cost nothing on first paint, and the State Bank figures they fall back to.
 *
 * Every panel is printed by hr_tv_widget(): a fixed-size .hr-tv frame holding a placeholder and an inert widget
 * (<script type="text/plain" data-hr-tv>). hr-ui.js starts the widget once the page has loaded and the frame is
 * within 400px of the viewport, and moves the frame through data-hr-tv-state: idle, loading, ready, error or off
 * (embedded content switched off in the cookie settings). Until "ready", and for good when the widget cannot load,
 * the placeholder shows a status line, the latest SBP figures and the instruments the live panel tracks, so the
 * panel is never an empty box and nothing moves when the widget arrives. The HTML is the same for every reader,
 * so pages stay cacheable.
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
 *     @type string   $link    Optional URL for a "more" link under the figures (e.g. the Markets page).
 *     @type string   $link_label Text of that link.
 *     @type string   $variant '' (panel) or 'tape' (one-line ticker).
 * }
 */
function hr_tv_placeholder( array $args ): string {
    $args = array_merge(
        array(
            'loading'    => 'Live market data loads here',
            'error'      => 'Live market data is unavailable right now.',
            'off'        => 'Live market data comes from TradingView, which may set its own cookies. Embedded content is switched off in your cookie settings.',
            'button'     => 'Load live data',
            'figures'    => array(),
            'symbols'    => array(),
            'link'       => '',
            'link_label' => '',
            'variant'    => '',
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
        $html .= '<div class="hr-tv-ph__actions">';
        $html .= '<button type="button" class="hr-tv-ph__load" data-hr-tv-load hidden>' . esc_html( $args['button'] ) . '</button>';
        if ( '' !== $args['link'] && '' !== $args['link_label'] ) {
            $html .= sprintf( '<a class="hr-tv-ph__link" href="%s">%s</a>', esc_url( $args['link'] ), esc_html( $args['link_label'] ) );
        }
        $html .= '</div>';
    }
    return $html . '</div>';
}

/**
 * A complete TradingView panel: frame, placeholder and inert widget.
 *
 * @param string $widget  Widget slug, e.g. "market-quotes" (embed-widget-market-quotes.js).
 * @param array  $config  Widget configuration. Width and height default to "100%": the frame sets the size in CSS.
 * @param array  $args    {
 *     @type string $class       Extra classes on the frame (size and colour variants: hr-tv--dark, hr-tv--quotes...).
 *     @type string $label       Accessible name of the panel.
 *     @type array  $placeholder Arguments for hr_tv_placeholder().
 *     @type bool   $autosize    False for widgets with their own fixed height (ticker tape).
 * }
 */
function hr_tv_widget( string $widget, array $config, array $args = array() ): string {
    if ( ! preg_match( '/^[a-z-]+$/', $widget ) ) {
        return '';
    }
    $args   = array_merge( array( 'class' => '', 'label' => '', 'placeholder' => array(), 'autosize' => true ), $args );
    $config = $args['autosize'] ? array_merge( array( 'width' => '100%', 'height' => '100%' ), $config ) : $config;
    $json   = wp_json_encode( $config, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP );

    return sprintf(
        '<div class="hr-tv %1$s" data-hr-tv-state="idle"%2$s>%3$s<div class="tradingview-widget-container"><div class="tradingview-widget-container__widget"></div><script type="text/plain" data-hr-tv="%4$s">%5$s</script></div></div>',
        esc_attr( trim( $args['class'] ) ),
        '' !== $args['label'] ? ' role="region" aria-label="' . esc_attr( $args['label'] ) . '"' : '',
        hr_tv_placeholder( $args['placeholder'] ),
        esc_url( "https://s3.tradingview.com/external-embedding/embed-widget-{$widget}.js" ),
        $json // phpcs:ignore WordPress.Security.EscapeOutput -- JSON with <, > and & hex-escaped, inside an inert script
    );
}
