<?php
/**
 * /thank-you/ page body. Theme override: headlinerec-child/hr/site/thank-you.php
 *
 * @var string    $for          newsletter|contact|media-kit|generic
 * @var array     $copy         eyebrow, title, lede, hint
 * @var WP_Post[] $latest       Latest stories
 * @var string    $podcast_url  Podcast archive ('' when the podcast is not installed)
 * @var string    $podcast_name "the Podcast" (or the show's own name)
 * @var string    $account_url  Registration link for signed-out readers ('' when accounts are off)
 * @var string    $streak_url   Account overview for signed-in readers ('' otherwise)
 */

use function HR\Platform\icon;

if ( ! defined( 'ABSPATH' ) ) exit;

$hr_home  = home_url( '/' );
$hr_share = rawurlencode( $hr_home );
$hr_text  = rawurlencode( 'Headline Recorder — Pakistan’s sharpest business and economy news' );
$hr_links = array(
    'LinkedIn' => 'https://www.linkedin.com/sharing/share-offsite/?url=' . $hr_share,
    'X'        => 'https://x.com/intent/post?url=' . $hr_share . '&text=' . $hr_text,
    'WhatsApp' => 'https://wa.me/?text=' . $hr_text . '%20' . $hr_share,
    'Facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . $hr_share,
);
?>
<div class="hrp hr-ty" data-variant="<?php echo esc_attr( $for ); ?>">
    <section class="hr-ty__hero" aria-labelledby="hr-ty-title">
        <span class="hr-ty__mark" aria-hidden="true"><?php echo icon( 'newsletter' === $for ? 'mail' : 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG ?></span>
        <p class="hrp-eyebrow"><?php echo esc_html( $copy['eyebrow'] ); ?></p>
        <h1 class="hr-ty__title" id="hr-ty-title"><?php echo esc_html( $copy['title'] ); ?></h1>
        <p class="hr-ty__lede"><?php echo esc_html( $copy['lede'] ); ?></p>
        <?php if ( ! empty( $copy['hint'] ) ) : ?>
            <p class="hr-ty__hint"><?php echo esc_html( $copy['hint'] ); ?></p>
        <?php endif; ?>
        <p class="hr-ty__actions">
            <a class="hr-ty__btn" href="<?php echo esc_url( $hr_home ); ?>">Read today’s top stories <?php echo icon( 'arrow-right' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG ?></a>
        </p>
    </section>

    <section class="hr-ty__next" aria-labelledby="hr-ty-next">
        <h2 class="hr-ty__h2" id="hr-ty-next">While you’re here</h2>
        <div class="hr-ty__grid">
            <?php if ( $latest ) : ?>
                <div class="hrp-card hrp-card--accent hr-ty__card hr-ty__card--stories">
                    <h3 class="hr-ty__h3"><?php echo icon( 'book-open' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG ?>Latest stories</h3>
                    <ol class="hr-ty__stories">
                        <?php foreach ( $latest as $hr_post ) : ?>
                            <li>
                                <a href="<?php echo esc_url( get_permalink( $hr_post ) ); ?>"><?php echo esc_html( html_entity_decode( get_the_title( $hr_post ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ); ?></a>
                                <time datetime="<?php echo esc_attr( get_the_date( 'c', $hr_post ) ); ?>"><?php echo esc_html( get_the_date( 'j M Y', $hr_post ) ); ?></time>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            <?php endif; ?>

            <?php if ( $podcast_url ) : ?>
                <div class="hrp-card hrp-card--accent hr-ty__card">
                    <h3 class="hr-ty__h3"><?php echo icon( 'headphones' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG ?>Listen to <?php echo esc_html( $podcast_name ); ?></h3>
                    <p>Pakistan’s business and economy news, briefed in minutes — for the commute or between meetings.</p>
                    <a class="hrp-btn hrp-btn--ghost" href="<?php echo esc_url( $podcast_url ); ?>">Listen to <?php echo esc_html( $podcast_name ); ?></a>
                </div>
            <?php endif; ?>

            <?php if ( $account_url || $streak_url ) : ?>
                <div class="hrp-card hrp-card--accent hr-ty__card">
                    <h3 class="hr-ty__h3"><?php echo icon( 'flame' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG ?>Build a reading streak</h3>
                    <?php if ( $account_url ) : ?>
                        <p>Create a free account to count the articles and op-eds you read each day, keep your streak alive and submit your own pieces.</p>
                        <a class="hrp-btn hrp-btn--ghost" href="<?php echo esc_url( $account_url ); ?>">Create an account</a>
                    <?php else : ?>
                        <p>Every article you finish counts towards today. Check your streak and keep it going.</p>
                        <a class="hrp-btn hrp-btn--ghost" href="<?php echo esc_url( $streak_url ); ?>">See your streak</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="hr-ty__share" aria-labelledby="hr-ty-share">
        <h2 class="hr-ty__h2" id="hr-ty-share">Know someone who should read us?</h2>
        <ul class="hr-ty__share-list">
            <?php foreach ( $hr_links as $hr_label => $hr_url ) : ?>
                <li><a href="<?php echo esc_url( $hr_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $hr_label ); ?><span class="hrp-sr"> (opens in a new tab)</span></a></li>
            <?php endforeach; ?>
            <li><a href="<?php echo esc_url( 'mailto:?subject=' . rawurlencode( 'Headline Recorder' ) . '&body=' . $hr_text . '%0A' . $hr_share ); ?>"><?php echo icon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG ?>Email</a></li>
        </ul>
    </section>
</div>
