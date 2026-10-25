import { CONFIG } from './config.js';

// All times and the "day" follow the workspace timezone, never the PC timezone.
// Later the API sets it from the website: CONFIG.WORKSPACE_TZ = server value.
const tz = () => CONFIG.WORKSPACE_TZ;
export const tmz = (ms) => new Date(ms).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', timeZone: tz() });
export const dmz = (ms) => new Date(ms).toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric', timeZone: tz() });
export const hourz = (ms) => Number(new Intl.DateTimeFormat('en-US', { hour: 'numeric', hour12: false, timeZone: tz() }).format(ms)) % 24;
export const dayKey = (ms) => new Date(ms).toLocaleDateString('en-CA', { timeZone: tz() }); // changes at 12:00 AM workspace time
