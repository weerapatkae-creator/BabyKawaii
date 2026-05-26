<?php
require_once __DIR__ . '/../config/database.php';
requireLogin();
$pdo = getDB();

// ── AJAX: get next available SKU ─────────────────────────────────────────────
if (isset($_GET['get_next_sku'])) {
    $type   = $_GET['type'] ?? 'single';
    if ($type === 'set') {
        $prefix = 'BK-SET-';
        $last   = $pdo->query("SELECT sku FROM products WHERE sku LIKE 'BK-SET-%' ORDER BY id DESC LIMIT 1")->fetchColumn();
    } else {
        $prefix = 'BK-';
        $last   = $pdo->query("SELECT sku FROM products WHERE sku LIKE 'BK-%' AND sku NOT LIKE 'BK-SET-%' ORDER BY id DESC LIMIT 1")->fetchColumn();
    }
    $num = 1;
    if ($last) { preg_match('/(\d+)$/', $last, $m); $num = (int)($m[1] ?? 0) + 1; }
    echo json_encode(['sku' => $prefix . str_pad($num, 3, '0', STR_PAD_LEFT)]);
    exit;
}

$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$product    = null;
$stockData  = [];
$bundleItems = [];

if ($editId) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$editId]);
    $product = $stmt->fetch();

    if ($product) {
        if ($product['product_type'] === 'bundle') {
            $bi = $pdo->prepare("
                SELECT bi.*, p.name as product_name, p.sku,
                       p.selling_price as unit_price, p.cost_price as unit_cost
                FROM bundle_items bi
                JOIN products p ON p.id = bi.product_id
                WHERE bi.bundle_id = ?
                ORDER BY bi.id
            ");
            $bi->execute([$editId]);
            $bundleItems = $bi->fetchAll();
        } else {
            $sStmt = $pdo->prepare("SELECT * FROM stock WHERE product_id = ?
                ORDER BY FIELD(size,'Premature','NB','0-3M','3-6M','6-9M','9-12M','12-18M','18-24M','Free Size')");
            $sStmt->execute([$editId]);
            $stockData = $sStmt->fetchAll();
        }
    }
}

// ─── POST HANDLER ──────────────────────────────────────────────────────────
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productType = $_POST['product_type'] ?? 'single';
    $name        = trim($_POST['name'] ?? '');
    $sku         = trim($_POST['sku'] ?? '');
    $catId       = (int)($_POST['category_id'] ?? 0);
    $desc        = trim($_POST['description'] ?? '');
    $materials   = trim($_POST['materials'] ?? '');
    $costPrice   = (float)($_POST['cost_price'] ?? 0);
    $sellPrice   = (float)($_POST['selling_price'] ?? 0);
    $tags        = trim($_POST['tags'] ?? '');
    $status      = $_POST['status'] ?? 'active';
    $isFeatured  = isset($_POST['is_featured']) ? 1 : 0;

    if (!$name)      $errors[] = 'กรุณากรอกชื่อสินค้า';
    if (!$sellPrice) $errors[] = 'กรุณากรอกราคาขาย';

    $bundlePostItems = [];
    if ($productType === 'bundle') {
        $bundlePostItems = array_values(array_filter(
            $_POST['bundle_items'] ?? [],
            fn($i) => !empty($i['product_id']) && !empty($i['size']) && !empty($i['color'])
        ));
        if (empty($bundlePostItems)) $errors[] = 'กรุณาเพิ่มสินค้าในเซตอย่างน้อย 1 รายการ';
    }

    if (empty($errors)) {
        $mainImage = $product['main_image'] ?? null;
        if (!empty($_FILES['main_image']['name'])) {
            $ext = strtolower(pathinfo($_FILES['main_image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                $filename = 'product_' . time() . '_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($_FILES['main_image']['tmp_name'], UPLOAD_PATH . 'products/' . $filename)) {
                    $mainImage = $filename;
                }
            }
        }

        if ($editId) {
            $pdo->prepare("UPDATE products SET name=?,sku=?,product_type=?,category_id=?,description=?,materials=?,cost_price=?,selling_price=?,main_image=?,tags=?,status=?,is_featured=?,updated_at=NOW() WHERE id=?")
                ->execute([$name,$sku,$productType,$catId?:null,$desc,$materials,$costPrice,$sellPrice,$mainImage,$tags,$status,$isFeatured,$editId]);
            $productId = $editId;
        } else {
            if (!$sku) {
                $lastId = $pdo->query("SELECT MAX(id) FROM products")->fetchColumn();
                $sku = 'BK-' . str_pad(($lastId + 1), 3, '0', STR_PAD_LEFT);
            }
            $pdo->prepare("INSERT INTO products (name,sku,product_type,category_id,description,materials,cost_price,selling_price,main_image,tags,status,is_featured) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$name,$sku,$productType,$catId?:null,$desc,$materials,$costPrice,$sellPrice,$mainImage,$tags,$status,$isFeatured]);
            $productId = $pdo->lastInsertId();
        }

        if ($productType === 'single') {
            if (isset($_POST['stock']) && is_array($_POST['stock'])) {
                foreach ($_POST['stock'] as $size => $colors) {
                    foreach ($colors as $color => $data) {
                        $pdo->prepare("INSERT INTO stock (product_id,size,color,quantity,min_alert) VALUES (?,?,?,?,?)
                            ON DUPLICATE KEY UPDATE quantity=VALUES(quantity),min_alert=VALUES(min_alert)")
                            ->execute([$productId,$size,$color,(int)($data['qty']??0),(int)($data['min']??5)]);
                    }
                }
            }
        } else {
            $pdo->prepare("DELETE FROM bundle_items WHERE bundle_id=?")->execute([$productId]);
            foreach ($bundlePostItems as $item) {
                $pdo->prepare("INSERT INTO bundle_items (bundle_id,product_id,size,color,quantity) VALUES (?,?,?,?,?)")
                    ->execute([$productId,(int)$item['product_id'],$item['size'],$item['color'],max(1,(int)($item['quantity']??1))]);
            }
        }

        header('Location: ' . SITE_URL . '/pages/products.php?msg=saved');
        exit;
    }
}


$pageTitle = 'เพิ่ม / แก้ไขสินค้า';
require_once __DIR__ . '/../includes/header.php';

// ─── HELPERS ────────────────────────────────────────────────────────────────
$categories   = $pdo->query("SELECT * FROM categories WHERE is_active=1 ORDER BY sort_order")->fetchAll();
$sizes        = ['Premature','NB','0-3M','3-6M','6-9M','9-12M','12-18M','18-24M','Free Size'];
$defaultColors= ['ขาว','ชมพู','ฟ้า','เหลือง','เขียว','ม่วง','แดง','ส้ม','เทา','ดำ','ลายทาง','ลายดอก','ลายการ์ตูน'];

$stockMatrix = [];
foreach ($stockData as $s) {
    $stockMatrix[$s['size']][$s['color']] = ['qty'=>$s['quantity'],'min'=>$s['min_alert']];
}

// All single products with stock for bundle builder
$rawStock = $pdo->query("
    SELECT p.id, p.name, p.sku, p.selling_price, p.cost_price,
           s.size, s.color, s.quantity as stock_qty
    FROM products p
    JOIN stock s ON s.product_id = p.id
    WHERE p.status != 'inactive' AND p.product_type = 'single'
    ORDER BY p.name, FIELD(s.size,'Premature','NB','0-3M','3-6M','6-9M','9-12M','12-18M','18-24M','Free Size'), s.color
")->fetchAll();

$stockIndex = [];
foreach ($rawStock as $r) {
    $pid = $r['id'];
    if (!isset($stockIndex[$pid])) {
        $stockIndex[$pid] = ['name'=>$r['name'],'sku'=>$r['sku'],'price'=>(float)$r['selling_price'],'cost'=>(float)$r['cost_price'],'sizes'=>[]];
    }
    if (!isset($stockIndex[$pid]['sizes'][$r['size']])) $stockIndex[$pid]['sizes'][$r['size']] = [];
    $stockIndex[$pid]['sizes'][$r['size']][] = ['color'=>$r['color'],'qty'=>(int)$r['stock_qty']];
}

$currentType = $product['product_type'] ?? 'single';
?>

<div class="container-fluid fade-in">
<div class="page-header">
    <div>
        <nav aria-label="breadcrumb"><ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/dashboard.php">หน้าแรก</a></li>
            <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/pages/products.php">สินค้า</a></li>
            <li class="breadcrumb-item active"><?= $editId ? 'แก้ไขสินค้า' : 'เพิ่มสินค้าใหม่' ?></li>
        </ol></nav>
        <h1 class="page-title"><?= $editId ? '✏️ แก้ไขสินค้า' : '➕ เพิ่มสินค้าใหม่' ?></h1>
    </div>
    <a href="<?= SITE_URL ?>/pages/products.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> กลับ</a>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" id="productForm">
<input type="hidden" name="product_type" id="productTypeInput" value="<?= htmlspecialchars($currentType) ?>">

<!-- ── TYPE SELECTOR ──────────────────────────────────── -->
<div class="card mb-4" <?= $editId ? 'style="opacity:0.85;"' : '' ?>>
    <div class="card-body py-3">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <span style="font-weight:600;font-size:0.9rem;">ประเภทสินค้า:</span>
            <div class="type-toggle-group">
                <button type="button" id="btnSingle" onclick="switchType('single')"
                    class="type-toggle-btn <?= $currentType==='single'?'active':'' ?>">
                    <i class="fas fa-tshirt me-1"></i> ชิ้นเดียว
                </button>
                <button type="button" id="btnBundle" onclick="switchType('bundle')"
                    class="type-toggle-btn bundle <?= $currentType==='bundle'?'active':'' ?>">
                    <i class="fas fa-boxes-stacked me-1"></i> เซต (Bundle)
                </button>
            </div>
            <?php if ($editId): ?>
            <small class="text-muted"><i class="fas fa-lock me-1"></i>ไม่สามารถเปลี่ยนประเภทหลังสร้างแล้ว</small>
            <?php endif; ?>
            <div id="bundleTypeBadge" class="ms-auto <?= $currentType==='bundle'?'':'d-none' ?>">
                <span style="background:linear-gradient(135deg,#FF85A2,#9B72CF);color:#fff;padding:5px 14px;border-radius:20px;font-size:0.82rem;font-weight:600;">🎁 เซตประหยัด</span>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
<!-- ── LEFT COLUMN ─────────────────────────────────────── -->
<div class="col-lg-8">

    <!-- Product Info (shared) -->
    <div class="card mb-4">
        <div class="card-header"><span class="card-title">📝 ข้อมูลสินค้า</span></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">ชื่อสินค้า <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control"
                        placeholder="เช่น เซตบอดี้สูท 3 สี (แดง+ดำ+เขียว)"
                        value="<?= htmlspecialchars($product['name'] ?? '') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">รหัสสินค้า (SKU)</label>
                    <div class="input-group">
                        <input type="text" name="sku" id="skuInput" class="form-control"
                               placeholder="กดปุ่ม 🔧 สร้างอัตโนมัติ"
                               value="<?= htmlspecialchars($product['sku'] ?? '') ?>"
                               oninput="updateVariantSKUs()">
                        <button type="button" class="btn btn-outline-secondary dropdown-toggle px-2"
                                data-bs-toggle="dropdown" aria-expanded="false" title="สร้างรหัสสินค้า">
                            <i class="fas fa-magic"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header" style="font-size:0.75rem;">สร้างรหัสอัตโนมัติ</h6></li>
                            <li>
                                <a class="dropdown-item" href="#" onclick="genSKU('single');return false;">
                                    <i class="fas fa-tshirt me-2 text-pink"></i>
                                    <strong>BK-001</strong>
                                    <span class="text-muted ms-1" style="font-size:0.78rem;">สินค้าชิ้นเดียว</span>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="#" onclick="genSKU('set');return false;">
                                    <i class="fas fa-boxes-stacked me-2" style="color:#9B72CF;"></i>
                                    <strong>BK-SET-001</strong>
                                    <span class="text-muted ms-1" style="font-size:0.78rem;">เซตสินค้า</span>
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-muted" href="#"
                                   onclick="document.getElementById('skuInput').value='';updateVariantSKUs();return false;">
                                    <i class="fas fa-eraser me-2"></i>ล้าง (ระบบสร้างให้เอง)
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div id="skuPreview" class="mt-1" style="font-size:0.72rem;color:var(--text-muted);min-height:16px;"></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">หมวดหมู่</label>
                    <select name="category_id" class="form-select">
                        <option value="">-- เลือกหมวดหมู่ --</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= ($product['category_id']??'') == $cat['id'] ? 'selected' : '' ?>>
                            <?= $cat['icon'] ?> <?= htmlspecialchars($cat['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">ราคาขาย (฿) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">฿</span>
                        <input type="number" name="selling_price" id="sellingPriceInput" class="form-control"
                            placeholder="0" step="1" min="0"
                            value="<?= $product['selling_price'] ?? '' ?>" required>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label" id="costLabel">ต้นทุน (฿)</label>
                    <div class="input-group">
                        <span class="input-group-text">฿</span>
                        <input type="number" name="cost_price" id="costPriceInput" class="form-control"
                            placeholder="0" step="1" min="0"
                            value="<?= $product['cost_price'] ?? '' ?>">
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">รายละเอียด</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="อธิบายสินค้า..."><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">วัสดุ / ผ้า</label>
                    <input type="text" name="materials" class="form-control" placeholder="เช่น Cotton 100%" value="<?= htmlspecialchars($product['materials'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">แท็ก (คั่นด้วย ,)</label>
                    <input type="text" name="tags" class="form-control" placeholder="เช่น เซต, ของขวัญ, ประหยัด" value="<?= htmlspecialchars($product['tags'] ?? '') ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- SINGLE: Stock Table -->
    <div id="singleStockSection" class="card <?= $currentType==='bundle'?'d-none':'' ?>">
        <div class="card-header">
            <span class="card-title">📦 จัดการสต็อก ตามไซต์ & สี</span>
            <button type="button" class="btn btn-sm btn-outline-pink" onclick="addColorRow()">
                <i class="fas fa-plus"></i> เพิ่มสี
            </button>
        </div>
        <div class="card-body">
            <p class="text-muted mb-3" style="font-size:0.85rem;">กรอกจำนวนสต็อกตามไซต์ที่ต้องการ (ว่างเว้น = ไม่มีไซต์นี้)</p>
            <div class="table-responsive">
                <table class="table table-bordered" id="stockTable" style="font-size:0.85rem;">
                    <thead>
                        <tr>
                            <th style="background:var(--bg);min-width:110px;">สี / ลาย</th>
                            <th style="background:var(--bg);min-width:130px;color:#9B72CF;">รหัสสี (SKU)</th>
                            <?php foreach ($sizes as $sz): ?>
                            <th style="background:var(--bg);text-align:center;min-width:78px;"><?= $sz ?></th>
                            <?php endforeach; ?>
                            <th style="background:var(--bg);width:40px;"></th>
                        </tr>
                    </thead>
                    <tbody id="stockTbody">
                        <?php
                        $existingColors = $editId ? array_unique(array_column($stockData, 'color')) : ['ขาว','ชมพู'];
                        if (empty($existingColors)) $existingColors = ['ขาว'];
                        foreach ($existingColors as $color): ?>
                        <tr class="stock-row">
                            <td>
                                <input type="text" class="form-control form-control-sm color-input"
                                       list="colorList" placeholder="สี..."
                                       value="<?= htmlspecialchars($color) ?>"
                                       oninput="updateStockNames(this);updateVariantSKUs()">
                            </td>
                            <td>
                                <div class="color-sku-badge" style="font-size:0.72rem;font-weight:700;color:#9B72CF;font-family:monospace;background:var(--lavender);border-radius:6px;padding:4px 8px;white-space:nowrap;">
                                    <?= htmlspecialchars(($product['sku'] ?? '') . ($color ? '-' . $color : '')) ?>
                                </div>
                            </td>
                            <?php foreach ($sizes as $sz): ?>
                            <td style="padding:4px;">
                                <input type="number" name="stock[<?= $sz ?>][<?= htmlspecialchars($color) ?>][qty]"
                                       class="form-control form-control-sm stock-qty text-center"
                                       placeholder="—" min="0"
                                       value="<?= $stockMatrix[$sz][$color]['qty'] ?? '' ?>"
                                       style="font-size:0.82rem;margin-bottom:2px;">
                                <div class="variant-sku-label" style="font-size:0.55rem;color:#aaa;text-align:center;font-family:monospace;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"
                                     title="<?= htmlspecialchars(($product['sku'] ?? '') . '-' . $color . '-' . $sz) ?>">
                                    <?= htmlspecialchars(($product['sku'] ?? '') . '-' . $color . '-' . $sz) ?>
                                </div>
                            </td>
                            <?php endforeach; ?>
                            <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this)"><i class="fas fa-times"></i></button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <datalist id="colorList"><?php foreach ($defaultColors as $c): ?><option value="<?= $c ?>"><?php endforeach; ?></datalist>
            <div class="d-flex align-items-center gap-3 mt-2">
                <small class="text-muted">* ตัวเลขในแต่ละช่อง = จำนวนในสต็อก &nbsp;·&nbsp; เว้นว่าง = ไม่มีไซต์นี้</small>
                <div id="variantCountBadge" style="font-size:0.75rem;background:var(--lavender);color:#9B72CF;border-radius:12px;padding:2px 10px;font-weight:600;"></div>
            </div>
        </div>
    </div>

    <!-- BUNDLE: Items Builder -->
    <div id="bundleSection" class="card <?= $currentType!=='bundle'?'d-none':'' ?>">
        <div class="card-header">
            <span class="card-title">🎁 สินค้าในเซต</span>
            <button type="button" class="btn btn-sm btn-primary" onclick="addBundleItem()">
                <i class="fas fa-plus me-1"></i> เพิ่มสินค้าในเซต
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0" style="font-size:0.85rem;">
                    <thead>
                        <tr>
                            <th style="min-width:200px;">สินค้า</th>
                            <th style="min-width:110px;">ไซต์</th>
                            <th style="min-width:130px;">สี</th>
                            <th style="width:80px;text-align:center;">จำนวน</th>
                            <th style="width:100px;text-align:right;">ราคา/ชิ้น</th>
                            <th style="width:90px;text-align:center;">สต็อก</th>
                            <th style="width:44px;"></th>
                        </tr>
                    </thead>
                    <tbody id="bundleItemsTbody">
                        <!-- JS will populate, or pre-fill from existing bundle -->
                    </tbody>
                </table>
            </div>
            <div id="bundleEmptyMsg" class="text-center py-4 text-muted <?= !empty($bundleItems)?'d-none':'' ?>">
                <div style="font-size:2rem;">🎁</div>
                <p class="mb-0" style="font-size:0.85rem;">คลิก "เพิ่มสินค้าในเซต" เพื่อเริ่มสร้างเซต</p>
            </div>

            <!-- Bundle summary footer -->
            <div id="bundleSummaryBar" class="px-4 py-3 border-top <?= empty($bundleItems)?'d-none':'' ?>"
                style="background:var(--lavender);border-radius:0 0 var(--radius) var(--radius);">
                <div class="row g-3 align-items-center">
                    <div class="col-auto">
                        <span style="font-size:0.8rem;color:var(--text-muted);">ราคาปกติรวม</span><br>
                        <span id="bundleRegularTotal" style="font-size:1.1rem;font-weight:600;text-decoration:line-through;color:var(--text-muted);">฿0</span>
                    </div>
                    <div class="col-auto"><i class="fas fa-arrow-right text-muted"></i></div>
                    <div class="col-auto">
                        <span style="font-size:0.8rem;color:var(--text-muted);">ราคาเซต (ที่ตั้งไว้)</span><br>
                        <span id="bundleSetPrice" style="font-size:1.1rem;font-weight:700;color:var(--pink-dark);">฿0</span>
                    </div>
                    <div class="col-auto">
                        <span style="font-size:0.8rem;color:var(--text-muted);">ลูกค้าประหยัด</span><br>
                        <span id="bundleSavings" style="font-size:1.1rem;font-weight:700;color:#3DD98F;">฿0</span>
                    </div>
                    <div class="col-auto ms-auto">
                        <span style="font-size:0.8rem;color:var(--text-muted);">สต็อกเซต (ที่ทำได้)</span><br>
                        <span id="bundleVirtualStock" style="font-size:1.1rem;font-weight:700;color:var(--lavender-dark);">— เซต</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div><!-- end left col -->

<!-- ── RIGHT COLUMN ─────────────────────────────────── -->
<div class="col-lg-4">
    <!-- Image -->
    <div class="card mb-4">
        <div class="card-header"><span class="card-title">🖼️ รูปภาพ</span></div>
        <div class="card-body">
            <div class="upload-zone mb-3" onclick="document.getElementById('mainImageInput').click()">
                <div class="upload-icon">📷</div>
                <p>คลิกหรือลากรูปภาพมาวาง</p>
                <p style="font-size:0.75rem;">JPG, PNG, WEBP (แนะนำ 800x800)</p>
            </div>
            <input type="file" id="mainImageInput" name="main_image" accept="image/*" class="d-none"
                onchange="previewImage(this,'mainImagePreview')">
            <img id="mainImagePreview"
                src="<?= $product['main_image'] ? SITE_URL.'/assets/uploads/products/'.htmlspecialchars($product['main_image']) : '' ?>"
                style="width:100%;border-radius:12px;display:<?= $product['main_image']?'block':'none' ?>;"
                class="mb-2">
        </div>
    </div>

    <!-- Settings -->
    <div class="card mb-4">
        <div class="card-header"><span class="card-title">⚙️ ตั้งค่าสินค้า</span></div>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label">สถานะ</label>
                <select name="status" class="form-select">
                    <option value="active"       <?= ($product['status']??'active')==='active'?'selected':'' ?>>✅ ขายอยู่</option>
                    <option value="out_of_stock" <?= ($product['status']??'')==='out_of_stock'?'selected':'' ?>>❌ หมดสต็อก</option>
                    <option value="inactive"     <?= ($product['status']??'')==='inactive'?'selected':'' ?>>🚫 ซ่อน</option>
                </select>
            </div>
            <div class="form-check">
                <input type="checkbox" name="is_featured" id="isFeatured" class="form-check-input" value="1"
                    <?= ($product['is_featured']??0)?'checked':'' ?>>
                <label class="form-check-label" for="isFeatured">⭐ สินค้าแนะนำ</label>
            </div>
        </div>
    </div>

    <!-- Profit / Savings preview -->
    <div class="card">
        <div class="card-header"><span class="card-title" id="profitCardTitle">💰 กำไรโดยประมาณ</span></div>
        <div class="card-body">
            <!-- Single mode -->
            <div id="singleProfitPanel">
                <div class="row g-2 text-center">
                    <div class="col-6">
                        <div style="font-size:0.78rem;color:var(--text-muted);">ราคาขาย</div>
                        <div id="previewSell" style="font-size:1.2rem;font-weight:700;color:var(--pink-dark);">฿0</div>
                    </div>
                    <div class="col-6">
                        <div style="font-size:0.78rem;color:var(--text-muted);">ต้นทุน</div>
                        <div id="previewCost" style="font-size:1.2rem;font-weight:700;color:var(--text-muted);">฿0</div>
                    </div>
                    <div class="col-12 border-top pt-2">
                        <div style="font-size:0.78rem;color:var(--text-muted);">กำไรต่อชิ้น</div>
                        <div id="previewProfit" style="font-size:1.5rem;font-weight:800;color:#3DD98F;">฿0</div>
                        <div id="previewMargin" style="font-size:0.78rem;color:var(--text-muted);">(0%)</div>
                    </div>
                </div>
            </div>
            <!-- Bundle mode -->
            <div id="bundleProfitPanel" class="<?= $currentType!=='bundle'?'d-none':'' ?>">
                <div class="row g-2 text-center">
                    <div class="col-6">
                        <div style="font-size:0.78rem;color:var(--text-muted);">ราคาปกติรวม</div>
                        <div id="bpRegular" style="font-size:1.1rem;font-weight:600;color:var(--text-muted);text-decoration:line-through;">฿0</div>
                    </div>
                    <div class="col-6">
                        <div style="font-size:0.78rem;color:var(--text-muted);">ราคาเซต</div>
                        <div id="bpSet" style="font-size:1.2rem;font-weight:700;color:var(--pink-dark);">฿0</div>
                    </div>
                    <div class="col-6 border-top pt-2">
                        <div style="font-size:0.78rem;color:var(--text-muted);">ต้นทุนรวม</div>
                        <div id="bpCost" style="font-size:1rem;font-weight:600;color:var(--text-muted);">฿0</div>
                    </div>
                    <div class="col-6 border-top pt-2">
                        <div style="font-size:0.78rem;color:var(--text-muted);">กำไรต่อเซต</div>
                        <div id="bpProfit" style="font-size:1.2rem;font-weight:800;color:#3DD98F;">฿0</div>
                    </div>
                    <div class="col-12 pt-1">
                        <div style="font-size:0.78rem;color:var(--text-muted);">ลูกค้าประหยัด</div>
                        <div id="bpSavings" style="font-size:1.1rem;font-weight:700;color:#9B72CF;">฿0 (0%)</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div><!-- end right col -->
</div><!-- end row -->

<!-- Submit -->
<div class="d-flex gap-3 mt-4 pb-4">
    <button type="submit" class="btn btn-primary btn-lg">
        <i class="fas fa-save me-1"></i> <?= $editId ? 'บันทึกการแก้ไข' : 'เพิ่มสินค้า' ?>
    </button>
    <a href="<?= SITE_URL ?>/pages/products.php" class="btn btn-outline-secondary btn-lg">ยกเลิก</a>
</div>
</form>
</div><!-- end container -->

<style>
.type-toggle-group { display:flex; gap:0; border:2px solid var(--border); border-radius:var(--radius-sm); overflow:hidden; }
.type-toggle-btn {
    padding:8px 20px; border:none; background:#fff; font-family:'Sarabun',sans-serif;
    font-size:0.9rem; cursor:pointer; transition:all .2s; color:var(--text-muted);
}
.type-toggle-btn:first-child { border-right:1px solid var(--border); }
.type-toggle-btn.active { background:var(--pink-light); color:var(--pink-dark); font-weight:600; }
.type-toggle-btn.bundle.active { background:var(--lavender); color:var(--lavender-dark); }
.type-toggle-btn:disabled { cursor:default; }
.bi-stock-ok   { color:#198754; font-weight:600; }
.bi-stock-low  { color:#856404; font-weight:600; }
.bi-stock-out  { color:#dc3545; font-weight:600; }
</style>

<?php
// Bundle items pre-fill data for JS
$bundlePrefill = json_encode($bundleItems, JSON_UNESCAPED_UNICODE);
$stockIndexJson = json_encode($stockIndex, JSON_UNESCAPED_UNICODE);
$editLocked = $editId ? 'true' : 'false';
?>
<script>
const SIZES = <?= json_encode($sizes) ?>;
const PRODUCTS_STOCK = <?= $stockIndexJson ?>;
const BUNDLE_PREFILL  = <?= $bundlePrefill ?>;
const EDIT_LOCKED     = <?= $editLocked ?>;
let bundleRowIdx = 0;

// ── TYPE SWITCH ──────────────────────────────────────────────────────────────
function switchType(type) {
    if (EDIT_LOCKED) return;
    document.getElementById('productTypeInput').value = type;

    const isSingle = type === 'single';
    document.getElementById('btnSingle').classList.toggle('active', isSingle);
    document.getElementById('btnBundle').classList.toggle('active', !isSingle);
    document.getElementById('singleStockSection').classList.toggle('d-none', !isSingle);
    document.getElementById('bundleSection').classList.toggle('d-none', isSingle);
    document.getElementById('bundleTypeBadge').classList.toggle('d-none', isSingle);
    document.getElementById('singleProfitPanel').classList.toggle('d-none', !isSingle);
    document.getElementById('bundleProfitPanel').classList.toggle('d-none', isSingle);
    document.getElementById('profitCardTitle').textContent = isSingle ? '💰 กำไรโดยประมาณ' : '💰 สรุปเซต';
    document.getElementById('costLabel').textContent = isSingle ? 'ต้นทุน (฿)' : 'ต้นทุนรวม (อัตโนมัติ)';
    document.getElementById('costPriceInput').readOnly = !isSingle;
    if (!isSingle) updateBundleSummary();
    else updateProfit();
}

// ── SINGLE: STOCK TABLE ──────────────────────────────────────────────────────
function addColorRow() {
    const tbody = document.getElementById('stockTbody');
    const existingColors = [...tbody.querySelectorAll('.color-input')].map(i => i.value).filter(Boolean);
    const defaultColors  = ['ขาว','ชมพู','ฟ้า','เหลือง','เขียว','ม่วง','แดง','ส้ม','เทา','ดำ'];
    const suggest = defaultColors.find(c => !existingColors.includes(c)) || '';

    // Use inline color picker row instead of prompt
    const color  = suggest;
    const baseSku = document.getElementById('skuInput')?.value || '';
    let html = `<tr class="stock-row">
        <td>
            <input type="text" class="form-control form-control-sm color-input" list="colorList"
                   placeholder="สี..." value="${color}"
                   oninput="updateStockNames(this);updateVariantSKUs()">
        </td>
        <td>
            <div class="color-sku-badge" style="font-size:0.72rem;font-weight:700;color:#9B72CF;font-family:monospace;background:var(--lavender);border-radius:6px;padding:4px 8px;white-space:nowrap;">
                ${baseSku ? baseSku + (color?'-'+color:'') : '—'}
            </div>
        </td>`;
    SIZES.forEach(sz => {
        const varSku = baseSku ? baseSku + '-' + color + '-' + sz : '';
        html += `<td style="padding:4px;">
            <input type="number" name="stock[${sz}][${color}][qty]"
                   class="form-control form-control-sm stock-qty text-center"
                   placeholder="—" min="0" style="font-size:0.82rem;margin-bottom:2px;">
            <div class="variant-sku-label" style="font-size:0.55rem;color:#aaa;text-align:center;font-family:monospace;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="${varSku}">${varSku}</div>
        </td>`;
    });
    html += `<td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this)"><i class="fas fa-times"></i></button></td></tr>`;
    tbody.insertAdjacentHTML('beforeend', html);
    // Focus the new color input
    const lastRow  = tbody.lastElementChild;
    const colorInp = lastRow.querySelector('.color-input');
    colorInp.focus();
    colorInp.select();
    updateVariantSKUs();
}

function removeRow(btn) {
    btn.closest('tr').remove();
    updateVariantSKUs();
}

function updateStockNames(input) {
    const newColor = input.value;
    const row = input.closest('tr');
    row.querySelectorAll('input[type=number]').forEach(inp => {
        inp.name = inp.name.replace(/\[([^\]]*)\]\[qty\]$/, `[${newColor}][qty]`);
    });
}

// ── SKU Generator ─────────────────────────────────────────────────────────────
async function genSKU(type) {
    const btn = document.querySelector('.dropdown-toggle[data-bs-toggle="dropdown"]');
    if (btn) btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    try {
        const r = await fetch(`?get_next_sku=1&type=${type}`);
        const d = await r.json();
        document.getElementById('skuInput').value = d.sku;
        updateVariantSKUs();
    } catch(e) {}
    if (btn) btn.innerHTML = '<i class="fas fa-magic"></i>';
}

// ── Color → English code map (mirrors PHP colorToEn) ─────────────────────────
function colorToEn(c) {
    const map = {
        'ขาว':'W','เงิน':'SV','เทา':'GY','ดำ':'BK',
        'แดง':'RD','ชมพู':'PK','ม่วง':'PU','ฟ้า':'BL',
        'น้ำเงิน':'NV','เขียว':'GN','เหลือง':'YL','ส้ม':'OR',
        'น้ำตาล':'BR','ทอง':'GD','ครีม':'CR','ลาย':'PT',
    };
    for (const [th, en] of Object.entries(map)) {
        if (c.includes(th)) return en;
    }
    const ascii = c.replace(/[^A-Za-z0-9]/g, '');
    return ascii ? ascii.slice(0,2).toUpperCase() : c.slice(0,2).toUpperCase() || 'XX';
}

// ── Variant SKU preview (live update) ────────────────────────────────────────
function updateVariantSKUs() {
    const baseSku = (document.getElementById('skuInput')?.value || '').trim();
    const rows    = document.querySelectorAll('#stockTbody .stock-row');
    let variantCount = 0;

    rows.forEach(row => {
        const color    = row.querySelector('.color-input')?.value || '';
        const colorEn  = colorToEn(color);
        const colorSku = baseSku ? (baseSku + (colorEn ? '-' + colorEn : '')) : (colorEn || color || '—');

        // Update color badge
        const badge = row.querySelector('.color-sku-badge');
        if (badge) badge.textContent = colorSku;

        // Update per-size variant labels
        const labels = row.querySelectorAll('.variant-sku-label');
        const sizeHeaders = [...document.querySelectorAll('#stockTable thead th')].slice(2, -1);
        labels.forEach((label, i) => {
            const sz = sizeHeaders[i]?.textContent?.trim() || '';
            const qty = row.querySelectorAll('.stock-qty')[i]?.value;
            const varSku = baseSku ? (colorSku + '-' + sz) : '';
            label.textContent = varSku;
            label.title = varSku;
            if (qty) variantCount++;
        });
    });

    // Update SKU preview
    const preview = document.getElementById('skuPreview');
    if (preview) {
        if (baseSku) {
            preview.innerHTML = `<i class="fas fa-tag" style="color:#9B72CF;"></i> <code style="color:#9B72CF;">${baseSku}</code> → รหัสตัวอย่าง: <code>${baseSku}-W-NB</code>`;
        } else {
            preview.textContent = 'ระบบจะสร้างรหัสให้อัตโนมัติเมื่อบันทึก';
        }
    }

    // Update variant count badge
    const badge = document.getElementById('variantCountBadge');
    if (badge) {
        badge.textContent = variantCount > 0 ? `✨ ${variantCount} variant codes` : '';
    }
}

// ── SINGLE: PROFIT PREVIEW ───────────────────────────────────────────────────
function updateProfit() {
    const sell   = parseFloat(document.getElementById('sellingPriceInput')?.value) || 0;
    const cost   = parseFloat(document.getElementById('costPriceInput')?.value) || 0;
    const profit = sell - cost;
    const margin = sell > 0 ? (profit / sell * 100).toFixed(1) : 0;
    document.getElementById('previewSell').textContent   = '฿' + sell.toLocaleString('th-TH');
    document.getElementById('previewCost').textContent   = '฿' + cost.toLocaleString('th-TH');
    document.getElementById('previewProfit').textContent = '฿' + profit.toLocaleString('th-TH');
    document.getElementById('previewMargin').textContent = `(${margin}%)`;
    document.getElementById('previewProfit').style.color = profit >= 0 ? '#3DD98F' : '#FF5757';
}
document.getElementById('sellingPriceInput')?.addEventListener('input', () => {
    const type = document.getElementById('productTypeInput').value;
    if (type === 'single') updateProfit(); else updateBundleSummary();
});
document.getElementById('costPriceInput')?.addEventListener('input', updateProfit);

// ── BUNDLE: ITEM ROWS ────────────────────────────────────────────────────────
function addBundleItem(prefill = null) {
    const idx = bundleRowIdx++;
    const tbody = document.getElementById('bundleItemsTbody');
    document.getElementById('bundleEmptyMsg')?.classList.add('d-none');
    document.getElementById('bundleSummaryBar')?.classList.remove('d-none');

    // Build product options
    const productOpts = Object.entries(PRODUCTS_STOCK).map(([id, p]) =>
        `<option value="${id}" ${prefill?.product_id == id ? 'selected' : ''}>${p.name} (${p.sku})</option>`
    ).join('');

    const tr = document.createElement('tr');
    tr.className = 'bundle-item-row';
    tr.dataset.idx = idx;
    tr.innerHTML = `
        <td>
            <select name="bundle_items[${idx}][product_id]" class="form-select form-select-sm bi-product" onchange="onBiProductChange(this)">
                <option value="">-- เลือกสินค้า --</option>${productOpts}
            </select>
        </td>
        <td>
            <select name="bundle_items[${idx}][size]" class="form-select form-select-sm bi-size" onchange="onBiSizeChange(this)">
                <option value="">-- ไซต์ --</option>
            </select>
        </td>
        <td>
            <select name="bundle_items[${idx}][color]" class="form-select form-select-sm bi-color" onchange="updateBundleSummary()">
                <option value="">-- สี --</option>
            </select>
        </td>
        <td>
            <input type="number" name="bundle_items[${idx}][quantity]"
                class="form-control form-control-sm bi-qty text-center"
                value="${prefill?.quantity || 1}" min="1"
                onchange="updateBundleSummary()">
        </td>
        <td class="bi-price-cell text-end" style="color:var(--text-muted);">—</td>
        <td class="bi-stock-cell text-center">—</td>
        <td>
            <button type="button" class="btn btn-sm btn-outline-danger"
                onclick="this.closest('tr').remove(); checkBundleEmpty(); updateBundleSummary();">
                <i class="fas fa-times"></i>
            </button>
        </td>`;
    tbody.appendChild(tr);

    if (prefill?.product_id) {
        const pSel = tr.querySelector('.bi-product');
        populateBiSizes(pSel);
        setTimeout(() => {
            const sSel = tr.querySelector('.bi-size');
            sSel.value = prefill.size || '';
            populateBiColors(sSel);
            setTimeout(() => {
                tr.querySelector('.bi-color').value = prefill.color || '';
                updateBundleSummary();
            }, 0);
        }, 0);
    }
}

function onBiProductChange(sel) { populateBiSizes(sel); updateBundleSummary(); }
function onBiSizeChange(sel)    { populateBiColors(sel); updateBundleSummary(); }

function populateBiSizes(productSel) {
    const row     = productSel.closest('tr');
    const pid     = productSel.value;
    const sizeSel = row.querySelector('.bi-size');
    const colSel  = row.querySelector('.bi-color');
    sizeSel.innerHTML = '<option value="">-- ไซต์ --</option>';
    colSel.innerHTML  = '<option value="">-- สี --</option>';
    row.querySelector('.bi-price-cell').textContent = '—';
    row.querySelector('.bi-stock-cell').innerHTML   = '<span style="color:var(--text-muted);">—</span>';
    if (!pid || !PRODUCTS_STOCK[pid]) return;
    Object.keys(PRODUCTS_STOCK[pid].sizes).forEach(sz => {
        sizeSel.innerHTML += `<option value="${sz}">${sz}</option>`;
    });
    row.querySelector('.bi-price-cell').textContent = '฿' + PRODUCTS_STOCK[pid].price.toLocaleString('th-TH');
}

function populateBiColors(sizeSel) {
    const row    = sizeSel.closest('tr');
    const pid    = row.querySelector('.bi-product').value;
    const size   = sizeSel.value;
    const colSel = row.querySelector('.bi-color');
    colSel.innerHTML = '<option value="">-- สี --</option>';
    row.querySelector('.bi-stock-cell').innerHTML = '<span style="color:var(--text-muted);">—</span>';
    if (!pid || !size || !PRODUCTS_STOCK[pid]) return;
    const colors = PRODUCTS_STOCK[pid].sizes[size] || [];
    colors.forEach(c => {
        colSel.innerHTML += `<option value="${c.color}">${c.color} (${c.qty})</option>`;
    });
    if (colors.length === 1) { colSel.value = colors[0].color; refreshStockCell(row, pid, size, colors[0].color); }
}

function refreshStockCell(row, pid, size, color) {
    const p      = PRODUCTS_STOCK[pid];
    if (!p) return;
    const colors = (p.sizes[size] || []);
    const match  = colors.find(c => c.color === color);
    const qty    = match ? match.qty : 0;
    const cls    = qty === 0 ? 'bi-stock-out' : qty <= 5 ? 'bi-stock-low' : 'bi-stock-ok';
    row.querySelector('.bi-stock-cell').innerHTML = `<span class="${cls}">${qty}</span>`;
}

function updateBundleSummary() {
    const rows = document.querySelectorAll('.bundle-item-row');
    let totalRegular = 0, totalCost = 0, minSets = Infinity;
    let valid = 0;

    rows.forEach(row => {
        const pid   = row.querySelector('.bi-product').value;
        const size  = row.querySelector('.bi-size').value;
        const color = row.querySelector('.bi-color').value;
        const qty   = parseInt(row.querySelector('.bi-qty').value) || 1;
        if (!pid || !size || !color) return;
        const p      = PRODUCTS_STOCK[pid];
        if (!p) return;
        const colors = (p.sizes[size] || []);
        const match  = colors.find(c => c.color === color);
        const stock  = match ? match.qty : 0;
        valid++;
        totalRegular += p.price * qty;
        totalCost    += p.cost  * qty;
        if (qty > 0) minSets = Math.min(minSets, Math.floor(stock / qty));
        refreshStockCell(row, pid, size, color);
    });

    const setPrice = parseFloat(document.getElementById('sellingPriceInput').value) || 0;
    const savings  = totalRegular - setPrice;
    const profit   = setPrice - totalCost;
    const savingsPct = totalRegular > 0 ? (savings / totalRegular * 100).toFixed(0) : 0;

    document.getElementById('bundleRegularTotal').textContent = '฿' + totalRegular.toLocaleString('th-TH');
    document.getElementById('bundleSetPrice').textContent     = '฿' + setPrice.toLocaleString('th-TH');
    document.getElementById('bundleSavings').textContent      = (savings >= 0 ? '+฿' : '-฿') + Math.abs(savings).toLocaleString('th-TH');
    document.getElementById('bundleVirtualStock').textContent = (valid === 0 || minSets === Infinity ? '—' : minSets) + ' เซต';

    document.getElementById('bpRegular').textContent  = '฿' + totalRegular.toLocaleString('th-TH');
    document.getElementById('bpSet').textContent      = '฿' + setPrice.toLocaleString('th-TH');
    document.getElementById('bpCost').textContent     = '฿' + totalCost.toLocaleString('th-TH');
    document.getElementById('bpProfit').textContent   = '฿' + profit.toLocaleString('th-TH');
    document.getElementById('bpSavings').textContent  = `฿${Math.abs(savings).toLocaleString('th-TH')} (${savingsPct}%)`;
    document.getElementById('bpProfit').style.color   = profit >= 0 ? '#3DD98F' : '#FF5757';

    // Auto-fill cost_price
    document.getElementById('costPriceInput').value = totalCost;
}

function checkBundleEmpty() {
    const rows = document.querySelectorAll('.bundle-item-row');
    if (rows.length === 0) {
        document.getElementById('bundleEmptyMsg')?.classList.remove('d-none');
        document.getElementById('bundleSummaryBar')?.classList.add('d-none');
    }
}

// Image preview
function previewImage(input, previewId) {
    const file = input.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
        const img = document.getElementById(previewId);
        img.src = e.target.result;
        img.style.display = 'block';
    };
    reader.readAsDataURL(file);
}

// ── INIT ─────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    if (EDIT_LOCKED) {
        document.getElementById('btnSingle').disabled = true;
        document.getElementById('btnBundle').disabled = true;
    }

    // Pre-fill bundle items when editing
    if (BUNDLE_PREFILL && BUNDLE_PREFILL.length > 0) {
        BUNDLE_PREFILL.forEach(item => addBundleItem(item));
    }

    const currentType = document.getElementById('productTypeInput').value;
    if (currentType === 'bundle') updateBundleSummary();
    else { updateProfit(); updateVariantSKUs(); }
});
updateProfit();
updateVariantSKUs();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
