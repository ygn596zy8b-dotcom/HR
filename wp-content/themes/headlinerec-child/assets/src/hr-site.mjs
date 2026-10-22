// Site-wide behaviour of the Headline Recorder child theme (MSBJ): masthead wordmark + tagline, desktop search
// panel, hamburger icon, signed-in CTA swap and the sticky mobile CTA. Built to assets/js/hr-site.js (deferred).
// No HTML strings: every node is built with createElement/textContent or cloned from server-rendered <template>s.

const DESKTOP = window.matchMedia('(min-width: 769px)');
const SVG = 'http://www.w3.org/2000/svg';

function el(tag, attrs, text) {
  const node = document.createElement(tag);
  for (const [k, v] of Object.entries(attrs || {})) node.setAttribute(k, v);
  if (text) node.textContent = text;
  return node;
}

// Desktop masthead: tagline on the right and the site title split into a two-line wordmark.
function masthead() {
  const header = document.querySelector('.site-header') || document.querySelector('.wrapper-header');
  if (!header) return;
  let stacked = false;
  const sync = () => {
    const tagline = document.getElementById('hr-premium-tagline');
    if (!DESKTOP.matches) {
      tagline?.remove();
      return;
    }
    if (tagline) return;
    header.style.position = 'relative';
    const box = el('div', { id: 'hr-premium-tagline' });
    box.append(el('div', { id: 'hr-tag-top' }, 'The Intelligence Layer'), el('div', { id: 'hr-tag-bottom' }, "Behind Pakistan's Business Conversations."));
    header.append(box);
    header.querySelectorAll('canvas').forEach((c) => c.remove());
    if (stacked) return;
    stacked = true;
    const title = document.querySelector('.site-title a') || document.querySelector('.site-title');
    const words = title ? title.textContent.trim().split(' ') : [];
    if (words.length >= 2) {
      title.replaceChildren(el('span', { class: 'hr-word-top' }, words[0]), el('span', { class: 'hr-word-bottom' }, words.slice(1).join(' ')));
      title.classList.add('hr-stacked-wordmark');
    }
  };
  sync();
  DESKTOP.addEventListener('change', sync);
}

// Desktop search: trigger + panel come from <template id="hr-nav-tools"> (footer) and are appended to the nav bar.
function navSearch() {
  const tpl = document.getElementById('hr-nav-tools');
  const nav = document.querySelector('.main-navigation') || document.querySelector('#site-navigation') || document.querySelector('nav.navbar');
  if (!tpl || !nav || document.getElementById('hr-action-group')) return;
  nav.append(tpl.content.cloneNode(true));
  const trigger = document.getElementById('hr-search-trigger');
  const panel = document.getElementById('hr-navbar-search');
  const input = document.getElementById('hr-inline-input');
  const close = panel.querySelector('.hr-inline-search-close');
  const setOpen = (open) => {
    panel.classList.toggle('active', open);
    panel.inert = !open;
    trigger.setAttribute('aria-expanded', String(open));
    if (open) {
      input.focus({ preventScroll: true });
    } else {
      input.value = '';
      trigger.focus({ preventScroll: true });
    }
  };
  trigger.addEventListener('click', () => setOpen(true));
  close.addEventListener('click', () => setOpen(false));
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && panel.classList.contains('active')) setOpen(false);
  });
}

// Mobile menu toggle: an SVG icon inside the parent theme's <a role="button"> (no nested controls), state in aria-expanded.
function hamburger() {
  const btn = document.querySelector('.toggle-menu .aft-void-menu') || document.querySelector('.toggle-menu');
  if (!btn) return;
  const svg = document.createElementNS(SVG, 'svg');
  svg.setAttribute('viewBox', '0 0 32 32');
  svg.setAttribute('aria-hidden', 'true');
  svg.setAttribute('focusable', 'false');
  for (const [d, cls] of [
    ['M27 10 13 10C10.8 10 9 8.2 9 6 9 3.5 10.8 2 13 2 15.2 2 17 3.8 17 6L17 26C17 28.2 18.8 30 21 30 23.2 30 25 28.2 25 26 25 23.8 23.2 22 21 22L7 22', 'line line-top-bottom'],
    ['M7 16 27 16', 'line'],
  ]) {
    const path = document.createElementNS(SVG, 'path');
    path.setAttribute('d', d);
    path.setAttribute('class', cls);
    svg.append(path);
  }
  const icon = el('span', { class: 'hamburger' });
  icon.append(svg);
  btn.replaceChildren(icon);
  // The parent theme also puts aria-expanded/aria-controls on its wrapper <span>, where ARIA does not allow them.
  const group = btn.closest('.toggle-menu');
  if (group && group !== btn) ['aria-expanded', 'aria-controls'].forEach((a) => group.removeAttribute(a));
  btn.addEventListener('click', () => {
    const open = btn.getAttribute('aria-expanded') !== 'true';
    btn.classList.toggle('is-open', open);
    btn.setAttribute('aria-expanded', String(open));
  });
  // role="button" on a link: Space must activate it too.
  btn.addEventListener('keydown', (e) => {
    if (e.key === ' ') {
      e.preventDefault();
      btn.click();
    }
  });
}

// Sticky mobile CTA: shown after the lead hero, hidden while scrolling down fast, while typing, or once dismissed.
// While it is on screen, :root --hr-sticky-cta-h holds its height (0px otherwise), so other fixed UI (back to top,
// the reading-streak widget in the bottom-left corner) can sit above it.
function mobileCta() {
  const root = document.documentElement;
  const setHeight = (px) => {
    const v = `${Math.round(px)}px`;
    if (root.style.getPropertyValue('--hr-sticky-cta-h') !== v) root.style.setProperty('--hr-sticky-cta-h', v);
  };
  setHeight(0);
  const bar = document.getElementById('hr-mobile-cta');
  if (!bar) return;
  const KEY = 'hr_mcta_dismissed';
  try {
    if (sessionStorage.getItem(KEY)) return bar.remove();
  } catch (e) { /* storage blocked: the bar stays dismissible for this page view */ }
  // Height only while shown; display:none (desktop, cookie sheet open) measures 0.
  const sync = () => setHeight(bar.classList.contains('is-visible') ? bar.getBoundingClientRect().height : 0);
  const hero = document.querySelector('.sbp-premium-hero-dark');
  const threshold = () => (hero ? hero.getBoundingClientRect().bottom + window.scrollY : window.innerHeight);
  let lastY = window.scrollY;
  let suppressed = false; // set by a fast scroll down, cleared by any scroll up
  let typing = false;
  let queued = false;
  const update = () => {
    queued = false;
    const y = window.scrollY;
    const dy = y - lastY;
    lastY = y;
    const vv = window.visualViewport;
    const keyboard = typing || (vv && vv.height < window.innerHeight * 0.75);
    if (dy > 40) suppressed = true;
    else if (dy < 0) suppressed = false;
    bar.classList.toggle('is-visible', !keyboard && !suppressed && y >= threshold());
    sync();
  };
  const queue = () => {
    if (!queued) {
      queued = true;
      requestAnimationFrame(update);
    }
  };
  window.addEventListener('scroll', queue, { passive: true });
  window.addEventListener('resize', queue, { passive: true });
  window.visualViewport?.addEventListener('resize', queue);
  if ('ResizeObserver' in window) new ResizeObserver(sync).observe(bar);
  // The bar is hidden by CSS while the cookie sheet is open or consent is pending (classes on <html>).
  new MutationObserver(queue).observe(root, { attributes: true, attributeFilter: ['class'] });
  const isField = (t) => t instanceof Element && t.matches('input:not([type=button]):not([type=submit]):not([type=checkbox]):not([type=radio]), textarea, select, [contenteditable]');
  document.addEventListener('focusin', (e) => {
    if (isField(e.target)) {
      typing = true;
      queue();
    }
  });
  document.addEventListener('focusout', (e) => {
    if (isField(e.target)) {
      typing = false;
      queue();
    }
  });
  bar.querySelector('.hr-mobile-cta-close')?.addEventListener('click', () => {
    try {
      sessionStorage.setItem(KEY, '1');
    } catch (e) { /* ignore */ }
    bar.remove();
    setHeight(0);
  });
  update();
}

// Signed-in hint (the platform plugin's non-secret hr_li cookie): CSS swaps "Join free" for "My reading streak".
// The plugin's own html.hr-li class only lasts until its session check; this one stays unless that check says signed out.
if (/(?:^|;\s*)hr_li=1(?:;|$)/.test(document.cookie)) {
  document.documentElement.classList.add('hr-signed-in');
  window.HR?.session?.then((me) => me || document.documentElement.classList.remove('hr-signed-in'));
}

masthead();
navSearch();
hamburger();
mobileCta();
