<?php
/**
 * BabyKawaii API — Stock
 * GET /api/v1/stock.php              → all stock
 * GET /api/v1/stock.php?filter=low   → low stock items
 * GET /api/v1/stock.php?filter=out   → out of stock
 * GET /api/v1/stock.php?product=N    → stock for one product
 */
require_once __DIR__ . '/auth.php';
requireApiKey();

if (method() !== 'GET') jsonErr('Method not allowed', 405);

$pdo    = getDB();
$filter = $_GET['filter'] ?? '';
$prodId = isset($_GET['product']) ? (int)$_GET['product'] : 0;

$where = ["p.status != 'inactive'", "p.product_type = 'single'"]; $params = [];
if ($prodId)         { $where[] = "s.product_id = ?";              $params[] = $prodId; }
if ($filter === 'low') { $where[] = "s.quantity > 0 AND s.quantity <= s.min_alert"; }
if ($filter === 'out') { $where[] = "s.quantity = 0"; }

$whereSQL = 'WHERE ' . implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT s.id, s.product_id, p.name as product_name, p.sku,
           s.size, s.color, s.quantity, s.min_alert,
           cat.name as cat_name, cat.icon as cat_icon
    FROM stock s
    JOIN products p ON p.id = s.product_id
    LEFT JOIN categories cat ON cat.id = p.category_id
    $whereSQL
    ORDER BY s.quantity ASC, p.name, s.size, s.color");
$stmt->execute($params);
$items = $stmt->fetchAll();

$summary = [
    'total_items'   => count($items),
    'total_qty'     => array_sum(array_column($items, 'quantity')),
    'low_count'     => count(array_filter($items, fn($i) => $i['quantity'] > 0 && $i['quantity'] <= $i['min_alert'])),
    'out_count'     => count(array_filter($items, fn($i) => $i['quantity'] == 0)),
];

// Build LINE-friendly alert message
$alerts = [];
foreach (array_filter($items, fn($i) => $i['quantity'] <= $i['min_alert']) as $item) {
    $status = $item['quantity'] == 0 ? '🔴 หมด' : '🟡 ใกล้หมด';
    $alerts[] = "$status {$item['product_name']} {$item['size']}/{$item['color']}: {$item['quantity']} ชิ้น";
}
$summary['alert_text'] = $alerts ? "⚠️ แจ้งเตือนสต็อก\n" . implode("\n", $alerts) : "✅ สต็อกปกติทุกรายการ";

jsonOK(['items' => $items, 'summary' => $summary]);
