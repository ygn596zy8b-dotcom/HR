<?php
/** Settings → Password (current password required; other sessions are signed out). */

use function HR\Platform\Accounts\field;
use function HR\Platform\Accounts\error_summary;
use function HR\Platform\Accounts\hidden_fields;
use function HR\Platform\Accounts\account_url;

if ( ! defined( 'ABSPATH' ) ) exit;

$hra_err = $errors['password'] ?? array();
?>
<section class="hra-section" id="password" aria-labelledby="password-title">
    <header class="hra-section__head">
        <h2 class="hra-h2" id="password-title">Password</h2>
        <p class="hra-muted">Changing it signs you out everywhere except here. With a passkey you rarely need it.</p>
    </header>
    <form class="hrp-form hra-form hra-narrow" method="post" action="<?php echo esc_url( account_url( 'settings' ) . '#password' ); ?>" novalidate data-hra-form>
        <?php error_summary( $hra_err, 'password-' ); ?>
        <?php hidden_fields( 'password' ); ?>
        <input type="text" name="username" value="<?php echo esc_attr( $user->user_email ); ?>" autocomplete="username" hidden>
        <?php
        field( array(
            'name'         => 'current_password',
            'label'        => 'Current password',
            'type'         => 'password',
            'error'        => $hra_err['current_password'] ?? '',
            'autocomplete' => 'current-password',
            'maxlength'    => 1024,
            'reveal'       => true,
            'attrs'        => array( 'data-hra-required' => 'Enter your current password.' ),
        ) );
        field( array(
            'name'         => 'new_password',
            'label'        => 'New password',
            'hint'         => 'At least 12 characters. We check it against known data breaches.',
            'type'         => 'password',
            'error'        => $hra_err['new_password'] ?? '',
            'autocomplete' => 'new-password',
            'maxlength'    => 1024,
            'minlength'    => 12,
            'reveal'       => true,
            'meter'        => true,
            'attrs'        => array( 'data-hra-required' => 'Choose a new password.', 'data-hra-user-inputs' => 'username' ),
        ) );
        ?>
        <div><button type="submit" class="hrp-btn hra-submit"><span>Change password</span></button></div>
    </form>
</section>
