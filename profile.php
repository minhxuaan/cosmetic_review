<?php
require_once 'config/db.php';
if (!isLoggedIn()) redirect('login.php');

$uid = $_SESSION['user_id'];
$user = $conn->query("SELECT * FROM users WHERE id=$uid")->fetch_assoc();

$error = '';
$success = '';

// Đổi mật khẩu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $old = $_POST['old_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_new'] ?? '';

    if (!password_verify($old, $user['password'])) {
        $error = 'Mật khẩu cũ không đúng.';
    } elseif (strlen($new) < 6) {
        $error = 'Mật khẩu mới phải có ít nhất 6 ký tự.';
    } elseif ($new !== $confirm) {
        $error = 'Mật khẩu xác nhận không khớp.';
    } else {
        $hashed = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->bind_param('si', $hashed, $uid);
        $stmt->execute();
        $success = 'Đổi mật khẩu thành công!';
    }
}

// Upload avatar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_avatar']) && !empty($_FILES['avatar']['name'])) {
    $upload_dir = 'uploads/avatars/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
    $allowed = ['jpg','jpeg','png','webp'];
    if (in_array(strtolower($ext), $allowed) && $_FILES['avatar']['size'] < 2*1024*1024) {
        $filename = 'user_' . $uid . '_' . uniqid() . '.' . $ext;
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_dir . $filename)) {
            $conn->query("UPDATE users SET avatar='$filename' WHERE id=$uid");
            $_SESSION['avatar'] = $filename;
            $success = 'Cập nhật ảnh đại diện thành công!';
            redirect('profile.php?success=1');
        }
    }
}

// Lấy reviews của user
$my_reviews = $conn->query("SELECT r.*, p.name as product_name FROM reviews r JOIN products p ON r.product_id=p.id WHERE r.user_id=$uid ORDER BY r.created_at DESC");
?>
<?php include 'includes/header.php'; ?>

<div class="main-container" style="max-width:900px;">
    <h1 class="section-title">Hồ sơ của tôi</h1>

    <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
    <?php endif; ?>
    <?php if ($success || isset($_GET['success'])): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success ?: 'Cập nhật thành công!' ?></div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 2fr;gap:24px;align-items:start;">
        <!-- Profile Card -->
        <div style="background:white;border-radius:var(--radius);padding:32px;box-shadow:var(--shadow);text-align:center;">
            <?php
            $avatar = $user['avatar'] ?? '';
            $avatarSrc = !empty($avatar)
                ? 'uploads/avatars/' . htmlspecialchars($avatar)
                : 'https://ui-avatars.com/api/?name=' . urlencode($user['username']) . '&background=c8896a&color=fff&size=100';
            ?>
            <img src="<?= $avatarSrc ?>"
                 onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?= urlencode($user['username']) ?>&background=c8896a&color=fff&size=100';"
                 style="width:100px;height:100px;border-radius:50%;object-fit:cover;border:3px solid var(--gold-light);margin:0 auto 16px;">

            <h2 style="font-family:'Playfair Display',serif;margin-bottom:4px;"><?= htmlspecialchars($user['username']) ?></h2>
            <p style="color:#888;font-size:0.9rem;margin-bottom:8px;"><?= htmlspecialchars($user['email']) ?></p>
            <span class="badge badge-<?= $user['role']==='admin'?'admin':'user' ?>" style="margin-bottom:20px;display:inline-block;">
                <?= $user['role']==='admin' ? '👑 Admin' : '👤 Thành viên' ?>
            </span>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-group" style="text-align:left;">
                    <label style="font-size:0.85rem;">Đổi ảnh đại diện</label>
                    <input type="file" name="avatar" accept="image/*" style="padding:6px;font-size:0.85rem;">
                </div>
                <button type="submit" name="update_avatar" class="btn-primary" style="font-size:0.88rem;padding:10px;">
                    Cập nhật ảnh
                </button>
            </form>
        </div>

        <div>
            <!-- Đổi mật khẩu -->
            <div style="background:white;border-radius:var(--radius);padding:28px;box-shadow:var(--shadow);margin-bottom:24px;">
                <h3 style="font-family:'Playfair Display',serif;margin-bottom:20px;">Đổi mật khẩu</h3>
                <form method="POST">
                    <div class="form-group">
                        <label>Mật khẩu hiện tại</label>
                        <input type="password" name="old_password" required>
                    </div>
                    <div class="form-group">
                        <label>Mật khẩu mới</label>
                        <input type="password" name="new_password" required>
                    </div>
                    <div class="form-group">
                        <label>Xác nhận mật khẩu mới</label>
                        <input type="password" name="confirm_new" required>
                    </div>
                    <button type="submit" name="change_password" class="btn-primary" style="width:auto;padding:10px 24px;">
                        Đổi mật khẩu
                    </button>
                </form>
            </div>

            <!-- My Reviews -->
            <div style="background:white;border-radius:var(--radius);padding:28px;box-shadow:var(--shadow);">
                <h3 style="font-family:'Playfair Display',serif;margin-bottom:16px;">Review của tôi (<?= $my_reviews->num_rows ?>)</h3>
                <?php if ($my_reviews->num_rows > 0):
                    while ($rv = $my_reviews->fetch_assoc()):
                ?>
                <div style="padding:12px 0;border-bottom:1px solid var(--cream-dark);">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                        <div>
                            <a href="product.php?id=<?= $rv['product_id'] ?>" style="font-weight:600;color:var(--rose);">
                                <?= htmlspecialchars($rv['product_name']) ?>
                            </a>
                            <div style="font-size:0.83rem;color:#888;"><?= date('d/m/Y', strtotime($rv['created_at'])) ?></div>
                            <div style="color:var(--gold);font-size:0.9rem;margin-top:2px;">
                                <?php for ($i=1;$i<=5;$i++): ?>
                                    <span style="color:<?=$i<=$rv['rating']?'var(--gold)':'#ddd'?>;">★</span>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <span class="badge badge-<?= $rv['status']==='approved'?'approved':($rv['status']==='pending'?'pending':'rejected') ?>">
                            <?= ['approved'=>'Đã duyệt','pending'=>'Chờ duyệt','rejected'=>'Từ chối'][$rv['status']] ?>
                        </span>
                    </div>
                    <?php if ($rv['title']): ?>
                        <div style="font-style:italic;font-size:0.88rem;margin-top:4px;color:var(--charcoal-mid);">"<?= htmlspecialchars($rv['title']) ?>"</div>
                    <?php endif; ?>
                </div>
                <?php endwhile;
                else: ?>
                    <p style="color:#aaa;">Bạn chưa viết review nào.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>