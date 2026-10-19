// wp-admin → Submissions → edit: opens a sealed submission with the editors' secret key, in this browser only.
// The key file is read locally, used once and dropped; decrypted content is rendered with textContent and
// disappears when the editor closes it or leaves the page. The attachment is offered as a download, never inline.
import { open, SEAL_ALG } from '../../../../../security/pq/pq.mjs';
import { countWords, sniffImage, DOCS, extOf, safeName, fromB64, sha256hex, fmtNum } from './seal.mjs';

const doc = document;

function el(tag, props = {}, ...kids) {
  const node = doc.createElement(tag);
  for (const [k, v] of Object.entries(props)) {
    if (k === 'text') node.textContent = v;
    else node.setAttribute(k, v);
  }
  node.append(...kids.filter(Boolean));
  return node;
}

function init(root) {
  const cfg = JSON.parse(doc.getElementById('hrs-open-config').textContent);
  const input = doc.getElementById('hrs-key');
  const status = root.querySelector('[data-hrs-status]');
  const out = root.querySelector('[data-hrs-out]');
  const forget = root.querySelector('[data-hrs-forget]');
  let urls = [];

  const say = (text, error = false) => {
    status.textContent = text;
    status.classList.toggle('is-error', error);
  };
  const close = () => {
    urls.forEach((u) => URL.revokeObjectURL(u));
    urls = [];
    out.replaceChildren();
    out.hidden = true;
    forget.hidden = true;
  };
  window.addEventListener('pagehide', close);
  forget.addEventListener('click', () => {
    close();
    say('Closed. The key and the decrypted text are gone from this page.');
  });

  input.addEventListener('change', async () => {
    const file = input.files[0];
    input.value = ''; // do not keep a handle on the key file
    if (!file) return;
    close();
    try {
      let key;
      try {
        key = file.size < 10000 ? JSON.parse(await file.text()) : null;
      } catch {
        key = null;
      }
      // The secret key is a 32-byte seed (43 base64url characters); the public key is far longer.
      if (key?.alg !== SEAL_ALG || typeof key.key !== 'string' || key.key.length !== 43 || typeof key.kid !== 'string') throw new Error('That is not the editors\' secret key (submissions.secret.json).');
      if (key.kid !== cfg.kid) throw new Error(`This submission was sealed to key ${cfg.kid.slice(0, 16)}…, but that file is key ${key.kid.slice(0, 16)}….`);
      say('Fetching the sealed file…');
      const res = await fetch(cfg.envelope, { credentials: 'same-origin', cache: 'no-store', headers: { 'X-WP-Nonce': cfg.nonce } });
      const env = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(env.message || 'The sealed file could not be loaded. Reload the page and try again.');
      const envelope = { protected: env.protected, kem: env.kem, iv: env.iv, ct: env.ct };
      if ((await sha256hex(JSON.stringify(envelope))) !== cfg.sha256) throw new Error('The stored file does not match the fingerprint recorded when it arrived. Do not trust it; tell the site administrator.');
      say('Decrypting…');
      let plain;
      try {
        plain = await open(envelope, key);
      } catch {
        throw new Error('Could not decrypt. The key does not match or the file was altered.');
      } finally {
        key = null;
      }
      const sub = JSON.parse(new TextDecoder('utf-8', { fatal: true }).decode(plain));
      if (sub?.v !== 1 || typeof sub.body !== 'string') throw new Error('This submission uses a format this page does not know.');
      render(sub, await cleanHeadshot(sub.headshot?.data_b64).catch(() => null));
      say('Opened in this tab only. Nothing was sent anywhere.');
    } catch (err) {
      close();
      say(err.message || 'Could not open this submission.', true);
    }
  });

  const str = (v) => (typeof v === 'string' ? v : '');

  // The bundle is whatever the writer's browser sealed, so its headshot is re-drawn here as a plain 800×800 JPEG
  // before it is shown or uploaded: no metadata (WordPress copies IPTC title/caption into the media library), no
  // oversized dimensions for the server's image processing.
  async function cleanHeadshot(b64) {
    const bytes = fromB64(b64);
    if (!sniffImage(bytes)) return null;
    const bmp = await createImageBitmap(new Blob([bytes]));
    const side = Math.min(bmp.width, bmp.height);
    const canvas = el('canvas', { width: '800', height: '800' });
    const g = canvas.getContext('2d');
    g.fillStyle = '#fff';
    g.fillRect(0, 0, 800, 800);
    g.drawImage(bmp, (bmp.width - side) / 2, (bmp.height - side) / 2, side, side, 0, 0, 800, 800);
    bmp.close();
    const jpeg = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.9));
    return jpeg ? { bytes: new Uint8Array(await jpeg.arrayBuffer()), type: 'image/jpeg' } : null;
  }

  function block(label, text, cls = '') {
    if (!str(text)) return null;
    return el('section', { class: `hrs-admin__block ${cls}` }, el('h4', { class: 'hrs-admin__label', text: label }), el('div', { class: 'hrs-admin__text', text: str(text) }));
  }

  function render(sub, headshotBytes) {
    const [min, max] = cfg.words;
    const words = countWords(str(sub.body));
    const inRange = words >= min && words <= max;

    let headshot = null;
    if (headshotBytes) {
      const url = URL.createObjectURL(new Blob([headshotBytes.bytes], { type: headshotBytes.type }));
      urls.push(url);
      headshot = el('img', { class: 'hrs-admin__photo', src: url, alt: `Headshot of ${str(sub.name)}`, width: '160', height: '160' });
    }

    const writer = el('div', { class: 'hrs-admin__writer' },
      headshot || el('div', { class: 'hrs-admin__photo hrs-admin__photo--none', text: 'No valid headshot' }),
      el('div', {},
        el('p', { class: 'hrs-admin__name', text: str(sub.name) }),
        el('p', { class: 'hrs-admin__designation', text: str(sub.designation) }),
        el('p', { class: 'hrs-admin__email', text: str(sub.email) }),
        el('p', { class: 'hrs-admin__bio', text: str(sub.bio) })));

    const badge = el('span', { class: `hrs-admin-badge ${inRange ? 'is-ok' : 'is-warn'}`, text: inRange ? 'Within limit' : `Outside ${fmtNum(min)}–${fmtNum(max)}` });
    const meta = el('p', { class: 'hrs-admin__meta' },
      `${sub.type === 'oped' ? 'Op-ed' : 'Article'} · ${str(sub.category_name)} · ${fmtNum(words)} words (counted here) `, badge);

    const body = el('div', { class: 'hrs-admin__body' }, ...str(sub.body).split(/\n\s*\n/).filter((p) => p.trim()).map((p) => el('p', { text: p.trim() })));

    let attachment = null;
    if (sub.attachment) {
      const a = sub.attachment;
      const name = safeName(str(a.name));
      const ext = extOf(name);
      let bytes = null;
      try {
        bytes = fromB64(a.data_b64);
      } catch { /* reported below */ }
      const ok = bytes && DOCS[ext] && DOCS[ext].ok(bytes);
      const button = el('button', { type: 'button', class: 'button' }, `Download ${name}`);
      button.addEventListener('click', () => {
        // Generic type + download attribute: the browser saves it instead of rendering it in this admin origin.
        const url = URL.createObjectURL(new Blob([bytes], { type: 'application/octet-stream' }));
        const link = el('a', { href: url, download: name });
        link.click();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
      });
      attachment = el('section', { class: 'hrs-admin__block' }, el('h4', { class: 'hrs-admin__label', text: 'Attachment' }),
        ok ? el('p', {}, `${name} · ${fmtNum(Math.ceil(bytes.length / 1024))} KB · file type checked `, button)
          : el('p', { class: 'hrs-admin__warn', text: `${name}: the contents do not match the file type, so it is not offered for download.` }));
    }

    const actions = el('div', { class: 'hrs-admin__actions' });
    if (cfg.canDraft) {
      const go = el('button', { type: 'button', class: 'button button-primary' }, 'Create draft post from this');
      const note = el('p', { class: 'description', text: sub.type === 'oped' ? 'Creates a draft in Opinion with the writer as guest author.' : 'Creates a draft in the chosen section with the writer as guest author.' });
      go.addEventListener('click', () => createDraft(sub, headshotBytes, go, actions));
      actions.append(go, note);
    } else {
      actions.append(el('p', { class: 'description', text: 'A draft post has already been created from this submission (see above).' }));
    }

    out.replaceChildren(
      writer,
      meta,
      el('h2', { class: 'hrs-admin__title', text: str(sub.title) }),
      str(sub.standfirst) ? el('p', { class: 'hrs-admin__standfirst', text: str(sub.standfirst) }) : null,
      body,
      block('References', sub.references),
      block('Conflicts of interest', sub.coi),
      attachment,
      el('p', { class: 'hrs-admin__fine', text: `Sealed in the writer's browser at ${str(sub.submitted_at)}.` }),
      actions,
    );
    out.hidden = false;
    forget.hidden = false;
  }

  async function createDraft(sub, headshot, button, actions) {
    button.disabled = true;
    const headers = { 'X-WP-Nonce': cfg.nonce };
    try {
      let avatarId;
      if (headshot && cfg.media) {
        say('Uploading the headshot to the media library…');
        const slug = str(sub.name).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'guest';
        const res = await fetch(cfg.media, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { ...headers, 'Content-Type': headshot.type, 'Content-Disposition': `attachment; filename="${slug}-headshot.jpg"` },
          body: new Blob([headshot.bytes], { type: headshot.type }),
        });
        const media = await res.json().catch(() => ({}));
        if (!res.ok || !Number.isInteger(media.id)) throw new Error(media.message || 'The headshot could not be uploaded.');
        avatarId = media.id;
      }
      say('Creating the draft…');
      const bio = str(sub.bio).includes(str(sub.designation)) ? str(sub.bio) : `${str(sub.designation)}. ${str(sub.bio)}`;
      const res = await fetch(cfg.draft, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { ...headers, 'Content-Type': 'application/json' },
        body: JSON.stringify({ title: str(sub.title), standfirst: str(sub.standfirst), body_text: str(sub.body), guest_author_name: str(sub.name), guest_author_bio: bio.slice(0, 800), avatar_id: avatarId }),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(data.message || 'The draft could not be created.');
      const statusSelect = doc.getElementById('hr-sub-status-select');
      if (statusSelect && ['received', 'in_review'].includes(statusSelect.value)) statusSelect.value = 'accepted'; // so "Update" keeps it
      const link = new URL(String(data.edit_url), location.href);
      actions.replaceChildren(link.origin === location.origin ? el('a', { class: 'button button-primary', href: link.href }, 'Edit the draft') : '', el('p', { class: 'description', text: 'Draft created. This submission is now marked Accepted.' }));
      say('Draft created.');
    } catch (err) {
      button.disabled = false;
      say(err.message, true);
    }
  }
}

const root = doc.querySelector('[data-hrs-open]');
if (root) init(root);
