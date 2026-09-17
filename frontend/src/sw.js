/**
 * Service Worker Recifood
 * Rôle minimal : rendre le site installable (PWA) et activer le Web Share Target.
 * Pas de mise en cache agressive : les recettes/données doivent toujours venir du réseau.
 */
const CACHE_NAME = 'recifood-shell-v1';
const APP_SHELL = [
    '/manifest.json',
    '/assets/css/style.css',
    '/assets/icons/icon-192.png',
    '/assets/icons/icon-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(APP_SHELL)).catch(() => {})
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)))
        )
    );
    self.clients.claim();
});

// Stratégie "network-first" : on privilégie toujours les données fraîches,
// et on retombe sur le cache de l'app shell uniquement hors-ligne.
self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') return;

    event.respondWith(
        fetch(event.request).catch(() =>
            caches.match(event.request).then((cached) => cached || caches.match('/manifest.json'))
        )
    );
});
