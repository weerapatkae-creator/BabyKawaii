<?php
/**
 * Refresh Facebook customer names + avatars for existing conversations
 * Run once: https://babykawaii.store/tools/refresh-fb-names.php?key=bk2026
 */
if (($_GET['key'] ?? '') !== 'bk2026') { http_response_code(403); die('Forbidden'); }

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/meta-api.php';

$pdo = getDB();

// ดึง conversations ที่ชื่อว่าง หรือ "ไม่ระบุชื่อ" จาก Facebook/Instagram
$rows = $pdo->query("
    SELECT c.id, c.customer_uid, pa.page_access_token
    FROM conversations c
    JOIN platform_accounts pa ON pa.id = c.platform_account_id
    JOIN platforms p ON p.id = c.platform_id
    WHERE p.slug IN ('facebook','instagram')
      AND (c.customer_name = '' OR c.customer_name = 'ไม่ระบุชื่อ' OR c.customer_name IS NULL)
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

$updated = 0;
$failed  = 0;
$log     = [];

foreach ($rows as $row) {
    // ใช้ Conversations API เพื่อดึงชื่อผู้เข้าร่วม
    $convUrl = "https://graph.facebook.com/v19.0/me/conversations"
        . "?user_id=" . urlencode($row['customer_uid'])
        . "&fields=participants"
        . "&access_token=" . urlencode($row['page_access_token']);
    $ch = curl_init($convUrl);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>8, CURLOPT_SSL_VERIFYPEER=>false]);
    $raw = curl_exec($ch);
    curl_close($ch);
    $data = $raw ? json_decode($raw, true) : [];

    $name   = null;
    $avatar = "https://graph.facebook.com/{$row['customer_uid']}/picture?type=normal";
    $error  = $data['error']['message'] ?? null;

    if (!empty($data['data'][0]['participants']['data'])) {
        foreach ($data['data'][0]['participants']['data'] as $p) {
            // หา participant ที่ id ตรงกับ customer_uid
            if (($p['id'] ?? '') === $row['customer_uid']) {
                $name = $p['name'] ?? null;
                break;
            }
        }
        // ถ้ายังไม่เจอ เอาคนแรกที่ไม่ใช่ Page (name ไม่ว่าง)
        if (!$name) {
            foreach ($data['data'][0]['participants']['data'] as $p) {
                if (!empty($p['name'])) {
                    $name = $p['name'];
                    break;
                }
            }
        }
    }

    if ($name) {
        $pdo->prepare("UPDATE conversations SET customer_name=?, customer_avatar=? WHERE id=?")
            ->execute([$name, $avatar, $row['id']]);
        $updated++;
        $log[] = "✅ #{$row['id']} → {$name}";
    } else {
        $failed++;
        $log[] = "❌ #{$row['id']} (uid:{$row['customer_uid']}) — " . ($error ?: substr($raw, 0, 150));
    }
    usleep(200000);
}

header('Content-Type: text/plain; charset=utf-8');
echo "=== Refresh Facebook Names ===\n";
echo "Updated: {$updated} | Failed: {$failed}\n\n";
echo implode("\n", $log);
