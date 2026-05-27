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
        $url = "https://graph.facebook.com/v19.0/{$userId}?fields=name,profile_pic&access_token=" . urlencode($accessToken);
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $res  = curl_exec($ch);
        curl_close($ch);
        $data = $res ? json_decode($res, true) : [];
        return [$data['name'] ?? null, $data['profile_pic'] ?? null];
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
