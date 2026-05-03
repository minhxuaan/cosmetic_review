<?php include 'admin_header.php';

// Approve
if (isset($_GET['approve'])) {
    $p_id = (int)$_GET['approve'];
    $conn->query("UPDATE posts SET status='approved' WHERE id=$p_id");
    redirect('posts_manage.php?msg=approved&filter=' . ($_GET['filter'] ?? 'pending'));
}

// Reject with reason
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_id'])) {
    $p_id = (int)$_POST['reject_id'];
    $reason = sanitize($_POST['reject_reason'] ?? 'Không phù hợp với tiêu chuẩn cộng đồng.');
    $conn->query("UPDATE posts SET status='rejected', reject_reason='$reason' WHERE id=$p_id");
    redirect('posts_manage.php?msg=rejected&filter=' . ($_POST['filter'] ?? 'pending'));
}

// Delete
if (isset($_GET['delete'])) {
    $p_id = (int)$_GET['delete'];
    $conn->query("DELETE FROM posts WHERE id=$p_id");
    redirect('posts_manage.php?msg=deleted');
}

$msg = $_GET['msg'] ?? '';
$filter = $_GET['filter'] ?? 'pending';

$where = "WHERE 1=1";
if ($filter !== 'all') $where .= " AND p.status='$filter'";

$posts = $conn->query("
    SELECT p.*, u.username, u.avatar, c.name as category_name,
           (SELECT COUNT(*) FROM post_likes WHERE post_id=p.id) as like_count,
           (SELECT COUNT(*) FROM post_comments WHERE post_id=p.id) as comment_count
    FROM posts p
    JOIN users u ON p.user_id=u.id
    LEFT JOIN categories c ON p.category_id=c.id
    $where
    ORDER BY p.created_at DESC
");

$counts = [
    'all'      => $conn->query("SELECT COUNT(*) as c FROM posts")->fetch_assoc()['c'],
    'pending'  => $conn->query("SELECT COUNT(*) as c FROM posts WHERE status='pending'")->fetch_assoc()['c'],
    'approved' => $conn->query("SELECT COUNT(*) as c FROM posts WHERE status='approved'")->fetch_assoc()['c'],
    'rejected' => $conn->query("SELECT COUNT(*) as c FROM posts WHERE status='rejected'")->fetch_assoc()['c'],
];
?>

<div class="admin-topbar">
    <h1>Duyệt bài Review của User</h1>
</div>

<?php if ($msg): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?= ['approved'=>'Đã duyệt bài viết.','rejected'=>'Đã từ chối bài viết.','deleted'=>'Đã xóa bài viết.'][$msg] ?? '' ?>
    </div>
<?php endif; ?>

<!-- Tabs -->
<div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;">
    <?php $tabs = ['all'=>'Tất cả','pending'=>'⏳ Chờ duyệt','approved'=>'✓ Đã duyệt','rejected'=>'✕ Từ chối'];
    foreach ($tabs as $key => $label): ?>
        <a href="posts_manage.php?filter=<?= $key ?>"
           class="filter-btn <?= $filter===$key?'active':'' ?>">
            <?= $label ?> (<?= $counts[$key] ?>)
        </a>
    <?php endforeach; ?>
</div>

<!-- Posts list -->
<?php if ($posts->num_rows > 0): ?>
    <?php while ($p = $posts->fetch_assoc()): ?>
    <div class="admin-card" style="margin-bottom:16px;">
        <div style="display:flex;gap:0;overflow:hidden;border-radius:var(--radius);">
            <!-- Thumb -->
            <div style="width:160px;flex-shrink:0;background:var(--cream);overflow:hidden;">
                <?php if ($p['cover_image']): ?>
                    <img src="../uploads/posts/<?= htmlspecialchars($p['cover_image']) ?>"
                         style="width:100%;height:100%;object-fit:cover;min-height:120px;">
                <?php else: ?>
                    <div style="height:100%;min-height:120px;display:flex;align-items:center;justify-content:center;font-size:2rem;color:var(--rose-light);">✦</div>
                <?php endif; ?>
            </div>

            <!-- Content -->
            <div style="flex:1;padding:20px 24px;">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;">
                    <div style="flex:1;">
                        <!-- Author -->
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                            <img src="../uploads/avatars/<?= htmlspecialchars($p['avatar']??'default.png') ?>"
                                 onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($p['username']) ?>&background=c8896a&color=fff&size=28'"
                                 style="width:28px;height:28px;border-radius:50%;object-fit:cover;">
                            <strong style="font-size:0.88rem;"><?= htmlspecialchars($p['username']) ?></strong>
                            <span style="color:#aaa;font-size:0.78rem;"><?= date('d/m/Y H:i', strtotime($p['created_at'])) ?></span>
                            <?php if ($p['category_name']): ?>
                                <span class="badge badge-user"><?= htmlspecialchars($p['category_name']) ?></span>
                            <?php endif; ?>
                        </div>

                        <!-- Product & Title -->
                        <div style="font-size:0.82rem;color:var(--rose);font-weight:600;margin-bottom:4px;">
                            <?= htmlspecialchars($p['product_name']) ?>
                            <?php if ($p['brand']): ?> · <?= htmlspecialchars($p['brand']) ?><?php endif; ?>
                        </div>
                        <h3 style="font-family:'Playfair Display',serif;font-size:1.05rem;margin-bottom:8px;line-height:1.3;">
                            <?= htmlspecialchars($p['title']) ?>
                        </h3>

                        <!-- Rating -->
                        <div style="display:flex;align-items:center;gap:4px;margin-bottom:8px;">
                            <?php for ($i=1;$i<=5;$i++): ?>
                                <span style="color:<?=$i<=$p['rating']?'var(--gold)':'#ddd'?>;">★</span>
                            <?php endfor; ?>
                            <span style="font-size:0.82rem;color:#888;margin-left:4px;"><?= $p['rating'] ?>/5</span>
                        </div>

                        <!-- Excerpt -->
                        <p style="font-size:0.88rem;color:var(--charcoal-mid);line-height:1.6;">
                            <?= htmlspecialchars(mb_strimwidth($p['content'],0,200,'...')) ?>
                        </p>

                        <?php if ($p['reject_reason']): ?>
                            <div style="font-size:0.82rem;color:#842029;background:#fdf3f2;padding:6px 10px;border-radius:6px;margin-top:8px;">
                                Lý do từ chối: <?= htmlspecialchars($p['reject_reason']) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Status badge -->
                    <div>
                        <span class="badge badge-<?= $p['status']==='approved'?'approved':($p['status']==='pending'?'pending':'rejected') ?>">
                            <?= ['approved'=>'Đã duyệt','pending'=>'Chờ duyệt','rejected'=>'Từ chối'][$p['status']] ?>
                        </span>
                    </div>
                </div>

                <!-- Actions -->
                <div style="display:flex;align-items:center;gap:10px;margin-top:16px;padding-top:12px;border-top:1px solid var(--cream-dark);flex-wrap:wrap;">
                    <a href="../post_detail.php?id=<?= $p['id'] ?>" target="_blank" class="btn-sm edit">
                        <i class="fas fa-eye"></i> Xem bài
                    </a>

                    <?php if ($p['status'] === 'pending' || $p['status'] === 'rejected'): ?>
                        <a href="posts_manage.php?approve=<?= $p['id'] ?>&filter=<?= $filter ?>" class="btn-sm approve">
                            <i class="fas fa-check"></i> Duyệt
                        </a>
                    <?php endif; ?>

                    <?php if ($p['status'] === 'pending' || $p['status'] === 'approved'): ?>
                        <!-- Reject form (inline) -->
                        <button onclick="toggleRejectForm(<?= $p['id'] ?>)" class="btn-sm reject">
                            <i class="fas fa-times"></i> Từ chối
                        </button>
                    <?php endif; ?>

                    <a href="posts_manage.php?delete=<?= $p['id'] ?>&filter=<?= $filter ?>"
                       class="btn-sm delete" data-confirm="Xóa bài viết này?">
                        <i class="fas fa-trash"></i> Xóa
                    </a>
                </div>

                <!-- Reject reason form -->
                <div id="rejectForm<?= $p['id'] ?>" style="display:none;margin-top:12px;">
                    <form method="POST" style="display:flex;gap:8px;align-items:flex-end;">
                        <input type="hidden" name="reject_id" value="<?= $p['id'] ?>">
                        <input type="hidden" name="filter" value="<?= $filter ?>">
                        <div style="flex:1;">
                            <label style="font-size:0.8rem;font-weight:600;display:block;margin-bottom:4px;">Lý do từ chối:</label>
                            <input type="text" name="reject_reason"
                                   value="Không phù hợp với tiêu chuẩn cộng đồng."
                                   style="width:100%;padding:8px 12px;border:1.5px solid var(--cream-dark);border-radius:var(--radius-sm);font-family:'DM Sans',sans-serif;font-size:0.88rem;">
                        </div>
                        <button type="submit" class="btn-sm reject" style="white-space:nowrap;">Xác nhận từ chối</button>
                        <button type="button" onclick="toggleRejectForm(<?= $p['id'] ?>)" class="btn-sm edit">Hủy</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endwhile; ?>
<?php else: ?>
    <div class="empty-state" style="padding:60px 0;">
        <div class="empty-state-icon">📭</div>
        <h3>Không có bài nào trong mục này</h3>
    </div>
<?php endif; ?>

<script>
function toggleRejectForm(id) {
    const form = document.getElementById('rejectForm' + id);
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}
</script>

</main></div>
<script src="../assets/js/main.js"></script>
</body></html>