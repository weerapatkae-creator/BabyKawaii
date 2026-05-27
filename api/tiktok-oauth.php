<?php
/**
 * BabyKawaii — TikTok Shop OAuth Callback
 * ────────────────────────────────────────
 * Redirect URL ที่ตั้งใน TikTok Partner Portal
 * URL: https://babykawaii.store/api/tiktok-oauth.php
 *
 * Flow:
 *   1. TikTok redirect มาพร้อม ?auth_code=xxx&state=xxx
 *   2. แลก auth_code เป็น access_token + refresh_token
 *   3. แสดงผลให้ admin copy ไปใส่ใน Platform Accounts
 */
require_once __DIR__ . '/../config/database.php';

$authCode  = trim($_GET['auth_code'] ?? '');
$stateRaw  = trim($_GET['state']     ?? '');
$errCode   = trim($_GET['error']     ?? '');

// ── ดึง credentials จาก state (base64 encoded: appKey:accountId) ──
$appKey    = '';
$appSecret = '';
$accountId = 0;
if ($stateRaw) {
    $decoded = base64_decode($stateRaw);
    if (str_contains($decoded, ':')) {
        [$appKey, $appSecret, $accountId] = explode(':', $decoded, 3) + ['','','0'];
        $accountId = (int)$accountId;
    }
}

// ── ถ้า TikTok ส่ง error กลับมา ──
if ($errCode) {
    $errMsg = htmlspecialchars($_GET['error_description'] ?? $errCode);
    die(renderPage('❌ Authorization Failed', "<div class='alert alert-danger'>$errMsg</div>"));
}

// ── ถ้าไม่มี auth_code ── แสดงหน้าเริ่มต้น (manual mode)
if (!$authCode) {
    echo renderPage('🔑 TikTok Shop OAuth', manualForm());
    exit;
}

// ── แลก auth_code เป็น access_token ──
$tokenData = null;
$errMsg    = '';
if ($appKey && $appSecret) {
    $tokenData = exchangeToken($appKey, $appSecret, $authCode);
    if (!$tokenData) $errMsg = 'แลก token ไม่สำเร็จ — ตรวจสอบ App Key / App Secret';
} else {
    // ไม่รู้ credentials → แสดงให้ user กรอกเอง
    echo renderPage('🔑 TikTok Shop OAuth — กรอก Credentials', manualExchangeForm($authCode));
    exit;
}

// ── บันทึก token ลง DB (ถ้ามี accountId) ──
$saved = false;
if ($tokenData && $accountId) {
    try {
        $pdo = getDB();
        $pdo->prepare("UPDATE platform_accounts SET page_access_token=?, updated_at=NOW() WHERE id=?")
            ->execute([$tokenData['access_token'], $accountId]);
        $saved = true;
    } catch (Exception $e) {}
}

// ── แสดงผล ──
if ($tokenData) {
    $html  = "<div class='alert alert-success'><strong>✅ ได้รับ Access Token สำเร็จ!</strong></div>";
    $html .= "<div class='card p-3 mb-3'>";
    $html .= "<div class='mb-2'><label class='fw-bold'>Access Token</label><div class='input-group'>";
    $html .= "<input type='text' class='form-control font-monospace' id='at' value='" . htmlspecialchars($tokenData['access_token']) . "' readonly>";
    $html .= "<button class='btn btn-outline-secondary' onclick=\"navigator.clipboard.writeText(document.getElementById('at').value);this.textContent='✅'\">Copy</button></div></div>";
    if (!empty($tokenData['refresh_token'])) {
        $html .= "<div class='mb-2'><label class='fw-bold text-muted small'>Refresh Token (เก็บไว้)</label>";
        $html .= "<input type='text' class='form-control form-control-sm font-monospace text-muted' value='" . htmlspecialchars($tokenData['refresh_token']) . "' readonly></div>";
    }
    $expire = $tokenData['access_token_expire_in'] ?? 0;
    if ($expire) $html .= "<div class='text-muted small'>หมดอายุใน: " . round($expire / 86400) . " วัน</div>";
    $html .= "</div>";

    if ($saved) {
        $html .= "<div class='alert alert-info'>✅ บันทึก Access Token ลงบัญชี #{$accountId} แล้วอัตโนมัติ</div>";
        $html .= "<a href='" . SITE_URL . "/pages/platform-accounts.php' class='btn btn-pink'>← กลับไปหน้า Platform Accounts</a>";
    } else {
        $html .= "<div class='alert alert-warning'>⚠️ คัดลอก Access Token ด้านบน แล้วนำไปใส่ในหน้า Platform Accounts ด้วยตนเอง</div>";
        $html .= "<a href='" . SITE_URL . "/pages/platform-accounts.php' class='btn btn-pink'>← ไปหน้า Platform Accounts</a>";
    }
    echo renderPage('🔑 TikTok Shop OAuth', $html);
} else {
    echo renderPage('❌ OAuth Error', "<div class='alert alert-danger'>" . htmlspecialchars($errMsg) . "</div>" . manualExchangeForm($authCode));
}

// ────────────────────────────────────────────────────
// Helper: แลก auth_code เป็น token
// ────────────────────────────────────────────────────
function exchangeToken(string $appKey, string $appSecret, string $authCode): ?array
{
    $url = 'https://open-api.tiktok-shops.com/api/v2/token/get?' . http_build_query([
        'app_key'    => $appKey,
        'app_secret' => $appSecret,
        'auth_code'  => $authCode,
        'grant_type' => 'authorized_code',
    ]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    if (!$resp) return null;
    $json = json_decode($resp, true);
    return (isset($json['data']['access_token'])) ? $json['data'] : null;
}

// ────────────────────────────────────────────────────
// Helper: แบบฟอร์ม exchange token ด้วยตนเอง
// ────────────────────────────────────────────────────
function manualExchangeForm(string $authCode = ''): string
{
    return "
    <div class='alert alert-warning'>ไม่พบ App Key/Secret ใน state — กรอกเองด้านล่างเพื่อแลก token</div>
    <form method='post' action='" . SITE_URL . "/api/tiktok-oauth-exchange.php'>
        <input type='hidden' name='auth_code' value='" . htmlspecialchars($authCode) . "'>
        <div class='mb-3'><label class='fw-bold'>App Key</label>
            <input type='text' name='app_key' class='form-control font-monospace' required></div>
        <div class='mb-3'><label class='fw-bold'>App Secret</label>
            <input type='password' name='app_secret' class='form-control font-monospace' required></div>
        <div class='mb-3'><label class='fw-bold'>Auth Code</label>
            <input type='text' name='auth_code' class='form-control font-monospace' value='" . htmlspecialchars($authCode) . "' required></div>
        <button class='btn btn-pink' type='submit'>แลก Token</button>
    </form>";
}

function manualForm(): string
{
    $siteUrl = SITE_URL;
    return "
    <div class='alert alert-info'>
        <strong>วิธีใช้:</strong><br>
        1. ไปที่หน้า <a href='{$siteUrl}/pages/platform-accounts.php'>Platform Accounts</a><br>
        2. แก้ไขบัญชี TikTok → คัดลอก <strong>Authorize URL</strong><br>
        3. เปิด URL นั้น → อนุมัติสิทธิ์ → ระบบจะ redirect กลับมาที่นี่พร้อม token อัตโนมัติ
    </div>
    <a href='{$siteUrl}/pages/platform-accounts.php' class='btn btn-outline-secondary'>← Platform Accounts</a>";
}

// ────────────────────────────────────────────────────
// Helper: render HTML page
// ────────────────────────────────────────────────────
function renderPage(string $title, string $content): string
{
    $siteUrl = SITE_URL;
    return "<!DOCTYPE html>
<html lang='th'>
<head>
<meta charset='UTF-8'>
<meta name='viewport' content='width=device-width,initial-scale=1'>
<title>{$title} — BabyKawaii</title>
<link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css'>
<style>
body{background:#fff5f8;font-family:system-ui,sans-serif;}
.btn-pink{background:linear-gradient(135deg,#FF85A2,#d4629e);color:#fff;border:none;}
.card{border-radius:12px;border:1px solid #ffe0eb;}
</style>
</head>
<body>
<div class='container py-5' style='max-width:600px'>
    <div class='d-flex align-items-center gap-3 mb-4'>
        <span style='font-size:2rem;'>🛍️</span>
        <div><h4 class='mb-0'>BabyKawaii Admin</h4><small class='text-muted'>TikTok Shop Integration</small></div>
    </div>
    <h5 class='mb-3'>{$title}</h5>
    {$content}
</div>
</body></html>";
}
