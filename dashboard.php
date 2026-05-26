<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';

$pdo = getDB();

// ===== Stats =====
$totalProducts = $pdo->query("SELECT COUNT(*) FROM products WHERE status != 'inactive'")->fetchColumn();
$totalStock    = $pdo->query("SELECT COALESCE(SUM(quantity), 0) FROM stock")->fetchColumn();
$lowStock      = $pdo->query("SELECT COUNT(*) FROM stock WHERE quantity > 0 AND quantity <= min_alert")->fetchColumn();
$outOfStock    = $pdo->query("SELECT COUNT(*) FROM stock WHERE quantity = 0")->fetchColumn();

// Sales today
$salesToday = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE DATE(order_date) = CURDATE() AND order_status NOT IN ('cancelled')")->fetchColumn();
// Sales this month
$salesMonth = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE MONTH(order_date) = MONTH(NOW()) AND YEAR(order_date) = YEAR(NOW()) AND order_status NOT IN ('cancelled')")->fetchColumn();
// Total orders this month
$ordersMonth = $pdo->query("SELECT COUNT(*) FROM orders WHERE MONTH(order_date) = MONTH(NOW()) AND YEAR(order_date) = YEAR(NOW())")->fetchColumn();
// Pending orders
$pendingOrdersCount = $pdo->query("SELECT COUNT(*) FROM orders WHERE order_status = 'pending'")->fetchColumn();

// Sales last 7 days
$salesLast7 = $pdo->query("
    SELECT DATE(order_date) as d, COALESCE(SUM(total_amount), 0) as total, COUNT(*) as cnt
    FROM orders
    WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
      AND order_status NOT IN ('cancelled')
    GROUP BY DATE(order_date)
    ORDER BY d ASC
")->fetchAll();

// Sales by platform (this month)
$salesByPlatform = $pdo->query("
    SELECT p.name, p.color, p.icon, COALESCE(SUM(o.total_amount), 0) as total, COUNT(o.id) as cnt
    FROM platforms p
    LEFT JOIN orders o ON o.platform_id = p.id
        AND MONTH(o.order_date) = MONTH(NOW())
        AND YEAR(o.order_date) = YEAR(NOW())
        AND o.order_status NOT IN ('cancelled')
    WHERE p.is_active = 1
    GROUP BY p.id
    ORDER BY total DESC
")->fetchAll();

// Best sellers this month
$bestSellers = $pdo->query("
    SELECT p.name, p.main_image, p.sku, SUM(oi.quantity) as qty, SUM(oi.total_price) as revenue, cat.name as cat_name
    FROM order_items oi
    JOIN products p ON p.id = oi.product_id
    LEFT JOIN categories cat ON cat.id = p.category_id
    JOIN orders o ON o.id = oi.order_id
    WHERE MONTH(o.order_date) = MONTH(NOW())
      AND YEAR(o.order_date) = YEAR(NOW())
      AND o.order_status NOT IN ('cancelled')
    GROUP BY oi.product_id
    ORDER BY qty DESC
    LIMIT 5
")->fetchAll();

// Recent orders
$recentOrders = $pdo->query("
    SELECT o.*, p.name as platform_name, p.icon as platform_icon, p.color as platform_color
    FROM orders o
    LEFT JOIN platforms p ON p.id = o.platform_id
    ORDER BY o.order_date DESC
    LIMIT 8
")->fetchAll();

// Low stock products
$lowStockProducts = $pdo->query("
    SELECT pr.name, pr.sku, s.size, s.color, s.quantity, s.min_alert, cat.name as cat_name
    FROM stock s
    JOIN products pr ON pr.id = s.product_id
    LEFT JOIN categories cat ON cat.id = pr.category_id
    WHERE s.quantity <= s.min_alert
    ORDER BY s.quantity ASC
    LIMIT 8
")->fetchAll();

// Chart data
$chartDays = [];
$chartSales = [];
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i days"));
    $chartDays[] = date('d/m', strtotime("-$i days"));
    $found = array_filter($salesLast7, fn($r) => $r['d'] === $day);
    $chartSales[] = $found ? (float)array_values($found)[0]['total'] : 0;
}

$platformNames  = array_column($salesByPlatform, 'name');
$platformTotals = array_column($salesByPlatform, 'total');
$platformColors = array_column($salesByPlatform, 'color');
?>

<div class="container-fluid fade-in">

    <?php if (isset($_GET['err']) && $_GET['err'] === 'noperm'): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        🔒 คุณไม่มีสิทธิ์เข้าถึงหน้านั้น — เฉพาะ Admin เท่านั้น
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">🌸 Dashboard</h1>
            <p class="page-subtitle">ยินดีต้อนรับ <?= htmlspecialchars($_SESSION['admin_name']) ?>! วันนี้ <?= formatDateTH(date('Y-m-d')) ?></p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= SITE_URL ?>/pages/orders.php" class="btn btn-primary">
                <i class="fas fa-shopping-bag me-1"></i> ดูออเดอร์ทั้งหมด
            </a>
            <a href="<?= SITE_URL ?>/pages/product-add.php" class="btn btn-kawaii">
                <i class="fas fa-plus me-1"></i> เพิ่มสินค้า
            </a>
        </div>
    </div>

    <!-- Stat Cards Row 1 -->
    <div class="row g-4 mb-4">
        <div class="col-6 col-lg-3">
            <div class="stat-card pink">
                <div class="stat-label">ยอดขายวันนี้</div>
                <div class="stat-value"><?= formatPrice($salesToday) ?></div>
                <i class="fas fa-coins stat-icon"></i>
                <div class="stat-change"><i class="fas fa-calendar-day"></i> <?= formatDateTH(date('Y-m-d')) ?></div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card mint">
                <div class="stat-label">ยอดขายเดือนนี้</div>
                <div class="stat-value"><?= formatPrice($salesMonth) ?></div>
                <i class="fas fa-chart-line stat-icon"></i>
                <div class="stat-change"><i class="fas fa-shopping-cart"></i> <?= number_format($ordersMonth) ?> ออเดอร์</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card purple">
                <div class="stat-label">สินค้าทั้งหมด</div>
                <div class="stat-value"><?= number_format($totalProducts) ?></div>
                <i class="fas fa-tshirt stat-icon"></i>
                <div class="stat-change"><i class="fas fa-cubes"></i> สต็อกรวม <?= number_format($totalStock) ?> ชิ้น</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card orange">
                <div class="stat-label">ออเดอร์รอดำเนินการ</div>
                <div class="stat-value"><?= number_format($pendingOrdersCount) ?></div>
                <i class="fas fa-clock stat-icon"></i>
                <?php if ($lowStock > 0): ?>
                <div class="stat-change down"><i class="fas fa-exclamation-triangle"></i> สต็อกใกล้หมด <?= $lowStock ?> รายการ</div>
                <?php else: ?>
                <div class="stat-change up"><i class="fas fa-check-circle"></i> สต็อกปกติ</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-4 mb-4">
        <!-- Sales 7 days chart -->
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header">
                    <span class="card-title">📈 ยอดขาย 7 วันล่าสุด</span>
                    <a href="<?= SITE_URL ?>/pages/sales.php" class="btn btn-sm btn-outline-pink">ดูรายละเอียด</a>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <!-- Platform pie -->
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">
                    <span class="card-title">📊 ยอดขายตามแพลตฟอร์ม</span>
                </div>
                <div class="card-body">
                    <div class="chart-container-sm">
                        <canvas id="platformChart"></canvas>
                    </div>
                    <div class="mt-3">
                        <?php foreach ($salesByPlatform as $pf): ?>
                        <div class="d-flex justify-content-between align-items-center mb-1 py-1 border-bottom">
                            <div class="d-flex align-items-center gap-2">
                                <span><?= $pf['icon'] ?></span>
                                <span style="font-size:0.82rem;"><?= htmlspecialchars($pf['name']) ?></span>
                            </div>
                            <div class="text-end">
                                <div style="font-weight:600;font-size:0.85rem;"><?= formatPrice($pf['total']) ?></div>
                                <div style="font-size:0.72rem;color:var(--text-muted);"><?= $pf['cnt'] ?> ออเดอร์</div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom Row -->
    <div class="row g-4">
        <!-- Best Sellers -->
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">
                    <span class="card-title">🏆 สินค้าขายดีเดือนนี้</span>
                    <a href="<?= SITE_URL ?>/pages/sales.php" class="btn btn-sm btn-outline-pink">ทั้งหมด</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($bestSellers)): ?>
                    <div class="text-center p-4 text-muted">
                        <div style="font-size:2rem;">🛍️</div>
                        <p class="mt-2 mb-0">ยังไม่มีข้อมูลยอดขาย</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($bestSellers as $i => $bs): ?>
                    <div class="d-flex align-items-center p-3 border-bottom gap-3">
                        <div class="fw-bold" style="width:24px;color:<?= $i===0?'#FFD700':($i===1?'#C0C0C0':($i===2?'#CD7F32':'var(--text-muted)')) ?>;font-size:1.1rem;">
                            <?= $i === 0 ? '🥇' : ($i === 1 ? '🥈' : ($i === 2 ? '🥉' : '#' . ($i+1))) ?>
                        </div>
                        <?php if ($bs['main_image']): ?>
                        <img src="<?= SITE_URL ?>/assets/uploads/products/<?= htmlspecialchars($bs['main_image']) ?>" style="width:44px;height:44px;border-radius:8px;object-fit:cover;">
                        <?php else: ?>
                        <div style="width:44px;height:44px;border-radius:8px;background:var(--pink-light);display:flex;align-items:center;justify-content:center;font-size:1.3rem;">👶</div>
                        <?php endif; ?>
                        <div class="flex-1">
                            <div style="font-size:0.85rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:150px;"><?= htmlspecialchars($bs['name']) ?></div>
                            <div style="font-size:0.75rem;color:var(--text-muted);"><?= htmlspecialchars($bs['cat_name'] ?? '') ?></div>
                        </div>
                        <div class="text-end ms-auto">
                            <div style="font-weight:700;font-size:0.88rem;color:var(--pink-dark);"><?= number_format($bs['qty']) ?> ชิ้น</div>
                            <div style="font-size:0.72rem;color:var(--text-muted);"><?= formatPrice($bs['revenue']) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Orders -->
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header">
                    <span class="card-title">📦 ออเดอร์ล่าสุด</span>
                    <a href="<?= SITE_URL ?>/pages/orders.php" class="btn btn-sm btn-outline-pink">ทั้งหมด</a>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>เลขออเดอร์</th>
                                <th>แพลตฟอร์ม</th>
                                <th>ยอด</th>
                                <th>สถานะ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentOrders as $order): ?>
                            <tr>
                                <td>
                                    <a href="<?= SITE_URL ?>/pages/orders.php?id=<?= $order['id'] ?>" style="color:var(--pink-dark);font-weight:600;font-size:0.82rem;"><?= htmlspecialchars($order['order_number']) ?></a>
                                    <div style="font-size:0.72rem;color:var(--text-muted);"><?= htmlspecialchars($order['customer_name'] ?? '-') ?></div>
                                </td>
                                <td>
                                    <span title="<?= htmlspecialchars($order['platform_name'] ?? '') ?>"><?= $order['platform_icon'] ?? '🛒' ?></span>
                                </td>
                                <td style="font-weight:600;font-size:0.85rem;"><?= formatPrice($order['total_amount']) ?></td>
                                <td>
                                    <span class="badge-status badge-<?= $order['order_status'] ?>"><?php
                                        $statusTH = ['pending'=>'รอดำเนิน','confirmed'=>'ยืนยันแล้ว','packing'=>'กำลังแพ็ค','shipped'=>'จัดส่งแล้ว','delivered'=>'ส่งถึงแล้ว','cancelled'=>'ยกเลิก','returned'=>'คืนสินค้า'];
                                        echo $statusTH[$order['order_status']] ?? $order['order_status'];
                                    ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recentOrders)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">ยังไม่มีออเดอร์</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Low Stock Alert -->
        <div class="col-lg-3">
            <div class="card h-100">
                <div class="card-header">
                    <span class="card-title">⚠️ สต็อกใกล้หมด</span>
                    <a href="<?= SITE_URL ?>/pages/stock.php?filter=low" class="btn btn-sm btn-outline-pink">จัดการ</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($lowStockProducts)): ?>
                    <div class="text-center p-4 text-muted">
                        <div style="font-size:2rem;">✅</div>
                        <p class="mt-2 mb-0 text-success fw-500">สต็อกอยู่ในระดับปกติ</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($lowStockProducts as $ls): ?>
                    <div class="d-flex justify-content-between align-items-center p-2 px-3 border-bottom">
                        <div>
                            <div style="font-size:0.82rem;font-weight:600;"><?= htmlspecialchars($ls['name']) ?></div>
                            <div style="font-size:0.72rem;color:var(--text-muted);"><?= $ls['size'] ?> / <?= htmlspecialchars($ls['color']) ?></div>
                        </div>
                        <span class="badge-status <?= $ls['quantity'] == 0 ? 'badge-out' : 'badge-low' ?>">
                            <?= $ls['quantity'] == 0 ? 'หมด' : $ls['quantity'] . ' ชิ้น' ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div><!-- end container -->

<?php
$extraJs = "
const salesCtx = document.getElementById('salesChart').getContext('2d');
new Chart(salesCtx, {
    type: 'bar',
    data: {
        labels: " . json_encode($chartDays) . ",
        datasets: [{
            label: 'ยอดขาย (฿)',
            data: " . json_encode($chartSales) . ",
            backgroundColor: 'rgba(255,133,162,0.7)',
            borderColor: '#FF85A2',
            borderWidth: 2,
            borderRadius: 8,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { color: '#EEE8FF' }, ticks: { callback: v => '฿'+v.toLocaleString('th-TH') } },
            x: { grid: { display: false } }
        }
    }
});

const pfCtx = document.getElementById('platformChart').getContext('2d');
new Chart(pfCtx, {
    type: 'doughnut',
    data: {
        labels: " . json_encode($platformNames) . ",
        datasets: [{
            data: " . json_encode($platformTotals) . ",
            backgroundColor: " . json_encode($platformColors) . ",
            borderWidth: 3,
            borderColor: '#fff',
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '65%',
        plugins: { legend: { position: 'bottom', labels: { padding: 10 } } }
    }
});
";
include __DIR__ . '/includes/footer.php';
?>
