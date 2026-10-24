<?php
/**
 * A single white paper or report: summary, PDF download and an inline reader on wide screens.
 */

use function HR\Platform\Research\pdf;

if ( ! defined( 'ABSPATH' ) ) exit;

get_header();
the_post();
$file = pdf( get_the_ID() );
?>
<main id="primary" class="hrp hr-research hr-research--single">
    <header class="hr-research__head">
        <p class="hrp-eyebrow">Report</p>
        <h1 class="hrp-title"><?php the_title(); ?></h1>
        <?php if ( has_excerpt() ) : ?><p class="hrp-lede"><?php echo esc_html( get_the_excerpt() ); ?></p><?php endif; ?>
        <p class="hr-research__meta">Published <time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time> · Headline Recorder</p>
        <?php if ( $file ) : ?>
            <p class="hr-research__actions">
                <a class="hrp-btn" href="<?php echo esc_url( $file['url'] ); ?>" target="_blank" rel="noopener">Read the PDF<?php echo $file['size'] ? esc_html( ' (' . size_format( $file['size'] ) . ')' ) : ''; ?></a>
                <a class="hrp-btn hrp-btn--ghost" href="<?php echo esc_url( $file['url'] ); ?>" download>Download</a>
            </p>
        <?php endif; ?>
    </header>
    <div class="hr-research__content entry-content"><?php the_content(); ?></div>
    <?php if ( $file ) : ?>
        <div class="hr-research__viewer">
            <iframe src="<?php echo esc_url( $file['url'] ); ?>#view=FitH" title="<?php echo esc_attr( get_the_title() . ' (PDF)' ); ?>" loading="lazy"></iframe>
        </div>
    <?php endif; ?>
</main>
<?php
get_footer();
