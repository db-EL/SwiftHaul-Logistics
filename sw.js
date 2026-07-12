// ============================================================
// SwiftHaul Service Worker
// Placed at the project root so its scope covers /rider/dashboard.php
// (a service worker can only control pages at or below its own path).
// Handles incoming Web Push events and notification clicks.
// ============================================================

self.addEventListener('push', (event) => {
    let data = { title: 'SwiftHaul', body: 'You have a new update.', url: '/' };
    try {
        if (event.data) data = { ...data, ...event.data.json() };
    } catch (e) {
        // If the payload isn't JSON for some reason, fall back to defaults above.
    }

    event.waitUntil(
        self.registration.showNotification(data.title, {
            body: data.body,
            icon: undefined, // add an icon path here if you have brand icon assets
            badge: undefined,
            data: { url: data.url },
            tag: 'swifthaul-order', // collapses rapid-fire notifications into one
            renotify: true,
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const targetUrl = event.notification.data?.url || '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const client of clientList) {
                if (client.url.includes(targetUrl.split('#')[0]) && 'focus' in client) {
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        })
    );
});
