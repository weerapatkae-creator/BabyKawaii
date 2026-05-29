<?php
require_once __DIR__ . '/../config/database.php';
requireAdmin();
$pdo = getDB();

/* ── AJAX ──────────────────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');

    if ($_POST['ajax'] === 'save') {
        $id       = (int)($_POST['id'] ?? 0);
        $platId   = (int)$_POST['platform_id'];
        $name     = trim($_POST['name'] ?? '');
        $uid      = trim($_POST['account_uid'] ?? '');
        $token    = trim($_POST['page_access_token'] ?? '');
        $secret   = trim($_POST['app_secret'] ?? '');
        $verify   = trim($_POST['webhook_verify_token'] ?? '');
        $color    = trim($_POST['color'] ?? '#1877F2');
        $notes    = trim($_POST['notes'] ?? '');
        $active   = (int)($_POST['is_active'] ?? 1);

        if (!$name || !$platId) { echo json_encode(['ok'=>false,'msg'=>'กรุณากรอกชื่อและเลือก platform']); exit; }

        if ($id) {
            $pdo->prepare("UPDATE platform_accounts SET platform_id=?,name=?,account_uid=?,page_access_token=?,app_secret=?,webhook_verify_token=?,color=?,notes=?,is_active=?,updated_at=NOW() WHERE id=?")
                ->execute([$platId,$name,$uid,$token,$secret,$verify,$color,$notes,$active,$id]);
        } else {
            $pdo->prepare("INSERT INTO platform_accounts (platform_id,name,account_uid,page_access_token,app_secret,webhook_verify_token,color,notes,is_active) VALUES (?,?,?,?,?,?,?,?,?)")
                ->execute([$platId,$name,$uid,$token,$secret,$verify,$color,$notes,$active]);
            $id = $pdo->lastInsertId();
        }
        echo json_encode(['ok'=>true,'id'=>$id]);
        exit;
    }

    if ($_POST['ajax'] === 'delete') {
        $pdo->prepare("DELETE FROM platform_accounts WHERE id=?")->execute([(int)$_POST['id']]);
        echo json_encode(['ok'=>true]);
        exit;
    }

    if ($_POST['ajax'] === 'toggle') {
        $pdo->prepare("UPDATE platform_accounts SET is_active = 1-is_active WHERE id=?")->execute([(int)$_POST['id']]);
        $row = $pdo->prepare("SELECT is_active FROM platform_accounts WHERE id=?");
        $row->execute([(int)$_POST['id']]);
        echo json_encode(['ok'=>true,'is_active'=>(int)$row->fetchColumn()]);
        exit;
    }

    echo json_encode(['ok'=>false]);
    exit;
}

$pageTitle = 'จัดการบัญชี Platform';
require_once __DIR__ . '/../includes/header.php';

$platforms = $pdo->query("SELECT * FROM platforms WHERE is_active=1 ORDER BY name")->fetchAll();
$accounts  = $pdo->query("
    SELECT pa.*, p.name as platform_name, p.icon as platform_icon, p.slug as platform_slug
    FROM platform_accounts pa
    JOIN platforms p ON p.id = pa.platform_id
    ORDER BY p.name, pa.name
")->fetchAll();

// group by platform
$grouped = [];
foreach ($accounts as $acc) {
    $grouped[$acc['platform_id']]['platform'] = ['id'=>$acc['platform_id'],'name'=>$acc['platform_name'],'icon'=>$acc['platform_icon'],'slug'=>$acc['platform_slug']];
    $grouped[$acc['platform_id']]['accounts'][] = $acc;
}

$siteUrl = SITE_URL;
$apiKey  = getSetting('api_key','YOUR_API_KEY');
?>

<style>
.acc-card { border:1px solid var(--border-color); border-radius:12px; overflow:hidden; margin-bottom:1rem; }
.acc-card-head { padding:10px 16px; display:flex; align-items:center; gap:10px; font-weight:600; font-size:.92rem; }
.acc-row { padding:12px 16px; border-top:1px solid #f0f0f0; display:flex; align-items:center; gap:12px; transition:background .1s; }
.acc-row:hover { background:#fafafa; }
.acc-dot { width:12px; height:12px; border-radius:50%; flex-shrink:0; }
.acc-name { font-weight:600; font-size:.88rem; flex:1; }
.acc-uid  { font-size:.75rem; color:#888; font-family:monospace; }
.acc-token-preview { font-size:.72rem; color:#aaa; font-family:monospace; letter-spacing:.02em; }
.webhook-url { font-family:monospace; font-size:.72rem; background:#f5f5f5; padding:4px 9px; border-radius:6px; word-break:break-all; border:1px solid #e5e5e5; cursor:pointer; }
.webhook-url:hover { background:#ffe8f0; border-color:var(--pink); }
.badge-active   { background:#d4edda; color:#155724; padding:2px 8px; border-radius:10px; font-size:.7rem; font-weight:600; }
.badge-inactive { background:#f8d7da; color:#721c24; padding:2px 8px; border-radius:10px; font-size:.7rem; font-weight:600; }
.empty-platform { padding:14px 16px; border-top:1px solid #f0f0f0; text-align:center; font-size:.82rem; color:#bbb; }
</style>

<div class="container-fluid fade-in px-3 py-3">
    <div class="d-flex align-items-center mb-3 gap-2 flex-wrap">
        <h1 class="page-title mb-0">📡 จัดการบัญชี Platform</h1>
        <button class="btn btn-pink btn-sm ms-auto" onclick="openModal()">
            <i class="fas fa-plus me-1"></i> เพิ่มบัญชีใหม่
        </button>
    </div>

    <!-- Info box -->
    <div class="alert alert-info d-flex gap-3 align-items-start mb-3" style="font-size:.82rem;">
        <i class="fas fa-info-circle mt-1 flex-shrink-0"></i>
        <div>
            <strong>แต่ละบัญชีมี Webhook URL ของตัวเอง</strong> — นำ URL ไปตั้งใน Facebook App / Instagram / TikTok Shop Developer Dashboard<br>
            <span class="text-muted">Platform จะส่ง event เข้ามาที่ URL นี้ → ระบบเก็บใน Inbox แยกตาม account</span>
        </div>
    </div>

    <?php if (empty($accounts)): ?>
    <div class="text-center py-5 text-muted">
        <i class="fas fa-plug fa-3x mb-3 opacity-25 d-block"></i>
        ยังไม่มีบัญชีที่เชื่อมต่อ<br>
        <button class="btn btn-pink btn-sm mt-3" onclick="openModal()">เพิ่มบัญชีแรก</button>
    </div>
    <?php else: ?>

    <?php foreach ($grouped as $pid => $group): ?>
    <div class="acc-card">
        <div class="acc-card-head" style="background:linear-gradient(135deg,#fff5f8,#fff);">
            <span style="font-size:1.2rem;"><?= $group['platform']['icon'] ?></span>
            <?= htmlspecialchars($group['platform']['name']) ?>
            <span class="badge bg-secondary ms-auto"><?= count($group['accounts']) ?> บัญชี</span>
            <button class="btn btn-sm btn-outline-pink" onclick="openModal(<?= $pid ?>)">
                <i class="fas fa-plus"></i> เพิ่ม
            </button>
        </div>

        <?php foreach ($group['accounts'] as $acc): ?>
        <?php
            $webhookUrl = ($acc['platform_slug'] === 'tiktok')
                ? $siteUrl . '/api/tiktok-webhook.php?account_id=' . $acc['id']
                : $siteUrl . '/api/inbox-webhook.php?account_id=' . $acc['id'] . '&api_key=' . $apiKey;
            $tokenPreview = $acc['page_access_token']
                ? substr($acc['page_access_token'], 0, 12) . '••••••••' . substr($acc['page_access_token'], -4)
                : '— ยังไม่ได้ตั้งค่า —';

            // TikTok OAuth URL (ใช้ app_key ที่เก็บใน webhook_verify_token)
            $tiktokAuthUrl = '';
            if ($acc['platform_slug'] === 'tiktok' && $acc['webhook_verify_token']) {
                $state = base64_encode($acc['webhook_verify_token'] . ':' . $acc['app_secret'] . ':' . $acc['id']);
                $redirectUri = urlencode($siteUrl . '/api/tiktok-oauth.php');
                $tiktokAuthUrl = "https://auth.tiktok-shops.com/oauth/authorize?app_key={$acc['webhook_verify_token']}&state={$state}&redirect_uri={$redirectUri}";
            }
        ?>
        <div class="acc-row" id="acc-row-<?= $acc['id'] ?>">
            <div class="acc-dot" style="background:<?= htmlspecialchars($acc['color']) ?>;"></div>
            <div style="flex:1;min-width:0;">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="acc-name"><?= htmlspecialchars($acc['name']) ?></span>
                    <span id="badge-<?= $acc['id'] ?>" class="<?= $acc['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
                        <?= $acc['is_active'] ? '● เปิด' : '○ ปิด' ?>
                    </span>
                    <?php if ($acc['account_uid']): ?>
                    <span class="acc-uid">ID: <?= htmlspecialchars($acc['account_uid']) ?></span>
                    <?php endif; ?>
                    <?php if ($acc['platform_slug'] === 'tiktok'): ?>
                    <span class="badge <?= $acc['page_access_token'] ? 'bg-success' : 'bg-warning text-dark' ?>" style="font-size:.65rem;">
                        <?= $acc['page_access_token'] ? '✅ Token OK' : '⚠️ ยังไม่ได้ authorize' ?>
                    </span>
                    <?php endif; ?>
                </div>
                <div class="acc-token-preview mt-1">🔑 Token: <?= htmlspecialchars($tokenPreview) ?></div>
                <div class="mt-1 d-flex align-items-center gap-2 flex-wrap">
                    <span class="text-muted" style="font-size:.72rem;">Webhook URL:</span>
                    <span class="webhook-url" onclick="copyWebhook(this)" title="คลิกเพื่อคัดลอก">
                        <?= htmlspecialchars($webhookUrl) ?>
                    </span>
                    <?php if ($acc['platform_slug'] !== 'tiktok' && $acc['webhook_verify_token']): ?>
                    <span style="font-size:.7rem;color:#888;">Verify token: <code><?= htmlspecialchars($acc['webhook_verify_token']) ?></code></span>
                    <?php endif; ?>
                </div>
                <?php if ($acc['notes']): ?>
                <div class="mt-1" style="font-size:.75rem;color:#999;"><?= htmlspecialchars($acc['notes']) ?></div>
                <?php endif; ?>
            </div>
            <div class="d-flex gap-2 flex-shrink-0 flex-wrap justify-content-end">
                <?php if ($tiktokAuthUrl): ?>
                <a href="<?= htmlspecialchars($tiktokAuthUrl) ?>" class="btn btn-sm btn-outline-dark" title="ขอ Access Token จาก TikTok" target="_blank">
                    🔗 Authorize
                </a>
                <?php endif; ?>
                <button class="btn btn-sm btn-outline-secondary" onclick="openModal(<?= $pid ?>, <?= htmlspecialchars(json_encode($acc)) ?>)" title="แก้ไข">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-sm btn-outline-<?= $acc['is_active'] ? 'warning' : 'success' ?>" onclick="toggleAccount(<?= $acc['id'] ?>, this)" title="<?= $acc['is_active'] ? 'ปิดใช้งาน' : 'เปิดใช้งาน' ?>">
                    <i class="fas fa-power-off"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteAccount(<?= $acc['id'] ?>)" title="ลบ">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>

    <?php endif; ?>

    <!-- Platforms with no accounts yet -->
    <?php foreach ($platforms as $pf):
        if (isset($grouped[$pf['id']])) continue; ?>
    <div class="acc-card" style="border-style:dashed;opacity:.65;">
        <div class="acc-card-head">
            <span style="font-size:1.2rem;"><?= $pf['icon'] ?></span>
            <?= htmlspecialchars($pf['name']) ?>
            <button class="btn btn-sm btn-outline-secondary ms-auto" onclick="openModal(<?= $pf['id'] ?>)">
                <i class="fas fa-plus me-1"></i> เพิ่มบัญชีแรก
            </button>
        </div>
        <div class="empty-platform">ยังไม่มีบัญชี — คลิก "เพิ่มบัญชีแรก" เพื่อเชื่อมต่อ</div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Add / Edit Modal -->
<div class="modal fade" id="accModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#FBE9EE,#F0EAF6);">
                <h5 class="modal-title" id="accModalTitle">เพิ่มบัญชีใหม่</h5>
                <button type="button" class="btn-close" onclick="closeAccModal()"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="fId">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Platform <span class="text-danger">*</span></label>
                        <select id="fPlatformId" class="form-select" onchange="updatePlatformHint()">
                            <?php foreach ($platforms as $pf): ?>
                            <option value="<?= $pf['id'] ?>" data-slug="<?= $pf['slug'] ?>" data-color="<?= $pf['color'] ?>">
                                <?= $pf['icon'] ?> <?= htmlspecialchars($pf['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">ชื่อบัญชี <span class="text-danger">*</span></label>
                        <input type="text" id="fName" class="form-control" placeholder="เช่น BabyKawaii TH, BabyKawaii Official">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" id="lUid">Account / Page ID</label>
                        <input type="text" id="fUid" class="form-control font-monospace" placeholder="ID ของ Page หรือ Account">
                        <div class="form-text" id="hintUid"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">สีแสดงผล (Inbox)</label>
                        <div class="d-flex gap-2 align-items-center">
                            <input type="color" id="fColor" class="form-control form-control-color" value="#1877F2" style="width:52px;height:38px;">
                            <div class="d-flex gap-1 flex-wrap">
                                <?php foreach (['#1877F2','#E4405F','#010101','#FF6B6B','#00B900','#8A6DB0'] as $c): ?>
                                <div onclick="document.getElementById('fColor').value='<?= $c ?>'" style="width:22px;height:22px;border-radius:50%;background:<?= $c ?>;cursor:pointer;border:2px solid #fff;box-shadow:0 0 0 1px #ccc;"></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">
                            <span id="lToken">Page Access Token</span>
                            <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <input type="password" id="fToken" class="form-control font-monospace" placeholder="Access token จาก Platform Developer Dashboard">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePass('fToken',this)">👁</button>
                        </div>
                        <div class="form-text" id="hintToken"></div>
                    </div>
                    <div class="col-md-6" id="rowSecret">
                        <label class="form-label fw-semibold" id="lSecret">App Secret</label>
                        <div class="input-group">
                            <input type="password" id="fSecret" class="form-control font-monospace" placeholder="App Secret">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePass('fSecret',this)">👁</button>
                        </div>
                        <div class="form-text" id="hintSecret"></div>
                    </div>
                    <div class="col-md-6" id="rowVerify">
                        <label class="form-label fw-semibold" id="lVerify">App Key / Verify Token</label>
                        <input type="text" id="fVerify" class="form-control font-monospace" placeholder="">
                        <div class="form-text" id="hintVerify"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">สถานะ</label>
                        <select id="fActive" class="form-select">
                            <option value="1">✅ เปิดใช้งาน</option>
                            <option value="0">❌ ปิดใช้งาน</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">หมายเหตุ</label>
                        <input type="text" id="fNotes" class="form-control" placeholder="โน้ตส่วนตัว เช่น 'เพจหลัก'">
                    </div>

                    <!-- Webhook URL preview -->
                    <div class="col-12" id="webhookPreview" style="display:none;">
                        <label class="form-label fw-semibold">Webhook URL (สำหรับบัญชีนี้)</label>
                        <div class="webhook-url" id="webhookPreviewUrl" onclick="copyWebhook(this)"></div>
                        <div class="form-text">นำ URL นี้ไปตั้งใน Platform Developer Dashboard → Webhooks</div>
                    </div>

                    <!-- Setup guide -->
                    <div class="col-12">
                        <div class="alert alert-light border" id="setupGuide" style="font-size:.78rem;"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" onclick="closeAccModal()">ยกเลิก</button>
                <button type="button" class="btn btn-pink" onclick="saveAccount()">
                    <i class="fas fa-save me-1"></i> บันทึก
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const SITE_URL = '<?= SITE_URL ?>';
const API_KEY  = '<?= $apiKey ?>';

const PLATFORM_HINTS = {
    facebook: {
        uid:         'Facebook Page ID (ดูได้ที่ About ของเพจ หรือ Graph API)',
        token:       'Page Access Token จาก Meta for Developers → Graph API Explorer',
        secret:      'App Secret จาก Meta App Dashboard → Settings → Basic',
        verify:      'ตั้งเองได้ เช่น babykawaii2025 — ใช้กรอกใน Meta Webhook → Verify Token',
        lSecret:     'App Secret (Meta)',
        lVerify:     'Webhook Verify Token (Meta)',
        showSecret:  true,
        showVerify:  true,
        webhookPath: '/api/meta-webhook.php',
        guide: `<strong>📘 วิธีตั้ง Facebook Messenger Webhook:</strong><br>
                1. ไปที่ <a href="https://developers.facebook.com" target="_blank">developers.facebook.com</a> → เปิด App<br>
                2. Add Product: <strong>Messenger</strong> → Settings<br>
                3. Webhooks → ใส่ Webhook URL + Verify Token แล้วกด Verify<br>
                4. Subscribe fields: <code>messages</code>, <code>messaging_postbacks</code><br>
                5. Access Tokens → Generate Page Access Token สำหรับเพจนั้น`
    },
    instagram: {
        uid:         'Instagram Account ID (ดูจาก Meta Business Suite → Settings)',
        token:       'Page Access Token (ต้องเชื่อมกับ Facebook Page ก่อน)',
        secret:      'App Secret เดียวกับ Facebook App',
        verify:      'Verify Token เดียวกับที่ตั้งใน Meta Webhook',
        lSecret:     'App Secret (Meta)',
        lVerify:     'Webhook Verify Token (Meta)',
        showSecret:  true,
        showVerify:  true,
        webhookPath: '/api/meta-webhook.php',
        guide: `<strong>📸 วิธีตั้ง Instagram DM Webhook:</strong><br>
                1. เชื่อม Instagram Professional กับ Facebook Page ก่อน<br>
                2. Meta App → Instagram → Webhooks → ใส่ Webhook URL<br>
                3. Subscribe: <code>messages</code><br>
                4. ใช้ Page Access Token + App Secret เดียวกับ Facebook Page`
    },
    tiktok: {
        uid:         'Shop ID จาก TikTok Seller Center → My Account → Shop Info',
        token:       'Access Token จาก TikTok Partner Portal → My Apps → App Detail → Authorization',
        secret:      'App Secret จาก TikTok Partner Portal → My Apps → App Detail → App Info',
        verify:      'App Key (Client Key) จาก TikTok Partner Portal → My Apps → App Detail → App Info',
        lSecret:     'App Secret (TikTok Shop)',
        lVerify:     'App Key (TikTok Shop)',
        showSecret:  true,
        showVerify:  true,
        webhookPath: '/api/tiktok-webhook.php',
        guide: `<strong>🛍️ วิธีเชื่อมต่อ TikTok Shop IM API:</strong><br>
                1. เข้า <a href="https://partner.tiktokshop.com" target="_blank">TikTok Partner Portal</a> → My Apps → สร้าง App ใหม่ (หรือใช้ App เดิม)<br>
                2. เปิด Permission: <code>im.message:write</code>, <code>im.message:read</code><br>
                3. คัดลอก <strong>App Key</strong> (ใส่ในช่อง App Key) และ <strong>App Secret</strong><br>
                4. Authorization → Generate Access Token → คัดลอก Access Token<br>
                5. ใส่ <strong>Shop ID</strong> จาก Seller Center → My Account<br>
                6. นำ <strong>Webhook URL</strong> ด้านล่างไปตั้งใน Partner Portal → Event Settings → Webhooks<br>
                &nbsp;&nbsp;&nbsp; Subscribe events: <code>IM_MESSAGE</code>, <code>ORDERS_STATUS_CHANGE</code><br>
                7. กด Verify — ระบบจะตอบ challenge อัตโนมัติ ✅`
    },
    walkin: {
        uid:         'ไม่จำเป็น',
        token:       'ไม่จำเป็น',
        secret:      '',
        verify:      '',
        lSecret:     'App Secret',
        lVerify:     'Verify Token',
        showSecret:  false,
        showVerify:  false,
        webhookPath: '',
        guide: `<strong>🏪 หน้าร้าน / Walk-in:</strong><br>กรอกชื่อร้านและสีแสดงผลเท่านั้น ไม่ต้องใส่ Token`
    }
};

function updatePlatformHint() {
    const sel  = document.getElementById('fPlatformId');
    const slug = sel.selectedOptions[0]?.dataset.slug || 'facebook';
    const col  = sel.selectedOptions[0]?.dataset.color || '#1877F2';
    const h    = PLATFORM_HINTS[slug] || PLATFORM_HINTS.facebook;

    document.getElementById('hintUid').textContent        = h.uid;
    document.getElementById('hintToken').innerHTML        = h.token;
    document.getElementById('hintSecret').textContent     = h.secret || '';
    document.getElementById('hintVerify').textContent     = h.verify || '';
    document.getElementById('lSecret').textContent        = h.lSecret || 'App Secret';
    document.getElementById('lVerify').textContent        = h.lVerify  || 'Verify Token';
    document.getElementById('lUid').textContent           = slug === 'tiktok' ? 'Shop ID' :
                                                            slug === 'walkin'  ? 'รหัสสาขา (ถ้ามี)' :
                                                            'Account / Page ID';
    document.getElementById('lToken').textContent         = slug === 'tiktok' ? 'Access Token (TikTok Shop)' : 'Page Access Token';
    document.getElementById('setupGuide').innerHTML       = h.guide;
    document.getElementById('fColor').value               = col;

    document.getElementById('rowSecret').style.display = h.showSecret ? '' : 'none';
    document.getElementById('rowVerify').style.display = h.showVerify ? '' : 'none';

    // walk-in เท่านั้นที่ไม่ต้อง token
    const tokenRequired = slug !== 'walkin';
    document.getElementById('fToken').required = tokenRequired;
    document.querySelector('label[for] span.text-danger, #lToken + span')?.remove();

    updateWebhookPreview(slug);
}

function updateWebhookPreview(slug) {
    const id = document.getElementById('fId').value;
    if (!slug) {
        const sel = document.getElementById('fPlatformId');
        slug = sel.selectedOptions[0]?.dataset.slug || 'facebook';
    }
    const h = PLATFORM_HINTS[slug] || PLATFORM_HINTS.facebook;
    if (id && h.webhookPath) {
        const url = `${SITE_URL}${h.webhookPath}?account_id=${id}` +
                    (slug !== 'tiktok' ? `&api_key=${API_KEY}` : '');
        document.getElementById('webhookPreviewUrl').textContent = url;
        document.getElementById('webhookPreview').style.display = '';
    } else {
        document.getElementById('webhookPreview').style.display = 'none';
    }
}

function closeAccModal() {
    const el = document.getElementById('accModal');
    // Bootstrap hide (triggers animation)
    try { bootstrap.Modal.getInstance(el)?.hide(); } catch(e) {}
    // Force full cleanup after animation (300ms default + buffer)
    setTimeout(() => {
        el.classList.remove('show');
        el.style.display = 'none';
        el.setAttribute('aria-hidden', 'true');
        el.removeAttribute('aria-modal');
        el.removeAttribute('role');
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
        document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
    }, 320);
}

function openModal(platformId, acc) {
    document.getElementById('fId').value        = acc?.id        || '';
    document.getElementById('fName').value      = acc?.name      || '';
    document.getElementById('fUid').value       = acc?.account_uid || '';
    document.getElementById('fToken').value     = acc?.page_access_token || '';
    document.getElementById('fSecret').value    = acc?.app_secret || '';
    document.getElementById('fVerify').value    = acc?.webhook_verify_token || '';
    document.getElementById('fColor').value     = acc?.color     || '#1877F2';
    document.getElementById('fActive').value    = acc?.is_active ?? 1;
    document.getElementById('fNotes').value     = acc?.notes     || '';
    document.getElementById('accModalTitle').textContent = acc ? 'แก้ไขบัญชี' : 'เพิ่มบัญชีใหม่';

    if (platformId) {
        document.getElementById('fPlatformId').value = platformId;
    }
    updatePlatformHint();
    updateWebhookPreview();

    bootstrap.Modal.getOrCreateInstance(document.getElementById('accModal')).show();
}

function saveAccount() {
    const data = {
        ajax: 'save',
        id:                    document.getElementById('fId').value,
        platform_id:           document.getElementById('fPlatformId').value,
        name:                  document.getElementById('fName').value.trim(),
        account_uid:           document.getElementById('fUid').value.trim(),
        page_access_token:     document.getElementById('fToken').value.trim(),
        app_secret:            document.getElementById('fSecret').value.trim(),
        webhook_verify_token:  document.getElementById('fVerify').value.trim(),
        color:                 document.getElementById('fColor').value,
        is_active:             document.getElementById('fActive').value,
        notes:                 document.getElementById('fNotes').value.trim(),
    };
    if (!data.name || !data.platform_id) { alert('กรุณากรอกชื่อบัญชีและเลือก Platform'); return; }

    fetch('', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams(data)
    }).then(r=>r.json()).then(res => {
        if (res.ok) { location.reload(); }
        else        { alert(res.msg || 'เกิดข้อผิดพลาด'); }
    });
}

function toggleAccount(id, btn) {
    fetch('', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({ajax:'toggle', id})
    }).then(r=>r.json()).then(res => {
        if (!res.ok) return;
        const badge = document.getElementById('badge-' + id);
        if (res.is_active) {
            badge.className = 'badge-active'; badge.textContent = '● เปิด';
            btn.className = btn.className.replace('success','warning');
        } else {
            badge.className = 'badge-inactive'; badge.textContent = '○ ปิด';
            btn.className = btn.className.replace('warning','success');
        }
    });
}

function deleteAccount(id) {
    if (!confirm('ลบบัญชีนี้? ข้อความใน Inbox ที่เชื่อมกับบัญชีนี้จะยังอยู่แต่ไม่มี account reference')) return;
    fetch('', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({ajax:'delete', id})
    }).then(r=>r.json()).then(res => { if (res.ok) document.getElementById('acc-row-'+id).remove(); });
}

function copyWebhook(el) {
    navigator.clipboard.writeText(el.textContent.trim()).then(() => {
        const orig = el.style.background;
        el.style.background = '#d4edda';
        setTimeout(() => el.style.background = orig, 1200);
    });
}

function togglePass(id, btn) {
    const el = document.getElementById(id);
    el.type = el.type === 'password' ? 'text' : 'password';
    btn.textContent = el.type === 'password' ? '👁' : '🙈';
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
