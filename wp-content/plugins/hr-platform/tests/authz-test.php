<?php
/**
 * Authorization smoke test (MSBJ): object-level checks and REST route-case bypasses. Creates throwaway users and a
 * throwaway submission, removes them at the end. Run: wp eval-file wp-content/plugins/hr-platform/tests/authz-test.php
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$fails = 0;
$ok    = function ( bool $cond, string $label ) use ( &$fails ) {
    $fails += $cond ? 0 : 1;
    echo ( $cond ? 'PASS ' : 'FAIL ' ), $label, "\n";
};
$call = function ( int $uid, string $method, string $route, array $params = array() ): int {
    wp_set_current_user( $uid );
    $r = new WP_REST_Request( $method, $route );
    foreach ( $params as $k => $v ) {
        $r->set_param( $k, $v );
    }
    return rest_get_server()->dispatch( $r )->get_status();
};
$mk = fn( string $role ) => wp_insert_user( array( 'user_login' => 'hr-authz-' . $role . '-' . wp_generate_password( 6, false ), 'user_email' => $role . '-' . wp_generate_password( 6, false ) . '@example.test', 'user_pass' => wp_generate_password( 24 ), 'role' => $role ) );

$owner  = $mk( 'subscriber' );
$other  = $mk( 'subscriber' );
$editor = $mk( 'editor' );
$signer = $mk( 'hr_signer' );
update_user_meta( $owner, 'hr_email_verified', '1' );

// Self-service email/password changes through core REST are refused in any letter case.
foreach ( array( '/wp/v2/users/me', '/WP/V2/USERS/ME', '/Wp/V2/Users/Me/' ) as $route ) {
    $status = $call( $owner, 'POST', $route, array( 'email' => 'hijack@example.test' ) );
    $ok( 403 === $status && 'hijack@example.test' !== get_userdata( $owner )->user_email, "users/me email change refused at {$route} ({$status})" );
}

// Staff without MFA cannot write through /wp/v2/ when enforcement is on, whatever the case of the route.
$enforce = static fn() => true;
add_filter( 'hr_enforce_staff_mfa', $enforce );
foreach ( array( '/wp/v2/posts', '/WP/V2/POSTS' ) as $route ) {
    $status = $call( $editor, 'POST', $route, array( 'title' => 'authz probe', 'status' => 'draft' ) );
    $ok( 403 === $status, "unenrolled editor write refused at {$route} ({$status})" );
}
remove_filter( 'hr_enforce_staff_mfa', $enforce );

// Sealed submissions: only editors may fetch the envelope; the submitting reader and other readers may not.
$file = HR\Platform\Submissions\write_envelope( '{"protected":"x","kem":"x","iv":"x","ct":"x"}' );
$sid  = wp_insert_post( array( 'post_type' => 'hr_submission', 'post_status' => 'private', 'post_author' => $owner, 'post_title' => 'authz probe', 'meta_input' => array( '_hr_sub_file' => $file, '_hr_sub_status' => 'received' ) ) );
foreach ( array( 'other reader' => $other, 'owner' => $owner, 'signer' => $signer, 'guest' => 0 ) as $who => $uid ) {
    $status = $call( $uid, 'GET', "/hr/v1/submissions/{$sid}/envelope" );
    $ok( in_array( $status, array( 401, 403 ), true ), "envelope refused for {$who} ({$status})" );
}
$ok( 200 === $call( $editor, 'GET', "/hr/v1/submissions/{$sid}/envelope" ), 'envelope allowed for editor' );

// Signer endpoints: the capability alone (a browser session) is not enough; an application password is required.
$ok( 403 === $call( $signer, 'GET', '/hr/v1/signing/queue' ), 'signing queue refused for signer without application password' );
$ok( 403 === $call( $editor, 'GET', '/hr/v1/signing/queue' ), 'signing queue refused for editor' );

wp_set_current_user( 0 );
wp_delete_post( $sid, true );
require_once ABSPATH . 'wp-admin/includes/user.php';
foreach ( array( $owner, $other, $editor, $signer ) as $uid ) {
    wp_delete_user( $uid );
}
echo $fails ? "{$fails} FAILED\n" : "ALL PASS\n";
