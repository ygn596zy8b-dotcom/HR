// Cookie banner + Google Consent Mode v2 updates. Markup: templates/site/consent-banner.php; early defaults are printed
// inline in <head> by includes/site/consent.php (window.hrConsentBoot).
//
// Public API for other scripts:
//   window.hrConsent.get()        -> { analytics, embeds, decided, gpc }
//   window.hrConsent.open()       -> show the banner with the choices panel open
//   document 'hr:consent' event   -> detail { analytics, embeds, decided, gpc, initial }
// Elements marked data-hr-consent="embeds" or "analytics" are switched on/off to match:
//   <iframe data-hr-consent="embeds" src|data-hr-src=…>, <script type="text/plain" data-hr-consent="…" data-hr-src=…>

const COOKIE = 'hr_consent';
const MAX_AGE = 180 * 24 * 60 * 60;
const boot = window.hrConsentBoot || {};
const gpc = boot.gpc === true || navigator.globalPrivacyControl === true;

function readChoice() {
  try {
    const m = document.cookie.match(/(?:^|;\s*)hr_consent=([^;]+)/);
    if (!m) return null;
    const c = JSON.parse(decodeURIComponent(m[1]));
    return c && c.v === 1 ? c : null;
  } catch {
    return null;
  }
}

function state(choice = readChoice()) {
  if (choice) return { analytics: choice.analytics === true && !gpc, embeds: choice.embeds === true, decided: true, gpc };
  // No choice yet: analytics wait for permission; embedded content stays on unless the browser asks for privacy (GPC).
  return { analytics: false, embeds: !gpc, decided: gpc, gpc };
}

function writeChoice(analytics, embeds) {
  const value = encodeURIComponent(JSON.stringify({ v: 1, analytics: !!analytics && !gpc, embeds: !!embeds, ts: Date.now() }));
  const secure = location.protocol === 'https:' ? '; Secure' : '';
  document.cookie = `${COOKIE}=${value}; Max-Age=${MAX_AGE}; Path=/; SameSite=Lax${secure}`;
}

// Google Analytics cookies are removed when analytics are switched off again.
function clearAnalyticsCookies() {
  const host = location.hostname;
  const parts = host.split('.');
  const domains = ['', host, `.${host}`];
  if (parts.length > 2) domains.push(`.${parts.slice(-2).join('.')}`);
  document.cookie.split(';').map((c) => c.split('=')[0].trim()).filter((n) => /^_ga(_|$)|^_gid$|^_gat/.test(n)).forEach((name) => {
    domains.forEach((domain) => {
      document.cookie = `${name}=; Max-Age=0; Path=/${domain ? `; Domain=${domain}` : ''}`;
    });
  });
}

function activateScript(node) {
  const src = node.getAttribute('data-hr-src');
  const live = document.createElement('script');
  for (const { name, value } of Array.from(node.attributes)) {
    if (!['type', 'data-hr-src', 'data-hr-consent'].includes(name)) live.setAttribute(name, value);
  }
  if (src) {
    live.src = src;
    live.async = true;
  }
  live.text = node.text; // inline widget config (e.g. TradingView) travels with the script
  node.replaceWith(live);
}

function applyElements(current) {
  document.querySelectorAll('script[type="text/plain"][data-hr-consent]').forEach((node) => {
    const kind = node.getAttribute('data-hr-consent');
    if ((kind === 'analytics' && current.analytics) || (kind === 'embeds' && current.embeds)) activateScript(node);
  });
  document.querySelectorAll('iframe[data-hr-consent="embeds"]').forEach((frame) => {
    const holder = frame.parentElement;
    if (current.embeds) {
      const src = frame.getAttribute('data-hr-src');
      if (src && !frame.getAttribute('src')) frame.setAttribute('src', src);
      frame.hidden = false;
      holder?.querySelector(':scope > .hr-embed-off')?.remove();
      return;
    }
    const src = frame.getAttribute('src');
    if (src && src !== 'about:blank') {
      frame.setAttribute('data-hr-src', src);
      frame.removeAttribute('src');
    }
    frame.hidden = true;
    if (holder && !holder.querySelector(':scope > .hr-embed-off')) holder.insertBefore(placeholder(frame), frame);
  });
}

function placeholder(frame) {
  const box = document.createElement('div');
  box.className = 'hrp hr-embed-off';
  const text = document.createElement('p');
  const host = /tradingview/i.test(frame.getAttribute('data-hr-src') || '') ? 'TradingView' : 'YouTube';
  text.textContent = `This ${host === 'YouTube' ? 'video' : 'widget'} is hosted by ${host}, which may set its own cookies. You switched embedded content off.`;
  const once = document.createElement('button');
  once.type = 'button';
  once.className = 'hr-cc__btn hr-embed-off__load';
  once.textContent = host === 'YouTube' ? 'Play this video' : 'Load this widget';
  once.addEventListener('click', () => {
    frame.setAttribute('src', frame.getAttribute('data-hr-src') || '');
    frame.hidden = false;
    box.remove();
    frame.focus();
  });
  const settings = document.createElement('button');
  settings.type = 'button';
  settings.className = 'hr-embed-off__settings';
  settings.textContent = 'Cookie settings';
  settings.addEventListener('click', () => api.open(settings));
  box.append(text, once, settings);
  return box;
}

function updateGoogle(current) {
  if (typeof window.gtag === 'function') {
    window.gtag('consent', 'update', { analytics_storage: current.analytics ? 'granted' : 'denied' });
  }
}

function announce(current, initial) {
  document.dispatchEvent(new CustomEvent('hr:consent', { detail: { ...current, initial } }));
}

// ---------------------------------------------------------------------------------------------------------------
// Banner
// ---------------------------------------------------------------------------------------------------------------
const root = document.querySelector('[data-hr-cc]');
const panel = root?.querySelector('#hr-cc-panel');
const chooseBtn = root?.querySelector('[data-hr-cc-action="choose"]');
const analyticsBox = root?.querySelector('#hr-cc-analytics');
const embedsBox = root?.querySelector('#hr-cc-embeds');
const status = document.querySelector('[data-hr-cc-status]');
let returnFocus = null;

function syncToggles(current) {
  if (!root) return;
  analyticsBox.checked = current.analytics;
  analyticsBox.disabled = gpc;
  embedsBox.checked = current.embeds;
  const note = root.querySelector('[data-hr-cc-gpc]');
  if (note) note.hidden = !gpc;
}

function showBanner() {
  root.classList.remove('is-closing');
  root.classList.add('is-open');
  document.documentElement.classList.remove('hr-consent-set');
  document.documentElement.classList.add('hr-consent-pending');
}

function hideBanner() {
  const hadFocus = root.contains(document.activeElement);
  const finish = () => {
    root.classList.remove('is-open', 'is-closing');
    document.documentElement.classList.remove('hr-consent-pending');
    document.documentElement.classList.add('hr-consent-set');
    setPanel(false, false);
  };
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) finish();
  else {
    root.classList.add('is-closing');
    window.setTimeout(finish, 200);
  }
  if (hadFocus && returnFocus && document.contains(returnFocus) && !root.contains(returnFocus)) returnFocus.focus();
  returnFocus = null;
}

function setPanel(open, moveFocus = true) {
  if (!panel) return;
  panel.hidden = !open;
  chooseBtn.setAttribute('aria-expanded', String(open));
  root.classList.toggle('has-panel', open);
  if (open) {
    syncToggles(state());
    if (moveFocus) root.querySelector('.hr-cc__legend').focus();
  } else if (moveFocus) {
    chooseBtn.focus();
  }
}

function decide(analytics, embeds, message) {
  const before = state();
  writeChoice(analytics, embeds);
  const current = state();
  updateGoogle(current);
  if (before.analytics && !current.analytics) clearAnalyticsCookies();
  applyElements(current);
  announce(current, false);
  if (status) status.textContent = message;
  hideBanner();
}

const api = {
  get: () => state(),
  open(trigger) {
    if (!root) return;
    returnFocus = trigger || document.activeElement;
    showBanner();
    setPanel(true);
  },
};
window.hrConsent = api;

if (root) {
  root.addEventListener('click', (event) => {
    const action = event.target.closest('[data-hr-cc-action]')?.getAttribute('data-hr-cc-action');
    if (!action) return;
    if (action === 'accept') decide(true, true, 'Cookie choices saved: all accepted.');
    else if (action === 'reject') decide(false, false, 'Cookie choices saved: only necessary cookies.');
    else if (action === 'choose') setPanel(panel.hidden);
    else if (action === 'save') decide(analyticsBox.checked, embedsBox.checked, 'Cookie choices saved.');
    else if (action === 'close') {
      if (readChoice()) hideBanner();
      else setPanel(false);
    }
  });
  root.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    if (!panel.hidden) {
      event.preventDefault();
      if (readChoice()) hideBanner();
      else setPanel(false);
    }
  });
}

document.addEventListener('click', (event) => {
  const trigger = event.target.closest('[data-hr-consent-open]');
  if (!trigger || !root) return;
  event.preventDefault();
  api.open(trigger);
});

// Initial state: stored choice (or GPC) applies silently; otherwise the banner stays up (CSS shows it at first paint).
const initial = state();
if (root) {
  syncToggles(initial);
  if (!readChoice() && !gpc) showBanner();
}
applyElements(initial);
announce(initial, true);
