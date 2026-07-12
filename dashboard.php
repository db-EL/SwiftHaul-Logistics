<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_login();
if (is_admin()) redirect('/admin/index.php');

$userId = current_user_id();
$errors = [];
$successMsg = null;

// Fetch user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$services = $pdo->query("SELECT * FROM services ORDER BY id ASC")->fetchAll();

// -------- Handle complaint submission --------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'file_complaint') {
    verify_csrf();
    $shipmentId = (int) ($_POST['shipment_id'] ?? 0);
    $complaintType = $_POST['complaint_type'] ?? '';
    $description = trim($_POST['description'] ?? '');

    $validTypes = ['item_missing', 'item_damaged', 'wrong_item', 'rider_misconduct', 'other'];

    $shipStmt = $pdo->prepare("SELECT * FROM shipments WHERE id = ? AND customer_id = ?");
    $shipStmt->execute([$shipmentId, $userId]);
    $targetShipment = $shipStmt->fetch();

    if (!$targetShipment || $targetShipment['current_status'] !== 'Delivered') {
        $errors[] = 'This shipment isn\'t eligible for a complaint.';
    } elseif (!$targetShipment['delivered_confirmed_at'] || strtotime($targetShipment['delivered_confirmed_at']) < time() - (COMPLAINT_WINDOW_HOURS * 3600)) {
        $errors[] = 'The complaint window for this delivery (' . COMPLAINT_WINDOW_HOURS . ' hours after delivery) has closed.';
    } elseif (!in_array($complaintType, $validTypes, true) || strlen($description) < 10) {
        $errors[] = 'Please select a complaint type and describe the issue (at least 10 characters).';
    } else {
        $existing = $pdo->prepare("SELECT id FROM delivery_complaints WHERE shipment_id = ? AND customer_id = ?");
        $existing->execute([$shipmentId, $userId]);
        if ($existing->fetch()) {
            $errors[] = 'You\'ve already filed a complaint for this delivery — our team is reviewing it.';
        } else {
            $pdo->prepare("INSERT INTO delivery_complaints (shipment_id, customer_id, complaint_type, description) VALUES (?, ?, ?, ?)")
                ->execute([$shipmentId, $userId, $complaintType, $description]);
            $successMsg = 'Your complaint has been submitted — our team will review it and follow up in Messages.';
        }
    }
}

// -------- Handle new shipment creation --------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_shipment') {
    verify_csrf();

    $pickup = trim($_POST['pickup_address'] ?? '');
    $dropoff = trim($_POST['dropoff_address'] ?? '');
    $receiverName = trim($_POST['receiver_name'] ?? '');
    $receiverPhone = trim($_POST['receiver_phone'] ?? '');
    $serviceId = (int) ($_POST['service_id'] ?? 0);
    $weightRaw = (float) ($_POST['weight_kg'] ?? 1);
    $distanceRaw = (float) ($_POST['distance_km'] ?? 1);
    [$weight, $distance] = clamp_shipment_inputs($weightRaw, $distanceRaw);

    if ($pickup === '' || $dropoff === '' || $receiverName === '' || $receiverPhone === '') {
        $errors[] = 'Please fill in all required shipment fields.';
    }

    $service = null;
    foreach ($services as $s) if ((int)$s['id'] === $serviceId) $service = $s;
    if (!$service) $errors[] = 'Please select a valid service.';

    if (!$errors) {
        $fee = $service['base_fee'] + ($distance * $service['rate_per_km']) + ($weight * $service['rate_per_kg']);
        $trackingId = generate_tracking_id($pdo);
        $deliveryOtp = generate_delivery_otp();

        $stmt = $pdo->prepare("INSERT INTO shipments (tracking_id, customer_id, service_id, pickup_address, dropoff_address, receiver_name, receiver_phone, weight_kg, distance_km, fee, current_status, delivery_otp)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Order Placed', ?)");
        $stmt->execute([$trackingId, $userId, $serviceId, $pickup, $dropoff, $receiverName, $receiverPhone, $weight, $distance, $fee, $deliveryOtp]);
        $shipmentId = $pdo->lastInsertId();

        $pdo->prepare("INSERT INTO tracking_status_history (shipment_id, status, note) VALUES (?, 'Order Placed', 'Shipment booked by customer.')")->execute([$shipmentId]);

        $successMsg = "Shipment booked! Your tracking ID is <strong>$trackingId</strong>. Please complete payment below to confirm your booking.<br>Your delivery confirmation code is <strong>$deliveryOtp</strong> — give this to the receiver so they can hand it to the rider on drop-off; it's required before the delivery can be marked complete.";
        $newShipmentId = $shipmentId;
        $newShipmentFee = $fee;
    }
}

// -------- Handle profile update --------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    verify_csrf();
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($fullName === '') {
        $errors[] = 'Full name cannot be empty.';
    } else {
        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ?, address = ? WHERE id = ?");
        $stmt->execute([$fullName, $phone, $address, $userId]);
        $_SESSION['user_name'] = $fullName;
        $successMsg = 'Profile updated successfully.';
        $user['full_name'] = $fullName; $user['phone'] = $phone; $user['address'] = $address;
    }
}

// Fetch shipments
$stmt = $pdo->prepare("
    SELECT s.*, r.full_name AS rider_name, dc.id AS complaint_id, dc.status AS complaint_status
    FROM shipments s
    LEFT JOIN riders r ON r.id = s.rider_id
    LEFT JOIN delivery_complaints dc ON dc.shipment_id = s.id AND dc.customer_id = s.customer_id
    WHERE s.customer_id = ?
    ORDER BY s.created_at DESC
");
$stmt->execute([$userId]);
$shipments = $stmt->fetchAll();

$totalShipments = count($shipments);
$inTransit = count(array_filter($shipments, fn($s) => in_array($s['current_status'], ['Picked Up','In Transit','Out for Delivery'])));
$delivered = count(array_filter($shipments, fn($s) => $s['current_status'] === 'Delivered'));

$pageTitle = 'My Dashboard';
require __DIR__ . '/includes/header.php';
?>

<div class="dash-layout">
    <aside class="dash-sidebar">
        <a href="#overview" class="active"><i class="fa-solid fa-gauge"></i> Overview</a>
        <a href="#new-shipment"><i class="fa-solid fa-plus"></i> New Shipment</a>
        <a href="#shipments"><i class="fa-solid fa-boxes-stacked"></i> My Shipments</a>
        <a href="#profile"><i class="fa-solid fa-user"></i> Profile</a>
        <a href="<?= BASE_URL ?>/messages.php"><i class="fa-solid fa-envelope"></i> Messages<?php
            $unreadStmt = $pdo->prepare("SELECT COUNT(*) c FROM support_threads WHERE user_id = ? AND read_by_user = 0");
            $unreadStmt->execute([$userId]);
            $unreadCount = $unreadStmt->fetch()['c'];
            if ($unreadCount > 0) echo ' <span class="status-badge st-cancelled" style="margin-left:4px;">' . $unreadCount . '</span>';
        ?></a>
        <a href="<?= BASE_URL ?>/track.php"><i class="fa-solid fa-location-crosshairs"></i> Track a Package</a>
        <a href="<?= BASE_URL ?>/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </aside>

    <main class="dash-main">
        <div class="dash-header" id="overview">
            <div>
                <h1>Welcome back, <?= e(explode(' ', $user['full_name'])[0]) ?> <i class="fa-solid fa-hand" style="color:var(--orange-500);"></i></h1>
                <p style="color:var(--text-muted);font-size:0.9rem;">Here's an overview of your shipments.</p>
            </div>
            <a href="#new-shipment" class="btn btn-primary"><i class="fa-solid fa-plus"></i> New Shipment</a>
        </div>

        <?php if ($successMsg): ?><div class="alert alert-success" data-autohide><i class="fa-solid fa-circle-check"></i> <div><?= $successMsg ?></div></div><?php endif; ?>
        <?php if ($errors): ?><div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <div><?= implode('<br>', array_map('e', $errors)) ?></div></div><?php endif; ?>

        <div class="stat-cards">
            <div class="stat-card"><div class="stat-icon" style="background:#eaf1ff;color:#2952cc;"><i class="fa-solid fa-boxes-stacked"></i></div><div class="stat-value"><?= $totalShipments ?></div><div class="stat-desc">Total Shipments</div></div>
            <div class="stat-card"><div class="stat-icon" style="background:#fff4e0;color:#b8790a;"><i class="fa-solid fa-truck"></i></div><div class="stat-value"><?= $inTransit ?></div><div class="stat-desc">In Transit</div></div>
            <div class="stat-card"><div class="stat-icon" style="background:#e8fbf3;color:#0b8a5c;"><i class="fa-solid fa-circle-check"></i></div><div class="stat-value"><?= $delivered ?></div><div class="stat-desc">Delivered</div></div>
            <div class="stat-card"><div class="stat-icon" style="background:#fdeee0;color:var(--orange-500);"><i class="fa-solid fa-naira-sign"></i></div><div class="stat-value">₦<?= number_format(array_sum(array_column($shipments,'fee')),0) ?></div><div class="stat-desc">Total Spent</div></div>
        </div>

        <!-- New Shipment -->
        <div class="data-card" id="new-shipment">
            <div class="data-card-head"><h3><i class="fa-solid fa-plus"></i> Book a New Shipment</h3></div>
            <div style="padding:24px;">
                <form method="POST" action="<?= BASE_URL ?>/dashboard.php#new-shipment">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create_shipment">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Pickup Address</label>
                            <input type="text" name="pickup_address" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Drop-off Address</label>
                            <input type="text" name="dropoff_address" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Receiver Name</label>
                            <input type="text" name="receiver_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Receiver Phone</label>
                            <input type="text" name="receiver_phone" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Service</label>
                            <select name="service_id" class="form-control" required>
                                <?php foreach ($services as $s): ?>
                                    <option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Weight (kg)</label>
                            <input type="number" step="0.1" min="0.1" name="weight_kg" class="form-control" value="1" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Distance (km)</label>
                        <input type="number" step="0.5" min="0.5" name="distance_km" class="form-control" value="5" required>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Book Shipment</button>
                </form>
            </div>
        </div>

        <!-- Shipments -->
        <div class="data-card" id="shipments">
            <div class="data-card-head"><h3><i class="fa-solid fa-boxes-stacked"></i> My Shipments</h3></div>
            <?php if (!$shipments): ?>
                <div class="table-empty">No shipments yet. Book your first one above!</div>
            <?php else: ?>
            <div style="overflow-x:auto;">
            <table class="data-table">
                <thead><tr><th>Tracking ID</th><th>To</th><th>Rider</th><th>Fee</th><th>Payment</th><th>Status</th><th>Delivery Code</th><th>Date</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($shipments as $s): ?>
                    <?php
                        $withinComplaintWindow = $s['delivered_confirmed_at'] && strtotime($s['delivered_confirmed_at']) > time() - (COMPLAINT_WINDOW_HOURS * 3600);
                        $canComplain = $s['current_status'] === 'Delivered' && $withinComplaintWindow && !$s['complaint_id'];
                    ?>
                    <tr>
                        <td><strong><?= e($s['tracking_id']) ?></strong></td>
                        <td><?= e($s['receiver_name']) ?></td>
                        <td><?= e($s['rider_name'] ?? 'Unassigned') ?></td>
                        <td>₦<?= number_format($s['fee'],0) ?></td>
                        <td>
                            <?php if ($s['payment_status'] === 'paid'): ?>
                                <span class="status-badge st-delivered">Paid</span>
                            <?php elseif ($s['payment_status'] === 'refunded'): ?>
                                <span class="status-badge st-cancelled">Refunded</span>
                            <?php elseif ($s['payment_status'] === 'failed'): ?>
                                <span class="status-badge st-cancelled">Failed</span>
                                <button class="btn btn-primary btn-sm pay-now-btn" data-shipment-id="<?= $s['id'] ?>" data-fee="<?= $s['fee'] ?>" data-tracking="<?= e($s['tracking_id']) ?>" style="margin-top:6px;">Retry Payment</button>
                            <?php else: ?>
                                <span class="status-badge st-pickedup">Unpaid</span>
                                <button class="btn btn-primary btn-sm pay-now-btn" data-shipment-id="<?= $s['id'] ?>" data-fee="<?= $s['fee'] ?>" data-tracking="<?= e($s['tracking_id']) ?>" style="margin-top:6px;">Pay Now</button>
                            <?php endif; ?>
                        </td>
                        <td><span class="status-badge <?= status_badge_class($s['current_status']) ?>"><?= e($s['current_status']) ?></span></td>
                        <td><?= $s['delivery_otp'] ? '<code style="font-weight:700;">' . e($s['delivery_otp']) . '</code>' : '<span style="color:var(--text-muted);font-size:0.8rem;">Used</span>' ?></td>
                        <td><?= date('M j, Y', strtotime($s['created_at'])) ?></td>
                        <td style="display:flex;gap:6px;flex-wrap:wrap;">
                            <a href="<?= BASE_URL ?>/track.php?id=<?= urlencode($s['tracking_id']) ?>" class="btn btn-ghost btn-sm">Track</a>
                            <?php if ($canComplain): ?>
                                <button class="btn btn-ghost btn-sm" data-modal-target="#complaint-<?= $s['id'] ?>" style="color:var(--danger);"><i class="fa-solid fa-flag"></i> Report Problem</button>
                            <?php elseif ($s['complaint_id']): ?>
                                <span class="status-badge st-pickedup">Complaint <?= ucfirst($s['complaint_status']) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Complaint modals (one per eligible delivered shipment) -->
        <?php foreach ($shipments as $s): ?>
            <?php
                $withinComplaintWindow = $s['delivered_confirmed_at'] && strtotime($s['delivered_confirmed_at']) > time() - (COMPLAINT_WINDOW_HOURS * 3600);
                $canComplain = $s['current_status'] === 'Delivered' && $withinComplaintWindow && !$s['complaint_id'];
            ?>
            <?php if ($canComplain): ?>
            <div class="modal-overlay" id="complaint-<?= $s['id'] ?>">
                <div class="modal-box">
                    <div class="modal-head">
                        <h3>Report a Problem — <?= e($s['tracking_id']) ?></h3>
                        <button class="modal-close" data-modal-close><i class="fa-solid fa-xmark"></i></button>
                    </div>
                    <p style="font-size:0.85rem;color:var(--text-muted);margin-bottom:16px;">You can report an issue within <?= COMPLAINT_WINDOW_HOURS ?> hours of delivery. Our team will review it and may follow up in <a href="<?= BASE_URL ?>/messages.php">Messages</a>.</p>
                    <form method="POST" action="<?= BASE_URL ?>/dashboard.php#shipments">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="file_complaint">
                        <input type="hidden" name="shipment_id" value="<?= $s['id'] ?>">
                        <div class="form-group">
                            <label>What went wrong?</label>
                            <select name="complaint_type" class="form-control" required>
                                <option value="">— Select —</option>
                                <option value="item_missing">Item Missing</option>
                                <option value="item_damaged">Item Damaged</option>
                                <option value="wrong_item">Wrong Item Delivered</option>
                                <option value="rider_misconduct">Rider Misconduct</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Describe what happened</label>
                            <textarea name="description" class="form-control" required minlength="10" placeholder="Please give as much detail as you can."></textarea>
                        </div>
                        <button type="submit" class="btn btn-danger btn-block">Submit Complaint</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>

        <!-- Profile -->
        <div class="data-card" id="profile">
            <div class="data-card-head"><h3><i class="fa-solid fa-user"></i> My Profile</h3></div>
            <div style="padding:24px;max-width:500px;">
                <form method="POST" action="<?= BASE_URL ?>/dashboard.php#profile">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_profile">
                    <div class="form-group"><label>Full Name</label><input type="text" name="full_name" class="form-control" value="<?= e($user['full_name']) ?>" required></div>
                    <div class="form-group"><label>Email (cannot be changed)</label><input type="email" class="form-control" value="<?= e($user['email']) ?>" disabled></div>
                    <div class="form-group"><label>Phone</label><input type="text" name="phone" class="form-control" value="<?= e($user['phone']) ?>"></div>
                    <div class="form-group"><label>Address</label><input type="text" name="address" class="form-control" value="<?= e($user['address']) ?>"></div>
                    <button type="submit" class="btn btn-navy"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
                </form>
            </div>
        </div>
    </main>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

<script src="https://js.paystack.co/v2/inline.js"></script>
<script>
const PAYSTACK_PUBLIC_KEY = "<?= e(PAYSTACK_PUBLIC_KEY) ?>";
const CUSTOMER_EMAIL = "<?= e($user['email']) ?>";

function payForShipment(shipmentId, feeNaira, trackingId) {
    const handler = PaystackPop.setup({
        key: PAYSTACK_PUBLIC_KEY,
        email: CUSTOMER_EMAIL,
        amount: Math.round(feeNaira * 100), // kobo
        currency: 'NGN',
        ref: 'SWH_' + shipmentId + '_' + Date.now(),
        metadata: { shipment_id: shipmentId, tracking_id: trackingId },
        callback: function (response) {
            fetch(BASE_URL_JS + '/api/paystack_verify.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    reference: response.reference,
                    shipment_id: shipmentId,
                    csrf_token: document.querySelector('input[name="csrf_token"]').value,
                }),
            })
            .then(res => res.json())
            .then(data => {
                showToast(data.message, data.success ? 'success' : 'error', { duration: 3200 });
                setTimeout(() => window.location.reload(), 1600);
            })
            .catch(() => showToast('Payment made, but we could not confirm it automatically. Please refresh the page.', 'error'));
        },
        onClose: function () {
            // user closed the payment popup without paying
        },
    });
    handler.openIframe();
}

document.querySelectorAll('.pay-now-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        payForShipment(
            btn.dataset.shipmentId,
            parseFloat(btn.dataset.fee),
            btn.dataset.tracking
        );
    });
});

<?php if (!empty($newShipmentId)): ?>
// Prompt payment immediately after booking a new shipment — styled toast
// instead of a native browser popup.
document.addEventListener('DOMContentLoaded', () => {
    showToast(
        'Your shipment is booked. Pay ₦<?= number_format($newShipmentFee, 0) ?> now to confirm it?',
        'info',
        {
            actionLabel: 'Pay Now',
            onAction: () => payForShipment(<?= (int) $newShipmentId ?>, <?= (float) $newShipmentFee ?>, ''),
        }
    );
});
<?php endif; ?>
</script>
