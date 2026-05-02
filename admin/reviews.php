<?php include 'admin_header.php';

// Approve / Reject
if (isset($_GET['approve'])) {
    $r_id = (int)$_GET['approve'];
    $conn->query("UPDATE reviews SET status='approved' WHERE id=$r_id");
    redirect('reviews.php?msg=approved');
}
if (isset($_GET['reject'])) {
    $r_id = (int)$_GET['reject'];
    $conn->query("UPDATE reviews SET status='rejected' WHERE id=$r_id");
    redirect('reviews.php?msg=rejected');
}
if (isset($_GET['delete'])) {
    $r_id = (int)$_GET['delete'];
    $conn->query("DELETE FROM reviews WHERE id=$r_id");
    redirect('reviews.php?msg=deleted');
}

$msg = $_GET['msg'] ?? '';
$filter = $_GET['filter'] ?? 'all';

$where = "WHERE 1=1";
if ($filter === 'pending') $where .= " AND r.status='pending'";
elseif ($filter === 'approved') $where .= " AND r.status='approved'";
elseif ($filter === 'rejected') $where .= " AND r.status='rejected'";

$reviews = $conn->query("SELECT r.*, u.username, p.name as product_name FROM reviews r JOIN users u ON r.user_id=u.id JOIN products p ON r.product_id=p.id $where ORDER BY r.created_at DESC");
?>

<div class="admin-topbar">
    <h1>Quản lý Review</h1>
</div>

<?php if ($msg): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?= ['approved'=>'Đã duyệt review.','rejected'=>'Đã từ chối review.','deleted'=>'Đã xóa review.'][$msg] ?? '' ?>
    </div>
<?php endif; ?>

<!-- Filter tabs -->
<div style="display:flex;gap:8px;margin-bottom:20px;">
    <?php $counts = [
        'all'      => $conn->query("SELECT COUNT(*) as c FROM reviews")->fetch_assoc()['c'],
        'pending'  => $conn->query("SELECT COUNT(*) as c FROM reviews WHERE status='pending'")->fetch_assoc()['c'],
        'approved' => $conn->query("SELECT COUNT(*) as c FROM reviews WHERE status='approved'")->fetch_assoc()['c'],
        'rejected' => $conn->query("SELECT COUNT(*) as c FROM reviews WHERE status='rejected'")->fetch_assoc()['c'],
    ];
    $tabs = ['all'=>'Tất cả','pending'=>'Chờ duyệt','approved'=>'Đã duyệt','rejected'=>'Từ chối'];
    foreach ($tabs as $key => $label): ?>
        <a href="reviews.php?filter=<?= $key ?>"
           class="filter-btn <?= $filter===$key?'active':'' ?>">
            <?= $label ?> (<?= $counts[$key] ?>)
        </a>
    <?php endforeach; ?>
</div>

<div class="admin-card">
    <div class="admin-card-header"><h3>Review (<?= $reviews->num_rows ?>)</h3></div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Người dùng</th>
                <th>Sản phẩm</th>
                <th>Tiêu đề</th>
                <th>Sao</th>
                <th>Trạng thái</th>
                <th>Ngày</th>
                <th>Hành động</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($rv = $reviews->fetch_assoc()): ?>
        <tr>
            <td><strong><?= htmlspecialchars($rv['username']) ?></strong></td>
            <td><?= htmlspecialchars(mb_strimwidth($rv['product_name'],0,35,'...')) ?></td>
            <td>
                <div><?= htmlspecialchars(mb_strimwidth($rv['title']??'',0,40,'...')) ?></div>
                <div style="font-size:0.78rem;color:#999;"><?= htmlspecialchars(mb_strimwidth($rv['content'],0,60,'...')) ?></div>
            </td>
            <td style="color:var(--gold);">
                <?php for ($i=1;$i<=5;$i++): ?>
                    <span style="color:<?=$i<=$rv['rating']?'var(--gold)':'#ddd'?>;">★</span>
                <?php endfor; ?>
            </td>
            <td>
                <span class="badge badge-<?= $rv['status']==='approved'?'approved':($rv['status']==='pending'?'pending':'rejected') ?>">
                    <?= ['approved'=>'Đã duyệt','pending'=>'Chờ duyệt','rejected'=>'Từ chối'][$rv['status']] ?>
                </span>
            </td>
            <td><?= date('d/m/Y', strtotime($rv['created_at'])) ?></td>
            <td style="white-space:nowrap;">
                <?php if ($rv['status'] === 'pending'): ?>
                    <a href="reviews.php?approve=<?= $rv['id'] ?>&filter=<?= $filter ?>" class="btn-sm approve">✓ Duyệt</a>
                    <a href="reviews.php?reject=<?= $rv['id'] ?>&filter=<?= $filter ?>" class="btn-sm reject">✕ Từ chối</a>
                <?php elseif ($rv['status'] === 'rejected'): ?>
                    <a href="reviews.php?approve=<?= $rv['id'] ?>&filter=<?= $filter ?>" class="btn-sm approve">↩ Duyệt lại</a>
                <?php endif; ?>
                <a href="reviews.php?delete=<?= $rv['id'] ?>&filter=<?= $filter ?>" class="btn-sm delete" data-confirm="Xóa review này?">🗑</a>
            </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

</main></div>
<script src="../assets/js/main.js"></script>
</body></html>