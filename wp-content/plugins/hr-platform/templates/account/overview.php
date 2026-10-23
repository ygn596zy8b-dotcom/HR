<?php
/**
 * Account overview. @var WP_User $user  @var string $greeting  @var string $first  @var bool $verified
 * @var bool $welcome  @var array $cards  @var array $checklist  @var int $score  @var array $level  @var int $since
 * @var array $pages  @var int $last
 */

use function HR\Platform\icon;
use function HR\Platform\render;
use function HR\Platform\Accounts\hidden_fields;
use function HR\Platform\Accounts\account_url;
use function HR\Platform\Accounts\page_url;
use function HR\Platform\Accounts\mask_email;
use function HR\Platform\Accounts\passkey_count;

if ( ! defined( 'ABSPATH' ) ) exit;

$hra_submit = page_url( 'submit' );
?>
<header class="hra-hello" data-hra-reveal>
    <h1 class="hra-page-title"><?php echo esc_html( $greeting . ', ' . $first ); ?></h1>
    <?php if ( $since ) : ?>
        <p class="hra-muted">Member since <?php echo esc_html( wp_date( 'F Y', $since ) ); ?></p>
    <?php endif; ?>
</header>

<?php if ( $welcome ) : ?>
    <section class="hra-welcome" aria-labelledby="hra-welcome-title" data-hra-reveal tabindex="-1" data-autofocus>
        <span class="hra-welcome__mark" aria-hidden="true"><?php echo icon( 'check' ); // phpcs:ignore -- static SVG ?></span>
        <p class="hrp-eyebrow">Email confirmed</p>
        <h2 class="hra-welcome__title" id="hra-welcome-title">You’re in. Welcome to Headline Recorder.</h2>
        <p class="hra-welcome__lede">Thank you for joining. Here is how to get the most out of your account:</p>
        <ol class="hra-next" role="list">
            <li>
                <span class="hra-next__num">1</span>
                <span class="hra-next__body"><strong>Read 3 articles today</strong> and start your reading streak. Come back tomorrow to keep the flame alive.</span>
                <a class="hra-next__cta" href="<?php echo esc_url( home_url( '/' ) ); ?>">Today’s stories <?php echo icon( 'arrow-right' ); // phpcs:ignore -- static SVG ?></a>
            </li>
            <li>
                <span class="hra-next__num">2</span>
                <span class="hra-next__body"><strong>Add a passkey</strong> and sign in with your fingerprint or face. No password to forget, nothing to phish.</span>
                <a class="hra-next__cta" href="<?php echo esc_url( account_url( 'settings' ) . '#passkeys' ); ?>">Add a passkey <?php echo icon( 'arrow-right' ); // phpcs:ignore -- static SVG ?></a>
            </li>
            <li>
                <span class="hra-next__num">3</span>
                <span class="hra-next__body"><strong>Have something to say?</strong> Submit an article or op-ed to our editors.</span>
                <a class="hra-next__cta" href="<?php echo esc_url( $hra_submit ?: home_url( '/contact-us/' ) ); ?>"><?php echo $hra_submit ? 'Submit an op-ed' : 'Contact the desk'; ?> <?php echo icon( 'arrow-right' ); // phpcs:ignore -- static SVG ?></a>
            </li>
        </ol>
    </section>
<?php elseif ( ! $verified ) : ?>
    <div class="hrp-alert hrp-alert--warn hra-banner" role="status" data-hra-reveal>
        <span class="hra-notice__icon"><?php echo icon( 'mail' ); // phpcs:ignore -- static SVG ?></span>
        <div class="hra-banner__body">
            <p class="hra-notice__title">Confirm your email address</p>
            <p>We sent a link to <strong><?php echo esc_html( mask_email( $user->user_email ) ); ?></strong>. Your streak counts already; confirming lets you submit articles and recover your account.</p>
        </div>
        <form method="post" action="<?php echo esc_url( account_url() ); ?>" class="hra-banner__form" data-hra-form>
            <?php hidden_fields( 'resend_verify' ); ?>
            <button type="submit" class="hrp-btn hrp-btn--ghost hra-submit"><span>Resend link</span></button>
        </form>
    </div>
<?php endif; ?>

<?php if ( $cards ) : ?>
    <section class="hra-cards" aria-label="Your activity">
        <?php foreach ( $cards as $hra_card ) : ?>
            <article class="hra-card" data-hra-reveal>
                <p class="hra-card__label"><?php echo icon( $hra_card['icon'] ); // phpcs:ignore -- static SVG ?><span><?php echo esc_html( $hra_card['title'] ); ?></span></p>
                <?php if ( '' !== $hra_card['value'] ) : ?>
                    <p class="hra-card__value"><?php echo esc_html( $hra_card['value'] ); ?></p>
                <?php endif; ?>
                <?php if ( '' !== $hra_card['body_html'] ) : ?>
                    <div class="hra-card__body"><?php echo $hra_card['body_html']; // phpcs:ignore -- passed through wp_kses_post() in overview_data() ?></div>
                <?php endif; ?>
                <?php if ( $hra_card['link'] ) : ?>
                    <a class="hra-card__link" href="<?php echo esc_url( $hra_card['link']['url'] ); ?>"><?php echo esc_html( $hra_card['link']['label'] ); ?> <?php echo icon( 'arrow-right' ); // phpcs:ignore -- static SVG ?></a>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<div class="hra-overview-grid">
    <section class="hrp-card hra-panel" aria-labelledby="hra-sec-title" data-hra-reveal>
        <div class="hra-panel__head">
            <h2 class="hra-h2" id="hra-sec-title">Account security</h2>
            <a class="hra-panel__more" href="<?php echo esc_url( account_url( 'settings' ) . '#security' ); ?>">Manage <?php echo icon( 'arrow-right' ); // phpcs:ignore -- static SVG ?></a>
        </div>
        <?php render( 'account/checklist', array( 'checklist' => $checklist, 'score' => $score, 'level' => $level ) ); ?>
    </section>
    <section class="hrp-card hra-panel hra-quick" aria-labelledby="hra-quick-title" data-hra-reveal>
        <h2 class="hra-h2" id="hra-quick-title">Shortcuts</h2>
        <ul class="hra-quick__list" role="list">
            <li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo icon( 'book-open' ); // phpcs:ignore -- static SVG ?><span><strong>Today’s stories</strong><small>Business, economy and markets</small></span></a></li>
            <?php if ( $hra_submit ) : ?>
                <li><a href="<?php echo esc_url( $hra_submit ); ?>"><?php echo icon( 'pen' ); // phpcs:ignore -- static SVG ?><span><strong>Submit an article</strong><small>Op-eds and analysis for our editors</small></span></a></li>
            <?php endif; ?>
            <li><a href="<?php echo esc_url( account_url( 'settings' ) . '#profile' ); ?>"><?php echo icon( 'user' ); // phpcs:ignore -- static SVG ?><span><strong>Profile</strong><small>Your name and email</small></span></a></li>
            <li><a href="<?php echo esc_url( account_url( 'settings' ) . ( passkey_count( (int) $user->ID ) ? '#sessions' : '#passkeys' ) ); ?>"><?php echo icon( 'key' ); // phpcs:ignore -- static SVG ?><span><strong><?php echo passkey_count( (int) $user->ID ) ? 'Where you are signed in' : 'Add a passkey'; ?></strong><small><?php echo passkey_count( (int) $user->ID ) ? 'Devices and sessions' : 'Faster, phishing-proof sign-in'; ?></small></span></a></li>
            <li><a href="<?php echo esc_url( account_url( 'settings' ) . '#privacy' ); ?>"><?php echo icon( 'download' ); // phpcs:ignore -- static SVG ?><span><strong>Your data</strong><small>Download or delete it</small></span></a></li>
        </ul>
        <?php if ( $last ) : ?>
            <p class="hra-note">Previous sign-in <?php echo esc_html( wp_date( 'j M Y, H:i', $last ) ); ?></p>
        <?php endif; ?>
    </section>
</div>
