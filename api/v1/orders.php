<?php
/**
 * BabyKawaii API — Orders
 * GET  /api/v1/orders.php         → list orders
 * GET  /api/v1/orders.php?id=N    → single order
 * POST /api/v1/orders.php         → create order (from LINE bot)
 * PATCH /api/v1/orders.php?id=N   → update status / tracking
 */
require_once __DIR__ . '/auth.php';
requireApiKey();

$pdo = getDB();

// ── GET ───────────────────────────────────────────────────────────────────────
if (method() === 'GET') {
    if (isset($_GET['id'])) {
        $stmt = $pdo->prepare("
            SELECT o.*, p.name as platform_name, p.icon as platform_icon
            FROM orders o LEFT JOIN platforms p ON p.id = o.platform_id
            WHERE o.id = ?");
        $stmt->execute([(int)$_GET['id']]);
        $order = $stmt->fetch();
        if (!$order) jsonErr('Order not found', 404);

        $items = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $items->execute([$order['id']]);
        $order['items'] = $items->fetchAll();
        jsonOK($order);
    }

    $status   = $_GET['status']   ?? '';
    $platform = $_GET['platform'] ?? '';
    $limit    = min((int)($_GET['limit'] ?? 20), 100);
    $page     = max(1, (int)($_GET['page'] ?? 1));
    $offset   = ($page - 1) * $limit;

    $where = []; $params = [];
    if ($status)   { $where[] = "o.order_status = ?";  $params[] = $status; }
    if ($platform) { $where[] = "p.slug = ?";           $params[] = $platform; }
    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $total = $pdo->prepare("SELECT COUNT(*) FROM orders o LEFT JOIN platforms p ON p.id=o.platform_id $whereSQL");
    $total->execute($params);

    $stmt = $pdo->prepare("
        SELECT o.id, o.order_number, o.customer_name, o.customer_phone,
               o.total_amount, o.order_status, o.payment_status,
               o.tracking_number, o.order_date,
               p.name as platform_name, p.icon as platform_icon
        FROM orders o LEFT JOIN platforms p ON p.id = o.platform_id
        $whereSQL ORDER BY o.order_date DESC LIMIT $limit OFFSET $offset");
    $stmt->execute($params);

    jsonOK([
        'orders'    => $stmt->fetchAll(),
        'total'     => (int)$total->fetchColumn(),
        'page'      => $page,
        'limit'     => $limit,
    ]);
}

// ── POST (create) ─────────────────────────────────────────────────────────────
if (method() === 'POST') {
    $body = getBody();

    $customerName  = trim($body['customer_name'] ?? '');
    $customerPhone = trim($body['customer_phone'] ?? '');
    $customerAddr  = trim($body['customer_address'] ?? '');
    $platformSlug  = $body['platform'] ?? 'facebook'; // default: Facebook Page DM
    $items         = $body['items'] ?? [];
    $notes         = $body['notes'] ?? '';

    if (!$customerName || empty($items)) jsonErr('customer_name and items are required');

    $platform = $pdo->prepare("SELECT id FROM platforms WHERE slug = ?");
    $platform->execute([$platformSlug]);
    $platformId = ($platform->fetchColumn()) ?: null;

    // Generate order number
    $orderNum = 'BK-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

    $subtotal = 0;
    $orderItems = [];
    foreach ($items as $item) {
        $pid     = (int)($item['product_id'] ?? 0);
        $qty     = (int)($item['quantity'] ?? 1);
        $size    = $item['size'] ?? '';
        $color   = $item['color'] ?? '';

        $prod = $pdo->prepare("SELECT id, name, selling_price, product_type FROM products WHERE id = ?");
        $prod->execute([$pid]);
        $prod = $prod->fetch();
        if (!$prod) jsonErr("Product ID $pid not found");

        $lineTotal = $prod['selling_price'] * $qty;
        $subtotal += $lineTotal;
        $orderItems[] = [
            'product_id'   => $pid,
            'product_name' => $prod['name'],
            'size'         => $size,
            'color'        => $color,
            'quantity'     => $qty,
            'unit_price'   => $prod['selling_price'],
            'total_price'  => $lineTotal,
        ];
    }

    $shippingCost = (float)($body['shipping_cost'] ?? getSetting('shipping_base', 50));
    $total = $subtotal + $shippingCost;

    $pdo->beginTransaction();
    try {
        $pdo->prepare("INSERT INTO orders
            (order_number, platform_id, customer_name, customer_phone, customer_address,
             shipping_cost, subtotal, total_amount, notes, order_status, payment_status, order_date)
            VALUES (?,?,?,?,?,?,?,?,?,'pending','pending',NOW())")
            ->execute([$orderNum, $platformId, $customerName, $customerPhone, $customerAddr,
                       $shippingCost, $subtotal, $total, $notes]);
        $orderId = $pdo->lastInsertId();

        foreach ($orderItems as $oi) {
            $pdo->prepare("INSERT INTO order_items
                (order_id, product_id, product_name, size, color, quantity, unit_price, total_price)
                VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$orderId, $oi['product_id'], $oi['product_name'], $oi['size'],
                           $oi['color'], $oi['quantity'], $oi['unit_price'], $oi['total_price']]);
        }

        // Deduct stock
        foreach ($orderItems as $oi) {
            $prod = $pdo->prepare("SELECT product_type FROM products WHERE id=?");
            $prod->execute([$oi['product_id']]);
            $prod = $prod->fetch();

            if ($prod && $prod['product_type'] === 'bundle') {
                $bItems = $pdo->prepare("SELECT * FROM bundle_items WHERE bundle_id=?");
                $bItems->execute([$oi['product_id']]);
                foreach ($bItems->fetchAll() as $bi) {
                    $pdo->prepare("UPDATE stock SET quantity = GREATEST(0, quantity - ?)
                        WHERE product_id=? AND size=? AND color=?")
                        ->execute([$bi['quantity'] * $oi['quantity'], $bi['product_id'], $bi['size'], $bi['color']]);
                }
            } else {
                $pdo->prepare("UPDATE stock SET quantity = GREATEST(0, quantity - ?)
                    WHERE product_id=? AND size=? AND color=?")
                    ->execute([$oi['quantity'], $oi['product_id'], $oi['size'], $oi['color']]);
            }
        }

        $pdo->commit();

        // Trigger n8n notification webhook if configured
        $n8nUrl = getSetting('n8n_base_url', '');
        if ($n8nUrl) {
            $payload = json_encode(['event'=>'new_order','order_id'=>$orderId,'order_number'=>$orderNum,'customer'=>$customerName,'total'=>$total]);
            @file_get_contents("$n8nUrl/webhook/babykawaii-events", false,
                stream_context_create(['http'=>['method'=>'POST','header'=>'Content-Type: application/json','content'=>$payload,'timeout'=>3]]));
        }

        jsonOK(['order_id'=>$orderId,'order_number'=>$orderNum,'total'=>$total], 201);

    } catch (Exception $e) {
        $pdo->rollBack();
        jsonErr('Failed to create order: ' . $e->getMessage(), 500);
    }
}

// ── PATCH (update status/tracking) ───────────────────────────────────────────
if (method() === 'PATCH') {
    $id   = (int)($_GET['id'] ?? 0);
    $body = getBody();
    if (!$id) jsonErr('id required');

    $allowed = ['order_status','payment_status','tracking_number','notes'];
    $sets = []; $params = [];
    foreach ($allowed as $f) {
        if (isset($body[$f])) { $sets[] = "$f = ?"; $params[] = $body[$f]; }
    }
    if (empty($sets)) jsonErr('Nothing to update');
    $params[] = $id;
    $pdo->prepare("UPDATE orders SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);
    jsonOK(['updated' => $id]);
}

jsonErr('Method not allowed', 405);
