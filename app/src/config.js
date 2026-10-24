// All tunable rules live here (see ../../FINAL_DECISIONS.md).
export const CONFIG = {
  API_MODE: 'mock',                          // 'mock' | 'live'
  API_BASE: 'http://localhost/krono/website/api', // Laragon; https in production
  HEARTBEAT_S: 15,
  PING_FAILS_BEFORE_OFFLINE: 3,
  IDLE_WARNING_S: 10,                       // 10 min: warning
  IDLE_STOP_S: 720,                          // 12 min: timers stop (at last activity)
  RESUME_WINDOW_MIN: 30,                     // offline Resume auto-start window
  WEBSITE_URL: 'http://localhost/krono/website/public', // Laragon; real domain later
  WORKSPACE_TZ: 'Asia/Dhaka',                // defines "day" for draft rule
};
