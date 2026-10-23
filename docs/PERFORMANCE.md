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

- Opacity-only scroll reveal (instead of fade + rise) to fix the home a11y contrast findings: no change; the cause was
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
  reader out each week; switch it off in AIOS if readers complain about losing their streak session.
  On cPanel set `define( 'DISABLE_WP_CRON', true );` and a real cron every 5 minutes:
  `*/5 * * * * cd /home/USER/public_html && /usr/local/bin/php wp-cron.php >/dev/null 2>&1`
  (keeps cron off the request path and runs the streak / signing jobs on time).

## Global delivery: how a reader anywhere gets the site from a nearby city

The site lives on one LiteSpeed server. Without help, a reader in London or Toronto waits for every page to cross
the world and back. Cloudflare fixes that: its network answers on the same address in 330+ cities (anycast), and
once a page is stored ("cached") in a city, the next reader there gets it straight from that city.

- **Pakistan:** Cloudflare has data centres in **Karachi (KHI), Lahore (LHE) and Islamabad (ISB)**. Most Pakistani
  readers are served from inside the country, in tens of milliseconds. Which city an internet provider reaches
  depends on that provider's links; when one routes abroad (Dubai, Mumbai, Singapore, Marseille), Smart Tiered Cache
  still answers from Cloudflare instead of the server.
- **Abroad:** readers in the Gulf, UK, Europe and North America are served from their own city.
- **Freshness:** when an editor publishes or updates a story, the site tells Cloudflare to drop the old copy at once
  (see "Connect the purge"). Readers never wait for an expiry to see a correction.
- **Privacy:** only pages that are identical for every reader are shared. Anything personal (signed-in pages, the
  account area, the reading streak, search results, previews) always comes from the server, every time.

### What the site sends (plugin module `includes/edge/`)

| Response | Cache-Control sent by the site | Edge lifetime (default) |
|---|---|---|
| Home, sections, tags, authors, `/podcast/`, `/verify/` | `public, max-age=0, s-maxage=300, stale-while-revalidate=600, stale-if-error=86400` | 5 min |
| Articles, pages, episodes | same, `s-maxage=3600` | 1 hour once the purge is connected (purged on change); **5 min until then** |
| Contact and PR & Media (pages with a contact form) | same, `s-maxage=300` (LiteSpeed Cache is told 300 s too) | 5 min |
| RSS feeds (served as `application/xml`), `/podcast/feed/`, XML sitemaps | `public, max-age=300, s-maxage=600, ...` | 10 min |
| `/signed/*` signature files | `public, max-age=300, s-maxage=3600, ...` | 1 hour once the purge is connected; 5 min until then |
| 404 / 410 for guests | `public, max-age=0, s-maxage=60, ...` | 1 min |
| Anyone signed in, or with a personal cookie | WordPress `no-store, private` (or `private, no-cache`) | never cached |
| `/account/*`, `/dashboard/`, `/thank-you/`, `/wp-json/*`, `/wp-admin/*`, `wp-login.php`, search, previews, POST | untouched (no-store where the module sets it) | never cached |

`max-age=0` makes browsers ask again on every visit (so nobody keeps a stale front page), `s-maxage` is what
Cloudflare keeps, `stale-while-revalidate` lets Cloudflare answer instantly while it refreshes in the background, and
`stale-if-error` keeps the site readable for a day if the server is down. A `CDN-Cache-Control` header repeats the
edge values and `X-HR-Cache` says what was decided (`public; kind=single; ttl=3600` or `bypass; reason=cookie`).

Personal cookies that switch caching off: `wordpress_logged_in_*`, `wordpress_sec_*`, `wp-postpass_*`,
`comment_author_*`, `hr_li`. A query string switches it off too, except campaign tags (`utm_*`, `fbclid`, `gclid` ...);
a tagged copy is a separate entry at Cloudflare that a purge cannot reach, so it is kept 5 minutes at most. The parent
theme's dark-mode cookie (`covernews-stored-site-mode`) is ignored by the server, so it can never change the shared page.
Every place that prints personal data or a security token (account pages, the session endpoint
`admin-ajax.php?action=hr_session`, the streak dashboard, submissions, Contact Form 7's nonce for signed-in users,
comment forms for returning commenters) is covered by one of those rules. Forms that guests see carry no nonce:
Contact Form 7 adds none for guests, Mailchimp's timestamp field accepts pages up to 90 days old, and the spam-check
token of the CF7 honeypot add-on (valid 2 hours) is why form pages get the short 5-minute lifetime; the header account chip and streak flame
are filled in by the browser from the session endpoint, so cached pages carry no personal data.

The lifetimes are in **Settings > Headline Recorder > Edge cache (Cloudflare)**; developers can also use the
`hr_edge_ttl`, `hr_edge_cache_decision`, `hr_edge_bypass_paths` and `hr_edge_personal_cookies` filters.

Check from a terminal (after Cloudflare is live):

```sh
curl -sI https://headlinerecorder.com/ | grep -iE 'cf-cache-status|x-hr-cache|cache-control|cf-ray'
#   first request MISS, second HIT; cf-ray ends in -KHI, -LHE or -ISB from Pakistan
curl -sI -H 'Cookie: hr_li=1' https://headlinerecorder.com/ | grep -iE 'cf-cache-status|x-hr-cache'
#   cf-cache-status: BYPASS (or DYNAMIC), x-hr-cache: bypass; reason=cookie
curl -s https://headlinerecorder.com/cdn-cgi/trace | grep colo     # which Cloudflare city answered you
```

## Cloudflare setup (exact clicks)

Free plan is enough for everything below. The security side (post-quantum TLS details, DNSSEC, WAF rules, real
visitor IPs) is in `infra/cloudflare/README.md`; do both.

1. **Add the site.** dash.cloudflare.com > **Add a domain** > `headlinerecorder.com` > **Free** > Continue. Check the
   imported DNS records, then at the domain registrar replace the nameservers with the two Cloudflare shows.
   Wait for the "Active" email.
2. **DNS > Records.** The `A`/`AAAA` record for `@` (headlinerecorder.com) and the `www` record: **Proxied** (orange
   cloud). Keep **DNS only** (grey cloud) for mail and cPanel: `mail`, `webmail`, `cpanel`, `ftp`, `MX`, `autodiscover`
   and every TXT record (SPF, DKIM, DMARC), otherwise email breaks.
3. **SSL/TLS > Overview > Configure:** **Full (strict)**. The server keeps its own certificate (cPanel AutoSSL).
4. **SSL/TLS > Edge Certificates:** **Always Use HTTPS** On, **Minimum TLS Version** TLS 1.2, **TLS 1.3** On,
   **Automatic HTTPS Rewrites** On. Post-quantum key agreement (X25519MLKEM768) is on by default for every browser
   that supports it; there is nothing to switch on.
5. **Speed > Optimization** (Cloudflare moves these between tabs; use the search box if one is not where listed):
   **HTTP/3 (with QUIC)** On, **Early Hints** On, **0-RTT** Off (replay risk on forms), **Rocket Loader** Off (it
   breaks the consent and ticker script order). Brotli compression is always on. The site sends `Link: rel=preload`
   headers for its two text fonts, which Early Hints turns into a head start for the browser.
6. **Caching > Tiered Cache:** **Smart Tiered Caching Topology** On.
7. **Caching > Configuration:** Browser Cache TTL **Respect Existing Headers**.
8. **Caching > Cache Rules > Create rule.** Create three rules in this order (when several match, the last one wins,
   so the bypass rule must be last). Use "Edit expression" and paste:

   **Rule 1, "Static files (long)"**
   ```
   (http.request.uri.path.extension in {"css" "js" "woff2" "woff" "ttf" "webp" "avif" "png" "jpg" "jpeg" "gif" "svg" "ico" "mp3" "xsl"})
   ```
   Cache eligibility: **Eligible for cache**. Edge TTL: **Ignore cache-control header and use this TTL**, 30 days.
   Browser TTL: **Respect origin** (the server already sends one year for versioned theme files).

   **Rule 2, "Anonymous HTML"**
   ```
   (http.host eq "headlinerecorder.com" and not http.request.uri.path.extension in {"css" "js" "woff2" "woff" "ttf" "webp" "avif" "png" "jpg" "jpeg" "gif" "svg" "ico" "mp3" "xsl"})
   ```
   Cache eligibility: **Eligible for cache**. Edge TTL: **Use cache-control header if present, bypass cache if not**
   (the site decides page by page). Browser TTL: **Respect origin**. Serve stale content while revalidating: leave on.

   **Rule 3, "Never cache personal" (must be last)**
   ```
   (http.cookie contains "wordpress_logged_in_") or (http.cookie contains "wordpress_sec_") or (http.cookie contains "wp-postpass_") or (http.cookie contains "comment_author_") or (http.cookie contains "hr_li") or starts_with(http.request.uri.path, "/account") or starts_with(http.request.uri.path, "/dashboard") or starts_with(http.request.uri.path, "/thank-you") or starts_with(http.request.uri.path, "/reading/") or starts_with(http.request.uri.path, "/wp-admin") or starts_with(http.request.uri.path, "/wp-json") or http.request.uri.path eq "/wp-login.php" or http.request.uri.path eq "/xmlrpc.php" or http.request.uri.path eq "/wp-cron.php"
   ```
   Cache eligibility: **Bypass cache**. If the editor refuses `starts_with`, build the same conditions in the visual
   builder with "URI Path" + "starts with".

   The site never marks a response that sets a cookie as shareable (it sends no `s-maxage` then), so with "bypass
   cache if not" Cloudflare does not store it. Rule 3 is a second lock: even if a personal page ever sent a public
   header by mistake, Cloudflare would still not store it.
9. **Security > WAF > Managed rules:** keep the **Cloudflare Free Managed Ruleset** on (since 21 October 2026 it
   also blocks php2shell attempts, CVE-2026-109618/109619; keep `session.upload_progress.enabled` Off regardless). Custom and rate-limiting rules:
   `infra/cloudflare/README.md` steps 6 and 7.
10. **Security > Bots: Bot Fight Mode OFF.** On the Free plan it cannot be told to let anyone through, and it
    challenges exactly the visitors this site needs: feed readers, Apple Podcasts / Spotify fetching
    `/podcast/feed/`, partners' tools fetching `/signed/*` and `/.well-known/pqsig-keys.json`, the GitHub signer calling
    `/wp-json/hr/v1/signing/*`, and uptime checks. Use the WAF rules (managed challenge on `/wp-login.php`, the rate
    limit on the login forms) instead.
11. **Do not use** "Cache Everything" Page Rules or APO together with these Cache Rules. APO (paid add-on) is an
    alternative to steps 8 and "Connect the purge", not an addition.

### Connect the purge (required: do this the same day Cloudflare goes live)

Without it Cloudflare cannot be told that a story changed. The site protects itself by keeping articles and
signature files only 5 minutes at the edge until the two lines below are in `wp-config.php`; once they are, articles
get the full hour and every change is purged within seconds.

1. Cloudflare > top-right profile icon > **My Profile > API Tokens > Create Token > Create Custom Token**.
2. Name: `headlinerecorder cache purge`. Permissions: **Zone | Cache Purge | Purge**, nothing else.
   Zone Resources: **Include | Specific zone | headlinerecorder.com**. Optional: Client IP Address Filtering = the
   server's IP (cPanel home page, "Shared IP Address"). Continue to summary > **Create Token** > copy it (shown once).
3. Zone ID: Cloudflare > headlinerecorder.com > **Overview**, right-hand column, "API" box, **Zone ID** > Copy.
4. cPanel > File Manager > `public_html/wp-config.php` > Edit. Above the line `/* That's all, stop editing! */` add:

   ```php
   define( 'HR_CF_ZONE_ID', 'paste the Zone ID' );
   define( 'HR_CF_API_TOKEN', 'paste the token' );
   ```
5. wp-admin > **Settings > Headline Recorder > Edge cache (Cloudflare)** now says "Connected". Press **Purge everything
   at Cloudflare** once; "Last purge" should read "done".

What is purged automatically: when a post, page or episode is published, updated, unpublished, trashed or deleted,
the home page, the article, its category and tag pages (first page), category feeds, the author page, the main feed,
`/podcast/` and `/podcast/feed/` (episodes), the article's `/signed/<id>.sig.json`, `/signed/feed.xml` (and
`.sig.json`), the sitemaps and `/llms.txt` / `/llms-full.txt`. Renamed or deleted categories and tags, new
signatures from the signer, and the keyset are purged too; a Customizer, menu or theme change purges everything.
Purges go out after the editor's page has loaded (never slowing down Save), at most 30 addresses per call, and are
retried after 1, 5, 15 and 60 minutes if Cloudflare is unreachable. Without the two constants nothing is sent.

If **LiteSpeed Cache > CDN > Cloudflare** is set up as well, leave it off: this module already purges Cloudflare,
including when you press LiteSpeed's "Purge All".

## LiteSpeed Cache (origin)

The plugin is installed; enable it in production. It caches pages on the server, and Cloudflare caches them again
close to readers; both are purged on every change. Settings that match this site:

- **Cache:** Enable Cache ON, Cache Logged-in Users OFF, Cache Commenters OFF, Cache REST API OFF,
  Cache Login Page ON, Cache Mobile OFF (one responsive design; Cloudflare also keeps one version).
- **TTL:** Default Public Cache TTL 3600, Front Page TTL 600, Feed TTL 600, 404 TTL 3600.
- **Purge:** "Auto Purge Rules For Publish/Update": All pages, Front page, Home page, Pages, Author archive,
  Post type archive, Yearly/Monthly/Daily archive, Term archive. "Serve Stale" ON.
- **Excludes > Do Not Cache URIs:** `/account/`, `/dashboard/`, `/wp-json/hr/v1/`, `/thank-you/`, `/reading/`. **Do Not Cache
  Cookies:** `hr_li`, `wordpress_logged_in`. (The plugin also sends `no-store` and `litespeed_control_set_nocache` on
  personalised responses, and the edge module never marks a page public when LiteSpeed Cache says `no-cache`.)
- **ESI:** OFF. Nothing per-user is rendered on cacheable pages: the header account chip and streak flame come from
  `admin-ajax.php?action=hr_session`.
- **Guest Mode:** ON (first-time visitors get the cached page immediately). **Guest Optimization:** OFF until tested.
- **Page Optimization > CSS:** CSS Minify ON, **CSS Combine OFF** (the theme's cascade depends on print order; with
  HTTP/2+ separate files cost little), UCSS OFF, "Load CSS Asynchronously" (CCSS via QUIC.cloud): try on staging,
  keep it if pages look identical; it is the main remaining LCP lever. Font Display Optimization: Default (the theme
  already uses `swap`).
- **JS:** JS Minify ON, **JS Combine OFF**, **Load JS Deferred OFF** (the theme already defers everything safely;
  LiteSpeed's defer would also delay the inline consent/ticker config).
- **HTML:** HTML Minify ON, DNS Prefetch Control ON, Remove Query Strings OFF (files are versioned by mtime),
  Remove Google Fonts N/A, Remove WordPress Emoji ON.
- **Media:** **Lazy Load Images OFF** (the theme sets `loading` itself and keeps the LCP eager), Responsive
  Placeholder OFF, Add Missing Sizes ON.
- **Browser cache:** ON, TTL 31557600 for static files (theme/plugin files carry `?ver=<mtime>`).
- **Crawler:** ON with the sitemap (`/sitemap.xml`), Delay 500 µs, Run Duration 400 s, Interval 3600 s,
  Threads 1, Server Load Limit 1: gentle on shared hosting; it re-warms the cache after purges.
- **Object Cache:** ON if the host offers Redis or LSMCD (cPanel > "Redis"/"Memcached"); otherwise OFF.
- **CDN:** QUIC.cloud CDN is LiteSpeed's own alternative to Cloudflare and is purged by LiteSpeed Cache itself.
  Use one CDN, not both: this runbook assumes Cloudflare, which also gives the
  post-quantum TLS and DNSSEC described in `infra/cloudflare/README.md`.

## Images (WebP / AVIF)

- **LiteSpeed Cache > Image Optimization (QUIC.cloud):** Auto Request Cron ON, Optimize Original Images ON, keep
  backups, **Next-Gen Image Format: AVIF** (falls back to WebP), **Image WebP/AVIF Replacement ON**, "WebP For Extra
  srcset" ON, and add `div.data-background` to **"WebP Attribute To Replace"** so the masthead background (712 KB PNG)
  is served as WebP (~80 KB). Most uploads are already WebP; this mainly converts the older PNG/JPEG ones.
- Upload photos at most 1600 px wide; WordPress makes the smaller sizes and the theme picks the right one (`sizes`).
- Cloudflare Polish (image compression at the edge) needs a paid plan and is not needed with the above.

## Core Web Vitals budgets and how to measure

| Metric (mobile, 75th percentile of real visits) | Budget |
|---|---|
| Largest Contentful Paint (LCP), 4G | under 2.0 s |
| Interaction to Next Paint (INP) | under 150 ms |
| Cumulative Layout Shift (CLS) | under 0.05 |
| Time to First Byte with a Cloudflare HIT (inside Pakistan) | under 200 ms |
| Lighthouse (lab, mobile), every category | 95 or more |

- **PageSpeed Insights** (pagespeed.web.dev): paste an article URL, choose Mobile. The top block "Discover what your
  real users are experiencing" is field data from Chrome users (CrUX) and is what Google ranks on; the lower score is
  a lab test. Test the home page, one article, `/podcast/` and a section page after every design change.
- **Search Console > Experience > Core Web Vitals:** groups of URLs that pass or fail, from the same field data.
- **CrUX Vis** (cruxvis.withgoogle.com): month-by-month field trends for the whole origin.
- **Sentry > Performance** (`HR_SENTRY_TRACES`): LCP, INP and CLS for each page as readers load it.
- Field data needs a few weeks of traffic after a change before it moves.

## Origin checklist (cPanel / LiteSpeed)

- PHP **8.4** (cPanel > MultiPHP Manager) at **8.4.27 or later** (php2shell fix; see `docs/DEPLOY.md`) with
  **OPcache** on: `opcache.memory_consumption=256`, `opcache.interned_strings_buffer=16`,
  `opcache.max_accelerated_files=20000`, `opcache.validate_timestamps=1`, `opcache.revalidate_freq=60`; JIT off
  (no gain for WordPress).
- Persistent **object cache** (Redis or LSMCD) if offered: WordPress then keeps query results and the home/llms
  transients in memory instead of the options table.
- HTTP/2 and HTTP/3 on (LiteSpeed default), Brotli/gzip for `text/*`, JS, JSON, SVG and `font/ttf`.
- MariaDB 11.4: `innodb_buffer_pool_size` at least the data size if the host allows; delete the AIOS checksum
  options above.
- `DISABLE_WP_CRON` + real cron (above). Keep `WP_DEBUG` off in production. With LiteSpeed (lsphp) the Cloudflare purge
  runs right after each save; on other PHP handlers it runs from the next cron tick.

## Speculation Rules and resource hints

- WordPress prints Speculation Rules; the site prefetches a same-origin page when the reader hovers or starts to
  touch its link (`prefetch`, `moderate`), and the rules sit in `<head>` so they work from the first moment.
  Never prefetched: `/account/*` (including logout), `/dashboard/*`, `/thank-you/*`, `/reading/*`, `/wp-json/*`,
  `/wp-admin/*`, `/wp-*.php` (login), every feed, `/signed/*`, `/.well-known/*`, `/og/*`, `.xml`/`.txt`/`.json`
  files, links with a query string (such as nonce links), `rel=nofollow` links and anything inside `.no-prefetch`.
  Signed-in readers get no speculation at all (WordPress default).
- No third-party origin is needed for the first paint: fonts are self-hosted and preloaded, TradingView, YouTube and
  Google load only after interaction or consent. So the site prints no `preconnect`/`dns-prefetch` hints, and drops
  any pointing at itself or at font CDNs it does not use.

## Generative-engine optimisation (GEO)

- **`/llms.txt`**: Yoast writes it weekly. Its post links now carry each article's editor-written summary
  (`_hr_geo_summary`, else the excerpt; `includes/site/geo.php`). In Yoast > Settings > Site features > llms.txt
  choose **manual page selection** (About, Editorial & Corrections Policy, Markets, Authors & Experts, PR & Media,
  Contact, Podcast); automatic selection currently lists the reader Dashboard.
- **`/llms-full.txt`** (`includes/site/geo.php`): publisher facts (address, editorial policy, contact, signing),
  how to cite, sections, and the latest 50 articles with date, update date, author, section and summary.
  Markdown, `Cache-Control: public, max-age=3600, stale-while-revalidate=86400`, rebuilt when posts change and purged
  at Cloudflare with them.
- **Article JSON-LD** (`inc/schema.php`): `NewsArticle` with ImageObject (size), `inLanguage`, `articleSection`,
  `keywords`, `wordCount`, `abstract` (the summary), `datePublished`/`dateModified`, author `Person` with bio and
  `sameAs` (guest LinkedIn / X / Facebook), publisher linked to the site-wide `#organization` node, and `speakable`
  (headline, summary, first paragraphs). Guest-author archives add a `ProfilePage` + `Person` (bio, photo, profiles).
- **Freshness:** articles edited more than a day after publishing show "Updated <date>" next to the date, matching
  `dateModified`; the edge purge makes the corrected text visible worldwide at once.
- **Organization / Podcast** JSON-LD (Yoast + `includes/site/schema.php`): NewsMediaOrganization with address,
  contact point, editorial and corrections policies; PodcastSeries / PodcastEpisode on the podcast pages.
- **robots.txt:** the static `robots.txt` in the web root (allows OAI-SearchBot, ChatGPT-User, PerplexityBot,
  Claude-SearchBot, GPTBot, ClaudeBot, Google-Extended; blocks CCBot, Bytespider ...) is kept as is. A static file
  wins over WordPress's virtual robots.txt. Bot Fight Mode stays off so these crawlers are not challenged.
- **IndexNow:** the official IndexNow plugin is installed but not active in the production database. Activate it
  (`wp plugin activate indexnow`) so Bing, Yandex, Naver and Seznam (and answer engines that read them) see new and
  updated articles within minutes.
