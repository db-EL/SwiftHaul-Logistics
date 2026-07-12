<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $reviewId = (int) ($_POST['review_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($reviewId && $action === 'toggle_hidden') {
        $pdo->prepare("UPDATE reviews SET is_hidden = NOT is_hidden WHERE id = ?")->execute([$reviewId]);
    } elseif ($reviewId && $action === 'delete') {
        $pdo->prepare("DELETE FROM reviews WHERE id = ?")->execute([$reviewId]);
    }
    redirect('/admin/reviews.php');
}

$reviews = $pdo->query("
    SELECT r.*, u.full_name, u.email FROM reviews r
    JOIN users u ON u.id = r.user_id
    ORDER BY r.created_at DESC
")->fetchAll();

$avgRating = $pdo->query("SELECT COALESCE(AVG(rating),0) v FROM reviews WHERE is_hidden = 0")->fetch()['v'];

$pageTitle = 'Manage Reviews';
$activePage = 'reviews';
require __DIR__ . '/_admin_header.php';
?>

<div class="dash-header">
    <div><h1>Customer Reviews</h1><p style="color:var(--text-muted);font-size:0.9rem;">Public reviews, visible on the Reviews page and featured on the homepage. Average rating: <strong><?= number_format($avgRating,1) ?></strong> / 5.</p></div>
</div>

<div class="data-card">
    <div class="data-card-head"><h3>All Reviews (<?= count($reviews) ?>)</h3></div>
    <?php if (!$reviews): ?>
        <div class="table-empty">No reviews yet.</div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="data-table">
        <thead><tr><th>Customer</th><th>Rating</th><th>Review</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($reviews as $r): ?>
            <tr>
                <td><strong><?= e($r['full_name']) ?></strong><br><span style="color:var(--text-muted);font-size:0.8rem;"><?= e($r['email']) ?></span></td>
                <td><?php for ($i=1;$i<=5;$i++): ?><i class="fa-solid fa-star" style="color:<?= $i<=$r['rating']?'var(--orange-500)':'#e2e5ec' ?>;font-size:0.8rem;"></i><?php endfor; ?></td>
                <td style="max-width:320px;"><?= e(mb_strimwidth($r['comment'], 0, 140, '…')) ?></td>
                <td><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
                <td><?php if ($r['is_hidden']): ?><span class="status-badge st-cancelled">Hidden</span><?php else: ?><span class="status-badge st-delivered">Visible</span><?php endif; ?></td>
                <td>
                    <div style="display:flex;gap:8px;">
                        <form method="POST" action="<?= BASE_URL ?>/admin/reviews.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="review_id" value="<?= $r['id'] ?>">
                            <input type="hidden" name="action" value="toggle_hidden">
                            <button type="submit" class="btn btn-ghost btn-sm"><?= $r['is_hidden'] ? 'Unhide' : 'Hide' ?></button>
                        </form>
                        <form method="POST" action="<?= BASE_URL ?>/admin/reviews.php" onsubmit="return confirm('Permanently delete this review?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="review_id" value="<?= $r['id'] ?>">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/_admin_footer.php'; ?>
