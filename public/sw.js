/*
 | Service worker minimal. RÈGLE DE CONFIDENTIALITÉ : aucune page HTML, aucune réponse Livewire/API et aucune donnée
 | d'utilisateur n'est jamais mise en cache. Seuls sont conservés : la page « hors connexion », les icônes et les
 | ressources statiques versionnées (/build/assets/*).
 */
const VERSION = 'v1';
const STATIC = `static-${VERSION}`;
const PRECACHE = ['/offline.html', '/icons/icon-192.png', '/icons/icon-512.png'];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(STATIC).then((c) => c.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== STATIC).map((k) => caches.delete(k)))).then(() => self.clients.claim()));
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  const url = new URL(req.url);
  if (req.method !== 'GET' || url.origin !== self.location.origin) return;

  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(() => caches.match('/offline.html')));
    return;
  }
  if (url.pathname.startsWith('/build/assets/') || url.pathname.startsWith('/icons/')) {
    e.respondWith(caches.match(req).then((hit) => hit || fetch(req).then((res) => {
      if (res.ok) { const copy = res.clone(); caches.open(STATIC).then((c) => c.put(req, copy)); }
      return res;
    })));
  }
});
