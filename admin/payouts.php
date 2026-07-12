<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/payouts.php';
require_admin();

$successMsg = null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'payout_rider') {
    verify_csrf();
    $riderId = (int) ($_POST['rider_id'] ?? 0);

    $result = attempt_rider_payout($pdo, $riderId);
    if ($result['success']) {
        $successMsg = $result['message'];
    } else {
        $errors[] = $result['message'];
    }
}

$riders = $pdo->query("SELECT * FROM riders WHERE wallet_balance > 0 OR total_earned > 0 ORDER BY wallet_balance DESC")->fetchAll();
$pendingEarnings = $pdo->query("
    SELECT re.*, r.full_name AS rider_name, s.tracking_id
    FROM rider_earnings re
    JOIN riders r ON r.id = re.rider_id
    JOIN shipments s ON s.id = re.shipment_id
    WHERE re.status = 'pending'
    ORDER BY re.created_at DESC
")->fetchAll();
$recentAttempts = $pdo->query("
    SELECT pa.*, r.full_name AS rider_name
    FROM payout_attempts pa
    JOIN riders r ON r.id = pa.rider_id
    ORDER BY pa.created_at DESC
    LIMIT 15
")->fetchAll();

$totalPending = array_sum(array_column($riders, 'wallet_balance'));

$pageTitle = 'Rider Payouts';
$activePage = 'payouts';
require __DIR__ . '/_admin_header.php';
?>

<div class="dash-header">
    <div>
        <h1>Rider Payouts</h1>
        <p style="color:var(--text-muted);font-size:0.9rem;">Payouts are <strong>automatic</strong>: the moment a paid shipment is marked Delivered, SwiftHaul pays the rider their <?= 100 - PLATFORM_COMMISSION_PERCENT ?>% share via Paystack immediately — no admin action needed. This page is only for reviewing history and manually retrying the rare payout that couldn't complete automatically.</p>
    </div>
</div>

<?php if ($successMsg): ?><div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= $successMsg ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <div><?= implode('<br>', array_map('e', $errors)) ?></div></div><?php endif; ?>

<div class="stat-cards" style="grid-template-columns:repeat(2,1fr);">
    <div class="stat-card"><div class="stat-icon" style="background:#fff4e0;color:#b8790a;"><i class="fa-solid fa-wallet"></i></div><div class="stat-value">₦<?= number_format($totalPending,0) ?></div><div class="stat-desc">Total Pending Payouts</div></div>
    <div class="stat-card"><div class="stat-icon" style="background:#e8fbf3;color:#0b8a5c;"><i class="fa-solid fa-hand-holding-dollar"></i></div><div class="stat-value"><?= count($pendingEarnings) ?></div><div class="stat-desc">Unpaid Delivery Earnings</div></div>
</div>

<div class="data-card">
    <div class="data-card-head"><h3>Rider Wallets</h3></div>
    <?php if (!$riders): ?>
        <div class="table-empty">No rider earnings yet — earnings appear here once a paid shipment is marked Delivered.</div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="data-table">
        <thead><tr><th>Rider</th><th>Bank Details</th><th>Wallet Balance</th><th>Total Earned</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($riders as $r): ?>
            <tr>
                <td><strong><?= e($r['full_name']) ?></strong><br><span style="color:var(--text-muted);font-size:0.8rem;"><?= e($r['vehicle_type']) ?><?= $r['vehicle_plate'] ? ' — ' . e($r['vehicle_plate']) : '' ?></span></td>
                <td>
                    <?php if ($r['account_number']): ?>
                        <?= e($r['bank_name']) ?><br><span style="color:var(--text-muted);font-size:0.8rem;"><?= e($r['account_number']) ?> — <?= e($r['account_name']) ?></span>
                    <?php else: ?>
                        <span style="color:var(--danger);font-size:0.82rem;">No bank details on file</span>
                    <?php endif; ?>
                </td>
                <td><strong>₦<?= number_format($r['wallet_balance'],0) ?></strong></td>
                <td>₦<?= number_format($r['total_earned'],0) ?></td>
                <td>
                    <?php if ((float)$r['wallet_balance'] > 0): ?>
                        <form method="POST" action="<?= BASE_URL ?>/admin/payouts.php" onsubmit="return confirm('Retry payout of ₦<?= number_format($r['wallet_balance'],0) ?> to <?= e($r['full_name']) ?> now?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="payout_rider">
                            <input type="hidden" name="rider_id" value="<?= $r['id'] ?>">
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-rotate-right"></i> Retry Payout</button>
                        </form>
                    <?php else: ?>
                        <span style="color:var(--text-muted);font-size:0.82rem;">Up to date</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<div class="data-card">
    <div class="data-card-head"><h3>Earnings Ledger (Pending)</h3></div>
    <?php if (!$pendingEarnings): ?>
        <div class="table-empty">Nothing pending — all delivered, paid shipments have been paid out.</div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="data-table">
        <thead><tr><th>Shipment</th><th>Rider</th><th>Delivery Fee</th><th>Commission (<?= PLATFORM_COMMISSION_PERCENT ?>%)</th><th>Rider Earning</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach ($pendingEarnings as $pe): ?>
            <tr>
                <td><?= e($pe['tracking_id']) ?></td>
                <td><?= e($pe['rider_name']) ?></td>
                <td>₦<?= number_format($pe['shipment_fee'],0) ?></td>
                <td>₦<?= number_format($pe['platform_commission'],0) ?></td>
                <td><strong>₦<?= number_format($pe['rider_earning'],0) ?></strong></td>
                <td><?= date('M j, Y', strtotime($pe['created_at'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<div class="data-card">
    <div class="data-card-head"><h3>Recent Payout Attempts (Audit Log)</h3></div>
    <?php if (!$recentAttempts): ?>
        <div class="table-empty">No payout attempts logged yet.</div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="data-table">
        <thead><tr><th>Rider</th><th>Amount</th><th>Result</th><th>Note</th><th>When</th></tr></thead>
        <tbody>
        <?php foreach ($recentAttempts as $a): ?>
            <tr>
                <td><?= e($a['rider_name']) ?></td>
                <td>₦<?= number_format($a['amount'],0) ?></td>
                <td><?php if ($a['success']): ?><span class="status-badge st-delivered">Success</span><?php else: ?><span class="status-badge st-cancelled">Failed</span><?php endif; ?></td>
                <td style="max-width:260px;font-size:0.82rem;color:var(--text-muted);"><?= e($a['note']) ?></td>
                <td><?= date('M j, g:i A', strtotime($a['created_at'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<div class="alert alert-info">
    <i class="fa-solid fa-circle-info"></i>
    <div>
        Payouts fire automatically the instant a paid shipment is marked Delivered — riders don't wait on an admin.
        If an automatic attempt fails (e.g. a network blip, or Paystack Transfers not yet enabled on your account), the balance stays safely in the rider's wallet and the "Retry Payout" button above becomes available.
        As a further safety net, <code>cron/retry_payouts.php</code> can be scheduled (e.g. every 15 minutes) to retry any stuck balances automatically — see the README — so riders still get paid even if nobody checks this page.
    </div>
</div>

<?php require __DIR__ . '/_admin_footer.php'; ?>
