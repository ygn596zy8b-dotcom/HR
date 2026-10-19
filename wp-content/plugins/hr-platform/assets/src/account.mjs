// Account pages: inline validation + error summary, busy buttons, password reveal and strength meter (WordPress's
// zxcvbn), passkeys (sign-in incl. conditional UI, add, step-up), recovery-code download/copy, confirm dialogs,
// step-up countdown and a little motion. Server data only arrives through data-* attributes and the
// non-executable #hra-config JSON; nothing here uses innerHTML or browser storage.
import { animate } from 'motion/mini';
import { stagger } from 'motion';

const doc = document;
const root = doc.documentElement;
root.classList.add('hra-js');
const reduced = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const $$ = (sel, ctx = doc) => [...ctx.querySelectorAll(sel)];

const cfg = (() => {
  try {
    return JSON.parse(doc.getElementById('hra-config')?.textContent || '{}');
  } catch {
    return {};
  }
})();

// ------------------------------------------------------------------------------------------------ small helpers
const NS = 'http://www.w3.org/2000/svg';
const PATHS = {
  eye: ['M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0', 'circle:12,12,3'],
  'eye-off': ['M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49', 'M14.084 14.158a3 3 0 0 1-4.242-4.242', 'M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143', 'm2 2 20 20'],
  check: ['M20 6 9 17l-5-5'],
};
function svg(name) {
  const el = doc.createElementNS(NS, 'svg');
  for (const [k, v] of Object.entries({ class: `hr-icon hr-icon-${name}`, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': '2', 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'aria-hidden': 'true', focusable: 'false' })) el.setAttribute(k, v);
  for (const d of PATHS[name] || []) {
    let node;
    if (d.startsWith('circle:')) {
      const [cx, cy, r] = d.slice(7).split(',');
      node = doc.createElementNS(NS, 'circle');
      node.setAttribute('cx', cx);
      node.setAttribute('cy', cy);
      node.setAttribute('r', r);
    } else {
      node = doc.createElementNS(NS, 'path');
      node.setAttribute('d', d);
    }
    el.appendChild(node);
  }
  return el;
}

function el(tag, props = {}, ...kids) {
  const node = doc.createElement(tag);
  for (const [k, v] of Object.entries(props)) {
    if (v == null || v === false) continue;
    if (k === 'class') node.className = v;
    else if (k === 'text') node.textContent = v;
    else node.setAttribute(k, v === true ? '' : String(v));
  }
  for (const kid of kids) if (kid) node.appendChild(typeof kid === 'string' ? doc.createTextNode(kid) : kid);
  return node;
}

let toastBox;
function toast(message) {
  if (!toastBox) {
    toastBox = el('div', { class: 'hrp hrp-toasts', role: 'status', 'aria-live': 'polite' });
    doc.body.appendChild(toastBox);
  }
  const t = el('div', { class: 'hrp-toast' }, svg('check'), el('span', { text: message }));
  toastBox.appendChild(t);
  if (!reduced()) animate(t, { opacity: [0, 1], transform: ['translateY(8px)', 'none'] }, { duration: 0.22 });
  setTimeout(() => t.remove(), 3200);
}

function setBusy(button, busy) {
  if (!button) return;
  if (busy) {
    button.setAttribute('aria-busy', 'true');
    button.setAttribute('aria-disabled', 'true');
  } else {
    button.removeAttribute('aria-busy');
    button.removeAttribute('aria-disabled');
  }
}

function sameOrigin(url) {
  try {
    const u = new URL(url, location.href);
    return u.origin === location.origin ? u.href : null;
  } catch {
    return null;
  }
}

async function api(path, body, auth) {
  if (!cfg.rest) throw Object.assign(new Error('Not available'), { code: 'config' });
  const headers = { 'Content-Type': 'application/json', Accept: 'application/json' };
  if (auth && cfg.nonce) headers['X-WP-Nonce'] = cfg.nonce;
  const res = await fetch(cfg.rest + path, { method: 'POST', credentials: 'same-origin', cache: 'no-store', headers, body: JSON.stringify(body || {}) });
  const data = await res.json().catch(() => null);
  if (!res.ok) throw Object.assign(new Error((data && data.message) || 'Something went wrong. Please try again.'), { code: data && data.code, status: res.status });
  return data;
}

// ------------------------------------------------------------------------------------------------ validation
function errorParts(input) {
  const id = input.id;
  const box = doc.getElementById(`${id}-error`);
  return { box, text: box?.querySelector('span') };
}

function setFieldError(input, message) {
  const { box, text } = errorParts(input);
  const describedBy = new Set((input.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean));
  if (message) {
    input.setAttribute('aria-invalid', 'true');
    if (box) {
      text.textContent = message;
      box.hidden = false;
      describedBy.add(box.id);
    }
  } else {
    input.removeAttribute('aria-invalid');
    if (box) {
      box.hidden = true;
      text.textContent = '';
      describedBy.delete(box.id);
    }
  }
  if (describedBy.size) input.setAttribute('aria-describedby', [...describedBy].join(' '));
  else input.removeAttribute('aria-describedby');
}

function validateInput(input) {
  const required = input.dataset.hraRequired;
  const value = input.type === 'checkbox' ? (input.checked ? '1' : '') : input.value.trim();
  if (required && !value) return required;
  if (!value) return '';
  if (input.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value)) return 'Enter a valid email address, like name@example.com.';
  const min = Number(input.getAttribute('minlength') || 0);
  if (min && value.length < min) return input.type === 'password' ? `Use at least ${min} characters. A short sentence works well.` : `Use at least ${min} characters.`;
  if (input.dataset.hraOtp && !/^\d{6}$/.test(value.replace(/\s+/g, ''))) return 'Enter the 6-digit code.';
  if (input.dataset.hraEquals && value !== input.dataset.hraEquals) return input.dataset.hraRequired || 'Please check this field.';
  return '';
}

function showSummary(form, errors) {
  const summary = form.querySelector('[data-hra-summary]');
  if (!summary) {
    errors[0]?.input.focus();
    return;
  }
  const title = summary.querySelector('.hra-summary__title span');
  const list = summary.querySelector('.hra-summary__list');
  const lead = summary.querySelector('.hra-summary__lead');
  if (lead) lead.remove();
  title.textContent = errors.length === 1 ? 'There is a problem with one field' : `There are problems with ${errors.length} fields`;
  list.replaceChildren(...errors.map(({ input, message }) => {
    const a = el('a', { href: `#${input.id}`, text: message });
    a.addEventListener('click', (e) => {
      e.preventDefault();
      input.focus();
    });
    return el('li', {}, a);
  }));
  summary.hidden = false;
  summary.focus();
  if (!reduced()) animate(summary, { opacity: [0, 1], transform: ['translateY(-4px)', 'none'] }, { duration: 0.2 });
}

// ------------------------------------------------------------------------------------------------ confirm dialog
function confirmDialog(message) {
  if (typeof HTMLDialogElement !== 'function') return Promise.resolve(window.confirm(message));
  return new Promise((resolve) => {
    const ok = el('button', { type: 'button', class: 'hrp-btn', text: 'Yes, continue' });
    const cancel = el('button', { type: 'button', class: 'hrp-btn hrp-btn--ghost', text: 'Cancel' });
    const dialog = el('dialog', { class: 'hrp hra-dialog', 'aria-labelledby': 'hra-dialog-title' },
      el('p', { class: 'hra-dialog__title', id: 'hra-dialog-title', text: 'Please confirm' }),
      el('p', { class: 'hra-dialog__text', text: message }),
      el('div', { class: 'hra-actions' }, ok, cancel));
    const done = (value) => {
      dialog.close();
      dialog.remove();
      resolve(value);
    };
    ok.addEventListener('click', () => done(true));
    cancel.addEventListener('click', () => done(false));
    dialog.addEventListener('cancel', (e) => {
      e.preventDefault();
      done(false);
    });
    doc.body.appendChild(dialog);
    dialog.showModal();
    cancel.focus();
  });
}

// ------------------------------------------------------------------------------------------------ forms
function initForms() {
  for (const form of $$('form[data-hra-form]')) {
    let submitting = false;
    let confirmed = false;
    form.addEventListener('submit', async (e) => {
      if (submitting) {
        e.preventDefault();
        return;
      }
      const errors = [];
      for (const input of $$('input[data-hra-required], input[minlength], input[data-hra-otp], input[data-hra-equals]', form)) {
        const message = validateInput(input);
        setFieldError(input, message);
        if (message) errors.push({ input, message });
      }
      if (errors.length) {
        e.preventDefault();
        showSummary(form, errors);
        return;
      }
      if (form.dataset.hraConfirm && !confirmed) {
        e.preventDefault();
        if (await confirmDialog(form.dataset.hraConfirm)) {
          confirmed = true;
          form.requestSubmit(e.submitter || undefined);
        }
        return;
      }
      submitting = true;
      setBusy(e.submitter || form.querySelector('[type="submit"]'), true);
    });
    form.addEventListener('input', (e) => {
      const input = e.target;
      if (input instanceof HTMLInputElement && input.getAttribute('aria-invalid') === 'true' && !validateInput(input)) setFieldError(input, '');
    });
    form.addEventListener('change', (e) => {
      const input = e.target;
      if (input instanceof HTMLInputElement && input.type === 'checkbox' && input.getAttribute('aria-invalid') === 'true' && !validateInput(input)) setFieldError(input, '');
    });
  }
  // Pages restored from the back/forward cache must not keep a spinning button.
  window.addEventListener('pageshow', (e) => {
    if (e.persisted) $$('[aria-busy="true"]').forEach((b) => setBusy(b, false));
  });

  // Downloads do not navigate: show the busy state briefly.
  for (const form of $$('form[data-hra-download]')) {
    form.addEventListener('submit', () => {
      const b = form.querySelector('[type="submit"]');
      setBusy(b, true);
      setTimeout(() => setBusy(b, false), 2500);
    });
  }

  // One-time codes: digits only; submit as soon as six are in.
  for (const input of $$('input[data-hra-otp]')) {
    input.addEventListener('input', () => {
      const digits = input.value.replace(/\D+/g, '').slice(0, 6);
      if (input.value !== digits) input.value = digits;
      if (digits.length === 6 && input.form && input.form.querySelectorAll('input:not([type="hidden"])').length === 1) input.form.requestSubmit();
    });
  }

  const auto = doc.querySelector('[data-autofocus]:not([hidden])');
  if (auto) {
    auto.focus({ preventScroll: false });
  }
}

// ------------------------------------------------------------------------------------------------ password UI
function initReveal() {
  const toggle = (btn, input, show) => {
    input.type = show ? 'text' : 'password';
    btn.setAttribute('aria-pressed', String(show));
    btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    btn.replaceChildren(svg(show ? 'eye-off' : 'eye'));
  };
  for (const btn of $$('button.hrp-reveal[data-hra-reveal]')) {
    const input = doc.getElementById(btn.dataset.hraReveal);
    if (!input) continue;
    btn.addEventListener('click', () => {
      toggle(btn, input, input.type === 'password');
      input.focus();
    });
    // Never leave a revealed password in the page once the form is sent (autofill, history restore).
    input.form?.addEventListener('submit', () => toggle(btn, input, false));
  }
}

const WORDS = ['Very weak', 'Weak', 'Fair', 'Good', 'Strong'];
const TIPS = [
  'Avoid common words and patterns. Try three or four random words.',
  'Easy to guess. Add another word or two — uncommon words are best.',
  'Getting there. A few more characters will make it much stronger.',
  'Good password. Longer is even better.',
  'Excellent. This would take an attacker centuries.',
];

function initMeters() {
  for (const meter of $$('[data-hra-meter]')) {
    const input = doc.getElementById(meter.dataset.hraMeter);
    if (!input) continue;
    const label = meter.querySelector('.hra-meter__label');
    const tip = meter.querySelector('.hra-meter__tip');
    const bars = $$('.hra-meter__bars span', meter);
    const form = input.form;
    const userInputs = () => (input.dataset.hraUserInputs || '').split(/\s+/).map((n) => form?.elements.namedItem(n)?.value || '').filter(Boolean)
      .flatMap((v) => v.toLowerCase().split(/[@\s.]+/)).filter((v) => v.length > 2);
    let last = -2;
    const update = () => {
      const pw = input.value;
      let score = -1;
      let words;
      let advice = '';
      if (!pw) {
        words = 'Use at least 12 characters.';
      } else if (pw.length < 12) {
        words = 'Too short';
        advice = `${12 - pw.length} more character${12 - pw.length === 1 ? '' : 's'} to go.`;
        score = 0;
      } else {
        const strength = window.wp && window.wp.passwordStrength;
        if (strength && typeof window.zxcvbn === 'function') {
          const disallowed = (strength.userInputDisallowedList ? strength.userInputDisallowedList() : []).concat(userInputs());
          const result = window.zxcvbn(pw, disallowed);
          score = result.score;
          advice = (result.feedback && result.feedback.warning) || TIPS[score];
        } else {
          // zxcvbn still loading: rough length-based guess, replaced on the next keystroke.
          score = pw.length >= 20 ? 3 : 2;
          advice = TIPS[score];
        }
        words = WORDS[score];
      }
      meter.dataset.score = String(score);
      label.textContent = words;
      tip.textContent = advice;
      if (score !== last && score >= 0 && !reduced()) {
        const lit = bars.slice(0, Math.max(1, score));
        animate(lit, { transform: ['scaleX(.6)', 'scaleX(1)'] }, { duration: 0.2, delay: stagger(0.03) });
      }
      last = score;
    };
    input.addEventListener('input', update);
    // zxcvbn arrives asynchronously; refresh once it does.
    const wait = setInterval(() => {
      if (typeof window.zxcvbn === 'function') {
        clearInterval(wait);
        if (input.value) update();
      }
    }, 400);
    setTimeout(() => clearInterval(wait), 15000);
  }
}

// ------------------------------------------------------------------------------------------------ passkeys
const b64uToBuf = (s) => {
  const b64 = String(s).replace(/-/g, '+').replace(/_/g, '/');
  const bin = atob(b64 + '='.repeat((4 - (b64.length % 4)) % 4));
  const out = new Uint8Array(bin.length);
  for (let i = 0; i < bin.length; i++) out[i] = bin.charCodeAt(i);
  return out.buffer;
};
const bufToB64u = (buf) => {
  if (!buf) return '';
  const bytes = new Uint8Array(buf);
  let s = '';
  for (let i = 0; i < bytes.length; i++) s += String.fromCharCode(bytes[i]);
  return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
};
const supportsPasskeys = () => typeof window.PublicKeyCredential === 'function' && !!navigator.credentials;

function requestOptions(o) {
  const pk = { challenge: b64uToBuf(o.challenge), timeout: o.timeout, rpId: o.rpId, userVerification: o.userVerification || 'required' };
  if (Array.isArray(o.allowCredentials) && o.allowCredentials.length) {
    pk.allowCredentials = o.allowCredentials.map((c) => ({ type: 'public-key', id: b64uToBuf(c.id), transports: c.transports }));
  }
  return pk;
}

function creationOptions(o) {
  return {
    rp: o.rp,
    user: { id: b64uToBuf(o.user.id), name: o.user.name, displayName: o.user.displayName },
    challenge: b64uToBuf(o.challenge),
    pubKeyCredParams: o.pubKeyCredParams,
    timeout: o.timeout,
    attestation: o.attestation || 'none',
    authenticatorSelection: o.authenticatorSelection,
    excludeCredentials: (o.excludeCredentials || []).map((c) => ({ type: 'public-key', id: b64uToBuf(c.id), transports: c.transports })),
    extensions: { credProps: true },
  };
}

function assertionBody(cred) {
  const r = cred.response;
  return {
    id: bufToB64u(cred.rawId),
    clientDataJSON: bufToB64u(r.clientDataJSON),
    authenticatorData: bufToB64u(r.authenticatorData),
    signature: bufToB64u(r.signature),
    userHandle: bufToB64u(r.userHandle),
  };
}

function passkeyMessage(err, fallback) {
  if (!err) return fallback;
  if (err.name === 'NotAllowedError' || err.name === 'AbortError') return 'Passkey sign-in was cancelled or timed out. You can try again.';
  if (err.name === 'InvalidStateError') return 'This device already has a passkey for your account.';
  if (err.name === 'SecurityError') return 'Passkeys need a secure (https) connection.';
  return err.message || fallback;
}

function initPasskeyLogin() {
  const form = doc.querySelector('form[data-hra-passkey-login]');
  if (!form) return;
  if (!supportsPasskeys()) return;
  $$('[data-hra-passkey-only]').forEach((n) => (n.hidden = false));
  const button = doc.querySelector('[data-hra-passkey-signin]');
  const errorBox = doc.querySelector('[data-hra-passkey-error]');
  const remember = form.querySelector('input[name="remember"]');
  let conditional = null;

  const finish = async (cred) => {
    const body = assertionBody(cred);
    body.remember = !!(remember && remember.checked);
    body.redirect_to = form.dataset.hraPasskeyLogin || '';
    const data = await api('passkeys/login/verify', body, false);
    const next = sameOrigin(data && data.redirect) || '/account/';
    location.assign(next);
  };

  const showError = (msg) => {
    errorBox.textContent = msg;
    errorBox.hidden = !msg;
  };

  // Conditional UI: passkeys appear in the email field's autofill list.
  (async () => {
    try {
      if (!PublicKeyCredential.isConditionalMediationAvailable || !(await PublicKeyCredential.isConditionalMediationAvailable())) return;
      conditional = new AbortController();
      const options = await api('passkeys/login/options', {}, false);
      const cred = await navigator.credentials.get({ publicKey: requestOptions(options), mediation: 'conditional', signal: conditional.signal });
      if (cred) {
        setBusy(button, true);
        await finish(cred);
      }
    } catch (err) {
      if (err && err.name !== 'AbortError' && err.name !== 'NotAllowedError' && !(conditional && conditional.signal.aborted)) showError(passkeyMessage(err, ''));
      setBusy(button, false);
    }
  })();

  button?.addEventListener('click', async () => {
    if (button.getAttribute('aria-busy') === 'true') return;
    showError('');
    if (conditional) conditional.abort();
    setBusy(button, true);
    try {
      const options = await api('passkeys/login/options', {}, false);
      const cred = await navigator.credentials.get({ publicKey: requestOptions(options) });
      await finish(cred);
    } catch (err) {
      setBusy(button, false);
      showError(passkeyMessage(err, 'We could not sign you in with that passkey.'));
    }
  });
}

// Second sign-in step with a passkey: options come from the page (non-executable JSON), the assertion goes back
// in a normal form post so the HttpOnly /account/ cookie that binds this sign-in travels with it.
function initTwoFactorPasskey() {
  const form = doc.querySelector('form[data-hra-2fa-passkey]');
  if (!form) return;
  const go = form.querySelector('[data-hra-2fa-passkey-go]');
  const errorBox = form.querySelector('[data-hra-2fa-passkey-error]');
  if (!supportsPasskeys()) {
    go.hidden = true;
    form.querySelector('[data-hra-passkey-unsupported]').hidden = false;
    return;
  }
  let options;
  try {
    options = JSON.parse(doc.getElementById('hra-2fa-options')?.textContent || 'null');
  } catch {
    options = null;
  }
  const run = async () => {
    if (!options || go.getAttribute('aria-busy') === 'true') return;
    errorBox.hidden = true;
    setBusy(go, true);
    try {
      const cred = await navigator.credentials.get({ publicKey: requestOptions(options) });
      const body = assertionBody(cred);
      for (const key of ['id', 'clientDataJSON', 'authenticatorData', 'signature']) form.elements.namedItem(`wa_${key}`).value = body[key];
      HTMLFormElement.prototype.submit.call(form);
    } catch (err) {
      setBusy(go, false);
      errorBox.textContent = passkeyMessage(err, 'That passkey could not be used. Try again.');
      errorBox.hidden = false;
    }
  };
  go.addEventListener('click', run);
}

function revealStepUp(anchor, message) {
  const panel = doc.getElementById(`stepup-${anchor}`) || doc.querySelector('[data-hra-stepup]');
  if (!panel) return;
  panel.scrollIntoView({ behavior: reduced() ? 'auto' : 'smooth', block: 'center' });
  const field = panel.querySelector('input[type="password"]');
  setTimeout(() => field?.focus({ preventScroll: true }), reduced() ? 0 : 350);
  if (message) toast(message);
  if (!reduced()) animate(panel, { transform: ['translateX(0)', 'translateX(-6px)', 'translateX(6px)', 'translateX(0)'] }, { duration: 0.3 });
}

function reloadAt(anchor) {
  if (location.hash !== `#${anchor}`) history.replaceState(null, '', `${location.pathname}${location.search}#${anchor}`);
  location.reload();
}

function initPasskeySettings() {
  const add = doc.querySelector('[data-hra-passkey-add]');
  if (add) {
    const errorBox = doc.querySelector('[data-hra-passkey-add-error]');
    if (!supportsPasskeys()) {
      add.hidden = true;
      const note = doc.querySelector('[data-hra-passkey-unsupported]');
      if (note) note.hidden = false;
    }
    add.addEventListener('click', async () => {
      if (add.getAttribute('aria-busy') === 'true') return;
      errorBox.hidden = true;
      if (add.dataset.needsStepup === '1') {
        revealStepUp('security', 'Confirm it’s you first, then add your passkey.');
        return;
      }
      setBusy(add, true);
      try {
        const options = await api('passkeys/register/options', {}, true);
        const cred = await navigator.credentials.create({ publicKey: creationOptions(options) });
        const r = cred.response;
        await api('passkeys/register/verify', {
          clientDataJSON: bufToB64u(r.clientDataJSON),
          attestationObject: bufToB64u(r.attestationObject),
          transports: typeof r.getTransports === 'function' ? r.getTransports() : [],
        }, true);
        reloadAt('passkeys');
      } catch (err) {
        setBusy(add, false);
        if (err && err.code === 'hr_step_up_required') {
          revealStepUp('security', err.message);
          return;
        }
        errorBox.textContent = passkeyMessage(err, 'We could not add that passkey. Please try again.');
        errorBox.hidden = false;
      }
    });
  }

  for (const btn of $$('[data-hra-stepup-passkey]')) {
    const panel = btn.closest('[data-hra-stepup]');
    const errorBox = panel?.querySelector('[data-hra-stepup-error]');
    if (!supportsPasskeys()) {
      btn.hidden = true;
      continue;
    }
    btn.addEventListener('click', async () => {
      if (btn.getAttribute('aria-busy') === 'true') return;
      if (errorBox) errorBox.hidden = true;
      setBusy(btn, true);
      try {
        const options = await api('passkeys/stepup/options', {}, true);
        const cred = await navigator.credentials.get({ publicKey: requestOptions(options) });
        const body = assertionBody(cred);
        delete body.userHandle;
        await api('passkeys/stepup/verify', body, true);
        reloadAt(btn.dataset.hraStepupPasskey || 'security');
      } catch (err) {
        setBusy(btn, false);
        if (errorBox) {
          errorBox.textContent = passkeyMessage(err, 'That passkey could not be verified. Try again or use your password.');
          errorBox.hidden = false;
        }
      }
    });
  }
}

// ------------------------------------------------------------------------------------------------ codes, copy, countdown
async function copyText(text, done = 'Copied to clipboard') {
  try {
    await navigator.clipboard.writeText(text);
    toast(done);
  } catch {
    toast('Copy failed — select the text and copy it manually.');
  }
}

function initCodes() {
  const list = doc.querySelector('[data-hra-code-list]');
  const codes = () => $$('code', list || doc.createElement('div')).map((c) => c.textContent.trim());
  doc.querySelector('[data-hra-download-codes]')?.addEventListener('click', () => {
    const host = location.hostname;
    const text = [
      'Headline Recorder — two-step verification recovery codes',
      `Account site: ${host}`,
      `Created: ${new Date().toLocaleString()}`,
      '',
      'Each code can be used once to sign in if you lose your authenticator.',
      'Keep them somewhere safe and private.',
      '',
      ...codes().map((c, i) => `${String(i + 1).padStart(2, ' ')}. ${c}`),
      '',
    ].join('\r\n');
    const url = URL.createObjectURL(new Blob([text], { type: 'text/plain;charset=utf-8' }));
    const a = el('a', { href: url, download: 'headline-recorder-recovery-codes.txt' });
    doc.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
    toast('Recovery codes downloaded');
  });
  doc.querySelector('[data-hra-copy-codes]')?.addEventListener('click', () => copyText(codes().join('\n'), 'Recovery codes copied'));
  for (const btn of $$('[data-hra-copy]')) btn.addEventListener('click', () => copyText(btn.dataset.hraCopy || '', 'Setup key copied'));

  const countdown = doc.querySelector('[data-hra-countdown]');
  if (countdown) {
    const end = Date.now() + Number(countdown.dataset.hraCountdown || 0) * 1000;
    const out = countdown.querySelector('[data-hra-countdown-text]');
    const tick = () => {
      const left = Math.max(0, end - Date.now());
      if (!left) {
        countdown.replaceChildren(el('span', { text: 'Your confirmation has expired. Reload the page to confirm it’s you again.' }));
        clearInterval(timer);
        return;
      }
      const min = Math.ceil(left / 60000);
      out.textContent = `${min} min`;
    };
    const timer = setInterval(tick, 10000);
  }
}

// ------------------------------------------------------------------------------------------------ settings nav
function initSubnav() {
  const nav = doc.querySelector('[data-hra-subnav]');
  if (!nav || !('IntersectionObserver' in window)) return;
  const links = new Map($$('a[href^="#"]', nav).map((a) => [a.getAttribute('href').slice(1), a]));
  const io = new IntersectionObserver((entries) => {
    for (const entry of entries) {
      if (!entry.isIntersecting) continue;
      links.forEach((a) => a.classList.remove('is-active'));
      links.get(entry.target.id)?.classList.add('is-active');
    }
  }, { rootMargin: '-30% 0px -60% 0px' });
  links.forEach((_, id) => {
    const section = doc.getElementById(id);
    if (section) io.observe(section);
  });
}

// ------------------------------------------------------------------------------------------------ motion
function initMotion() {
  if (reduced()) return;
  const items = $$('[data-hra-reveal]:not(.hrp-reveal)');
  if (items.length) animate(items, { opacity: [0, 1], transform: ['translateY(8px)', 'none'] }, { duration: 0.28, delay: stagger(0.04), ease: [0.16, 1, 0.3, 1] });
  const bars = $$('.hra-score__bar span.is-on');
  if (bars.length) animate(bars, { transform: ['scaleX(0)', 'scaleX(1)'] }, { duration: 0.3, delay: stagger(0.06, { startDelay: 0.15 }), ease: [0.16, 1, 0.3, 1] });
}

initForms();
initReveal();
initMeters();
initPasskeyLogin();
initTwoFactorPasskey();
initPasskeySettings();
initCodes();
initSubnav();
initMotion();
