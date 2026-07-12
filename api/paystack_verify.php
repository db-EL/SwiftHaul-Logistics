<?php
// ============================================================
// AJAX endpoint: POST /api/paystack_verify.php
// Body: { reference, shipment_id, csrf_token }
//
// Fired by the customer's browser immediately after checkout for
// fast UX. This is a convenience path, NOT the sole source of
// truth — api/paystack_webhook.php (fired by Paystack's own
// servers) is authoritative and will confirm the payment even if
// this call never happens (tab closed, network drop, etc).
// ============================================================
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/payments.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Please log in again.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$token = $input['csrf_token'] ?? '';
if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    echo json_encode(['success' => false, 'message' => 'Invalid session. Please refresh and try again.']);
    exit;
}

$reference = trim($input['reference'] ?? '');
$shipmentId = (int) ($input['shipment_id'] ?? 0);

if (!$reference || !$shipmentId) {
    echo json_encode(['success' => false, 'message' => 'Missing payment reference.']);
    exit;
}

// Make sure this shipment belongs to the logged-in customer before touching it.
$stmt = $pdo->prepare("SELECT id FROM shipments WHERE id = ? AND customer_id = ?");
$stmt->execute([$shipmentId, current_user_id()]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Shipment not found.']);
    exit;
}

$result = confirm_shipment_payment($pdo, $shipmentId, $reference);
echo json_encode($result);
