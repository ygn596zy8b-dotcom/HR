<?php
/**
 * /account/submissions/: the reader's own submissions with status and the editors' note. A submission can be
 * withdrawn until an editor starts on it.
 * Theme override: headlinerec-child/hr/submissions/list.php
 *
 * @var WP_Post[] $items      the current reader's submissions (queried by post_author)
 * @var bool      $withdrawn
 * @var string    $error
 */

use HR\Platform\Submissions as S;
use function HR\Platform\icon;
use function HR\Platform\Accounts\notice;
use function HR\Platform\Accounts\hidden_fields;
use function HR\Platform\Accounts\account_url;

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="hrs">
    <?php if ( $withdrawn ) : ?>
        <?php notice( 'ok', 'Your submission was withdrawn and deleted.' ); ?>
    <?php endif; ?>
    <?php if ( '' !== $error ) : ?>
        <?php notice( 'error', $error ); ?>
    <?php endif; ?>

    <?php if ( ! $items ) : ?>
        <div class="hrp-card hra-state hra-empty">
            <span class="hra-state__icon" aria-hidden="true"><?php echo icon( 'pen' ); // phpcs:ignore -- static SVG ?></span>
            <h2 class="hra-h2">No submissions yet</h2>
            <p class="hra-lede">Have a view on Pakistan's economy, markets or business? Send us an article or an op-ed.</p>
            <div class="hra-actions"><a class="hrp-btn" href="<?php echo esc_url( account_url( 'submit' ) ); ?>"><?php echo icon( 'pen' ); // phpcs:ignore -- static SVG ?><span>Submit an article</span></a></div>
        </div>
    <?php else : ?>
        <div class="hrs-list-head">
            <p class="hra-muted"><?php echo esc_html( sprintf( _n( '%d submission', '%d submissions', count( $items ) ), count( $items ) ) ); ?>. We reply within 7 days.</p>
            <a class="hrp-btn" href="<?php echo esc_url( account_url( 'submit' ) ); ?>"><?php echo icon( 'pen' ); // phpcs:ignore -- static SVG ?><span>New submission</span></a>
        </div>
        <ul class="hra-list hrs-list" role="list">
            <?php foreach ( $items as $hrs_post ) :
                $hrs_id     = (int) $hrs_post->ID;
                $hrs_status = S\meta( $hrs_id, 'status' );
                $hrs_note   = S\meta( $hrs_id, 'note' );
                ?>
                <li class="hra-list__item hrs-item">
                    <div class="hra-list__main">
                        <p class="hra-list__title">
                            <span><?php echo esc_html( $hrs_post->post_title ); ?></span>
                            <span class="hrp-badge <?php echo esc_attr( S\status_class( $hrs_status ) ); ?>"><?php echo esc_html( S\STATUSES[ $hrs_status ] ?? 'Received' ); ?></span>
                        </p>
                        <p class="hra-list__meta">
                            <?php
                            echo esc_html( implode( ' · ', array(
                                S\TYPES[ S\meta( $hrs_id, 'type' ) ] ?? 'Article',
                                sprintf( '%s words', number_format( (int) S\meta( $hrs_id, 'words' ) ) ),
                                'Sent ' . get_the_date( 'j M Y', $hrs_post ),
                                '#' . $hrs_id,
                            ) ) );
                            ?>
                        </p>
                        <?php if ( '' !== $hrs_note ) : ?>
                            <div class="hrs-note">
                                <p class="hrs-note__label">Note from the editors</p>
                                <p class="hrs-note__text"><?php echo nl2br( esc_html( $hrs_note ) ); // phpcs:ignore -- escaped ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if ( 'received' === $hrs_status ) : ?>
                        <div class="hra-list__actions">
                            <form method="post" action="<?php echo esc_url( account_url( 'submissions' ) ); ?>" class="hra-inline-form" data-hra-form data-hra-confirm="<?php echo esc_attr( sprintf( 'Withdraw "%s"? It will be deleted permanently.', $hrs_post->post_title ) ); ?>">
                                <?php hidden_fields( 'withdraw' ); ?>
                                <input type="hidden" name="submission" value="<?php echo (int) $hrs_id; ?>">
                                <button type="submit" class="hrp-btn hrp-btn--link hra-danger-link"><?php echo icon( 'trash' ); // phpcs:ignore -- static SVG ?><span>Withdraw</span></button>
                            </form>
                        </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
