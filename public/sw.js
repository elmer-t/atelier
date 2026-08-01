/**
 * Atelier service worker (#35).
 *
 * Served from the app root so it can claim the whole-origin scope a push subscription
 * needs — which is why it lives here as a static file rather than a Vite-hashed asset.
 * It does exactly two things: turn an incoming push into a notification, and route a
 * click on that notification to the artifact it points at (carried in `data.url`).
 */

self.addEventListener('install', () => {
    // Take over without waiting for existing tabs to close.
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
    if (!event.data) {
        return;
    }

    let payload = {};

    try {
        payload = event.data.json();
    } catch (error) {
        payload = { title: 'Atelier', body: event.data.text() };
    }

    const title = payload.title || 'Atelier';
    const options = {
        body: payload.body || '',
        icon: payload.icon || '/apple-touch-icon.png',
        badge: payload.badge || '/favicon.svg',
        tag: payload.tag,
        data: payload.data || {},
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = (event.notification.data && event.notification.data.url) || '/';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            // Focus an existing tab already on that URL instead of opening a new one.
            for (const client of clientList) {
                if (client.url === url && 'focus' in client) {
                    return client.focus();
                }
            }

            if (self.clients.openWindow) {
                return self.clients.openWindow(url);
            }

            return undefined;
        })
    );
});
