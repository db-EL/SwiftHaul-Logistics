<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/password_reset.php';

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$errors = [];
$success = false;

$resetRow = $token ? find_valid_reset_token($pdo, $token, 'user') : null;
$account = null;
if ($resetRow) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$resetRow['account_id']]);
    $account = $stmt->fetch();
}
$tokenValid = $resetRow && $account;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (!$tokenValid) {
        $errors[] = 'This reset link is invalid or has expired. Please request a new one.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
        if ($password !== $confirm) $errors[] = 'Passwords do not match.';

        if (!$errors) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $account['id']]);
            mark_reset_token_used($pdo, $resetRow['id'], 'user', $account['id']);
            $success = true;
        }
    }
}

$pageTitle = 'Reset Password';
require __DIR__ . '/includes/header.php';
?>

<div class="auth-wrap">
    <div class="auth-box reveal">
        <div class="form-card">
            <h2>Set a New Password</h2>

            <?php if ($success): ?>
                <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> Your password has been reset. You can now log in.</div>
                <a href="<?= BASE_URL ?>/login.php" class="btn btn-primary btn-block">Go to Login</a>
            <?php else: ?>
                <?php if ($errors): ?>
                    <div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i><div><?= implode('<br>', array_map('e', $errors)) ?></div></div>
                <?php endif; ?>

                <?php if (!$tokenValid): ?>
                    <p style="color:var(--text-muted);margin-bottom:20px;">This link is invalid or has expired.</p>
                    <a href="<?= BASE_URL ?>/forgot-password.php" class="btn btn-primary btn-block">Request a New Link</a>
                <?php else: ?>
                    <p class="sub">Resetting password for <strong><?= e($account['email']) ?></strong></p>
                    <form method="POST" action="<?= BASE_URL ?>/reset-password.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="token" value="<?= e($token) ?>">
                        <div class="form-group">
                            <label for="password">New Password</label>
                            <input type="password" id="password" name="password" class="form-control" required minlength="6">
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="6">
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">Reset Password</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
