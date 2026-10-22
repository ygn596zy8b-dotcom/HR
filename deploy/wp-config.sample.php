<?php
/**
 * Production wp-config.php template for headlinerecorder.com.
 *
 * The old wp-config.php was published in a public archive (see docs/INCIDENT-2026-10-public-archive.md):
 * every value marked CHANGE ME must be NEW, not copied from the old file.
 * Keep this file at 0400/0440 permissions. Never commit the filled-in copy.
 */

// Begin AIOWPSEC Firewall (kept from the original file; the path is the host's account path)
if ( file_exists( '/home/esofkogo/headlinerecorder.com/aios-bootstrap.php' ) ) {
    include_once '/home/esofkogo/headlinerecorder.com/aios-bootstrap.php';
}
// End AIOWPSEC Firewall

define( 'WP_CACHE', true ); // LiteSpeed Cache

// ** Database: CHANGE ME (new password; disable remote MySQL in cPanel) ** //
define( 'DB_NAME', 'CHANGE_ME' );
define( 'DB_USER', 'CHANGE_ME' );
define( 'DB_PASSWORD', 'CHANGE_ME' );
define( 'DB_HOST', 'localhost' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

// ** Keys and salts. CHANGE ME: paste fresh values from https://api.wordpress.org/secret-key/1.1/salt/ ** //
define( 'AUTH_KEY',         'CHANGE_ME' );
define( 'SECURE_AUTH_KEY',  'CHANGE_ME' );
define( 'LOGGED_IN_KEY',    'CHANGE_ME' );
define( 'NONCE_KEY',        'CHANGE_ME' );
define( 'AUTH_SALT',        'CHANGE_ME' );
define( 'SECURE_AUTH_SALT', 'CHANGE_ME' );
define( 'LOGGED_IN_SALT',   'CHANGE_ME' );
define( 'NONCE_SALT',       'CHANGE_ME' );

$table_prefix = 'wp6m_';

// ** Production behaviour ** //
define( 'WP_ENVIRONMENT_TYPE', 'production' );
define( 'WP_DEBUG', false );          // errors go to Sentry, never to visitors
define( 'WP_DEBUG_LOG', false );
define( 'WP_DEBUG_DISPLAY', false );
define( 'DISALLOW_FILE_EDIT', true ); // no theme/plugin editor in wp-admin
define( 'FORCE_SSL_ADMIN', true );
define( 'WP_POST_REVISIONS', 25 );
define( 'WP_AUTO_UPDATE_CORE', 'minor' );

// ** Headline Recorder platform (all optional; features stay off until set) ** //
// Sentry: create a project at sentry.io → Settings → Client Keys (DSN). The DSN is not a secret, but keep it here.
// define( 'HR_SENTRY_DSN', 'https://<key>@o<org>.ingest.sentry.io/<project>' );
// define( 'HR_SENTRY_TRACES', 0.05 );           // share of requests traced for performance
// define( 'HR_RELEASE', 'headline-recorder@1.0.0' );

// Claude API for AI-drafted podcast episodes (Settings → Headline Recorder → Podcast → enable). SECRET.
// define( 'ANTHROPIC_API_KEY', 'CHANGE_ME' );

// Encrypted reader submissions are stored here, outside the web root.
define( 'HR_PRIVATE_DIR', '/home/esofkogo/hr-private' );

// Optional: once every editor/author has added a passkey or two-step code, require it for wp-admin (admins are
// never redirected, they only see a reminder).
// define( 'HR_ENFORCE_STAFF_MFA', true );

// Behind Cloudflare: trust CF-Connecting-IP only from Cloudflare ranges (see infra/cloudflare/README.md).
// define( 'HR_TRUSTED_PROXY_HEADER', 'HTTP_CF_CONNECTING_IP' );
// define( 'HR_TRUSTED_PROXIES', '173.245.48.0/20,...' );

// Cloudflare cache purge on publish/update (docs/PERFORMANCE.md, "Connect the purge"). API token with the single
// permission Zone > Cache Purge > Purge for this zone; nothing is sent while these are absent.
// define( 'HR_CF_ZONE_ID', 'CHANGE_ME' );   // 32 characters, Cloudflare > Overview > API > Zone ID
// define( 'HR_CF_API_TOKEN', 'CHANGE_ME' );

/* That's all, stop editing! Happy publishing. */

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}
require_once ABSPATH . 'wp-settings.php';
