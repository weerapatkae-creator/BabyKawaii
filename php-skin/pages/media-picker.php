<?php
/**
 * Media Picker — standalone popup for calendar modal
 * Opens as window.open(), sends selected items back via postMessage
 */
require_once __DIR__ . '/../config/database.php';
requireLogin();

$pdo = getDB();
$typeFilter = $_GET['type'] ?? '';
$search     = trim($_GET['q'] ?? '');
$multi      = !empty($_GET['multi']); // allow multiple select

$where  = ['1=1'];
$params = [];
if ($typeFilter) { $where[] = 'file_type = ?'; $params[] = $typeFilter; }
if ($search)     { $where[] = '(title LIKE ? OR tags LIKE ? OR original_name LIKE ?)'; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }

$stmt = $pdo->prepare("SELECT * FROM media WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC LIMIT 200");
$stmt->execute($params);
$items = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>เลือกสื่อ</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
* { box-sizing: border-box; }
body { font-family: 'Sarabun', sans-serif; font-size: 13px; background: #f5f5f5; margin: 0; }
.picker-head {
    background: linear-gradient(135deg, #E8869B, #8A6DB0);
    color: #fff; padding: 10px 16px;
    display: flex; align-items: center; gap: 10px;
    position: sticky; top: 0; z-index: 10;
}
.picker-head h5 { margin: 0; font-size: 0.95rem; flex: 1; }
.search-bar { padding: 10px 16px; background: #fff; border-bottom: 1px solid #eee; display: flex; gap: 8px; }
.search-bar input { flex: 1; border: 1px solid #ddd; border-radius: 8px; padding: 6px 12px; font-family: inherit; font-size: 0.82rem; }
.search-bar select { border: 1px solid #ddd; border-radius: 8px; padding: 6px 10px; font-family: inherit; font-size: 0.82rem; }
.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 8px; padding: 12px; }
.media-item {
    background: #fff; border-radius: 8px; overflow: hidden;
    border: 2px solid transparent; cursor: pointer;
    transition: all 0.15s; position: relative;
}
.media-item:hover { border-color: #E8869B; transform: translateY(-1px); }
.media-item.selected { border-color: #8A6DB0; }
.media-item.selected::after {
    content: '✓'; position: absolute; top: 4px; right: 4px;
    background: #8A6DB0; color: #fff; border-radius: 50%;
    width: 20px; height: 20px; display: flex; align-items: center;
    justify-content: center; font-size: 0.7rem; font-weight: 700;
}
.media-thumb { width: 100%; aspect-ratio: 1; object-fit: cover; display: block; }
.video-thumb {
    width: 100%; aspect-ratio: 1; background: #1a1a2e;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 1.5rem;
}
.media-name { padding: 4px 6px; font-size: 0.68rem; color: #666; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.media-type-badge {
    position: absolute; top: 4px; left: 4px;
    background: rgba(0,0,0,0.6); color: #fff;
    border-radius: 4px; padding: 1px 5px; font-size: 0.62rem;
}
.picker-footer {
    position: sticky; bottom: 0; background: #fff;
    border-top: 1px solid #eee; padding: 10px 16px;
    display: flex; justify-content: space-between; align-items: center;
    box-shadow: 0 -2px 8px rgba(0,0,0,0.08);
}
.btn-confirm {
    background: linear-gradient(135deg, #E8869B, #8A6DB0);
    color: #fff; border: none; border-radius: 8px;
    padding: 8px 24px; font-family: inherit; font-weight: 600;
    font-size: 0.88rem; cursor: pointer;
}
.btn-confirm:disabled { opacity: 0.5; cursor: not-allowed; }
.selected-count { font-size: 0.82rem; color: #8A6DB0; font-weight: 600; }
.empty-state { text-align: center; padding: 40px; color: #aaa; }
</style>
</head>
<body>

<div class="picker-head">
    <h5>🖼️ เลือกสื่อ<?= $multi ? ' (เลือกได้หลายไฟล์)' : '' ?></h5>
    <button onclick="window.close()" style="background:none;border:none;color:#fff;font-size:1.2rem;cursor:pointer;">✕</button>
</div>

<div class="search-bar">
    <input type="text" id="searchInput" placeholder="🔍 ค้นหาชื่อ, แท็ก..." value="<?= htmlspecialchars($search) ?>" oninput="filterItems(this.value)">
    <select id="typeFilter" onchange="filterByType(this.value)">
        <option value="">ทุกประเภท</option>
        <option value="image" <?= $typeFilter==='image'?'selected':'' ?>>🖼️ รูปภาพ</option>
        <option value="video" <?= $typeFilter==='video'?'selected':'' ?>>🎬 วิดีโอ</option>
    </select>
</div>

<div class="grid" id="mediaGrid">
<?php if (empty($items)): ?>
    <div class="empty-state" style="grid-column:1/-1;">
        <i class="fas fa-photo-film fa-2x mb-2 d-block"></i>
        ยังไม่มีสื่อในคลัง<br>
        <a href="<?= SITE_URL ?>/pages/media.php" target="_blank" style="color:#E8869B;">อัปโหลดสื่อก่อน</a>
    </div>
<?php endif; ?>
<?php foreach ($items as $m):
    $isVideo = $m['file_type'] === 'video';
    $url = SITE_URL . '/' . ltrim($m['file_path'] ?? 'assets/uploads/media/'.$m['filename'], '/');
    $thumb = $m['thumbnail_path'] ? SITE_URL . '/' . ltrim($m['thumbnail_path'], '/') : null;
    $dataJson = htmlspecialchars(json_encode([
        'id'            => $m['id'],
        'url'           => $url,
        'thumbnail_url' => $thumb ?? ($isVideo ? null : $url),
        'original_name' => $m['original_name'],
        'title'         => $m['title'],
        'file_type'     => $m['file_type'],
        'file_size'     => $m['file_size'],
    ], JSON_UNESCAPED_UNICODE));
?>
<div class="media-item" data-id="<?= $m['id'] ?>" data-type="<?= $m['file_type'] ?>"
     data-title="<?= htmlspecialchars(strtolower($m['title'].$m['original_name'].$m['tags'])) ?>"
     data-item='<?= $dataJson ?>'
     onclick="toggleSelect(this)">
    <?php if ($isVideo): ?>
    <div class="video-thumb">
        <?php if ($thumb): ?>
        <img src="<?= $thumb ?>" style="width:100%;height:100%;object-fit:cover;position:absolute;top:0;left:0;">
        <?php endif; ?>
        <i class="fas fa-play-circle" style="position:relative;z-index:1;opacity:0.9;"></i>
    </div>
    <?php else: ?>
    <img src="<?= $url ?>" class="media-thumb" loading="lazy" alt="<?= htmlspecialchars($m['title'] ?? '') ?>">
    <?php endif; ?>
    <div class="media-type-badge"><?= $isVideo ? '🎬' : '🖼️' ?></div>
    <div class="media-name"><?= htmlspecialchars($m['title'] ?: $m['original_name']) ?></div>
</div>
<?php endforeach; ?>
</div>

<div class="picker-footer">
    <span class="selected-count" id="selCount">ยังไม่ได้เลือก</span>
    <div class="d-flex gap-2">
        <button class="btn btn-sm btn-outline-secondary" onclick="clearSelection()">ล้าง</button>
        <button class="btn-confirm" id="btnConfirm" disabled onclick="confirmSelection()">
            ✅ ยืนยันการเลือก
        </button>
    </div>
</div>

<script>
const MULTI = <?= $multi ? 'true' : 'false' ?>;
let selected = [];

function toggleSelect(el) {
    const id   = el.dataset.id;
    const item = JSON.parse(el.dataset.item);
    if (el.classList.contains('selected')) {
        el.classList.remove('selected');
        selected = selected.filter(i => i.id != id);
    } else {
        if (!MULTI) {
            document.querySelectorAll('.media-item.selected').forEach(e => e.classList.remove('selected'));
            selected = [];
        }
        el.classList.add('selected');
        selected.push(item);
    }
    updateFooter();
}

function updateFooter() {
    const btn = document.getElementById('btnConfirm');
    const cnt = document.getElementById('selCount');
    btn.disabled = selected.length === 0;
    cnt.textContent = selected.length > 0 ? `เลือกแล้ว ${selected.length} ไฟล์` : 'ยังไม่ได้เลือก';
}

function clearSelection() {
    document.querySelectorAll('.media-item.selected').forEach(e => e.classList.remove('selected'));
    selected = [];
    updateFooter();
}

function confirmSelection() {
    if (!selected.length) return;
    // Send back to opener
    if (window.opener && window.opener._mediaPickerCallback) {
        window.opener._mediaPickerCallback(selected);
    } else {
        window.opener?.postMessage({ type: 'MEDIA_SELECTED', items: selected }, '*');
    }
    window.close();
}

function filterItems(q) {
    q = q.toLowerCase();
    const typeVal = document.getElementById('typeFilter').value;
    document.querySelectorAll('.media-item').forEach(el => {
        const titleMatch = el.dataset.title.includes(q);
        const typeMatch  = !typeVal || el.dataset.type === typeVal;
        el.style.display = (titleMatch && typeMatch) ? '' : 'none';
    });
}

function filterByType(val) {
    filterItems(document.getElementById('searchInput').value);
}

// Also listen for postMessage from parent (if needed)
window.addEventListener('message', e => {
    if (e.data?.type === 'CLOSE_PICKER') window.close();
});
</script>
</body>
</html>
