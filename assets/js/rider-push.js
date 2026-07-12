// ============================================================
// Rider push notification opt-in.
// Triggered by a button click (not automatically), since browsers
// handle permission prompts best when tied to a deliberate user
// gesture rather than firing on page load.
// ============================================================
(function () {
    const enableBtn = document.getElementById('enablePushBtn');
    if (!enableBtn) return;

    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = atob(base64);
        return Uint8Array.from([...rawData].map((c) => c.charCodeAt(0)));
    }

    async function enablePush() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            showToast('Push notifications aren\'t supported in this browser.', 'error');
            return;
        }

        try {
            const registration = await navigator.serviceWorker.register(BASE_URL_JS + '/sw.js', { scope: BASE_URL_JS + '/' });

            const permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                showToast('Notification permission was not granted.', 'info');
                return;
            }

            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY),
            });

            const res = await fetch(BASE_URL_JS + '/api/rider_push_subscribe.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ subscription: subscription.toJSON(), csrf_token: RIDER_CSRF_TOKEN }),
            });
            const data = await res.json();
            showToastFromResult(data);

            if (data.success) {
                enableBtn.textContent = 'Push Notifications On';
                enableBtn.disabled = true;
            }
        } catch (err) {
            showToast('Could not enable push notifications: ' + err.message, 'error');
        }
    }

    enableBtn.addEventListener('click', enablePush);
})();
