<?php
/**
 * Visible notices: the byline pill, the note after the article, the footer line, the RSS signature element,
 * the <link rel="alternate"> to the signature file, and a status summary on the settings page.
 */

namespace HR\Platform\Signing;

use function HR\Platform\asset_url;
use function HR\Platform\asset_ver;
use function HR\Platform\enqueue_base_style;
use function HR\Platform\render;

if ( ! defined( 'ABSPATH' ) ) exit;

// The footer line is on every page, so it gets a few inline rules instead of a render-blocking stylesheet;
// signing.css is only for articles (pill + note) and /verify/ (enqueued by its route).
const TRUST_CSS = '.hr-pqsig-trust{display:flex;flex-wrap:wrap;align-items:center;gap:4px 8px;margin:0;font:400 13px/1.5 var(--hrp-font,sans-serif);color:inherit}'
    . '.hr-pqsig-trust .hr-icon{width:16px;height:16px;flex:none;color:var(--hrp-red,#ff001c)}'
    . '.hr-pqsig-trust a{display:inline-flex;align-items:center;min-height:44px;color:#fff;font-weight:700;text-decoration:none}'
    . '.hr-pqsig-trust a:hover{color:var(--hrp-red,#ff001c);text-decoration:underline;text-underline-offset:3px}'
    . '.hr-pqsig-trust a:focus-visible{outline:2px solid var(--hrp-red,#ff001c);outline-offset:2px}'
    // Phones: one flowing line (the icon stays with the text, whatever alignment the footer uses), no dangling dot.
    . '@media (max-width:600px){.hr-pqsig-trust{display:block}.hr-pqsig-trust .hr-icon{display:inline-block;vertical-align:-3px;margin-right:6px}'
    . '.hr-pqsig-trust>span[aria-hidden="true"]{display:none}.hr-pqsig-trust a{margin-left:6px}}';

add_action( 'wp_enqueue_scripts', function () {
    wp_register_style( 'hr-signing-trust', false, array(), null );
    wp_enqueue_style( 'hr-signing-trust' );
    wp_add_inline_style( 'hr-signing-trust', TRUST_CSS );
    if ( is_singular( 'post' ) ) {
        enqueue_base_style();
        wp_enqueue_style( 'hr-signing', asset_url( 'css/signing.css' ), array( 'hr-platform' ), asset_ver( 'css/signing.css' ) );
    }
} );

function current_post_id( $post_id ): int {
    $post_id = (int) $post_id;
    return $post_id > 0 ? $post_id : (int) get_the_ID();
}

add_action( 'hr_single_after_byline', function ( $post_id = 0 ) {
    $state = signature_state( current_post_id( $post_id ) );
    if ( $state ) {
        render( 'signing/badge', array( 'state' => $state ) );
    }
}, 10, 1 );

add_action( 'hr_single_after_content', function ( $post_id = 0 ) {
    $state = signature_state( current_post_id( $post_id ) );
    if ( $state ) {
        render( 'signing/note', array(
            'state'      => $state,
            'sig_path'   => '/signed/' . $state['post_id'] . '.sig.json',
            'sig_url'    => signature_url( $state['post_id'] ),
            'verify_url' => verify_url( '/signed/' . $state['post_id'] . '.sig.json' ),
            'about_url'  => verify_url(),
        ) );
    }
}, 10, 1 );

add_action( 'hr_footer_trust', function () {
    // Only claim signed feeds when the site serves one (Yoast's "Remove global feed" switches /feed/ off).
    render( 'signing/footer-trust', array( 'verify_url' => verify_url(), 'has_feeds' => (bool) feed_sources() ) );
} );

add_action( 'wp_head', function () {
    if ( ! is_singular( 'post' ) ) {
        return;
    }
    $state = signature_state( (int) get_queried_object_id() );
    if ( $state && 'signed' === $state['state'] ) {
        printf( '<link rel="alternate" type="application/json" title="Post-quantum signature" href="%s">' . "\n", esc_url( signature_url( $state['post_id'] ) ) );
    }
} );

add_action( 'rss2_ns', function () {
    echo "\n\t" . 'xmlns:hrsig="' . esc_attr( NS_URI ) . '"' . "\n";
} );

add_action( 'rss2_item', function () {
    $state = signature_state( (int) get_the_ID() );
    if ( $state && 'signed' === $state['state'] ) {
        printf(
            "\t\t" . '<hrsig:signature href="%s" alg="%s" kid="%s"/>' . "\n",
            esc_url( signature_url( $state['post_id'] ) ),
            esc_attr( ALG ),
            esc_attr( $state['kid'] )
        );
    }
} );

// Settings → Headline Recorder: what the signer has done so far.
add_action( 'hr_settings_section_after', function ( $section ) {
    if ( 'signing' !== $section || ! current_user_can( 'manage_options' ) ) {
        return;
    }
    global $wpdb;
    $public  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish' AND post_password = ''" );
    $pending = queue_candidate_ids( 0, 0, true );
    $signers = count( get_users( array( 'role' => ROLE, 'fields' => 'ID', 'number' => 50 ) ) );
    $keys    = keyset();
    ?>
    <table class="widefat striped" style="max-width:720px">
        <tbody>
            <tr><th scope="row">Keyset</th><td><?php echo $keys ? esc_html( sprintf( '%d key(s): %s', count( $keys['keys'] ), implode( ', ', array_map( static fn( $k ) => substr( $k['kid'], 0, 16 ) . '…' . ( empty( $k['revoked'] ) ? '' : ' (revoked)' ), $keys['keys'] ) ) ) ) : 'Not configured — nothing can be signed yet.'; ?></td></tr>
            <tr><th scope="row">Articles</th><td><?php echo esc_html( sprintf( '%d of %d published articles signed; %d waiting for the signer.', max( 0, $public - $pending ), $public, $pending ) ); ?></td></tr>
            <?php foreach ( feed_sources() as $key => $source ) : $feed = stored_feed( $key ); ?>
                <tr><th scope="row"><?php echo esc_html( 'Feed ' . $source ); ?></th><td><?php echo $feed ? esc_html( 'Signed snapshot from ' . wp_date( 'j M Y, H:i', (int) $feed['at'] ) ) : 'Not signed yet.'; ?></td></tr>
            <?php endforeach; ?>
            <tr><th scope="row">Signer accounts</th><td><?php echo esc_html( $signers ? sprintf( '%d user(s) with the Content signer role.', $signers ) : 'None. Create a user with the Content signer role and an application password for the signing job.' ); ?></td></tr>
        </tbody>
    </table>
    <?php
} );
