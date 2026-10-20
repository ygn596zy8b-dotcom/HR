<?php
/**
 * Signed-in account shell: page heading, account navigation (left on desktop, scrolling tabs on mobile), content.
 * Theme override: headlinerec-child/hr/account/app.php
 *
 * @var WP_User  $user
 * @var string   $active  current page slug ('overview', 'settings' or a contributed slug)
 * @var string   $title
 * @var callable $content prints the page body
 */

use function HR\Platform\icon;
use function HR\Platform\Accounts\nav_items;
use function HR\Platform\Accounts\render_flash;
use function HR\Platform\Accounts\account_url;
use function HR\Platform\Accounts\initials;
use function HR\Platform\Accounts\is_verified;

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="hrp hra hra-app hra-app--<?php echo esc_attr( sanitize_html_class( $active ?: 'none' ) ); ?>">
    <div class="hra-app__head">
        <div class="hrp-wrap hra-app__head-inner">
            <span class="hra-avatar" aria-hidden="true"><?php echo esc_html( initials( $user->display_name ) ); ?></span>
            <div>
                <p class="hrp-eyebrow">Your account</p>
                <p class="hra-app__who">
                    <span><?php echo esc_html( $user->display_name ); ?></span>
                    <?php if ( is_verified( (int) $user->ID ) ) : ?>
                        <span class="hrp-badge hrp-badge--ok"><?php echo icon( 'check' ); // phpcs:ignore -- static SVG ?>Verified</span>
                    <?php else : ?>
                        <span class="hrp-badge hrp-badge--muted">Email not confirmed</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>
    <div class="hrp-wrap hra-app__body">
        <nav class="hra-nav" aria-label="Account">
            <ul class="hra-nav__list" role="list">
                <?php foreach ( nav_items() as $hra_slug => $hra_item ) : ?>
                    <li>
                        <a class="hra-nav__link<?php echo $hra_slug === $active ? ' is-active' : ''; ?>" href="<?php echo esc_url( $hra_item['url'] ); ?>" <?php echo $hra_slug === $active ? 'aria-current="page"' : ''; ?>>
                            <?php echo icon( $hra_item['icon'] ?: 'file-text' ); // phpcs:ignore -- static SVG ?>
                            <span><?php echo esc_html( $hra_item['title'] ); ?></span>
                            <?php if ( ! empty( $hra_item['external'] ) ) : ?><?php echo icon( 'arrow-right', '', 'hra-nav__ext' ); // phpcs:ignore -- static SVG ?><?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
                <li class="hra-nav__sep">
                    <a class="hra-nav__link" href="<?php echo esc_url( account_url( 'logout' ) ); ?>"><?php echo icon( 'log-out' ); // phpcs:ignore -- static SVG ?><span>Sign out</span></a>
                </li>
            </ul>
        </nav>
        <main class="hra-app__main" id="hra-main">
            <?php if ( isset( $_GET['notice'] ) && 'reader-admin' === $_GET['notice'] ) : ?>
                <div class="hrp-alert hrp-alert--warn" role="status"><p>The WordPress dashboard is only for site staff. This account is a reader account, so you were brought to your account page. Staff: sign out and sign in with your editor or administrator account.</p></div>
            <?php endif; ?>
            <?php if ( 'overview' !== $active ) : ?>
                <h1 class="hra-page-title"><?php echo esc_html( $title ); ?></h1>
            <?php endif; ?>
            <div class="hra-flash" data-hra-flash><?php render_flash(); ?></div>
            <?php call_user_func( $content ); ?>
        </main>
    </div>
</div>
