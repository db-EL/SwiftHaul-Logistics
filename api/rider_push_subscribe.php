<?php
// ============================================================
// AJAX endpoint: POST /api/rider_push_subscribe.php
// Body: { subscription: PushSubscriptionJSON, csrf_token }
// Called once the rider grants notification permission and the
// browser generates a push subscription.
// ============================================================
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!is_rider_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$token = $input['csrf_token'] ?? '';
if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    echo json_encode(['success' => false, 'message' => 'Invalid session.']);
    exit;
}

$sub = $input['subscription'] ?? null;
$endpoint = $sub['endpoint'] ?? null;
$p256dh = $sub['keys']['p256dh'] ?? null;
$auth = $sub['keys']['auth'] ?? null;

if (!$endpoint || !$p256dh || !$auth) {
    echo json_encode(['success' => false, 'message' => 'Invalid subscription payload.']);
    exit;
}

$stmt = $pdo->prepare("
    INSERT INTO rider_push_subscriptions (rider_id, endpoint, p256dh, auth)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE rider_id = VALUES(rider_id), p256dh = VALUES(p256dh), auth = VALUES(auth)
");
$stmt->execute([current_rider_id(), $endpoint, $p256dh, $auth]);

echo json_encode(['success' => true, 'message' => 'Push notifications enabled.']);
