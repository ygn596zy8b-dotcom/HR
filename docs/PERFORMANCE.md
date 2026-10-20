# Performance, geographic delivery and GEO

Owner decision 7 (docs/SPEC.md): mobile LCP < 2.0 s on 4G, INP < 150 ms, CLS < 0.05, Lighthouse ≥ 95 in every
category, edge caching close to readers in Pakistan, and generative-engine optimisation. This file records what was
measured, what changed, what was tried and dropped, and the hosting settings that finish the job in production.

## How it was measured

- Lighthouse 13.5.0, mobile form factor, simulated throttling (Moto G class CPU ×4, 150 ms RTT, 1.6 Mbit/s).
- The local dev server (`php -S`) speaks HTTP/1.1 without compression, which production never does. Lighthouse
  therefore ran through a small HTTPS + HTTP/2 + Brotli front (as LiteSpeed / Cloudflare serve the site). Raw dev-server
  numbers are listed too.
- No page cache locally: every run includes the full WordPress render (TTFB 150–260 ms). In production anonymous
  readers get LiteSpeed / Cloudflare cache hits.
- Third parties (TradingView, Google, YouTube) are unreachable from the sandbox, so their cost is not in the numbers
  and their failed requests are why best-practices shows 96 ("errors in console") on those pages.
- Simulated LCP on this shared machine varies ±300 ms between runs, so "after" is the median of 3 runs.
  JSON summaries: `/home/user/site/perf/` (`before-*` single run, `before3-*` median of 3 with `inc/perf.php` switched
  off, `after-*` median of 3, `after-raw-*` dev server).

## Before / after

Production-like front (HTTP/2 + Brotli), mobile. Scores are perf / a11y / best-practices / SEO.

| Page | Before (1 run) | Before (median of 3) | **After (median of 3)** |
|---|---|---|---|
| Home `/` | 93/90/96/100 · FCP 2.45 s · LCP 2.72 s · CLS 0 · 480 KB | 84 · FCP 3.44 s · LCP 3.44 s | **98/100/96/100 · FCP 1.68 s · LCP 2.21 s · TBT 74 ms · CLS 0 · 324 KB** |
| Article | 90/99/96/100 · FCP 1.95 s · LCP 3.40 s · CLS 0.022 · 464 KB | 83 · FCP 2.82 s · LCP 3.90 s | **98/99/96/100 · FCP 1.70 s · LCP 2.15 s · TBT 32 ms · CLS 0 · 309 KB** |
| `/podcast/` | 96/99/96/100 · FCP 2.24 s · LCP 2.24 s · 416 KB | 86 · FCP 3.23 s · LCP 3.30 s | **98/99/96/100 · FCP 1.67 s · LCP 2.28 s · TBT 17 ms · CLS 0 · 260 KB** |
| `/account/login/` | 99/100/96/66 · FCP 1.67 s · LCP 1.76 s · 412 KB | 92 · FCP 2.69 s · LCP 2.74 s | **97/100/100/66 · FCP 1.69 s · LCP 2.38 s · TBT 38 ms · CLS 0.013 · 268 KB** |

Raw dev server (HTTP/1.1, no compression, 1 run): home 59 → 64 (LCP 8.5 → 7.3 s), article 60 → 65 (8.4 → 6.5 s),
podcast 59 → 65 (7.5 → 6.2 s), login 64 → 66 (5.8 → 6.0 s).

Server (dev box, no page cache): home render 236 → ~140 ms, 190 → 94 database queries.

Notes:
- SEO 66 on `/account/login/` is intended: account pages are `noindex`.
- `/podcast/` a11y 99: the podcast archive template has no `<main>` landmark (podcast module).
- Article a11y 99: an `h3` directly under the `h1` inside that article's body (editorial content).
- Login "before" is bimodal (91 / 92 / 99); the single 99 run was the fast mode.
- **LCP < 2.0 s is met in the fast runs (1.93–2.05 s) but not at the median (2.15–2.38 s).** What is left on the
  critical path: ~85 KB (Brotli) of render-blocking parent-theme + Bootstrap CSS, 27 KB of HTML (31 KB of it raw is the
  Customizer's Additional CSS, printed inline) and an uncached 150–200 ms TTFB. In production the edge cache (TTFB
  ~30–80 ms inside Pakistan) and Early Hints remove most of the TTFB term. If field LCP (Sentry / CrUX) still misses,
  the next step is LiteSpeed's critical CSS ("Load CSS Asynchronously", below), then moving Additional CSS into a file.
- INP cannot be measured by a navigation run; TBT ≤ 74 ms and the only new handlers are a delegated click (YouTube)
  and an IntersectionObserver. Watch INP in the field with Sentry browser tracing (`HR_SENTRY_TRACES`).

## What changed

Theme `inc/perf.php` (all front-end policy in one place) unless noted.

| Change | Why |
|---|---|
| jQuery moved to the footer; every classic footer script deferred (WordPress keeps any with inline "after" code blocking) | jQuery + migrate were the only render-blocking scripts; on short pages the footer scripts still held the first paint |
| Parent's forced `loading="lazy"` undone for images that ask for `fetchpriority="high"` | the parent filter lazy-loaded the article hero (the LCP), costing ~350 ms of load delay |
| Parent's preload of the 1900 px header PNG (712 KB) removed | decorative masthead background, applied by script; the preload took bandwidth from the LCP |
| Source Sans: Latin subsets, 75 KB → 15 KB per face, declared inline + preloaded (`assets/fonts/`, OFL) | both faces are used in the first screen; renamed "HR Sans" internally (OFL Reserved Font Name) |
| Blockspare CSS + Font Awesome only on pages with Blockspare blocks (none today) | ~20 KB of CSS + an icon font; the only `fa` icon (`#scroll-up`) was hidden by the parent anyway (removed) |
| Contact Form 7 + reCAPTCHA only on Contact and PR & Media (or content with a form) | reCAPTCHA (~150 KB of Google script) loaded on every page |
| TradingView: inert `<script type="text/plain" data-hr-tv>` started by `hr-ui.js` after `load` + idle, when within 400 px of the viewport; heights reserved (ticker 46 px, markets page widgets) | the widgets are ~1 MB of third-party script/iframes; reserved space keeps CLS at 0 when they arrive |
| YouTube facade (`hr_youtube_facade()`): poster + play button, the privacy-enhanced player loads on tap (spotlight video and article oEmbeds) | the player is ~1 MB of script per embed |
| Home sections cached as HTML (transient `hr_home_sections`, cleared on any post/term/Customizer/platform-settings change, 10 min backstop) | ~100 queries / ~90 ms per uncached render; identical for every reader |
| Speculation Rules: prefetch on hover/touch (`moderate`), never `/account/*`, `/dashboard/*`, `/wp-json/*`, feeds, `/signed/*`, `/og/*`; core already skips wp-admin, `wp-*.php` (login/logout) and query URLs, and signed-in readers | near-instant article-to-article navigation |
| `content-visibility: auto` on every home section below the first screen, with placeholder heights above the tallest real section (`hr-elevate.css` §7) | skips layout/paint of ~8,000 px of the home page; the old 900 px placeholder let skipped content overlap the next section (axe flagged it) |
| Today's Briefing card image lazy (was `fetchpriority=high` while below the fold); article hero `sizes` matches its 700 px column | stop below-fold images competing with the LCP; desktop downloads the 768 w file instead of 1200 w |
| `<main>` around the home sections; "Latest News" label is the `h2` its `h3` headlines need; cookie sheet gets `scroll-padding-bottom` | a11y 90 → 100 on the home page (computed styles unchanged) |

Already in place and kept: `no_found_rows` on every home query, `width`/`height`/`decoding=async` on images, Google
Analytics only after consent (Consent Mode, `includes/site/consent.php`), deferred theme/plugin scripts, no `unload`
handlers (bfcache-friendly; account pages are `no-store` on purpose).

### Tried and dropped (measured, within noise or wrong)

- Opacity-only scroll reveal (instead of fade + rise) to fix the home a11y contrast findings: no change — the cause was
  the `content-visibility` placeholder. Reverted.
- Blocking Lato / the first briefing image to see if they hold the LCP: ±300 ms noise, no signal. Lato stays (it is
  the body face and already a Latin subset).
- Critical-CSS inlining by hand: the cascade depends on print order (`wp_head` 101 Additional CSS, 999 theme files);
  a hand-made critical set would break on every design change. Use LiteSpeed's generated CCSS instead (below).

### Database, cron, options

- Autoloaded options: 529 rows, **134 KB** (healthy; keep under ~800 KB). Top: `_transient_wp_core_block_css_files`
  23 KB, `rewrite_rules` 18 KB, `wp6m_user_roles` 7 KB, `litespeed.cloud._summary` 7 KB, `wpseo_titles` 7 KB,
  `aio_wp_security_configs` 7 KB, `hr_platform_settings` 6 KB.
- Not autoloaded but huge: `aiowps_plugin_checksums` **9.7 MB** and `aiowps_core_checksums` 1.3 MB (All-In-One
  Security file-change scans; the plugin is inactive). Delete both if file-change detection is not used:
  `wp option delete aiowps_plugin_checksums aiowps_core_checksums`.
- WP-Cron: 34 events, no duplicates. `aios_change_auth_keys_and_salt` (weekly) rotates the salts and signs every
  reader out each week — switch it off in AIOS if readers complain about losing their streak session.
  On cPanel set `define( 'DISABLE_WP_CRON', true );` and a real cron every 5 minutes:
  `*/5 * * * * cd /home/USER/public_html && /usr/local/bin/php wp-cron.php >/dev/null 2>&1`
  (keeps cron off the request path and runs the streak / signing jobs on time).

## LiteSpeed Cache (origin)

The plugin is installed; enable it in production. Settings that match this site:

- **Cache:** Enable Cache ON, Cache Logged-in Users OFF, Cache Commenters OFF, Cache REST API OFF,
  Cache Login Page ON, Cache Mobile OFF (one responsive design).
- **TTL:** Default Public Cache TTL 3600, Front Page TTL 600, Feed TTL 600, 404 TTL 3600.
- **Purge:** "Auto Purge Rules For Publish/Update" → All pages, Front page, Home page, Pages, Author archive,
  Post type archive, Yearly/Monthly/Daily archive, Term archive. "Serve Stale" ON.
- **Excludes → Do Not Cache URIs:** `/account/`, `/dashboard/`, `/wp-json/hr/v1/`, `/verify/` (posts its own
  form), `/thank-you/`. **Do Not Cache Cookies:** `hr_li`, `wordpress_logged_in`. (The plugin also sends `no-store`
  and `litespeed_control_set_nocache` on personalised responses.)
- **ESI:** OFF. Nothing per-user is rendered on cacheable pages: the header account chip and streak flame come from
  `admin-ajax.php?action=hr_session`.
- **Guest Mode:** ON (first-time visitors get the cached page immediately). **Guest Optimization:** OFF until tested.
- **Page Optimization → CSS:** CSS Minify ON, **CSS Combine OFF** (the theme's cascade depends on print order; with
  HTTP/2+ separate files cost little), UCSS OFF, "Load CSS Asynchronously" (CCSS via QUIC.cloud) — try on staging,
  keep it if pages look identical; it is the main remaining LCP lever. Font Display Optimization: Default (the theme
  already uses `swap`).
- **JS:** JS Minify ON, **JS Combine OFF**, **Load JS Deferred OFF** (the theme already defers everything safely;
  LiteSpeed's defer would also delay the inline consent/ticker config).
- **HTML:** HTML Minify ON, DNS Prefetch Control ON, Remove Query Strings OFF (files are versioned by mtime),
  Remove Google Fonts N/A, Remove WordPress Emoji ON.
- **Media:** **Lazy Load Images OFF** (the theme sets `loading` itself and keeps the LCP eager), Responsive
  Placeholder OFF, Add Missing Sizes ON.
- **Image Optimization (QUIC.cloud):** Auto Request Cron ON, Optimize Original Images ON, keep backups,
  **Next-Gen Image Format: AVIF** (falls back to WebP), **Image WebP/AVIF Replacement ON**, "WebP For Extra srcset" ON,
  and add `div.data-background` to **"WebP Attribute To Replace"** so the masthead background (712 KB PNG) is served
  as WebP (~80 KB). Most uploads are already WebP; this mainly converts the older PNG/JPEG ones.
- **Browser cache:** ON, TTL 31557600 for static files (theme/plugin files carry `?ver=<mtime>`).
- **Crawler:** ON with the Yoast sitemap (`/sitemap_index.xml`), Delay 500 µs, Run Duration 400 s, Interval 3600 s,
  Threads 1, Server Load Limit 1 — gentle on shared hosting; it re-warms the cache after purges.
- **Object Cache:** ON if the host offers Redis or LSMCD (cPanel → "Redis"/"Memcached"); otherwise OFF.

## Cloudflare (edge, close to readers)

The full Cloudflare setup (TLS, post-quantum key exchange, WAF, real IPs) is in `infra/cloudflare/README.md`; its
caching step is the HTML edge cache summarised here.

- **Cache Rule "Anonymous HTML"** — when hostname is `headlinerecorder.com`, method GET/HEAD, and NOT (cookie
  contains `wordpress_logged_in_` or `hr_li` or `wp-postpass_` or `comment_author_`, or path starts with `/account/`,
  `/dashboard/`, `/wp-json/hr/v1/`, `/wp-admin/`, `/wp-login.php`, `/verify/`, or the query string is non-empty):
  Eligible for cache; **Edge TTL** override: 5 min for `/` and archives, 1 h for articles (`/20*`); **Browser TTL**:
  respect origin (HTML is revalidated); **Serve stale content while revalidating** ON. Never cache responses with
  `Set-Cookie` (Cloudflare's default).
- **Purge on publish:** LiteSpeed Cache → CDN → Cloudflare API (zone + API token with *Cache Purge* only) so a
  purge in LiteSpeed also purges Cloudflare. Simpler alternative: Cloudflare **APO** for WordPress (paid add-on),
  which caches HTML at every PoP and purges on publish; it bypasses on WordPress login cookies by default — add
  `hr_li` to its bypass cookies.
- **Speed:** Tiered Cache (Smart Tiered Topology) ON, **HTTP/3 (QUIC)** ON, **Early Hints** ON (Cloudflare turns the
  page's `preload` links — fonts, CSS — into 103 responses while the origin renders), Brotli is always on, 0-RTT OFF
  (replay risk on forms), Rocket Loader OFF (breaks the consent / ticker ordering), Auto Minify n/a (deprecated).
- **Where readers are served:** Cloudflare has PoPs in **Karachi (KHI), Lahore (LHE) and Islamabad (ISB)**, so with
  the HTML cached at the edge most Pakistani readers get pages from inside the country (TTFB tens of ms instead of a
  round trip to the origin data centre). Which PoP an ISP reaches depends on that ISP's peering; when a Pakistani ISP
  routes abroad (Dubai, Mumbai, Singapore, Marseille), Tiered Cache still keeps hits off the origin. Readers in the Gulf
  and UK/US hit their local PoP.
- Check a hit: `curl -sI https://headlinerecorder.com/ | grep -iE 'cf-cache-status|cf-ray'` → `HIT` and a ray ID
  ending in `-KHI`, `-LHE` or `-ISB` from Pakistan.

## Origin checklist (cPanel / LiteSpeed)

- PHP **8.4** (cPanel → MultiPHP Manager) with **OPcache** on: `opcache.memory_consumption=256`,
  `opcache.interned_strings_buffer=16`, `opcache.max_accelerated_files=20000`, `opcache.validate_timestamps=1`,
  `opcache.revalidate_freq=60`; JIT off (no gain for WordPress).
- Persistent **object cache** (Redis or LSMCD) if offered — WordPress then keeps query results and the home/llms
  transients in memory instead of the options table.
- HTTP/2 and HTTP/3 on (LiteSpeed default), Brotli/gzip for `text/*`, JS, JSON, SVG and `font/ttf`.
- MariaDB 11.4: `innodb_buffer_pool_size` ≥ the data size if the host allows; delete the AIOS checksum options above.
- `DISABLE_WP_CRON` + real cron (above). Keep `WP_DEBUG` off in production.

## Generative-engine optimisation (GEO)

- **`/llms.txt`** — Yoast writes it weekly. Its post links now carry each article's editor-written summary
  (`_hr_geo_summary`, else the excerpt; `includes/site/geo.php`). In Yoast → Settings → Site features → llms.txt
  choose **manual page selection** (About, Editorial & Corrections Policy, Markets, Authors & Experts, PR & Media,
  Contact, Podcast) — automatic selection currently lists the reader Dashboard.
- **`/llms-full.txt`** (new, `includes/site/geo.php`) — publisher facts (address, editorial policy, contact, signing),
  how to cite, sections, and the latest 50 articles with date, update date, author, section and summary.
  Markdown, `Cache-Control: public, max-age=3600, stale-while-revalidate=86400`, rebuilt when posts change.
- **Article JSON-LD** (`inc/schema.php`): `NewsArticle` with ImageObject (size), `inLanguage`, `articleSection`,
  `keywords`, `wordCount`, `abstract` (the summary), `datePublished`/`dateModified`, author `Person` with bio and
  `sameAs` (guest LinkedIn / X / Facebook), publisher linked to the site-wide `#organization` node, and `speakable`
  (headline, summary, first paragraphs). Guest-author archives add a `ProfilePage` + `Person` (bio, photo, profiles).
- **Freshness:** articles edited more than a day after publishing show "Updated <date>" next to the date, matching
  `dateModified`.
- **Organization / Podcast** JSON-LD (Yoast + `includes/site/schema.php`): NewsMediaOrganization with address,
  contact point, editorial and corrections policies; PodcastSeries / PodcastEpisode on the podcast pages.
- **robots.txt:** the static `robots.txt` in the web root (allows OAI-SearchBot, ChatGPT-User, PerplexityBot,
  Claude-SearchBot, GPTBot, ClaudeBot, Google-Extended; blocks CCBot, Bytespider …) is kept as is. A static file wins
  over WordPress's virtual robots.txt.
- **IndexNow:** the official IndexNow plugin is installed but not active in the production database — activate it
  (`wp plugin activate indexnow`) so Bing, Yandex, Naver and Seznam (and answer engines that read them) see new and
  updated articles within minutes.
