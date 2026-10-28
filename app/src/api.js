import { CONFIG } from './config.js';

// Talks to the Krono website API (same database and same account as the website).
// The login token stays inside the Electron main process; this file never sees it.
export function api(method, path, body, auth = true) {
  if (!window.krono?.api) return Promise.resolve({ ok: false, error: 'no_bridge', message: 'Open the Krono Desktop app to continue.' });
  return window.krono.api({ base: CONFIG.API_BASE, method, path, body, auth });
}
export const liveAuth = () => CONFIG.AUTH_MODE === 'live';
