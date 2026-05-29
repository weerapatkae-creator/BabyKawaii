<?php
require_once __DIR__ . '/../config/database.php';
requireLogin();

$shopName = getSetting('shop_name', 'BabyKawaii Shop');
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Get low stock count for badge
$pdo = getDB();
$lowStockCount  = $pdo->query("SELECT COUNT(*) FROM stock WHERE quantity > 0 AND quantity <= min_alert")->fetchColumn();
$pendingOrders  = $pdo->query("SELECT COUNT(*) FROM orders WHERE order_status = 'pending'")->fetchColumn();
$unreadMessages = $pdo->query("SELECT COALESCE(SUM(unread_count),0) FROM conversations WHERE status != 'closed'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?> - <?= $shopName ?></title>
    <!-- Google Fonts: Noto Sans Thai -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>

<!-- Top Navbar -->
<nav class="navbar navbar-expand-lg navbar-top fixed-top">
    <div class="container-fluid">
        <!-- Toggle sidebar -->
        <button class="btn btn-link sidebar-toggle me-1" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>

        <!-- Brand -->
        <a class="navbar-brand" href="<?= SITE_URL ?>/dashboard.php">
            <span class="brand-text"><?= $shopName ?></span>
        </a>

        <!-- Search (เดสก์ท็อป) -->
        <form class="topbar-search d-none d-lg-flex" action="<?= SITE_URL ?>/pages/products.php" method="get">
            <i class="fas fa-search"></i>
            <input type="text" name="q" placeholder="ค้นหาสินค้า ออเดอร์ ลูกค้า…" autocomplete="off">
        </form>

        <div class="ms-auto d-flex align-items-center gap-2">
            <!-- Low stock alert -->
            <?php if ($lowStockCount > 0): ?>
            <a href="<?= SITE_URL ?>/pages/stock.php?filter=low" class="btn btn-sm btn-warning position-relative">
                <i class="fas fa-box-open"></i>
                <span class="badge bg-danger badge-pill ms-1"><?= $lowStockCount ?></span>
                <span class="d-none d-md-inline ms-1">สต็อกใกล้หมด</span>
            </a>
            <?php endif; ?>

            <!-- Pending orders -->
            <?php if ($pendingOrders > 0): ?>
            <a href="<?= SITE_URL ?>/pages/orders.php?status=pending" class="btn btn-sm btn-info position-relative">
                <i class="fas fa-shopping-bag"></i>
                <span class="badge bg-danger badge-pill ms-1"><?= $pendingOrders ?></span>
                <span class="d-none d-md-inline ms-1">ออเดอร์ใหม่</span>
            </a>
            <?php endif; ?>

            <!-- Inbox unread -->
            <a href="<?= SITE_URL ?>/pages/inbox.php" id="headerInboxBtn"
               class="btn btn-sm position-relative <?= $unreadMessages > 0 ? 'btn-danger' : 'btn-outline-secondary' ?>">
                <i class="fas fa-comment-dots"></i>
                <span id="headerInboxBadge"
                      class="badge bg-white text-danger badge-pill ms-1"
                      style="<?= $unreadMessages > 0 ? '' : 'display:none' ?>">
                    <?= $unreadMessages ?: '' ?>
                </span>
                <span id="headerInboxLabel" class="d-none d-md-inline ms-1"
                      style="<?= $unreadMessages > 0 ? '' : 'display:none' ?>">ข้อความใหม่</span>
            </a>

            <!-- User dropdown -->
            <div class="dropdown">
                <button class="btn btn-link dropdown-toggle user-btn" data-bs-toggle="dropdown">
                    <span class="user-avatar-circle"><?= mb_substr($_SESSION['admin_name'] ?? 'A', 0, 1, 'UTF-8') ?></span>
                    <span class="d-none d-md-inline"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?= SITE_URL ?>/pages/settings.php"><i class="fas fa-cog me-2"></i>ตั้งค่า</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?= SITE_URL ?>/logout.php"><i class="fas fa-sign-out-alt me-2"></i>ออกจากระบบ</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<div class="wrapper">
<?php include __DIR__ . '/sidebar.php'; ?>

<div class="main-content" id="mainContent">
