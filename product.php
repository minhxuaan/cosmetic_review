<?php
require_once 'config/db.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect('index.php');

// Lấy sản phẩm
$stmt = $conn->prepare("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ? AND p.is_active = 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
if (!$product) { echo "Sản phẩm không tồn tại."; exit; }

// Rating
$rating_data = getAvgRating($id);
$avg = round($rating_data['avg_rating'], 1);

// Rating breakdown
$breakdown = [];
for ($s = 5; $s >= 1; $s--) {
    $r = $conn->query("SELECT COUNT(*) as cnt FROM reviews WHERE product_id=$id AND rating=$s AND status='approved'")->fetch_assoc();
    $breakdown[$s] = $r['cnt'];
}

// Reviews
$reviews = $conn->query("SELECT r.*, u.username, u.avatar FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.product_id = $id AND r.status = 'approved' ORDER BY r.created_at DESC");

// Comments
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment'])) {
    if (!isLoggedIn()) redirect('login.php');
    $content = sanitize($_POST['comment']);
    if (strlen($content) > 2) {
        $uid = $_SESSION['user_id'];
        $conn->query("INSERT INTO comments (product_id, user_id, content) VALUES ($id, $uid, '$content')");
        redirect("product.php?id=$id#comments");
    }
}

$comments = $conn->query("SELECT cm.*, u.username, u.avatar FROM comments cm JOIN users u ON cm.user_id = u.id WHERE cm.product_id = $id ORDER BY cm.created_at DESC");

// Kiểm tra user đã review chưa
$already_reviewed = false;
if (isLoggedIn()) {
    $uid = $_SESSION['user_id'];
    $check = $conn->query("SELECT id FROM reviews WHERE product_id=$id AND user_id=$uid")->num_rows;
    $already_reviewed = $check > 0;
}
?>
<?php include 'includes/header.php'; ?>

<!-- Product Detail -->
<div class="product-detail">
    <!-- Image -->
    <div class="product-detail-img">
        <?php if (!empty($product['image'])): ?>
            <img src="uploads/products/<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
        <?php else: ?>
            <img src="https://via.placeholder.com/600x600/fdf6f0/c8896a?text=✦+<?= urlencode($product['brand']) ?>" alt="No image">
        <?php endif; ?>
    </div>

    <!-- Info -->
    <div class="product-detail-info">
        <div class="product-detail-category"><?= htmlspecialchars($product['category_name'] ?? '') ?></div>
        <h1 class="product-detail-name"><?= htmlspecialchars($product['name']) ?></h1>
        <div class="product-detail-brand">Thương hiệu: <strong><?= htmlspecialchars($product['brand']) ?></strong></div>

        <!-- Rating Summary -->
        <div class="rating-summary">
            <div>
                <div class="rating-big"><?= $avg > 0 ? $avg : '—' ?></div>
                <div class="stars" style="margin-top:6px;">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <span class="star <?= $i <= round($avg) ? 'filled' : '' ?>">★</span>
                    <?php endfor; ?>
                </div>
                <div style="font-size:0.8rem;color:#888;margin-top:4px;"><?= $rating_data['total'] ?> đánh giá</div>
            </div>
            <div class="rating-detail">
                <div class="rating-bars">
                    <?php foreach ($breakdown as $stars => $cnt):
                        $pct = $rating_data['total'] > 0 ? ($cnt / $rating_data['total'] * 100) : 0;
                    ?>
                    <div class="rating-bar-row">
                        <span><?= $stars ?>★</span>
                        <div class="rating-bar-track">
                            <div class="rating-bar-fill" style="width:<?= $pct ?>%"></div>
                        </div>
                        <span><?= $cnt ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="product-description"><?= nl2br(htmlspecialchars($product['description'])) ?></div>

        <?php if ($product['price'] > 0): ?>
            <div class="detail-price"><?= number_format($product['price'], 0, ',', '.') ?>đ</div>
        <?php endif; ?>

        <?php if (isLoggedIn() && !$already_reviewed): ?>
            <a href="write_review.php?id=<?= $id ?>" class="btn-write-review">
                <i class="fas fa-pen-nib"></i> Viết review của bạn
            </a>
        <?php elseif ($already_reviewed): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> Bạn đã viết review cho sản phẩm này</div>
        <?php else: ?>
            <a href="login.php" class="btn-write-review">
                <i class="fas fa-sign-in-alt"></i> Đăng nhập để review
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Reviews -->
<div class="reviews-section">
    <div class="reviews-header">
        <h2 class="section-title">Đánh giá từ cộng đồng</h2>
        <span style="color:#888;font-size:0.9rem;"><?= $reviews->num_rows ?> review</span>
    </div>

    <?php if ($reviews->num_rows > 0):
        while ($rv = $reviews->fetch_assoc()):
    ?>
    <div class="review-card">
        <div class="review-header">
            <img class="reviewer-avatar"
                 src="uploads/avatars/<?= htmlspecialchars($rv['avatar'] ?? 'default.png') ?>"
                 onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($rv['username']) ?>&background=c8896a&color=fff'"
                 alt="<?= htmlspecialchars($rv['username']) ?>">
            <div class="reviewer-info">
                <div class="reviewer-name"><?= htmlspecialchars($rv['username']) ?></div>
                <div class="review-stars">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <span class="star <?= $i <= $rv['rating'] ? 'filled' : '' ?>">★</span>
                    <?php endfor; ?>
                </div>
                <div class="reviewer-meta"><?= date('d/m/Y', strtotime($rv['created_at'])) ?></div>
            </div>
        </div>

        <?php if (!empty($rv['title'])): ?>
            <div class="review-title"><?= htmlspecialchars($rv['title']) ?></div>
        <?php endif; ?>

        <div class="review-content"><?= nl2br(htmlspecialchars($rv['content'])) ?></div>

        <?php
        $imgs = $conn->query("SELECT image_path FROM review_images WHERE review_id = {$rv['id']}");
        if ($imgs->num_rows > 0):
        ?>
        <div class="review-images">
            <?php while ($img = $imgs->fetch_assoc()): ?>
                <img src="uploads/reviews/<?= htmlspecialchars($img['image_path']) ?>" alt="Review image">
            <?php endwhile; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endwhile;
    else: ?>
    <div class="empty-state" style="padding:40px 0;">
        <div class="empty-state-icon">💬</div>
        <h3>Chưa có review nào</h3>
        <p>Hãy là người đầu tiên chia sẻ trải nghiệm!</p>
    </div>
    <?php endif; ?>
</div>

<!-- Comments -->
<div class="comments-section" id="comments">
    <h2 class="section-title">Bình luận</h2>
    <p class="section-subtitle" style="margin-bottom:20px;">Thảo luận thêm về sản phẩm này</p>

    <?php if (isLoggedIn()): ?>
    <form class="comment-form" method="POST" style="margin-bottom:24px;">
        <div class="form-group">
            <textarea name="comment" placeholder="Chia sẻ thêm về sản phẩm này..." rows="3"></textarea>
        </div>
        <button type="submit" class="btn-review" style="border:none;cursor:pointer;">
            <i class="fas fa-paper-plane"></i> Gửi bình luận
        </button>
    </form>
    <?php else: ?>
        <div class="alert alert-warning">
            <i class="fas fa-info-circle"></i>
            <a href="login.php" style="color:var(--rose);font-weight:600;">Đăng nhập</a> để bình luận
        </div>
    <?php endif; ?>

    <?php if ($comments && $comments->num_rows > 0):
        while ($cm = $comments->fetch_assoc()):
    ?>
    <div class="comment-card">
        <img class="reviewer-avatar"
             src="uploads/avatars/<?= htmlspecialchars($cm['avatar'] ?? 'default.png') ?>"
             onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($cm['username']) ?>&background=c8896a&color=fff'"
             alt="">
        <div class="comment-body">
            <div class="comment-author"><?= htmlspecialchars($cm['username']) ?></div>
            <div class="comment-text"><?= nl2br(htmlspecialchars($cm['content'])) ?></div>
            <div class="comment-date"><?= date('d/m/Y H:i', strtotime($cm['created_at'])) ?></div>
        </div>
    </div>
    <?php endwhile;
    else: ?>
        <p style="color:#aaa;font-size:0.9rem;">Chưa có bình luận nào.</p>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>