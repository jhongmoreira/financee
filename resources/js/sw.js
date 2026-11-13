/*
| Service worker do Financee (servido por /sw.js via ServiceWorkerController,
| que injeta a versão do release e a lista de assets do Vite).
| Estratégia: assets e ícones = cache-first; páginas = rede, com fallback
| para /offline. Dados financeiros nunca são guardados em cache.
*/
const VERSION = '__VERSION__';
const CACHE = `financee-${VERSION}`;
const PRECACHE = __PRECACHE__;

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/')) {
        event.respondWith(
            caches.match(request).then((cached) => cached || fetch(request).then((response) => {
                if (response.ok) {
                    const copy = response.clone();
                    caches.open(CACHE).then((cache) => cache.put(request, copy));
                }
                return response;
            })),
        );
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match('/offline')));
    }
});

// Notificações push. Formato enviado pelo servidor (WebPushMessage):
// { title, body, icon, badge, tag, data: { url } }
self.addEventListener('push', (event) => {
    let payload = {};
    try { payload = event.data ? event.data.json() : {}; } catch (e) { payload = { body: event.data ? event.data.text() : '' }; }

    event.waitUntil(self.registration.showNotification(payload.title || 'Financee', {
        body: payload.body || '',
        icon: payload.icon || '/icons/icon-192.png',
        badge: payload.badge || '/icons/favicon-32.png',
        tag: payload.tag || undefined,
        renotify: !!payload.tag,
        data: { url: (payload.data && payload.data.url) || payload.url || '/' },
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = event.notification.data?.url || '/';
    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            const client = clients.find((c) => c.url.startsWith(self.location.origin));
            if (client) {
                client.navigate(target);
                return client.focus();
            }
            return self.clients.openWindow(target);
        }),
    );
});
