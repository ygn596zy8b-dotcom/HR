// Read tracking on single articles (same cached file for every visitor; it does nothing for guests).
//
// Signed-in readers: POST hr/v1/reads/start → count visible time only (Page Visibility API) and wait until the
// sentinel after the article body has been on screen → POST hr/v1/reads/complete. A counted read dispatches
// 'hr:streak' (the header chip updates), shows a "+10 XP" toast and, when the daily goal is met, a celebration
// dialog. The REST nonce comes from the in-memory session payload (window.HR.session) and is never stored.
import { animate } from 'motion/mini';
import { spring } from 'motion';

const NS = 'http://www.w3.org/2000/svg';
const FLAME = [
  'M32 3C34 13 50 22 50 39C50 52 42 61 32 61C22 61 14 52 14 40C14 31 19 25 23 20C24 27 27 31 31 32C27 22 29 11 32 3Z',
  'M32 30C34 37 42 41 42 49C42 55 37 59 32 59C27 59 22 55 22 49C22 44 25 41 27 38C28 42 30 44 32 44C30 39 31 34 32 30Z',
];
const sentinel = document.querySelector('.hr-read-sentinel[data-hr-post]');
const reduceMotion = () => window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

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

function sameOrigin(value) {
  try {
    const u = new URL(String(value || ''), location.href);
    return u.origin === location.origin ? u.href : null;
  } catch {
    return null;
  }
}

function flame() {
  const svg = document.createElementNS(NS, 'svg');
  svg.setAttribute('viewBox', '0 0 64 64');
  svg.setAttribute('aria-hidden', 'true');
  svg.setAttribute('class', 'hrs-flame is-lit');
  FLAME.forEach((d, i) => {
    const p = document.createElementNS(NS, 'path');
    p.setAttribute('d', d);
    p.setAttribute('class', i ? 'hrs-flame__inner' : 'hrs-flame__outer');
    svg.appendChild(p);
  });
  return svg;
}

// The celebration styles are only needed by signed-in readers, so they load on demand.
let cssReady;
function loadCss() {
  const href = sameOrigin(sentinel.dataset.hrCss);
  cssReady ||= new Promise((resolve) => {
    if (!href) return resolve();
    const link = h('link', { rel: 'stylesheet', href });
    link.onload = link.onerror = () => resolve();
    document.head.appendChild(link);
    setTimeout(resolve, 3000);
  });
  return cssReady;
}

let region;
function toast(text) {
  region ||= document.body.appendChild(h('div', { class: 'hrp-toasts', role: 'status', 'aria-live': 'polite' }));
  const el = h('div', { class: 'hrp-toast hrs-toast' }, flame(), h('span', { text }));
  region.appendChild(el);
  if (!reduceMotion()) animate(el, { opacity: [0, 1], transform: ['translateY(12px)', 'none'] }, { duration: 0.25, ease: [0.16, 1, 0.3, 1] });
  setTimeout(() => el.remove(), 6000);
}

function celebrate(r) {
  const s = r.streak || {};
  const c = r.celebrate || {};
  const n = Number(s.current) || 0;
  const back = document.activeElement;
  const title = n > 1 ? `${n}-day streak!` : 'Your streak has started!';
  const lines = ['Daily goal met. Come back tomorrow to keep it going.'];
  if (c.new_best) lines.push('That is your best streak yet.');
  if (c.freeze_earned) lines.push('You earned a streak freeze: it saves your streak on a day you miss.');
  if (c.badge_title) lines.push(`New badge: ${c.badge_title}.`);
  const embers = h('span', { class: 'hrs-embers', 'aria-hidden': 'true' });
  const art = h('span', { class: 'hrs-celebrate__art' }, flame(), embers);
  const close = h('button', { type: 'button', class: 'hrp-btn hrp-btn--ghost', text: 'Keep reading', autofocus: true });
  const dashboard = sameOrigin(sentinel.dataset.hrDashboard);
  const dlg = h('dialog', { class: 'hrp hrp-dark hrs-celebrate', 'aria-labelledby': 'hrs-cel-title', 'aria-describedby': 'hrs-cel-text' },
    art,
    h('p', { class: 'hrp-eyebrow', text: `+${Number(r.xp_gained) || 0} XP · Level ${Number(s.level) || 1}` }),
    h('h2', { class: 'hrs-celebrate__title', id: 'hrs-cel-title', text: title }),
    h('div', { id: 'hrs-cel-text' }, ...lines.map((text) => h('p', { text }))),
    h('div', { class: 'hrs-celebrate__actions' }, dashboard ? h('a', { class: 'hrp-btn', href: dashboard, text: 'See your streak' }) : null, close));
  close.addEventListener('click', () => dlg.close());
  dlg.addEventListener('click', (e) => { if (e.target === dlg) dlg.close(); });
  dlg.addEventListener('close', () => {
    dlg.remove();
    if (back && back.isConnected && back.focus) back.focus();
  });
  document.body.appendChild(dlg);
  dlg.showModal();
  if (reduceMotion()) return;
  animate(dlg, { opacity: [0, 1], transform: ['translateY(16px) scale(.96)', 'none'] }, { duration: 0.28, ease: [0.16, 1, 0.3, 1] });
  animate(art.firstChild, { transform: ['scale(.3) rotate(-14deg)', 'scale(1) rotate(0deg)'] }, { type: spring, bounce: 0.55, duration: 0.8 });
  for (let i = 0; i < 8; i++) {
    const dot = embers.appendChild(h('i'));
    const angle = (-90 + (i - 3.5) * 22) * (Math.PI / 180);
    const dist = 46 + (i % 3) * 14;
    animate(dot, { transform: ['translate(0,0) scale(1)', `translate(${Math.cos(angle) * dist}px,${Math.sin(angle) * dist}px) scale(.3)`], opacity: [1, 0] }, { duration: 0.9, delay: 0.15 + i * 0.02, ease: 'ease-out' });
  }
}

async function track() {
  const session = window.HR && window.HR.session;
  if (!sentinel || !session || typeof session.then !== 'function') return;
  const data = await session;
  const rest = sameOrigin(sentinel.dataset.hrRest);
  const postId = Number(sentinel.dataset.hrPost);
  if (!data || data.logged_in !== true || typeof data.nonce !== 'string' || !rest || !(postId > 0)) return;

  const post = (path, body) => fetch(rest + path, {
    method: 'POST',
    credentials: 'same-origin',
    cache: 'no-store',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-WP-Nonce': data.nonce },
    body: JSON.stringify(body),
  }).then(async (res) => ({ ok: res.ok, body: await res.json().catch(() => ({})) }), () => ({ ok: false, body: {} }));

  const start = await post('start', { post_id: postId });
  if (!start.ok || typeof start.body.token !== 'string') return;
  const { token, issued_at: issuedAt } = start.body;
  const need = Math.max(0, Number(start.body.min_seconds) || 0) * 1000;

  let visible = 0;
  let last = document.visibilityState === 'visible' ? performance.now() : null;
  let seen = false;
  let done = false;
  const tick = () => {
    if (last === null) return;
    const now = performance.now();
    visible += now - last;
    last = now;
  };
  const complete = async (retry) => {
    const res = await post('complete', { post_id: postId, token, issued_at: issuedAt });
    // The server clock decides; if it is a few seconds behind ours, try once more.
    if (!res.ok && res.body.code === 'too_fast' && retry) return setTimeout(() => complete(false), 5000);
    if (!res.ok || !res.body.counted) return;
    const s = res.body.streak || {};
    document.dispatchEvent(new CustomEvent('hr:streak', { detail: s }));
    await loadCss();
    const goal = Number(s.goal) || 0;
    const c = res.body.celebrate || {};
    toast(`${c.goal_met ? 'Daily goal met · ' : ''}+${Number(res.body.xp_gained) || 0} XP · ${Math.min(Number(s.today) || 0, goal)}/${goal} today`);
    if (c.goal_met) celebrate(res.body);
    else if (c.badge_title) toast(`New badge: ${c.badge_title}`);
  };
  const check = () => {
    tick();
    if (done || !seen || visible < need) return;
    done = true;
    clearInterval(timer);
    complete(true);
  };
  const timer = setInterval(check, 1000);
  document.addEventListener('visibilitychange', () => {
    tick();
    last = document.visibilityState === 'visible' ? performance.now() : null;
    check();
  });
  const io = new IntersectionObserver((entries) => {
    if (!entries.some((e) => e.isIntersecting)) return;
    seen = true;
    io.disconnect();
    check();
  });
  io.observe(sentinel);
}

// Deferred scripts run before DOMContentLoaded, so the account chip has set window.HR.session by then.
if (document.readyState === 'complete') track();
else document.addEventListener('DOMContentLoaded', track, { once: true });
