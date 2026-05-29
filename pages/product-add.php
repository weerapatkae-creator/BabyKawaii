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
                ORDER BY FIELD(size,'Premature','NB','0-3M','3-6M','6-9M','9-12M','Free Size')");
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
$sizes        = ['Premature','NB','0-3M','3-6M','6-9M','9-12M','Free Size'];
$defaultColors= ['ขาว','ชมพู','ฟ้า','เหลือง','เขียว','ม่วง','แดง','ส้ม','เทา','ดำ','ลายทาง','ลายดอก','ลายการ์ตูน'];

$stockMatrix = [];
foreach ($stockData as $s) {
    $stockMatrix[$s['size']][$s['color']] = ['qty'=>$s['quantity'],'min'=>$s['min_alert']];
}

// All single products with stock for bundle builder
$rawStock = $pdo->query("
    SELECT p.id, p.name, p.sku, p.selling_price, p.cost_price, p.main_image,
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
        $stockIndex[$pid] = ['name'=>$r['name'],'sku'=>$r['sku'],'price'=>(float)$r['selling_price'],'cost'=>(float)$r['cost_price'],'image'=>$r['main_image']??'','sizes'=>[]];
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
            <span class="card-title">📦 สต็อกสินค้า</span>
            <button type="button" class="btn btn-sm btn-outline-pink" onclick="addColorRow()">
                <i class="fas fa-plus"></i> เพิ่มสี
            </button>
        </div>
        <div class="card-body" style="padding:12px;">
            <div class="table-responsive">
                <table id="stockTable" style="font-size:0.83rem;width:100%;border-collapse:separate;border-spacing:0;">
                    <thead>
                        <tr>
                            <th style="background:#faf7f9;padding:8px 10px;border-bottom:2px solid #efe9ee;white-space:nowrap;min-width:110px;border-radius:8px 0 0 0;">สี / ลาย</th>
                            <?php foreach ($sizes as $sz): ?>
                            <th style="background:#faf7f9;padding:8px 6px;border-bottom:2px solid #efe9ee;text-align:center;min-width:66px;font-weight:700;color:#c05a78;"><?= $sz ?></th>
                            <?php endforeach; ?>
                            <th style="background:#faf7f9;border-bottom:2px solid #efe9ee;width:36px;border-radius:0 8px 0 0;"></th>
                        </tr>
                    </thead>
                    <tbody id="stockTbody">
                        <?php
                        $existingColors = $editId ? array_unique(array_column($stockData, 'color')) : ['ขาว','ชมพู'];
                        if (empty($existingColors)) $existingColors = ['ขาว'];
                        foreach ($existingColors as $color): ?>
                        <tr class="stock-row" style="border-bottom:1px solid #f5f0f5;">
                            <td data-label="สี" style="padding:6px 8px;">
                                <input type="text" class="form-control form-control-sm color-input"
                                       list="colorList" placeholder="สี..."
                                       value="<?= htmlspecialchars($color) ?>"
                                       oninput="updateStockNames(this);updateVariantSKUs()"
                                       style="border-radius:7px;font-size:0.82rem;">
                            </td>
                            <?php foreach ($sizes as $sz): ?>
                            <td data-label="<?= $sz ?>" style="padding:5px 4px;text-align:center;">
                                <input type="number" name="stock[<?= $sz ?>][<?= htmlspecialchars($color) ?>][qty]"
                                       class="form-control form-control-sm stock-qty text-center"
                                       placeholder="—" min="0"
                                       value="<?= $stockMatrix[$sz][$color]['qty'] ?? '' ?>"
                                       style="font-size:0.85rem;border-radius:7px;padding:5px 4px;">
                            </td>
                            <?php endforeach; ?>
                            <td style="padding:5px 4px;text-align:center;">
                                <button type="button" style="background:none;border:none;color:#ddd;cursor:pointer;font-size:0.85rem;padding:4px;" onclick="removeRow(this)">
                                    <i class="fas fa-times"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <datalist id="colorList"><?php foreach ($defaultColors as $c): ?><option value="<?= $c ?>"><?php endforeach; ?></datalist>
            <div class="d-flex align-items-center gap-2 mt-3">
                <small class="text-muted" style="font-size:0.76rem;">ตัวเลข = จำนวนสต็อก · เว้นว่าง = ไม่มีไซต์นี้</small>
                <div id="variantCountBadge" style="font-size:0.73rem;background:var(--lavender);color:#9B72CF;border-radius:10px;padding:2px 10px;font-weight:600;"></div>
            </div>
        </div>
    </div>

    <!-- BUNDLE: Items Builder -->
    <div id="bundleSection" class="card <?= $currentType!=='bundle'?'d-none':'' ?>">
        <div class="card-header">
            <span class="card-title">🎁 สินค้าในเซต</span>
            <span style="font-size:.72rem;color:#aaa;">ลากหรือคลิกสินค้าเพื่อเพิ่ม</span>
        </div>
        <div class="card-body p-0">
            <div class="bundle-builder-wrap">

                <!-- ── LEFT: product picker ── -->
                <div class="bpicker-pane">
                    <div class="bpicker-search">
                        <input type="text" id="bpickerQ" class="form-control form-control-sm"
                               placeholder="🔍 ค้นหาสินค้า..." oninput="filterBundlePicker(this.value)">
                    </div>
                    <div id="bpickerList" class="bpicker-list"></div>
                </div>

                <!-- ── RIGHT: drop zone ── -->
                <div class="bdrop-pane">
                    <div id="bundleDropZone" class="bdrop-zone"
                         ondragover="event.preventDefault();this.classList.add('drag-over')"
                         ondragleave="this.classList.remove('drag-over')"
                         ondrop="onBundleDrop(event)">
                        <div id="bundleEmptyMsg" class="bdrop-empty <?= !empty($bundleItems)?'d-none':'' ?>">
                            <div style="font-size:2.2rem;">🎁</div>
                            <div style="font-size:.8rem;color:#bbb;margin-top:6px;">ลากหรือกดสินค้าจากซ้าย<br>เพื่อเพิ่มในเซต</div>
                        </div>
                        <div id="bundleItemsList"></div>
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
                                <span style="font-size:0.8rem;color:var(--text-muted);">ราคาเซต</span><br>
                                <span id="bundleSetPrice" style="font-size:1.1rem;font-weight:700;color:var(--pink-dark);">฿0</span>
                            </div>
                            <div class="col-auto">
                                <span style="font-size:0.8rem;color:var(--text-muted);">ประหยัด</span><br>
                                <span id="bundleSavings" style="font-size:1.1rem;font-weight:700;color:#3DD98F;">฿0</span>
                            </div>
                            <div class="col-auto ms-auto">
                                <span style="font-size:0.8rem;color:var(--text-muted);">สต็อกเซต</span><br>
                                <span id="bundleVirtualStock" style="font-size:1.1rem;font-weight:700;color:var(--lavender-dark);">— เซต</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div><!-- .bundle-builder-wrap -->
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

/* ── Bundle drag-drop builder ──────────────────────────────── */
.bundle-builder-wrap {
    display: flex;
    min-height: 320px;
}
.bpicker-pane {
    width: 46%;
    border-right: 1.5px solid #ede5f5;
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
}
.bpicker-search { padding: 10px 12px; border-bottom: 1px solid #f3ecfb; }
.bpicker-list   { flex: 1; overflow-y: auto; padding: 8px 10px; max-height: 380px; display: flex; flex-direction: column; gap: 8px; }

/* product card in picker */
.bpicker-product { border: 1.5px solid #ede5f5; border-radius: 10px; overflow: hidden; background:#fafafa; }
.bpicker-product-header { padding: 8px 10px; font-size: .8rem; font-weight: 700; color: #444; background: #f5f0fb; display:flex; align-items:center; gap:8px; }
.bpicker-chips { display: flex; flex-wrap: wrap; gap: 5px; padding: 8px 10px; }
.bchip {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 4px 10px; border-radius: 20px; font-size: .72rem; font-weight: 600;
    cursor: grab; user-select: none;
    border: 1.5px solid #ddd; background: #fff;
    transition: transform .12s, box-shadow .12s;
}
.bchip:hover  { transform: translateY(-1px); box-shadow: 0 3px 8px rgba(0,0,0,.12); }
.bchip:active { cursor: grabbing; transform: scale(.96); }
.bchip.out    { background:#fef2f2; border-color:#fca5a5; color:#dc2626; }
.bchip.low    { background:#fffbeb; border-color:#fde68a; color:#b45309; }
.bchip.ok     { background:#f0fdf4; border-color:#bbf7d0; color:#15803d; }

/* drop zone */
.bdrop-pane   { flex: 1; display: flex; flex-direction: column; }
.bdrop-zone   { flex: 1; padding: 10px; min-height: 200px; transition: background .15s; }
.bdrop-zone.drag-over { background: #f5f0ff; outline: 2px dashed #9b72cf; outline-offset:-4px; border-radius:8px; }
.bdrop-empty  { text-align: center; padding: 40px 16px; pointer-events: none; }

/* bundle item card (right side) */
.bi-card {
    display: flex; align-items: center; gap: 8px;
    background: #fff; border: 1.5px solid #e5d8f7; border-radius: 10px;
    padding: 8px 10px; margin-bottom: 6px;
    box-shadow: 0 1px 4px rgba(155,114,207,.1);
}
.bi-card-info { flex: 1; min-width: 0; }
.bi-card-name { font-size: .8rem; font-weight: 700; color: #333; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.bi-card-meta { font-size: .68rem; color: #888; margin-top: 1px; }
.bi-qty-wrap  { display: flex; align-items: center; gap: 4px; }
.bi-qty-wrap button {
    width:24px; height:24px; border-radius:50%; border:1.5px solid #ddd; background:#fff;
    font-size:.85rem; line-height:1; cursor:pointer; display:flex;align-items:center;justify-content:center;
    color:#666; padding:0;
}
.bi-qty-wrap button:hover { border-color:#9b72cf; color:#9b72cf; }
.bi-qty-inp  { width:36px; text-align:center; border:1.5px solid #ddd; border-radius:6px; font-size:.82rem; padding:2px 0; }
.bi-remove   { background:none; border:none; color:#ddd; cursor:pointer; font-size:.85rem; padding:2px 4px; line-height:1; }
.bi-remove:hover { color:#dc2626; }

@media (max-width: 640px) {
    .bundle-builder-wrap { flex-direction: column; }
    .bpicker-pane { width: 100%; border-right: none; border-bottom: 1.5px solid #ede5f5; }
    .bpicker-list { max-height: 220px; }
    .bdrop-zone   { min-height: 140px; }
}

/* ── Mobile stock table → card layout ─────────────────────── */
@media (max-width: 640px) {
    #stockTable thead { display: none; }
    #stockTable, #stockTable tbody { display: block; }
    #stockTable tbody { display: flex; flex-direction: column; gap: 10px; }

    .stock-row {
        display: grid !important;
        grid-template-columns: repeat(3, 1fr);
        gap: 6px;
        background: #fff;
        border: 1.5px solid #efe9ee !important;
        border-radius: 12px !important;
        padding: 10px !important;
        box-shadow: 0 1px 3px rgba(44,42,48,.06);
    }
    /* Color input spans full width */
    .stock-row td:first-child {
        grid-column: 1 / -1;
        padding: 0 0 4px 0 !important;
    }
    .stock-row td:first-child input {
        font-weight: 700;
        font-size: 0.88rem !important;
        background: #faf0f4;
    }
    /* Delete button — bottom right */
    .stock-row td:last-child {
        grid-column: 1 / -1;
        text-align: right;
        padding: 4px 0 0 0 !important;
    }
    /* Each size cell — show label above input */
    .stock-row td[data-label]:not(:first-child):not(:last-child) {
        display: flex !important;
        flex-direction: column;
        align-items: center;
        padding: 0 !important;
        gap: 3px;
    }
    .stock-row td[data-label]:not(:first-child):not(:last-child)::before {
        content: attr(data-label);
        font-size: 0.6rem;
        font-weight: 700;
        color: #c05a78;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .stock-row td[data-label]:not(:first-child):not(:last-child) input {
        width: 100%;
        text-align: center;
        padding: 7px 4px !important;
        font-size: 0.9rem !important;
        border-radius: 8px !important;
    }
}
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
    let html = `<tr class="stock-row" style="border-bottom:1px solid #f5f0f5;">
        <td data-label="สี" style="padding:6px 8px;">
            <input type="text" class="form-control form-control-sm color-input" list="colorList"
                   placeholder="สี..." value="${color}"
                   oninput="updateStockNames(this);updateVariantSKUs()"
                   style="border-radius:7px;font-size:0.82rem;">
        </td>`;
    SIZES.forEach(sz => {
        html += `<td data-label="${sz}" style="padding:5px 4px;text-align:center;">
            <input type="number" name="stock[${sz}][${color}][qty]"
                   class="form-control form-control-sm stock-qty text-center"
                   placeholder="—" min="0" style="font-size:0.85rem;border-radius:7px;padding:5px 4px;">
        </td>`;
    });
    html += `<td style="padding:5px 4px;text-align:center;">
        <button type="button" style="background:none;border:none;color:#ddd;cursor:pointer;font-size:0.85rem;padding:4px;" onclick="removeRow(this)">
            <i class="fas fa-times"></i>
        </button>
    </td></tr>`;
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
// ── BUNDLE: DRAG-DROP PICKER ─────────────────────────────────────────────────

function renderBundlePicker(filter = '') {
    const list = document.getElementById('bpickerList');
    if (!list) return;
    const q = filter.toLowerCase();
    list.innerHTML = '';
    Object.entries(PRODUCTS_STOCK).forEach(([pid, p]) => {
        if (q && !p.name.toLowerCase().includes(q) && !(p.sku||'').toLowerCase().includes(q)) return;
        const chipHtml = Object.entries(p.sizes).map(([size, colors]) =>
            colors.map(c => {
                const cls = c.qty === 0 ? 'out' : c.qty <= 3 ? 'low' : 'ok';
                const data = JSON.stringify({pid, size, color: c.color, qty: c.qty});
                return `<span class="bchip ${cls}"
                    draggable="true"
                    onclick="addBundleItemByData(${escAttrJson(data)})"
                    ondragstart="onChipDragStart(event,${escAttrJson(data)})"
                    title="คลิกหรือลากเพื่อเพิ่ม">
                    ${size} · ${c.color} <span style="opacity:.7;font-size:.65rem;">(${c.qty})</span>
                </span>`;
            }).join('')
        ).join('');
        if (!chipHtml) return;
        const imgHtml = p.image
            ? `<img src="<?= UPLOAD_URL ?>products/${p.image}" style="width:36px;height:36px;object-fit:cover;border-radius:6px;flex-shrink:0;border:1px solid #ede5f5;">`
            : `<div style="width:36px;height:36px;border-radius:6px;background:#f5f0fb;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:1.1rem;">👶</div>`;
        list.insertAdjacentHTML('beforeend', `
            <div class="bpicker-product">
                <div class="bpicker-product-header">
                    ${imgHtml}
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${p.name}</div>
                        ${p.sku ? `<div style="font-size:.62rem;color:#aaa;">${p.sku}</div>` : ''}
                    </div>
                </div>
                <div class="bpicker-chips">${chipHtml}</div>
            </div>`);
    });
    if (!list.children.length) {
        list.innerHTML = '<div style="text-align:center;color:#ccc;padding:24px;font-size:.8rem;">ไม่พบสินค้า</div>';
    }
}

function escAttrJson(obj) {
    return JSON.stringify(obj).replace(/'/g, '&#39;').replace(/"/g, '&quot;');
}

function filterBundlePicker(q) { renderBundlePicker(q); }

let _dragData = null;
function onChipDragStart(event, data) {
    _dragData = data;
    event.dataTransfer.effectAllowed = 'copy';
}

function onBundleDrop(event) {
    event.preventDefault();
    document.getElementById('bundleDropZone')?.classList.remove('drag-over');
    if (_dragData) { addBundleItemByData(_dragData); _dragData = null; }
}

function addBundleItemByData(data, qty = 1) {
    const { pid, size, color } = typeof data === 'string' ? JSON.parse(data) : data;
    const p = PRODUCTS_STOCK[pid];
    if (!p) return;
    const idx = bundleRowIdx++;
    const colors = (p.sizes[size] || []);
    const match  = colors.find(c => c.color === color);
    const stock  = match ? match.qty : 0;
    const cls    = stock === 0 ? 'bi-stock-out' : stock <= 3 ? 'bi-stock-low' : 'bi-stock-ok';

    document.getElementById('bundleEmptyMsg')?.classList.add('d-none');
    document.getElementById('bundleSummaryBar')?.classList.remove('d-none');

    const card = document.createElement('div');
    card.className = 'bi-card';
    card.dataset.idx = idx;
    card.innerHTML = `
        <input type="hidden" name="bundle_items[${idx}][product_id]" value="${pid}">
        <input type="hidden" name="bundle_items[${idx}][size]"       value="${size}">
        <input type="hidden" name="bundle_items[${idx}][color]"      value="${color}">
        <div class="bi-card-info">
            <div class="bi-card-name">${p.name}</div>
            <div class="bi-card-meta">${size} · ${color} · สต็อก: <span class="${cls}">${stock}</span></div>
        </div>
        <div class="bi-qty-wrap">
            <button type="button" onclick="adjustBiQty(this,-1)">−</button>
            <input type="number" class="bi-qty-inp" name="bundle_items[${idx}][quantity]"
                   value="${qty}" min="1" oninput="updateBundleSummary()">
            <button type="button" onclick="adjustBiQty(this,1)">+</button>
        </div>
        <button type="button" class="bi-remove" onclick="removeBundleItem(this)" title="ลบ">
            <i class="fas fa-times"></i>
        </button>`;
    document.getElementById('bundleItemsList').appendChild(card);
    updateBundleSummary();
}

function adjustBiQty(btn, delta) {
    const inp = btn.parentElement.querySelector('.bi-qty-inp');
    inp.value = Math.max(1, (parseInt(inp.value) || 1) + delta);
    updateBundleSummary();
}

function removeBundleItem(btn) {
    btn.closest('.bi-card').remove();
    const list = document.getElementById('bundleItemsList');
    if (!list.children.length) {
        document.getElementById('bundleEmptyMsg')?.classList.remove('d-none');
        document.getElementById('bundleSummaryBar')?.classList.add('d-none');
    }
    updateBundleSummary();
}

function updateBundleSummary() {
    const cards = document.querySelectorAll('.bi-card');
    let totalRegular = 0, totalCost = 0, minSets = Infinity, valid = 0;

    cards.forEach(card => {
        const pid   = card.querySelector('[name$="[product_id]"]').value;
        const size  = card.querySelector('[name$="[size]"]').value;
        const color = card.querySelector('[name$="[color]"]').value;
        const qty   = parseInt(card.querySelector('.bi-qty-inp').value) || 1;
        const p = PRODUCTS_STOCK[pid]; if (!p) return;
        const match = (p.sizes[size] || []).find(c => c.color === color);
        const stock = match ? match.qty : 0;
        valid++;
        totalRegular += p.price * qty;
        totalCost    += p.cost  * qty;
        if (qty > 0) minSets = Math.min(minSets, Math.floor(stock / qty));
    });

    const setPrice   = parseFloat(document.getElementById('sellingPriceInput').value) || 0;
    const savings    = totalRegular - setPrice;
    const profit     = setPrice - totalCost;
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
    document.getElementById('costPriceInput').value   = totalCost;
}

function checkBundleEmpty() {
    if (!document.querySelectorAll('.bi-card').length) {
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

    // Render product picker
    renderBundlePicker();

    // Pre-fill bundle items when editing
    if (BUNDLE_PREFILL && BUNDLE_PREFILL.length > 0) {
        BUNDLE_PREFILL.forEach(item => addBundleItemByData(
            { pid: item.product_id, size: item.size, color: item.color },
            parseInt(item.quantity) || 1
        ));
    }

    const currentType = document.getElementById('productTypeInput').value;
    if (currentType === 'bundle') updateBundleSummary();
    else { updateProfit(); updateVariantSKUs(); }
});
updateProfit();
updateVariantSKUs();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
