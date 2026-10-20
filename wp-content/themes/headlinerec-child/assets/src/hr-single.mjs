// Single-article behaviour (MSBJ): AI briefing reveal and the author bio "See More" toggle.
// Built to assets/js/hr-single.js, loaded (deferred) only on single posts.

const REDUCED = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// AI briefing <details>: the summary text is in the HTML for crawlers; on first open it is revealed word by word.
function aiBriefing() {
  const details = document.getElementById('ai-briefing-details');
  if (!details) return;
  const summary = document.getElementById('ai-briefing-summary');
  const box = document.getElementById('ai-content-box');
  const status = document.getElementById('ai-status-text');
  const icon = document.getElementById('ai-toggle-icon');
  const words = box.textContent.trim().split(/\s+/);
  let revealed = false;
  details.addEventListener('toggle', () => {
    icon.textContent = details.open ? '−' : '+';
  });
  summary.addEventListener('click', (e) => {
    if (revealed) return;
    revealed = true;
    if (REDUCED) {
      status.textContent = '✧ EXECUTIVE BRIEFING';
      return; // opens normally with the full text
    }
    e.preventDefault();
    status.textContent = '✧ ANALYZING REPORT...';
    status.classList.add('ai-shimmer');
    icon.textContent = '⟳';
    box.textContent = '';
    setTimeout(() => {
      details.open = true;
      status.classList.remove('ai-shimmer');
      status.textContent = '✧ EXECUTIVE BRIEFING';
      box.classList.add('ai-cursor');
      let i = 0;
      const timer = setInterval(() => {
        if (i < words.length) {
          box.append(document.createTextNode(words[i++] + ' '));
        } else {
          clearInterval(timer);
          box.classList.remove('ai-cursor');
        }
      }, 35);
    }, 800);
  });
}

// Author bio: clamped to three lines; the button only shows when the text overflows.
function bioToggle() {
  const text = document.querySelector('.hr-author-bio-text');
  const btn = document.querySelector('.hr-bio-toggle');
  if (!text || !btn) return;
  if (text.scrollHeight > text.clientHeight) btn.hidden = false;
  btn.addEventListener('click', (e) => {
    e.preventDefault();
    const open = text.classList.toggle('is-expanded');
    btn.textContent = open ? 'See Less' : 'See More';
    btn.setAttribute('aria-expanded', String(open));
  });
}

aiBriefing();
bioToggle();
