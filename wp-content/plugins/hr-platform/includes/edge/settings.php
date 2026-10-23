<?php
/**
 * Edge cache settings and the status panel on Settings > Headline Recorder.
 * The Cloudflare credentials are wp-config.php constants (never stored in the database):
 *   define( 'HR_CF_ZONE_ID', '<32-character zone ID>' );
 *   define( 'HR_CF_API_TOKEN', '<API token with Zone > Cache Purge only>' );
 */

namespace HR\Platform\Edge;

use function HR\Platform\setting;

if ( ! defined( 'ABSPATH' ) ) exit;

/** Defaults in seconds. Every value can also be changed in code with the `hr_edge_ttl` filter. */
const TTL_DEFAULTS = array(
    'single' => 3600,  // articles, pages, episodes: purged on every change, so an hour at the edge is safe
    'form'   => 300,   // pages with a contact form: the CF7 honeypot token in the form is only valid for two hours
    'list'   => 300,   // home, sections, tags, authors, archives, /podcast/: collect new stories from many posts
    'feed'   => 600,   // RSS/Atom feeds and XML sitemaps
    'signed' => 3600,  // /signed/* and the public keyset: purged when the signer uploads
    'gone'   => 60,    // 404 and 410
    'swr'    => 600,   // stale-while-revalidate: serve the old copy for this long while the edge refetches
    'sie'    => 86400, // stale-if-error: keep the site readable for a day if the origin is down
);

add_filter( 'hr_settings_schema', function ( array $schema ): array {
    $number = static fn( string $label, string $key, string $help ) => array(
        'label'       => $label,
        'type'        => 'number',
        'default'     => TTL_DEFAULTS[ $key ],
        'description' => $help,
        'sanitize'    => static fn( $v ) => '' === $v ? '' : max( 0, min( 31536000, (int) $v ) ),
    );
    $schema['edge'] = array(
        'title'       => 'Edge cache (Cloudflare)',
        'description' => 'Anonymous readers get pages from the nearest Cloudflare city (Karachi, Lahore and Islamabad inside Pakistan). Only pages that are the same for everyone are marked shareable; signed-in readers, account pages, search, previews and the REST API never are. Values are in seconds. Setup guide: docs/PERFORMANCE.md.',
        'fields'      => array(
            'edge_cache_headers' => array( 'label' => 'Send edge cache headers', 'type' => 'checkbox', 'default' => 1, 'description' => 'Adds Cache-Control with s-maxage to pages that are safe to share. Switch off to fall back to WordPress defaults.' ),
            'edge_ttl_single'    => $number( 'Articles and pages', 'single', 'Edge lifetime of an article, page or episode. Changes are purged at once, so this is only a backstop. Until Cloudflare purging is connected (HR_CF_ZONE_ID and HR_CF_API_TOKEN), articles and signatures are capped at the home page value so a correction is never stuck at the edge for long.' ),
            'edge_ttl_form'      => $number( 'Pages with a contact form', 'form', 'Contact and PR & Media pages. Kept short because the spam check inside the form expires after two hours.' ),
            'edge_ttl_list'      => $number( 'Home and section pages', 'list', 'Home, categories, tags, authors and the podcast page.' ),
            'edge_ttl_feed'      => $number( 'Feeds and sitemaps', 'feed', 'RSS feeds, the podcast feed and XML sitemaps.' ),
            'edge_ttl_signed'    => $number( 'Signatures', 'signed', '/signed/* files and /.well-known/pqsig-keys.json.' ),
            'edge_ttl_gone'      => $number( 'Not found (404/410)', 'gone', 'Kept short so a page published a minute later is not hidden.' ),
            'edge_swr'           => $number( 'Serve stale while refreshing', 'swr', 'stale-while-revalidate.' ),
            'edge_sie'           => $number( 'Serve stale if the server is down', 'sie', 'stale-if-error.' ),
            'edge_purge'         => array( 'label' => 'Purge Cloudflare on changes', 'type' => 'checkbox', 'default' => 1, 'description' => 'Needs HR_CF_ZONE_ID and HR_CF_API_TOKEN in wp-config.php.' ),
        ),
    );
    return $schema;
} );

function headers_enabled(): bool {
    return (bool) apply_filters( 'hr_edge_headers_enabled', ! empty( setting( 'edge_cache_headers' ) ) );
}

/** Edge lifetime for a kind of response: single, form, list, feed, signed, gone, swr, sie. */
function ttl( string $kind ): int {
    $value = setting( 'edge_' . ( in_array( $kind, array( 'swr', 'sie' ), true ) ? $kind : 'ttl_' . $kind ) );
    $value = is_numeric( $value ) ? (int) $value : ( TTL_DEFAULTS[ $kind ] ?? 0 );
    // Articles and signature files rely on the purge to stay fresh. Without a working purge connection a changed
    // article could stay old at the edge for the whole lifetime, so they get no more than a home page.
    if ( in_array( $kind, array( 'single', 'signed' ), true ) && ! purge_enabled() ) {
        $value = min( $value, ttl( 'list' ) );
    }
    return max( 0, min( 31536000, (int) apply_filters( 'hr_edge_ttl', $value, $kind ) ) );
}
