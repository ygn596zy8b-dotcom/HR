// Browser error + performance monitoring. Config comes from <script type="application/json" id="hr-sentry-config">
// (printed by includes/monitoring/module.php); envelopes go through the same-origin tunnel, never straight to Sentry.
// No Session Replay, no release-health sessions: one fewer request per page view and nothing recorded about readers.
import { init, browserTracingIntegration } from '@sentry/browser';

const SENSITIVE = /pass|pwd|token|key|nonce|secret|sig|code|email|auth|login/i;
const EMAIL = /[A-Z0-9._%+-]+(?:@|%40)[A-Z0-9.-]+\.[A-Z]{2,}/gi;
const QUERY_PARAM = /([?&;])([^=&#\s;]+)=([^&#\s;]*)/g;

function decode(s) {
  try {
    return decodeURIComponent(s);
  } catch {
    return s;
  }
}

function scrubString(s) {
  return s
    .replace(EMAIL, '[email]')
    .replace(QUERY_PARAM, (m, sep, name) => (SENSITIVE.test(decode(name)) ? `${sep}${name}=[Filtered]` : m));
}

// Strings anywhere in the event (messages, exception values, breadcrumb URLs, span descriptions) lose emails and
// sensitive query values. Mutates in place: the SDK hands us its own copy.
function scrub(value, depth = 0) {
  if (typeof value === 'string') return scrubString(value);
  if (!value || typeof value !== 'object' || depth > 12) return value;
  for (const k of Object.keys(value)) value[k] = scrub(value[k], depth + 1);
  return value;
}

function scrubEvent(event) {
  const req = event.request;
  if (req) {
    delete req.cookies;
    if (req.headers) {
      for (const name of Object.keys(req.headers)) {
        if (/^(cookie|authorization|x-wp-nonce)$/i.test(name)) delete req.headers[name];
      }
    }
    if (typeof req.query_string === 'string') req.query_string = scrubString('?' + req.query_string).slice(1);
  }
  if (event.user) event.user = event.user.id ? { id: event.user.id } : undefined;
  return scrub(event);
}

const escapeRe = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

function start() {
  const el = document.getElementById('hr-sentry-config');
  if (!el) return;
  let cfg;
  try {
    cfg = JSON.parse(el.textContent || '{}');
  } catch {
    return;
  }
  if (!cfg || typeof cfg.dsn !== 'string' || !cfg.dsn) return;

  const ownOrigin = new RegExp('^' + escapeRe(location.origin) + '(?:/|$)');
  init({
    dsn: cfg.dsn,
    environment: cfg.environment,
    release: cfg.release,
    tunnel: cfg.tunnel,
    sendDefaultPii: false,
    sendClientReports: false,
    tracesSampleRate: Number(cfg.tracesSampleRate) || 0,
    integrations: (defaults) => [...defaults.filter((i) => i.name !== 'BrowserSession'), browserTracingIntegration()],
    // Only errors from our own scripts; third-party widgets and extensions are not ours to fix.
    allowUrls: [ownOrigin],
    denyUrls: [/^(?:chrome|moz|safari|safari-web|ms-browser)-extension:\/\//i, /^chrome:\/\//i, /^webkit-masked-url:/i, /\/extensions\//i],
    ignoreErrors: [
      /^ResizeObserver loop/,
      /Non-Error promise rejection captured/,
      /extension:\/\//i,
      /^Script error\.?$/,
      /webkit-masked-url/i,
    ],
    tracePropagationTargets: [ownOrigin],
    beforeSend: scrubEvent,
    beforeSendTransaction: scrubEvent,
  });
}

try {
  start();
} catch {
  // Monitoring must never break the page.
}
