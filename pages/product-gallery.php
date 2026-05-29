<?php
$pageTitle = 'คลังรูปสินค้า';
require_once __DIR__ . '/../config/database.php';
requireLogin();

$uploadDir = UPLOAD_PATH . 'products/';
$uploadUrl = UPLOAD_URL  . 'products/';
$allowed   = ['jpg','jpeg','png','webp','gif'];
$msg = '';

// ── Upload ────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['images']['name'][0])) {
    $ok = $fail = 0;
    foreach ($_FILES['images']['name'] as $i => $name) {
        if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) { $fail++; continue; }
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) { $fail++; continue; }
        $dest = $uploadDir . 'product_' . time() . '_' . uniqid() . '.' . $ext;
        move_uploaded_file($_FILES['images']['tmp_name'][$i], $dest) ? $ok++ : $fail++;
    }
    $msg = "อัปโหลดสำเร็จ $ok ไฟล์" . ($fail ? " (ล้มเหลว $fail)" : '');
}

// ── Delete ────────────────────────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $file = basename($_GET['delete']);
    $path = $uploadDir . $file;
    if ($file && file_exists($path) && is_file($path)) {
        unlink($path);
        header('Location: ' . SITE_URL . '/pages/product-gallery.php?msg=deleted');
        exit;
    }
}

// ── Load images (filesystem) ──────────────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
$files  = [];
foreach (glob($uploadDir . '*.{jpg,jpeg,png,webp,gif}', GLOB_BRACE) as $path) {
    $base = basename($path);
    if ($search && stripos($base, $search) === false) continue;
    $files[] = ['name' => $base, 'url' => $uploadUrl . $base, 'mtime' => filemtime($path), 'size' => filesize($path)];
}
usort($files, fn($a,$b) => $b['mtime'] - $a['mtime']);

$fmtSize = fn($b) => $b >= 1048576 ? round($b/1048576,1).' MB' : round($b/1024).' KB';

if (isset($_GET['msg'])) $msg = $_GET['msg'] === 'deleted' ? 'ลบรูปแล้ว' : '';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid fade-in">
<div class="page-header">
    <div>
        <h1 class="page-title">🖼️ คลังรูปสินค้า</h1>
        <p class="text-muted mb-0" style="font-size:.82rem;"><?= count($files) ?> รูป · อัปโหลดทีเดียวได้หลายรูป</p>
    </div>
    <a href="<?= SITE_URL ?>/pages/products.php" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left me-1"></i> สินค้า
    </a>
</div>

<?php if ($msg): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- ── Upload zone ──────────────────────────────────────────────────────────── -->
<div class="card mb-4">
    <div class="card-body p-3">
        <form method="POST" enctype="multipart/form-data" id="uploadForm">
            <div id="dropZone" onclick="document.getElementById('imgInput').click()"
                 style="border:2.5px dashed #d8b4fe;border-radius:14px;padding:28px 20px;text-align:center;cursor:pointer;background:#faf5ff;transition:background .15s;">
                <div style="font-size:2rem;">📸</div>
                <div style="font-weight:700;color:#7c3aed;margin-top:6px;">คลิกหรือลากรูปมาวาง</div>
                <div style="font-size:.75rem;color:#aaa;margin-top:4px;">JPG, PNG, WEBP · เลือกได้หลายไฟล์พร้อมกัน</div>
                <div id="selectedInfo" style="font-size:.8rem;color:#7c3aed;margin-top:8px;display:none;"></div>
            </div>
            <input type="file" id="imgInput" name="images[]" accept="image/*" multiple class="d-none"
                   onchange="onFilesSelected(this)">
            <div id="previewStrip" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:12px;"></div>
            <div id="uploadActions" style="display:none;margin-top:12px;text-align:right;gap:8px;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-upload me-1"></i> อัปโหลด <span id="uploadCount"></span> รูป
                </button>
                <button type="button" class="btn btn-outline-secondary ms-2" onclick="clearSelection()">ยกเลิก</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Search ──────────────────────────────────────────────────────────────── -->
<div class="d-flex align-items-center gap-3 mb-3">
    <form class="d-flex gap-2 flex-grow-1" style="max-width:340px;">
        <input type="text" name="q" class="form-control form-control-sm" placeholder="🔍 ค้นหาชื่อไฟล์..."
               value="<?= htmlspecialchars($search) ?>">
        <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
        <?php if ($search): ?><a href="?" class="btn btn-sm btn-outline-danger"><i class="fas fa-times"></i></a><?php endif; ?>
    </form>
    <span class="text-muted" style="font-size:.8rem;white-space:nowrap;"><?= count($files) ?> ไฟล์</span>
</div>

<!-- ── Gallery grid ────────────────────────────────────────────────────────── -->
<?php if (empty($files)): ?>
<div class="text-center py-5 text-muted">
    <div style="font-size:3rem;">🖼️</div>
    <p class="mt-2">ยังไม่มีรูปสินค้า กด Upload เพื่อเพิ่ม</p>
</div>
<?php else: ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px;">
<?php foreach ($files as $f): ?>
<div class="pg-card" style="border-radius:12px;overflow:hidden;background:#fff;border:1.5px solid #ede5f5;position:relative;">
    <div style="aspect-ratio:1;overflow:hidden;background:#f5f0fb;">
        <img src="<?= htmlspecialchars($f['url']) ?>" loading="lazy" alt="<?= htmlspecialchars($f['name']) ?>"
             style="width:100%;height:100%;object-fit:cover;display:block;">
    </div>
    <div style="padding:6px 8px;">
        <div style="font-size:.65rem;color:#888;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($f['name']) ?></div>
        <div style="font-size:.6rem;color:#bbb;"><?= $fmtSize($f['size']) ?> · <?= date('d/m/y', $f['mtime']) ?></div>
    </div>
    <div style="position:absolute;top:6px;right:6px;display:flex;gap:4px;">
        <a href="<?= htmlspecialchars($f['url']) ?>" target="_blank"
           style="background:rgba(0,0,0,.45);color:#fff;border-radius:6px;padding:3px 6px;font-size:.65rem;text-decoration:none;"
           title="ดูรูปเต็ม">⛶</a>
        <a href="?delete=<?= urlencode($f['name']) ?>"
           onclick="return confirm('ลบรูปนี้?')"
           style="background:rgba(220,38,38,.75);color:#fff;border-radius:6px;padding:3px 6px;font-size:.65rem;text-decoration:none;"
           title="ลบ">✕</a>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>

<script>
function onFilesSelected(input) {
    const files = [...input.files];
    if (!files.length) return;
    document.getElementById('selectedInfo').style.display = 'block';
    document.getElementById('selectedInfo').textContent = `เลือก ${files.length} ไฟล์`;
    document.getElementById('uploadActions').style.display = 'block';
    document.getElementById('uploadCount').textContent = files.length;

    const strip = document.getElementById('previewStrip');
    strip.innerHTML = '';
    files.slice(0, 20).forEach(f => {
        const img = document.createElement('img');
        img.style.cssText = 'width:72px;height:72px;object-fit:cover;border-radius:8px;border:1.5px solid #ede5f5;';
        const reader = new FileReader();
        reader.onload = e => img.src = e.target.result;
        reader.readAsDataURL(f);
        strip.appendChild(img);
    });
    if (files.length > 20) {
        const more = document.createElement('div');
        more.style.cssText = 'width:72px;height:72px;border-radius:8px;background:#f5f0fb;display:flex;align-items:center;justify-content:center;font-size:.8rem;color:#9b72cf;';
        more.textContent = '+' + (files.length - 20);
        strip.appendChild(more);
    }
}

function clearSelection() {
    document.getElementById('imgInput').value = '';
    document.getElementById('previewStrip').innerHTML = '';
    document.getElementById('selectedInfo').style.display = 'none';
    document.getElementById('uploadActions').style.display = 'none';
}

// Drag-and-drop onto zone
const dz = document.getElementById('dropZone');
dz.addEventListener('dragover', e => { e.preventDefault(); dz.style.background = '#f3e8ff'; });
dz.addEventListener('dragleave', () => { dz.style.background = '#faf5ff'; });
dz.addEventListener('drop', e => {
    e.preventDefault(); dz.style.background = '#faf5ff';
    const dt = new DataTransfer();
    [...e.dataTransfer.files].filter(f => f.type.startsWith('image/')).forEach(f => dt.items.add(f));
    const inp = document.getElementById('imgInput');
    inp.files = dt.files;
    onFilesSelected(inp);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
