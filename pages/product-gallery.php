<?php
$pageTitle = 'คลังรูปสินค้า';
require_once __DIR__ . '/../config/database.php';
requireLogin();

$uploadDir = UPLOAD_PATH . 'products/';
$thumbDir  = UPLOAD_PATH . 'products/thumbs/';
$uploadUrl = UPLOAD_URL  . 'products/';
$thumbUrl  = UPLOAD_URL  . 'products/thumbs/';
$allowed   = ['jpg','jpeg','png','webp','gif'];

// ── Image resizer ──────────────────────────────────────────────────────────────
// $size=300 → thumbnail 300×300 crop-center
// $size=1000 → resize max-side 1000px (maintain aspect ratio)
function makeThumb(string $src, string $dest, int $size = 300): bool {
    $info = @getimagesize($src);
    if (!$info) return false;
    [$w, $h, $type] = $info;
    $img = match($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($src),
        IMAGETYPE_PNG  => @imagecreatefrompng($src),
        IMAGETYPE_WEBP => @imagecreatefromwebp($src),
        IMAGETYPE_GIF  => @imagecreatefromgif($src),
        default        => false,
    };
    if (!$img) return false;

    if ($size === 300) {
        // crop square from center for gallery thumbnails
        $side  = min($w, $h);
        $x     = (int)(($w - $side) / 2);
        $y     = (int)(($h - $side) / 2);
        $out   = imagecreatetruecolor($size, $size);
        imagecopyresampled($out, $img, 0, 0, $x, $y, $size, $size, $side, $side);
    } else {
        // maintain aspect ratio, shrink only if larger than $size
        if ($w <= $size && $h <= $size) {
            $nw = $w; $nh = $h;
        } else {
            $ratio = min($size/$w, $size/$h);
            $nw = max(1, (int)($w * $ratio));
            $nh = max(1, (int)($h * $ratio));
        }
        $out = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
    }

    $ok = imagejpeg($out, $dest, 85);
    imagedestroy($img);
    imagedestroy($out);
    return $ok;
}

function thumbPath(string $filename, string $thumbDir): string {
    return $thumbDir . pathinfo($filename, PATHINFO_FILENAME) . '.jpg';
}

// ── AJAX: bulk delete ────────────────────────────────────────────────────────
if (isset($_POST['ajax_bulk_delete'])) {
    header('Content-Type: application/json');
    $names = json_decode($_POST['files'] ?? '[]', true);
    $deleted = 0;
    foreach ((array)$names as $name) {
        $file = basename($name);
        $path = $uploadDir . $file;
        if ($file && file_exists($path) && is_file($path)) {
            unlink($path);
            $tp = thumbPath($file, $thumbDir);
            if (file_exists($tp)) unlink($tp);
            $deleted++;
        }
    }
    echo json_encode(['ok'=>true,'deleted'=>$deleted]);
    exit;
}

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
    // บันทึกเป็น .jpg เสมอ (ลดขนาดไฟล์)
    $filename = 'product_' . time() . '_' . uniqid() . '.jpg';
    $dest     = $uploadDir . $filename;
    if (move_uploaded_file($f['tmp_name'], $dest)) {
        // resize ทับไฟล์ต้นฉบับให้ max 1000px (ลบต้นฉบับใหญ่ทิ้ง)
        makeThumb($dest, $dest, 1000);  // ทับตัวเอง
        // สร้าง thumbnail 300px
        $tPath = thumbPath($filename, $thumbDir);
        makeThumb($dest, $tPath, 300);
        $tUrl = file_exists($tPath) ? ($thumbUrl . basename($tPath)) : ($uploadUrl . $filename);
        echo json_encode(['ok'=>true,'filename'=>$filename,'url'=>$uploadUrl.$filename,'thumb'=>$tUrl]);
    } else {
        echo json_encode(['ok'=>false,'msg'=>'บันทึกไฟล์ไม่ได้']);
    }
    exit;
}

// ── Delete ────────────────────────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $file = basename($_GET['delete']);
    $path = $uploadDir . $file;
    if ($file && file_exists($path) && is_file($path)) {
        unlink($path);
        $tp = thumbPath($file, $thumbDir);
        if (file_exists($tp)) unlink($tp);
    }
    header('Location: ' . SITE_URL . '/pages/product-gallery.php');
    exit;
}

// ── AJAX: load more (JSON) ────────────────────────────────────────────────────
if (isset($_GET['ajax_list'])) {
    header('Content-Type: application/json');
    $q    = trim($_GET['q'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $per  = 24;
    $all  = [];
    foreach (glob($uploadDir . '*.{jpg,jpeg,png,webp,gif}', GLOB_BRACE) as $path) {
        $base = basename($path);
        if ($q && stripos($base, $q) === false) continue;
        $all[] = ['name'=>$base,'mtime'=>filemtime($path),'size'=>filesize($path)];
    }
    usort($all, fn($a,$b) => $b['mtime'] - $a['mtime']);
    $total = count($all);
    $items = array_map(function($f) use ($uploadUrl,$thumbUrl,$thumbDir) {
        $tp = thumbPath($f['name'], $thumbDir);
        return [
            'name'  => $f['name'],
            'url'   => $uploadUrl . $f['name'],
            'thumb' => file_exists($tp) ? ($thumbUrl . basename($tp)) : ($uploadUrl . $f['name']),
            'size'  => $f['size'] >= 1048576
                ? round($f['size']/1048576,1).' MB'
                : round($f['size']/1024).' KB',
        ];
    }, array_slice($all, ($page-1)*$per, $per));
    echo json_encode(['items'=>$items,'total'=>$total,'page'=>$page,'per'=>$per]);
    exit;
}

// ── Load images (first page only) ────────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
$PER    = 24;
$allFiles = [];
foreach (glob($uploadDir . '*.{jpg,jpeg,png,webp,gif}', GLOB_BRACE) as $path) {
    $base = basename($path);
    if ($search && stripos($base, $search) === false) continue;
    $allFiles[] = ['name'=>$base,'mtime'=>filemtime($path),'size'=>filesize($path)];
}
usort($allFiles, fn($a,$b) => $b['mtime'] - $a['mtime']);
$totalFiles = count($allFiles);
$files = array_map(function($f) use ($uploadUrl,$thumbUrl,$thumbDir) {
    $tp = thumbPath($f['name'], $thumbDir);
    $f['url']   = $uploadUrl . $f['name'];
    $f['thumb'] = file_exists($tp) ? ($thumbUrl . basename($tp)) : ($uploadUrl . $f['name']);
    return $f;
}, array_slice($allFiles, 0, $PER));
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

/* ── Selection mode ─────────────────────────── */
.gal-card.selected {
    border-color: #7c3aed;
    box-shadow: 0 0 0 3px rgba(124,58,237,.25);
}
.gal-card .gal-check {
    position: absolute; top: 6px; left: 6px;
    width: 22px; height: 22px; border-radius: 50%;
    background: rgba(255,255,255,.85); border: 2px solid #ddd;
    display: none; align-items: center; justify-content: center;
    cursor: pointer; font-size: .75rem; z-index: 2;
    transition: background .12s, border-color .12s;
}
.select-mode .gal-card .gal-check  { display: flex; }
.select-mode .gal-card .gal-actions { display: none; }
.gal-card.selected .gal-check {
    background: #7c3aed; border-color: #7c3aed; color: #fff;
}
.select-mode .gal-card { cursor: pointer; }

#selectBar {
    position: sticky; bottom: 16px; z-index: 100;
    background: #fff; border: 1.5px solid #7c3aed; border-radius: 14px;
    padding: 10px 16px; display: flex; align-items: center; gap: 10px;
    box-shadow: 0 4px 20px rgba(124,58,237,.2);
    display: none;
}
</style>

<div class="container-fluid fade-in">
<div class="page-header">
    <div>
        <h1 class="page-title">🖼️ คลังรูปสินค้า</h1>
        <p class="text-muted mb-0" style="font-size:.82rem;" id="totalCount"><?= $totalFiles ?> รูป</p>
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

<!-- ── Toolbar ────────────────────────────────────────────────────────────── -->
<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
    <form class="d-flex gap-2" style="flex:1;max-width:300px;">
        <input type="text" name="q" class="form-control form-control-sm"
               placeholder="🔍 ค้นหา..." value="<?= htmlspecialchars($search) ?>">
        <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
        <?php if ($search): ?>
        <a href="?" class="btn btn-sm btn-outline-danger"><i class="fas fa-times"></i></a>
        <?php endif; ?>
    </form>
    <button class="btn btn-sm btn-outline-secondary ms-auto" id="btnSelect" onclick="toggleSelectMode()">
        <i class="fas fa-check-square me-1"></i> เลือก
    </button>
</div>

<!-- Sticky select bar -->
<div id="selectBar">
    <input type="checkbox" id="chkAll" onchange="toggleSelectAll(this.checked)"
           style="width:18px;height:18px;accent-color:#7c3aed;cursor:pointer;">
    <span id="selectCount" style="font-size:.85rem;font-weight:600;color:#7c3aed;flex:1;">เลือก 0 รูป</span>
    <button class="btn btn-sm btn-outline-secondary" onclick="toggleSelectMode()">ยกเลิก</button>
    <button class="btn btn-sm btn-danger" id="btnBulkDel" onclick="bulkDelete()" disabled>
        <i class="fas fa-trash me-1"></i> ลบที่เลือก
    </button>
</div>

<!-- ── Gallery ───────────────────────────────────────────────────────────── -->
<div id="gallery" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:10px;">
<?php foreach ($files as $f): ?>
<div class="gal-card" data-name="<?= htmlspecialchars($f['name']) ?>" onclick="onCardClick(this)">
    <div class="gal-check">✓</div>
    <img src="<?= htmlspecialchars($f['thumb']) ?>" loading="lazy" alt="" decoding="async">
    <div class="gal-card-meta">
        <div class="fn"><?= htmlspecialchars($f['name']) ?></div>
        <div class="sz"><?= $fmtSize($f['size']) ?></div>
    </div>
    <div class="gal-actions">
        <a href="<?= htmlspecialchars($uploadUrl.$f['name']) ?>" target="_blank" class="gal-btn" onclick="event.stopPropagation()">⛶</a>
        <a href="?delete=<?= urlencode($f['name']) ?>"
           onclick="event.stopPropagation();return confirm('ลบรูปนี้?')" class="gal-btn del">✕</a>
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

<?php if ($totalFiles > $PER): ?>
<div style="text-align:center;margin:20px 0;" id="loadMoreWrap">
    <button class="btn btn-outline-secondary" onclick="loadMore()" id="loadMoreBtn">
        โหลดเพิ่ม (เหลืออีก <?= $totalFiles - $PER ?> รูป)
    </button>
</div>
<?php endif; ?>

</div><!-- container -->

<script>
const UPLOAD_URL  = '<?= SITE_URL ?>/pages/product-gallery.php';
const SEARCH_Q    = <?= json_encode($search) ?>;
let _done = 0, _total = 0, _currentCount = <?= $totalFiles ?>;
let _galPage = 1, _galTotal = <?= $totalFiles ?>, _galPer = <?= $PER ?>, _loading = false;

const MAX_FILES    = 50;
const MAX_PX       = 1000; // ลดจาก 1200 → 1000 ประหยัด memory
const JPEG_QUALITY = 0.80;

// resize + upload ทีละ 1 รูป เพื่อใช้ memory คงที่ ไม่ว่าจะ 5 หรือ 50 รูป
function resizeAndUpload(file, idx, total, sumEl) {
    return new Promise(resolve => {
        sumEl.textContent = `ปรับขนาด ${idx}/${total}...`;

        // สร้าง progress item (ไม่โหลดรูปเป็น preview เพื่อประหยัด memory)
        const el = document.createElement('div');
        el.className = 'pg-item';
        el.innerHTML = `<div style="width:100%;height:70px;background:#ede5f5;display:flex;align-items:center;justify-content:center;font-size:1.2rem;">📷</div>
            <div class="pg-bar-wrap"><div class="pg-bar"></div></div>
            <div class="pg-status">↻</div>`;
        document.getElementById('queue').appendChild(el);

        const setStatus = t => { const s = el.querySelector('.pg-status'); if(s) s.textContent = t; };
        const setBar    = p => { const b = el.querySelector('.pg-bar');    if(b) b.style.width = p+'%'; };

        try {
            const imgEl = new Image();
            const objUrl = URL.createObjectURL(file);

            imgEl.onerror = () => {
                URL.revokeObjectURL(objUrl);
                // fallback: upload original ถ้า decode ไม่ได้
                doUpload(file, file.name);
            };

            imgEl.onload = () => {
                try {
                    let w = imgEl.naturalWidth  || imgEl.width  || 0;
                    let h = imgEl.naturalHeight || imgEl.height || 0;

                    if (w > MAX_PX || h > MAX_PX) {
                        const r = Math.min(MAX_PX/w, MAX_PX/h);
                        w = Math.round(w * r);
                        h = Math.round(h * r);
                    }

                    const c = document.createElement('canvas');
                    c.width = w || 1; c.height = h || 1;
                    c.getContext('2d').drawImage(imgEl, 0, 0, c.width, c.height);

                    // revoke SETELAH draw
                    URL.revokeObjectURL(objUrl);
                    // clear img ref
                    imgEl.src = '';

                    c.toBlob(blob => {
                        c.width = c.height = 0; // free canvas memory
                        doUpload(blob || file, file.name);
                    }, 'image/jpeg', JPEG_QUALITY);

                } catch(e) {
                    URL.revokeObjectURL(objUrl);
                    doUpload(file, file.name);
                }
            };

            imgEl.src = objUrl;

        } catch(e) { doUpload(file, file.name); }

        function doUpload(data, name) {
            sumEl.textContent = `อัปโหลด ${idx}/${total}...`;
            setStatus('⬆');
            const fd = new FormData();
            fd.append('ajax_upload', '1');
            fd.append('image', data, name || 'img.jpg');

            const xhr = new XMLHttpRequest();
            xhr.open('POST', UPLOAD_URL);
            xhr.upload.onprogress = e => {
                if (e.lengthComputable) setBar(Math.round(e.loaded/e.total*100));
            };
            xhr.onload = () => {
                try {
                    const res = JSON.parse(xhr.responseText);
                    if (res.ok) {
                        el.classList.add('done'); setStatus('✓');
                        // แสดง thumbnail เล็กๆ หลัง upload สำเร็จ (ไม่กิน memory เพราะเล็กแล้ว)
                        if (res.thumb) {
                            const img2 = new Image();
                            img2.onload = () => {
                                const ph = el.querySelector('div');
                                if (ph) ph.replaceWith(img2);
                            };
                            img2.style.cssText = 'width:100%;height:70px;object-fit:cover;display:block;';
                            img2.src = res.thumb;
                        }
                        addToGallery(res.filename, res.url, res.thumb);
                    } else {
                        el.classList.add('error'); setStatus('✗');
                    }
                } catch(e) { el.classList.add('error'); setStatus('✗'); }
                _done++; updateSummary(); resolve();
            };
            xhr.onerror = () => { el.classList.add('error'); setStatus('✗'); _done++; updateSummary(); resolve(); };
            xhr.send(fd);
        }
    });
}

async function handleFiles(fileList) {
    let files = [...fileList].filter(f => f.type.startsWith('image/'));
    if (!files.length) return;
    if (files.length > MAX_FILES) files = files.slice(0, MAX_FILES);

    document.getElementById('queue').innerHTML = '';
    document.getElementById('imgInput').value = '';
    const sumEl = document.getElementById('summary');
    sumEl.style.display = 'block';
    sumEl.style.color   = '#7c3aed';
    _done = 0; _total  = files.length;
    sumEl.textContent   = `เตรียม ${_total} รูป...`;

    // process ทีละ 1 รูป เพื่อ memory คงที่
    for (let i = 0; i < files.length; i++) {
        await resizeAndUpload(files[i], i + 1, files.length, sumEl);
    }
}

// ไม่ใช้แล้ว แต่เก็บไว้ไม่ให้ error ถ้ามี reference อื่น
function createQueueItem(file) {
    const el = document.createElement('div');
    el.className = 'pg-item';
    el.innerHTML = `<div style="height:70px;background:#ede5f5;"></div>
        <div class="pg-bar-wrap"><div class="pg-bar"></div></div>
        <div class="pg-status">รอ...</div>`;
    return { el };
}


function addToGallery(filename, url, thumb) {
    const empty = document.getElementById('emptyMsg');
    if (empty) empty.remove();

    _currentCount++;
    document.getElementById('totalCount').textContent = _currentCount + ' รูป';

    const card = document.createElement('div');
    card.className = 'gal-card';
    card.dataset.name = filename;
    card.setAttribute('onclick', 'onCardClick(this)');
    card.innerHTML = `<div class="gal-check">✓</div>
        <img src="${thumb||url}" loading="lazy" decoding="async">
        <div class="gal-card-meta"><div class="fn">${filename}</div></div>
        <div class="gal-actions">
            <a href="${url}" target="_blank" class="gal-btn" onclick="event.stopPropagation()">⛶</a>
            <a href="?delete=${encodeURIComponent(filename)}"
               onclick="event.stopPropagation();return confirm('ลบรูปนี้?')" class="gal-btn del">✕</a>
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

// ── Select mode ──────────────────────────────────────────────────────────────
let _selectMode = false;

function toggleSelectMode() {
    _selectMode = !_selectMode;
    document.getElementById('gallery').classList.toggle('select-mode', _selectMode);
    document.getElementById('selectBar').style.display = _selectMode ? 'flex' : 'none';
    document.getElementById('btnSelect').classList.toggle('btn-outline-secondary', !_selectMode);
    document.getElementById('btnSelect').classList.toggle('btn-secondary', _selectMode);
    if (!_selectMode) {
        document.querySelectorAll('.gal-card.selected').forEach(c => c.classList.remove('selected'));
        updateSelectCount();
    }
}

function onCardClick(card) {
    if (!_selectMode) return;
    card.classList.toggle('selected');
    updateSelectCount();
    document.getElementById('chkAll').checked =
        document.querySelectorAll('.gal-card').length ===
        document.querySelectorAll('.gal-card.selected').length;
}

function toggleSelectAll(checked) {
    document.querySelectorAll('.gal-card').forEach(c => c.classList.toggle('selected', checked));
    updateSelectCount();
}

function updateSelectCount() {
    const n = document.querySelectorAll('.gal-card.selected').length;
    document.getElementById('selectCount').textContent = `เลือก ${n} รูป`;
    document.getElementById('btnBulkDel').disabled = n === 0;
}

async function bulkDelete() {
    const cards = [...document.querySelectorAll('.gal-card.selected')];
    if (!cards.length) return;
    if (!confirm(`ลบ ${cards.length} รูปที่เลือก?`)) return;

    const files = cards.map(c => c.dataset.name);
    const fd = new FormData();
    fd.append('ajax_bulk_delete', '1');
    fd.append('files', JSON.stringify(files));

    const btn = document.getElementById('btnBulkDel');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    const res  = await fetch(UPLOAD_URL, { method:'POST', body: fd });
    const data = await res.json();

    if (data.ok) {
        cards.forEach(c => c.remove());
        _currentCount -= data.deleted;
        document.getElementById('totalCount').textContent = _currentCount + ' รูป';
        document.getElementById('chkAll').checked = false;
        updateSelectCount();
        toggleSelectMode();
    }
}

// ── Load more ────────────────────────────────────────────────────────────────
async function loadMore() {
    if (_loading) return;
    _loading = true;
    const btn = document.getElementById('loadMoreBtn');
    if (btn) btn.textContent = 'กำลังโหลด...';

    _galPage++;
    const res  = await fetch(`${UPLOAD_URL}?ajax_list=1&page=${_galPage}&q=${encodeURIComponent(SEARCH_Q)}`);
    const data = await res.json();
    _loading   = false;

    data.items.forEach(item => {
        const card = document.createElement('div');
        card.className = 'gal-card';
        card.dataset.name = item.name;
        card.setAttribute('onclick','onCardClick(this)');
        card.innerHTML = `<div class="gal-check">✓</div>
            <img src="${item.thumb||item.url}" loading="lazy" decoding="async" alt="">
            <div class="gal-card-meta">
                <div class="fn">${item.name}</div>
                <div class="sz">${item.size}</div>
            </div>
            <div class="gal-actions">
                <a href="${item.url}" target="_blank" class="gal-btn" onclick="event.stopPropagation()">⛶</a>
                <a href="?delete=${encodeURIComponent(item.name)}"
                   onclick="event.stopPropagation();return confirm('ลบรูปนี้?')" class="gal-btn del">✕</a>
            </div>`;
        document.getElementById('gallery').appendChild(card);
    });

    const loaded = _galPage * _galPer;
    const wrap   = document.getElementById('loadMoreWrap');
    if (loaded >= data.total) {
        if (wrap) wrap.remove();
    } else {
        if (btn) btn.textContent = `โหลดเพิ่ม (เหลืออีก ${data.total - loaded} รูป)`;
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
