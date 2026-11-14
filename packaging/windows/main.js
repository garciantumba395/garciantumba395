// Coquille Electron : charge l'application web hébergée. Aucune donnée n'est stockée ni aucune logique métier ici.
const { app, BrowserWindow, shell } = require('electron');
const path = require('path');
const { appUrl } = require('./app-config.json');

const origin = new URL(appUrl).origin;
if (!app.requestSingleInstanceLock()) app.quit();

function createWindow() {
  const win = new BrowserWindow({
    width: 1200, height: 800, minWidth: 380, minHeight: 600, backgroundColor: '#f8fafc',
    webPreferences: { contextIsolation: true, sandbox: true, nodeIntegration: false, webviewTag: false, preload: path.join(__dirname, 'preload.js') },
  });
  win.setMenuBarVisibility(false);
  // Seule l'origine de l'application reste dans la fenêtre ; tout le reste s'ouvre dans le navigateur (https uniquement).
  const guard = (e, url) => { if (new URL(url).origin !== origin) { e.preventDefault(); if (url.startsWith('https://')) shell.openExternal(url); } };
  win.webContents.on('will-navigate', guard);
  win.webContents.setWindowOpenHandler(({ url }) => { if (url.startsWith('https://')) shell.openExternal(url); return { action: 'deny' }; });
  win.loadURL(appUrl);
}

app.whenReady().then(createWindow);
app.on('second-instance', () => { const w = BrowserWindow.getAllWindows()[0]; if (w) { if (w.isMinimized()) w.restore(); w.focus(); } });
app.on('window-all-closed', () => app.quit());
