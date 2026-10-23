<?php
/**
 * Post-quantum content signing: canonical article bytes, automatic server-side signing, the signer's queue/upload
 * API, public signature files, the publisher keyset, the /verify/ page and the "signed" notices.
 *
 * The site signs every article and the feeds itself with ML-DSA-87 + Ed448 (pure PHP, pq/), using a server signing
 * key that is kept encrypted outside the web root (keystore.php, set up in Settings > Content signing). The REST
 * queue/upload API stays as the offline alternative (security/pq/wp-sign.mjs). See docs/SIGNING.md.
 */

namespace HR\Platform\Signing;

if ( ! defined( 'ABSPATH' ) ) exit;

const ALG          = 'ML-DSA-87+Ed448';
const NS_URI       = 'https://headlinerecorder.com/ns/pqsig/1';
const ROLE         = 'hr_signer';
const CAP          = 'hr_sign_content';
// Signatures live in their own table (store.php). These four meta keys are where older versions kept them; they are
// read only until the one-time migration has moved them. META_STALE is still post meta (a small queue marker).
const META_SIG     = '_hr_pqsig';
const META_SHA     = '_hr_pqsig_sha512';
const META_KID     = '_hr_pqsig_kid';
const META_AT      = '_hr_pqsig_at';
const META_STALE   = '_hr_pqsig_stale';
const FEED_OPTION  = 'hr_pqsig_feed_';

// FIPS 204 ML-DSA-87 and RFC 8032 Ed448 sizes, in bytes.
const MLDSA87_PK_BYTES  = 2592;
const MLDSA87_SIG_BYTES = 4627;
const ED448_PK_BYTES    = 57;
const ED448_SIG_BYTES   = 114;

require_once __DIR__ . '/pq/load.php';
require_once __DIR__ . '/keyset.php';
require_once __DIR__ . '/canonical.php';
require_once __DIR__ . '/crypto.php';
require_once __DIR__ . '/keystore.php';
require_once __DIR__ . '/store.php';
require_once __DIR__ . '/rest.php';
require_once __DIR__ . '/signer.php';
require_once __DIR__ . '/routes.php';
require_once __DIR__ . '/display.php';
if ( is_admin() || wp_doing_ajax() ) {
    require_once __DIR__ . '/admin.php';
}
require_once __DIR__ . '/cli.php';

function install_role(): void {
    $role = get_role( ROLE );
    if ( ! $role ) {
        add_role( ROLE, 'Content signer', array( 'read' => true, CAP => true ) );
    } elseif ( ! $role->has_cap( CAP ) ) {
        $role->add_cap( CAP );
    }
}
add_action( 'hr_install', __NAMESPACE__ . '\\install_role' );
add_action( 'hr_install', __NAMESPACE__ . '\\schedule_sweep' );

// The platform only runs hr_install when its DB version changes, so a module added later heals itself once.
add_action( 'init', function () {
    if ( ! get_role( ROLE ) ) {
        install_role();
    }
}, 30 );
add_action( 'admin_init', __NAMESPACE__ . '\\schedule_sweep' );
