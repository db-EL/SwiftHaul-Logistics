<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/shipment_status.php';
require_admin();

$successMsg = null;
$errors = [];

// Manual override — most deliveries now progress via the rider's own
// dashboard without any admin involvement. This form stays available for
// the rare case that needs a manual nudge (e.g. reassigning a rider, or
// force-cancelling something).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $shipmentId = (int) ($_POST['shipment_id'] ?? 0);
    $newStatus = $_POST['new_status'] ?? '';
    $riderId = $_POST['rider_id'] ?? '';
    $note = trim($_POST['note'] ?? '');
    $otpInput = trim($_POST['delivery_otp'] ?? '');

    if (!$shipmentId) {
        $errors[] = 'Invalid update request.';
    } else {
        $riderVal = $riderId !== '' ? (int) $riderId : null;

        $shipStmt = $pdo->prepare("SELECT rider_id FROM shipments WHERE id = ?");
        $shipStmt->execute([$shipmentId]);
        $existing = $shipStmt->fetch();

        if ($existing && (int) $existing['rider_id'] !== (int) $riderVal) {
            // Admin manually reassigning — bypasses the normal atomic claim
            // guard since this is a deliberate admin override, not a rider
            // racing another rider for an open order.
            $pdo->prepare("UPDATE shipments SET rider_id = ? WHERE id = ?")->execute([$riderVal, $shipmentId]);
        }

        $result = transition_shipment_status($pdo, $shipmentId, $newStatus, $note, $otpInput, null);
        if ($result['success']) {
            $successMsg = $result['message'];
        } else {
            $errors[] = $result['message'];
        }
    }
}

$shipments = $pdo->query("
    SELECT s.*, u.full_name AS customer_name, r.full_name AS rider_name
    FROM shipments s
    JOIN users u ON u.id = s.customer_id
    LEFT JOIN riders r ON r.id = s.rider_id
    ORDER BY s.created_at DESC
")->fetchAll();

$riders = $pdo->query("SELECT * FROM riders ORDER BY full_name ASC")->fetchAll();

$pageTitle = 'Manage Shipments';
$activePage = 'shipments';
require __DIR__ . '/_admin_header.php';
?>

<div class="dash-header">
    <div><h1>Manage Shipments</h1><p style="color:var(--text-muted);font-size:0.9rem;">Riders now claim and progress their own deliveries from their dashboard — this page is a manual override, not a required step. Use it to reassign a stuck order or force a status change.</p></div>
</div>

<?php if ($successMsg): ?><div class="alert alert-success" data-autohide><i class="fa-solid fa-circle-check"></i> <?= e($successMsg) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>

<div class="data-card">
    <div class="data-card-head"><h3>All Shipments (<?= count($shipments) ?>)</h3></div>
    <?php if (!$shipments): ?>
        <div class="table-empty">No shipments have been placed yet.</div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="data-table">
        <thead><tr><th>Tracking ID</th><th>Customer</th><th>Rider</th><th>Fee</th><th>Payment</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($shipments as $s): ?>
            <tr>
                <td><strong><?= e($s['tracking_id']) ?></strong></td>
                <td><?= e($s['customer_name']) ?></td>
                <td><?= e($s['rider_name'] ?? 'Unassigned') ?></td>
                <td>₦<?= number_format($s['fee'],0) ?></td>
                <td>
                    <?php if ($s['payment_status'] === 'paid'): ?>
                        <span class="status-badge st-delivered">Paid</span>
                    <?php elseif ($s['payment_status'] === 'failed'): ?>
                        <span class="status-badge st-cancelled">Failed</span>
                    <?php else: ?>
                        <span class="status-badge st-pickedup">Unpaid</span>
                    <?php endif; ?>
                </td>
                <td><span class="status-badge <?= status_badge_class($s['current_status']) ?>"><?= e($s['current_status']) ?></span></td>
                <td><?= date('M j, Y', strtotime($s['created_at'])) ?></td>
                <td><button class="btn btn-ghost btn-sm" data-modal-target="#modal-<?= $s['id'] ?>"><i class="fa-solid fa-pen"></i> Update</button></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<!-- Update Modals -->
<?php foreach ($shipments as $s): ?>
<div class="modal-overlay" id="modal-<?= $s['id'] ?>">
    <div class="modal-box">
        <div class="modal-head">
            <h3>Update <?= e($s['tracking_id']) ?></h3>
            <button class="modal-close" data-modal-close><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>/admin/shipments.php">
            <?= csrf_field() ?>
            <input type="hidden" name="shipment_id" value="<?= $s['id'] ?>">
            <div class="form-group">
                <label>Status</label>
                <select name="new_status" class="form-control">
                    <?php foreach (['Order Placed','Rider Assigned','Picked Up','In Transit','Out for Delivery','Delivered','Cancelled'] as $st): ?>
                        <option value="<?= $st ?>" <?= $s['current_status'] === $st ? 'selected' : '' ?>><?= $st ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Assign Rider</label>
                <select name="rider_id" class="form-control">
                    <option value="">— Unassigned —</option>
                    <?php foreach ($riders as $r): ?>
                        <option value="<?= $r['id'] ?>" <?= $s['rider_id'] == $r['id'] ? 'selected' : '' ?>><?= e($r['full_name']) ?> (<?= e($r['vehicle_type']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Note (optional)</label>
                <input type="text" name="note" class="form-control" placeholder="e.g. Rider is 5 minutes away">
            </div>
            <div class="form-group">
                <label>Delivery Confirmation Code <span style="font-weight:400;color:var(--text-muted);">(only required when setting status to Delivered)</span></label>
                <input type="text" name="delivery_otp" class="form-control" placeholder="6-digit code from the receiver" maxlength="6">
                <?php if ($s['delivery_otp']): ?>
                    <div class="hint">A code is active for this shipment. Ask the receiver for it before marking Delivered.</div>
                <?php elseif ($s['current_status'] !== 'Delivered'): ?>
                    <div class="hint" style="color:var(--danger);">No active code on file — this shipment can't be marked Delivered until one exists.</div>
                <?php endif; ?>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Save Update</button>
        </form>
    </div>
</div>
<?php endforeach; ?>

<?php require __DIR__ . '/_admin_footer.php'; ?>
