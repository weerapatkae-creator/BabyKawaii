<?php
/**
 * BabyKawaii API — Products
 * GET /api/v1/products.php              → list products (for LINE bot menu)
 * GET /api/v1/products.php?id=N         → product detail with stock
 * GET /api/v1/products.php?type=bundle  → bundles only
 */
require_once __DIR__ . '/auth.php';
requireApiKey();

if (method() !== 'GET') jsonErr('Method not allowed', 405);

$pdo  = getDB();
$type = $_GET['type'] ?? '';
$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id) {
    $stmt = $pdo->prepare("
        SELECT p.*, cat.name as cat_name, cat.icon as cat_icon
        FROM products p LEFT JOIN categories cat ON cat.id=p.category_id
        WHERE p.id=? AND p.status != 'inactive'");
    $stmt->execute([$id]);
    $prod = $stmt->fetch();
    if (!$prod) jsonErr('Product not found', 404);

    if ($prod['product_type'] === 'bundle') {
        $bi = $pdo->prepare("
            SELECT bi.*, p2.name as component_name, COALESCE(s.quantity,0) as stock
            FROM bundle_items bi JOIN products p2 ON p2.id=bi.product_id
            LEFT JOIN stock s ON s.product_id=bi.product_id AND s.size=bi.size AND s.color=bi.color
            WHERE bi.bundle_id=?");
        $bi->execute([$id]);
        $prod['bundle_items'] = $bi->fetchAll();
        // virtual stock
        $min = PHP_INT_MAX;
        foreach ($prod['bundle_items'] as $b) {
            $min = min($min, $b['bi_qty'] > 0 ? floor($b['stock'] / $b['bi_qty']) : 0);
        }
        $prod['virtual_stock'] = ($min === PHP_INT_MAX) ? 0 : $min;
    } else {
        $s = $pdo->prepare("SELECT size, color, quantity FROM stock WHERE product_id=? ORDER BY FIELD(size,'Premature','NB','0-3M','3-6M','6-9M','9-12M','12-18M','18-24M','Free Size'),color");
        $s->execute([$id]);
        $prod['stock'] = $s->fetchAll();
        $prod['total_stock'] = array_sum(array_column($prod['stock'], 'quantity'));
    }
    jsonOK($prod);
}

// List
$where = ["p.status = 'active'"]; $params = [];
if ($type) { $where[] = "p.product_type = ?"; $params[] = $type; }
$whereSQL = 'WHERE ' . implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT p.id, p.name, p.sku, p.product_type, p.selling_price,
           p.description, p.main_image,
           cat.name as cat_name, cat.icon as cat_icon,
           COALESCE(SUM(s.quantity),0) as total_stock
    FROM products p
    LEFT JOIN categories cat ON cat.id=p.category_id
    LEFT JOIN stock s ON s.product_id=p.id
    $whereSQL
    GROUP BY p.id ORDER BY p.name");
$stmt->execute($params);
$products = $stmt->fetchAll();

// cost_price ไม่ส่งออกไปใน list response เด็ดขาด
foreach ($products as &$p) { unset($p['cost_price']); }
unset($p);

// numbered menu for bot
$menu = [];
foreach ($products as $i => $p) {
    $menu[] = [
        'no'    => $i + 1,
        'id'    => $p['id'],
        'name'  => $p['name'],
        'price' => (float)$p['selling_price'],
        'type'  => $p['product_type'],
        'stock' => (int)$p['total_stock'],
    ];
}

jsonOK(['products' => $products, 'menu' => $menu]);
