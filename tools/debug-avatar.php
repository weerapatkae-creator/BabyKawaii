<?php
if (($_GET['key'] ?? '') !== 'bk2026') { die('Forbidden'); }
require_once __DIR__ . '/../config/database.php';

$convId = (int)($_GET['conv_id'] ?? 19);
$pdo = getDB();

$stmt = $pdo->prepare("
    SELECT c.id, c.customer_uid, c.customer_avatar, c.customer_name,
           pa.page_access_token
    FROM conversations c
    JOIN platform_accounts pa ON pa.id = c.platform_account_id
    WHERE c.id = ?
");
$stmt->execute([$convId]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Conv #$convId:\n";
echo "  customer_uid: " . ($data['customer_uid'] ?? 'NULL') . "\n";
echo "  customer_name: " . ($data['customer_name'] ?? 'NULL') . "\n";
echo "  customer_avatar: " . ($data['customer_avatar'] ?? 'NULL') . "\n";
echo "  token: " . substr($data['page_access_token'] ?? '', 0, 20) . "...\n\n";

if (!empty($data['customer_uid']) && !empty($data['page_access_token'])) {
    $uid   = $data['customer_uid'];
    $token = $data['page_access_token'];
    $url   = "https://graph.facebook.com/{$uid}/picture?type=normal&redirect=true&access_token=" . urlencode($token);
    echo "Picture URL: $url\n\n";

    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true, CURLOPT_TIMEOUT=>8, CURLOPT_SSL_VERIFYPEER=>false, CURLOPT_USERAGENT=>'Mozilla/5.0']);
    $img  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ct   = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    echo "HTTP Code: $code\n";
    echo "Content-Type: $ct\n";
    echo "Image size: " . strlen($img) . " bytes\n";
}
