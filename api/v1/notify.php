<?php
/**
 * BabyKawaii API — Send LINE Notification (internal trigger)
 * POST /api/v1/notify.php
 * Body: { "type": "line_message", "message": "...", "user_id": "..." }
 *
 * NOTE: LINE Notify ปิดให้บริการแล้ว (31 มี.ค. 2568)
 *       ใช้ LINE Messaging API (type=line_message) แทนทั้งหมด
 */
require_once __DIR__ . '/auth.php';
requireApiKey();

if (method() !== 'POST') jsonErr('Method not allowed', 405);

$pdo  = getDB();
$body = getBody();

$type    = $body['type']    ?? 'line_message';
$message = trim($body['message'] ?? '');
if (!$message) jsonErr('message required');

// ── LINE Notify — deprecated (ปิดบริการแล้ว 31 มี.ค. 2568) ──────────────────
if ($type === 'line_notify') {
    jsonErr('LINE Notify ปิดให้บริการแล้ว กรุณาใช้ type=line_message แทน', 410);
}

// ── LINE Messaging API (push to admin or specific user) ──────────────────────
if ($type === 'line_message') {
    $token  = getSetting('line_channel_access_token', '');
    $userId = $body['user_id'] ?? getSetting('line_admin_user_id', '');
    if (!$token)  jsonErr('LINE Channel Access Token not configured', 503);
    if (!$userId) jsonErr('user_id required for line_message', 400);

    $payload = json_encode([
        'to'       => $userId,
        'messages' => [['type'=>'text','text'=>$message]],
    ]);

    $ch = curl_init('https://api.line.me/v2/bot/message/push');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer $token",
            "Content-Type: application/json",
        ],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT    => 10,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code === 200) jsonOK(['sent'=>true,'channel'=>'line_message','user_id'=>$userId]);
    jsonErr("LINE Message API failed (HTTP $code): $resp", 502);
}

jsonErr('Unknown type. Use: line_notify or line_message');
