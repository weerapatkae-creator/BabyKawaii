<?php
/**
 * BabyKawaii API — Sales Summary
 * GET /api/v1/sales.php?period=today|week|month|year
 */
require_once __DIR__ . '/auth.php';
requireApiKey();

if (method() !== 'GET') jsonErr('Method not allowed', 405);

$pdo    = getDB();
$period = $_GET['period'] ?? 'today';

switch ($period) {
    case 'week':   $dateFilter = "o.order_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)"; break;
    case 'month':  $dateFilter = "MONTH(o.order_date)=MONTH(NOW()) AND YEAR(o.order_date)=YEAR(NOW())"; break;
    case 'year':   $dateFilter = "YEAR(o.order_date)=YEAR(NOW())"; break;
    default:       $dateFilter = "DATE(o.order_date)=CURDATE()"; $period = 'today';
}

$notCancelled = "o.order_status NOT IN ('cancelled','returned')";

// KPIs
$revenue  = $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM orders o WHERE $dateFilter AND $notCancelled")->fetchColumn();
$orders   = $pdo->query("SELECT COUNT(*) FROM orders o WHERE $dateFilter")->fetchColumn();
$pending  = $pdo->query("SELECT COUNT(*) FROM orders o WHERE $dateFilter AND o.order_status='pending'")->fetchColumn();
$avgOrder = $orders > 0 ? round($revenue / $orders, 2) : 0;

// Sales by platform
$byPlatform = $pdo->query("
    SELECT p.name, p.icon, p.slug,
           COUNT(o.id) as cnt, COALESCE(SUM(o.total_amount),0) as total
    FROM platforms p
    LEFT JOIN orders o ON o.platform_id=p.id AND $dateFilter AND $notCancelled
    WHERE p.is_active=1
    GROUP BY p.id ORDER BY total DESC")->fetchAll();

// Top products
$topProducts = $pdo->query("
    SELECT pr.name, pr.sku, SUM(oi.quantity) as qty, SUM(oi.total_price) as revenue
    FROM order_items oi
    JOIN products pr ON pr.id=oi.product_id
    JOIN orders o ON o.id=oi.order_id
    WHERE $dateFilter AND $notCancelled
    GROUP BY oi.product_id ORDER BY qty DESC LIMIT 5")->fetchAll();

// Daily trend (last 7 days always shown for context)
$trend = $pdo->query("
    SELECT DATE(order_date) as day,
           COUNT(*) as orders,
           COALESCE(SUM(total_amount),0) as revenue
    FROM orders
    WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND $notCancelled
    GROUP BY DATE(order_date) ORDER BY day ASC")->fetchAll();

// Build LINE-friendly report text
$periodLabel = ['today'=>'วันนี้','week'=>'7 วันล่าสุด','month'=>'เดือนนี้','year'=>'ปีนี้'][$period];
$lineText  = "📊 รายงานยอดขาย{$periodLabel}\n";
$lineText .= "────────────────\n";
$lineText .= "💰 รายได้รวม: ฿" . number_format($revenue, 0) . "\n";
$lineText .= "📦 ออเดอร์: " . number_format($orders) . " รายการ\n";
$lineText .= "⏳ รอดำเนินการ: $pending รายการ\n";
$lineText .= "🛒 AOV: ฿" . number_format($avgOrder, 0) . "\n";
if ($topProducts) {
    $lineText .= "────────────────\n🏆 ขายดี:\n";
    foreach ($topProducts as $i => $p) {
        $lineText .= ($i+1) . ". {$p['name']} × {$p['qty']} ชิ้น\n";
    }
}

jsonOK([
    'period'       => $period,
    'kpi'          => ['revenue'=>(float)$revenue,'orders'=>(int)$orders,'pending'=>(int)$pending,'avg_order'=>$avgOrder],
    'by_platform'  => $byPlatform,
    'top_products' => $topProducts,
    'trend'        => $trend,
    'line_text'    => $lineText,
]);
