// Loaded by sentry.js for the sampled share of page views (the sampling decision is already made there, so every
// transaction on this page is kept). Same error setup plus page-load/navigation/fetch spans and web vitals.
import { browserTracingIntegration } from '@sentry/browser';
import { readConfig, start } from './_sentry-common.mjs';

try {
  const cfg = readConfig();
  if (cfg) start(cfg, [browserTracingIntegration()], 1);
} catch {
  // Monitoring must never break the page.
}
