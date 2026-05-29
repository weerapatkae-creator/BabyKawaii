<?php
$pageTitle = 'คลังรูปสินค้า';
require_once __DIR__ . '/../config/database.php';
requireLogin();

$uploadDir = UPLOAD_PATH . 'products/';
$uploadUrl = UPLOAD_URL  . 'products/';
$allowed   = ['jpg','jpeg','png','webp','gif'];

// ── AJAX: upload single file ─────────────────────────────────────────────────
if (isset($_POST['ajax_upload'])) {
    header('Content-Type: application/json');
    $f = $_FILES['image'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['ok'=>false,'msg'=>'upload error '.(($f['error'])??'no file')]);
        exit;
    }
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        echo json_encode(['ok'=>false,'msg'=>'ไฟล์ไม่รองรับ']);
        exit;
    }
    $dest = $uploadDir . 'product_' . time() . '_' . uniqid() . '.' . $ext;
    if (move_uploaded_file($f['tmp_name'], $dest)) {
        $filename = basename($dest);
        echo json_encode(['ok'=>true,'filename'=>$filename,'url'=>$uploadUrl.$filename]);
    } else {
        echo json_encode(['ok'=>false,'msg'=>'บันทึกไฟล์ไม่ได้']);
    }
    exit;
}

// ── Delete ────────────────────────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $file = basename($_GET['delete']);
    $path = $uploadDir . $file;
    if ($file && file_exists($path) && is_file($path)) unlink($path);
    header('Location: ' . SITE_URL . '/pages/product-gallery.php');
    exit;
}

// ── Load images ───────────────────────────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
$files  = [];
foreach (glob($uploadDir . '*.{jpg,jpeg,png,webp,gif}', GLOB_BRACE) as $path) {
    $base = basename($path);
    if ($search && stripos($base, $search) === false) continue;
    $files[] = ['name'=>$base,'url'=>$uploadUrl.$base,'mtime'=>filemtime($path),'size'=>filesize($path)];
}
usort($files, fn($a,$b) => $b['mtime'] - $a['mtime']);
$fmtSize = fn($b) => $b >= 1048576 ? round($b/1048576,1).' MB' : round($b/1024).' KB';

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.pg-drop {
    border: 2.5px dashed #d8b4fe; border-radius: 16px;
    padding: 32px 20px; text-align: center; cursor: pointer;
    background: #faf5ff; transition: background .15s, border-color .15s;
    position: relative;
}
.pg-drop.dragging { background: #f3e8ff; border-color: #9b72cf; }
.pg-drop input[type=file] {
    position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%;
}
.pg-queue { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 14px; }
.pg-item {
    position: relative; width: 90px; border-radius: 10px; overflow: hidden;
    background: #f5f0fb; border: 1.5px solid #ede5f5;
}
.pg-item img { width: 100%; height: 90px; object-fit: cover; display: block; }
.pg-item .pg-bar-wrap {
    height: 4px; background: #e9d5ff;
}
.pg-item .pg-bar { height: 100%; width: 0; background: #7c3aed; transition: width .2s; }
.pg-item .pg-status {
    font-size: .6rem; text-align: center; padding: 2px 4px;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    color: #888;
}
.pg-item.done  .pg-bar  { background: #22c55e; width: 100%; }
.pg-item.error .pg-bar  { background: #ef4444; width: 100%; }
.pg-summary { font-size: .82rem; color: #7c3aed; font-weight: 600; margin-top: 10px; }

.gal-card {
    border-radius: 12px; overflow: hidden; background: #fff;
    border: 1.5px solid #ede5f5; position: relative;
}
.gal-card img { width: 100%; aspect-ratio: 1; object-fit: cover; display: block; }
.gal-card-meta { padding: 5px 8px; }
.gal-card-meta .fn { font-size: .62rem; color: #999; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.gal-card-meta .sz { font-size: .6rem; color: #bbb; }
.gal-actions {
    position: absolute; top: 5px; right: 5px;
    display: flex; gap: 3px; opacity: 0; transition: opacity .15s;
}
.gal-card:hover .gal-actions { opacity: 1; }
.gal-btn {
    background: rgba(0,0,0,.5); color: #fff; border: none;
    border-radius: 6px; padding: 3px 7px; font-size: .65rem;
    cursor: pointer; text-decoration: none; display: inline-block;
}
.gal-btn.del { background: rgba(220,38,38,.75); }
</style>

<div class="container-fluid fade-in">
<div class="page-header">
    <div>
        <h1 class="page-title">🖼️ คลังรูปสินค้า</h1>
        <p class="text-muted mb-0" style="font-size:.82rem;" id="totalCount"><?= count($files) ?> รูป</p>
    </div>
    <a href="<?= SITE_URL ?>/pages/products.php" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left me-1"></i> สินค้า
    </a>
</div>

<!-- ── Upload zone ────────────────────────────────────────────────────────── -->
<div class="card mb-4">
    <div class="card-body p-3">
        <div class="pg-drop" id="dropZone">
            <input type="file" id="imgInput" accept="image/*" multiple
                   onchange="handleFiles(this.files)">
            <div style="font-size:2rem;">📸</div>
            <div style="font-weight:700;color:#7c3aed;margin-top:6px;">
                กดเลือกรูป หรือลากมาวาง
            </div>
            <div style="font-size:.75rem;color:#aaa;margin-top:4px;">
                JPG, PNG, WEBP · เลือกได้หลายรูปพร้อมกัน
            </div>
        </div>

        <div class="pg-queue" id="queue"></div>
        <div class="pg-summary" id="summary" style="display:none;"></div>
    </div>
</div>

<!-- ── Search ─────────────────────────────────────────────────────────────── -->
<div class="d-flex align-items-center gap-3 mb-3">
    <form class="d-flex gap-2 flex-grow-1" style="max-width:320px;">
        <input type="text" name="q" class="form-control form-control-sm"
               placeholder="🔍 ค้นหา..." value="<?= htmlspecialchars($search) ?>">
        <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
        <?php if ($search): ?>
        <a href="?" class="btn btn-sm btn-outline-danger"><i class="fas fa-times"></i></a>
        <?php endif; ?>
    </form>
</div>

<!-- ── Gallery ───────────────────────────────────────────────────────────── -->
<div id="gallery" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:10px;">
<?php foreach ($files as $f): ?>
<div class="gal-card">
    <img src="<?= htmlspecialchars($f['url']) ?>" loading="lazy" alt="">
    <div class="gal-card-meta">
        <div class="fn"><?= htmlspecialchars($f['name']) ?></div>
        <div class="sz"><?= $fmtSize($f['size']) ?></div>
    </div>
    <div class="gal-actions">
        <a href="<?= htmlspecialchars($f['url']) ?>" target="_blank" class="gal-btn">⛶</a>
        <a href="?delete=<?= urlencode($f['name']) ?>"
           onclick="return confirm('ลบรูปนี้?')" class="gal-btn del">✕</a>
    </div>
</div>
<?php endforeach; ?>
<?php if (empty($files)): ?>
<div id="emptyMsg" style="grid-column:1/-1;text-align:center;padding:48px;color:#ccc;">
    <div style="font-size:3rem;">🖼️</div>
    <p class="mt-2">ยังไม่มีรูปสินค้า</p>
</div>
<?php endif; ?>
</div>

</div><!-- container -->

<script>
const UPLOAD_URL = '<?= SITE_URL ?>/pages/product-gallery.php';
let _done = 0, _total = 0, _currentCount = <?= count($files) ?>;

function handleFiles(fileList) {
    const files = [...fileList].filter(f => f.type.startsWith('image/'));
    if (!files.length) return;

    document.getElementById('queue').innerHTML = '';
    document.getElementById('summary').style.display = 'none';
    _done = 0; _total = files.length;

    files.forEach(file => {
        const item = createQueueItem(file);
        document.getElementById('queue').appendChild(item.el);
        uploadFile(file, item);
    });
    // reset input so same files can be re-selected
    document.getElementById('imgInput').value = '';
}

function createQueueItem(file) {
    const el = document.createElement('div');
    el.className = 'pg-item';
    const url = URL.createObjectURL(file);
    el.innerHTML = `<img src="${url}">
        <div class="pg-bar-wrap"><div class="pg-bar" id="bar-${file.name}"></div></div>
        <div class="pg-status" id="st-${file.name}">รอ...</div>`;
    return { el, bar: null, status: null };
}

function uploadFile(file, item) {
    const fd = new FormData();
    fd.append('ajax_upload', '1');
    fd.append('image', file);

    const xhr = new XMLHttpRequest();
    xhr.open('POST', UPLOAD_URL);

    xhr.upload.onprogress = e => {
        if (!e.lengthComputable) return;
        const pct = Math.round(e.loaded / e.total * 100);
        const bar = item.el.querySelector('.pg-bar');
        const st  = item.el.querySelector('.pg-status');
        if (bar) bar.style.width = pct + '%';
        if (st)  st.textContent = pct + '%';
    };

    xhr.onload = () => {
        try {
            const res = JSON.parse(xhr.responseText);
            const st  = item.el.querySelector('.pg-status');
            if (res.ok) {
                item.el.classList.add('done');
                if (st) st.textContent = '✓';
                addToGallery(res.filename, res.url);
            } else {
                item.el.classList.add('error');
                if (st) st.textContent = 'ผิดพลาด';
            }
        } catch(e) {
            item.el.classList.add('error');
        }
        _done++;
        updateSummary();
    };

    xhr.onerror = () => {
        item.el.classList.add('error');
        const st = item.el.querySelector('.pg-status');
        if (st) st.textContent = 'error';
        _done++;
        updateSummary();
    };

    xhr.send(fd);
}

function addToGallery(filename, url) {
    const empty = document.getElementById('emptyMsg');
    if (empty) empty.remove();

    _currentCount++;
    document.getElementById('totalCount').textContent = _currentCount + ' รูป';

    const card = document.createElement('div');
    card.className = 'gal-card';
    card.innerHTML = `<img src="${url}" loading="lazy">
        <div class="gal-card-meta">
            <div class="fn">${filename}</div>
        </div>
        <div class="gal-actions">
            <a href="${url}" target="_blank" class="gal-btn">⛶</a>
            <a href="?delete=${encodeURIComponent(filename)}"
               onclick="return confirm('ลบรูปนี้?')" class="gal-btn del">✕</a>
        </div>`;
    document.getElementById('gallery').prepend(card);
}

function updateSummary() {
    const s = document.getElementById('summary');
    s.style.display = 'block';
    if (_done < _total) {
        s.textContent = `กำลังอัปโหลด ${_done}/${_total}...`;
    } else {
        const ok    = document.getElementById('queue').querySelectorAll('.done').length;
        const fail  = document.getElementById('queue').querySelectorAll('.error').length;
        s.textContent = `อัปโหลดเสร็จ ${ok} รูป` + (fail ? ` (ล้มเหลว ${fail})` : '');
        s.style.color = fail ? '#ef4444' : '#22c55e';
    }
}

// Drag-drop (desktop)
const dz = document.getElementById('dropZone');
dz.addEventListener('dragover',  e => { e.preventDefault(); dz.classList.add('dragging'); });
dz.addEventListener('dragleave', () => dz.classList.remove('dragging'));
dz.addEventListener('drop', e => {
    e.preventDefault(); dz.classList.remove('dragging');
    handleFiles(e.dataTransfer.files);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
