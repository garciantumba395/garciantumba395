// Solisend - version de RECETTE autonome. Lance le serveur PHP embarqué (127.0.0.1 uniquement), une base SQLite locale,
// des données fictives et le fournisseur SMS SIMULÉ. Aucun SMS réel, aucune clé, aucun accès réseau sortant requis.
const { app, BrowserWindow, Menu, dialog, shell } = require('electron');
const { spawn, spawnSync } = require('child_process');
const crypto = require('crypto');
const fs = require('fs');
const net = require('net');
const os = require('os');
const path = require('path');

const res = app.isPackaged ? process.resourcesPath : path.join(__dirname, 'stage');
const phpExe = path.join(res, 'php', 'php.exe');
const appSrc = path.join(res, 'app');
const data = path.join(app.getPath('userData'), 'solisend-data');
let appDir = '';
let server = null, scheduler = null, port = 0, quitting = false, win = null, accountsFile = '';
const LAN_PORT = 8787;
const lanFlag = () => path.join(data, 'lan-enabled');
const lanOn = () => fs.existsSync(lanFlag());
// Première adresse IPv4 privée de la machine (pour le téléphone sur le même Wi-Fi).
function lanIp() {
  const all = Object.values(os.networkInterfaces()).flat().filter((i) => i && i.family === 'IPv4' && !i.internal);
  const priv = all.find((i) => /^(192\.168\.|10\.|172\.(1[6-9]|2\d|3[01])\.)/.test(i.address));
  return (priv || all[0] || { address: '127.0.0.1' }).address;
}

if (!app.requestSingleInstanceLock()) app.quit();

const freePort = () => new Promise((ok, ko) => { const s = net.createServer(); s.once('error', ko); s.listen(0, '127.0.0.1', () => { const p = s.address().port; s.close(() => ok(p)); }); });
const mk = (...p) => fs.mkdirSync(path.join(data, ...p), { recursive: true });

function env() {
  const keyFile = path.join(data, 'app.key');
  if (!fs.existsSync(keyFile)) fs.writeFileSync(keyFile, 'base64:' + crypto.randomBytes(32).toString('base64'));
  return {
    ...process.env,
    PHPRC: path.join(res, 'php'), PHP_INI_SCAN_DIR: '',
    APP_NAME: 'Solisend', APP_ENV: 'local', APP_DEBUG: 'false', APP_KEY: fs.readFileSync(keyFile, 'utf8').trim(),
    APP_URL: lanOn() ? `http://${lanIp()}:${port}` : `http://127.0.0.1:${port}`, APP_TIMEZONE: 'Africa/Kinshasa', APP_LOCALE: 'fr', APP_FALLBACK_LOCALE: 'fr',
    DB_CONNECTION: 'sqlite', DB_DATABASE: path.join(data, 'database.sqlite'),
    SESSION_DRIVER: 'file', CACHE_STORE: 'file', QUEUE_CONNECTION: 'sync', MAIL_MAILER: 'log', LOG_CHANNEL: 'single', LOG_LEVEL: 'warning',
    SMS_PROVIDER: 'fake', SMS_SENDING_ENABLED: 'false',
    SECURITY_REQUIRE_EMAIL_VERIFICATION: 'false', SECURITY_REQUIRE_2FA_FOR_ADMINS: 'false',
  };
}

const phpArgs = ['-d', 'extension_dir=' + path.join(res, 'php', 'ext')];
const artisan = (args, e) => spawnSync(phpExe, [...phpArgs, 'artisan', ...args], { cwd: appDir, env: e, encoding: 'utf8', windowsHide: true });

function prepareApp() {
  // Le dossier d'installation peut être en lecture seule : on travaille sur une copie dans le profil de l'utilisateur
  // (recopiée à chaque nouvelle version). Les données (base, clé, comptes) restent à part et ne sont jamais effacées.
  appDir = path.join(data, 'app');
  const stamp = path.join(appDir, '.stamp');
  const want = app.getVersion();
  if (!fs.existsSync(stamp) || fs.readFileSync(stamp, 'utf8') !== want) {
    fs.rmSync(appDir, { recursive: true, force: true });
    fs.cpSync(appSrc, appDir, { recursive: true });
    for (const d of [['storage', 'app'], ['storage', 'framework', 'cache', 'data'], ['storage', 'framework', 'sessions'], ['storage', 'framework', 'views'], ['storage', 'logs'], ['bootstrap', 'cache']]) fs.mkdirSync(path.join(appDir, ...d), { recursive: true });
    fs.writeFileSync(stamp, want);
  }
}

const portFree = (p, host) => new Promise((ok) => { const s = net.createServer(); s.once('error', () => ok(false)); s.listen(p, host, () => s.close(() => ok(true))); });

// Prépare l'application, la base et les données fictives (idempotent).
async function prepare() {
  fs.mkdirSync(data, { recursive: true });
  prepareApp();
  const db = path.join(data, 'database.sqlite');
  if (!fs.existsSync(db)) fs.writeFileSync(db, '');
  port = lanOn() && (await portFree(LAN_PORT, '0.0.0.0')) ? LAN_PORT : await freePort();
  const e = env();
  const m = artisan(['migrate', '--force'], e);
  if (m.status !== 0) throw new Error('Migration de la base impossible :\n' + (m.stderr || m.stdout));

  // Données fictives au premier lancement uniquement ; les identifiants sont gardés dans un fichier lisible par l'utilisateur.
  accountsFile = path.join(data, 'COMPTES-DEMO.txt');
  if (!fs.existsSync(accountsFile)) {
    const s = artisan(['db:seed', '--class=DemoSeeder', '--force'], e);
    if (s.status !== 0) throw new Error('Données de démonstration impossibles :\n' + (s.stderr || s.stdout));
    const pw = (s.stdout.match(/Mot de passe[^:]*:\s*(\S+)/) || [])[1] || '(voir journal)';
    fs.writeFileSync(accountsFile, `Solisend Recette - comptes de démonstration (données entièrement fictives)\r\n\r\nMot de passe de tous les comptes : ${pw}\r\n\r\n` +
      'proprio-demo@example.test  : propriétaire de « Boutique Démo »\r\nadmin-demo@example.test    : administrateur de la plateforme\r\nmembre-demo@example.test   : membre\r\nautre-demo@example.test    : autre entreprise (test d\'isolation)\r\n');
  }
  return e;
}

// Démarre le serveur PHP : 127.0.0.1 par défaut ; 0.0.0.0 seulement si le mode « téléphone » est activé.
async function launch(e) {
  const host = port === LAN_PORT && lanOn() ? '0.0.0.0' : '127.0.0.1';
  server = spawn(phpExe, [...phpArgs, '-S', `${host}:${port}`, path.join(appDir, 'vendor', 'laravel', 'framework', 'src', 'Illuminate', 'Foundation', 'resources', 'server.php')],
    { cwd: path.join(appDir, 'public'), env: e, windowsHide: true });
  const mine = server;
  server.on('exit', () => { if (!quitting && server === mine) { dialog.showErrorBox('Solisend', 'Le serveur local s\'est arrêté.'); app.quit(); } });
  scheduler = setInterval(() => artisan(['schedule:run'], e), 60000); // campagnes programmées : une passe par minute
  for (let i = 0; i < 50; i++) { // attend que le serveur réponde
    if (await new Promise((ok) => net.connect(port, '127.0.0.1').once('connect', function () { this.destroy(); ok(true); }).once('error', () => ok(false)))) break;
    await new Promise((r) => setTimeout(r, 200));
  }
}

function stop() {
  if (scheduler) { clearInterval(scheduler); scheduler = null; }
  if (server) { const s = server; server = null; s.kill(); }
}

async function togglePhoneMode() {
  const enabling = !lanOn();
  const ask = await dialog.showMessageBox(win, enabling
    ? { type: 'warning', title: 'Connecter un téléphone', buttons: ['Activer', 'Annuler'], defaultId: 1, cancelId: 1,
        message: 'Autoriser un téléphone du même Wi-Fi à ouvrir cette application ?',
        detail: 'Le téléphone et cet ordinateur doivent être sur le même réseau Wi-Fi.\nWindows peut demander d\'autoriser le pare-feu : choisissez « Réseaux privés ».\nLes données sont fictives et les SMS simulés, mais toute personne de ce réseau pourrait ouvrir la page de connexion tant que ce mode est actif. Désactivez-le après vos tests.' }
    : { type: 'question', title: 'Connecter un téléphone', buttons: ['Désactiver', 'Annuler'], defaultId: 1, cancelId: 1, message: 'Désactiver l\'accès depuis le téléphone ?' });
  if (ask.response !== 0) return;
  if (enabling) fs.writeFileSync(lanFlag(), '1'); else fs.rmSync(lanFlag(), { force: true });
  stop();
  const e = await prepare();
  await launch(e);
  await win.loadURL(`http://127.0.0.1:${port}/connexion`);
  if (lanOn()) {
    const url = `http://${lanIp()}:${port}`;
    const note = port === LAN_PORT ? '' : '\n\n(Le port 8787 était occupé : adresse différente à chaque lancement.)';
    dialog.showMessageBox(win, { type: 'info', title: 'Téléphone', message: 'Adresse à saisir dans l\'application Android :', detail: url + note, buttons: ['OK', 'Copier l\'adresse'] })
      .then((r) => { if (r.response === 1) require('electron').clipboard.writeText(url); });
  }
}

function buildMenu() {
  Menu.setApplicationMenu(Menu.buildFromTemplate([
    { label: 'Recette', submenu: [
      { label: 'Connecter un téléphone (Wi-Fi)…', click: () => togglePhoneMode().catch((err) => dialog.showErrorBox('Solisend', String(err.message || err))) },
      { label: 'Ouvrir les comptes de démonstration', click: () => shell.openPath(accountsFile) },
      { type: 'separator' }, { role: 'quit', label: 'Quitter' } ] },
    { label: 'Affichage', submenu: [{ role: 'reload', label: 'Actualiser' }, { role: 'togglefullscreen', label: 'Plein écran' }, { role: 'zoomIn' }, { role: 'zoomOut' }, { role: 'resetZoom' }] },
  ]));
}

async function main() {
  try { await launch(await prepare()); } catch (err) { dialog.showErrorBox('Solisend - démarrage impossible', String(err.message || err).slice(0, 2000)); return app.quit(); }
  buildMenu();
  win = new BrowserWindow({ width: 1200, height: 800, minWidth: 380, minHeight: 600, backgroundColor: '#ffffff', title: 'Solisend (recette)',
    webPreferences: { contextIsolation: true, sandbox: true, nodeIntegration: false, preload: path.join(__dirname, 'preload.js') } });
  const isLocal = (url) => { try { const u = new URL(url); return u.hostname === '127.0.0.1' && Number(u.port) === port; } catch { return false; } };
  win.webContents.on('will-navigate', (ev, url) => { if (!isLocal(url)) { ev.preventDefault(); if (url.startsWith('https://')) shell.openExternal(url); } });
  win.webContents.setWindowOpenHandler(({ url }) => { if (url.startsWith('https://')) shell.openExternal(url); return { action: 'deny' }; });
  win.loadURL(`http://127.0.0.1:${port}/connexion`);
  win.webContents.once('did-finish-load', () => dialog.showMessageBox(win, { type: 'info', title: 'Solisend - recette',
    message: 'Version de RECETTE : données fictives, SMS simulés (aucun envoi réel).', detail: 'Les comptes et le mot de passe de démonstration sont dans :\n' + accountsFile + '\n\nPour tester avec un téléphone : menu Recette > Connecter un téléphone (Wi-Fi).', buttons: ['OK', 'Ouvrir le fichier'] })
    .then((r) => { if (r.response === 1) shell.openPath(accountsFile); }));
}

app.whenReady().then(main);
app.on('before-quit', () => { quitting = true; stop(); });
app.on('window-all-closed', () => app.quit());
