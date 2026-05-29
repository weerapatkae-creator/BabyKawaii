<?php
$pageTitle = 'System Test';
require_once __DIR__ . '/../config/database.php';
requireAdmin();
$pdo = getDB();

// ─── Test runner ──────────────────────────────────────────────────────────────
$results = [];

function test(string $group, string $name, callable $fn): array {
    $start = microtime(true);
    try {
        $result = $fn();
        $ms     = round((microtime(true) - $start) * 1000, 1);
        return [
            'group'  => $group,
            'name'   => $name,
            'status' => $result['status'] ?? 'pass',   // pass | warn | fail
            'msg'    => $result['msg']    ?? '',
            'detail' => $result['detail'] ?? '',
            'ms'     => $ms,
        ];
    } catch (Throwable $e) {
        $ms = round((microtime(true) - $start) * 1000, 1);
        return [
            'group'  => $group,
            'name'   => $name,
            'status' => 'fail',
            'msg'    => $e->getMessage(),
            'detail' => '',
            'ms'     => $ms,
        ];
    }
}

// ════════════════════════════════════════════════════════════
//  1. PHP Environment
// ════════════════════════════════════════════════════════════
$results[] = test('PHP', 'PHP Version ≥ 8.0', function() {
    $v = PHP_VERSION;
    if (version_compare($v, '8.0', '<'))
        return ['status'=>'fail','msg'=>"PHP $v — ต้องการ 8.0 ขึ้นไป"];
    return ['status'=>'pass','msg'=>"PHP $v"];
});

foreach (['pdo','pdo_mysql','curl','json','mbstring','openssl','gd','fileinfo'] as $ext) {
    $results[] = test('PHP', "Extension: $ext", function() use ($ext) {
        if (!extension_loaded($ext))
            return ['status'=>'fail','msg'=>"ไม่มี extension $ext"];
        return ['status'=>'pass','msg'=>'โหลดแล้ว'];
    });
}

$results[] = test('PHP', 'max_execution_time ≥ 30s', function() {
    $v = (int)ini_get('max_execution_time');
    if ($v > 0 && $v < 30)
        return ['status'=>'warn','msg'=>"{$v}s — แนะนำ 60s ขึ้นไป"];
    return ['status'=>'pass','msg'=>($v === 0 ? 'unlimited' : "{$v}s")];
});

$results[] = test('PHP', 'upload_max_filesize ≥ 10M', function() {
    $raw = ini_get('upload_max_filesize');
    $mb  = (int)$raw;
    if (str_contains(strtolower($raw),'g')) $mb *= 1024;
    if ($mb < 10)
        return ['status'=>'warn','msg'=>"$raw — แนะนำ 10M ขึ้นไป"];
    return ['status'=>'pass','msg'=>$raw];
});

// ════════════════════════════════════════════════════════════
//  2. Config
// ════════════════════════════════════════════════════════════
$results[] = test('Config', 'config/local.php มีอยู่', function() {
    if (!file_exists(__DIR__ . '/../config/local.php'))
        return ['status'=>'warn','msg'=>'ไม่พบ local.php — ใช้ค่า default (ดีสำหรับ localhost)'];
    return ['status'=>'pass','msg'=>'พบ local.php'];
});

$results[] = test('Config', 'SITE_URL ไม่ใช่ localhost', function() {
    if (str_contains(SITE_URL, 'localhost'))
        return ['status'=>'warn','msg'=>'SITE_URL ยังเป็น localhost — ต้องเปลี่ยนก่อน deploy'];
    return ['status'=>'pass','msg'=>SITE_URL];
});

$results[] = test('Config', 'HTTPS', function() {
    if (!str_starts_with(SITE_URL, 'https://'))
        return ['status'=>'warn','msg'=>'SITE_URL ยังไม่ใช้ HTTPS'];
    return ['status'=>'pass','msg'=>'ใช้ HTTPS แล้ว'];
});

// ════════════════════════════════════════════════════════════
//  3. Database Connection & Tables
// ════════════════════════════════════════════════════════════
$results[] = test('Database', 'เชื่อมต่อ MySQL', function() {
    $pdo = getDB();
    $v   = $pdo->query("SELECT VERSION()")->fetchColumn();
    return ['status'=>'pass','msg'=>"MySQL $v"];
});

$requiredTables = [
    'admin_users','categories','products','stock','stock_movements',
    'platforms','orders','order_items','media','promotions',
    'content_calendar','settings','customers','conversations',
    'messages','quick_replies','line_chat_sessions',
];
foreach ($requiredTables as $tbl) {
    $results[] = test('Database', "Table: $tbl", function() use ($tbl) {
        $pdo = getDB();
        $exists = $pdo->query("SHOW TABLES LIKE '$tbl'")->fetchColumn();
        if (!$exists) return ['status'=>'fail','msg'=>"ตาราง $tbl ไม่มี — รัน setup-complete.sql"];
        $cnt = $pdo->query("SELECT COUNT(*) FROM `$tbl`")->fetchColumn();
        return ['status'=>'pass','msg'=>"$cnt rows"];
    });
}

$results[] = test('Database', 'Write/Read/Delete test', function() {
    $pdo = getDB();
    $key = '__system_test_' . time();
    $pdo->prepare("INSERT INTO settings (setting_key,setting_value,setting_group) VALUES (?,?,'test')")
        ->execute([$key,'ok']);
    $val = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key=?");
    $val->execute([$key]);
    $val = $val->fetchColumn();
    $pdo->prepare("DELETE FROM settings WHERE setting_key=?")->execute([$key]);
    if ($val !== 'ok') return ['status'=>'fail','msg'=>'Write/Read ไม่ตรงกัน'];
    return ['status'=>'pass','msg'=>'Write → Read → Delete สำเร็จ'];
});

// ════════════════════════════════════════════════════════════
//  4. File System
// ════════════════════════════════════════════════════════════
$uploadDirs = [
    'assets/uploads/',
    'assets/uploads/products/',
];
foreach ($uploadDirs as $dir) {
    $results[] = test('Files', "เขียนได้: $dir", function() use ($dir) {
        $path = __DIR__ . '/../' . $dir;
        if (!is_dir($path)) {
            @mkdir($path, 0775, true);
            if (!is_dir($path)) return ['status'=>'fail','msg'=>"สร้าง directory ไม่ได้: $dir"];
        }
        if (!is_writable($path)) return ['status'=>'fail','msg'=>"ไม่มีสิทธิ์เขียน: $dir"];
        // Try write a temp file
        $tmp = $path . '.write_test_' . time();
        if (file_put_contents($tmp, '1') === false) return ['status'=>'fail','msg'=>"เขียนไฟล์ไม่ได้: $dir"];
        unlink($tmp);
        return ['status'=>'pass','msg'=>'อ่าน/เขียนได้'];
    });
}

$results[] = test('Files', 'assets/css/style.css', function() {
    $f = __DIR__ . '/../assets/css/style.css';
    if (!file_exists($f)) return ['status'=>'fail','msg'=>'ไม่พบ style.css'];
    return ['status'=>'pass','msg'=>round(filesize($f)/1024,1) . ' KB'];
});

$results[] = test('Files', 'assets/js/main.js', function() {
    $f = __DIR__ . '/../assets/js/main.js';
    if (!file_exists($f)) return ['status'=>'fail','msg'=>'ไม่พบ main.js'];
    return ['status'=>'pass','msg'=>round(filesize($f)/1024,1) . ' KB'];
});

$results[] = test('Files', 'assets/images/logo.svg', function() {
    $f = __DIR__ . '/../assets/images/logo.svg';
    if (!file_exists($f)) return ['status'=>'fail','msg'=>'ไม่พบ logo.svg'];
    return ['status'=>'pass','msg'=>round(filesize($f)/1024,1) . ' KB'];
});

// ════════════════════════════════════════════════════════════
//  5. Settings in DB
// ════════════════════════════════════════════════════════════
$results[] = test('Settings', 'shop_name', function() {
    $v = getSetting('shop_name','');
    if (!$v) return ['status'=>'warn','msg'=>'ยังไม่ได้ตั้งชื่อร้าน'];
    return ['status'=>'pass','msg'=>$v];
});

$results[] = test('Settings', 'shop_phone', function() {
    $v = getSetting('shop_phone','');
    if (!$v) return ['status'=>'warn','msg'=>'ยังไม่ได้ตั้งเบอร์โทร'];
    return ['status'=>'pass','msg'=>$v];
});

$results[] = test('Settings', 'LINE Channel Access Token', function() {
    $v = getSetting('line_channel_access_token','');
    if (!$v) return ['status'=>'warn','msg'=>'ยังไม่ได้ตั้งค่า — การแจ้งเตือน LINE จะไม่ทำงาน'];
    return ['status'=>'pass','msg'=>substr($v,0,10).'...'];
});

$results[] = test('Settings', 'LINE Admin User ID', function() {
    $v = getSetting('line_admin_user_id','');
    if (!$v) return ['status'=>'warn','msg'=>'ยังไม่ได้ตั้งค่า — การแจ้งเตือน LINE จะไม่ทำงาน'];
    return ['status'=>'pass','msg'=>$v];
});

$results[] = test('Settings', 'n8n Base URL', function() {
    $v = getSetting('n8n_base_url','');
    if (!$v) return ['status'=>'warn','msg'=>'ยังไม่ได้ตั้งค่า — Automation workflows จะไม่ทำงาน'];
    return ['status'=>'pass','msg'=>$v];
});

// ════════════════════════════════════════════════════════════
//  6. External Services
// ════════════════════════════════════════════════════════════
$results[] = test('External', 'cURL ออก internet ได้', function() {
    if (!function_exists('curl_init')) return ['status'=>'fail','msg'=>'ไม่มี cURL'];
    $ch = curl_init('https://httpbin.org/get');
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>8,CURLOPT_SSL_VERIFYPEER=>false]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($err) return ['status'=>'fail','msg'=>"cURL error: $err"];
    if ($code !== 200) return ['status'=>'warn','msg'=>"HTTP $code — อาจถูก firewall บล็อก"];
    return ['status'=>'pass','msg'=>"HTTP $code OK"];
});

$results[] = test('External', 'LINE Messaging API (ping)', function() {
    $token = getSetting('line_channel_access_token','');
    if (!$token) return ['status'=>'warn','msg'=>'ข้าม — ยังไม่ได้ตั้งค่า token'];
    $ch = curl_init('https://api.line.me/v2/bot/info');
    curl_setopt_array($ch,[
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_HTTPHEADER=>["Authorization: Bearer $token"],
        CURLOPT_TIMEOUT=>8,
        CURLOPT_SSL_VERIFYPEER=>false,
    ]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code === 200) {
        $info = json_decode($raw,true);
        return ['status'=>'pass','msg'=>'เชื่อมต่อสำเร็จ — Bot: '.($info['basicId']??'OK')];
    }
    $msg = json_decode($raw,true)['message'] ?? "HTTP $code";
    return ['status'=>'fail','msg'=>"LINE API: $msg"];
});

$results[] = test('External', 'n8n Webhook (ping)', function() {
    $url = getSetting('n8n_base_url','');
    if (!$url) return ['status'=>'warn','msg'=>'ข้าม — ยังไม่ได้ตั้งค่า n8n URL'];
    $ch = curl_init(rtrim($url,'/') . '/healthz');
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>6,CURLOPT_SSL_VERIFYPEER=>false]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($err) return ['status'=>'fail','msg'=>"cURL: $err"];
    if ($code === 0)   return ['status'=>'fail','msg'=>'เชื่อมต่อ n8n ไม่ได้'];
    if ($code >= 200 && $code < 400) return ['status'=>'pass','msg'=>"HTTP $code"];
    return ['status'=>'warn','msg'=>"HTTP $code — ตรวจสอบ URL อีกครั้ง"];
});

// ════════════════════════════════════════════════════════════
//  7. Auth & Session
// ════════════════════════════════════════════════════════════
$results[] = test('Auth', 'Session ทำงาน', function() {
    if (session_status() !== PHP_SESSION_ACTIVE)
        return ['status'=>'fail','msg'=>'Session ไม่ active'];
    if (!isset($_SESSION['admin_id']))
        return ['status'=>'fail','msg'=>'ไม่มี admin_id ใน session'];
    return ['status'=>'pass','msg'=>'Session ID: '.session_id()];
});

$results[] = test('Auth', 'Admin user ใน DB', function() {
    $pdo  = getDB();
    $id   = $_SESSION['admin_id'] ?? 0;
    $stmt = $pdo->prepare("SELECT username,role FROM admin_users WHERE id=? AND is_active=1");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if (!$user) return ['status'=>'fail','msg'=>'ไม่พบ user ใน DB'];
    return ['status'=>'pass','msg'=>$user['username'].' ('.$user['role'].')'];
});

// ════════════════════════════════════════════════════════════
//  Summarize
// ════════════════════════════════════════════════════════════
$countPass = count(array_filter($results, fn($r) => $r['status']==='pass'));
$countWarn = count(array_filter($results, fn($r) => $r['status']==='warn'));
$countFail = count(array_filter($results, fn($r) => $r['status']==='fail'));
$total     = count($results);
$score     = $total > 0 ? round($countPass / $total * 100) : 0;

// Group
$groups = [];
foreach ($results as $r) $groups[$r['group']][] = $r;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid fade-in" style="max-width:900px;">
    <div class="page-header">
        <div>
            <h1 class="page-title">🧪 System Test</h1>
            <p class="page-subtitle">ตรวจสอบความพร้อมก่อน / หลัง Deploy</p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <span style="font-size:0.85rem;color:var(--text-muted);">รันเมื่อ: <?= date('d/m/Y H:i:s') ?></span>
            <a href="?" class="btn btn-primary btn-sm"><i class="fas fa-rotate me-1"></i> รันใหม่</a>
        </div>
    </div>

    <!-- Score banner -->
    <div class="card mb-4" style="background:<?= $countFail>0?'#fff0f0':($countWarn>0?'#fffde7':'#f0fff4') ?>;border-left:5px solid <?= $countFail>0?'#e53935':($countWarn>0?'#f9a825':'#2e7d32') ?>;">
        <div class="card-body py-3 d-flex align-items-center gap-4 flex-wrap">
            <div style="font-size:2.5rem;font-weight:800;color:<?= $countFail>0?'#e53935':($countWarn>0?'#f9a825':'#2e7d32') ?>;">
                <?= $score ?>%
            </div>
            <div style="flex:1;">
                <div style="font-size:1.05rem;font-weight:700;margin-bottom:4px;">
                    <?php if ($countFail === 0 && $countWarn === 0): ?>
                        ✅ ทุกอย่างพร้อม Deploy!
                    <?php elseif ($countFail === 0): ?>
                        ⚠️ ผ่าน แต่มีคำเตือน <?= $countWarn ?> รายการ
                    <?php else: ?>
                        ❌ พบ <?= $countFail ?> ข้อผิดพลาด — ต้องแก้ก่อน deploy
                    <?php endif; ?>
                </div>
                <div style="font-size:0.85rem;color:#666;">
                    <span class="me-3">✅ ผ่าน: <strong><?= $countPass ?></strong></span>
                    <span class="me-3">⚠️ คำเตือน: <strong><?= $countWarn ?></strong></span>
                    <span>❌ ผิดพลาด: <strong><?= $countFail ?></strong></span>
                    <span class="ms-3 text-muted">/ <?= $total ?> รายการ</span>
                </div>
            </div>
            <!-- Progress bar -->
            <div style="width:180px;">
                <div style="background:#e0e0e0;border-radius:20px;height:12px;overflow:hidden;">
                    <div style="width:<?= $score ?>%;background:<?= $countFail>0?'#e53935':($countWarn>0?'#f9a825':'#2e7d32') ?>;height:100%;border-radius:20px;transition:width .5s;"></div>
                </div>
                <div style="font-size:0.72rem;color:#888;text-align:center;margin-top:4px;"><?= $countPass ?>/<?= $total ?> passed</div>
            </div>
        </div>
    </div>

    <!-- Results by group -->
    <?php foreach ($groups as $groupName => $items):
        $gFail = count(array_filter($items, fn($r) => $r['status']==='fail'));
        $gWarn = count(array_filter($items, fn($r) => $r['status']==='warn'));
        $gIcon = $gFail>0 ? '❌' : ($gWarn>0 ? '⚠️' : '✅');
    ?>
    <div class="card mb-3">
        <div class="card-header d-flex align-items-center gap-2 py-2">
            <span style="font-size:1rem;"><?= $gIcon ?></span>
            <span class="card-title mb-0"><?= htmlspecialchars($groupName) ?></span>
            <span class="ms-auto" style="font-size:0.75rem;color:var(--text-muted);">
                <?= count(array_filter($items,fn($r)=>$r['status']==='pass')) ?>/<?= count($items) ?> passed
            </span>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0" style="font-size:0.85rem;">
                <tbody>
                    <?php foreach ($items as $r): ?>
                    <tr style="<?= $r['status']==='fail'?'background:#fff5f5':($r['status']==='warn'?'background:#fffde7':'') ?>">
                        <td style="width:28px;text-align:center;font-size:1rem;">
                            <?= $r['status']==='pass'?'✅':($r['status']==='warn'?'⚠️':'❌') ?>
                        </td>
                        <td style="width:35%;font-weight:500;">
                            <?= htmlspecialchars($r['name']) ?>
                        </td>
                        <td style="color:<?= $r['status']==='fail'?'#c62828':($r['status']==='warn'?'#e65100':'#2e7d32') ?>">
                            <?= htmlspecialchars($r['msg']) ?>
                            <?php if ($r['detail']): ?>
                            <div style="font-size:0.78rem;color:#888;"><?= htmlspecialchars($r['detail']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="width:60px;text-align:right;color:#aaa;font-size:0.75rem;">
                            <?= $r['ms'] ?>ms
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Quick fixes guide -->
    <?php if ($countFail > 0 || $countWarn > 0): ?>
    <div class="card mb-4">
        <div class="card-header"><span class="card-title">🔧 วิธีแก้ปัญหาที่พบ</span></div>
        <div class="card-body" style="font-size:0.85rem;">
            <?php
            $fixes = [];
            foreach ($results as $r) {
                if ($r['status'] === 'fail') {
                    if (str_contains($r['name'],'Table:'))
                        $fixes['db_migrate'] = '⬛ ตาราง DB ไม่ครบ → รัน <code>database/setup-complete.sql</code>';
                    if (str_contains($r['name'],'local.php'))
                        $fixes['local'] = '⬛ ไม่มี config → คัดลอก <code>config/local.example.php</code> ไปเป็น <code>config/local.php</code> แล้วแก้ค่า';
                    if (str_contains($r['name'],'เขียนได้'))
                        $fixes['perm'] = '⬛ Permission → รัน <code>chmod -R 775 assets/uploads/</code>';
                    if (str_contains($r['name'],'Extension'))
                        $fixes['ext'] = '⬛ PHP Extension → รัน <code>apt install php8.x-[extension]</code> แล้ว restart Apache/Nginx';
                }
                if ($r['status'] === 'warn') {
                    if (str_contains($r['name'],'SITE_URL'))
                        $fixes['siteurl'] = '🟡 SITE_URL → แก้ใน <code>config/local.php</code>';
                    if (str_contains($r['name'],'HTTPS'))
                        $fixes['https'] = '🟡 HTTPS → ติดตั้ง SSL Certificate (Let\'s Encrypt ฟรี)';
                    if (str_contains($r['name'],'LINE'))
                        $fixes['line'] = '🟡 LINE Token → ตั้งค่าที่ <a href="'.SITE_URL.'/pages/integrations.php">Admin → เชื่อมต่อระบบ</a>';
                    if (str_contains($r['name'],'n8n'))
                        $fixes['n8n'] = '🟡 n8n → ตั้งค่าที่ <a href="'.SITE_URL.'/pages/integrations.php">Admin → เชื่อมต่อระบบ</a>';
                }
            }
            foreach ($fixes as $fix) {
                echo "<div class='mb-2'>$fix</div>";
            }
            ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- System info -->
    <div class="card mb-4">
        <div class="card-header"><span class="card-title">📋 System Info</span></div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0" style="font-size:0.82rem;">
                <tbody>
                    <?php
                    $pdo2 = getDB();
                    $dbVer = $pdo2->query("SELECT VERSION()")->fetchColumn();
                    $dbSize = $pdo2->query("SELECT ROUND(SUM(data_length+index_length)/1024/1024,2) FROM information_schema.tables WHERE table_schema=DATABASE()")->fetchColumn();
                    $rows = [
                        ['PHP Version',      PHP_VERSION],
                        ['PHP SAPI',         PHP_SAPI],
                        ['MySQL Version',    $dbVer],
                        ['Database Size',    $dbSize . ' MB'],
                        ['SITE_URL',         SITE_URL],
                        ['Server Software',  $_SERVER['SERVER_SOFTWARE'] ?? '-'],
                        ['Server OS',        PHP_OS],
                        ['Timezone',         date_default_timezone_get()],
                        ['Memory Limit',     ini_get('memory_limit')],
                        ['Upload Max',       ini_get('upload_max_filesize')],
                        ['Post Max',         ini_get('post_max_size')],
                        ['Execution Time',   ini_get('max_execution_time').'s'],
                    ];
                    foreach ($rows as [$k,$v]):
                    ?>
                    <tr>
                        <td style="width:40%;color:var(--text-muted);padding-left:16px;"><?= htmlspecialchars($k) ?></td>
                        <td style="font-family:monospace;font-weight:500;"><?= htmlspecialchars((string)$v) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
