<?php include 'admin_header.php';

// Toggle role
if (isset($_GET['toggle_role'])) {
    $u_id = (int)$_GET['toggle_role'];
    if ($u_id != $_SESSION['user_id']) {
        $u = $conn->query("SELECT role FROM users WHERE id=$u_id")->fetch_assoc();
        $new_role = $u['role'] === 'admin' ? 'user' : 'admin';
        $conn->query("UPDATE users SET role='$new_role' WHERE id=$u_id");
    }
    redirect('users.php');
}

// Delete user
if (isset($_GET['delete'])) {
    $u_id = (int)$_GET['delete'];
    if ($u_id != $_SESSION['user_id']) {
        $conn->query("DELETE FROM users WHERE id=$u_id");
    }
    redirect('users.php?msg=deleted');
}

$msg = $_GET['msg'] ?? '';

$users = $conn->query("SELECT u.*, 
    (SELECT COUNT(*) FROM reviews WHERE user_id=u.id) as review_count,
    (SELECT COUNT(*) FROM comments WHERE user_id=u.id) as comment_count
    FROM users u ORDER BY u.created_at DESC");
?>

<div class="admin-topbar">
    <h1>Quản lý người dùng</h1>
</div>

<?php if ($msg === 'deleted'): ?>
    <div class="alert alert-success"><i class="fas fa-check"></i> Đã xóa người dùng.</div>
<?php endif; ?>

<div class="admin-card">
    <div class="admin-card-header"><h3>Danh sách người dùng (<?= $users->num_rows ?>)</h3></div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Tên đăng nhập</th>
                <th>Email</th>
                <th>Vai trò</th>
                <th>Số review</th>
                <th>Bình luận</th>
                <th>Ngày đăng ký</th>
                <th>Hành động</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($u = $users->fetch_assoc()):
            $avatar = $u['avatar'] ?? '';
            $avatarSrc = !empty($avatar)
                ? '../uploads/avatars/' . htmlspecialchars($avatar)
                : 'https://ui-avatars.com/api/?name=' . urlencode($u['username']) . '&background=c8896a&color=fff&size=40';
        ?>
        <tr>
            <td>
                <div style="display:flex;align-items:center;gap:10px;">
                    <img src="<?= $avatarSrc ?>"
                         onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?= urlencode($u['username']) ?>&background=c8896a&color=fff&size=40';"
                         style="width:36px;height:36px;border-radius:50%;object-fit:cover;">
                    <strong><?= htmlspecialchars($u['username']) ?></strong>
                    <?php if ($u['id'] == $_SESSION['user_id']): ?>
                        <span class="badge badge-approved">Bạn</span>
                    <?php endif; ?>
                </div>
            </td>
            <td><?= htmlspecialchars($u['email']) ?></td>
            <td>
                <span class="badge badge-<?= $u['role']==='admin'?'admin':'user' ?>">
                    <?= $u['role']==='admin' ? '👑 Admin' : '👤 User' ?>
                </span>
            </td>
            <td><?= $u['review_count'] ?></td>
            <td><?= $u['comment_count'] ?></td>
            <td><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
            <td style="white-space:nowrap;">
                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                    <a href="users.php?toggle_role=<?= $u['id'] ?>" class="btn-sm edit">
                        <?= $u['role']==='admin' ? '→ User' : '→ Admin' ?>
                    </a>
                    <a href="users.php?delete=<?= $u['id'] ?>" class="btn-sm delete"
                       data-confirm="Xóa người dùng <?= htmlspecialchars($u['username']) ?>?">🗑</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

</main></div>
<script src="../assets/js/main.js"></script>
</body></html>