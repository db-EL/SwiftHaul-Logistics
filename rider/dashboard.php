<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/paystack.php';
require_rider_login();

$riderId = current_rider_id();
$stmt = $pdo->prepare("SELECT * FROM riders WHERE id = ?");
$stmt->execute([$riderId]);
$rider = $stmt->fetch();

if (!$rider) {
    // Rider account no longer exists (e.g. deleted) — clear the stale session.
    unset($_SESSION['rider_id'], $_SESSION['rider_name']);
    redirect('/rider-login.php');
}

$errors = [];
$successMsg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    verify_csrf();
    $phone = trim($_POST['phone'] ?? '');
    $bankCode = trim($_POST['bank_code'] ?? '');
    $accountNumber = trim($_POST['account_number'] ?? '');
    $accountName = trim($_POST['account_name'] ?? '');
    $banks = nigerian_banks_list();
    $bankName = $banks[$bankCode] ?? null;

    $stmt = $pdo->prepare("UPDATE riders SET phone = ?, bank_name = ?, bank_code = ?, account_number = ?, account_name = ?, paystack_recipient_code = NULL WHERE id = ?");
    $stmt->execute([$phone, $bankName, $bankCode ?: null, $accountNumber ?: null, $accountName ?: null, $riderId]);
    $successMsg = 'Profile updated.';
    $stmt = $pdo->prepare("SELECT * FROM riders WHERE id = ?");
    $stmt->execute([$riderId]);
    $rider = $stmt->fetch();
}

$pendingStmt = $pdo->prepare("SELECT COALESCE(SUM(rider_earning),0) v FROM rider_earnings WHERE rider_id = ? AND status = 'pending'");
$pendingStmt->execute([$riderId]);
$pendingEarnings = (float) $pendingStmt->fetch()['v'];

$completedDeliveries = $pdo->prepare("SELECT COUNT(*) c FROM shipments WHERE rider_id = ? AND current_status = 'Delivered'");
$completedDeliveries->execute([$riderId]);
$completedCount = $completedDeliveries->fetch()['c'];

$earningsStmt = $pdo->prepare("
    SELECT re.*, s.tracking_id FROM rider_earnings re
    JOIN shipments s ON s.id = re.shipment_id
    WHERE re.rider_id = ?
    ORDER BY re.created_at DESC LIMIT 10
");
$earningsStmt->execute([$riderId]);
$recentEarnings = $earningsStmt->fetchAll();

$pageTitle = 'Rider Dashboard';
require __DIR__ . '/../includes/header.php';
?>

<div class="dash-layout">
    <aside class="dash-sidebar">
        <a href="#available" class="active"><i class="fa-solid fa-bell"></i> Available Orders</a>
        <a href="#active"><i class="fa-solid fa-route"></i> My Deliveries</a>
        <a href="#earnings"><i class="fa-solid fa-wallet"></i> Earnings</a>
        <a href="#profile"><i class="fa-solid fa-user"></i> Profile</a>
        <a href="<?= BASE_URL ?>/rider-logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </aside>

    <main class="dash-main">
        <div class="dash-header">
            <div>
                <h1>Hi, <?= e(explode(' ', $rider['full_name'])[0]) ?> <i class="fa-solid fa-hand" style="color:var(--orange-500);"></i></h1>
                <p style="color:var(--text-muted);font-size:0.9rem;">You're an independent rider — accept the orders you want, skip the rest.</p>
            </div>
            <div class="rider-status-toggle">
                <span id="riderStatusLabel" class="status-badge <?= $rider['status'] === 'available' ? 'st-delivered' : ($rider['status'] === 'on_delivery' ? 'st-pickedup' : 'st-cancelled') ?>">
                    <?= $rider['status'] === 'available' ? 'Online — Available' : ($rider['status'] === 'on_delivery' ? 'On a Delivery' : 'Offline') ?>
                </span>
                <?php if ($rider['status'] !== 'on_delivery'): ?>
                <button id="toggleOnlineBtn" class="btn <?= $rider['status'] === 'available' ? 'btn-ghost' : 'btn-primary' ?> btn-sm" data-current="<?= e($rider['status']) ?>" <?= ($rider['status'] !== 'available' && $rider['kyc_status'] !== 'verified') ? 'disabled title="Verification required before going online"' : '' ?>>
                    <?= $rider['status'] === 'available' ? 'Go Offline' : 'Go Online' ?>
                </button>
                <?php endif; ?>
                <button id="enablePushBtn" class="btn btn-ghost btn-sm"><i class="fa-solid fa-bell"></i> Enable Push Notifications</button>
            </div>
        </div>

        <?php if ($rider['kyc_status'] !== 'verified'): ?>
            <?php if ($rider['kyc_status'] === 'pending'): ?>
                <div class="alert alert-info"><i class="fa-solid fa-clock"></i> <div>Your identity and vehicle documents are pending review. You can browse your dashboard now, but you'll need to wait for verification before going online to accept orders.</div></div>
            <?php else: ?>
                <div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <div>Your identity verification was rejected. Please <a href="<?= BASE_URL ?>/contact.php" style="color:inherit;text-decoration:underline;">contact support</a> to resolve this before you can go online.</div></div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($successMsg): ?><div class="alert alert-success" data-autohide><i class="fa-solid fa-circle-check"></i> <?= e($successMsg) ?></div><?php endif; ?>
        <?php if ($errors): ?><div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>

        <div class="stat-cards">
            <div class="stat-card"><div class="stat-icon" style="background:#e8fbf3;color:#0b8a5c;"><i class="fa-solid fa-wallet"></i></div><div class="stat-value">₦<?= number_format($rider['wallet_balance'],0) ?></div><div class="stat-desc">Wallet Balance</div></div>
            <div class="stat-card"><div class="stat-icon" style="background:#eaf1ff;color:#2952cc;"><i class="fa-solid fa-sack-dollar"></i></div><div class="stat-value">₦<?= number_format($rider['total_earned'],0) ?></div><div class="stat-desc">Total Earned</div></div>
            <div class="stat-card"><div class="stat-icon" style="background:#fff4e0;color:#b8790a;"><i class="fa-solid fa-boxes-stacked"></i></div><div class="stat-value"><?= $completedCount ?></div><div class="stat-desc">Deliveries Completed</div></div>
            <div class="stat-card"><div class="stat-icon" style="background:#fdeee0;color:var(--orange-500);"><i class="fa-solid fa-location-crosshairs"></i></div><div class="stat-value" id="locationStatusValue">—</div><div class="stat-desc">Live Location</div></div>
        </div>

        <!-- Available Orders -->
        <div class="data-card" id="available">
            <div class="data-card-head">
                <h3><i class="fa-solid fa-bell"></i> Available Orders</h3>
                <span style="font-size:0.78rem;color:var(--text-muted);">Refreshes automatically</span>
            </div>
            <div id="availableOrdersList">
                <div class="table-empty">Go online to see available orders.</div>
            </div>
        </div>

        <!-- Active Deliveries -->
        <div class="data-card" id="active">
            <div class="data-card-head"><h3><i class="fa-solid fa-route"></i> My Active Deliveries</h3></div>
            <div id="activeDeliveriesList">
                <div class="table-empty">No active deliveries.</div>
            </div>
        </div>

        <!-- Earnings -->
        <div class="data-card" id="earnings">
            <div class="data-card-head">
                <h3><i class="fa-solid fa-wallet"></i> Recent Earnings</h3>
                <span style="font-size:0.82rem;color:var(--text-muted);">₦<?= number_format($pendingEarnings,0) ?> pending payout</span>
            </div>
            <?php if (!$recentEarnings): ?>
                <div class="table-empty">No earnings yet — complete a delivery to start earning.</div>
            <?php else: ?>
            <div style="overflow-x:auto;">
            <table class="data-table">
                <thead><tr><th>Shipment</th><th>Fee</th><th>Your Share</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                <?php foreach ($recentEarnings as $earn): ?>
                    <tr>
                        <td><?= e($earn['tracking_id']) ?></td>
                        <td>₦<?= number_format($earn['shipment_fee'],0) ?></td>
                        <td><strong>₦<?= number_format($earn['rider_earning'],0) ?></strong></td>
                        <td><?php if ($earn['status'] === 'paid'): ?><span class="status-badge st-delivered">Paid</span><?php else: ?><span class="status-badge st-pickedup">Pending</span><?php endif; ?></td>
                        <td><?= date('M j, Y', strtotime($earn['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Profile -->
        <div class="data-card" id="profile">
            <div class="data-card-head"><h3><i class="fa-solid fa-user"></i> Profile & Payout Details</h3></div>
            <div style="padding:24px;max-width:520px;">
                <form method="POST" action="<?= BASE_URL ?>/rider/dashboard.php#profile">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_profile">
                    <div class="form-group"><label>Full Name</label><input type="text" class="form-control" value="<?= e($rider['full_name']) ?>" disabled></div>
                    <div class="form-group"><label>Email (cannot be changed)</label><input type="email" class="form-control" value="<?= e($rider['email']) ?>" disabled></div>
                    <div class="form-group"><label>Phone</label><input type="text" name="phone" class="form-control" value="<?= e($rider['phone']) ?>"></div>
                    <div class="form-group"><label>Vehicle</label><input type="text" class="form-control" value="<?= e($rider['vehicle_type']) ?> <?= $rider['vehicle_plate'] ? '— ' . e($rider['vehicle_plate']) : '' ?>" disabled></div>
                    <hr style="border:none;border-top:1px solid var(--border);margin:18px 0;">
                    <p style="font-size:0.8rem;font-weight:600;color:var(--navy-900);margin-bottom:12px;">Payout Bank Details</p>
                    <div class="form-group">
                        <label>Bank</label>
                        <select name="bank_code" class="form-control">
                            <option value="">— Select bank —</option>
                            <?php foreach (nigerian_banks_list() as $code => $bname): ?>
                                <option value="<?= e($code) ?>" <?= $rider['bank_code'] === $code ? 'selected' : '' ?>><?= e($bname) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Account Number</label><input type="text" name="account_number" class="form-control" maxlength="10" value="<?= e($rider['account_number']) ?>"></div>
                        <div class="form-group"><label>Account Name</label><input type="text" name="account_name" class="form-control" value="<?= e($rider['account_name']) ?>"></div>
                    </div>
                    <button type="submit" class="btn btn-navy"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
                </form>
            </div>
        </div>
    </main>
</div>

<!-- Delivery status update modal -->
<div class="modal-overlay" id="statusModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3>Update Delivery</h3>
            <button class="modal-close" data-modal-close><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="statusUpdateForm">
            <input type="hidden" name="shipment_id" id="statusShipmentId">
            <div class="form-group">
                <label>New Status</label>
                <select name="new_status" id="statusNewStatus" class="form-control">
                    <option value="Picked Up">Picked Up</option>
                    <option value="In Transit">In Transit</option>
                    <option value="Out for Delivery">Out for Delivery</option>
                    <option value="Delivered">Delivered</option>
                </select>
            </div>
            <div class="form-group" id="otpFieldGroup" style="display:none;">
                <label>How is the receiver confirming delivery?</label>
                <div style="display:flex;gap:16px;margin-bottom:12px;">
                    <label style="display:flex;align-items:center;gap:6px;font-weight:400;font-size:0.85rem;">
                        <input type="radio" name="confirmation_method" value="otp" checked> Confirmation Code
                    </label>
                    <label style="display:flex;align-items:center;gap:6px;font-weight:400;font-size:0.85rem;">
                        <input type="radio" name="confirmation_method" value="signature"> Signature
                    </label>
                </div>

                <div id="otpMethodFields">
                    <label>Delivery Confirmation Code</label>
                    <input type="text" name="delivery_otp" id="statusOtpInput" class="form-control" maxlength="6" placeholder="6-digit code from the receiver">
                    <div class="hint">Ask the receiver for the code the customer shared with them.</div>
                </div>

                <div id="signatureMethodFields" style="display:none;">
                    <label>Receiver's Signature</label>
                    <canvas id="signatureCanvas" style="width:100%;height:160px;border:1.5px dashed var(--border);border-radius:var(--radius-sm);touch-action:none;background:#fafbfd;"></canvas>
                    <button type="button" id="signatureClearBtn" class="btn btn-ghost btn-sm" style="margin-top:8px;">Clear Signature</button>
                    <div class="hint">Hand your device to the receiver so they can sign directly on the screen.</div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Save Update</button>
        </form>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/signature-pad.js"></script>

<script>
const RIDER_CSRF_TOKEN = "<?= e(csrf_token()) ?>";
const VAPID_PUBLIC_KEY = "<?= e(VAPID_PUBLIC_KEY) ?>";
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>

<script src="<?= BASE_URL ?>/assets/js/rider-dashboard.js"></script>
<script src="<?= BASE_URL ?>/assets/js/rider-push.js"></script>
