<?php
/**
 * BabyKawaii — Inbox Webhook
 * Receives inbound messages from n8n (which gets them from FB/IG/LINE/TikTok)
 *
 * POST body (JSON):
 * {
 *   "platform_id":   2,
 *   "customer_uid":  "FB_USER_12345",
 *   "customer_name": "สมใจ จันทร์แก้ว",
 *   "customer_avatar": "https://...",
 *   "message":       "ยังไม่ได้ของเลยค่ะ",
 *   "message_type":  "text",
 *   "media_url":     null,
 *   "platform_message_id": "m_abc123"
 * }
 */
require_once __DIR__ . '/../config/database.php';

// Verify API key
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';
$validKey = getSetting('api_key', '');
if ($validKey && $apiKey !== $validKey) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'POST only']);
    exit;
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$pdo = getDB();

$platformId    = (int)($data['platform_id']        ?? 0);
$customerUid   = trim($data['customer_uid']         ?? '');
$customerName  = trim($data['customer_name']        ?? 'ไม่ระบุชื่อ');
$customerAvatar= trim($data['customer_avatar']      ?? '');
$message       = trim($data['message']              ?? '');
$messageType   = $data['message_type']              ?? 'text';
$mediaUrl      = $data['media_url']                 ?? null;
$platformMsgId = $data['platform_message_id']       ?? null;

if (!$customerUid || !$message) {
    http_response_code(400);
    echo json_encode(['error' => 'customer_uid and message required']);
    exit;
}

// ── Upsert conversation ──────────────────────────────────────────
$conv = $pdo->prepare("SELECT id, unread_count FROM conversations WHERE platform_id=? AND customer_uid=?");
$conv->execute([$platformId ?: null, $customerUid]);
$conv = $conv->fetch();

if ($conv) {
    $convId = $conv['id'];
    $pdo->prepare("UPDATE conversations SET customer_name=?, customer_avatar=?, last_message=?, last_message_at=NOW(), unread_count=unread_count+1, status='open', updated_at=NOW() WHERE id=?")
        ->execute([$customerName, $customerAvatar, $message, $convId]);
} else {
    $pdo->prepare("INSERT INTO conversations (platform_id, customer_uid, customer_name, customer_avatar, last_message, last_message_at, unread_count, status) VALUES (?,?,?,?,?,NOW(),1,'open')")
        ->execute([$platformId ?: null, $customerUid, $customerName, $customerAvatar, $message]);
    $convId = $pdo->lastInsertId();
}

// ── Save message ─────────────────────────────────────────────────
$pdo->prepare("INSERT INTO messages (conversation_id, direction, content, message_type, media_url, sent_at, platform_message_id) VALUES (?,?,?,?,?,NOW(),?)")
    ->execute([$convId, 'inbound', $message, $messageType, $mediaUrl, $platformMsgId]);

// ── LINE Notify admin ────────────────────────────────────────────
$platform = $pdo->prepare("SELECT name, icon FROM platforms WHERE id=?");
$platform->execute([$platformId]);
$platform = $platform->fetch();
$platformStr = $platform ? "{$platform['icon']} {$platform['name']}" : 'ไม่ระบุ';

$notifyMsg = "\n💬 ข้อความใหม่!\n"
    . "จาก: {$customerName}\n"
    . "แพลตฟอร์ม: {$platformStr}\n"
    . "ข้อความ: " . mb_substr($message, 0, 100) . (mb_strlen($message)>100?'...':'') . "\n"
    . "→ ดูใน Inbox: " . SITE_URL . "/pages/inbox.php";

sendLineNotify($notifyMsg);

echo json_encode([
    'ok'              => true,
    'conversation_id' => $convId,
    'message_saved'   => true,
]);
