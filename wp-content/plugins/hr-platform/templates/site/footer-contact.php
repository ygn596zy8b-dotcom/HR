<?php
/**
 * Footer address block (do_action( 'hr_footer_contact' )). Plain semantic <address>; the structured data is in the
 * page's JSON-LD graph. Only filled-in parts are printed.
 *
 * @var array $org Organisation details from HR\Platform\Site\org().
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$hr_lines = array_filter( array(
    $org['street_address'] ?? '',
    trim( implode( ' ', array_filter( array( trim( implode( ', ', array_filter( array( $org['locality'] ?? '', $org['region'] ?? '' ) ) ) ), $org['postal_code'] ?? '' ) ) ) ),
    ! empty( $org['country'] ) ? \HR\Platform\Site\country_name( $org['country'] ) : '',
) );
if ( ! $hr_lines && empty( $org['phone'] ) && empty( $org['email'] ) ) {
    return;
}
?>
<address class="hr-footer-address">
    <?php if ( ! empty( $org['legal_name'] ) ) : ?>
        <strong class="hr-footer-address__name"><?php echo esc_html( $org['legal_name'] ); ?></strong>
    <?php endif; ?>
    <?php if ( $hr_lines ) : ?>
        <span class="hr-footer-address__street">
            <?php echo implode( '<br>', array_map( 'esc_html', $hr_lines ) ); ?>
        </span>
    <?php endif; ?>
    <?php if ( ! empty( $org['phone'] ) ) : ?>
        <a class="hr-footer-address__tel" href="<?php echo esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $org['phone'] ), array( 'tel' ) ); ?>"><?php echo esc_html( $org['phone'] ); ?></a>
    <?php endif; ?>
    <?php if ( ! empty( $org['email'] ) ) : ?>
        <a class="hr-footer-address__email" href="<?php echo esc_url( 'mailto:' . $org['email'] ); ?>"><?php echo str_replace( '@', '@<wbr>', esc_html( $org['email'] ) ); // Narrow footer column: break after the @, never mid-word. ?></a>
    <?php endif; ?>
</address>
