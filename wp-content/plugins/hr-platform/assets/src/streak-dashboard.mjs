// /dashboard/: busy state on the preference forms, focus on the result message after a save, and the "How streaks
// work" tutorial.
//
// The tutorial steps are printed as static cards in #hrs-how (readable without JavaScript). This turns them into a
// step-through dialog (Back / Next, "Got it" on the last step, "Don't show again" on every step). It opens by itself
// when the section says data-hrs-tour="auto" (first sign-in, see includes/streaks/tour.php) and from any
// [data-hrs-tour-open] link. "Got it" and "Don't show again" tell the server (POST hr_action=tour, nonce, same
// origin); closing it any other way keeps it for the next visit.

for (const form of document.querySelectorAll('.hrs-form')) {
  form.addEventListener('submit', (e) => {
    const btn = form.querySelector('[type="submit"]');
    if (!btn) return;
    if (btn.getAttribute('aria-busy') === 'true') return e.preventDefault();
    btn.setAttribute('aria-busy', 'true');
  });
}
document.querySelector('.hrs-flash')?.focus();

const reduceMotion = () => window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const NS = 'http://www.w3.org/2000/svg';
const FLAME = [
  'M32 3C34 13 50 22 50 39C50 52 42 61 32 61C22 61 14 52 14 40C14 31 19 25 23 20C24 27 27 31 31 32C27 22 29 11 32 3Z',
  'M32 30C34 37 42 41 42 49C42 55 37 59 32 59C27 59 22 55 22 49C22 44 25 41 27 38C28 42 30 44 32 44C30 39 31 34 32 30Z',
];

function h(tag, props = {}, ...children) {
  const el = document.createElement(tag);
  for (const [k, v] of Object.entries(props)) {
    if (v === null || v === undefined || v === false) continue;
    if (k === 'class') el.className = v;
    else if (k === 'text') el.textContent = v;
    else el.setAttribute(k, v === true ? '' : String(v));
  }
  for (const c of children) if (c) el.append(c);
  return el;
}

function flame() {
  const svg = document.createElementNS(NS, 'svg');
  for (const [k, v] of Object.entries({ viewBox: '0 0 64 64', 'aria-hidden': 'true', focusable: 'false', class: 'hrs-flame is-lit' })) svg.setAttribute(k, v);
  FLAME.forEach((d, i) => {
    const p = document.createElementNS(NS, 'path');
    p.setAttribute('d', d);
    p.setAttribute('class', i ? 'hrs-flame__inner' : 'hrs-flame__outer');
    svg.appendChild(p);
  });
  return svg;
}

function sameOrigin(value) {
  try {
    const u = new URL(String(value || ''), location.href);
    return u.origin === location.origin ? u.href : null;
  } catch {
    return null;
  }
}

function tour(section) {
  const steps = [...section.querySelectorAll('[data-hrs-step]')].map((li) => ({
    icon: li.dataset.icon || '',
    svg: li.querySelector('.hrs-how__icon svg'),
    title: li.querySelector('.hrs-how__title')?.textContent.trim() || '',
    text: li.querySelector('.hrs-how__text')?.textContent.trim() || '',
    detail: li.querySelector('.hrs-how__detail')?.textContent.trim() || '',
  }));
  if (!steps.length || typeof HTMLDialogElement !== 'function') return;

  const action = sameOrigin(section.dataset.hrsAction);
  const nonce = section.dataset.hrsNonce || '';
  let index = 0;
  let back = null;

  const art = h('div', { class: 'hrs-tour__art', 'aria-hidden': 'true' });
  const count = h('p', { class: 'hrs-tour__count' });
  const title = h('h2', { class: 'hrs-tour__title', id: 'hrs-tour-title' });
  const text = h('p', { class: 'hrs-tour__text', id: 'hrs-tour-text' });
  const detail = h('p', { class: 'hrs-tour__detail' });
  const dots = h('ol', { class: 'hrs-tour__dots', 'aria-hidden': 'true' }, ...steps.map(() => h('li')));
  const prev = h('button', { type: 'button', class: 'hrp-btn hrp-btn--ghost hrs-tour__back', text: 'Back' });
  const next = h('button', { type: 'button', class: 'hrp-btn hrs-tour__next', text: 'Next' });
  const never = h('button', { type: 'button', class: 'hrs-tour__never', text: "Don't show again" });
  const close = h('button', { type: 'button', class: 'hrs-tour__close', 'aria-label': 'Close the tutorial' });
  close.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>';
  const body = h('div', { class: 'hrs-tour__body', 'aria-live': 'polite' }, count, title, text, detail);
  const dlg = h('dialog', { class: 'hrp hrs-tour', 'aria-labelledby': 'hrs-tour-title', 'aria-describedby': 'hrs-tour-text' },
    close,
    art,
    body,
    dots,
    h('div', { class: 'hrs-tour__nav' }, prev, next),
    h('p', { class: 'hrs-tour__foot' }, never));

  function show(i, focusNext = false) {
    index = Math.max(0, Math.min(steps.length - 1, i));
    const s = steps[index];
    art.replaceChildren(index === 0 || s.icon === 'flame' ? flame() : s.svg ? s.svg.cloneNode(true) : '');
    art.dataset.icon = index === 0 ? 'flame' : s.icon;
    count.textContent = `Step ${index + 1} of ${steps.length}`;
    title.textContent = s.title;
    text.textContent = s.text;
    detail.textContent = s.detail;
    detail.hidden = !s.detail;
    [...dots.children].forEach((d, n) => d.classList.toggle('is-on', n <= index));
    prev.hidden = index === 0;
    const last = index === steps.length - 1;
    next.textContent = last ? 'Got it' : 'Next';
    next.classList.toggle('is-done', last);
    if (focusNext) next.focus();
    if (!reduceMotion() && body.animate) {
      body.animate([{ opacity: 0, transform: 'translateX(12px)' }, { opacity: 1, transform: 'none' }], { duration: 260, easing: 'cubic-bezier(.16,1,.3,1)' });
      art.animate([{ transform: 'scale(.6) rotate(-10deg)', opacity: 0 }, { transform: 'none', opacity: 1 }], { duration: 520, easing: 'cubic-bezier(.34,1.56,.64,1)' });
    }
  }

  // Remembered on the server, so the tutorial does not open by itself again on any device.
  async function finish() {
    section.dataset.hrsTour = '';
    dlg.close();
    if (!action || !nonce) return;
    try {
      await fetch(action, {
        method: 'POST',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { Accept: 'application/json' },
        body: new URLSearchParams({ hr_action: 'tour', _hrsnonce: nonce }),
      });
    } catch {
      // Offline: it opens again next time, which is harmless.
    }
  }

  prev.addEventListener('click', () => show(index - 1, true));
  next.addEventListener('click', () => (index === steps.length - 1 ? finish() : show(index + 1, true)));
  never.addEventListener('click', finish);
  close.addEventListener('click', () => dlg.close());
  dlg.addEventListener('click', (e) => {
    if (e.target === dlg) dlg.close();
  });
  dlg.addEventListener('keydown', (e) => {
    if (e.target.closest('button') && e.target !== next && e.target !== prev) return;
    if (e.key === 'ArrowRight' && index < steps.length - 1) show(index + 1, true);
    else if (e.key === 'ArrowLeft' && index > 0) show(index - 1, true);
  });
  dlg.addEventListener('close', () => {
    document.documentElement.classList.remove('hrs-tour-open');
    if (back && back.isConnected && back.focus) back.focus();
  });
  document.body.appendChild(dlg);

  function open(e) {
    if (e) e.preventDefault();
    back = e && e.currentTarget instanceof HTMLElement ? e.currentTarget : document.activeElement;
    show(0);
    dlg.showModal();
    document.documentElement.classList.add('hrs-tour-open');
    next.focus();
    if (!reduceMotion() && dlg.animate) dlg.animate([{ opacity: 0, transform: 'translateY(16px) scale(.97)' }, { opacity: 1, transform: 'none' }], { duration: 300, easing: 'cubic-bezier(.16,1,.3,1)' });
  }

  for (const el of document.querySelectorAll('[data-hrs-tour-open]')) {
    el.hidden = false;
    el.addEventListener('click', open);
  }
  if (section.dataset.hrsTour === 'auto') open();
}

const how = document.getElementById('hrs-how');
if (how) tour(how);
