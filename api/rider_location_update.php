<?php
// ============================================================
// AJAX endpoint: POST /api/rider_location_update.php
// Body: { lat, lng, csrf_token }
// Called periodically by the rider dashboard's geolocation watcher
// while the rider is online. Feeds riders.current_lat/current_lng,
// which the public tracking page polls to show a live position.
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

$lat = filter_var($input['lat'] ?? null, FILTER_VALIDATE_FLOAT);
$lng = filter_var($input['lng'] ?? null, FILTER_VALIDATE_FLOAT);

if ($lat === false || $lng === false || $lat === null || $lng === null || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    echo json_encode(['success' => false, 'message' => 'Invalid coordinates.']);
    exit;
}

$pdo->prepare("UPDATE riders SET current_lat = ?, current_lng = ?, location_updated_at = NOW() WHERE id = ?")
    ->execute([$lat, $lng, current_rider_id()]);

echo json_encode(['success' => true]);
