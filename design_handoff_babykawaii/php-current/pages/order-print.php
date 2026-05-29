<?php
require_once __DIR__ . '/../config/database.php';
requireLogin();
$pdo = getDB();

$id  = (int)($_GET['id'] ?? 0);
$raw = array_filter(array_map('intval', explode(',', $_GET['ids'] ?? '')));
$ids = $id ? [$id] : array_values($raw);

if (empty($ids)) { header('Location: ' . SITE_URL . '/pages/orders.php'); exit; }

$ph     = implode(',', array_fill(0, count($ids), '?'));
$orders = $pdo->prepare("
    SELECT o.id, o.order_number, o.customer_name, o.customer_phone, o.customer_address,
           o.tracking_number, o.payment_method, o.payment_status, o.total_amount,
           o.shipping_cost, o.notes,
           p.name AS platform_name, p.icon AS platform_icon
    FROM orders o LEFT JOIN platforms p ON p.id = o.platform_id
    WHERE o.id IN ($ph) ORDER BY o.order_date DESC");
$orders->execute($ids);
$orders = $orders->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ข้อมูลจัดส่ง — <?= count($orders) ?> ออเดอร์</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700;800&display=swap" rel="stylesheet">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Sarabun',sans-serif; background:#f0f2f5; color:#111; min-height:100vh; }

.toolbar {
    background:#fff; border-bottom:3px solid #e91e8c;
    padding:10px 20px; display:flex; align-items:center; gap:12px;
    position:sticky; top:0; z-index:100;
}
.toolbar h2 { flex:1; font-size:0.95rem; font-weight:700; color:#333; }
.btn-back   { color:#888; text-decoration:none; font-size:0.82rem; white-space:nowrap; }
.btn-back:hover { color:#e91e8c; }
.count-badge {
    background:#fce4ec; color:#c2185b;
    font-size:0.75rem; font-weight:700;
    padding:3px 10px; border-radius:20px;
}

.cards-wrap {
    padding:24px 20px;
    display:flex; flex-wrap:wrap; gap:16px; justify-content:center;
    max-width:1100px; margin:0 auto;
}

/* ── Card ── */
.card {
    background:#fff;
    border-radius:12px;
    width:320px;
    box-shadow:0 2px 12px rgba(0,0,0,.08);
    overflow:hidden;
    border:1px solid #f0f0f0;
}

.card-top {
    background:linear-gradient(135deg,#880e4f,#e91e8c);
    color:#fff;
    padding:10px 14px 8px;
    display:flex; justify-content:space-between; align-items:flex-start;
}
.card-top .order-num  { font-size:0.72rem; opacity:.8; font-family:monospace; }
.card-top .platform   { font-size:0.72rem; opacity:.8; }

.card-body { padding:16px 14px 12px; }

.recipient-name {
    font-size:1.4rem; font-weight:800; line-height:1.2;
    margin-bottom:6px; color:#111;
}
.recipient-phone {
    font-size:1.05rem; font-weight:700;
    color:#e91e8c; margin-bottom:10px;
    display:flex; align-items:center; gap:6px;
}
.recipient-phone svg { flex-shrink:0; }

.address-box {
    background:#fafafa; border:1px solid #f0f0f0;
    border-left:3px solid #e91e8c;
    border-radius:0 6px 6px 0;
    padding:8px 12px;
    font-size:0.88rem; color:#444; line-height:1.65;
}
.address-box.empty { color:#bbb; font-style:italic; }

.card-divider { border:none; border-top:1px solid #f5f5f5; margin:12px 0; }

.card-meta {
    display:flex; flex-wrap:wrap; gap:6px; align-items:center;
}
.badge {
    font-size:0.68rem; font-weight:700;
    padding:3px 9px; border-radius:20px;
    white-space:nowrap;
}
.badge-cod    { background:#ffebee; color:#c62828; border:1px solid #ffcdd2; }
.badge-paid   { background:#e8f5e9; color:#2e7d32; border:1px solid #c8e6c9; }
.badge-normal { background:#f5f5f5; color:#666; border:1px solid #e0e0e0; }

.tracking-row {
    margin-top:8px;
    display:flex; align-items:center; gap:8px;
    background:#f8f9ff; border:1px solid #e8eaf6;
    border-radius:6px; padding:7px 10px;
}
.tracking-label { font-size:0.65rem; text-transform:uppercase; letter-spacing:0.8px; color:#9e9e9e; flex-shrink:0; }
.tracking-value { font-size:0.85rem; font-weight:700; font-family:monospace; color:#1a237e; word-break:break-all; }
.tracking-value.empty { color:#bbb; font-weight:400; font-style:italic; font-family:inherit; }

.notes-row {
    margin-top:8px;
    background:#fffde7; border:1px solid #fff9c4;
    border-radius:6px; padding:6px 10px;
    font-size:0.8rem; color:#795548;
}

.copy-btn {
    margin-left:auto; background:none; border:1px solid #c5cae9;
    border-radius:5px; padding:2px 8px; font-size:0.7rem;
    color:#5c6bc0; cursor:pointer; font-family:inherit;
    flex-shrink:0; white-space:nowrap;
}
.copy-btn:hover { background:#e8eaf6; }
.copy-btn.copied { background:#e8f5e9; color:#2e7d32; border-color:#c8e6c9; }
</style>
</head>
<body>

<div class="toolbar">
    <a href="<?= SITE_URL ?>/pages/orders.php" class="btn-back">← กลับ</a>
    <h2>📋 ข้อมูลจัดส่ง</h2>
    <span class="count-badge"><?= count($orders) ?> ออเดอร์</span>
</div>

<div class="cards-wrap">
<?php foreach ($orders as $order):
    $isCOD  = stripos($order['payment_method'] ?? '', 'ปลายทาง') !== false;
    $isPaid = ($order['payment_status'] ?? '') === 'paid';
    $trk    = $order['tracking_number'] ?? '';
?>
<div class="card">

    <div class="card-top">
        <span class="order-num">#<?= htmlspecialchars($order['order_number']) ?></span>
        <span class="platform"><?= $order['platform_icon'] ?? '🛒' ?> <?= htmlspecialchars($order['platform_name'] ?? '') ?></span>
    </div>

    <div class="card-body">

        <div class="recipient-name"><?= htmlspecialchars($order['customer_name'] ?? '—') ?></div>

        <?php if ($order['customer_phone']): ?>
        <div class="recipient-phone">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.5 10.81 19.79 19.79 0 01.46 2.18 2 2 0 012.45 0h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.91 7.91a16 16 0 006.16 6.16l.79-.79a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
            <?= htmlspecialchars($order['customer_phone']) ?>
        </div>
        <?php endif; ?>

        <?php if ($order['customer_address']): ?>
        <div class="address-box"><?= nl2br(htmlspecialchars($order['customer_address'])) ?></div>
        <?php else: ?>
        <div class="address-box empty">ไม่มีที่อยู่จัดส่ง</div>
        <?php endif; ?>

        <hr class="card-divider">

        <div class="card-meta">
            <?php if ($isCOD): ?>
            <span class="badge badge-cod">💰 เก็บปลายทาง <?= number_format((float)$order['total_amount'], 0) ?>฿</span>
            <?php elseif ($isPaid): ?>
            <span class="badge badge-paid">✅ ชำระแล้ว</span>
            <?php else: ?>
            <span class="badge badge-normal">฿<?= number_format((float)$order['total_amount'], 0) ?></span>
            <?php endif; ?>
        </div>

        <div class="tracking-row">
            <span class="tracking-label">Tracking</span>
            <?php if ($trk): ?>
            <span class="tracking-value"><?= htmlspecialchars($trk) ?></span>
            <button class="copy-btn" onclick="copyText(this,'<?= htmlspecialchars($trk, ENT_QUOTES) ?>')">คัดลอก</button>
            <?php else: ?>
            <span class="tracking-value empty">ยังไม่มีเลขพัสดุ</span>
            <?php endif; ?>
        </div>

        <?php if ($order['notes']): ?>
        <div class="notes-row">💬 <?= htmlspecialchars($order['notes']) ?></div>
        <?php endif; ?>

    </div>
</div>
<?php endforeach; ?>
</div>

<script>
function copyText(btn, text) {
    navigator.clipboard.writeText(text).then(() => {
        btn.textContent = '✅ คัดลอกแล้ว';
        btn.classList.add('copied');
        setTimeout(() => { btn.textContent = 'คัดลอก'; btn.classList.remove('copied'); }, 2000);
    });
}
</script>
</body>
</html>
