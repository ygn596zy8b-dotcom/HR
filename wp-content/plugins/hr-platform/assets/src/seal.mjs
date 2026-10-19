// /account/submit/: validates the form, crops the headshot to an 800×800 JPEG (which also drops EXIF/GPS data),
// seals everything to the editors' public key with security/pq/pq.mjs and uploads only the envelope.
// Unsealed text is never written to browser storage: on a shared computer it would outlive the session.
// Also exports the small helpers submission-open.mjs reuses on the editors' side.
import { animate } from 'motion';
import { seal, SEAL_ALG } from '../../../../../security/pq/pq.mjs';

const doc = document;
const te = new TextEncoder();
const MB = 1024 * 1024;
const reduced = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// ------------------------------------------------------------------------------------------------ shared helpers
export const countWords = (text) => (String(text).trim().match(/\S+/g) || []).length;
const starts = (bytes, sig, at = 0) => sig.every((b, i) => bytes[at + i] === b);
const ZIP = [0x50, 0x4b, 0x03, 0x04];

/** Image type from its first bytes ('' if not JPEG, PNG or WebP). */
export function sniffImage(b) {
  if (starts(b, [0xff, 0xd8, 0xff])) return 'image/jpeg';
  if (starts(b, [0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a])) return 'image/png';
  if (starts(b, [0x52, 0x49, 0x46, 0x46]) && starts(b, [0x57, 0x45, 0x42, 0x50], 8)) return 'image/webp';
  return '';
}

function isText(b) {
  if (b.includes(0)) return false;
  try {
    new TextDecoder('utf-8', { fatal: true }).decode(b);
    return true;
  } catch {
    return false;
  }
}

export const DOCS = {
  docx: { type: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', ok: (b) => starts(b, ZIP) },
  odt: { type: 'application/vnd.oasis.opendocument.text', ok: (b) => starts(b, ZIP) },
  pdf: { type: 'application/pdf', ok: (b) => starts(b, [0x25, 0x50, 0x44, 0x46, 0x2d]) },
  rtf: { type: 'application/rtf', ok: (b) => starts(b, [0x7b, 0x5c, 0x72, 0x74, 0x66]) },
  txt: { type: 'text/plain', ok: isText },
};

export const extOf = (name) => (/\.([a-z0-9]{1,5})$/i.exec(String(name)) || ['', ''])[1].toLowerCase();

/** A filename safe to offer for download: letters, digits, dot, dash, underscore and spaces only. */
export function safeName(name) {
  const clean = String(name).normalize('NFKC').replace(/[^\p{L}\p{N}._ -]+/gu, '_').replace(/^[.\s]+/, '').trim();
  return clean.slice(-100) || 'attachment';
}

export function toB64(bytes) {
  let s = '';
  for (let i = 0; i < bytes.length; i += 0x8000) s += String.fromCharCode.apply(null, bytes.subarray(i, i + 0x8000));
  return btoa(s);
}

export const fromB64 = (s) => Uint8Array.from(atob(String(s)), (c) => c.charCodeAt(0));
export const hex = (buf) => [...new Uint8Array(buf)].map((b) => b.toString(16).padStart(2, '0')).join('');
export const sha256hex = async (text) => hex(await crypto.subtle.digest('SHA-256', te.encode(text)));
export const groupHex = (h) => (h.match(/.{1,8}/g) || []).join(' ');
export const fmtNum = (n) => Number(n).toLocaleString('en-US');
const sizeMB = (n) => (n < MB ? `${Math.max(1, Math.round(n / 1024))} KB` : `${(n / MB).toFixed(1)} MB`);

// ------------------------------------------------------------------------------------------------ the form
function init(form) {
  const cfg = JSON.parse(doc.getElementById('hrs-config').textContent);
  if (cfg.key?.alg !== SEAL_ALG || !window.crypto?.subtle) return; // the button stays disabled
  const $ = (id) => doc.getElementById(id);
  const f = Object.fromEntries(['headshot', 'name', 'designation', 'email', 'bio', 'title', 'category', 'standfirst', 'body', 'references', 'coi', 'attachment', 'zoom', 'type', 'remember'].map((k) => [k, $(`hrs-${k}`)]));
  const decls = [...form.querySelectorAll('input[id^="hrs-decl-"]')];
  const submit = form.querySelector('[data-hrs-submit]');
  const status = form.querySelector('[data-hrs-status]');
  const root = form.closest('[data-hrs-root]');
  const done = root.querySelector('[data-hrs-done]');
  let dirty = false;
  let busy = false;
  let img = null;
  let view = { z: 1, cx: 0.5, cy: 0.5 };
  let attachment = null;
  submit.disabled = false;

  const type = () => form.querySelector('input[name="hrs-type"]:checked')?.value || '';

  // ---------------------------------------------------------------- inline errors + summary (same markup as accounts)
  function setError(el, message) {
    const box = $(`${el.id}-error`);
    const ids = new Set((el.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean));
    if (message) {
      el.setAttribute('aria-invalid', 'true');
      box.querySelector('span').textContent = message;
      box.hidden = false;
      ids.add(box.id);
    } else {
      el.removeAttribute('aria-invalid');
      box.hidden = true;
      ids.delete(box.id);
    }
    if (ids.size) el.setAttribute('aria-describedby', [...ids].join(' '));
    else el.removeAttribute('aria-describedby');
  }

  const focusTarget = (el) => (el === f.type ? form.querySelector('input[name="hrs-type"]') : el);

  function showSummary(errors, lead = '') {
    const summary = form.querySelector('[data-hra-summary]');
    summary.querySelector('.hra-summary__lead')?.remove();
    summary.querySelector('.hra-summary__title span').textContent = errors.length === 0 ? 'Something went wrong' : errors.length === 1 ? 'There is a problem with one field' : `There are problems with ${errors.length} fields`;
    if (lead) {
      const p = doc.createElement('p');
      p.className = 'hra-summary__lead';
      p.textContent = lead;
      summary.querySelector('.hra-summary__title').after(p);
    }
    summary.querySelector('.hra-summary__list').replaceChildren(...errors.map(({ el, message }) => {
      const a = doc.createElement('a');
      a.href = `#${el.id}`;
      a.textContent = message;
      a.addEventListener('click', (e) => {
        e.preventDefault();
        focusTarget(el).focus();
      });
      const li = doc.createElement('li');
      li.append(a);
      return li;
    }));
    summary.hidden = false;
    summary.focus();
    if (!reduced()) animate(summary, { opacity: [0, 1], transform: ['translateY(-4px)', 'none'] }, { duration: 0.2 });
  }

  // ---------------------------------------------------------------- validation
  function wordMessage() {
    const t = type();
    if (!t) return 'Choose Article or Op-ed above to see the word limit.';
    const [min, max] = cfg.words[t];
    const n = countWords(f.body.value);
    const label = t === 'oped' ? 'Op-eds' : 'Articles';
    if (n < min) return `${fmtNum(n)} words. ${label} need at least ${fmtNum(min)}: add ${fmtNum(min - n)} more.`;
    if (n > max) return `${fmtNum(n)} words. ${label} can be at most ${fmtNum(max)}: cut ${fmtNum(n - max)}.`;
    return '';
  }

  function check(el) {
    if (el === f.headshot) return img ? '' : el.dataset.hrsRequired;
    if (el === f.type) return type() ? '' : 'Choose Article or Op-ed.';
    if (el === f.attachment) return el.getAttribute('aria-invalid') === 'true' ? $('hrs-attachment-error').textContent : '';
    const value = el.type === 'checkbox' ? (el.checked ? '1' : '') : el.value.trim();
    if (!value) return el.dataset.hrsRequired || '';
    const max = Number(el.getAttribute('maxlength') || 0);
    if (max && value.length > max) return `Keep this under ${fmtNum(max)} characters.`;
    if (el.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value)) return 'Enter a valid email address, like name@example.com.';
    if (el === f.body) return wordMessage();
    return '';
  }

  const ordered = () => [f.headshot, f.name, f.designation, f.email, f.bio, f.type, f.title, f.category, f.standfirst, f.body, f.references, f.coi, f.attachment, ...decls];

  // ---------------------------------------------------------------- live counters
  function updateCounter(el) {
    const out = $(`${el.id}-count`);
    if (!out) return;
    const max = Number(el.dataset.hrsCount);
    const n = el.value.length;
    out.textContent = `${fmtNum(n)} / ${fmtNum(max)} characters`;
    out.classList.toggle('is-near', n > max * 0.9);
  }

  function updateWords() {
    const out = $('hrs-body-count');
    const t = type();
    const n = countWords(f.body.value);
    const message = wordMessage();
    if (!t) out.textContent = `${fmtNum(n)} words. Choose Article or Op-ed above to see the limit.`;
    else if (message) out.textContent = message;
    else out.textContent = `${fmtNum(n)} words. Within the ${t === 'oped' ? `${fmtNum(cfg.words.oped[0])}–${fmtNum(cfg.words.oped[1])}` : `${fmtNum(cfg.words.article[0])}–${fmtNum(cfg.words.article[1])}`} word range.`;
    out.classList.toggle('is-bad', Boolean(t && message && n > 0));
    out.classList.toggle('is-ok', Boolean(t && !message));
  }

  form.querySelectorAll('[data-hrs-count]').forEach((el) => {
    updateCounter(el);
    el.addEventListener('input', () => updateCounter(el));
  });
  f.body.addEventListener('input', updateWords);
  updateWords();

  form.addEventListener('input', (e) => {
    dirty = true;
    const el = e.target.name === 'hrs-type' ? f.type : e.target;
    if (el.getAttribute?.('aria-invalid') === 'true') setError(el, check(el));
  });
  form.addEventListener('change', (e) => {
    if (e.target.name !== 'hrs-type') return;
    updateWords();
    if (f.type.getAttribute('aria-invalid') === 'true') setError(f.type, '');
    if (f.body.getAttribute('aria-invalid') === 'true') setError(f.body, check(f.body));
  });
  window.addEventListener('beforeunload', (e) => {
    if (dirty) {
      e.preventDefault();
      e.returnValue = '';
    }
  });

  // ---------------------------------------------------------------- headshot: type by magic bytes, crop on a canvas
  const canvas = form.querySelector('[data-hrs-canvas]');
  const ctx = canvas.getContext('2d');
  const zoomWrap = form.querySelector('[data-hrs-zoom-wrap]');

  function draw() {
    const side = Math.min(img.width, img.height) / view.z;
    const sx = Math.min(Math.max(view.cx * img.width - side / 2, 0), img.width - side);
    const sy = Math.min(Math.max(view.cy * img.height - side / 2, 0), img.height - side);
    view.cx = (sx + side / 2) / img.width;
    view.cy = (sy + side / 2) / img.height;
    ctx.fillStyle = '#fff'; // transparent PNGs become white, not black, as JPEG
    ctx.fillRect(0, 0, 800, 800);
    ctx.drawImage(img, sx, sy, side, side, 0, 0, 800, 800);
  }

  function pan(dx, dy) {
    if (!img) return;
    const side = Math.min(img.width, img.height) / view.z;
    view.cx += (dx * side) / img.width;
    view.cy += (dy * side) / img.height;
    draw();
  }

  f.headshot.addEventListener('change', async () => {
    const file = f.headshot.files[0];
    f.headshot.value = '';
    if (!file) return;
    let message = '';
    let bitmap = null;
    if (file.size > 5 * MB) message = `That photo is ${sizeMB(file.size)}. The limit is 5 MB.`;
    else if (!sniffImage(new Uint8Array(await file.slice(0, 16).arrayBuffer()))) message = 'Use a JPEG, PNG or WebP photo.';
    else {
      try {
        bitmap = await createImageBitmap(file); // applies the EXIF orientation
      } catch {
        message = 'We could not read that photo. Try another one.';
      }
      if (bitmap && (bitmap.width < 400 || bitmap.height < 400)) {
        message = `That photo is ${bitmap.width} × ${bitmap.height} pixels. Use one at least 400 × 400.`;
        bitmap = null;
      }
    }
    if (message) {
      setError(f.headshot, message);
      return;
    }
    img?.close?.();
    img = bitmap;
    view = { z: 1, cx: 0.5, cy: 0.5 };
    // Never zoom past the photo's own resolution: the crop always keeps at least 400 source pixels.
    const maxZoom = Math.min(3, Math.min(img.width, img.height) / 400);
    f.zoom.max = String(maxZoom);
    f.zoom.value = '1';
    zoomWrap.hidden = maxZoom < 1.05;
    canvas.hidden = false;
    form.querySelector('[data-hrs-empty]').hidden = true;
    form.querySelector('[data-hrs-pick]').textContent = 'Replace photo';
    setError(f.headshot, '');
    dirty = true;
    draw();
    if (!reduced()) animate(canvas, { opacity: [0, 1], transform: ['scale(.97)', 'scale(1)'] }, { duration: 0.22 });
  });

  f.zoom.addEventListener('input', () => {
    view.z = Number(f.zoom.value) || 1;
    if (img) draw();
  });
  let drag = null;
  canvas.addEventListener('pointerdown', (e) => {
    drag = { x: e.clientX, y: e.clientY };
    canvas.setPointerCapture(e.pointerId);
  });
  canvas.addEventListener('pointermove', (e) => {
    if (!drag) return;
    const scale = 1 / canvas.clientWidth;
    pan(-(e.clientX - drag.x) * scale, -(e.clientY - drag.y) * scale);
    drag = { x: e.clientX, y: e.clientY };
  });
  ['pointerup', 'pointercancel'].forEach((t) => canvas.addEventListener(t, () => { drag = null; }));
  canvas.addEventListener('keydown', (e) => {
    const step = { ArrowLeft: [-0.04, 0], ArrowRight: [0.04, 0], ArrowUp: [0, -0.04], ArrowDown: [0, 0.04] }[e.key];
    if (!step) return;
    e.preventDefault();
    pan(...step);
  });

  // ---------------------------------------------------------------- attachment: extension AND magic bytes
  const attachName = form.querySelector('[data-hrs-attach-name]');
  const attachRemove = form.querySelector('[data-hrs-attach-remove]');
  function setAttachment(value) {
    attachment = value;
    attachName.hidden = !value;
    attachRemove.hidden = !value;
    attachName.textContent = value ? `${value.name} · ${sizeMB(value.size)}` : '';
  }
  f.attachment.addEventListener('change', async () => {
    const file = f.attachment.files[0];
    f.attachment.value = '';
    if (!file) return;
    const ext = extOf(file.name);
    let message = '';
    let bytes = null;
    if (!DOCS[ext]) message = 'Choose a .docx, .pdf, .odt, .rtf or .txt file.';
    else if (file.size > 8 * MB) message = `That file is ${sizeMB(file.size)}. The limit is 8 MB.`;
    else if (file.size === 0) message = 'That file is empty.';
    else {
      bytes = new Uint8Array(await file.arrayBuffer());
      if (!DOCS[ext].ok(bytes)) message = `That file's contents do not look like a .${ext} file. Save it again and retry.`;
    }
    setAttachment(message ? null : { name: safeName(file.name), type: DOCS[ext].type, size: file.size, bytes });
    setError(f.attachment, message);
  });
  attachRemove.addEventListener('click', () => {
    setAttachment(null);
    setError(f.attachment, '');
    f.attachment.focus();
  });

  // ---------------------------------------------------------------- submit: seal, then upload the envelope
  const say = (text) => { status.textContent = text; };
  function setBusy(on, text = '') {
    busy = on;
    for (const attr of ['aria-busy', 'aria-disabled']) {
      if (on) submit.setAttribute(attr, 'true');
      else submit.removeAttribute(attr);
    }
    form.querySelectorAll('fieldset.hrs-step').forEach((fs) => { fs.disabled = on; });
    say(text);
  }

  const val = (el) => el.value.trim();
  async function bundle() {
    const jpeg = await new Promise((resolve, reject) => canvas.toBlob((b) => (b ? resolve(b) : reject(new Error('photo'))), 'image/jpeg', 0.88));
    return {
      v: 1,
      type: type(),
      title: val(f.title),
      category: Number(f.category.value),
      category_name: f.category.selectedOptions[0]?.textContent || '',
      standfirst: val(f.standfirst),
      body: val(f.body),
      name: val(f.name),
      designation: val(f.designation),
      email: val(f.email),
      bio: val(f.bio),
      references: val(f.references),
      coi: val(f.coi),
      headshot: { type: 'image/jpeg', width: 800, height: 800, data_b64: toB64(new Uint8Array(await jpeg.arrayBuffer())) },
      attachment: attachment ? { name: attachment.name, type: attachment.type, size: attachment.size, data_b64: toB64(attachment.bytes) } : null,
      submitted_at: new Date().toISOString(),
    };
  }

  let nonce = (() => {
    try {
      return JSON.parse($('hra-config')?.textContent || '{}').nonce || '';
    } catch {
      return '';
    }
  })();
  const freshNonce = async () => (await (await fetch(cfg.session, { credentials: 'same-origin', cache: 'no-store' })).json()).nonce || '';
  const post = (body) => fetch(cfg.endpoint, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce }, body });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (busy) return;
    const errors = [];
    for (const el of ordered()) {
      const message = check(el);
      setError(el, message);
      if (message) errors.push({ el, message });
    }
    if (errors.length) {
      showSummary(errors);
      return;
    }
    form.querySelector('[data-hra-summary]').hidden = true;
    setBusy(true, 'Encrypting your submission on this device…');
    try {
      const sealed = await seal(te.encode(JSON.stringify(await bundle())), cfg.key);
      const envelope = { protected: sealed.protected, kem: sealed.kem, iv: sealed.iv, ct: sealed.ct };
      const fingerprint = await sha256hex(JSON.stringify(envelope));
      const payload = {
        type: type(),
        title: val(f.title),
        category: Number(f.category.value),
        words: countWords(f.body.value),
        email: val(f.email),
        has_attachment: Boolean(attachment),
        envelope,
      };
      if (f.remember.checked) payload.profile = { name: val(f.name), designation: val(f.designation), bio: val(f.bio) };
      const body = JSON.stringify(payload);
      if (body.length > 16 * MB) throw new Error('Your submission is too large once encrypted. Use a smaller attachment.');
      say(`Uploading the encrypted file (${sizeMB(body.length)})…`);
      let res = await post(body);
      let data = await res.json().catch(() => ({}));
      if (res.status === 403 && data.code === 'rest_cookie_invalid_nonce') {
        nonce = await freshNonce(); // the page was open for hours; get a fresh token and retry once
        res = await post(body);
        data = await res.json().catch(() => ({}));
      }
      if (res.status === 401 || data.code === 'rest_cookie_invalid_nonce') throw new Error('You have been signed out. Sign in again in a new tab, then press Encrypt and submit. Your text is still here.');
      if (!res.ok || !data.id) {
        const el = { type: f.type, title: f.title, category: f.category, body: f.body, email: f.email }[data.data?.field];
        if (el) {
          setBusy(false);
          setError(el, data.message);
          showSummary([{ el, message: data.message }]);
          return;
        }
        throw new Error(data.message || 'We could not send your submission. Please try again.');
      }
      dirty = false;
      showDone(data, fingerprint);
    } catch (err) {
      setBusy(false);
      const offline = err instanceof TypeError;
      showSummary([], offline ? 'We could not reach our server. Check your connection and try again. Your text is still here.' : err.message);
    }
  });

  function showDone(data, fingerprint) {
    form.reset();
    ctx.clearRect(0, 0, 800, 800);
    img = null;
    attachment = null;
    form.hidden = true;
    root.querySelectorAll('.hrs-lede, .hrs-guidelines').forEach((n) => { n.hidden = true; });
    done.querySelector('[data-hrs-done-title]').textContent = `Submission #${data.id} received`;
    done.querySelector('[data-hrs-fingerprint]').textContent = groupHex(String(data.sha256));
    if (data.sha256 !== fingerprint) done.querySelector('[data-hrs-fp-note]').textContent = `Your browser computed ${groupHex(fingerprint)} for what it sent. Please mention this if you contact us.`;
    done.hidden = false;
    done.focus();
    done.scrollIntoView({ block: 'start', behavior: reduced() ? 'auto' : 'smooth' });
    if (!reduced()) {
      animate(done, { opacity: [0, 1], transform: ['translateY(8px)', 'none'] }, { duration: 0.28, ease: [0.16, 1, 0.3, 1] });
      animate(done.querySelector('.hrs-done__mark'), { opacity: [0, 1], transform: ['scale(.6)', 'scale(1)'] }, { duration: 0.3, delay: 0.08, ease: [0.34, 1.56, 0.64, 1] });
    }
  }
}

const form = doc.querySelector('[data-hrs-form]');
if (form) init(form);
