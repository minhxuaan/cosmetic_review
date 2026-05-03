<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$current_page = basename($_SERVER['PHP_SELF']);

$base = isset($base_path) ? $base_path : '';
$sessionAvatar = $_SESSION['avatar'] ?? '';
$username = $_SESSION['username'] ?? 'U';
$avatarSrc = !empty($sessionAvatar)
    ? $base . 'uploads/avatars/' . htmlspecialchars($sessionAvatar)
    : 'https://ui-avatars.com/api/?name=' . urlencode($username) . '&background=c8896a&color=fff&size=40';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GlowReview – Review Mỹ Phẩm</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/style.css">
    <style>
        .nav-user {
            position: relative;
        }
        .nav-user .dropdown {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.12);
            min-width: 160px;
            z-index: 999;
            padding-top: 8px;
        }
        .nav-user .dropdown::before {
            content: '';
            position: absolute;
            top: -10px;
            left: 0;
            right: 0;
            height: 10px;
        }
        .nav-user .dropdown a {
            display: block;
            padding: 10px 16px;
            color: var(--charcoal, #333);
            font-size: 0.9rem;
            white-space: nowrap;
            transition: background 0.15s;
        }
        .nav-user .dropdown a:hover {
            background: #fdf6f0;
            color: var(--rose, #c8896a);
        }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="nav-container">
        <a href="<?= $base ?>index.php" class="logo">
            <span class="logo-icon">✦</span> GlowReview
        </a>

        <div class="nav-search">
            <form action="<?= $base ?>index.php" method="GET">
                <input type="text" name="search" placeholder="Tìm sản phẩm..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                <button type="submit"><i class="fas fa-search"></i></button>
            </form>
        </div>

        <div class="nav-links">
            <a href="<?= $base ?>index.php" class="<?= $current_page === 'index.php' ? 'active' : '' ?>">Sản phẩm</a>
            <a href="<?= $base ?>posts.php" class="<?= $current_page === 'posts.php' ? 'active' : '' ?>">Bài Review</a>

            <?php if (isLoggedIn()): ?>
                <a href="<?= $base ?>create_post.php" style="background:rgba(200,137,106,0.12);color:var(--rose);border-radius:50px;padding:7px 14px;font-size:0.88rem;font-weight:600;">
                    <i class="fas fa-pen-nib"></i> Đăng review
                </a>
                <?php if (isAdmin()): ?>
                    <a href="<?= $base ?>admin/index.php" class="nav-admin">
                        <i class="fas fa-crown"></i> Admin
                    </a>
                <?php endif; ?>

                <div class="nav-user"
                     onmouseenter="clearTimeout(this._t); this.querySelector('.dropdown').style.display='block';"
                     onmouseleave="this._t = setTimeout(() => this.querySelector('.dropdown').style.display='none', 200);">
                    <img src="<?= $avatarSrc ?>"
                         class="nav-avatar"
                         onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?= urlencode($username) ?>&background=c8896a&color=fff&size=40';">
                    <span><?= htmlspecialchars($username) ?></span>
                    <div class="dropdown">
                        <a href="<?= $base ?>profile.php">Hồ sơ</a>
                        <a href="<?= $base ?>my_posts.php">Bài review của tôi</a>
                        <a href="<?= $base ?>logout.php">Đăng xuất</a>
                    </div>
                </div>

            <?php else: ?>
                <a href="<?= $base ?>login.php" class="btn-nav-login">Đăng nhập</a>
                <a href="<?= $base ?>register.php" class="btn-nav-register">Đăng ký</a>
            <?php endif; ?>
        </div>

        <button class="hamburger" id="hamburger">
            <span></span><span></span><span></span>
        </button>
    </div>
</nav>

<div class="mobile-menu" id="mobileMenu">
    <a href="<?= $base ?>index.php">Sản phẩm</a>
    <a href="<?= $base ?>posts.php">Bài Review</a>
    <?php if (isLoggedIn()): ?>
        <a href="<?= $base ?>create_post.php">Đăng review</a>
        <?php if (isAdmin()): ?>
            <a href="<?= $base ?>admin/index.php">Admin</a>
        <?php endif; ?>
        <a href="<?= $base ?>profile.php">Hồ sơ</a>
        <a href="<?= $base ?>my_posts.php">Bài review của tôi</a>
        <a href="<?= $base ?>logout.php">Đăng xuất</a>
    <?php else: ?>
        <a href="<?= $base ?>login.php">Đăng nhập</a>
        <a href="<?= $base ?>register.php">Đăng ký</a>
    <?php endif; ?>
</div>