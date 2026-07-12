<?php
// ============================================================
// AJAX endpoint: POST /api/rider_update_delivery_status.php
// Body: { shipment_id, new_status, note?, delivery_otp?, csrf_token }
// Uses the same transition_shipment_status() as the admin override
// page, with actingRiderId set so a rider can only update their own
// assigned deliveries.
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
$newStatus = trim($input['new_status'] ?? '');
$note = trim($input['note'] ?? '');
$otpInput = trim($input['delivery_otp'] ?? '');
$signatureData = $input['signature_data'] ?? null;

if (!$shipmentId || !$newStatus) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

$result = transition_shipment_status($pdo, $shipmentId, $newStatus, $note ?: null, $otpInput ?: null, current_rider_id(), $signatureData ?: null);
echo json_encode($result);
