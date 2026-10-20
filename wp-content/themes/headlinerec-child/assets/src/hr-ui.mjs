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

// TradingView panels: skeleton in CSS until the iframe arrives; after 8 s say so (the widget may still load later).
document.querySelectorAll('.tradingview-widget-container').forEach((box) => {
  if (box.querySelector('iframe')) return;
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
});

// Forms: busy state while a newsletter / contact form submits; the footer email field gets a name.
document.addEventListener('submit', (e) => {
  if (e.target.matches('.mc4wp-form, .wpcf7-form')) e.target.setAttribute('aria-busy', 'true');
}, true);
document.addEventListener('wpcf7submit', (e) => e.target.querySelector('form')?.removeAttribute('aria-busy'));
addEventListener('pageshow', () => document.querySelectorAll('form[aria-busy]').forEach((f) => f.removeAttribute('aria-busy')));
document.querySelectorAll('.hr-nl-input:not([aria-label])').forEach((i) => i.setAttribute('aria-label', 'Email address'));
