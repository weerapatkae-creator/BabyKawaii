<?php
// ── Bootstrap + auth BEFORE any output ───────────────────────────────────────
require_once __DIR__ . '/../config/database.php';
requireLogin();
$pdo = getDB();

/* ── AJAX handlers (must run before ANY HTML output) ─────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');

    // Send reply
    if ($_POST['ajax'] === 'send') {
        $convId  = (int)$_POST['conv_id'];
        $content = trim($_POST['content'] ?? '');
        if (!$convId || !$content) { echo json_encode(['ok'=>false]); exit; }

        $pdo->prepare("INSERT INTO messages (conversation_id,direction,content,message_type,sent_by,sent_at) VALUES (?,?,?,?,?,NOW())")
            ->execute([$convId, 'outbound', $content, 'text', $_SESSION['admin_id'] ?? 1]);
        $msgId = $pdo->lastInsertId();

        $pdo->prepare("UPDATE conversations SET last_message=?,last_message_at=NOW(),status='open',updated_at=NOW() WHERE id=?")
            ->execute([$content, $convId]);

        $conv = $pdo->prepare("
            SELECT c.*, p.name as platform_name, p.slug as platform_slug,
                   pa.page_access_token, pa.account_uid as page_id
            FROM conversations c
            LEFT JOIN platforms p ON p.id=c.platform_id
            LEFT JOIN platform_accounts pa ON pa.id=c.platform_account_id
            WHERE c.id=?
        ");
        $conv->execute([$convId]);
        $conv = $conv->fetch();

        $sent = false;
        if ($conv) {
            $slug = $conv['platform_slug'] ?? '';

            // ── ส่งผ่าน Facebook / Instagram Send API โดยตรง ──
            if (in_array($slug, ['facebook','instagram']) && $conv['page_access_token']) {
                require_once __DIR__ . '/../includes/meta-api.php';
                $sent = sendFbMessage($conv['customer_uid'], $content, $conv['page_access_token']);
            }

            // ── trigger n8n (สำหรับแพลตฟอร์มอื่น หรือ fallback) ──
            if (!$sent) {
                triggerN8n('inbox_reply', [
                    'conversation_id' => $convId,
                    'platform'        => $conv['platform_name'] ?? '',
                    'customer_uid'    => $conv['customer_uid'],
                    'customer_name'   => $conv['customer_name'],
                    'message'         => $content,
                ]);
            }
        }

        echo json_encode(['ok'=>true, 'msg_id'=>$msgId, 'sent_at'=>date('H:i'), 'sent_via'=>$sent?'facebook_api':'n8n']);
        exit;
    }

    // Load messages (marks as read, supports since_id for polling)
    if ($_POST['ajax'] === 'messages') {
        $convId  = (int)$_POST['conv_id'];
        $sinceId = (int)($_POST['since_id'] ?? 0);

        $pdo->prepare("UPDATE messages SET is_read=1 WHERE conversation_id=? AND direction='inbound'")->execute([$convId]);
        $pdo->prepare("UPDATE conversations SET unread_count=0 WHERE id=?")->execute([$convId]);

        if ($sinceId) {
            // Poll mode — only new messages
            $msgs = $pdo->prepare("SELECT m.*,u.full_name as sender_name FROM messages m LEFT JOIN admin_users u ON u.id=m.sent_by WHERE m.conversation_id=? AND m.id>? ORDER BY m.sent_at ASC");
            $msgs->execute([$convId, $sinceId]);
        } else {
            // Initial load — last 100
            $msgs = $pdo->prepare("SELECT m.*,u.full_name as sender_name FROM messages m LEFT JOIN admin_users u ON u.id=m.sent_by WHERE m.conversation_id=? ORDER BY m.sent_at ASC LIMIT 100");
            $msgs->execute([$convId]);
        }
        echo json_encode($msgs->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    // Update conversation status
    if ($_POST['ajax'] === 'status') {
        $pdo->prepare("UPDATE conversations SET status=?,updated_at=NOW() WHERE id=?")->execute([$_POST['status'], (int)$_POST['conv_id']]);
        echo json_encode(['ok'=>true]);
        exit;
    }

    // Save quick reply — คืน id ด้วยเสมอ (ใช้ refresh chips)
    if ($_POST['ajax'] === 'save_qr') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $pdo->prepare("UPDATE quick_replies SET label=?,content=? WHERE id=?")->execute([trim($_POST['label']),trim($_POST['content']),$id]);
        } else {
            $pdo->prepare("INSERT INTO quick_replies (label,content) VALUES (?,?)")->execute([trim($_POST['label']),trim($_POST['content'])]);
            $id = (int)$pdo->lastInsertId();
        }
        echo json_encode(['ok'=>true, 'id'=>$id]);
        exit;
    }

    // Delete quick reply
    if ($_POST['ajax'] === 'delete_qr') {
        $pdo->prepare("DELETE FROM quick_replies WHERE id=?")->execute([(int)$_POST['id']]);
        echo json_encode(['ok'=>true]);
        exit;
    }

    // Get all quick replies (ใช้ refresh chips หลัง save/delete)
    if ($_POST['ajax'] === 'get_qr') {
        $rows = $pdo->query("SELECT id,label,content FROM quick_replies WHERE is_active=1 ORDER BY sort_order,id")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($rows);
        exit;
    }

    echo json_encode(['ok'=>false]);
    exit;
}

// Badge/poll GET handler
if (isset($_GET['ajax_badge'])) {
    header('Content-Type: application/json');
    try {
        $unread = $pdo->query("SELECT COALESCE(SUM(unread_count),0) FROM conversations WHERE status!='closed'")->fetchColumn();
        $open   = $pdo->query("SELECT COUNT(*) FROM conversations WHERE status='open'")->fetchColumn();
        echo json_encode(['unread'=>(int)$unread,'open'=>(int)$open]);
    } catch(Exception $e) {
        echo json_encode(['unread'=>0,'open'=>0]);
    }
    exit;
}

// ── Now safe to output HTML ───────────────────────────────────────────────────
$pageTitle = 'Inbox — ข้อความลูกค้า';
require_once __DIR__ . '/../includes/header.php';

/* ── Data ───────────────────────────────────────────────────────── */
$statusFilter   = $_GET['status']   ?? 'open';
$platformFilter = (int)($_GET['platform'] ?? 0);
$accountFilter  = (int)($_GET['account']  ?? 0);
$search         = trim($_GET['q']   ?? '');
$activeConvId   = (int)($_GET['conv'] ?? 0);

$where  = ['1=1'];
$params = [];
if ($statusFilter === 'new') {
    $where[] = 'c.unread_count > 0';
    $where[] = "c.status != 'closed'";
} elseif ($statusFilter === 'open') {
    $where[] = "c.status IN ('open','pending')";
} elseif ($statusFilter === 'closed') {
    $where[] = "c.status = 'closed'";
}
// 'all' → ไม่ filter status
if ($platformFilter) { $where[] = 'c.platform_id = ?'; $params[] = $platformFilter; }
if ($accountFilter)  { $where[] = 'c.platform_account_id = ?'; $params[] = $accountFilter; }
if ($search)         { $where[] = '(c.customer_name LIKE ? OR c.last_message LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }

try {
    $convStmt = $pdo->prepare(
        "SELECT c.*,
                p.name  AS platform_name,  p.icon  AS platform_icon,  p.color AS platform_color,
                pa.name AS account_name,   pa.color AS account_color
         FROM conversations c
         LEFT JOIN platforms        p  ON p.id  = c.platform_id
         LEFT JOIN platform_accounts pa ON pa.id = c.platform_account_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY c.last_message_at DESC LIMIT 80"
    );
    $convStmt->execute($params);
    $conversations = $convStmt->fetchAll();

    $platforms    = $pdo->query("SELECT * FROM platforms WHERE is_active=1 ORDER BY name")->fetchAll();
    $quickReplies = $pdo->query("SELECT * FROM quick_replies WHERE is_active=1 ORDER BY sort_order")->fetchAll();

    // Accounts grouped for filter chips
    $allAccounts  = $pdo->query(
        "SELECT pa.id, pa.name, pa.color, p.icon AS platform_icon, p.color AS platform_color, p.id AS platform_id
         FROM platform_accounts pa
         JOIN platforms p ON p.id = pa.platform_id
         WHERE pa.is_active=1
         ORDER BY p.name, pa.name"
    )->fetchAll();

    $stats = $pdo->query("SELECT
        SUM(status='open' OR status='pending')           AS open_count,
        SUM(status='closed')                             AS closed_count,
        SUM(unread_count > 0 AND status != 'closed')     AS new_count,
        COALESCE(SUM(unread_count),0)                    AS total_unread
        FROM conversations")->fetch();

    $activeConv = null;
    if ($activeConvId) {
        $s = $pdo->prepare(
            "SELECT c.*,
                    p.name  AS platform_name, p.icon AS platform_icon, p.color AS platform_color,
                    pa.name AS account_name,  pa.color AS account_color
             FROM conversations c
             LEFT JOIN platforms         p  ON p.id  = c.platform_id
             LEFT JOIN platform_accounts pa ON pa.id = c.platform_account_id
             WHERE c.id=?"
        );
        $s->execute([$activeConvId]);
        $activeConv = $s->fetch();
    }
} catch (Exception $e) {
    // Tables may not exist yet — show empty state
    $conversations = []; $platforms = []; $quickReplies = []; $allAccounts = [];
    $stats = ['open_count'=>0,'pending_count'=>0,'closed_count'=>0,'unread_convs'=>0];
    $activeConv = null;
}
?>

<style>
.inbox-wrap { display:flex; height:calc(100vh - 130px); gap:0; overflow:hidden; border-radius:12px; border:1px solid var(--border-color); background:#fff; }

/* Left panel */
.inbox-list { width:300px; min-width:260px; flex-shrink:0; border-right:1px solid var(--border-color); display:flex; flex-direction:column; }
.inbox-list-head { padding:10px 12px; border-bottom:1px solid var(--border-color); }
.inbox-search { width:100%; border:1px solid var(--border-color); border-radius:8px; padding:6px 11px; font-size:0.82rem; font-family:inherit; }
.inbox-search:focus { outline:none; border-color:var(--pink); }
.conv-list { overflow-y:auto; flex:1; }
.conv-item { padding:10px 12px; cursor:pointer; border-bottom:1px solid #f5f5f5; display:flex; gap:9px; align-items:flex-start; transition:background .12s; }
.conv-item:hover { background:#fafafa; }
.conv-item.active { background:#FFF0F5; border-left:3px solid var(--pink); padding-left:9px; }
.conv-item.unread .conv-name { font-weight:700; }
.conv-avatar { width:36px; height:36px; border-radius:50%; background:linear-gradient(135deg,#FF85A2,#9B72CF); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:0.85rem; flex-shrink:0; }
.conv-body { flex:1; min-width:0; }
.conv-name { font-size:0.84rem; font-weight:500; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.conv-preview { font-size:0.74rem; color:#888; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:2px; }
.conv-meta { display:flex; justify-content:space-between; align-items:center; margin-top:2px; }
.conv-time { font-size:0.66rem; color:#aaa; }
.conv-badge { background:var(--pink); color:#fff; border-radius:50%; width:17px; height:17px; display:flex; align-items:center; justify-content:center; font-size:0.62rem; font-weight:700; }
.platform-dot { display:inline-block; width:8px; height:8px; border-radius:50%; margin-right:3px; }

/* Right panel */
.inbox-chat { flex:1; display:flex; flex-direction:column; background:#f8f8f8; min-width:0; }
.chat-head { background:#fff; padding:10px 14px; border-bottom:1px solid var(--border-color); display:flex; align-items:center; gap:10px; }
.chat-head-info { flex:1; min-width:0; }
.chat-head-name { font-weight:700; font-size:0.92rem; }
.chat-head-meta { font-size:0.73rem; color:#888; }
.chat-messages { flex:1; overflow-y:auto; padding:14px; display:flex; flex-direction:column; gap:8px; }
.msg-row { display:flex; gap:7px; align-items:flex-end; }
.msg-row.outbound { flex-direction:row-reverse; }
/* msg-content คือ wrapper ที่กำหนด max-width แทนการกำหนดใน msg-bubble */
.msg-content { max-width:65%; }
.msg-row.outbound .msg-content { align-items:flex-end; display:flex; flex-direction:column; }
.msg-bubble { padding:8px 12px; border-radius:14px; font-size:0.83rem; line-height:1.5; word-break:break-word; }
.msg-row.inbound  .msg-bubble { background:#fff; border:1px solid #eee; border-bottom-left-radius:3px; }
.msg-row.outbound .msg-bubble { background:linear-gradient(135deg,#FF85A2,#d4629e); color:#fff; border-bottom-right-radius:3px; }
.msg-time { font-size:0.63rem; color:#bbb; margin-top:3px; display:block; }
.msg-row.outbound .msg-time { text-align:right; color:#bbb; }
.msg-system { text-align:center; font-size:0.71rem; color:#bbb; padding:3px 0; font-style:italic; }
.msg-avatar { width:26px; height:26px; border-radius:50%; background:linear-gradient(135deg,#FF85A2,#9B72CF); color:#fff; display:flex; align-items:center; justify-content:center; font-size:0.66rem; font-weight:700; flex-shrink:0; overflow:hidden; }
.msg-avatar img { width:100%; height:100%; object-fit:cover; border-radius:50%; }
.msg-img { max-width:200px; border-radius:10px; cursor:pointer; }
/* Live indicator */
.live-dot { display:inline-block; width:7px; height:7px; border-radius:50%; background:#22c55e; animation:livePulse 1.5s infinite; margin-right:3px; vertical-align:middle; }
@keyframes livePulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:0.4;transform:scale(0.8)} }

/* Reply box */
.chat-reply { background:#fff; border-top:1px solid var(--border-color); padding:10px 14px; }
.qr-chips { display:flex; flex-wrap:wrap; gap:5px; margin-bottom:8px; }
.qr-chip { background:#f0f0f5; border:1px solid #ddd; border-radius:16px; padding:3px 10px; font-size:0.74rem; cursor:pointer; transition:all .12s; white-space:nowrap; }
.qr-chip:hover { background:var(--pink); color:#fff; border-color:var(--pink); }
.reply-input-row { display:flex; gap:8px; align-items:flex-end; }
.reply-textarea { flex:1; border:1px solid var(--border-color); border-radius:10px; padding:8px 11px; font-family:inherit; font-size:0.83rem; resize:none; min-height:38px; max-height:110px; overflow-y:auto; }
.reply-textarea:focus { outline:none; border-color:var(--pink); }
.btn-send { background:linear-gradient(135deg,#FF85A2,#d4629e); color:#fff; border:none; border-radius:10px; padding:8px 16px; cursor:pointer; font-family:inherit; font-weight:600; white-space:nowrap; transition:opacity .15s; }
.btn-send:hover { opacity:0.88; }
.btn-send:disabled { opacity:0.5; cursor:not-allowed; }

/* Status tabs */
.status-tabs { display:flex; border-bottom:1px solid var(--border-color); }
.status-tab { flex:1; text-align:center; padding:7px 2px; font-size:0.76rem; cursor:pointer; border-bottom:2px solid transparent; color:#888; text-decoration:none; transition:color .12s; }
.status-tab.active { color:var(--pink); border-bottom-color:var(--pink); font-weight:600; }
.status-tab .tab-count { display:inline-block; background:#eee; border-radius:8px; padding:0px 5px; font-size:0.63rem; margin-left:2px; }
.status-tab.active .tab-count { background:var(--pink); color:#fff; }
.inbox-empty { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; color:#aaa; gap:8px; }

/* Typing indicator */
.typing-dot { display:inline-block; width:6px; height:6px; border-radius:50%; background:#aaa; animation:typing .9s infinite; }
.typing-dot:nth-child(2) { animation-delay:.2s; }
.typing-dot:nth-child(3) { animation-delay:.4s; }
@keyframes typing { 0%,60%,100%{transform:translateY(0)} 30%{transform:translateY(-4px)} }

/* Message entrance animation */
@keyframes msgIn {
    from { opacity:0; transform:translateY(8px); }
    to   { opacity:1; transform:translateY(0); }
}
.msg-row    { animation: msgIn 0.18s ease-out; }
.msg-system { animation: msgIn 0.15s ease-out; }

@media(max-width:768px){
    .inbox-list { width:100%; position:absolute; z-index:5; }
    .inbox-list.hide-mobile { display:none; }
}
</style>

<div class="container-fluid fade-in px-0">
    <!-- Header bar -->
    <div class="d-flex gap-2 px-3 pt-3 pb-2 flex-wrap align-items-center">
        <h1 class="page-title mb-0">💬 Inbox</h1>
        <div class="d-flex gap-2 ms-auto flex-wrap align-items-center">
            <?php if (($stats['new_count'] ?? 0) > 0): ?>
            <span class="badge bg-danger" id="globalNewBadge">💬 ใหม่ <?= $stats['new_count'] ?></span>
            <?php else: ?>
            <span class="badge bg-danger d-none" id="globalNewBadge">💬 ใหม่ 0</span>
            <?php endif; ?>
            <span class="badge bg-success" id="globalOpenBadge">🟢 เปิด <?= $stats['open_count'] ?? 0 ?></span>
            <button class="btn btn-sm btn-outline-secondary" onclick="openQrManager()">⚡ ข้อความสำเร็จรูป</button>
        </div>
    </div>

    <div class="inbox-wrap mx-3 mb-3" id="inboxWrap">

        <!-- LEFT: conversation list -->
        <div class="inbox-list" id="inboxList">
            <div class="inbox-list-head">
                <input type="text" id="searchInput" class="inbox-search" placeholder="🔍 ค้นหาชื่อ / ข้อความ..."
                       value="<?= htmlspecialchars($search) ?>" oninput="filterConvs(this.value)">
                <?php if (!empty($allAccounts)): ?>
                <div class="d-flex gap-1 mt-2 flex-wrap">
                    <?php foreach ($allAccounts as $acc):
                        $isActiveAcc = $accountFilter === (int)$acc['id'];
                        $base        = $acc['color'] ?: $acc['platform_color'] ?: '#888';
                    ?>
                    <a href="?status=<?= $statusFilter ?>&account=<?= $acc['id'] ?><?= $activeConvId?'&conv='.$activeConvId:'' ?>"
                       class="badge text-decoration-none"
                       style="background:<?= $isActiveAcc ? $base : '#eee' ?>;color:<?= $isActiveAcc ? '#fff' : '#555' ?>;font-weight:<?= $isActiveAcc?'700':'400' ?>;">
                        <?= $acc['platform_icon'] ?> <?= htmlspecialchars($acc['name']) ?>
                    </a>
                    <?php endforeach; ?>
                    <?php if ($accountFilter || $platformFilter): ?>
                    <a href="?status=<?= $statusFilter ?><?= $activeConvId?'&conv='.$activeConvId:'' ?>"
                       class="badge bg-secondary text-decoration-none">✕ ทั้งหมด</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Status tabs -->
            <div class="status-tabs">
                <?php
                $tabCounts = [
                    'new'    => $stats['new_count']    ?? 0,
                    'open'   => $stats['open_count']   ?? 0,
                    'closed' => $stats['closed_count'] ?? 0,
                    'all'    => ($stats['open_count']??0)+($stats['closed_count']??0),
                ];
                $tabLabels = ['new'=>'💬 ใหม่','open'=>'🟢 เปิด','closed'=>'🔒 จบแล้ว','all'=>'ทั้งหมด'];
                foreach ($tabLabels as $s => $label): ?>
                <a href="?status=<?= $s ?><?= $accountFilter?'&account='.$accountFilter:'' ?><?= $activeConvId?'&conv='.$activeConvId:'' ?>"
                   class="status-tab <?= $statusFilter===$s?'active':'' ?>"
                   id="tab_<?= $s ?>">
                    <?= $label ?><span class="tab-count" id="tabCount_<?= $s ?>"><?= $tabCounts[$s] ?></span>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- Conversation list -->
            <div class="conv-list" id="convList">
                <?php if (empty($conversations)): ?>
                <div class="text-center py-5 text-muted" style="font-size:0.85rem;">
                    <i class="fas fa-comment-slash fa-2x mb-2 d-block opacity-25"></i>
                    ไม่มีบทสนทนา<br>
                    <span class="opacity-50" style="font-size:0.75rem;">ข้อความจะปรากฏเมื่อลูกค้าติดต่อผ่าน API</span>
                </div>
                <?php endif; ?>
                <?php foreach ($conversations as $conv):
                    $initial  = mb_substr($conv['customer_name'] ?: '?', 0, 1);
                    $isActive = $activeConvId === (int)$conv['id'];
                    $hasUnread= $conv['unread_count'] > 0;
                    $timeAgoStr = $conv['last_message_at'] ? timeAgo($conv['last_message_at']) : '';
                ?>
                <div class="conv-item <?= $isActive?'active':'' ?> <?= $hasUnread?'unread':'' ?>"
                     data-conv="<?= $conv['id'] ?>"
                     onclick="openConv(<?= $conv['id'] ?>)">
                    <div class="conv-avatar"
                         style="<?= $conv['platform_color'] && !$conv['customer_avatar'] ? 'background:linear-gradient(135deg,'.$conv['platform_color'].','.adjustColor($conv['platform_color']).');' : '' ?>">
                        <?php if ($conv['customer_avatar']): ?>
                            <img src="<?= htmlspecialchars($conv['customer_avatar']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                        <?php else: ?>
                            <?= htmlspecialchars($initial) ?>
                        <?php endif; ?>
                    </div>
                    <div class="conv-body">
                        <div class="d-flex justify-content-between align-items-start gap-1">
                            <div class="conv-name">
                                <?php if ($conv['platform_color']): ?>
                                <span class="platform-dot" style="background:<?= $conv['platform_color'] ?>;"></span>
                                <?php endif; ?>
                                <?= htmlspecialchars($conv['customer_name'] ?: 'ไม่ระบุชื่อ') ?>
                            </div>
                            <?php if ($hasUnread): ?>
                            <span class="conv-badge"><?= min($conv['unread_count'],9) ?><?= $conv['unread_count']>9?'+':'' ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="conv-preview"><?= htmlspecialchars(mb_substr($conv['last_message'] ?? '—', 0, 50)) ?></div>
                        <div class="conv-meta">
                            <span class="conv-time"><?= $timeAgoStr ?></span>
                            <span style="font-size:0.67rem;">
                                <?php if ($conv['account_name'] ?? ''): ?>
                                <span style="background:<?= $conv['account_color'] ?? $conv['platform_color'] ?? '#888' ?>;color:#fff;padding:1px 5px;border-radius:8px;font-size:0.62rem;">
                                    <?= $conv['platform_icon'] ?? '' ?> <?= htmlspecialchars($conv['account_name']) ?>
                                </span>
                                <?php else: ?>
                                <span style="color:#bbb;"><?= $conv['platform_icon'] ?? '' ?></span>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- RIGHT: chat panel -->
        <div class="inbox-chat" id="chatPanel">
            <?php if ($activeConv): ?>
            <!-- Chat header -->
            <div class="chat-head">
                <button class="btn btn-sm btn-outline-secondary d-md-none me-1" onclick="toggleList()">☰</button>
                <div class="conv-avatar" style="width:38px;height:38px;font-size:0.9rem;overflow:hidden;
                    <?= $activeConv['customer_avatar'] ? '' : ($activeConv['platform_color']?'background:linear-gradient(135deg,'.$activeConv['platform_color'].','.adjustColor($activeConv['platform_color']).');':'') ?>">
                    <?php if ($activeConv['customer_avatar']): ?>
                        <img src="<?= htmlspecialchars($activeConv['customer_avatar']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                    <?php else: ?>
                        <?= mb_substr($activeConv['customer_name'] ?: '?', 0, 1) ?>
                    <?php endif; ?>
                </div>
                <div class="chat-head-info">
                    <div class="chat-head-name">
                        <span class="live-dot" id="liveDot" title="Real-time"></span>
                        <?= htmlspecialchars($activeConv['customer_name'] ?: 'ไม่ระบุชื่อ') ?>
                    </div>
                    <div class="chat-head-meta d-flex align-items-center gap-2 flex-wrap">
                        <?php if ($activeConv['platform_icon']): ?>
                        <span style="background:<?= $activeConv['platform_color'] ?>;color:#fff;padding:1px 8px;border-radius:10px;font-size:0.68rem;">
                            <?= $activeConv['platform_icon'] ?> <?= htmlspecialchars($activeConv['platform_name']) ?>
                        </span>
                        <?php endif; ?>
                        <?php if ($activeConv['account_name'] ?? ''): ?>
                        <span style="background:<?= $activeConv['account_color'] ?? '#888' ?>;color:#fff;padding:1px 8px;border-radius:10px;font-size:0.68rem;">
                            <?= htmlspecialchars($activeConv['account_name']) ?>
                        </span>
                        <?php endif; ?>
                        <span class="text-muted" style="font-size:0.7rem;">UID: <?= htmlspecialchars($activeConv['customer_uid']) ?></span>
                        <span id="typingIndicator" style="display:none;font-size:0.72rem;color:#aaa;">
                            กำลังพิมพ์<span class="typing-dot ms-1"></span><span class="typing-dot"></span><span class="typing-dot"></span>
                        </span>
                    </div>
                </div>
                <div class="d-flex gap-2 align-items-center flex-shrink-0">
                    <select class="form-select form-select-sm" style="width:auto;font-size:0.78rem;" onchange="setStatus(<?= $activeConv['id'] ?>, this.value)">
                        <option value="open"   <?= in_array($activeConv['status'],['open','pending'])?'selected':'' ?>>🟢 กำลังคุย</option>
                        <option value="closed" <?= $activeConv['status']==='closed'?'selected':'' ?>>🔒 จบแล้ว</option>
                    </select>
                    <a href="<?= SITE_URL ?>/pages/orders.php?q=<?= urlencode($activeConv['customer_name'] ?? '') ?>"
                       class="btn btn-sm btn-outline-primary" title="ดูออเดอร์"
                       target="_blank" rel="noopener">
                        <i class="fas fa-box-open"></i>
                    </a>
                </div>
            </div>

            <!-- Messages area -->
            <div class="chat-messages" id="chatMessages">
                <div class="msg-system" id="loadingMsg">
                    <span class="typing-dot"></span><span class="typing-dot"></span><span class="typing-dot"></span>
                </div>
            </div>

            <!-- Reply box -->
            <div class="chat-reply">
                <?php if (!empty($quickReplies)): ?>
                <div class="qr-chips" id="qrChips">
                    <?php foreach ($quickReplies as $qr): ?>
                    <span class="qr-chip" onclick="useQR(<?= htmlspecialchars(json_encode($qr['content'])) ?>)"><?= htmlspecialchars($qr['label']) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <div class="reply-input-row">
                    <textarea id="replyText" class="reply-textarea" placeholder="พิมพ์ข้อความ... (Enter = ส่ง, Shift+Enter = ขึ้นบรรทัด)" rows="1"
                              onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendReply();}"
                              oninput="autoResize(this)"></textarea>
                    <button class="btn-send" id="btnSend" onclick="sendReply()">ส่ง ➤</button>
                </div>
            </div>

            <?php else: ?>
            <div class="inbox-empty">
                <i class="fas fa-comments fa-3x opacity-25"></i>
                <div style="font-size:0.88rem;">เลือกบทสนทนาจากรายการซ้าย</div>
                <?php if (empty($conversations)): ?>
                <div class="text-center mt-2 px-4" style="font-size:0.78rem;color:#bbb;max-width:300px;">
                    ยังไม่มีข้อความ — เชื่อมต่อ Facebook/Instagram/TikTok ผ่าน n8n webhook แล้วข้อความจากลูกค้าจะปรากฏที่นี่
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Quick Reply Manager Modal -->
<div class="modal fade" id="qrModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#FFE4EC,#EDE7FF);">
                <h5 class="modal-title">⚡ จัดการข้อความสำเร็จรูป</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="qrList">
                    <?php foreach ($quickReplies as $qr): ?>
                    <div class="d-flex gap-2 align-items-start mb-3 qr-row" data-id="<?= $qr['id'] ?>">
                        <div class="flex-grow-1">
                            <input type="text" class="form-control form-control-sm mb-1 qr-label" value="<?= htmlspecialchars($qr['label']) ?>" placeholder="ชื่อปุ่ม (เช่น ขอบคุณ)">
                            <textarea class="form-control form-control-sm qr-content" rows="2" placeholder="ข้อความที่จะส่ง"><?= htmlspecialchars($qr['content']) ?></textarea>
                        </div>
                        <div class="d-flex flex-column gap-1 pt-1">
                            <button class="btn btn-sm btn-outline-primary" onclick="saveQR(this)" title="บันทึก">💾</button>
                            <button class="btn btn-sm btn-outline-danger"  onclick="deleteQR(this)" title="ลบ">🗑️</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button class="btn btn-sm btn-outline-secondary mt-2" onclick="addQRRow()">
                    <i class="fas fa-plus me-1"></i> เพิ่มข้อความใหม่
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const CONV_ID          = <?= $activeConvId ?: 'null' ?>;
const SITE_URL         = '<?= SITE_URL ?>';
const CUSTOMER_AVATAR  = <?= json_encode($activeConv['customer_avatar'] ?? '') ?>;
const CUSTOMER_INITIAL = <?= json_encode(mb_substr($activeConv['customer_name'] ?? '?', 0, 1)) ?>;
let   lastMsgId       = 0;
let   pollTimer       = null;
// globalLastId เริ่มจาก max ID ปัจจุบัน → ป้องกันเสียงดังกับข้อความเก่า
let   globalLastId    = <?= (int)$pdo->query("SELECT COALESCE(MAX(id),0) FROM messages")->fetchColumn() ?>;

/* ── Notification sound (Web Audio API — ไม่ต้องใช้ไฟล์เสียง) ──── */
let _audioCtx = null;
function playNotif() {
    try {
        if (!_audioCtx) _audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const ctx = _audioCtx;
        const osc = ctx.createOscillator();
        const g   = ctx.createGain();
        osc.connect(g); g.connect(ctx.destination);
        osc.type = 'sine';
        osc.frequency.setValueAtTime(820, ctx.currentTime);
        osc.frequency.setValueAtTime(1060, ctx.currentTime + 0.08);
        g.gain.setValueAtTime(0.18, ctx.currentTime);
        g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.38);
        osc.start(ctx.currentTime);
        osc.stop(ctx.currentTime + 0.38);
    } catch(e) {}
}
// Unlock AudioContext บน mobile (ต้อง user gesture ก่อน)
document.addEventListener('click', () => {
    if (!_audioCtx) _audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    if (_audioCtx.state === 'suspended') _audioCtx.resume();
}, { once: true });

/* ── HTML escaping ──────────────────────────────────────────────── */
function esc(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
}

/* ── Render a single message row ───────────────────────────────── */
function renderMsg(m) {
    if (m.message_type === 'system') return `<div class="msg-system">${esc(m.content)}</div>`;
    const dir  = m.direction;
    const time = (m.sent_at || m.created_at || '').substring(11, 16);
    const senderLabel = dir === 'outbound' && m.sender_name ? ` · ${esc(m.sender_name)}` : '';

    // avatar
    const custAv = CUSTOMER_AVATAR
        ? `<div class="msg-avatar"><img src="${esc(CUSTOMER_AVATAR)}" alt="${esc(CUSTOMER_INITIAL)}"></div>`
        : `<div class="msg-avatar">${esc(CUSTOMER_INITIAL)}</div>`;
    const adminAv = `<div class="msg-avatar" style="background:linear-gradient(135deg,#FF85A2,#E8547A);">A</div>`;
    const avatar  = dir === 'inbound' ? custAv : adminAv;

    let bubbleContent = '';
    if ((m.message_type === 'image' || m.message_type === 'video') && m.media_url) {
        bubbleContent = `<img src="${esc(m.media_url)}" class="msg-img" onclick="window.open('${esc(m.media_url)}','_blank')">`;
    } else {
        bubbleContent = esc(m.content);
    }

    return `<div class="msg-row ${dir}" data-msg-id="${parseInt(m.id)||''}">
        ${dir === 'inbound' ? avatar : ''}
        <div class="msg-content">
            <div class="msg-bubble">${bubbleContent}</div>
            <span class="msg-time">${time}${senderLabel}</span>
        </div>
        ${dir === 'outbound' ? avatar : ''}
    </div>`;
}

/* ── Load messages (initial load) ──────────────────────────────── */
function loadMessages(convId) {
    fetch('', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({ajax:'messages', conv_id: convId})
    })
    .then(r => r.json())
    .then(msgs => {
        const el = document.getElementById('chatMessages');
        if (!msgs.length) {
            el.innerHTML = '<div class="msg-system">ยังไม่มีข้อความในบทสนทนานี้</div>';
            lastMsgId = 0;
        } else {
            el.innerHTML = msgs.map(renderMsg).join('');
            lastMsgId = msgs.reduce((max, m) => Math.max(max, m.id||0), 0);
            el.scrollTop = el.scrollHeight;
        }
        startRealtime(convId);
    })
    .catch(() => {
        document.getElementById('chatMessages').innerHTML = '<div class="msg-system text-danger">โหลดข้อความไม่ได้ — ลองรีเฟรช</div>';
        // fallback polling
        startPolling(convId);
    });
}

/* ── Real-time: polling 1 วินาที ──────────────────────────────── */
// SSE ถูกถอดออกเพราะมัน hold PHP-FPM worker ทำให้ webhook ช้า
// polling 1 วินาทีใช้ worker แค่ ~20ms ต่อรอบ ไม่ block อะไร
function startRealtime(convId) {
    startPolling(convId);
}

function startPolling(convId) {
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(() => pollMessages(convId), 1000);
}

function pollMessages(convId) {
    fetch('', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({ajax:'messages', conv_id: convId, since_id: lastMsgId})
    })
    .then(r => r.json())
    .then(msgs => {
        if (!msgs.length) return;
        const el  = document.getElementById('chatMessages');
        const atB = el.scrollHeight - el.scrollTop - el.clientHeight < 80;
        msgs.forEach(m => {
            const mid = parseInt(m.id) || 0;
            // ข้ามถ้า message นี้มีใน DOM แล้ว (optimistic bubble)
            if (mid && el.querySelector(`[data-msg-id="${mid}"]`)) {
                lastMsgId = Math.max(lastMsgId, mid);
                return;
            }
            el.insertAdjacentHTML('beforeend', renderMsg(m));
            lastMsgId = Math.max(lastMsgId, mid);
        });
        if (atB) el.scrollTop = el.scrollHeight;
        // ไม่ play sound ที่นี่ — globalPoll จัดการเสียงแทน (กัน double sound)
    })
    .catch(() => {});
}


/* ── Open conversation ─────────────────────────────────────────── */
function openConv(id) {
    // Navigate to conversation
    window.location.href = `?status=<?= $statusFilter ?>&conv=${id}<?= $platformFilter?'&platform='.$platformFilter:'' ?>`;
}

/* ── Send reply ─────────────────────────────────────────────────── */
function sendReply() {
    if (!CONV_ID) return;
    const txt     = document.getElementById('replyText');
    const btn     = document.getElementById('btnSend');
    const content = txt.value.trim();
    if (!content) return;

    txt.value = '';
    autoResize(txt);
    btn.disabled = true;

    // Optimistic UI
    const el   = document.getElementById('chatMessages');
    const now  = new Date().toTimeString().substring(0,5);
    const tmpId = 'tmp_' + Date.now();
    el.insertAdjacentHTML('beforeend', `<div class="msg-row outbound" id="${tmpId}">
        <div>
            <div class="msg-bubble">${esc(content)}</div>
            <span class="msg-time">${now} · กำลังส่ง...</span>
        </div>
        <div class="msg-avatar" style="background:linear-gradient(135deg,#FF85A2,#E8547A);">A</div>
    </div>`);
    el.scrollTop = el.scrollHeight;

    fetch('', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({ajax:'send', conv_id: CONV_ID, content})
    })
    .then(r => r.json())
    .then(res => {
        btn.disabled = false;
        const tmp = document.getElementById(tmpId);
        if (res.ok) {
            const mid = parseInt(res.msg_id) || 0;
            if (mid) lastMsgId = Math.max(lastMsgId, mid);
            if (tmp) {
                // ใส่ data-msg-id ก่อน → pollMessages จะ skip ข้อความนี้ (กัน duplicate)
                if (mid) tmp.setAttribute('data-msg-id', mid);
                tmp.querySelector('.msg-time').textContent = res.sent_at || now;
                tmp.removeAttribute('id');
            }
        } else {
            if (tmp) tmp.querySelector('.msg-time').textContent = '❌ ส่งไม่ได้';
        }
    })
    .catch(() => {
        btn.disabled = false;
        const tmp = document.getElementById(tmpId);
        if (tmp) tmp.querySelector('.msg-time').textContent = '❌ ไม่มีการเชื่อมต่อ';
    });
}

/* ── Set status ─────────────────────────────────────────────────── */
function setStatus(id, status) {
    fetch('', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({ajax:'status', conv_id:id, status})
    });
}

/* ── Quick replies ──────────────────────────────────────────────── */
function useQR(content) {
    const ta = document.getElementById('replyText');
    if (ta) { ta.value = content; ta.focus(); autoResize(ta); }
}

function openQrManager() {
    bootstrap.Modal.getOrCreateInstance(document.getElementById('qrModal')).show();
}

function addQRRow() {
    document.getElementById('qrList').insertAdjacentHTML('beforeend', `
    <div class="d-flex gap-2 align-items-start mb-3 qr-row" data-id="0">
        <div class="flex-grow-1">
            <input type="text" class="form-control form-control-sm mb-1 qr-label" placeholder="ชื่อปุ่ม">
            <textarea class="form-control form-control-sm qr-content" rows="2" placeholder="ข้อความที่จะส่ง"></textarea>
        </div>
        <div class="d-flex flex-column gap-1 pt-1">
            <button class="btn btn-sm btn-outline-primary" onclick="saveQR(this)">💾</button>
            <button class="btn btn-sm btn-outline-danger"  onclick="deleteQR(this)">🗑️</button>
        </div>
    </div>`);
}

function saveQR(btn) {
    const row     = btn.closest('.qr-row');
    const label   = row.querySelector('.qr-label').value.trim();
    const content = row.querySelector('.qr-content').value.trim();
    if (!label || !content) { btn.textContent='⚠️'; setTimeout(()=>btn.textContent='💾',1200); return; }
    fetch('', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({ajax:'save_qr', id: row.dataset.id||0, label, content})
    }).then(r=>r.json()).then(res => {
        if (res.ok) {
            if (res.id) row.dataset.id = res.id;  // อัปเดต id สำหรับแถวใหม่
            btn.textContent = '✅';
            setTimeout(() => btn.textContent = '💾', 1500);
            refreshQRChips(); // ← อัปเดต chips ทันที
        }
    });
}

function deleteQR(btn) {
    const row = btn.closest('.qr-row');
    if (!confirm('ลบข้อความนี้?')) return;
    const id = row.dataset.id;
    if (id && id !== '0') {
        fetch('', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body: new URLSearchParams({ajax:'delete_qr', id})
        }).then(() => refreshQRChips()); // ← อัปเดต chips หลังลบ
    }
    row.remove();
}

/* ── Refresh QR chips ใน reply box ─────────────────────────────── */
function refreshQRChips() {
    fetch('', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({ajax:'get_qr'})
    })
    .then(r => r.json())
    .then(qrs => {
        const chips = document.getElementById('qrChips');
        if (!chips) return;
        if (!qrs.length) { chips.innerHTML = ''; return; }
        chips.innerHTML = qrs.map(q =>
            `<span class="qr-chip" onclick="useQR(${JSON.stringify(q.content)})">${esc(q.label)}</span>`
        ).join('');
    })
    .catch(() => {});
}

/* ── Filter convs (client-side) ────────────────────────────────── */
function filterConvs(q) {
    q = q.toLowerCase();
    document.querySelectorAll('.conv-item').forEach(el => {
        const name = el.querySelector('.conv-name').textContent.toLowerCase();
        const prev = el.querySelector('.conv-preview').textContent.toLowerCase();
        el.style.display = (name.includes(q) || prev.includes(q)) ? '' : 'none';
    });
}

/* ── Auto-resize textarea ───────────────────────────────────────── */
function autoResize(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 110) + 'px';
}

/* ── Toggle list on mobile ──────────────────────────────────────── */
function toggleList() {
    document.getElementById('inboxList').classList.toggle('hide-mobile');
}

/* ── Global Notify: real-time sound + badge + conv list ─────────── */
// globalLastId ถูก init จาก PHP (max message ID ตอน load) — อยู่บนสุดของ <script>
let globalTimer = null;

function startGlobalNotify() {
    globalTimer = setInterval(globalPoll, 1500);
}

function globalPoll() {
    fetch(`${SITE_URL}/api/inbox-notify.php?last_id=${globalLastId}`)
    .then(r => r.json())
    .then(data => {
        // เล่นเสียงเฉพาะข้อความขาเข้าที่ใหม่กว่า globalLastId (ลูกค้าทักมา)
        if (data.has_new && data.new_id > globalLastId) {
            playNotif();
            flashTitle();
        }
        if (data.new_id > globalLastId) globalLastId = data.new_id;

        // อัปเดต stat badges
        updateStatBadges(data.stats);

        // อัปเดต conv list (เฉพาะ unread count + ย้าย conv ที่มีข้อความใหม่ขึ้นบน)
        if (data.convs) updateConvList(data.convs);
    })
    .catch(() => {});
}

function updateStatBadges(stats) {
    if (!stats) return;
    const newCount  = parseInt(stats.new_count)  || 0;
    const openCount = parseInt(stats.open_count) || 0;

    // Header badges
    const newBadge  = document.getElementById('globalNewBadge');
    const openBadge = document.getElementById('globalOpenBadge');
    if (newBadge)  { newBadge.textContent = `💬 ใหม่ ${newCount}`; newBadge.classList.toggle('d-none', newCount === 0); }
    if (openBadge) openBadge.textContent = `🟢 เปิด ${openCount}`;

    // Tab counts
    const map = { new: stats.new_count, open: stats.open_count, closed: stats.closed_count };
    Object.entries(map).forEach(([k, v]) => {
        const el = document.getElementById(`tabCount_${k}`);
        if (el) el.textContent = v ?? 0;
    });

    // Browser tab title
    const total = parseInt(stats.total_unread) || 0;
    document.title = total > 0 ? `(${total}) Inbox — BabyKawaii` : 'Inbox — BabyKawaii';
}

function updateConvList(convs) {
    convs.forEach(c => {
        const item = document.querySelector(`.conv-item[data-conv="${c.id}"]`);
        if (!item) return; // conv ไม่อยู่ใน list ที่ filter ปัจจุบัน

        // อัปเดต unread badge
        const badge = item.querySelector('.conv-badge');
        const name  = item.querySelector('.conv-name');
        if (parseInt(c.unread_count) > 0) {
            item.classList.add('unread');
            if (name) name.style.fontWeight = '700';
            if (badge) { badge.style.display='flex'; badge.textContent = Math.min(c.unread_count,9)+(c.unread_count>9?'+':''); }
        } else {
            item.classList.remove('unread');
            if (name) name.style.fontWeight = '';
            if (badge) badge.style.display = 'none';
        }

        // อัปเดต preview text
        const preview = item.querySelector('.conv-preview');
        if (preview && c.last_message) preview.textContent = c.last_message.substring(0,50);
    });
}

/* ── Flash browser tab เมื่อมีข้อความใหม่ ──────────────────────── */
let flashTimer = null;
function flashTitle() {
    if (document.hasFocus()) return; // ไม่ flash ถ้าหน้าต่าง focus อยู่
    let on = true;
    clearInterval(flashTimer);
    flashTimer = setInterval(() => {
        document.title = on ? '🔔 ข้อความใหม่!' : 'Inbox — BabyKawaii';
        on = !on;
    }, 800);
    // หยุด flash เมื่อ focus กลับมา
    window.addEventListener('focus', () => {
        clearInterval(flashTimer);
        flashTimer = null;
    }, { once: true });
}

// ── Init ──────────────────────────────────────────────────────────
if (CONV_ID) loadMessages(CONV_ID);
startGlobalNotify(); // real-time sound + badge + conv list updates
</script>

<?php
function adjustColor(string $hex): string {
    // Darken a hex color by ~20% for gradient
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    [$r,$g,$b] = [hexdec(substr($hex,0,2)), hexdec(substr($hex,2,2)), hexdec(substr($hex,4,2))];
    return sprintf('#%02x%02x%02x', max(0,$r-40), max(0,$g-40), max(0,$b-40));
}
// timeAgo() is defined in config/database.php — do NOT redeclare here
?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
