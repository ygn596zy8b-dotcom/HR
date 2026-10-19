<?php
/**
 * Post-quantum content signing: canonical article bytes, the signer's queue/upload API, public signature files,
 * the publisher keyset, the /verify/ page and the "signed" notices.
 *
 * The signing secret key never reaches this server. A signer (GitHub Action or an editor's machine, see
 * security/pq/wp-sign.mjs) pulls the queue, signs locally with ML-DSA-87 + Ed448 and uploads the signatures.
 */

namespace HR\Platform\Signing;

if ( ! defined( 'ABSPATH' ) ) exit;

const ALG          = 'ML-DSA-87+Ed448';
const NS_URI       = 'https://headlinerecorder.com/ns/pqsig/1';
const ROLE         = 'hr_signer';
const CAP          = 'hr_sign_content';
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

require_once __DIR__ . '/keyset.php';
require_once __DIR__ . '/canonical.php';
require_once __DIR__ . '/rest.php';
require_once __DIR__ . '/routes.php';
require_once __DIR__ . '/display.php';

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
