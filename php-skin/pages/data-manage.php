<?php
$pageTitle = 'จัดการข้อมูล';
require_once __DIR__ . '/../config/database.php';
requireAdmin();
$pdo = getDB();

$msg = '';
$msgType = '';

// ── Handle clear actions ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $confirm = trim($_POST['confirm_text'] ?? '');
    $action  = $_POST['action'] ?? '';

    if ($confirm !== 'DELETE') {
        $msg     = 'กรุณาพิมพ์ DELETE (ตัวพิมพ์ใหญ่) เพื่อยืนยัน';
        $msgType = 'danger';
    } else {
        try {
            $pdo->exec("SET FOREIGN_KEY_CHECKS=0");

            if (in_array($action, ['clear_orders', 'clear_all'])) {
                $pdo->exec("TRUNCATE TABLE order_items");
                $pdo->exec("TRUNCATE TABLE orders");
            }
            if (in_array($action, ['clear_stock', 'clear_all'])) {
                $pdo->exec("TRUNCATE TABLE stock_movements");
                $pdo->exec("TRUNCATE TABLE stock");
            }
            if (in_array($action, ['clear_products', 'clear_all'])) {
                $pdo->exec("TRUNCATE TABLE stock_movements");
                $pdo->exec("TRUNCATE TABLE stock");
                $pdo->exec("TRUNCATE TABLE order_items");
                $pdo->exec("TRUNCATE TABLE orders");
                $pdo->exec("TRUNCATE TABLE products");
            }

            $pdo->exec("SET FOREIGN_KEY_CHECKS=1");

            $labels = [
                'clear_orders'   => 'ออเดอร์ทั้งหมด',
                'clear_stock'    => 'ข้อมูลสต็อก',
                'clear_products' => 'สินค้า + สต็อก + ออเดอร์ทั้งหมด',
                'clear_all'      => 'ข้อมูลทั้งหมด',
            ];
            $msg     = '✅ ลบ' . ($labels[$action] ?? '') . 'เรียบร้อยแล้ว';
            $msgType = 'success';
        } catch (Exception $e) {
            $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
            $msg     = '❌ เกิดข้อผิดพลาด: ' . $e->getMessage();
            $msgType = 'danger';
        }
    }
}

// ── Current counts ────────────────────────────────────────────────────────────
$cntProducts = $pdo->query("SELECT COUNT(*) FROM products WHERE status != 'inactive'")->fetchColumn();
$cntVariants = $pdo->query("SELECT COUNT(*) FROM stock")->fetchColumn();
$cntStockQty = $pdo->query("SELECT COALESCE(SUM(quantity),0) FROM stock")->fetchColumn();
$cntOrders   = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$cntOrderItems = $pdo->query("SELECT COUNT(*) FROM order_items")->fetchColumn();
$cntMovements  = $pdo->query("SELECT COUNT(*) FROM stock_movements")->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid fade-in" style="max-width:960px;">
    <div class="page-header">
        <div>
            <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= SITE_URL ?>/dashboard.php">หน้าแรก</a></li><li class="breadcrumb-item active">จัดการข้อมูล</li></ol></nav>
            <h1 class="page-title">🗂️ จัดการข้อมูล</h1>
            <p class="page-subtitle">Export / Import CSV · เคลียร์ข้อมูลทดสอบ</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= SITE_URL ?>/pages/import.php" class="btn btn-primary">
                <i class="fas fa-file-import me-1"></i> Import CSV
            </a>
        </div>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?> alert-dismissible fade show">
        <?= $msg ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- ── Current Data Stats ──────────────────────────────────────────── -->
    <div class="card mb-4">
        <div class="card-header">
            <span class="card-title">📊 ข้อมูลปัจจุบัน</span>
        </div>
        <div class="card-body p-0">
            <div class="row g-0 text-center">
                <?php
                $stats = [
                    ['label'=>'สินค้า','value'=>$cntProducts,'icon'=>'fa-tshirt','color'=>'var(--pink-dark)'],
                    ['label'=>'Stock Variants','value'=>$cntVariants,'icon'=>'fa-cubes','color'=>'#6f42c1'],
                    ['label'=>'ชิ้นรวม','value'=>number_format($cntStockQty),'icon'=>'fa-boxes-stacked','color'=>'#0d6efd'],
                    ['label'=>'ออเดอร์','value'=>$cntOrders,'icon'=>'fa-shopping-bag','color'=>'#198754'],
                    ['label'=>'รายการสินค้า','value'=>$cntOrderItems,'icon'=>'fa-list','color'=>'#fd7e14'],
                    ['label'=>'ประวัติสต็อก','value'=>$cntMovements,'icon'=>'fa-history','color'=>'#6c757d'],
                ];
                foreach ($stats as $i => $s):
                ?>
                <div class="col-6 col-md-2 py-4" style="border-right:1px solid var(--border);<?= $i===5?'border-right:none':'' ?>">
                    <i class="fas <?= $s['icon'] ?> mb-2" style="font-size:1.4rem;color:<?= $s['color'] ?>"></i>
                    <div style="font-size:1.5rem;font-weight:800;color:<?= $s['color'] ?>"><?= $s['value'] ?></div>
                    <div style="font-size:0.75rem;color:var(--text-muted);"><?= $s['label'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ── CSV Export ─────────────────────────────────────────────────── -->
    <div class="card mb-4">
        <div class="card-header">
            <span class="card-title">📤 Export CSV</span>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3 col-6">
                    <a href="<?= SITE_URL ?>/pages/export.php?type=products" class="btn btn-outline-success w-100 py-3 d-flex flex-column align-items-center gap-1">
                        <i class="fas fa-tshirt fa-lg"></i>
                        <span class="fw-semibold">สินค้า</span>
                        <small class="text-muted"><?= $cntProducts ?> รายการ</small>
                    </a>
                </div>
                <div class="col-md-3 col-6">
                    <a href="<?= SITE_URL ?>/pages/export.php?type=stock" class="btn btn-outline-success w-100 py-3 d-flex flex-column align-items-center gap-1">
                        <i class="fas fa-cubes fa-lg"></i>
                        <span class="fw-semibold">สต็อก</span>
                        <small class="text-muted"><?= $cntVariants ?> Variants</small>
                    </a>
                </div>
                <div class="col-md-3 col-6">
                    <a href="<?= SITE_URL ?>/pages/export.php?type=orders" class="btn btn-outline-success w-100 py-3 d-flex flex-column align-items-center gap-1">
                        <i class="fas fa-shopping-bag fa-lg"></i>
                        <span class="fw-semibold">ออเดอร์</span>
                        <small class="text-muted"><?= $cntOrders ?> รายการ</small>
                    </a>
                </div>
                <div class="col-md-3 col-6">
                    <a href="<?= SITE_URL ?>/pages/export.php?type=customers" class="btn btn-outline-success w-100 py-3 d-flex flex-column align-items-center gap-1">
                        <i class="fas fa-users fa-lg"></i>
                        <span class="fw-semibold">ลูกค้า</span>
                        <small class="text-muted">Lifetime</small>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- ── CSV Import ─────────────────────────────────────────────────── -->
    <div class="card mb-4">
        <div class="card-header">
            <span class="card-title">📥 Import CSV</span>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <a href="<?= SITE_URL ?>/pages/import.php?tab=products" class="btn btn-outline-primary w-100 py-3 d-flex flex-column align-items-center gap-1">
                        <i class="fas fa-tshirt fa-lg"></i>
                        <span class="fw-semibold">Import สินค้า</span>
                        <small class="text-muted">sku, name, category, cost, price, tags</small>
                    </a>
                </div>
                <div class="col-md-6">
                    <a href="<?= SITE_URL ?>/pages/import.php?tab=stock" class="btn btn-outline-primary w-100 py-3 d-flex flex-column align-items-center gap-1">
                        <i class="fas fa-cubes fa-lg"></i>
                        <span class="fw-semibold">Import สต็อก</span>
                        <small class="text-muted">sku, size, color, quantity, min_alert</small>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Danger Zone ────────────────────────────────────────────────── -->
    <div class="card mb-4" style="border:2px solid #fee2e2;">
        <div class="card-header" style="background:#fff5f5;border-bottom:1px solid #fee2e2;">
            <span class="card-title" style="color:#dc2626;">⚠️ Danger Zone — ลบข้อมูล</span>
        </div>
        <div class="card-body">
            <p class="text-muted mb-4" style="font-size:0.88rem;">
                การลบข้อมูลไม่สามารถกู้คืนได้ ใช้สำหรับเคลียร์ข้อมูลทดสอบก่อน Launch จริง
            </p>
            <div class="row g-3">

                <!-- Clear Orders -->
                <div class="col-md-4">
                    <div class="border rounded-3 p-3 h-100" style="border-color:#fca5a5!important;">
                        <div class="mb-2"><i class="fas fa-shopping-bag text-danger me-1"></i><strong>ลบออเดอร์</strong></div>
                        <p class="text-muted mb-3" style="font-size:0.8rem;">ลบ orders + order_items ทั้งหมด<br>คงสินค้าและสต็อกไว้</p>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-danger"><?= $cntOrders ?> ออเดอร์</span>
                            <span class="badge bg-secondary"><?= $cntOrderItems ?> รายการ</span>
                        </div>
                        <button class="btn btn-outline-danger btn-sm w-100 mt-2"
                            onclick="openConfirm('clear_orders','ลบออเดอร์ทั้งหมด (<?= $cntOrders ?> รายการ)')">
                            <i class="fas fa-trash me-1"></i> ลบออเดอร์
                        </button>
                    </div>
                </div>

                <!-- Clear Stock -->
                <div class="col-md-4">
                    <div class="border rounded-3 p-3 h-100" style="border-color:#fca5a5!important;">
                        <div class="mb-2"><i class="fas fa-cubes text-danger me-1"></i><strong>ลบสต็อก</strong></div>
                        <p class="text-muted mb-3" style="font-size:0.8rem;">ลบ stock + ประวัติการเคลื่อนไหว<br>คงข้อมูลสินค้าไว้</p>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-danger"><?= $cntVariants ?> Variants</span>
                            <span class="badge bg-secondary"><?= $cntMovements ?> ประวัติ</span>
                        </div>
                        <button class="btn btn-outline-danger btn-sm w-100 mt-2"
                            onclick="openConfirm('clear_stock','ลบสต็อกทั้งหมด (<?= $cntVariants ?> Variants)')">
                            <i class="fas fa-trash me-1"></i> ลบสต็อก
                        </button>
                    </div>
                </div>

                <!-- Clear All -->
                <div class="col-md-4">
                    <div class="border rounded-3 p-3 h-100" style="background:#fff5f5;border-color:#dc2626!important;">
                        <div class="mb-2"><i class="fas fa-bomb text-danger me-1"></i><strong>ลบทุกอย่าง</strong></div>
                        <p class="text-muted mb-3" style="font-size:0.8rem;">ลบสินค้า + สต็อก + ออเดอร์<br>คง category / platform / settings</p>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-danger"><?= $cntProducts ?> สินค้า</span>
                            <span class="badge bg-danger"><?= $cntOrders ?> ออเดอร์</span>
                        </div>
                        <button class="btn btn-danger btn-sm w-100 mt-2"
                            onclick="openConfirm('clear_all','ลบข้อมูลทั้งหมด (สินค้า + สต็อก + ออเดอร์)')">
                            <i class="fas fa-bomb me-1"></i> ลบทุกอย่าง
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>

</div>

<!-- Confirm Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border:2px solid #dc2626;border-radius:var(--radius);">
            <div class="modal-header" style="background:#fee2e2;">
                <h5 class="modal-title text-danger"><i class="fas fa-exclamation-triangle me-2"></i>ยืนยันการลบ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <p id="confirmDesc" class="fw-semibold mb-3"></p>
                    <p class="text-muted mb-3" style="font-size:0.88rem;">
                        การลบข้อมูลไม่สามารถกู้คืนได้ กรุณาพิมพ์ <code>DELETE</code> เพื่อยืนยัน
                    </p>
                    <input type="text" name="confirm_text" class="form-control border-danger"
                           placeholder='พิมพ์ DELETE เพื่อยืนยัน' autocomplete="off">
                    <input type="hidden" name="action" id="confirmAction">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-trash me-1"></i> ยืนยันลบ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openConfirm(action, desc) {
    document.getElementById('confirmAction').value = action;
    document.getElementById('confirmDesc').textContent = '⚠️ ' + desc;
    document.querySelector('#confirmModal input[name=confirm_text]').value = '';
    new bootstrap.Modal(document.getElementById('confirmModal')).show();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
