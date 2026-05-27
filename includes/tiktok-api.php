<?php
/**
 * BabyKawaii — TikTok Shop Open API Helpers
 * ──────────────────────────────────────────
 * Shared by:
 *   pages/inbox.php  → ใช้ตอนส่งข้อความตอบกลับผ่าน TikTok Shop IM API
 *
 * ต้องเตรียมข้อมูลใน platform_accounts:
 *   account_uid          = Shop ID
 *   page_access_token    = Access Token (จาก TikTok Partner Portal)
 *   app_secret           = App Secret
 *   webhook_verify_token = App Key
 */

if (!function_exists('tiktokSign')) {
    /**
     * คำนวณ signature สำหรับ TikTok Shop REST API
     * format: SHA256(app_secret + sorted_params_string + app_secret)
     * sorted_params_string = concat sorted query/body params (ยกเว้น sign, access_token)
     */
    function tiktokSign(string $appSecret, array $params): string
    {
        ksort($params);
        $str = $appSecret;
        foreach ($params as $k => $v) {
            $str .= $k . $v;
        }
        $str .= $appSecret;
        return hash('sha256', $str);
    }
}

if (!function_exists('sendTiktokMessage')) {
    /**
     * ส่งข้อความตอบกลับลูกค้าผ่าน TikTok Shop IM API
     *
     * @param string $conversationId  conversation_id จาก webhook (เก็บเป็น customer_uid)
     * @param string $text            ข้อความที่จะส่ง
     * @param string $accessToken     Access Token จาก TikTok Partner Portal
     * @param string $shopId          Shop ID (account_uid)
     * @param string $appKey          App Key (webhook_verify_token)
     * @param string $appSecret       App Secret
     * @return bool true = ส่งสำเร็จ
     */
    function sendTiktokMessage(
        string $conversationId,
        string $text,
        string $accessToken,
        string $shopId,
        string $appKey,
        string $appSecret
    ): bool {
        if (!$accessToken || !$appKey || !$appSecret || !$conversationId) return false;

        $timestamp = time();

        // Query params (ยกเว้น access_token และ sign)
        $queryParams = [
            'app_key'   => $appKey,
            'shop_id'   => $shopId,
            'timestamp' => (string)$timestamp,
        ];

        // Body params (JSON)
        $body = [
            'conversation_id' => $conversationId,
            'message_type'    => 1,   // 1 = text
            'text'            => $text,
        ];

        // Sign: query params + body params (ยกเว้น access_token, sign)
        $signParams = array_merge($queryParams, [
            'conversation_id' => $conversationId,
            'message_type'    => '1',
            'text'            => $text,
        ]);
        $queryParams['sign']         = tiktokSign($appSecret, $signParams);
        $queryParams['access_token'] = $accessToken;

        $url = 'https://open-api.tiktok-shops.com/api/im/chatmessage/send?' . http_build_query($queryParams);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$resp) return false;
        $json = json_decode($resp, true);

        // TikTok Shop API ตอบ code=0 = สำเร็จ
        return isset($json['code']) && (int)$json['code'] === 0;
    }
}
