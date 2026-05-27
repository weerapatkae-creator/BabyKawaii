<?php
/**
 * BabyKawaii — Inbox Server-Sent Events (v2 — instant push)
 * ─────────────────────────────────────────────────────────
 * ส่งข้อมูลข้อความจริงมาทันที ไม่ต้อง client fetch รอบสอง
 * GET: conv_id, since_id
 */
require_once __DIR__ . '/../config/database.php';
requireLogin();

set_time_limit(0);       // ไม่ให้ PHP หมดเวลา
ignore_user_abort(false);

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Nginx: ห้าม buffer

while (ob_get_level()) ob_end_clean();

$convId  = (int)($_GET['conv_id']  ?? 0);
$sinceId = (int)($_GET['since_id'] ?? 0);

if (!$convId) {
    echo "event: error\ndata: {\"error\":\"no conv_id\"}\n\n";
    flush();
    exit;
}

$pdo   = getDB();
$start = time();
$tick  = 0;

// Heartbeat เริ่มต้น
echo "event: ping\ndata: {}\n\n";
flush();

while (!connection_aborted() && (time() - $start) < 28) {

    $stmt = $pdo->prepare("
        SELECT m.id, m.direction, m.message_type, m.content,
               m.media_url, m.sent_at, m.is_read,
               u.full_name AS sender_name
        FROM   messages m
        LEFT JOIN admin_users u ON u.id = m.sent_by
        WHERE  m.conversation_id = ? AND m.id > ?
        ORDER  BY m.id ASC
        LIMIT  20
    ");
    $stmt->execute([$convId, $sinceId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $sinceId = max($sinceId, (int)$row['id']);
        echo "event: msg\n";
        echo "data: " . json_encode($row, JSON_UNESCAPED_UNICODE) . "\n\n";
    }

    if (!empty($rows)) flush();

    // Keepalive ทุก 25 รอบ (~5 วินาที) เพื่อไม่ให้ connection หมดอายุ
    if (++$tick % 25 === 0) {
        echo "event: ping\ndata: {}\n\n";
        flush();
    }

    usleep(200000); // ตรวจ DB ทุก 0.2 วินาที
}

// บอก client reconnect
echo "event: close\ndata: {}\n\n";
flush();
