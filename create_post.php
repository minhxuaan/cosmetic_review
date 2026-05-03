<?php
require_once 'config/db.php';
if (!isLoggedIn()) redirect('login.php');

$uid = $_SESSION['user_id'];
$categories = $conn->query("SELECT * FROM categories ORDER BY name");
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_name = sanitize($_POST['product_name'] ?? '');
    $brand        = sanitize($_POST['brand'] ?? '');
    $category_id  = (int)($_POST['category_id'] ?? 0);
    $rating       = (int)($_POST['rating'] ?? 0);
    $title        = sanitize($_POST['title'] ?? '');
    $content      = sanitize($_POST['content'] ?? '');
    $pros         = sanitize($_POST['pros'] ?? '');
    $cons         = sanitize($_POST['cons'] ?? '');

    // Validate
    if (empty($product_name))       $error = 'Vui lòng nhập tên sản phẩm.';
    elseif ($rating < 1 || $rating > 5) $error = 'Vui lòng chọn số sao đánh giá.';
    elseif (empty($title))          $error = 'Vui lòng nhập tiêu đề bài review.';
    elseif (mb_strlen($content) < 50) $error = 'Nội dung review cần ít nhất 50 ký tự.';
    else {
        // Upload ảnh bìa
        $cover_image = '';
        if (!empty($_FILES['cover_image']['name'])) {
            $upload_dir = 'uploads/posts/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $ext = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp']) && $_FILES['cover_image']['size'] < 5*1024*1024) {
                $cover_image = 'cover_' . uniqid() . '.' . $ext;
                move_uploaded_file($_FILES['cover_image']['tmp_name'], $upload_dir . $cover_image);
            }
        }

        $cat_val = $category_id ?: 'NULL';
        $stmt = $conn->prepare("INSERT INTO posts (user_id, product_name, brand, category_id, rating, title, content, pros, cons, cover_image, status) VALUES (?,?,?,?,?,?,?,?,?,?,'pending')");
        $stmt->bind_param('issiiissss', $uid, $product_name, $brand, $category_id, $rating, $title, $content, $pros, $cons, $cover_image);

        if ($stmt->execute()) {
            $post_id = $conn->insert_id;

            // Upload ảnh phụ
            if (!empty($_FILES['gallery']['name'][0])) {
                $upload_dir = 'uploads/posts/';
                foreach ($_FILES['gallery']['tmp_name'] as $k => $tmp) {
                    if ($_FILES['gallery']['error'][$k] === 0) {
                        $ext = strtolower(pathinfo($_FILES['gallery']['name'][$k], PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg','jpeg','png','webp']) && $_FILES['gallery']['size'][$k] < 5*1024*1024) {
                            $fname = 'gallery_' . uniqid() . '.' . $ext;
                            if (move_uploaded_file($tmp, $upload_dir . $fname)) {
                                $conn->query("INSERT INTO post_images (post_id, image_path) VALUES ($post_id, '$fname')");
                            }
                        }
                    }
                }
            }
            redirect('my_posts.php?created=1');
        } else {
            $error = 'Có lỗi xảy ra, vui lòng thử lại.';
        }
    }
}
?>
<?php include 'includes/header.php'; ?>

<div class="main-container" style="max-width:780px;">
    <div style="margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;">
        <a href="posts.php" style="color:var(--rose);font-size:0.9rem;">
            <i class="fas fa-arrow-left"></i> Về trang bài review
        </a>
        <a href="my_posts.php" style="color:var(--charcoal-mid);font-size:0.9rem;">
            <i class="fas fa-list"></i> Bài của tôi
        </a>
    </div>

    <div class="create-post-card">
        <div class="create-post-header">
            <span class="create-post-icon">✦</span>
            <div>
                <h1>Đăng bài review</h1>
                <p>Chia sẻ trải nghiệm sản phẩm của bạn với cộng đồng</p>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
        <?php endif; ?>

        <div class="alert alert-warning" style="margin-bottom:24px;">
            <i class="fas fa-clock"></i>
            Bài review sẽ được <strong>admin duyệt</strong> trước khi hiển thị công khai. Thường trong vòng 24 giờ.
        </div>

        <form method="POST" enctype="multipart/form-data" id="createPostForm">

            <!-- ===== THÔNG TIN SẢN PHẨM ===== -->
            <div class="form-section">
                <div class="form-section-title">
                    <span class="form-section-num">1</span> Thông tin sản phẩm
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label>Tên sản phẩm <span class="required">*</span></label>
                        <input type="text" name="product_name"
                               placeholder="VD: Laneige Water Sleeping Mask"
                               value="<?= htmlspecialchars($_POST['product_name'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Thương hiệu</label>
                        <input type="text" name="brand"
                               placeholder="VD: Laneige, The Ordinary..."
                               value="<?= htmlspecialchars($_POST['brand'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label>Danh mục</label>
                        <select name="category_id">
                            <option value="">-- Chọn danh mục --</option>
                            <?php $categories->data_seek(0); while ($c = $categories->fetch_assoc()): ?>
                                <option value="<?= $c['id'] ?>" <?= ($_POST['category_id']??'')==$c['id']?'selected':'' ?>>
                                    <?= htmlspecialchars($c['name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Đánh giá của bạn <span class="required">*</span></label>
                        <div class="star-rating-input">
                            <?php for ($i=5;$i>=1;$i--): ?>
                                <input type="radio" id="star<?=$i?>" name="rating" value="<?=$i?>" <?= ($_POST['rating']??'')==$i?'checked':'' ?>>
                                <label for="star<?=$i?>" title="<?=$i?> sao">★</label>
                            <?php endfor; ?>
                        </div>
                        <div id="ratingLabel" class="rating-label-text">Chọn số sao...</div>
                    </div>
                </div>
            </div>

            <!-- ===== NỘI DUNG BÀI REVIEW ===== -->
            <div class="form-section">
                <div class="form-section-title">
                    <span class="form-section-num">2</span> Nội dung bài review
                </div>

                <div class="form-group">
                    <label>Tiêu đề bài review <span class="required">*</span></label>
                    <input type="text" name="title"
                           placeholder="VD: Mặt nạ ngủ Laneige - Xứng đáng với giá tiền!"
                           maxlength="200"
                           value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required>
                    <div class="char-count" id="titleCount">0/200</div>
                </div>

                <div class="form-group">
                    <label>Nội dung chi tiết <span class="required">*</span> <small style="color:#aaa;font-weight:400;">(tối thiểu 50 ký tự)</small></label>
                    <textarea name="content" id="contentArea" rows="8"
                              placeholder="Chia sẻ trải nghiệm thực tế của bạn: kết cấu, mùi hương, hiệu quả, thời gian thấy kết quả, phù hợp loại da nào..."><?= htmlspecialchars($_POST['content'] ?? '') ?></textarea>
                    <div class="char-count" id="contentCount">0/∞ (tối thiểu 50)</div>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label><i class="fas fa-thumbs-up" style="color:#2eb85c;"></i> Điểm tốt (Pros)</label>
                        <textarea name="pros" rows="3"
                                  placeholder="Mỗi điểm một dòng&#10;VD: Dưỡng ẩm tốt&#10;Mùi thơm dễ chịu"><?= htmlspecialchars($_POST['pros'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-thumbs-down" style="color:#e74c3c;"></i> Điểm chưa tốt (Cons)</label>
                        <textarea name="cons" rows="3"
                                  placeholder="Mỗi điểm một dòng&#10;VD: Giá hơi cao&#10;Không kèm thìa múc"><?= htmlspecialchars($_POST['cons'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- ===== ẢNH ===== -->
            <div class="form-section">
                <div class="form-section-title">
                    <span class="form-section-num">3</span> Hình ảnh
                </div>

                <div class="form-group">
                    <label>Ảnh bìa bài review</label>
                    <div class="upload-zone" id="coverUploadZone">
                        <input type="file" name="cover_image" id="coverInput" accept="image/*" style="display:none;">
                        <div class="upload-placeholder" id="coverPlaceholder">
                            <i class="fas fa-camera"></i>
                            <span>Kéo thả hoặc <u style="cursor:pointer;" onclick="event.stopPropagation();document.getElementById('coverInput').click()">chọn ảnh bìa</u></span>
                            <small>JPG, PNG, WEBP — tối đa 5MB</small>
                        </div>
                        <img id="coverPreview" src="" alt="" style="display:none;max-height:200px;border-radius:8px;margin-top:10px;">
                    </div>
                </div>

                <div class="form-group">
                    <label>Ảnh sản phẩm thực tế (tối đa 5 ảnh)</label>
                    <input type="file" name="gallery[]" multiple accept="image/*"
                           style="padding:10px;background:var(--cream);border-radius:var(--radius-sm);border:1.5px dashed var(--cream-dark);width:100%;">
                    <small style="color:#888;">Ảnh thực tế giúp bài review đáng tin cậy hơn.</small>
                    <div id="galleryPreviews" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;"></div>
                </div>
            </div>

            <!-- Submit -->
            <div style="display:flex;gap:12px;align-items:center;margin-top:8px;">
                <button type="submit" class="btn-primary" style="width:auto;padding:14px 36px;">
                    <i class="fas fa-paper-plane"></i> Gửi bài review
                </button>
                <a href="posts.php" style="color:var(--charcoal-mid);font-size:0.9rem;">Hủy</a>
            </div>
        </form>
    </div>
</div>

<style>
.create-post-card {
    background: white;
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    overflow: hidden;
}

.create-post-header {
    background: linear-gradient(135deg, var(--rose), var(--rose-dark));
    color: white;
    padding: 32px 40px;
    display: flex;
    align-items: center;
    gap: 20px;
}

.create-post-icon {
    font-size: 2.5rem;
    opacity: 0.6;
}

.create-post-header h1 {
    font-family: 'Playfair Display', serif;
    font-size: 1.6rem;
    margin-bottom: 4px;
}

.create-post-header p {
    opacity: 0.85;
    font-size: 0.9rem;
}

.form-section {
    padding: 28px 40px;
    border-bottom: 1px solid var(--cream-dark);
}

.form-section:last-of-type { border-bottom: none; }

form > div:last-child { padding: 0 40px 32px; }

.form-section-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 700;
    font-size: 1rem;
    color: var(--charcoal);
    margin-bottom: 20px;
}

.form-section-num {
    width: 28px;
    height: 28px;
    background: var(--rose);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    font-weight: 700;
    flex-shrink: 0;
}

.form-row-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.required { color: var(--rose); }

.char-count {
    font-size: 0.78rem;
    color: #bbb;
    text-align: right;
    margin-top: 4px;
}

.upload-zone {
    border: 2px dashed var(--cream-dark);
    border-radius: var(--radius-sm);
    padding: 24px;
    text-align: center;
    transition: var(--transition);
    cursor: pointer;
}

.upload-zone:hover, .upload-zone.dragover {
    border-color: var(--rose);
    background: rgba(200,137,106,0.04);
}

.upload-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    color: var(--charcoal-mid);
}

.upload-placeholder i { font-size: 2rem; color: var(--rose-light); }
.upload-placeholder small { font-size: 0.78rem; color: #aaa; }

.rating-label-text {
    font-size: 0.85rem;
    color: var(--rose);
    font-weight: 500;
    margin-top: 6px;
    min-height: 20px;
}

@media (max-width: 640px) {
    .form-section { padding: 20px 20px; }
    form > div:last-child { padding: 0 20px 24px; }
    .create-post-header { padding: 24px 20px; }
    .form-row-2 { grid-template-columns: 1fr; }
}
</style>

<script>
// Star rating labels
const ratingLabels = ['', 'Rất tệ 😞', 'Không tốt 😕', 'Bình thường 😐', 'Tốt 😊', 'Xuất sắc! 🌟'];
document.querySelectorAll('.star-rating-input input').forEach(input => {
    input.addEventListener('change', function() {
        document.getElementById('ratingLabel').textContent = ratingLabels[this.value];
    });
});

// Char count
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
        const el = document.getElementById('contentCount');
        el.textContent = len + '/∞ (tối thiểu 50)';
        el.style.color = len >= 50 ? '#2eb85c' : '#e74c3c';
    });
}

// Cover image preview
const coverInput = document.getElementById('coverInput');
const coverPreview = document.getElementById('coverPreview');
const coverPlaceholder = document.getElementById('coverPlaceholder');
const uploadZone = document.getElementById('coverUploadZone');

uploadZone.addEventListener('click', () => coverInput.click());

uploadZone.addEventListener('dragover', e => { e.preventDefault(); uploadZone.classList.add('dragover'); });
uploadZone.addEventListener('dragleave', () => uploadZone.classList.remove('dragover'));
uploadZone.addEventListener('drop', e => {
    e.preventDefault();
    uploadZone.classList.remove('dragover');
    if (e.dataTransfer.files[0]) {
        coverInput.files = e.dataTransfer.files;
        showCoverPreview(e.dataTransfer.files[0]);
    }
});

coverInput.addEventListener('change', function() {
    if (this.files[0]) showCoverPreview(this.files[0]);
});

function showCoverPreview(file) {
    const reader = new FileReader();
    reader.onload = e => {
        coverPreview.src = e.target.result;
        coverPreview.style.display = 'block';
        coverPlaceholder.style.display = 'none';
    };
    reader.readAsDataURL(file);
}

// Gallery previews
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