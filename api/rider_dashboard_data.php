<?php
// ============================================================
// AJAX endpoint: GET /api/rider_dashboard_data.php
// Polled by the rider dashboard every few seconds to refresh the
// available-orders pool (Bolt-style broadcast to all online riders)
// and the rider's own active deliveries, without a page reload.
// ============================================================
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!is_rider_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

$riderId = current_rider_id();

$riderStmt = $pdo->prepare("SELECT * FROM riders WHERE id = ?");
$riderStmt->execute([$riderId]);
$rider = $riderStmt->fetch();

if (!$rider) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Rider not found.']);
    exit;
}

// Available orders: unclaimed, paid shipments this rider hasn't already
// rejected. Shown to every "available" rider — a simplified stand-in for
// real geo-matching, since addresses aren't geocoded in this build (see
// README for the production-hardening note on this).
$availableStmt = $pdo->prepare("
    SELECT s.id, s.tracking_id, s.pickup_address, s.dropoff_address, s.weight_kg, s.distance_km, s.fee, s.created_at,
           sv.name AS service_name, sv.icon AS service_icon
    FROM shipments s
    LEFT JOIN services sv ON sv.id = s.service_id
    WHERE s.rider_id IS NULL
      AND s.payment_status = 'paid'
      AND s.current_status = 'Order Placed'
      AND s.id NOT IN (SELECT shipment_id FROM order_rejections WHERE rider_id = ?)
    ORDER BY s.created_at ASC
    LIMIT 20
");
$availableStmt->execute([$riderId]);
$available = $availableStmt->fetchAll();

// This rider's own active deliveries.
$activeStmt = $pdo->prepare("
    SELECT s.*, u.full_name AS customer_name, u.phone AS customer_phone
    FROM shipments s
    JOIN users u ON u.id = s.customer_id
    WHERE s.rider_id = ? AND s.current_status NOT IN ('Delivered', 'Cancelled')
    ORDER BY s.accepted_at DESC
");
$activeStmt->execute([$riderId]);
$active = $activeStmt->fetchAll();

foreach ($available as &$o) {
    $o['fee_formatted'] = '₦' . number_format($o['fee'], 0);
    $o['created_at_human'] = date('g:i A', strtotime($o['created_at']));
}
unset($o);

foreach ($active as &$a) {
    $a['fee_formatted'] = '₦' . number_format($a['fee'], 0);
    $a['has_otp'] = !empty($a['delivery_otp']);
}
unset($a);

echo json_encode([
    'success' => true,
    'rider' => [
        'status' => $rider['status'],
        'wallet_balance' => (float) $rider['wallet_balance'],
    ],
    'available_orders' => $available,
    'active_deliveries' => $active,
]);
