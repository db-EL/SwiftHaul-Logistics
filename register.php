<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/file_upload.php';

if (is_logged_in()) {
    redirect(is_admin() ? '/admin/index.php' : '/dashboard.php');
}

$errors = [];
$rateLimitKey = 'register:' . client_ip();
$blockedSeconds = rate_limit_seconds_remaining($pdo, $rateLimitKey);
$docTypes = [
    'national_id' => 'National ID Card',
    'voters_card' => "Voter's Card",
    'international_passport' => 'International Passport',
    'drivers_license' => "Driver's License",
    'utility_bill' => 'Utility Bill (shows current address)',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if ($blockedSeconds > 0) {
        $errors[] = 'Too many attempts from this connection. Please try again in ' . ceil($blockedSeconds / 60) . ' minute(s).';
    } else {

    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $docType = $_POST['verification_doc_type'] ?? '';

    if ($fullName === '' || strlen($fullName) < 3) $errors[] = 'Please enter your full name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if ($phone === '' || strlen($phone) < 7) $errors[] = 'Please enter a valid phone number.';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';
    if (!isset($docTypes[$docType])) $errors[] = 'Please select the type of document you\'re uploading.';

    if (!$errors) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with that email already exists. Please log in instead.';
        }
    }

    // Uploads are validated even if earlier fields already failed, so the
    // person sees every problem at once rather than re-uploading repeatedly.
    $selfiePath = handle_photo_upload('selfie', 'selfies', $errors);
    $docPath = handle_document_upload('verification_doc', $errors);

    if ($errors) {
        rate_limit_record_failure($pdo, $rateLimitKey, maxAttempts: 10, windowSeconds: 600, blockSeconds: 900);
    }

    if (!$errors && $selfiePath && $docPath) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (full_name, email, phone, password_hash, role, address, selfie_path, verification_doc_type, verification_doc_path, kyc_status) VALUES (?, ?, ?, ?, 'customer', ?, ?, ?, ?, 'pending')");
        $stmt->execute([$fullName, $email, $phone, $hash, $address, $selfiePath, $docType, $docPath]);

        session_regenerate_id(true);
        $_SESSION['user_id'] = $pdo->lastInsertId();
        $_SESSION['user_name'] = $fullName;
        $_SESSION['role'] = 'customer';

        flash('success', 'Welcome to SwiftHaul! Your account has been created.');
        redirect('/dashboard.php');
    }

    }
}

$pageTitle = 'Create an Account';
require __DIR__ . '/includes/header.php';
?>

<div class="auth-wrap">
    <div class="auth-box reveal">
        <div class="form-card">
            <h2>Create Your Account</h2>
            <p class="sub">Join SwiftHaul and start shipping in minutes.</p>

            <?php if ($errors): ?>
                <div class="alert alert-error">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <div><?= implode('<br>', array_map('e', $errors)) ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= BASE_URL ?>/register.php" enctype="multipart/form-data" novalidate>
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" class="form-control" value="<?= e($_POST['full_name'] ?? '') ?>" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" value="<?= e($_POST['email'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="text" id="phone" name="phone" class="form-control" value="<?= e($_POST['phone'] ?? '') ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="address">Address</label>
                    <input type="text" id="address" name="address" class="form-control" value="<?= e($_POST['address'] ?? '') ?>">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" class="form-control" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="6">
                    </div>
                </div>

                <hr style="border:none;border-top:1px solid var(--border);margin:20px 0;">
                <p style="font-size:0.85rem;font-weight:600;color:var(--navy-900);margin-bottom:4px;"><i class="fa-solid fa-shield-halved"></i> Identity Verification</p>
                <p style="font-size:0.78rem;color:var(--text-muted);margin-bottom:16px;">Required so we can hold accounts accountable in the rare event of a delivery dispute. Reviewed by our team; never shown publicly. See our <a href="<?= BASE_URL ?>/privacy-policy.php" style="color:var(--orange-500);">Privacy Policy</a> for how this is stored and used.</p>

                <div class="form-group">
                    <label for="selfie">A Clear Selfie</label>
                    <input type="file" id="selfie" name="selfie" class="form-control" accept="image/jpeg,image/png" capture="user" required>
                    <div class="hint">JPG or PNG, under 5MB. Face clearly visible, no filters.</div>
                </div>
                <div class="form-group">
                    <label for="verification_doc_type">Verification Document Type</label>
                    <select id="verification_doc_type" name="verification_doc_type" class="form-control" required>
                        <option value="">— Select document type —</option>
                        <?php foreach ($docTypes as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= ($_POST['verification_doc_type'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="verification_doc">Upload Document</label>
                    <input type="file" id="verification_doc" name="verification_doc" class="form-control" accept="image/jpeg,image/png,application/pdf" required>
                    <div class="hint">JPG, PNG, or PDF, under 5MB. Any one of: national ID, voter's card, international passport, driver's license, or a utility bill showing your current address.</div>
                </div>

                <button type="submit" class="btn btn-primary btn-block" style="margin-top:10px;">Create Account <i class="fa-solid fa-arrow-right"></i></button>
                <p style="text-align:center;font-size:0.78rem;color:var(--text-muted);margin-top:14px;">By creating an account, you agree to our <a href="<?= BASE_URL ?>/privacy-policy.php" style="color:var(--orange-500);font-weight:600;">Privacy Policy</a>.</p>
            </form>

            <p class="auth-switch">Already have an account? <a href="<?= BASE_URL ?>/login.php">Log in</a></p>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
