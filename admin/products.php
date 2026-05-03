<?php include 'admin_header.php';

// Xóa sản phẩm (xóa thật)
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    // Xóa ảnh sản phẩm nếu có
    $product = $conn->query("SELECT image FROM products WHERE id=$del_id")->fetch_assoc();
    if ($product && $product['image'] && file_exists('../uploads/products/' . $product['image'])) {
        unlink('../uploads/products/' . $product['image']);
    }
    $conn->query("DELETE FROM products WHERE id=$del_id");
    redirect('products.php?msg=deleted');
}

// Toggle active
if (isset($_GET['toggle'])) {
    $t_id = (int)$_GET['toggle'];
    $conn->query("UPDATE products SET is_active = NOT is_active WHERE id=$t_id");
    redirect('products.php');
}

$msg = $_GET['msg'] ?? '';

// Search
$search = '';
$where = "WHERE 1=1";
if (!empty($_GET['s'])) {
    $search = $conn->real_escape_string($_GET['s']);
    $where .= " AND (p.name LIKE '%$search%' OR p.brand LIKE '%$search%')";
}

$products = $conn->query("SELECT p.*, c.name as cat_name,
    COALESCE(AVG(r.rating),0) as avg_rating, COUNT(r.id) as review_count
    FROM products p
    LEFT JOIN categories c ON p.category_id=c.id
    LEFT JOIN reviews r ON r.product_id=p.id AND r.status='approved'
    $where
    GROUP BY p.id
    ORDER BY p.created_at DESC");
?>

<div class="admin-topbar">
    <h1>Quản lý sản phẩm</h1>
    <a href="add_product.php" class="btn-add"><i class="fas fa-plus"></i> Thêm sản phẩm</a>
</div>

<?php if ($msg === 'deleted'): ?>
    <div class="alert alert-success"><i class="fas fa-check"></i> Đã xóa sản phẩm thành công.</div>
<?php endif; ?>
<?php if ($msg === 'added'): ?>
    <div class="alert alert-success"><i class="fas fa-check"></i> Thêm sản phẩm thành công.</div>
<?php endif; ?>

<div class="admin-card">
    <div class="admin-card-header">
        <h3>Danh sách sản phẩm (<?= $products->num_rows ?>)</h3>
        <form method="GET" style="display:flex;gap:8px;">
            <input type="text" name="s" value="<?= htmlspecialchars($search) ?>" placeholder="Tìm sản phẩm..." style="padding:6px 12px;border:1.5px solid var(--cream-dark);border-radius:var(--radius-sm);font-family:'DM Sans',sans-serif;">
            <button type="submit" class="btn-sm edit" style="padding:6px 12px;">Tìm</button>
        </form>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Ảnh</th>
                <th>Tên sản phẩm</th>
                <th>Thương hiệu</th>
                <th>Danh mục</th>
                <th>Giá</th>
                <th>Rating</th>
                <th>Trạng thái</th>
                <th>Hành động</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($p = $products->fetch_assoc()): ?>
        <tr>
            <td>
                <?php if ($p['image']): ?>
                    <img src="../uploads/products/<?= htmlspecialchars($p['image']) ?>" style="width:48px;height:48px;object-fit:cover;border-radius:8px;">
                <?php else: ?>
                    <div style="width:48px;height:48px;background:var(--cream);border-radius:8px;display:flex;align-items:center;justify-content:center;color:#ccc;">✦</div>
                <?php endif; ?>
            </td>
            <td><strong><?= htmlspecialchars(mb_strimwidth($p['name'],0,45,'...')) ?></strong></td>
            <td><?= htmlspecialchars($p['brand']) ?></td>
            <td><?= htmlspecialchars($p['cat_name'] ?? '—') ?></td>
            <td><?= $p['price']>0 ? number_format($p['price'],0,',','.') . 'đ' : '—' ?></td>
            <td>
                <span style="color:var(--gold);">★</span>
                <?= number_format($p['avg_rating'],1) ?>
                <span style="color:#888;font-size:0.8rem;">(<?= $p['review_count'] ?>)</span>
            </td>
            <td>
                <span class="badge <?= $p['is_active'] ? 'badge-approved' : 'badge-rejected' ?>">
                    <?= $p['is_active'] ? 'Hiển thị' : 'Ẩn' ?>
                </span>
            </td>
            <td style="white-space:nowrap;">
                <a href="edit_product.php?id=<?= $p['id'] ?>" class="btn-sm edit"><i class="fas fa-edit"></i></a>
                <a href="products.php?toggle=<?= $p['id'] ?>" class="btn-sm <?= $p['is_active']?'reject':'approve' ?>">
                    <i class="fas fa-<?= $p['is_active']?'eye-slash':'eye' ?>"></i>
                </a>
                <a href="products.php?delete=<?= $p['id'] ?>" class="btn-sm delete"
                   data-confirm="Xoá sản phẩm này?"><i class="fas fa-trash"></i></a>
            </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

</main></div>
<script src="../assets/js/main.js"></script>
</body></html>