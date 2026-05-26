<?php
require_once __DIR__ . '/../config/database.php';
requireLogin();
$pdo = getDB();
// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $pdo->prepare("UPDATE products SET status = 'inactive' WHERE id = ?")->execute([$_GET['delete']]);
    header('Location: ' . SITE_URL . '/pages/products.php?msg=deleted');
    exit;
}


$pageTitle = 'สินค้าทั้งหมด';
require_once __DIR__ . '/../includes/header.php';

// Filters
$search   = trim($_GET['q'] ?? '');
$catFilter = $_GET['cat'] ?? '';
$statusFilter = $_GET['status'] ?? '';

$typeFilter = $_GET['type'] ?? '';

// ถ้า filter=inactive ให้แสดงสินค้าที่ปิดการขาย ถ้าไม่ได้กรอง ซ่อน inactive
$where  = [];
$params = [];
if ($statusFilter === 'inactive') {
    $where[] = "p.status = 'inactive'";
} elseif ($statusFilter) {
    $where[] = "p.status != 'inactive'";
    $where[] = "p.status = ?"; $params[] = $statusFilter;
} else {
    $where[] = "p.status != 'inactive'";
}
if ($search)    { $where[] = "(p.name LIKE ? OR p.sku LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($catFilter) { $where[] = "p.category_id = ?"; $params[] = $catFilter; }
if ($typeFilter){ $where[] = "p.product_type = ?"; $params[] = $typeFilter; }

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$products = $pdo->prepare("
    SELECT p.*, cat.name as cat_name, cat.icon as cat_icon,
           COALESCE(SUM(s.quantity), 0) as total_stock,
           COUNT(CASE WHEN s.quantity = 0 THEN 1 END) as out_sizes,
           COUNT(CASE WHEN s.quantity > 0 AND s.quantity <= s.min_alert THEN 1 END) as low_sizes
    FROM products p
    LEFT JOIN categories cat ON cat.id = p.category_id
    LEFT JOIN stock s ON s.product_id = p.id
    $whereSQL
    GROUP BY p.id
    ORDER BY p.created_at DESC
");
$products->execute($params);
$products = $products->fetchAll();

$categories = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order")->fetchAll();

// Pre-compute virtual stock for all bundles
$bundleVirtualStock = [];
$bundleRows = $pdo->query("
    SELECT bi.bundle_id, bi.product_id, bi.size, bi.color, bi.quantity as bi_qty,
           COALESCE(s.quantity,0) as stock_qty
    FROM bundle_items bi
    LEFT JOIN stock s ON s.product_id = bi.product_id AND s.size = bi.size AND s.color = bi.color
")->fetchAll();
$bundleComponents = [];
foreach ($bundleRows as $br) {
    $bundleComponents[$br['bundle_id']][] = $br;
}
foreach ($bundleComponents as $bid => $comps) {
    $min = PHP_INT_MAX;
    foreach ($comps as $c) {
        $sets = $c['bi_qty'] > 0 ? floor($c['stock_qty'] / $c['bi_qty']) : 0;
        $min = min($min, $sets);
    }
    $bundleVirtualStock[$bid] = ($min === PHP_INT_MAX) ? 0 : $min;
}
?>

<div class="container-fluid fade-in">
    <div class="page-header">
        <div>
            <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= SITE_URL ?>/dashboard.php">หน้าแรก</a></li><li class="breadcrumb-item active">สินค้า</li></ol></nav>
            <h1 class="page-title">👕 สินค้าทั้งหมด</h1>
            <p class="page-subtitle">จัดการสินค้าเสื้อผ้าเด็กทั้งหมด <?= count($products) ?> รายการ</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= SITE_URL ?>/pages/export.php?type=products" class="btn btn-outline-success btn-sm">
                <i class="fas fa-file-csv me-1"></i> Export CSV
            </a>
            <a href="<?= SITE_URL ?>/pages/import.php?tab=products" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-file-import me-1"></i> Import CSV
            </a>
            <a href="<?= SITE_URL ?>/pages/product-add.php" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> เพิ่มสินค้าใหม่
            </a>
            <a href="<?= SITE_URL ?>/pages/product-add.php" onclick="sessionStorage.setItem('initBundleMode','1')" class="btn btn-kawaii">
                <i class="fas fa-boxes-stacked me-1"></i> สร้างเซต
            </a>
        </div>
    </div>

    <?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success alert-auto">✅ <?= $_GET['msg'] === 'saved' ? 'บันทึกสินค้าเรียบร้อย' : 'ลบสินค้าเรียบร้อย' ?></div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body py-3">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <input type="text" name="q" class="form-control" placeholder="🔍 ค้นหาชื่อสินค้า, รหัสสินค้า..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-3">
                    <select name="cat" class="form-select">
                        <option value="">📂 ทุกหมวดหมู่</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $catFilter == $cat['id'] ? 'selected' : '' ?>><?= $cat['icon'] ?> <?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">ทุกสถานะ</option>
                        <option value="active" <?= $statusFilter==='active'?'selected':'' ?>>✅ ขายอยู่</option>
                        <option value="out_of_stock" <?= $statusFilter==='out_of_stock'?'selected':'' ?>>❌ หมดสต็อก</option>
                        <option value="inactive" <?= $statusFilter==='inactive'?'selected':'' ?>>🚫 ปิดการขาย</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="type" class="form-select">
                        <option value="">ทุกประเภท</option>
                        <option value="single" <?= $typeFilter==='single'?'selected':'' ?>>👕 ชิ้นเดียว</option>
                        <option value="bundle" <?= $typeFilter==='bundle'?'selected':'' ?>>🎁 เซต</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">กรอง</button>
                    <a href="<?= SITE_URL ?>/pages/products.php" class="btn btn-outline-secondary ms-1">ล้าง</a>
                </div>
            </form>
        </div>
    </div>

    <!-- View toggle -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <span class="text-muted" style="font-size:0.88rem;">แสดง <?= count($products) ?> รายการ</span>
        <div class="btn-group btn-group-sm">
            <button class="btn btn-outline-secondary active" id="viewGrid" onclick="switchView('grid')"><i class="fas fa-grid-2"></i></button>
            <button class="btn btn-outline-secondary" id="viewTable" onclick="switchView('table')"><i class="fas fa-list"></i></button>
        </div>
    </div>

    <!-- Grid View -->
    <div id="gridView" class="row g-3">
        <?php foreach ($products as $product): ?>
        <div class="col-6 col-md-4 col-lg-3">
            <div class="product-card h-100">
                <?php if ($product['main_image']): ?>
                <img src="<?= SITE_URL ?>/assets/uploads/products/<?= htmlspecialchars($product['main_image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                <?php else: ?>
                <div class="product-no-img"><?= $product['cat_icon'] ?? '👶' ?></div>
                <?php endif; ?>
                <div class="product-info">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <div class="d-flex gap-1 align-items-center flex-wrap">
                            <span style="font-size:0.72rem;color:var(--text-muted);"><?= htmlspecialchars($product['sku'] ?? '') ?></span>
                            <?php if ($product['product_type'] === 'bundle'): ?>
                            <span style="background:linear-gradient(135deg,#9B72CF,#7B52AF);color:#fff;font-size:0.62rem;font-weight:700;padding:2px 7px;border-radius:20px;">🎁 เซต</span>
                            <?php endif; ?>
                        </div>
                        <?php
                        if ($product['product_type'] === 'bundle') {
                            $vs = $bundleVirtualStock[$product['id']] ?? 0;
                            $stockClass = $vs === 0 ? 'badge-out' : ($vs <= 3 ? 'badge-low' : 'badge-active');
                            $stockLabel = $vs === 0 ? 'หมด' : $vs . ' เซต';
                        } else {
                            $stockClass = 'badge-active';
                            $stockLabel = $product['total_stock'] . ' ชิ้น';
                            if ($product['total_stock'] == 0) { $stockClass = 'badge-out'; $stockLabel = 'หมด'; }
                            elseif ($product['low_sizes'] > 0) { $stockClass = 'badge-low'; $stockLabel = '⚠️ ' . $product['total_stock']; }
                        }
                        ?>
                        <span class="badge-status <?= $stockClass ?>"><?= $stockLabel ?></span>
                    </div>
                    <div class="product-name"><?= htmlspecialchars($product['name']) ?></div>
                    <div style="font-size:0.75rem;color:var(--text-muted);"><?= $product['cat_icon'] ?? '' ?> <?= htmlspecialchars($product['cat_name'] ?? '') ?></div>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <div class="product-price"><?= formatPrice($product['selling_price']) ?></div>
                        <div class="btn-group btn-group-sm">
                            <a href="<?= SITE_URL ?>/pages/product-add.php?edit=<?= $product['id'] ?>" class="btn btn-outline-secondary btn-sm" title="แก้ไข"><i class="fas fa-edit"></i></a>
                            <?php if ($product['product_type'] !== 'bundle'): ?>
                            <a href="<?= SITE_URL ?>/pages/stock.php?product=<?= $product['id'] ?>" class="btn btn-outline-secondary btn-sm" title="สต็อก"><i class="fas fa-cubes"></i></a>
                            <?php else: ?>
                            <button type="button" class="btn btn-outline-secondary btn-sm" title="ดูส่วนประกอบเซต"
                                onclick="showBundleDetail(<?= $product['id'] ?>, <?= htmlspecialchars(json_encode($product['name']), ENT_QUOTES) ?>)">
                                <i class="fas fa-list-check"></i>
                            </button>
                            <?php endif; ?>
                            <a href="?delete=<?= $product['id'] ?>" class="btn btn-outline-danger btn-sm btn-delete-confirm" title="ลบ"><i class="fas fa-trash"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($products)): ?>
        <div class="col-12 text-center py-5 text-muted">
            <div style="font-size:3rem;">🔍</div>
            <p>ไม่พบสินค้าที่ตรงกับเงื่อนไข</p>
            <a href="<?= SITE_URL ?>/pages/product-add.php" class="btn btn-primary">+ เพิ่มสินค้าแรก</a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Table View (hidden by default) -->
    <div id="tableView" style="display:none;">
        <div class="card">
            <div class="table-responsive">
                <table class="table mb-0" id="dataTable">
                    <thead>
                        <tr>
                            <th>รูป</th><th>ชื่อสินค้า / รหัส</th><th>หมวดหมู่</th>
                            <th>ราคาขาย</th><th>ต้นทุน</th><th>สต็อกรวม</th><th>สถานะ</th><th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $p): ?>
                        <tr>
                            <td>
                                <?php if ($p['main_image']): ?>
                                <img src="<?= SITE_URL ?>/assets/uploads/products/<?= htmlspecialchars($p['main_image']) ?>" style="width:48px;height:48px;border-radius:8px;object-fit:cover;">
                                <?php else: ?>
                                <div style="width:48px;height:48px;border-radius:8px;background:var(--pink-light);display:flex;align-items:center;justify-content:center;"><?= $p['cat_icon'] ?? '👶' ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div>
                                        <div style="font-weight:600;"><?= htmlspecialchars($p['name']) ?></div>
                                        <div style="font-size:0.75rem;color:var(--text-muted);">
                                            <?= htmlspecialchars($p['sku'] ?? '') ?>
                                            <?php if ($p['product_type']==='bundle'): ?>
                                            <span style="background:linear-gradient(135deg,#9B72CF,#7B52AF);color:#fff;font-size:0.62rem;padding:1px 6px;border-radius:20px;margin-left:4px;">🎁 เซต</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td><?= $p['cat_icon'] ?? '' ?> <?= htmlspecialchars($p['cat_name'] ?? '-') ?></td>
                            <td style="font-weight:600;color:var(--pink-dark);"><?= formatPrice($p['selling_price']) ?></td>
                            <td><?= formatPrice($p['cost_price']) ?></td>
                            <td>
                                <?php if ($p['product_type']==='bundle'): ?>
                                <?php $vs = $bundleVirtualStock[$p['id']] ?? 0; ?>
                                <span class="badge-status <?= $vs===0?'badge-out':($vs<=3?'badge-low':'badge-active') ?>">
                                    <?= $vs ?> เซต
                                </span>
                                <?php else: ?>
                                <span class="badge-status <?= $p['total_stock']==0?'badge-out':($p['low_sizes']>0?'badge-low':'badge-active') ?>">
                                    <?= $p['total_stock'] ?> ชิ้น
                                </span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge-status badge-<?= $p['status'] ?>"><?= $p['status']==='active'?'ขายอยู่':'หมดสต็อก' ?></span></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="<?= SITE_URL ?>/pages/product-add.php?edit=<?= $p['id'] ?>" class="btn btn-outline-primary btn-sm">แก้ไข</a>
                                    <?php if ($p['product_type']!=='bundle'): ?>
                                    <a href="<?= SITE_URL ?>/pages/stock.php?product=<?= $p['id'] ?>" class="btn btn-outline-secondary btn-sm">สต็อก</a>
                                    <?php endif; ?>
                                    <a href="?delete=<?= $p['id'] ?>" class="btn btn-outline-danger btn-sm btn-delete-confirm">ลบ</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
<!-- Bundle Detail Modal -->
<div class="modal fade" id="bundleDetailModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:var(--radius);border:none;">
            <div class="modal-header" style="background:linear-gradient(135deg,#9B72CF,#7B52AF);color:#fff;border-radius:var(--radius) var(--radius) 0 0;">
                <h5 class="modal-title">🎁 ส่วนประกอบเซต: <span id="bundleDetailName"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" id="bundleDetailBody">
                <div class="text-center py-4"><div class="spinner-border text-pink" role="status"></div></div>
            </div>
        </div>
    </div>
</div>

<?php
// Bundle detail data for modal (all bundles pre-loaded)
$allBundleDetails = [];
foreach ($bundleComponents as $bid => $comps) {
    $allBundleDetails[$bid] = $comps;
}
// Attach product names
foreach ($allBundleDetails as $bid => &$comps) {
    foreach ($comps as &$c) {
        // name already from bundleRows? No — bundleRows doesn't have name
        // We need to fetch it
    }
}
// Fetch full bundle detail with product names
$bundleDetailFull = [];
if (!empty($bundleComponents)) {
    $allBi = $pdo->query("
        SELECT bi.bundle_id, bi.product_id, bi.size, bi.color, bi.quantity as bi_qty,
               p.name as product_name, p.sku,
               COALESCE(s.quantity,0) as stock_qty
        FROM bundle_items bi
        JOIN products p ON p.id = bi.product_id
        LEFT JOIN stock s ON s.product_id=bi.product_id AND s.size=bi.size AND s.color=bi.color
        ORDER BY bi.bundle_id, bi.id
    ")->fetchAll();
    foreach ($allBi as $row) {
        $bundleDetailFull[$row['bundle_id']][] = $row;
    }
}
?>
<script>
const BUNDLE_DETAIL = <?= json_encode($bundleDetailFull, JSON_UNESCAPED_UNICODE) ?>;
const BUNDLE_VIRTUAL_STOCK = <?= json_encode($bundleVirtualStock) ?>;

function switchView(mode) {
    document.getElementById('gridView').style.display  = mode === 'grid' ? '' : 'none';
    document.getElementById('tableView').style.display = mode === 'table' ? '' : 'none';
    document.getElementById('viewGrid').classList.toggle('active', mode === 'grid');
    document.getElementById('viewTable').classList.toggle('active', mode === 'table');
}

function showBundleDetail(bundleId, bundleName) {
    document.getElementById('bundleDetailName').textContent = bundleName;
    const items   = BUNDLE_DETAIL[bundleId] || [];
    const vStock  = BUNDLE_VIRTUAL_STOCK[bundleId] ?? 0;

    if (!items.length) {
        document.getElementById('bundleDetailBody').innerHTML = '<p class="p-4 text-muted text-center">ไม่พบส่วนประกอบ</p>';
    } else {
        let html = `<div class="table-responsive"><table class="table mb-0" style="font-size:0.85rem;">
            <thead><tr>
                <th>สินค้า</th><th>ไซต์</th><th>สี</th>
                <th class="text-center">ต้องการ</th><th class="text-center">สต็อก</th>
            </tr></thead><tbody>`;
        items.forEach(item => {
            const need = item.bi_qty;
            const have = item.stock_qty;
            const cls  = have === 0 ? 'badge-out' : have < need ? 'badge-low' : 'badge-active';
            html += `<tr>
                <td><div style="font-weight:600">${item.product_name}</div>
                    <div style="font-size:0.72rem;color:var(--text-muted)">${item.sku}</div></td>
                <td>${item.size}</td>
                <td>${item.color}</td>
                <td class="text-center">${need} ชิ้น</td>
                <td class="text-center"><span class="badge-status ${cls}">${have} ชิ้น</span></td>
            </tr>`;
        });
        const vsClass = vStock===0?'badge-out':vStock<=3?'badge-low':'badge-active';
        html += `</tbody></table></div>
            <div class="px-4 py-3 border-top d-flex justify-content-between align-items-center" style="background:var(--lavender);">
                <span style="font-size:0.85rem;font-weight:600;">สต็อกเซตที่ทำได้ทั้งหมด:</span>
                <span class="badge-status ${vsClass}" style="font-size:0.95rem;">${vStock} เซต</span>
            </div>`;
        document.getElementById('bundleDetailBody').innerHTML = html;
    }
    new bootstrap.Modal(document.getElementById('bundleDetailModal')).show();
}

// Auto-switch to bundle mode when arriving from "สร้างเซต" button
if (sessionStorage.getItem('initBundleMode') === '1') {
    sessionStorage.removeItem('initBundleMode');
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
