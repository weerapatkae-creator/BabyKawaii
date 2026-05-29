<?php
$navGeneral = [
    ['url' => SITE_URL . '/dashboard.php',         'icon' => 'fa-chart-pie',      'label' => 'Dashboard',           'page' => 'dashboard'],
    ['url' => SITE_URL . '/pages/products.php',    'icon' => 'fa-tshirt',         'label' => 'สินค้าทั้งหมด',       'page' => 'products'],
    ['url' => SITE_URL . '/pages/product-add.php', 'icon' => 'fa-plus-circle',    'label' => 'เพิ่มสินค้าใหม่',     'page' => 'product-add'],
    ['url' => SITE_URL . '/pages/stock.php',       'icon' => 'fa-boxes-stacked',  'label' => 'จัดการสต็อก',        'page' => 'stock'],
    ['url' => SITE_URL . '/pages/orders.php',      'icon' => 'fa-shopping-bag',   'label' => 'ออเดอร์ / คำสั่งซื้อ','page' => 'orders'],
    ['url' => SITE_URL . '/pages/customers.php',   'icon' => 'fa-users',          'label' => 'ฐานข้อมูลลูกค้า',    'page' => 'customers'],
    ['url' => SITE_URL . '/pages/inbox.php',        'icon' => 'fa-comment-dots',   'label' => 'Inbox ข้อความ',       'page' => 'inbox'],
    ['url' => SITE_URL . '/pages/sales.php',       'icon' => 'fa-chart-line',     'label' => 'ยอดขาย & วิเคราะห์', 'page' => 'sales'],
    ['url' => SITE_URL . '/pages/platforms.php',   'icon' => 'fa-share-nodes',    'label' => 'แพลตฟอร์มขาย',       'page' => 'platforms'],
    ['url' => SITE_URL . '/pages/media.php',       'icon' => 'fa-photo-film',     'label' => 'คลังสื่อ',           'page' => 'media'],
    ['url' => SITE_URL . '/pages/promotions.php',  'icon' => 'fa-tag',            'label' => 'โปรโมชั่น',          'page' => 'promotions'],
    ['url' => SITE_URL . '/pages/calendar.php',    'icon' => 'fa-calendar-days',  'label' => 'ปฏิทินโพสต์',        'page' => 'calendar'],
];

// Admin-only menu items
$navAdmin = [
    ['url' => SITE_URL . '/pages/users.php',             'icon' => 'fa-users-gear',    'label' => 'จัดการผู้ใช้งาน',  'page' => 'users'],
    ['url' => SITE_URL . '/pages/platform-accounts.php', 'icon' => 'fa-layer-group',   'label' => 'บัญชี Platform',    'page' => 'platform-accounts'],
    ['url' => SITE_URL . '/pages/integrations.php',      'icon' => 'fa-plug',          'label' => 'เชื่อมต่อระบบ',    'page' => 'integrations'],
    ['url' => SITE_URL . '/pages/settings.php',          'icon' => 'fa-gear',          'label' => 'ตั้งค่าร้าน',      'page' => 'settings'],
    ['url' => SITE_URL . '/pages/data-manage.php',       'icon' => 'fa-database',      'label' => 'จัดการข้อมูล',     'page' => 'data-manage'],
    ['url' => SITE_URL . '/pages/deploy-guide.php',      'icon' => 'fa-rocket',        'label' => 'คู่มือ Deploy',     'page' => 'deploy-guide'],
    ['url' => SITE_URL . '/pages/system-test.php',       'icon' => 'fa-flask',         'label' => 'System Test',       'page' => 'system-test'],
];

$isAdminRole = in_array($_SESSION['admin_role'] ?? '', ['superadmin', 'admin']);
?>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="<?= SITE_URL ?>/assets/images/logo-light.svg"
             alt="BabyKawaii"
             class="sidebar-logo-img"
             style="max-width:164px; width:100%; display:block; margin:0 auto 6px;">
        <div class="shop-subtitle text-center">
            <?= $_SESSION['admin_role'] === 'superadmin' ? '⭐ Superadmin' : ($_SESSION['admin_role'] === 'admin' ? '🔑 Admin Panel' : '👤 Staff') ?>
        </div>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($navGeneral as $item): ?>
        <a href="<?= $item['url'] ?>"
           class="nav-item <?= ($currentPage === $item['page']) ? 'active' : '' ?>">
            <i class="fas <?= $item['icon'] ?>"></i>
            <span><?= $item['label'] ?></span>
            <?php if ($item['page'] === 'orders' && $pendingOrders > 0): ?>
                <span class="nav-badge"><?= $pendingOrders ?></span>
            <?php endif; ?>
            <?php if ($item['page'] === 'inbox'): ?>
                <span class="nav-badge" id="sidebarInboxBadge"
                      style="<?= $unreadMessages > 0 ? '' : 'display:none' ?>">
                    <?= $unreadMessages ?: '' ?>
                </span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>

        <?php if ($isAdminRole): ?>
        <div class="nav-section-label">จัดการระบบ</div>
        <?php foreach ($navAdmin as $item): ?>
        <a href="<?= $item['url'] ?>"
           class="nav-item <?= ($currentPage === $item['page']) ? 'active' : '' ?>">
            <i class="fas <?= $item['icon'] ?>"></i>
            <span><?= $item['label'] ?></span>
        </a>
        <?php endforeach; ?>
        <?php endif; ?>
    </nav>

    <!-- Quick platform links -->
    <div class="sidebar-platforms">
        <div class="platform-title">แพลตฟอร์มของฉัน</div>
        <div class="platform-icons">
            <a href="<?= SITE_URL ?>/pages/platform-accounts.php?platform=facebook" title="Facebook Page DM" class="platform-icon fb"><i class="fab fa-facebook-f"></i></a>
            <a href="<?= SITE_URL ?>/pages/platform-accounts.php?platform=tiktok"   title="TikTok Shop"      class="platform-icon tt"><i class="fab fa-tiktok"></i></a>
            <a href="<?= SITE_URL ?>/pages/platform-accounts.php?platform=instagram" title="Instagram DM"    class="platform-icon ig"><i class="fab fa-instagram"></i></a>
        </div>
    </div>
</aside>
