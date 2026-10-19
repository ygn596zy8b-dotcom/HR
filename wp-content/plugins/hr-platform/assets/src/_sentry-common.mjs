// Shared by sentry.mjs (errors) and sentry-tracing.mjs (errors + performance). Config comes from
// <script type="application/json" id="hr-sentry-config"> printed by includes/monitoring/module.php; envelopes go
// through the same-origin tunnel, never straight to Sentry. No Session Replay and no release-health sessions:
// nothing is recorded about readers and there is no extra request per page view.
// A hand-built client instead of init(): init() bundles every default integration (sessions, culture context,
// console capture …) whether used or not, which pushes the error bundle past 90 KB.
import {
  BrowserClient,
  breadcrumbsIntegration,
  browserApiErrorsIntegration,
  dedupeIntegration,
  defaultStackParser,
  eventFiltersIntegration,
  functionToStringIntegration,
  getCurrentScope,
  globalHandlersIntegration,
  httpContextIntegration,
  linkedErrorsIntegration,
  makeFetchTransport,
} from '@sentry/browser';

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

export function readConfig() {
  const el = document.getElementById('hr-sentry-config');
  if (!el) return null;
  try {
    const cfg = JSON.parse(el.textContent || '{}');
    return cfg && typeof cfg.dsn === 'string' && cfg.dsn ? cfg : null;
  } catch {
    return null;
  }
}

export function start(cfg, extraIntegrations = [], tracesSampleRate = 0) {
  // Anchored, so "https://evil.example/?https://headlinerecorder.com" does not match.
  const ownOrigin = new RegExp('^' + escapeRe(location.origin) + '(?:/|$)');
  const client = new BrowserClient({
    dsn: cfg.dsn,
    environment: cfg.environment,
    release: cfg.release,
    tunnel: cfg.tunnel,
    sendDefaultPii: false,
    sendClientReports: false,
    tracesSampleRate,
    transport: makeFetchTransport,
    stackParser: defaultStackParser,
    integrations: [
      eventFiltersIntegration(),
      functionToStringIntegration(),
      browserApiErrorsIntegration(),
      breadcrumbsIntegration(),
      globalHandlersIntegration(),
      linkedErrorsIntegration(),
      dedupeIntegration(),
      httpContextIntegration(),
      ...extraIntegrations,
    ],
    // Only errors from our own scripts; third-party widgets and extensions are not ours to fix.
    allowUrls: [ownOrigin],
    denyUrls: [/^(?:chrome|moz|safari|safari-web|ms-browser)-extension:\/\//i, /^chrome:\/\//i, /^webkit-masked-url:/i, /\/extensions\//i],
    ignoreErrors: [/^ResizeObserver loop/, /Non-Error promise rejection captured/, /extension:\/\//i, /^Script error\.?$/, /webkit-masked-url/i],
    tracePropagationTargets: [ownOrigin],
    beforeSend: scrubEvent,
    beforeSendTransaction: scrubEvent,
  });
  getCurrentScope().setClient(client);
  client.init();
}
