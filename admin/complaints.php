<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/paystack.php';
require_admin();

$successMsg = null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $complaintId = (int) ($_POST['complaint_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    $stmt = $pdo->prepare("
        SELECT dc.*, s.fee, s.payment_status, s.paystack_reference, s.tracking_id
        FROM delivery_complaints dc
        JOIN shipments s ON s.id = dc.shipment_id
        WHERE dc.id = ?
    ");
    $stmt->execute([$complaintId]);
    $complaint = $stmt->fetch();

    if (!$complaint) {
        $errors[] = 'Complaint not found.';
    } elseif ($action === 'update_status') {
        $newStatus = $_POST['status'] ?? '';
        $notes = trim($_POST['admin_notes'] ?? '');
        if (in_array($newStatus, ['open', 'investigating', 'resolved', 'dismissed'], true)) {
            $pdo->prepare("UPDATE delivery_complaints SET status = ?, admin_notes = ? WHERE id = ?")
                ->execute([$newStatus, $notes ?: null, $complaintId]);
            $successMsg = 'Complaint updated.';
        }
    } elseif ($action === 'process_refund') {
        if ($complaint['payment_status'] !== 'paid') {
            $errors[] = 'This shipment isn\'t in a refundable state (already refunded, or was never paid).';
        } elseif (!$complaint['paystack_reference']) {
            $errors[] = 'No payment reference on file for this shipment.';
        } else {
            $result = paystack_refund_transaction($complaint['paystack_reference'], $complaint['fee'], 'Refund for complaint #' . $complaintId . ' on ' . $complaint['tracking_id']);
            if ($result['ok']) {
                $pdo->prepare("UPDATE shipments SET payment_status = 'refunded', refunded_at = NOW() WHERE id = ?")->execute([$complaint['shipment_id']]);
                $pdo->prepare("UPDATE delivery_complaints SET status = 'resolved', admin_notes = CONCAT(COALESCE(admin_notes,''), '\n[Refund processed via Paystack]') WHERE id = ?")->execute([$complaintId]);
                $successMsg = 'Refund of ₦' . number_format($complaint['fee'], 0) . ' processed successfully.';
            } else {
                $errors[] = 'Refund failed: ' . $result['message'];
            }
        }
    }
}

$complaints = $pdo->query("
    SELECT dc.*, s.tracking_id, s.fee, s.payment_status, u.full_name AS customer_name, u.email AS customer_email
    FROM delivery_complaints dc
    JOIN shipments s ON s.id = dc.shipment_id
    JOIN users u ON u.id = dc.customer_id
    ORDER BY FIELD(dc.status, 'open', 'investigating', 'resolved', 'dismissed'), dc.created_at DESC
")->fetchAll();

$typeLabels = [
    'item_missing' => 'Item Missing',
    'item_damaged' => 'Item Damaged',
    'wrong_item' => 'Wrong Item Delivered',
    'rider_misconduct' => 'Rider Misconduct',
    'other' => 'Other',
];
$statusBadge = ['open' => 'st-cancelled', 'investigating' => 'st-pickedup', 'resolved' => 'st-delivered', 'dismissed' => 'st-orderplaced'];

$pageTitle = 'Complaints';
$activePage = 'complaints';
require __DIR__ . '/_admin_header.php';
?>

<div class="dash-header">
    <div><h1>Delivery Complaints</h1><p style="color:var(--text-muted);font-size:0.9rem;">Reported within <?= COMPLAINT_WINDOW_HOURS ?> hours of a delivery being marked complete. Refunds are never automatic — review each case before processing one.</p></div>
</div>

<?php if ($successMsg): ?><div class="alert alert-success" data-autohide><i class="fa-solid fa-circle-check"></i> <?= e($successMsg) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>

<div class="data-card">
    <div class="data-card-head"><h3>All Complaints (<?= count($complaints) ?>)</h3></div>
    <?php if (!$complaints): ?>
        <div class="table-empty">No complaints filed.</div>
    <?php else: ?>
    <?php foreach ($complaints as $c): ?>
        <div style="padding:20px 24px;border-bottom:1px solid var(--border);">
            <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:10px;">
                <div>
                    <strong><?= e($c['tracking_id']) ?></strong> — <?= e($typeLabels[$c['complaint_type']] ?? $c['complaint_type']) ?>
                    <div style="font-size:0.8rem;color:var(--text-muted);"><?= e($c['customer_name']) ?> (<?= e($c['customer_email']) ?>) · <?= date('M j, Y g:i A', strtotime($c['created_at'])) ?></div>
                </div>
                <span class="status-badge <?= $statusBadge[$c['status']] ?>"><?= ucfirst($c['status']) ?></span>
            </div>
            <p style="font-size:0.9rem;color:var(--text-dark);margin-bottom:14px;"><?= nl2br(e($c['description'])) ?></p>

            <form method="POST" action="<?= BASE_URL ?>/admin/complaints.php" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;margin-bottom:10px;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="complaint_id" value="<?= $c['id'] ?>">
                <div class="form-group" style="margin-bottom:0;flex:1;min-width:200px;">
                    <label>Admin Notes</label>
                    <input type="text" name="admin_notes" class="form-control" value="<?= e($c['admin_notes']) ?>" placeholder="Internal notes about this case">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="open" <?= $c['status']==='open'?'selected':'' ?>>Open</option>
                        <option value="investigating" <?= $c['status']==='investigating'?'selected':'' ?>>Investigating</option>
                        <option value="resolved" <?= $c['status']==='resolved'?'selected':'' ?>>Resolved</option>
                        <option value="dismissed" <?= $c['status']==='dismissed'?'selected':'' ?>>Dismissed</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-navy btn-sm">Save</button>
            </form>

            <?php if ($c['payment_status'] === 'paid'): ?>
                <form method="POST" action="<?= BASE_URL ?>/admin/complaints.php" onsubmit="return confirm('Refund ₦<?= number_format($c['fee'],0) ?> to the customer via Paystack? This cannot be undone.');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="process_refund">
                    <input type="hidden" name="complaint_id" value="<?= $c['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm"><i class="fa-solid fa-money-bill-transfer"></i> Refund ₦<?= number_format($c['fee'],0) ?></button>
                </form>
            <?php elseif ($c['payment_status'] === 'refunded'): ?>
                <span class="status-badge st-cancelled">Already Refunded</span>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/_admin_footer.php'; ?>
