<?php
/** Settings → Profile. */

use function HR\Platform\Accounts\field;
use function HR\Platform\Accounts\error_summary;
use function HR\Platform\Accounts\hidden_fields;
use function HR\Platform\Accounts\account_url;

if ( ! defined( 'ABSPATH' ) ) exit;

$hra_err = $errors['profile'] ?? array();
?>
<section class="hra-section" id="profile" aria-labelledby="profile-title">
    <header class="hra-section__head">
        <h2 class="hra-h2" id="profile-title">Profile</h2>
        <p class="hra-muted">Your name appears on articles and op-eds you submit.</p>
    </header>
    <form class="hrp-form hra-form" method="post" action="<?php echo esc_url( account_url( 'settings' ) . '#profile' ); ?>" novalidate data-hra-form>
        <?php error_summary( $hra_err, 'profile-' ); ?>
        <?php hidden_fields( 'profile' ); ?>
        <div class="hra-inline-fields">
            <?php
            field( array(
                'name'         => 'display_name',
                'label'        => 'Display name',
                'value'        => $values['display_name'] ?? $user->display_name,
                'error'        => $hra_err['display_name'] ?? '',
                'autocomplete' => 'name',
                'maxlength'    => 60,
                'minlength'    => 2,
                'attrs'        => array( 'data-hra-required' => 'Enter your name (at least 2 characters).' ),
            ) );
            ?>
            <button type="submit" class="hrp-btn hra-submit"><span>Save</span></button>
        </div>
    </form>
</section>
