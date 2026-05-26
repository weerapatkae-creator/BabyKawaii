<?php
/**
 * BabyKawaii API — Auth & Response Helpers
 */
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ── Authentication ────────────────────────────────────────────────────────────
function requireApiKey() {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    // Normalize header keys to lowercase
    $headers = array_change_key_case($headers, CASE_LOWER);

    $token = $headers['authorization'] ?? $headers['x-api-key'] ?? $_GET['api_key'] ?? '';
    $token = trim(str_replace('Bearer ', '', $token));

    $stored = getSetting('api_key', '');
    if (!$stored || !hash_equals($stored, $token)) {
        jsonErr('Unauthorized — invalid or missing API key', 401);
    }
}

// ── Response helpers ─────────────────────────────────────────────────────────
function jsonOK($data, int $code = 200): void {
    http_response_code($code);
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function jsonErr(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

function getBody(): array {
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?? [];
}

function method(): string { return strtoupper($_SERVER['REQUEST_METHOD']); }
