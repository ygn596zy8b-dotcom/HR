<?php
/**
 * Settings page: in-page section navigation and the five sections (templates/account/settings/*.php).
 * Variables: see HR\Platform\Accounts\settings_view_data().
 */

use function HR\Platform\render;
use function HR\Platform\Accounts\notice;

if ( ! defined( 'ABSPATH' ) ) exit;

$hra_data = compact( 'user', 'errors', 'values', 'codes', 'codes_new', 'staff_notice', 'verified', 'pending_email', 'recent', 'recent_left', 'passkeys', 'totp_on', 'totp_since', 'recovery_left', 'setup', 'sessions', 'checklist', 'score', 'level', 'staff' );
$hra_sections = array(
    'profile'  => 'Profile',
    'email'    => 'Email',
    'password' => 'Password',
    'security' => 'Security',
    'privacy'  => 'Privacy & data',
);
?>
<nav class="hra-subnav" aria-label="Settings sections" data-hra-subnav>
    <ul role="list">
        <?php foreach ( $hra_sections as $hra_id => $hra_label ) : ?>
            <li><a href="#<?php echo esc_attr( $hra_id ); ?>"><?php echo esc_html( $hra_label ); ?></a></li>
        <?php endforeach; ?>
    </ul>
</nav>

<?php
if ( $staff_notice ) {
    notice( 'warn', 'Accounts that can edit the site must use a passkey or two-step verification before opening the dashboard. Add a passkey below — it takes about a minute.', 'One more step for staff' );
}
if ( isset( $errors['csrf']['_form'] ) ) {
    notice( 'error', $errors['csrf']['_form'] );
}
foreach ( array_keys( $hra_sections ) as $hra_id ) {
    render( 'account/settings/' . $hra_id, $hra_data );
}
