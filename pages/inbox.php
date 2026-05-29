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
                   pa.page_access_token, pa.account_uid as page_id,
                   pa.app_secret, pa.webhook_verify_token as app_key
            FROM conversations c
            LEFT JOIN platforms p ON p.id=c.platform_id
            LEFT JOIN platform_accounts pa ON pa.id=c.platform_account_id
            WHERE c.id=?
        ");
        $conv->execute([$convId]);
        $conv = $conv->fetch();

        $sent = false;
        $slug = '';
        if ($conv) {
            $slug = $conv['platform_slug'] ?? '';

            // ── ส่งผ่าน Facebook / Instagram Send API โดยตรง ──
            if (in_array($slug, ['facebook','instagram']) && $conv['page_access_token']) {
                require_once __DIR__ . '/../includes/meta-api.php';
                $sent = sendFbMessage($conv['customer_uid'], $content, $conv['page_access_token']);
            }

            // ── ส่งผ่าน TikTok Shop IM API ──
            if (!$sent && $slug === 'tiktok' && $conv['page_access_token'] && $conv['app_key']) {
                require_once __DIR__ . '/../includes/tiktok-api.php';
                $sent = sendTiktokMessage(
                    $conv['customer_uid'],        // conversation_id
                    $content,
                    $conv['page_access_token'],   // access_token
                    $conv['page_id']    ?? '',    // shop_id
                    $conv['app_key']    ?? '',    // app_key
                    $conv['app_secret'] ?? ''     // app_secret
                );
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

        $via = $sent ? (($slug === 'tiktok') ? 'tiktok_api' : 'facebook_api') : 'n8n';
        echo json_encode(['ok'=>true, 'msg_id'=>$msgId, 'sent_at'=>date('H:i'), 'sent_via'=>$via]);
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

    // ── ค้นสินค้า + สต็อก ────────────────────────────────────────────
    if ($_POST['ajax'] === 'products_search') {
        $q     = trim($_POST['q'] ?? '');
        $qLike = "%$q%";
        if ($q === '') {
            $stmt = $pdo->prepare("
                SELECT p.id,p.name,p.sku,p.selling_price,p.main_image,p.status,
                       COALESCE(SUM(s.quantity),0) AS total_stock
                FROM products p LEFT JOIN stock s ON s.product_id=p.id
                WHERE p.status IN ('active','out_of_stock')
                GROUP BY p.id
                ORDER BY p.is_featured DESC, p.status='active' DESC, p.name
                LIMIT 30
            ");
            $stmt->execute();
        } else {
            $stmt = $pdo->prepare("
                SELECT p.id,p.name,p.sku,p.selling_price,p.main_image,p.status,
                       COALESCE(SUM(s.quantity),0) AS total_stock
                FROM products p LEFT JOIN stock s ON s.product_id=p.id
                WHERE (p.name LIKE ? OR p.sku LIKE ? OR p.tags LIKE ?)
                GROUP BY p.id
                ORDER BY p.status='active' DESC, p.name
                LIMIT 20
            ");
            $stmt->execute([$qLike,$qLike,$qLike]);
        }
        $prods = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($prods) {
            $ids = implode(',', array_map('intval', array_column($prods,'id')));
            $stockRows = $pdo->query("
                SELECT product_id,size,color,quantity,min_alert
                FROM stock WHERE product_id IN ($ids)
                ORDER BY product_id,
                  FIELD(size,'Premature','NB','0-3M','3-6M','6-9M','9-12M','12-18M','18-24M','Free Size')
            ")->fetchAll(PDO::FETCH_ASSOC);
            $stockMap = [];
            foreach ($stockRows as $sr) { $stockMap[$sr['product_id']][] = $sr; }
            foreach ($prods as &$p) {
                $p['image_url'] = $p['main_image'] ? (UPLOAD_URL . $p['main_image']) : '';
                $p['stocks']    = $stockMap[$p['id']] ?? [];
            }
        }
        echo json_encode($prods, JSON_UNESCAPED_UNICODE); exit;
    }

    // ── ค้นออเดอร์ลูกค้า ─────────────────────────────────────────────
    if ($_POST['ajax'] === 'customer_orders') {
        $q = trim($_POST['q'] ?? '');
        if (!$q) { echo json_encode([]); exit; }
        $stmt = $pdo->prepare("
            SELECT o.id,o.order_number,o.customer_name,o.customer_phone,
                   o.total_amount,o.order_status,o.payment_status,o.order_date,
                   p.name AS platform_name, p.color AS platform_color,
                   COUNT(oi.id) AS item_count
            FROM orders o
            LEFT JOIN platforms p ON p.id=o.platform_id
            LEFT JOIN order_items oi ON oi.order_id=o.id
            WHERE o.customer_name LIKE ? OR o.order_number LIKE ? OR o.customer_phone LIKE ?
            GROUP BY o.id ORDER BY o.order_date DESC LIMIT 15
        ");
        $stmt->execute(["%$q%","%$q%","%$q%"]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE); exit;
    }

    // ── สต็อกต่ำ ──────────────────────────────────────────────────────
    if ($_POST['ajax'] === 'low_stock') {
        $stmt = $pdo->query("
            SELECT p.id,p.name,p.sku,p.selling_price,
                   s.size,s.color,s.quantity,s.min_alert
            FROM stock s
            JOIN products p ON p.id=s.product_id
            WHERE s.quantity <= s.min_alert AND p.status='active'
            ORDER BY s.quantity ASC, p.name
            LIMIT 40
        ");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE); exit;
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
                p.name  AS platform_name,  p.slug  AS platform_slug,
                p.icon  AS platform_icon,  p.color AS platform_color,
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
        "SELECT pa.id, pa.name, pa.color, p.icon AS platform_icon,
                p.slug AS platform_slug, p.color AS platform_color, p.id AS platform_id
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
                    p.name  AS platform_name, p.slug  AS platform_slug,
                    p.icon  AS platform_icon, p.color AS platform_color,
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

// ── Platform FA icon helper ────────────────────────────────────────
$_PF_FA = [
    'facebook'  => 'fab fa-facebook-f',
    'instagram' => 'fab fa-instagram',
    'tiktok'    => 'fab fa-tiktok',
    'line'      => 'fab fa-line',
    'twitter'   => 'fab fa-x-twitter',
    'youtube'   => 'fab fa-youtube',
    'shopee'    => 'fas fa-shopping-bag',
    'lazada'    => 'fas fa-store',
];
function pfIcon(string $slug, string $color = '#fff', string $size = '0.72rem'): string {
    global $_PF_FA;
    $fa = $_PF_FA[strtolower($slug)] ?? 'fas fa-store';
    return '<i class="' . $fa . '" style="font-size:' . $size . ';color:' . $color . ';line-height:1;"></i>';
}
?>

<style>
.inbox-wrap { display:flex; height:calc(100vh - 165px); gap:0; overflow:hidden; border-radius:12px; border:1px solid var(--border-color); background:var(--card); }

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
.conv-avatar { width:38px; height:38px; border-radius:50%; background:linear-gradient(135deg,#FF85A2,#9B72CF); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:0.88rem; flex-shrink:0; box-shadow:0 2px 6px rgba(155,114,207,.22); }
.conv-body { flex:1; min-width:0; }
.conv-name { font-size:0.84rem; font-weight:500; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.conv-preview { font-size:0.74rem; color:#888; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:2px; }
.conv-meta { display:flex; justify-content:space-between; align-items:center; margin-top:2px; }
.conv-time { font-size:0.66rem; color:#aaa; }
.conv-badge { background:var(--pink); color:#fff; border-radius:50%; width:17px; height:17px; display:flex; align-items:center; justify-content:center; font-size:0.62rem; font-weight:700; }
.platform-dot { display:inline-block; width:8px; height:8px; border-radius:50%; margin-right:3px; }

/* Right panel */
.inbox-chat { flex:1; display:flex; flex-direction:column; background:#f8f8f8; min-width:0; }
.chat-head { background:#fff; padding:12px 16px; border-bottom:1px solid var(--border-color); display:flex; align-items:center; gap:12px; }
.chat-head-info { flex:1; min-width:0; }
.chat-head-name { font-weight:700; font-size:0.94rem; color:#2c2a30; letter-spacing:-0.01em; }
.chat-head-meta { font-size:0.73rem; color:#aaa; margin-top:1px; }
/* Large avatar in chat header */
.chat-head .conv-avatar { width:42px; height:42px; font-size:1rem; }
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
.qr-chips { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:10px; }
.qr-chip {
    background: #fff;
    border: 1.5px solid #f0d6de;
    border-radius: 20px;
    padding: 4px 13px;
    font-size: 0.73rem;
    font-weight: 500;
    cursor: pointer;
    transition: all .18s ease;
    white-space: nowrap;
    color: #c05a78;
    box-shadow: 0 1px 3px rgba(210,106,130,.08);
    letter-spacing: 0.01em;
}
.qr-chip:hover {
    background: linear-gradient(135deg, #FF85A2, #d4629e);
    color: #fff;
    border-color: transparent;
    box-shadow: 0 3px 10px rgba(210,106,130,.28);
    transform: translateY(-1px);
}
.qr-chip:active { transform: translateY(0); box-shadow: none; }
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

/* ── MOBILE (≤ 767px) ───────────────────────────────────────── */
@media(max-width:767px){

    /* ── inbox-wrap: full-bleed, dynamic height ─────────────── */
    #inboxApp { padding:8px 12px 0 !important; }
    #inboxApp .bk-page__head { margin-bottom:10px !important; flex-wrap:nowrap !important; overflow-x:auto; scrollbar-width:none; }
    #inboxApp .bk-page__head::-webkit-scrollbar { display:none; }
    #inboxApp .bk-page__title { font-size:0.95rem !important; white-space:nowrap; }

    .inbox-wrap {
        height: calc(var(--mob-h, 100vh) - 130px) !important;
        margin:0 !important;
        border-radius:0 !important;
        border-left:none !important;
        border-right:none !important;
        border-bottom:none !important;
        position:relative;
        overflow:hidden;
    }

    /* ── List panel: full-screen layer ─────────────────────── */
    .inbox-list {
        width:100% !important;
        min-width:100% !important;
        border-right:none !important;
        position:absolute !important;
        inset:0;
        z-index:2;
        background:#fff;
        transition:transform .25s ease;
    }

    /* ── Chat panel: off-screen right by default ────────────── */
    .inbox-chat {
        position:absolute !important;
        inset:0;
        z-index:3;
        transform:translateX(102%);
        transition:transform .25s ease;
        background:#f8f8f8;
    }

    /* ── mob-chat: slide list out, slide chat in ────────────── */
    .inbox-wrap.mob-chat .inbox-list { transform:translateX(-30%); pointer-events:none; }
    .inbox-wrap.mob-chat .inbox-chat { transform:translateX(0); }

    /* ── Tools panel: bottom sheet on mobile ───────────────── */
    #btnTools { display:inline-flex !important; }

    .inbox-tools {
        display:flex !important;
        position:fixed !important;
        bottom:0; left:0; right:0;
        width:100% !important;
        min-width:100% !important;
        max-height:72vh;
        height:72vh;
        border-left:none !important;
        border-top:1px solid var(--border-color) !important;
        border-radius:18px 18px 0 0 !important;
        box-shadow:0 -4px 24px rgba(0,0,0,.18);
        z-index:1050;
        transform:translateY(110%);
        transition:transform .28s cubic-bezier(.4,0,.2,1);
        overflow:hidden;
    }
    .inbox-tools.open { transform:translateY(0); }

    /* drag handle */
    .tools-head::before {
        content:'';
        display:block;
        width:36px; height:4px;
        background:#ddd;
        border-radius:2px;
        margin:0 auto 8px;
    }
    .tools-head { flex-direction:column; padding-top:10px !important; }

    /* backdrop */
    #toolsBackdrop {
        display:none;
        position:fixed;
        inset:0;
        background:rgba(0,0,0,.4);
        z-index:1049;
        backdrop-filter:blur(1px);
    }
    #toolsBackdrop.show { display:block; }

    /* ── Chat header: compact ───────────────────────────────── */
    .chat-head { padding:8px 10px !important; gap:6px !important; }
    .chat-head-meta .text-muted { display:none !important; } /* ซ่อน UID */
    .chat-head-meta { gap:4px !important; }

    /* ── QR chips: horizontal scroll ────────────────────────── */
    .qr-chips {
        flex-wrap:nowrap !important;
        overflow-x:auto;
        -webkit-overflow-scrolling:touch;
        padding-bottom:3px;
        scrollbar-width:none;
        mask-image:linear-gradient(to right,transparent,#000 10%,#000 90%,transparent);
    }
    .qr-chips::-webkit-scrollbar { display:none; }

    /* ── Account filter chips: horizontal scroll ────────────── */
    .inbox-list-head .d-flex.gap-1 {
        flex-wrap:nowrap !important;
        overflow-x:auto;
        -webkit-overflow-scrolling:touch;
        padding-bottom:3px;
        scrollbar-width:none;
    }
    .inbox-list-head .d-flex.gap-1::-webkit-scrollbar { display:none; }

    /* ── Reply box: tighter padding ─────────────────────────── */
    .chat-reply { padding:8px 10px !important; }

    /* ── Status tabs: smaller text ──────────────────────────── */
    .status-tab { font-size:0.72rem !important; padding:6px 2px !important; }
}

/* ── Tools Panel ────────────────────────────────────────────────── */
.inbox-tools { width:0; min-width:0; overflow:hidden; border-left:1px solid transparent; background:#faf7f9; display:flex; flex-direction:column; transition:width .22s ease,min-width .22s ease; flex-shrink:0; }
.inbox-tools.open { width:300px; min-width:280px; border-left-color:var(--border-color); }
.tools-head {
    padding: 14px 16px 12px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
    white-space: nowrap;
    background: #fff;
}
.tools-head span { font-weight: 700; font-size: 0.88rem; color: #2c2a30; letter-spacing: -0.01em; }
.tools-head button {
    background: #f5f0f3;
    border: none;
    cursor: pointer;
    color: #999;
    font-size: 0.78rem;
    padding: 5px 7px;
    border-radius: 7px;
    line-height: 1;
    transition: background .15s, color .15s;
}
.tools-head button:hover { background: #ffe0eb; color: #c05a78; }
.tools-tabs { display:flex; border-bottom:1px solid var(--border-color); flex-shrink:0; white-space:nowrap; background:#fff; }
.tools-tab {
    flex: 1;
    text-align: center;
    padding: 9px 4px;
    font-size: 0.73rem;
    font-weight: 500;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    color: #aaa;
    transition: color .15s, border-color .15s;
    user-select: none;
}
.tools-tab.active { color: #c05a78; border-bottom-color: #d4629e; font-weight: 600; }
.tools-tab.active { color:var(--pink); border-bottom-color:var(--pink); font-weight:700; }
.tools-pane { flex:1; overflow-y:auto; -webkit-overflow-scrolling:touch; display:flex; flex-direction:column; min-height:0; overflow-x:hidden; background:#faf7f9; }
.tools-search-box { padding:10px 12px; border-bottom:1px solid #f0ebee; position:sticky; top:0; background:#fff; z-index:2; flex-shrink:0; }
.tools-search-box input {
    width: 100%;
    border: 1.5px solid #efe9ee;
    border-radius: 10px;
    padding: 7px 11px;
    font-size: 0.79rem;
    font-family: inherit;
    background: #faf7f9;
    color: #2c2a30;
    transition: border-color .15s, box-shadow .15s;
}
.tools-search-box input:focus { outline:none; border-color:#e8869b; background:#fff; box-shadow:0 0 0 3px rgba(232,134,155,.12); }
.tools-hint { padding:28px 16px; text-align:center; color:#ccc; font-size:0.78rem; line-height:1.7; }
.product-card { padding:11px 14px; border-bottom:1px solid #f0ebee; cursor:default; transition:background .12s; background:#fff; margin:0 8px 6px; border-radius:10px; border:1px solid #f0ebee; box-shadow:0 1px 3px rgba(44,42,48,.04); }
.product-card:hover { background:#fdfafc; }
.pf-stock-badge { padding:2px 6px; border-radius:10px; font-size:0.62rem; font-weight:700; white-space:nowrap; }
.btn-insert { background:#f2f0f8; border:1px solid #ddd; border-radius:12px; padding:3px 9px; font-size:0.68rem; cursor:pointer; white-space:nowrap; transition:all .12s; color:#555; }
.btn-insert:hover { background:var(--pink); color:#fff; border-color:var(--pink); }
.order-card { padding:11px 14px; border-bottom:none; background:#fff; margin:0 8px 6px; border-radius:10px; border:1px solid #f0ebee; box-shadow:0 1px 3px rgba(44,42,48,.04); transition:box-shadow .15s; }
.order-card:hover { box-shadow:0 3px 10px rgba(44,42,48,.09); }
.ls-bar-wrap { background:#efe9ee; border-radius:4px; height:5px; flex:1; overflow:hidden; }
.ls-bar-fill { height:100%; border-radius:4px; transition:width .3s; }
#btnTools.active { background:linear-gradient(135deg,#FF85A2,#d4629e); color:#fff; border-color:transparent; }
/* Tools pane padding for cards */
#productResults, #orderResults, #lowStockResults { padding:8px 0 12px; display:flex; flex-direction:column; }
</style>

<div class="bk-page fade-in" style="padding-bottom:0" id="inboxApp">
    <!-- Header bar -->
    <div class="bk-page__head" style="margin-bottom:16px">
        <div>
            <h1 class="bk-page__title">Inbox ข้อความ</h1>
            <div class="bk-page__sub">รวมแชตจากทุกช่องทาง · ยังไม่ได้อ่าน <span id="globalNewBadge"><?= $stats['new_count'] ?? 0 ?></span> ข้อความ</div>
        </div>
        <div class="bk-actions">
            <span class="badge-status badge-active" id="globalOpenBadge">เปิด <?= $stats['open_count'] ?? 0 ?></span>
            <button class="bk-btn bk-btn--ghost bk-btn--sm" onclick="openQrManager()"><i class="fas fa-bolt" style="font-size:.8rem"></i> ข้อความสำเร็จรูป</button>
            <button class="bk-btn bk-btn--ghost bk-btn--sm" onclick="toggleTools()" id="btnTools" title="เครื่องมือ">
                <i class="fas fa-toolbox" style="font-size:.8rem"></i> เครื่องมือ
            </button>
        </div>
    </div>

    <div class="inbox-wrap mx-0 mb-0" id="inboxWrap" style="border-radius:var(--radius);border:1px solid var(--border)"
>

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
                       class="badge text-decoration-none d-inline-flex align-items-center gap-1"
                       style="background:<?= $isActiveAcc ? $base : '#eee' ?>;color:<?= $isActiveAcc ? '#fff' : '#555' ?>;font-weight:<?= $isActiveAcc?'700':'400' ?>;">
                        <?php if ($acc['platform_slug'] ?? ''): ?>
                        <?= pfIcon($acc['platform_slug'], $isActiveAcc ? '#fff' : ($acc['platform_color'] ?? '#888'), '0.72rem') ?>
                        <?php endif; ?>
                        <?= htmlspecialchars($acc['name']) ?>
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
                    <div class="conv-avatar" style="position:relative;<?= $conv['platform_color'] && !$conv['customer_avatar'] ? 'background:linear-gradient(135deg,'.$conv['platform_color'].','.adjustColor($conv['platform_color']).');' : '' ?>">
                        <?php if ($conv['customer_avatar']):
                            $slug = $conv['platform_slug'] ?? '';
                            $avatarSrc = in_array($slug, ['facebook','instagram'])
                                ? SITE_URL . '/api/avatar-proxy.php?conv_id=' . (int)$conv['id']
                                : $conv['customer_avatar'];
                        ?>
                            <img src="<?= htmlspecialchars($avatarSrc) ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                            <span style="display:none;width:100%;height:100%;align-items:center;justify-content:center;"><?= htmlspecialchars($initial) ?></span>
                        <?php else: ?>
                            <?= htmlspecialchars($initial) ?>
                        <?php endif; ?>
                        <?php if ($conv['platform_slug'] ?? ''): ?>
                        <span style="position:absolute;bottom:-3px;right:-3px;width:15px;height:15px;border-radius:50%;background:<?= $conv['platform_color'] ?? '#888' ?>;border:1.5px solid #fff;display:flex;align-items:center;justify-content:center;z-index:1;">
                            <?= pfIcon($conv['platform_slug'], '#fff', '0.46rem') ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="conv-body">
                        <div class="d-flex justify-content-between align-items-start gap-1">
                            <div class="conv-name">
                                <?php if ($conv['platform_slug'] ?? ''): ?>
                                <span style="display:inline-flex;align-items:center;justify-content:center;width:14px;height:14px;border-radius:50%;background:<?= $conv['platform_color'] ?? '#888' ?>;margin-right:3px;vertical-align:middle;flex-shrink:0;">
                                    <?= pfIcon($conv['platform_slug'], '#fff', '0.48rem') ?>
                                </span>
                                <?php elseif ($conv['platform_color']): ?>
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
                                <span style="background:<?= $conv['account_color'] ?? $conv['platform_color'] ?? '#888' ?>;color:#fff;padding:1px 5px;border-radius:8px;font-size:0.62rem;display:inline-flex;align-items:center;gap:3px;">
                                    <?php if ($conv['platform_slug'] ?? ''): ?><?= pfIcon($conv['platform_slug'], '#fff', '0.58rem') ?><?php endif; ?>
                                    <?= htmlspecialchars($conv['account_name']) ?>
                                </span>
                                <?php elseif ($conv['platform_slug'] ?? ''): ?>
                                <span><?= pfIcon($conv['platform_slug'], $conv['platform_color'] ?? '#bbb', '0.78rem') ?></span>
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
                <button class="btn btn-sm btn-outline-secondary d-md-none me-1" onclick="backToList()" style="padding:5px 10px;font-size:0.8rem;">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <div class="conv-avatar" style="width:38px;height:38px;font-size:0.9rem;overflow:hidden;
                    <?= $activeConv['customer_avatar'] ? '' : ($activeConv['platform_color']?'background:linear-gradient(135deg,'.$activeConv['platform_color'].','.adjustColor($activeConv['platform_color']).');':'') ?>">
                    <?php if ($activeConv['customer_avatar']):
                        $activeSlug = $activeConv['platform_slug'] ?? '';
                        $activeAvatarSrc = in_array($activeSlug, ['facebook','instagram'])
                            ? SITE_URL . '/api/avatar-proxy.php?conv_id=' . (int)$activeConvId
                            : $activeConv['customer_avatar'];
                    ?>
                        <img src="<?= htmlspecialchars($activeAvatarSrc) ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                        <span style="display:none;width:100%;height:100%;align-items:center;justify-content:center;"><?= mb_substr($activeConv['customer_name'] ?: '?', 0, 1) ?></span>
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
                        <?php if ($activeConv['platform_name'] ?? ''): ?>
                        <span style="background:<?= $activeConv['platform_color'] ?>;color:#fff;padding:3px 9px;border-radius:10px;font-size:0.68rem;display:inline-flex;align-items:center;gap:5px;">
                            <?php if ($activeConv['platform_slug'] ?? ''): ?><?= pfIcon($activeConv['platform_slug'], '#fff', '0.76rem') ?><?php endif; ?>
                            <?= htmlspecialchars($activeConv['platform_name']) ?>
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
                <div class="qr-chips" id="qrChips">
                    <?php foreach ($quickReplies as $qr): ?>
                    <span class="qr-chip"
                          data-content="<?= htmlspecialchars($qr['content'], ENT_QUOTES) ?>"
                          onclick="useQR(this.dataset.content)"><?= htmlspecialchars($qr['label']) ?></span>
                    <?php endforeach; ?>
                </div>
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
        </div><!-- /inbox-chat -->

        <!-- ══════════════════════════════════════════════════════ -->
        <!-- RIGHT: Tools Panel                                     -->
        <!-- ══════════════════════════════════════════════════════ -->
        <div class="inbox-tools" id="toolsPanel">

            <!-- Header -->
            <div class="tools-head">
                <span style="font-weight:700;font-size:0.85rem;">🛠️ เครื่องมือแอดมิน</span>
                <button onclick="toggleTools()" style="background:none;border:none;cursor:pointer;color:#aaa;font-size:0.88rem;padding:2px 4px;line-height:1;" title="ปิด">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Tabs -->
            <div class="tools-tabs">
                <div class="tools-tab active" onclick="switchToolsTab('products')" id="ttab-products">🔍 สินค้า</div>
                <div class="tools-tab" onclick="switchToolsTab('orders')"   id="ttab-orders">📋 ออเดอร์</div>
                <div class="tools-tab" onclick="switchToolsTab('lowstock')" id="ttab-lowstock">⚠️ ต่ำ</div>
            </div>

            <!-- Tab: Products -->
            <div class="tools-pane" id="tpane-products">
                <div class="tools-search-box">
                    <input type="text" id="productSearch"
                           placeholder="🔍 ชื่อสินค้า / SKU..."
                           oninput="debounceProductSearch(this.value)"
                           autocomplete="off">
                </div>
                <div id="productResults">
                    <div class="tools-hint">พิมพ์ชื่อสินค้าเพื่อค้นหา<br><span style="font-size:0.72rem;color:#ccc;">หรือเว้นว่างเพื่อดูทั้งหมด</span></div>
                </div>
            </div>

            <!-- Tab: Orders -->
            <div class="tools-pane" id="tpane-orders" style="display:none;">
                <div class="tools-search-box">
                    <input type="text" id="orderSearch"
                           placeholder="🔍 ชื่อลูกค้า / เลขออเดอร์..."
                           oninput="debounceOrderSearch(this.value)"
                           autocomplete="off">
                </div>
                <div id="orderResults">
                    <div class="tools-hint">พิมพ์ชื่อลูกค้าหรือเลขออเดอร์</div>
                </div>
            </div>

            <!-- Tab: Low Stock -->
            <div class="tools-pane" id="tpane-lowstock" style="display:none;">
                <div id="lowStockResults">
                    <div class="tools-hint">คลิกแท็บ ⚠️ เพื่อโหลด</div>
                </div>
            </div>

        </div><!-- /inbox-tools -->

    </div><!-- /inbox-wrap -->
</div><!-- /inboxApp -->

<!-- Mobile tools backdrop -->
<div id="toolsBackdrop" onclick="toggleTools()"></div>

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
const CUSTOMER_AVATAR  = <?php
    $slug = $activeConv['platform_slug'] ?? '';
    if (!empty($activeConv['customer_avatar']) && in_array($slug, ['facebook','instagram'])) {
        echo json_encode(SITE_URL . '/api/avatar-proxy.php?conv_id=' . (int)$activeConvId);
    } else {
        echo json_encode($activeConv['customer_avatar'] ?? '');
    }
?>;
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
        ? `<div class="msg-avatar"><img src="${esc(CUSTOMER_AVATAR)}" alt="${esc(CUSTOMER_INITIAL)}" onerror="this.style.display='none';this.parentElement.innerHTML='${esc(CUSTOMER_INITIAL)}';"></div>`
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
        // หา #qrChips — ถ้าไม่มี (ตอน load หน้ายังไม่มี QR เลย) ให้สร้างใหม่
        let chips = document.getElementById('qrChips');
        if (!chips) {
            const replyBox = document.querySelector('.chat-reply');
            if (!replyBox) return;
            chips = document.createElement('div');
            chips.id        = 'qrChips';
            chips.className = 'qr-chips';
            replyBox.insertBefore(chips, replyBox.firstChild);
        }
        if (!qrs.length) { chips.innerHTML = ''; return; }
        // ใช้ data-content แทน JSON ใน onclick → ปลอดภัยกว่า ไม่มีปัญหา quote
        chips.innerHTML = qrs.map(q => {
            const safe = String(q.content || '')
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;');
            return `<span class="qr-chip" data-content="${safe}" onclick="useQR(this.dataset.content)">${esc(q.label)}</span>`;
        }).join('');
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

/* ── Mobile panel switching ──────────────────────────────────────── */
function backToList() {
    document.getElementById('inboxWrap').classList.remove('mob-chat');
}
function toggleList() { backToList(); } // legacy alias

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
startGlobalNotify();

// ── Mobile: dynamic viewport height (fixes iOS Safari URL-bar resize) ──
(function() {
    function setMobH() {
        document.documentElement.style.setProperty('--mob-h', window.innerHeight + 'px');
    }
    setMobH();
    window.addEventListener('resize', setMobH);
})();

// ── Mobile: portal — move toolsPanel to <body> so position:fixed escapes overflow:hidden ──
// (Desktop keeps it inside flex container for sidebar behaviour)
if (window.innerWidth <= 767) {
    const panel   = document.getElementById('toolsPanel');
    const backdrop = document.getElementById('toolsBackdrop');
    if (panel)    document.body.appendChild(panel);
    if (backdrop) document.body.appendChild(backdrop);
}

// ── Mobile: auto-show chat panel if conv already selected ──────────────
if (CONV_ID && window.innerWidth <= 767) {
    document.getElementById('inboxWrap').classList.add('mob-chat');
}

// ── Mobile: open conv → switch to chat panel without full page reload ──
(function() {
    const origOpenConv = window.openConv;
    window.openConv = function(id) {
        if (window.innerWidth <= 767) {
            // On mobile: navigate (page reload) — mob-chat auto-applied on load
            window.location.href = `?status=<?= $statusFilter ?>&conv=${id}<?= $platformFilter?'&platform='.$platformFilter:'' ?><?= $accountFilter?'&account='.$accountFilter:'' ?>`;
        } else {
            origOpenConv(id);
        }
    };
})();

/* ══════════════════════════════════════════════════════════════════
   TOOLS PANEL
   ══════════════════════════════════════════════════════════════════ */

const ACTIVE_CUSTOMER = <?= json_encode($activeConv['customer_name'] ?? '') ?>;
const UPLOAD_URL      = SITE_URL + '/assets/uploads/';
let _toolsOpen        = false;
let _curTab           = 'products';
let _prodTimer        = null;
let _orderTimer       = null;
let _lowLoaded        = false;

/* ── Toggle panel ─────────────────────────────────────────────── */
function toggleTools() {
    const panel    = document.getElementById('toolsPanel');
    const btn      = document.getElementById('btnTools');
    const backdrop = document.getElementById('toolsBackdrop');
    const isMobile = window.innerWidth <= 767;

    _toolsOpen = !_toolsOpen;
    panel.classList.toggle('open', _toolsOpen);
    btn.classList.toggle('active', _toolsOpen);

    // backdrop (mobile bottom-sheet only)
    if (backdrop) backdrop.classList.toggle('show', _toolsOpen && isMobile);

    // prevent body scroll when bottom sheet open on mobile
    if (isMobile) document.body.style.overflow = _toolsOpen ? 'hidden' : '';

    if (_toolsOpen) {
        if (_curTab === 'products') {
            const inp = document.getElementById('productSearch');
            searchProducts(inp ? inp.value : '');
            setTimeout(() => inp && inp.focus(), isMobile ? 350 : 220);
        } else if (_curTab === 'orders' && ACTIVE_CUSTOMER) {
            const inp = document.getElementById('orderSearch');
            if (inp && !inp.value) { inp.value = ACTIVE_CUSTOMER; searchOrders(ACTIVE_CUSTOMER); }
        } else if (_curTab === 'lowstock' && !_lowLoaded) {
            loadLowStock();
        }
    }
}

/* ── Switch tabs ──────────────────────────────────────────────── */
function switchToolsTab(tab) {
    _curTab = tab;
    ['products','orders','lowstock'].forEach(t => {
        document.getElementById('ttab-' + t).classList.toggle('active', t === tab);
        document.getElementById('tpane-' + t).style.display = t === tab ? 'flex' : 'none';
    });
    if (tab === 'products') {
        const inp = document.getElementById('productSearch');
        searchProducts(inp ? inp.value : '');
        setTimeout(() => inp && inp.focus(), 100);
    }
    if (tab === 'orders') {
        const inp = document.getElementById('orderSearch');
        if (ACTIVE_CUSTOMER && inp && !inp.value) {
            inp.value = ACTIVE_CUSTOMER;
            searchOrders(ACTIVE_CUSTOMER);
        } else if (inp && inp.value) {
            searchOrders(inp.value);
        }
        setTimeout(() => inp && inp.focus(), 100);
    }
    if (tab === 'lowstock' && !_lowLoaded) loadLowStock();
}

/* ── Products ─────────────────────────────────────────────────── */
function debounceProductSearch(q) {
    clearTimeout(_prodTimer);
    _prodTimer = setTimeout(() => searchProducts(q), 320);
}

function searchProducts(q) {
    const el = document.getElementById('productResults');
    el.innerHTML = '<div class="tools-hint" style="padding:10px;"><span class="typing-dot"></span><span class="typing-dot"></span><span class="typing-dot"></span></div>';
    fetch('', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({ajax:'products_search', q})
    })
    .then(r => r.json())
    .then(list => {
        if (!list.length) { el.innerHTML = '<div class="tools-hint">ไม่พบสินค้า</div>'; return; }
        el.innerHTML = list.map(renderProduct).join('');
    })
    .catch(() => { el.innerHTML = '<div class="tools-hint" style="color:#e74c3c;">โหลดไม่ได้</div>'; });
}

function renderProduct(p) {
    const sColors = { active:'#27ae60', inactive:'#95a5a6', out_of_stock:'#e74c3c' };
    const sLabels = { active:'มีสินค้า', inactive:'ปิด', out_of_stock:'หมด' };
    const sc = sColors[p.status] || '#888';
    const sl = sLabels[p.status] || p.status;

    const img = p.image_url
        ? `<img src="${esc(p.image_url)}" style="width:46px;height:46px;object-fit:cover;border-radius:8px;flex-shrink:0;border:1px solid #eee;">`
        : `<div style="width:46px;height:46px;border-radius:8px;background:#f5f0f8;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:1.3rem;">🧸</div>`;

    // Stock badges per size
    const badges = (p.stocks || []).map(s => {
        const qty   = parseInt(s.quantity);
        const alert = parseInt(s.min_alert);
        const bg    = qty === 0 ? '#f0f0f0' : qty <= alert ? '#fff3cd' : '#d4edda';
        const tc    = qty === 0 ? '#aaa'    : qty <= alert ? '#856404' : '#155724';
        return `<span class="pf-stock-badge" style="background:${bg};color:${tc};">${esc(s.size)}: ${qty}</span>`;
    }).join('');

    // Build insert text
    const insertText  = `${p.name} ราคา ฿${parseFloat(p.selling_price).toLocaleString('th-TH',{maximumFractionDigits:0})}`;
    const stockText   = (p.stocks||[]).filter(s=>s.quantity>0).map(s=>`${s.size}:${s.quantity}`).join(' ') || 'ไม่มีสต็อก';
    const insertStock = `${p.name}\nราคา ฿${parseFloat(p.selling_price).toLocaleString('th-TH',{maximumFractionDigits:0})}\nสต็อก: ${stockText}`;

    return `<div class="product-card">
        <div style="display:flex;gap:8px;align-items:flex-start;">
            ${img}
            <div style="flex:1;min-width:0;">
                <div style="font-size:0.8rem;font-weight:700;line-height:1.3;margin-bottom:3px;word-break:break-word;">${esc(p.name)}</div>
                <div style="display:flex;align-items:center;gap:5px;margin-bottom:5px;flex-wrap:wrap;">
                    <span style="font-size:0.85rem;color:var(--pink);font-weight:700;">฿${parseFloat(p.selling_price).toLocaleString('th-TH',{maximumFractionDigits:0})}</span>
                    <span style="font-size:0.65rem;color:${sc};background:${sc}1a;padding:1px 6px;border-radius:8px;font-weight:600;">${sl}</span>
                    ${p.sku ? `<span style="font-size:0.62rem;color:#bbb;">SKU: ${esc(p.sku)}</span>` : ''}
                </div>
                ${badges ? `<div style="display:flex;flex-wrap:wrap;gap:3px;margin-bottom:6px;">${badges}</div>` : '<div style="font-size:0.65rem;color:#ccc;margin-bottom:6px;">ไม่มีข้อมูลสต็อก</div>'}
            </div>
        </div>
        <div style="display:flex;gap:5px;margin-top:4px;flex-wrap:wrap;">
            <button class="btn-insert" onclick="insertToReply(${JSON.stringify(insertText)})">
                <i class="fas fa-tag" style="font-size:.6rem;"></i> ส่งราคา
            </button>
            <button class="btn-insert" onclick="insertToReply(${JSON.stringify(insertStock)})">
                <i class="fas fa-boxes" style="font-size:.6rem;"></i> ส่งสต็อก
            </button>
        </div>
    </div>`;
}

/* ── Orders ───────────────────────────────────────────────────── */
function debounceOrderSearch(q) {
    clearTimeout(_orderTimer);
    _orderTimer = setTimeout(() => searchOrders(q), 350);
}

function searchOrders(q) {
    const el = document.getElementById('orderResults');
    if (!q.trim()) { el.innerHTML = '<div class="tools-hint">พิมพ์ชื่อลูกค้าหรือเลขออเดอร์</div>'; return; }
    el.innerHTML = '<div class="tools-hint" style="padding:10px;"><span class="typing-dot"></span><span class="typing-dot"></span><span class="typing-dot"></span></div>';
    fetch('', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({ajax:'customer_orders', q})
    })
    .then(r => r.json())
    .then(list => {
        if (!list.length) { el.innerHTML = '<div class="tools-hint">ไม่พบออเดอร์</div>'; return; }
        el.innerHTML = list.map(renderOrder).join('');
    })
    .catch(() => { el.innerHTML = '<div class="tools-hint" style="color:#e74c3c;">โหลดไม่ได้</div>'; });
}

function renderOrder(o) {
    const statusMap = {
        AWAITING_PAYMENT:'รอชำระ', AWAITING_SHIPMENT:'รอส่ง', SHIPPED:'จัดส่งแล้ว',
        DELIVERED:'ได้รับแล้ว', CANCELLED:'ยกเลิก', pending:'รอดำเนินการ',
        processing:'กำลังดำเนินการ', shipped:'จัดส่งแล้ว', delivered:'ได้รับแล้ว',
        cancelled:'ยกเลิก', completed:'เสร็จสิ้น',
    };
    const statusClr = { cancelled:'#e74c3c', delivered:'#27ae60', completed:'#27ae60', DELIVERED:'#27ae60', CANCELLED:'#e74c3c' };
    const sc = statusClr[o.order_status] || '#e67e22';
    const sl = statusMap[o.order_status] || o.order_status;
    const dateStr = (o.order_date||'').substring(0,10);

    return `<div class="order-card">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:4px;">
            <div>
                <div style="font-size:0.78rem;font-weight:700;">
                    <a href="${SITE_URL}/pages/orders.php?q=${encodeURIComponent(o.order_number)}" target="_blank"
                       style="color:inherit;text-decoration:none;">#${esc(o.order_number)}</a>
                </div>
                <div style="font-size:0.74rem;color:#555;margin-top:1px;">${esc(o.customer_name||'')}</div>
                <div style="font-size:0.68rem;color:#aaa;margin-top:1px;">${dateStr} · ${o.item_count} รายการ</div>
            </div>
            <div style="text-align:right;flex-shrink:0;">
                <div style="font-size:0.8rem;font-weight:700;color:var(--pink);">฿${parseFloat(o.total_amount||0).toLocaleString('th-TH',{maximumFractionDigits:0})}</div>
                <span style="font-size:0.62rem;color:${sc};background:${sc}1a;padding:1px 6px;border-radius:8px;font-weight:600;">${sl}</span>
            </div>
        </div>
        ${o.platform_name ? `<div style="margin-top:4px;"><span style="background:${o.platform_color||'#888'};color:#fff;padding:1px 6px;border-radius:8px;font-size:0.62rem;">${esc(o.platform_name)}</span></div>` : ''}
    </div>`;
}

/* ── Low Stock ────────────────────────────────────────────────── */
function loadLowStock() {
    const el = document.getElementById('lowStockResults');
    el.innerHTML = '<div class="tools-hint" style="padding:10px;"><span class="typing-dot"></span><span class="typing-dot"></span><span class="typing-dot"></span></div>';
    fetch('', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({ajax:'low_stock'})
    })
    .then(r => r.json())
    .then(list => {
        _lowLoaded = true;
        if (!list.length) { el.innerHTML = '<div class="tools-hint">✅ สต็อกปกติทุกรายการ</div>'; return; }
        // group by product
        const byProd = {};
        list.forEach(r => { (byProd[r.id] = byProd[r.id] || { name:r.name, sku:r.sku, items:[] }).items.push(r); });
        el.innerHTML = Object.values(byProd).map(prod => {
            const rows = prod.items.map(s => {
                const pct = s.min_alert > 0 ? Math.min(100, Math.round(s.quantity/s.min_alert*100)) : 0;
                const bc  = s.quantity === 0 ? '#e74c3c' : '#e67e22';
                return `<div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;">
                    <span style="font-size:0.7rem;min-width:60px;color:#555;">${esc(s.size)}</span>
                    <div class="ls-bar-wrap"><div class="ls-bar-fill" style="width:${pct}%;background:${bc};"></div></div>
                    <span style="font-size:0.7rem;font-weight:700;min-width:28px;text-align:right;color:${bc};">${s.quantity}</span>
                    <span style="font-size:0.62rem;color:#aaa;">/${s.min_alert}</span>
                </div>`;
            }).join('');
            return `<div style="padding:10px 12px;border-bottom:1px solid #f5f5f5;">
                <div style="font-size:0.78rem;font-weight:700;margin-bottom:6px;">${esc(prod.name)}${prod.sku?`<span style="color:#bbb;font-weight:400;margin-left:5px;font-size:0.65rem;">SKU:${esc(prod.sku)}</span>`:''}
                </div>${rows}
            </div>`;
        }).join('');
    })
    .catch(() => { el.innerHTML = '<div class="tools-hint" style="color:#e74c3c;">โหลดไม่ได้</div>'; });
}

/* ── Insert text into reply box ───────────────────────────────── */
function insertToReply(text) {
    const ta = document.getElementById('replyText');
    if (!ta) { alert('เลือกบทสนทนาก่อนเพื่อส่งข้อความ'); return; }
    ta.value = ta.value ? ta.value + '\n' + text : text;
    ta.focus();
    autoResize(ta);
    // scroll chat to bottom
    const msgs = document.getElementById('chatMessages');
    if (msgs) msgs.scrollTop = msgs.scrollHeight;
}
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
