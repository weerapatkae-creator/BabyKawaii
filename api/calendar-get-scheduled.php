<?php
/**
 * BabyKawaii — Calendar: Get posts ready to publish
 * n8n polls this every minute via HTTP Request node
 *
 * GET /api/calendar-get-scheduled.php
 * Headers: X-API-Key: {api_key}
 *
 * Returns: array of posts with media URLs + per-platform captions
 */
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// Auth
$apiKey   = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';
$validKey = getSetting('api_key', '');
if ($validKey && $apiKey !== $validKey) {
    http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit;
}

$pdo = getDB();

// Get posts that are scheduled and due (within next 2 minutes buffer)
$posts = $pdo->prepare("
    SELECT c.*,
           GROUP_CONCAT(pp.platform_id) as confirmed_platforms
    FROM content_calendar c
    LEFT JOIN post_platforms pp ON pp.post_id = c.id AND pp.status = 'pending'
    WHERE c.publish_status = 'scheduled'
      AND c.scheduled_at <= DATE_ADD(NOW(), INTERVAL 2 MINUTE)
      AND c.scheduled_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
    GROUP BY c.id
    ORDER BY c.scheduled_at ASC
    LIMIT 20
");
$posts->execute();
$posts = $posts->fetchAll(PDO::FETCH_ASSOC);

$result = [];

foreach ($posts as $post) {
    // Get platform details
    $pids     = array_filter(array_map('intval', explode(',', $post['platform_ids'] ?? '')));
    $platforms = [];
    if ($pids) {
        $ph = implode(',', array_fill(0, count($pids), '?'));
        $pfStmt = $pdo->prepare("SELECT * FROM platforms WHERE id IN ($ph) AND is_active=1");
        $pfStmt->execute($pids);
        $platforms = $pfStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Per-platform captions
    $captions = [];
    try { $captions = json_decode($post['captions_json'] ?? '{}', true) ?: []; } catch(Exception $e) {}

    // Media files
    $mediaItems = [];
    $mids = array_filter(array_map('intval', explode(',', $post['media_ids'] ?? '')));
    if ($mids) {
        $mh = implode(',', array_fill(0, count($mids), '?'));
        $mStmt = $pdo->prepare("SELECT * FROM media WHERE id IN ($mh)");
        $mStmt->execute($mids);
        foreach ($mStmt->fetchAll(PDO::FETCH_ASSOC) as $m) {
            $mediaItems[] = [
                'id'        => $m['id'],
                'type'      => $m['file_type'],
                'url'       => SITE_URL . '/' . ltrim($m['file_path'] ?? '', '/'),
                'thumbnail' => $m['thumbnail_path'] ? SITE_URL . '/' . ltrim($m['thumbnail_path'], '/') : null,
                'filename'  => $m['original_name'],
            ];
        }
    }

    // Product info
    $product = null;
    if ($post['product_id']) {
        $p = $pdo->prepare("SELECT id,name,sku,selling_price FROM products WHERE id=?");
        $p->execute([$post['product_id']]);
        $product = $p->fetch(PDO::FETCH_ASSOC);
    }

    // Promotion info
    $promotion = null;
    if ($post['promotion_id']) {
        $p = $pdo->prepare("SELECT id,name,discount_type,discount_value FROM promotions WHERE id=?");
        $p->execute([$post['promotion_id']]);
        $promotion = $p->fetch(PDO::FETCH_ASSOC);
    }

    // Build per-platform publish items
    $publishItems = [];
    foreach ($platforms as $pf) {
        $pfCaption = $captions[$pf['id']] ?? $post['caption'] ?? '';
        $hashtags  = $post['hashtags'] ?? '';
        $fullCaption = trim($pfCaption . ($hashtags ? "\n\n" . $hashtags : ''));

        $publishItems[] = [
            'platform_id'   => $pf['id'],
            'platform_name' => $pf['name'],
            'platform_type' => strtolower($pf['name']),
            'caption'       => $fullCaption,
            'caption_raw'   => $pfCaption,
            'hashtags'      => $hashtags,
        ];
    }

    // Mark as publishing (prevent duplicate processing)
    $pdo->prepare("UPDATE content_calendar SET publish_status='publishing', updated_at=NOW() WHERE id=?")
        ->execute([$post['id']]);

    // Create post_platforms rows
    foreach ($pids as $pid) {
        $pdo->prepare("INSERT IGNORE INTO post_platforms (post_id, platform_id, status) VALUES (?,?,'publishing')")
            ->execute([$post['id'], $pid]);
        $pdo->prepare("UPDATE post_platforms SET status='publishing', updated_at=NOW() WHERE post_id=? AND platform_id=?")
            ->execute([$post['id'], $pid]);
    }

    $result[] = [
        'post_id'       => (int)$post['id'],
        'title'         => $post['title'],
        'post_type'     => $post['post_type'],
        'scheduled_at'  => $post['scheduled_at'],
        'caption'       => $post['caption'],
        'hashtags'      => $post['hashtags'],
        'notes'         => $post['notes'],
        'media'         => $mediaItems,
        'platforms'     => $publishItems,
        'product'       => $product,
        'promotion'     => $promotion,
        'callback_url'  => SITE_URL . '/api/calendar-publish-update.php',
        'api_key'       => $validKey,
    ];
}

echo json_encode([
    'ok'    => true,
    'count' => count($result),
    'posts' => $result,
    'time'  => date('Y-m-d H:i:s'),
]);
