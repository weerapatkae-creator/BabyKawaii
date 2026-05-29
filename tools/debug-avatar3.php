<?php
if (($_GET['key'] ?? '') !== 'bk2026') { die('Forbidden'); }
require_once __DIR__ . '/../config/database.php';

$convId = (int)($_GET['conv_id'] ?? 19);
$pdo = getDB();
$stmt = $pdo->prepare("SELECT c.customer_uid, pa.page_access_token FROM conversations c JOIN platform_accounts pa ON pa.id = c.platform_account_id WHERE c.id = ?");
$stmt->execute([$convId]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);
$uid   = $data['customer_uid'];
$token = $data['page_access_token'];

// ทดสอบ 3 format
$tests = [
    "v19 + token"  => "https://graph.facebook.com/v19.0/{$uid}/picture?type=normal&access_token=" . urlencode($token),
    "no-v + token"  => "https://graph.facebook.com/{$uid}/picture?type=normal&access_token=" . urlencode($token),
    "no-v no-token" => "https://graph.facebook.com/{$uid}/picture?type=normal",
];

foreach ($tests as $label => $url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true, CURLOPT_TIMEOUT=>8, CURLOPT_SSL_VERIFYPEER=>false, CURLOPT_USERAGENT=>'Mozilla/5.0']);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ct   = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    echo "[$label]\n";
    echo "  HTTP: $code | Type: $ct | Size: " . strlen($body) . " bytes\n";
    echo "  Final URL: $finalUrl\n\n";
}
