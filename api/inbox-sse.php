<?php
/**
 * BabyKawaii — Inbox Server-Sent Events
 * รับ notifications แบบ real-time เมื่อมีข้อความใหม่
 * GET params: conv_id, since_id
 */
require_once __DIR__ . '/../config/database.php';
requireLogin();

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');  // Nginx: ห้าม buffer
header('Access-Control-Allow-Origin: *');

// ปิด output buffering ทั้งหมด
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
$limit = 25; // วินาที — client จะ reconnect อัตโนมัติ

function sseFlush(string $data, string $event = 'message'): void {
    echo "event: {$event}\n";
    echo "data: {$data}\n\n";
    flush();
}

// Heartbeat ตอนต้น
sseFlush('{}', 'ping');

while (!connection_aborted() && (time() - $start) < $limit) {
    // ตรวจสอบข้อความใหม่
    $stmt = $pdo->prepare("
        SELECT MAX(id) AS max_id, COUNT(*) AS cnt
        FROM messages
        WHERE conversation_id = ? AND id > ?
    ");
    $stmt->execute([$convId, $sinceId]);
    $row = $stmt->fetch();

    if ((int)$row['cnt'] > 0) {
        $sinceId = (int)$row['max_id'];
        sseFlush(json_encode([
            'new_count' => (int)$row['cnt'],
            'max_id'    => $sinceId,
        ]), 'new_message');
    } else {
        // Keepalive ทุก 5 รอบ (2.5 วินาที)
        static $tick = 0;
        if (++$tick % 5 === 0) sseFlush('{}', 'ping');
    }

    usleep(500000); // ตรวจทุก 0.5 วินาที
}

// บอก client ให้ reconnect
sseFlush('{}', 'close');
