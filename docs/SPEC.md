# Spec: Headline Recorder — elevation, podcast, accounts, signing, monitoring

Site: headlinerecorder.com, WordPress 7.1.3 on LiteSpeed/cPanel (PHP 8.4, MariaDB 11.4).
Parent theme CoverNews 7.5.5, child theme `headlinerec-child` (all custom templates live there).
Source of the current site: Zenodo 10.5281/zenodo.23458635 (DB dump + full webroot).

## Objective

Keep every existing feature, page, colour and piece of content. On top of it:

1. **Elevate the design** — same palette and fonts, better rhythm, states, motion and accessibility.
2. **Podcast** — a "Podcast" page (`/podcast/`) and a home-page hero for the Headline Recorder Podcast with a player.
   "AI podcast": episodes can be AI-drafted from the day's articles (Claude API) and reviewed by an editor;
   episodes without uploaded audio can be played with the reader's on-device voice.
3. **Accounts** — create an account / sign in (password + passkeys), track a Duolingo-style **reading streak**
   (articles and op-eds read per day), and **submit articles and op-eds** from the account.
4. **Sentry** — PHP and browser error/performance monitoring, CSP violation reports, cron check-ins.
5. **Client logos** — a logo section at the end of the home page, managed in wp-admin.
6. **Post-quantum security** — PQ TLS key exchange (ML-KEM), hardened DNS/SSH/headers, every article and
   feed signed with **ML-DSA-87 + Ed448**, submissions sealed with **ML-KEM-1024 + P-384 → AES-256-GCM**,
   a public `/verify/` page, and a visible "signed" notice on articles and in the footer.

## Capability map

| Module id | Responsibility | Depends on |
|---|---|---|
| hardening | mu-plugin: cookies, password hashing, enumeration, XML-RPC, headers/CSP, rate-limit helper | — |
| monitoring | mu-plugin + module: Sentry PHP init, browser SDK, tunnel endpoint, CSP report target | hardening |
| core | `hr-platform` plugin bootstrap, settings page, template loader, shared helpers/contract | — |
| accounts | routes under `/account/`, register/verify/login/passkeys/reset/logout/settings/delete/export | core, hardening |
| streaks | reads table, read tracking REST, streak engine, header widget, dashboard UI | accounts |
| submissions | sealed article/op-ed submissions, editor decryption in browser, status workflow | accounts |
| podcast | `hr_episode` CPT, `/podcast/` archive + singles, iTunes feed, player, AI draft generator, home hero | core |
| clients | `hr_client` CPT, home logo strip | core |
| signing | canonical article bytes, signing queue/upload REST, `/signed/*`, keyset, `/verify/`, badges | core |
| theme | design elevation, hook slots used by the plugin, header account slot | — |
| infra | LiteSpeed `.htaccess`, Cloudflare PQ guide, nginx/SSH/DNSSEC (VPS), CI, signer Action | signing |

Build order: hardening, core, theme → monitoring, accounts, podcast, clients, signing → streaks, submissions → infra.

## Project structure

```
wp-content/themes/headlinerec-child/   theme: templates, assets/css/hr-elevate.css, assets/js/hr-ui.js
wp-content/plugins/hr-platform/        plugin: hr-platform.php, includes/<module>/, templates/, assets/, composer.json
wp-content/mu-plugins/hr-hardening.php early security (loads before plugins)
wp-content/mu-plugins/hr-sentry.php    early Sentry init (catches fatals in other plugins)
security/pq/                           ML-DSA/ML-KEM toolkit, signer CLI (wp-sign.mjs), verify page source, tests
infra/                                 litespeed/, cloudflare/, nginx/, ssh/, dns/
.github/workflows/                     ci.yml (lint/test/build), sign-content.yml (scheduled signer)
docs/                                  this spec, DEPLOY.md
```

## Contract between theme and plugin

The theme renders hook slots; the plugin fills them. Every call is guarded so either side works alone.

| Action | Where (theme) | Filled by |
|---|---|---|
| `hr_header_account` | header, right of nav/search | accounts/streaks (sign-in link or avatar + flame) |
| `hr_home_after_banner` | home, right after the main news banner | podcast hero ("Podcast") |
| `hr_home_before_footer` | home, after the last section, before footer | client logos |
| `hr_single_after_byline` | single post meta strip, after read time (not inside the share group) | signing badge |
| `hr_single_before_content` | single post, between meta strip and hero image | "Listen" button + player panel (podcast) |
| `hr_single_after_content` | single post, right after the article body | signing note (`#hr-signature`), read sentinel (streaks) |
| `hr_footer_trust` | footer bottom bar | "Every article is post-quantum signed — verify" |

CSS tokens are defined once by the theme on `:root` (`--hr-red`, `--hr-ink`, `--hr-paper`, …); plugin CSS
uses `var(--hr-red, <fallback>)` so it renders correctly even without the theme.

## Routes

| Path | Owner | Notes |
|---|---|---|
| `/account/`, `/account/{login,register,verify,forgot,reset,settings,submit,submissions}/` | accounts | rewrite rules, `nocache`, `noindex` |
| `/podcast/`, `/podcast/<slug>/`, `/podcast/feed/` | podcast | CPT archive/single, iTunes RSS |
| `/verify/` | signing | reader/partner verification |
| `/signed/<post-id>.sig.json`, `/signed/feed.xml(.sig.json)`, `/signed/podcast.xml(.sig.json)` | signing | CORS `*` |
| `/.well-known/pqsig-keys.json` | signing | keyset from settings |
| REST `hr/v1/*` | modules | cookie auth + nonce; signer uses an application password with role `hr_signer` |
| `admin-ajax.php?action=hr_session` | streaks | returns REST nonce + "me" for cache-safe pages |

## Security requirements (all modules)

- Every state-changing request: capability check, nonce (form `wp_nonce_field` or REST `X-WP-Nonce`), and
  Origin check on custom POST handlers. Escape on output (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`).
- Auth cookies `Secure` (on HTTPS) + `HttpOnly` + `SameSite` (Lax for the front-end cookie, Strict for wp-admin).
  New hashes use Argon2id. Generic auth errors (no account enumeration). Rate limits on login, register,
  reset, verify-resend, submit, read tracking.
- No secrets in git or in the web root. Config comes from `wp-config.php` constants:
  `HR_SENTRY_DSN`, `HR_SENTRY_BROWSER_DSN`, `HR_SENTRY_TRACES`, `ANTHROPIC_API_KEY`.
- The signing secret key never touches the web server; signing runs in CI or on an editor's machine.
- Submission plaintext never reaches the server: sealed in the browser, opened in the editor's browser.

## Testing

- `php -l` on every PHP file; plugin PHPUnit-free smoke tests via `wp eval`-style scripts in `tests/`.
- `cd security/pq && npm test` for crypto (sign/verify/seal/open, tamper cases, signer round-trip).
- Playwright end-to-end against the local site: register → verify → login → read → streak → submit → podcast → verify page.
- Visual check: desktop 1440 and mobile 390 screenshots of home, single, podcast, account, verify.

## Boundaries

- Always: keep existing templates' content and features; guard every theme→plugin call; escape output.
- Ask first: deleting content, changing the palette, adding third-party services beyond Sentry/Claude.
- Never: commit secrets or the site dump; ship the signing secret key to the server; weaken a check to pass a test.

## Owner decisions (confirmed — these OVERRIDE any older wording in task prompts)

1. **The podcast is called simply "Podcast"** — not "Boss". Page title, home hero heading, nav item, feed title,
   episode titles ("Daily Briefing — <date>: …"), CTAs ("Listen to the Podcast") all use "Podcast" /
   "Headline Recorder Podcast". The setting `podcast_title` defaults to "Podcast". Never output the word "Boss".
2. **Organisation address** (use in schema, contact page, footer, legal pages):
   First Floor, Feroze Heights, Saadi Town, Karachi, Sindh 75280, Pakistan.
   Defaults: org_street_address "First Floor, Feroze Heights, Saadi Town", org_locality "Karachi",
   org_region "Sindh", org_postal_code "75280", org_country "PK". Phone: not provided — leave empty.
3. All credentials exposed by the public archive have been rotated by the owner (2026-10-19).
4. After the feature build: **extreme performance, geographic delivery and GEO optimisation** — Core Web Vitals
   budgets (mobile LCP < 2.0 s on 4G, INP < 150 ms, CLS < 0.05, Lighthouse ≥ 95), edge caching close to readers in
   Pakistan and abroad (Cloudflare PoPs in Karachi/Lahore/Islamabad), LiteSpeed tuning, third-party facades, image and
   font optimisation, speculation rules, and generative-engine optimisation (llms.txt, structured data, summaries).

## Assumptions

1. "AI podcast" means AI-drafted episodes (Claude) reviewed by an editor before publishing, plus on-device
   voice playback when no audio file is attached. Nothing AI-generated is published automatically.
2. Client logos are uploaded by the team in wp-admin (no logos are invented).
3. Hosting stays on LiteSpeed/cPanel; PQ TLS for readers comes from Cloudflare (or a VPS with the nginx config).
