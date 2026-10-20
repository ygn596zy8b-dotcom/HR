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

// TradingView panels ship inert (<script type="text/plain" data-hr-tv>) and start once the page has loaded and the
// panel is near the viewport, so the widgets (~1 MB) never compete with the first paint. Skeleton in CSS until the
// iframe arrives; 8 s after starting, say so (the widget may still load later).
const TV_SRC = /^https:\/\/s3\.tradingview\.com\/external-embedding\/embed-widget-[a-z-]+\.js$/;
const tvBoxes = [...document.querySelectorAll('.tradingview-widget-container')].filter((box) => !box.querySelector('iframe'));
if (tvBoxes.length) {
  const io = new IntersectionObserver((entries) => entries.forEach((entry) => {
    if (!entry.isIntersecting) return;
    io.unobserve(entry.target);
    startTv(entry.target);
  }), { rootMargin: '400px 0px' });
  const watch = () => (window.requestIdleCallback || setTimeout)(() => tvBoxes.forEach((box) => io.observe(box)));
  if (document.readyState === 'complete') watch(); else addEventListener('load', watch, { once: true });
}

function startTv(box) {
  const inert = box.querySelector('script[data-hr-tv]');
  if (inert && TV_SRC.test(inert.dataset.hrTv)) {
    const live = document.createElement('script');
    live.src = inert.dataset.hrTv;
    live.async = true;
    live.text = inert.text; // the widget reads its JSON config from its own <script>
    inert.replaceWith(live);
  }
  let note;
  const timer = setTimeout(() => {
    box.classList.add('is-unavailable');
    if (box.classList.contains('hr-ticker-tape__widget')) return;
    note = document.createElement('p');
    note.className = 'hr-tv-unavailable';
    note.setAttribute('role', 'status');
    note.textContent = 'Live market data is unavailable right now.';
    box.append(note);
  }, 8000);
  const mo = new MutationObserver(() => {
    if (!box.querySelector('iframe')) return;
    clearTimeout(timer);
    box.classList.remove('is-unavailable');
    note?.remove();
    mo.disconnect();
  });
  mo.observe(box, { childList: true, subtree: true });
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
