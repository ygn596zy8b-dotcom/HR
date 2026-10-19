<?php
/**
 * Client & partner logo band.
 *
 * @var array  $clients list of ['name', 'url', 'img'] (img is wp_get_attachment_image HTML)
 * @var bool   $marquee scroll the logos (enough logos and the marquee style chosen)
 * @var string $eyebrow
 * @var string $heading
 */

use function HR\Platform\icon;

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! $clients ) : ?>
    <section class="hrp hr-clients hr-clients--empty">
        <div class="hrp-wrap">
            <a class="hr-clients__empty" href="<?php echo esc_url( admin_url( 'edit.php?post_type=hr_client' ) ); ?>">Add client logos in wp-admin → Client logos</a>
        </div>
    </section>
    <?php
    return;
endif;

$title_id = wp_unique_id( 'hr-clients-title-' );
$list     = static function ( bool $copy ) use ( $clients ) {
    ?>
    <ul class="hr-clients__list" role="list"<?php echo $copy ? ' aria-hidden="true" inert' : ''; ?>>
        <?php foreach ( $clients as $c ) : ?>
            <li class="hr-clients__item">
                <?php if ( '' !== $c['url'] ) : ?>
                    <a class="hr-clients__link" href="<?php echo esc_url( $c['url'], array( 'https' ) ); ?>" target="_blank" rel="noopener noreferrer">
                        <?php echo $c['img']; // phpcs:ignore WordPress.Security.EscapeOutput -- core-built <img>, alt escaped by core. ?>
                        <span class="hrp-sr">(opens in a new tab)</span>
                    </a>
                <?php else : ?>
                    <span class="hr-clients__link"><?php echo $c['img']; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php
};
?>
<section class="hrp hr-clients <?php echo $marquee ? 'hr-clients--marquee' : 'hr-clients--grid'; ?>" aria-labelledby="<?php echo esc_attr( $title_id ); ?>" data-hr-clients>
    <div class="hrp-wrap hr-clients__head">
        <div>
            <?php if ( '' !== $eyebrow ) : ?>
                <p class="hrp-eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
            <?php endif; ?>
            <h2 class="hr-clients__title" id="<?php echo esc_attr( $title_id ); ?>"><?php echo esc_html( $heading ); ?></h2>
        </div>
        <?php if ( $marquee ) : ?>
            <button type="button" class="hrp-btn hrp-btn--ghost hr-clients__toggle" data-hr-clients-toggle>
                <?php echo icon( 'pause', '', 'hr-clients__icon-pause' ) . icon( 'play', '', 'hr-clients__icon-play' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                <span data-hr-clients-label>Pause</span><span class="hrp-sr"> scrolling logos</span>
            </button>
        <?php endif; ?>
    </div>
    <div class="<?php echo $marquee ? 'hr-clients__viewport' : 'hrp-wrap'; ?>">
        <?php if ( $marquee ) : ?>
            <div class="hr-clients__track" style="--hr-clients-dur:<?php echo (int) max( 30, count( $clients ) * 5 ); ?>s">
                <?php $list( false ); $list( true ); ?>
            </div>
        <?php else : ?>
            <?php $list( false ); ?>
        <?php endif; ?>
    </div>
</section>
