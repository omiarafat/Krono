const { contextBridge, ipcRenderer } = require('electron');
contextBridge.exposeInMainWorld('kronoToast', {
  close: () => ipcRenderer.invoke('toast:close'),
  onSeconds: (cb) => ipcRenderer.on('toast:seconds', (_e, s) => cb(s)),
});
