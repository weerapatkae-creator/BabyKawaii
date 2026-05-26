<?php
/**
 * BabyKawaii API — LINE Chat Session State
 * Used by n8n to store/retrieve chatbot conversation state per LINE user
 *
 * GET  /api/v1/chat.php?user_id=Uxxxx       → get session
 * POST /api/v1/chat.php                      → upsert session
 * DELETE /api/v1/chat.php?user_id=Uxxxx     → reset session
 */
require_once __DIR__ . '/auth.php';
requireApiKey();

$pdo = getDB();

if (method() === 'GET') {
    $uid = trim($_GET['user_id'] ?? '');
    if (!$uid) jsonErr('user_id required');

    $stmt = $pdo->prepare("SELECT * FROM line_chat_sessions WHERE line_user_id = ?");
    $stmt->execute([$uid]);
    $session = $stmt->fetch();

    if (!$session) {
        jsonOK(['line_user_id'=>$uid,'state'=>'idle','data'=>null,'exists'=>false]);
    }
    $session['data'] = $session['data'] ? json_decode($session['data'], true) : null;
    $session['exists'] = true;
    jsonOK($session);
}

if (method() === 'POST') {
    $body = getBody();
    $uid  = trim($body['user_id'] ?? '');
    if (!$uid) jsonErr('user_id required');

    $state       = $body['state'] ?? 'idle';
    $data        = isset($body['data']) ? json_encode($body['data'], JSON_UNESCAPED_UNICODE) : null;
    $displayName = $body['display_name'] ?? null;

    $pdo->prepare("INSERT INTO line_chat_sessions (line_user_id, display_name, state, data)
        VALUES (?,?,?,?)
        ON DUPLICATE KEY UPDATE state=VALUES(state), data=VALUES(data),
            display_name=COALESCE(VALUES(display_name), display_name)")
        ->execute([$uid, $displayName, $state, $data]);

    jsonOK(['line_user_id'=>$uid,'state'=>$state]);
}

if (method() === 'DELETE') {
    $uid = trim($_GET['user_id'] ?? '');
    if (!$uid) jsonErr('user_id required');
    $pdo->prepare("DELETE FROM line_chat_sessions WHERE line_user_id=?")->execute([$uid]);
    jsonOK(['deleted'=>$uid]);
}

jsonErr('Method not allowed', 405);
