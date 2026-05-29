<?php
require_once __DIR__ . '/../config/database.php';
requireLogin();
$pdo = getDB();
// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_promo'])) {
    $id         = (int)($_POST['promo_id'] ?? 0);
    $name       = trim($_POST['name']);
    $code       = strtoupper(trim($_POST['code'] ?? ''));
    $type       = $_POST['type'];
    $value      = (float)($_POST['discount_value'] ?? 0);
    $minPurch   = (float)($_POST['min_purchase'] ?? 0);
    $maxDisc    = (float)($_POST['max_discount'] ?? 0);
    $usageLimit = (int)($_POST['usage_limit'] ?? 0);
    $startDate  = $_POST['start_date'] ?: null;
    $endDate    = $_POST['end_date'] ?: null;
    $desc       = trim($_POST['description'] ?? '');
    $status     = $_POST['status'] ?? 'active';
    $pfIds      = implode(',', $_POST['platform_ids'] ?? []);

    if ($id) {
        $pdo->prepare("UPDATE promotions SET name=?,code=?,type=?,discount_value=?,min_purchase=?,max_discount=?,usage_limit=?,start_date=?,end_date=?,description=?,platform_ids=?,status=? WHERE id=?")
            ->execute([$name,$code,$type,$value,$minPurch,$maxDisc,$usageLimit,$startDate,$endDate,$desc,$pfIds,$status,$id]);
    } else {
        $pdo->prepare("INSERT INTO promotions (name,code,type,discount_value,min_purchase,max_discount,usage_limit,start_date,end_date,description,platform_ids,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$name,$code ?: null,$type,$value,$minPurch,$maxDisc,$usageLimit,$startDate,$endDate,$desc,$pfIds,$status]);
    }
    header('Location: ' . SITE_URL . '/pages/promotions.php?msg=saved');
    exit;
}

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $pdo->prepare("DELETE FROM promotions WHERE id=?")->execute([$_GET['delete']]);
    header('Location: ' . SITE_URL . '/pages/promotions.php?msg=deleted');
    exit;
}

$promos = $pdo->query("SELECT * FROM promotions ORDER BY created_at DESC")->fetchAll();
$platforms = $pdo->query("SELECT * FROM platforms WHERE is_active=1")->fetchAll();

$typeLabels = ['percent'=>'ลด %','fixed'=>'ลดจำนวนเงิน','free_shipping'=>'ส่งฟรี','bundle'=>'ซื้อ X แถม Y','flash_sale'=>'⚡ Flash Sale'];

$pageTitle = 'โปรโมชั่น';
require_once __DIR__ . '/../includes/header.php';

?>

<div class="container-fluid fade-in">
    <div class="page-header">
        <div>
            <h1 class="page-title">🏷️ โปรโมชั่น</h1>
            <p class="page-subtitle">จัดการส่วนลด โปรโมชั่น และ Flash Sale</p>
        </div>
        <button class="btn btn-primary" onclick="openPromoModal()" data-bs-toggle="modal" data-bs-target="#promoModal">
            <i class="fas fa-plus me-1"></i> สร้างโปรโมชั่น
        </button>
    </div>

    <?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success alert-auto">✅ <?= $_GET['msg']==='saved'?'บันทึกโปรโมชั่นเรียบร้อย':'ลบโปรโมชั่นเรียบร้อย' ?></div>
    <?php endif; ?>

    <!-- Active Promos -->
    <?php $active = array_filter($promos, fn($p) => $p['status']==='active'); ?>
    <?php if ($active): ?>
    <h5 class="mb-3">✅ โปรโมชั่นที่ใช้งานอยู่</h5>
    <div class="row g-3 mb-4">
        <?php foreach ($active as $promo): ?>
        <div class="col-md-6 col-lg-4">
            <div class="promo-card">
                <span class="promo-badge"><?= $typeLabels[$promo['type']] ?? $promo['type'] ?></span>
                <div class="promo-name"><?= htmlspecialchars($promo['name']) ?></div>
                <?php if ($promo['code']): ?>
                <div style="font-size:0.85rem;color:var(--pink-dark);margin:4px 0;">
                    <i class="fas fa-ticket-alt"></i> โค้ด: <strong><?= htmlspecialchars($promo['code']) ?></strong>
                </div>
                <?php endif; ?>
                <div class="promo-value">
                    <?php if ($promo['type']==='percent'): ?>
                    ลด <?= $promo['discount_value'] ?>%
                    <?php elseif ($promo['type']==='fixed'): ?>
                    ลด ฿<?= number_format($promo['discount_value']) ?>
                    <?php elseif ($promo['type']==='free_shipping'): ?>
                    🚚 ส่งฟรี
                    <?php elseif ($promo['type']==='flash_sale'): ?>
                    ⚡ ลด <?= $promo['discount_value'] ?>%
                    <?php else: ?>
                    Bundle Deal
                    <?php endif; ?>
                </div>
                <?php if ($promo['min_purchase'] > 0): ?>
                <div class="promo-dates">ซื้อขั้นต่ำ ฿<?= number_format($promo['min_purchase']) ?></div>
                <?php endif; ?>
                <div class="promo-dates">
                    <?php if ($promo['start_date']): ?>📅 <?= formatDateTH($promo['start_date']) ?> – <?= $promo['end_date'] ? formatDateTH($promo['end_date']) : 'ไม่กำหนด' ?><?php endif; ?>
                    <?php if ($promo['usage_limit'] > 0): ?>· ใช้ได้ <?= $promo['usage_limit'] - $promo['usage_count'] ?>/<?= $promo['usage_limit'] ?> ครั้ง<?php endif; ?>
                </div>
                <div class="mt-2 d-flex gap-2">
                    <button class="btn btn-sm btn-outline-pink" onclick='openPromoModal(<?= htmlspecialchars(json_encode($promo)) ?>)' data-bs-toggle="modal" data-bs-target="#promoModal">แก้ไข</button>
                    <a href="?delete=<?= $promo['id'] ?>" class="btn btn-sm btn-outline-danger btn-delete-confirm">ลบ</a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- All Promos Table -->
    <div class="card">
        <div class="card-header"><span class="card-title">รายการโปรโมชั่นทั้งหมด</span></div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>ชื่อโปรโมชั่น</th><th>โค้ด</th><th>ประเภท</th><th>ส่วนลด</th><th>ช่วงเวลา</th><th>การใช้งาน</th><th>สถานะ</th><th>จัดการ</th></tr></thead>
                <tbody>
                    <?php foreach ($promos as $promo): ?>
                    <tr>
                        <td><div style="font-weight:600;"><?= htmlspecialchars($promo['name']) ?></div>
                            <?php if ($promo['description']): ?><div style="font-size:0.75rem;color:var(--text-muted);"><?= htmlspecialchars(substr($promo['description'],0,50)) ?></div><?php endif; ?>
                        </td>
                        <td><?= $promo['code'] ? '<code>'.htmlspecialchars($promo['code']).'</code>' : '-' ?></td>
                        <td><span class="badge bg-light text-dark"><?= $typeLabels[$promo['type']] ?? $promo['type'] ?></span></td>
                        <td style="font-weight:600;color:var(--pink-dark);">
                            <?= $promo['type']==='percent'||$promo['type']==='flash_sale' ? $promo['discount_value'].'%' : ($promo['type']==='fixed' ? '฿'.number_format($promo['discount_value']) : '–') ?>
                        </td>
                        <td style="font-size:0.78rem;">
                            <?= $promo['start_date'] ? formatDateTH($promo['start_date']) : '–' ?>
                            <?= $promo['end_date'] ? ' → '.formatDateTH($promo['end_date']) : '' ?>
                        </td>
                        <td><?= $promo['usage_count'] ?><?= $promo['usage_limit'] ? '/'.$promo['usage_limit'] : '' ?></td>
                        <td><span class="badge-status badge-<?= $promo['status']==='active'?'active':($promo['status']==='expired'?'out':'inactive') ?>"><?= ['active'=>'ใช้งาน','inactive'=>'หยุด','expired'=>'หมดอายุ'][$promo['status']] ?></span></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary me-1" onclick='openPromoModal(<?= htmlspecialchars(json_encode($promo)) ?>)' data-bs-toggle="modal" data-bs-target="#promoModal">แก้ไข</button>
                            <a href="?delete=<?= $promo['id'] ?>" class="btn btn-sm btn-outline-danger btn-delete-confirm">ลบ</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($promos)): ?>
                    <tr><td colspan="8" class="text-center py-4 text-muted">ยังไม่มีโปรโมชั่น</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Promo Modal -->
<div class="modal fade" id="promoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST">
            <input type="hidden" name="save_promo" value="1">
            <input type="hidden" name="promo_id" id="promoId" value="0">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title" id="promoModalTitle">➕ สร้างโปรโมชั่น</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">ชื่อโปรโมชั่น <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="promoName" class="form-control" required placeholder="เช่น โปรลดราคาสงกรานต์">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">รหัสส่วนลด (Coupon Code)</label>
                            <input type="text" name="code" id="promoCode" class="form-control" placeholder="BABY10 (ว่างได้)">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">ประเภทโปรโมชั่น</label>
                            <select name="type" id="promoType" class="form-select" onchange="updatePromoType()">
                                <option value="percent">ลด %</option>
                                <option value="fixed">ลดจำนวนเงิน</option>
                                <option value="free_shipping">จัดส่งฟรี</option>
                                <option value="bundle">ซื้อ X แถม Y</option>
                                <option value="flash_sale">⚡ Flash Sale</option>
                            </select>
                        </div>
                        <div class="col-md-4" id="discValueGroup">
                            <label class="form-label">มูลค่าส่วนลด</label>
                            <div class="input-group">
                                <input type="number" name="discount_value" id="promoValue" class="form-control" value="0" min="0" step="0.01">
                                <span class="input-group-text" id="discUnit">%</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">ซื้อขั้นต่ำ (฿)</label>
                            <input type="number" name="min_purchase" id="promoMin" class="form-control" value="0" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">ลดสูงสุด (฿) (0=ไม่จำกัด)</label>
                            <input type="number" name="max_discount" id="promoMax" class="form-control" value="0" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">จำกัดการใช้ (0=ไม่จำกัด)</label>
                            <input type="number" name="usage_limit" id="promoLimit" class="form-control" value="0" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">วันเริ่ม</label>
                            <input type="datetime-local" name="start_date" id="promoStart" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">วันสิ้นสุด</label>
                            <input type="datetime-local" name="end_date" id="promoEnd" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">แพลตฟอร์มที่ใช้</label>
                            <div class="d-flex gap-3 flex-wrap">
                                <?php foreach ($platforms as $pf): ?>
                                <label class="form-check-label d-flex align-items-center gap-1">
                                    <input type="checkbox" name="platform_ids[]" value="<?= $pf['id'] ?>" class="form-check-input promo-pf">
                                    <?= $pf['icon'] ?> <?= htmlspecialchars($pf['name']) ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">รายละเอียด</label>
                            <textarea name="description" id="promoDesc" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">สถานะ</label>
                            <select name="status" id="promoStatus" class="form-select">
                                <option value="active">✅ ใช้งาน</option>
                                <option value="inactive">⏸️ หยุดใช้</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">✅ บันทึก</button>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                </div>
            </div>
        </form>
    </div>
</div>
<script>
function updatePromoType() {
    const type = document.getElementById('promoType').value;
    const unitEl = document.getElementById('discUnit');
    const grp = document.getElementById('discValueGroup');
    unitEl.textContent = type === 'fixed' ? '฿' : '%';
    grp.style.display = type === 'free_shipping' ? 'none' : '';
}
function openPromoModal(p) {
    if (p) {
        document.getElementById('promoModalTitle').textContent = '✏️ แก้ไขโปรโมชั่น';
        document.getElementById('promoId').value = p.id;
        document.getElementById('promoName').value = p.name;
        document.getElementById('promoCode').value = p.code || '';
        document.getElementById('promoType').value = p.type;
        document.getElementById('promoValue').value = p.discount_value;
        document.getElementById('promoMin').value = p.min_purchase;
        document.getElementById('promoMax').value = p.max_discount;
        document.getElementById('promoLimit').value = p.usage_limit;
        document.getElementById('promoStart').value = p.start_date ? p.start_date.replace(' ','T') : '';
        document.getElementById('promoEnd').value = p.end_date ? p.end_date.replace(' ','T') : '';
        document.getElementById('promoDesc').value = p.description || '';
        document.getElementById('promoStatus').value = p.status;
        const pfIds = (p.platform_ids || '').split(',');
        document.querySelectorAll('.promo-pf').forEach(cb => { cb.checked = pfIds.includes(cb.value); });
        updatePromoType();
    } else {
        document.getElementById('promoModalTitle').textContent = '➕ สร้างโปรโมชั่น';
        document.getElementById('promoId').value = '0';
        document.querySelectorAll('.promo-pf').forEach(cb => cb.checked = false);
    }
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
