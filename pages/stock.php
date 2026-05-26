<?php
$pageTitle = 'จัดการสต็อก';
require_once __DIR__ . '/../config/database.php';
requireLogin();
$pdo = getDB();
$filter        = $_GET['filter']  ?? '';
$productFilter = isset($_GET['product']) ? (int)$_GET['product'] : 0;
$search        = trim($_GET['q'] ?? '');

// ── POST handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'adjust') {
        $stockId = (int)$_POST['stock_id'];
        $newQty  = (int)$_POST['new_qty'];
        $reason  = trim($_POST['reason'] ?? 'แก้ไขสต็อก');

        $old = $pdo->prepare("SELECT s.*, p.name as product_name FROM stock s JOIN products p ON p.id=s.product_id WHERE s.id = ?");
        $old->execute([$stockId]);
        $oldRow = $old->fetch();

        if ($oldRow) {
            $pdo->prepare("UPDATE stock SET quantity=? WHERE id=?")->execute([$newQty, $stockId]);
            $pdo->prepare("INSERT INTO stock_movements (product_id,size,color,movement_type,quantity,before_qty,after_qty,reason,created_by) VALUES (?,?,?,?,?,?,?,?,?)")
                ->execute([$oldRow['product_id'],$oldRow['size'],$oldRow['color'],'adjust',abs($newQty-$oldRow['quantity']),$oldRow['quantity'],$newQty,$reason,$_SESSION['admin_id']]);
            if ($newQty <= $oldRow['min_alert']) {
                $isOut = ($newQty === 0);
                sendLineNotify(($isOut?'🔴':'⚠️')." สต็อก".($isOut?'หมด':'ใกล้หมด').": {$oldRow['product_name']} {$oldRow['size']}/{$oldRow['color']}\nเหลือ {$newQty} ชิ้น");
            }
        }
        echo json_encode(['ok'=>true,'new_qty'=>$newQty]);
        exit;
    }

    if ($_POST['action'] === 'restock') {
        $stockId = (int)$_POST['stock_id'];
        $addQty  = (int)$_POST['add_qty'];
        $reason  = trim($_POST['reason'] ?? 'รับสินค้าเพิ่ม');

        $old = $pdo->prepare("SELECT s.*, p.name as product_name FROM stock s JOIN products p ON p.id=s.product_id WHERE s.id = ?");
        $old->execute([$stockId]);
        $oldRow = $old->fetch();

        if ($oldRow && $addQty > 0) {
            $newQty = $oldRow['quantity'] + $addQty;
            $pdo->prepare("UPDATE stock SET quantity=? WHERE id=?")->execute([$newQty, $stockId]);
            $pdo->prepare("INSERT INTO stock_movements (product_id,size,color,movement_type,quantity,before_qty,after_qty,reason,created_by) VALUES (?,?,?,?,?,?,?,?,?)")
                ->execute([$oldRow['product_id'],$oldRow['size'],$oldRow['color'],'in',$addQty,$oldRow['quantity'],$newQty,$reason,$_SESSION['admin_id']]);
        }
        echo json_encode(['ok'=>true,'new_qty'=>$newQty ?? ($oldRow['quantity'] ?? 0)]);
        exit;
    }

    // ── Barcode scan: +1 per scan ─────────────────────────────────────────────
    if ($_POST['action'] === 'scan') {
        $barcode = trim($_POST['barcode'] ?? '');
        if (!$barcode) { echo json_encode(['ok'=>false,'msg'=>'ไม่มีรหัส']); exit; }

        $allSizes    = ['Premature','NB','0-3M','3-6M','6-9M','9-12M','12-18M','18-24M','Free Size'];
        $allProducts = $pdo->query("SELECT id, sku FROM products WHERE status!='inactive' AND sku!='' AND sku IS NOT NULL ORDER BY LENGTH(sku) DESC")->fetchAll();

        $found = null;
        foreach ($allProducts as $p) {
            $prefix = $p['sku'] . '-';
            if (strpos($barcode, $prefix) !== 0) continue;
            $rest = substr($barcode, strlen($prefix)); // e.g. "W-NB" or "NB" or "PK-0-3M"

            $matchSize = $matchColor = null;
            // Try exact size (no color prefix)
            if (in_array($rest, $allSizes)) {
                $matchSize = $rest;
            }
            // Try "-{size}" suffix → color prefix before it
            if (!$matchSize) {
                foreach ($allSizes as $sz) {
                    $sfx = '-' . $sz;
                    if (strlen($rest) > strlen($sfx) && substr($rest, -strlen($sfx)) === $sfx) {
                        $matchSize  = $sz;
                        $matchColor = substr($rest, 0, -strlen($sfx));
                        break;
                    }
                }
            }
            if (!$matchSize) continue;

            if ($matchColor) {
                $colorTh = colorEnToTh($matchColor);
                $stmt = $pdo->prepare("SELECT s.*, p.name AS product_name, p.sku AS product_sku FROM stock s JOIN products p ON p.id=s.product_id WHERE s.product_id=? AND s.size=? AND s.color=?");
                $stmt->execute([$p['id'], $matchSize, $colorTh]);
            } else {
                $stmt = $pdo->prepare("SELECT s.*, p.name AS product_name, p.sku AS product_sku FROM stock s JOIN products p ON p.id=s.product_id WHERE s.product_id=? AND s.size=?");
                $stmt->execute([$p['id'], $matchSize]);
            }
            $found = $stmt->fetch();
            if ($found) break;
        }

        if (!$found) {
            echo json_encode(['ok'=>false,'msg'=>'ไม่พบสินค้า: ' . $barcode]);
            exit;
        }

        $oldQty = (int)$found['quantity'];
        $newQty = $oldQty + 1;
        $pdo->prepare("UPDATE stock SET quantity=? WHERE id=?")->execute([$newQty, $found['id']]);
        $pdo->prepare("INSERT INTO stock_movements (product_id,size,color,movement_type,quantity,before_qty,after_qty,reason,created_by) VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$found['product_id'],$found['size'],$found['color'],'scan',1,$oldQty,$newQty,'สแกนบาร์โค้ด',$_SESSION['admin_id']]);

        echo json_encode([
            'ok'           => true,
            'stock_id'     => (int)$found['id'],
            'product_id'   => (int)$found['product_id'],
            'product_name' => $found['product_name'],
            'sku'          => $found['product_sku'],
            'size'         => $found['size'],
            'color'        => $found['color'],
            'old_qty'      => $oldQty,
            'new_qty'      => $newQty,
        ]);
        exit;
    }
}

// ── Build main query ─────────────────────────────────────────────────────────
$where  = ["p.status != 'inactive'"];
$params = [];
if ($filter === 'low')   { $where[] = "s.quantity > 0 AND s.quantity <= s.min_alert"; }
if ($filter === 'out')   { $where[] = "s.quantity = 0"; }
if ($productFilter)      { $where[] = "p.id = ?"; $params[] = $productFilter; }
if ($search)             { $where[] = "(p.name LIKE ? OR p.sku LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

$whereSQL = 'WHERE ' . implode(' AND ', $where);

$stocks = $pdo->prepare("
    SELECT s.*, p.name as product_name, p.sku, p.main_image,
           cat.name as cat_name, cat.icon as cat_icon
    FROM stock s
    JOIN products p ON p.id = s.product_id
    LEFT JOIN categories cat ON cat.id = p.category_id
    $whereSQL
    ORDER BY p.name,
             s.color,
             FIELD(s.size,'Premature','NB','0-3M','3-6M','6-9M','9-12M','12-18M','18-24M','Free Size')
");
$stocks->execute($params);
$stocks = $stocks->fetchAll();

// ── Summary stats ─────────────────────────────────────────────────────────────
$summary = $pdo->query("
    SELECT COUNT(*) as total_variants,
           COALESCE(SUM(s.quantity),0) as total_qty,
           SUM(CASE WHEN s.quantity=0 THEN 1 ELSE 0 END) as out_count,
           SUM(CASE WHEN s.quantity>0 AND s.quantity<=s.min_alert THEN 1 ELSE 0 END) as low_count
    FROM stock s JOIN products p ON p.id=s.product_id WHERE p.status!='inactive'
")->fetch();

$orphanCount = $pdo->query("SELECT COUNT(*) FROM stock s JOIN products p ON p.id=s.product_id WHERE p.status='inactive'")->fetchColumn();
$products    = $pdo->query("SELECT id, name, sku FROM products WHERE status!='inactive' ORDER BY name")->fetchAll();

// ── Color helpers ─────────────────────────────────────────────────────────────
function colorToCss(string $c): string {
    $map = [
        'ขาว'=>'#e8e8e8','เงิน'=>'#c0c7ce','เทา'=>'#9ca3af','ดำ'=>'#374151',
        'แดง'=>'#ef4444','ชมพู'=>'#f472b6','ม่วง'=>'#a855f7','ฟ้า'=>'#60a5fa',
        'น้ำเงิน'=>'#3b82f6','เขียว'=>'#4ade80','เหลือง'=>'#facc15','ส้ม'=>'#fb923c',
        'น้ำตาล'=>'#a16207','ทอง'=>'#fbbf24','ครีม'=>'#fef3c7','ลาย'=>'#94a3b8',
        'ไม่ระบุ'=>'#d1d5db',
    ];
    foreach ($map as $th => $css) {
        if (mb_strpos($c, $th) !== false) return $css;
    }
    $hue = abs(crc32($c)) % 360;
    return "hsl($hue,55%,65%)";
}

function colorEnToTh(string $en): string {
    $map = [
        'W'=>'ขาว','SV'=>'เงิน','GY'=>'เทา','BK'=>'ดำ',
        'RD'=>'แดง','PK'=>'ชมพู','PU'=>'ม่วง','BL'=>'ฟ้า',
        'NV'=>'น้ำเงิน','GN'=>'เขียว','YL'=>'เหลือง','OR'=>'ส้ม',
        'BR'=>'น้ำตาล','GD'=>'ทอง','CR'=>'ครีม','PT'=>'ลาย',
    ];
    return $map[strtoupper($en)] ?? $en;
}

function colorToEn(string $c): string {
    $map = [
        'ขาว'=>'W','เงิน'=>'SV','เทา'=>'GY','ดำ'=>'BK',
        'แดง'=>'RD','ชมพู'=>'PK','ม่วง'=>'PU','ฟ้า'=>'BL',
        'น้ำเงิน'=>'NV','เขียว'=>'GN','เหลือง'=>'YL','ส้ม'=>'OR',
        'น้ำตาล'=>'BR','ทอง'=>'GD','ครีม'=>'CR','ลาย'=>'PT',
    ];
    foreach ($map as $th => $en) {
        if (mb_strpos($c, $th) !== false) return $en;
    }
    $ascii = preg_replace('/[^A-Za-z0-9]/', '', $c);
    return $ascii ? strtoupper(substr($ascii, 0, 2)) : strtoupper(substr(md5($c), 0, 2));
}

// ── Group by product → colors → sizes ────────────────────────────────────────
$productGroups = [];
foreach ($stocks as $s) {
    $pid   = $s['product_id'];
    $color = $s['color'];

    if (!isset($productGroups[$pid])) {
        $productGroups[$pid] = [
            'product_id'   => $pid,
            'product_name' => $s['product_name'],
            'sku'          => $s['sku'] ?? '',
            'cat_icon'     => $s['cat_icon'] ?? '👶',
            'cat_name'     => $s['cat_name'] ?? '',
            'main_image'   => $s['main_image'],
            'colors'       => [],
            'total_qty'    => 0,
            'has_low'      => false,
            'has_out'      => false,
        ];
    }

    if (!isset($productGroups[$pid]['colors'][$color])) {
        $productGroups[$pid]['colors'][$color] = [
            'color'     => $color,
            'color_css' => colorToCss($color),
            'color_en'  => colorToEn($color),
            'sizes'     => [],
            'total_qty' => 0,
            'has_low'   => false,
            'has_out'   => false,
        ];
    }

    $qty = (int)$s['quantity'];
    $min = (int)$s['min_alert'];

    $productGroups[$pid]['colors'][$color]['sizes'][] = [
        'id'        => (int)$s['id'],
        'size'      => $s['size'],
        'quantity'  => $qty,
        'min_alert' => $min,
    ];
    $productGroups[$pid]['colors'][$color]['total_qty'] += $qty;
    if ($qty === 0)          $productGroups[$pid]['colors'][$color]['has_out'] = true;
    elseif ($qty <= $min)    $productGroups[$pid]['colors'][$color]['has_low'] = true;

    $productGroups[$pid]['total_qty'] += $qty;
    if ($qty === 0)          $productGroups[$pid]['has_out'] = true;
    elseif ($qty <= $min)    $productGroups[$pid]['has_low'] = true;
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* ══ Product accordion row ════════════════════════════════════════════════ */
.prow {
    background: #fff;
    border: 1.5px solid #e5e7eb;
    border-radius: 14px;
    margin-bottom: 8px;
    overflow: hidden;
    transition: border-color .2s, box-shadow .2s;
}
.prow:hover:not(.open) {
    border-color: #fbb6ce;
    box-shadow: 0 2px 10px rgba(233,30,140,.08);
}
.prow.open {
    border-color: #E91E8C;
    box-shadow: 0 4px 18px rgba(233,30,140,.13);
}

/* ── Collapsed header row ── */
.prow-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    cursor: pointer;
    user-select: none;
    transition: background .15s;
}
.prow-header:hover,
.prow.open .prow-header { background: #fdf4fb; }

.swatch-stack { display:flex; gap:4px; align-items:center; flex-shrink:0; }
.swatch {
    width: 12px; height: 12px;
    border-radius: 50%;
    border: 1.5px solid rgba(0,0,0,.09);
    flex-shrink: 0;
}
.prow-name  { font-weight: 700; font-size: .93rem; color: #111827; line-height:1.2; }
.prow-meta  { font-size: .68rem; color: #9ca3af; margin-top:1px; }
.prow-sku {
    font-family: monospace;
    font-size: .72rem;
    font-weight: 700;
    color: #7c3aed;
    background: #f5f3ff;
    border-radius: 6px;
    padding: 3px 8px;
    white-space: nowrap;
    flex-shrink: 0;
}
.prow-qty {
    border-radius: 20px;
    padding: 4px 12px;
    font-weight: 700;
    font-size: .78rem;
    white-space: nowrap;
    flex-shrink: 0;
}
.prow-qty.ok   { background:#dcfce7; color:#166534; }
.prow-qty.warn { background:#fef3c7; color:#92400e; }
.prow-qty.out  { background:#fee2e2; color:#991b1b; }
.prow-chevron {
    color: #d1d5db;
    font-size: .8rem;
    flex-shrink: 0;
    transition: transform .25s, color .2s;
    margin-left: auto;
}
.prow.open .prow-chevron { transform:rotate(180deg); color:#E91E8C; }

/* ── Expandable body ── */
.prow-body {
    max-height: 0;
    overflow: hidden;
    transition: max-height .4s cubic-bezier(.4,0,.2,1);
}
.prow.open .prow-body { max-height: 3000px; }

/* ── Color block inside body ── */
.color-block {
    border-top: 1px solid #f3f4f6;
    padding: 10px 16px;
}
.color-block-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    flex-wrap: wrap;
}
.cdot {
    width: 12px; height: 12px;
    border-radius: 50%;
    border: 1.5px solid rgba(0,0,0,.09);
    flex-shrink: 0;
}
.cname  { font-weight: 700; font-size: .82rem; color: #374151; }
.ccode  {
    font-family: monospace;
    font-size: .65rem;
    font-weight: 700;
    color: #7c3aed;
    background: #f5f3ff;
    border-radius: 5px;
    padding: 2px 6px;
    letter-spacing: .4px;
}
.cqty {
    border-radius: 12px;
    padding: 2px 9px;
    font-weight: 700;
    font-size: .72rem;
    flex-shrink: 0;
}
.cqty.ok   { background:#dcfce7; color:#166534; }
.cqty.warn { background:#fef3c7; color:#92400e; }
.cqty.out  { background:#fee2e2; color:#991b1b; }
.restock-btn {
    border: 1.5px solid #e5e7eb;
    border-radius: 8px;
    background: #fff;
    color: #E91E8C;
    font-size: .72rem;
    font-weight: 700;
    padding: 3px 9px;
    cursor: pointer;
    transition: border-color .15s, background .15s;
    margin-left: auto;
    flex-shrink: 0;
}
.restock-btn:hover { border-color: #E91E8C; background: #fdf4fb; }

/* ── Size cells ── */
.sizes-row {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
}
.size-cell {
    display: flex;
    flex-direction: column;
    align-items: center;
    border-radius: 9px;
    padding: 6px 5px 5px;
    min-width: 50px;
    border: 1.5px solid transparent;
    cursor: pointer;
    transition: box-shadow .15s, border-color .15s;
    gap: 1px;
}
.size-cell:hover { box-shadow: 0 0 0 2px #E91E8C44; }
.size-cell.editing { border-color: #E91E8C !important; box-shadow: 0 0 0 2px #E91E8C22; }
.size-label {
    font-size: .56rem;
    color: #9ca3af;
    font-weight: 700;
    line-height: 1;
}
.qty-val {
    font-size: 1.05rem;
    font-weight: 800;
    line-height: 1;
    display: block;
}
.qty-input {
    width: 100%;
    border: none;
    background: transparent;
    text-align: center;
    font-size: 1rem;
    font-weight: 800;
    padding: 0;
    outline: none;
}
.qty-status { font-size: .5rem; font-weight: 700; display: block; }

/* ── Row footer ── */
.prow-footer {
    border-top: 1px solid #f3f4f6;
    padding: 9px 16px;
    display: flex;
    justify-content: flex-end;
    background: #fafafa;
}

/* ── Animations ── */
.flash-ok  { animation: flashGreen .6s ease; }
.flash-err { animation: flashRed   .6s ease; }
@keyframes flashGreen { 0%,100%{background:inherit} 30%{background:#bbf7d0} }
@keyframes flashRed   { 0%,100%{background:inherit} 30%{background:#fecaca} }

/* ══ Scan mode bar ═══════════════════════════════════════════════════════ */
.scan-bar {
    background: #fff;
    border: 2px solid #E91E8C;
    border-radius: 14px;
    padding: 10px 14px;
    margin-bottom: 12px;
    box-shadow: 0 4px 20px rgba(233,30,140,.15);
}
.scan-bar-top {
    display: flex;
    align-items: center;
    gap: 10px;
}
#scanInput {
    flex: 1;
    border: 1.5px solid #fbb6ce;
    border-radius: 9px;
    padding: 7px 12px;
    font-size: .88rem;
    outline: none;
    font-family: monospace;
    transition: border-color .15s;
}
#scanInput:focus { border-color: #E91E8C; box-shadow: 0 0 0 3px rgba(233,30,140,.1); }
.scan-pulse {
    width: 10px; height: 10px;
    background: #E91E8C;
    border-radius: 50%;
    flex-shrink: 0;
    animation: pulse 1.2s infinite;
}
@keyframes pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(.7)} }
.scan-log {
    margin-top: 8px;
    display: flex;
    flex-direction: column;
    gap: 4px;
    max-height: 160px;
    overflow-y: auto;
}
.scan-log-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 5px 10px;
    border-radius: 8px;
    font-size: .78rem;
    animation: slideIn .25s ease;
}
.scan-log-item.ok  { background: #f0fdf4; border: 1px solid #bbf7d0; }
.scan-log-item.err { background: #fef2f2; border: 1px solid #fecaca; }
@keyframes slideIn { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:none} }

/* ── Print button (in color header) ─────────────────────────────────────── */
.print-bc-btn {
    border: 1.5px solid #e5e7eb;
    border-radius: 8px;
    background: #fff;
    color: #6b7280;
    font-size: .72rem;
    font-weight: 700;
    padding: 3px 9px;
    cursor: pointer;
    transition: border-color .15s, background .15s, color .15s;
    flex-shrink: 0;
}
.print-bc-btn:hover { border-color: #7c3aed; color: #7c3aed; background: #f5f3ff; }

/* ── Print preview modal labels ─────────────────────────────────────────── */
.bc-preview-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    padding: 4px;
}
.bc-preview-label {
    border: 1.5px solid #e5e7eb;
    border-radius: 8px;
    padding: 8px 10px;
    width: calc(33.33% - 7px);
    min-width: 130px;
    display: flex;
    flex-direction: column;
    align-items: center;
    background: #fff;
    box-sizing: border-box;
}
.bc-preview-label svg { width: 100%; max-width: 150px; }
.bc-preview-sku  { font-family: monospace; font-size: .68rem; font-weight: 800; color: #374151; margin-top: 4px; }
.bc-preview-info { font-size: .6rem; color: #9ca3af; margin-top: 2px; }

/* ══ Print media — only show barcode labels ═══════════════════════════════ */
#barcodesPrintArea { display: none; }
@media print {
    body.printing-bc > *:not(#barcodesPrintArea) { display: none !important; }
    #barcodesPrintArea {
        display: flex !important;
        flex-wrap: wrap;
        gap: 3mm;
        padding: 8mm;
    }
    .bc-print-label {
        width: 60mm;
        height: 34mm;
        border: 0.4mm solid #ccc;
        padding: 2.5mm 3mm;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        box-sizing: border-box;
        break-inside: avoid;
    }
    .bc-print-label svg { width: 100%; }
    .bc-print-sku  { font-family: monospace; font-size: 7pt; font-weight: bold; margin-top: 1mm; }
    .bc-print-info { font-size: 5.5pt; color: #666; margin-top: .5mm; }
}

/* ── Scan flash ─────────────────────────────────────────────────────────── */
.scan-flash { animation: scanFlash .7s ease; }
@keyframes scanFlash { 0%,100%{background:inherit} 20%,60%{background:#bbf7d0} }

/* ── Stat strip ── */
.stat-strip {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 16px;
}
.stat-chip {
    display: flex;
    align-items: center;
    gap: 7px;
    background: #fff;
    border: 1.5px solid #e5e7eb;
    border-radius: 10px;
    padding: 8px 14px;
    font-size: .8rem;
    font-weight: 600;
    color: #374151;
    text-decoration: none;
    transition: border-color .15s, box-shadow .15s;
    flex: 1;
    min-width: 130px;
}
.stat-chip:hover { border-color: #E91E8C; box-shadow: 0 2px 8px rgba(233,30,140,.1); color: #374151; }
.stat-chip i { font-size: .82rem; width: 18px; text-align: center; }
.stat-chip .chip-val { font-size: 1.1rem; font-weight: 800; }
.chip-green { border-color:#bbf7d0; }  .chip-green .chip-val { color:#166534; }
.chip-purple { border-color:#ddd6fe; } .chip-purple .chip-val { color:#6d28d9; }
.chip-amber { border-color:#fde68a; }  .chip-amber .chip-val { color:#92400e; }
.chip-red { border-color:#fecaca; }    .chip-red .chip-val { color:#991b1b; }
</style>

<div class="container-fluid fade-in">

    <!-- Page header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">📦 จัดการสต็อก</h1>
            <p class="page-subtitle">กดที่รายการเพื่อดูรายละเอียด · คลิกตัวเลขเพื่อแก้ไข</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button onclick="toggleScanMode()" id="scanModeBtn" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-barcode me-1"></i> โหมดสแกน
            </button>
            <a href="<?= SITE_URL ?>/pages/export.php?type=stock" class="btn btn-outline-success btn-sm">
                <i class="fas fa-file-csv me-1"></i> Export
            </a>
            <a href="<?= SITE_URL ?>/pages/import.php?tab=stock" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-file-import me-1"></i> Import
            </a>
            <a href="<?= SITE_URL ?>/pages/product-add.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus me-1"></i> เพิ่มสินค้า
            </a>
        </div>
    </div>

    <?php if ($orphanCount > 0): ?>
    <div class="alert alert-warning d-flex align-items-center gap-3 mb-3" style="border-radius:10px;">
        <i class="fas fa-exclamation-triangle"></i>
        <div>พบสต็อก <?= $orphanCount ?> รายการ ของสินค้าที่ปิดการขาย —
            <a href="<?= SITE_URL ?>/pages/data-manage.php" class="alert-link">เคลียร์ข้อมูล</a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Stat strip -->
    <div class="stat-strip">
        <div class="stat-chip chip-green">
            <i class="fas fa-boxes-stacked" style="color:#16a34a;"></i>
            <div><div class="chip-val"><?= number_format($summary['total_qty']) ?></div><div style="font-size:.68rem;color:#6b7280;">ชิ้นรวม</div></div>
        </div>
        <div class="stat-chip chip-purple">
            <i class="fas fa-tags" style="color:#7c3aed;"></i>
            <div><div class="chip-val"><?= count($productGroups) ?></div><div style="font-size:.68rem;color:#6b7280;">สินค้า</div></div>
        </div>
        <a href="?filter=low<?= $productFilter?"&product=$productFilter":'' ?>" class="stat-chip chip-amber">
            <i class="fas fa-exclamation-triangle" style="color:#d97706;"></i>
            <div><div class="chip-val"><?= number_format($summary['low_count']) ?></div><div style="font-size:.68rem;color:#6b7280;">ใกล้หมด</div></div>
        </a>
        <a href="?filter=out<?= $productFilter?"&product=$productFilter":'' ?>" class="stat-chip chip-red">
            <i class="fas fa-times-circle" style="color:#dc2626;"></i>
            <div><div class="chip-val"><?= number_format($summary['out_count']) ?></div><div style="font-size:.68rem;color:#6b7280;">หมดสต็อก</div></div>
        </a>
    </div>

    <!-- Filter bar -->
    <div class="card mb-4" style="border-radius:12px;">
        <div class="card-body py-3">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <input type="text" name="q" class="form-control" placeholder="🔍 ค้นหาชื่อสินค้า, รหัส SKU..."
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-3">
                    <select name="product" class="form-select">
                        <option value="">ทุกสินค้า</option>
                        <?php foreach ($products as $pr): ?>
                        <option value="<?= $pr['id'] ?>" <?= $productFilter==$pr['id']?'selected':'' ?>>
                            <?= htmlspecialchars($pr['name']) ?><?= $pr['sku'] ? ' ('.$pr['sku'].')' : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="filter" class="form-select">
                        <option value="" <?= $filter===''?'selected':'' ?>>ทุกสถานะ</option>
                        <option value="low" <?= $filter==='low'?'selected':'' ?>>⚠️ ใกล้หมด</option>
                        <option value="out" <?= $filter==='out'?'selected':'' ?>>❌ หมดสต็อก</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm">กรอง</button>
                    <a href="?" class="btn btn-outline-secondary btn-sm ms-1">ล้าง</a>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Scan mode bar ─────────────────────────────────────────────────── -->
    <div id="scanBar" class="scan-bar d-none">
        <div class="scan-bar-top">
            <span class="scan-pulse"></span>
            <span style="font-weight:700;font-size:.85rem;color:#E91E8C;white-space:nowrap;">
                <i class="fas fa-barcode me-1"></i>โหมดสแกน
            </span>
            <input id="scanInput" type="text" autocomplete="off" spellcheck="false"
                   placeholder="สแกนบาร์โค้ด หรือพิมพ์รหัสแล้วกด Enter...">
            <span id="scanCount" style="font-size:.75rem;color:#6b7280;white-space:nowrap;flex-shrink:0;">
                สแกนแล้ว <b id="scanCountNum">0</b> รายการ
            </span>
            <button class="btn btn-sm btn-outline-secondary" style="flex-shrink:0;" onclick="toggleScanMode()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="scanLog" class="scan-log mt-2"></div>
    </div>

    <!-- ── Product accordion list ─────────────────────────────────────────── -->
    <?php if (empty($productGroups)): ?>
    <div class="card p-5 text-center" style="border-radius:14px;">
        <?php if ($orphanCount > 0): ?>
            <div style="font-size:2.5rem;">⚠️</div>
            <p class="text-muted mt-2">สินค้าทั้งหมดถูกปิดการขาย</p>
            <div class="d-flex gap-2 justify-content-center mt-3">
                <a href="<?= SITE_URL ?>/pages/products.php" class="btn btn-outline-primary btn-sm">👕 หน้าสินค้า</a>
                <a href="<?= SITE_URL ?>/pages/data-manage.php" class="btn btn-outline-danger btn-sm">🗂️ เคลียร์ข้อมูล</a>
            </div>
        <?php elseif (empty($products)): ?>
            <div style="font-size:2.5rem;">📦</div>
            <p class="text-muted mt-2">ยังไม่มีสินค้าในระบบ</p>
            <div class="d-flex gap-2 justify-content-center mt-3">
                <a href="<?= SITE_URL ?>/pages/product-add.php" class="btn btn-primary btn-sm">+ เพิ่มสินค้า</a>
                <a href="<?= SITE_URL ?>/pages/import.php?tab=products" class="btn btn-outline-primary btn-sm">📥 Import CSV</a>
            </div>
        <?php else: ?>
            <div style="font-size:2.5rem;">🔍</div>
            <p class="text-muted mt-2">ไม่พบสต็อกที่ตรงกับเงื่อนไข</p>
            <a href="?" class="btn btn-outline-secondary btn-sm mt-2">ล้างตัวกรอง</a>
        <?php endif; ?>
    </div>
    <?php else: ?>

    <div id="productList">
    <?php foreach ($productGroups as $group):
        $tot  = $group['total_qty'];
        $cls  = ($tot === 0) ? 'out' : ($group['has_out'] || $group['has_low'] ? 'warn' : 'ok');
        $icon = ($tot === 0) ? '❌' : ($group['has_out'] || $group['has_low'] ? '⚠️' : '✅');
        $totalVariants = array_sum(array_map(fn($c) => count($c['sizes']), $group['colors']));
        // Auto-open if filtered to a single product or has alerts
        $autoOpen = ($productFilter == $group['product_id']) || ($filter !== '' && ($group['has_out'] || $group['has_low']));
    ?>
    <div class="prow <?= $autoOpen ? 'open' : '' ?>" id="prow-<?= $group['product_id'] ?>">

        <!-- ── Collapsed header ── -->
        <div class="prow-header" onclick="toggleRow(<?= $group['product_id'] ?>)">

            <!-- Color swatches -->
            <div class="swatch-stack">
                <?php $ci = 0; foreach ($group['colors'] as $cd): if ($ci++ >= 5) break; ?>
                <span class="swatch" style="background:<?= $cd['color_css'] ?>;" title="<?= htmlspecialchars($cd['color']) ?>"></span>
                <?php endforeach; ?>
                <?php if (count($group['colors']) > 5): ?>
                <span style="font-size:.62rem;color:#9ca3af;">+<?= count($group['colors'])-5 ?></span>
                <?php endif; ?>
            </div>

            <!-- Name + meta -->
            <div style="flex:1;min-width:0;">
                <div class="prow-name"><?= htmlspecialchars($group['product_name']) ?></div>
                <div class="prow-meta">
                    <?= $group['cat_icon'] ?> <?= htmlspecialchars($group['cat_name']) ?>
                    · <?= count($group['colors']) ?> สี · <?= $totalVariants ?> รูปแบบ
                </div>
            </div>

            <!-- SKU -->
            <?php if ($group['sku']): ?>
            <span class="prow-sku"><?= htmlspecialchars($group['sku']) ?></span>
            <?php endif; ?>

            <!-- Qty badge -->
            <span class="prow-qty <?= $cls ?>"><?= $icon ?> <?= $tot ?> ชิ้น</span>

            <!-- Chevron -->
            <i class="fas fa-chevron-down prow-chevron"></i>
        </div>

        <!-- ── Expanded body ── -->
        <div class="prow-body">

            <?php foreach ($group['colors'] as $colorData):
                $cTot = $colorData['total_qty'];
                $cCls = ($cTot === 0) ? 'out' : ($colorData['has_out'] || $colorData['has_low'] ? 'warn' : 'ok');
                $cIco = ($cTot === 0) ? '❌' : ($colorData['has_out'] || $colorData['has_low'] ? '⚠️' : '');
                $colorSkuCode = $group['sku'] ? $group['sku'].'-'.$colorData['color_en'] : $colorData['color_en'];
            ?>
            <div class="color-block">

                <!-- Color header -->
                <?php
                    $printLabels = array_map(fn($sz) => [
                        'variantSku'  => $colorSkuCode . '-' . $sz['size'],
                        'productName' => $group['product_name'],
                        'colorTh'     => $colorData['color'],
                        'size'        => $sz['size'],
                    ], $colorData['sizes']);
                    $printJson = htmlspecialchars(json_encode($printLabels), ENT_QUOTES);
                ?>
                <div class="color-block-header">
                    <span class="cdot" style="background:<?= $colorData['color_css'] ?>;"></span>
                    <span class="cname"><?= htmlspecialchars($colorData['color']) ?></span>
                    <span class="ccode"><?= htmlspecialchars($colorSkuCode) ?></span>
                    <span class="cqty <?= $cCls ?>"><?= $cIco ?> <?= $cTot ?> ชิ้น</span>
                    <button class="print-bc-btn"
                            onclick="event.stopPropagation();openPrintModal(<?= $printJson ?>,'<?= htmlspecialchars($group['product_name'],ENT_QUOTES).' · '.$colorData['color'] ?>')">
                        <i class="fas fa-print me-1"></i>พิมพ์
                    </button>
                    <button class="restock-btn"
                            onclick="event.stopPropagation();openRestockCard(<?= htmlspecialchars(json_encode($colorData['sizes']),ENT_QUOTES) ?>,'<?= htmlspecialchars($group['product_name'],ENT_QUOTES) ?>','<?= htmlspecialchars($colorData['color'],ENT_QUOTES) ?>')">
                        <i class="fas fa-plus me-1"></i>เติมสต็อก
                    </button>
                </div>

                <!-- Sizes -->
                <div class="sizes-row">
                    <?php foreach ($colorData['sizes'] as $sz):
                        $qty      = $sz['quantity'];
                        $szIsOut  = ($qty === 0);
                        $szIsLow  = (!$szIsOut && $qty <= $sz['min_alert']);
                        $cellBg   = $szIsOut ? '#fef2f2' : ($szIsLow ? '#fffbeb' : '#f0fdf4');
                        $cellBdr  = $szIsOut ? '#fca5a5' : ($szIsLow ? '#fde68a' : '#bbf7d0');
                        $qtyColor = $szIsOut ? '#dc2626' : ($szIsLow ? '#b45309' : '#15803d');
                        $variantSku = $group['sku'] ? $colorSkuCode.'-'.$sz['size'] : '';
                    ?>
                    <div class="size-cell"
                         style="background:<?= $cellBg ?>;border-color:<?= $cellBdr ?>;"
                         data-stock-id="<?= $sz['id'] ?>"
                         data-qty="<?= $qty ?>"
                         data-min="<?= $sz['min_alert'] ?>"
                         title="<?= htmlspecialchars($variantSku ?: $sz['size']) ?> · คลิกแก้ไข"
                         onclick="startEdit(this)">
                        <span class="size-label"><?= htmlspecialchars($sz['size']) ?></span>
                        <span class="qty-val" style="color:<?= $qtyColor ?>;"><?= $qty ?></span>
                        <span class="qty-status" style="color:<?= $qtyColor ?>;"><?= $szIsOut ? 'หมด' : ($szIsLow ? '⚠️' : '') ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>

            </div>
            <?php endforeach; ?>

            <!-- Row footer -->
            <div class="prow-footer">
                <a href="<?= SITE_URL ?>/pages/product-add.php?edit=<?= $group['product_id'] ?>"
                   class="btn btn-outline-secondary btn-sm" style="font-size:.78rem;">
                    <i class="fas fa-pen me-1"></i> แก้ไขสินค้า
                </a>
            </div>

        </div><!-- .prow-body -->
    </div><!-- .prow -->
    <?php endforeach; ?>
    </div>

    <?php endif; ?>
</div>

<!-- ── Restock modal ──────────────────────────────────────────────────────────── -->
<div class="modal fade" id="restockModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:400px;">
        <div class="modal-content" style="border-radius:16px;border:none;">
            <div class="modal-header" style="background:linear-gradient(135deg,#E91E8C,#9B72CF);color:#fff;border:none;border-radius:16px 16px 0 0;">
                <div>
                    <h5 class="modal-title mb-0" style="font-size:.95rem;">📥 เติมสต็อก</h5>
                    <div id="restockSubtitle" style="font-size:.75rem;opacity:.85;margin-top:1px;"></div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3" id="restockBody"></div>
            <div class="modal-footer border-0 pt-0">
                <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
                <button class="btn btn-primary btn-sm" id="restockConfirmBtn" onclick="submitRestockCard()">
                    <i class="fas fa-check me-1"></i> ยืนยัน
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ── Hidden print area (used during window.print()) ────────────────────────── -->
<div id="barcodesPrintArea"></div>

<!-- ── Print preview modal ───────────────────────────────────────────────────── -->
<div class="modal fade" id="printModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="border-radius:16px;border:none;">
            <div class="modal-header" style="border-bottom:1px solid #f3f4f6;">
                <div>
                    <h5 class="modal-title mb-0" style="font-size:.95rem;">
                        <i class="fas fa-barcode me-2" style="color:#7c3aed;"></i>พิมพ์บาร์โค้ด
                    </h5>
                    <div id="printModalTitle" style="font-size:.75rem;color:#9ca3af;margin-top:2px;"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <!-- Tip -->
                <div style="background:#f5f3ff;border-radius:9px;padding:9px 12px;margin-bottom:12px;font-size:.75rem;color:#6d28d9;">
                    <i class="fas fa-lightbulb me-1"></i>
                    แนะนำ: ใช้กระดาษสติ๊กเกอร์ A4 แบบ 4×3 ช่อง หรือ Brother / Dymo label paper
                    · รหัสบาร์โค้ดฟอร์แมต <b>CODE128</b>
                </div>
                <!-- Label grid preview -->
                <div id="printLabelsGrid" class="bc-preview-grid"></div>
            </div>
            <div class="modal-footer" style="border-top:1px solid #f3f4f6;gap:8px;">
                <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">ปิด</button>
                <button class="btn btn-sm" id="doPrintBtn"
                        style="background:linear-gradient(135deg,#7c3aed,#E91E8C);color:#fff;border:none;"
                        onclick="doPrintBarcodes()">
                    <i class="fas fa-print me-1"></i> สั่งพิมพ์
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// ── Toggle accordion row ───────────────────────────────────────────────────────
function toggleRow(pid) {
    document.getElementById('prow-' + pid).classList.toggle('open');
}

// ── Inline edit ───────────────────────────────────────────────────────────────
function startEdit(cell) {
    if (cell.querySelector('.qty-input')) return;
    cell.classList.add('editing');
    const qtyEl   = cell.querySelector('.qty-val');
    const stockId = cell.dataset.stockId;
    const current = parseInt(cell.dataset.qty);

    const input = document.createElement('input');
    input.type      = 'number';
    input.min       = 0;
    input.value     = current;
    input.className = 'qty-input';
    input.style.color = qtyEl.style.color;
    input.onclick   = (e) => e.stopPropagation();
    input.onblur    = () => saveEdit(cell, stockId, input, current);
    input.onkeydown = (e) => {
        if (e.key === 'Enter')  { e.preventDefault(); input.blur(); }
        if (e.key === 'Escape') { input.onblur = null; cell.classList.remove('editing'); qtyEl.style.display=''; input.remove(); }
    };
    qtyEl.style.display = 'none';
    cell.querySelector('.qty-status').style.display = 'none';
    cell.insertBefore(input, cell.querySelector('.qty-status'));
    input.focus(); input.select();
}

async function saveEdit(cell, stockId, input, original) {
    cell.classList.remove('editing');
    const newQty = parseInt(input.value);
    const qtyEl  = cell.querySelector('.qty-val');
    const statEl = cell.querySelector('.qty-status');
    input.remove();
    qtyEl.style.display = '';
    statEl.style.display = '';

    if (isNaN(newQty) || newQty < 0 || newQty === original) return;

    const fd = new FormData();
    fd.append('action',   'adjust');
    fd.append('stock_id', stockId);
    fd.append('new_qty',  newQty);
    fd.append('reason',   'แก้ไขจากหน้าสต็อก');

    try {
        const res  = await fetch('', { method:'POST', body:fd });
        const data = await res.json();
        if (!data.ok) throw new Error();

        const min = parseInt(cell.dataset.min);
        cell.dataset.qty = newQty;
        qtyEl.textContent = newQty;

        const isOut = newQty === 0;
        const isLow = !isOut && newQty <= min;
        const fg = isOut ? '#dc2626' : (isLow ? '#b45309' : '#15803d');
        const bg = isOut ? '#fef2f2' : (isLow ? '#fffbeb' : '#f0fdf4');
        const br = isOut ? '#fca5a5' : (isLow ? '#fde68a' : '#bbf7d0');

        qtyEl.style.color      = fg;
        cell.style.background  = bg;
        cell.style.borderColor = br;
        statEl.style.color     = fg;
        statEl.textContent     = isOut ? 'หมด' : (isLow ? '⚠️' : '');

        cell.classList.add('flash-ok');
        cell.addEventListener('animationend', () => cell.classList.remove('flash-ok'), { once:true });

        updateTotals(cell);
    } catch (e) {
        cell.classList.add('flash-err');
        cell.addEventListener('animationend', () => cell.classList.remove('flash-err'), { once:true });
    }
}

function updateTotals(cell) {
    // Color block total
    const block = cell.closest('.color-block');
    if (block) {
        let ct = 0, co = false, cl = false;
        block.querySelectorAll('.size-cell').forEach(c => {
            const q = parseInt(c.dataset.qty), m = parseInt(c.dataset.min);
            ct += q; if (q===0) co=true; if (q>0&&q<=m) cl=true;
        });
        const cb = block.querySelector('.cqty');
        if (cb) {
            const out = ct===0;
            cb.className = 'cqty ' + (out?'out':(co||cl?'warn':'ok'));
            cb.textContent = (out?'❌':(co||cl?'⚠️':' '))+' '+ct+' ชิ้น';
        }
    }
    // Product row total
    const row = cell.closest('.prow');
    if (row) {
        let total = 0, hasOut = false, hasLow = false;
        row.querySelectorAll('.size-cell').forEach(c => {
            const q = parseInt(c.dataset.qty), m = parseInt(c.dataset.min);
            total += q; if (q===0) hasOut=true; if (q>0&&q<=m) hasLow=true;
        });
        const badge = row.querySelector('.prow-qty');
        if (badge) {
            const out = total===0;
            badge.className = 'prow-qty ' + (out?'out':(hasOut||hasLow?'warn':'ok'));
            badge.textContent = (out?'❌':(hasOut||hasLow?'⚠️':'✅'))+' '+total+' ชิ้น';
        }
    }
}

// ── Restock modal ─────────────────────────────────────────────────────────────
function openRestockCard(sizes, productName, color) {
    document.getElementById('restockSubtitle').textContent = productName + ' · ' + color;
    let html = '<div class="mb-3"><label class="form-label fw-semibold" style="font-size:.82rem;">หมายเหตุ</label>'
             + '<input type="text" id="restockCardReason" class="form-control form-control-sm" placeholder="รับสินค้าจากซัพพลายเออร์"></div>'
             + '<div class="row g-2">';
    sizes.forEach((sz, i) => {
        const isOut = sz.quantity === 0;
        html += `<div class="col-6">
            <div style="border:1.5px solid ${isOut?'#fecaca':'#e5e7eb'};border-radius:10px;padding:9px;background:${isOut?'#fff5f5':'#fff'};">
                <div style="font-size:.7rem;color:#6b7280;font-weight:700;">${sz.size}</div>
                <div style="font-size:.78rem;color:#374151;margin-bottom:5px;">มี: <b>${sz.quantity}</b>${isOut?' <span style="color:#dc2626;font-size:.7rem;">(หมด)</span>':''}</div>
                <div class="input-group input-group-sm">
                    <span class="input-group-text" style="font-size:.72rem;">+</span>
                    <input type="number" class="form-control restock-qty-input" min="0" value="${isOut?10:0}"
                           data-stock-id="${sz.id}" data-idx="${i}">
                </div>
            </div>
        </div>`;
    });
    html += '</div>';
    document.getElementById('restockBody').innerHTML = html;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('restockModal')).show();
}

async function submitRestockCard() {
    const reason = document.getElementById('restockCardReason').value || 'รับสินค้าเพิ่ม';
    const inputs = document.querySelectorAll('.restock-qty-input');
    const btn    = document.getElementById('restockConfirmBtn');
    btn.disabled  = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>บันทึก...';

    let saved = 0;
    for (const inp of inputs) {
        const qty = parseInt(inp.value);
        if (!qty || qty <= 0) continue;
        const fd = new FormData();
        fd.append('action',   'restock');
        fd.append('stock_id', inp.dataset.stockId);
        fd.append('add_qty',  qty);
        fd.append('reason',   reason);
        const res = await fetch('', { method:'POST', body:fd });
        const d   = await res.json();
        if (d.ok) saved++;
    }
    bootstrap.Modal.getInstance(document.getElementById('restockModal')).hide();
    btn.disabled  = false;
    btn.innerHTML = '<i class="fas fa-check me-1"></i> ยืนยัน';
    if (saved > 0) location.reload();
}

// ══════════════════════════════════════════════════════════════════════════════
// SCAN MODE
// ══════════════════════════════════════════════════════════════════════════════
let _scanActive = false;
let _scanTotal  = 0;

function toggleScanMode() {
    _scanActive = !_scanActive;
    const bar = document.getElementById('scanBar');
    const btn = document.getElementById('scanModeBtn');
    bar.classList.toggle('d-none', !_scanActive);
    if (_scanActive) {
        btn.innerHTML = '<i class="fas fa-stop me-1" style="color:#E91E8C;"></i> ปิดสแกน';
        btn.classList.replace('btn-outline-secondary','btn-outline-danger');
        document.getElementById('scanInput').focus();
    } else {
        btn.innerHTML = '<i class="fas fa-barcode me-1"></i> โหมดสแกน';
        btn.classList.replace('btn-outline-danger','btn-outline-secondary');
    }
}

// Capture Enter from the visible scan input
document.getElementById('scanInput').addEventListener('keydown', function(e) {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    const val = this.value.trim();
    if (val) { processBarcode(val); this.value = ''; }
});

// Global keyboard capture — so scanner works without clicking the input first
let _globalBuf = '', _globalTimer = null;
document.addEventListener('keydown', function(e) {
    if (!_scanActive) return;
    // If focus is on another form element (not scan input), skip
    const active = document.activeElement;
    if (active && ['INPUT','TEXTAREA','SELECT'].includes(active.tagName) && active.id !== 'scanInput') return;

    if (e.key === 'Enter') {
        clearTimeout(_globalTimer);
        if (_globalBuf.trim()) {
            processBarcode(_globalBuf.trim());
            _globalBuf = '';
            document.getElementById('scanInput').value = '';
        }
        return;
    }
    if (e.key.length === 1) {
        _globalBuf += e.key;
        document.getElementById('scanInput').value = _globalBuf;
        clearTimeout(_globalTimer);
        _globalTimer = setTimeout(() => { _globalBuf = ''; }, 600);
    }
});

async function processBarcode(barcode) {
    const fd = new FormData();
    fd.append('action',  'scan');
    fd.append('barcode', barcode);

    let result;
    try {
        const res = await fetch('', { method:'POST', body:fd });
        result = await res.json();
    } catch(e) {
        addScanLog(false, barcode, 'เชื่อมต่อล้มเหลว');
        return;
    }

    if (!result.ok) {
        addScanLog(false, barcode, result.msg || 'ไม่พบสินค้า');
        return;
    }

    _scanTotal++;
    document.getElementById('scanCountNum').textContent = _scanTotal;

    const msg = `${result.product_name} · ${result.color} · ${result.size}  ${result.old_qty} → <b>${result.new_qty}</b> ชิ้น`;
    addScanLog(true, result.sku + '-' + result.size, msg);

    // Auto-expand the product row and flash the size cell
    const row = document.getElementById('prow-' + result.product_id);
    if (row) {
        if (!row.classList.contains('open')) row.classList.add('open');
        const cell = row.querySelector(`.size-cell[data-stock-id="${result.stock_id}"]`);
        if (cell) {
            cell.dataset.qty = result.new_qty;
            cell.querySelector('.qty-val').textContent = result.new_qty;
            cell.classList.add('scan-flash');
            cell.addEventListener('animationend', () => cell.classList.remove('scan-flash'), { once:true });
            updateTotals(cell);
            cell.scrollIntoView({ behavior:'smooth', block:'nearest' });
        }
    }
}

function addScanLog(ok, code, msg) {
    const log  = document.getElementById('scanLog');
    const item = document.createElement('div');
    item.className = 'scan-log-item ' + (ok ? 'ok' : 'err');
    item.innerHTML = `
        <span style="font-size:.9rem;">${ok ? '✅' : '❌'}</span>
        <code style="font-size:.72rem;color:${ok?'#374151':'#991b1b'};">${code}</code>
        <span style="flex:1;">${msg}</span>
        <span style="font-size:.65rem;color:#9ca3af;">${new Date().toLocaleTimeString('th-TH',{hour:'2-digit',minute:'2-digit',second:'2-digit'})}</span>`;
    log.prepend(item);
    // Keep only last 15 entries
    while (log.children.length > 15) log.removeChild(log.lastChild);
}

// ══════════════════════════════════════════════════════════════════════════════
// PRINT BARCODES
// ══════════════════════════════════════════════════════════════════════════════
let _printLabelsData = [];

function openPrintModal(labels, title) {
    _printLabelsData = labels;
    document.getElementById('printModalTitle').textContent = title || '';

    // Render preview grid
    const grid = document.getElementById('printLabelsGrid');
    grid.innerHTML = labels.map((l, i) =>
        `<div class="bc-preview-label">
            <svg id="pbc${i}"></svg>
            <div class="bc-preview-sku">${l.variantSku}</div>
            <div class="bc-preview-info">${l.productName} · ${l.colorTh} · ${l.size}</div>
        </div>`
    ).join('');

    // Generate barcodes (JsBarcode works even before the modal is visible)
    labels.forEach((l, i) => {
        try {
            JsBarcode('#pbc' + i, l.variantSku, {
                format: 'CODE128', width: 1.4, height: 38,
                displayValue: false, margin: 2,
            });
        } catch(e) { /* invalid chars — skip */ }
    });

    bootstrap.Modal.getOrCreateInstance(document.getElementById('printModal')).show();
}

function doPrintBarcodes() {
    const pa = document.getElementById('barcodesPrintArea');
    pa.innerHTML = _printLabelsData.map((l, i) =>
        `<div class="bc-print-label">
            <svg id="bcp${i}"></svg>
            <div class="bc-print-sku">${l.variantSku}</div>
            <div class="bc-print-info">${l.productName} · ${l.colorTh} · ${l.size}</div>
        </div>`
    ).join('');

    // Generate barcodes in print area
    _printLabelsData.forEach((l, i) => {
        try {
            JsBarcode('#bcp' + i, l.variantSku, {
                format: 'CODE128', width: 1.2, height: 32,
                displayValue: false, margin: 2,
            });
        } catch(e) {}
    });

    document.body.classList.add('printing-bc');
    window.print();
    document.body.classList.remove('printing-bc');
}
</script>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
