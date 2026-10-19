// End-to-end test of wp-sign.mjs against a mock of the hr-platform signing API (node:http, no network).
import { test, after } from 'node:test';
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { execFile } from 'node:child_process';
import { mkdtempSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { generateSigningKey, verify, b64u, sha512hex } from './pq.mjs';

const SIGNER = fileURLToPath(new URL('./wp-sign.mjs', import.meta.url));
const te = new TextEncoder();
const td = new TextDecoder();
const USER = 'pqsigner';
const PASS = 'abcd EFGH ijkl MNOP qrst UVWX';

const dir = mkdtempSync(join(tmpdir(), 'wp-sign-test-'));
after(() => rmSync(dir, { recursive: true, force: true }));
const signer = generateSigningKey();
const keyFile = join(dir, 'signing.secret.json');
writeFileSync(keyFile, JSON.stringify(signer.secretKey), { mode: 0o600 });

const canonical = (id) => te.encode(JSON.stringify({ v: 1, url: `http://127.0.0.1/2026/10/${id}/`, title: `Story ${id} — “quoted”`, author: 'Desk', published: '2026-10-17T10:00:00Z', modified: '2026-10-17T10:00:00Z', html: `<p>Body ${id} ünïcode</p>` }));
const FEED = te.encode('<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel><title>HR</title></channel></rss>');

/** Mock site. `opts.tamper` = ids whose reported sha512 is wrong; `opts.keyset` overrides the published keyset. */
async function mockSite(opts = {}) {
  const state = { articles: new Map(), uploads: [], feeds: [], authHeaders: new Set(), requests: [] };
  for (let id = 1; id <= (opts.count ?? 30); id++) state.articles.set(id, { bytes: canonical(id), stored: false });
  const keyset = opts.keyset ?? { keys: [signer.publicKey] };
  const json = (res, code, body) => { res.writeHead(code, { 'Content-Type': 'application/json' }); res.end(JSON.stringify(body)); };
  const server = createServer(async (req, res) => {
    const chunks = [];
    for await (const c of req) chunks.push(c);
    const body = Buffer.concat(chunks);
    const path = new URL(req.url, 'http://x').pathname;
    state.requests.push(`${req.method} ${path}`);
    if (path === '/.well-known/pqsig-keys.json') return json(res, 200, keyset);
    if (path === '/feed/') { res.writeHead(200, { 'Content-Type': 'application/rss+xml' }); return res.end(FEED); }
    if (!path.startsWith('/wp-json/hr/v1/')) return json(res, 404, { code: 'rest_no_route' });
    state.authHeaders.add(req.headers.authorization ?? '');
    if (req.headers.authorization !== `Basic ${Buffer.from(`${USER}:${PASS}`).toString('base64')}`) return json(res, 401, { code: 'rest_forbidden' });
    if (opts.redirectTo && path.endsWith('/signing/queue')) { res.writeHead(302, { Location: `${opts.redirectTo}/steal` }); return res.end(); }
    if (req.method === 'GET' && path.endsWith('/signing/queue')) {
      const pending = [...state.articles].filter(([, a]) => !a.stored).reverse();
      const items = pending.slice(0, 100).map(([id, a]) => ({
        id, type: 'article', url: `/2026/10/${id}/`, canonical: b64u.enc(a.bytes),
        sha512: opts.tamper?.includes(id) ? sha512hex(te.encode('something else')) : sha512hex(a.bytes),
      }));
      return json(res, 200, { items, remaining: pending.length, feeds: [{ key: 'feed', source: '/feed/', signed_sha512: null, signed_at: null }] });
    }
    if (req.method === 'POST' && path.endsWith('/signing/signatures')) {
      const { items } = JSON.parse(body.toString('utf8'));
      assert.ok(items.length <= 25, 'batches of at most 25');
      const results = items.map(({ id, sig }) => {
        state.uploads.push({ id, sig });
        const a = state.articles.get(id);
        if (!a) return { id, status: 'not_found' };
        a.stored = true;
        return { id, status: 'stored' };
      });
      return json(res, 200, { stored: results.filter((r) => r.status === 'stored').length, results });
    }
    if (req.method === 'POST' && path.endsWith('/signing/feeds')) {
      state.feeds.push(JSON.parse(body.toString('utf8')));
      return json(res, 200, { key: 'feed', status: 'stored' });
    }
    return json(res, 404, { code: 'rest_no_route' });
  });
  await new Promise((r) => server.listen(0, '127.0.0.1', r));
  state.url = `http://127.0.0.1:${server.address().port}`;
  state.close = () => new Promise((r) => server.close(r));
  return state;
}

function run(args, env = { HR_WP_USER: USER, HR_WP_APP_PASSWORD: PASS }) {
  return new Promise((resolve) => {
    execFile(process.execPath, [SIGNER, ...args], { env: { PATH: process.env.PATH, ...env }, timeout: 120_000 }, (err, stdout, stderr) => {
      resolve({ code: err ? (typeof err.code === 'number' ? err.code : 1) : 0, out: stdout + stderr });
    });
  });
}

test('signs the whole queue and the feed; every upload verifies with the published keyset', async () => {
  const site = await mockSite({ count: 30 });
  try {
    const r = await run(['--site', site.url, '--key', keyFile, '--pin', signer.publicKey.kid, '--insecure-local']);
    assert.equal(r.code, 0, r.out);
    assert.equal(site.uploads.length, 30);
    const keyset = { keys: [signer.publicKey] };
    for (const { id, sig } of site.uploads) {
      const v = verify(sig, undefined, keyset);
      assert.equal(v.ok, true, v.reason);
      assert.deepEqual(v.bytes, site.articles.get(id).bytes, 'attached payload is exactly the canonical bytes');
      assert.equal(v.header.type, 'article');
      assert.equal(v.header.url, `/2026/10/${id}/`);
    }
    assert.equal(site.feeds.length, 1);
    const [feed] = site.feeds;
    assert.deepEqual(b64u.dec(feed.content), FEED);
    const fv = verify(feed.sig, FEED, keyset);
    assert.equal(fv.ok, true, fv.reason);
    assert.equal(fv.header.type, 'feed');
    assert.equal(fv.header.url, '/signed/feed.xml');
    assert.equal(feed.sig.payload, undefined, 'feed signatures are detached');
    assert.ok(!r.out.includes(PASS) && !r.out.includes(Buffer.from(`${USER}:${PASS}`).toString('base64')), 'never prints credentials');
    assert.ok(!r.out.includes(signer.secretKey.mldsa87.slice(0, 32)), 'never prints key material');
  } finally {
    await site.close();
  }
});

test('refuses to sign canonical bytes whose hash does not match what the site reported', async () => {
  const site = await mockSite({ count: 5, tamper: [3] });
  try {
    const r = await run(['--site', site.url, '--key', keyFile, '--insecure-local']);
    assert.equal(r.code, 1, r.out);
    assert.match(r.out, /article 3: canonical bytes do not match/);
    assert.ok(!site.uploads.some((u) => u.id === 3), 'tampered article was not signed');
    assert.equal(site.feeds.length, 0, 'feeds are not signed after a refusal');
  } finally {
    await site.close();
  }
});

test('stops with a clear message when the key is not in the site keyset', async () => {
  const other = generateSigningKey();
  const site = await mockSite({ keyset: { keys: [other.publicKey] } });
  try {
    const r = await run(['--site', site.url, '--key', keyFile, '--insecure-local']);
    assert.equal(r.code, 1);
    assert.match(r.out, /paste signing\.public\.json into Settings → Headline Recorder/i);
    assert.equal(site.uploads.length, 0);
  } finally {
    await site.close();
  }
});

test('--limit and --dry-run', async () => {
  const site = await mockSite({ count: 30 });
  try {
    const dry = await run(['--site', site.url, '--key', keyFile, '--insecure-local', '--dry-run']);
    assert.equal(dry.code, 0, dry.out);
    assert.equal(site.uploads.length + site.feeds.length, 0, 'dry run uploads nothing');
    const limited = await run(['--site', site.url, '--key', keyFile, '--insecure-local', '--limit', '7']);
    assert.equal(limited.code, 0, limited.out);
    assert.equal(site.uploads.length, 7);
  } finally {
    await site.close();
  }
});

test('transport and credential rules', async () => {
  const insecure = await run(['--site', 'http://headlinerecorder.com', '--key', keyFile, '--insecure-local']);
  assert.equal(insecure.code, 2);
  assert.match(insecure.out, /non-https/);
  const noCreds = await run(['--site', 'https://example.com', '--key', keyFile], {});
  assert.equal(noCreds.code, 2);
  assert.match(noCreds.out, /HR_WP_USER/);
  const inUrl = await run(['--site', `https://${USER}:x@example.com`, '--key', keyFile]);
  assert.equal(inUrl.code, 2);
  const wrongPin = await run(['--site', 'https://example.com', '--key', keyFile, '--pin', 'f'.repeat(64)]);
  assert.equal(wrongPin.code, 1);
  assert.match(wrongPin.out, /pinned key/);

  // An API redirect to another origin must not be followed with the Authorization header.
  const thief = await mockSite({ count: 0 });
  const site = await mockSite({ count: 3, redirectTo: thief.url });
  try {
    const r = await run(['--site', site.url, '--key', keyFile, '--insecure-local']);
    assert.equal(r.code, 1, r.out);
    assert.equal(thief.requests.length, 0, 'nothing reached the other origin');
  } finally {
    await site.close();
    await thief.close();
  }
});
