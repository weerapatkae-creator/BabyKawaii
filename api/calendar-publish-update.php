<?php
/**
 * BabyKawaii — Calendar: n8n reports publish result back
 *
 * POST body (JSON):
 * {
 *   "post_id": 5,
 *   "results": [
 *     { "platform_id": 1, "status": "published", "platform_post_id": "12345" },
 *     { "platform_id": 2, "status": "failed",    "error": "Invalid token" }
 *   ]
 * }
 */
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$apiKey   = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';
$validKey = getSetting('api_key', '');
if ($validKey && $apiKey !== $validKey) {
    http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error'=>'POST only']); exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['post_id'])) {
    http_response_code(400); echo json_encode(['error'=>'post_id required']); exit;
}

$pdo    = getDB();
$postId = (int)$data['post_id'];
$results= $data['results'] ?? [];

if (empty($results)) {
    http_response_code(400); echo json_encode(['error'=>'results array required']); exit;
}

$successCount = 0;
$failCount    = 0;
$publishedPlatforms = [];
$errors = [];

foreach ($results as $r) {
    $pfId      = (int)($r['platform_id'] ?? 0);
    $status    = in_array($r['status'] ?? '', ['published','failed','skipped']) ? $r['status'] : 'failed';
    $pfPostId  = $r['platform_post_id'] ?? null;
    $errorMsg  = $r['error'] ?? null;

    $pdo->prepare("UPDATE post_platforms
        SET status=?, platform_post_id=?, published_at=IF(?='published',NOW(),NULL), error_msg=?, updated_at=NOW()
        WHERE post_id=? AND platform_id=?")
        ->execute([$status, $pfPostId, $status, $errorMsg, $postId, $pfId]);

    if ($status === 'published') {
        $successCount++;
        $publishedPlatforms[] = $pfId;
    } else {
        $failCount++;
        if ($errorMsg) $errors[] = "Platform $pfId: $errorMsg";
    }
}

// Determine overall publish_status
$total = count($results);
if ($successCount === $total) {
    $overallStatus = 'published';
} elseif ($successCount > 0) {
    $overallStatus = 'published'; // partial success = mark published, errors noted
} else {
    $overallStatus = 'failed';
}

$pdo->prepare("UPDATE content_calendar
    SET publish_status=?,
        status=?,
        published_at=IF(?='published',NOW(),published_at),
        published_platforms=?,
        publish_errors=?,
        updated_at=NOW()
    WHERE id=?")
    ->execute([
        $overallStatus,
        $overallStatus,
        $overallStatus,
        implode(',', $publishedPlatforms),
        $errors ? implode('; ', $errors) : null,
        $postId,
    ]);

// Get post info for LINE notification
$post = $pdo->prepare("SELECT title, scheduled_at FROM content_calendar WHERE id=?");
$post->execute([$postId]);
$post = $post->fetch();

// Notify admin via LINE
if ($post) {
    if ($overallStatus === 'published') {
        $msg = "\n✅ โพสต์สำเร็จ!\n📝 {$post['title']}\n📊 สำเร็จ {$successCount}/{$total} แพลตฟอร์ม";
    } else {
        $msg = "\n❌ โพสต์ล้มเหลว!\n📝 {$post['title']}\n⚠️ " . implode(', ', $errors);
    }
    sendLineNotify($msg);
}

echo json_encode([
    'ok'              => true,
    'post_id'         => $postId,
    'overall_status'  => $overallStatus,
    'success'         => $successCount,
    'failed'          => $failCount,
    'total'           => $total,
]);
