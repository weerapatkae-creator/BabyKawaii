<?php
/**
 * BabyKawaii — Meta Webhook (Facebook Page + Instagram)
 * ─────────────────────────────────────────────────────
 * GET  → Webhook verification challenge จาก Meta
 * POST → รับข้อความจาก Facebook Page และ Instagram DM
 *
 * ตั้ง Webhook URL ใน Meta App:
 *   http://159.223.52.122/api/meta-webhook.php
 * Verify Token:
 *   babykawaii_verify_2026
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/meta-api.php';

define('META_VERIFY_TOKEN', 'babykawaii_verify_2026');

// ── GET: Webhook Verification ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode      = $_GET['hub_mode']         ?? $_GET['hub.mode']         ?? '';
    $token     = $_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? '';
    $challenge = $_GET['hub_challenge']    ?? $_GET['hub.challenge']    ?? '';

    if ($mode === 'subscribe' && $token === META_VERIFY_TOKEN) {
        http_response_code(200);
        echo $challenge;
    } else {
        http_response_code(403);
        echo 'Forbidden';
    }
    exit;
}

// ── POST: Receive Messages ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

header('Content-Type: application/json');

$body    = file_get_contents('php://input');
$payload = json_decode($body, true);

if (!$payload || !isset($payload['object'])) {
    echo json_encode(['ok' => false, 'error' => 'Invalid payload']);
    exit;
}

$pdo      = getDB();
$object   = $payload['object']; // 'page' or 'instagram'
$entries  = $payload['entry']   ?? [];
$saved    = 0;

foreach ($entries as $entry) {
    $pageId    = $entry['id'] ?? '';           // Facebook Page ID หรือ IG Account ID
    $messaging = $entry['messaging'] ?? [];    // Facebook Messenger
    $changes   = $entry['changes']   ?? [];    // Instagram DM

    // ── หา platform_account จาก page_id ───────────────────
    $accStmt = $pdo->prepare("
        SELECT pa.id, pa.name, pa.platform_id, pa.page_access_token
        FROM platform_accounts pa
        WHERE pa.account_uid = ? AND pa.is_active = 1
        LIMIT 1
    ");
    $accStmt->execute([$pageId]);
    $acc = $accStmt->fetch();

    if (!$acc) continue; // ไม่พบ account ที่ตรงกัน

    $platformAccountId   = (int)$acc['id'];
    $platformAccountName = $acc['name'];
    $platformId          = (int)$acc['platform_id'];

    // ── Facebook Messenger messages ────────────────────────
    foreach ($messaging as $msg) {
        $senderId = $msg['sender']['id']    ?? '';
        $text     = $msg['message']['text'] ?? '';
        $msgId    = $msg['message']['id']   ?? '';
        $mediaUrl = null;

        if (empty($senderId) || $senderId === $pageId) continue; // ข้ามข้อความที่เพจส่งเอง

        // ตรวจสอบ attachment (รูป/วิดีโอ)
        $attachments = $msg['message']['attachments'] ?? [];
        $msgType = 'text';
        if (!empty($attachments)) {
            $att     = $attachments[0];
            $msgType = $att['type'] ?? 'file';
            $mediaUrl = $att['payload']['url'] ?? null;
            if (empty($text)) $text = "[{$msgType}]";
        }

        if (empty($text)) continue;

        // ดึงชื่อ + รูปโปรไฟล์จาก Facebook Graph API
        [$senderName, $senderAvatar] = fetchFbProfile($senderId, $acc['page_access_token']);

        saveMessage($pdo, $platformId, $platformAccountId, $platformAccountName, $senderId, $senderName, $senderAvatar, $text, $msgType, $mediaUrl, $msgId);
        $saved++;
    }

    // ── Instagram DM (via changes) ─────────────────────────
    foreach ($changes as $change) {
        if (($change['field'] ?? '') !== 'messages') continue;
        $val      = $change['value'] ?? [];
        $senderId = $val['sender']['id']    ?? '';
        $text     = $val['message']['text'] ?? '';
        $msgId    = $val['message']['mid']  ?? '';
        $mediaUrl = null;
        $msgType  = 'text';

        if (empty($senderId) || $senderId === $pageId) continue;

        $attachments = $val['message']['attachments'] ?? [];
        if (!empty($attachments)) {
            $att     = $attachments[0];
            $msgType = $att['type'] ?? 'image';
            $mediaUrl = $att['payload']['url'] ?? null;
            if (empty($text)) $text = "[{$msgType}]";
        }

        if (empty($text)) continue;

        [$senderName, $senderAvatar] = fetchFbProfile($senderId, $acc['page_access_token']);

        saveMessage($pdo, $platformId, $platformAccountId, $platformAccountName, $senderId, $senderName, $senderAvatar, $text, $msgType, $mediaUrl, $msgId);
        $saved++;
    }
}

echo json_encode(['ok' => true, 'saved' => $saved]);

// ── Helper: บันทึกข้อความลง DB ────────────────────────────
function saveMessage(PDO $pdo, int $platformId, int $accountId, string $accountName,
                     string $senderUid, ?string $senderName, ?string $senderAvatar,
                     string $text, string $msgType, ?string $mediaUrl, string $msgId): void
{
    // เช็คสถานะเดิมก่อน upsert
    $existing = $pdo->prepare("
        SELECT id, unread_count, status
        FROM conversations
        WHERE platform_id=? AND platform_account_id=? AND customer_uid=?
        LIMIT 1
    ");
    $existing->execute([$platformId, $accountId, $senderUid]);
    $existingRow  = $existing->fetch();
    $wasUnread    = $existingRow && (int)$existingRow['unread_count'] > 0;

    // Upsert conversation — เปิดใหม่ถ้าปิดไปแล้ว
    $pdo->prepare("
        INSERT INTO conversations
            (platform_id, platform_account_id, platform_account_name, customer_uid, customer_name, customer_avatar, last_message, last_message_at, unread_count, status)
        VALUES (?,?,?,?,?,?,?,NOW(),1,'open')
        ON DUPLICATE KEY UPDATE
            last_message    = VALUES(last_message),
            last_message_at = NOW(),
            unread_count    = unread_count + 1,
            status          = IF(status = 'closed', 'open', status),
            customer_name   = COALESCE(NULLIF(VALUES(customer_name),''), customer_name),
            customer_avatar = COALESCE(NULLIF(VALUES(customer_avatar),''), customer_avatar)
    ")->execute([$platformId, $accountId, $accountName, $senderUid, $senderName ?? '', $senderAvatar ?? '', $text]);

    // หา conversation id
    $conv = $pdo->prepare("
        SELECT id FROM conversations
        WHERE platform_id=? AND platform_account_id=? AND customer_uid=?
        LIMIT 1
    ");
    $conv->execute([$platformId, $accountId, $senderUid]);
    $convId = (int)($conv->fetchColumn() ?: 0);
    if (!$convId) return;

    // บันทึกข้อความ (ป้องกัน duplicate)
    $pdo->prepare("
        INSERT IGNORE INTO messages
            (conversation_id, direction, message_type, content, media_url, platform_message_id, sent_at)
        VALUES (?, 'inbound', ?, ?, ?, ?, NOW())
    ")->execute([$convId, $msgType, $text, $mediaUrl, $msgId ?: null]);

    // LINE notification — atomic cooldown 3 นาที (ป้องกัน flood + race condition)
    $claim = $pdo->prepare("
        UPDATE conversations
        SET line_notified_at = NOW()
        WHERE id = ?
          AND (line_notified_at IS NULL OR line_notified_at < NOW() - INTERVAL 3 MINUTE)
    ");
    $claim->execute([$convId]);
    if ($claim->rowCount() > 0) {
        $notifyMsg = "🔔 มีลูกค้าทักใหม่!\n"
            . "📲 [{$accountName}]\n"
            . "⏰ " . date('d/m/Y H:i') . " น.\n"
            . "👉 " . SITE_URL . "/pages/inbox.php?conv={$convId}";
        sendLineNotify($notifyMsg);
    }
}

// ── fetchFbProfile() และ sendFbMessage() อยู่ใน includes/meta-api.php ──
