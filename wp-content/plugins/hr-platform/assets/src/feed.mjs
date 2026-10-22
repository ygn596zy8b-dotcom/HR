// Browser view of the RSS feeds (assets/xsl/feed.xsl): "Copy link" button, and plain-text item summaries.
// Loaded as a same-origin file, so it runs under the site's Content Security Policy without inline script.

const status = document.querySelector('[data-copy-status]');

function say(message, done) {
  if (!status) return;
  status.textContent = message;
  status.classList.toggle('is-done', Boolean(done));
}

async function copy(input) {
  const text = input.value;
  try {
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(text);
      return true;
    }
  } catch {
    // Permission refused: fall back to selecting the text.
  }
  input.focus();
  input.select();
  try {
    return document.execCommand('copy');
  } catch {
    return false;
  }
}

for (const button of document.querySelectorAll('[data-copy]')) {
  const input = document.getElementById(button.dataset.copy);
  if (!input) continue;
  button.hidden = false;
  button.addEventListener('click', async () => {
    const ok = await copy(input);
    if (ok) {
      button.textContent = 'Copied';
      say('Link copied. Now paste it into your app.', true);
      setTimeout(() => { button.textContent = 'Copy link'; }, 2500);
    } else {
      input.focus();
      input.select();
      say('The link is selected: copy it with your device, then paste it into your app.', false);
    }
  });
}

// Clicking the link box selects all of it, so it is easy to copy by hand.
for (const input of document.querySelectorAll('[data-feed-url]')) {
  input.addEventListener('focus', () => input.select());
}

// Summaries come from the feed as escaped HTML; the stylesheet removes the tags, this decodes entities
// such as &#8217; without ever inserting markup into the page.
// The attribution lines the feed carries for readers ("This article was originally published at ...", "The post ...
// appeared first on ...") are left out of this preview, which is already on the site.
const parser = new DOMParser();
for (const el of document.querySelectorAll('[data-decode]')) {
  let text = el.textContent;
  if (text.includes('&')) {
    text = parser.parseFromString(`<!doctype html><body>${text.replace(/</g, '&lt;')}`, 'text/html').body.textContent;
  }
  text = text.replace(/\s+/g, ' ')
    .replace(/^This article was originally published at .{1,80}?\s\.\s*/, '')
    .replace(/\s*The post .+ appeared first on .{1,80}$/, '')
    .trim();
  if (text.length > 260) text = `${text.slice(0, 257).replace(/\s+\S*$/, '')}...`;
  if (text) el.textContent = text;
}
