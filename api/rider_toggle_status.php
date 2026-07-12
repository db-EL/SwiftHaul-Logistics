<?php
// ============================================================
// AJAX endpoint: POST /api/rider_toggle_status.php
// Body: { csrf_token }
// Toggles between 'available' and 'offline'. Riders can't set
// themselves to 'on_delivery' directly — that's system-managed by
// claim_order()/transition_shipment_status().
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
    echo json_encode(['success' => false, 'message' => 'Invalid session. Please refresh and try again.']);
    exit;
}

$riderId = current_rider_id();
$stmt = $pdo->prepare("SELECT status, kyc_status FROM riders WHERE id = ?");
$stmt->execute([$riderId]);
$rider = $stmt->fetch();

if (!$rider) {
    echo json_encode(['success' => false, 'message' => 'Rider not found.']);
    exit;
}

if ($rider['status'] === 'on_delivery') {
    echo json_encode(['success' => false, 'message' => 'You can\'t go offline while on an active delivery.']);
    exit;
}

$goingOnline = $rider['status'] !== 'available';

if ($goingOnline && $rider['kyc_status'] !== 'verified') {
    $message = $rider['kyc_status'] === 'rejected'
        ? 'Your identity verification was rejected. Please contact support to resolve this before going online.'
        : 'Your identity verification is still pending review. You\'ll be able to go online once our team approves your documents.';
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

$newStatus = $goingOnline ? 'available' : 'offline';
$pdo->prepare("UPDATE riders SET status = ? WHERE id = ?")->execute([$newStatus, $riderId]);

echo json_encode(['success' => true, 'status' => $newStatus, 'message' => $newStatus === 'available' ? 'You\'re online and will start seeing available orders.' : 'You\'re offline.']);
