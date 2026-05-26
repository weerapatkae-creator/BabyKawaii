<?php
$pageTitle = 'Import CSV';
require_once __DIR__ . '/../config/database.php';
requireAdmin();
$pdo = getDB();

// ── Template download ─────────────────────────────────────────────────────────
if (isset($_GET['template'])) {
    $bom = "\xEF\xBB\xBF";
    if ($_GET['template'] === 'products') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="template_products.csv"');
        echo $bom;
        echo "sku,name,category,cost_price,selling_price,tags,status\r\n";
        echo "BK001,ชุดนอนน้องหมี,ชุดนอน,150,299,น่ารัก,active\r\n";
        echo "BK002,เสื้อกระต่ายน้อย,เสื้อแขนสั้น,200,450,น่ารัก,active\r\n";
        exit;
    }
    if ($_GET['template'] === 'stock') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="template_stock.csv"');
        echo $bom;
        echo "sku,size,color,quantity,min_alert\r\n";
        echo "BK001,NB,ขาว,10,5\r\n";
        echo "BK001,0-3M,ชมพู,15,5\r\n";
        echo "BK001,3-6M,ฟ้า,8,3\r\n";
        echo "BK002,Free Size,เหลือง,20,5\r\n";
        exit;
    }
}

// ── Valid ENUM values for stock.size ─────────────────────────────────────────
$validSizes = ['Premature','NB','0-3M','3-6M','6-9M','9-12M','12-18M','18-24M','Free Size'];

// ── Load categories for matching ─────────────────────────────────────────────
$categories = $pdo->query("SELECT id, name FROM categories WHERE is_active=1")->fetchAll(PDO::FETCH_KEY_PAIR);
// key = name, value = id
$catByName = array_flip($categories);  // name→id

// ── Process import ────────────────────────────────────────────────────────────
$importResults = [];
$importType    = '';
$importDone    = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $importType = $_POST['import_type'] ?? 'products';
    $file = $_FILES['csv_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $importResults[] = ['row'=>0,'status'=>'error','msg'=>'อัปโหลดไฟล์ไม่สำเร็จ (error code: '.$file['error'].')'];
    } else {
        $handle = fopen($file['tmp_name'], 'r');
        // Strip UTF-8 BOM if present
        $firstLine = fgets($handle);
        $firstLine = str_replace("\xEF\xBB\xBF", '', $firstLine);
        rewind($handle);
        fgets($handle); // skip header line (already read above, just advance)

        $rowNum = 1; // header = row 1

        if ($importType === 'products') {
            // Expected columns: sku, name, category, cost_price, selling_price, tags, status
            // Re-parse header
            rewind($handle);
            $headerRaw = fgets($handle);
            $headerRaw = str_replace("\xEF\xBB\xBF", '', trim($headerRaw));
            $headers   = str_getcsv($headerRaw);
            $headers   = array_map('strtolower', array_map('trim', $headers));
            $required  = ['name','selling_price'];

            $inserted = 0; $updated = 0; $errors = 0;

            while (($row = fgetcsv($handle)) !== false) {
                $rowNum++;
                if (count($row) < 2) continue;
                $data = array_combine(
                    array_slice($headers, 0, count($row)),
                    array_slice($row,    0, count($headers))
                );

                // Validate
                if (empty($data['name'])) {
                    $importResults[] = ['row'=>$rowNum,'status'=>'error','msg'=>'ไม่มีชื่อสินค้า'];
                    $errors++; continue;
                }
                $sellingPrice = (float)($data['selling_price'] ?? 0);
                if ($sellingPrice <= 0) {
                    $importResults[] = ['row'=>$rowNum,'status'=>'error','msg'=>"ราคาขายต้องมากกว่า 0 (ได้: {$data['selling_price']})"];
                    $errors++; continue;
                }

                $sku       = trim($data['sku'] ?? '');
                $catName   = trim($data['category'] ?? '');
                $catId     = $catByName[$catName] ?? null;
                $costPrice = (float)($data['cost_price'] ?? 0);
                $tags      = trim($data['tags'] ?? '');
                $status    = in_array($data['status'] ?? 'active', ['active','inactive','out_of_stock'])
                             ? ($data['status'] ?? 'active') : 'active';

                try {
                    if ($sku) {
                        // Check if exists
                        $exists = $pdo->prepare("SELECT id FROM products WHERE sku=?");
                        $exists->execute([$sku]);
                        $existId = $exists->fetchColumn();

                        if ($existId) {
                            $pdo->prepare("UPDATE products SET name=?,category_id=?,cost_price=?,selling_price=?,tags=?,status=?,updated_at=NOW() WHERE sku=?")
                                ->execute([$data['name'], $catId, $costPrice, $sellingPrice, $tags, $status, $sku]);
                            $importResults[] = ['row'=>$rowNum,'status'=>'updated','msg'=>"อัปเดต: {$data['name']}"];
                            $updated++;
                        } else {
                            $pdo->prepare("INSERT INTO products (sku,name,category_id,cost_price,selling_price,tags,status) VALUES (?,?,?,?,?,?,?)")
                                ->execute([$sku, $data['name'], $catId, $costPrice, $sellingPrice, $tags, $status]);
                            $importResults[] = ['row'=>$rowNum,'status'=>'inserted','msg'=>"เพิ่มใหม่: {$data['name']}"];
                            $inserted++;
                        }
                    } else {
                        // No SKU - always insert
                        $pdo->prepare("INSERT INTO products (name,category_id,cost_price,selling_price,tags,status) VALUES (?,?,?,?,?,?)")
                            ->execute([$data['name'], $catId, $costPrice, $sellingPrice, $tags, $status]);
                        $importResults[] = ['row'=>$rowNum,'status'=>'inserted','msg'=>"เพิ่มใหม่: {$data['name']} (ไม่มี SKU)"];
                        $inserted++;
                    }
                } catch (Exception $e) {
                    $importResults[] = ['row'=>$rowNum,'status'=>'error','msg'=>'DB Error: '.$e->getMessage()];
                    $errors++;
                }
            }

            array_unshift($importResults, [
                'row'    => 0,
                'status' => $errors > 0 ? 'warn' : 'ok',
                'msg'    => "สรุป: เพิ่มใหม่ $inserted | อัปเดต $updated | ข้อผิดพลาด $errors",
            ]);

        } elseif ($importType === 'stock') {
            // Expected columns: sku, size, color, quantity, min_alert
            rewind($handle);
            $headerRaw = fgets($handle);
            $headerRaw = str_replace("\xEF\xBB\xBF", '', trim($headerRaw));
            $headers   = str_getcsv($headerRaw);
            $headers   = array_map('strtolower', array_map('trim', $headers));

            $inserted = 0; $updated = 0; $errors = 0;

            // Pre-load product sku→id map
            $skuMap = $pdo->query("SELECT sku, id FROM products WHERE status!='inactive'")->fetchAll(PDO::FETCH_KEY_PAIR);

            while (($row = fgetcsv($handle)) !== false) {
                $rowNum++;
                if (count($row) < 3) continue;
                $data = array_combine(
                    array_slice($headers, 0, count($row)),
                    array_slice($row,    0, count($headers))
                );

                $sku       = trim($data['sku'] ?? '');
                $size      = trim($data['size'] ?? '');
                $color     = trim($data['color'] ?? 'ไม่ระบุ');
                $qty       = (int)($data['quantity'] ?? 0);
                $minAlert  = (int)($data['min_alert'] ?? 5);

                if (!$sku) {
                    $importResults[] = ['row'=>$rowNum,'status'=>'error','msg'=>'ไม่มี SKU'];
                    $errors++; continue;
                }
                if (!isset($skuMap[$sku])) {
                    $importResults[] = ['row'=>$rowNum,'status'=>'error','msg'=>"ไม่พบสินค้า SKU: $sku"];
                    $errors++; continue;
                }
                if (!in_array($size, $validSizes)) {
                    $importResults[] = ['row'=>$rowNum,'status'=>'error','msg'=>"ไซต์ไม่ถูกต้อง: '$size' (ต้องเป็น: ".implode(', ',$validSizes).")"];
                    $errors++; continue;
                }

                $productId = $skuMap[$sku];
                try {
                    $pdo->prepare("INSERT INTO stock (product_id,size,color,quantity,min_alert)
                                   VALUES (?,?,?,?,?)
                                   ON DUPLICATE KEY UPDATE quantity=VALUES(quantity), min_alert=VALUES(min_alert), updated_at=NOW()")
                        ->execute([$productId, $size, $color, $qty, $minAlert]);

                    // Check if it was insert or update (rows affected: 1=insert, 2=update, 0=no change)
                    $importResults[] = ['row'=>$rowNum,'status'=>'inserted','msg'=>"$sku | $size | $color → $qty ชิ้น"];
                    $inserted++;
                } catch (Exception $e) {
                    $importResults[] = ['row'=>$rowNum,'status'=>'error','msg'=>'DB Error: '.$e->getMessage()];
                    $errors++;
                }
            }

            array_unshift($importResults, [
                'row'    => 0,
                'status' => $errors > 0 ? 'warn' : 'ok',
                'msg'    => "สรุป: บันทึก $inserted รายการ | ข้อผิดพลาด $errors",
            ]);
        }

        fclose($handle);
        $importDone = true;
    }
}

$activeTab = $_GET['tab'] ?? 'products';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid fade-in" style="max-width:860px;">
    <div class="page-header">
        <div>
            <nav aria-label="breadcrumb"><ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/dashboard.php">หน้าแรก</a></li>
                <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/pages/data-manage.php">จัดการข้อมูล</a></li>
                <li class="breadcrumb-item active">Import CSV</li>
            </ol></nav>
            <h1 class="page-title">📥 Import CSV</h1>
            <p class="page-subtitle">นำเข้าข้อมูลสินค้าหรือสต็อกจากไฟล์ CSV</p>
        </div>
    </div>

    <!-- ── Tabs ──────────────────────────────────────────────────────── -->
    <ul class="nav nav-tabs mb-4" id="importTabs">
        <li class="nav-item">
            <a class="nav-link <?= $activeTab==='products'&&!$importDone?'active':'' ?> <?= $importDone&&$importType==='products'?'active':'' ?>"
               href="?tab=products">👕 Import สินค้า</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $activeTab==='stock'&&!$importDone?'active':'' ?> <?= $importDone&&$importType==='stock'?'active':'' ?>"
               href="?tab=stock">📦 Import สต็อก</a>
        </li>
    </ul>

    <?php
    $showTab = $importDone ? $importType : $activeTab;
    ?>

    <!-- ── Import Results ─────────────────────────────────────────────── -->
    <?php if ($importDone && !empty($importResults)): ?>
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="card-title">📋 ผลการ Import</span>
            <a href="?tab=<?= $importType ?>" class="btn btn-sm btn-outline-secondary">Import ใหม่</a>
        </div>
        <div class="table-responsive" style="max-height:400px;overflow-y:auto;">
            <table class="table table-sm mb-0">
                <thead class="sticky-top bg-white">
                    <tr><th style="width:60px">แถว</th><th style="width:90px">สถานะ</th><th>รายละเอียด</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($importResults as $r): ?>
                    <?php
                    $badgeClass = match($r['status']) {
                        'ok'       => 'bg-success',
                        'inserted' => 'bg-primary',
                        'updated'  => 'bg-info text-dark',
                        'warn'     => 'bg-warning text-dark',
                        'error'    => 'bg-danger',
                        default    => 'bg-secondary',
                    };
                    $label = match($r['status']) {
                        'ok'       => '✅ สรุป',
                        'inserted' => '➕ เพิ่ม',
                        'updated'  => '✏️ อัปเดต',
                        'warn'     => '⚠️ สรุป',
                        'error'    => '❌ ผิดพลาด',
                        default    => $r['status'],
                    };
                    $rowStyle = $r['status']==='error'?'background:#fff5f5;':($r['row']===0?'background:#f0fff4;font-weight:600;':'');
                    ?>
                    <tr style="<?= $rowStyle ?>">
                        <td class="text-muted"><?= $r['row']>0 ? $r['row'] : '-' ?></td>
                        <td><span class="badge <?= $badgeClass ?>" style="font-size:0.72rem;"><?= $label ?></span></td>
                        <td style="font-size:0.85rem;"><?= htmlspecialchars($r['msg']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Products Tab ───────────────────────────────────────────────── -->
    <?php if ($showTab === 'products'): ?>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="card-title">👕 Import ข้อมูลสินค้า</span>
            <a href="?template=products" class="btn btn-sm btn-outline-success">
                <i class="fas fa-download me-1"></i> ดาวน์โหลด Template
            </a>
        </div>
        <div class="card-body">
            <!-- Column spec -->
            <div class="alert alert-info mb-4" style="font-size:0.85rem;">
                <strong>📋 คอลัมน์ที่รองรับ:</strong><br>
                <code>sku</code> (ไม่บังคับ) &nbsp;·&nbsp;
                <code>name</code> <span class="text-danger">*บังคับ</span> &nbsp;·&nbsp;
                <code>category</code> (ชื่อหมวดหมู่) &nbsp;·&nbsp;
                <code>cost_price</code> &nbsp;·&nbsp;
                <code>selling_price</code> <span class="text-danger">*บังคับ</span> &nbsp;·&nbsp;
                <code>tags</code> &nbsp;·&nbsp;
                <code>status</code> (active / inactive)
                <hr class="my-2">
                <strong>💡 หมายเหตุ:</strong> ถ้ามี sku และสินค้านั้นมีอยู่แล้ว → อัปเดตข้อมูล &nbsp;|&nbsp; ถ้าไม่มี → เพิ่มใหม่
            </div>

            <!-- Categories list -->
            <div class="mb-4">
                <p class="fw-semibold mb-2" style="font-size:0.85rem;">📂 หมวดหมู่ที่มีในระบบ:</p>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($categories as $id => $catName): ?>
                    <span class="badge bg-light text-dark border" style="font-size:0.78rem;"><?= htmlspecialchars($catName) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="import_type" value="products">
                <div class="mb-3">
                    <label class="form-label fw-semibold">เลือกไฟล์ CSV</label>
                    <input type="file" name="csv_file" class="form-control" accept=".csv,text/csv" required>
                    <div class="form-text">ไฟล์ .csv เท่านั้น รองรับภาษาไทย (UTF-8)</div>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-file-import me-1"></i> เริ่ม Import สินค้า
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Stock Tab ──────────────────────────────────────────────────── -->
    <?php if ($showTab === 'stock'): ?>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="card-title">📦 Import ข้อมูลสต็อก</span>
            <a href="?template=stock" class="btn btn-sm btn-outline-success">
                <i class="fas fa-download me-1"></i> ดาวน์โหลด Template
            </a>
        </div>
        <div class="card-body">
            <!-- Column spec -->
            <div class="alert alert-info mb-4" style="font-size:0.85rem;">
                <strong>📋 คอลัมน์ที่รองรับ:</strong><br>
                <code>sku</code> <span class="text-danger">*บังคับ</span> (ต้องตรงกับสินค้าในระบบ) &nbsp;·&nbsp;
                <code>size</code> <span class="text-danger">*บังคับ</span> &nbsp;·&nbsp;
                <code>color</code> &nbsp;·&nbsp;
                <code>quantity</code> &nbsp;·&nbsp;
                <code>min_alert</code> (ค่าเริ่มต้น 5)
                <hr class="my-2">
                <strong>📐 ไซต์ที่ใช้ได้:</strong>
                <?php foreach ($validSizes as $sz): ?>
                <code><?= $sz ?></code><?= $sz !== end($validSizes) ? ' &nbsp;·&nbsp; ' : '' ?>
                <?php endforeach; ?>
                <hr class="my-2">
                <strong>💡 หมายเหตุ:</strong> ถ้า sku+size+color ซ้ำ → อัปเดตจำนวน
            </div>

            <!-- Products list -->
            <?php
            $prodList = $pdo->query("SELECT sku, name FROM products WHERE status!='inactive' AND sku IS NOT NULL ORDER BY name")->fetchAll();
            ?>
            <?php if ($prodList): ?>
            <div class="mb-4">
                <p class="fw-semibold mb-2" style="font-size:0.85rem;">👕 สินค้าที่มี SKU ในระบบ (<?= count($prodList) ?> รายการ):</p>
                <div style="max-height:140px;overflow-y:auto;background:var(--bg-secondary);border-radius:8px;padding:10px;">
                    <?php foreach ($prodList as $p): ?>
                    <span class="badge bg-light text-dark border me-1 mb-1" style="font-size:0.75rem;">
                        <code><?= htmlspecialchars($p['sku']) ?></code> <?= htmlspecialchars($p['name']) ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="alert alert-warning mb-4">⚠️ ยังไม่มีสินค้าในระบบ กรุณา Import สินค้าก่อน</div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="import_type" value="stock">
                <div class="mb-3">
                    <label class="form-label fw-semibold">เลือกไฟล์ CSV</label>
                    <input type="file" name="csv_file" class="form-control" accept=".csv,text/csv" required>
                    <div class="form-text">ไฟล์ .csv เท่านั้น รองรับภาษาไทย (UTF-8)</div>
                </div>
                <button type="submit" class="btn btn-primary" <?= empty($prodList)?'disabled':'' ?>>
                    <i class="fas fa-file-import me-1"></i> เริ่ม Import สต็อก
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
