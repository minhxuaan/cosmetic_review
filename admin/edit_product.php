<?php include 'admin_header.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect('products.php');

$product = $conn->query("SELECT * FROM products WHERE id=$id")->fetch_assoc();
if (!$product) redirect('products.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = sanitize($_POST['name'] ?? '');
    $brand       = sanitize($_POST['brand'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $description = sanitize($_POST['description'] ?? '');
    $price       = (float)($_POST['price'] ?? 0);

    $image = $product['image'];

    if (!empty($_FILES['image']['name'])) {
        $upload_dir = '../uploads/products/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg','jpeg','png','webp'];
        if (in_array(strtolower($ext), $allowed) && $_FILES['image']['size'] < 5*1024*1024) {
            $new_image = uniqid('prod_') . '.' . $ext;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $new_image)) {
                if ($image && file_exists('../uploads/products/' . $image)) {
                    unlink('../uploads/products/' . $image);
                }
                $image = $new_image;
            }
        }
    }

    $stmt = $conn->prepare("UPDATE products SET name=?, brand=?, category_id=?, description=?, price=?, image=? WHERE id=?");
    $stmt->bind_param('ssisdsi', $name, $brand, $category_id, $description, $price, $image, $id);
    if ($stmt->execute()) {
        redirect('products.php?msg=updated');
    } else {
        $error = 'Lỗi khi cập nhật sản phẩm.';
    }
}

$categories = $conn->query("SELECT * FROM categories ORDER BY name");
?>

<div class="admin-topbar">
    <h1>Chỉnh sửa sản phẩm</h1>
    <a href="products.php" style="font-size:0.9rem;color:var(--rose);"><i class="fas fa-arrow-left"></i> Quay lại</a>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= $error ?></div>
<?php endif; ?>

<div class="admin-card">
    <div class="admin-card-header"><h3><?= htmlspecialchars($product['name']) ?></h3></div>
    <div style="padding:32px;">
        <form method="POST" enctype="multipart/form-data">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                <div class="form-group">
                    <label>Tên sản phẩm *</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($product['name']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Thương hiệu *</label>
                    <input type="text" name="brand" value="<?= htmlspecialchars($product['brand']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Danh mục</label>
                    <select name="category_id">
                        <option value="">-- Chọn danh mục --</option>
                        <?php while ($c = $categories->fetch_assoc()): ?>
                            <option value="<?= $c['id'] ?>" <?= $product['category_id']==$c['id']?'selected':'' ?>>
                                <?= htmlspecialchars($c['name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Giá (VNĐ)</label>
                    <input type="number" name="price" value="<?= $product['price'] ?>" min="0" step="1000">
                </div>
            </div>

            <div class="form-group">
                <label>Mô tả</label>
                <textarea name="description" rows="5" style="width:100%;padding:12px;border:1.5px solid var(--cream-dark);border-radius:var(--radius-sm);font-family:'DM Sans',sans-serif;resize:vertical;"><?= htmlspecialchars($product['description']) ?></textarea>
            </div>

            <div class="form-group">
                <label>Ảnh sản phẩm</label>
                <?php if ($product['image']): ?>
                    <div style="margin-bottom:10px;">
                        <img src="../uploads/products/<?= htmlspecialchars($product['image']) ?>" style="height:80px;border-radius:8px;">
                        <small style="display:block;color:#888;margin-top:4px;">Ảnh hiện tại. Upload ảnh mới để thay thế.</small>
                    </div>
                <?php endif; ?>
                <input type="file" name="image" accept="image/*" style="padding:8px;background:var(--cream);">
            </div>

            <button type="submit" class="btn-add" style="padding:12px 28px;border-radius:50px;border:none;cursor:pointer;font-size:0.95rem;">
                <i class="fas fa-save"></i> Lưu thay đổi
            </button>
        </form>
    </div>
</div>

</main></div>
<script src="../assets/js/main.js"></script>
</body></html>