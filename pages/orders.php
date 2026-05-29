<?php
require_once __DIR__ . '/../config/database.php';
requireLogin();
$pdo = getDB();
// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $orderId   = (int)$_POST['order_id'];
    $newStatus = $_POST['new_status'];
    $tracking  = trim($_POST['tracking_number'] ?? '');

    $pdo->prepare("UPDATE orders SET order_status=?, tracking_number=COALESCE(NULLIF(?,''), tracking_number), updated_at=NOW() WHERE id=?")->execute([$newStatus, $tracking, $orderId]);

    // If shipped/delivered, deduct stock (only if not already deducted at creation)
    if (in_array($newStatus, ['shipped', 'delivered'])) {
        $alreadyRow = $pdo->prepare("SELECT stock_deducted FROM orders WHERE id=?");
        $alreadyRow->execute([$orderId]);
        if (!(int)$alreadyRow->fetchColumn()) {
            $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
            $stmt->execute([$orderId]);
            $items = $stmt->fetchAll();
            foreach ($items as $item) {
                if ($item['product_id']) {
                    $pdo->prepare("UPDATE stock SET quantity = GREATEST(0, quantity - ?) WHERE product_id=? AND size=?")->execute([$item['quantity'], $item['product_id'], $item['size']]);
                }
            }
            $pdo->prepare("UPDATE orders SET stock_deducted=1 WHERE id=?")->execute([$orderId]);
        }
    }

    // ── Notify n8n / LINE ─────────────────────────────────────────────
    $orderRow = $pdo->prepare("SELECT order_number, customer_name, total_amount FROM orders WHERE id=?");
    $orderRow->execute([$orderId]);
    $orderRow = $orderRow->fetch();
    if ($orderRow) {
        if ($newStatus === 'shipped' && $tracking) {
            $lineMsg = "🚚 จัดส่งออเดอร์แล้ว!\n"
                . "#{$orderRow['order_number']}\n"
                . "👤 {$orderRow['customer_name']}\n"
                . "📦 เลขพัสดุ: {$tracking}\n"
                . "⏰ " . date('d/m/Y H:i') . " น.\n"
                . "👉 " . SITE_URL . "/pages/orders.php?id={$orderId}";
            sendLineNotify($lineMsg);
        }
        triggerN8n('order_status_changed', [
            'order_id'     => $orderId,
            'order_number' => $orderRow['order_number'],
            'customer'     => $orderRow['customer_name'],
            'total'        => $orderRow['total_amount'],
            'new_status'   => $newStatus,
            'tracking'     => $tracking,
        ]);
    }

    header('Location: ' . SITE_URL . '/pages/orders.php?msg=updated');
    exit;
}

// Handle new order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_order'])) {
    $platformId    = (int)$_POST['platform_id'];
    $customerName  = trim($_POST['customer_name']);
    $customerPhone = trim($_POST['customer_phone'] ?? '');
    $address       = trim($_POST['customer_address'] ?? '');
    $shipping      = (float)($_POST['shipping_cost'] ?? 0);
    $discount      = (float)($_POST['discount_amount'] ?? 0);
    $payMethod     = $_POST['payment_method'] ?? '';
    $notes         = trim($_POST['notes'] ?? '');
    $orderDate     = $_POST['order_date'] ?: date('Y-m-d H:i:s');

    // Generate order number
    $prefix = 'BK' . date('Ymd');
    $lastOrder = $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(order_date) = CURDATE()")->fetchColumn();
    $orderNum = $prefix . str_pad($lastOrder + 1, 3, '0', STR_PAD_LEFT);

    // Calculate subtotal from items
    $items = json_decode($_POST['items_json'] ?? '[]', true);
    $subtotal = 0;
    foreach ($items as $item) { $subtotal += (float)$item['price'] * (int)$item['qty']; }
    $total = $subtotal + $shipping - $discount;

    $pdo->prepare("INSERT INTO orders (order_number, platform_id, customer_name, customer_phone, customer_address, shipping_cost, discount_amount, subtotal, total_amount, payment_method, notes, order_status, payment_status, order_date)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,'pending','pending',?)")->execute([
        $orderNum, $platformId ?: null, $customerName, $customerPhone, $address,
        $shipping, $discount, $subtotal, $total, $payMethod, $notes, $orderDate
    ]);
    $newOrderId = $pdo->lastInsertId();

    // Insert items
    foreach ($items as $item) {
        $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, size, color, quantity, unit_price, total_price) VALUES (?,?,?,?,?,?,?,?)")->execute([
            $newOrderId, $item['product_id'] ?: null, $item['name'], $item['size'] ?? '', $item['color'] ?? '', $item['qty'], $item['price'], $item['price'] * $item['qty']
        ]);
    }

    // ── Notify n8n / LINE ─────────────────────────────────────────────
    triggerN8n('new_order', [
        'order_id'     => $newOrderId,
        'order_number' => $orderNum,
        'customer'     => $customerName,
        'total'        => $total,
        'platform'     => 'admin',
    ]);
    $lineMsg = "🛍️ ออเดอร์ใหม่!\n"
        . "#{$orderNum}\n"
        . "👤 {$customerName}\n"
        . "💰 ฿" . number_format($total, 0) . "\n"
        . "⏰ " . date('d/m/Y H:i') . " น.\n"
        . "👉 " . SITE_URL . "/pages/orders.php?id={$newOrderId}";
    sendLineNotify($lineMsg);

    header('Location: ' . SITE_URL . '/pages/orders.php?msg=added');
    exit;
}


$pageTitle = 'ออเดอร์ / คำสั่งซื้อ';
require_once __DIR__ . '/../includes/header.php';

// Filters
$statusFilter = $_GET['status'] ?? '';
$platformFilter = (int)($_GET['platform'] ?? 0);
$search = trim($_GET['q'] ?? '');
$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to'] ?? '';

$where = ['1=1'];
$params = [];
if ($statusFilter)   { $where[] = 'o.order_status = ?'; $params[] = $statusFilter; }
if ($platformFilter) { $where[] = 'o.platform_id = ?';  $params[] = $platformFilter; }
if ($search)         { $where[] = '(o.order_number LIKE ? OR o.customer_name LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($dateFrom)       { $where[] = 'DATE(o.order_date) >= ?'; $params[] = $dateFrom; }
if ($dateTo)         { $where[] = 'DATE(o.order_date) <= ?'; $params[] = $dateTo; }

$stmt = $pdo->prepare("SELECT o.*, pl.name as platform_name, pl.icon as platform_icon, pl.color as platform_color,
    (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
    FROM orders o
    LEFT JOIN platforms pl ON pl.id = o.platform_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY o.order_date DESC LIMIT 200");
$stmt->execute($params);
$orders = $stmt->fetchAll();

$platforms = $pdo->query("SELECT * FROM platforms WHERE is_active=1 ORDER BY name")->fetchAll();
$products  = $pdo->query("SELECT id, name, sku, selling_price FROM products WHERE status='active' ORDER BY name")->fetchAll();

$statusList = ['pending'=>'⏳ รอดำเนินการ','confirmed'=>'✅ ยืนยันแล้ว','packing'=>'📦 กำลังแพ็ค','shipped'=>'🚚 จัดส่งแล้ว','delivered'=>'🏠 ส่งถึงแล้ว','cancelled'=>'❌ ยกเลิก','returned'=>'↩️ คืนสินค้า'];
$statusTH   = ['pending'=>'รอดำเนิน','confirmed'=>'ยืนยันแล้ว','packing'=>'กำลังแพ็ค','shipped'=>'จัดส่งแล้ว','delivered'=>'ส่งถึงแล้ว','cancelled'=>'ยกเลิก','returned'=>'คืนสินค้า'];
?>

<div class="container-fluid fade-in">
    <div class="page-header">
        <div>
            <h1 class="page-title">🛍️ ออเดอร์ / คำสั่งซื้อ</h1>
            <p class="page-subtitle">จัดการออเดอร์ทั้งหมดจากทุกแพลตฟอร์ม</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= SITE_URL ?>/pages/export.php?type=orders<?= $statusFilter ? '&status='.$statusFilter : '' ?><?= $search ? '&q='.urlencode($search) : '' ?><?= $dateFrom ? '&date_from='.$dateFrom : '' ?><?= $dateTo ? '&date_to='.$dateTo : '' ?>" class="btn btn-outline-success btn-sm">
                <i class="fas fa-file-csv me-1"></i> Export CSV
            </a>
            <button id="btnPrintSelected" class="btn btn-outline-pink btn-sm d-none" onclick="printSelected()">
                <i class="fas fa-print me-1"></i> พิมพ์ที่เลือก
            </button>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addOrderModal">
                <i class="fas fa-plus me-1"></i> เพิ่มออเดอร์
            </button>
        </div>
    </div>

    <?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success alert-auto">✅ <?= $_GET['msg'] === 'added' ? 'เพิ่มออเดอร์เรียบร้อย' : 'อัปเดตสถานะเรียบร้อย' ?></div>
    <?php endif; ?>

    <!-- Status filter tabs -->
    <div class="d-flex gap-2 mb-3 flex-wrap">
        <a href="?" class="btn btn-sm <?= !$statusFilter ? 'btn-primary' : 'btn-outline-secondary' ?>">ทั้งหมด (<?= count($orders) ?>)</a>
        <?php foreach ($statusList as $k => $v):
            $cnt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE order_status=?"); $cnt->execute([$k]); $n = $cnt->fetchColumn();
        ?>
        <a href="?status=<?= $k ?>" class="btn btn-sm <?= $statusFilter===$k ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= $v ?> (<?= $n ?>)</a>
        <?php endforeach; ?>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body py-3">
            <form method="GET" class="row g-2 align-items-end">
                <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
                <div class="col-md-3">
                    <input type="text" name="q" class="form-control form-control-sm" placeholder="🔍 ค้นหาออเดอร์, ชื่อลูกค้า..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2">
                    <select name="platform" class="form-select form-select-sm">
                        <option value="">ทุกแพลตฟอร์ม</option>
                        <?php foreach ($platforms as $pf): ?>
                        <option value="<?= $pf['id'] ?>" <?= $platformFilter==$pf['id']?'selected':'' ?>><?= $pf['icon'] ?> <?= htmlspecialchars($pf['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_from" class="form-control form-control-sm" value="<?= $dateFrom ?>">
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_to" class="form-control form-control-sm" value="<?= $dateTo ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm">กรอง</button>
                    <a href="?" class="btn btn-outline-secondary btn-sm">ล้าง</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Orders Table -->
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th style="width:36px;"><input type="checkbox" id="selectAll" title="เลือกทั้งหมด" onchange="toggleSelectAll(this)"></th>
                        <th>เลขออเดอร์</th><th>แพลตฟอร์ม</th><th>ลูกค้า</th>
                        <th>สินค้า</th><th class="text-end">ยอดรวม</th>
                        <th>การชำระ</th><th>สถานะ</th><th>วันที่</th><th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><input type="checkbox" class="order-check" value="<?= $order['id'] ?>" onchange="updateBatchBar()"></td>
                        <td>
                            <div style="font-weight:700;color:var(--pink-dark);"><?= htmlspecialchars($order['order_number']) ?></div>
                            <?php if ($order['tracking_number']): ?>
                            <div style="font-size:0.72rem;color:var(--text-muted);"><i class="fas fa-truck"></i> <?= htmlspecialchars($order['tracking_number']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="background:<?= htmlspecialchars($order['platform_color'] ?? '#666') ?>;color:#fff;padding:3px 10px;border-radius:12px;font-size:0.75rem;">
                                <?= $order['platform_icon'] ?? '🛒' ?> <?= htmlspecialchars($order['platform_name'] ?? 'ไม่ระบุ') ?>
                            </span>
                        </td>
                        <td>
                            <div style="font-weight:500;"><?= htmlspecialchars($order['customer_name'] ?? '-') ?></div>
                            <div style="font-size:0.72rem;color:var(--text-muted);"><?= htmlspecialchars($order['customer_phone'] ?? '') ?></div>
                        </td>
                        <td><span class="badge bg-light text-dark"><?= $order['item_count'] ?> รายการ</span></td>
                        <td class="text-end">
                            <div style="font-weight:700;"><?= formatPrice($order['total_amount']) ?></div>
                            <?php if ($order['shipping_cost'] > 0): ?>
                            <div style="font-size:0.72rem;color:var(--text-muted);">+ค่าส่ง <?= formatPrice($order['shipping_cost']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php $payClass = $order['payment_status']==='paid'?'badge-active':($order['payment_status']==='refunded'?'badge-out':'badge-pending'); ?>
                            <span class="badge-status <?= $payClass ?>"><?= ['pending'=>'รอชำระ','paid'=>'ชำระแล้ว','refunded'=>'คืนเงิน'][$order['payment_status']] ?></span>
                        </td>
                        <td><span class="badge-status badge-<?= $order['order_status'] ?>"><?= $statusTH[$order['order_status']] ?></span></td>
                        <td style="font-size:0.8rem;white-space:nowrap;"><?= formatDateTH($order['order_date']) ?></td>
                        <td class="text-nowrap">
                            <a href="<?= SITE_URL ?>/pages/order-print.php?id=<?= $order['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="พิมพ์ใบส่งของ">
                                <i class="fas fa-print"></i>
                            </a>
                            <button class="btn btn-sm btn-outline-primary ms-1" onclick="openStatusModal(<?= htmlspecialchars(json_encode($order)) ?>)" title="แก้ไขสถานะ">
                                <i class="fas fa-edit"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($orders)): ?>
                    <tr><td colspan="10" class="text-center py-5 text-muted">ไม่มีออเดอร์</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" id="statusForm">
            <input type="hidden" name="update_status" value="1">
            <input type="hidden" name="order_id" id="modalOrderId">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">🔄 อัปเดตสถานะออเดอร์</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="modalOrderInfo" class="alert alert-light mb-3"></div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">สถานะใหม่</label>
                            <select name="new_status" id="modalStatus" class="form-select" onchange="onStatusChange(this.value)">
                                <?php foreach ($statusList as $k => $v): ?>
                                <option value="<?= $k ?>"><?= $v ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6" id="trackingGroup">
                            <label class="form-label fw-semibold">🚚 เลขพัสดุ</label>
                            <div class="input-group">
                                <input type="text" name="tracking_number" id="modalTracking" class="form-control" placeholder="เช่น TH0000000000, EF000000000TH">
                                <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('ocrInput').click()" title="📷 สแกนจากรูปใบส่ง">
                                    📷
                                </button>
                            </div>
                            <input type="file" id="ocrInput" accept="image/*" capture="environment" style="display:none" onchange="runOCR(this)">
                            <div id="ocrStatus" class="form-text mt-1" style="min-height:1.2em;"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <div>
                        <a id="modalPrintLink" href="#" target="_blank" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-print me-1"></i> พิมพ์ใบส่งของ
                        </a>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary">✅ บันทึก</button>
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Add Order Modal -->
<div class="modal fade" id="addOrderModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST">
            <input type="hidden" name="add_order" value="1">
            <input type="hidden" name="items_json" id="itemsJson" value="[]">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">➕ เพิ่มออเดอร์ใหม่</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">แพลตฟอร์ม</label>
                            <select name="platform_id" class="form-select">
                                <option value="">-- เลือก --</option>
                                <?php foreach ($platforms as $pf): ?>
                                <option value="<?= $pf['id'] ?>" <?= $pf['slug']==='facebook' ? 'selected' : '' ?>><?= $pf['icon'] ?> <?= htmlspecialchars($pf['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">วันที่สั่งซื้อ</label>
                            <input type="datetime-local" name="order_date" class="form-control" value="<?= date('Y-m-d\TH:i') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">ชื่อลูกค้า <span class="text-danger">*</span></label>
                            <input type="text" name="customer_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">เบอร์โทร</label>
                            <input type="text" name="customer_phone" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">ที่อยู่จัดส่ง</label>
                            <textarea name="customer_address" class="form-control" rows="2"></textarea>
                        </div>
                        <!-- Items -->
                        <div class="col-12">
                            <label class="form-label">รายการสินค้า</label>
                            <div id="orderItems"></div>
                            <button type="button" class="btn btn-outline-pink btn-sm mt-2" onclick="addOrderItem()"><i class="fas fa-plus"></i> เพิ่มสินค้า</button>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">ค่าส่ง</label>
                            <input type="number" name="shipping_cost" id="shippingCost" class="form-control" value="50" min="0" oninput="calcTotal()">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">ส่วนลด</label>
                            <input type="number" name="discount_amount" id="discountAmount" class="form-control" value="0" min="0" oninput="calcTotal()">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">วิธีชำระเงิน</label>
                            <select name="payment_method" class="form-select">
                                <option value="โอนเงิน">โอนเงิน</option>
                                <option value="เก็บเงินปลายทาง">เก็บเงินปลายทาง</option>
                                <option value="บัตรเครดิต">บัตรเครดิต</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="alert alert-light text-end">
                                ยอดรวมทั้งหมด: <strong id="totalDisplay" style="font-size:1.2rem;color:var(--pink-dark);">฿0.00</strong>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">หมายเหตุ</label>
                            <input type="text" name="notes" class="form-control" placeholder="หมายเหตุเพิ่มเติม">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary" onclick="prepareItems()">✅ บันทึกออเดอร์</button>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
const PRODUCTS = <?= json_encode($products) ?>;
const SIZES = ['Premature','NB','0-3M','3-6M','6-9M','9-12M','12-18M','18-24M','Free Size'];
let orderItems = [];

const SITE_URL = '<?= SITE_URL ?>';

function openStatusModal(order) {
    document.getElementById('modalOrderId').value = order.id;
    document.getElementById('modalStatus').value = order.order_status;
    document.getElementById('modalTracking').value = order.tracking_number || '';
    document.getElementById('modalOrderInfo').innerHTML =
        `<strong>${order.order_number}</strong> &mdash; ${order.customer_name || ''} &mdash; ${order.total_amount ? '฿' + parseFloat(order.total_amount).toLocaleString('th-TH') : ''}`;
    document.getElementById('modalPrintLink').href = SITE_URL + '/pages/order-print.php?id=' + order.id;
    // Reset OCR status
    const ocrStatus = document.getElementById('ocrStatus');
    if (ocrStatus) ocrStatus.innerHTML = '';
    document.getElementById('ocrInput').value = '';
    onStatusChange(order.order_status);
    bootstrap.Modal.getOrCreateInstance(document.getElementById('statusModal')).show();
}

function onStatusChange(val) {
    // Highlight tracking field when status is shipped
    const tg = document.getElementById('trackingGroup');
    if (val === 'shipped' || val === 'delivered') {
        tg.querySelector('label').innerHTML = '🚚 เลขพัสดุ <span class="text-danger">*</span>';
        tg.querySelector('.form-control').style.borderColor = 'var(--pink)';
    } else {
        tg.querySelector('label').innerHTML = '🚚 เลขพัสดุ';
        tg.querySelector('.form-control').style.borderColor = '';
    }
}


function toggleSelectAll(cb) {
    document.querySelectorAll('.order-check').forEach(c => c.checked = cb.checked);
    updateBatchBar();
}
function updateBatchBar() {
    const checked = document.querySelectorAll('.order-check:checked');
    const btn = document.getElementById('btnPrintSelected');
    btn.classList.toggle('d-none', checked.length === 0);
    btn.textContent = checked.length ? `🖨️ พิมพ์ ${checked.length} ใบ` : '';
}
function printSelected() {
    const ids = [...document.querySelectorAll('.order-check:checked')].map(c => c.value).join(',');
    if (ids) window.open(SITE_URL + '/pages/order-print.php?ids=' + ids, '_blank');
}

function addOrderItem() {
    const div = document.createElement('div');
    div.className = 'row g-2 mb-2 order-item-row';
    div.innerHTML = `
        <div class="col-md-4">
            <select class="form-select form-select-sm item-product" onchange="updateItemPrice(this)">
                <option value="">-- สินค้า --</option>
                ${PRODUCTS.map(p => `<option value="${p.id}" data-price="${p.selling_price}" data-name="${p.name}">${p.name}</option>`).join('')}
            </select>
        </div>
        <div class="col-md-2">
            <select class="form-select form-select-sm item-size">${SIZES.map(s=>`<option>${s}</option>`).join('')}</select>
        </div>
        <div class="col-md-2">
            <input type="number" class="form-control form-control-sm item-qty" placeholder="จำนวน" value="1" min="1" oninput="calcTotal()">
        </div>
        <div class="col-md-2">
            <input type="number" class="form-control form-control-sm item-price" placeholder="ราคา" value="0" oninput="calcTotal()">
        </div>
        <div class="col-md-2">
            <button type="button" class="btn btn-outline-danger btn-sm w-100" onclick="this.closest('.order-item-row').remove();calcTotal()"><i class="fas fa-times"></i></button>
        </div>`;
    document.getElementById('orderItems').appendChild(div);
}
function updateItemPrice(sel) {
    const opt = sel.options[sel.selectedIndex];
    sel.closest('.order-item-row').querySelector('.item-price').value = opt.dataset.price || 0;
    calcTotal();
}
function calcTotal() {
    let sub = 0;
    document.querySelectorAll('.order-item-row').forEach(row => {
        sub += (parseFloat(row.querySelector('.item-price').value) || 0) * (parseInt(row.querySelector('.item-qty').value) || 0);
    });
    const ship = parseFloat(document.getElementById('shippingCost').value) || 0;
    const disc = parseFloat(document.getElementById('discountAmount').value) || 0;
    document.getElementById('totalDisplay').textContent = '฿' + (sub + ship - disc).toLocaleString('th-TH', {minimumFractionDigits:2});
}
// ── OCR Tracking Scanner ─────────────────────────────────────────────────────
let _tesseractLoaded = false;

async function loadTesseract() {
    if (_tesseractLoaded || typeof Tesseract !== 'undefined') {
        _tesseractLoaded = true; return true;
    }
    return new Promise((resolve, reject) => {
        const s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js';
        s.onload  = () => { _tesseractLoaded = true; resolve(true); };
        s.onerror = () => reject(new Error('โหลด Tesseract.js ไม่ได้'));
        document.head.appendChild(s);
    });
}

async function runOCR(input) {
    if (!input.files || !input.files[0]) return;
    const statusEl = document.getElementById('ocrStatus');
    statusEl.innerHTML = '<span class="text-muted">⏳ กำลังโหลดระบบ OCR...</span>';

    try {
        await loadTesseract();
        statusEl.innerHTML = '<span class="text-muted">⏳ กำลังอ่านเลข Tracking...</span>';

        const { createWorker } = Tesseract;
        const worker = await createWorker('eng', 1, { logger: () => {} });
        const { data: { text } } = await worker.recognize(input.files[0]);
        await worker.terminate();
        input.value = '';

        const tracking = extractTracking(text);
        if (tracking) {
            document.getElementById('modalTracking').value = tracking;
            statusEl.innerHTML = `<span class="text-success">✅ อ่านได้: <strong>${tracking}</strong> — กรุณาตรวจสอบก่อนบันทึก</span>`;
        } else {
            statusEl.innerHTML = '<span class="text-warning">⚠️ อ่านไม่พบเลข Tracking — กรอกเองได้เลย</span>';
        }
    } catch (e) {
        input.value = '';
        statusEl.innerHTML = '<span class="text-danger">❌ ' + (e.message || 'ไม่สามารถอ่านรูปได้') + ' กรุณากรอกเอง</span>';
    }
}

function extractTracking(text) {
    // ลบ whitespace ให้เป็น single-line
    const t = text.replace(/\s+/g, ' ').toUpperCase();
    const patterns = [
        /\bTH[0-9]{10,14}\b/,          // Flash Express
        /\bEF[0-9]{9,12}TH\b/,          // J&T Express
        /\b[0-9]{3}[A-Z]{2}[0-9]{7,10}\b/, // Kerry / SCG
        /\bSPX[A-Z0-9]{8,15}\b/,        // Shopee Express
        /\bL[A-Z][0-9]{9,12}\b/,        // Lazada / LPT
        /\b[A-Z]{2}[0-9]{8,14}[A-Z]{2}\b/, // EMS / Standard
        /\b[A-Z]{2}[0-9]{8,14}\b/,      // Generic 2-letter prefix
        /\b[0-9]{12,20}\b/,             // Numeric only (some couriers)
    ];
    for (const p of patterns) {
        const m = t.match(p);
        if (m) return m[0];
    }
    return null;
}

// ─────────────────────────────────────────────────────────────────────────────
function prepareItems() {
    const items = [];
    document.querySelectorAll('.order-item-row').forEach(row => {
        const sel = row.querySelector('.item-product');
        const opt = sel.options[sel.selectedIndex];
        items.push({
            product_id: sel.value,
            name: opt.dataset.name || opt.text,
            size: row.querySelector('.item-size').value,
            color: '',
            qty: parseInt(row.querySelector('.item-qty').value) || 1,
            price: parseFloat(row.querySelector('.item-price').value) || 0
        });
    });
    document.getElementById('itemsJson').value = JSON.stringify(items);
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
