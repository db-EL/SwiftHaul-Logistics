<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$totalShipments = $pdo->query("SELECT COUNT(*) c FROM shipments")->fetch()['c'];
$totalCustomers = $pdo->query("SELECT COUNT(*) c FROM users WHERE role='customer'")->fetch()['c'];
$grossBookings = $pdo->query("SELECT COALESCE(SUM(fee),0) s FROM shipments WHERE current_status != 'Cancelled'")->fetch()['s'];
$activeRiders = $pdo->query("SELECT COUNT(*) c FROM riders WHERE status != 'offline'")->fetch()['c'];
$platformCommissionEarned = $pdo->query("SELECT COALESCE(SUM(platform_commission),0) v FROM rider_earnings")->fetch()['v'];

$pendingCustomerKyc = $pdo->query("SELECT COUNT(*) c FROM users WHERE role = 'customer' AND kyc_status = 'pending'")->fetch()['c'];
$pendingRiderKyc = $pdo->query("SELECT COUNT(*) c FROM riders WHERE kyc_status = 'pending'")->fetch()['c'];
$openComplaints = $pdo->query("SELECT COUNT(*) c FROM delivery_complaints WHERE status IN ('open','investigating')")->fetch()['c'];

$statusCounts = $pdo->query("SELECT current_status, COUNT(*) c FROM shipments GROUP BY current_status")->fetchAll();
$statusLabels = array_column($statusCounts, 'current_status');
$statusValues = array_column($statusCounts, 'c');

$recentShipments = $pdo->query("SELECT s.*, u.full_name AS customer_name FROM shipments s JOIN users u ON u.id = s.customer_id ORDER BY s.created_at DESC LIMIT 6")->fetchAll();

// Last 7 days shipment volume
$dailyStmt = $pdo->query("SELECT DATE(created_at) d, COUNT(*) c FROM shipments GROUP BY DATE(created_at) ORDER BY d ASC");
$daily = $dailyStmt->fetchAll();
$dailyLabels = array_map(fn($r) => date('M j', strtotime($r['d'])), $daily);
$dailyValues = array_column($daily, 'c');

$pageTitle = 'Admin Overview';
$activePage = 'overview';
require __DIR__ . '/_admin_header.php';
?>

<div class="dash-header">
    <div><h1>Admin Overview</h1><p style="color:var(--text-muted);font-size:0.9rem;">Business performance at a glance.</p></div>
</div>

<?php if ($pendingCustomerKyc || $pendingRiderKyc || $openComplaints): ?>
<div class="alert alert-info">
    <i class="fa-solid fa-circle-info"></i>
    <div>
        Needs attention:
        <?php if ($pendingRiderKyc): ?><a href="<?= BASE_URL ?>/admin/riders.php" style="color:var(--navy-900);font-weight:700;text-decoration:underline;"><?= $pendingRiderKyc ?> rider verification<?= $pendingRiderKyc == 1 ? '' : 's' ?> pending</a><?php endif; ?>
        <?php if ($pendingRiderKyc && ($pendingCustomerKyc || $openComplaints)): ?> · <?php endif; ?>
        <?php if ($pendingCustomerKyc): ?><a href="<?= BASE_URL ?>/admin/customers.php" style="color:var(--navy-900);font-weight:700;text-decoration:underline;"><?= $pendingCustomerKyc ?> customer verification<?= $pendingCustomerKyc == 1 ? '' : 's' ?> pending</a><?php endif; ?>
        <?php if ($pendingCustomerKyc && $openComplaints): ?> · <?php endif; ?>
        <?php if ($openComplaints): ?><a href="<?= BASE_URL ?>/admin/complaints.php" style="color:var(--navy-900);font-weight:700;text-decoration:underline;"><?= $openComplaints ?> open complaint<?= $openComplaints == 1 ? '' : 's' ?></a><?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="stat-cards">
    <div class="stat-card"><div class="stat-icon" style="background:#eaf1ff;color:#2952cc;"><i class="fa-solid fa-boxes-stacked"></i></div><div class="stat-value"><?= $totalShipments ?></div><div class="stat-desc">Total Shipments</div></div>
    <div class="stat-card"><div class="stat-icon" style="background:#fff4e0;color:#b8790a;"><i class="fa-solid fa-users"></i></div><div class="stat-value"><?= $totalCustomers ?></div><div class="stat-desc">Customers</div></div>
    <div class="stat-card"><div class="stat-icon" style="background:#e8fbf3;color:#0b8a5c;"><i class="fa-solid fa-motorcycle"></i></div><div class="stat-value"><?= $activeRiders ?></div><div class="stat-desc">Active Riders</div></div>
    <a href="<?= BASE_URL ?>/admin/revenue.php" class="stat-card" style="display:block;text-decoration:none;"><div class="stat-icon" style="background:#fdeee0;color:var(--orange-500);"><i class="fa-solid fa-sack-dollar"></i></div><div class="stat-value">₦<?= number_format($platformCommissionEarned,0) ?></div><div class="stat-desc">Your Platform Commission <i class="fa-solid fa-arrow-right" style="font-size:0.7rem;"></i></div></a>
</div>
<p style="color:var(--text-muted);font-size:0.82rem;margin-top:-20px;margin-bottom:30px;">Gross bookings across all shipments: ₦<?= number_format($grossBookings,0) ?>. <a href="<?= BASE_URL ?>/admin/revenue.php" style="color:var(--orange-500);font-weight:600;">See the full revenue breakdown <i class="fa-solid fa-arrow-right" style="font-size:0.75rem;"></i></a></p>

<div style="display:grid;grid-template-columns:1.4fr 1fr;gap:24px;margin-bottom:30px;">
    <div class="data-card">
        <div class="data-card-head"><h3>Shipment Volume (Last 7 Days)</h3></div>
        <div style="padding:24px;"><canvas id="volumeChart" height="110"></canvas></div>
    </div>
    <div class="data-card">
        <div class="data-card-head"><h3>Shipments by Status</h3></div>
        <div style="padding:24px;"><canvas id="statusChart" height="110"></canvas></div>
    </div>
</div>

<div class="data-card">
    <div class="data-card-head">
        <h3>Recent Shipments</h3>
        <a href="<?= BASE_URL ?>/admin/shipments.php" class="btn btn-ghost btn-sm">View All</a>
    </div>
    <div style="overflow-x:auto;">
    <table class="data-table">
        <thead><tr><th>Tracking ID</th><th>Customer</th><th>Fee</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach ($recentShipments as $s): ?>
            <tr>
                <td><strong><?= e($s['tracking_id']) ?></strong></td>
                <td><?= e($s['customer_name']) ?></td>
                <td>₦<?= number_format($s['fee'],0) ?></td>
                <td><span class="status-badge <?= status_badge_class($s['current_status']) ?>"><?= e($s['current_status']) ?></span></td>
                <td><?= date('M j, Y', strtotime($s['created_at'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('volumeChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($dailyLabels) ?>,
        datasets: [{
            label: 'Shipments',
            data: <?= json_encode($dailyValues) ?>,
            borderColor: '#FF6B35',
            backgroundColor: 'rgba(255,107,53,0.12)',
            fill: true, tension: 0.4, pointRadius: 3,
        }]
    },
    options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
});

new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($statusLabels) ?>,
        datasets: [{
            data: <?= json_encode($statusValues) ?>,
            backgroundColor: ['#2952cc', '#b8790a', '#0b6fb8', '#FF6B35', '#0b8a5c', '#E4483C'],
        }]
    },
    options: { plugins: { legend: { position: 'bottom' } } }
});
</script>

<?php require __DIR__ . '/_admin_footer.php'; ?>
