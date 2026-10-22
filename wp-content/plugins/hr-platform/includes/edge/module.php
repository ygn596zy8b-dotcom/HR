<?php
/**
 * Edge: fast delivery from the reader's nearest Cloudflare city, without ever sharing anything personal.
 *   settings.php     TTLs, switches and the edge status panel (Settings > Headline Recorder > Edge cache)
 *   headers.php      Cache-Control / CDN-Cache-Control for anonymous pages, feeds and /signed/*; Early Hints links
 *   purge.php        Cloudflare purge on publish/update/trash/delete, term edits and stored signatures (async, batched)
 *   hints.php        speculation rules exclusions and resource hint clean-up
 * The production runbook (Cloudflare clicks, LiteSpeed settings, budgets) is docs/PERFORMANCE.md.
 */

namespace HR\Platform\Edge;

if ( ! defined( 'ABSPATH' ) ) exit;

foreach ( array( 'settings', 'headers', 'purge', 'hints' ) as $hr_edge_part ) {
    require_once __DIR__ . "/{$hr_edge_part}.php";
}
unset( $hr_edge_part );
