/* service-worker.js — Tally Client PWA */
const CACHE = 'tally-client-v1';
const PRECACHE = [
  './client.php',
  './client.css',
  './client.js',
  './i18n.js',
  './manifest.webmanifest',
];

self.addEventListener('install', e => {
  e.waitUntil(
    caches.open(CACHE).then(c => c.addAll(PRECACHE)).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', e => {
  /* Network first for PHP pages, cache first for assets */
  const url = new URL(e.request.url);
  const isAsset = url.pathname.match(/\.(css|js|png|jpg|jpeg|webp|woff2?)$/);
  if(isAsset){
    e.respondWith(
      caches.match(e.request).then(cached => cached || fetch(e.request).then(res => {
        const clone = res.clone();
        caches.open(CACHE).then(c => c.put(e.request, clone));
        return res;
      }))
    );
  } else {
    e.respondWith(
      fetch(e.request).catch(() => caches.match(e.request))
    );
  }
});