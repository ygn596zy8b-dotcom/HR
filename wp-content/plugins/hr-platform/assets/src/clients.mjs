// Pause/Play for the client logo marquee (WCAG 2.2.2: moving content must be pausable).
for (const root of document.querySelectorAll('[data-hr-clients]')) {
  const btn = root.querySelector('[data-hr-clients-toggle]');
  if (!btn) continue;
  const label = btn.querySelector('[data-hr-clients-label]');
  btn.addEventListener('click', () => {
    label.textContent = root.classList.toggle('is-paused') ? 'Play' : 'Pause';
  });
}
