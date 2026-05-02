<?php include 'admin_header.php';

// Thêm danh mục
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    $name = sanitize($_POST['cat_name'] ?? '');
    $slug = sanitize(strtolower(preg_replace('/\s+/', '-', $_POST['cat_name'] ?? '')));
    if ($name) {
        $stmt = $conn->prepare("INSERT INTO categories (name, slug) VALUES (?,?)");
        $stmt->bind_param('ss', $name, $slug);
        $stmt->execute();
    }
    redirect('categories.php');
}

// Xóa
if (isset($_GET['delete'])) {
    $c_id = (int)$_GET['delete'];
    $conn->query("DELETE FROM categories WHERE id=$c_id");
    redirect('categories.php');
}

$categories = $conn->query("SELECT c.*, COUNT(p.id) as product_count FROM categories c LEFT JOIN products p ON p.category_id=c.id GROUP BY c.id ORDER BY c.name");
?>

<div class="admin-topbar">
    <h1>Quản lý danh mục</h1>
</div>

<div style="display:grid;grid-template-columns:1fr 2fr;gap:24px;align-items:start;">
    <!-- Add form -->
    <div class="admin-card">
        <div class="admin-card-header"><h3>Thêm danh mục</h3></div>
        <div style="padding:24px;">
            <form method="POST">
                <div class="form-group">
                    <label>Tên danh mục</label>
                    <input type="text" name="cat_name" placeholder="VD: Chăm sóc da" required>
                </div>
                <button type="submit" name="add" class="btn-add" style="border:none;cursor:pointer;padding:10px 20px;border-radius:50px;">
                    <i class="fas fa-plus"></i> Thêm
                </button>
            </form>
        </div>
    </div>

    <!-- List -->
    <div class="admin-card">
        <div class="admin-card-header"><h3>Danh sách danh mục</h3></div>
        <table class="data-table">
            <thead>
                <tr><th>Tên</th><th>Slug</th><th>Số SP</th><th>Hành động</th></tr>
            </thead>
            <tbody>
            <?php while ($c = $categories->fetch_assoc()): ?>
            <tr>
                <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
                <td><code><?= htmlspecialchars($c['slug']) ?></code></td>
                <td><?= $c['product_count'] ?></td>
                <td>
                    <?php if ($c['product_count'] == 0): ?>
                        <a href="categories.php?delete=<?= $c['id'] ?>" class="btn-sm delete" data-confirm="Xóa danh mục này?">🗑 Xóa</a>
                    <?php else: ?>
                        <span style="color:#aaa;font-size:0.8rem;">Đang sử dụng</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

</main></div>
<script src="../assets/js/main.js"></script>
</body></html>