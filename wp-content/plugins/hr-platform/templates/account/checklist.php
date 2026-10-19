<?php
/**
 * Security score + checklist (overview and Settings → Security). Honest: an item is done only when it really is.
 * @var array $checklist  @var int $score  @var array $level [name, advice]  @var bool $compact
 */

use function HR\Platform\icon;

if ( ! defined( 'ABSPATH' ) ) exit;

$hra_total = count( $checklist );
?>
<div class="hra-score" data-score="<?php echo (int) $score; ?>">
    <div class="hra-score__top">
        <div>
            <p class="hra-score__label">Security score</p>
            <p class="hra-score__level"><span class="hra-score__num"><?php echo (int) $score; ?><small>/<?php echo (int) $hra_total; ?></small></span> <?php echo esc_html( $level[0] ); ?></p>
        </div>
        <span class="hra-score__shield<?php echo $score >= $hra_total ? ' is-full' : ''; ?>" aria-hidden="true"><?php echo icon( 'shield-check' ); // phpcs:ignore -- static SVG ?></span>
    </div>
    <div class="hra-score__bar" role="meter" aria-valuemin="0" aria-valuemax="<?php echo (int) $hra_total; ?>" aria-valuenow="<?php echo (int) $score; ?>" aria-label="Security score">
        <?php for ( $i = 0; $i < $hra_total; $i++ ) : ?><span class="<?php echo $i < $score ? 'is-on' : ''; ?>"></span><?php endfor; ?>
    </div>
    <p class="hra-score__advice"><?php echo esc_html( $level[1] ); ?></p>
    <ul class="hra-checklist" role="list">
        <?php foreach ( $checklist as $hra_item ) : ?>
            <li class="hra-checklist__item<?php echo $hra_item['done'] ? ' is-done' : ''; ?>">
                <span class="hra-checklist__mark" aria-hidden="true"><?php echo icon( $hra_item['done'] ? 'check' : 'chevron-right' ); // phpcs:ignore -- static SVG ?></span>
                <span class="hra-checklist__text">
                    <strong><?php echo esc_html( $hra_item['title'] ); ?></strong>
                    <span class="hrp-sr"><?php echo $hra_item['done'] ? '(done)' : '(to do)'; ?></span>
                    <span class="hra-checklist__detail"><?php echo esc_html( $hra_item['detail'] ); ?></span>
                </span>
                <?php if ( ! $hra_item['done'] ) : ?>
                    <a class="hra-checklist__cta" href="<?php echo esc_url( $hra_item['url'] ); ?>"><?php echo esc_html( $hra_item['cta'] ); ?><span class="hrp-sr"> — <?php echo esc_html( $hra_item['title'] ); ?></span></a>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
