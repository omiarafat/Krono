import { useRef, useState } from 'react';
import { CONFIG } from './config.js';
import { useApp, PROJECTS, fmt, fmtHM, fmtDur } from './store.jsx';
import { Ico } from './icons.jsx';
import { api, liveAuth } from './api.js';
import { nowMs } from './clock.js';
import { tmz as tm, dmz as dy, hourz } from './time.js';

export const Logo = ({ cls }) => { const { dark } = useApp(); return <img className={cls} src={`./brand/Krono_Logo_${dark ? 'Dark' : 'Light'}.svg`} alt="Krono" />; };
const Notice = ({ icon, title, kind = '', children }) => (
  <div className={`notice ${kind}`}>
    <div className="notice-icon">
      <Ico n={icon} />
    </div>
      <div>
        <h3>{title}</h3>
        <p>{children}</p>
      </div>
  </div>
);
export const Modal = ({ icon, title, copy, wide, onClose, actions, children }) => (
  <div className="modal-overlay" onMouseDown={onClose}>
    <div className={`modal ${wide ? 'wide' : ''}`} role="dialog" aria-label={title} onMouseDown={(e) => e.stopPropagation()}>
      <div className="modal-head">
        <div className="modal-symbol">
          <Ico n={icon} />
        </div>
        <div style={{ flex: 1 }}>
          <h2 className="modal-title">{title}</h2>
          <p className="modal-copy">{copy}</p>
        </div>
        <button className="subtle" style={{ border: 0, background: 'none' }} aria-label="Close" onClick={onClose}>
          <Ico n="x" />
        </button>
      </div>
      <div className="modal-body">{children}</div>
      <div className="modal-actions">{actions}</div>
    </div>
  </div>
);
const Heading = ({ eyebrow, title, text, children }) => (
  <div className="page-heading">
    <div>
      <div className="eyebrow">{eyebrow}</div>
      <h1>{title}</h1>
      <p>{text}</p>
    </div>
    {children}
  </div>
);

export function Auth() {
  const { login, signedIn, notify, online } = useApp();
  const [mode, setMode] = useState('login'); const [f, setF] = useState({ name: '', email: '', password: '' });
  const [code, setCode] = useState(''); const [err, setErr] = useState(''); const [busy, setBusy] = useState(false);
  const [dev, setDev] = useState(false); const ref = useRef(null);
  const set = (k) => (e) => setF({ ...f, [k]: e.target.value });
  const go = (m) => (e) => { e.preventDefault(); setErr(''); setMode(m); };
  const email = f.email.trim().toLowerCase();

  // Same account as the website: everything below goes to the website API (one database, one account per email).
  const signIn = async () => {
    const r = await api('POST', '/auth/login', { email, password: f.password }, false);
    if (r.ok && r.user) return signedIn(r.user);
    if (r.error === 'unverified') { setDev(!!r.devMode); setCode(''); setMode('verify'); setErr(''); return; }
    setErr(r.message || 'Could not sign in.');
  };
  const submit = async (e) => {
    e.preventDefault(); setErr('');
    if (!online) return setErr('You are offline. Connect to the internet to continue.');
    if (!liveAuth()) { // local test mode only (AUTH_MODE: 'mock')
      if (mode === 'verify') return code === '123456' ? login(f.name || f.email.split('@')[0], f.email) : setErr('That code is not correct.');
      if (!f.email.includes('@') || f.password.length < 6) return setErr('Enter a valid email and a password with 6 or more characters.');
      return mode === 'register' ? setMode('verify') : login(f.name || f.email.split('@')[0], f.email);
    }
    setBusy(true);
    try {
      if (mode === 'verify') {
        const r = await api('POST', '/auth/verify', { email, code }, false);
        if (!r.ok) return setErr(r.message || 'That code is not correct.');
        if (f.password) return await signIn(); // verified: sign in right away with the password just typed
        setMode('login'); return notify('success', 'Email verified', 'You can sign in now.');
      }
      if (!email.includes('@') || f.password.length < 8) return setErr('Enter a valid email and a password with 8 or more characters.');
      if (mode === 'register') {
        if (f.name.trim().length < 2) return setErr('Enter your full name.');
        const r = await api('POST', '/auth/register', { name: f.name.trim(), email, password: f.password }, false);
        if (!r.ok) { if (r.error === 'email_exists') setMode('login'); return setErr(r.message || 'Could not create the account.'); }
        setDev(!!r.devMode); setCode(''); return setMode('verify');
      }
      await signIn();
    } finally { setBusy(false); }
  };
  const resend = async () => {
    if (!liveAuth()) return notify('info', 'Code sent', 'Dev mode: the code is still 123456.');
    const r = await api('POST', '/auth/resend', { email }, false);
    if (r.ok) { setDev(!!r.devMode); notify('info', 'Code sent', 'Check your email for the new code.'); } else notify('error', 'Could not send a new code', r.message);
  };
  const T = { login: ['Welcome back', 'Sign in to continue tracking your work.'], register: ['Create your account', 'Use one Krono account for the website and desktop app.'], verify: ['Verify your email', `Enter the one-time code sent to ${f.email}.`] }[mode];
  return (
    <div className="auth-stage">
      <form className="auth-card" onSubmit={submit}>
      <Logo cls="auth-logo" />
      <div className="eyebrow">KRONO ACCOUNT</div>
      <h1>{T[0]}</h1>
      <p>{T[1]}</p>
      {mode === 'verify' ? (
        <div className="auth-fields">
          <div className="verify-code" onClick={() => ref.current.focus()}>
            {[0, 1, 2, 3, 4, 5].map((i) => <span key={i}>{code[i] || ''}</span>)}
          </div>
          <input ref={ref} style={{ position: 'absolute', opacity: 0, pointerEvents: 'none' }} inputMode="numeric" maxLength={6} value={code} onChange={(e) => setCode(e.target.value.replace(/\D/g, ''))} autoFocus aria-label="Verification code" />
          <div className="helper">
            The verification code expires. Request a new code if needed.{(dev || !liveAuth()) && ' Dev mode: the code is 123456.'} </div>
        </div>
      ) : (
        <div className="auth-fields">
          {mode === 'register' && <div className="field">
            <label>Full name</label>
            <input className="input" value={f.name} onChange={set('name')} />
            </div>
          }
          <div className="field">
            <label>Email address</label>
            <input className="input" type="email" value={f.email} onChange={set('email')} />
          </div>
          <div className="field">
            <label>Password</label>
            <input className="input" type="password" value={f.password} onChange={set('password')} />
          </div>
          {mode === 'login' && <div className="between small">
            <span className="muted">Use your verified Krono account.</span>
            <a className="inline-link" href="#" onClick={(e) => e.preventDefault()}>Forgot password?</a>
            </div>
          }
        </div>
      )}
      {err && <div className="helper" style={{ color: 'var(--danger)', marginTop: 12 }} role="alert">{err}</div>}
      <div className="auth-actions">
        <button className="btn primary" type="submit" disabled={busy || (mode === 'verify' && code.length < 6)}>
          {busy ? 'Please wait…' : { login: 'Sign In', register: 'Create Account', verify: 'Verify Email' }[mode]}
        </button>
        {mode === 'verify' && <button className="btn" type="button" onClick={resend}>Resend code</button>}
      </div>
      <div className="auth-foot">
        {mode === 'login' && <>New to Krono? <a href="#" onClick={go('register')}>Create an account</a></>}
        {mode === 'register' && <>Already have an account? <a href="#" onClick={go('login')}>Sign in</a></>}
        {mode === 'verify' && <>Wrong email? <a href="#" onClick={go('register')}>Back to registration</a></>}
      </div>
      <div className="auth-help">
        Your website and Desktop App use the same verified Krono account. Each client keeps its own login session.
      </div>
    </form>
  </div>
  );
}

export function Dashboard({ go }) {
  const { user, session, tasks, stats, elapsed, openWebsite } = useApp();
  const run = tasks.find((t) => t.status === 'running'); const first = user.name.split(' ')[0];
  const h = hourz(nowMs()); const hi = h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening';
  const Metric = ({ l, i, v, s }) => <div className="metric">
      <div className="between">
        <div className="metric-label">{l}</div>
        <div className="metric-icon"><Ico n={i} /></div>
      </div>
      <div className="metric-value">{v}</div>
      <div className="metric-foot">{s}</div>
    </div>;
  const status = { out: 'Signed out', working: 'Working', break: 'On break' }[session.status];
  return (<>
    <Heading eyebrow={`TODAY · ${dy(nowMs()).toUpperCase()}`} title={`${hi}, ${first}`} text="Here is your workday at a glance." />
    <div className="grid3" style={{ marginBottom: 16 }}>
      <Metric l="Net work time" i="clock" v={fmtHM(stats.net)} s="Today · after breaks" />
      <Metric l="Saved task time" i="timer" v={fmtHM(stats.saved)} s="Submitted time logs" />
      <Metric l="Break time" i="coffee" v={fmtDur(stats.brk)} s={`${stats.breakCount} breaks today`} />
    </div>
    <div className="grid2" style={{ marginBottom: 16 }}>
      <section className="panel">
        <div className="between">
          <div>
            <div className="panel-title">
              <Ico n="attendance" />
              <span>Attendance overview</span>
            </div>
            <p className="panel-subtitle">Today’s attendance session</p>
          </div>
          <span className="badge">● {status}</span>
        </div>
        <div className="divider" />
        <div className="stat-line">
          <span className="muted">Signed in</span>
          <strong>{session.signedInAt && session.status !== 'out' ? tm(session.signedInAt) : 'Not signed in'}</strong>
        </div>
        <div className="stat-line">
          <span className="muted">Current status</span>
          <strong>{status}</strong>
        </div>
        <div className="stat-line">
          <span className="muted">Breaks completed</span>
          <strong>{session.breaks.length} breaks · {fmtDur(stats.brk)}</strong>
        </div>
        <div style={{ marginTop: 16 }}>
          <a className="inline-link" href="#" onClick={(e) => { e.preventDefault(); go('work'); }}>View Attendance →</a>
        </div>
      </section>
      <section className="panel"><div className="between"><div><div className="panel-title"><Ico n="timer" /><span>Current task</span></div><p className="panel-subtitle">Live timer status</p></div>
        <span className="badge">{run ? <><span className="dot running" /> Running</> : 'No timer'}</span></div>
        <div style={{ marginTop: 20, fontSize: 15, fontWeight: 780 }}>{run ? run.title : 'No task is running'}</div><div className="muted small" style={{ marginTop: 5 }}>{run ? run.project : 'Start a task from Time Tracking.'}</div>
        <div className="between" style={{ marginTop: 21 }}><div><div className="muted tiny">ELAPSED TIME</div><div style={{ fontSize: 26, fontWeight: 800, letterSpacing: -1, marginTop: 5 }}>{run ? fmt(elapsed(run)) : '00:00:00'}</div></div>
          <a className="inline-link" href="#" onClick={(e) => { e.preventDefault(); go('work'); }}>Manage Timer →</a></div></section>
    </div>
    <div style={{ marginBottom: 16 }}><BreakSummary /></div>
    <section className="panel" style={{ marginBottom: 16 }}>
      <div className="between" style={{ marginBottom: 14 }}><div><div className="panel-title"><Ico n="external" /><span>Manage more on Krono Website</span></div><p className="panel-subtitle">Projects, team and reporting live in your browser.</p></div>
        <button className="btn primary" onClick={openWebsite}><Ico n="external" /> View Website</button></div>
      <div className="grid4">{[['folder', 'Projects & Tasks', 'Create and manage work'], ['users', 'Team & Workspace', 'Members and roles'], ['file', 'Reports & History', 'Saved logs and PDFs'], ['calendar', 'Calendar & Deadlines', 'Schedule and due dates']].map(([i, t, s]) => (
        <div className="row" key={t}><div className="metric-icon"><Ico n={i} /></div><div><strong style={{ fontSize: 10 }}>{t}</strong><div className="muted tiny" style={{ marginTop: 4 }}>{s}</div></div></div>))}</div>
    </section>
    <Notice icon="info" title="Live attendance and timers are controlled here">The Website manages projects, tasks and reports. Start or stop your work timer only from Krono Desktop’s Time Tracking page.</Notice>
  </>);
}

export function Attendance() {
  const { session: s, stats, signIn, signOut, breakStart, breakEnd, hasUnsaved, setPendingSignOut } = useApp();
  const head = <Heading eyebrow="LIVE WORK CONTROL" title="Attendance & Time Tracking" text="Control your work session and track one task at a time." />;
  if (s.status === 'out') return (<>{head}
    <section className="panel empty-state" style={{ minHeight: 280 }}><div className="empty-inner"><div className="empty-art"><Ico n="calendar" /></div>
      <h2>You’re not signed in</h2><p>Sign in to begin today’s attendance session. Krono will record the server timestamp for your session.</p>
      <button className="btn primary" style={{ minWidth: 160 }} onClick={signIn}><Ico n="checkCircle" /> Sign In</button>
      <div className="helper" style={{ marginTop: 12 }}>Sign Out and Break controls appear after a successful sign in.</div></div></section></>);
  const br = s.status === 'break';
  return (<>{head}
    <div className="grid2" style={{ marginBottom: 16 }}>
      {br ? <section className="panel"><div className="between"><div className="panel-title"><Ico n="coffee" /><span>Break in progress</span></div><p className="panel-subtitle">Attendance session remains active.</p></div>
        <div className="attendance-big">On Break</div><div className="muted small">Break #{s.breaks.length + 1} · Started at {tm(s.breakStart)}</div><div className="divider" />
        <div className="metric-label">Current break duration</div><div style={{ fontSize: 30, fontWeight: 800, marginTop: 8 }}>{fmt((nowMs() - s.breakStart) / 1000)}</div></section>
      : <section className="panel"><div className="between"><div className="panel-title"><Ico n="attendance" /><span>Current session</span></div><p className="panel-subtitle">Attendance controls</p></div>
        <div className="attendance-big">Working</div><div className="muted small">Signed in today at {tm(s.signedInAt)}</div><div className="divider" />
        <div className="grid2"><div><div className="metric-label">Net work time</div><div style={{ fontSize: 22, fontWeight: 800, marginTop: 8 }}>{fmtHM(stats.net)}</div></div><div><div className="metric-label">Gross time</div><div style={{ fontSize: 22, fontWeight: 800, marginTop: 8 }}>{fmtHM(stats.gross)}</div></div></div></section>}
      <section className="panel"><div className="panel-title"><Ico n="bolt" /><span>Attendance actions</span></div>
        <p className="panel-subtitle">{br ? 'Sign Out is unavailable during a break.' : 'Only one attendance session can be active.'}</p>
        <div className="stack" style={{ marginTop: 20 }}>
          <button className="btn primary" style={{ justifyContent: 'center' }} onClick={br ? breakEnd : breakStart}><Ico n="coffee" /> {br ? 'End Break' : 'Start Break'}</button>
          <button className={`btn ${br ? '' : 'danger'}`} style={{ justifyContent: 'center' }} disabled={br} onClick={() => (hasUnsaved ? setPendingSignOut('ask') : signOut())}><Ico n="logout" /> Sign Out</button>
          {!br && <button className="btn" disabled>Sign In</button>}</div>
        {br ? <div style={{ marginTop: 14 }}><Notice icon="info" kind="warning" title="Task timer paused">Your running task was automatically paused when this break started. It will not resume automatically.</Notice></div>
          : <div className="helper">All actions are recorded with authoritative server timestamps.</div>}</section>
    </div>
  </>);
}

export function Tracking() {
  const { tasks, session, online, elapsed, start, resume, pause, stop, remove, addTask, stopId, setStopId, cancelStop, setPendingStart, oldDrafts, isOld, resumeReq, guard } = useApp();
  const [add, setAdd] = useState(false); const [pj, setPj] = useState(0); const [tk, setTk] = useState(0);
  const [sw, setSw] = useState(null); const [rm, setRm] = useState(null); const [desc, setDesc] = useState('');
  const can = session.status === 'working';
  const run = tasks.find((t) => t.status === 'running');
  const byId = (id) => tasks.find((t) => t.id === id);
  const begin = (t) => (run && run.id !== t.id ? setSw(t.id) : t.status === 'paused' ? resume(t.id) : start(t.id));
  const ask = (id) => { setDesc(byId(id)?.draft || ''); setStopId(id); };
  const blocked = oldDrafts.length > 0;
  const openAdd = () => { if (blocked) return; if (guard('add a task')) setAdd(true); };
  const stopT = stopId && byId(stopId); const swT = sw && byId(sw); const rmT = rm && byId(rm);
  return (<>
    {tasks.length === 0 ? (
      <section className="panel empty-state"><div className="empty-inner"><div className="empty-art"><Ico n="timer" /></div><h2>No tasks in your tracking list</h2>
        <p>Add an incomplete project task to your list. Adding a task does not start its timer.</p>
        <button className="btn primary" onClick={openAdd}><Ico n="plus" /> Add Task</button>
        <div className="helper" style={{ marginTop: 12 }}>Completed projects and tasks are excluded from the picker.</div></div></section>
    ) : (<>
      <div className="between" style={{ marginBottom: 14 }}><div><div className="panel-title"><Ico n="list" /> Tracking list</div><p className="panel-subtitle">Only one task timer can run at a time.</p></div>
        <button className="btn primary" disabled={blocked} onClick={openAdd}><Ico n="plus" /> Add Task</button></div>
      {blocked && <div style={{ marginBottom: 14 }}><Notice icon="warning" kind="warning" title="Save earlier drafts first">Tasks from a previous day are drafts. Save each one with a description before adding new tasks.</Notice></div>}
      {!online && <div style={{ marginBottom: 14 }}><Notice icon="warning" kind="warning" title="You are offline">Running timers stopped when the connection was lost. Start, sign in and add-task actions are unavailable until you reconnect.</Notice></div>}
      {!can && <div style={{ marginBottom: 14 }}>{session.status === 'break' ? <Notice icon="coffee" kind="warning" title="Break in progress">Start and Resume are paused until you end your break. Your task stays paused.</Notice> : <Notice icon="info" kind="warning" title="Sign in to start a timer">Use Sign In above before starting a task. Timers cannot run during a break.</Notice>}</div>}
      <section className="panel" style={{ padding: 14, marginBottom: 16 }}><div className="task-list">{tasks.map((t) => { const e = elapsed(t); const old = isOld(t) && e > 0; const lab = old ? 'Draft' : { running: 'Running', paused: 'Paused', idle: 'Not started' }[t.status]; return (
        <div className="task-row" key={t.id}>
          <div className="task-main"><span className={`dot ${t.status === 'idle' ? '' : t.status}`} /><div className="task-detail"><div className="task-title">{t.title}</div>
            <div className="task-project">{t.project} <span style={{ padding: '0 4px' }}>·</span><span className="badge gray">{lab}</span>{t.draft && <span className="badge warning" style={{ marginLeft: 6 }}>Not saved yet</span>}{resumeReq?.id === t.id && <span className="badge warning" style={{ marginLeft: 6 }}>Resumes when online</span>}</div></div></div>
          <div className="task-time">{fmt(e)}</div>
          <div className="task-actions">
            {old ? <button className="btn btn-sm primary" onClick={() => ask(t.id)}><Ico n="stop" /> Save</button> : (<>
              {t.status === 'running' ? <button className="btn btn-sm" onClick={pause}><Ico n="pause" /> Pause</button>
                : <button className="btn btn-sm primary" disabled={!can} onClick={() => begin(t)}><Ico n="play" /> {t.status === 'paused' ? 'Resume' : 'Start'}</button>}
              {e > 0 && <button className={`btn btn-sm ${t.status === 'running' ? 'danger' : ''}`} onClick={() => ask(t.id)}><Ico n="stop" /> Stop</button>}
              <button className="btn btn-sm btn-icon" title="Remove" aria-label="Remove task" onClick={() => (e > 0 ? setRm(t.id) : remove(t.id))}><Ico n="trash" /></button></>)}
          </div></div>); })}</div></section>
      <Notice icon="info" title="Timer state is saved safely">Pause keeps the current work session as a draft. Stop requires a non-empty work description; only a successful save removes that item from this list.</Notice>
    </>)}
    {add && <Modal icon="folder" title="Add task to tracking list" copy="Choose a project, then select one of its tasks that is not Completed." onClose={() => setAdd(false)}
      actions={<><button className="btn" onClick={() => setAdd(false)}>Cancel</button><button className="btn primary" onClick={() => { addTask(PROJECTS[pj].name, PROJECTS[pj].tasks[tk]); setAdd(false); }}>Add Task</button></>}>
      <div className="field"><label>Project</label><select className="input" value={pj} onChange={(e) => { setPj(+e.target.value); setTk(0); }}>{PROJECTS.map((p, i) => <option key={p.name} value={i}>{p.name}</option>)}</select></div>
      <div className="field"><label>Task</label><select className="input" value={tk} onChange={(e) => setTk(+e.target.value)}>{PROJECTS[pj].tasks.map((t, i) => <option key={t} value={i}>{t}</option>)}</select></div>
      <div className="helper">Completed projects and tasks do not appear here. Adding a task does not start the timer.</div></Modal>}
    {stopT && <Modal icon="file" title="Save work session" copy="Add a short description of what you completed before stopping this timer." onClose={cancelStop}
      actions={<><button className="btn" onClick={cancelStop}>Cancel</button><button className={`btn primary ${desc.trim() ? '' : 'disabled'}`} disabled={!desc.trim()} onClick={() => { stop(stopT.id, desc.trim()); setDesc(''); }}>Save &amp; Stop</button></>}>
      <Notice icon="timer" title={stopT.title}>{stopT.project} · Duration so far: {fmt(elapsed(stopT))}</Notice>
      <div className="field"><label>Work description <span style={{ color: 'var(--danger)' }}>*</span></label><textarea className="input textarea" placeholder="Describe what you worked on…" value={desc} onChange={(e) => setDesc(e.target.value)} autoFocus />
        <div className="helper">Required. The Save button stays disabled until a description is entered.</div></div></Modal>}
    {swT && run && <Modal wide icon="warning" title="Another timer is running" copy={`${run.title} is currently running. Pause or save it before switching tasks.`} onClose={() => setSw(null)}
      actions={<><button className="btn" onClick={() => setSw(null)}>Close</button><button className="btn" onClick={() => { start(swT.id); setSw(null); }}>Pause current &amp; Switch</button><button className="btn primary" onClick={() => { setPendingStart(swT.id); ask(run.id); setSw(null); }}>Stop current…</button></>}>
      <Notice kind="warning" icon="timer" title={run.title}>{run.project} · {fmt(elapsed(run))} elapsed</Notice>
      <Notice icon="play" title="Requested task">{swT.title} · {swT.project}</Notice></Modal>}
    {rmT && <Modal icon="warning" title="Remove task from list?" copy="This task has an unsaved work session. Choose what happens to the tracked time." onClose={() => setRm(null)}
      actions={<><button className="btn" onClick={() => setRm(null)}>Cancel</button><button className="btn danger" onClick={() => { remove(rmT.id); setRm(null); }}>Discard &amp; Remove</button><button className="btn primary" disabled={!online} onClick={() => { ask(rmT.id); setRm(null); }}>Save &amp; Remove</button></>}>
      <Notice kind="warning" icon="warning" title="Unsaved time will not appear in reports">{rmT.title} · {fmt(elapsed(rmT))} tracked. The source task will not be deleted.</Notice></Modal>}
  </>);
}

export function Settings() {
  const { theme, setTheme } = useApp();
  const O = [['light', '☼ Light', 'Use Krono’s existing light theme.'], ['dark', '◐ Dark', 'Use Krono’s existing dark theme.'], ['system', '▧ System', 'Follow Windows appearance.']];
  return (<>
    <Heading eyebrow="PREFERENCES" title="Settings" text="Choose how Krono Desktop looks." />
    <section className="panel" style={{ maxWidth: 900 }}><div className="panel-title"><Ico n="settings" /><span>Appearance</span></div><p className="panel-subtitle">Your theme preference is saved for this app.</p>
      <div className="appearance-options">{O.map(([m, l, d]) => (
        <div key={m} className="theme-option" role="button" tabIndex={0} onClick={() => setTheme(m)} onKeyDown={(e) => e.key === 'Enter' && setTheme(m)} style={theme === m ? { borderColor: 'var(--brand)', boxShadow: '0 0 0 2px var(--brand-soft) inset', cursor: 'pointer' } : { cursor: 'pointer' }}>
          <div className={`theme-preview ${m === 'light' ? '' : m}`}><div className="tp-rail" /><div className="tp-content"><div className="tp-lines" /><div className="tp-lines" style={{ width: '55%' }} /></div></div>
          <strong>{l} {theme === m && <span className="checkmark">✓ Selected</span>}</strong><p>{d}</p></div>))}</div>
      <div style={{ marginTop: 18 }}><Notice icon="info" title="Theme applies immediately">Only the existing Krono Light/Dark tokens are used. No additional settings are included in this plan.</Notice></div></section>
  </>);
}

export function BreakSummary() {
  const { session: s, stats } = useApp();
  const Hist = ({ i, a, b, d, first }) => (
    <div className="between" style={{ padding: '12px 0', borderTop: first ? 0 : '1px solid var(--border)' }}><div className="row"><span className="history-no">{i}</span><div className="history-copy"><strong>Break #{i}</strong><small>{dy(a)} · {tm(a)} – {b ? tm(b) : 'In progress'}</small></div></div><span className="duration">{d}</span></div>);
  const history = (title, sub) => (
    <section className="panel"><div className="panel-title"><Ico n="history" /><span>{title}</span></div><p className="panel-subtitle">{sub}</p>
      {s.breaks.map((b, i) => <Hist key={i} i={i + 1} a={b.start} b={b.end} d={fmtDur(((b.end ?? nowMs()) - b.start) / 1000)} first={i === 0} />)}
      {s.status === 'break' && <Hist i={s.breaks.length + 1} a={s.breakStart} d={fmtDur(stats.brk - s.breaks.reduce((x, b) => x + ((b.end ?? nowMs()) - b.start) / 1000, 0), true)} first={!s.breaks.length} />}
      {!s.breaks.length && s.status !== 'break' && <p className="muted small" style={{ marginTop: 14 }}>No breaks recorded yet.</p>}
      <div className="stat-line" style={{ marginTop: 8, borderTop: '1px solid var(--border)' }}><span className="muted">Offline time</span><strong>{fmtDur(stats.offlineTotal)} · {stats.offlineCount} times</strong></div></section>);
  const br = s.status === 'break';
  return history(br ? 'Today’s break history' : 'Break summary', br ? 'Completed breaks and the active break are shown.' : 'Each break is recorded separately.');
}

export const Work = () => (<><Attendance /><Tracking /></>);
