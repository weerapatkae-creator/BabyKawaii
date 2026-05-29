<?php
/**
 * Avatar Proxy — ดึงรูปโปรไฟล์ Facebook ผ่าน server-side
 * ถ้าดึงไม่ได้ จะ generate avatar จากตัวอักษรแรกของชื่อแทน
 *
 * Usage: /api/avatar-proxy.php?conv_id=123
 */
require_once __DIR__ . '/../config/database.php';

$convId = intval($_GET['conv_id'] ?? 0);
if (!$convId) { serveInitialAvatar('?'); }

$pdo = getDB();
$stmt = $pdo->prepare("
    SELECT c.customer_uid, c.customer_name, c.customer_avatar,
           pa.page_access_token
    FROM conversations c
    JOIN platform_accounts pa ON pa.id = c.platform_account_id
    WHERE c.id = ?
    LIMIT 1
");
$stmt->execute([$convId]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data || empty($data['customer_uid'])) {
    serveInitialAvatar('?');
}

$uid      = $data['customer_uid'];
$token    = $data['page_access_token'] ?? '';
$name     = $data['customer_name'] ?? '';
$initial  = mb_strtoupper(mb_substr($name ?: '?', 0, 1, 'UTF-8'), 'UTF-8');

// ลอง fetch รูปจาก Graph API
$url = "https://graph.facebook.com/v19.0/{$uid}/picture?type=normal"
     . ($token ? '&access_token=' . urlencode($token) : '');

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 6,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_USERAGENT      => 'Mozilla/5.0',
]);
$imgData     = curl_exec($ch);
$httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

// ถ้าได้รูปจริง ส่งกลับเลย
if ($httpCode === 200 && !empty($imgData) && strpos($contentType, 'image') !== false) {
    header('Content-Type: ' . $contentType);
    header('Cache-Control: public, max-age=3600');
    echo $imgData;
    exit;
}

// fallback — generate SVG avatar จากตัวอักษรแรก
serveInitialAvatar($initial);

// ── Helper ──────────────────────────────────────────────────────
function serveInitialAvatar(string $letter): void {
    // สีพื้นหลัง gradient ตาม hash ของตัวอักษร
    $colors = [
        ['#FF85A2','#9B72CF'],
        ['#FF9F7E','#FF6B9D'],
        ['#72CFB0','#5A78B4'],
        ['#FFB347','#FF85A2'],
        ['#9B72CF','#5A78B4'],
    ];
    $idx = ord($letter) % count($colors);
    [$c1, $c2] = $colors[$idx];

    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100">
  <defs>
    <linearGradient id="g" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="{$c1}"/>
      <stop offset="100%" stop-color="{$c2}"/>
    </linearGradient>
  </defs>
  <circle cx="50" cy="50" r="50" fill="url(#g)"/>
  <text x="50" y="50" dominant-baseline="central" text-anchor="middle"
        font-family="'Noto Sans Thai',Arial,sans-serif" font-size="42" font-weight="700" fill="#fff">{$letter}</text>
</svg>
SVG;

    header('Content-Type: image/svg+xml');
    header('Cache-Control: public, max-age=86400');
    echo $svg;
    exit;
}
