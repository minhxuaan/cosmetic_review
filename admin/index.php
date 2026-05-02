<?php include 'admin_header.php'; ?>

<?php
// Stats
$stats = [
    'products' => $conn->query("SELECT COUNT(*) as c FROM products")->fetch_assoc()['c'],
    'users'    => $conn->query("SELECT COUNT(*) as c FROM users WHERE role='user'")->fetch_assoc()['c'],
    'reviews'  => $conn->query("SELECT COUNT(*) as c FROM reviews WHERE status='approved'")->fetch_assoc()['c'],
    'pending'  => $conn->query("SELECT COUNT(*) as c FROM reviews WHERE status='pending'")->fetch_assoc()['c'],
];

// Recent reviews
$recent_reviews = $conn->query("SELECT r.*, u.username, p.name as product_name FROM reviews r JOIN users u ON r.user_id=u.id JOIN products p ON r.product_id=p.id ORDER BY r.created_at DESC LIMIT 8");

// Rating overview by category
$cat_stats = $conn->query("SELECT c.name, COUNT(p.id) as product_count, COALESCE(AVG(r.rating),0) as avg_rating FROM categories c LEFT JOIN products p ON p.category_id=c.id LEFT JOIN reviews r ON r.product_id=p.id AND r.status='approved' GROUP BY c.id");

// Monthly reviews (last 6 months)
$monthly = $conn->query("SELECT DATE_FORMAT(created_at,'%m/%Y') as month, COUNT(*) as cnt FROM reviews WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY month ORDER BY created_at");
?>

<div class="admin-topbar">
    <h1>Dashboard</h1>
    <span style="font-size:0.9rem;color:#888;">Xin chào, <strong><?= htmlspecialchars($_SESSION['username']) ?></strong> 👑</span>
</div>

<!-- Stat Cards -->
<div class="stat-cards">
    <div class="stat-card">
        <div class="stat-card-icon rose"><i class="fas fa-box"></i></div>
        <div class="stat-card-body">
            <div class="stat-card-number"><?= $stats['products'] ?></div>
            <div class="stat-card-label">Sản phẩm</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon gold"><i class="fas fa-users"></i></div>
        <div class="stat-card-body">
            <div class="stat-card-number"><?= $stats['users'] ?></div>
            <div class="stat-card-label">Người dùng</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon green"><i class="fas fa-star"></i></div>
        <div class="stat-card-body">
            <div class="stat-card-number"><?= $stats['reviews'] ?></div>
            <div class="stat-card-label">Review đã duyệt</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon blue"><i class="fas fa-clock"></i></div>
        <div class="stat-card-body">
            <div class="stat-card-number"><?= $stats['pending'] ?></div>
            <div class="stat-card-label">Chờ duyệt</div>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
    <!-- Recent Reviews -->
    <div class="admin-card" style="grid-column:1/-1;">
        <div class="admin-card-header">
            <h3>Review gần đây</h3>
            <a href="reviews.php" style="font-size:0.85rem;color:var(--rose);">Xem tất cả</a>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Người dùng</th>
                    <th>Sản phẩm</th>
                    <th>Sao</th>
                    <th>Trạng thái</th>
                    <th>Ngày</th>
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($rv = $recent_reviews->fetch_assoc()): ?>
            <tr>
                <td><strong><?= htmlspecialchars($rv['username']) ?></strong></td>
                <td><?= htmlspecialchars(mb_strimwidth($rv['product_name'], 0, 40, '...')) ?></td>
                <td>
                    <?php for ($i=1;$i<=5;$i++): ?>
                        <span style="color:<?=$i<=$rv['rating']?'#c9a86c':'#ddd'?>;">★</span>
                    <?php endfor; ?>
                </td>
                <td>
                    <span class="badge badge-<?= $rv['status'] === 'approved' ? 'approved' : ($rv['status'] === 'pending' ? 'pending' : 'rejected') ?>">
                        <?= $rv['status'] === 'approved' ? 'Đã duyệt' : ($rv['status'] === 'pending' ? 'Chờ duyệt' : 'Từ chối') ?>
                    </span>
                </td>
                <td><?= date('d/m/Y', strtotime($rv['created_at'])) ?></td>
                <td>
                    <?php if ($rv['status'] === 'pending'): ?>
                        <a href="reviews.php?approve=<?= $rv['id'] ?>" class="btn-sm approve">✓ Duyệt</a>
                        <a href="reviews.php?reject=<?= $rv['id'] ?>" class="btn-sm reject">✕ Từ chối</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- Category Stats -->
    <div class="admin-card">
        <div class="admin-card-header"><h3>Thống kê theo danh mục</h3></div>
        <div style="padding:20px;">
            <?php while ($cs = $cat_stats->fetch_assoc()): ?>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--cream-dark);">
                <div>
                    <div style="font-weight:600;font-size:0.9rem;"><?= htmlspecialchars($cs['name']) ?></div>
                    <div style="font-size:0.8rem;color:#888;"><?= $cs['product_count'] ?> sản phẩm</div>
                </div>
                <div style="text-align:right;">
                    <div style="color:var(--gold);font-weight:600;">
                        <?php for ($i=1;$i<=5;$i++): ?>★<?php endfor; ?>
                    </div>
                    <div style="font-size:0.8rem;color:#888;"><?= number_format($cs['avg_rating'],1) ?>/5</div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="admin-card">
        <div class="admin-card-header"><h3>Thao tác nhanh</h3></div>
        <div style="padding:20px;display:flex;flex-direction:column;gap:12px;">
            <a href="add_product.php" class="btn-add" style="border-radius:50px;padding:12px 20px;">
                <i class="fas fa-plus"></i> Thêm sản phẩm mới
            </a>
            <a href="reviews.php?filter=pending" class="btn-add" style="border-radius:50px;padding:12px 20px;background:var(--gold);">
                <i class="fas fa-clock"></i> Duyệt review (<?= $stats['pending'] ?>)
            </a>
            <a href="categories.php" class="btn-add" style="border-radius:50px;padding:12px 20px;background:var(--charcoal-mid);">
                <i class="fas fa-tags"></i> Quản lý danh mục
            </a>
        </div>
    </div>
</div>

</main></div>
<script src="../assets/js/main.js"></script>
</body></html>