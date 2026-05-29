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

// ทดสอบ Conversations API พร้อม profile_pic
$url = "https://graph.facebook.com/v19.0/me/conversations?user_id={$uid}&fields=participants%7Bname%2Cprofile_pic%2Cid%7D&access_token=" . urlencode($token);
echo "URL: $url\n\n";

$ch = curl_init($url);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>8, CURLOPT_SSL_VERIFYPEER=>false]);
$raw = curl_exec($ch);
curl_close($ch);

echo "Response:\n";
$decoded = json_decode($raw, true);
echo json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
