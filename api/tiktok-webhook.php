<?php
/**
 * BabyKawaii — TikTok Shop Open API Webhook
 * ─────────────────────────────────────────
 * Webhook URL: https://babykawaii.store/api/tiktok-webhook.php?account_id=X
 *
 * ตั้ง Webhook ใน TikTok Seller Center:
 *   Partner Portal → My Apps → Event Settings → Webhooks
 *
 * Events handled:
 *   type 7  = IM_MESSAGE   → บันทึกข้อความเข้า Inbox
 *   type 1  = ORDER        → แจ้งเตือนออเดอร์ใหม่
 */
require_once __DIR__ . '/../config/database.php';

$rawBody  = file_get_contents('php://input');
$payload  = json_decode($rawBody, true);

http_response_code(200);
header('Content-Type: application/json');

// ── โหลด account ──────────────────────────────────────────────────
$accountId = (int)($_GET['account_id'] ?? 0);
if (!$accountId) {
    echo json_encode(['ok' => false, 'msg' => 'missing account_id']);
    exit;
}

$pdo = getDB();
$acc = $pdo->prepare("
    SELECT pa.*, p.id AS pid, p.name AS platform_name, p.slug AS platform_slug
    FROM   platform_accounts pa
    JOIN   platforms p ON p.id = pa.platform_id
    WHERE  pa.id = ? AND pa.is_active = 1
");
$acc->execute([$accountId]);
$acc = $acc->fetch();

if (!$acc) {
    echo json_encode(['ok' => false, 'msg' => 'account not found']);
    exit;
}

// ── Verify signature (TikTok Shop v2) ────────────────────────────
// TikTok ส่ง header: x-tiktok-signature = HMAC-SHA256(app_secret, body)
if ($acc['app_secret']) {
    $sig      = $_SERVER['HTTP_X_TIKTOK_SIGNATURE'] ?? '';
    $expected = hash_hmac('sha256', $rawBody, $acc['app_secret']);
    if ($sig && !hash_equals($expected, ltrim($sig, 'sha256='))) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'msg' => 'invalid signature']);
        exit;
    }
}

if (empty($payload)) {
    echo json_encode(['ok' => true]);
    exit;
}

$eventType  = (int)($payload['type']    ?? 0);
$shopId     = $payload['shop_id']        ?? '';
$data       = $payload['data']           ?? [];

// ── Event: IM_MESSAGE (type 7) ──────────────────────────────────
if ($eventType === 7) {
    $senderId   = (string)($data['from_user_id']   ?? ($data['buyer_uid'] ?? ''));
    $senderName = $data['from_user_name']            ?? ($data['buyer_nickname'] ?? 'ลูกค้า TikTok');
    $msgText    = $data['message']                   ?? ($data['content'] ?? '[ข้อความ]');
    $msgType    = $data['message_type']              ?? 'text';
    $convUid    = $data['conversation_id']           ?? $senderId;
    $msgId      = $data['message_id']                ?? '';

    if (!$senderId) {
        echo json_encode(['ok' => true, 'msg' => 'no sender_id']);
        exit;
    }

    // Upsert conversation
    $existing = $pdo->prepare("
        SELECT id, unread_count FROM conversations
        WHERE platform_id=? AND platform_account_id=? AND customer_uid=?
        LIMIT 1
    ");
    $existing->execute([$acc['pid'], $accountId, $senderId]);
    $existingRow = $existing->fetch();

    if ($existingRow) {
        $convId = (int)$existingRow['id'];
        $pdo->prepare("
            UPDATE conversations
            SET customer_name=?, last_message=?, last_message_at=NOW(),
                unread_count=unread_count+1,
                status=IF(status='closed','open',status),
                updated_at=NOW()
            WHERE id=?
        ")->execute([$senderName, mb_substr($msgText, 0, 255), $convId]);
    } else {
        $pdo->prepare("
            INSERT INTO conversations
                (platform_id, platform_account_id, platform_account_name,
                 customer_uid, customer_name, last_message, last_message_at, unread_count, status)
            VALUES (?,?,?,?,?,?,NOW(),1,'open')
        ")->execute([$acc['pid'], $accountId, $acc['name'], $senderId, $senderName, mb_substr($msgText, 0, 255)]);
        $convId = (int)$pdo->lastInsertId();
    }

    // Save message (prevent duplicate)
    $pdo->prepare("
        INSERT IGNORE INTO messages
            (conversation_id, direction, message_type, content, platform_message_id, sent_at)
        VALUES (?, 'inbound', ?, ?, ?, NOW())
    ")->execute([$convId, $msgType === 'text' ? 'text' : 'image', $msgText, $msgId ?: null]);

    // LINE notification — atomic cooldown 3 นาที
    $claim = $pdo->prepare("
        UPDATE conversations
        SET line_notified_at = NOW()
        WHERE id = ?
          AND (line_notified_at IS NULL OR line_notified_at < NOW() - INTERVAL 3 MINUTE)
    ");
    $claim->execute([$convId]);
    if ($claim->rowCount() > 0) {
        $notifyMsg = "🔔 มีลูกค้าทักใหม่!\n"
            . "📲 TikTok [{$acc['name']}]\n"
            . "⏰ " . date('d/m/Y H:i') . " น.\n"
            . "👉 " . SITE_URL . "/pages/inbox.php?conv={$convId}";
        sendLineNotify($notifyMsg);
    }

    echo json_encode(['ok' => true, 'conv_id' => $convId]);
    exit;
}

// ── Event: ORDER (type 1) ────────────────────────────────────────
if ($eventType === 1) {
    $orderId     = $data['order_id']     ?? '';
    $orderStatus = $data['order_status'] ?? '';
    $buyerName   = $data['buyer_name']   ?? 'ลูกค้า TikTok';

    if ($orderId && in_array($orderStatus, ['AWAITING_SHIPMENT', 'ON_HOLD'])) {
        $lineMsg = "🛍️ ออเดอร์ใหม่ TikTok!\n"
            . "#{$orderId}\n"
            . "👤 {$buyerName}\n"
            . "⏰ " . date('d/m/Y H:i') . " น.\n"
            . "👉 " . SITE_URL . "/pages/orders.php";
        sendLineNotify($lineMsg);
    }

    echo json_encode(['ok' => true]);
    exit;
}

// ── Verification challenge (GET) ──────────────────────────────────
// TikTok ส่ง GET เพื่อ verify webhook URL
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $challenge = $_GET['challenge'] ?? '';
    if ($challenge) {
        echo json_encode(['challenge' => $challenge]);
        exit;
    }
}

echo json_encode(['ok' => true]);
