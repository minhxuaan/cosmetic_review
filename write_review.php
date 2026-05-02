<?php
require_once 'config/db.php';
if (!isLoggedIn()) redirect('login.php');

$product_id = (int)($_GET['id'] ?? 0);
if (!$product_id) redirect('index.php');

$product = $conn->query("SELECT * FROM products WHERE id = $product_id AND is_active = 1")->fetch_assoc();
if (!$product) { echo "Sản phẩm không tồn tại."; exit; }

// Kiểm tra đã review chưa
$uid = $_SESSION['user_id'];
$already = $conn->query("SELECT id FROM reviews WHERE product_id=$product_id AND user_id=$uid")->num_rows;
if ($already) {
    $_SESSION['msg'] = 'Bạn đã review sản phẩm này rồi.';
    redirect("product.php?id=$product_id");
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating  = (int)($_POST['rating'] ?? 0);
    $title   = sanitize($_POST['title'] ?? '');
    $content = sanitize($_POST['content'] ?? '');

    if ($rating < 1 || $rating > 5) {
        $error = 'Vui lòng chọn số sao đánh giá.';
    } elseif (strlen($content) < 10) {
        $error = 'Nội dung review phải có ít nhất 10 ký tự.';
    } else {
        $stmt = $conn->prepare("INSERT INTO reviews (product_id, user_id, rating, title, content, status) VALUES (?, ?, ?, ?, ?, 'pending')");
        $stmt->bind_param('iiiss', $product_id, $uid, $rating, $title, $content);

        if ($stmt->execute()) {
            $review_id = $conn->insert_id;

            // Upload ảnh
            if (!empty($_FILES['images']['name'][0])) {
                $upload_dir = 'uploads/reviews/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

                foreach ($_FILES['images']['tmp_name'] as $k => $tmp) {
                    if ($_FILES['images']['error'][$k] === 0) {
                        $ext = pathinfo($_FILES['images']['name'][$k], PATHINFO_EXTENSION);
                        $allowed = ['jpg','jpeg','png','webp'];
                        if (in_array(strtolower($ext), $allowed) && $_FILES['images']['size'][$k] < 5*1024*1024) {
                            $filename = uniqid('rv_') . '.' . $ext;
                            if (move_uploaded_file($tmp, $upload_dir . $filename)) {
                                $conn->query("INSERT INTO review_images (review_id, image_path) VALUES ($review_id, '$filename')");
                            }
                        }
                    }
                }
            }

            redirect("product.php?id=$product_id&reviewed=1");
        } else {
            $error = 'Đã xảy ra lỗi, vui lòng thử lại.';
        }
    }
}
?>
<?php include 'includes/header.php'; ?>

<div class="main-container" style="max-width:720px;">
    <div style="margin-bottom:24px;">
        <a href="product.php?id=<?= $product_id ?>" style="color:var(--rose);font-size:0.9rem;">
            <i class="fas fa-arrow-left"></i> Quay lại sản phẩm
        </a>
    </div>

    <div style="background:white;border-radius:var(--radius);padding:40px;box-shadow:var(--shadow);">
        <h1 class="section-title" style="margin-bottom:8px;">Viết review</h1>
        <p class="section-subtitle">Sản phẩm: <strong><?= htmlspecialchars($product['name']) ?></strong></p>

        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <!-- Star Rating -->
            <div class="form-group">
                <label>Đánh giá của bạn <span style="color:var(--rose)">*</span></label>
                <div class="star-rating-input">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                    <input type="radio" id="star<?= $i ?>" name="rating" value="<?= $i ?>">
                    <label for="star<?= $i ?>" title="<?= $i ?> sao">★</label>
                    <?php endfor; ?>
                </div>
                <div id="ratingLabel" style="font-size:0.85rem;color:#888;margin-top:6px;">Chọn số sao...</div>
            </div>

            <div class="form-group">
                <label>Tiêu đề review</label>
                <input type="text" name="title" placeholder="VD: Sản phẩm tuyệt vời, dưỡng ẩm cực tốt!" maxlength="200" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Nội dung review <span style="color:var(--rose)">*</span></label>
                <textarea name="content" rows="6" placeholder="Chia sẻ trải nghiệm thực tế của bạn: kết cấu, mùi hương, hiệu quả sau khi sử dụng, phù hợp loại da nào..." style="width:100%;padding:12px;border:1.5px solid var(--cream-dark);border-radius:var(--radius-sm);font-family:'DM Sans',sans-serif;resize:vertical;" required><?= htmlspecialchars($_POST['content'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label>Ảnh thực tế (tối đa 5 ảnh)</label>
                <input type="file" name="images[]" multiple accept="image/*" style="padding:8px;background:var(--cream);">
                <small style="color:#888;font-size:0.8rem;">Định dạng: JPG, PNG, WEBP. Tối đa 5MB mỗi ảnh.</small>
            </div>

            <div class="alert alert-warning">
                <i class="fas fa-info-circle"></i>
                Review của bạn sẽ được admin duyệt trước khi hiển thị.
            </div>

            <button type="submit" class="btn-primary" style="width:auto;padding:14px 32px;">
                <i class="fas fa-paper-plane"></i> Gửi review
            </button>
        </form>
    </div>
</div>

<script>
const labels = ['', 'Rất tệ 😞', 'Không tốt 😕', 'Bình thường 😐', 'Tốt 😊', 'Tuyệt vời! 🌟'];
document.querySelectorAll('.star-rating-input input').forEach(input => {
    input.addEventListener('change', function() {
        document.getElementById('ratingLabel').textContent = labels[this.value];
    });
});
</script>

<?php include 'includes/footer.php'; ?>