<?php
/**
 * Avatar Proxy — ดึงรูปโปรไฟล์ Facebook ผ่าน server-side
 * เพราะ PSID (Page-Scoped ID) ต้องใช้ access token จึงโหลดจาก browser ตรงๆ ไม่ได้
 *
 * Usage: /api/avatar-proxy.php?conv_id=123
 */
require_once __DIR__ . '/../config/database.php';

$convId = intval($_GET['conv_id'] ?? 0);
if (!$convId) { http_response_code(404); exit; }

$pdo = getDB();

// ดึง customer_uid + platform access token
$row = $pdo->prepare("
    SELECT c.customer_uid, c.customer_avatar,
           pa.page_access_token
    FROM conversations c
    JOIN platform_accounts pa ON pa.id = c.platform_account_id
    WHERE c.id = ?
    LIMIT 1
");
$row->execute([$convId]);
$data = $row->fetch(PDO::FETCH_ASSOC);

if (!$data || empty($data['customer_uid'])) { http_response_code(404); exit; }

$uid   = $data['customer_uid'];
$token = $data['page_access_token'] ?? '';

// สร้าง URL ดึงรูปจาก Graph API พร้อม access token
$url = "https://graph.facebook.com/v19.0/{$uid}/picture?type=normal"
     . ($token ? '&access_token=' . urlencode($token) : '');

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 8,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_USERAGENT      => 'Mozilla/5.0',
]);
$imgData = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

if ($httpCode !== 200 || empty($imgData)) {
    http_response_code(404);
    exit;
}

// Cache 1 ชั่วโมง
header('Content-Type: ' . ($contentType ?: 'image/jpeg'));
header('Cache-Control: public, max-age=3600');
echo $imgData;
