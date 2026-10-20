// Pause/Play for the client logo marquee (WCAG 2.2.2: moving content must be pausable).
for (const root of document.querySelectorAll('[data-hr-clients]')) {
  const btn = root.querySelector('[data-hr-clients-toggle]');
  if (!btn) continue;
  const label = btn.querySelector('[data-hr-clients-label]');
  btn.addEventListener('click', () => {
    label.textContent = root.classList.toggle('is-paused') ? 'Play' : 'Pause';
  });
  // Keyboard focus stops the slide (CSS); centre the focused logo, and undo the scroll when focus leaves so the loop has no gap.
  const viewport = root.querySelector('.hr-clients__viewport');
  viewport.addEventListener('focusin', (e) => {
    if (e.target.matches(':focus-visible')) e.target.scrollIntoView({ block: 'nearest', inline: 'center' });
  });
  viewport.addEventListener('focusout', (e) => {
    if (!viewport.contains(e.relatedTarget)) viewport.scrollLeft = 0;
  });
}
