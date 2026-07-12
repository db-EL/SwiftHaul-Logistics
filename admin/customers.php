<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $userId = (int) ($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($userId && in_array($action, ['verify', 'reject'], true)) {
        $pdo->prepare("UPDATE users SET kyc_status = ? WHERE id = ?")
            ->execute([$action === 'verify' ? 'verified' : 'rejected', $userId]);
    }
    redirect('/admin/customers.php');
}

$customers = $pdo->query("
    SELECT u.*, (SELECT COUNT(*) FROM shipments s WHERE s.customer_id = u.id) AS shipment_count
    FROM users u WHERE u.role = 'customer'
    ORDER BY FIELD(u.kyc_status, 'pending', 'rejected', 'verified'), u.created_at DESC
")->fetchAll();

$docTypeLabels = [
    'national_id' => 'National ID Card',
    'voters_card' => "Voter's Card",
    'international_passport' => 'International Passport',
    'drivers_license' => "Driver's License",
    'utility_bill' => 'Utility Bill',
];

$pageTitle = 'Manage Customers';
$activePage = 'customers';
require __DIR__ . '/_admin_header.php';
?>

<div class="dash-header">
    <div><h1>Customers</h1><p style="color:var(--text-muted);font-size:0.9rem;">Review identity verification documents submitted at signup.</p></div>
</div>

<div class="data-card">
    <div class="data-card-head"><h3>All Customers (<?= count($customers) ?>)</h3></div>
    <?php if (!$customers): ?>
        <div class="table-empty">No customers yet.</div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="data-table">
        <thead><tr><th>Name</th><th>Contact</th><th>Shipments</th><th>Documents</th><th>KYC Status</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($customers as $c): ?>
            <tr>
                <td><strong><?= e($c['full_name']) ?></strong></td>
                <td><?= e($c['email']) ?><br><span style="color:var(--text-muted);font-size:0.8rem;"><?= e($c['phone']) ?></span></td>
                <td><?= (int) $c['shipment_count'] ?></td>
                <td>
                    <?php if ($c['selfie_path']): ?>
                        <a href="<?= BASE_URL ?>/admin/view_document.php?account_type=user&id=<?= $c['id'] ?>&field=selfie_path" target="_blank" class="btn btn-ghost btn-sm">Selfie</a>
                    <?php endif; ?>
                    <?php if ($c['verification_doc_path']): ?>
                        <a href="<?= BASE_URL ?>/admin/view_document.php?account_type=user&id=<?= $c['id'] ?>&field=verification_doc_path" target="_blank" class="btn btn-ghost btn-sm"><?= e($docTypeLabels[$c['verification_doc_type']] ?? 'Document') ?></a>
                    <?php endif; ?>
                    <?php if (!$c['selfie_path'] && !$c['verification_doc_path']): ?>
                        <span style="color:var(--text-muted);font-size:0.8rem;">None on file</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php $map = ['pending' => 'st-pickedup', 'verified' => 'st-delivered', 'rejected' => 'st-cancelled']; ?>
                    <span class="status-badge <?= $map[$c['kyc_status']] ?>"><?= ucfirst($c['kyc_status']) ?></span>
                </td>
                <td>
                    <?php if ($c['kyc_status'] !== 'verified'): ?>
                        <form method="POST" action="<?= BASE_URL ?>/admin/customers.php" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="user_id" value="<?= $c['id'] ?>">
                            <input type="hidden" name="action" value="verify">
                            <button type="submit" class="btn btn-primary btn-sm">Verify</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($c['kyc_status'] !== 'rejected'): ?>
                        <form method="POST" action="<?= BASE_URL ?>/admin/customers.php" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="user_id" value="<?= $c['id'] ?>">
                            <input type="hidden" name="action" value="reject">
                            <button type="submit" class="btn btn-ghost btn-sm">Reject</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/_admin_footer.php'; ?>
