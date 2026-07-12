<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

$errors = [];
$successMsg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_review') {
    require_login();
    verify_csrf();

    $rating = (int) ($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

    if ($rating < 1 || $rating > 5) $errors[] = 'Please choose a rating between 1 and 5 stars.';
    if ($comment === '' || strlen($comment) < 5) $errors[] = 'Please write a short review.';

    if (!$errors) {
        $pdo->prepare("
            INSERT INTO reviews (user_id, rating, comment) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment), is_hidden = 0
        ")->execute([current_user_id(), $rating, $comment]);

        $successMsg = 'Thanks for your review!';
    }
}

$myReview = null;
if (is_logged_in()) {
    $stmt = $pdo->prepare("SELECT * FROM reviews WHERE user_id = ?");
    $stmt->execute([current_user_id()]);
    $myReview = $stmt->fetch();
}

$stats = $pdo->query("SELECT COUNT(*) c, COALESCE(AVG(rating),0) avg_rating FROM reviews WHERE is_hidden = 0")->fetch();
$reviews = $pdo->query("
    SELECT r.*, u.full_name FROM reviews r
    JOIN users u ON u.id = r.user_id
    WHERE r.is_hidden = 0
    ORDER BY r.created_at DESC
    LIMIT 50
")->fetchAll();

function star_html($rating, $size = '1rem') {
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        $html .= '<i class="fa-solid fa-star" style="font-size:' . $size . ';color:' . ($i <= $rating ? 'var(--orange-500)' : '#e2e5ec') . ';"></i>';
    }
    return $html;
}

$pageTitle = 'Customer Reviews';
require __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
    <div class="container">
        <h1>Customer Reviews</h1>
        <p>What customers say about shipping with SwiftHaul.</p>
        <div style="margin-top:20px;">
            <?= star_html(round($stats['avg_rating']), '1.4rem') ?>
            <span style="margin-left:10px;font-weight:700;font-size:1.2rem;"><?= number_format($stats['avg_rating'], 1) ?></span>
            <span style="color:rgba(255,255,255,0.7);"> · <?= (int) $stats['c'] ?> review<?= $stats['c'] == 1 ? '' : 's' ?></span>
        </div>
    </div>
</section>

<section>
    <div class="container">
        <div style="max-width:640px;margin:0 auto 60px;" class="reveal">
            <div class="form-card">
                <?php if (!is_logged_in()): ?>
                    <h2 style="text-align:center;margin-bottom:10px;font-size:1.2rem;">Leave a Review</h2>
                    <p style="text-align:center;color:var(--text-muted);font-size:0.9rem;">
                        <a href="<?= BASE_URL ?>/login.php" style="color:var(--orange-500);font-weight:600;">Log in</a> to share your experience.
                    </p>
                <?php else: ?>
                    <h2 style="text-align:center;margin-bottom:6px;font-size:1.2rem;"><?= $myReview ? 'Edit Your Review' : 'Leave a Review' ?></h2>
                    <p style="text-align:center;color:var(--text-muted);font-size:0.88rem;margin-bottom:22px;">Visible to everyone on this page and possibly featured on our homepage.</p>

                    <?php if ($successMsg): ?><div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= e($successMsg) ?></div><?php endif; ?>
                    <?php if ($errors): ?><div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i><div><?= implode('<br>', array_map('e', $errors)) ?></div></div><?php endif; ?>

                    <form method="POST" action="<?= BASE_URL ?>/reviews.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="submit_review">
                        <div class="form-group">
                            <label>Rating</label>
                            <div class="star-rating-input" id="starRatingInput">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <input type="radio" name="rating" id="star<?= $i ?>" value="<?= $i ?>" <?= ($myReview['rating'] ?? 0) == $i ? 'checked' : '' ?> required>
                                    <label for="star<?= $i ?>"><i class="fa-solid fa-star"></i></label>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Your Review</label>
                            <textarea name="comment" class="form-control" required><?= e($myReview['comment'] ?? '') ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block"><?= $myReview ? 'Update Review' : 'Submit Review' ?></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="section-head reveal">
            <span class="section-tag">From Our Customers</span>
            <h2>All Reviews</h2>
        </div>

        <?php if (!$reviews): ?>
            <p class="text-center" style="color:var(--text-muted);">No reviews yet — be the first!</p>
        <?php else: ?>
        <div class="testimonial-grid stagger">
            <?php foreach ($reviews as $i => $r): ?>
            <div class="testimonial-card stagger-item reveal" style="--i:<?= $i % 3 ?>">
                <div class="testimonial-stars"><?= star_html($r['rating']) ?></div>
                <p class="quote"><?= nl2br(e($r['comment'])) ?></p>
                <div class="testimonial-author">
                    <div class="avatar-circle"><?= e(mb_strtoupper(mb_substr($r['full_name'],0,1) . mb_substr(strrchr($r['full_name'],' ') ?: '',1,1))) ?></div>
                    <div><div class="name"><?= e($r['full_name']) ?></div><div class="role"><?= date('M Y', strtotime($r['created_at'])) ?></div></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<style>
.star-rating-input { display: flex; flex-direction: row-reverse; justify-content: flex-end; gap: 6px; }
.star-rating-input input { position: absolute; opacity: 0; width: 1px; height: 1px; }
.star-rating-input label { cursor: pointer; line-height: 1; }
.star-rating-input i { font-size: 1.7rem; color: #e2e5ec; transition: color 150ms ease; }
.star-rating-input label:hover i,
.star-rating-input label:hover ~ label i,
.star-rating-input input:checked + label i,
.star-rating-input input:checked ~ label i {
    color: var(--orange-500);
}
</style>

<?php require __DIR__ . '/includes/footer.php'; ?>
