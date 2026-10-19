// Browser error monitoring. Performance tracing (browserTracingIntegration) roughly doubles the SDK's size, so it
// lives in sentry-tracing.js and only the sampled share of page views downloads it — instead of this SDK, not on top.
import { readConfig, start } from './_sentry-common.mjs';

try {
  const cfg = readConfig();
  if (cfg) {
    const rate = Math.min(1, Math.max(0, Number(cfg.tracesSampleRate) || 0));
    if (cfg.tracingSrc && Math.random() < rate) {
      const script = document.createElement('script');
      script.src = cfg.tracingSrc;
      script.async = true;
      script.onerror = () => start(cfg); // tracing bundle unavailable: still report errors
      document.head.appendChild(script);
    } else {
      start(cfg);
    }
  }
} catch {
  // Monitoring must never break the page.
}
