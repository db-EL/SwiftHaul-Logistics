<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/paystack.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/file_upload.php';

if (is_rider_logged_in()) {
    redirect('/rider/dashboard.php');
}

$errors = [];
$rateLimitKey = 'rider_register:' . client_ip();
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
        $errors[] = 'Too many attempts. Please try again in ' . ceil($blockedSeconds / 60) . ' minute(s).';
    } else {
        $name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $vehicle = trim($_POST['vehicle_type'] ?? '');
        $plate = trim($_POST['vehicle_plate'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $bankCode = trim($_POST['bank_code'] ?? '');
        $accountNumber = trim($_POST['account_number'] ?? '');
        $accountName = trim($_POST['account_name'] ?? '');
        $docType = $_POST['verification_doc_type'] ?? '';

        if ($name === '' || strlen($name) < 3) $errors[] = 'Please enter your full name.';
        if ($phone === '' || strlen($phone) < 7) $errors[] = 'Please enter a valid phone number.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
        if ($vehicle === '') $errors[] = 'Please select the vehicle you own.';
        if ($plate === '') $errors[] = 'Please enter your vehicle\'s plate number.';
        if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
        if ($password !== $confirm) $errors[] = 'Passwords do not match.';
        if (!isset($docTypes[$docType])) $errors[] = 'Please select the type of document you\'re uploading.';

        if (!$errors) {
            $stmt = $pdo->prepare("SELECT id FROM riders WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = 'An account with that email already exists. Please log in instead.';
            }
        }

        $selfiePath = handle_photo_upload('selfie', 'selfies', $errors);
        $vehiclePhotoPath = handle_photo_upload('vehicle_photo', 'vehicles', $errors);
        $docPath = handle_document_upload('verification_doc', $errors);

        if ($errors) {
            rate_limit_record_failure($pdo, $rateLimitKey, maxAttempts: 8, windowSeconds: 600, blockSeconds: 900);
        }

        if (!$errors && $selfiePath && $vehiclePhotoPath && $docPath) {
            $banks = nigerian_banks_list();
            $bankName = $banks[$bankCode] ?? null;
            $hash = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $pdo->prepare("
                INSERT INTO riders (full_name, phone, email, password_hash, vehicle_type, vehicle_plate, status, bank_name, bank_code, account_number, account_name, selfie_path, vehicle_photo_path, verification_doc_type, verification_doc_path, kyc_status)
                VALUES (?, ?, ?, ?, ?, ?, 'offline', ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([$name, $phone, $email, $hash, $vehicle, $plate, $bankName, $bankCode ?: null, $accountNumber ?: null, $accountName ?: null, $selfiePath, $vehiclePhotoPath, $docType, $docPath]);

            session_regenerate_id(true);
            $_SESSION['rider_id'] = $pdo->lastInsertId();
            $_SESSION['rider_name'] = $name;

            redirect('/rider/dashboard.php');
        }
    }
}

$pageTitle = 'Ride With SwiftHaul';
require __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
    <div class="container">
        <h1>Ride With SwiftHaul</h1>
        <p>Bring your own bike, van, or truck and start earning today — create your account instantly.</p>
    </div>
</section>

<section>
    <div class="container">
        <div class="steps-grid stagger" style="margin-bottom:60px;">
            <div class="step-card stagger-item reveal" style="--i:0">
                <div class="step-icon"><i class="fa-solid fa-motorcycle"></i></div>
                <h3>Use Your Own Vehicle</h3>
                <p>Motorbike, van, truck, or bicycle — SwiftHaul owns no fleet; you bring yours.</p>
            </div>
            <div class="step-card stagger-item reveal" style="--i:1">
                <div class="step-icon"><i class="fa-solid fa-bolt"></i></div>
                <h3>Start Fast</h3>
                <p>No lengthy application — just verified identity and vehicle documents, reviewed quickly by our team.</p>
            </div>
            <div class="step-card stagger-item reveal" style="--i:2">
                <div class="step-icon"><i class="fa-solid fa-hand-pointer"></i></div>
                <h3>Choose Your Orders</h3>
                <p>See available deliveries in real time. Accept the ones you want; skip the ones you don't.</p>
            </div>
            <div class="step-card stagger-item reveal" style="--i:3">
                <div class="step-icon"><i class="fa-solid fa-money-bill-transfer"></i></div>
                <h3>Get Paid Automatically</h3>
                <p>You keep <?= 100 - PLATFORM_COMMISSION_PERCENT ?>% of every delivery fee, paid straight to your bank after each confirmed delivery.</p>
            </div>
        </div>

        <div style="max-width:640px;margin:0 auto;" class="reveal">
            <div class="form-card">
                <h2 style="text-align:center;margin-bottom:6px;">Create Your Rider Account</h2>
                <p style="text-align:center;color:var(--text-muted);margin-bottom:28px;font-size:0.9rem;">Takes about 5 minutes, including document upload. Your account can go online once our team verifies your documents.</p>

                <?php if ($errors): ?>
                    <div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i><div><?= implode('<br>', array_map('e', $errors)) ?></div></div>
                <?php endif; ?>

                <form method="POST" action="<?= BASE_URL ?>/rider-register.php" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <div class="form-row">
                        <div class="form-group"><label>Full Name</label><input type="text" name="full_name" class="form-control" value="<?= e($_POST['full_name'] ?? '') ?>" required></div>
                        <div class="form-group"><label>Phone Number</label><input type="text" name="phone" class="form-control" value="<?= e($_POST['phone'] ?? '') ?>" required></div>
                    </div>
                    <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" value="<?= e($_POST['email'] ?? '') ?>" required></div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Vehicle You Own</label>
                            <select name="vehicle_type" class="form-control" required>
                                <option value="">— Select —</option>
                                <option>Motorbike</option><option>Van</option><option>Truck</option><option>Bicycle</option><option>Car</option>
                            </select>
                        </div>
                        <div class="form-group"><label>Plate Number</label><input type="text" name="vehicle_plate" class="form-control" value="<?= e($_POST['vehicle_plate'] ?? '') ?>" required></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Password</label><input type="password" name="password" class="form-control" required minlength="6"></div>
                        <div class="form-group"><label>Confirm Password</label><input type="password" name="confirm_password" class="form-control" required minlength="6"></div>
                    </div>

                    <hr style="border:none;border-top:1px solid var(--border);margin:18px 0;">
                    <p style="font-size:0.85rem;font-weight:600;color:var(--navy-900);margin-bottom:4px;"><i class="fa-solid fa-shield-halved"></i> Identity & Vehicle Verification</p>
                    <p style="font-size:0.78rem;color:var(--text-muted);margin-bottom:16px;">Required for every rider — holds accounts accountable for the goods they carry and protects customers. Reviewed by our team; never shown publicly.</p>

                    <div class="form-group">
                        <label>A Clear Selfie</label>
                        <input type="file" name="selfie" class="form-control" accept="image/jpeg,image/png" capture="user" required>
                        <div class="hint">JPG or PNG, under 5MB. Face clearly visible.</div>
                    </div>
                    <div class="form-group">
                        <label>Photo of Your Vehicle (plate number visible)</label>
                        <input type="file" name="vehicle_photo" class="form-control" accept="image/jpeg,image/png" capture="environment" required>
                        <div class="hint">JPG or PNG, under 5MB. Take the photo so the plate number is clearly readable.</div>
                    </div>
                    <div class="form-group">
                        <label>Verification Document Type</label>
                        <select name="verification_doc_type" class="form-control" required>
                            <option value="">— Select document type —</option>
                            <?php foreach ($docTypes as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= ($_POST['verification_doc_type'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Upload Document</label>
                        <input type="file" name="verification_doc" class="form-control" accept="image/jpeg,image/png,application/pdf" required>
                        <div class="hint">JPG, PNG, or PDF, under 5MB.</div>
                    </div>

                    <hr style="border:none;border-top:1px solid var(--border);margin:18px 0;">
                    <p style="font-size:0.8rem;font-weight:600;color:var(--navy-900);margin-bottom:12px;">Payout Bank Details <span style="font-weight:400;color:var(--text-muted);">(can also be added later from your dashboard)</span></p>
                    <div class="form-group">
                        <label>Bank</label>
                        <select name="bank_code" class="form-control">
                            <option value="">— Select bank —</option>
                            <?php foreach (nigerian_banks_list() as $code => $bname): ?>
                                <option value="<?= e($code) ?>"><?= e($bname) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Account Number</label><input type="text" name="account_number" class="form-control" maxlength="10"></div>
                        <div class="form-group"><label>Account Name</label><input type="text" name="account_name" class="form-control"></div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block" style="margin-top:10px;">Create Account <i class="fa-solid fa-arrow-right"></i></button>
                    <p style="text-align:center;font-size:0.78rem;color:var(--text-muted);margin-top:14px;">By creating an account, you agree to our <a href="<?= BASE_URL ?>/privacy-policy.php" style="color:var(--orange-500);font-weight:600;">Privacy Policy</a>, including live location sharing while you're online for deliveries.</p>
                </form>

                <p class="auth-switch">Already riding with us? <a href="<?= BASE_URL ?>/rider-login.php">Log in</a></p>
            </div>
        </div>
        <p class="text-center reveal" style="color:var(--text-muted);font-size:0.85rem;margin-top:16px;max-width:640px;margin-left:auto;margin-right:auto;">
            Your account is created right away, but you'll need to wait for document verification (usually quick) before you can go online and accept orders.
        </p>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
