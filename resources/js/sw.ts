import { cleanupOutdatedCaches, precacheAndRoute } from 'workbox-precaching';

declare let self: ServiceWorkerGlobalScope;

// Clean old caches from previous versions
cleanupOutdatedCaches();

// Precache all assets from the Vite build manifest
precacheAndRoute(self.__WB_MANIFEST);

// Handle push notifications
self.addEventListener('push', (event) => {
    const data = event.data?.json() ?? {};
    event.waitUntil(
        self.registration.showNotification(data.title ?? 'Sentinel', {
            body: data.body ?? '',
            icon: '/pwa-192x192.png',
            badge: '/pwa-192x192.png',
            tag: data.tag ?? 'sentinel-notification',
            data: { url: data.url ?? '/' },
        }),
    );
});

// Handle notification click -- open or focus the app
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url ?? '/';
    event.waitUntil(
        self.clients.matchAll({ type: 'window' }).then((clients) => {
            const existing = clients.find((c) => c.url.includes(url));
            if (existing) {
                return existing.focus();
            }
            return self.clients.openWindow(url);
        }),
    );
});

// Handle skip waiting for prompt-for-update flow
self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});
