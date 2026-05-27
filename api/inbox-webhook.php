<?php
/**
 * BabyKawaii — Inbox Webhook
 * Receives inbound messages from n8n (which gets them from FB/IG/TikTok)
 *
 * URL pattern (recommended):
 *   POST /api/inbox-webhook.php?account_id={platform_account.id}&api_key={key}
 *
 * POST body (JSON):
 * {
 *   "customer_uid":        "FB_USER_12345",
 *   "customer_name":       "สมใจ จันทร์แก้ว",
 *   "customer_avatar":     "https://...",
 *   "message":             "ยังไม่ได้ของเลยค่ะ",
 *   "message_type":        "text",
 *   "media_url":           null,
 *   "platform_message_id": "m_abc123",
 *
 *   // Optional — override account lookup when account_id is in URL
 *   "platform_id":         2,
 *   "platform_account_id": 3
 * }
 */
require_once __DIR__ . '/../config/database.php';

// Verify API key
$apiKey   = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';
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

// ── Resolve platform account ──────────────────────────────────────
// Priority: ?account_id=X in URL  >  platform_account_id in body  >  platform_id in body
$accountIdFromUrl  = (int)($_GET['account_id'] ?? 0);
$accountIdFromBody = (int)($data['platform_account_id'] ?? 0);
$platformIdFromBody= (int)($data['platform_id'] ?? 0);

$platformAccountId   = null;
$platformAccountName = null;
$platformId          = null;

if ($accountIdFromUrl || $accountIdFromBody) {
    $lookupId = $accountIdFromUrl ?: $accountIdFromBody;
    $acc = $pdo->prepare("SELECT pa.id, pa.name, pa.platform_id FROM platform_accounts pa WHERE pa.id=? AND pa.is_active=1");
    $acc->execute([$lookupId]);
    $acc = $acc->fetch();
    if ($acc) {
        $platformAccountId   = (int)$acc['id'];
        $platformAccountName = $acc['name'];
        $platformId          = (int)$acc['platform_id'];
    }
}

// Fall back to platform_id from body if account not found
if (!$platformId && $platformIdFromBody) {
    $platformId = $platformIdFromBody;
}

// ── Extract message fields ────────────────────────────────────────
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

// ── Upsert conversation ───────────────────────────────────────────
// Unique on (platform_id, platform_account_id, customer_uid)
if ($platformAccountId) {
    $conv = $pdo->prepare("SELECT id, unread_count FROM conversations WHERE platform_id=? AND platform_account_id=? AND customer_uid=?");
    $conv->execute([$platformId, $platformAccountId, $customerUid]);
} else {
    $conv = $pdo->prepare("SELECT id, unread_count FROM conversations WHERE platform_id=? AND platform_account_id IS NULL AND customer_uid=?");
    $conv->execute([$platformId, $customerUid]);
}
$conv = $conv->fetch();

if ($conv) {
    $convId = $conv['id'];
    $pdo->prepare(
        "UPDATE conversations
         SET customer_name=?, customer_avatar=?, last_message=?, last_message_at=NOW(),
             unread_count=unread_count+1,
             status=IF(status='closed','open',status),
             updated_at=NOW(),
             platform_account_name=?
         WHERE id=?"
    )->execute([$customerName, $customerAvatar, $message, $platformAccountName, $convId]);
} else {
    $pdo->prepare(
        "INSERT INTO conversations
             (platform_id, platform_account_id, platform_account_name,
              customer_uid, customer_name, customer_avatar,
              last_message, last_message_at, unread_count, status)
         VALUES (?,?,?,?,?,?,?,NOW(),1,'open')"
    )->execute([
        $platformId ?: null,
        $platformAccountId,
        $platformAccountName,
        $customerUid,
        $customerName,
        $customerAvatar,
        $message,
    ]);
    $convId = $pdo->lastInsertId();
}

// ── Save message ──────────────────────────────────────────────────
$pdo->prepare(
    "INSERT INTO messages (conversation_id, direction, content, message_type, media_url, sent_at, platform_message_id)
     VALUES (?,?,?,?,?,NOW(),?)"
)->execute([$convId, 'inbound', $message, $messageType, $mediaUrl, $platformMsgId]);

// ── LINE Notify admin — atomic cooldown 3 นาที ───────────────────
$claim = $pdo->prepare("
    UPDATE conversations
    SET line_notified_at = NOW()
    WHERE id = ?
      AND (line_notified_at IS NULL OR line_notified_at < NOW() - INTERVAL 3 MINUTE)
");
$claim->execute([$convId]);
if ($claim->rowCount() > 0) {
    $platform = $pdo->prepare("SELECT name, icon FROM platforms WHERE id=?");
    $platform->execute([$platformId]);
    $platform    = $platform->fetch();
    $platformStr = $platform ? "{$platform['icon']} {$platform['name']}" : 'ไม่ระบุ';
    $accountStr  = $platformAccountName ? " [{$platformAccountName}]" : '';

    $notifyMsg = "🔔 มีลูกค้าทักใหม่!\n"
        . "📲 {$platformStr}{$accountStr}\n"
        . "⏰ " . date('d/m/Y H:i') . " น.\n"
        . "👉 " . SITE_URL . "/pages/inbox.php?conv={$convId}";
    sendLineNotify($notifyMsg);
}

echo json_encode([
    'ok'                  => true,
    'conversation_id'     => $convId,
    'platform_account_id' => $platformAccountId,
    'message_saved'       => true,
]);
