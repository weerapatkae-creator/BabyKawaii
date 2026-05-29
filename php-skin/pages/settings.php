<?php
require_once __DIR__ . '/../config/database.php';
requireAdmin();
$pdo = getDB();
// Handle save settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $settingFields = ['shop_name','shop_tagline','shop_phone','shop_line','shop_facebook','shop_tiktok','shop_instagram','low_stock_alert','currency','shipping_base','shipping_free_threshold'];
    foreach ($settingFields as $field) {
        $val = trim($_POST[$field] ?? '');
        $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?")->execute([$field, $val, $val]);
    }
    header('Location: ' . SITE_URL . '/pages/settings.php?msg=saved');
    exit;
}


$pageTitle = 'ตั้งค่าร้าน';
require_once __DIR__ . '/../includes/header.php';

// Handle change password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new     = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    $user = $pdo->prepare("SELECT password FROM admin_users WHERE id=?");
    $user->execute([$_SESSION['admin_id']]);
    $user = $user->fetch();

    if (!password_verify($current, $user['password'])) {
        $pwError = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
    } elseif ($new !== $confirm) {
        $pwError = 'รหัสผ่านใหม่ไม่ตรงกัน';
    } elseif (strlen($new) < 6) {
        $pwError = 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร';
    } else {
        $pdo->prepare("UPDATE admin_users SET password=? WHERE id=?")->execute([password_hash($new, PASSWORD_DEFAULT), $_SESSION['admin_id']]);
        $pwSuccess = 'เปลี่ยนรหัสผ่านเรียบร้อย';
    }
}

// Load all settings
$allSettings = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<div class="container-fluid fade-in">
    <div class="page-header">
        <div>
            <h1 class="page-title">⚙️ ตั้งค่าร้าน</h1>
            <p class="page-subtitle">ข้อมูลร้านค้าและการตั้งค่าระบบ</p>
        </div>
    </div>

    <?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success alert-auto">✅ บันทึกการตั้งค่าเรียบร้อย</div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <!-- Shop Info -->
            <div class="card mb-4">
                <div class="card-header"><span class="card-title">🌸 ข้อมูลร้านค้า</span></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="save_settings" value="1">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">ชื่อร้าน</label>
                                <input type="text" name="shop_name" class="form-control" value="<?= htmlspecialchars($allSettings['shop_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Tagline / คำโปรย</label>
                                <input type="text" name="shop_tagline" class="form-control" value="<?= htmlspecialchars($allSettings['shop_tagline'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">📞 เบอร์โทรศัพท์</label>
                                <input type="text" name="shop_phone" class="form-control" value="<?= htmlspecialchars($allSettings['shop_phone'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">💬 Line OA</label>
                                <input type="text" name="shop_line" class="form-control" placeholder="@babykawaii" value="<?= htmlspecialchars($allSettings['shop_line'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">📘 Facebook</label>
                                <input type="text" name="shop_facebook" class="form-control" value="<?= htmlspecialchars($allSettings['shop_facebook'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">🎵 TikTok</label>
                                <input type="text" name="shop_tiktok" class="form-control" value="<?= htmlspecialchars($allSettings['shop_tiktok'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">📸 Instagram</label>
                                <input type="text" name="shop_instagram" class="form-control" value="<?= htmlspecialchars($allSettings['shop_instagram'] ?? '') ?>">
                            </div>
                            <hr>
                            <div class="col-md-4">
                                <label class="form-label">⚠️ แจ้งเตือนสต็อกต่ำกว่า</label>
                                <div class="input-group">
                                    <input type="number" name="low_stock_alert" class="form-control" min="1" value="<?= htmlspecialchars($allSettings['low_stock_alert'] ?? '5') ?>">
                                    <span class="input-group-text">ชิ้น</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">🚚 ค่าจัดส่งพื้นฐาน</label>
                                <div class="input-group">
                                    <span class="input-group-text">฿</span>
                                    <input type="number" name="shipping_base" class="form-control" min="0" value="<?= htmlspecialchars($allSettings['shipping_base'] ?? '50') ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">🎁 ส่งฟรีเมื่อซื้อครบ</label>
                                <div class="input-group">
                                    <span class="input-group-text">฿</span>
                                    <input type="number" name="shipping_free_threshold" class="form-control" min="0" value="<?= htmlspecialchars($allSettings['shipping_free_threshold'] ?? '500') ?>">
                                </div>
                            </div>
                        </div>
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> บันทึกการตั้งค่า</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Change Password -->
            <div class="card">
                <div class="card-header"><span class="card-title">🔒 เปลี่ยนรหัสผ่าน</span></div>
                <div class="card-body">
                    <?php if (isset($pwError)): ?><div class="alert alert-danger">❌ <?= htmlspecialchars($pwError) ?></div><?php endif; ?>
                    <?php if (isset($pwSuccess)): ?><div class="alert alert-success">✅ <?= htmlspecialchars($pwSuccess) ?></div><?php endif; ?>
                    <form method="POST">
                        <input type="hidden" name="change_password" value="1">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">รหัสผ่านปัจจุบัน</label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">รหัสผ่านใหม่</label>
                                <input type="password" name="new_password" class="form-control" required minlength="6">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">ยืนยันรหัสผ่านใหม่</label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>
                        </div>
                        <div class="mt-3">
                            <button type="submit" class="btn btn-kawaii">🔐 เปลี่ยนรหัสผ่าน</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Quick info -->
            <div class="card mb-4">
                <div class="card-header"><span class="card-title">📊 ข้อมูลระบบ</span></div>
                <div class="card-body">
                    <?php
                    $totalP = $pdo->query("SELECT COUNT(*) FROM products WHERE status!='inactive'")->fetchColumn();
                    $totalO = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
                    $totalS = $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE order_status NOT IN ('cancelled')")->fetchColumn();
                    $totalMedia = $pdo->query("SELECT COUNT(*) FROM media")->fetchColumn();
                    ?>
                    <table class="table table-sm mb-0">
                        <tr><td>👕 สินค้าทั้งหมด</td><td class="text-end fw-bold"><?= number_format($totalP) ?></td></tr>
                        <tr><td>📦 ออเดอร์ทั้งหมด</td><td class="text-end fw-bold"><?= number_format($totalO) ?></td></tr>
                        <tr><td>💰 รายได้รวม</td><td class="text-end fw-bold"><?= formatPrice($totalS) ?></td></tr>
                        <tr><td>🖼️ ไฟล์สื่อ</td><td class="text-end fw-bold"><?= number_format($totalMedia) ?></td></tr>
                        <tr><td>👤 ผู้ใช้งาน</td><td class="text-end fw-bold"><?= htmlspecialchars($_SESSION['admin_name']) ?></td></tr>
                        <tr><td>🗓️ วันที่</td><td class="text-end"><?= formatDateTH(date('Y-m-d')) ?></td></tr>
                    </table>
                </div>
            </div>

            <!-- Content Ideas for Baby Shop -->
            <div class="card">
                <div class="card-header"><span class="card-title">💡 ไอเดียเนื้อหาร้านเสื้อผ้าเด็ก</span></div>
                <div class="card-body" style="font-size:0.82rem;">
                    <div class="mb-2"><strong>📸 รูปภาพ:</strong><br>• รูปสินค้าพื้นหลังขาว/พาสเทล<br>• Flat-lay เสื้อผ้าน่ารัก<br>• รูปเด็กสวมใส่จริง</div>
                    <div class="mb-2"><strong>🎬 วิดีโอ:</strong><br>• Unboxing พัสดุ<br>• How to ใส่/ดูแลผ้า<br>• Transformation ก่อน-หลัง<br>• รีวิวจากแม่จริง</div>
                    <div class="mb-2"><strong>💬 Caption:</strong><br>• เน้น "ปลอดภัย ไม่แสบผิว"<br>• "ซักง่าย ไม่ยับ"<br>• "#แรกเกิด #ของขวัญเด็ก"</div>
                    <div><strong>📅 Best Time:</strong><br>• FB/IG: 8-9, 12, 20-21 น.<br>• TikTok: 19-22 น.<br>• Line: 7-9, 12, 20-22 น.</div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
