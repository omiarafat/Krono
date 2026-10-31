const { contextBridge, ipcRenderer } = require('electron');
const listen = (ch, cb) => {
  const f = (_e, ...a) => cb(...a);
  ipcRenderer.on(ch, f);
  return () => ipcRenderer.removeListener(ch, f);
};
contextBridge.exposeInMainWorld('krono', {
  setTheme: (m) => ipcRenderer.invoke('theme:set', m),
  setTitle: (t) => ipcRenderer.invoke('window:title', t),
  idleWarning: (o) => ipcRenderer.invoke('idle:warning', o),
  allowClose: () => ipcRenderer.invoke('window:close'),
  onIdleTick: (cb) => listen('idle:tick', cb),
  onPower: (cb) => listen('power', cb),
  onCloseRequested: (cb) => listen('close:requested', cb),
  // Website API (token is kept in the main process) and krono://auth sign-in links
  api: (o) => ipcRenderer.invoke('api:request', o),
  takeDeepLink: () => ipcRenderer.invoke('deeplink:take'),
  onDeepLink: (cb) => listen('deeplink', cb),
});
