<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

// 1. A customer pays the FULL delivery fee via Paystack Checkout.
//    That money is credited straight to YOUR Paystack balance —
//    not the rider's. Nothing extra needs to happen for that part.
// 2. When a delivery is confirmed, only the RIDER'S SHARE is sent
//    out via Paystack Transfer. Your commission is simply the part
//    that's never transferred out — it stays in your balance.
// 3. So "platform fee revenue" isn't a separate transaction; it's
//    everything collected minus everything paid out to riders.
//    This page just makes that number visible.
// ------------------------------------------------------------

$grossCollected = (float) $pdo->query("SELECT COALESCE(SUM(fee),0) v FROM shipments WHERE payment_status = 'paid'")->fetch()['v'];

$paidOutToRiders = (float) $pdo->query("SELECT COALESCE(SUM(rider_earning),0) v FROM rider_earnings WHERE status = 'paid'")->fetch()['v'];

$pendingRiderPayouts = (float) $pdo->query("SELECT COALESCE(SUM(rider_earning),0) v FROM rider_earnings WHERE status = 'pending'")->fetch()['v'];

// Commission actually realized: only counted once a delivery has gone
// through the full earnings cycle (i.e. a rider_earnings row exists).
$realizedCommission = (float) $pdo->query("SELECT COALESCE(SUM(platform_commission),0) v FROM rider_earnings")->fetch()['v'];

// Fully-yours revenue: paid shipments that were never assigned/completed
// with a rider at all (no rider_earnings row was ever created for them),
// so 100% of the fee stayed with you rather than accruing a rider share.
$noRiderRevenue = (float) $pdo->query("
    SELECT COALESCE(SUM(s.fee),0) v FROM shipments s
    WHERE s.payment_status = 'paid'
    AND s.id NOT IN (SELECT shipment_id FROM rider_earnings)
")->fetch()['v'];

// The number that matters most: what's sitting in your Paystack balance
// right now that isn't earmarked to go out to a rider.
$netInYourBalance = $grossCollected - $paidOutToRiders - $pendingRiderPayouts;

$deliveries = $pdo->query("
    SELECT re.*, s.tracking_id, r.full_name AS rider_name, s.delivered_confirmed_at
    FROM rider_earnings re
    JOIN shipments s ON s.id = re.shipment_id
    JOIN riders r ON r.id = re.rider_id
    ORDER BY re.created_at DESC
    LIMIT 25
")->fetchAll();

$pageTitle = 'Platform Revenue';
$activePage = 'revenue';
require __DIR__ . '/_admin_header.php';
?>

<div class="dash-header">
    <div>
        <h1>Platform Revenue</h1>
        <p style="color:var(--text-muted);font-size:0.9rem;">SwiftHaul keeps <?= PLATFORM_COMMISSION_PERCENT ?>% of every delivery fee automatically — customers pay the full fee to your Paystack balance, and only the rider's <?= 100 - PLATFORM_COMMISSION_PERCENT ?>% share is ever transferred out. This page shows what that's actually added up to.</p>
    </div>
</div>

<div class="stat-cards">
    <div class="stat-card">
        <div class="stat-icon" style="background:#e8fbf3;color:#0b8a5c;"><i class="fa-solid fa-sack-dollar"></i></div>
        <div class="stat-value">₦<?= number_format($netInYourBalance,0) ?></div>
        <div class="stat-desc">Currently Yours in Paystack Balance</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#eaf1ff;color:#2952cc;"><i class="fa-solid fa-wallet"></i></div>
        <div class="stat-value">₦<?= number_format($grossCollected,0) ?></div>
        <div class="stat-desc">Gross Collected From Customers</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fff4e0;color:#b8790a;"><i class="fa-solid fa-motorcycle"></i></div>
        <div class="stat-value">₦<?= number_format($paidOutToRiders,0) ?></div>
        <div class="stat-desc">Paid Out to Riders (Completed)</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fdeee0;color:var(--orange-500);"><i class="fa-solid fa-clock"></i></div>
        <div class="stat-value">₦<?= number_format($pendingRiderPayouts,0) ?></div>
        <div class="stat-desc">Owed to Riders, Not Yet Paid</div>
    </div>
</div>

<div class="data-card">
    <div class="data-card-head"><h3>Commission Breakdown</h3></div>
    <div style="padding:24px;display:grid;grid-template-columns:1fr 1fr;gap:24px;">
        <div>
            <div class="meta-label">Commission earned on completed rider deliveries</div>
            <div style="font-family:'Sora';font-size:1.6rem;font-weight:800;color:var(--navy-900);">₦<?= number_format($realizedCommission,0) ?></div>
            <p style="color:var(--text-muted);font-size:0.85rem;margin-top:6px;"><?= PLATFORM_COMMISSION_PERCENT ?>% commission across every shipment a rider has been paid (or is owed) for.</p>
        </div>
        <div>
            <div class="meta-label">Revenue from shipments with no rider involved</div>
            <div style="font-family:'Sora';font-size:1.6rem;font-weight:800;color:var(--navy-900);">₦<?= number_format($noRiderRevenue,0) ?></div>
            <p style="color:var(--text-muted);font-size:0.85rem;margin-top:6px;">Paid shipments that never had a rider assigned/completed — 100% of the fee stayed with you.</p>
        </div>
    </div>
</div>

<div class="data-card">
    <div class="data-card-head"><h3>Recent Rider Deliveries — Fee Split</h3></div>
    <?php if (!$deliveries): ?>
        <div class="table-empty">No completed rider deliveries yet.</div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="data-table">
        <thead><tr><th>Shipment</th><th>Rider</th><th>Fee Collected</th><th>Your Commission</th><th>Rider Share</th><th>Payout Status</th></tr></thead>
        <tbody>
        <?php foreach ($deliveries as $d): ?>
            <tr>
                <td><?= e($d['tracking_id']) ?></td>
                <td><?= e($d['rider_name']) ?></td>
                <td>₦<?= number_format($d['shipment_fee'],0) ?></td>
                <td><strong style="color:#0b8a5c;">₦<?= number_format($d['platform_commission'],0) ?></strong></td>
                <td>₦<?= number_format($d['rider_earning'],0) ?></td>
                <td>
                    <?php if ($d['status'] === 'paid'): ?>
                        <span class="status-badge st-delivered">Rider Paid</span>
                    <?php else: ?>
                        <span class="status-badge st-pickedup">Rider Payout Pending</span>
                    <?php endif; ?>
                </td>
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
        <strong>Nothing extra to configure in Paystack for this.</strong> Because checkout charges the customer directly to your account and only the rider's cut is ever transferred out, your commission is retained automatically on every delivery — the same mechanic Bolt/Uber use, just implemented as "collect the whole fare, pay out the driver's share" rather than a real-time split. Adjust the split via <code>PLATFORM_COMMISSION_PERCENT</code> in <code>config/config.php</code>.
    </div>
</div>

<?php require __DIR__ . '/_admin_footer.php'; ?>
