import { createContext, useContext, useEffect, useRef, useState } from 'react';
import { CONFIG } from './config.js';
import { tmz as tm, dayKey } from './time.js';
import { nowMs } from './clock.js';
import { api, liveAuth } from './api.js';

const Ctx = createContext(null);
export const useApp = () => useContext(Ctx);
// Mock data until the PHP API exists (tasks come from the website in production).
export const PROJECTS = [
  { name: 'Website Redesign', tasks: ['Homepage Development', 'Design review', 'Footer cleanup'] },
  { name: 'Client Portal', tasks: ['Implement login validation', 'Dashboard API'] },
  { name: 'Internal Tools', tasks: ['Prepare weekly report', 'Update onboarding guide'] },
];
const p2 = (n) => String(Math.floor(n)).padStart(2, '0');
export const fmt = (s) => { s = Math.max(0, Math.floor(s)); return `${p2(s / 3600)}:${p2((s % 3600) / 60)}:${p2(s % 60)}`; };
export const fmtHM = (s) => { const h = Math.floor(s / 3600); const m = p2((s % 3600) / 60); return h > 0 ? `${h}h ${m}m` : `${Number(m)}m`; }; // 40m, 1h 00m, 1h 30m
export const fmtDur = (s, sec) => `${Math.floor(s / 60)}m${sec ? ` ${p2(s % 60)}s` : ''}`;

const KEY = 'krono-mock-v3';
const BEAT = 'krono-beat'; // last time the app was alive (for crash / closed-app recovery)
const load = () => { try { return JSON.parse(localStorage.getItem(KEY)) || {}; } catch { return {}; } };
const t0 = () => nowMs();
const pauseAt = (list, ts) => list.map((t) => (t.since ? { ...t, acc: t.acc + Math.max(0, ts - t.since) / 1000, since: null, status: 'paused', pausedAt: ts } : t));

export function AppProvider({ children }) {
  const init = load();
  const beat = Number(localStorage.getItem(BEAT)) || 0;
  const closedAt = init.tasks?.some((t) => t.since) && t0() - beat > 30000 ? beat : null; // app was closed while a timer ran
  const startNote = useRef(closedAt);
  const [user, setUser] = useState(init.user || null);
  const [owner, setOwner] = useState(init.owner || init.user?.email || null); // whose local data (tasks, logs) this device holds
  const [session, setSession] = useState(init.session || { status: 'out', signedInAt: null, breaks: [] });
  const [tasks, setTasks] = useState(closedAt ? pauseAt(init.tasks, closedAt) : init.tasks || []);
  const [logs, setLogs] = useState(init.logs || []);
  const [theme, setTheme] = useState(init.theme || 'system');
  const [offlineLog, setOfflineLog] = useState(init.offlineLog || []);
  const [lastActive, setLastActive] = useState(init.lastActive || t0());
  const [noticeSeen, setNoticeSeen] = useState(!!init.noticeSeen);
  const [sysDark, setSysDark] = useState(matchMedia('(prefers-color-scheme: dark)').matches);
  const [now, setNow] = useState(t0());
  const [online, setOnline] = useState(navigator.onLine);
  const [toasts, setToasts] = useState([]);
  const [stopId, setStopId] = useState(null);
  const [pendingStart, setPendingStart] = useState(null);
  const [pendingSignOut, setPendingSignOut] = useState(false);
  const [resumeReq, setResumeReq] = useState(null); // { id, at } remembered while offline
  const [idleNote, setIdleNote] = useState(null);
  const offlineSince = useRef(null); const slept = useRef(false); const R = useRef({});
  R.current = { tasks, session, resumeReq, user, owner };

  const notify = (kind, title, small) => setToasts((l) => [...l.slice(-3), { id: crypto.randomUUID(), kind, title, small }]);
  const guard = (what) => (online ? true : (notify('error', 'You are offline', `Connect to the internet to ${what}.`), false));
  const startRaw = (id, ts = t0()) => setTasks((p) => pauseAt(p, ts).map((t) => (t.id === id ? { ...t, since: ts, status: 'running' } : t)));

  // Live accounts: check the saved login with the website (a revoked or expired login signs the app out).
  useEffect(() => {
    if (!liveAuth() || !R.current.user) return;
    api('GET', '/me').then((r) => { if (r.status === 401) setUser(null); else if (r.ok && r.user) setUser((u) => (u && u.email === r.user.email && u.name === r.user.name ? u : r.user)); });
  }, []);
  useEffect(() => {
    if (startNote.current) { notify('info', 'Krono was closed', `Krono was closed from ${tm(startNote.current)}. Your timer was paused at that time.`); startNote.current = null; }
  }, []);
  const lastTick = useRef(t0()); const sleepAt = useRef(null);
  useEffect(() => {
    const i = setInterval(() => {
      const n = t0(); const last = lastTick.current; const gap = n - last; lastTick.current = n; setNow(n);
      localStorage.setItem(BEAT, String(n));
      if ((gap > 20000 || gap < -2000) && R.current.tasks.some((t) => t.since)) { // PC slept / froze / clock changed
        const at = Math.min(last, n); setTasks((p) => pauseAt(p, at));
        notify('info', gap < 0 ? 'The PC clock changed' : 'Your PC was paused or asleep', `Your timer was paused at ${tm(at)}. Resume it when you are ready.`);
      }
    }, 1000);
    return () => clearInterval(i);
  }, []);
  useEffect(() => {
    const mq = matchMedia('(prefers-color-scheme: dark)'); const f = () => setSysDark(mq.matches);
    const off = () => { const ts = t0(); offlineSince.current = ts; setOnline(false); setTasks((p) => pauseAt(p, ts)); }; // timer stops when offline begins
    const on = () => {
      const ts = t0(); const from = offlineSince.current || ts; offlineSince.current = null; setOnline(true);
      setOfflineLog((l) => [...l, { start: from, end: ts }]);
      setSession((s) => ({ ...s, draftBreak: false, breaks: s.breaks.map((b) => (b.end == null ? { start: b.start, end: ts } : b)) })); // drafted break end = back online
      const rr = R.current.resumeReq;
      if (!rr) return notify('info', 'You are back online', 'Your drafts are synced.');
      setResumeReq(null);
      const task = R.current.tasks.find((x) => x.id === rr.id);
      const why = !task ? 'The task is no longer in your list.' : ts - rr.at > CONFIG.RESUME_WINDOW_MIN * 60000 ? `Reconnecting took longer than ${CONFIG.RESUME_WINDOW_MIN} minutes. Resume it yourself.`
        : R.current.session.status !== 'working' ? 'Your attendance is not in a working state.' : slept.current ? 'Your PC went to sleep.' : R.current.tasks.some((x) => x.since) ? 'Another timer is running.' : null;
      slept.current = false;
      if (why) notify('info', 'Resume was not started', why); else { startRaw(rr.id, ts); notify('success', 'You are back online', 'Your task timer resumed.'); }
    };
    mq.addEventListener('change', f); addEventListener('online', on); addEventListener('offline', off);
    return () => { mq.removeEventListener('change', f); removeEventListener('online', on); removeEventListener('offline', off); };
  }, []);
  useEffect(() => { localStorage.setItem(KEY, JSON.stringify({ user, owner, session, tasks, logs, theme, offlineLog, lastActive, noticeSeen })); }, [user, owner, session, tasks, logs, theme, offlineLog, lastActive, noticeSeen]);
  const dark = theme === 'dark' || (theme === 'system' && sysDark);
  useEffect(() => { document.body.dataset.theme = dark ? 'dark' : 'light'; window.krono?.setTheme(theme); }, [dark, theme]);

  const today = dayKey(now);
  const isOld = (t) => t.status !== 'running' && dayKey(t.pausedAt || t.createdAt || now) !== today; // carried-over draft
  const elapsed = (t) => t.acc + (t.since ? (now - t.since) / 1000 : 0);
  const brk = session.breaks.reduce((a, b) => a + ((b.end ?? now) - b.start) / 1000, 0) + (session.status === 'break' ? (now - session.breakStart) / 1000 : 0);
  const end = session.status === 'out' ? session.signedOutAt || session.signedInAt : now;
  const gross = session.signedInAt ? Math.max(0, (end - session.signedInAt) / 1000) : 0;
  const offlineTotal = offlineLog.reduce((a, o) => a + (o.end - o.start) / 1000, 0) + (offlineSince.current ? (now - offlineSince.current) / 1000 : 0);
  const stats = { gross, net: Math.max(0, gross - brk), brk, breakCount: session.breaks.length + (session.status === 'break' ? 1 : 0), saved: logs.filter((l) => dayKey(l.at) === today).reduce((a, l) => a + l.secs, 0), offlineTotal, offlineCount: offlineLog.length + (offlineSince.current ? 1 : 0) };
  const hasUnsaved = tasks.some((t) => elapsed(t) > 0);
  const oldDrafts = tasks.filter((t) => isOld(t) && elapsed(t) > 0);

  // Day change: never-started leftovers vanish; yesterday's open session closes at the last activity.
  useEffect(() => { if (tasks.some((t) => isOld(t) && t.acc === 0 && !t.since)) setTasks((p) => p.filter((t) => !(isOld(t) && t.acc === 0 && !t.since))); });
  useEffect(() => {
    if (session.status === 'out' || !session.signedInAt || dayKey(session.signedInAt) === today || now - lastActive < CONFIG.IDLE_STOP_S * 1000) return;
    const e = Math.max(session.signedInAt, lastActive);
    setTasks((p) => pauseAt(p, e));
    setSession((s) => ({ ...s, status: 'out', signedOutAt: e, autoClosed: true, breakStart: null, breaks: s.status === 'break' ? [...s.breaks, { start: s.breakStart, end: e }] : s.breaks }));
    notify('info', 'Yesterday’s session was closed automatically', `Sign-out time was set to ${tm(e)}, your last activity.`);
  });

  // A different account on this device never sees the previous account's local tasks and logs.
  const adopt = (u) => {
    if (R.current.owner && R.current.owner !== u.email) { setTasks([]); setLogs([]); setOfflineLog([]); setSession({ status: 'out', signedInAt: null, breaks: [] }); }
    setOwner(u.email); setUser(u);
  };
  const signOut = () => { if (!guard('sign out')) return; setTasks((p) => pauseAt(p, t0())); setSession((s) => ({ ...s, status: 'out', signedOutAt: t0() })); };
  const A = {
    login: (name, email) => adopt({ name, email }), // mock mode only
    signedIn: (u) => adopt(u),                       // real account returned by the website API
    logout: () => {
      if (!guard('log out')) return;
      if (liveAuth()) api('POST', '/auth/logout').catch(() => {}); // the app always signs out, even if the server cannot be reached
      setUser(null);
    },
    // Website -> app: krono://auth?code=... carries a one-time code. Exchange it for this app's own login.
    handoffLogin: async (code) => {
      const cur = R.current;
      const busy = cur.user && (cur.session.status !== 'out' || cur.tasks.some((t) => t.acc > 0 || t.since));
      if (busy) return notify('error', 'Could not switch account', 'Finish Sign Out and save your tracked time first, then press Open Krono Desktop again.');
      const r = await api('POST', '/auth/handoff', { code }, false);
      if (!r.ok || !r.user) return notify('error', 'Could not sign in from the website', r.message || 'Press Open Krono Desktop on the website again.');
      adopt(r.user);
      notify('success', 'Signed in from the website', r.user.email);
    },
    // App -> website: open the website already signed in as the same account.
    openWebsite: async () => {
      if (liveAuth() && R.current.user) {
        const r = await api('POST', '/auth/web-link');
        if (r.ok && r.url) return window.open(r.url);
      }
      window.open(CONFIG.WEBSITE_URL);
    },
    signIn: () => { if (guard('sign in')) setSession({ status: 'working', signedInAt: t0(), breaks: [] }); },
    signOut,
    start: (id) => { if (guard('start a task')) startRaw(id); },
    resume: (id) => {
      if (online) return startRaw(id);
      slept.current = false; setResumeReq({ id, at: t0() });
      notify('error', 'You are offline', `Resume will start automatically if you reconnect within ${CONFIG.RESUME_WINDOW_MIN} minutes.`);
    },
    breakStart: () => {
      if (online) { setTasks((p) => pauseAt(p, t0())); return setSession((s) => ({ ...s, status: 'break', breakStart: t0() })); }
      setSession((s) => ({ ...s, status: 'break', breakStart: offlineSince.current || t0(), draftBreak: true }));
      notify('info', 'Break saved as a draft', 'It will be recorded when you are back online.');
    },
    breakEnd: () => {
      setSession((s) => ({ ...s, status: 'working', breaks: [...s.breaks, { start: s.breakStart, end: online ? t0() : null }], breakStart: null }));
      online ? notify('info', 'Break ended successfully', 'Your previous task is still paused. Start a task when ready.') : notify('info', 'Break end saved as a draft', 'The end time will be set when you are back online.');
    },
    addTask: (project, title) => {
      if (!guard('add a task')) return;
      if (tasks.some((t) => t.project === project && t.title === title)) return notify('info', 'Task is already in your list', 'Choose a different task.');
      setTasks((p) => [...p, { id: crypto.randomUUID(), title, project, status: 'idle', acc: 0, since: null, createdAt: t0() }]);
      notify('success', 'Task added to tracking list', 'The timer has not started.');
    },
    pause: () => setTasks((p) => pauseAt(p, t0())),
    remove: (id) => { setTasks((p) => p.filter((t) => t.id !== id)); notify('info', 'Task removed from tracking list', 'The source project and task were not deleted.'); },
    stop: (id, desc) => {
      const t = tasks.find((x) => x.id === id); if (!t) return;
      if (!online) { // keep the description as a draft; save when online
        setTasks((p) => p.map((x) => (x.id === id ? { ...x, draft: desc } : x))); setStopId(null);
        return notify('error', 'Could not save time log', 'Your timer session and description are preserved. Save again when you are online.');
      }
      setLogs((l) => [...l, { id, title: t.title, project: t.project, desc, secs: elapsed(t), at: t.pausedAt || t0() }]);
      setTasks((p) => p.filter((x) => x.id !== id)); setStopId(null);
      notify('success', 'Time log saved successfully', 'Task removed from your tracking list.');
      if (pendingStart) { startRaw(pendingStart); setPendingStart(null); }
    },
    cancelStop: () => { setStopId(null); setPendingStart(null); setPendingSignOut(false); },
    discardAndSignOut: () => { setTasks((p) => p.filter((t) => !(t.acc > 0 || t.since))); signOut(); },
    idleStop: (ts) => { setTasks((p) => pauseAt(p, ts)); setIdleNote({ from: ts }); },
    idleBack: () => { if (idleNote) { notify('info', 'You were idle', `No mouse or keyboard activity since ${tm(idleNote.from)}. Your timer was paused at that time.`); setIdleNote(null); } },
    touch: setLastActive, setSlept: (v) => { slept.current = v; },
    onSuspend: () => { slept.current = true; if (R.current.tasks.some((t) => t.since)) { sleepAt.current = t0(); setTasks((p) => pauseAt(p, sleepAt.current)); } },
    onResume: () => { const at = sleepAt.current; sleepAt.current = null; if (at) notify('info', 'Your PC was asleep', `From ${tm(at)} to ${tm(t0())}. Your timer was paused at ${tm(at)}.`); },
    setTheme, notify, guard, setStopId, setPendingStart, setPendingSignOut, setNoticeSeen,
  };
  useEffect(() => { if (pendingSignOut === true && !stopId && !hasUnsaved) { signOut(); setPendingSignOut(false); } });
  return <Ctx.Provider value={{ user, session, tasks, logs, theme, dark, online, stats, toasts, stopId, hasUnsaved, oldDrafts, isOld, resumeReq, pendingSignOut, noticeSeen, elapsed, ...A, dismissToast: (id) => setToasts((l) => l.filter((x) => x.id !== id)) }}>{children}</Ctx.Provider>;
}
