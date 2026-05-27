<?php
/**
 * BabyKawaii — LINE Messaging API Webhook
 * ────────────────────────────────────────
 * ตั้ง Webhook URL ใน LINE Developers Console:
 *   https://babykawaii.store/api/line-webhook.php
 *
 * Features:
 *  - Auto-save Admin User ID ครั้งแรกที่เจ้าของร้านส่งข้อความหา bot
 *  - พิมพ์ "id"  → bot ตอบ User ID ของคุณ
 *  - พิมพ์ "test" → bot ตอบยืนยันว่าระบบแจ้งเตือนพร้อมแล้ว
 */
require_once __DIR__ . '/../config/database.php';

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody, true);

// ── Verify LINE Signature ────────────────────────────────────────
$secret = getSetting('line_channel_secret', '');
if ($secret) {
    $sig  = $_SERVER['HTTP_X_LINE_SIGNATURE'] ?? '';
    $hash = base64_encode(hash_hmac('sha256', $rawBody, $secret, true));
    if (!hash_equals($hash, $sig)) {
        http_response_code(400);
        exit('Invalid signature');
    }
}

http_response_code(200);
header('Content-Type: application/json');

if (empty($payload['events'])) {
    echo json_encode(['ok' => true]);
    exit;
}

$pdo   = getDB();
$token = getSetting('line_channel_access_token', '');

foreach ($payload['events'] as $ev) {
    $userId     = $ev['source']['userId']  ?? '';
    $type       = $ev['type']              ?? '';
    $replyToken = $ev['replyToken']        ?? '';

    if (!$userId) continue;

    // ── Auto-save User ID ครั้งแรก ──────────────────────────────
    $saved = getSetting('line_admin_user_id', '');
    if (!$saved) {
        $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value)
            VALUES ('line_admin_user_id', ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ")->execute([$userId]);
        $saved = $userId;
    }

    // ── Handle text messages ─────────────────────────────────────
    if ($type === 'message' && ($ev['message']['type'] ?? '') === 'text' && $replyToken && $token) {
        $text = trim(mb_strtolower($ev['message']['text'] ?? ''));

        if ($text === 'id') {
            lineReply($replyToken, "🆔 LINE User ID ของคุณ:\n{$userId}\n\n(คัดลอกแล้วกรอกใน Admin → เชื่อมต่อระบบ)", $token);
        } elseif ($text === 'test') {
            lineReply($replyToken, "✅ BabyKawaii Admin\nระบบแจ้งเตือน LINE พร้อมแล้ว!\n\nเมื่อลูกค้าทักผ่าน Facebook/Instagram คุณจะได้รับการแจ้งเตือนที่นี่", $token);
        } else {
            // ข้อความอื่น — ตอบรับและแสดง User ID
            lineReply($replyToken, "🌸 BabyKawaii Admin Bot\nรับข้อความแล้วค่ะ\n\n💡 พิมพ์ id   → ดู User ID\n💡 พิมพ์ test → ทดสอบระบบ", $token);
        }
    }

    // ── Follow event (เพิ่มเพื่อน) ──────────────────────────────
    if ($type === 'follow' && $replyToken && $token) {
        lineReply($replyToken, "🌸 ยินดีต้อนรับสู่ BabyKawaii Admin!\n\nbot นี้จะแจ้งเตือนเมื่อลูกค้าส่งข้อความมา\n\nพิมพ์ id เพื่อรับ User ID สำหรับตั้งค่าระบบ", $token);
    }
}

echo json_encode(['ok' => true]);

// ── Helper: Reply message ────────────────────────────────────────
function lineReply(string $replyToken, string $text, string $accessToken): void
{
    $ch = curl_init('https://api.line.me/v2/bot/message/reply');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
        ],
        CURLOPT_POSTFIELDS     => json_encode([
            'replyToken' => $replyToken,
            'messages'   => [['type' => 'text', 'text' => $text]],
        ], JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    curl_exec($ch);
    curl_close($ch);
}
