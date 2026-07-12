<?php
// Expects $pageTitle to optionally be set before include
$pageTitle = $pageTitle ?? SITE_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> | <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<script>const BASE_URL_JS = "<?= BASE_URL ?>";</script>
</head>
<body class="<?= !empty($hasHeroSection) ? 'has-hero' : '' ?>">

<nav class="navbar" id="navbar">
    <div class="nav-container">
        <a href="<?= BASE_URL ?>/index.php" class="nav-logo">
            <i class="fa-solid fa-box-open"></i> Swift<span>Haul</span>
        </a>
        <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation">
            <i class="fa-solid fa-bars"></i>
        </button>
        <ul class="nav-links" id="navLinks">
            <li><a href="<?= BASE_URL ?>/index.php">Home</a></li>
            <li><a href="<?= BASE_URL ?>/services.php">Services</a></li>
            <li><a href="<?= BASE_URL ?>/track.php">Track Package</a></li>
            <li><a href="<?= BASE_URL ?>/about.php">About</a></li>
            <li><a href="<?= BASE_URL ?>/contact.php">Contact</a></li>
            <li class="nav-dropdown">
                <button class="nav-dropdown-trigger" aria-haspopup="true" aria-expanded="false">More <i class="fa-solid fa-chevron-down"></i></button>
                <ul class="nav-dropdown-menu">
                    <li><a href="<?= BASE_URL ?>/reviews.php"><i class="fa-solid fa-star"></i> Reviews</a></li>
                    <li><a href="<?= BASE_URL ?>/rider-register.php"><i class="fa-solid fa-motorcycle"></i> Ride With Us</a></li>
                    <?php if (!is_rider_logged_in() && !is_logged_in()): ?>
                        <li><a href="<?= BASE_URL ?>/rider-login.php"><i class="fa-solid fa-right-to-bracket"></i> Rider Login</a></li>
                    <?php endif; ?>
                </ul>
            </li>
            <?php if (is_rider_logged_in()): ?>
                <li><a href="<?= BASE_URL ?>/rider/dashboard.php" class="nav-cta">Rider Dashboard</a></li>
                <li><a href="<?= BASE_URL ?>/rider-logout.php">Logout</a></li>
            <?php elseif (is_logged_in()): ?>
                <?php if (is_admin()): ?>
                    <li><a href="<?= BASE_URL ?>/admin/index.php" class="nav-cta">Admin Panel</a></li>
                <?php else: ?>
                    <li><a href="<?= BASE_URL ?>/dashboard.php" class="nav-cta">Dashboard</a></li>
                <?php endif; ?>
                <li><a href="<?= BASE_URL ?>/logout.php">Logout</a></li>
            <?php else: ?>
                <li><a href="<?= BASE_URL ?>/login.php">Login</a></li>
                <li><a href="<?= BASE_URL ?>/register.php" class="nav-cta">Ship Now</a></li>
            <?php endif; ?>
        </ul>
    </div>
</nav>
