const { app, BrowserWindow, ipcMain, nativeTheme, powerMonitor, screen, shell } = require('electron');
const path = require('path');

// One app instance only (one timer per user).
if (!app.requestSingleInstanceLock()) app.quit();

// krono:// links from the website ("Open App").
app.setAsDefaultProtocolClient('krono');

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
  createWindow();
  powerMonitor.on('suspend', () => win && win.webContents.send('power', 'suspend'));
  powerMonitor.on('resume', () => win && win.webContents.send('power', 'resume'));
});

app.on('second-instance', () => {
  if (!win) return;
  if (win.isMinimized()) win.restore();
  win.focus();
});
app.on('window-all-closed', () => app.quit());
