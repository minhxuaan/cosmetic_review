<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GlowReview – Review Mỹ Phẩm</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?= isset($base_path) ? $base_path : '' ?>assets/css/style.css">
</head>
<body>
<nav class="navbar">
    <div class="nav-container">
        <a href="<?= isset($base_path) ? $base_path : '' ?>index.php" class="logo">
            <span class="logo-icon">✦</span> GlowReview
        </a>
        <div class="nav-search">
            <form action="<?= isset($base_path) ? $base_path : '' ?>index.php" method="GET">
                <input type="text" name="search" placeholder="Tìm sản phẩm..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                <button type="submit"><i class="fas fa-search"></i></button>
            </form>
        </div>
        <div class="nav-links">
            <a href="<?= isset($base_path) ? $base_path : '' ?>index.php" class="<?= $current_page === 'index.php' ? 'active' : '' ?>">Sản phẩm</a>
            <?php if (isLoggedIn()): ?>
                <?php if (isAdmin()): ?>
                    <a href="<?= isset($base_path) ? $base_path : '' ?>admin/index.php" class="nav-admin">
                        <i class="fas fa-crown"></i> Admin
                    </a>
                <?php endif; ?>
                <div class="nav-user">
                    <?php
                        $base = isset($base_path) ? $base_path : '';
                        $avatar = $_SESSION['avatar'] ?? '';
                        $username = $_SESSION['username'] ?? 'U';
                        $avatarSrc = !empty($avatar)
                            ? $base . 'uploads/avatars/' . htmlspecialchars($avatar)
                            : 'https://ui-avatars.com/api/?name=' . urlencode($username) . '&background=c8896a&color=fff&size=40';
                    ?>
                    <img src="<?= $avatarSrc ?>"
                         class="nav-avatar"
                         onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?= urlencode($username) ?>&background=c8896a&color=fff&size=40';">
                    <span><?= htmlspecialchars($username) ?></span>
                    <div class="dropdown">
                        <a href="<?= $base ?>profile.php">Hồ sơ</a>
                        <a href="<?= $base ?>logout.php">Đăng xuất</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="<?= isset($base_path) ? $base_path : '' ?>login.php" class="btn-nav-login">Đăng nhập</a>
                <a href="<?= isset($base_path) ? $base_path : '' ?>register.php" class="btn-nav-register">Đăng ký</a>
            <?php endif; ?>
        </div>
        <button class="hamburger" id="hamburger">
            <span></span><span></span><span></span>
        </button>
    </div>
</nav>
<div class="mobile-menu" id="mobileMenu">
    <a href="<?= isset($base_path) ? $base_path : '' ?>index.php">Sản phẩm</a>
    <?php if (isLoggedIn()): ?>
        <?php if (isAdmin()): ?><a href="<?= isset($base_path) ? $base_path : '' ?>admin/index.php">Admin</a><?php endif; ?>
        <a href="<?= isset($base_path) ? $base_path : '' ?>profile.php">Hồ sơ</a>
        <a href="<?= isset($base_path) ? $base_path : '' ?>logout.php">Đăng xuất</a>
    <?php else: ?>
        <a href="<?= isset($base_path) ? $base_path : '' ?>login.php">Đăng nhập</a>
        <a href="<?= isset($base_path) ? $base_path : '' ?>register.php">Đăng ký</a>
    <?php endif; ?>
</div>