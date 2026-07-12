<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/paystack.php';
require_admin();

$successMsg = null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_rider') {
        $name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $vehicle = trim($_POST['vehicle_type'] ?? 'Motorbike');
        $plate = trim($_POST['vehicle_plate'] ?? '');
        $bankCode = trim($_POST['bank_code'] ?? '');
        $accountNumber = trim($_POST['account_number'] ?? '');
        $accountName = trim($_POST['account_name'] ?? '');
        $banks = nigerian_banks_list();
        $bankName = $banks[$bankCode] ?? null;

        if ($name === '' || $phone === '') {
            $errors[] = 'Rider name and phone are required.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO riders (full_name, phone, email, vehicle_type, vehicle_plate, status, bank_name, bank_code, account_number, account_name) VALUES (?, ?, ?, ?, ?, 'available', ?, ?, ?, ?)");
            $stmt->execute([$name, $phone, $email ?: null, $vehicle, $plate ?: null, $bankName, $bankCode ?: null, $accountNumber ?: null, $accountName ?: null]);
            $successMsg = 'Rider added successfully.';
        }
    } elseif ($action === 'update_status') {
        $riderId = (int) ($_POST['rider_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if (in_array($status, ['available','on_delivery','offline'], true) && $riderId) {
            $stmt = $pdo->prepare("UPDATE riders SET status = ? WHERE id = ?");
            $stmt->execute([$status, $riderId]);
            $successMsg = 'Rider status updated.';
        }
    } elseif ($action === 'verify_kyc' || $action === 'reject_kyc') {
        $riderId = (int) ($_POST['rider_id'] ?? 0);
        if ($riderId) {
            $newKycStatus = $action === 'verify_kyc' ? 'verified' : 'rejected';
            $pdo->prepare("UPDATE riders SET kyc_status = ? WHERE id = ?")->execute([$newKycStatus, $riderId]);
            $successMsg = 'Rider verification status updated.';
        }
    }
}

$riders = $pdo->query("
    SELECT r.*, (SELECT COUNT(*) FROM shipments s WHERE s.rider_id = r.id) AS shipment_count
    FROM riders r ORDER BY FIELD(r.kyc_status, 'pending', 'rejected', 'verified'), r.full_name ASC
")->fetchAll();

$docTypeLabels = [
    'national_id' => 'National ID',
    'voters_card' => "Voter's Card",
    'international_passport' => 'Passport',
    'drivers_license' => "Driver's License",
    'utility_bill' => 'Utility Bill',
];

$pageTitle = 'Manage Riders';
$activePage = 'riders';
require __DIR__ . '/_admin_header.php';
?>

<div class="dash-header">
    <div><h1>Manage Riders</h1><p style="color:var(--text-muted);font-size:0.9rem;">Add riders and update their availability.</p></div>
    <button class="btn btn-primary" data-modal-target="#addRiderModal"><i class="fa-solid fa-plus"></i> Add Rider</button>
</div>

<?php if ($successMsg): ?><div class="alert alert-success" data-autohide><i class="fa-solid fa-circle-check"></i> <?= e($successMsg) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>

<div class="data-card">
    <div class="data-card-head"><h3>All Riders (<?= count($riders) ?>)</h3></div>
    <?php if (!$riders): ?>
        <div class="table-empty">No riders yet. Add your first rider.</div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="data-table">
        <thead><tr><th>Name</th><th>Phone</th><th>Vehicle (Own)</th><th>Deliveries</th><th>Wallet</th><th>Documents</th><th>KYC</th><th>Status</th><th>Update</th></tr></thead>
        <tbody>
        <?php foreach ($riders as $r): ?>
            <tr>
                <td><strong><?= e($r['full_name']) ?></strong></td>
                <td><?= e($r['phone']) ?></td>
                <td><?= e($r['vehicle_type']) ?><?= $r['vehicle_plate'] ? ' — ' . e($r['vehicle_plate']) : '' ?></td>
                <td><?= (int)$r['shipment_count'] ?></td>
                <td>₦<?= number_format($r['wallet_balance'],0) ?></td>
                <td>
                    <div style="display:flex;flex-direction:column;gap:4px;">
                        <?php if ($r['selfie_path']): ?><a href="<?= BASE_URL ?>/admin/view_document.php?account_type=rider&id=<?= $r['id'] ?>&field=selfie_path" target="_blank" class="btn btn-ghost btn-sm">Selfie</a><?php endif; ?>
                        <?php if ($r['vehicle_photo_path']): ?><a href="<?= BASE_URL ?>/admin/view_document.php?account_type=rider&id=<?= $r['id'] ?>&field=vehicle_photo_path" target="_blank" class="btn btn-ghost btn-sm">Vehicle Photo</a><?php endif; ?>
                        <?php if ($r['verification_doc_path']): ?><a href="<?= BASE_URL ?>/admin/view_document.php?account_type=rider&id=<?= $r['id'] ?>&field=verification_doc_path" target="_blank" class="btn btn-ghost btn-sm"><?= e($docTypeLabels[$r['verification_doc_type']] ?? 'Document') ?></a><?php endif; ?>
                        <?php if (!$r['selfie_path'] && !$r['vehicle_photo_path'] && !$r['verification_doc_path']): ?><span style="color:var(--text-muted);font-size:0.8rem;">None on file</span><?php endif; ?>
                    </div>
                </td>
                <td>
                    <?php $kycMap = ['pending' => 'st-pickedup', 'verified' => 'st-delivered', 'rejected' => 'st-cancelled']; ?>
                    <span class="status-badge <?= $kycMap[$r['kyc_status']] ?>"><?= ucfirst($r['kyc_status']) ?></span>
                    <div style="display:flex;gap:6px;margin-top:6px;">
                        <?php if ($r['kyc_status'] !== 'verified'): ?>
                            <form method="POST" action="<?= BASE_URL ?>/admin/riders.php">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="verify_kyc">
                                <input type="hidden" name="rider_id" value="<?= $r['id'] ?>">
                                <button type="submit" class="btn btn-primary btn-sm">Verify</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($r['kyc_status'] !== 'rejected'): ?>
                            <form method="POST" action="<?= BASE_URL ?>/admin/riders.php">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="reject_kyc">
                                <input type="hidden" name="rider_id" value="<?= $r['id'] ?>">
                                <button type="submit" class="btn btn-ghost btn-sm">Reject</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </td>
                <td>
                    <?php
                        $badgeMap = ['available' => 'st-delivered', 'on_delivery' => 'st-intransit', 'offline' => 'st-cancelled'];
                        $labelMap = ['available' => 'Available', 'on_delivery' => 'On Delivery', 'offline' => 'Offline'];
                    ?>
                    <span class="status-badge <?= $badgeMap[$r['status']] ?>"><?= $labelMap[$r['status']] ?></span>
                </td>
                <td>
                    <form method="POST" action="<?= BASE_URL ?>/admin/riders.php" style="display:flex;gap:8px;align-items:center;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="rider_id" value="<?= $r['id'] ?>">
                        <select name="status" class="form-control" style="padding:6px 10px;font-size:0.8rem;width:auto;" onchange="this.form.submit()">
                            <option value="available" <?= $r['status']==='available'?'selected':'' ?>>Available</option>
                            <option value="on_delivery" <?= $r['status']==='on_delivery'?'selected':'' ?>>On Delivery</option>
                            <option value="offline" <?= $r['status']==='offline'?'selected':'' ?>>Offline</option>
                        </select>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<div class="modal-overlay" id="addRiderModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3>Add New Rider</h3>
            <button class="modal-close" data-modal-close><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>/admin/riders.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_rider">
            <p style="font-size:0.8rem;color:var(--text-muted);margin-bottom:16px;">Riders are independent freelance partners who use their own vehicle — SwiftHaul does not own or provide vehicles.</p>
            <div class="form-row">
                <div class="form-group"><label>Full Name</label><input type="text" name="full_name" class="form-control" required></div>
                <div class="form-group"><label>Phone</label><input type="text" name="phone" class="form-control" required></div>
            </div>
            <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control"></div>
            <div class="form-row">
                <div class="form-group">
                    <label>Vehicle Type (own vehicle)</label>
                    <select name="vehicle_type" class="form-control">
                        <option>Motorbike</option><option>Van</option><option>Truck</option><option>Bicycle</option><option>Car</option>
                    </select>
                </div>
                <div class="form-group"><label>Plate Number</label><input type="text" name="vehicle_plate" class="form-control"></div>
            </div>
            <hr style="border:none;border-top:1px solid var(--border);margin:18px 0;">
            <p style="font-size:0.8rem;font-weight:600;color:var(--navy-900);margin-bottom:12px;">Payout Bank Details (for Paystack transfers)</p>
            <div class="form-group">
                <label>Bank</label>
                <select name="bank_code" class="form-control">
                    <option value="">— Select bank —</option>
                    <?php foreach (nigerian_banks_list() as $code => $name): ?>
                        <option value="<?= e($code) ?>"><?= e($name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Account Number</label><input type="text" name="account_number" class="form-control" maxlength="10"></div>
                <div class="form-group"><label>Account Name</label><input type="text" name="account_name" class="form-control"></div>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Add Rider</button>
        </form>
    </div>
</div>

<?php require __DIR__ . '/_admin_footer.php'; ?>
