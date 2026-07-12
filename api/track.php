<?php
// ============================================================
// AJAX endpoint: GET /api/track.php?tracking_id=SH-9K2M7QZX41
// Returns JSON with shipment status + history. No page reload.
// ============================================================
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';

header('Content-Type: application/json');

// Tracking IDs are high-entropy and unguessable, but still rate-limit
// lookups per IP as defense in depth against scripted enumeration.
$rateLimitKey = 'track:' . client_ip();
if (rate_limit_seconds_remaining($pdo, $rateLimitKey) > 0) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many lookups. Please wait a minute and try again.']);
    exit;
}

$trackingId = trim($_GET['tracking_id'] ?? '');

if ($trackingId === '') {
    echo json_encode(['success' => false, 'message' => 'Please provide a tracking ID.']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT s.*, r.full_name AS rider_name, r.current_lat, r.current_lng, r.location_updated_at
    FROM shipments s
    LEFT JOIN riders r ON r.id = s.rider_id
    WHERE s.tracking_id = ?
    LIMIT 1
");
$stmt->execute([$trackingId]);
$shipment = $stmt->fetch();

if (!$shipment) {
    rate_limit_record_failure($pdo, $rateLimitKey, maxAttempts: 20, windowSeconds: 120, blockSeconds: 300);
    echo json_encode(['success' => false, 'message' => 'No shipment found with that tracking ID. Please check and try again.']);
    exit;
}

$histStmt = $pdo->prepare("SELECT status, note, updated_at FROM tracking_status_history WHERE shipment_id = ? ORDER BY updated_at ASC");
$histStmt->execute([$shipment['id']]);
$history = $histStmt->fetchAll();

foreach ($history as &$h) {
    $h['updated_at'] = date('M j, Y g:i A', strtotime($h['updated_at']));
}
unset($h);

// Only surface a rider's live position while they're actually en route on
// THIS shipment, and only if it's reasonably fresh (last 10 minutes) —
// otherwise a stale position could mislead the customer.
$activeStatuses = ['Rider Assigned', 'Picked Up', 'In Transit', 'Out for Delivery'];
$hasLiveLocation = $shipment['current_lat'] !== null
    && in_array($shipment['current_status'], $activeStatuses, true)
    && $shipment['location_updated_at']
    && strtotime($shipment['location_updated_at']) > time() - 600;

echo json_encode([
    'success' => true,
    'shipment' => [
        'tracking_id'      => $shipment['tracking_id'],
        'current_status'   => $shipment['current_status'],
        'receiver_name'    => $shipment['receiver_name'],
        'receiver_phone'   => $shipment['receiver_phone'],
        'pickup_address'   => $shipment['pickup_address'],
        'dropoff_address'  => $shipment['dropoff_address'],
        'rider_name'       => $shipment['rider_name'],
        'history'          => array_reverse($history),
        'rider_location'   => $hasLiveLocation ? [
            'lat' => (float) $shipment['current_lat'],
            'lng' => (float) $shipment['current_lng'],
            'updated_at' => $shipment['location_updated_at'],
        ] : null,
        'is_active'        => in_array($shipment['current_status'], $activeStatuses, true),
    ],
]);
