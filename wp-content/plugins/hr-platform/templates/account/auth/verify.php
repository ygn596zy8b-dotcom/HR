<?php
/**
 * Email confirmation. GET shows a button (link scanners only fetch), POST confirms.
 * @var string $state confirm|invalid|error|other_user  @var string $kind signup|email
 * @var int $u  @var string $t  @var string $email  @var string $message
 */

use function HR\Platform\icon;
use function HR\Platform\Accounts\hidden_fields;
use function HR\Platform\Accounts\account_url;
use function HR\Platform\Accounts\mask_email;

if ( ! defined( 'ABSPATH' ) ) exit;

$hra_change = 'email' === $kind;

if ( 'confirm' === $state ) : ?>
    <div class="hra-state">
        <span class="hra-state__icon" aria-hidden="true"><?php echo icon( 'mail' ); // phpcs:ignore -- static SVG ?></span>
        <p class="hrp-eyebrow"><?php echo $hra_change ? 'New email address' : 'One last step'; ?></p>
        <h1 class="hra-title"><?php echo $hra_change ? 'Confirm your new email' : 'Confirm your email'; ?></h1>
        <p class="hra-lede">
            <?php if ( $hra_change ) : ?>
                Your account will switch to <strong><?php echo esc_html( mask_email( (string) $email ) ); ?></strong>.
            <?php else : ?>
                Confirm <strong><?php echo esc_html( mask_email( (string) $email ) ); ?></strong> to finish setting up your account.
            <?php endif; ?>
        </p>
        <form class="hra-form" method="post" action="<?php echo esc_url( account_url( 'verify' ) ); ?>" data-hra-form>
            <?php hidden_fields( 'verify' ); ?>
            <input type="hidden" name="u" value="<?php echo (int) $u; ?>">
            <input type="hidden" name="t" value="<?php echo esc_attr( $t ); ?>">
            <?php if ( $hra_change ) : ?><input type="hidden" name="a" value="email"><?php endif; ?>
            <button type="submit" class="hrp-btn hrp-btn--block hra-submit"><?php echo icon( 'check' ); // phpcs:ignore -- static SVG ?><span><?php echo $hra_change ? 'Confirm new email' : 'Confirm my email'; ?></span></button>
        </form>
    </div>
<?php elseif ( 'other_user' === $state ) : ?>
    <div class="hra-state">
        <span class="hra-state__icon hra-state__icon--warn" aria-hidden="true"><?php echo icon( 'user' ); // phpcs:ignore -- static SVG ?></span>
        <h1 class="hra-title" tabindex="-1" data-autofocus>You are signed in to another account</h1>
        <p class="hra-lede">This link belongs to a different account. Sign out first, then open the link again.</p>
        <p class="hra-actions"><a class="hrp-btn" href="<?php echo esc_url( account_url( 'logout' ) ); ?>">Sign out</a></p>
    </div>
<?php else : ?>
    <div class="hra-state">
        <span class="hra-state__icon hra-state__icon--error" aria-hidden="true"><?php echo icon( 'x' ); // phpcs:ignore -- static SVG ?></span>
        <h1 class="hra-title" tabindex="-1" data-autofocus><?php echo 'error' === $state ? 'We could not confirm that' : 'This link has expired'; ?></h1>
        <p class="hra-lede">
            <?php
            echo esc_html( 'error' === $state && ! empty( $message )
                ? $message
                : 'Confirmation links work once and for 48 hours. Sign in and we will send you a fresh one from your account page.' );
            ?>
        </p>
        <p class="hra-actions">
            <a class="hrp-btn" href="<?php echo esc_url( account_url() ); ?>">Go to my account</a>
            <a class="hrp-btn hrp-btn--ghost" href="<?php echo esc_url( account_url( 'login' ) ); ?>">Sign in</a>
        </p>
    </div>
<?php endif;
