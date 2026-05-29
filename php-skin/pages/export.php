<?php
/**
 * BabyKawaii — CSV / Excel Export
 * Usage: ?type=orders   → export orders with filter params
 *        ?type=customers → export customer list
 *        ?type=stock    → export stock snapshot
 *        ?type=sales    → export daily sales summary
 */
require_once __DIR__ . '/../config/database.php';
requireLogin();

$pdo  = getDB();
$type = $_GET['type'] ?? 'orders';

/* ── helpers ─────────────────────────────────────────────────────────── */
function csvRow(array $cols): string {
    return implode(',', array_map(fn($v) => '"' . str_replace('"', '""', $v ?? '') . '"', $cols)) . "\r\n";
}
function sendCsv(string $filename, string $content): void {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM so Excel reads Thai correctly
    echo $content;
    exit;
}

/* ═══════════════════════════════════════════════════════════════════════
   ORDERS
═══════════════════════════════════════════════════════════════════════ */
if ($type === 'orders') {
    $statusFilter   = $_GET['status']    ?? '';
    $platformFilter = (int)($_GET['platform'] ?? 0);
    $search         = trim($_GET['q']    ?? '');
    $dateFrom       = $_GET['date_from'] ?? '';
    $dateTo         = $_GET['date_to']   ?? '';

    $where  = ['1=1'];
    $params = [];
    if ($statusFilter)   { $where[] = 'o.order_status = ?'; $params[] = $statusFilter; }
    if ($platformFilter) { $where[] = 'o.platform_id = ?';  $params[] = $platformFilter; }
    if ($search)         { $where[] = '(o.order_number LIKE ? OR o.customer_name LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
    if ($dateFrom)       { $where[] = 'DATE(o.order_date) >= ?'; $params[] = $dateFrom; }
    if ($dateTo)         { $where[] = 'DATE(o.order_date) <= ?'; $params[] = $dateTo; }

    $stmt = $pdo->prepare(
        "SELECT o.order_number, o.order_date, pl.name AS platform,
                o.customer_name, o.customer_phone, o.customer_address,
                o.order_status, o.payment_status, o.payment_method,
                o.subtotal, o.shipping_cost, o.discount_amount, o.total_amount,
                o.tracking_number, o.notes
         FROM orders o
         LEFT JOIN platforms pl ON pl.id = o.platform_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY o.order_date DESC"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $statusTH = [
        'pending'   => 'รอดำเนินการ',
        'confirmed' => 'ยืนยันแล้ว',
        'packing'   => 'กำลังแพ็ค',
        'shipped'   => 'จัดส่งแล้ว',
        'delivered' => 'ส่งถึงแล้ว',
        'cancelled' => 'ยกเลิก',
        'returned'  => 'คืนสินค้า',
    ];
    $payTH = ['pending' => 'รอชำระ', 'paid' => 'ชำระแล้ว', 'refunded' => 'คืนเงิน'];

    $out  = csvRow(['เลขออเดอร์','วันที่สั่ง','แพลตฟอร์ม','ชื่อลูกค้า','เบอร์โทร','ที่อยู่','สถานะออเดอร์','สถานะชำระ','วิธีชำระ','ยอดสินค้า','ค่าส่ง','ส่วนลด','ยอดรวม','เลขพัสดุ','หมายเหตุ']);
    foreach ($rows as $r) {
        $out .= csvRow([
            $r['order_number'],
            date('d/m/Y H:i', strtotime($r['order_date'])),
            $r['platform'] ?? '',
            $r['customer_name'],
            $r['customer_phone'],
            $r['customer_address'],
            $statusTH[$r['order_status']] ?? $r['order_status'],
            $payTH[$r['payment_status']] ?? $r['payment_status'],
            $r['payment_method'],
            number_format($r['subtotal'], 2),
            number_format($r['shipping_cost'], 2),
            number_format($r['discount_amount'], 2),
            number_format($r['total_amount'], 2),
            $r['tracking_number'],
            $r['notes'],
        ]);
    }
    sendCsv('orders_' . date('Ymd_His') . '.csv', $out);
}

/* ═══════════════════════════════════════════════════════════════════════
   CUSTOMERS
═══════════════════════════════════════════════════════════════════════ */
if ($type === 'customers') {
    $rows = $pdo->query(
        "SELECT c.name, c.phone, c.email, c.address,
                COUNT(o.id) AS total_orders,
                COALESCE(SUM(o.total_amount),0) AS lifetime_value,
                MAX(o.order_date) AS last_order
         FROM customers c
         LEFT JOIN orders o ON o.customer_phone = c.phone
         GROUP BY c.id
         ORDER BY lifetime_value DESC"
    )->fetchAll();

    $out = csvRow(['ชื่อ','เบอร์โทร','อีเมล','ที่อยู่','จำนวนออเดอร์','ยอดซื้อรวม','ออเดอร์ล่าสุด']);
    foreach ($rows as $r) {
        $out .= csvRow([
            $r['name'], $r['phone'], $r['email'] ?? '', $r['address'] ?? '',
            $r['total_orders'],
            number_format($r['lifetime_value'], 2),
            $r['last_order'] ? date('d/m/Y', strtotime($r['last_order'])) : '',
        ]);
    }
    sendCsv('customers_' . date('Ymd_His') . '.csv', $out);
}

/* ═══════════════════════════════════════════════════════════════════════
   PRODUCTS
═══════════════════════════════════════════════════════════════════════ */
if ($type === 'products') {
    $rows = $pdo->query(
        "SELECT p.sku, p.name, cat.name AS category,
                p.cost_price, p.selling_price,
                p.tags, p.status, p.description,
                COALESCE(SUM(s.quantity),0) AS total_stock
         FROM products p
         LEFT JOIN categories cat ON cat.id = p.category_id
         LEFT JOIN stock s ON s.product_id = p.id
         WHERE p.status != 'inactive'
         GROUP BY p.id
         ORDER BY cat.name, p.name"
    )->fetchAll();

    $statusTH = ['active'=>'ขายอยู่','out_of_stock'=>'หมดสต็อก','inactive'=>'ปิดการขาย'];

    $out = csvRow(['sku','name','category','cost_price','selling_price','tags','status','description','total_stock']);
    foreach ($rows as $r) {
        $out .= csvRow([
            $r['sku'] ?? '',
            $r['name'],
            $r['category'] ?? '',
            number_format($r['cost_price'], 2),
            number_format($r['selling_price'], 2),
            $r['tags'] ?? '',
            $r['status'],
            $r['description'] ?? '',
            $r['total_stock'],
        ]);
    }
    sendCsv('products_' . date('Ymd_His') . '.csv', $out);
}

/* ═══════════════════════════════════════════════════════════════════════
   STOCK
═══════════════════════════════════════════════════════════════════════ */
if ($type === 'stock') {
    $rows = $pdo->query(
        "SELECT p.sku, p.name AS product_name, cat.name AS category,
                s.size, s.color, s.quantity, s.min_alert,
                p.cost_price, p.selling_price,
                (s.quantity * p.cost_price) AS stock_value
         FROM stock s
         JOIN products p ON p.id = s.product_id
         LEFT JOIN categories cat ON cat.id = p.category_id
         WHERE p.status != 'inactive'
         ORDER BY cat.name, p.name,
                  FIELD(s.size,'Premature','NB','0-3M','3-6M','6-9M','9-12M','12-18M','18-24M','Free Size'),
                  s.color"
    )->fetchAll();

    $out = csvRow(['sku','product_name','category','size','color','quantity','min_alert','cost_price','selling_price','stock_value']);
    foreach ($rows as $r) {
        $out .= csvRow([
            $r['sku'] ?? '',
            $r['product_name'],
            $r['category'] ?? '',
            $r['size'],
            $r['color'],
            $r['quantity'],
            $r['min_alert'],
            number_format($r['cost_price'], 2),
            number_format($r['selling_price'], 2),
            number_format($r['stock_value'], 2),
        ]);
    }
    sendCsv('stock_' . date('Ymd_His') . '.csv', $out);
}

/* ═══════════════════════════════════════════════════════════════════════
   SALES SUMMARY
═══════════════════════════════════════════════════════════════════════ */
if ($type === 'sales') {
    $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
    $dateTo   = $_GET['date_to']   ?? date('Y-m-d');

    $rows = $pdo->prepare(
        "SELECT DATE(order_date) AS day,
                COUNT(*) AS orders,
                SUM(total_amount) AS revenue,
                SUM(shipping_cost) AS shipping,
                SUM(discount_amount) AS discount
         FROM orders
         WHERE order_status NOT IN ('cancelled','returned')
           AND DATE(order_date) BETWEEN ? AND ?
         GROUP BY day ORDER BY day"
    );
    $rows->execute([$dateFrom, $dateTo]);
    $rows = $rows->fetchAll();

    $out = csvRow(['วันที่','จำนวนออเดอร์','ยอดขาย','ค่าส่ง','ส่วนลด']);
    foreach ($rows as $r) {
        $out .= csvRow([
            date('d/m/Y', strtotime($r['day'])),
            $r['orders'],
            number_format($r['revenue'], 2),
            number_format($r['shipping'], 2),
            number_format($r['discount'], 2),
        ]);
    }
    sendCsv('sales_' . date('Ymd_His') . '.csv', $out);
}

// Fallback
header('Location: ' . SITE_URL . '/pages/orders.php');
exit;
