#!/usr/bin/env node
// Signs Headline Recorder articles and feeds. Runs in CI (.github/workflows/sign-content.yml) or on an editor's
// machine — never on the web server. Uses pq.mjs; no crypto of its own.
//
//   HR_WP_USER=pqsigner HR_WP_APP_PASSWORD='xxxx xxxx …' \
//   node wp-sign.mjs --site https://headlinerecorder.com --key signing.secret.json [--pin <kid>] [--limit N] [--dry-run]
//
// Exit code 0 when everything that needed signing was signed and stored, 1 on any failure, 2 on usage errors.
import { readFileSync } from 'node:fs';
import { parseArgs } from 'node:util';
import { sign, verify, b64u, sha512hex, SIG_ALG } from './pq.mjs';

const USAGE = `usage: node wp-sign.mjs --site https://example.com --key signing.secret.json [--pin <kid>] [--limit N] [--dry-run] [--insecure-local]
  credentials come from the environment only: HR_WP_USER, HR_WP_APP_PASSWORD (a WordPress application password
  of a user with the "Content signer" role)`;

const BATCH_ITEMS = 25;
const BATCH_BYTES = 4 * 1024 * 1024; // stays under common PHP post_max_size limits
const MAX_ATTEMPTS = 3; // per article per run, so a post that keeps changing cannot loop forever
const TIMEOUT_MS = 60_000;
const LOCAL_HOSTS = new Set(['localhost', '127.0.0.1', '[::1]']);

const log = (msg) => console.log(`[wp-sign] ${msg}`);
const fail = (msg, code = 1) => { console.error(`[wp-sign] error: ${msg}`); process.exit(code); };

let opts;
try {
  ({ values: opts } = parseArgs({
    options: {
      site: { type: 'string' }, key: { type: 'string' }, pin: { type: 'string' }, limit: { type: 'string' },
      'dry-run': { type: 'boolean' }, 'insecure-local': { type: 'boolean' }, help: { type: 'boolean' },
    },
  }));
} catch (err) {
  fail(`${err.message}\n${USAGE}`, 2);
}
if (opts.help) { console.log(USAGE); process.exit(0); }
if (!opts.site || !opts.key) fail(`--site and --key are required\n${USAGE}`, 2);

let site;
try { site = new URL(opts.site); } catch { fail('--site is not a valid URL', 2); }
if (site.username || site.password) fail('put credentials in HR_WP_USER / HR_WP_APP_PASSWORD, not in the URL', 2);
if (site.protocol !== 'https:') {
  if (!(opts['insecure-local'] && site.protocol === 'http:' && LOCAL_HOSTS.has(site.hostname))) {
    fail('refusing a non-https site (plain http is only allowed with --insecure-local for localhost / 127.0.0.1)', 2);
  }
}
const origin = site.origin;
const limit = opts.limit === undefined ? Infinity : Number.parseInt(opts.limit, 10);
if (!(limit > 0)) fail('--limit must be a positive number', 2);
const dryRun = Boolean(opts['dry-run']);

const user = process.env.HR_WP_USER ?? '';
const appPassword = process.env.HR_WP_APP_PASSWORD ?? '';
if (!user || !appPassword) fail('set HR_WP_USER and HR_WP_APP_PASSWORD in the environment', 2);
const auth = `Basic ${Buffer.from(`${user}:${appPassword}`).toString('base64')}`;

let secretKey;
try {
  secretKey = JSON.parse(readFileSync(opts.key, 'utf8'));
} catch {
  fail('could not read the key file (expected signing.secret.json)', 2);
}
if (secretKey?.alg !== SIG_ALG || typeof secretKey.kid !== 'string') fail('the key file is not an ML-DSA-87+Ed448 signing key', 2);
if (opts.pin && opts.pin !== secretKey.kid) fail(`the key file is ${secretKey.kid.slice(0, 16)}…, not the pinned key ${String(opts.pin).slice(0, 16)}…`);

// ---- HTTP: same origin only, never follow redirects (credentials must not travel anywhere else).

const sameOriginUrl = (path) => {
  if (typeof path !== 'string' || !path.startsWith('/') || path.startsWith('//')) throw new Error(`refusing non-local path ${JSON.stringify(path)}`);
  const url = new URL(path, origin);
  if (url.origin !== origin) throw new Error(`refusing ${url.origin}`);
  return url;
};

async function request(path, { method = 'GET', body, authed = true } = {}) {
  const headers = { Accept: 'application/json' };
  if (authed) headers.Authorization = auth;
  if (body !== undefined) headers['Content-Type'] = 'application/json';
  let res;
  try {
    // redirect 'manual': a redirect is reported, never followed, so credentials cannot travel anywhere else.
    res = await fetch(sameOriginUrl(path), { method, headers, body, redirect: 'manual', signal: AbortSignal.timeout(TIMEOUT_MS) });
  } catch (err) {
    throw new Error(`${method} ${path} → ${err?.name === 'TimeoutError' ? 'timed out' : `network error (${err?.cause?.code ?? err?.message ?? 'unknown'})`}`);
  }
  if (res.status >= 300 && res.status < 400) {
    throw new Error(`${method} ${path} → HTTP ${res.status} redirect (not followed; is this page disabled on the site?)`);
  }
  const bytes = new Uint8Array(await res.arrayBuffer());
  if (!res.ok) {
    let detail = '';
    try { detail = JSON.parse(new TextDecoder().decode(bytes)).code ?? ''; } catch { /* not JSON */ }
    throw new Error(`${method} ${path} → HTTP ${res.status}${detail ? ` (${detail})` : ''}`);
  }
  return bytes;
}
const getJson = async (path, o) => JSON.parse(new TextDecoder().decode(await request(path, o)));
const api = (path) => `/wp-json/hr/v1${path}`;

// ---- Main

let failures = 0;
const problem = (msg) => { failures++; console.error(`[wp-sign] ${msg}`); };

async function main() {
  // 1. The site must publish our key; otherwise readers could not verify anything we upload.
  // Hosts often answer /.well-known/ before WordPress does, so fall back to the plugin's REST copy.
  let keyset;
  const errors = [];
  for (const path of ['/.well-known/pqsig-keys.json', '/wp-json/hr/v1/signing/keyset']) {
    try {
      keyset = await getJson(path, { authed: false });
      break;
    } catch (err) {
      errors.push(err.message);
    }
  }
  if (!keyset) fail(`could not load the site keyset (${errors.join('; ')}). Update the Headline Recorder platform, then paste signing.public.json into Settings → Headline Recorder → Content signing.`);
  if (errors.length) console.error(`[wp-sign] note: /.well-known/pqsig-keys.json is not reachable (${errors[0]}); used the REST copy. Partners should be able to fetch the well-known URL — see docs/DEPLOY.md.`);
  const published = (keyset?.keys ?? []).find((k) => k.kid === secretKey.kid);
  if (!published) fail(`key ${secretKey.kid.slice(0, 16)}… is not in the site keyset. Paste signing.public.json into Settings → Headline Recorder → Content signing.`);
  if (published.revoked) fail(`key ${secretKey.kid.slice(0, 16)}… is marked revoked on the site; sign with the current key.`);
  log(`site ${origin}, key ${secretKey.kid.slice(0, 16)}…${dryRun ? ' (dry run)' : ''}`);

  // 2. Articles.
  const attempts = new Map();
  let signed = 0, stored = 0, feeds = null;
  for (let page = 0; page < 1000 && signed < limit; page++) {
    const queue = await getJson(api('/signing/queue'));
    feeds ??= Array.isArray(queue.feeds) ? queue.feeds : [];
    const items = (Array.isArray(queue.items) ? queue.items : []).filter((it) => (attempts.get(it.id) ?? 0) < MAX_ATTEMPTS);
    if (!items.length) break;
    log(`queue: ${queue.items.length} in this page, ${queue.remaining ?? '?'} pending`);

    const docs = [];
    let refused = false;
    for (const it of items) {
      if (signed >= limit) break;
      attempts.set(it.id, (attempts.get(it.id) ?? 0) + 1);
      const result = signArticle(it, keyset);
      if (!result) { refused = true; continue; }
      docs.push(result);
      signed++;
    }
    if (dryRun) { log(`dry run: ${docs.length} signature(s) created and self-verified, nothing uploaded`); break; }
    for (const batch of batches(docs)) {
      const res = await getJson(api('/signing/signatures'), { method: 'POST', body: JSON.stringify({ items: batch }) });
      const results = Array.isArray(res.results) ? res.results : [];
      stored += Number(res.stored) || 0;
      for (const r of results) {
        if (r.status === 'stale') log(`article ${r.id}: changed while signing, will retry`);
        else if (r.status === 'not_found') log(`article ${r.id}: no longer public, skipped`);
        else if (r.status !== 'stored') problem(`article ${r.id}: rejected by the site (${r.status})`);
      }
      log(`uploaded ${batch.length}: ${Number(res.stored) || 0} stored`);
    }
    // Bytes that do not match their own hash, or an upload the site rejected: stop rather than loop on it.
    if (refused || failures) break;
  }
  log(`articles: ${signed} signed, ${dryRun ? 0 : stored} stored`);

  // 3. Feeds: sign the bytes the public gets (fetched without credentials), detached.
  for (const feed of feeds ?? []) {
    if (failures) break;
    try {
      await signFeed(feed, keyset);
    } catch (err) {
      problem(`feed ${feed?.key}: ${err.message}`);
    }
  }
}

function signArticle(it, keyset) {
  if (!Number.isInteger(it.id) || typeof it.url !== 'string' || !it.url.startsWith('/') || typeof it.canonical !== 'string' || typeof it.sha512 !== 'string') {
    problem(`malformed queue item ${JSON.stringify(it.id)}; refusing`);
    return null;
  }
  let bytes;
  try { bytes = b64u.dec(it.canonical); } catch { problem(`article ${it.id}: canonical bytes are not base64url; refusing`); return null; }
  if (sha512hex(bytes) !== it.sha512) {
    problem(`article ${it.id}: canonical bytes do not match the SHA-512 the site reported; refusing to sign`);
    return null;
  }
  // Only ever sign what the site's format says an article is: a v1 record whose address is the queued one on
  // --site. A compromised or misconfigured server then cannot get arbitrary bytes signed as an article.
  if (!isArticleRecord(bytes, it.url)) {
    problem(`article ${it.id}: canonical bytes are not a v1 article at ${it.url} on ${origin}; refusing to sign`);
    return null;
  }
  const doc = sign(bytes, secretKey, { type: 'article', url: it.url, attach: true });
  const check = verify(doc, undefined, keyset);
  if (!check.ok || check.key.kid !== secretKey.kid) {
    problem(`article ${it.id}: self-verification failed (${check.reason ?? 'wrong key'})`);
    return null;
  }
  return { id: it.id, sig: doc };
}

const ARTICLE_FIELDS = 'v,url,title,author,published,modified,html';
function isArticleRecord(bytes, path) {
  try {
    const a = JSON.parse(new TextDecoder('utf-8', { fatal: true }).decode(bytes));
    if (!a || typeof a !== 'object' || Array.isArray(a) || Object.keys(a).join(',') !== ARTICLE_FIELDS || a.v !== 1) return false;
    if (!['title', 'author', 'published', 'modified', 'html'].every((k) => typeof a[k] === 'string')) return false;
    const signed = new URL(a.url);
    return signed.origin === origin && signed.href === sameOriginUrl(path).href;
  } catch {
    return false;
  }
}

function isRssFeed(bytes) {
  let xml;
  try { xml = new TextDecoder('utf-8', { fatal: true }).decode(bytes); } catch { return false; }
  if (xml.includes('\0') || /<!(?:DOCTYPE|ENTITY)/i.test(xml)) return false;
  // Optional BOM, XML declaration (UTF-8 if it names an encoding), processing instructions or comments, then <rss>.
  const decl = /^﻿?\s*<\?xml\b[^>]*?\bencoding=["']([^"']+)["']/.exec(xml);
  if (decl && decl[1].toUpperCase() !== 'UTF-8') return false;
  return /^﻿?\s*(?:<\?[\s\S]*?\?>\s*|<!--[\s\S]*?-->\s*)*<rss[\s>]/.test(xml);
}

function* batches(docs) {
  let batch = [], size = 0;
  for (const d of docs) {
    const n = JSON.stringify(d).length;
    if (batch.length && (batch.length >= BATCH_ITEMS || size + n > BATCH_BYTES)) { yield batch; batch = []; size = 0; }
    batch.push(d);
    size += n;
  }
  if (batch.length) yield batch;
}

async function signFeed(feed, keyset) {
  if (!/^[a-z0-9-]{1,32}$/.test(feed?.key ?? '')) throw new Error('malformed feed entry');
  const bytes = await request(feed.source, { authed: false });
  // Like articles: only ever sign what a feed is (UTF-8 RSS without a DTD), never whatever bytes a path returned.
  if (!isRssFeed(bytes)) throw new Error(`${feed.source} did not return a UTF-8 RSS feed without a DTD; refusing to sign`);
  const sha = sha512hex(bytes);
  if (feed.signed_sha512 === sha) { log(`feed ${feed.key}: unchanged`); return; }
  const url = `/signed/${feed.key}.xml`;
  const doc = sign(bytes, secretKey, { type: 'feed', url });
  const check = verify(doc, bytes, keyset);
  if (!check.ok || check.key.kid !== secretKey.kid) throw new Error(`self-verification failed (${check.reason ?? 'wrong key'})`);
  if (dryRun) { log(`feed ${feed.key}: signed ${bytes.length} bytes (dry run, not uploaded)`); return; }
  await getJson(api('/signing/feeds'), { method: 'POST', body: JSON.stringify({ key: feed.key, content: b64u.enc(bytes), sig: doc }) });
  log(`feed ${feed.key}: signed ${bytes.length} bytes → ${url}`);
}

main().then(
  () => {
    if (failures) fail(`${failures} problem(s); see above`);
    log('done');
  },
  (err) => fail(err?.message ?? String(err)),
);
