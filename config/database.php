<?php
// =====================================================
// BabyKawaii Shop - Database Configuration
// =====================================================
// สำหรับ deploy จริง: คัดลอก config/local.example.php
// ไปเป็น config/local.php แล้วแก้ค่าให้ตรงกับ server
// =====================================================

if (file_exists(__DIR__ . '/local.php')) {
    require_once __DIR__ . '/local.php';
} else {
    // ─── Default values (localhost / XAMPP) ───────────────
    define('DB_HOST',    'localhost');
    define('DB_NAME',    'babykawaii_db');
    define('DB_USER',    'root');
    define('DB_PASS',    '');
    define('DB_CHARSET', 'utf8mb4');

    define('SITE_NAME', 'BabyKawaii Shop');
    define('SITE_URL',  'http://localhost/BabyKawaii-Admin');
    define('UPLOAD_PATH', __DIR__ . '/../assets/uploads/');
    define('UPLOAD_URL',  SITE_URL . '/assets/uploads/');
}

// ─── DB Connection ────────────────────────────────────
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die('<div style="padding:20px;background:#fff0f0;border:1px solid #f00;border-radius:8px;font-family:sans-serif;">
                <h3>❌ ไม่สามารถเชื่อมต่อฐานข้อมูล</h3>
                <p>กรุณาตรวจสอบไฟล์ <code>config/local.php</code></p>
                <small>' . htmlspecialchars($e->getMessage()) . '</small></div>');
        }
    }
    return $pdo;
}

// ─── Helpers ─────────────────────────────────────────
function formatPrice($amount) {
    return '฿' . number_format($amount, 2);
}

function formatDateTH($date) {
    if (!$date) return '-';
    $months = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    $d = new DateTime($date);
    return $d->format('j') . ' ' . $months[(int)$d->format('n')] . ' ' . ($d->format('Y') + 543);
}

function timeAgo($datetime) {
    $now  = new DateTime();
    $ago  = new DateTime($datetime);
    $diff = $now->diff($ago);
    if ($diff->i < 1 && $diff->h == 0 && $diff->d == 0) return 'เมื่อกี้';
    if ($diff->h == 0 && $diff->d == 0) return $diff->i . ' นาทีที่แล้ว';
    if ($diff->d == 0) return $diff->h . ' ชั่วโมงที่แล้ว';
    if ($diff->d < 7)  return $diff->d . ' วันที่แล้ว';
    return formatDateTH($datetime);
}

// ─── Session ──────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ─── Auth ─────────────────────────────────────────────
function requireLogin() {
    if (!isset($_SESSION['admin_id'])) {
        header('Location: ' . SITE_URL . '/login.php');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if (!in_array($_SESSION['admin_role'] ?? '', ['superadmin', 'admin'])) {
        header('Location: ' . SITE_URL . '/dashboard.php?err=noperm');
        exit;
    }
}

function isSuperAdmin() {
    return ($_SESSION['admin_role'] ?? '') === 'superadmin';
}

function isAdmin() {
    return in_array($_SESSION['admin_role'] ?? '', ['superadmin', 'admin']);
}

// ─── Settings helpers ─────────────────────────────────
function getSetting($key, $default = '') {
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['setting_value'] : $default;
}

function saveSetting($key, $value, $group = 'general') {
    $pdo = getDB();
    $pdo->prepare("INSERT INTO settings (setting_key, setting_value, setting_group) VALUES (?,?,?)
                   ON DUPLICATE KEY UPDATE setting_value=?")
        ->execute([$key, $value, $group, $value]);
}

// ─── n8n webhook (fire-and-forget) ───────────────────
function triggerN8n(string $event, array $payload = []): void {
    $n8nUrl = getSetting('n8n_base_url', '');
    if (!$n8nUrl) return;

    $payload['event']     = $event;
    $payload['timestamp'] = time();
    $payload['source']    = 'admin';
    $url = rtrim($n8nUrl, '/') . '/webhook/babykawaii-events';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 3,
            CURLOPT_NOSIGNAL       => 1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);
        @curl_exec($ch);
        @curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/json\r\n",
            'content'       => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'timeout'       => 3,
            'ignore_errors' => true,
        ]]);
        @file_get_contents($url, false, $ctx);
    }
}

// ─── LINE Notification ────────────────────────────────
// LINE Notify ปิดบริการแล้ว (31 มี.ค. 2568)
// ใช้ LINE Messaging API push message แทน
// ต้องตั้งค่า: line_channel_access_token + line_admin_user_id
// ที่ Admin → เชื่อมต่อระบบ
function sendLineNotify(string $message): bool {
    $token  = getSetting('line_channel_access_token', '');
    $userId = getSetting('line_admin_user_id', '');
    if (!$token || !$userId) return false;
    if (!function_exists('curl_init')) return false;

    $ch = curl_init('https://api.line.me/v2/bot/message/push');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'to'       => $userId,
            'messages' => [['type' => 'text', 'text' => $message]],
        ], JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code === 200;
}
