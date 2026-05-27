<?php
/**
 * BabyKawaii — TikTok Shop Open API Webhook
 * ─────────────────────────────────────────
 * Webhook URL: https://babykawaii.store/api/tiktok-webhook.php?account_id=X
 *
 * ตั้ง Webhook ใน TikTok Partner Portal:
 *   My Apps → Event Settings → Webhooks → ใส่ URL → Verify
 *   Subscribe: IM_MESSAGE, ORDERS_STATUS_CHANGE
 *
 * Events:
 *   type 7 = IM_MESSAGE  → บันทึกข้อความเข้า Inbox
 *   type 1 = ORDER       → แจ้งเตือนออเดอร์ใหม่ LINE
 *   GET ?challenge=xxx   → Webhook verification
 */
require_once __DIR__ . '/../config/database.php';

$rawBody = file_get_contents('php://input');

// ── Webhook Verification (GET challenge) ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['challenge'])) {
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['challenge' => $_GET['challenge']]);
    exit;
}

http_response_code(200);
header('Content-Type: application/json');

$payload = json_decode($rawBody, true);

// ── โหลด account ─────────────────────────────────────────────────
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

// ── Verify TikTok Shop Signature ─────────────────────────────────
// TikTok Shop: SHA256(app_secret + timestamp + nonce + body)
// Header: x-tiktok-signature
if ($acc['app_secret'] && !empty($rawBody)) {
    $sig       = $_SERVER['HTTP_X_TIKTOK_SIGNATURE'] ?? '';
    $timestamp = $payload['timestamp'] ?? '';
    $nonce     = $payload['nonce']     ?? '';

    $expected  = hash('sha256', $acc['app_secret'] . $timestamp . $nonce . $rawBody);

    if ($sig && !hash_equals($expected, $sig)) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'msg' => 'invalid signature']);
        exit;
    }
}

if (empty($payload)) {
    echo json_encode(['ok' => true]);
    exit;
}

$eventType = (int)($payload['type'] ?? 0);
$data      = $payload['data']       ?? [];

// ── Event: IM_MESSAGE (type 7) ───────────────────────────────────
if ($eventType === 7) {
    // TikTok Shop ส่ง buyer_uid + conversation_id
    $senderId    = (string)($data['buyer_uid']       ?? ($data['from_user_id'] ?? ''));
    $senderName  = $data['buyer_nickname']            ?? ($data['from_user_name'] ?? 'ลูกค้า TikTok');
    $convUid     = $data['conversation_id']           ?? $senderId; // ใช้ conversation_id เป็น key
    $msgText     = $data['content']                   ?? ($data['message'] ?? '[ข้อความ]');
    $msgType     = $data['content_type']              ?? 'text';
    $msgId       = $data['message_id']                ?? '';

    if (!$convUid) {
        echo json_encode(['ok' => true, 'msg' => 'no conversation_id']);
        exit;
    }

    // Upsert conversation — ใช้ conversation_id เป็น customer_uid
    $existing = $pdo->prepare("
        SELECT id, unread_count FROM conversations
        WHERE platform_id=? AND platform_account_id=? AND customer_uid=?
        LIMIT 1
    ");
    $existing->execute([$acc['pid'], $accountId, $convUid]);
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
        ")->execute([$acc['pid'], $accountId, $acc['name'], $convUid, $senderName, mb_substr($msgText, 0, 255)]);
        $convId = (int)$pdo->lastInsertId();
    }

    // Save message
    $pdo->prepare("
        INSERT IGNORE INTO messages
            (conversation_id, direction, message_type, content, platform_message_id, sent_at)
        VALUES (?, 'inbound', ?, ?, ?, NOW())
    ")->execute([$convId, in_array($msgType, ['text','TEXT']) ? 'text' : 'image', $msgText, $msgId ?: null]);

    // LINE notification — atomic 3 min cooldown
    $claim = $pdo->prepare("
        UPDATE conversations SET line_notified_at=NOW()
        WHERE id=? AND (line_notified_at IS NULL OR line_notified_at < NOW() - INTERVAL 3 MINUTE)
    ");
    $claim->execute([$convId]);
    if ($claim->rowCount() > 0) {
        sendLineNotify("🔔 มีลูกค้าทักใหม่!\n📲 TikTok [{$acc['name']}]\n⏰ " . date('d/m/Y H:i') . " น.\n👉 " . SITE_URL . "/pages/inbox.php?conv={$convId}");
    }

    echo json_encode(['ok' => true, 'conv_id' => $convId]);
    exit;
}

// ── Event: ORDER (type 1) ────────────────────────────────────────
if ($eventType === 1) {
    $orderId     = $data['order_id']     ?? '';
    $orderStatus = $data['order_status'] ?? '';
    $buyerName   = $data['buyer_name']   ?? ($data['recipient_address']['name'] ?? 'ลูกค้า TikTok');

    if ($orderId && in_array($orderStatus, ['AWAITING_SHIPMENT', 'UNPAID', 'ON_HOLD'])) {
        sendLineNotify("🛍️ ออเดอร์ใหม่ TikTok!\n#{$orderId}\n👤 {$buyerName}\n⏰ " . date('d/m/Y H:i') . " น.\n👉 " . SITE_URL . "/pages/orders.php");
    }

    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['ok' => true]);
