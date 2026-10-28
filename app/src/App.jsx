import { useEffect, useRef, useState } from 'react';
import { CONFIG } from './config.js';
import { AppProvider, useApp, fmt } from './store.jsx';
import { Auth, Dashboard, Work, Settings, Logo, Modal } from './pages.jsx';
import { Ico } from './icons.jsx';
import { nowMs } from './clock.js';

const NAV = [['dashboard', 'Dashboard', 'dashboard'], ['work', 'Attendance & Time Tracking', 'timer'], ['site', 'View Website', 'external'], ['settings', 'Settings', 'settings']];
const PAGES = { dashboard: Dashboard, work: Work, settings: Settings };

const NetBadge = ({ online }) => (
  <span className={`badge ${online ? '' : 'danger'}`}><span className="dot" style={online ? undefined : { background: 'var(--danger)' }} /> {online ? 'Online' : 'Offline'}</span>
);

function Shell() {
  const A = useApp();
  const { user, session, tasks, toasts, dismissToast, online, elapsed, hasUnsaved, logout } = A;
  const [page, setPage] = useState('dashboard');
  const [idle, setIdle] = useState(0);
  const [closing, setClosing] = useState(false);
  const [loggingOut, setLoggingOut] = useState(false);
  const run = tasks.find((t) => t.status === 'running');
  const busy = session.status !== 'out' || hasUnsaved;

  useEffect(() => {
    const k = window.krono; if (!k) return;
    const a = k.onIdleTick((s) => { setIdle(s); A.touch(nowMs() - s * 1000); });
    const b = k.onCloseRequested(() => (!user || !busy ? k.allowClose() : setClosing(true)));
    const c = k.onPower((e) => (e === 'suspend' ? A.onSuspend() : A.onResume()));
    return () => { a(); b(); c(); };
  }, [user, busy]);

  // krono://auth?code=... from the website ("Open Krono Desktop"): sign in automatically.
  const seenCodes = useRef(new Set());
  useEffect(() => {
    const k = window.krono; if (!k?.onDeepLink) return;
    const use = (code) => { if (!code || seenCodes.current.has(code)) return; seenCodes.current.add(code); A.handoffLogin(code); };
    const off = k.onDeepLink(use);
    k.takeDeepLink().then(use);
    return off;
  }, []);

  useEffect(() => { if (run && idle >= CONFIG.IDLE_STOP_S) A.idleStop(nowMs() - idle * 1000); else if (idle < 10) A.idleBack(); }, [idle]);
  const warn = !!run && idle >= CONFIG.IDLE_WARNING_S;
  const left = Math.max(0, CONFIG.IDLE_STOP_S - idle);
  useEffect(() => { window.krono?.idleWarning({ show: warn, secondsLeft: left }); }, [warn, left]);
  useEffect(() => { window.krono?.setTitle(run ? `Krono · ${run.title} ${fmt(elapsed(run))}` : 'Krono Desktop'); });

  const pageName = NAV.find((n) => n[0] === page)[1];
  const bar = (
    <div className="titlebar"><Logo />{/* logo sized by .titlebar img */}<span className="app-title">Krono Desktop</span><span className="muted small">· {user ? pageName : 'Welcome'}</span></div>
  );
  const toastEl = (
    <div className="toast-stack" style={{ '--toast-s': `${CONFIG.TOAST_SECONDS}s` }}>
      {toasts.map((t) => (
        <div key={t.id} className={`toast-float ${t.kind}`} role="status"><div className="toast">
          <div className="toast-icon"><Ico n={t.kind === 'success' ? 'checkCircle' : t.kind === 'error' ? 'warning' : 'info'} /></div>
          <div><strong>{t.title}</strong><small>{t.small}</small></div><div className="spacer" />
          <button className="subtle" style={{ border: 0, background: 'none', cursor: 'pointer' }} aria-label="Dismiss" onClick={() => dismissToast(t.id)}><Ico n="x" /></button></div>
          <div className="toast-bar" onAnimationEnd={() => dismissToast(t.id)} /></div>
      ))}
    </div>
  );
  if (!user) return <div className="window">{bar}<div className="auth-window" style={{ position: 'relative' }}><div style={{ position: 'absolute', top: 14, right: 20 }}><NetBadge online={online} /></div><Auth /></div>{toastEl}</div>;

  const Page = PAGES[page];
  const initials = user.name.split(' ').map((w) => w[0]).slice(0, 2).join('').toUpperCase();
  const firstPending = tasks.find((t) => elapsed(t) > 0);
  return (
    <div className="window">{bar}
      <div className="app-body">
        <aside className="sidebar">
          <div className="brand"><Logo /><small>TRACK TIME · MANAGE WORK</small></div>
          <div className="nav-caption">YOUR WORK</div>
          <div className="nav-list">{NAV.map(([id, label, icon]) => (
            <button key={id} className={`nav-item ${page === id ? 'active' : ''}`} onClick={() => (id === 'site' ? A.openWebsite() : setPage(id))}>
              <Ico n={icon} /><span>{label}</span>{id === 'site' && <><span className="spacer" /><Ico n="external" size={13} /></>}</button>))}</div>
          <div className="nav-divider" /><div className="sidebar-spacer" />
          <button className="account-mini" onClick={() => setLoggingOut(true)}><div className="avatar">{initials}</div>
            <div className="user-meta"><strong>Account / Logout</strong><span>{user.email}</span></div><Ico n="more" /></button>
        </aside>
        <main className="main">
          <header className="topbar"><div className="crumb">Krono Desktop <span style={{ padding: '0 8px', color: 'var(--subtle)' }}>›</span><strong>{pageName}</strong></div>
            <div className="top-right"><NetBadge online={online} /><div className="mini-account"><div className="avatar">{initials}</div><div><strong>{user.name}</strong><small>Workspace account</small></div></div></div></header>
          <div className="main-inner"><Page go={setPage} />
            <div className="footer">Krono Desktop <span style={{ padding: '0 6px' }}>·</span> Track time. Manage work.</div></div>
        </main>
      </div>
      {!A.noticeSeen && <Modal icon="shield" title="How Krono checks activity" copy="Krono only checks whether the mouse or keyboard was used, so idle timers can pause. It never records what you type or what is on your screen." onClose={() => A.setNoticeSeen(true)}
        actions={<button className="btn primary" onClick={() => A.setNoticeSeen(true)}>Got it</button>}><div className="notice"><div className="notice-icon"><Ico n="info" /></div><div><h3>Warning after {CONFIG.IDLE_WARNING_S / 60} idle minutes</h3><p>If there is still no activity after {CONFIG.IDLE_STOP_S / 60} minutes, running timers pause at your last activity time.</p></div></div></Modal>}
      {loggingOut && <Modal icon="logout" title="Log out of Krono Desktop?" copy="You will return to the app login screen. The Website browser session remains signed in." onClose={() => setLoggingOut(false)}
        actions={<><button className="btn" onClick={() => setLoggingOut(false)}>Cancel</button><button className="btn danger" disabled={busy} onClick={() => { setLoggingOut(false); logout(); }}>Logout</button></>}>
        <div className="notice"><div className="notice-icon"><Ico n="user" /></div><div><h3>{user.email}</h3><p>Before logout, active attendance and unsaved task sessions must be resolved.</p></div></div></Modal>}
      {closing && <Modal icon="warning" title="Finish active work before closing" copy="Krono cannot close while attendance is active or tracked time is unsaved." onClose={() => setClosing(false)}
        actions={<><button className="btn" onClick={() => setClosing(false)}>Keep working</button><button className="btn primary" onClick={() => { setClosing(false); setPage('work'); }}>Go to Attendance</button></>}>
        <div className="notice warning"><div className="notice-icon"><Ico n="shield" /></div><div><h3>Work session still active</h3><p>Save/discard any pending task log and complete Sign Out first. If an action fails, this window stays open.</p></div></div></Modal>}
      {A.pendingSignOut === 'ask' && (
        <Modal wide icon="warning" title="Resolve current task before sign out" copy="A task session has tracked time that has not been saved yet." onClose={() => A.setPendingSignOut(false)}
          actions={<><button className="btn" onClick={() => A.setPendingSignOut(false)}>Cancel</button><button className="btn danger" onClick={() => { A.discardAndSignOut(); A.setPendingSignOut(false); }}>Discard &amp; Sign Out</button>
            <button className="btn primary" onClick={() => { A.setPendingSignOut(true); A.setStopId(firstPending.id); setPage('work'); }}>Save &amp; Sign Out</button></>}>
          {firstPending && <div className="notice warning"><div className="notice-icon"><Ico n="timer" /></div><div><h3>{firstPending.title}</h3><p>{firstPending.project} · {fmt(elapsed(firstPending))} unsaved</p></div></div>}</Modal>)}
      {toastEl}
    </div>
  );
}

export default function App() { return <AppProvider><Shell /></AppProvider>; }
