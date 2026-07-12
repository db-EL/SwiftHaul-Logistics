<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reply') {
    verify_csrf();
    $threadId = (int) ($_POST['thread_id'] ?? 0);
    $body = trim($_POST['body'] ?? '');

    if ($threadId && $body !== '') {
        $pdo->prepare("INSERT INTO support_messages (thread_id, sender_type, sender_name, body) VALUES (?, 'admin', 'SwiftHaul Support', ?)")
            ->execute([$threadId, $body]);
        $pdo->prepare("UPDATE support_threads SET read_by_admin = 1, read_by_user = 0, updated_at = NOW() WHERE id = ?")->execute([$threadId]);
        redirect('/admin/messages.php?thread=' . $threadId);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'close') {
    verify_csrf();
    $threadId = (int) ($_POST['thread_id'] ?? 0);
    $pdo->prepare("UPDATE support_threads SET status = 'closed' WHERE id = ?")->execute([$threadId]);
    redirect('/admin/messages.php?thread=' . $threadId);
}

$threads = $pdo->query("
    SELECT st.*, u.full_name AS user_name, u.email AS user_email
    FROM support_threads st
    LEFT JOIN users u ON u.id = st.user_id
    ORDER BY st.read_by_admin ASC, st.updated_at DESC
")->fetchAll();

$activeThreadId = (int) ($_GET['thread'] ?? ($threads[0]['id'] ?? 0));
$activeThread = null;
$activeMessages = [];

foreach ($threads as $t) {
    if ((int) $t['id'] === $activeThreadId) { $activeThread = $t; break; }
}

if ($activeThread) {
    $pdo->prepare("UPDATE support_threads SET read_by_admin = 1 WHERE id = ?")->execute([$activeThreadId]);
    $msgStmt = $pdo->prepare("SELECT * FROM support_messages WHERE thread_id = ? ORDER BY created_at ASC");
    $msgStmt->execute([$activeThreadId]);
    $activeMessages = $msgStmt->fetchAll();
}

$pageTitle = 'Support Messages';
$activePage = 'messages';
require __DIR__ . '/_admin_header.php';
?>

<div class="dash-header">
    <div><h1>Support Messages</h1><p style="color:var(--text-muted);font-size:0.9rem;">Reply directly — customers see your response the next time they check Messages.</p></div>
</div>

<div class="messages-layout">
    <div class="data-card">
        <div class="data-card-head"><h3>Conversations (<?= count($threads) ?>)</h3></div>
        <?php if (!$threads): ?>
            <div class="table-empty">No messages yet.</div>
        <?php else: ?>
            <div style="max-height:600px;overflow-y:auto;">
            <?php foreach ($threads as $t): ?>
                <a href="<?= BASE_URL ?>/admin/messages.php?thread=<?= $t['id'] ?>" style="display:block;padding:16px 20px;border-bottom:1px solid var(--border);<?= $activeThreadId === (int)$t['id'] ? 'background:var(--bg);' : '' ?>">
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;">
                        <strong style="font-size:0.9rem;"><?= e($t['user_name'] ?? $t['guest_name'] ?? 'Guest') ?></strong>
                        <?php if (!$t['read_by_admin']): ?><span style="width:8px;height:8px;border-radius:50%;background:var(--orange-500);flex-shrink:0;"></span><?php endif; ?>
                    </div>
                    <div style="font-size:0.82rem;color:var(--text-muted);margin-top:2px;"><?= e($t['subject'] ?: 'Message') ?></div>
                    <div style="font-size:0.72rem;color:var(--text-muted);margin-top:4px;"><?= date('M j, g:i A', strtotime($t['updated_at'])) ?> <?php if ($t['status']==='closed'): ?><span class="status-badge st-cancelled" style="margin-left:6px;">Closed</span><?php endif; ?></div>
                </a>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="data-card">
        <?php if (!$activeThread): ?>
            <div class="table-empty">Select a conversation.</div>
        <?php else: ?>
            <div class="data-card-head">
                <div>
                    <h3><?= e($activeThread['subject'] ?: 'Message') ?></h3>
                    <div style="font-size:0.8rem;color:var(--text-muted);margin-top:2px;">
                        <?= e($activeThread['user_name'] ?? $activeThread['guest_name'] ?? 'Guest') ?>
                        — <?= e($activeThread['user_email'] ?? $activeThread['guest_email'] ?? 'no email') ?>
                    </div>
                </div>
                <?php if ($activeThread['status'] !== 'closed'): ?>
                <form method="POST" action="<?= BASE_URL ?>/admin/messages.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="close">
                    <input type="hidden" name="thread_id" value="<?= $activeThread['id'] ?>">
                    <button type="submit" class="btn btn-ghost btn-sm">Mark Closed</button>
                </form>
                <?php endif; ?>
            </div>
            <div style="padding:20px 24px;max-height:420px;overflow-y:auto;">
                <?php foreach ($activeMessages as $m): ?>
                    <div style="margin-bottom:18px;<?= $m['sender_type'] === 'admin' ? 'text-align:right;' : '' ?>">
                        <div style="display:inline-block;max-width:80%;padding:12px 16px;border-radius:var(--radius-sm);<?= $m['sender_type'] === 'admin' ? 'background:var(--navy-900);color:var(--white);' : 'background:var(--bg);' ?>">
                            <div style="font-size:0.78rem;font-weight:600;margin-bottom:4px;<?= $m['sender_type'] === 'admin' ? 'color:var(--orange-400);' : 'color:var(--orange-500);' ?>"><?= $m['sender_type'] === 'admin' ? 'You (Support)' : e($m['sender_name']) ?></div>
                            <div style="font-size:0.9rem;"><?= nl2br(e($m['body'])) ?></div>
                        </div>
                        <div style="font-size:0.72rem;color:var(--text-muted);margin-top:4px;"><?= date('M j, g:i A', strtotime($m['created_at'])) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ($activeThread['status'] !== 'closed'): ?>
            <div style="padding:16px 24px;border-top:1px solid var(--border);">
                <form method="POST" action="<?= BASE_URL ?>/admin/messages.php" style="display:flex;gap:10px;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reply">
                    <input type="hidden" name="thread_id" value="<?= $activeThread['id'] ?>">
                    <input type="text" name="body" class="form-control" placeholder="Type a reply..." required>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i></button>
                </form>
            </div>
            <?php else: ?>
            <div style="padding:16px 24px;border-top:1px solid var(--border);color:var(--text-muted);font-size:0.85rem;">This conversation is closed.</div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/_admin_footer.php'; ?>
