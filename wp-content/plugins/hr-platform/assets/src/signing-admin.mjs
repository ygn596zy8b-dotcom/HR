// Settings > Content signing: the "Sign all pending now" loop and the copy button for the HR_SIGNING_KEK line.
// Batches go to admin-ajax.php?action=hr_pqsig_backlog (manage_options, nonce, same origin). Each batch signs for
// about 8 seconds and returns a cursor, so a failing article is reported once and skipped, never retried forever.

const root = document.getElementById('hr-sig-backlog');

function fmt(n) {
  return Number(n || 0).toLocaleString();
}

function setText(sel, value) {
  document.querySelectorAll(`[data-hr-sig="${sel}"]`).forEach((el) => { el.textContent = fmt(value); });
}

const sleep = (ms) => new Promise((resolve) => { setTimeout(resolve, ms); });

if (root) {
  const runBtn = root.querySelector('.hr-sig-run');
  const stopBtn = root.querySelector('.hr-sig-stop');
  const box = root.querySelector('.hr-sig-progress');
  const bar = root.querySelector('.hr-sig-progress__bar');
  const label = root.querySelector('.hr-sig-progress__label');
  const failBox = root.querySelector('.hr-sig-failures');
  const failList = failBox ? failBox.querySelector('ul') : null;
  let stopping = false;
  let running = false;

  const say = (text) => { label.textContent = text; };

  const addFailure = (f) => {
    if (!failList) return;
    const li = document.createElement('li');
    if (f.edit) {
      const a = document.createElement('a');
      a.href = f.edit;
      a.textContent = f.title;
      li.append(a);
    } else {
      li.append(document.createTextNode(f.title));
    }
    li.append(document.createTextNode(`: ${f.message}`));
    failList.append(li);
    failBox.hidden = false;
  };

  async function batch(cursor) {
    const body = new URLSearchParams({ action: 'hr_pqsig_backlog', nonce: root.dataset.nonce, cursor });
    const res = await fetch(root.dataset.ajax, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body,
    });
    let json = null;
    try { json = await res.json(); } catch { /* not JSON: a PHP error page or a timeout */ }
    if (!json || !json.success) {
      const msg = json && json.data && json.data.message ? json.data.message : `The server answered HTTP ${res.status}.`;
      const err = new Error(msg);
      err.retry = !json || res.status >= 500;
      throw err;
    }
    return json.data;
  }

  async function run() {
    if (running) return;
    running = true;
    stopping = false;
    runBtn.disabled = true;
    stopBtn.hidden = false;
    stopBtn.disabled = false;
    box.hidden = false;
    if (failList) failList.textContent = '';
    if (failBox) failBox.hidden = true;

    const total = Math.max(1, Number(root.dataset.pending) || 0);
    let cursor = '';
    let checked = 0;
    let signed = 0;
    let failed = 0;
    let errors = 0;
    bar.value = 0;
    say('Starting...');

    try {
      while (!stopping) {
        let data;
        try {
          data = await batch(cursor);
          errors = 0;
        } catch (err) {
          errors += 1;
          if (!err.retry || errors > 4) throw err;
          say(`${err.message} Trying again...`);
          await sleep(3000 * errors);
          continue;
        }
        if (data.busy) {
          say('An automatic run is signing right now. Waiting for it to finish...');
          await sleep(4000);
          continue;
        }
        cursor = data.cursor || cursor;
        checked += data.checked;
        signed += data.signed;
        failed += data.failed.length;
        data.failed.forEach(addFailure);
        setText('signed', data.signedAll);
        setText('public', data.public);
        setText('pending', data.pending);
        bar.value = data.done ? 100 : Math.min(99, Math.floor((100 * checked) / total));
        if (data.stopped) {
          say(`Stopped: ${data.stopped}`);
          break;
        }
        if (data.done) {
          const feeds = Object.entries(data.feeds || {})
            .map(([key, status]) => `${key} feed: ${status === 'stored' ? 'signed' : status}`).join('; ');
          say(`Done. ${fmt(signed)} signed${failed ? `, ${fmt(failed)} could not be signed (listed below)` : ''}. ${fmt(data.signedAll)} of ${fmt(data.public)} articles are now signed.${feeds ? ` ${feeds}.` : ''}`);
          break;
        }
        say(`${fmt(signed)} signed so far (${bar.value}%), ${fmt(data.pending)} still pending...`);
      }
      if (stopping) say(`Stopped. ${fmt(signed)} signed. Press the button again to continue.`);
    } catch (err) {
      say(`Stopped: ${err.message}`);
    } finally {
      running = false;
      runBtn.disabled = false;
      stopBtn.hidden = true;
      runBtn.textContent = 'Run again';
    }
  }

  runBtn.addEventListener('click', run);
  stopBtn.addEventListener('click', () => {
    stopping = true;
    stopBtn.disabled = true;
    say('Stopping after this batch...');
  });
  window.addEventListener('beforeunload', (e) => {
    if (running && !stopping) {
      e.preventDefault();
      e.returnValue = '';
    }
  });
}

document.querySelectorAll('.hr-sig-copy-btn').forEach((btn) => {
  btn.addEventListener('click', async () => {
    const input = document.getElementById(btn.dataset.target);
    if (!input) return;
    input.select();
    let ok = false;
    try {
      await navigator.clipboard.writeText(input.value);
      ok = true;
    } catch {
      ok = document.execCommand && document.execCommand('copy');
    }
    btn.textContent = ok ? 'Copied' : 'Select and copy';
    setTimeout(() => { btn.textContent = 'Copy'; }, 2500);
  });
});

// Each key form sends once; a second click while the server works would only trip the rate limit.
document.querySelectorAll('.hr-sig-form').forEach((form) => {
  form.addEventListener('submit', (e) => {
    if (form.querySelector('.hr-sig-danger') && !window.confirm('Remove the signing key from this server? Automatic signing stops until a key is installed again.')) {
      e.preventDefault();
      return;
    }
    const btn = form.querySelector('button[type="submit"]');
    if (btn) {
      setTimeout(() => { btn.disabled = true; btn.textContent = 'Working...'; }, 0);
    }
  });
});
