// Solisend - version de RECETTE autonome. Lance le serveur PHP embarqué (127.0.0.1 uniquement), une base SQLite locale,
// des données fictives et le fournisseur SMS SIMULÉ. Aucun SMS réel, aucune clé, aucun accès réseau sortant requis.
const { app, BrowserWindow, dialog, shell } = require('electron');
const { spawn, spawnSync } = require('child_process');
const crypto = require('crypto');
const fs = require('fs');
const net = require('net');
const path = require('path');

const res = app.isPackaged ? process.resourcesPath : path.join(__dirname, 'stage');
const phpExe = path.join(res, 'php', 'php.exe');
const appSrc = path.join(res, 'app');
const data = path.join(app.getPath('userData'), 'solisend-data');
let appDir = '';
let server = null, scheduler = null, port = 0, quitting = false;

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
    APP_URL: `http://127.0.0.1:${port}`, APP_TIMEZONE: 'Africa/Kinshasa', APP_LOCALE: 'fr', APP_FALLBACK_LOCALE: 'fr',
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

async function start() {
  fs.mkdirSync(data, { recursive: true });
  prepareApp();
  const db = path.join(data, 'database.sqlite');
  if (!fs.existsSync(db)) fs.writeFileSync(db, '');
  port = await freePort();
  const e = env();

  const m = artisan(['migrate', '--force'], e);
  if (m.status !== 0) throw new Error('Migration de la base impossible :\n' + (m.stderr || m.stdout));

  // Données fictives au premier lancement uniquement ; les identifiants sont gardés dans un fichier lisible par l'utilisateur.
  const accounts = path.join(data, 'COMPTES-DEMO.txt');
  if (!fs.existsSync(accounts)) {
    const s = artisan(['db:seed', '--class=DemoSeeder', '--force'], e);
    if (s.status !== 0) throw new Error('Données de démonstration impossibles :\n' + (s.stderr || s.stdout));
    const pw = (s.stdout.match(/Mot de passe[^:]*:\s*(\S+)/) || [])[1] || '(voir journal)';
    fs.writeFileSync(accounts, `Solisend Recette - comptes de démonstration (données entièrement fictives)\r\n\r\nMot de passe de tous les comptes : ${pw}\r\n\r\n` +
      'proprio-demo@example.test  : propriétaire de « Boutique Démo »\r\nadmin-demo@example.test    : administrateur de la plateforme\r\nmembre-demo@example.test   : membre\r\nautre-demo@example.test    : autre entreprise (test d\'isolation)\r\n');
  }

  server = spawn(phpExe, [...phpArgs, '-S', `127.0.0.1:${port}`, path.join(appDir, 'vendor', 'laravel', 'framework', 'src', 'Illuminate', 'Foundation', 'resources', 'server.php')],
    { cwd: path.join(appDir, 'public'), env: { ...e, PHP_CLI_SERVER_WORKERS: '4' }, windowsHide: true });
  server.on('exit', () => { if (!quitting) { dialog.showErrorBox('Solisend', 'Le serveur local s\'est arrêté.'); app.quit(); } });
  // Tâches planifiées (campagnes programmées) : une passe par minute.
  scheduler = setInterval(() => artisan(['schedule:run'], e), 60000);

  for (let i = 0; i < 50; i++) { // attend que le serveur réponde
    if (await new Promise((ok) => net.connect(port, '127.0.0.1').once('connect', function () { this.destroy(); ok(true); }).once('error', () => ok(false)))) break;
    await new Promise((r) => setTimeout(r, 200));
  }
  return accounts;
}

async function main() {
  let accounts;
  try { accounts = await start(); } catch (err) { dialog.showErrorBox('Solisend - démarrage impossible', String(err.message || err).slice(0, 2000)); return app.quit(); }
  const win = new BrowserWindow({ width: 1200, height: 800, minWidth: 380, minHeight: 600, backgroundColor: '#ffffff', title: 'Solisend (recette)',
    webPreferences: { contextIsolation: true, sandbox: true, nodeIntegration: false, preload: path.join(__dirname, 'preload.js') } });
  win.setMenuBarVisibility(false);
  const origin = `http://127.0.0.1:${port}`;
  win.webContents.on('will-navigate', (ev, url) => { if (new URL(url).origin !== origin) { ev.preventDefault(); if (url.startsWith('https://')) shell.openExternal(url); } });
  win.webContents.setWindowOpenHandler(({ url }) => { if (url.startsWith('https://')) shell.openExternal(url); return { action: 'deny' }; });
  win.loadURL(origin + '/connexion');
  win.webContents.once('did-finish-load', () => dialog.showMessageBox(win, { type: 'info', title: 'Solisend - recette',
    message: 'Version de RECETTE : données fictives, SMS simulés (aucun envoi réel).', detail: 'Les comptes et le mot de passe de démonstration sont dans :\n' + accounts, buttons: ['OK', 'Ouvrir le fichier'] })
    .then((r) => { if (r.response === 1) shell.openPath(accounts); }));
}

app.whenReady().then(main);
app.on('before-quit', () => { quitting = true; if (scheduler) clearInterval(scheduler); if (server) server.kill(); });
app.on('window-all-closed', () => app.quit());
