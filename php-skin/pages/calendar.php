<?php
// ── Bootstrap DB + auth BEFORE any output ────────────────────────────────────
require_once __DIR__ . '/../config/database.php';
requireLogin();
$pdo = getDB();

// ── Month navigation ──────────────────────────────────────────────────────────
$year  = (int)($_GET['y'] ?? date('Y'));
$month = (int)($_GET['m'] ?? date('n'));
if ($month < 1)  { $month = 12; $year--; }
if ($month > 12) { $month = 1;  $year++; }
$prevY = $month === 1  ? $year - 1 : $year;  $prevM = $month === 1  ? 12 : $month - 1;
$nextY = $month === 12 ? $year + 1 : $year;  $nextM = $month === 12 ? 1  : $month + 1;

$monthStart  = new DateTime("$year-$month-01");
$daysInMonth = (int)$monthStart->format('t');
$firstDow    = (int)$monthStart->format('w'); // 0=Sun
$today       = date('Y-m-d');

$thaiMonths = ['','มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน',
               'กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
$monthLabel  = $thaiMonths[$month] . ' ' . ($year + 543);

// ── Handle POST actions (must run before ANY output) ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Business Event: save ──────────────────────────────────────────────────
    if ($action === 'save_biz') {
        header('Content-Type: application/json');
        $id       = (int)($_POST['id'] ?? 0);
        $title    = trim($_POST['title']      ?? '');
        $evType   = $_POST['event_type']      ?? 'other';
        $startAt  = trim($_POST['start_at']   ?? '');
        $endAt    = trim($_POST['end_at']     ?? '') ?: null;
        $amount   = $_POST['amount'] !== '' ? (float)$_POST['amount'] : null;
        $notes    = trim($_POST['notes']      ?? '');
        $color    = trim($_POST['color']      ?? '#E67E22');

        if (!$title || !$startAt) { echo json_encode(['ok'=>false,'msg'=>'กรุณากรอกชื่อและวันเวลา']); exit; }

        if ($id) {
            $pdo->prepare("UPDATE business_events SET title=?,event_type=?,start_at=?,end_at=?,amount=?,notes=?,color=? WHERE id=?")
                ->execute([$title,$evType,$startAt,$endAt,$amount,$notes,$color,$id]);
        } else {
            $pdo->prepare("INSERT INTO business_events (title,event_type,start_at,end_at,amount,notes,color,created_by) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$title,$evType,$startAt,$endAt,$amount,$notes,$color,$_SESSION['admin_id']??null]);
            $id = $pdo->lastInsertId();
        }
        echo json_encode(['ok'=>true,'id'=>$id]);
        exit;
    }

    // ── Business Event: delete ────────────────────────────────────────────────
    if ($action === 'delete_biz') {
        header('Content-Type: application/json');
        $pdo->prepare("DELETE FROM business_events WHERE id=?")->execute([(int)$_POST['id']]);
        echo json_encode(['ok'=>true]);
        exit;
    }

    if ($action === 'save') {
        $id          = (int)($_POST['id'] ?? 0);
        $title       = trim($_POST['title'] ?? '');
        $ctype       = $_POST['content_type'] ?? 'post';
        $postType    = $_POST['post_type']    ?? 'image';
        $platforms   = implode(',', array_map('intval', (array)($_POST['platform_ids'] ?? [])));
        $productId   = (int)($_POST['product_id'] ?? 0) ?: null;
        $promoId     = (int)($_POST['promotion_id'] ?? 0) ?: null;
        $caption     = trim($_POST['caption'] ?? '');
        $hashtags    = trim($_POST['hashtags'] ?? '');
        $scheduledAt = $_POST['scheduled_at'] ?: null;
        $status      = $_POST['status'] ?? 'draft';
        $notes       = trim($_POST['notes'] ?? '');
        $mediaIds    = trim($_POST['media_ids'] ?? '');

        // Per-platform captions
        $captionsJson = null;
        $pfCaptions = $_POST['caption_pf'] ?? [];
        if (!empty(array_filter($pfCaptions))) {
            $captionsJson = json_encode($pfCaptions, JSON_UNESCAPED_UNICODE);
        }

        if (!$title) { header("Location: ?y=$year&m=$month&err=notitle"); exit; }

        if ($id) {
            $pdo->prepare("UPDATE content_calendar SET title=?,content_type=?,post_type=?,platform_ids=?,
                product_id=?,promotion_id=?,caption=?,captions_json=?,hashtags=?,scheduled_at=?,status=?,publish_status=?,media_ids=?,notes=?
                WHERE id=?")->execute([$title,$ctype,$postType,$platforms,$productId,$promoId,
                $caption,$captionsJson,$hashtags,$scheduledAt,$status,$status,$mediaIds,$notes,$id]);
        } else {
            $pdo->prepare("INSERT INTO content_calendar
                (title,content_type,post_type,platform_ids,product_id,promotion_id,caption,captions_json,hashtags,scheduled_at,status,publish_status,media_ids,notes)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")->execute([$title,$ctype,$postType,$platforms,
                $productId,$promoId,$caption,$captionsJson,$hashtags,$scheduledAt,$status,$status,$mediaIds,$notes]);
            $newId = $pdo->lastInsertId();
            // Trigger n8n if scheduled
            if ($status === 'scheduled' && $scheduledAt) {
                triggerN8n('content_scheduled', ['post_id'=>$newId,'title'=>$title,'scheduled_at'=>$scheduledAt,'platforms'=>$platforms]);
            }
        }
        header("Location: ?y=$year&m=$month&msg=saved"); exit;
    }

    if ($action === 'delete') {
        $pdo->prepare("DELETE FROM content_calendar WHERE id=?")->execute([(int)$_POST['id']]);
        header("Location: ?y=$year&m=$month&msg=deleted"); exit;
    }

    if ($action === 'status') {
        $newStatus = $_POST['new_status'];
        $pubAt     = $newStatus === 'published' ? ', published_at=NOW()' : '';
        $pdo->prepare("UPDATE content_calendar SET status=? $pubAt WHERE id=?")
            ->execute([$newStatus, (int)$_POST['id']]);
        echo json_encode(['ok' => true]); exit;
    }

    if ($action === 'publish_now') {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['ok'=>false,'error'=>'id required']); exit; }
        $pdo->prepare("UPDATE content_calendar
            SET status='scheduled', publish_status='scheduled',
                scheduled_at=NOW(), updated_at=NOW()
            WHERE id=?")
            ->execute([$id]);
        $post = $pdo->prepare("SELECT title, platform_ids FROM content_calendar WHERE id=?");
        $post->execute([$id]);
        $post = $post->fetch();
        if ($post) {
            triggerN8n('content_scheduled', [
                'post_id'      => $id,
                'title'        => $post['title'],
                'scheduled_at' => date('Y-m-d H:i:s'),
                'platforms'    => $post['platform_ids'],
            ]);
        }
        echo json_encode(['ok'=>true, 'msg'=>'กำหนดโพสต์ทันที — n8n จะดำเนินการภายใน 1 นาที']); exit;
    }
}

// ── Now safe to output HTML ───────────────────────────────────────────────────
$pageTitle = 'ปฏิทินโพสต์';
require_once __DIR__ . '/../includes/header.php';

// ── Load data ─────────────────────────────────────────────────────────────────
$rangeStart = sprintf('%04d-%02d-01 00:00:00', $year, $month);
$rangeEnd   = sprintf('%04d-%02d-%02d 23:59:59', $year, $month, $daysInMonth);

$posts = $pdo->prepare("SELECT c.*, p.name as product_name
    FROM content_calendar c
    LEFT JOIN products p ON p.id = c.product_id
    WHERE (c.scheduled_at BETWEEN ? AND ?) OR (c.scheduled_at IS NULL AND MONTH(c.created_at)=? AND YEAR(c.created_at)=?)
    ORDER BY c.scheduled_at ASC, c.id ASC");
$posts->execute([$rangeStart, $rangeEnd, $month, $year]);
$posts = $posts->fetchAll();

// Index posts by day
$postsByDay = [];
foreach ($posts as $post) {
    $day = $post['scheduled_at'] ? (int)date('j', strtotime($post['scheduled_at'])) : 0;
    $postsByDay[$day][] = $post;
}

// ── Business events (ซื้อสินค้า, ประชุม, ฯลฯ) ────────────────────────────────
$bizEvents = $pdo->prepare("SELECT * FROM business_events WHERE start_at BETWEEN ? AND ? ORDER BY start_at ASC");
$bizEvents->execute([$rangeStart, $rangeEnd]);
$bizEvents = $bizEvents->fetchAll();

$bizByDay = [];
foreach ($bizEvents as $ev) {
    $day = (int)date('j', strtotime($ev['start_at']));
    $bizByDay[$day][] = $ev;
}

$platforms  = $pdo->query("SELECT * FROM platforms WHERE is_active=1 ORDER BY name")->fetchAll();
$products   = $pdo->query("SELECT id,name FROM products WHERE status='active' ORDER BY name")->fetchAll();
$promotions = $pdo->query("SELECT id,name FROM promotions WHERE status='active' ORDER BY name")->fetchAll();

// Map platform id → object
$platformMap = [];
foreach ($platforms as $pf) { $platformMap[$pf['id']] = $pf; }

// Load per-platform publish statuses for this month's posts
$postIds = array_column($posts, 'id');
$postPlatformsByPostId = [];
if (!empty($postIds)) {
    try {
        $ph  = implode(',', array_fill(0, count($postIds), '?'));
        $ppStmt = $pdo->prepare("SELECT * FROM post_platforms WHERE post_id IN ($ph)");
        $ppStmt->execute($postIds);
        foreach ($ppStmt->fetchAll() as $pp) {
            $postPlatformsByPostId[$pp['post_id']][$pp['platform_id']] = $pp;
        }
    } catch (Exception $e) {
        // post_platforms table may not exist yet
    }
}

// Stats this month (use publish_status for accuracy)
$statTotal     = count($posts);
$statDraft     = count(array_filter($posts, fn($p) => ($p['publish_status'] ?? $p['status']) === 'draft'));
$statScheduled = count(array_filter($posts, fn($p) => in_array($p['publish_status'] ?? $p['status'], ['scheduled','publishing'])));
$statPublished = count(array_filter($posts, fn($p) => ($p['publish_status'] ?? $p['status']) === 'published'));
$statFailed    = count(array_filter($posts, fn($p) => ($p['publish_status'] ?? $p['status']) === 'failed'));

// Content type config
$ctypes = [
    'post'    => ['📝','โพสต์','#4FC3F7'],
    'story'   => ['📸','Story','#F48FB1'],
    'reel'    => ['🎬','Reel','#CE93D8'],
    'video'   => ['🎥','วิดีโอ','#FFCC80'],
    'live'    => ['📡','Live','#EF9A9A'],
    'ad'      => ['📢','โฆษณา','#A5D6A7'],
];

// Post type config (used in calendar chips + modal)
$postTypes = [
    'video'        => ['🎬','วิดีโอ/คลิป','#CE93D8'],
    'reel'         => ['🎥','Reel/Short','#FFCC80'],
    'image'        => ['📸','รูปสินค้า','#4FC3F7'],
    'product'      => ['🛍️','เปิดตัวสินค้า','#81C784'],
    'promo'        => ['🏷️','โปรโมชั่น','#FF8A65'],
    'announcement' => ['📢','ประกาศ','#F48FB1'],
    'story'        => ['✨','Story','#B39DDB'],
    'live'         => ['📡','Live','#EF9A9A'],
];

$statusCfg = [
    'draft'     => ['badge-secondary','ร่าง'],
    'scheduled' => ['badge-primary','กำหนดแล้ว'],
    'published' => ['badge-success','เผยแพร่แล้ว'],
    'cancelled' => ['badge-danger','ยกเลิก'],
];

// Publish status badge config
$pubStatusCfg = [
    'draft'      => ['⬜','#aaa',     'ร่าง'],
    'scheduled'  => ['📅','#4FC3F7',  'กำหนดแล้ว'],
    'publishing' => ['🔄','#FF9800',  'กำลังโพสต์...'],
    'published'  => ['✅','#3AA088',  'โพสต์แล้ว'],
    'failed'     => ['❌','#E45252',  'ล้มเหลว'],
    'cancelled'  => ['🚫','#aaa',     'ยกเลิก'],
];

// Encode all post data for JS
$postsJS = json_encode(array_values($posts), JSON_UNESCAPED_UNICODE);
$platformsJS = json_encode(array_values($platformMap), JSON_UNESCAPED_UNICODE);
?>

<div class="container-fluid fade-in">

    <!-- ── Header ──────────────────────────────────────────────────────────── -->
    <div class="page-header">
        <div>
            <h1 class="page-title">📅 ปฏิทินโพสต์</h1>
            <p class="page-subtitle">วางแผนและตั้งเวลาโพสต์คอนเทนต์ทุกแพลตฟอร์ม</p>
        </div>
        <div class="page-actions d-flex gap-2">
            <div class="btn-group">
                <button class="btn btn-outline-secondary btn-sm" onclick="setView('calendar')" id="btnCal">
                    <i class="fas fa-calendar-days"></i> ปฏิทิน
                </button>
                <button class="btn btn-outline-secondary btn-sm" onclick="setView('list')" id="btnList">
                    <i class="fas fa-list"></i> รายการ
                </button>
            </div>
            <button class="btn btn-outline-warning" onclick="openBizModal()">
                <i class="fas fa-calendar-plus me-1"></i> เพิ่ม Event งาน
            </button>
            <button class="btn btn-primary" onclick="openAdd()">
                <i class="fas fa-plus me-1"></i> เพิ่มโพสต์
            </button>
        </div>
    </div>

    <?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success alert-auto">
        ✅ <?= ['saved'=>'บันทึกโพสต์เรียบร้อย','deleted'=>'ลบโพสต์แล้ว'][$_GET['msg']] ?? 'สำเร็จ' ?>
    </div>
    <?php endif; ?>

    <!-- ── Stat cards ──────────────────────────────────────────────────────── -->
    <div class="row g-3 mb-4 row-cols-2 row-cols-md-5">
        <?php foreach ([
            ['📅','โพสต์ทั้งหมด',$statTotal,   '#8A6DB0'],
            ['✏️','ร่าง',        $statDraft,    '#8585A0'],
            ['⏰','กำหนดแล้ว',   $statScheduled,'#4FC3F7'],
            ['✅','โพสต์แล้ว',   $statPublished,'#3AA088'],
            ['❌','ล้มเหลว',     $statFailed,   '#E45252'],
        ] as [$icon,$label,$val,$color]): ?>
        <div class="col">
            <div class="stat-card" style="border-left:4px solid <?= $color ?>">
                <div class="stat-icon" style="color:<?= $color ?>"><?= $icon ?></div>
                <div class="stat-value"><?= $val ?></div>
                <div class="stat-label"><?= $label ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ── Month Navigator ─────────────────────────────────────────────────── -->
    <div class="card mb-3">
        <div class="card-body py-2 d-flex align-items-center justify-content-between">
            <a href="?y=<?= $prevY ?>&m=<?= $prevM ?>" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-chevron-left"></i>
            </a>
            <div class="d-flex align-items-center gap-3">
                <span class="fw-bold fs-5"><?= $monthLabel ?></span>
                <?php if ($year !== (int)date('Y') || $month !== (int)date('n')): ?>
                <a href="?y=<?= date('Y') ?>&m=<?= date('n') ?>" class="btn btn-xs btn-outline-pink">วันนี้</a>
                <?php endif; ?>
            </div>
            <a href="?y=<?= $nextY ?>&m=<?= $nextM ?>" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-chevron-right"></i>
            </a>
        </div>
    </div>

    <!-- Platform legend -->
    <div class="d-flex flex-wrap gap-2 mb-3">
        <?php foreach ($platforms as $pf): ?>
        <span class="cal-legend" style="background:<?= htmlspecialchars($pf['color']) ?>20;border-color:<?= htmlspecialchars($pf['color']) ?>">
            <span style="width:8px;height:8px;border-radius:50%;background:<?= htmlspecialchars($pf['color']) ?>;display:inline-block;"></span>
            <?= $pf['icon'] ?> <?= htmlspecialchars($pf['name']) ?>
        </span>
        <?php endforeach; ?>
        <?php foreach ($ctypes as $k => [$icon,$label,$color]): ?>
        <span class="cal-legend" style="background:<?= $color ?>20;border-color:<?= $color ?>">
            <?= $icon ?> <?= $label ?>
        </span>
        <?php endforeach; ?>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         CALENDAR VIEW
    ════════════════════════════════════════════════════════════════════════ -->
    <div id="viewCalendar">
        <div class="cal-grid card">
            <!-- Day headers -->
            <div class="cal-header-row">
                <?php foreach (['อา','จ','อ','พ','พฤ','ศ','ส'] as $i => $d): ?>
                <div class="cal-header-cell <?= $i===0?'text-danger':($i===6?'text-primary':'') ?>"><?= $d ?></div>
                <?php endforeach; ?>
            </div>

            <!-- Calendar cells -->
            <div class="cal-body">
                <?php
                // Leading empty cells
                for ($i = 0; $i < $firstDow; $i++): ?>
                <div class="cal-cell cal-empty"></div>
                <?php endfor;

                // Day cells
                for ($day = 1; $day <= $daysInMonth; $day++):
                    $dateStr  = sprintf('%04d-%02d-%02d', $year, $month, $day);
                    $isToday  = ($dateStr === $today);
                    $isSunday = ((($firstDow + $day - 1) % 7) === 0);
                    $isSat    = ((($firstDow + $day - 1) % 7) === 6);
                    $dayPosts   = $postsByDay[$day] ?? [];
                    $dayBizEvts = $bizByDay[$day]   ?? [];
                ?>
                <div class="cal-cell <?= $isToday?'cal-today':'' ?> <?= $isSunday?'cal-sun':($isSat?'cal-sat':'') ?>"
                     onclick="openAdd('<?= $dateStr ?>')" title="เพิ่มโพสต์ <?= $dateStr ?>">
                    <div class="cal-day-num <?= $isToday?'today-badge':'' ?>"><?= $day ?></div>
                    <div class="cal-events">

                        <?php foreach ($dayBizEvts as $ev):
                            $evColor  = $ev['color'] ?: '#E67E22';
                            $evStart  = date('H:i', strtotime($ev['start_at']));
                            $evEnd    = $ev['end_at'] ? '–'.date('H:i', strtotime($ev['end_at'])) : '';
                            $evAmount = $ev['amount'] ? ' ฿'.number_format($ev['amount'],0) : '';
                        ?>
                        <div class="cal-event cal-biz-event"
                             style="background:<?= $evColor ?>22;border-left:3px solid <?= $evColor ?>;"
                             onclick="event.stopPropagation();showBizEvent(<?= htmlspecialchars(json_encode($ev),ENT_QUOTES) ?>)"
                             title="<?= htmlspecialchars($ev['title']) ?><?= $evAmount ?>">
                            <span class="cal-event-title" style="color:<?= $evColor ?>;font-weight:600;">
                                <?= htmlspecialchars(mb_strimwidth($ev['title'],0,16,'…')) ?>
                            </span>
                            <span class="cal-event-time"><?= $evStart ?><?= $evEnd ?></span>
                        </div>
                        <?php endforeach; ?>

                        <?php foreach ($dayPosts as $post):
                            $pids    = array_filter(array_map('intval', explode(',', $post['platform_ids'] ?? '')));
                            $pfColor = !empty($pids) && isset($platformMap[reset($pids)])
                                       ? $platformMap[reset($pids)]['color'] : '#8A6DB0';
                            $ptIcon  = $postTypes[$post['post_type']][0]    ?? $ctypes[$post['content_type']][0] ?? '📝';
                            $pubSt   = $post['publish_status'] ?? $post['status'];
                            [$pubEmoji,$pubColor] = [$pubStatusCfg[$pubSt][0]??'⬜', $pubStatusCfg[$pubSt][1]??'#aaa'];
                        ?>
                        <div class="cal-event"
                             style="background:<?= $pfColor ?>22;border-left:3px solid <?= $pfColor ?>;"
                             onclick="event.stopPropagation();openEdit(<?= $post['id'] ?>)"
                             title="<?= htmlspecialchars($post['title']) ?> — <?= $pubStatusCfg[$pubSt][2]??'' ?>">
                            <span class="cal-event-icon"><?= $ptIcon ?></span>
                            <span class="cal-event-title"><?= htmlspecialchars(mb_strimwidth($post['title'],0,18,'…')) ?></span>
                            <span class="cal-event-time"><?= $post['scheduled_at'] ? date('H:i', strtotime($post['scheduled_at'])) : '' ?></span>
                            <span style="font-size:0.62rem;margin-left:1px;" title="<?= $pubStatusCfg[$pubSt][2]??'' ?>"><?= $pubEmoji ?></span>
                        </div>
                        <?php endforeach; ?>

                        <?php if (count($dayPosts) === 0 && count($dayBizEvts) === 0): ?>
                        <div class="cal-add-hint">+ เพิ่ม</div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endfor; ?>

                <!-- Trailing empty cells to fill last row -->
                <?php
                $totalCells = $firstDow + $daysInMonth;
                $trailing   = (7 - ($totalCells % 7)) % 7;
                for ($i = 0; $i < $trailing; $i++): ?>
                <div class="cal-cell cal-empty"></div>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         LIST VIEW
    ════════════════════════════════════════════════════════════════════════ -->
    <div id="viewList" style="display:none">
        <div class="card">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>วัน/เวลา</th>
                            <th>หัวข้อ</th>
                            <th>ประเภท</th>
                            <th>แพลตฟอร์ม / สถานะ</th>
                            <th>สินค้า</th>
                            <th>สถานะโพสต์</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($posts)): ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted">
                            ยังไม่มีโพสต์เดือนนี้ — <a href="#" onclick="openAdd();return false">เพิ่มโพสต์แรก</a>
                        </td></tr>
                        <?php endif; ?>
                        <?php foreach ($posts as $post):
                            $pids    = array_filter(array_map('intval', explode(',', $post['platform_ids'] ?? '')));
                            [$stClass,$stLabel] = $statusCfg[$post['status']] ?? ['badge-secondary','ไม่ระบุ'];
                            [$cIcon,$cLabel,$cColor] = $ctypes[$post['content_type']] ?? ['📝','โพสต์','#aaa'];
                            $ptIcon  = $postTypes[$post['post_type']][0] ?? $cIcon;
                            $ptLabel = $postTypes[$post['post_type']][1] ?? $cLabel;
                            $ptColor = $postTypes[$post['post_type']][2] ?? $cColor;
                            $pubSt   = $post['publish_status'] ?? $post['status'];
                            [$pubEmoji,$pubColor,$pubLabel] = $pubStatusCfg[$pubSt] ?? ['⬜','#aaa','—'];
                            $pfStatuses = $postPlatformsByPostId[$post['id']] ?? [];
                        ?>
                        <tr>
                            <td style="white-space:nowrap;font-size:0.82rem;">
                                <?php if ($post['scheduled_at']): ?>
                                <div class="fw-semibold"><?= formatDateTH(substr($post['scheduled_at'],0,10)) ?></div>
                                <div class="text-muted"><?= date('H:i', strtotime($post['scheduled_at'])) ?></div>
                                <?php else: ?>
                                <span class="text-muted">ไม่ได้กำหนด</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($post['title']) ?></div>
                                <?php if ($post['caption']): ?>
                                <div class="text-muted" style="font-size:0.75rem;"><?= htmlspecialchars(mb_strimwidth($post['caption'],0,60,'…')) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge" style="background:<?= $ptColor ?>30;color:<?= $ptColor ?>;border:1px solid <?= $ptColor ?>50;font-size:0.72rem;">
                                    <?= $ptIcon ?> <?= $ptLabel ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-1 align-items-center">
                                    <?php foreach ($pids as $pid):
                                        if (!isset($platformMap[$pid])) continue;
                                        $pf   = $platformMap[$pid];
                                        $ppSt = $pfStatuses[$pid]['status'] ?? null;
                                        $ppEmoji = match($ppSt) {
                                            'published'  => '✅',
                                            'failed'     => '❌',
                                            'publishing' => '🔄',
                                            'skipped'    => '⏭️',
                                            'pending'    => '⏳',
                                            default      => ''
                                        };
                                        $ppTitle = $ppSt ? "สถานะ: $ppSt" : '';
                                    ?>
                                    <span class="badge d-inline-flex align-items-center gap-1"
                                          style="background:<?= $pf['color'] ?>;font-size:0.7rem;"
                                          title="<?= htmlspecialchars($pf['name']) ?> — <?= $ppTitle ?>">
                                        <?= $pf['icon'] ?>
                                        <?php if ($ppEmoji): ?><span style="font-size:0.65rem;"><?= $ppEmoji ?></span><?php endif; ?>
                                    </span>
                                    <?php endforeach; ?>
                                    <?php if (!empty($post['publish_errors'])): ?>
                                    <span title="<?= htmlspecialchars($post['publish_errors']) ?>" style="cursor:help;font-size:0.72rem;">⚠️</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td style="font-size:0.82rem;"><?= htmlspecialchars($post['product_name'] ?? '—') ?></td>
                            <td>
                                <span class="pub-status-badge" style="color:<?= $pubColor ?>;font-size:0.82rem;">
                                    <?= $pubEmoji ?> <?= $pubLabel ?>
                                </span>
                                <?php if (in_array($post['publish_status'], ['draft','scheduled','failed'])): ?>
                                <br><button class="btn btn-xs btn-outline-success mt-1"
                                        onclick="publishNow(<?= $post['id'] ?>,this)"
                                        title="โพสต์ตอนนี้เลย">
                                    <i class="fas fa-bolt"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="openEdit(<?= $post['id'] ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div><!-- /container -->

<!-- ═════════════════════════════════════════════════════════════════════════
     ADD TYPE PICKER MODAL
══════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="addPickerModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:360px;">
        <div class="modal-content" style="border-radius:16px;overflow:hidden;">
            <div class="modal-header border-0 pb-1" style="background:#f8f9fa;">
                <div>
                    <div class="fw-bold" id="addPickerDateLabel" style="font-size:.95rem;"></div>
                    <div class="text-muted" style="font-size:.78rem;">เลือกประเภทที่ต้องการเพิ่ม</div>
                </div>
                <button type="button" class="btn-close" onclick="closePickerModal()"></button>
            </div>
            <div class="modal-body p-3 d-flex flex-column gap-2">
                <button class="btn btn-lg w-100 text-start d-flex align-items-center gap-3"
                        style="background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border:none;border-radius:12px;padding:14px 18px;"
                        onclick="closePickerModal(); openAddPost()">
                    <span style="font-size:1.5rem;">📝</span>
                    <div>
                        <div class="fw-bold" style="font-size:.92rem;">เพิ่มโพสต์ Content</div>
                        <div style="font-size:.75rem;opacity:.85;">วางแผนโพสต์ FB / IG / TikTok</div>
                    </div>
                </button>
                <button class="btn btn-lg w-100 text-start d-flex align-items-center gap-3"
                        style="background:linear-gradient(135deg,#f093fb,#f5a623);color:#fff;border:none;border-radius:12px;padding:14px 18px;"
                        onclick="closePickerModal(); openBizModal(null, _pickerDate)">
                    <span style="font-size:1.5rem;">📋</span>
                    <div>
                        <div class="fw-bold" style="font-size:.92rem;">เพิ่ม Event งาน</div>
                        <div style="font-size:.75rem;opacity:.85;">ซื้อสินค้า · นับสต็อก · ประชุม ฯลฯ</div>
                    </div>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ═════════════════════════════════════════════════════════════════════════
     BUSINESS EVENT MODAL
══════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="bizModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#FFF3E0,#FFF8E1);">
                <h5 class="modal-title" id="bizModalTitle">📅 เพิ่ม Event งาน</h5>
                <button type="button" class="btn-close" onclick="closeBizModal()"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="bId" value="0">
                <div class="row g-3">

                    <!-- ประเภท -->
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">ประเภท Event</label>
                        <select id="bType" class="form-select" onchange="bizTypeChanged()">
                            <option value="purchase">🛒 ซื้อสินค้าเข้าสต็อก</option>
                            <option value="stock_count">📋 นับสต็อก</option>
                            <option value="delivery">🚚 รับของ / จัดส่ง</option>
                            <option value="meeting">💬 ประชุม / นัดหมาย</option>
                            <option value="task">✅ งานทั่วไป</option>
                            <option value="promotion">🎉 โปรโมชั่น</option>
                            <option value="other">📌 อื่นๆ</option>
                        </select>
                    </div>

                    <!-- ชื่อ -->
                    <div class="col-md-7">
                        <label class="form-label fw-semibold">ชื่อ Event <span class="text-danger">*</span></label>
                        <input type="text" id="bTitle" class="form-control" placeholder="เช่น ซื้อสินค้าตลาดโชคชัย 4">
                    </div>

                    <!-- วันเวลาเริ่ม -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">วันที่ & เวลาเริ่ม <span class="text-danger">*</span></label>
                        <input type="datetime-local" id="bStart" class="form-control">
                    </div>

                    <!-- วันเวลาสิ้นสุด -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">วันที่ & เวลาสิ้นสุด</label>
                        <input type="datetime-local" id="bEnd" class="form-control">
                        <div class="form-text">เว้นว่างถ้าไม่ระบุเวลาสิ้นสุด</div>
                    </div>

                    <!-- มูลค่า (purchase/delivery) -->
                    <div class="col-md-6" id="bAmountRow">
                        <label class="form-label fw-semibold">มูลค่า / ต้นทุน (บาท)</label>
                        <div class="input-group">
                            <span class="input-group-text">฿</span>
                            <input type="number" id="bAmount" class="form-control" placeholder="0.00" step="0.01" min="0">
                        </div>
                    </div>

                    <!-- สี -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">สีแสดงผล</label>
                        <div class="d-flex gap-2 align-items-center">
                            <input type="color" id="bColor" class="form-control form-control-color" value="#E67E22" style="width:48px;height:36px;">
                            <div class="d-flex gap-1 flex-wrap" id="bColorPresets">
                                <?php foreach ([
                                    '#E67E22'=>'ส้ม','#E74C3C'=>'แดง','#27AE60'=>'เขียว',
                                    '#2980B9'=>'น้ำเงิน','#8E44AD'=>'ม่วง','#2C3E50'=>'เทา'
                                ] as $c=>$lbl): ?>
                                <div onclick="document.getElementById('bColor').value='<?= $c ?>'"
                                     style="width:22px;height:22px;border-radius:50%;background:<?= $c ?>;cursor:pointer;border:2px solid #fff;box-shadow:0 0 0 1px #ccc;"
                                     title="<?= $lbl ?>"></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- หมายเหตุ -->
                    <div class="col-12">
                        <label class="form-label fw-semibold">หมายเหตุ / รายละเอียด</label>
                        <textarea id="bNotes" class="form-control" rows="3"
                                  placeholder="เช่น รายการสินค้าที่ซื้อ, ที่อยู่, ราคา..."></textarea>
                    </div>

                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-outline-danger" id="bDeleteBtn" onclick="deleteBizEvent()" style="display:none;">
                    <i class="fas fa-trash me-1"></i> ลบ Event
                </button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary" onclick="closeBizModal()">ยกเลิก</button>
                    <button type="button" class="btn btn-warning" onclick="saveBizEvent()">
                        <i class="fas fa-save me-1"></i> บันทึก
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═════════════════════════════════════════════════════════════════════════
     ADD / EDIT MODAL
══════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="postModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form method="POST" id="postForm">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id"     id="fId"     value="0">
            <div class="modal-content">

                <div class="modal-header" style="background:linear-gradient(135deg,#FBE9EE,#F0EAF6);">
                    <h5 class="modal-title" id="modalTitle">📝 เพิ่มโพสต์ใหม่</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">

                        <!-- Title -->
                        <div class="col-12">
                            <label class="form-label fw-semibold">หัวข้อโพสต์ <span class="text-danger">*</span></label>
                            <input type="text" name="title" id="fTitle" class="form-control"
                                   placeholder="เช่น รีวิวบอดี้สูทคอลเลคชันใหม่" required>
                        </div>

                        <!-- Post Type chips -->
                        <div class="col-12">
                            <label class="form-label fw-semibold">ประเภทโพสต์</label>
                            <input type="hidden" name="post_type" id="fPostType" value="image">
                            <div class="d-flex flex-wrap gap-2" id="postTypeChips">
                                <?php foreach ($postTypes as $k => [$icon,$lbl,$col]):
                                ?>
                                <button type="button" class="post-type-chip" data-type="<?= $k ?>"
                                        style="--pt-color:<?= $col ?>;"
                                        onclick="selectPostType('<?= $k ?>', this)">
                                    <?= $icon ?> <?= $lbl ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Status + DateTime -->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">สถานะโพสต์
                                <span data-bs-toggle="tooltip" title="สถานะบอกให้ระบบรู้ว่าจะทำอะไรกับโพสต์นี้" style="cursor:help;">ℹ️</span>
                            </label>
                            <select name="status" id="fStatus" class="form-select" onchange="updateStatusHint(this.value)">
                                <option value="draft">📝 ร่าง — ยังไม่พร้อมโพสต์</option>
                                <option value="scheduled">📅 กำหนดแล้ว — ให้ n8n โพสต์อัตโนมัติ</option>
                                <option value="published">✅ เผยแพร่แล้ว — โพสต์ขึ้นแล้ว</option>
                                <option value="cancelled">🚫 ยกเลิก — ไม่โพสต์</option>
                            </select>
                            <div id="statusHint" class="form-text mt-1" style="font-size:.75rem;line-height:1.5;"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">วันเวลาโพสต์</label>
                            <input type="datetime-local" name="scheduled_at" id="fScheduled" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">ประเภทคอนเทนต์</label>
                            <select name="content_type" id="fType" class="form-select">
                                <?php foreach ($ctypes as $k => [$icon,$label,$color]): ?>
                                <option value="<?= $k ?>"><?= $icon ?> <?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Media Picker -->
                        <div class="col-12">
                            <label class="form-label fw-semibold">🖼️ สื่อที่แนบ (รูป/วิดีโอ)</label>
                            <input type="hidden" name="media_ids" id="fMediaIds" value="">
                            <div id="mediaPickerSelected" class="d-flex flex-wrap gap-2 mb-2"></div>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="openMediaPicker()">
                                <i class="fas fa-photo-film me-1"></i> เลือกจากคลังสื่อ
                            </button>
                            <span class="form-text ms-2">เลือกได้หลายไฟล์</span>
                        </div>

                        <!-- Platforms -->
                        <div class="col-12">
                            <label class="form-label fw-semibold">แพลตฟอร์มที่จะโพสต์</label>
                            <div class="d-flex flex-wrap gap-2" id="platformChecks">
                                <?php foreach ($platforms as $pf): ?>
                                <label class="platform-check-label" style="--pf-color:<?= htmlspecialchars($pf['color']) ?>">
                                    <input type="checkbox" name="platform_ids[]" value="<?= $pf['id'] ?>" class="pf-check"
                                           data-pfid="<?= $pf['id'] ?>" data-pfname="<?= htmlspecialchars($pf['name']) ?>"
                                           data-pficon="<?= $pf['icon'] ?>"
                                           onchange="updateCaptionTabs()">
                                    <span><?= $pf['icon'] ?> <?= htmlspecialchars($pf['name']) ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Caption Tabs (shared + per-platform) -->
                        <div class="col-12">
                            <label class="form-label fw-semibold">Caption / ข้อความโพสต์</label>
                            <div class="caption-tabs-wrap">
                                <ul class="nav nav-tabs nav-tabs-sm mb-2" id="captionTabs">
                                    <li class="nav-item">
                                        <a class="nav-link active" data-tab="shared" href="#" onclick="switchCaptionTab('shared',this);return false;">
                                            🌐 ใช้ร่วมกัน
                                        </a>
                                    </li>
                                    <!-- Platform tabs added by JS -->
                                </ul>
                                <div id="captionTabContent">
                                    <textarea name="caption" id="fCaption" class="form-control" rows="4"
                                              placeholder="เขียน caption ที่จะใช้กับทุกแพลตฟอร์ม..."
                                              oninput="document.getElementById('captionCount').textContent=this.value.length+' ตัวอักษร'"></textarea>
                                    <div id="pfCaptionPanels"></div>
                                </div>
                                <div class="d-flex justify-content-between mt-1">
                                    <div class="form-text">กด tab แพลตฟอร์มเพื่อเขียน caption แยก หรือใช้ "ร่วมกัน" สำหรับทุกที่</div>
                                    <span class="form-text" id="captionCount">0 ตัวอักษร</span>
                                </div>
                            </div>
                        </div>

                        <!-- Hashtags -->
                        <div class="col-12">
                            <label class="form-label fw-semibold">Hashtags</label>
                            <input type="text" name="hashtags" id="fHashtags" class="form-control"
                                   placeholder="#babykawaii #เสื้อผ้าเด็ก #ของขวัญเด็กแรกเกิด">
                            <div class="form-text">คั่นด้วยช่องว่าง ใส่ # นำหน้า</div>
                        </div>

                        <!-- Product + Promotion -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">🛍️ สินค้าที่โปรโมต</label>
                            <select name="product_id" id="fProduct" class="form-select">
                                <option value="">-- ไม่ระบุ --</option>
                                <?php foreach ($products as $prod): ?>
                                <option value="<?= $prod['id'] ?>"><?= htmlspecialchars($prod['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">🏷️ โปรโมชั่น</label>
                            <select name="promotion_id" id="fPromo" class="form-select">
                                <option value="">-- ไม่ระบุ --</option>
                                <?php foreach ($promotions as $promo): ?>
                                <option value="<?= $promo['id'] ?>"><?= htmlspecialchars($promo['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Notes -->
                        <div class="col-12">
                            <label class="form-label fw-semibold">หมายเหตุ (ภายใน)</label>
                            <input type="text" name="notes" id="fNotes" class="form-control"
                                   placeholder="หมายเหตุสำหรับทีม ไม่แสดงสาธารณะ">
                        </div>

                    </div>
                </div>

                <div class="modal-footer flex-wrap gap-2">
                    <button type="button" class="btn btn-outline-danger me-auto d-none" id="btnDelete"
                            onclick="deletePost()">
                        <i class="fas fa-trash me-1"></i> ลบโพสต์
                    </button>
                    <!-- Publish status indicator (edit mode only) -->
                    <span id="modalPubStatus" class="d-none" style="font-size:0.82rem;"></span>
                    <!-- Publish Now (edit mode only, shown when not already published) -->
                    <button type="button" class="btn btn-success d-none" id="btnPublishNow"
                            onclick="publishNow()">
                        <i class="fas fa-bolt me-1"></i> โพสต์ทันที
                    </button>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> บันทึก
                    </button>
                </div>

            </div><!-- /modal-content -->
        </form>
    </div>
</div>

<!-- Delete confirm form (hidden) -->
<form method="POST" id="deleteForm" style="display:none">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id"     id="deleteId">
</form>

<!-- Quick status form (hidden) -->
<form method="POST" id="statusForm" style="display:none">
    <input type="hidden" name="action"     value="status">
    <input type="hidden" name="id"         id="sId">
    <input type="hidden" name="new_status" id="sStatus">
</form>

<!-- ── CSS ────────────────────────────────────────────────────────────────── -->
<style>
/* ── Calendar grid ── */
.cal-grid { overflow: hidden; }
.cal-header-row {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    background: linear-gradient(135deg, #FBE9EE, #F0EAF6);
}
.cal-header-cell {
    padding: 10px 4px;
    text-align: center;
    font-weight: 700;
    font-size: 0.82rem;
}
.cal-body {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
}
.cal-cell {
    min-height: 120px;
    border-right: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
    padding: 6px 4px 4px;
    cursor: pointer;
    transition: background 0.15s;
    position: relative;
}
.cal-cell:hover { background: #FAF5FF; }
.cal-cell:nth-child(7n) { border-right: none; }
.cal-empty { background: #fafafa; cursor: default; }
.cal-empty:hover { background: #fafafa; }
.cal-today { background: #FFF0FA !important; }
.cal-sun .cal-day-num { color: #E45252; }
.cal-sat .cal-day-num { color: #4FC3F7; }

.cal-day-num {
    font-size: 0.82rem;
    font-weight: 600;
    color: #555;
    margin-bottom: 4px;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}
.today-badge {
    background: var(--pink-dark);
    color: #fff !important;
}

/* ── Calendar events ── */
.cal-events { display: flex; flex-direction: column; gap: 2px; }
.cal-event {
    border-radius: 5px;
    padding: 2px 5px;
    font-size: 0.7rem;
    display: flex;
    align-items: center;
    gap: 3px;
    cursor: pointer;
    transition: filter 0.12s;
    overflow: hidden;
}
.cal-event:hover { filter: brightness(0.92); }
.cal-event-icon  { flex-shrink: 0; font-size: 0.7rem; }
.cal-event-title { flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 500; }
.cal-event-time  { flex-shrink: 0; color: #888; font-size: 0.65rem; }

.cal-add-hint {
    font-size: 0.68rem;
    color: #ccc;
    text-align: center;
    padding: 2px 0;
    opacity: 0;
    transition: opacity 0.15s;
}
.cal-cell:hover .cal-add-hint { opacity: 1; }

/* ── Legend ── */
.cal-legend {
    font-size: 0.73rem;
    padding: 3px 8px;
    border-radius: 20px;
    border: 1px solid;
    display: flex;
    align-items: center;
    gap: 4px;
}

/* ── Platform checkbox pills ── */
.platform-check-label {
    cursor: pointer;
    padding: 5px 12px;
    border-radius: 20px;
    border: 2px solid var(--pf-color, #ccc);
    font-size: 0.82rem;
    transition: all 0.15s;
    user-select: none;
    background: transparent;
    color: var(--text);
}
.platform-check-label:has(.pf-check:checked) {
    background: var(--pf-color, #ccc);
    color: #fff;
}
.pf-check { display: none; }

/* ── Btn xs ── */
.btn-xs { padding: 0.15rem 0.5rem; font-size: 0.72rem; }

/* ── Post type chips ── */
.post-type-chip {
    padding: 5px 12px; border-radius: 20px; font-size: 0.8rem;
    border: 2px solid var(--pt-color, #ccc);
    background: transparent; cursor: pointer; transition: all .15s;
}
.post-type-chip.active {
    background: var(--pt-color, #ccc);
    color: #fff; border-color: var(--pt-color, #ccc);
}

/* ── Media picker thumbnail ── */
.media-thumb-pick {
    width: 70px; height: 70px; object-fit: cover; border-radius: 6px;
    border: 2px solid #eee; cursor: pointer; position: relative;
}
.media-thumb-wrap { position: relative; display: inline-block; }
.media-thumb-wrap .remove-media {
    position: absolute; top: -6px; right: -6px;
    width: 18px; height: 18px; border-radius: 50%;
    background: #e74c3c; color: #fff; border: none;
    font-size: 0.6rem; cursor: pointer; display: flex;
    align-items: center; justify-content: center; line-height: 1;
}

/* ── Caption tabs ── */
.nav-tabs-sm .nav-link {
    font-size: 0.78rem; padding: 5px 10px;
}
.pf-caption-panel { display: none; }
.pf-caption-panel.active { display: block; }

/* ── Publish status ── */
.pub-status-badge { font-weight: 600; white-space: nowrap; }
</style>

<!-- ── JavaScript ─────────────────────────────────────────────────────────── -->
<script>
const ALL_POSTS     = <?= $postsJS ?>;
const ALL_PLATFORMS = <?= $platformsJS ?>;
const YEAR = <?= $year ?>, MONTH = <?= $month ?>;

// ── View toggle ───────────────────────────────────────────────────────────────
function setView(v) {
    document.getElementById('viewCalendar').style.display = v === 'calendar' ? '' : 'none';
    document.getElementById('viewList').style.display     = v === 'list'     ? '' : 'none';
    document.getElementById('btnCal').classList.toggle('btn-primary',       v === 'calendar');
    document.getElementById('btnCal').classList.toggle('btn-outline-secondary', v !== 'calendar');
    document.getElementById('btnList').classList.toggle('btn-primary',      v === 'list');
    document.getElementById('btnList').classList.toggle('btn-outline-secondary', v !== 'list');
    localStorage.setItem('calView', v);
}
// Restore saved view
const savedView = localStorage.getItem('calView') || 'calendar';
setView(savedView);

// ── Modal helpers ─────────────────────────────────────────────────────────────
// Bootstrap JS loads in footer.php (after this script) — lazy-init to avoid ReferenceError
function getModal() {
    return bootstrap.Modal.getOrCreateInstance(document.getElementById('postModal'));
}

function resetForm() {
    document.getElementById('postForm').reset();
    document.getElementById('fId').value = '0';
    document.getElementById('btnDelete').classList.add('d-none');
    document.getElementById('btnPublishNow').classList.add('d-none');
    document.getElementById('modalPubStatus').classList.add('d-none');
    document.getElementById('modalTitle').textContent = '📝 เพิ่มโพสต์ใหม่';
    // Uncheck all platforms
    document.querySelectorAll('.pf-check').forEach(c => c.checked = false);
}

// ── Add Type Picker ───────────────────────────────────────────────────────────
let _pickerDate = '';

function showAddPicker(dateStr) {
    _pickerDate = dateStr;
    // Format Thai date label
    const [y,m,d] = dateStr.split('-');
    const thMonths = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    document.getElementById('addPickerDateLabel').textContent =
        `📅 ${parseInt(d)} ${thMonths[parseInt(m)]} ${parseInt(y)+543}`;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('addPickerModal')).show();
}

function closePickerModal() {
    try { bootstrap.Modal.getInstance(document.getElementById('addPickerModal'))?.hide(); } catch(e) {}
    setTimeout(() => {
        const el = document.getElementById('addPickerModal');
        el.classList.remove('show'); el.style.display = 'none';
        el.setAttribute('aria-hidden','true'); el.removeAttribute('aria-modal');
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
        document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
    }, 320);
}

// ── Status hint descriptions ──────────────────────────────────────────────────
const STATUS_HINTS = {
    draft:     '📝 <strong>ร่าง</strong> — บันทึกไว้ก่อน ยังไม่พร้อมโพสต์ n8n จะ<u>ไม่</u>ทำงาน ใช้ตอนยังคิด caption ไม่เสร็จหรือรอรูป',
    scheduled: '📅 <strong>กำหนดแล้ว</strong> — ระบบจะส่ง trigger ให้ n8n ทันที n8n จะโพสต์ขึ้นแพลตฟอร์มตามเวลาที่ตั้งไว้ <span style="color:#E67E22;">⚠️ ต้องกรอกวันเวลาด้วย</span>',
    published: '✅ <strong>เผยแพร่แล้ว</strong> — โพสต์ขึ้นแพลตฟอร์มไปแล้ว เปลี่ยนมาสถานะนี้เมื่อโพสต์เองมือหรือ n8n โพสต์สำเร็จ',
    cancelled: '🚫 <strong>ยกเลิก</strong> — ตัดสินใจไม่โพสต์แล้ว เก็บไว้เป็นบันทึก n8n จะ<u>ไม่</u>ทำงาน',
};
function updateStatusHint(val) {
    const el = document.getElementById('statusHint');
    if (el) el.innerHTML = STATUS_HINTS[val] || '';
}

function openAddPost() {
    resetForm();
    document.getElementById('fScheduled').value = _pickerDate + 'T09:00';
    getModal().show();
}

// ── Business Event Modal ──────────────────────────────────────────────────────
const BIZ_COLOR_DEFAULTS = {
    purchase:'#E67E22', stock_count:'#27AE60', delivery:'#2980B9',
    meeting:'#8E44AD', task:'#2C3E50', promotion:'#E74C3C', other:'#95A5A6'
};

function closeBizModal() {
    try { bootstrap.Modal.getInstance(document.getElementById('bizModal'))?.hide(); } catch(e) {}
    setTimeout(() => {
        const el = document.getElementById('bizModal');
        el.classList.remove('show'); el.style.display = 'none';
        el.setAttribute('aria-hidden','true'); el.removeAttribute('aria-modal');
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
        document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
    }, 320);
}

function openBizModal(ev, defaultDate) {
    // Reset
    document.getElementById('bId').value      = ev?.id        || '0';
    document.getElementById('bType').value    = ev?.event_type || 'purchase';
    document.getElementById('bTitle').value   = ev?.title      || '';
    document.getElementById('bAmount').value  = ev?.amount     || '';
    document.getElementById('bNotes').value   = ev?.notes      || '';
    document.getElementById('bColor').value   = ev?.color      || '#E67E22';
    document.getElementById('bizModalTitle').textContent = ev ? '✏️ แก้ไข Event งาน' : '📅 เพิ่ม Event งาน';
    document.getElementById('bDeleteBtn').style.display  = ev ? '' : 'none';

    // Dates — convert "YYYY-MM-DD HH:MM:SS" → "YYYY-MM-DDTHH:MM"
    const toLocal = s => s ? s.substring(0,10)+'T'+s.substring(11,16) : '';
    if (ev) {
        document.getElementById('bStart').value = toLocal(ev.start_at);
        document.getElementById('bEnd').value   = toLocal(ev.end_at);
    } else {
        const d = defaultDate || new Date().toISOString().substring(0,10);
        document.getElementById('bStart').value = d + 'T09:00';
        document.getElementById('bEnd').value   = '';
    }

    bizTypeChanged();
    bootstrap.Modal.getOrCreateInstance(document.getElementById('bizModal')).show();
}

function bizTypeChanged() {
    const type = document.getElementById('bType').value;
    // Show amount only for purchase/delivery
    document.getElementById('bAmountRow').style.display =
        ['purchase','delivery'].includes(type) ? '' : 'none';
    // Auto-set color if field is at default
    if (!document.getElementById('bId').value || document.getElementById('bId').value === '0') {
        document.getElementById('bColor').value = BIZ_COLOR_DEFAULTS[type] || '#95A5A6';
    }
}

function saveBizEvent() {
    const title   = document.getElementById('bTitle').value.trim();
    const startAt = document.getElementById('bStart').value;
    if (!title)   { alert('กรุณากรอกชื่อ Event'); return; }
    if (!startAt) { alert('กรุณาเลือกวันที่และเวลา'); return; }

    const body = new URLSearchParams({
        action:     'save_biz',
        id:         document.getElementById('bId').value,
        event_type: document.getElementById('bType').value,
        title,
        start_at:   startAt.replace('T',' ') + ':00',
        end_at:     (document.getElementById('bEnd').value || '').replace('T',' ') + (document.getElementById('bEnd').value ? ':00' : ''),
        amount:     document.getElementById('bAmount').value || '',
        notes:      document.getElementById('bNotes').value,
        color:      document.getElementById('bColor').value,
    });

    fetch('', { method:'POST', body })
        .then(r => r.json())
        .then(res => {
            if (res.ok) { closeBizModal(); location.reload(); }
            else        { alert(res.msg || 'เกิดข้อผิดพลาด'); }
        });
}

function deleteBizEvent() {
    if (!confirm('ลบ Event นี้?')) return;
    fetch('', { method:'POST', body: new URLSearchParams({ action:'delete_biz', id: document.getElementById('bId').value }) })
        .then(r => r.json())
        .then(res => { if (res.ok) { closeBizModal(); location.reload(); } });
}

// Called when clicking existing biz event on calendar — opens edit modal
function showBizEvent(ev) {
    openBizModal(ev);
}

function openAdd(dateStr) {
    if (dateStr) {
        showAddPicker(dateStr);
        return;
    }
    resetForm();
    if (dateStr) {
        document.getElementById('fScheduled').value = dateStr + 'T09:00';
    } else {
        const now = new Date();
        const pad = n => String(n).padStart(2,'0');
        document.getElementById('fScheduled').value =
            `${now.getFullYear()}-${pad(now.getMonth()+1)}-${pad(now.getDate())}T09:00`;
    }
    getModal().show();
}

function openEdit(id) {
    const post = ALL_POSTS.find(p => p.id == id);
    if (!post) return;

    resetForm();
    document.getElementById('modalTitle').textContent = '✏️ แก้ไขโพสต์';
    document.getElementById('fId').value        = post.id;
    document.getElementById('fTitle').value     = post.title;
    document.getElementById('fType').value      = post.content_type;
    document.getElementById('fStatus').value    = post.status;
    updateStatusHint(post.status);
    document.getElementById('fCaption').value   = post.caption || '';
    document.getElementById('fHashtags').value  = post.hashtags || '';
    document.getElementById('fProduct').value   = post.product_id || '';
    document.getElementById('fPromo').value     = post.promotion_id || '';
    document.getElementById('fNotes').value     = post.notes || '';
    document.getElementById('btnDelete').classList.remove('d-none');
    updateCaptionCount();

    // Post type
    if (post.post_type) selectPostType(post.post_type);

    if (post.scheduled_at) {
        document.getElementById('fScheduled').value = post.scheduled_at.replace(' ','T').substring(0,16);
    }

    // Check platforms
    const pids = (post.platform_ids || '').split(',').map(Number);
    document.querySelectorAll('.pf-check').forEach(c => {
        c.checked = pids.includes(Number(c.value));
    });

    // Per-platform captions
    let captionsData = {};
    try { captionsData = JSON.parse(post.captions_json || '{}'); } catch(e) {}
    updateCaptionTabs(captionsData);

    // Media IDs
    if (post.media_ids) {
        document.getElementById('fMediaIds').value = post.media_ids;
        renderSelectedMedia(post.media_ids.split(',').filter(Boolean));
    }

    // Publish status badge + Publish Now button
    const pubSt = post.publish_status || post.status;
    const pubMap = {
        'draft':      ['⬜','#aaa',    'ร่าง'],
        'scheduled':  ['📅','#4FC3F7', 'กำหนดแล้ว'],
        'publishing': ['🔄','#FF9800', 'กำลังโพสต์...'],
        'published':  ['✅','#3AA088', 'โพสต์แล้ว'],
        'failed':     ['❌','#E45252', 'ล้มเหลว'],
        'cancelled':  ['🚫','#aaa',    'ยกเลิก'],
    };
    const [emoji, color, label] = pubMap[pubSt] || ['⬜','#aaa','—'];
    const statusSpan = document.getElementById('modalPubStatus');
    statusSpan.innerHTML = `<span style="color:${color}">${emoji} ${label}</span>`;
    statusSpan.classList.remove('d-none');

    // Show "Publish Now" for non-published posts that have an ID
    const btnPN = document.getElementById('btnPublishNow');
    if (pubSt !== 'published' && pubSt !== 'publishing') {
        btnPN.classList.remove('d-none');
        btnPN.dataset.postId = post.id;
    } else {
        btnPN.classList.add('d-none');
    }

    getModal().show();
}

function deletePost() {
    if (!confirm('ลบโพสต์นี้จริงหรือ?')) return;
    document.getElementById('deleteId').value = document.getElementById('fId').value;
    document.getElementById('deleteForm').submit();
}

function quickStatus(id, newStatus) {
    document.getElementById('sId').value     = id;
    document.getElementById('sStatus').value = newStatus;
    document.getElementById('statusForm').submit();
}

// Caption character counter
function updateCaptionCount() {
    const len = document.getElementById('fCaption').value.length;
    document.getElementById('captionCount').textContent = len.toLocaleString('th-TH') + ' ตัวอักษร';
}
document.getElementById('fCaption').addEventListener('input', updateCaptionCount);

// Hashtag auto-formatter: add # if missing
document.getElementById('fHashtags').addEventListener('blur', function() {
    const tags = this.value.split(/\s+/).filter(Boolean).map(t => t.startsWith('#') ? t : '#' + t);
    this.value = tags.join(' ');
});

/* ── Post Type chips ──────────────────────────────────────────── */
function selectPostType(type, el) {
    document.getElementById('fPostType').value = type;
    document.querySelectorAll('.post-type-chip').forEach(c => c.classList.remove('active'));
    const target = el || document.querySelector(`.post-type-chip[data-type="${type}"]`);
    if (target) target.classList.add('active');
}
// Select default
selectPostType('image');

/* ── Per-Platform Caption Tabs ───────────────────────────────── */
let currentCaptionTab = 'shared';

function updateCaptionTabs(existingCaptions) {
    existingCaptions = existingCaptions || {};
    const checkedPfs = [...document.querySelectorAll('.pf-check:checked')];
    const tabList    = document.getElementById('captionTabs');
    const panelArea  = document.getElementById('pfCaptionPanels');

    // Remove old platform tabs
    tabList.querySelectorAll('.pf-tab').forEach(t => t.remove());
    panelArea.innerHTML = '';

    checkedPfs.forEach(cb => {
        const pfId   = cb.dataset.pfid;
        const pfName = cb.dataset.pfname;
        const pfIcon = cb.dataset.pficon;

        // Tab
        const li = document.createElement('li');
        li.className = 'nav-item pf-tab';
        li.innerHTML = `<a class="nav-link" data-tab="pf_${pfId}" href="#"
            onclick="switchCaptionTab('pf_${pfId}',this);return false;">
            ${pfIcon} ${pfName}
        </a>`;
        tabList.appendChild(li);

        // Panel
        const panel = document.createElement('div');
        panel.className = 'pf-caption-panel';
        panel.id = `cap_pf_${pfId}`;
        panel.innerHTML = `<textarea name="caption_pf[${pfId}]" class="form-control" rows="4"
            placeholder="Caption สำหรับ ${pfName} โดยเฉพาะ (เว้นว่างเพื่อใช้ caption รวม)">${existingCaptions[pfId] || ''}</textarea>`;
        panelArea.appendChild(panel);
    });

    // Stay on shared tab if none selected
    switchCaptionTab('shared', tabList.querySelector('[data-tab="shared"]'));
}

function switchCaptionTab(tab, el) {
    currentCaptionTab = tab;
    // Update active tab
    document.querySelectorAll('#captionTabs .nav-link').forEach(a => a.classList.remove('active'));
    if (el) el.classList.add('active');
    // Show/hide shared caption textarea
    const sharedArea = document.getElementById('fCaption');
    if (sharedArea) sharedArea.style.display = tab === 'shared' ? '' : 'none';
    // Show/hide per-platform panels
    document.querySelectorAll('.pf-caption-panel').forEach(p => {
        p.classList.toggle('active', p.id === `cap_${tab}`);
    });
}

/* ── Media Picker ─────────────────────────────────────────────── */
let selectedMediaIds = [];

function openMediaPicker() {
    const url = '<?= SITE_URL ?>/pages/media-picker.php?multi=1';
    const w = window.open(url, 'media_picker', 'width=900,height=600,scrollbars=yes');
    window._mediaPickerCallback = function(items) {
        items.forEach(item => {
            if (!selectedMediaIds.includes(String(item.id))) {
                selectedMediaIds.push(String(item.id));
            }
        });
        document.getElementById('fMediaIds').value = selectedMediaIds.join(',');
        renderSelectedMedia(selectedMediaIds, items);
    };
    // Also catch postMessage in case callback isn't available
    window.addEventListener('message', function onMsg(e) {
        if (e.data?.type === 'MEDIA_SELECTED') {
            window.removeEventListener('message', onMsg);
            window._mediaPickerCallback(e.data.items);
        }
    });
}

function renderSelectedMedia(ids, items) {
    const wrap = document.getElementById('mediaPickerSelected');
    wrap.innerHTML = '';
    ids.filter(Boolean).forEach(id => {
        const item = (items || []).find(i => String(i.id) === String(id));
        const thumbWrap = document.createElement('div');
        thumbWrap.className = 'media-thumb-wrap';
        thumbWrap.innerHTML = item
            ? `<img src="${item.url || item.thumbnail_url}" class="media-thumb-pick" title="${item.original_name || ''}">
               <button type="button" class="remove-media" onclick="removeMedia('${id}')">✕</button>`
            : `<div class="media-thumb-pick d-flex align-items-center justify-content-center bg-light" style="font-size:0.7rem;">ID:${id}
               <button type="button" class="remove-media" onclick="removeMedia('${id}')">✕</button></div>`;
        wrap.appendChild(thumbWrap);
    });
}

function removeMedia(id) {
    selectedMediaIds = selectedMediaIds.filter(i => i !== String(id));
    document.getElementById('fMediaIds').value = selectedMediaIds.join(',');
    renderSelectedMedia(selectedMediaIds);
}

/* ── Publish Now ─────────────────────────────────────────────────── */
// Called from modal button (no arg) or from list view button (with id + el)
function publishNow(id, btnEl) {
    const postId = id || document.getElementById('btnPublishNow').dataset.postId;
    if (!postId) return;

    const btn = btnEl || document.getElementById('btnPublishNow');
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> กำลังส่ง...';

    const fd = new FormData();
    fd.append('action', 'publish_now');
    fd.append('id', postId);

    fetch('', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.ok) {
                // Update status in modal if open
                const statusSpan = document.getElementById('modalPubStatus');
                if (statusSpan && !statusSpan.classList.contains('d-none')) {
                    statusSpan.innerHTML = '<span style="color:#4FC3F7">📅 กำหนดแล้ว — n8n จะโพสต์ภายใน 1 นาที</span>';
                }
                btn.innerHTML = '<i class="fas fa-check me-1"></i> ส่งแล้ว!';
                btn.classList.replace('btn-success', 'btn-outline-success');
                setTimeout(() => {
                    btn.innerHTML = origHtml;
                    btn.disabled = false;
                    if (!id) { // called from modal — close it
                        document.querySelector('#postModal [data-bs-dismiss="modal"]').click();
                    }
                }, 2000);
            } else {
                alert('เกิดข้อผิดพลาด: ' + (res.error || 'ไม่ทราบสาเหตุ'));
                btn.innerHTML = origHtml;
                btn.disabled = false;
            }
        })
        .catch(() => {
            alert('เชื่อมต่อ server ไม่ได้');
            btn.innerHTML = origHtml;
            btn.disabled = false;
        });
}

// Reset media on form reset
const origReset = resetForm;
resetForm = function() {
    origReset();
    selectedMediaIds = [];
    document.getElementById('fMediaIds').value = '';
    document.getElementById('mediaPickerSelected').innerHTML = '';
    selectPostType('image');
    updateCaptionTabs({});
};
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
