// /dashboard/: busy state on the preference forms, and focus on the result message after a save.
for (const form of document.querySelectorAll('.hrs-form')) {
  form.addEventListener('submit', (e) => {
    const btn = form.querySelector('[type="submit"]');
    if (!btn) return;
    if (btn.getAttribute('aria-busy') === 'true') return e.preventDefault();
    btn.setAttribute('aria-busy', 'true');
  });
}
document.querySelector('.hrs-flash')?.focus();
