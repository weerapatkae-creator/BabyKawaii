<?php
require_once __DIR__ . '/../config/database.php';
requireLogin();
$pdo = getDB();
// Handle upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_media'])) {
    if (!empty($_FILES['media_files']['name'][0])) {
        $allowedImg  = ['jpg','jpeg','png','gif','webp'];
        $allowedVid  = ['mp4','mov','avi','webm'];
        $uploaded = 0;
        foreach ($_FILES['media_files']['name'] as $i => $fname) {
            if ($_FILES['media_files']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
            $type = in_array($ext, $allowedImg) ? 'image' : (in_array($ext, $allowedVid) ? 'video' : null);
            if (!$type) continue;
            $newName = 'media_' . time() . '_' . $i . '.' . $ext;
            $dest = UPLOAD_PATH . 'media/' . $newName;
            if (move_uploaded_file($_FILES['media_files']['tmp_name'][$i], $dest)) {
                $pdo->prepare("INSERT INTO media (filename, original_name, file_type, mime_type, file_size, file_path, title, tags, platform_ids, product_id)
                    VALUES (?,?,?,?,?,?,?,?,?,?)")->execute([
                    $newName, $fname, $type, $_FILES['media_files']['type'][$i],
                    $_FILES['media_files']['size'][$i],
                    'assets/uploads/media/' . $newName,
                    trim($_POST['media_title'] ?? '') ?: pathinfo($fname, PATHINFO_FILENAME),
                    trim($_POST['media_tags'] ?? ''),
                    trim($_POST['platform_ids'] ?? ''),
                    ($_POST['product_id'] ?? '') ?: null
                ]);
                $uploaded++;
            }
        }
        header('Location: ' . SITE_URL . '/pages/media.php?msg=' . $uploaded);
        exit;
    }
}

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $row = $pdo->prepare("SELECT * FROM media WHERE id=?");
    $row->execute([$_GET['delete']]);
    $row = $row->fetch();
    if ($row) {
        @unlink(UPLOAD_PATH . 'media/' . $row['filename']);
        $pdo->prepare("DELETE FROM media WHERE id=?")->execute([$_GET['delete']]);
    }
    header('Location: ' . SITE_URL . '/pages/media.php?msg=deleted');
    exit;
}


$pageTitle = 'คลังสื่อ';
require_once __DIR__ . '/../includes/header.php';

// Filters
$typeFilter = $_GET['type'] ?? '';
$search     = trim($_GET['q'] ?? '');
$pfFilter   = $_GET['platform'] ?? '';

$where = ['1=1'];
$params = [];
if ($typeFilter) { $where[] = 'm.file_type = ?'; $params[] = $typeFilter; }
if ($search)     { $where[] = '(m.title LIKE ? OR m.tags LIKE ? OR m.original_name LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($pfFilter)   { $where[] = "FIND_IN_SET(?, REPLACE(REPLACE(m.platform_ids,' ',''),',',','))"; $params[] = $pfFilter; }

$stmt = $pdo->prepare("SELECT m.*, p.name as product_name FROM media m LEFT JOIN products p ON p.id = m.product_id WHERE ".implode(' AND ',$where)." ORDER BY m.created_at DESC");
$stmt->execute($params);
$mediaList = $stmt->fetchAll();

$platforms = $pdo->query("SELECT * FROM platforms WHERE is_active=1")->fetchAll();
$products  = $pdo->query("SELECT id, name FROM products WHERE status='active' ORDER BY name")->fetchAll();

// Stats
$imgCount = $pdo->query("SELECT COUNT(*) FROM media WHERE file_type='image'")->fetchColumn();
$vidCount = $pdo->query("SELECT COUNT(*) FROM media WHERE file_type='video'")->fetchColumn();
?>

<div class="container-fluid fade-in">
    <div class="page-header">
        <div>
            <h1 class="page-title">🖼️ คลังสื่อ</h1>
            <p class="page-subtitle">รูปภาพและวิดีโอสำหรับโปรโมทสินค้า</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
            <i class="fas fa-upload me-1"></i> อัปโหลดสื่อ
        </button>
    </div>

    <?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success alert-auto">✅ <?= is_numeric($_GET['msg']) ? 'อัปโหลดสำเร็จ '.$_GET['msg'].' ไฟล์' : 'ลบไฟล์เรียบร้อย' ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card pink">
                <div class="stat-label">รูปภาพทั้งหมด</div>
                <div class="stat-value"><?= number_format($imgCount) ?></div>
                <i class="fas fa-image stat-icon"></i>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card purple">
                <div class="stat-label">วิดีโอทั้งหมด</div>
                <div class="stat-value"><?= number_format($vidCount) ?></div>
                <i class="fas fa-video stat-icon"></i>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card mint">
                <div class="stat-label">ไฟล์ทั้งหมด</div>
                <div class="stat-value"><?= number_format($imgCount + $vidCount) ?></div>
                <i class="fas fa-photo-film stat-icon"></i>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body py-3">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <input type="text" name="q" class="form-control form-control-sm" placeholder="🔍 ค้นหาชื่อ, แท็ก..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2">
                    <select name="type" class="form-select form-select-sm">
                        <option value="">ทุกประเภท</option>
                        <option value="image" <?= $typeFilter==='image'?'selected':'' ?>>🖼️ รูปภาพ</option>
                        <option value="video" <?= $typeFilter==='video'?'selected':'' ?>>🎬 วิดีโอ</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm">กรอง</button>
                    <a href="?" class="btn btn-outline-secondary btn-sm">ล้าง</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Media Grid -->
    <?php if (empty($mediaList)): ?>
    <div class="text-center py-5">
        <div style="font-size:4rem;">📁</div>
        <h4 class="text-muted mt-3">ยังไม่มีสื่อ</h4>
        <p class="text-muted">เริ่มอัปโหลดรูปภาพหรือวิดีโอสำหรับโปรโมทสินค้า</p>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">อัปโหลดเลย</button>
    </div>
    <?php else: ?>
    <div class="media-grid">
        <?php foreach ($mediaList as $m): ?>
        <div class="media-item">
            <?php if ($m['file_type'] === 'image'): ?>
            <img src="<?= SITE_URL ?>/assets/uploads/media/<?= htmlspecialchars($m['filename']) ?>" alt="<?= htmlspecialchars($m['title']) ?>" loading="lazy">
            <?php else: ?>
            <div style="width:100%;height:130px;background:#1a1a2e;display:flex;align-items:center;justify-content:center;font-size:2.5rem;">🎬</div>
            <?php endif; ?>
            <div class="media-overlay">
                <a href="<?= SITE_URL ?>/assets/uploads/media/<?= htmlspecialchars($m['filename']) ?>" target="_blank" class="btn btn-sm btn-light">👁️</a>
                <a href="?delete=<?= $m['id'] ?>" class="btn btn-sm btn-danger btn-delete-confirm">🗑️</a>
            </div>
            <div class="media-info">
                <div class="media-name"><?= htmlspecialchars($m['title'] ?: $m['original_name']) ?></div>
                <div class="media-type">
                    <?= $m['file_type']==='image'?'🖼️':'🎬' ?>
                    <?php if ($m['tags']): ?> · <?= htmlspecialchars(implode(', ', array_slice(explode(',', $m['tags']), 0, 2))) ?><?php endif; ?>
                </div>
                <?php if ($m['product_name']): ?>
                <div style="font-size:0.68rem;color:var(--pink-dark);">👕 <?= htmlspecialchars($m['product_name']) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="upload_media" value="1">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">📤 อัปโหลดสื่อ</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <!-- Drag drop zone -->
                    <div class="upload-zone mb-4" data-input="mediaFilesInput">
                        <div class="upload-icon">📁</div>
                        <p><strong>คลิกหรือลากไฟล์มาวาง</strong></p>
                        <p>รองรับ JPG, PNG, WEBP, GIF, MP4, MOV (สูงสุด 50MB)</p>
                        <div id="uploadPreview" class="d-flex flex-wrap gap-2 mt-3"></div>
                    </div>
                    <input type="file" id="mediaFilesInput" name="media_files[]" multiple accept="image/*,video/*" class="d-none">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">ชื่อ / หัวข้อ</label>
                            <input type="text" name="media_title" class="form-control" placeholder="เช่น โปรโมทบอดี้สูทลายดาว">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">แท็ก (คั่นด้วย ,)</label>
                            <input type="text" name="media_tags" class="form-control" placeholder="โปรโมท, บอดี้สูท, แรกเกิด">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">สินค้าที่เกี่ยวข้อง</label>
                            <select name="product_id" class="form-select">
                                <option value="">ไม่ระบุ</option>
                                <?php foreach ($products as $pr): ?><option value="<?= $pr['id'] ?>"><?= htmlspecialchars($pr['name']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">แพลตฟอร์มที่ใช้</label>
                            <div class="d-flex gap-2 flex-wrap mt-1">
                                <?php foreach ($platforms as $pf): ?>
                                <label class="form-check-label d-flex align-items-center gap-1" style="cursor:pointer;">
                                    <input type="checkbox" name="platform_ids[]" value="<?= $pf['id'] ?>" class="form-check-input">
                                    <?= $pf['icon'] ?> <?= htmlspecialchars($pf['name']) ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">📤 อัปโหลด</button>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('mediaFilesInput').addEventListener('change', function () {
    const preview = document.getElementById('uploadPreview');
    preview.innerHTML = '';
    Array.from(this.files).forEach(file => {
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = e => {
                const img = document.createElement('img');
                img.src = e.target.result;
                img.style.cssText = 'width:80px;height:80px;object-fit:cover;border-radius:8px;border:2px solid var(--pink);';
                preview.appendChild(img);
            };
            reader.readAsDataURL(file);
        } else {
            const div = document.createElement('div');
            div.style.cssText = 'width:80px;height:80px;background:#1a1a2e;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;';
            div.textContent = '🎬';
            preview.appendChild(div);
        }
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
