<?php
$pageTitle = 'ยอดขาย & วิเคราะห์';
require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();

$period = $_GET['period'] ?? 'month';
$platformFilter = (int)($_GET['platform'] ?? 0);

switch ($period) {
    case 'today':  $dateWhere = "DATE(o.order_date) = CURDATE()"; break;
    case 'week':   $dateWhere = "o.order_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)"; break;
    case 'month':  $dateWhere = "MONTH(o.order_date)=MONTH(NOW()) AND YEAR(o.order_date)=YEAR(NOW())"; break;
    case 'year':   $dateWhere = "YEAR(o.order_date) = YEAR(NOW())"; break;
    default:       $dateWhere = "MONTH(o.order_date)=MONTH(NOW()) AND YEAR(o.order_date)=YEAR(NOW())";
}

$pfWhere = $platformFilter ? "AND o.platform_id = $platformFilter" : '';

// Key metrics
$metrics = $pdo->query("SELECT
    COALESCE(SUM(o.total_amount), 0) as revenue,
    COALESCE(SUM(o.total_amount - o.shipping_cost - o.discount_amount), 0) as net_revenue,
    COUNT(*) as total_orders,
    COALESCE(AVG(o.total_amount), 0) as aov,
    COALESCE(SUM(oi.quantity), 0) as units_sold
    FROM orders o
    LEFT JOIN order_items oi ON oi.order_id = o.id
    WHERE $dateWhere $pfWhere AND o.order_status NOT IN ('cancelled')
")->fetch();

// Sales by platform
$byPlatform = $pdo->query("SELECT pl.name, pl.icon, pl.color,
    COALESCE(SUM(o.total_amount), 0) as revenue,
    COUNT(o.id) as orders,
    COALESCE(AVG(o.total_amount), 0) as aov
    FROM platforms pl
    LEFT JOIN orders o ON o.platform_id = pl.id AND $dateWhere AND o.order_status NOT IN ('cancelled') $pfWhere
    WHERE pl.is_active = 1
    GROUP BY pl.id ORDER BY revenue DESC")->fetchAll();

// Best sellers
$bestSellers = $pdo->query("SELECT p.name, p.sku, p.main_image, cat.icon as cat_icon,
    SUM(oi.quantity) as qty, SUM(oi.total_price) as revenue
    FROM order_items oi
    JOIN products p ON p.id = oi.product_id
    LEFT JOIN categories cat ON cat.id = p.category_id
    JOIN orders o ON o.id = oi.order_id
    WHERE $dateWhere $pfWhere AND o.order_status NOT IN ('cancelled')
    GROUP BY oi.product_id ORDER BY qty DESC LIMIT 10")->fetchAll();

// Sales by size
$bySizeRaw = $pdo->query("SELECT oi.size, SUM(oi.quantity) as qty, SUM(oi.total_price) as revenue
    FROM order_items oi
    JOIN orders o ON o.id = oi.order_id
    WHERE $dateWhere $pfWhere AND o.order_status NOT IN ('cancelled') AND oi.size != ''
    GROUP BY oi.size ORDER BY qty DESC")->fetchAll();

// Daily/Monthly chart data
$chartData = $pdo->query("SELECT
    DATE_FORMAT(o.order_date, '" . ($period === 'year' ? '%Y-%m' : '%Y-%m-%d') . "') as period,
    SUM(o.total_amount) as revenue,
    COUNT(*) as orders
    FROM orders o WHERE $dateWhere $pfWhere AND o.order_status NOT IN ('cancelled')
    GROUP BY period ORDER BY period ASC")->fetchAll();

$platforms = $pdo->query("SELECT * FROM platforms WHERE is_active=1 ORDER BY name")->fetchAll();

// Categories revenue
$byCat = $pdo->query("SELECT cat.name, cat.icon, SUM(oi.quantity) as qty, SUM(oi.total_price) as revenue
    FROM order_items oi
    JOIN products p ON p.id = oi.product_id
    LEFT JOIN categories cat ON cat.id = p.category_id
    JOIN orders o ON o.id = oi.order_id
    WHERE $dateWhere $pfWhere AND o.order_status NOT IN ('cancelled')
    GROUP BY p.category_id ORDER BY revenue DESC LIMIT 8")->fetchAll();
?>

<div class="container-fluid fade-in">
    <div class="page-header">
        <div>
            <h1 class="page-title">📈 ยอดขาย & วิเคราะห์</h1>
            <p class="page-subtitle">รายงานยอดขายและข้อมูลเชิงวิเคราะห์</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <!-- Period filter -->
            <div class="btn-group btn-group-sm">
                <?php foreach (['today'=>'วันนี้','week'=>'7 วัน','month'=>'เดือนนี้','year'=>'ปีนี้'] as $k=>$v): ?>
                <a href="?period=<?= $k ?>&platform=<?= $platformFilter ?>" class="btn <?= $period===$k?'btn-primary':'btn-outline-secondary' ?>"><?= $v ?></a>
                <?php endforeach; ?>
            </div>
            <!-- Platform filter -->
            <select class="form-select form-select-sm" style="width:auto;" onchange="window.location='?period=<?= $period ?>&platform='+this.value">
                <option value="0">ทุกแพลตฟอร์ม</option>
                <?php foreach ($platforms as $pf): ?>
                <option value="<?= $pf['id'] ?>" <?= $platformFilter==$pf['id']?'selected':'' ?>><?= $pf['icon'] ?> <?= htmlspecialchars($pf['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php
            $exportFrom = match($period) {
                'today' => date('Y-m-d'),
                'week'  => date('Y-m-d', strtotime('-7 days')),
                'year'  => date('Y-01-01'),
                default => date('Y-m-01'),  // month
            };
            $exportTo = date('Y-m-d');
            ?>
            <a href="<?= SITE_URL ?>/pages/export.php?type=sales&date_from=<?= $exportFrom ?>&date_to=<?= $exportTo ?>" class="btn btn-outline-success btn-sm">
                <i class="fas fa-file-csv me-1"></i> Export CSV
            </a>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="stat-card pink">
                <div class="stat-label">รายได้รวม</div>
                <div class="stat-value" style="font-size:1.4rem;"><?= formatPrice($metrics['revenue']) ?></div>
                <i class="fas fa-coins stat-icon"></i>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card mint">
                <div class="stat-label">จำนวนออเดอร์</div>
                <div class="stat-value"><?= number_format($metrics['total_orders']) ?></div>
                <i class="fas fa-shopping-bag stat-icon"></i>
                <div class="stat-change"><i class="fas fa-tshirt"></i> <?= number_format($metrics['units_sold']) ?> ชิ้น</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card purple">
                <div class="stat-label">AOV (ยอดเฉลี่ยต่อออเดอร์)</div>
                <div class="stat-value" style="font-size:1.4rem;"><?= formatPrice($metrics['aov']) ?></div>
                <i class="fas fa-chart-bar stat-icon"></i>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card orange">
                <div class="stat-label">รายได้สุทธิ</div>
                <div class="stat-value" style="font-size:1.4rem;"><?= formatPrice($metrics['net_revenue']) ?></div>
                <i class="fas fa-wallet stat-icon"></i>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><span class="card-title">📊 แนวโน้มยอดขาย</span></div>
                <div class="card-body"><div class="chart-container"><canvas id="trendChart"></canvas></div></div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><span class="card-title">🛒 ยอดขายตามแพลตฟอร์ม</span></div>
                <div class="card-body">
                    <div class="chart-container-sm"><canvas id="platformChart"></canvas></div>
                    <div class="mt-2">
                        <?php foreach ($byPlatform as $pf): ?>
                        <?php $total = array_sum(array_column($byPlatform, 'revenue')); $pct = $total > 0 ? round($pf['revenue']/$total*100) : 0; ?>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between" style="font-size:0.82rem;">
                                <span><?= $pf['icon'] ?> <?= htmlspecialchars($pf['name']) ?></span>
                                <span style="font-weight:600;"><?= formatPrice($pf['revenue']) ?> (<?= $pct ?>%)</span>
                            </div>
                            <div class="progress" style="height:5px;border-radius:4px;">
                                <div class="progress-bar" style="width:<?= $pct ?>%;background:<?= htmlspecialchars($pf['color']) ?>;"></div>
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
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header"><span class="card-title">🏆 สินค้าขายดี</span></div>
                <div class="card-body p-0">
                    <?php foreach ($bestSellers as $i => $bs): ?>
                    <div class="d-flex align-items-center p-3 border-bottom gap-2">
                        <div style="width:24px;text-align:center;font-size:1rem;"><?= ['🥇','🥈','🥉'][$i] ?? '#'.($i+1) ?></div>
                        <?php if ($bs['main_image']): ?>
                        <img src="<?= SITE_URL ?>/assets/uploads/products/<?= htmlspecialchars($bs['main_image']) ?>" style="width:44px;height:44px;border-radius:8px;object-fit:cover;">
                        <?php else: ?>
                        <div style="width:44px;height:44px;border-radius:8px;background:var(--pink-light);display:flex;align-items:center;justify-content:center;"><?= $bs['cat_icon'] ?? '👶' ?></div>
                        <?php endif; ?>
                        <div class="flex-1">
                            <div style="font-weight:600;font-size:0.85rem;"><?= htmlspecialchars($bs['name']) ?></div>
                            <div style="font-size:0.72rem;color:var(--text-muted);"><?= htmlspecialchars($bs['sku'] ?? '') ?></div>
                        </div>
                        <div class="text-end">
                            <div style="font-weight:700;color:var(--pink-dark);"><?= number_format($bs['qty']) ?> ชิ้น</div>
                            <div style="font-size:0.75rem;color:var(--text-muted);"><?= formatPrice($bs['revenue']) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($bestSellers)): ?>
                    <div class="text-center p-4 text-muted">ยังไม่มีข้อมูล</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- By Size -->
        <div class="col-lg-3">
            <div class="card h-100">
                <div class="card-header"><span class="card-title">📏 ยอดขายตามไซต์</span></div>
                <div class="card-body">
                    <?php foreach ($bySizeRaw as $sz): ?>
                    <div class="d-flex justify-content-between align-items-center mb-2 py-1 border-bottom">
                        <span class="size-badge size-ok"><?= htmlspecialchars($sz['size']) ?></span>
                        <div class="text-end">
                            <span style="font-weight:600;"><?= number_format($sz['qty']) ?> ชิ้น</span>
                            <div style="font-size:0.72rem;color:var(--text-muted);"><?= formatPrice($sz['revenue']) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($bySizeRaw)): ?><div class="text-center text-muted">ยังไม่มีข้อมูล</div><?php endif; ?>
                </div>
            </div>
        </div>

        <!-- By Category -->
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header"><span class="card-title">🗂️ ยอดขายตามหมวดหมู่</span></div>
                <div class="card-body">
                    <div class="chart-container-sm mb-3"><canvas id="catChart"></canvas></div>
                    <?php foreach ($byCat as $cat): ?>
                    <div class="d-flex justify-content-between align-items-center mb-1" style="font-size:0.82rem;">
                        <span><?= $cat['icon'] ?? '📦' ?> <?= htmlspecialchars($cat['name'] ?? 'ไม่ระบุ') ?></span>
                        <span style="font-weight:600;"><?= number_format($cat['qty']) ?> ชิ้น</span>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($byCat)): ?><div class="text-center text-muted">ยังไม่มีข้อมูล</div><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$chartLabels  = array_column($chartData, 'period');
$chartRevenue = array_column($chartData, 'revenue');
$chartOrders  = array_column($chartData, 'orders');
$pfNames   = array_column($byPlatform, 'name');
$pfRevenue = array_column($byPlatform, 'revenue');
$pfColors  = array_column($byPlatform, 'color');
$catNames  = array_column($byCat, 'name');
$catQty    = array_column($byCat, 'qty');
$catColors = ['#E8869B','#8A6DB0','#3AA088','#FFD166','#4FC3F7','#FF9A3C','#E4405F','#8B5CF6'];
$extraJs = "
const tCtx = document.getElementById('trendChart').getContext('2d');
new Chart(tCtx, {
    type: 'line',
    data: {
        labels: " . json_encode($chartLabels) . ",
        datasets: [{
            label: 'รายได้ (฿)',
            data: " . json_encode($chartRevenue) . ",
            borderColor: '#E8869B', backgroundColor: 'rgba(232,134,155,0.1)',
            fill: true, tension: 0.4, yAxisID: 'y'
        },{
            label: 'ออเดอร์',
            data: " . json_encode($chartOrders) . ",
            borderColor: '#8A6DB0', backgroundColor: 'transparent',
            fill: false, tension: 0.4, yAxisID: 'y1'
        }]
    },
    options: {
        responsive:true, maintainAspectRatio:false,
        scales: {
            y: { beginAtZero:true, grid:{color:'#EEE8FF'}, ticks:{callback: v=>'฿'+v.toLocaleString('th-TH')} },
            y1: { position:'right', beginAtZero:true, grid:{display:false} }
        }
    }
});
const pfCtx = document.getElementById('platformChart').getContext('2d');
new Chart(pfCtx, {
    type: 'doughnut',
    data: { labels:" . json_encode($pfNames) . ", datasets:[{data:" . json_encode($pfRevenue) . ",backgroundColor:" . json_encode($pfColors) . ",borderWidth:3,borderColor:'#fff'}] },
    options: { responsive:true, maintainAspectRatio:false, cutout:'60%', plugins:{legend:{display:false}} }
});
const catCtx = document.getElementById('catChart').getContext('2d');
new Chart(catCtx, {
    type: 'bar',
    data: { labels:" . json_encode($catNames) . ", datasets:[{label:'ชิ้น',data:" . json_encode($catQty) . ",backgroundColor:" . json_encode($catColors) . ",borderRadius:6}] },
    options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true,grid:{color:'#EEE8FF'}},x:{grid:{display:false}}} }
});
";
include __DIR__ . '/../includes/footer.php'; ?>
