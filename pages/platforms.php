<?php
require_once __DIR__ . '/../config/database.php';
requireLogin();
$pdo = getDB();
// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_platform'])) {
    $id         = (int)($_POST['platform_id'] ?? 0);
    $name       = trim($_POST['name']);
    $icon       = trim($_POST['icon'] ?? '🛒');
    $color      = trim($_POST['color'] ?? '#666');
    $url        = trim($_POST['page_url'] ?? '');
    $username   = trim($_POST['username'] ?? '');
    $followers  = (int)($_POST['followers'] ?? 0);
    $commission = (float)($_POST['commission_rate'] ?? 0);
    $notes      = trim($_POST['notes'] ?? '');
    $isActive   = isset($_POST['is_active']) ? 1 : 0;

    if ($id) {
        $pdo->prepare("UPDATE platforms SET name=?,icon=?,color=?,page_url=?,username=?,followers=?,commission_rate=?,notes=?,is_active=? WHERE id=?")->execute([$name,$icon,$color,$url,$username,$followers,$commission,$notes,$isActive,$id]);
    } else {
        $slug = strtolower(preg_replace('/[^a-z0-9]/', '-', $name));
        $pdo->prepare("INSERT INTO platforms (name,slug,icon,color,page_url,username,followers,commission_rate,notes,is_active) VALUES (?,?,?,?,?,?,?,?,?,?)")->execute([$name,$slug,$icon,$color,$url,$username,$followers,$commission,$notes,$isActive]);
    }
    header('Location: ' . SITE_URL . '/pages/platforms.php?msg=saved');
    exit;
}

$platforms = $pdo->query("SELECT * FROM platforms ORDER BY id")->fetchAll();


$pageTitle = 'แพลตฟอร์มขาย';
require_once __DIR__ . '/../includes/header.php';

// Sales per platform this month
$pfSales = $pdo->query("SELECT platform_id, SUM(total_amount) as revenue, COUNT(*) as orders
    FROM orders WHERE MONTH(order_date)=MONTH(NOW()) AND YEAR(order_date)=YEAR(NOW()) AND order_status NOT IN ('cancelled')
    GROUP BY platform_id")->fetchAll(PDO::FETCH_KEY_PAIR);
// Re-fetch as assoc
$pfSalesStmt = $pdo->query("SELECT platform_id, SUM(total_amount) as revenue, COUNT(*) as orders
    FROM orders WHERE MONTH(order_date)=MONTH(NOW()) AND YEAR(order_date)=YEAR(NOW()) AND order_status NOT IN ('cancelled')
    GROUP BY platform_id");
$pfSalesData = [];
while ($row = $pfSalesStmt->fetch()) {
    $pfSalesData[$row['platform_id']] = $row;
}
?>

<div class="container-fluid fade-in">
    <div class="page-header">
        <div>
            <h1 class="page-title">🔗 แพลตฟอร์มขาย</h1>
            <p class="page-subtitle">จัดการแพลตฟอร์มและดูยอดขายแต่ละช่องทาง</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#pfModal" onclick="openPfModal()">
            <i class="fas fa-plus me-1"></i> เพิ่มแพลตฟอร์ม
        </button>
    </div>

    <?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success alert-auto">✅ บันทึกเรียบร้อย</div>
    <?php endif; ?>

    <!-- Platform Cards -->
    <div class="row g-4 mb-4">
        <?php foreach ($platforms as $pf):
            $sales = $pfSalesData[$pf['id']] ?? ['revenue' => 0, 'orders' => 0];
            $gradientClass = "style=\"background:linear-gradient(135deg,{$pf['color']},darken)\"";
            $bg = $pf['color'];
        ?>
        <div class="col-md-6 col-lg-4">
            <div class="platform-card" style="background:linear-gradient(135deg,<?= htmlspecialchars($bg) ?>,<?= htmlspecialchars($bg) ?>99);">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="pf-icon"><?= $pf['icon'] ?></div>
                        <div class="pf-name"><?= htmlspecialchars($pf['name']) ?></div>
                        <?php if ($pf['username']): ?>
                        <div style="font-size:0.8rem;opacity:0.8;">@<?= htmlspecialchars($pf['username']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex gap-2">
                        <?php if ($pf['page_url']): ?>
                        <a href="<?= htmlspecialchars($pf['page_url']) ?>" target="_blank" class="btn btn-sm btn-light btn-light" style="opacity:0.9;">
                            <i class="fas fa-external-link-alt"></i>
                        </a>
                        <?php endif; ?>
                        <button class="btn btn-sm btn-light" style="opacity:0.9;" onclick="openPfModal(<?= htmlspecialchars(json_encode($pf)) ?>)">
                            <i class="fas fa-edit"></i>
                        </button>
                    </div>
                </div>
                <div class="row g-2 text-center">
                    <div class="col-4">
                        <div class="pf-stat"><?= number_format($sales['revenue']) ?></div>
                        <div class="pf-label">฿ยอดขายเดือนนี้</div>
                    </div>
                    <div class="col-4">
                        <div class="pf-stat"><?= number_format($sales['orders']) ?></div>
                        <div class="pf-label">ออเดอร์</div>
                    </div>
                    <div class="col-4">
                        <div class="pf-stat"><?= number_format($pf['followers']) ?></div>
                        <div class="pf-label">ผู้ติดตาม</div>
                    </div>
                </div>
                <?php if ($pf['commission_rate'] > 0): ?>
                <div class="mt-2" style="font-size:0.75rem;opacity:0.8;">
                    <i class="fas fa-percent"></i> ค่าคอม <?= $pf['commission_rate'] ?>%
                </div>
                <?php endif; ?>
                <div class="mt-1">
                    <span style="background:rgba(255,255,255,0.2);padding:2px 8px;border-radius:12px;font-size:0.75rem;">
                        <?= $pf['is_active'] ? '✅ ใช้งาน' : '⏸️ หยุดใช้' ?>
                    </span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Tips for each platform -->
    <div class="row g-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header"><span class="card-title">💡 เคล็ดลับการขายแต่ละแพลตฟอร์ม</span></div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-6 col-lg-3">
                            <div class="text-center p-3" style="background:var(--blue-light);border-radius:12px;">
                                <div style="font-size:2rem;">📘</div>
                                <h6 class="mt-2 fw-bold" style="color:#1877F2;">Facebook Page</h6>
                                <ul class="text-start text-muted list-unstyled" style="font-size:0.82rem;">
                                    <li>✅ โพสต์รูปสวย ใส่ราคา</li>
                                    <li>✅ ทำ Reels สั้น 15-30 วิ</li>
                                    <li>✅ Live สดทุกอาทิตย์</li>
                                    <li>✅ โปรโมทด้วยโฆษณา</li>
                                    <li>✅ ตอบ Comment เร็ว</li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <div class="text-center p-3" style="background:#f5f5f5;border-radius:12px;">
                                <div style="font-size:2rem;">🎵</div>
                                <h6 class="mt-2 fw-bold">TikTok Shop</h6>
                                <ul class="text-start text-muted list-unstyled" style="font-size:0.82rem;">
                                    <li>✅ วิดีโอสนุก ใส่เพลงเทรนด์</li>
                                    <li>✅ Unboxing / รีวิว</li>
                                    <li>✅ ทำ Hashtag Challenge</li>
                                    <li>✅ ร่วม TikTok Affiliate</li>
                                    <li>✅ Live ขายสด</li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <div class="text-center p-3" style="background:linear-gradient(135deg,#FFF0F5,#FFF0F0);border-radius:12px;">
                                <div style="font-size:2rem;">📸</div>
                                <h6 class="mt-2 fw-bold" style="color:#E4405F;">Instagram</h6>
                                <ul class="text-start text-muted list-unstyled" style="font-size:0.82rem;">
                                    <li>✅ รูปภาพสวยงาม สม่ำเสมอ</li>
                                    <li>✅ Reel สั้น น่ารัก</li>
                                    <li>✅ Stories ทุกวัน</li>
                                    <li>✅ Hashtag เด็กๆ</li>
                                    <li>✅ Collab กับแม่บล็อก</li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <div class="text-center p-3" style="background:#f0fff4;border-radius:12px;">
                                <div style="font-size:2rem;">💬</div>
                                <h6 class="mt-2 fw-bold" style="color:#00B900;">Line OA</h6>
                                <ul class="text-start text-muted list-unstyled" style="font-size:0.82rem;">
                                    <li>✅ Broadcast โปรโมชั่น</li>
                                    <li>✅ Rich Menu สวยงาม</li>
                                    <li>✅ ตอบแชทรวดเร็ว</li>
                                    <li>✅ ทำ Line Shopping</li>
                                    <li>✅ Coupon สำหรับ member</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Platform Modal -->
<div class="modal fade" id="pfModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST">
            <input type="hidden" name="save_platform" value="1">
            <input type="hidden" name="platform_id" id="pfId" value="0">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title" id="pfModalTitle">➕ เพิ่มแพลตฟอร์ม</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">ชื่อแพลตฟอร์ม</label>
                            <input type="text" name="name" id="pfName" class="form-control" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">ไอคอน</label>
                            <input type="text" name="icon" id="pfIcon" class="form-control" value="🛒">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">สี</label>
                            <input type="color" name="color" id="pfColor" class="form-control form-control-color w-100" value="#666666">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">URL เพจ</label>
                            <input type="url" name="page_url" id="pfUrl" class="form-control" placeholder="https://...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">ชื่อผู้ใช้ / @username</label>
                            <input type="text" name="username" id="pfUsername" class="form-control" placeholder="@username">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">จำนวนผู้ติดตาม</label>
                            <input type="number" name="followers" id="pfFollowers" class="form-control" value="0" min="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">ค่าคอมมิชชั่น (%)</label>
                            <input type="number" name="commission_rate" id="pfCommission" class="form-control" value="0" step="0.01" min="0">
                        </div>
                        <div class="col-12">
                            <label class="form-label">หมายเหตุ</label>
                            <textarea name="notes" id="pfNotes" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input type="checkbox" name="is_active" id="pfActive" class="form-check-input" value="1" checked>
                                <label class="form-check-label" for="pfActive">เปิดใช้งาน</label>
                            </div>
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
const pfModalEl = document.getElementById('pfModal');
function openPfModal(pf) {
    if (pf) {
        document.getElementById('pfModalTitle').textContent = '✏️ แก้ไขแพลตฟอร์ม';
        document.getElementById('pfId').value = pf.id;
        document.getElementById('pfName').value = pf.name;
        document.getElementById('pfIcon').value = pf.icon;
        document.getElementById('pfColor').value = pf.color;
        document.getElementById('pfUrl').value = pf.page_url || '';
        document.getElementById('pfUsername').value = pf.username || '';
        document.getElementById('pfFollowers').value = pf.followers;
        document.getElementById('pfCommission').value = pf.commission_rate;
        document.getElementById('pfNotes').value = pf.notes || '';
        document.getElementById('pfActive').checked = pf.is_active == 1;
    } else {
        document.getElementById('pfModalTitle').textContent = '➕ เพิ่มแพลตฟอร์ม';
        document.getElementById('pfId').value = '0';
        document.querySelector('[name=name]').value = '';
    }
    new bootstrap.Modal(pfModalEl).show();
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
