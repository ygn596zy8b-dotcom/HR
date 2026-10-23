// Floating reading-streak widget, bottom left on every front-end page (includes/streaks/widget.php).
//
// Pages are cached for everyone, so the server prints only a static JSON config (#hr-streak-widget-config). This
// script waits for window.HR.session (account-chip.js): signed-out visitors get an invitation to start a streak,
// signed-in readers their live counter from the session payload ('streak', 'streak_ui'). Nothing is shown without
// JavaScript, inside iframes, or in print.
//
// Open or minimised: guests see the card on every page of a new browser session until they minimise it; readers see
// it on the first page of a session, then the compact pill unless they reopen it. Kept in sessionStorage (per tab
// session, never sent to the server). On the streaks page itself it always starts as the pill.
//
// Updates live on the document 'hr:streak' event (detail = streak view) from streak.js, with a short celebration
// when the daily goal is met. window.HR.streakWidget = { visible(), notify( text ) } lets streak.js show its
// "+10 XP" note here instead of a separate toast.
//
// Motion: the same timings as streak.js (motion's animate + spring), done with the Web Animations API so this
// script, which every visitor downloads, stays small. A spring becomes a CSS linear() easing.

const NS = 'http://www.w3.org/2000/svg';
const FLAME = [
  'M32 3C34 13 50 22 50 39C50 52 42 61 32 61C22 61 14 52 14 40C14 31 19 25 23 20C24 27 27 31 31 32C27 22 29 11 32 3Z',
  'M32 30C34 37 42 41 42 49C42 55 37 59 32 59C27 59 22 55 22 49C22 44 25 41 27 38C28 42 30 44 32 44C30 39 31 34 32 30Z',
];
const ICONS = {
  snowflake: 'M2 12h20M12 2v20M20 16l-4-4 4-4M4 8l4 4-4 4M16 4l-4 4-4-4M8 20l4-4 4 4',
  minimise: 'M6 9l6 6 6-6',
  arrow: 'M5 12h14M12 5l7 7-7 7',
  check: 'M20 6 9 17l-5-5',
};
const RING_R = 22;
const RING_C = 2 * Math.PI * RING_R;
const spring = 'spring';
const springCache = {};

/** Damped spring sampled into a CSS linear() easing; bounce 0 to 1 like motion's spring (falls back to an overshoot). */
function springEasing(bounce) {
  if (springCache[bounce]) return springCache[bounce];
  const zeta = Math.max(0.05, 1 - bounce);
  const w = 12;
  const wd = w * Math.sqrt(Math.max(0.0001, 1 - zeta * zeta));
  const pts = [];
  for (let i = 0; i <= 40; i++) {
    const t = i / 40;
    pts.push((1 - Math.exp(-zeta * w * t) * (Math.cos(wd * t) + ((zeta * w) / wd) * Math.sin(wd * t))).toFixed(3));
  }
  pts[40] = '1';
  const linear = `linear(${pts.join(',')})`;
  springCache[bounce] = window.CSS && CSS.supports && CSS.supports('animation-timing-function', linear) ? linear : 'cubic-bezier(.34,1.56,.64,1)';
  return springCache[bounce];
}

/** animate( el, { prop: [from, to] }, { duration, delay, ease, type, bounce } ): Promise, motion's call shape. */
function animate(el, keyframes, opts = {}) {
  if (!el || typeof el.animate !== 'function') return Promise.resolve();
  let easing = 'ease';
  if (opts.type === spring) easing = springEasing(opts.bounce ?? 0.3);
  else if (Array.isArray(opts.ease)) easing = `cubic-bezier(${opts.ease.join(',')})`;
  else if (typeof opts.ease === 'string') easing = opts.ease;
  const a = el.animate(keyframes, { duration: (opts.duration ?? 0.3) * 1000, delay: (opts.delay ?? 0) * 1000, easing, fill: 'backwards' });
  return a.finished;
}

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

function svg(attrs, ...children) {
  const el = document.createElementNS(NS, 'svg');
  for (const [k, v] of Object.entries({ 'aria-hidden': 'true', focusable: 'false', ...attrs })) el.setAttribute(k, String(v));
  for (const c of children) el.appendChild(c);
  return el;
}

function path(d, cls) {
  const p = document.createElementNS(NS, 'path');
  p.setAttribute('d', d);
  if (cls) p.setAttribute('class', cls);
  return p;
}

function icon(name) {
  return svg({ class: `hrsw-icon hrsw-icon--${name}`, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2, 'stroke-linecap': 'round', 'stroke-linejoin': 'round' }, path(ICONS[name]));
}

function flame(cls = '') {
  return svg({ class: `hrs-flame hrsw-flame ${cls}`.trim(), viewBox: '0 0 64 64' }, path(FLAME[0], 'hrs-flame__outer'), path(FLAME[1], 'hrs-flame__inner'));
}

function ring() {
  const circle = (cls) => {
    const c = document.createElementNS(NS, 'circle');
    for (const [k, v] of Object.entries({ class: cls, cx: 26, cy: 26, r: RING_R })) c.setAttribute(k, String(v));
    return c;
  };
  const bar = circle('hrsw-ring__bar');
  bar.style.strokeDasharray = `${RING_C}`;
  bar.style.strokeDashoffset = `${RING_C}`;
  return { el: svg({ class: 'hrsw-ring__svg', viewBox: '0 0 52 52' }, circle('hrsw-ring__track'), bar), bar };
}

function config() {
  try {
    return JSON.parse(document.getElementById('hr-streak-widget-config')?.textContent || '{}') || {};
  } catch {
    return {};
  }
}

/** Only same-origin http(s) URLs are used as link targets. */
function safeUrl(value) {
  if (typeof value !== 'string' || !value) return null;
  try {
    const u = new URL(value, location.href);
    return u.origin === location.origin && /^https?:$/.test(u.protocol) ? u : null;
  } catch {
    return null;
  }
}

const store = {
  get(key) {
    try {
      return window.sessionStorage.getItem(key);
    } catch {
      return null;
    }
  },
  set(key, value) {
    try {
      window.sessionStorage.setItem(key, value);
    } catch {
      // Private mode or blocked storage: the widget still works, it just forgets between pages.
    }
  },
};

function loadCss(href) {
  return new Promise((resolve) => {
    if (!href) return resolve(false);
    const link = h('link', { rel: 'stylesheet', href: href.href });
    link.onload = () => resolve(true);
    link.onerror = () => resolve(false);
    document.head.appendChild(link);
    setTimeout(() => resolve(link.sheet !== null), 4000);
  });
}

const plural = (n, one, many) => (n === 1 ? one : many).replace('%d', String(n));
const num = (v, max = 100000) => Math.max(0, Math.min(max, Math.floor(Number(v) || 0)));

function view(s, ui) {
  const goal = Math.max(1, num(s.goal, 100));
  return {
    current: num(s.current),
    best: num(s.best),
    today: num(s.today, 1000),
    goal,
    met: s.met_today === true,
    risk: s.at_risk === true,
    freezes: num(s.freezes, 10),
    maxFreezes: Math.max(1, num(ui && ui.max_freezes, 10) || 2),
  };
}

function mount(cfg, data) {
  const reader = !!(data && data.logged_in === true && data.streak && typeof data.streak === 'object');
  const ui = (data && data.streak_ui) || {};
  const dashboard = safeUrl(cfg.dashboard);
  const onDashboard = !!dashboard && dashboard.pathname.replace(/\/+$/, '') === location.pathname.replace(/\/+$/, '');
  const key = reader ? 'hr_sw_reader' : 'hr_sw_guest';
  const saved = store.get(key);
  // Forms and the streaks page itself: start as the pill so the card never sits over fields on small screens.
  const quiet = onDashboard || /^\/account(\/|$)/.test(location.pathname);
  let open = !quiet && (reader ? saved === null || saved === 'open' : saved !== 'min');
  if (reader && saved === null && !quiet) store.set(key, 'min'); // only the first page of a session opens by itself

  const root = h('aside', { class: `hrsw ${reader ? 'is-reader' : 'is-guest'}`, 'aria-label': 'Reading streak', 'data-open': String(open) });
  const live = h('p', { class: 'hrsw-sr', 'aria-live': 'polite', 'aria-atomic': 'true' });
  const cardId = 'hrsw-card';
  const card = h('div', { class: 'hrsw-card', id: cardId, tabindex: '-1' });
  const minimise = h('button', { type: 'button', class: 'hrsw-min', 'aria-expanded': 'true', 'aria-controls': cardId, 'aria-label': 'Minimise reading streak' }, icon('minimise'));
  const pill = h('button', { type: 'button', class: 'hrsw-pill', 'aria-expanded': 'false', 'aria-controls': cardId });
  const pillFlame = flame();
  const pillText = h('span', { class: 'hrsw-pill__text', 'aria-hidden': 'true' });
  const pillNote = h('span', { class: 'hrsw-pill__note', 'aria-hidden': 'true' });
  const pillLabel = h('span', { class: 'hrsw-sr' });
  const embers = h('span', { class: 'hrsw-embers', 'aria-hidden': 'true' });
  pill.append(h('span', { class: 'hrsw-pill__art' }, pillFlame, embers), pillText, pillNote, pillLabel);

  const art = h('span', { class: 'hrsw-art', 'aria-hidden': 'true' }, flame('hrsw-art__flame'), h('span', { class: 'hrsw-embers', 'aria-hidden': 'true' }));
  const note = h('p', { class: 'hrsw-note', hidden: true });
  let render = () => {};

  if (!reader) {
    const register = safeUrl(cfg.register);
    const login = safeUrl(cfg.login);
    // Come back to this page after signing in or confirming a new account (the server validates redirect_to too).
    const here = location.href.split('#')[0];
    if (!onDashboard) for (const u of [login, register]) if (u) u.searchParams.set('redirect_to', here);
    const goal = Math.max(1, num(cfg.goal, 100)) || 3;
    card.append(
      h('div', { class: 'hrsw-head' }, art, minimise),
      h('p', { class: 'hrsw-eyebrow', text: 'Reading streak' }),
      h('h2', { class: 'hrsw-title', id: 'hrsw-title', text: 'Start your reading streak' }),
      h('p', { class: 'hrsw-text', text: `Sign in or create an account to start your streak. Finish ${plural(goal, '%d article', '%d articles')} a day to keep it going.` }),
      h('div', { class: 'hrsw-actions' },
        register ? h('a', { class: 'hrsw-btn hrsw-btn--primary', href: register.href, text: 'Create free account' }) : null,
        login ? h('a', { class: 'hrsw-btn hrsw-btn--ghost', href: login.href, text: 'Sign in' }) : null),
    );
    card.setAttribute('aria-labelledby', 'hrsw-title');
    pillText.textContent = 'Start a streak';
    pillLabel.textContent = 'Reading streak: start your streak. Show details';
  } else {
    const count = h('span', { class: 'hrsw-count__num' });
    const unit = h('span', { class: 'hrsw-count__unit', text: 'day streak' });
    const { el: ringSvg, bar } = ring();
    const ringNum = h('span', { class: 'hrsw-ring__num' });
    const progress = h('p', { class: 'hrsw-progress__main' });
    const hint = h('p', { class: 'hrsw-progress__hint' });
    const flakes = h('span', { class: 'hrsw-freezes__icons', 'aria-hidden': 'true' });
    const freezeText = h('span', { class: 'hrsw-freezes__text' });
    const link = dashboard ? h('a', { class: 'hrsw-link', href: dashboard.href }, h('span', { text: ui.tour === true ? 'See how streaks work' : 'Open your streaks' }), icon('arrow')) : null;
    card.append(
      h('div', { class: 'hrsw-head' }, art, h('p', { class: 'hrsw-count', id: 'hrsw-title' }, count, unit), minimise),
      h('div', { class: 'hrsw-progress' }, h('span', { class: 'hrsw-ring', 'aria-hidden': 'true' }, ringSvg, ringNum), h('div', {}, progress, hint)),
      note,
      h('div', { class: 'hrsw-foot' }, h('p', { class: 'hrsw-freezes' }, flakes, freezeText), link),
    );
    card.setAttribute('aria-labelledby', 'hrsw-title');

    let last = null;
    render = (raw, announce) => {
      const v = view(raw, ui);
      const lit = v.met ? 'is-lit' : v.current > 0 ? 'is-warm' : '';
      for (const f of [pillFlame, art.firstChild]) f.setAttribute('class', `hrs-flame hrsw-flame ${f === art.firstChild ? 'hrsw-art__flame ' : ''}${lit}`.trim());
      root.dataset.state = v.met ? 'met' : v.risk ? 'risk' : 'pending';
      count.textContent = String(v.current);
      const done = Math.min(v.today, v.goal);
      bar.style.strokeDashoffset = String(RING_C * (1 - done / v.goal));
      ringNum.replaceChildren(v.met ? icon('check') : document.createTextNode(`${done}/${v.goal}`));
      progress.textContent = v.met ? "Today's goal is met" : `${done} of ${plural(v.goal, '%d article', '%d articles')} today`;
      const left = v.goal - done;
      if (v.met) hint.textContent = v.current > 1 ? 'Your streak is safe. See you tomorrow.' : 'Your streak has started. See you tomorrow.';
      else if (v.risk) hint.textContent = `Read ${plural(left, '%d more article', '%d more articles')} before midnight to keep your streak.`;
      else hint.textContent = `${done ? `Read ${plural(left, '%d more article', '%d more articles')}` : `Finish ${plural(left, '%d article', '%d articles')} today`} to ${v.best > 0 ? 'start a new streak' : 'start your streak'}.`;
      flakes.replaceChildren(...Array.from({ length: v.maxFreezes }, (_, i) => {
        const f = icon('snowflake');
        if (i < v.freezes) f.classList.add('is-on');
        return f;
      }));
      freezeText.textContent = `${v.freezes} of ${v.maxFreezes} freezes`;
      pillText.textContent = String(v.current);
      pillNote.textContent = v.met ? '' : `${done}/${v.goal}`;
      const summary = `${v.current}-day streak, ${v.met ? "today's goal met" : `${done} of ${v.goal} articles today`}`;
      pillLabel.textContent = `Reading streak: ${summary}. Show details`;
      if (announce && last) {
        live.textContent = v.met && !last.met ? `Daily goal met. ${summary}.` : `Article counted. ${summary}.`;
        if (!reduceMotion() && v.current !== last.current) animate(count, { transform: ['translateY(10px)', 'none'], opacity: [0, 1] }, { duration: 0.3, ease: [0.16, 1, 0.3, 1] });
      }
      const goalJustMet = !!(last && v.met && !last.met);
      last = v;
      return goalJustMet;
    };
  }

  root.append(card, pill, live);

  function setOpen(next, { focus = false, remember = true, motion = true } = {}) {
    open = next;
    root.dataset.open = String(open);
    card.hidden = !open;
    pill.hidden = open;
    pill.setAttribute('aria-expanded', String(open));
    minimise.setAttribute('aria-expanded', String(open));
    if (remember) store.set(key, open ? 'open' : 'min');
    if (open && motion && !reduceMotion()) animate(card, { opacity: [0, 1], transform: ['translateY(12px) scale(.97)', 'none'] }, { duration: 0.32, ease: [0.16, 1, 0.3, 1] });
    if (!open && motion && !reduceMotion()) animate(pill, { opacity: [0, 1], transform: ['scale(.85)', 'none'] }, { type: spring, bounce: 0.4, duration: 0.45 });
    if (focus) (open ? card : pill).focus({ preventScroll: true });
  }

  pill.addEventListener('click', () => setOpen(true, { focus: true }));
  minimise.addEventListener('click', () => setOpen(false, { focus: true }));
  root.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && open && card.contains(document.activeElement)) {
      e.preventDefault();
      setOpen(false, { focus: true });
    }
  });

  // Stay clear of the theme's sticky mobile bar and the cookie banner (measured, so any height works).
  const bar = document.getElementById('hr-mobile-cta');
  const cc = document.getElementById('hr-cc');
  const shown = (el) => {
    if (!el) return 0;
    const st = getComputedStyle(el);
    if (st.display === 'none' || st.visibility === 'hidden') return 0;
    const r = el.getBoundingClientRect();
    return r.height > 0 && r.top < window.innerHeight ? Math.round(window.innerHeight - r.top) : 0;
  };
  let queued = false;
  const place = () => {
    queued = false;
    const ccLift = shown(cc);
    root.classList.toggle('is-under-banner', ccLift > 0);
    root.style.setProperty('--hrsw-cc', `${ccLift}px`);
    root.style.setProperty('--hrsw-cta', `${bar && bar.classList.contains('is-visible') ? shown(bar) : 0}px`);
  };
  const queue = () => {
    if (!queued) {
      queued = true;
      requestAnimationFrame(place);
    }
  };
  const mo = new MutationObserver(queue);
  for (const el of [bar, cc, document.documentElement]) if (el) mo.observe(el, { attributes: true, attributeFilter: ['class', 'hidden', 'style'] });
  if (cc && 'ResizeObserver' in window) new ResizeObserver(queue).observe(cc);
  if (cc) cc.addEventListener('transitionend', queue);
  window.addEventListener('resize', queue, { passive: true });

  setOpen(open, { remember: false, motion: false });
  render(data && data.streak, false);
  document.body.appendChild(root);
  place();

  // ---- celebration and notes
  const burst = (host) => {
    for (let i = 0; i < 8; i++) {
      const dot = host.appendChild(h('i'));
      const angle = (-90 + (i - 3.5) * 24) * (Math.PI / 180);
      const dist = 26 + (i % 3) * 10;
      animate(dot, { transform: ['translate(0,0) scale(1)', `translate(${Math.cos(angle) * dist}px,${Math.sin(angle) * dist}px) scale(.3)`], opacity: [1, 0] }, { duration: 0.8, delay: 0.1 + i * 0.02, ease: 'ease-out' })
        .then(() => dot.remove(), () => dot.remove());
    }
  };
  const celebrate = () => {
    root.classList.add('is-celebrating');
    setTimeout(() => root.classList.remove('is-celebrating'), 2400);
    if (reduceMotion()) return;
    const target = open ? art : pill.querySelector('.hrsw-pill__art');
    animate(target.querySelector('svg'), { transform: ['scale(.4) rotate(-14deg)', 'scale(1) rotate(0deg)'] }, { type: spring, bounce: 0.55, duration: 0.8 });
    burst(target.querySelector('.hrsw-embers'));
  };
  // streak.js may have a modal open for the same moment; celebrate once it has closed so the reader sees it.
  const whenClear = (fn, tries = 0) => (document.querySelector('dialog[open]') && tries < 200 ? setTimeout(() => whenClear(fn, tries + 1), 300) : fn());

  let noteTimer;
  let notes = [];
  // Several notes in a row (the read, then a new badge) stack in the card; the pill shows the latest.
  const notify = (text) => {
    const t = String(text || '').slice(0, 120);
    if (!t) return;
    live.textContent = t;
    clearTimeout(noteTimer);
    notes = [...notes, t].slice(-3);
    note.replaceChildren(...notes.map((n) => h('span', { text: n })));
    note.hidden = !open;
    pill.classList.toggle('has-note', !open);
    if (!open) pillNote.textContent = t;
    noteTimer = setTimeout(() => {
      notes = [];
      note.hidden = true;
      pill.classList.remove('has-note');
      render(lastDetail || (data && data.streak), false);
    }, 6000);
  };

  let lastDetail = null;
  if (reader) {
    document.addEventListener('hr:streak', (e) => {
      if (!e.detail || typeof e.detail !== 'object') return;
      lastDetail = e.detail;
      if (render(e.detail, true)) whenClear(celebrate);
    });
  }

  window.HR.streakWidget = {
    visible: () => root.isConnected && getComputedStyle(root).display !== 'none',
    notify,
  };
}

async function start() {
  try {
    if (window.top !== window.self) return;
  } catch {
    return;
  }
  const cfg = config();
  const HR = (window.HR = window.HR || {});
  const session = HR.session && typeof HR.session.then === 'function' ? HR.session : Promise.resolve(null);
  const [data, cssOk] = await Promise.all([session.catch(() => null), loadCss(safeUrl(cfg.css))]);
  if (!cssOk || document.querySelector('.hrsw')) return;
  mount(cfg, data);
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true });
else start();
