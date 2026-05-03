<?php
require_once 'config/db.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect('posts.php');

// Admin có thể xem tất cả trạng thái, user chỉ xem approved
$status_condition = isAdmin() ? "1=1" : "p.status = 'approved'";

$post = $conn->query("
    SELECT p.*, u.username, u.avatar, c.name as category_name
    FROM posts p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.id = $id AND $status_condition
")->fetch_assoc();

if (!$post) {
    echo "<div class='main-container'><div class='empty-state'><div class='empty-state-icon'>🔍</div><h3>Bài viết không tồn tại</h3><p>Bài viết có thể đã bị xóa hoặc chưa được duyệt.</p><a href='posts.php'>← Quay lại</a></div></div>";
    exit;
}

// Tăng view
$conn->query("UPDATE posts SET view_count = view_count + 1 WHERE id = $id");

// Gallery
$gallery = $conn->query("SELECT image_path FROM post_images WHERE post_id = $id");

// Like
$uid = $_SESSION['user_id'] ?? 0;
$liked = $uid ? $conn->query("SELECT id FROM post_likes WHERE post_id=$id AND user_id=$uid")->num_rows > 0 : false;
$like_count = $conn->query("SELECT COUNT(*) as c FROM post_likes WHERE post_id=$id")->fetch_assoc()['c'];

// Xử lý like
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_like'])) {
    if (!isLoggedIn()) redirect('login.php');
    if ($liked) {
        $conn->query("DELETE FROM post_likes WHERE post_id=$id AND user_id=$uid");
    } else {
        $conn->query("INSERT IGNORE INTO post_likes (post_id, user_id) VALUES ($id, $uid)");
    }
    redirect("post_detail.php?id=$id#reactions");
}

// Xử lý comment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_comment'])) {
    if (!isLoggedIn()) redirect('login.php');
    $content = sanitize($_POST['comment_content'] ?? '');
    if (mb_strlen($content) > 2) {
        $conn->query("INSERT INTO post_comments (post_id, user_id, content) VALUES ($id, $uid, '$content')");
    }
    redirect("post_detail.php?id=$id#comments");
}

$comments = $conn->query("SELECT pc.*, u.username, u.avatar FROM post_comments pc JOIN users u ON pc.user_id=u.id WHERE pc.post_id=$id ORDER BY pc.created_at ASC");

// Bài liên quan
$related = $conn->query("
    SELECT p.*, u.username,
           (SELECT COUNT(*) FROM post_likes WHERE post_id=p.id) as like_count
    FROM posts p JOIN users u ON p.user_id=u.id
    WHERE p.status='approved' AND p.id != $id
      AND (p.category_id = {$post['category_id']} OR p.brand = '{$conn->real_escape_string($post['brand'])}')
    ORDER BY p.created_at DESC LIMIT 3
");
?>
<?php include 'includes/header.php'; ?>

<div style="max-width:900px;margin:40px auto;padding:0 24px;">

    <!-- Banner trạng thái cho Admin -->
    <?php if (isAdmin() && $post['status'] !== 'approved'): ?>
    <div class="alert <?= $post['status']==='pending' ? 'alert-warning' : 'alert-error' ?>" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
        <div>
            <?php if ($post['status'] === 'pending'): ?>
                <i class="fas fa-hourglass-half"></i>
                <strong>Bài đang chờ duyệt.</strong> Xem xong hãy duyệt hoặc từ chối bên dưới.
            <?php else: ?>
                <i class="fas fa-times-circle"></i>
                <strong>Bài đã bị từ chối.</strong>
                <?php if ($post['reject_reason']): ?> Lý do: <?= htmlspecialchars($post['reject_reason']) ?><?php endif; ?>
            <?php endif; ?>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <?php if ($post['status'] === 'pending' || $post['status'] === 'rejected'): ?>
                <a href="../admin/posts_manage.php?approve=<?= $post['id'] ?>&filter=<?= $post['status'] ?>"
                   class="btn-sm approve" style="text-decoration:none;">
                    <i class="fas fa-check"></i> Duyệt bài này
                </a>
            <?php endif; ?>
            <a href="../admin/posts_manage.php?filter=<?= $post['status'] ?>"
               class="btn-sm edit" style="text-decoration:none;">
                <i class="fas fa-arrow-left"></i> Quay lại danh sách
            </a>
        </div>
    </div>
    <?php endif; ?>
    <div style="display:flex;align-items:center;gap:8px;font-size:0.85rem;color:#aaa;margin-bottom:24px;">
        <a href="posts.php" style="color:var(--rose);">Bài review</a>
        <span>›</span>
        <?php if ($post['category_name']): ?>
            <a href="posts.php?category=<?= $post['category_id'] ?>" style="color:var(--rose);"><?= htmlspecialchars($post['category_name']) ?></a>
            <span>›</span>
        <?php endif; ?>
        <span><?= htmlspecialchars(mb_strimwidth($post['title'], 0, 40, '...')) ?></span>
    </div>

    <article class="post-detail-article">
        <!-- Cover -->
        <?php if ($post['cover_image']): ?>
            <div class="post-detail-cover">
                <img src="uploads/posts/<?= htmlspecialchars($post['cover_image']) ?>" alt="<?= htmlspecialchars($post['title']) ?>">
            </div>
        <?php endif; ?>

        <div class="post-detail-body">
            <!-- Tags -->
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
                <?php if ($post['category_name']): ?>
                    <span class="post-cat-tag"><?= htmlspecialchars($post['category_name']) ?></span>
                <?php endif; ?>
                <?php if ($post['brand']): ?>
                    <span class="post-brand-tag"><?= htmlspecialchars($post['brand']) ?></span>
                <?php endif; ?>
            </div>

            <!-- Product name -->
            <div class="post-detail-product"><?= htmlspecialchars($post['product_name']) ?></div>

            <!-- Title -->
            <h1 class="post-detail-title"><?= htmlspecialchars($post['title']) ?></h1>

            <!-- Rating + Meta -->
            <div class="post-detail-meta">
                <div class="post-detail-stars">
                    <?php for ($i=1;$i<=5;$i++): ?>
                        <span class="star <?=$i<=$post['rating']?'filled':''?>">★</span>
                    <?php endfor; ?>
                    <span style="font-weight:700;color:var(--charcoal);margin-left:4px;"><?= $post['rating'] ?>.0</span>
                </div>
                <div class="post-detail-author">
                    <img src="uploads/avatars/<?= htmlspecialchars($post['avatar']??'default.png') ?>"
                         onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($post['username']) ?>&background=c8896a&color=fff&size=40'"
                         alt="">
                    <div>
                        <div style="font-weight:600;font-size:0.9rem;"><?= htmlspecialchars($post['username']) ?></div>
                        <div style="font-size:0.78rem;color:#aaa;"><?= date('d/m/Y \l\ú\c H:i', strtotime($post['created_at'])) ?> · <?= $post['view_count'] ?> lượt xem</div>
                    </div>
                </div>
            </div>

            <hr style="border:none;border-top:1px solid var(--cream-dark);margin:24px 0;">

            <!-- Content -->
            <div class="post-detail-content">
                <?= nl2br(htmlspecialchars($post['content'])) ?>
            </div>

            <!-- Pros & Cons -->
            <?php if ($post['pros'] || $post['cons']): ?>
            <div class="pros-cons-box">
                <?php if ($post['pros']): ?>
                <div class="pros-box">
                    <div class="pros-cons-title"><i class="fas fa-thumbs-up"></i> Điểm tốt</div>
                    <?php foreach (explode("\n", $post['pros']) as $pro):
                        $pro = trim($pro);
                        if ($pro):
                    ?>
                        <div class="pros-item"><i class="fas fa-check"></i> <?= htmlspecialchars($pro) ?></div>
                    <?php endif; endforeach; ?>
                </div>
                <?php endif; ?>
                <?php if ($post['cons']): ?>
                <div class="cons-box">
                    <div class="pros-cons-title"><i class="fas fa-thumbs-down"></i> Điểm chưa tốt</div>
                    <?php foreach (explode("\n", $post['cons']) as $con):
                        $con = trim($con);
                        if ($con):
                    ?>
                        <div class="cons-item"><i class="fas fa-times"></i> <?= htmlspecialchars($con) ?></div>
                    <?php endif; endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Gallery -->
            <?php if ($gallery->num_rows > 0): ?>
            <div style="margin-top:28px;">
                <div style="font-weight:600;margin-bottom:12px;">📸 Ảnh thực tế</div>
                <div class="post-gallery">
                    <?php while ($img = $gallery->fetch_assoc()): ?>
                        <img src="uploads/posts/<?= htmlspecialchars($img['image_path']) ?>"
                             alt="Gallery" class="gallery-img" loading="lazy">
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Reactions -->
            <div id="reactions" style="display:flex;align-items:center;gap:16px;margin-top:32px;padding-top:24px;border-top:1px solid var(--cream-dark);">
                <form method="POST" style="margin:0;">
                    <button type="submit" name="toggle_like" class="like-btn <?= $liked?'liked':'' ?>">
                        <i class="fas fa-heart"></i>
                        <span><?= $like_count ?> Hữu ích</span>
                    </button>
                </form>
                <a href="#comments" style="color:var(--charcoal-mid);font-size:0.9rem;">
                    <i class="fas fa-comment"></i> <?= $comments->num_rows ?> bình luận
                </a>
                <button onclick="copyLink()" style="background:none;border:none;color:var(--charcoal-mid);font-size:0.9rem;cursor:pointer;">
                    <i class="fas fa-share-alt"></i> Chia sẻ
                </button>
            </div>
        </div>
    </article>

    <!-- Comments -->
    <div id="comments" style="background:white;border-radius:var(--radius);padding:32px;box-shadow:var(--shadow);margin-top:24px;">
        <h3 style="font-family:'Playfair Display',serif;margin-bottom:24px;">
            Bình luận (<?= $comments->num_rows ?>)
        </h3>

        <?php if (isLoggedIn()): ?>
        <form method="POST" style="margin-bottom:24px;">
            <div style="display:flex;gap:12px;align-items:flex-start;">
                <img src="uploads/avatars/<?= htmlspecialchars($_SESSION['avatar']??'default.png') ?>"
                     onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['username']) ?>&background=c8896a&color=fff&size=40'"
                     style="width:40px;height:40px;border-radius:50%;object-fit:cover;flex-shrink:0;">
                <div style="flex:1;">
                    <textarea name="comment_content" rows="3"
                              placeholder="Chia sẻ ý kiến của bạn về bài review này..."
                              style="width:100%;padding:12px;border:1.5px solid var(--cream-dark);border-radius:var(--radius-sm);font-family:'DM Sans',sans-serif;resize:vertical;transition:var(--transition);"
                              onfocus="this.style.borderColor='var(--rose)'" onblur="this.style.borderColor='var(--cream-dark)'"></textarea>
                    <div style="text-align:right;margin-top:8px;">
                        <button type="submit" name="submit_comment" class="btn-review" style="border:none;cursor:pointer;">
                            <i class="fas fa-paper-plane"></i> Gửi
                        </button>
                    </div>
                </div>
            </div>
        </form>
        <?php else: ?>
            <div class="alert alert-warning">
                <i class="fas fa-info-circle"></i>
                <a href="login.php" style="color:var(--rose);font-weight:600;">Đăng nhập</a> để bình luận
            </div>
        <?php endif; ?>

        <!-- Comment list -->
        <?php if ($comments->num_rows > 0):
            $comments->data_seek(0);
            while ($cm = $comments->fetch_assoc()):
        ?>
        <div class="comment-card">
            <img src="uploads/avatars/<?= htmlspecialchars($cm['avatar']??'default.png') ?>"
                 onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($cm['username']) ?>&background=c8896a&color=fff&size=40'"
                 class="reviewer-avatar" alt="">
            <div class="comment-body">
                <div class="comment-author"><?= htmlspecialchars($cm['username']) ?></div>
                <div class="comment-text"><?= nl2br(htmlspecialchars($cm['content'])) ?></div>
                <div class="comment-date"><?= date('d/m/Y H:i', strtotime($cm['created_at'])) ?></div>
            </div>
        </div>
        <?php endwhile;
        else: ?>
            <p style="color:#aaa;text-align:center;padding:20px 0;">Chưa có bình luận nào. Hãy là người đầu tiên!</p>
        <?php endif; ?>
    </div>

    <!-- Related posts -->
    <?php if ($related->num_rows > 0): ?>
    <div style="margin-top:40px;">
        <h3 class="section-title" style="margin-bottom:20px;">Bài review liên quan</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px;">
            <?php while ($rel = $related->fetch_assoc()): ?>
            <a href="post_detail.php?id=<?= $rel['id'] ?>" class="related-card">
                <?php if ($rel['cover_image']): ?>
                    <img src="uploads/posts/<?= htmlspecialchars($rel['cover_image']) ?>" alt="">
                <?php else: ?>
                    <div class="related-no-img">✦</div>
                <?php endif; ?>
                <div style="padding:14px;">
                    <div style="font-size:0.78rem;color:var(--rose);font-weight:600;margin-bottom:4px;"><?= htmlspecialchars($rel['brand']??'') ?></div>
                    <div style="font-weight:600;font-size:0.9rem;line-height:1.3;"><?= htmlspecialchars(mb_strimwidth($rel['title'],0,60,'...')) ?></div>
                    <div style="font-size:0.78rem;color:#aaa;margin-top:6px;"><?= htmlspecialchars($rel['username']) ?> · <i class="fas fa-heart" style="color:var(--rose-light);"></i> <?= $rel['like_count'] ?></div>
                </div>
            </a>
            <?php endwhile; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.post-detail-article {
    background: white;
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    overflow: hidden;
}

.post-detail-cover {
    aspect-ratio: 16/7;
    overflow: hidden;
    background: var(--cream);
}

.post-detail-cover img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.post-detail-body { padding: 36px 40px; }

.post-detail-product {
    font-size: 0.85rem;
    color: var(--charcoal-mid);
    letter-spacing: 0.05em;
    text-transform: uppercase;
    font-weight: 600;
    margin-bottom: 8px;
}

.post-detail-title {
    font-family: 'Playfair Display', serif;
    font-size: 2rem;
    line-height: 1.25;
    margin-bottom: 20px;
    color: var(--charcoal);
}

.post-detail-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
}

.post-detail-stars { display: flex; align-items: center; gap: 2px; }
.post-detail-stars .star { font-size: 1.2rem; }

.post-detail-author {
    display: flex;
    align-items: center;
    gap: 10px;
}

.post-detail-author img {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--gold-light);
}

.post-detail-content {
    font-size: 1rem;
    line-height: 1.85;
    color: var(--charcoal-mid);
    margin-bottom: 28px;
}

.pros-cons-box {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-top: 24px;
}

.pros-box {
    background: #eafaf1;
    border: 1px solid #c3e6cb;
    border-radius: var(--radius-sm);
    padding: 16px;
}

.cons-box {
    background: #fdf3f2;
    border: 1px solid #f5c6cb;
    border-radius: var(--radius-sm);
    padding: 16px;
}

.pros-cons-title {
    font-weight: 700;
    font-size: 0.9rem;
    margin-bottom: 10px;
}

.pros-box .pros-cons-title { color: #0f5132; }
.cons-box .pros-cons-title { color: #842029; }

.pros-item, .cons-item {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    font-size: 0.88rem;
    margin-bottom: 6px;
}

.pros-item i { color: #2eb85c; flex-shrink: 0; margin-top: 2px; }
.cons-item i { color: #e74c3c; flex-shrink: 0; margin-top: 2px; }

.post-gallery {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 8px;
}

.gallery-img {
    width: 100%;
    aspect-ratio: 1;
    object-fit: cover;
    border-radius: var(--radius-sm);
    cursor: pointer;
    transition: var(--transition);
}

.gallery-img:hover { opacity: 0.85; transform: scale(1.03); }

.like-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: 50px;
    border: 1.5px solid var(--cream-dark);
    background: white;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.9rem;
    cursor: pointer;
    transition: var(--transition);
    color: var(--charcoal-mid);
}

.like-btn:hover, .like-btn.liked {
    border-color: var(--rose);
    background: rgba(200,137,106,0.08);
    color: var(--rose);
}

.like-btn.liked i { color: var(--rose); }

.related-card {
    background: white;
    border-radius: var(--radius-sm);
    overflow: hidden;
    box-shadow: var(--shadow);
    transition: var(--transition);
    display: block;
    color: var(--charcoal);
}

.related-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-hover); }

.related-card img { width: 100%; height: 140px; object-fit: cover; }
.related-no-img {
    height: 140px;
    background: var(--cream);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: var(--rose-light);
}

@media (max-width: 640px) {
    .post-detail-body { padding: 24px 20px; }
    .post-detail-title { font-size: 1.4rem; }
    .pros-cons-box { grid-template-columns: 1fr; }
}
</style>

<script>
function copyLink() {
    navigator.clipboard.writeText(window.location.href).then(() => {
        alert('Đã copy link bài viết!');
    });
}

// Lightbox
document.querySelectorAll('.gallery-img').forEach(img => {
    img.addEventListener('click', function() {
        const overlay = document.createElement('div');
        overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.88);z-index:9999;display:flex;align-items:center;justify-content:center;cursor:pointer;';
        const bigImg = document.createElement('img');
        bigImg.src = this.src;
        bigImg.style.cssText = 'max-width:92vw;max-height:92vh;border-radius:12px;';
        overlay.appendChild(bigImg);
        overlay.addEventListener('click', () => overlay.remove());
        document.body.appendChild(overlay);
    });
});
</script>

<?php include 'includes/footer.php'; ?>