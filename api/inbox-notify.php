<?php
/**
 * BabyKawaii — Global Inbox Notification Poller
 * ─────────────────────────────────────────────
 * GET ?last_id=N  → คืน new inbound messages + unread count + conv list
 * ใช้โดย JS poll ทุก 1.5 วินาที
 */
require_once __DIR__ . '/../config/database.php';
requireLogin();

header('Content-Type: application/json');
header('Cache-Control: no-cache');

$lastId = (int)($_GET['last_id'] ?? 0);
$pdo    = getDB();

// ข้อความใหม่ขาเข้าทุก conversation ตั้งแต่ last_id
$r = $pdo->prepare("
    SELECT MAX(id) AS max_id, COUNT(*) AS cnt
    FROM messages
    WHERE id > ? AND direction = 'inbound'
");
$r->execute([$lastId]);
$row = $r->fetch();

// ยอดรวม unread + open
$stats = $pdo->query("
    SELECT
        COALESCE(SUM(unread_count), 0)                     AS total_unread,
        SUM(unread_count > 0 AND status != 'closed')       AS new_count,
        SUM(status IN ('open','pending'))                   AS open_count,
        SUM(status = 'closed')                             AS closed_count
    FROM conversations
")->fetch(PDO::FETCH_ASSOC);

// Conversation list (เรียงตาม last_message_at)
$convs = $pdo->query("
    SELECT c.id, c.customer_name, c.customer_avatar,
           c.last_message, c.last_message_at,
           c.unread_count, c.status,
           p.icon  AS platform_icon,
           p.color AS platform_color,
           pa.name  AS account_name,
           pa.color AS account_color
    FROM   conversations c
    LEFT JOIN platforms         p  ON p.id  = c.platform_id
    LEFT JOIN platform_accounts pa ON pa.id = c.platform_account_id
    ORDER  BY c.last_message_at DESC
    LIMIT  60
")->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'new_id'  => (int)($row['max_id'] ?? $lastId),
    'has_new' => (int)($row['cnt'] ?? 0) > 0,
    'stats'   => $stats,
    'convs'   => $convs,
], JSON_UNESCAPED_UNICODE);
