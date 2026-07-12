<?php
// Web Push notifications for riders.
//
// Unlike the in-app toast/polling feed (which only works while a
// rider has the dashboard tab open and focused), this sends a real
// OS-level push notification via the browser's Push API — it can
// arrive even if the tab is backgrounded or the browser is closed
// (as long as the OS/browser process is running), because delivery
// is handled by the browser vendor's push service, not by us polling.
//
// Requires:
//   - `composer install` (minishlink/web-push)
//   - Real VAPID keys in config.php (see the instructions there)
//   - HTTPS in production (Push API requires a secure context — an
//     exception is made by browsers for http://localhost specifically,
//     which is why this can still be tested locally under XAMPP)

/**
 * Send a push notification to every subscribed device for one rider.
 * Silently skips (logs only) if Composer deps or VAPID keys aren't set
 * up yet, so the rest of the order flow is never blocked by this.
 */
function send_push_to_rider(PDO $pdo, int $riderId, string $title, string $body, ?string $url = null): void {
    if (!push_notifications_configured()) {
        return;
    }

    $stmt = $pdo->prepare("SELECT * FROM rider_push_subscriptions WHERE rider_id = ?");
    $stmt->execute([$riderId]);
    $subscriptions = $stmt->fetchAll();

    if (!$subscriptions) {
        return;
    }

    try {
        $webPush = new \Minishlink\WebPush\WebPush([
            'VAPID' => [
                'subject' => VAPID_SUBJECT,
                'publicKey' => VAPID_PUBLIC_KEY,
                'privateKey' => VAPID_PRIVATE_KEY,
            ],
        ]);

        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'url' => $url ?? (BASE_URL . '/rider/dashboard.php'),
        ]);

        foreach ($subscriptions as $sub) {
            $subscription = \Minishlink\WebPush\Subscription::create([
                'endpoint' => $sub['endpoint'],
                'publicKey' => $sub['p256dh'],
                'authToken' => $sub['auth'],
            ]);
            $webPush->queueNotification($subscription, $payload);
        }

        foreach ($webPush->flush() as $report) {
            if (!$report->isSuccess() && $report->isSubscriptionExpired()) {
                // The browser unsubscribed (uninstalled, cleared data, etc)
                // — clean up so we stop trying to push to a dead endpoint.
                $pdo->prepare("DELETE FROM rider_push_subscriptions WHERE endpoint = ?")
                    ->execute([$report->getRequest()->getUri()]);
            }
        }
    } catch (\Throwable $e) {
        error_log('[push] Failed to send push notification: ' . $e->getMessage());
    }
}

/**
 * Notify every AVAILABLE rider (i.e. online and not already on a
 * delivery) that a new order has entered the claim pool. Called right
 * after a shipment's payment is confirmed — see includes/payments.php.
 */
function notify_available_riders_of_new_order(PDO $pdo, int $shipmentId): void {
    if (!push_notifications_configured()) {
        return;
    }

    $shipStmt = $pdo->prepare("SELECT tracking_id, fee, pickup_address FROM shipments WHERE id = ?");
    $shipStmt->execute([$shipmentId]);
    $shipment = $shipStmt->fetch();
    if (!$shipment) return;

    $riders = $pdo->query("SELECT id FROM riders WHERE status = 'available'")->fetchAll();

    $title = 'New delivery available';
    $body = 'Pickup near ' . mb_strimwidth($shipment['pickup_address'], 0, 40, '…') . ' — ₦' . number_format($shipment['fee'], 0);

    foreach ($riders as $r) {
        send_push_to_rider($pdo, (int) $r['id'], $title, $body, BASE_URL . '/rider/dashboard.php#available');
    }
}

function push_notifications_configured(): bool {
    return COMPOSER_DEPENDENCIES_LOADED
        && class_exists(\Minishlink\WebPush\WebPush::class)
        && VAPID_PUBLIC_KEY !== 'REPLACE_WITH_YOUR_VAPID_PUBLIC_KEY'
        && VAPID_PRIVATE_KEY !== 'REPLACE_WITH_YOUR_VAPID_PRIVATE_KEY';
}
