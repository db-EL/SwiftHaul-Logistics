<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$userId = current_user_id();
$errors = [];
$successMsg = null;

// New thread
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'new_thread') {
    verify_csrf();
    $subject = trim($_POST['subject'] ?? '');
    $body = trim($_POST['body'] ?? '');

    if ($body === '' || strlen($body) < 5) {
        $errors[] = 'Please enter a message.';
    } else {
        $pdo->prepare("INSERT INTO support_threads (user_id, subject, status, read_by_admin, read_by_user) VALUES (?, ?, 'open', 0, 1)")
            ->execute([$userId, $subject ?: 'New message']);
        $threadId = $pdo->lastInsertId();

        $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $name = $stmt->fetch()['full_name'];

        $pdo->prepare("INSERT INTO support_messages (thread_id, sender_type, sender_name, body) VALUES (?, 'user', ?, ?)")
            ->execute([$threadId, $name, $body]);

        $successMsg = 'Message sent — our team will reply here soon.';
        redirect('/messages.php?thread=' . $threadId);
    }
}

// Reply to existing thread
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reply') {
    verify_csrf();
    $threadId = (int) ($_POST['thread_id'] ?? 0);
    $body = trim($_POST['body'] ?? '');

    $stmt = $pdo->prepare("SELECT * FROM support_threads WHERE id = ? AND user_id = ?");
    $stmt->execute([$threadId, $userId]);
    $thread = $stmt->fetch();

    if ($thread && $body !== '') {
        $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $name = $stmt->fetch()['full_name'];

        $pdo->prepare("INSERT INTO support_messages (thread_id, sender_type, sender_name, body) VALUES (?, 'user', ?, ?)")->execute([$threadId, $name, $body]);
        $pdo->prepare("UPDATE support_threads SET status = 'open', read_by_admin = 0, read_by_user = 1, updated_at = NOW() WHERE id = ?")->execute([$threadId]);
        redirect('/messages.php?thread=' . $threadId);
    }
}

$threads = $pdo->prepare("SELECT * FROM support_threads WHERE user_id = ? ORDER BY updated_at DESC");
$threads->execute([$userId]);
$threads = $threads->fetchAll();

$activeThreadId = (int) ($_GET['thread'] ?? 0);
$activeThread = null;
$activeMessages = [];

if ($activeThreadId) {
    $stmt = $pdo->prepare("SELECT * FROM support_threads WHERE id = ? AND user_id = ?");
    $stmt->execute([$activeThreadId, $userId]);
    $activeThread = $stmt->fetch();

    if ($activeThread) {
        $pdo->prepare("UPDATE support_threads SET read_by_user = 1 WHERE id = ?")->execute([$activeThreadId]);
        $msgStmt = $pdo->prepare("SELECT * FROM support_messages WHERE thread_id = ? ORDER BY created_at ASC");
        $msgStmt->execute([$activeThreadId]);
        $activeMessages = $msgStmt->fetchAll();
    }
}

$pageTitle = 'Messages';
require __DIR__ . '/includes/header.php';
?>

<div class="dash-layout">
    <aside class="dash-sidebar">
        <a href="<?= BASE_URL ?>/dashboard.php"><i class="fa-solid fa-gauge"></i> Dashboard</a>
        <a href="#" class="active"><i class="fa-solid fa-envelope"></i> Messages</a>
        <a href="<?= BASE_URL ?>/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </aside>

    <main class="dash-main">
        <div class="dash-header">
            <div><h1>Messages</h1><p style="color:var(--text-muted);font-size:0.9rem;">Send a message to SwiftHaul support — an admin will reply here.</p></div>
            <button class="btn btn-primary" data-modal-target="#newThreadModal"><i class="fa-solid fa-plus"></i> New Message</button>
        </div>

        <?php if ($errors): ?><div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>

        <div class="messages-layout">
            <div class="data-card">
                <div class="data-card-head"><h3>Conversations</h3></div>
                <?php if (!$threads): ?>
                    <div class="table-empty">No messages yet.</div>
                <?php else: ?>
                    <?php foreach ($threads as $t): ?>
                        <a href="<?= BASE_URL ?>/messages.php?thread=<?= $t['id'] ?>" style="display:block;padding:16px 20px;border-bottom:1px solid var(--border);<?= $activeThreadId === (int)$t['id'] ? 'background:var(--bg);' : '' ?>">
                            <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;">
                                <strong style="font-size:0.9rem;"><?= e($t['subject'] ?: 'Message') ?></strong>
                                <?php if (!$t['read_by_user']): ?><span style="width:8px;height:8px;border-radius:50%;background:var(--orange-500);flex-shrink:0;"></span><?php endif; ?>
                            </div>
                            <div style="font-size:0.78rem;color:var(--text-muted);margin-top:4px;"><?= date('M j, Y g:i A', strtotime($t['updated_at'])) ?></div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="data-card">
                <?php if (!$activeThread): ?>
                    <div class="table-empty">Select a conversation, or start a new one.</div>
                <?php else: ?>
                    <div class="data-card-head"><h3><?= e($activeThread['subject'] ?: 'Message') ?></h3></div>
                    <div style="padding:20px 24px;max-height:420px;overflow-y:auto;">
                        <?php foreach ($activeMessages as $m): ?>
                            <div style="margin-bottom:18px;<?= $m['sender_type'] === 'admin' ? '' : 'text-align:right;' ?>">
                                <div style="display:inline-block;max-width:80%;padding:12px 16px;border-radius:var(--radius-sm);<?= $m['sender_type'] === 'admin' ? 'background:var(--bg);' : 'background:var(--navy-900);color:var(--white);' ?>">
                                    <div style="font-size:0.78rem;font-weight:600;margin-bottom:4px;<?= $m['sender_type'] === 'admin' ? 'color:var(--orange-500);' : 'color:rgba(255,255,255,0.7);' ?>"><?= $m['sender_type'] === 'admin' ? 'SwiftHaul Support' : e($m['sender_name']) ?></div>
                                    <div style="font-size:0.9rem;"><?= nl2br(e($m['body'])) ?></div>
                                </div>
                                <div style="font-size:0.72rem;color:var(--text-muted);margin-top:4px;"><?= date('M j, g:i A', strtotime($m['created_at'])) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="padding:16px 24px;border-top:1px solid var(--border);">
                        <form method="POST" action="<?= BASE_URL ?>/messages.php" style="display:flex;gap:10px;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="reply">
                            <input type="hidden" name="thread_id" value="<?= $activeThread['id'] ?>">
                            <input type="text" name="body" class="form-control" placeholder="Type a reply..." required>
                            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i></button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<div class="modal-overlay" id="newThreadModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3>New Message</h3>
            <button class="modal-close" data-modal-close><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>/messages.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="new_thread">
            <div class="form-group"><label>Subject</label><input type="text" name="subject" class="form-control" placeholder="e.g. Question about my delivery"></div>
            <div class="form-group"><label>Message</label><textarea name="body" class="form-control" required placeholder="How can we help?"></textarea></div>
            <button type="submit" class="btn btn-primary btn-block">Send Message</button>
        </form>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
