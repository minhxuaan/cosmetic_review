<?php
require_once 'config/db.php';

// Lấy danh mục
$categories = $conn->query("SELECT * FROM categories ORDER BY name");

// Filter & Search
$where = "WHERE 1=1 AND p.is_active = 1";
$params = [];

if (!empty($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $where .= " AND (p.name LIKE '%$search%' OR p.brand LIKE '%$search%')";
}

if (!empty($_GET['category'])) {
    $cat_id = (int)$_GET['category'];
    $where .= " AND p.category_id = $cat_id";
}

// Sort
$sort = "p.created_at DESC";
if (!empty($_GET['sort'])) {
    switch($_GET['sort']) {
        case 'rating': $sort = "avg_rating DESC"; break;
        case 'reviews': $sort = "review_count DESC"; break;
        case 'price_asc': $sort = "p.price ASC"; break;
        case 'price_desc': $sort = "p.price DESC"; break;
        case 'newest': $sort = "p.created_at DESC"; break;
    }
}

// Pagination
$per_page = 12;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

// Count
$count_sql = "SELECT COUNT(*) as total FROM products p $where";
$count_result = $conn->query($count_sql)->fetch_assoc();
$total = $count_result['total'];
$total_pages = ceil($total / $per_page);

// Products query
$sql = "SELECT p.*, c.name as category_name,
        COALESCE(AVG(r.rating), 0) as avg_rating,
        COUNT(r.id) as review_count
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN reviews r ON r.product_id = p.id AND r.status = 'approved'
        $where
        GROUP BY p.id
        ORDER BY $sort
        LIMIT $per_page OFFSET $offset";

$products = $conn->query($sql);

// Stats
$stats = $conn->query("SELECT 
    (SELECT COUNT(*) FROM products WHERE is_active=1) as total_products,
    (SELECT COUNT(*) FROM reviews WHERE status='approved') as total_reviews,
    (SELECT COUNT(*) FROM users) as total_users")->fetch_assoc();
?>
<?php include 'includes/header.php'; ?>

<!-- Hero -->
<section class="hero">
    <div class="hero-content">
        <h1>Review mỹ phẩm từ<br><em>cộng đồng thật sự</em></h1>
        <p>Khám phá hàng trăm sản phẩm với đánh giá trung thực từ những người đã dùng thực tế.</p>
        <div class="hero-stats">
            <div class="stat-item">
                <div class="stat-number"><?= number_format($stats['total_products']) ?>+</div>
                <div class="stat-label">Sản phẩm</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?= number_format($stats['total_reviews']) ?>+</div>
                <div class="stat-label">Đánh giá</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?= number_format($stats['total_users']) ?>+</div>
                <div class="stat-label">Thành viên</div>
            </div>
        </div>
    </div>
</section>

<!-- Filter Bar -->
<div class="filter-bar">
    <div class="filter-container">
        <span class="filter-label">Danh mục:</span>
        <a href="index.php" class="filter-btn <?= empty($_GET['category']) ? 'active' : '' ?>">Tất cả</a>
        <?php
        $categories->data_seek(0);
        while ($cat = $categories->fetch_assoc()):
        ?>
            <a href="index.php?category=<?= $cat['id'] ?><?= !empty($_GET['search']) ? '&search='.urlencode($_GET['search']) : '' ?>"
               class="filter-btn <?= (isset($_GET['category']) && $_GET['category'] == $cat['id']) ? 'active' : '' ?>">
                <?= htmlspecialchars($cat['name']) ?>
            </a>
        <?php endwhile; ?>

        <select class="sort-select" onchange="location.href=this.value">
            <option value="index.php?<?= http_build_query(array_merge($_GET, ['sort'=>'newest'])) ?>" <?= ($_GET['sort']??'')=='newest'?'selected':'' ?>>Mới nhất</option>
            <option value="index.php?<?= http_build_query(array_merge($_GET, ['sort'=>'rating'])) ?>" <?= ($_GET['sort']??'')=='rating'?'selected':'' ?>>Đánh giá cao</option>
            <option value="index.php?<?= http_build_query(array_merge($_GET, ['sort'=>'reviews'])) ?>" <?= ($_GET['sort']??'')=='reviews'?'selected':'' ?>>Nhiều review</option>
            <option value="index.php?<?= http_build_query(array_merge($_GET, ['sort'=>'price_asc'])) ?>" <?= ($_GET['sort']??'')=='price_asc'?'selected':'' ?>>Giá: thấp → cao</option>
            <option value="index.php?<?= http_build_query(array_merge($_GET, ['sort'=>'price_desc'])) ?>" <?= ($_GET['sort']??'')=='price_desc'?'selected':'' ?>>Giá: cao → thấp</option>
        </select>
    </div>
</div>

<!-- Products -->
<div class="main-container">
    <?php if (!empty($_GET['search'])): ?>
        <p class="section-subtitle">
            Kết quả tìm kiếm cho "<strong><?= htmlspecialchars($_GET['search']) ?></strong>" — <?= $total ?> sản phẩm
        </p>
    <?php endif; ?>

    <?php if ($products && $products->num_rows > 0): ?>
        <div class="products-grid">
            <?php while ($p = $products->fetch_assoc()):
                $avg = round($p['avg_rating'], 1);
            ?>
            <div class="product-card">
                <div class="product-img-wrap">
                    <a href="product.php?id=<?= $p['id'] ?>">
                        <?php if (!empty($p['image'])): ?>
                            <img src="uploads/products/<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" loading="lazy">
                        <?php else: ?>
                            <img src="assets/img/no-product.png" alt="No image" loading="lazy" onerror="this.src='https://via.placeholder.com/400x300/fdf6f0/c8896a?text=✦'">
                        <?php endif; ?>
                    </a>
                    <?php if ($p['review_count'] > 0): ?>
                        <span class="product-badge">⭐ <?= $avg ?></span>
                    <?php endif; ?>
                </div>
                <div class="product-info">
                    <div class="product-category"><?= htmlspecialchars($p['category_name'] ?? '') ?></div>
                    <a href="product.php?id=<?= $p['id'] ?>">
                        <h3 class="product-name"><?= htmlspecialchars($p['name']) ?></h3>
                    </a>
                    <div class="product-brand">by <?= htmlspecialchars($p['brand']) ?></div>

                    <div class="stars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span class="star <?= $i <= round($avg) ? 'filled' : '' ?>">★</span>
                        <?php endfor; ?>
                        <span class="rating-text"><?= $avg > 0 ? $avg : 'Chưa có' ?> (<?= $p['review_count'] ?> review)</span>
                    </div>

                    <div class="product-footer">
                        <div class="product-price">
                            <?= $p['price'] > 0 ? number_format($p['price'], 0, ',', '.') . 'đ' : 'Liên hệ' ?>
                        </div>
                        <a href="product.php?id=<?= $p['id'] ?>" class="btn-review">
                            <i class="fas fa-eye"></i> Xem
                        </a>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
                   class="page-btn <?= $i === $page ? 'active' : '' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon">🔍</div>
            <h3>Không tìm thấy sản phẩm</h3>
            <p>Thử thay đổi bộ lọc hoặc từ khóa tìm kiếm khác.</p>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>