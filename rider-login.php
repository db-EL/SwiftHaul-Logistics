<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/security.php';

if (is_rider_logged_in()) {
    redirect('/rider/dashboard.php');
}

$errors = [];
$rateLimitKey = 'rider_login:' . client_ip();
$blockedSeconds = rate_limit_seconds_remaining($pdo, $rateLimitKey);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if ($blockedSeconds > 0) {
        $errors[] = 'Too many failed login attempts. Please try again in ' . ceil($blockedSeconds / 60) . ' minute(s).';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = $pdo->prepare("SELECT * FROM riders WHERE email = ?");
        $stmt->execute([$email]);
        $rider = $stmt->fetch();

        if (!$rider || !$rider['password_hash'] || !password_verify($password, $rider['password_hash'])) {
            rate_limit_record_failure($pdo, $rateLimitKey, maxAttempts: 6, windowSeconds: 300, blockSeconds: 900);
            $errors[] = 'Invalid email or password.';
        } else {
            rate_limit_reset($pdo, $rateLimitKey);
            session_regenerate_id(true);
            $_SESSION['rider_id'] = $rider['id'];
            $_SESSION['rider_name'] = $rider['full_name'];
            redirect('/rider/dashboard.php');
        }
    }
}

$pageTitle = 'Rider Login';
require __DIR__ . '/includes/header.php';
?>

<div class="auth-wrap">
    <div class="auth-box reveal">
        <div class="form-card">
            <h2>Rider Login</h2>
            <p class="sub">Log in to see available orders and manage your deliveries.</p>

            <?php if ($errors): ?>
                <div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i><div><?= implode('<br>', array_map('e', $errors)) ?></div></div>
            <?php endif; ?>

            <form method="POST" action="<?= BASE_URL ?>/rider-login.php" novalidate>
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" value="<?= e($_POST['email'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Log In <i class="fa-solid fa-arrow-right"></i></button>
            </form>

            <p class="auth-switch"><a href="<?= BASE_URL ?>/rider-forgot-password.php">Forgot your password?</a></p>
            <p class="auth-switch">New rider? <a href="<?= BASE_URL ?>/rider-register.php">Create an account</a></p>

            <!-- <div class="demo-box">
                <strong>Demo rider logins</strong> (password: <strong>Demo@1234</strong>)<br>
                emeka.rider@example.com or grace.rider@example.com
            </div> -->
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
