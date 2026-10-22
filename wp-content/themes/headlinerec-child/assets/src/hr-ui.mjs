// Headline Recorder UI motion and states (MSBJ). CSS does the work where browsers can; this file adds the
// fallbacks and the few things CSS cannot do: reading progress, scroll reveal, figure count-up, TradingView
// placeholders and form busy states. Motion is skipped entirely under prefers-reduced-motion.
import { inView, stagger } from 'motion';

const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
const EASE = 'cubic-bezier(0.16, 1, 0.3, 1)';

// Reading progress on posts: CSS scroll timeline where supported, otherwise a passive scroll listener.
const article = document.body.classList.contains('single-post') && document.querySelector('.hr-article, .esa-article');
if (article && !reduce) {
  const bar = document.createElement('div');
  bar.className = 'hr-progress';
  bar.setAttribute('aria-hidden', 'true');
  document.body.append(bar);
  if (!CSS.supports('timeline-scope', '--hr-read')) {
    let queued = false;
    const update = () => {
      queued = false;
      const r = article.getBoundingClientRect();
      const span = r.height - innerHeight;
      const p = span > 0 ? Math.min(1, Math.max(0, -r.top / span)) : 1;
      bar.style.transform = `scaleX(${p})`;
    };
    addEventListener('scroll', () => { if (!queued) { queued = true; requestAnimationFrame(update); } }, { passive: true });
    update();
  }
}

// Scroll reveal on the home page where CSS scroll-driven animations are missing (Firefox, older Safari).
// Only elements still below the fold are hidden, right before they are observed.
if (!reduce && document.body.classList.contains('home') && !CSS.supports('animation-timeline', 'view()')) {
  const sel = '.sbp-section-header, .sbp-exec-header, .sbp-remaining-item, .sbp-list-item, .sbp-biz-list-item, .sbp-exec-card, .sbp-term-card';
  const delay = stagger(0.05);
  document.querySelectorAll(`#page :is(${sel})`).forEach((el) => {
    if (el.getBoundingClientRect().top < innerHeight) return;
    const i = Math.min([...el.parentElement.children].indexOf(el), 7);
    el.style.opacity = '0';
    const stop = inView(el, () => {
      stop();
      el.animate([{ opacity: 0, transform: 'translateY(16px)' }, { opacity: 1, transform: 'none' }], { duration: 400, easing: EASE, delay: delay(i, 8) * 1000, fill: 'backwards' });
      el.style.opacity = '';
    });
  });
}

// Count-up for macro figures as they enter view. The real value is in the HTML and is restored exactly at the end.
if (!reduce) {
  document.querySelectorAll('.sbp-term-val, .sbp-ticker-value-row, .hr-macro-item-val, .sbp-ind-val').forEach((el) => {
    const t = [...el.childNodes].find((n) => n.nodeType === 3 && /\d/.test(n.data));
    const m = t && t.data.match(/^(\D*?)(-?)([\d,]*\d)(?:\.(\d+))?(.*)$/s);
    if (!m) return;
    const [, pre, sign, int, dec = '', post] = m;
    const target = Number(sign + int.replace(/,/g, '') + (dec ? `.${dec}` : ''));
    const fmt = new Intl.NumberFormat('en-US', { minimumFractionDigits: dec.length, maximumFractionDigits: dec.length, useGrouping: int.includes(',') });
    // Only the number moves into a box that holds its final width, so the unit beside it stays put.
    const num = t.splitText(pre.length);
    num.splitText(num.data.length - post.length);
    const final = num.data;
    const box = document.createElement('span');
    box.style.display = 'inline-block';
    num.replaceWith(box);
    box.append(num);
    box.style.minWidth = `${box.getBoundingClientRect().width}px`;
    const stop = inView(el, () => {
      stop();
      const t0 = performance.now();
      const tick = (now) => {
        const k = Math.min(1, (now - t0) / 900);
        num.data = k < 1 ? fmt.format(target * (1 - (1 - k) ** 3)) : final;
        if (k < 1) requestAnimationFrame(tick);
      };
      requestAnimationFrame(tick);
    }, { amount: 0.6 });
  });
}

// TradingView panels (inc/markets.php) ship inert (<script type="text/plain" data-hr-tv>) inside a fixed-size .hr-tv
// frame with a placeholder. Nothing third-party loads with the page: a widget starts once the page has loaded and its
// frame is within 400px of the viewport, so the ~1 MB of widget code never competes with the first paint. The frame
// moves through data-hr-tv-state (idle, loading, ready, error, off) and CSS shows the placeholder until "ready", so
// a blocked or slow widget leaves the latest official figures on screen instead of an empty box.
const TV_SRC = /^https:\/\/s3\.tradingview\.com\/external-embedding\/embed-widget-[a-z-]+\.js$/;
const TV_TIMEOUT = 12000;
const tvFrames = [...document.querySelectorAll('script[type="text/plain"][data-hr-tv]')]
  .map((s) => s.closest('.hr-tv') || s.closest('.tradingview-widget-container'))
  .filter((f, i, all) => f && all.indexOf(f) === i);

const embedsAllowed = () => (window.hrConsent?.get ? window.hrConsent.get().embeds !== false : true);

function tvState(frame, state) {
  frame.dataset.hrTvState = state;
  const ph = frame.querySelector('[data-hr-tv-ph]');
  if (!ph) return;
  const msg = ph.querySelector('[data-hr-tv-msg]');
  const text = ph.dataset[`msg${state[0].toUpperCase()}${state.slice(1)}`];
  if (msg && text && msg.textContent !== text) msg.textContent = text;
  const load = ph.querySelector('[data-hr-tv-load]');
  if (load) load.hidden = state !== 'off';
  ph.toggleAttribute('aria-hidden', state === 'ready');
  ph.inert = state === 'ready';
}

function startTv(frame) {
  if (frame.dataset.hrTvState === 'loading' || frame.dataset.hrTvState === 'ready') return;
  const inert = frame.querySelector('script[type="text/plain"][data-hr-tv]');
  if (!inert || !TV_SRC.test(inert.dataset.hrTv)) return;
  tvState(frame, 'loading');
  const box = inert.closest('.tradingview-widget-container') || frame;
  const ready = () => {
    clearTimeout(timer);
    mo.disconnect();
    tvState(frame, 'ready');
  };
  const fail = () => {
    if (frame.dataset.hrTvState === 'loading') tvState(frame, 'error');
  };
  // Ready when the widget's iframe has loaded; a late load still wins over the timeout.
  const watchFrame = (iframe) => {
    if (iframe.dataset.hrTvWatched) return;
    iframe.dataset.hrTvWatched = '1';
    iframe.addEventListener('load', ready, { once: true });
  };
  const mo = new MutationObserver(() => box.querySelectorAll('iframe').forEach(watchFrame));
  mo.observe(box, { childList: true, subtree: true });
  const timer = setTimeout(fail, TV_TIMEOUT);
  const live = document.createElement('script');
  live.src = inert.dataset.hrTv;
  live.async = true;
  live.text = inert.text; // the widget reads its JSON config from its own <script>
  live.addEventListener('error', () => {
    clearTimeout(timer);
    fail();
  });
  inert.replaceWith(live);
}

if (tvFrames.length) {
  const io = new IntersectionObserver((entries) => entries.forEach((entry) => {
    if (!entry.isIntersecting) return;
    io.unobserve(entry.target);
    startTv(entry.target);
  }), { rootMargin: '400px 0px' });
  const watch = (frame) => {
    if (frame.dataset.hrTvState && frame.dataset.hrTvState !== 'idle' && frame.dataset.hrTvState !== 'off') return;
    if (embedsAllowed()) {
      tvState(frame, 'idle');
      io.observe(frame);
    } else {
      tvState(frame, 'off');
    }
  };
  // "Load live data" in the switched-off state: this panel only, this page view only.
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-hr-tv-load]');
    const frame = btn?.closest('.hr-tv');
    if (frame) startTv(frame);
  });
  // A change in the cookie settings applies at once: switched on, waiting panels start as they come near.
  document.addEventListener('hr:consent', () => tvFrames.forEach((f) => {
    if (f.dataset.hrTvState === 'off' && embedsAllowed()) watch(f);
  }));
  const begin = () => (window.requestIdleCallback || setTimeout)(() => tvFrames.forEach(watch));
  if (document.readyState === 'complete') begin(); else addEventListener('load', begin, { once: true });
}

// YouTube facades (inc/perf.php): the privacy-enhanced player replaces the poster when the reader presses play.
document.addEventListener('click', (e) => {
  const btn = e.target.closest('.hr-yt__btn');
  const id = btn?.dataset.hrYt;
  if (!id || !/^[\w-]{11}$/.test(id)) return;
  const frame = document.createElement('iframe');
  frame.src = `https://www.youtube-nocookie.com/embed/${id}?autoplay=1`;
  frame.title = btn.dataset.hrTitle || 'YouTube video';
  frame.allow = 'autoplay; encrypted-media; picture-in-picture; web-share';
  frame.allowFullscreen = true;
  frame.referrerPolicy = 'strict-origin-when-cross-origin';
  btn.replaceWith(frame);
  frame.focus();
});

// Forms: busy state while a newsletter / contact form submits; the footer email field gets a name.
document.addEventListener('submit', (e) => {
  if (e.target.matches('.mc4wp-form, .wpcf7-form')) e.target.setAttribute('aria-busy', 'true');
}, true);
document.addEventListener('wpcf7submit', (e) => e.target.querySelector('form')?.removeAttribute('aria-busy'));
addEventListener('pageshow', () => document.querySelectorAll('form[aria-busy]').forEach((f) => f.removeAttribute('aria-busy')));
document.querySelectorAll('.hr-nl-input:not([aria-label])').forEach((i) => i.setAttribute('aria-label', 'Email address'));
