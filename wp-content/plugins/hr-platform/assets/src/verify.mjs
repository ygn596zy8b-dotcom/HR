// /verify/: checks signature files in the reader's browser with the shared toolkit (no crypto is forked here).
// Safety rules, same as security/pq/verify: every value from a signature file reaches the page through textContent,
// and fetches are same-origin only.
import { verify, b64u, SIG_ALG } from '../../../../../security/pq/pq.mjs';

const root = document.getElementById('hr-verify');
if (root) init(root);

function init(root) {
  const $ = (id) => document.getElementById(`hr-verify-${id}`);
  const KEYSET_URL = root.dataset.keys || '/.well-known/pqsig-keys.json';
  const REST = root.dataset.rest || '/wp-json/hr/v1/';
  let keyset = null;
  let keysetLoad = null;
  let signedText = null;
  let trust = null;

  const sameOrigin = (u) => {
    const url = new URL(u, location.href);
    if (url.origin !== location.origin || !/^https?:$/.test(url.protocol)) throw new Error('Only links on this site can be checked here.');
    return url;
  };
  const fetchBytes = async (u) => {
    const url = sameOrigin(u);
    let res;
    // A redirect or a network failure only says "Failed to fetch"; tell the reader what could not be loaded.
    try { res = await fetch(url, { credentials: 'omit', cache: 'no-store', redirect: 'error' }); } catch { throw new Error(`Could not load ${u}. Check the link and your connection.`); }
    if (!res.ok) throw new Error(res.status === 404 ? `Nothing found at ${u}.` : `Could not load ${u} (HTTP ${res.status}).`);
    return new Uint8Array(await res.arrayBuffer());
  };
  const parseJson = (bytes) => JSON.parse(new TextDecoder().decode(bytes));
  const groups = (hex) => (String(hex).match(/.{1,4}/g) || []).join(' ');
  const normalize = (s) => s.normalize('NFC').replace(/\s+/g, ' ').trim();
  // Text a reader sees, independent of markup and whitespace. DOMParser does not run scripts or load resources.
  const plainText = (html) => {
    const { body } = new DOMParser().parseFromString(html, 'text/html');
    body.querySelectorAll('script, style, noscript, template').forEach((n) => n.remove());
    return normalize(body.textContent || '');
  };
  const when = (iso, withTime = true) => {
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '';
    return withTime ? d.toLocaleString(undefined, { dateStyle: 'long', timeStyle: 'short' }) : d.toLocaleDateString(undefined, { dateStyle: 'long' });
  };
  const sameBytes = (a, b) => a.length === b.length && a.every((v, i) => v === b[i]);

  function el(tag, text, cls) {
    const n = document.createElement(tag);
    if (text != null) n.textContent = text;
    if (cls) n.className = cls;
    return n;
  }

  // Short messages for screen readers (a role="status" region); the report itself is announced through focus.
  const announce = (text) => { $('status').textContent = text; };
  const verdict = (text) => { const p = el('p', text, 'hr-verify-verdict'); p.id = 'hr-verify-verdict'; return p; };

  function busy(message) {
    const out = $('result');
    out.replaceChildren(verdict(message));
    out.removeAttribute('aria-describedby');
    out.dataset.state = 'busy';
    out.hidden = false;
    announce(message);
  }

  function show(result) {
    const out = $('result');
    out.replaceChildren();
    out.hidden = false;
    out.dataset.state = result.ok ? 'ok' : 'fail';
    out.append(verdict(result.ok ? 'Authentic: this is exactly what the publisher signed.' : 'Not verified'));
    if (result.ok) out.append(el('p', trustText(), 'hr-verify-trust-note'));
    out.removeAttribute('aria-describedby');
    announce('');
    if (!result.ok) {
      const reason = el('p', result.reason);
      reason.id = 'hr-verify-reason';
      out.append(reason);
      out.setAttribute('aria-describedby', reason.id);
    }

    const h = result.header;
    if (h && typeof h === 'object') {
      if (!result.ok) out.append(el('p', 'The details below are what the file claims. They could not be confirmed.', 'hr-verify-note'));
      const dl = el('dl', null, 'hr-verify-dl');
      const row = (k, v) => v && dl.append(el('dt', k), el('dd', String(v)));
      row('Type', h.type);
      row('Address', h.url);
      row('Signed', h.signedAt && when(h.signedAt));
      row('Signing key', h.kid && groups(h.kid));
      row('Algorithm', h.alg === SIG_ALG ? `${SIG_ALG} · SHA-512` : h.alg);
      row('SHA-512', h.sha512);
      out.append(dl);
    }

    signedText = null;
    $('compare').hidden = true;
    if (result.ok && result.header.type === 'article') {
      let a;
      try { a = parseJson(result.bytes); } catch { a = null; }
      if (a && typeof a === 'object') {
        const live = el('p', 'Comparing with the live article…', 'hr-verify-live');
        live.dataset.state = 'checking';
        out.append(live);
        compareLive(result, live);

        const art = el('article', null, 'hr-verify-article');
        art.append(el('h4', String(a.title ?? 'Untitled')));
        art.append(el('p', [a.author, a.published && when(a.published, false)].filter(Boolean).map(String).join(' · '), 'hr-verify-byline'));
        signedText = plainText(String(a.html ?? ''));
        art.append(el('p', signedText, 'hr-verify-article-body'));
        out.append(el('h3', 'Signed original'), art);
        $('compare').hidden = false;
      }
    }
    out.focus();
  }

  // NEW vs. the standalone page: is the article on the site today still the signed one?
  async function compareLive(result, line) {
    const h = result.header;
    let text, state;
    try {
      if (typeof h.url !== 'string' || !h.url.startsWith('/')) throw new Error('no address');
      const url = sameOrigin(`${REST}canonical`);
      url.searchParams.set('url', h.url);
      const res = await fetch(url, { credentials: 'omit', cache: 'no-store', redirect: 'error' });
      if (res.status === 404) {
        [text, state] = ['The article is no longer published at this address.', 'changed'];
      } else if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
      } else if (sameBytes(new Uint8Array(await res.arrayBuffer()), result.bytes)) {
        [text, state] = ['The live article is identical to the signed version.', 'same'];
      } else {
        const date = when(h.signedAt) || 'an earlier date';
        [text, state] = [`The live article has changed since it was signed on ${date} (re-signing pending or the copy was altered).`, 'changed'];
      }
    } catch {
      [text, state] = ['The live article could not be checked right now.', 'error'];
    }
    line.textContent = text;
    line.dataset.state = state;
    announce(text);
  }

  // Trust anchors: the key list must be signed by a key whose ID is published in DNS (checked over DNS-over-HTTPS;
  // "AD" means the resolver validated DNSSEC). Without both, a compromised web server could add a key of its own.
  function trustText() {
    if (!trust) return 'The publisher key list has not been checked yet.';
    if (!trust.certified) return 'Caution: the publisher key list is not certified by its offline key.';
    if (!trust.pinned) return 'Caution: the key that certified the key list is not published in DNS.';
    return `Key list certified by key ${trust.certified.slice(0, 16)}…, published in DNS${trust.dnssec ? ' with DNSSEC validation' : ' (DNSSEC not validated)'}.`;
  }

  async function certify() {
    const line = el('p', 'Checking the publisher key list…', 'hr-verify-trust');
    root.prepend(line);
    try {
      const cert = parseJson(await fetchBytes(REST + 'signing/keyset-certificate'));
      const bytes = b64u.dec(String(cert?.keyset || ''));
      const ks = parseJson(bytes);
      let certified = '';
      if (cert?.signature && Array.isArray(ks?.keys)) {
        const r = verify(cert.signature, bytes, ks);
        if (r.ok && r.header.type === 'keyset') certified = String(r.header.kid).toLowerCase();
      }
      if (certified) keyset = ks; // verify articles against exactly the certified list
      let pins = [], dnssec = false;
      try {
        const host = location.hostname.replace(/^www\./, '');
        const res = await fetch(`https://cloudflare-dns.com/dns-query?name=_pqsig.${encodeURIComponent(host)}&type=TXT`, { headers: { accept: 'application/dns-json' } });
        const j = await res.json();
        dnssec = j?.AD === true;
        pins = (Array.isArray(j?.Answer) ? j.Answer : []).map((a) => (String(a?.data || '').replace(/"/g, '').match(/kid=([0-9a-f]{64})/i) || [])[1]).filter(Boolean).map((k) => k.toLowerCase());
      } catch { /* DNS unreachable: reported as not pinned */ }
      trust = { certified, pinned: !!certified && pins.includes(certified), dnssec };
    } catch {
      trust = { certified: '', pinned: false, dnssec: false };
    }
    line.textContent = trustText();
    line.dataset.state = trust.certified && trust.pinned ? 'ok' : 'warn';
  }

  async function loadKeyset() {
    keyset = parseJson(await fetchBytes(KEYSET_URL));
    if (!Array.isArray(keyset?.keys)) throw new Error('bad keyset');
  }

  async function check(docBytes, contentBytes) {
    try { await keysetLoad; } catch { /* reported below */ }
    if (!keyset) throw new Error('The publisher keyset could not be loaded, so nothing can be verified. Please reload the page.');
    let doc;
    try { doc = parseJson(docBytes); } catch { throw new Error('That is not a signature file (.sig.json).'); }
    if (!doc || typeof doc !== 'object') throw new Error('That is not a signature file (.sig.json).');
    if (!contentBytes && typeof doc.payload !== 'string') {
      // Detached signature (e.g. the feed): fetch what the header says it covers, from this site only.
      let h;
      try { h = parseJson(b64u.dec(doc.protected)); } catch { throw new Error('Malformed signature file.'); }
      if (typeof h?.url !== 'string' || !h.url) throw new Error('This signature is detached: also choose the file it covers.');
      contentBytes = await fetchBytes(h.url);
    }
    show(verify(doc, contentBytes, keyset));
  }

  const guard = (fn) => async (e) => {
    e?.preventDefault();
    try { await fn(); } catch (err) { show({ ok: false, reason: err?.message || 'Something went wrong.' }); }
  };

  $('by-link').addEventListener('submit', guard(async () => {
    const value = $('sig-url').value.trim();
    if (!value) throw new Error('Enter the link to a signature file, for example /signed/1234.sig.json.');
    busy('Verifying…');
    await check(await fetchBytes(value));
  }));
  $('by-file').addEventListener('submit', guard(async () => {
    const [sig] = $('sig-file').files, [content] = $('content-file').files;
    if (!sig) throw new Error('Choose a signature file (.sig.json).');
    if (sig.size > 16 * 1024 * 1024) throw new Error('That file is too large to be a signature file.');
    busy('Verifying…');
    await check(new Uint8Array(await sig.arrayBuffer()), content && new Uint8Array(await content.arrayBuffer()));
  }));
  $('compare-form').addEventListener('submit', (e) => {
    e.preventDefault();
    const out = $('compare-result');
    const got = normalize($('received').value);
    const say = (text, state) => { out.textContent = text; out.dataset.state = state; };
    if (!signedText) return say('Verify an article first.', 'diff');
    if (!got) return say('Paste some text first.', 'diff');
    if (got === signedText) return say('✓ Identical to the signed original.', 'ok');
    if (got.length >= 40 && signedText.includes(got)) return say('✓ This is an unaltered excerpt of the signed original.', 'ok');
    let i = 0;
    while (i < got.length && got[i] === signedText[i]) i++;
    say(`✗ Differs from the signed original. First difference near: “…${got.slice(Math.max(0, i - 30), i + 30)}…”`, 'diff');
  });

  keysetLoad = loadKeyset().then(certify, certify);
  const params = new URLSearchParams(location.search);
  const post = params.get('post');
  const q = params.get('sig') || (post && /^\d{1,20}$/.test(post) ? `/signed/${post}.sig.json` : '');
  if (q) {
    $('sig-url').value = q;
    busy('Verifying…');
    guard(async () => check(await fetchBytes(q)))();
  }
}
