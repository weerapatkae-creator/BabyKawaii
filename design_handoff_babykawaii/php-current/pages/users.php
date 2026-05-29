<?php
require_once __DIR__ . '/../config/database.php';
requireAdmin();
$pageTitle = 'จัดการผู้ใช้งาน';
require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();
$myId   = $_SESSION['admin_id'];
$myRole = $_SESSION['admin_role'];
$msg    = '';
$msgType = 'success';

// ─── ACTIONS ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ADD USER
    if ($action === 'add') {
        $username  = trim($_POST['username'] ?? '');
        $fullName  = trim($_POST['full_name'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $role      = $_POST['role'] ?? 'staff';
        $password  = $_POST['password'] ?? '';
        $confirm   = $_POST['confirm_password'] ?? '';

        // Admin can only create staff; superadmin can create admin or staff
        $allowedRoles = ($myRole === 'superadmin') ? ['admin', 'staff'] : ['staff'];
        if (!in_array($role, $allowedRoles)) $role = 'staff';

        if (!$username || !$password) {
            $msg = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
            $msgType = 'danger';
        } elseif (strlen($password) < 6) {
            $msg = 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร';
            $msgType = 'danger';
        } elseif ($password !== $confirm) {
            $msg = 'รหัสผ่านไม่ตรงกัน';
            $msgType = 'danger';
        } else {
            // Check duplicate username
            $check = $pdo->prepare("SELECT id FROM admin_users WHERE username = ?");
            $check->execute([$username]);
            if ($check->fetch()) {
                $msg = "ชื่อผู้ใช้ \"$username\" มีอยู่แล้ว";
                $msgType = 'danger';
            } else {
                $pdo->prepare("INSERT INTO admin_users (username, password, full_name, email, role) VALUES (?,?,?,?,?)")
                    ->execute([$username, password_hash($password, PASSWORD_DEFAULT), $fullName, $email, $role]);
                $msg = "เพิ่มผู้ใช้ \"$username\" เรียบร้อยแล้ว";
            }
        }
    }

    // EDIT USER
    elseif ($action === 'edit') {
        $userId   = (int)($_POST['user_id'] ?? 0);
        $fullName = trim($_POST['full_name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $role     = $_POST['role'] ?? 'staff';
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $newPass  = $_POST['new_password'] ?? '';

        // Fetch target user
        $target = $pdo->prepare("SELECT * FROM admin_users WHERE id = ?");
        $target->execute([$userId]);
        $target = $target->fetch();

        if (!$target) {
            $msg = 'ไม่พบผู้ใช้ที่ต้องการแก้ไข';
            $msgType = 'danger';
        } elseif (!canManageUser($myRole, $myId, $target)) {
            $msg = 'คุณไม่มีสิทธิ์แก้ไขผู้ใช้นี้';
            $msgType = 'danger';
        } else {
            // Role: admin can only keep staff as staff; superadmin free to set
            if ($myRole !== 'superadmin') $role = $target['role'];
            // Can't demote yourself
            if ($userId === $myId) $role = $myRole;
            // Can't deactivate yourself
            if ($userId === $myId) $isActive = 1;

            if ($newPass && strlen($newPass) < 6) {
                $msg = 'รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร';
                $msgType = 'danger';
            } else {
                $pdo->prepare("UPDATE admin_users SET full_name=?, email=?, role=?, is_active=? WHERE id=?")
                    ->execute([$fullName, $email, $role, $isActive, $userId]);
                if ($newPass) {
                    $pdo->prepare("UPDATE admin_users SET password=? WHERE id=?")
                        ->execute([password_hash($newPass, PASSWORD_DEFAULT), $userId]);
                }
                // Update session name if editing self
                if ($userId === $myId) $_SESSION['admin_name'] = $fullName ?: $_SESSION['admin_name'];
                $msg = "บันทึกข้อมูล \"{$target['username']}\" เรียบร้อย";
            }
        }
    }

    // DELETE USER
    elseif ($action === 'delete') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $target = $pdo->prepare("SELECT * FROM admin_users WHERE id = ?");
        $target->execute([$userId]);
        $target = $target->fetch();

        if (!$target) {
            $msg = 'ไม่พบผู้ใช้';
            $msgType = 'danger';
        } elseif ($userId === $myId) {
            $msg = 'ไม่สามารถลบบัญชีตัวเองได้';
            $msgType = 'danger';
        } elseif (!canManageUser($myRole, $myId, $target)) {
            $msg = 'คุณไม่มีสิทธิ์ลบผู้ใช้นี้';
            $msgType = 'danger';
        } else {
            $pdo->prepare("DELETE FROM admin_users WHERE id = ?")->execute([$userId]);
            $msg = "ลบผู้ใช้ \"{$target['username']}\" เรียบร้อยแล้ว";
        }
    }

    // TOGGLE ACTIVE
    elseif ($action === 'toggle') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId === $myId) {
            $msg = 'ไม่สามารถปิดใช้งานบัญชีตัวเองได้';
            $msgType = 'danger';
        } else {
            $target = $pdo->prepare("SELECT * FROM admin_users WHERE id = ?");
            $target->execute([$userId]);
            $target = $target->fetch();
            if ($target && canManageUser($myRole, $myId, $target)) {
                $pdo->prepare("UPDATE admin_users SET is_active = 1 - is_active WHERE id = ?")->execute([$userId]);
                $msg = 'เปลี่ยนสถานะผู้ใช้เรียบร้อย';
            } else {
                $msg = 'ไม่มีสิทธิ์เปลี่ยนสถานะผู้ใช้นี้';
                $msgType = 'danger';
            }
        }
    }
}

// ─── HELPER: can the current user manage target? ──────────────────────────
function canManageUser($myRole, $myId, $target) {
    if ($myRole === 'superadmin') return true;
    // admin can only manage staff (not other admins / superadmins)
    if ($myRole === 'admin' && $target['role'] === 'staff') return true;
    return false;
}

// ─── LOAD USERS ────────────────────────────────────────────────────────────
$users = $pdo->query("SELECT * FROM admin_users ORDER BY FIELD(role,'superadmin','admin','staff'), username ASC")->fetchAll();

$roleLabel = ['superadmin' => 'ผู้ดูแลระบบ', 'admin' => 'Admin', 'staff' => 'Staff'];
$roleColor = ['superadmin' => 'badge-packing', 'admin' => 'badge-confirmed', 'staff' => 'badge-active'];
?>

<div class="container-fluid fade-in">
    <div class="page-header">
        <div>
            <h1 class="page-title">👥 จัดการผู้ใช้งาน</h1>
            <p class="page-subtitle">เพิ่ม แก้ไข และกำหนดสิทธิ์ผู้ใช้ในระบบ</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="fas fa-user-plus me-1"></i> เพิ่มผู้ใช้ใหม่
        </button>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?> alert-dismissible fade show" role="alert">
        <?= $msgType === 'success' ? '✅' : '❌' ?> <?= htmlspecialchars($msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Role Summary Cards -->
    <?php
    $countAdmin = count(array_filter($users, fn($u) => in_array($u['role'], ['superadmin','admin'])));
    $countStaff = count(array_filter($users, fn($u) => $u['role'] === 'staff'));
    $countActive = count(array_filter($users, fn($u) => $u['is_active']));
    ?>
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card pink">
                <div class="stat-label">ผู้ใช้ทั้งหมด</div>
                <div class="stat-value"><?= count($users) ?></div>
                <i class="fas fa-users stat-icon"></i>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card purple">
                <div class="stat-label">Admin</div>
                <div class="stat-value"><?= $countAdmin ?></div>
                <i class="fas fa-user-shield stat-icon"></i>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card mint">
                <div class="stat-label">Staff</div>
                <div class="stat-value"><?= $countStaff ?></div>
                <i class="fas fa-user-tie stat-icon"></i>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card orange">
                <div class="stat-label">ใช้งานอยู่</div>
                <div class="stat-value"><?= $countActive ?></div>
                <i class="fas fa-circle-check stat-icon"></i>
            </div>
        </div>
    </div>

    <!-- Users Table -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">📋 รายชื่อผู้ใช้งานทั้งหมด</span>
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>ผู้ใช้งาน</th>
                        <th>อีเมล</th>
                        <th>บทบาท</th>
                        <th>สถานะ</th>
                        <th>เข้าใช้ล่าสุด</th>
                        <th>วันที่สร้าง</th>
                        <th class="text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                <?php $isMe = ($u['id'] === $myId); $canManage = canManageUser($myRole, $myId, $u) || $isMe; ?>
                <tr class="<?= !$u['is_active'] ? 'opacity-50' : '' ?>">
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="user-avatar-circle" style="background:<?= $u['role']==='superadmin'?'linear-gradient(135deg,#8A6DB0,#74589B)':($u['role']==='admin'?'linear-gradient(135deg,#E8869B,#D26A82)':'linear-gradient(135deg,#3AA088,#2F8A72)') ?>">
                                <?= mb_substr($u['full_name'] ?: $u['username'], 0, 1) ?>
                            </div>
                            <div>
                                <div style="font-weight:600;font-size:0.9rem;">
                                    <?= htmlspecialchars($u['full_name'] ?: $u['username']) ?>
                                    <?php if ($isMe): ?><span class="badge bg-secondary ms-1" style="font-size:0.65rem;">คุณ</span><?php endif; ?>
                                </div>
                                <div style="font-size:0.78rem;color:var(--text-muted);">@<?= htmlspecialchars($u['username']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td style="font-size:0.85rem;color:var(--text-muted);"><?= htmlspecialchars($u['email'] ?: '—') ?></td>
                    <td><span class="badge-status <?= $roleColor[$u['role']] ?>"><?= $roleLabel[$u['role']] ?></span></td>
                    <td>
                        <?php if ($u['is_active']): ?>
                            <span class="badge-status badge-active"><i class="fas fa-circle" style="font-size:0.5rem;"></i> ใช้งาน</span>
                        <?php else: ?>
                            <span class="badge-status badge-inactive"><i class="fas fa-circle" style="font-size:0.5rem;"></i> ปิดใช้งาน</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:0.82rem;"><?= $u['last_login'] ? timeAgo($u['last_login']) : '—' ?></td>
                    <td style="font-size:0.82rem;"><?= formatDateTH($u['created_at']) ?></td>
                    <td class="text-center">
                        <?php if ($canManage): ?>
                        <div class="d-flex gap-1 justify-content-center">
                            <!-- Edit Button -->
                            <button class="btn btn-sm btn-outline-pink"
                                onclick="openEditModal(<?= htmlspecialchars(json_encode($u), ENT_QUOTES) ?>)"
                                title="แก้ไข">
                                <i class="fas fa-edit"></i>
                            </button>

                            <!-- Toggle Active (not self) -->
                            <?php if (!$isMe): ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn btn-sm <?= $u['is_active'] ? 'btn-warning' : 'btn-success' ?>"
                                    title="<?= $u['is_active'] ? 'ปิดใช้งาน' : 'เปิดใช้งาน' ?>"
                                    onclick="return confirm('<?= $u['is_active'] ? 'ปิดใช้งานบัญชีนี้?' : 'เปิดใช้งานบัญชีนี้?' ?>')">
                                    <i class="fas <?= $u['is_active'] ? 'fa-user-slash' : 'fa-user-check' ?>"></i>
                                </button>
                            </form>

                            <!-- Delete (not self, not superadmin unless I'm superadmin) -->
                            <?php if (!($u['role'] === 'superadmin' && $myRole !== 'superadmin')): ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger"
                                    title="ลบ"
                                    onclick="return confirm('ลบผู้ใช้ \"<?= htmlspecialchars($u['username']) ?>\" ? การดำเนินการนี้ไม่สามารถย้อนกลับได้')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <span style="font-size:0.78rem;color:var(--text-muted);">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Permission Reference Card -->
    <div class="card">
        <div class="card-header"><span class="card-title">🔐 สิทธิ์การใช้งานตามบทบาท</span></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="perm-block" style="border-left:4px solid #8A6DB0;padding:12px 16px;background:var(--lavender);border-radius:var(--radius-sm);">
                        <div style="font-weight:700;color:#8A6DB0;margin-bottom:8px;">👑 ผู้ดูแลระบบ (Superadmin)</div>
                        <ul style="font-size:0.82rem;margin:0;padding-left:16px;color:var(--text);">
                            <li>เข้าถึงทุกฟีเจอร์ในระบบ</li>
                            <li>จัดการผู้ใช้ทุกระดับ</li>
                            <li>เพิ่ม/ลบ Admin และ Staff</li>
                            <li>ตั้งค่าระบบทั้งหมด</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="perm-block" style="border-left:4px solid #E8869B;padding:12px 16px;background:var(--pink-light);border-radius:var(--radius-sm);">
                        <div style="font-weight:700;color:var(--pink-dark);margin-bottom:8px;">🛡️ Admin</div>
                        <ul style="font-size:0.82rem;margin:0;padding-left:16px;color:var(--text);">
                            <li>เข้าถึงทุกฟีเจอร์ในระบบ</li>
                            <li>เพิ่ม/แก้ไข/ลบ Staff</li>
                            <li>ตั้งค่าร้านค้า</li>
                            <li>ดูรายงานยอดขาย</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="perm-block" style="border-left:4px solid #3AA088;padding:12px 16px;background:var(--mint);border-radius:var(--radius-sm);">
                        <div style="font-weight:700;color:#198754;margin-bottom:8px;">👷 Staff</div>
                        <ul style="font-size:0.82rem;margin:0;padding-left:16px;color:var(--text);">
                            <li>จัดการสินค้าและสต็อก</li>
                            <li>รับ-จัดการออเดอร์</li>
                            <li>อัปโหลดสื่อ / โปรโมชั่น</li>
                            <li>ไม่สามารถจัดการผู้ใช้หรือตั้งค่าได้</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ─── ADD USER MODAL ─────────────────────────────────────────────────────── -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:var(--radius);border:none;">
            <div class="modal-header" style="background:linear-gradient(135deg,#E8869B,#8A6DB0);color:#fff;border-radius:var(--radius) var(--radius) 0 0;">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>เพิ่มผู้ใช้ใหม่</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">ชื่อผู้ใช้ (Username) <span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control" placeholder="เช่น staff_nook" required pattern="[a-zA-Z0-9_]+" title="ภาษาอังกฤษ ตัวเลข หรือ _ เท่านั้น">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">ชื่อ-นามสกุล</label>
                            <input type="text" name="full_name" class="form-control" placeholder="เช่น นุ้ย สาวเสน่ห์">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">อีเมล</label>
                            <input type="email" name="email" class="form-control" placeholder="staff@example.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">บทบาท</label>
                            <select name="role" class="form-select">
                                <?php if ($myRole === 'superadmin'): ?>
                                <option value="admin">🛡️ Admin</option>
                                <?php endif; ?>
                                <option value="staff" selected>👷 Staff</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">รหัสผ่าน <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" name="password" id="addPw" class="form-control" placeholder="อย่างน้อย 6 ตัวอักษร" required minlength="6">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePw('addPw',this)">👁️</button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">ยืนยันรหัสผ่าน <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" name="confirm_password" id="addPwC" class="form-control" placeholder="พิมพ์รหัสผ่านอีกครั้ง" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePw('addPwC',this)">👁️</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus me-1"></i> เพิ่มผู้ใช้</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ─── EDIT USER MODAL ────────────────────────────────────────────────────── -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:var(--radius);border:none;">
            <div class="modal-header" style="background:linear-gradient(135deg,#8A6DB0,#74589B);color:#fff;border-radius:var(--radius) var(--radius) 0 0;">
                <h5 class="modal-title"><i class="fas fa-user-edit me-2"></i>แก้ไขผู้ใช้: <span id="editUsername"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="user_id" id="editUserId">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">ชื่อ-นามสกุล</label>
                            <input type="text" name="full_name" id="editFullName" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">อีเมล</label>
                            <input type="email" name="email" id="editEmail" class="form-control">
                        </div>
                        <div class="col-md-6" id="editRoleWrap">
                            <label class="form-label">บทบาท</label>
                            <select name="role" id="editRole" class="form-select">
                                <?php if ($myRole === 'superadmin'): ?>
                                <option value="superadmin">👑 Superadmin</option>
                                <option value="admin">🛡️ Admin</option>
                                <?php endif; ?>
                                <option value="staff">👷 Staff</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">สถานะ</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="is_active" id="editActive" value="1">
                                <label class="form-check-label" for="editActive">ใช้งานอยู่</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <hr style="border-color:var(--border);">
                            <label class="form-label">🔑 รีเซ็ตรหัสผ่าน <small class="text-muted">(เว้นว่างถ้าไม่ต้องการเปลี่ยน)</small></label>
                            <div class="input-group">
                                <input type="password" name="new_password" id="editPw" class="form-control" placeholder="รหัสผ่านใหม่ (อย่างน้อย 6 ตัวอักษร)" minlength="6">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePw('editPw',this)">👁️</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-kawaii"><i class="fas fa-save me-1"></i> บันทึกการแก้ไข</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.user-avatar-circle {
    width: 38px; height: 38px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 1rem; color: #fff;
    flex-shrink: 0;
}
</style>

<?php
$extraJs = "
function openEditModal(user) {
    document.getElementById('editUserId').value   = user.id;
    document.getElementById('editUsername').textContent = user.username;
    document.getElementById('editFullName').value = user.full_name || '';
    document.getElementById('editEmail').value    = user.email || '';
    document.getElementById('editActive').checked = user.is_active == 1;
    document.getElementById('editPw').value       = '';

    const roleSelect = document.getElementById('editRole');
    if (roleSelect) {
        roleSelect.value = user.role;
        // If editing self, disable role change
        roleSelect.disabled = (user.id == " . $myId . ");
    }

    // Disable active toggle if editing self
    document.getElementById('editActive').disabled = (user.id == " . $myId . ");

    const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
    modal.show();
}

function togglePw(id, btn) {
    const el = document.getElementById(id);
    el.type = el.type === 'password' ? 'text' : 'password';
    btn.textContent = el.type === 'password' ? '👁️' : '🙈';
}
";
include __DIR__ . '/../includes/footer.php';
?>
