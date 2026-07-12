<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

$errors = [];
$success = false;
$isLoggedIn = is_logged_in();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (!$isLoggedIn) {
        if ($name === '') $errors[] = 'Please enter your name.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email.';
    }
    if ($message === '' || strlen($message) < 5) $errors[] = 'Please enter a message.';

    if (!$errors) {
        if ($isLoggedIn) {
            $userId = current_user_id();
            $stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            $pdo->prepare("INSERT INTO support_threads (user_id, subject, status, read_by_admin, read_by_user) VALUES (?, ?, 'open', 0, 1)")
                ->execute([$userId, $subject ?: 'Contact form message']);
            $threadId = $pdo->lastInsertId();

            $pdo->prepare("INSERT INTO support_messages (thread_id, sender_type, sender_name, body) VALUES (?, 'user', ?, ?)")
                ->execute([$threadId, $user['full_name'], $message]);

            redirect('/messages.php?thread=' . $threadId);
        } else {
            $pdo->prepare("INSERT INTO support_threads (guest_name, guest_email, subject, status, read_by_admin) VALUES (?, ?, ?, 'open', 0)")
                ->execute([$name, $email, $subject ?: 'Contact form message']);
            $threadId = $pdo->lastInsertId();

            $pdo->prepare("INSERT INTO support_messages (thread_id, sender_type, sender_name, body) VALUES (?, 'user', ?, ?)")
                ->execute([$threadId, $name, $message]);

            $success = true;
        }
    }
}

$pageTitle = 'Contact Us';
require __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
    <div class="container">
        <h1>Get In Touch</h1>
        <p>Questions about a shipment, pricing, or partnership? We'd love to hear from you.</p>
    </div>
</section>

<section>
    <div class="container">
        <div class="contact-grid">
            <div class="contact-info-card reveal-left">
                <h3>Contact Information</h3>
                <div class="contact-info-item"><i class="fa-solid fa-location-dot"></i><div><strong>Address</strong><br>Port Harcourt, Rivers State, Nigeria</div></div>
                <div class="contact-info-item"><i class="fa-solid fa-phone"></i><div><strong>Phone</strong><br>+234 810 661 4760</div></div>
                <div class="contact-info-item"><i class="fa-solid fa-envelope"></i><div><strong>Email</strong><br>els147353@gmail.com</div></div>
                <div class="contact-info-item"><i class="fa-solid fa-clock"></i><div><strong>Hours</strong><br>Mon – Sat, 8am – 8pm</div></div>
                <?php if ($isLoggedIn): ?>
                    <p style="margin-top:20px;font-size:0.85rem;color:rgba(255,255,255,0.7);">Signed in — your message will appear in <a href="<?= BASE_URL ?>/messages.php" style="color:var(--orange-500);font-weight:600;">Messages</a>, where you can see our reply.</p>
                <?php endif; ?>
            </div>

            <div class="form-card reveal-right">
                <?php if ($success): ?>
                    <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> Thanks for reaching out! We'll get back to you by email shortly.</div>
                <?php endif; ?>
                <?php if ($errors): ?>
                    <div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i><div><?= implode('<br>', array_map('e', $errors)) ?></div></div>
                <?php endif; ?>
                <form method="POST" action="<?= BASE_URL ?>/contact.php">
                    <?= csrf_field() ?>
                    <?php if (!$isLoggedIn): ?>
                    <div class="form-row">
                        <div class="form-group"><label>Your Name</label><input type="text" name="name" class="form-control" value="<?= e($_POST['name'] ?? '') ?>" required></div>
                        <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" value="<?= e($_POST['email'] ?? '') ?>" required></div>
                    </div>
                    <?php endif; ?>
                    <div class="form-group"><label>Subject</label><input type="text" name="subject" class="form-control" value="<?= e($_POST['subject'] ?? '') ?>"></div>
                    <div class="form-group"><label>Message</label><textarea name="message" class="form-control" required><?= e($_POST['message'] ?? '') ?></textarea></div>
                    <button type="submit" class="btn btn-primary btn-block">Send Message <i class="fa-solid fa-paper-plane"></i></button>
                </form>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
