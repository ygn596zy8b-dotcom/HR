// Reader/partner verification page. Everything shown from a signature file goes through textContent, never innerHTML.
import { verify, b64u, SIG_ALG } from '../pq.mjs';

const $ = (id) => document.getElementById(id);
const KEYSET_URL = '/.well-known/pqsig-keys.json';
let keyset = null;
let signedText = null;

const sameOrigin = (u) => {
  const url = new URL(u, location.href);
  if (url.origin !== location.origin) throw new Error('Only links on this site can be checked here.');
  return url;
};
const fetchBytes = async (u) => {
  const res = await fetch(sameOrigin(u), { credentials: 'omit', cache: 'no-store' });
  if (!res.ok) throw new Error(`Could not load ${u} (HTTP ${res.status}).`);
  return new Uint8Array(await res.arrayBuffer());
};
const parseJson = (bytes) => JSON.parse(new TextDecoder().decode(bytes));
const groups = (hex) => hex.match(/.{1,4}/g).join(' ');
// Text a reader sees, independent of markup and whitespace. DOMParser does not run scripts or load resources.
const plainText = (html) => {
  const { body } = new DOMParser().parseFromString(html, 'text/html');
  body.querySelectorAll('script, style, noscript, template').forEach((n) => n.remove());
  return normalize(body.textContent);
};
const normalize = (s) => s.normalize('NFC').replace(/\s+/g, ' ').trim();

function el(tag, text, cls) {
  const n = document.createElement(tag);
  if (text != null) n.textContent = text;
  if (cls) n.className = cls;
  return n;
}

function show(result) {
  const out = $('result');
  out.replaceChildren();
  out.hidden = false;
  out.dataset.state = result.ok ? 'ok' : 'fail';
  out.append(el('p', result.ok ? 'Authentic — this is exactly what the publisher signed.' : 'Not verified', 'verdict'));
  if (!result.ok) out.append(el('p', result.reason));

  const h = result.header;
  if (h && typeof h === 'object') {
    const dl = el('dl');
    const row = (k, v) => v && dl.append(el('dt', k), el('dd', String(v)));
    row('Type', h.type);
    row('Address', h.url);
    row('Signed', h.signedAt && new Date(h.signedAt).toLocaleString());
    row('Signing key', h.kid && groups(String(h.kid)));
    row('SHA-512', h.sha512);
    if (!result.ok) out.append(el('p', 'The details below are what the file claims. They could not be confirmed.', 'note'));
    out.append(dl);
  }

  signedText = null;
  $('compare').hidden = true;
  if (result.ok && result.header.type === 'article') {
    let a;
    try { a = parseJson(result.bytes); } catch { return; }
    const art = el('article', null, 'signed-article');
    art.append(el('h2', a.title ?? 'Untitled'));
    art.append(el('p', [a.author, a.published && new Date(a.published).toLocaleDateString()].filter(Boolean).join(' · '), 'byline'));
    signedText = plainText(String(a.html ?? ''));
    art.append(el('p', signedText, 'body'));
    out.append(el('h3', 'Signed original'), art);
    $('compare').hidden = false;
  }
  out.focus();
}

async function check(docBytes, contentBytes) {
  if (!keyset) throw new Error('The publisher keyset is not loaded, so nothing can be verified.');
  const doc = parseJson(docBytes);
  if (!contentBytes && !doc.payload) {
    // Detached signature (e.g. the feed): fetch what the header says it covers, from this site only.
    const h = parseJson(b64u.dec(doc.protected));
    if (!h.url) throw new Error('This signature is detached: also choose the file it covers.');
    contentBytes = await fetchBytes(h.url);
  }
  show(verify(doc, contentBytes, keyset));
}

const guard = (fn) => async (e) => {
  e?.preventDefault();
  try { await fn(); } catch (err) { show({ ok: false, reason: err.message }); }
};

$('by-link').addEventListener('submit', guard(async () => check(await fetchBytes($('sig-url').value.trim()))));
$('by-file').addEventListener('submit', guard(async () => {
  const [sig] = $('sig-file').files, [content] = $('content-file').files;
  if (!sig) throw new Error('Choose a signature file (.sig.json).');
  await check(new Uint8Array(await sig.arrayBuffer()), content && new Uint8Array(await content.arrayBuffer()));
}));
$('compare-form').addEventListener('submit', (e) => {
  e.preventDefault();
  const got = normalize($('received').value);
  const out = $('compare-result');
  if (!got) return void (out.textContent = 'Paste some text first.');
  if (got === signedText) return void (out.textContent = '✓ Identical to the signed original.');
  if (got.length >= 40 && signedText.includes(got)) return void (out.textContent = '✓ This is an unaltered excerpt of the signed original.');
  let i = 0;
  while (i < got.length && got[i] === signedText[i]) i++;
  out.textContent = `✗ Differs from the signed original. First difference near: “…${got.slice(Math.max(0, i - 30), i + 30)}…”`;
});

(async () => {
  try {
    keyset = parseJson(await fetchBytes(KEYSET_URL));
    const list = $('keys');
    list.replaceChildren();
    for (const k of keyset.keys ?? []) {
      const li = el('li');
      li.append(el('code', groups(String(k.kid))), el('span', ` ${k.alg === SIG_ALG ? SIG_ALG : 'unknown algorithm'}${k.revoked ? ` — revoked ${k.revoked}` : ''}`));
      list.append(li);
    }
  } catch (err) {
    $('keys').replaceChildren(el('li', `Could not load the publisher keyset: ${err.message}`));
  }
  const q = new URLSearchParams(location.search).get('sig');
  if (q) {
    $('sig-url').value = q;
    guard(async () => check(await fetchBytes(q)))();
  }
})();
