<?php
// Include after config/functions are loaded and require_admin() has run.
// Expects $pageTitle and $activePage to be set.
require __DIR__ . '/../includes/header.php';
?>
<div class="dash-layout">
    <aside class="dash-sidebar">
        <a href="<?= BASE_URL ?>/admin/index.php" class="<?= $activePage === 'overview' ? 'active' : '' ?>"><i class="fa-solid fa-gauge"></i> Overview</a>
        <a href="<?= BASE_URL ?>/admin/shipments.php" class="<?= $activePage === 'shipments' ? 'active' : '' ?>"><i class="fa-solid fa-boxes-stacked"></i> Shipments</a>
        <a href="<?= BASE_URL ?>/admin/customers.php" class="<?= $activePage === 'customers' ? 'active' : '' ?>"><i class="fa-solid fa-users"></i> Customers</a>
        <a href="<?= BASE_URL ?>/admin/riders.php" class="<?= $activePage === 'riders' ? 'active' : '' ?>"><i class="fa-solid fa-motorcycle"></i> Riders</a>
        <a href="<?= BASE_URL ?>/admin/payouts.php" class="<?= $activePage === 'payouts' ? 'active' : '' ?>"><i class="fa-solid fa-money-bill-transfer"></i> Payouts</a>
        <a href="<?= BASE_URL ?>/admin/revenue.php" class="<?= $activePage === 'revenue' ? 'active' : '' ?>"><i class="fa-solid fa-sack-dollar"></i> Platform Revenue</a>
        <a href="<?= BASE_URL ?>/admin/messages.php" class="<?= $activePage === 'messages' ? 'active' : '' ?>"><i class="fa-solid fa-envelope"></i> Messages</a>
        <a href="<?= BASE_URL ?>/admin/complaints.php" class="<?= $activePage === 'complaints' ? 'active' : '' ?>"><i class="fa-solid fa-flag"></i> Complaints</a>
        <a href="<?= BASE_URL ?>/admin/reviews.php" class="<?= $activePage === 'reviews' ? 'active' : '' ?>"><i class="fa-solid fa-star"></i> Reviews</a>
        <a href="<?= BASE_URL ?>/index.php"><i class="fa-solid fa-globe"></i> View Site</a>
        <a href="<?= BASE_URL ?>/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </aside>
    <main class="dash-main">
