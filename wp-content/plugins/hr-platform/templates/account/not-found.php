<?php
/** Unknown /account/<slug>/ page. */

use function HR\Platform\Accounts\account_url;

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="hrp-card hra-empty">
    <p class="hra-lede">There is no account page at this address. It may have moved.</p>
    <p class="hra-actions"><a class="hrp-btn" href="<?php echo esc_url( account_url() ); ?>">Go to your account</a></p>
</div>
