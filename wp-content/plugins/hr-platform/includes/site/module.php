<?php
/**
 * Site — SEO and trust layer on top of Yoast SEO and the theme:
 *   settings.php  Organisation details + cookie settings (Settings → Headline Recorder)
 *   seo.php       meta descriptions, titles, Open Graph / Twitter gaps
 *   og-card.php   branded 1200×630 share cards at /og/<post-id>-<version>.png
 *   icons.php     favicon sizes, maskable icon, /manifest.webmanifest, theme-color
 *   alt-text.php  alt text fallbacks + the "Fill missing alt text" tool
 *   schema.php    Organization address/contact, PodcastSeries/PodcastEpisode JSON-LD, footer address
 *   consent.php   cookie banner + Google Consent Mode v2
 *   thank-you.php /thank-you/ page and the newsletter / contact form redirects
 *   sitemaps.php  core sitemap additions (podcast archive, /verify/) and exclusions (private types, accounts)
 *   geo.php       /llms-full.txt (publisher facts, citation, latest articles with summaries), llms.txt link text
 */

namespace HR\Platform\Site;

if ( ! defined( 'ABSPATH' ) ) exit;

foreach ( array( 'settings', 'seo', 'og-card', 'icons', 'alt-text', 'schema', 'consent', 'thank-you', 'sitemaps', 'geo' ) as $hr_site_part ) {
    require_once __DIR__ . "/{$hr_site_part}.php";
}
unset( $hr_site_part );
require_once __DIR__ . '/typography.php'; // house style: no em dashes (on save, on display, Tools > Remove em dashes)
