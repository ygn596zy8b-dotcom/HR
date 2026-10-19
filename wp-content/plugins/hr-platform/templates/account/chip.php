<?php
/**
 * Header account slot (inside the theme's primary navigation). Identical for every visitor so cached pages stay
 * correct; account-chip.js swaps in the signed-in chip. Theme override: headlinerec-child/hr/account/chip.php
 */

use function HR\Platform\icon;
use function HR\Platform\Accounts\account_url;

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="hr-account-slot" data-hr-account>
    <span class="hr-account-slot__guest">
        <a class="hr-account-slot__signin" href="<?php echo esc_url( account_url( 'login' ) ); ?>"><?php echo icon( 'user' ); // phpcs:ignore -- static SVG ?><span>Sign in</span></a>
        <a class="hr-account-slot__join" href="<?php echo esc_url( account_url( 'register' ) ); ?>">Create account</a>
    </span>
</div>
