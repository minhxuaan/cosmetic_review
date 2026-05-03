<?php
require_once __DIR__ . '/../config/db.php';
if (!isLoggedIn() || !isAdmin()) redirect('../login.php');
$base_path = '../';
$current_admin = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin – GlowReview</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="admin-wrapper">

<aside class="admin-sidebar">
    <div class="admin-logo">
        <i class="fas fa-crown" style="color:var(--gold)"></i>
        GlowReview Admin
    </div>
    <nav class="admin-nav">
        <div class="admin-nav-section">Tổng quan</div>
        <a href="index.php" class="<?= $current_admin==='index.php'?'active':'' ?>">
            <i class="fas fa-chart-line"></i> Dashboard
        </a>

        <div class="admin-nav-section">Quản lý</div>
        <a href="products.php" class="<?= $current_admin==='products.php'?'active':'' ?>">
            <i class="fas fa-box"></i> Sản phẩm
        </a>
        <a href="reviews.php" class="<?= $current_admin==='reviews.php'?'active':'' ?>">
            <i class="fas fa-star"></i> Review sản phẩm
            <?php
            $pending = $conn->query("SELECT COUNT(*) as c FROM reviews WHERE status='pending'")->fetch_assoc()['c'];
            if ($pending > 0): ?>
                <span style="background:var(--rose);color:white;border-radius:50px;padding:1px 7px;font-size:0.72rem;margin-left:auto;"><?= $pending ?></span>
            <?php endif; ?>
        </a>
        <a href="posts_manage.php" class="<?= $current_admin==='posts_manage.php'?'active':'' ?>">
            <i class="fas fa-newspaper"></i> Bài post user
            <?php
            $pending_posts = $conn->query("SELECT COUNT(*) as c FROM posts WHERE status='pending'")->fetch_assoc()['c'];
            if ($pending_posts > 0): ?>
                <span style="background:var(--gold);color:white;border-radius:50px;padding:1px 7px;font-size:0.72rem;margin-left:auto;"><?= $pending_posts ?></span>
            <?php endif; ?>
        </a>
        <a href="users.php" class="<?= $current_admin==='users.php'?'active':'' ?>">
            <i class="fas fa-users"></i> Người dùng
        </a>
        <a href="categories.php" class="<?= $current_admin==='categories.php'?'active':'' ?>">
            <i class="fas fa-tags"></i> Danh mục
        </a>

        <div class="admin-nav-section">Khác</div>
        <a href="../index.php"><i class="fas fa-eye"></i> Xem website</a>
        <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Đăng xuất</a>
    </nav>
</aside>

<main class="admin-content">