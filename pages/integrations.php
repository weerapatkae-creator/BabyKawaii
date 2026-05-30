<?php
require_once __DIR__ . '/../config/database.php';
requireAdmin();
$pdo = getDB();
$msg = '';
$msgType = 'success';

// ── Handle actions ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Generate new API Key
    if (isset($_POST['action']) && $_POST['action'] === 'gen_api_key') {
        $key = 'bk_' . bin2hex(random_bytes(24));
        $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('api_key',?) ON DUPLICATE KEY UPDATE setting_value=?")
            ->execute([$key, $key]);
        $msg = 'สร้าง API Key ใหม่เรียบร้อย — กรุณาอัปเดต n8n workflows ด้วย key ใหม่';
        $msgType = 'warning';
    }

    // Test LINE notification
    if (isset($_POST['action']) && $_POST['action'] === 'test_line') {
        header('Content-Type: application/json');
        $ok = sendLineNotify("🧪 ทดสอบแจ้งเตือน BabyKawaii Admin\n\nระบบแจ้งเตือน LINE ทำงานปกติ ✅");
        echo json_encode(['ok' => $ok, 'msg' => $ok ? 'ส่งสำเร็จ! เช็ค LINE ได้เลย' : 'ส่งไม่ได้ — ตรวจสอบ Token และ User ID']);
        exit;
    }

    // Save LINE & n8n settings
    if (isset($_POST['action']) && $_POST['action'] === 'save_integration') {
        $fields = [
            'line_notify_token',
            'line_channel_access_token',
            'line_channel_secret',
            'line_admin_user_id',
            'n8n_base_url',
            'webhook_secret',
        ];
        foreach ($fields as $field) {
            $val = trim($_POST[$field] ?? '');
            // Allow saving empty string to clear a value (if user explicitly blanked it)
            $pdo->prepare("INSERT INTO settings (setting_key, setting_value, setting_group) VALUES (?,?,'integration') ON DUPLICATE KEY UPDATE setting_value=?")
                ->execute([$field, $val, $val]);
        }
        $msg = 'บันทึกการตั้งค่าเรียบร้อย';
        $msgType = 'success';
        header('Location: ' . SITE_URL . '/pages/integrations.php?msg=saved');
        exit;
    }

    // Run n8n database migration
    if (isset($_POST['action']) && $_POST['action'] === 'run_migration') {
        try {
            // Create line_chat_sessions table
            $pdo->exec("CREATE TABLE IF NOT EXISTS line_chat_sessions (
                id            INT AUTO_INCREMENT PRIMARY KEY,
                line_user_id  VARCHAR(100) NOT NULL UNIQUE,
                display_name  VARCHAR(200),
                state         VARCHAR(50)  DEFAULT 'idle',
                data          JSON,
                updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Ensure integration settings rows exist
            $integrationSettings = [
                'api_key', 'line_notify_token', 'line_channel_access_token',
                'line_channel_secret', 'line_admin_user_id', 'n8n_base_url', 'webhook_secret'
            ];
            foreach ($integrationSettings as $key) {
                $pdo->prepare("INSERT IGNORE INTO settings (setting_key, setting_value, setting_group) VALUES (?,'','integration')")
                    ->execute([$key]);
            }
            header('Location: ' . SITE_URL . '/pages/integrations.php?msg=migrated');
            exit;
        } catch (Exception $e) {
            $msg = 'Migration ล้มเหลว: ' . $e->getMessage();
            $msgType = 'danger';
        }
    }
}

$pageTitle = 'เชื่อมต่อระบบ';
require_once __DIR__ . '/../includes/header.php';

// ── Load settings ─────────────────────────────────────────────────────────────
$keys = ['api_key','line_notify_token','line_channel_access_token','line_channel_secret',
         'line_admin_user_id','n8n_base_url','webhook_secret'];
$cfg = [];
foreach ($keys as $k) {
    $cfg[$k] = getSetting($k, '');
}

// Mask API key for display (show first 10 chars + ***)
function maskKey($key) {
    if (!$key) return '';
    return substr($key, 0, 12) . str_repeat('*', max(0, strlen($key) - 12));
}

// Determine base webhook URL
$n8nBase = rtrim($cfg['n8n_base_url'] ?: 'https://YOUR_N8N_SERVER', '/');
$thisUrl  = rtrim(SITE_URL, '/');

// Check if migration has been run (line_chat_sessions table exists)
$migrationDone = false;
try {
    $pdo->query("SELECT 1 FROM line_chat_sessions LIMIT 1");
    $migrationDone = true;
} catch (Exception $e) { /* table not yet created */ }
?>

<div class="container-fluid fade-in">
    <div class="page-header">
        <div>
            <h1 class="page-title">🔌 เชื่อมต่อระบบ</h1>
            <p class="page-subtitle">ตั้งค่า n8n · LINE Notify · LINE Messaging API</p>
        </div>
        <div class="page-actions">
            <a href="<?= SITE_URL ?>/pages/settings.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> กลับ
            </a>
        </div>
    </div>

    <?php
    $flashMsg = $msg;
    $flashType = $msgType;
    if (!$flashMsg && isset($_GET['msg'])) {
        $map = [
            'saved'    => ['success', '✅ บันทึกการตั้งค่าเรียบร้อย'],
            'migrated' => ['success', '✅ Migration สำเร็จ — สร้างตาราง line_chat_sessions เรียบร้อย'],
            'keygen'   => ['warning', '⚠️ สร้าง API Key ใหม่แล้ว — อัปเดต n8n ด้วยนะ'],
        ];
        [$flashType, $flashMsg] = $map[$_GET['msg']] ?? ['info', ''];
    }
    if ($flashMsg):
    ?>
    <div class="alert alert-<?= $flashType ?> alert-auto"><?= $flashMsg ?></div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- ── LEFT COLUMN ──────────────────────────────────────────────── -->
        <div class="col-lg-8">

            <!-- API Key Card -->
            <div class="card mb-4">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span class="card-title">🔑 API Key</span>
                    <span class="badge bg-<?= $cfg['api_key'] ? 'success' : 'danger' ?>">
                        <?= $cfg['api_key'] ? 'ตั้งค่าแล้ว' : 'ยังไม่มี' ?>
                    </span>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        ใช้ API Key นี้ใน n8n workflows (header <code>X-API-Key</code>) และใช้เรียก BabyKawaii API โดยตรง
                    </p>

                    <?php if ($cfg['api_key']): ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">API Key ปัจจุบัน</label>
                        <div class="input-group">
                            <input type="text" class="form-control font-monospace"
                                   id="apiKeyDisplay"
                                   value="<?= htmlspecialchars($cfg['api_key']) ?>"
                                   readonly>
                            <button class="btn btn-outline-secondary" type="button"
                                    onclick="copyToClipboard('apiKeyDisplay', this)" title="คัดลอก">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-warning">⚠️ ยังไม่มี API Key — กรุณาสร้างก่อน</div>
                    <?php endif; ?>

                    <form method="POST" onsubmit="return confirm('สร้าง API Key ใหม่จะทำให้ key เก่าใช้ไม่ได้ทันที ยืนยัน?')">
                        <input type="hidden" name="action" value="gen_api_key">
                        <button type="submit" class="btn btn-<?= $cfg['api_key'] ? 'outline-warning' : 'primary' ?>">
                            <i class="fas fa-rotate me-1"></i>
                            <?= $cfg['api_key'] ? 'สร้าง API Key ใหม่' : 'สร้าง API Key' ?>
                        </button>
                    </form>
                </div>
            </div>

            <!-- LINE Settings Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <span class="card-title">💚 LINE Settings</span>
                </div>
                <div class="card-body">
                    <form method="POST" id="integrationForm">
                        <input type="hidden" name="action" value="save_integration">

                        <!-- LINE Notify — deprecated -->
                        <div class="alert alert-warning d-flex gap-2 align-items-start mb-4" style="font-size:0.85rem;">
                            <span style="font-size:1.1rem;">⚠️</span>
                            <div>
                                <strong>LINE Notify ปิดให้บริการแล้วตั้งแต่ 31 มีนาคม 2568</strong><br>
                                การแจ้งเตือนระบบย้ายไปใช้ <strong>LINE Messaging API</strong> แทน<br>
                                กรอก <em>Channel Access Token</em> + <em>User ID Admin</em> ด้านล่างแทนได้เลย
                            </div>
                        </div>

                        <hr>

                        <!-- LINE Messaging API -->
                        <div class="integration-section mb-3">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <span class="intg-icon intg-msg">🤖</span>
                                <div>
                                    <div class="fw-semibold">LINE OA แจ้งเตือน</div>
                                    <div class="text-muted" style="font-size:0.78rem;">
                                        แจ้งเตือนทาง LINE ทุกครั้งที่ลูกค้าส่งข้อความ — ต้องมี LINE Official Account
                                    </div>
                                </div>
                                <a href="https://developers.line.biz/console/" target="_blank"
                                   class="btn btn-sm btn-outline-success ms-auto">
                                    <i class="fas fa-external-link-alt me-1"></i> LINE Console
                                </a>
                            </div>

                            <!-- ขั้นตอนการตั้งค่า -->
                            <div class="alert alert-info py-2 px-3 mb-3" style="font-size:0.82rem;">
                                <strong>📋 ขั้นตอนตั้งค่า (ครั้งแรก)</strong>
                                <ol class="mb-0 mt-1 ps-3" style="line-height:2;">
                                    <li>สร้าง <a href="https://manager.line.biz/" target="_blank">LINE Official Account</a> (ฟรี)</li>
                                    <li>เปิด <a href="https://developers.line.biz/console/" target="_blank">LINE Developers Console</a> → เลือก Provider → เลือก Channel → <strong>Messaging API</strong></li>
                                    <li>คัดลอก <strong>Channel Secret</strong> (Basic Settings) และ <strong>Channel Access Token</strong> (Messaging API) มาใส่ด้านล่าง แล้วกด <strong>บันทึก</strong></li>
                                    <li>ใส่ <strong>Webhook URL</strong> นี้ใน LINE Console → Messaging API → Webhook URL แล้วกด <strong>Verify</strong></li>
                                    <li>เปิด LINE โทรศัพท์ → เพิ่มเพื่อน LINE OA ของคุณ → พิมพ์ <code>id</code> → bot จะตอบ User ID มาให้</li>
                                    <li>คัดลอก User ID มาใส่ในช่อง <strong>Admin LINE User ID</strong> แล้วกด <strong>บันทึก</strong></li>
                                    <li>กดปุ่ม <strong>ทดสอบส่ง</strong> เพื่อยืนยันว่าแจ้งเตือนทำงาน ✅</li>
                                </ol>
                            </div>

                            <!-- Webhook URL -->
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Webhook URL <span class="text-muted fw-normal">(ใส่ใน LINE Console)</span></label>
                                <div class="input-group">
                                    <input type="text" class="form-control font-monospace bg-light"
                                           id="lineWebhookUrl"
                                           value="<?= SITE_URL ?>/api/line-webhook.php" readonly>
                                    <button type="button" class="btn btn-outline-secondary"
                                            onclick="copyText('lineWebhookUrl', this)">
                                        <i class="fas fa-copy"></i> คัดลอก
                                    </button>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Channel Access Token</label>
                                    <input type="text" name="line_channel_access_token"
                                           class="form-control font-monospace"
                                           placeholder="Long-lived channel access token"
                                           value="<?= htmlspecialchars($cfg['line_channel_access_token']) ?>">
                                    <div class="form-text">Messaging API → Issue → Long-lived token</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Channel Secret</label>
                                    <input type="text" name="line_channel_secret"
                                           class="form-control font-monospace"
                                           placeholder="Channel secret"
                                           value="<?= htmlspecialchars($cfg['line_channel_secret']) ?>">
                                    <div class="form-text">Basic Settings → Channel Secret</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Admin LINE User ID</label>
                                    <div class="input-group">
                                        <input type="text" name="line_admin_user_id"
                                               id="lineAdminUserId"
                                               class="form-control font-monospace"
                                               placeholder="Uxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                                               value="<?= htmlspecialchars($cfg['line_admin_user_id']) ?>">
                                        <?php if ($cfg['line_admin_user_id']): ?>
                                        <span class="input-group-text text-success" title="ตั้งค่าแล้ว">✅</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="form-text">พิมพ์ <code>id</code> ใน LINE OA → bot จะตอบ User ID มาให้</div>
                                </div>
                                <div class="col-md-6 d-flex align-items-end">
                                    <?php if ($cfg['line_channel_access_token'] && $cfg['line_admin_user_id']): ?>
                                    <button type="button" class="btn btn-success w-100" onclick="testLineNotify()">
                                        <i class="fas fa-paper-plane me-1"></i> ทดสอบส่งแจ้งเตือน LINE
                                    </button>
                                    <?php else: ?>
                                    <div class="text-muted" style="font-size:0.8rem;">
                                        ⬅️ กรอก Token + User ID แล้วบันทึกก่อน จึงจะทดสอบได้
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <!-- n8n Config -->
                        <div class="integration-section mb-3">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <span class="intg-icon intg-n8n">⚙️</span>
                                <div>
                                    <div class="fw-semibold">n8n Server</div>
                                    <div class="text-muted" style="font-size:0.78rem;">
                                        URL ของ n8n ที่ติดตั้งบน VPS/Server
                                    </div>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label">n8n Base URL</label>
                                    <div class="input-group">
                                        <input type="url" name="n8n_base_url" class="form-control"
                                               placeholder="https://n8n.yourdomain.com"
                                               value="<?= htmlspecialchars($cfg['n8n_base_url']) ?>">
                                        <?php if ($cfg['n8n_base_url'] && $cfg['api_key']): ?>
                                        <button type="button" class="btn btn-outline-secondary"
                                                onclick="testN8nWebhook()" title="ทดสอบส่ง event">
                                            <i class="fas fa-paper-plane"></i> ทดสอบ
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                    <div class="form-text">ไม่ต้องใส่ / ท้าย URL</div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> บันทึกการตั้งค่า
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Webhook URLs Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <span class="card-title">🌐 Webhook & API Endpoints</span>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        URL เหล่านี้ใช้กรอกใน n8n และ LINE Developers Console
                    </p>
                    <div class="table-responsive">
                        <table class="table table-sm endpoint-table">
                            <thead>
                                <tr>
                                    <th>ชื่อ</th>
                                    <th>URL</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $endpoints = [
                                    ['🔔 n8n Webhook (Events)',    $n8nBase . '/webhook/babykawaii-events',  'POST จาก BabyKawaii → n8n'],
                                    ['🤖 LINE Bot Webhook',         $n8nBase . '/webhook/line-bot',           'ตั้งใน LINE Console Webhook URL'],
                                    ['📦 Orders API',               $thisUrl  . '/api/v1/orders.php',         'GET / POST ออเดอร์'],
                                    ['🛍️ Products API',             $thisUrl  . '/api/v1/products.php',       'GET รายการสินค้า'],
                                    ['📊 Sales API',                $thisUrl  . '/api/v1/sales.php',          'GET ยอดขาย'],
                                    ['📦 Stock API',                $thisUrl  . '/api/v1/stock.php',          'GET สถานะสต็อก'],
                                    ['💬 Chat Session API',         $thisUrl  . '/api/v1/chat.php',           'GET / POST / DELETE session'],
                                    ['📲 Notify API',               $thisUrl  . '/api/v1/notify.php',         'POST ส่ง LINE message'],
                                ];
                                foreach ($endpoints as $i => [$label, $url, $note]):
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold" style="font-size:0.85rem;"><?= $label ?></div>
                                        <div class="text-muted" style="font-size:0.75rem;"><?= $note ?></div>
                                    </td>
                                    <td>
                                        <code class="endpoint-url" id="ep<?= $i ?>"><?= htmlspecialchars($url) ?></code>
                                    </td>
                                    <td>
                                        <button class="btn btn-xs btn-outline-secondary"
                                                onclick="copyToClipboard('ep<?= $i ?>', this)">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>

        <!-- ── RIGHT COLUMN ─────────────────────────────────────────────── -->
        <div class="col-lg-4">

            <!-- Status Card -->
            <div class="card mb-4">
                <div class="card-header"><span class="card-title">📡 สถานะการเชื่อมต่อ</span></div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php
                        $statusItems = [
                            ['API Key',              (bool)$cfg['api_key']],
                            ['LINE Notify',          (bool)$cfg['line_notify_token']],
                            ['LINE Channel Token',   (bool)$cfg['line_channel_access_token']],
                            ['LINE Channel Secret',  (bool)$cfg['line_channel_secret']],
                            ['LINE Admin User ID',   (bool)$cfg['line_admin_user_id']],
                            ['n8n Base URL',         (bool)$cfg['n8n_base_url']],
                        ];
                        foreach ($statusItems as [$label, $ok]):
                        ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                            <span style="font-size:0.85rem;"><?= $label ?></span>
                            <span class="badge bg-<?= $ok ? 'success' : 'secondary' ?>">
                                <?= $ok ? '✓ ตั้งค่าแล้ว' : '— ยังไม่ตั้งค่า' ?>
                            </span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- n8n Workflows Download Card -->
            <div class="card mb-4">
                <div class="card-header"><span class="card-title">⬇️ n8n Workflows</span></div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        Import ไฟล์ JSON เข้า n8n แล้วแทนที่ค่า placeholder ด้วยข้อมูลจริงของคุณ
                    </p>

                    <?php
                    // คำอธิบายที่อ่านง่ายต่อชื่อไฟล์ (ไฟล์ที่ไม่อยู่ในแมพจะ fallback เป็นชื่อไฟล์)
                    $wfMeta = [
                        '01-admin-line-notifications.json' => ['📢 Admin LINE Notifications', 'แจ้งเตือนออเดอร์ใหม่ + สต็อกใกล้หมด ผ่าน LINE'],
                        '02-daily-sales-report.json'       => ['📊 Daily Sales Report',       'รายงานยอดขายประจำวันส่ง LINE ทุก 8:00 น.'],
                        '03-social-dm-intake.json'         => ['💬 Social DM Intake',          'ดึงข้อความจาก FB/IG/TikTok เข้า Inbox'],
                        '04-fb-ig-chatbot.json'            => ['🤖 FB / IG Chatbot',           'ตอบลูกค้าอัตโนมัติบน Facebook & Instagram'],
                        '05-tiktok-order-sync.json'        => ['🛒 TikTok Order Sync',         'ซิงค์ออเดอร์จาก TikTok Shop เข้าระบบ'],
                        '06-calendar-ai-autopublish.json'  => ['✨ Calendar AI Auto-Publish',  'โพสต์ตามปฏิทิน + AI เขียน caption ให้อัตโนมัติเมื่อเว้นว่าง'],
                        '07-n8n-error-alert.json'          => ['⚠️ n8n Error Alert',           'แจ้งเตือนผ่าน LINE เมื่อ workflow ใดล้มเหลว'],
                        'calendar-autopublish.json'        => ['📅 Calendar Auto-Publish',     'โพสต์คอนเทนต์ตามปฏิทินอัตโนมัติทุกแพลตฟอร์ม'],
                    ];
                    $wfDir   = __DIR__ . '/../n8n-workflows';
                    $wfFiles = glob($wfDir . '/*.json') ?: [];
                    sort($wfFiles);
                    $workflows = [];
                    foreach ($wfFiles as $path) {
                        $file = basename($path);
                        [$title, $desc] = $wfMeta[$file] ?? ['🔧 ' . $file, 'n8n workflow'];
                        $workflows[] = [$file, $title, $desc];
                    }
                    $wfCount = count($workflows);
                    foreach ($workflows as [$file, $title, $desc]):
                    ?>
                    <div class="workflow-item mb-3">
                        <div class="d-flex align-items-start gap-2">
                            <div class="flex-grow-1">
                                <div class="fw-semibold" style="font-size:0.88rem;"><?= $title ?></div>
                                <div class="text-muted" style="font-size:0.76rem;"><?= $desc ?></div>
                            </div>
                            <a href="<?= SITE_URL ?>/n8n-workflows/<?= $file ?>"
                               download
                               class="btn btn-xs btn-outline-primary flex-shrink-0">
                                <i class="fas fa-download me-1"></i> JSON
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Migration Card -->
            <div class="card mb-4 <?= $migrationDone ? 'border-success' : 'border-warning' ?>" style="border-width:2px!important">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span class="card-title">🗄️ Database Migration</span>
                    <span class="badge bg-<?= $migrationDone ? 'success' : 'warning text-dark' ?>">
                        <?= $migrationDone ? '✓ Done' : '! ยังไม่ได้รัน' ?>
                    </span>
                </div>
                <div class="card-body">
                    <?php if ($migrationDone): ?>
                    <p class="text-success small mb-0">
                        ✅ ตาราง <code>line_chat_sessions</code> พร้อมใช้งาน
                    </p>
                    <?php else: ?>
                    <p class="text-warning small mb-2">
                        ⚠️ ยังไม่ได้รัน migration — Chatbot จะใช้งานไม่ได้
                    </p>
                    <form method="POST">
                        <input type="hidden" name="action" value="run_migration">
                        <button type="submit" class="btn btn-warning btn-sm w-100">
                            <i class="fas fa-database me-1"></i> รัน Migration ตอนนี้
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Event Hooks Reference Card -->
            <div class="card mb-4">
                <div class="card-header"><span class="card-title">⚡ Event Hooks</span></div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0" style="font-size:0.78rem;">
                        <thead class="table-light">
                            <tr><th>การกระทำ</th><th>Event</th><th>LINE</th></tr>
                        </thead>
                        <tbody>
                            <tr><td>สร้างออเดอร์ใหม่ (Admin)</td><td><code>new_order</code></td><td>✅</td></tr>
                            <tr><td>สร้างออเดอร์ (LINE Bot)</td><td><code>new_order</code></td><td>✅</td></tr>
                            <tr><td>อัปเดตสถานะออเดอร์</td><td><code>order_status_changed</code></td><td>🚚 shipped</td></tr>
                            <tr><td>ปรับสต็อก → ต่ำกว่าขั้นต่ำ</td><td><code>stock_alert</code></td><td>✅</td></tr>
                            <tr><td>รับสินค้าเพิ่ม (จาก 0)</td><td><code>stock_restocked</code></td><td>—</td></tr>
                            <tr class="table-light"><td><em>n8n รายวัน 8:00</em></td><td><code>schedule</code></td><td>📊 report</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Setup Guide Card -->
            <div class="card mb-4">
                <div class="card-header"><span class="card-title">📖 วิธีติดตั้ง</span></div>
                <div class="card-body" style="font-size:0.82rem;">
                    <div class="setup-steps">
                        <div class="setup-step">
                            <span class="step-num">1</span>
                            <div>
                                <strong>สร้าง API Key</strong><br>
                                กดปุ่ม "สร้าง API Key" ด้านซ้าย
                            </div>
                        </div>
                        <div class="setup-step">
                            <span class="step-num">2</span>
                            <div>
                                <strong>LINE Notify Token</strong><br>
                                ไปที่ <a href="https://notify-bot.line.me/th/" target="_blank">notify-bot.line.me</a>
                                → My page → Generate token
                            </div>
                        </div>
                        <div class="setup-step">
                            <span class="step-num">3</span>
                            <div>
                                <strong>LINE Messaging API</strong><br>
                                ไปที่ <a href="https://developers.line.biz/console/" target="_blank">LINE Developers</a>
                                → สร้าง Provider/Channel → เปิด Messaging API
                            </div>
                        </div>
                        <div class="setup-step">
                            <span class="step-num">4</span>
                            <div>
                                <strong>ติดตั้ง n8n บน VPS</strong><br>
                                <code>npm install -g n8n && n8n</code><br>
                                หรือใช้ Docker:<br>
                                <code>docker run -p 5678:5678 n8nio/n8n</code>
                            </div>
                        </div>
                        <div class="setup-step">
                            <span class="step-num">5</span>
                            <div>
                                <strong>Import Workflows</strong><br>
                                ดาวน์โหลด JSON <?= $wfCount ?> ไฟล์ด้านบน → n8n → Import workflow → แทนที่<br>
                                <code class="small">YOUR_SITE_URL</code> → URL เว็บนี้<br>
                                <code class="small">YOUR_API_KEY</code> → API Key ด้านบน<br>
                                <code class="small">YOUR_LINE_NOTIFY_TOKEN</code> → token LINE<br>
                                <code class="small">YOUR_LINE_CHANNEL_ACCESS_TOKEN</code>
                            </div>
                        </div>
                        <div class="setup-step">
                            <span class="step-num">6</span>
                            <div>
                                <strong>ตั้ง LINE Webhook URL</strong><br>
                                LINE Console → Messaging API → Webhook URL:<br>
                                <code class="small"><?= htmlspecialchars($n8nBase) ?>/webhook/line-bot</code>
                            </div>
                        </div>
                        <div class="setup-step">
                            <span class="step-num">7</span>
                            <div>
                                <strong>กรอก n8n Base URL</strong><br>
                                กรอก URL n8n ของคุณในฟอร์มด้านซ้าย แล้วบันทึก
                            </div>
                        </div>
                        <div class="setup-step">
                            <span class="step-num">8</span>
                            <div>
                                <strong>Activate Workflows</strong><br>
                                เปิด workflows ทั้ง <?= $wfCount ?> ใน n8n (toggle Active)
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div><!-- /row -->
</div>

<!-- Toast notification -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:9999">
    <div id="copyToast" class="toast align-items-center border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body">✅ คัดลอกแล้ว!</div>
            <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<style>
/* Integration page styles */
.intg-icon {
    font-size: 1.4rem;
    flex-shrink: 0;
}
.btn-xs {
    padding: 0.2rem 0.5rem;
    font-size: 0.75rem;
}
.endpoint-table code.endpoint-url {
    font-size: 0.73rem;
    color: #c0392b;
    word-break: break-all;
}
.endpoint-table td { vertical-align: middle; }

/* Setup steps */
.setup-steps { display: flex; flex-direction: column; gap: 0.75rem; }
.setup-step {
    display: flex;
    gap: 0.6rem;
    align-items: flex-start;
}
.step-num {
    background: var(--primary-color, #e91e8c);
    color: #fff;
    border-radius: 50%;
    width: 22px;
    height: 22px;
    min-width: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.72rem;
    font-weight: 700;
    margin-top: 1px;
}
.workflow-item + .workflow-item {
    padding-top: 0.6rem;
    border-top: 1px dashed #eee;
}
.integration-section { padding: 0.25rem 0; }
</style>

<script>
function copyToClipboard(elId, btn) {
    const el = document.getElementById(elId);
    const text = el.value ?? el.textContent;
    navigator.clipboard.writeText(text.trim()).then(() => {
        const toast = new bootstrap.Toast(document.getElementById('copyToast'), {delay: 1500});
        toast.show();
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check text-success"></i>';
        setTimeout(() => btn.innerHTML = orig, 1500);
    });
}

function testLineNotify() {
    const btn = event.target.closest('button');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังส่ง...';
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'test_line' })
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        if (data.ok) {
            btn.innerHTML = '<i class="fas fa-check"></i> ส่งสำเร็จ! เช็ค LINE ได้เลย';
            btn.classList.replace('btn-success', 'btn-success');
        } else {
            btn.innerHTML = '<i class="fas fa-times"></i> ส่งไม่ได้';
            alert('❌ ' + (data.msg || 'ตรวจสอบ Token และ User ID'));
        }
        setTimeout(() => { btn.disabled = false; btn.innerHTML = orig; }, 3500);
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-times text-danger"></i> Error';
        setTimeout(() => btn.innerHTML = orig, 3000);
    });
}

function copyText(inputId, btn) {
    const el = document.getElementById(inputId);
    if (!el) return;
    const text = el.value ?? el.textContent;
    navigator.clipboard.writeText(text.trim()).then(() => {
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check text-success"></i> คัดลอกแล้ว';
        setTimeout(() => btn.innerHTML = orig, 2000);
    }).catch(() => {
        el.select();
        document.execCommand('copy');
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check text-success"></i> คัดลอกแล้ว';
        setTimeout(() => btn.innerHTML = orig, 2000);
    });
}

function testN8nWebhook() {
    const btn = event.target.closest('button');
    runApiTest(btn,
        '<?= SITE_URL ?>/api/v1/notify.php',
        { type: 'n8n_test' },
        'btn-outline-secondary', 'btn-secondary',
        // Direct fetch to n8n webhook instead
        () => {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            fetch('<?= htmlspecialchars($n8nBase) ?>/webhook/babykawaii-events', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ event: 'test', source: 'admin_integrations', timestamp: Date.now() }),
                signal: AbortSignal.timeout(5000)
            })
            .then(r => {
                btn.disabled = false;
                if (r.ok || r.status < 500) {
                    btn.innerHTML = '<i class="fas fa-check text-success"></i> n8n ตอบรับ!';
                } else {
                    btn.innerHTML = '<i class="fas fa-times text-danger"></i> HTTP ' + r.status;
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-times text-danger"></i> เชื่อมต่อไม่ได้';
                console.warn('n8n test error:', err.message);
            });
        }
    );
}

function runApiTest(btn, url, body, origClass, activeClass, customFn) {
    if (customFn) { customFn(); return; }
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-API-Key': '<?= htmlspecialchars($cfg['api_key']) ?>' },
        body: JSON.stringify(body)
    })
    .then(r => r.json())
    .then(d => {
        btn.disabled = false;
        if (d.ok) {
            btn.innerHTML = '<i class="fas fa-check text-success"></i> สำเร็จ!';
            if (activeClass) btn.className = btn.className.replace(origClass, activeClass);
        } else {
            btn.innerHTML = '<i class="fas fa-times text-danger"></i> ล้มเหลว';
            alert('❌ ' + (d.error ?? 'ไม่สามารถส่งได้'));
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-times text-danger"></i> Error';
        alert('❌ ไม่สามารถเชื่อมต่อ API ได้');
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
