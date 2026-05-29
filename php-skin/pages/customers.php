<?php
require_once __DIR__ . '/../config/database.php';
requireLogin();
$pdo = getDB();
/* ── POST handlers ────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Save (add or edit)
    if ($action === 'save') {
        $id      = (int)($_POST['id'] ?? 0);
        $name    = trim($_POST['name']);
        $phone   = trim($_POST['phone'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $bday    = $_POST['birthday'] ?: null;
        $tags    = trim($_POST['tags'] ?? '');
        $notes   = trim($_POST['notes'] ?? '');

        if ($id) {
            $pdo->prepare("UPDATE customers SET name=?,phone=?,email=?,address=?,birthday=?,tags=?,notes=?,updated_at=NOW() WHERE id=?")
                ->execute([$name,$phone,$email,$address,$bday,$tags,$notes,$id]);
        } else {
            $pdo->prepare("INSERT INTO customers (name,phone,email,address,birthday,tags,notes) VALUES (?,?,?,?,?,?,?)")
                ->execute([$name,$phone,$email,$address,$bday,$tags,$notes]);
        }
        header('Location: ' . SITE_URL . '/pages/customers.php?msg=saved'); exit;
    }

    // Delete
    if ($action === 'delete') {
        $pdo->prepare("DELETE FROM customers WHERE id=?")->execute([(int)$_POST['id']]);
        header('Location: ' . SITE_URL . '/pages/customers.php?msg=deleted'); exit;
    }

    // Sync stats from orders (recompute totals)
    if ($action === 'sync') {
        $pdo->exec("
            UPDATE customers c
            JOIN (
                SELECT customer_phone, COUNT(*) AS cnt, SUM(total_amount) AS tot, MAX(order_date) AS last
                FROM orders WHERE customer_phone IS NOT NULL AND customer_phone <> ''
                GROUP BY customer_phone
            ) o ON o.customer_phone = c.phone
            SET c.total_orders = o.cnt, c.total_spent = o.tot, c.last_order_at = o.last
        ");
        header('Location: ' . SITE_URL . '/pages/customers.php?msg=synced'); exit;
    }
}


$pageTitle = 'ฐานข้อมูลลูกค้า';
require_once __DIR__ . '/../includes/header.php';

/* ── Filters ─────────────────────────────────────────────────────── */
$search = trim($_GET['q'] ?? '');
$tagFilter = trim($_GET['tag'] ?? '');
$sortBy = in_array($_GET['sort'] ?? '', ['last_order_at','total_spent','total_orders','name']) ? $_GET['sort'] : 'last_order_at';

$where  = ['1=1'];
$params = [];
if ($search) {
    $where[] = '(c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)';
    $params  = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}
if ($tagFilter) {
    $where[] = 'FIND_IN_SET(?, REPLACE(c.tags," ",""))';
    $params[] = $tagFilter;
}

$stmt = $pdo->prepare(
    "SELECT c.*,
        (SELECT COUNT(*) FROM orders WHERE customer_phone = c.phone AND customer_phone <> '') AS order_count,
        (SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE customer_phone = c.phone AND customer_phone <> '') AS spend
     FROM customers c
     WHERE " . implode(' AND ', $where) . "
     ORDER BY c.$sortBy DESC LIMIT 200"
);
$stmt->execute($params);
$customers = $stmt->fetchAll();

// Summary stats
$stats = $pdo->query("SELECT COUNT(*) AS total, COALESCE(SUM(total_spent),0) AS revenue, COALESCE(AVG(total_spent),0) AS avg_spend FROM customers")->fetch();

// All distinct tags for filter chips
$allTags = [];
$tagRows = $pdo->query("SELECT tags FROM customers WHERE tags <> ''")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tagRows as $t) {
    foreach (array_filter(array_map('trim', explode(',', $t))) as $tag) {
        $allTags[$tag] = ($allTags[$tag] ?? 0) + 1;
    }
}
arsort($allTags);
?>

<div class="container-fluid fade-in">
    <div class="page-header">
        <div>
            <h1 class="page-title">👥 ฐานข้อมูลลูกค้า</h1>
            <p class="page-subtitle">ติดตามและจัดการข้อมูลลูกค้าทั้งหมด</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <form method="POST" class="d-inline">
                <input type="hidden" name="action" value="sync">
                <button type="submit" class="btn btn-outline-secondary btn-sm" title="อัปเดตยอดซื้อจากออเดอร์">
                    <i class="fas fa-sync me-1"></i> ซิงค์ยอด
                </button>
            </form>
            <a href="<?= SITE_URL ?>/pages/export.php?type=customers" class="btn btn-outline-success btn-sm">
                <i class="fas fa-file-csv me-1"></i> Export CSV
            </a>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#custModal" onclick="openCustModal()">
                <i class="fas fa-plus me-1"></i> เพิ่มลูกค้า
            </button>
        </div>
    </div>

    <?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success alert-auto">✅
        <?= ['saved'=>'บันทึกข้อมูลลูกค้าเรียบร้อย','deleted'=>'ลบลูกค้าแล้ว','synced'=>'ซิงค์ยอดจากออเดอร์เรียบร้อย'][$_GET['msg']] ?? 'เรียบร้อย' ?>
    </div>
    <?php endif; ?>

    <!-- Stats row -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card p-3 text-center">
                <div style="font-size:1.6rem;font-weight:700;color:var(--pink-dark);"><?= number_format($stats['total']) ?></div>
                <div class="text-muted" style="font-size:0.82rem;">ลูกค้าทั้งหมด</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card p-3 text-center">
                <div style="font-size:1.6rem;font-weight:700;color:#8A6DB0;"><?= formatPrice($stats['revenue']) ?></div>
                <div class="text-muted" style="font-size:0.82rem;">ยอดซื้อรวมทั้งหมด</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card p-3 text-center">
                <div style="font-size:1.6rem;font-weight:700;color:#27ae60;"><?= formatPrice($stats['avg_spend']) ?></div>
                <div class="text-muted" style="font-size:0.82rem;">ยอดซื้อเฉลี่ย/ราย</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card p-3 text-center">
                <div style="font-size:1.6rem;font-weight:700;color:#e67e22;"><?= count($allTags) ?></div>
                <div class="text-muted" style="font-size:0.82rem;">Tag / กลุ่มลูกค้า</div>
            </div>
        </div>
    </div>

    <!-- Tag filter chips -->
    <?php if ($allTags): ?>
    <div class="d-flex gap-2 flex-wrap mb-3 align-items-center">
        <span class="text-muted small">แท็ก:</span>
        <a href="?<?= $search ? 'q='.urlencode($search).'&' : '' ?>" class="badge-status <?= !$tagFilter ? 'badge-active' : 'badge-pending' ?>" style="cursor:pointer;text-decoration:none;">ทั้งหมด</a>
        <?php foreach ($allTags as $tag => $cnt): ?>
        <a href="?tag=<?= urlencode($tag) ?><?= $search ? '&q='.urlencode($search) : '' ?>"
           class="badge-status <?= $tagFilter===$tag ? 'badge-active' : 'badge-pending' ?>"
           style="cursor:pointer;text-decoration:none;"><?= htmlspecialchars($tag) ?> (<?= $cnt ?>)</a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Filter bar -->
    <div class="card mb-4">
        <div class="card-body py-3">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-5">
                    <input type="text" name="q" class="form-control form-control-sm" placeholder="🔍 ชื่อ, เบอร์โทร, อีเมล..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-3">
                    <select name="sort" class="form-select form-select-sm">
                        <option value="last_order_at" <?= $sortBy==='last_order_at'?'selected':'' ?>>เรียงตาม: ออเดอร์ล่าสุด</option>
                        <option value="total_spent"   <?= $sortBy==='total_spent'?'selected':'' ?>>เรียงตาม: ยอดซื้อ</option>
                        <option value="total_orders"  <?= $sortBy==='total_orders'?'selected':'' ?>>เรียงตาม: จำนวนออเดอร์</option>
                        <option value="name"          <?= $sortBy==='name'?'selected':'' ?>>เรียงตาม: ชื่อ ก-ฮ</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm">ค้นหา</button>
                    <a href="?" class="btn btn-outline-secondary btn-sm">ล้าง</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Customer table -->
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>ลูกค้า</th>
                        <th>ติดต่อ</th>
                        <th>แท็ก</th>
                        <th class="text-center">ออเดอร์</th>
                        <th class="text-end">ยอดซื้อรวม</th>
                        <th>ออเดอร์ล่าสุด</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $c):
                        $tagList = array_filter(array_map('trim', explode(',', $c['tags'] ?? '')));
                        $tagColors = ['VIP'=>'#8A6DB0','ลูกค้าประจำ'=>'#E8869B','ออเดอร์ใหม่'=>'#27ae60','ต้องติดตาม'=>'#e67e22'];
                    ?>
                    <tr>
                        <td>
                            <div style="font-weight:600;"><?= htmlspecialchars($c['name']) ?></div>
                            <?php if ($c['address']): ?>
                            <div style="font-size:0.72rem;color:var(--text-muted);" title="<?= htmlspecialchars($c['address']) ?>">
                                📍 <?= htmlspecialchars(mb_substr($c['address'], 0, 40)) ?><?= mb_strlen($c['address']) > 40 ? '...' : '' ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($c['phone']): ?><div style="font-size:0.85rem;">📞 <?= htmlspecialchars($c['phone']) ?></div><?php endif; ?>
                            <?php if ($c['email']): ?><div style="font-size:0.78rem;color:var(--text-muted);">✉️ <?= htmlspecialchars($c['email']) ?></div><?php endif; ?>
                        </td>
                        <td>
                            <?php foreach ($tagList as $tag):
                                $col = $tagColors[$tag] ?? '#6c757d';
                            ?>
                            <span style="background:<?= $col ?>;color:#fff;padding:2px 8px;border-radius:10px;font-size:0.7rem;margin:1px;display:inline-block;"><?= htmlspecialchars($tag) ?></span>
                            <?php endforeach; ?>
                        </td>
                        <td class="text-center">
                            <?php $cnt = max($c['total_orders'], $c['order_count']); ?>
                            <?php if ($cnt > 0): ?>
                            <a href="<?= SITE_URL ?>/pages/orders.php?q=<?= urlencode($c['phone'] ?: $c['name']) ?>" class="badge bg-light text-dark text-decoration-none" title="ดูออเดอร์">
                                <?= $cnt ?> ใบ
                            </a>
                            <?php else: ?>
                            <span class="badge bg-light text-muted">0 ใบ</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php $spend = max($c['total_spent'], $c['spend']); ?>
                            <span style="font-weight:600;color:var(--pink-dark);"><?= formatPrice($spend) ?></span>
                        </td>
                        <td style="font-size:0.8rem;color:var(--text-muted);">
                            <?= $c['last_order_at'] ? formatDateTH($c['last_order_at']) : '—' ?>
                        </td>
                        <td class="text-nowrap">
                            <button class="btn btn-sm btn-outline-primary" onclick="openCustModal(<?= htmlspecialchars(json_encode($c)) ?>)" title="แก้ไข">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger ms-1" onclick="confirmDelete(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['name'])) ?>')" title="ลบ">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($customers)): ?>
                    <tr><td colspan="7" class="text-center py-5">
                        <div class="text-muted">
                            <i class="fas fa-users fa-2x mb-2 d-block opacity-25"></i>
                            <?= $search ? 'ไม่พบลูกค้าที่ค้นหา' : 'ยังไม่มีข้อมูลลูกค้า — กด "+ เพิ่มลูกค้า" หรือ "ซิงค์ยอด" จากออเดอร์' ?>
                        </div>
                    </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Customer Modal -->
<div class="modal fade" id="custModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="custId" value="0">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="custModalTitle">➕ เพิ่มลูกค้าใหม่</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="custName" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">เบอร์โทรศัพท์</label>
                            <input type="tel" name="phone" id="custPhone" class="form-control" placeholder="0812345678">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">อีเมล</label>
                            <input type="email" name="email" id="custEmail" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">วันเกิด (สำหรับส่งโปรโมชั่น)</label>
                            <input type="date" name="birthday" id="custBirthday" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">ที่อยู่จัดส่ง</label>
                            <textarea name="address" id="custAddress" class="form-control" rows="2" placeholder="บ้านเลขที่ ถนน ตำบล อำเภอ จังหวัด รหัสไปรษณีย์"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">แท็ก / กลุ่มลูกค้า</label>
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                <?php foreach (['VIP','ลูกค้าประจำ','ออเดอร์ใหม่','ต้องติดตาม'] as $preset): ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary tag-preset" onclick="toggleTag(this, '<?= $preset ?>')"><?= $preset ?></button>
                                <?php endforeach; ?>
                            </div>
                            <input type="text" name="tags" id="custTags" class="form-control" placeholder="แท็กคั่นด้วยจุลภาค เช่น VIP, ลูกค้าประจำ">
                            <div class="form-text">คั่นหลายแท็กด้วยเครื่องหมายจุลภาค (,)</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">โน้ต / หมายเหตุ</label>
                            <textarea name="notes" id="custNotes" class="form-control" rows="2" placeholder="หมายเหตุเพิ่มเติม..."></textarea>
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

<!-- Delete confirm -->
<form method="POST" id="deleteForm">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteId" value="">
</form>

<script>
function openCustModal(c) {
    document.getElementById('custId').value       = c ? c.id     : 0;
    document.getElementById('custName').value     = c ? c.name   : '';
    document.getElementById('custPhone').value    = c ? c.phone  : '';
    document.getElementById('custEmail').value    = c ? c.email  : '';
    document.getElementById('custAddress').value  = c ? c.address: '';
    document.getElementById('custBirthday').value = c ? (c.birthday || '') : '';
    document.getElementById('custTags').value     = c ? (c.tags  || '') : '';
    document.getElementById('custNotes').value    = c ? (c.notes || '') : '';
    document.getElementById('custModalTitle').textContent = c ? '✏️ แก้ไขข้อมูลลูกค้า' : '➕ เพิ่มลูกค้าใหม่';
    // Highlight preset tags
    const activeTags = (c ? c.tags || '' : '').split(',').map(t => t.trim());
    document.querySelectorAll('.tag-preset').forEach(btn => {
        btn.classList.toggle('btn-primary', activeTags.includes(btn.textContent));
        btn.classList.toggle('btn-outline-secondary', !activeTags.includes(btn.textContent));
    });
    new bootstrap.Modal(document.getElementById('custModal')).show();
}

function toggleTag(btn, tag) {
    const inp  = document.getElementById('custTags');
    let tags   = inp.value.split(',').map(t => t.trim()).filter(Boolean);
    const idx  = tags.indexOf(tag);
    if (idx >= 0) {
        tags.splice(idx, 1);
        btn.classList.replace('btn-primary','btn-outline-secondary');
    } else {
        tags.push(tag);
        btn.classList.replace('btn-outline-secondary','btn-primary');
    }
    inp.value = tags.join(', ');
}

function confirmDelete(id, name) {
    if (confirm('ลบข้อมูลลูกค้า "' + name + '" ออกจากระบบ?')) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
