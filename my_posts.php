<?php
require_once 'config/db.php';
if (!isLoggedIn()) redirect('login.php');

$uid = $_SESSION['user_id'];

// Xóa bài
if (isset($_GET['delete'])) {
    $p_id = (int)$_GET['delete'];
    $post = $conn->query("SELECT id FROM posts WHERE id=$p_id AND user_id=$uid")->fetch_assoc();
    if ($post) {
        $conn->query("DELETE FROM posts WHERE id=$p_id");
        redirect('my_posts.php?msg=deleted');
    }
}

$msg = $_GET['msg'] ?? '';
$filter = $_GET['filter'] ?? 'all';

$where = "WHERE p.user_id = $uid";
if ($filter !== 'all') $where .= " AND p.status = '$filter'";

$posts = $conn->query("
    SELECT p.*, c.name as category_name,
           (SELECT COUNT(*) FROM post_likes WHERE post_id=p.id) as like_count,
           (SELECT COUNT(*) FROM post_comments WHERE post_id=p.id) as comment_count
    FROM posts p
    LEFT JOIN categories c ON p.category_id=c.id
    $where
    ORDER BY p.created_at DESC
");

$counts = [
    'all'      => $conn->query("SELECT COUNT(*) as c FROM posts WHERE user_id=$uid")->fetch_assoc()['c'],
    'pending'  => $conn->query("SELECT COUNT(*) as c FROM posts WHERE user_id=$uid AND status='pending'")->fetch_assoc()['c'],
    'approved' => $conn->query("SELECT COUNT(*) as c FROM posts WHERE user_id=$uid AND status='approved'")->fetch_assoc()['c'],
    'rejected' => $conn->query("SELECT COUNT(*) as c FROM posts WHERE user_id=$uid AND status='rejected'")->fetch_assoc()['c'],
];
?>
<?php include 'includes/header.php'; ?>

<div class="main-container" style="max-width:900px;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
        <div>
            <h1 class="section-title" style="margin-bottom:4px;">Bài review của tôi</h1>
            <p class="section-subtitle">Quản lý các bài bạn đã đăng</p>
        </div>
        <a href="create_post.php" class="btn-write-review">
            <i class="fas fa-pen-nib"></i> Viết bài mới
        </a>
    </div>

    <?php if ($msg === 'deleted'): ?>
        <div class="alert alert-success"><i class="fas fa-check"></i> Đã xóa bài viết.</div>
    <?php endif; ?>
    <?php if ($msg === 'updated'): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> Đã lưu thay đổi thành công!</div>
    <?php endif; ?>
    <?php if ($msg === 'created'): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            Bài review đã được gửi! Chúng tôi sẽ duyệt trong vòng 24 giờ.
        </div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash'])): ?>
        <div class="alert alert-warning"><i class="fas fa-info-circle"></i> <?= htmlspecialchars($_SESSION['flash']) ?></div>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <!-- Status Tabs -->
    <div style="display:flex;gap:8px;margin-bottom:24px;flex-wrap:wrap;">
        <?php $tabs = ['all'=>'Tất cả','pending'=>'Chờ duyệt','approved'=>'Đã duyệt','rejected'=>'Bị từ chối']; ?>
        <?php foreach ($tabs as $key => $label): ?>
            <a href="my_posts.php?filter=<?= $key ?>"
               class="filter-btn <?= $filter===$key?'active':'' ?>">
                <?= $label ?>
                <span style="background:<?=$filter===$key?'rgba(255,255,255,0.3)':'var(--cream-dark)'?>;padding:1px 7px;border-radius:50px;font-size:0.78rem;margin-left:4px;">
                    <?= $counts[$key] ?>
                </span>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($posts->num_rows > 0): ?>
        <div style="display:flex;flex-direction:column;gap:16px;">
            <?php while ($p = $posts->fetch_assoc()): ?>
            <div class="my-post-card">
                <!-- Cover thumbnail -->
                <div class="my-post-thumb">
                    <?php if ($p['cover_image']): ?>
                        <img src="uploads/posts/<?= htmlspecialchars($p['cover_image']) ?>" alt="">
                    <?php else: ?>
                        <div class="my-post-thumb-placeholder">✦</div>
                    <?php endif; ?>
                </div>

                <!-- Info -->
                <div class="my-post-info">
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
                        <div style="flex:1;">
                            <div style="font-size:0.78rem;color:var(--rose);font-weight:600;margin-bottom:4px;">
                                <?= htmlspecialchars($p['brand']??'') ?>
                                <?php if ($p['category_name']): ?> · <?= htmlspecialchars($p['category_name']) ?><?php endif; ?>
                            </div>
                            <div style="font-size:0.85rem;color:var(--charcoal-mid);margin-bottom:6px;">
                                Sản phẩm: <strong><?= htmlspecialchars($p['product_name']) ?></strong>
                            </div>
                            <h3 style="font-family:'Playfair Display',serif;font-size:1.05rem;line-height:1.3;">
                                <?= htmlspecialchars($p['title']) ?>
                            </h3>
                        </div>
                        <div style="text-align:right;flex-shrink:0;">
                            <?php
                            $badge = match($p['status']) {
                                'approved' => ['class'=>'badge-approved','text'=>'✓ Đã duyệt'],
                                'pending'  => ['class'=>'badge-pending','text'=>'⏳ Chờ duyệt'],
                                'rejected' => ['class'=>'badge-rejected','text'=>'✕ Bị từ chối'],
                                default    => ['class'=>'','text'=>$p['status']]
                            };
                            ?>
                            <span class="badge <?= $badge['class'] ?>"><?= $badge['text'] ?></span>
                        </div>
                    </div>

                    <?php if ($p['status'] === 'rejected' && $p['reject_reason']): ?>
                        <div class="alert alert-error" style="margin-top:8px;padding:8px 12px;font-size:0.83rem;">
                            <i class="fas fa-info-circle"></i>
                            Lý do từ chối: <?= htmlspecialchars($p['reject_reason']) ?>
                        </div>
                    <?php endif; ?>

                    <div class="my-post-footer">
                        <div style="display:flex;gap:16px;font-size:0.83rem;color:#aaa;">
                            <span><i class="fas fa-star" style="color:var(--gold);"></i> <?= $p['rating'] ?></span>
                            <span><i class="fas fa-heart"></i> <?= $p['like_count'] ?></span>
                            <span><i class="fas fa-comment"></i> <?= $p['comment_count'] ?></span>
                            <span><?= date('d/m/Y', strtotime($p['created_at'])) ?></span>
                        </div>
                        <div style="display:flex;gap:8px;">
                            <?php if ($p['status'] === 'approved'): ?>
                                <a href="post_detail.php?id=<?= $p['id'] ?>" class="btn-sm edit">
                                    <i class="fas fa-eye"></i> Xem
                                </a>
                            <?php endif; ?>
                            <?php if ($p['status'] !== 'approved' || isAdmin()): ?>
                                <a href="edit_post.php?id=<?= $p['id'] ?>" class="btn-sm edit">
                                    <i class="fas fa-edit"></i> Sửa
                                </a>
                            <?php else: ?>
                                <span class="btn-sm" style="opacity:0.4;cursor:not-allowed;" title="Bài đã duyệt, không thể sửa">
                                    <i class="fas fa-lock"></i> Đã khóa
                                </span>
                            <?php endif; ?>
                            <a href="my_posts.php?delete=<?= $p['id'] ?>" class="btn-sm delete"
                               data-confirm="Xóa bài review '<?= htmlspecialchars(addslashes($p['title'])) ?>'?">
                                <i class="fas fa-trash"></i> Xóa
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon">📝</div>
            <h3>Chưa có bài review nào</h3>
            <p>Chia sẻ trải nghiệm mỹ phẩm của bạn với cộng đồng!</p>
            <a href="create_post.php" class="btn-review" style="display:inline-flex;margin-top:16px;">
                <i class="fas fa-pen-nib"></i> Viết bài review đầu tiên
            </a>
        </div>
    <?php endif; ?>
</div>

<style>
.my-post-card {
    background: white;
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    display: flex;
    gap: 0;
    overflow: hidden;
    transition: var(--transition);
}
.my-post-card:hover { box-shadow: var(--shadow-hover); }

.my-post-thumb {
    width: 140px;
    flex-shrink: 0;
    background: var(--cream);
    overflow: hidden;
}

.my-post-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.my-post-thumb-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: var(--rose-light);
    min-height: 120px;
}

.my-post-info {
    flex: 1;
    padding: 20px 24px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.my-post-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid var(--cream-dark);
    flex-wrap: wrap;
    gap: 8px;
}

@media (max-width: 540px) {
    .my-post-thumb { width: 90px; }
    .my-post-info { padding: 14px 16px; }
}
</style>

<?php include 'includes/footer.php'; ?>