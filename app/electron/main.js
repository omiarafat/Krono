const { app, BrowserWindow, ipcMain, nativeTheme, net, powerMonitor, safeStorage, screen, shell } = require('electron');
const fs = require('fs');
const path = require('path');

// One app instance only (one timer per user).
const gotLock = app.requestSingleInstanceLock();
if (!gotLock) app.quit();

// krono:// links from the website ("Open Krono Desktop" -> automatic sign-in).
// In development (electron .) Windows needs the script path as well.
if (process.defaultApp && process.argv[1]) app.setAsDefaultProtocolClient('krono', process.execPath, [path.resolve(process.argv[1])]);
else app.setAsDefaultProtocolClient('krono');

// ---- krono://auth?code=<64 hex>: one-time sign-in code from the website ----
let pendingLink = null; // kept until the app window asks for it (app was closed / still loading)
function handleLink(u) {
  const m = /^krono:\/\/auth\/?\?code=([a-f0-9]{64})$/i.exec(String(u || '').trim());
  if (!m) return;
  pendingLink = m[1].toLowerCase();
  if (!win || win.isDestroyed()) return; // app starting: the window will ask for pendingLink
  if (win.isMinimized()) win.restore();
  win.show(); win.focus();
  if (!win.webContents.isLoading()) { win.webContents.send('deeplink', pendingLink); pendingLink = null; }
}
ipcMain.handle('deeplink:take', () => { const c = pendingLink; pendingLink = null; return c; });
app.on('open-url', (e, u) => { e.preventDefault(); handleLink(u); }); // macOS
const firstLink = process.argv.find((a) => String(a).startsWith('krono://'));

// ---- Login token: stays in this (main) process, encrypted with the OS (Windows DPAPI). The window never sees it. ----
const tokenPath = (ext) => path.join(app.getPath('userData'), `session.${ext}`);
function saveToken(t) {
  try {
    clearToken();
    if (safeStorage.isEncryptionAvailable()) fs.writeFileSync(tokenPath('enc'), safeStorage.encryptString(t));
    else fs.writeFileSync(tokenPath('txt'), t, { mode: 0o600 });
  } catch (_) { /* token just stays in memory for this run */ }
  memToken = t;
}
function loadToken() {
  if (memToken) return memToken;
  try {
    if (fs.existsSync(tokenPath('enc'))) return (memToken = safeStorage.decryptString(fs.readFileSync(tokenPath('enc'))));
    if (fs.existsSync(tokenPath('txt'))) return (memToken = fs.readFileSync(tokenPath('txt'), 'utf8').trim());
  } catch (_) { /* unreadable: treat as signed out */ }
  return '';
}
function clearToken() { memToken = ''; for (const e of ['enc', 'txt']) { try { fs.unlinkSync(tokenPath(e)); } catch (_) {} } }
let memToken = '';

// API calls go through here, so the website needs no CORS rules and the token never reaches the page.
ipcMain.handle('api:request', async (_e, { base, method = 'GET', path: p, body, auth = true } = {}) => {
  if (!/^https?:\/\//.test(String(base)) || !String(p).startsWith('/')) return { ok: false, error: 'bad_request', message: 'Bad request.' };
  const headers = { Accept: 'application/json', 'Content-Type': 'application/json' };
  if (auth) { const t = loadToken(); if (t) headers.Authorization = `Bearer ${t}`; }
  const ac = new AbortController(); const timer = setTimeout(() => ac.abort(), 15000);
  try {
    const r = await net.fetch(base + p, { method, headers, body: body ? JSON.stringify(body) : undefined, signal: ac.signal });
    let j = {}; try { j = await r.json(); } catch (_) { /* not JSON */ }
    if (typeof j.token === 'string') { saveToken(j.token); delete j.token; }
    if (auth && r.status === 401) clearToken();
    return { status: r.status, ...j };
  } catch (_) {
    return { ok: false, error: 'network', message: 'Cannot reach the Krono server. Check your internet connection.' };
  } finally {
    clearTimeout(timer);
    if (p === '/auth/logout') clearToken();
  }
});

// TODO: replace with exact values from tailwind-v4-theme.css tokens.
const BAR = {
  light: { color: '#FFFFFF', symbolColor: '#617166' },
  dark: { color: '#14251B', symbolColor: '#B6C8BA' },
};
const overlay = () => ({ ...(nativeTheme.shouldUseDarkColors ? BAR.dark : BAR.light), height: 38 });

let win = null;
let allowClose = false;

function createWindow() {
  win = new BrowserWindow({
    width: 1280,
    height: 820,
    minWidth: 1100,
    minHeight: 700,
    title: 'Krono Desktop',
    icon: path.join(__dirname, '../build/icon.png'),
    titleBarStyle: 'hidden', // custom bar look, native Windows buttons + Snap Layouts
    titleBarOverlay: overlay(),
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration: false,
      backgroundThrottling: false, // keep 1 s timers running when minimized
    },
  });
  win.setMenuBarVisibility(false);
  win.webContents.on('did-finish-load', () => { if (pendingLink) { win.webContents.send('deeplink', pendingLink); pendingLink = null; } });
  win.webContents.setWindowOpenHandler(({ url }) => { if (/^https?:/.test(url)) shell.openExternal(url); return { action: 'deny' }; });

  if (app.isPackaged) {
    win.loadFile(path.join(__dirname, '../dist/index.html'));
  } else {
    const url = 'http://localhost:5173';
    win.loadURL(url);
    // Vite may not be ready yet: retry instead of showing a blank window.
    win.webContents.on('did-fail-load', () => setTimeout(() => win && win.loadURL(url), 1000));
    // Ctrl+Shift+I opens DevTools (dev only) to see errors.
    win.webContents.on('before-input-event', (_e, i) => {
      if (i.control && i.shift && i.key.toLowerCase() === 'i') win.webContents.toggleDevTools();
    });
  }

  // Close guard: renderer decides (sign-out / drafts), then calls window:close.
  win.on('closed', () => { win = null; if (toast && !toast.isDestroyed()) toast.destroy(); });
  win.on('close', (e) => {
    if (allowClose) return;
    e.preventDefault();
    win.webContents.send('close:requested');
  });
}

nativeTheme.on('updated', () => win && win.setTitleBarOverlay(overlay()));

ipcMain.handle('theme:set', (_e, mode) => {
  if (['light', 'dark', 'system'].includes(mode)) nativeTheme.themeSource = mode;
});
ipcMain.handle('window:title', (_e, t) => win && win.setTitle(String(t).slice(0, 120)));
ipcMain.handle('window:close', () => { allowClose = true; win && win.close(); });

// Idle warning: a small notification-style window at bottom-right (main app stays
// untouched). Our own window is not muted by Focus Assist / Do Not Disturb.
let toast = null;
let toastDismissed = false; // user pressed X: stay hidden until activity resumes
function ensureToast() {
  if (toast && !toast.isDestroyed()) return toast;
  const { x, y, width, height } = screen.getPrimaryDisplay().workArea;
  toast = new BrowserWindow({
    width: 360, height: 112, x: x + width - 376, y: y + height - 128,
    frame: false, resizable: false, movable: false, minimizable: false, maximizable: false,
    skipTaskbar: true, focusable: false, show: false, alwaysOnTop: true,
    webPreferences: { preload: path.join(__dirname, 'toast-preload.js'), contextIsolation: true },
  });
  toast.setAlwaysOnTop(true, 'screen-saver');
  toast.loadFile(path.join(__dirname, 'idle-toast.html'));
  return toast;
}
ipcMain.handle('idle:warning', (_e, { show, secondsLeft }) => {
  if (show) {
    if (toastDismissed) return;
    const t = ensureToast();
    const go = () => { t.webContents.send('toast:seconds', secondsLeft); if (!t.isVisible()) t.showInactive(); };
    if (t.webContents.isLoading()) t.webContents.once('did-finish-load', go); else go();
  } else {
    toastDismissed = false; // activity resumed: next warning can show again
    if (toast && !toast.isDestroyed() && toast.isVisible()) toast.hide();
  }
});
// X button: hide the popup only. Timers still stop at IDLE_STOP_S.
ipcMain.handle('toast:close', () => {
  toastDismissed = true;
  if (toast && !toast.isDestroyed()) toast.hide();
});

// System-wide idle seconds (only a number; never keys or screen content).
setInterval(() => {
  if (win && !win.isDestroyed()) win.webContents.send('idle:tick', powerMonitor.getSystemIdleTime());
}, 5000);

app.whenReady().then(() => {
  if (!gotLock) return;
  createWindow();
  if (firstLink) handleLink(firstLink);
  powerMonitor.on('suspend', () => win && win.webContents.send('power', 'suspend'));
  powerMonitor.on('resume', () => win && win.webContents.send('power', 'resume'));
});

app.on('second-instance', (_e, argv) => {
  const link = (argv || []).find((a) => String(a).startsWith('krono://'));
  if (link) return handleLink(link);
  if (!win) return;
  if (win.isMinimized()) win.restore();
  win.focus();
});
app.on('window-all-closed', () => app.quit());
