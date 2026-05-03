<?php
require_once 'config/db.php';

// Filter & Search
$where = "WHERE p.status = 'approved'";

if (!empty($_GET['search'])) {
    $s = $conn->real_escape_string($_GET['search']);
    $where .= " AND (p.product_name LIKE '%$s%' OR p.brand LIKE '%$s%' OR p.title LIKE '%$s%' OR u.username LIKE '%$s%')";
}

if (!empty($_GET['category'])) {
    $cat_id = (int)$_GET['category'];
    $where .= " AND p.category_id = $cat_id";
}

if (!empty($_GET['rating'])) {
    $r = (int)$_GET['rating'];
    $where .= " AND p.rating = $r";
}

// Sort
$sort = "p.created_at DESC";
if (!empty($_GET['sort'])) {
    switch($_GET['sort']) {
        case 'popular': $sort = "like_count DESC, p.created_at DESC"; break;
        case 'rating_high': $sort = "p.rating DESC, p.created_at DESC"; break;
        case 'rating_low': $sort = "p.rating ASC, p.created_at DESC"; break;
        case 'oldest': $sort = "p.created_at ASC"; break;
        default: $sort = "p.created_at DESC";
    }
}

// Pagination
$per_page = 9;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

$count_result = $conn->query("SELECT COUNT(*) as total FROM posts p JOIN users u ON p.user_id=u.id $where")->fetch_assoc();
$total = $count_result['total'];
$total_pages = ceil($total / $per_page);

$posts = $conn->query("
    SELECT p.*, u.username, u.avatar,
           c.name as category_name,
           (SELECT COUNT(*) FROM post_likes WHERE post_id=p.id) as like_count,
           (SELECT COUNT(*) FROM post_comments WHERE post_id=p.id) as comment_count
    FROM posts p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN categories c ON p.category_id = c.id
    $where
    ORDER BY $sort
    LIMIT $per_page OFFSET $offset
");

$categories = $conn->query("SELECT * FROM categories ORDER BY name");
$total_posts = $conn->query("SELECT COUNT(*) as c FROM posts WHERE status='approved'")->fetch_assoc()['c'];
?>
<?php include 'includes/header.php'; ?>

<!-- Hero -->
<section class="hero" style="padding:60px 24px 48px;">
    <div class="hero-content">
        <h1>Bài review từ<br><em>cộng đồng</em></h1>
        <p>Những trải nghiệm thật từ những người dùng thật.<br>Chia sẻ để giúp nhau chọn mỹ phẩm đúng đắn hơn.</p>
        <?php if (isLoggedIn()): ?>
            <a href="create_post.php" class="btn-write-review" style="display:inline-flex;margin-top:8px;">
                <i class="fas fa-pen-nib"></i> Viết bài review
            </a>
        <?php else: ?>
            <a href="login.php" class="btn-write-review" style="display:inline-flex;margin-top:8px;">
                <i class="fas fa-sign-in-alt"></i> Đăng nhập để đăng bài
            </a>
        <?php endif; ?>
    </div>
</section>

<!-- Filter Bar -->
<div class="filter-bar">
    <div class="filter-container">
        <span class="filter-label">Danh mục:</span>
        <a href="posts.php" class="filter-btn <?= empty($_GET['category']) ? 'active' : '' ?>">Tất cả</a>
        <?php $categories->data_seek(0); while ($cat = $categories->fetch_assoc()): ?>
            <a href="posts.php?<?= http_build_query(array_merge($_GET, ['category'=>$cat['id'],'page'=>1])) ?>"
               class="filter-btn <?= (($_GET['category']??'')==$cat['id']) ? 'active' : '' ?>">
                <?= htmlspecialchars($cat['name']) ?>
            </a>
        <?php endwhile; ?>

        <!-- Rating filter -->
        <div style="margin-left:8px;display:flex;gap:4px;">
            <?php for ($r = 5; $r >= 1; $r--): ?>
                <a href="posts.php?<?= http_build_query(array_merge($_GET, ['rating'=>$r,'page'=>1])) ?>"
                   class="filter-btn <?= (($_GET['rating']??'')==$r) ? 'active' : '' ?>"
                   style="padding:6px 10px;">
                    <?= $r ?>★
                </a>
            <?php endfor; ?>
        </div>

        <select class="sort-select" onchange="location.href=this.value">
            <option value="posts.php?<?= http_build_query(array_merge($_GET, ['sort'=>'newest'])) ?>" <?= ($_GET['sort']??'')!='popular'&&($_GET['sort']??'')!='rating_high'&&($_GET['sort']??'')!='rating_low'?'selected':'' ?>>Mới nhất</option>
            <option value="posts.php?<?= http_build_query(array_merge($_GET, ['sort'=>'popular'])) ?>" <?= ($_GET['sort']??'')==='popular'?'selected':'' ?>>Phổ biến nhất</option>
            <option value="posts.php?<?= http_build_query(array_merge($_GET, ['sort'=>'rating_high'])) ?>" <?= ($_GET['sort']??'')==='rating_high'?'selected':'' ?>>Đánh giá cao</option>
            <option value="posts.php?<?= http_build_query(array_merge($_GET, ['sort'=>'rating_low'])) ?>" <?= ($_GET['sort']??'')==='rating_low'?'selected':'' ?>>Đánh giá thấp</option>
        </select>
    </div>
</div>

<!-- Posts Grid -->
<div class="main-container">
    <?php if (!empty($_GET['search'])): ?>
        <p class="section-subtitle">Kết quả cho "<strong><?= htmlspecialchars($_GET['search']) ?></strong>" — <?= $total ?> bài</p>
    <?php else: ?>
        <p class="section-subtitle"><?= $total ?> bài review từ cộng đồng</p>
    <?php endif; ?>

    <?php if ($posts && $posts->num_rows > 0): ?>
    <div class="posts-grid">
        <?php while ($post = $posts->fetch_assoc()): ?>
        <article class="post-card">
            <!-- Cover Image -->
            <a href="post_detail.php?id=<?= $post['id'] ?>" class="post-cover">
                <?php if ($post['cover_image']): ?>
                    <img src="uploads/posts/<?= htmlspecialchars($post['cover_image']) ?>"
                         alt="<?= htmlspecialchars($post['title']) ?>" loading="lazy">
                <?php else: ?>
                    <div class="post-cover-placeholder">
                        <span>✦</span>
                        <small><?= htmlspecialchars($post['brand'] ?? '') ?></small>
                    </div>
                <?php endif; ?>
                <div class="post-rating-badge">
                    <?php for ($i=1;$i<=5;$i++): ?>
                        <span style="color:<?=$i<=$post['rating']?'#f9c74f':'rgba(255,255,255,0.4)'?>;">★</span>
                    <?php endfor; ?>
                </div>
            </a>

            <div class="post-body">
                <!-- Category + Brand -->
                <div class="post-meta-top">
                    <?php if ($post['category_name']): ?>
                        <span class="post-cat-tag"><?= htmlspecialchars($post['category_name']) ?></span>
                    <?php endif; ?>
                    <span class="post-brand-tag"><?= htmlspecialchars($post['brand'] ?? '') ?></span>
                </div>

                <!-- Product name -->
                <div class="post-product-name"><?= htmlspecialchars($post['product_name']) ?></div>

                <!-- Title -->
                <a href="post_detail.php?id=<?= $post['id'] ?>">
                    <h2 class="post-title"><?= htmlspecialchars($post['title']) ?></h2>
                </a>

                <!-- Excerpt -->
                <p class="post-excerpt"><?= htmlspecialchars(mb_strimwidth(strip_tags($post['content']), 0, 110, '...')) ?></p>

                <!-- Footer -->
                <div class="post-footer">
                    <div class="post-author">
                        <img src="uploads/avatars/<?= htmlspecialchars($post['avatar'] ?? 'default.png') ?>"
                             onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($post['username']) ?>&background=c8896a&color=fff&size=32'"
                             alt="<?= htmlspecialchars($post['username']) ?>">
                        <span><?= htmlspecialchars($post['username']) ?></span>
                    </div>
                    <div class="post-stats">
                        <span><i class="fas fa-heart"></i> <?= $post['like_count'] ?></span>
                        <span><i class="fas fa-comment"></i> <?= $post['comment_count'] ?></span>
                    </div>
                </div>
                <div class="post-date"><?= date('d/m/Y', strtotime($post['created_at'])) ?></div>
            </div>
        </article>
        <?php endwhile; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page-1])) ?>" class="page-btn"><i class="fas fa-chevron-left"></i></a>
        <?php endif; ?>
        <?php for ($i = max(1,$page-2); $i <= min($total_pages,$page+2); $i++): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
               class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($page < $total_pages): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page+1])) ?>" class="page-btn"><i class="fas fa-chevron-right"></i></a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="empty-state">
        <div class="empty-state-icon">📝</div>
        <h3>Chưa có bài review nào</h3>
        <p>Hãy là người đầu tiên chia sẻ trải nghiệm mỹ phẩm!</p>
        <?php if (isLoggedIn()): ?>
            <a href="create_post.php" class="btn-review" style="display:inline-flex;margin-top:16px;">
                <i class="fas fa-pen-nib"></i> Viết bài ngay
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>