<?php
/**
 * BabyKawaii API — Bot Catalog (Chatbot use only)
 * GET /api/v1/bot-catalog.php
 *
 * คืนข้อมูลสินค้า + สต็อก ให้ Gemini/AI chatbot ใช้
 * ⚠️  ไม่มี cost_price ในผลลัพธ์เด็ดขาด
 * ⚠️  ข้อมูลเป็น text-friendly สำหรับ prompt injection
 */
require_once __DIR__ . '/auth.php';
requireApiKey();

if (method() !== 'GET') jsonErr('Method not allowed', 405);

$pdo = getDB();

// ── ดึงสินค้าทั้งหมด (ไม่มีต้นทุน) ─────────────────────────────────────────
$products = $pdo->query("
    SELECT p.id, p.sku, p.name, p.selling_price, p.description,
           cat.name as category
    FROM products p
    LEFT JOIN categories cat ON cat.id = p.category_id
    WHERE p.status = 'active'
    ORDER BY p.name
")->fetchAll();

// ── ดึงสต็อก ──────────────────────────────────────────────────────────────────
$stockRows = $pdo->query("
    SELECT s.product_id, s.size, s.color, s.quantity
    FROM stock s
    JOIN products p ON p.id = s.product_id
    WHERE p.status = 'active' AND p.product_type = 'single'
    ORDER BY s.product_id,
             FIELD(s.size,'Premature','NB','0-3M','3-6M','6-9M','9-12M','12-18M','18-24M','Free Size'),
             s.color
")->fetchAll();

// group stock by product_id
$stockMap = [];
foreach ($stockRows as $r) {
    $stockMap[$r['product_id']][] = [
        'size'  => $r['size'],
        'color' => $r['color'],
        'qty'   => (int)$r['quantity'],
    ];
}

// ── Build catalog ─────────────────────────────────────────────────────────────
$catalog = [];
foreach ($products as $p) {
    $pid     = $p['id'];
    $stock   = $stockMap[$pid] ?? [];
    $hasStock = array_filter($stock, fn($s) => $s['qty'] > 0);

    $catalog[] = [
        'id'          => $pid,
        'sku'         => $p['sku'],
        'name'        => $p['name'],
        'category'    => $p['category'] ?? '',
        'price'       => (float)$p['selling_price'],   // selling price only
        'description' => $p['description'] ?? '',
        'in_stock'    => count($hasStock) > 0,
        'stock'       => $stock,   // [{size, color, qty}]
    ];
}

// ── Build compact text for AI prompt ─────────────────────────────────────────
$lines = [];
foreach ($catalog as $p) {
    $lines[] = "- [{$p['sku']}] {$p['name']} ราคา ฿" . number_format($p['price'], 0);
    foreach ($p['stock'] as $s) {
        $status = $s['qty'] > 0 ? "✅ {$s['qty']} ชิ้น" : "❌ หมด";
        $lines[] = "  ไซต์ {$s['size']} สี {$s['color']}: $status";
    }
}
$promptText = implode("\n", $lines);

jsonOK([
    'catalog'     => $catalog,
    'prompt_text' => $promptText,   // พร้อมใส่ใน Gemini prompt ได้เลย
    'updated_at'  => date('Y-m-d H:i:s'),
]);
