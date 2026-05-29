<?php
/**
 * BabyKawaii — Meta (Facebook / Instagram) API Helpers
 * ─────────────────────────────────────────────────────
 * Shared by:
 *   api/meta-webhook.php  → ใช้ตอนรับข้อความขาเข้า
 *   pages/inbox.php       → ใช้ตอนส่งข้อความตอบกลับ
 */

if (!function_exists('fetchFbProfile')) {
    /**
     * ดึงชื่อ + รูปโปรไฟล์จาก Facebook Graph API
     * @return array [string|null $name, string|null $avatarUrl]
     */
    function fetchFbProfile(string $userId, string $accessToken): array
    {
        if (!$accessToken || !$userId) return [null, null];

        // วิธีที่ 1: ดึงชื่อจาก Conversations API (ใช้ได้โดยไม่ต้องขอ user_profile permission)
        $convUrl = "https://graph.facebook.com/v19.0/me/conversations"
            . "?user_id=" . urlencode($userId)
            . "&fields=participants"
            . "&access_token=" . urlencode($accessToken);
        $ch = curl_init($convUrl);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>6, CURLOPT_SSL_VERIFYPEER=>false]);
        $res  = curl_exec($ch);
        curl_close($ch);
        $conv = $res ? json_decode($res, true) : [];

        $name = null;
        if (!empty($conv['data'][0]['participants']['data'])) {
            // หา participant ที่ id ตรงกับ customer_uid
            foreach ($conv['data'][0]['participants']['data'] as $p) {
                if (($p['id'] ?? '') === $userId) {
                    $name = $p['name'] ?? null;
                    break;
                }
            }
            // fallback: เอาคนแรกที่มีชื่อ
            if (!$name) {
                foreach ($conv['data'][0]['participants']['data'] as $p) {
                    if (!empty($p['name'])) { $name = $p['name']; break; }
                }
            }
        }

        // รูปโปรไฟล์ — ใช้ Graph API picture redirect (ไม่ต้อง access token)
        $avatarUrl = "https://graph.facebook.com/{$userId}/picture?type=normal";

        return [$name, $avatarUrl];
    }
}

if (!function_exists('sendFbMessage')) {
    /**
     * ส่งข้อความกลับหาลูกค้าผ่าน Facebook / Instagram Send API
     * @return bool true = สำเร็จ
     */
    function sendFbMessage(string $recipientId, string $text, string $accessToken): bool
    {
        if (!$accessToken) return false;
        $url = "https://graph.facebook.com/v19.0/me/messages?access_token=" . urlencode($accessToken);
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode([
                'recipient' => ['id' => $recipientId],
                'message'   => ['text' => $text],
            ]),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code === 200;
    }
}
