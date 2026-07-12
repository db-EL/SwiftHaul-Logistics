<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/security.php';

if (is_logged_in()) {
    redirect(is_admin() ? '/admin/index.php' : '/dashboard.php');
}

$errors = [];
$rateLimitKey = 'login:' . client_ip();
$blockedSeconds = rate_limit_seconds_remaining($pdo, $rateLimitKey);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if ($blockedSeconds > 0) {
        $errors[] = 'Too many failed login attempts. Please try again in ' . ceil($blockedSeconds / 60) . ' minute(s).';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            rate_limit_record_failure($pdo, $rateLimitKey, maxAttempts: 6, windowSeconds: 300, blockSeconds: 900);
            $errors[] = 'Invalid email or password.';
        } else {
            rate_limit_reset($pdo, $rateLimitKey);

            // Regenerate the session ID on login to prevent session fixation
            // (an attacker who fixed a session ID before login gains nothing).
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            redirect($user['role'] === 'admin' ? '/admin/index.php' : '/dashboard.php');
        }
    }
}

$pageTitle = 'Login';
require __DIR__ . '/includes/header.php';
?>

<div class="auth-wrap">
    <div class="auth-box reveal">
        <div class="form-card">
            <h2>Welcome Back</h2>
            <p class="sub">Log in to manage your shipments.</p>

            <?php if ($errors): ?>
                <div class="alert alert-error">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <div><?= implode('<br>', array_map('e', $errors)) ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= BASE_URL ?>/login.php" novalidate>
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

            <p class="auth-switch">
                <a href="<?= BASE_URL ?>/forgot-password.php">Forgot your password?</a>
            </p>
            <p class="auth-switch">Don't have an account? <a href="<?= BASE_URL ?>/register.php">Sign up</a></p>

            <!-- <div class="demo-box">
                <strong>Demo logins</strong> (password for all: <strong>Demo@1234</strong>)<br>
                Admin: admin@swifthaul.com<br>
                Customer: chidinma@example.com or tunde@example.com
            </div> -->
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
