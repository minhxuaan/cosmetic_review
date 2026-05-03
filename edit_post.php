<?php
require_once 'config/db.php';
if (!isLoggedIn()) redirect('login.php');

$uid = $_SESSION['user_id'];
$id  = (int)($_GET['id'] ?? 0);
if (!$id) redirect('my_posts.php');

// Lấy bài - chỉ cho sửa bài của chính mình (hoặc admin sửa bất kỳ)
$post = $conn->query("
    SELECT p.*, c.name as category_name
    FROM posts p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.id = $id " . (isAdmin() ? "" : "AND p.user_id = $uid")
)->fetch_assoc();

if (!$post) {
    redirect('my_posts.php');
}

// Không cho sửa bài đã duyệt (trừ admin)
if ($post['status'] === 'approved' && !isAdmin()) {
    $_SESSION['flash'] = 'Bài đã được duyệt, bạn không thể chỉnh sửa. Liên hệ admin nếu cần thay đổi.';
    redirect('my_posts.php');
}

$categories = $conn->query("SELECT * FROM categories ORDER BY name");
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_name = sanitize($_POST['product_name'] ?? '');
    $brand        = sanitize($_POST['brand'] ?? '');
    $category_id  = (int)($_POST['category_id'] ?? 0);
    $rating       = (int)($_POST['rating'] ?? 0);
    $title        = sanitize($_POST['title'] ?? '');
    $content      = sanitize($_POST['content'] ?? '');
    $pros         = sanitize($_POST['pros'] ?? '');
    $cons         = sanitize($_POST['cons'] ?? '');

    if (empty($product_name))           $error = 'Vui lòng nhập tên sản phẩm.';
    elseif ($rating < 1 || $rating > 5) $error = 'Vui lòng chọn số sao đánh giá.';
    elseif (empty($title))              $error = 'Vui lòng nhập tiêu đề bài review.';
    elseif (mb_strlen($content) < 50)   $error = 'Nội dung review cần ít nhất 50 ký tự.';
    else {
        // Upload ảnh bìa mới nếu có
        $cover_image = $post['cover_image'];
        if (!empty($_FILES['cover_image']['name'])) {
            $upload_dir = 'uploads/posts/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $ext = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp']) && $_FILES['cover_image']['size'] < 5*1024*1024) {
                $new_name = 'cover_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $upload_dir . $new_name)) {
                    // Xóa ảnh bìa cũ
                    if ($cover_image && file_exists($upload_dir . $cover_image)) {
                        unlink($upload_dir . $cover_image);
                    }
                    $cover_image = $new_name;
                }
            }
        }

        // Khi user sửa bài rejected → tự động chuyển về pending để admin duyệt lại
        $new_status = $post['status'];
        if (!isAdmin() && $post['status'] === 'rejected') {
            $new_status = 'pending';
        }

        $stmt = $conn->prepare("
            UPDATE posts
            SET product_name=?, brand=?, category_id=?, rating=?, title=?, content=?, pros=?, cons=?, cover_image=?, status=?
            WHERE id=?
        ");
        $stmt->bind_param('ssiissssssi', $product_name, $brand, $category_id, $rating, $title, $content, $pros, $cons, $cover_image, $new_status, $id);

        if ($stmt->execute()) {
            // Upload ảnh gallery mới nếu có
            if (!empty($_FILES['gallery']['name'][0])) {
                $upload_dir = 'uploads/posts/';
                foreach ($_FILES['gallery']['tmp_name'] as $k => $tmp) {
                    if ($_FILES['gallery']['error'][$k] === 0) {
                        $ext = strtolower(pathinfo($_FILES['gallery']['name'][$k], PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg','jpeg','png','webp']) && $_FILES['gallery']['size'][$k] < 5*1024*1024) {
                            $fname = 'gallery_' . uniqid() . '.' . $ext;
                            if (move_uploaded_file($tmp, $upload_dir . $fname)) {
                                $conn->query("INSERT INTO post_images (post_id, image_path) VALUES ($id, '$fname')");
                            }
                        }
                    }
                }
            }

            // Xóa ảnh gallery được chọn xóa
            if (!empty($_POST['delete_images'])) {
                foreach ($_POST['delete_images'] as $img_id) {
                    $img_id = (int)$img_id;
                    $img = $conn->query("SELECT image_path FROM post_images WHERE id=$img_id AND post_id=$id")->fetch_assoc();
                    if ($img) {
                        if (file_exists('uploads/posts/' . $img['image_path'])) {
                            unlink('uploads/posts/' . $img['image_path']);
                        }
                        $conn->query("DELETE FROM post_images WHERE id=$img_id");
                    }
                }
            }

            if (isAdmin()) {
                redirect('admin/posts_manage.php?msg=updated');
            } else {
                redirect('my_posts.php?msg=updated');
            }
        } else {
            $error = 'Có lỗi xảy ra, vui lòng thử lại.';
        }
    }
    // Reload lại data post sau khi lỗi để không mất dữ liệu đã nhập
    $post['product_name'] = $_POST['product_name'] ?? $post['product_name'];
    $post['brand']        = $_POST['brand'] ?? $post['brand'];
    $post['category_id']  = $_POST['category_id'] ?? $post['category_id'];
    $post['rating']       = $_POST['rating'] ?? $post['rating'];
    $post['title']        = $_POST['title'] ?? $post['title'];
    $post['content']      = $_POST['content'] ?? $post['content'];
    $post['pros']         = $_POST['pros'] ?? $post['pros'];
    $post['cons']         = $_POST['cons'] ?? $post['cons'];
}

// Lấy ảnh gallery hiện tại
$gallery = $conn->query("SELECT * FROM post_images WHERE post_id = $id");
?>
<?php include 'includes/header.php'; ?>

<div class="main-container" style="max-width:780px;">
    <div style="margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;">
        <a href="my_posts.php" style="color:var(--rose);font-size:0.9rem;">
            <i class="fas fa-arrow-left"></i> Bài review của tôi
        </a>
        <?php if ($post['status'] !== 'approved' || isAdmin()): ?>
            <a href="post_detail.php?id=<?= $id ?>&preview=1" target="_blank" style="color:var(--charcoal-mid);font-size:0.9rem;">
                <i class="fas fa-eye"></i> Xem trước
            </a>
        <?php endif; ?>
    </div>

    <!-- Thông báo trạng thái -->
    <?php if ($post['status'] === 'rejected' && !isAdmin()): ?>
        <div class="alert alert-error alert-permanent" style="margin-bottom:16px;">
            <i class="fas fa-info-circle"></i>
            Bài bị từ chối<?php if ($post['reject_reason']): ?>: <strong><?= htmlspecialchars($post['reject_reason']) ?></strong><?php endif; ?>.
            Hãy chỉnh sửa và gửi lại để được duyệt.
        </div>
    <?php elseif ($post['status'] === 'pending'): ?>
        <div class="alert alert-warning alert-permanent" style="margin-bottom:16px;">
            <i class="fas fa-hourglass-half"></i>
            Bài đang chờ duyệt. Bạn vẫn có thể chỉnh sửa trước khi admin duyệt.
        </div>
    <?php endif; ?>

    <div class="create-post-card">
        <div class="create-post-header" style="background:linear-gradient(135deg,var(--charcoal-mid),var(--charcoal));">
            <span class="create-post-icon">✎</span>
            <div>
                <h1>Chỉnh sửa bài review</h1>
                <p><?= htmlspecialchars(mb_strimwidth($post['title'], 0, 60, '...')) ?></p>
            </div>
        </div>

        <?php if ($error): ?>
            <div style="padding:0 40px;margin-top:20px;">
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">

            <!-- THÔNG TIN SẢN PHẨM -->
            <div class="form-section">
                <div class="form-section-title">
                    <span class="form-section-num">1</span> Thông tin sản phẩm
                </div>
                <div class="form-row-2">
                    <div class="form-group">
                        <label>Tên sản phẩm <span class="required">*</span></label>
                        <input type="text" name="product_name"
                               value="<?= htmlspecialchars($post['product_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Thương hiệu</label>
                        <input type="text" name="brand"
                               value="<?= htmlspecialchars($post['brand'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-row-2">
                    <div class="form-group">
                        <label>Danh mục</label>
                        <select name="category_id">
                            <option value="">-- Chọn danh mục --</option>
                            <?php $categories->data_seek(0); while ($c = $categories->fetch_assoc()): ?>
                                <option value="<?= $c['id'] ?>" <?= $post['category_id']==$c['id']?'selected':'' ?>>
                                    <?= htmlspecialchars($c['name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Đánh giá <span class="required">*</span></label>
                        <div class="star-rating-input">
                            <?php for ($i=5;$i>=1;$i--): ?>
                                <input type="radio" id="star<?=$i?>" name="rating" value="<?=$i?>"
                                       <?= $post['rating']==$i ? 'checked' : '' ?>>
                                <label for="star<?=$i?>" title="<?=$i?> sao">★</label>
                            <?php endfor; ?>
                        </div>
                        <div id="ratingLabel" class="rating-label-text">
                            <?php
                            $labels = ['','Rất tệ 😞','Không tốt 😕','Bình thường 😐','Tốt 😊','Xuất sắc! 🌟'];
                            echo $labels[$post['rating']] ?? '';
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- NỘI DUNG -->
            <div class="form-section">
                <div class="form-section-title">
                    <span class="form-section-num">2</span> Nội dung bài review
                </div>

                <div class="form-group">
                    <label>Tiêu đề <span class="required">*</span></label>
                    <input type="text" name="title" maxlength="200"
                           value="<?= htmlspecialchars($post['title']) ?>" required>
                    <div class="char-count" id="titleCount"><?= mb_strlen($post['title']) ?>/200</div>
                </div>

                <div class="form-group">
                    <label>Nội dung chi tiết <span class="required">*</span></label>
                    <textarea name="content" id="contentArea" rows="8"><?= htmlspecialchars($post['content']) ?></textarea>
                    <div class="char-count" id="contentCount" style="color:<?= mb_strlen($post['content'])>=50?'#2eb85c':'#e74c3c'?>">
                        <?= mb_strlen($post['content']) ?>/∞ (tối thiểu 50)
                    </div>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label><i class="fas fa-thumbs-up" style="color:#2eb85c;"></i> Điểm tốt (Pros)</label>
                        <textarea name="pros" rows="3"><?= htmlspecialchars($post['pros'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-thumbs-down" style="color:#e74c3c;"></i> Điểm chưa tốt (Cons)</label>
                        <textarea name="cons" rows="3"><?= htmlspecialchars($post['cons'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- ẢNH -->
            <div class="form-section">
                <div class="form-section-title">
                    <span class="form-section-num">3</span> Hình ảnh
                </div>

                <!-- Ảnh bìa -->
                <div class="form-group">
                    <label>Ảnh bìa</label>
                    <?php if ($post['cover_image']): ?>
                        <div style="margin-bottom:12px;display:flex;align-items:flex-start;gap:12px;">
                            <img src="uploads/posts/<?= htmlspecialchars($post['cover_image']) ?>"
                                 id="coverPreview"
                                 style="height:100px;border-radius:8px;object-fit:cover;">
                            <div>
                                <div style="font-size:0.83rem;color:#888;margin-bottom:6px;">Ảnh bìa hiện tại</div>
                                <small style="color:#aaa;">Upload ảnh mới bên dưới để thay thế</small>
                            </div>
                        </div>
                    <?php else: ?>
                        <img id="coverPreview" src="" style="display:none;height:100px;border-radius:8px;margin-bottom:12px;">
                    <?php endif; ?>
                    <input type="file" name="cover_image" id="coverInput" accept="image/*"
                           style="padding:8px;background:var(--cream);border-radius:var(--radius-sm);border:1.5px dashed var(--cream-dark);width:100%;">
                </div>

                <!-- Gallery hiện tại -->
                <?php if ($gallery->num_rows > 0): ?>
                <div class="form-group">
                    <label>Ảnh trong bài (bỏ chọn để xóa)</label>
                    <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:8px;">
                        <?php while ($img = $gallery->fetch_assoc()): ?>
                        <div style="position:relative;display:inline-block;">
                            <img src="uploads/posts/<?= htmlspecialchars($img['image_path']) ?>"
                                 style="width:80px;height:80px;object-fit:cover;border-radius:8px;border:2px solid var(--cream-dark);">
                            <label style="position:absolute;top:-6px;right:-6px;background:var(--rose);color:white;border-radius:50%;width:22px;height:22px;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:0.8rem;" title="Xóa ảnh này">
                                <input type="checkbox" name="delete_images[]" value="<?= $img['id'] ?>" style="display:none;"
                                       onchange="this.parentElement.style.background=this.checked?'#e74c3c':'var(--rose)'">
                                ✕
                            </label>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <small style="color:#888;">Nhấn ✕ đỏ trên ảnh để đánh dấu xóa, sau đó bấm Lưu.</small>
                </div>
                <?php endif; ?>

                <!-- Upload ảnh mới -->
                <div class="form-group">
                    <label>Thêm ảnh mới (tối đa 5)</label>
                    <input type="file" name="gallery[]" multiple accept="image/*"
                           style="padding:8px;background:var(--cream);border-radius:var(--radius-sm);border:1.5px dashed var(--cream-dark);width:100%;">
                    <div id="galleryPreviews" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;"></div>
                </div>
            </div>

            <!-- Submit -->
            <div style="padding:0 40px 32px;display:flex;gap:12px;align-items:center;">
                <button type="submit" class="btn-primary" style="width:auto;padding:13px 32px;">
                    <i class="fas fa-save"></i> Lưu thay đổi
                </button>
                <a href="my_posts.php" style="color:var(--charcoal-mid);font-size:0.9rem;">Hủy</a>
            </div>
        </form>
    </div>
</div>

<!-- Reuse styles từ create_post.php -->
<style>
.create-post-card { background:white; border-radius:var(--radius); box-shadow:var(--shadow); overflow:hidden; }
.create-post-header { color:white; padding:32px 40px; display:flex; align-items:center; gap:20px; }
.create-post-icon { font-size:2.5rem; opacity:0.6; }
.create-post-header h1 { font-family:'Playfair Display',serif; font-size:1.6rem; margin-bottom:4px; }
.create-post-header p { opacity:0.85; font-size:0.9rem; }
.form-section { padding:28px 40px; border-bottom:1px solid var(--cream-dark); }
.form-section:last-of-type { border-bottom:none; }
.form-section-title { display:flex; align-items:center; gap:10px; font-weight:700; font-size:1rem; color:var(--charcoal); margin-bottom:20px; }
.form-section-num { width:28px; height:28px; background:var(--rose); color:white; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.85rem; font-weight:700; flex-shrink:0; }
.form-row-2 { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
.required { color:var(--rose); }
.char-count { font-size:0.78rem; color:#bbb; text-align:right; margin-top:4px; }
.rating-label-text { font-size:0.85rem; color:var(--rose); font-weight:500; margin-top:6px; min-height:20px; }
@media(max-width:640px){
    .form-section{padding:20px;}
    .create-post-header{padding:24px 20px;}
    .form-row-2{grid-template-columns:1fr;}
    div[style*="padding:0 40px 32px"]{padding:0 20px 24px!important;}
}
</style>

<script>
const stars = document.querySelectorAll('.star-rating-input label');
const inputs = document.querySelectorAll('.star-rating-input input');
const labels = ['', 'Rất tệ 😞', 'Không tốt 😕', 'Bình thường 😐', 'Tốt 😊', 'Tuyệt vời! 🌟'];

function updateStars(value) {
    stars.forEach(label => {
        const starVal = parseInt(label.getAttribute('for').replace('star', ''));
        label.style.color = starVal <= value ? 'var(--gold, #f4a621)' : '#ddd';
    });
}

inputs.forEach(input => {
    input.addEventListener('change', function() {
        updateStars(parseInt(this.value));
        document.getElementById('ratingLabel').textContent = labels[this.value];
    });
});

stars.forEach(label => {
    label.addEventListener('mouseenter', function() {
        const val = parseInt(this.getAttribute('for').replace('star', ''));
        updateStars(val);
    });
    label.addEventListener('mouseleave', function() {
        const checked = document.querySelector('.star-rating-input input:checked');
        updateStars(checked ? parseInt(checked.value) : 0);
    });
});

const titleInput = document.querySelector('input[name="title"]');
const contentArea = document.getElementById('contentArea');
if (titleInput) {
    titleInput.addEventListener('input', () => {
        document.getElementById('titleCount').textContent = titleInput.value.length + '/200';
    });
}
if (contentArea) {
    contentArea.addEventListener('input', () => {
        const len = contentArea.value.length;
        const el  = document.getElementById('contentCount');
        el.textContent  = len + '/∞ (tối thiểu 50)';
        el.style.color  = len >= 50 ? '#2eb85c' : '#e74c3c';
    });
}

// Cover preview
document.getElementById('coverInput').addEventListener('change', function() {
    if (this.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            const preview = document.getElementById('coverPreview');
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(this.files[0]);
    }
});

// Gallery preview
document.querySelector('input[name="gallery[]"]').addEventListener('change', function() {
    const container = document.getElementById('galleryPreviews');
    container.innerHTML = '';
    Array.from(this.files).slice(0,5).forEach(file => {
        const reader = new FileReader();
        reader.onload = e => {
            const img = document.createElement('img');
            img.src = e.target.result;
            img.style.cssText = 'width:70px;height:70px;object-fit:cover;border-radius:8px;border:2px solid var(--cream-dark);';
            container.appendChild(img);
        };
        reader.readAsDataURL(file);
    });
});
</script>

<?php include 'includes/footer.php'; ?>