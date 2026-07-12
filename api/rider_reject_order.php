<?php
// ============================================================
// AJAX endpoint: POST /api/rider_reject_order.php
// Body: { shipment_id, csrf_token }
// Removes the order from THIS rider's pool only — it stays fully
// available to every other rider.
// ============================================================
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/shipment_status.php';

header('Content-Type: application/json');

if (!is_rider_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$token = $input['csrf_token'] ?? '';
if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    echo json_encode(['success' => false, 'message' => 'Invalid session. Please refresh and try again.']);
    exit;
}

$shipmentId = (int) ($input['shipment_id'] ?? 0);
if (!$shipmentId) {
    echo json_encode(['success' => false, 'message' => 'Missing shipment.']);
    exit;
}

$result = reject_order($pdo, $shipmentId, current_rider_id());
echo json_encode($result);
