// Header account chip (site-wide, deferred). Pages are cached for everyone, so the server always prints the
// guest links; when the non-secret hr_li hint cookie is present this asks admin-ajax.php?action=hr_session who is
// signed in and swaps in the account button + menu (with the reading streak when the streaks module adds one).
//
// Contract for other scripts:
//   window.HR.session            Promise resolving to the session payload, or null when signed out
//   document 'hr:session' event  detail = payload (or null), fired once
//   document 'hr:streak' event   dispatch it with detail = new streak object to update the chip live
// The REST nonce lives only in this in-memory payload; it is never written to storage.

const HAS_HINT = /(?:^|;\s*)hr_li=1(?:;|$)/;
const NS = 'http://www.w3.org/2000/svg';
const ICONS = {
  flame: [['path', { d: 'M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z' }]],
  gauge: [['path', { d: 'm12 14 4-4' }], ['path', { d: 'M3.34 19a10 10 0 1 1 17.32 0' }]],
  user: [['path', { d: 'M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2' }], ['circle', { cx: 12, cy: 7, r: 4 }]],
  pen: [['path', { d: 'M12 20h9' }], ['path', { d: 'M16.376 3.622a1 1 0 0 1 3.002 3.002L7.368 18.635a2 2 0 0 1-.855.506l-2.872.838a.5.5 0 0 1-.62-.62l.838-2.872a2 2 0 0 1 .506-.854z' }]],
  sliders: [['path', { d: 'M21 4h-7M10 4H3M21 12h-9M8 12H3M21 20h-5M12 20H3M14 2v4M8 10v4M16 18v4' }]],
  logout: [['path', { d: 'M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4' }], ['polyline', { points: '16 17 21 12 16 7' }], ['line', { x1: 21, x2: 9, y1: 12, y2: 12 }]],
  chevron: [['path', { d: 'm9 18 6-6-6-6' }]],
};

const reduceMotion = () => window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function icon(name) {
  const svg = document.createElementNS(NS, 'svg');
  for (const [k, v] of Object.entries({ viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2, 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'aria-hidden': 'true', focusable: 'false' })) svg.setAttribute(k, String(v));
  for (const [tag, attrs] of ICONS[name] || []) {
    const el = document.createElementNS(NS, tag);
    for (const [k, v] of Object.entries(attrs)) el.setAttribute(k, String(v));
    svg.appendChild(el);
  }
  return svg;
}

function h(tag, props = {}, ...children) {
  const el = document.createElement(tag);
  for (const [k, v] of Object.entries(props)) {
    if (v === null || v === undefined || v === false) continue;
    if (k === 'class') el.className = v;
    else if (k === 'text') el.textContent = v;
    else el.setAttribute(k, v === true ? '' : String(v));
  }
  for (const c of children) if (c) el.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
  return el;
}

function config() {
  try {
    return JSON.parse(document.getElementById('hr-account-chip-config')?.textContent || '{}');
  } catch {
    return {};
  }
}

/** Only same-origin http(s) URLs from the payload are used as link targets. */
function safeUrl(value) {
  try {
    const u = new URL(String(value), location.href);
    return u.origin === location.origin && /^https?:$/.test(u.protocol) ? u.href : null;
  } catch {
    return null;
  }
}

async function fetchSession() {
  if (!HAS_HINT.test(document.cookie)) return null;
  const endpoint = safeUrl(config().session);
  if (!endpoint) return null;
  try {
    const res = await fetch(endpoint, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } });
    if (!res.ok) return null;
    const data = await res.json();
    if (data && data.logged_in === true) return data;
    // The hint outlived the session (expired or signed out elsewhere): stop asking on every page.
    document.cookie = 'hr_li=; Max-Age=0; path=/; SameSite=Lax';
    return null;
  } catch {
    return null;
  }
}

function streakState(s) {
  if (s.met_today) return 'met';
  return s.at_risk ? 'risk' : 'pending';
}

function streakLabel(s) {
  const n = Number(s.current) || 0;
  const days = `${n}-day reading streak`;
  if (s.met_today) return `${days}, today's goal met`;
  const goal = Number(s.goal) || 0;
  const today = Number(s.today) || 0;
  return goal ? `${days}, ${today} of ${goal} articles read today` : days;
}

function mountChip(slot, data) {
  const user = data.user || {};
  const links = data.links || {};
  const name = String(user.name || 'Your account').slice(0, 80);
  const initials = String(user.initials || 'HR').slice(0, 3);
  const menuId = 'hr-acct-menu';

  const btn = h('button', { type: 'button', class: 'hr-acct__btn', 'aria-expanded': 'false', 'aria-controls': menuId });
  btn.appendChild(h('span', { class: 'hr-acct__avatar', 'aria-hidden': 'true', text: initials }));
  const streakEl = h('span', { class: 'hr-acct__streak', hidden: true, 'aria-hidden': 'true' }, icon('flame'), h('span', { class: 'hr-acct__num' }));
  btn.appendChild(streakEl);
  btn.appendChild(h('span', { class: 'hr-acct__chev', 'aria-hidden': 'true' }, icon('chevron')));
  const btnLabel = h('span', { class: 'hrp-sr' });
  btnLabel.style.cssText = 'position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap';
  btn.appendChild(btnLabel);

  // The theme's navigation clips overflow, so the menu lives on <body> and is positioned next to the button.
  const menu = h('div', { class: 'hr-acct-menu', id: menuId, hidden: true });
  const head = h('div', { class: 'hr-acct-menu__head' },
    h('span', { class: 'hr-acct-menu__avatar', 'aria-hidden': 'true', text: initials }),
    h('div', {}, h('p', { class: 'hr-acct-menu__name', text: name }), h('p', { class: 'hr-acct-menu__meta' })));
  const meta = head.querySelector('.hr-acct-menu__meta');
  const account = safeUrl(links.account);
  if (user.verified === false && account) meta.appendChild(h('a', { href: account, text: 'Confirm your email' }));
  else meta.textContent = 'Signed in';
  menu.appendChild(head);

  const streakBox = h('div', { class: 'hr-acct-menu__streak', hidden: true });
  menu.appendChild(streakBox);

  const list = h('ul', { role: 'list' });
  for (const [key, label, ico] of [['dashboard', 'Dashboard', 'gauge'], ['account', 'Account', 'user'], ['submit', 'Submit an article', 'pen'], ['settings', 'Settings', 'sliders']]) {
    const url = safeUrl(links[key]);
    if (url) list.appendChild(h('li', {}, h('a', { href: url }, icon(ico), label)));
  }
  menu.appendChild(list);

  const logout = safeUrl(links.logout);
  if (logout && data.logout_nonce) {
    menu.appendChild(h('form', { method: 'post', action: logout },
      h('input', { type: 'hidden', name: 'hr_action', value: 'logout' }),
      h('input', { type: 'hidden', name: '_hrnonce', value: String(data.logout_nonce) }),
      h('button', { type: 'submit' }, icon('logout'), 'Sign out')));
  }

  function renderStreak(s, animateIt) {
    if (!s || typeof s !== 'object') {
      btnLabel.textContent = `Account menu for ${name}`;
      return;
    }
    const n = Math.max(0, Number(s.current) || 0);
    streakEl.hidden = false;
    streakEl.dataset.state = streakState(s);
    streakEl.querySelector('.hr-acct__num').textContent = String(n);
    btnLabel.textContent = `Account menu for ${name}. ${streakLabel(s)}.`;
    btn.title = streakLabel(s);

    streakBox.hidden = false;
    streakBox.replaceChildren();
    const goal = Math.max(0, Number(s.goal) || 0);
    const today = Math.max(0, Number(s.today) || 0);
    streakBox.appendChild(h('p', {}, h('span', {}, h('strong', { text: `${n}-day streak` })), h('span', { text: `Best ${Math.max(n, Number(s.best) || 0)}` })));
    if (goal) {
      const status = s.met_today ? "Today's goal met" : `${Math.min(today, goal)} of ${goal} today`;
      const freezes = Number(s.freezes) || 0;
      streakBox.appendChild(h('p', { style: 'margin-top:4px' }, h('span', { text: status }), freezes ? h('span', { text: `${freezes} freeze${freezes === 1 ? '' : 's'}` }) : null));
      const fill = h('span');
      fill.style.transform = `scaleX(${Math.min(1, today / goal)})`;
      streakBox.appendChild(h('div', { class: 'hr-acct-menu__bar', role: 'presentation' }, fill));
    }
    if (animateIt && !reduceMotion() && streakEl.animate) {
      // Small spring-like pop (overshooting easing) when the streak changes; transform/opacity only.
      streakEl.querySelector('svg').animate([{ transform: 'scale(1)' }, { transform: 'scale(1.45) rotate(-8deg)' }, { transform: 'scale(1)' }], { duration: 300, easing: 'cubic-bezier(.34,1.56,.64,1)' });
      streakEl.querySelector('.hr-acct__num').animate([{ transform: 'translateY(6px)', opacity: 0 }, { transform: 'none', opacity: 1 }], { duration: 240, easing: 'cubic-bezier(.16,1,.3,1)' });
    }
  }

  function place() {
    const r = btn.getBoundingClientRect();
    const width = Math.min(288, window.innerWidth - 24);
    const left = Math.max(12, Math.min(r.right - width, window.innerWidth - width - 12));
    menu.style.left = `${left}px`;
    menu.style.top = `${Math.round(r.bottom + 8)}px`;
  }

  function open() {
    place();
    menu.hidden = false;
    btn.setAttribute('aria-expanded', 'true');
    if (!reduceMotion() && menu.animate) menu.animate([{ opacity: 0, transform: 'translateY(-6px)' }, { opacity: 1, transform: 'none' }], { duration: 180, easing: 'cubic-bezier(.16,1,.3,1)' });
    menu.querySelector('a, button')?.focus();
    document.addEventListener('pointerdown', onOutside, true);
    window.addEventListener('resize', close);
    window.addEventListener('scroll', place, { passive: true });
  }

  function close(returnFocus = false) {
    if (menu.hidden) return;
    menu.hidden = true;
    btn.setAttribute('aria-expanded', 'false');
    document.removeEventListener('pointerdown', onOutside, true);
    window.removeEventListener('resize', close);
    window.removeEventListener('scroll', place);
    if (returnFocus === true) btn.focus();
  }

  function onOutside(e) {
    if (!menu.contains(e.target) && !btn.contains(e.target)) close();
  }

  btn.addEventListener('click', () => (menu.hidden ? open() : close(true)));
  menu.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      e.preventDefault();
      close(true);
      return;
    }
    if (e.key !== 'Tab') return;
    // The menu sits at the end of <body>; keep keyboard order as if it followed the button.
    const items = [...menu.querySelectorAll('a, button')];
    if (e.shiftKey && document.activeElement === items[0]) {
      e.preventDefault();
      close(true);
    } else if (!e.shiftKey && document.activeElement === items[items.length - 1]) {
      close(true);
    }
  });
  btn.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') close(true);
  });

  renderStreak(data.streak, false);
  document.addEventListener('hr:streak', (e) => renderStreak(e.detail, true));

  const wrap = h('div', { class: 'hr-acct' }, btn);
  slot.replaceChildren(wrap);
  document.body.appendChild(menu);
}

const HR = (window.HR = window.HR || {});
let settle;
HR.session = new Promise((resolve) => (settle = resolve));

(async () => {
  const data = await fetchSession();
  if (data) {
    document.querySelectorAll('[data-hr-account]').forEach((slot, i) => {
      if (i === 0) mountChip(slot, data);
    });
  }
  document.documentElement.classList.remove('hr-li');
  settle(data);
  document.dispatchEvent(new CustomEvent('hr:session', { detail: data }));
})();
