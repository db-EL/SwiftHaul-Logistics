<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/password_reset.php';

if (is_logged_in()) {
    redirect(is_admin() ? '/admin/index.php' : '/dashboard.php');
}

$submitted = false;
$devResetLink = null;
$errors = [];
$rateLimitKey = 'forgot_password:' . client_ip();
$blockedSeconds = rate_limit_seconds_remaining($pdo, $rateLimitKey);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if ($blockedSeconds > 0) {
        $errors[] = 'Too many requests. Please try again in ' . ceil($blockedSeconds / 60) . ' minute(s).';
    } else {
        $email = trim($_POST['email'] ?? '');
        rate_limit_record_failure($pdo, $rateLimitKey, maxAttempts: 5, windowSeconds: 600, blockSeconds: 900);

        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // Always behave the same whether or not the account exists,
            // so this form can't be used to enumerate registered emails.
            if ($user) {
                $token = create_password_reset_token($pdo, 'user', $user['id']);
                $resetLink = 'http://' . $_SERVER['HTTP_HOST'] . BASE_URL . '/reset-password.php?token=' . $token;

                $sent = send_email(
                    $email,
                    'Reset your SwiftHaul password',
                    "Hi " . e($user['full_name']) . ",<br><br>Click the link below to reset your password (expires in 30 minutes):<br><a href=\"$resetLink\">$resetLink</a><br><br>If you didn't request this, you can ignore this email."
                );

                // Local/dev convenience only: if there's no mail server
                // configured (common on fresh XAMPP installs), show the
                // link directly so you can still test the flow. This is
                // disabled automatically once APP_ENV is 'production'.
                if (!$sent && APP_ENV !== 'production') {
                    $devResetLink = $resetLink;
                }
            }
        }

        $submitted = true;
    }
}

$pageTitle = 'Forgot Password';
require __DIR__ . '/includes/header.php';
?>

<div class="auth-wrap">
    <div class="auth-box reveal">
        <div class="form-card">
            <h2>Reset Your Password</h2>
            <p class="sub">Enter your email and we'll send you a reset link.</p>

            <?php if ($errors): ?>
                <div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i><div><?= implode('<br>', array_map('e', $errors)) ?></div></div>
            <?php elseif ($submitted): ?>
                <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> If an account exists for that email, we've sent a password reset link. It expires in 30 minutes.</div>
                <?php if ($devResetLink): ?>
                    <div class="alert alert-info">
                        <i class="fa-solid fa-circle-info"></i>
                        <div>
                            <strong>Local dev mode:</strong> no mail server is configured, so here's the link directly:<br>
                            <a href="<?= e($devResetLink) ?>"><?= e($devResetLink) ?></a>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (!$submitted || $errors): ?>
            <form method="POST" action="<?= BASE_URL ?>/forgot-password.php">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Send Reset Link</button>
            </form>
            <?php endif; ?>

            <p class="auth-switch">Remembered it? <a href="<?= BASE_URL ?>/login.php">Back to login</a></p>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
