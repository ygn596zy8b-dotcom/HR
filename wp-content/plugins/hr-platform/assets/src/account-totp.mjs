// Two-step verification setup: draws the QR code for the authenticator app on a <canvas>. The otpauth:// URI is
// built here from data attributes (the secret is already on the page for manual entry) and never leaves the browser.
import qrcode from 'qrcode-generator';

function draw(box) {
  const host = box.closest('[data-hra-totp-setup]');
  const target = box;
  const secret = (host.dataset.secret || '').replace(/[^A-Z2-7]/gi, '').toUpperCase();
  if (!secret) return;
  const issuer = host.dataset.issuer || 'Headline Recorder';
  const account = host.dataset.account || '';
  const label = encodeURIComponent(issuer) + ':' + encodeURIComponent(account);
  const uri = `otpauth://totp/${label}?secret=${secret}&issuer=${encodeURIComponent(issuer)}&algorithm=SHA1&digits=6&period=30`;

  const qr = qrcode(0, 'M');
  qr.addData(uri);
  qr.make();
  const count = qr.getModuleCount();
  const quiet = 2;
  const scale = Math.max(4, Math.floor(376 / (count + quiet * 2)));
  const size = (count + quiet * 2) * scale;
  const canvas = document.createElement('canvas');
  canvas.width = size;
  canvas.height = size;
  const ctx = canvas.getContext('2d');
  ctx.fillStyle = '#ffffff';
  ctx.fillRect(0, 0, size, size);
  ctx.fillStyle = '#050505';
  for (let r = 0; r < count; r++) {
    for (let c = 0; c < count; c++) {
      if (qr.isDark(r, c)) ctx.fillRect((c + quiet) * scale, (r + quiet) * scale, scale, scale);
    }
  }
  canvas.setAttribute('aria-hidden', 'true');
  target.replaceChildren(canvas);
  if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches && canvas.animate) {
    canvas.animate([{ opacity: 0, transform: 'scale(.97)' }, { opacity: 1, transform: 'none' }], { duration: 260, easing: 'cubic-bezier(.16,1,.3,1)' });
  }
}

document.querySelectorAll('[data-hra-totp-setup] [data-hra-qr]').forEach(draw);
