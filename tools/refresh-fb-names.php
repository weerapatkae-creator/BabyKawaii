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
    [$name, $avatar] = fetchFbProfile($row['customer_uid'], $row['page_access_token']);
    if ($name) {
        $pdo->prepare("UPDATE conversations SET customer_name=?, customer_avatar=? WHERE id=?")
            ->execute([$name, $avatar, $row['id']]);
        $updated++;
        $log[] = "✅ #{$row['id']} → {$name}";
    } else {
        $failed++;
        $log[] = "❌ #{$row['id']} (uid:{$row['customer_uid']}) — ดึงชื่อไม่ได้";
    }
    usleep(200000); // 0.2s delay ป้องกัน rate limit
}

header('Content-Type: text/plain; charset=utf-8');
echo "=== Refresh Facebook Names ===\n";
echo "Updated: {$updated} | Failed: {$failed}\n\n";
echo implode("\n", $log);
