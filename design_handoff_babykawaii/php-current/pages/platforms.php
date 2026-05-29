<?php
require_once __DIR__ . '/../config/database.php';
requireLogin();
$pdo = getDB();
// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_platform'])) {
    $id         = (int)($_POST['platform_id'] ?? 0);
    $name       = trim($_POST['name']);
    $icon       = trim($_POST['icon'] ?? '🛒');
    $color      = trim($_POST['color'] ?? '#666');
    $url        = trim($_POST['page_url'] ?? '');
    $username   = trim($_POST['username'] ?? '');
    $followers  = (int)($_POST['followers'] ?? 0);
    $commission = (float)($_POST['commission_rate'] ?? 0);
    $notes      = trim($_POST['notes'] ?? '');
    $isActive   = isset($_POST['is_active']) ? 1 : 0;

    if ($id) {
        $pdo->prepare("UPDATE platforms SET name=?,icon=?,color=?,page_url=?,username=?,followers=?,commission_rate=?,notes=?,is_active=? WHERE id=?")->execute([$name,$icon,$color,$url,$username,$followers,$commission,$notes,$isActive,$id]);
    } else {
        $slug = strtolower(preg_replace('/[^a-z0-9]/', '-', $name));
        $pdo->prepare("INSERT INTO platforms (name,slug,icon,color,page_url,username,followers,commission_rate,notes,is_active) VALUES (?,?,?,?,?,?,?,?,?,?)")->execute([$name,$slug,$icon,$color,$url,$username,$followers,$commission,$notes,$isActive]);
    }
    header('Location: ' . SITE_URL . '/pages/platforms.php?msg=saved');
    exit;
}

// แสดงเฉพาะแพลตฟอร์มที่มี platform_accounts เชื่อมต่ออยู่แล้ว
$platforms = $pdo->query("
    SELECT p.*, COUNT(pa.id) AS account_count
    FROM   platforms p
    INNER  JOIN platform_accounts pa ON pa.platform_id = p.id AND pa.is_active = 1
    WHERE  p.is_active = 1
    GROUP  BY p.id
    ORDER  BY p.id
")->fetchAll();

// ดึงรายชื่อ accounts ของแต่ละ platform
$accountsByPf = [];
$accStmt = $pdo->query("SELECT pa.*, p.id AS pid FROM platform_accounts pa JOIN platforms p ON p.id=pa.platform_id WHERE pa.is_active=1");
foreach ($accStmt->fetchAll() as $a) { $accountsByPf[$a['pid']][] = $a; }

// จำนวนลูกค้าที่เคยทักเข้ามา (unique) ต่อแพลตฟอร์ม
$pfConvStats = [];
$stmt = $pdo->query("
    SELECT platform_id,
           COUNT(DISTINCT customer_uid) AS customer_count,
           COUNT(*) AS total_convs
    FROM   conversations
    WHERE  platform_id IS NOT NULL
    GROUP  BY platform_id
");
foreach ($stmt->fetchAll() as $row) {
    $pfConvStats[$row['platform_id']] = $row;
}

// Brand icon mapping (slug → FontAwesome class)
$brandIcons = [
    'facebook'  => ['fab fa-facebook-f',   '#1877F2'],
    'instagram' => ['fab fa-instagram',     '#E4405F'],
    'tiktok'    => ['fab fa-tiktok',        '#111111'],
    'line'      => ['fab fa-line',          '#06C755'],
    'twitter'   => ['fab fa-x-twitter',    '#000000'],
    'youtube'   => ['fab fa-youtube',       '#FF0000'],
    'shopee'    => ['fas fa-shopping-bag',  '#EE4D2D'],
    'lazada'    => ['fas fa-store',         '#0F146D'],
];


$pageTitle = 'แพลตฟอร์มขาย';
require_once __DIR__ . '/../includes/header.php';

// Sales per platform this month
$pfSales = $pdo->query("SELECT platform_id, SUM(total_amount) as revenue, COUNT(*) as orders
    FROM orders WHERE MONTH(order_date)=MONTH(NOW()) AND YEAR(order_date)=YEAR(NOW()) AND order_status NOT IN ('cancelled')
    GROUP BY platform_id")->fetchAll(PDO::FETCH_KEY_PAIR);
// Re-fetch as assoc
$pfSalesStmt = $pdo->query("SELECT platform_id, SUM(total_amount) as revenue, COUNT(*) as orders
    FROM orders WHERE MONTH(order_date)=MONTH(NOW()) AND YEAR(order_date)=YEAR(NOW()) AND order_status NOT IN ('cancelled')
    GROUP BY platform_id");
$pfSalesData = [];
while ($row = $pfSalesStmt->fetch()) {
    $pfSalesData[$row['platform_id']] = $row;
}
?>

<div class="container-fluid fade-in">
    <div class="page-header">
        <div>
            <h1 class="page-title">🔗 แพลตฟอร์มขาย</h1>
            <p class="page-subtitle">จัดการแพลตฟอร์มและดูยอดขายแต่ละช่องทาง</p>
        </div>
        <button class="btn btn-primary" onclick="openPfModal()">
            <i class="fas fa-plus me-1"></i> เพิ่มแพลตฟอร์ม
        </button>
    </div>

    <?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success alert-auto">✅ บันทึกเรียบร้อย</div>
    <?php endif; ?>

    <!-- Platform Cards -->
    <div class="row g-4 mb-4">
        <?php foreach ($platforms as $pf):
            $sales   = $pfSalesData[$pf['id']]  ?? ['revenue' => 0, 'orders' => 0];
            $convSt  = $pfConvStats[$pf['id']]   ?? ['customer_count' => 0];
            $slug    = strtolower($pf['slug'] ?? preg_replace('/[^a-z0-9]/', '', strtolower($pf['name'])));
            [$faIcon, $iconColor] = $brandIcons[$slug] ?? ['fas fa-store', $pf['color']];
            $bg      = $pf['color'];
            $accounts = $accountsByPf[$pf['id']] ?? [];
        ?>
        <div class="col-md-6 col-lg-4">
            <div class="platform-card" style="background:linear-gradient(135deg,<?= htmlspecialchars($bg) ?>,<?= htmlspecialchars($bg) ?>bb);">

                <!-- Header row: icon + name + actions -->
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="d-flex align-items-center gap-3">
                        <!-- Brand icon circle -->
                        <div style="width:48px;height:48px;border-radius:50%;background:rgba(255,255,255,0.22);
                                    display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="<?= $faIcon ?>" style="font-size:1.4rem;color:#fff;"></i>
                        </div>
                        <div>
                            <div class="pf-name" style="font-size:1.1rem;"><?= htmlspecialchars($pf['name']) ?></div>
                            <?php if ($pf['username']): ?>
                            <div style="font-size:0.78rem;opacity:0.85;">@<?= htmlspecialchars($pf['username']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="d-flex gap-1">
                        <?php if ($pf['page_url']): ?>
                        <a href="<?= htmlspecialchars($pf['page_url']) ?>" target="_blank"
                           class="btn btn-sm btn-light" style="opacity:0.9;" title="เปิดเพจ">
                            <i class="fas fa-external-link-alt"></i>
                        </a>
                        <?php endif; ?>
                        <button class="btn btn-sm btn-light" style="opacity:0.9;"
                                onclick="openPfModal(<?= htmlspecialchars(json_encode($pf)) ?>)" title="แก้ไข">
                            <i class="fas fa-edit"></i>
                        </button>
                    </div>
                </div>

                <!-- Account chips -->
                <?php if ($accounts): ?>
                <div class="d-flex flex-wrap gap-1 mb-3">
                    <?php foreach ($accounts as $a): ?>
                    <span style="background:rgba(255,255,255,0.28);padding:3px 10px;border-radius:20px;
                                 font-size:0.73rem;font-weight:600;display:flex;align-items:center;gap:4px;">
                        <i class="<?= $faIcon ?>" style="font-size:0.7rem;"></i>
                        <?= htmlspecialchars($a['name']) ?>
                    </span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Stats row -->
                <div class="row g-2 text-center">
                    <div class="col-4">
                        <div class="pf-stat"><?= number_format($sales['revenue']) ?></div>
                        <div class="pf-label">฿ เดือนนี้</div>
                    </div>
                    <div class="col-4">
                        <div class="pf-stat"><?= number_format($sales['orders']) ?></div>
                        <div class="pf-label">ออเดอร์</div>
                    </div>
                    <div class="col-4">
                        <div class="pf-stat"><?= number_format($convSt['customer_count']) ?></div>
                        <div class="pf-label">💬 คนทักเข้ามา</div>
                    </div>
                </div>

                <?php if ($pf['commission_rate'] > 0): ?>
                <div class="mt-2" style="font-size:0.75rem;opacity:0.8;">
                    <i class="fas fa-percent"></i> ค่าคอม <?= $pf['commission_rate'] ?>%
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Platform Tips -->
    <div class="row g-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span class="card-title">💡 คู่มือขายแต่ละแพลตฟอร์ม</span>
                    <span class="text-muted" style="font-size:.75rem;">อ้างอิงจากข้อมูลตลาดไทย 2025-2026</span>
                </div>
                <div class="card-body">

                    <!-- Tabs -->
                    <ul class="nav nav-tabs mb-3" id="tipTabs">
                        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tip-fb">📘 Facebook</a></li>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tip-tt">🎵 TikTok Shop</a></li>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tip-ig">📸 Instagram</a></li>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tip-general">🎯 ภาพรวม</a></li>
                    </ul>

                    <div class="tab-content">

                        <!-- Facebook -->
                        <div class="tab-pane fade show active" id="tip-fb">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="p-3 rounded-3" style="background:#EBF5FF;border-left:4px solid #1877F2;">
                                        <div class="fw-bold mb-2" style="color:#1877F2;">📹 Content ที่ทำงาน</div>
                                        <ul class="list-unstyled mb-0" style="font-size:.83rem;">
                                            <li>🎬 Reels 15–30 วิ แสดงชุดบนเด็กจริง</li>
                                            <li>🛍️ Live ขายสดทุกอาทิตย์ Conversion ~7.4%</li>
                                            <li>📸 รูป Before/After ใส่ชุด + ราคาชัดเจน</li>
                                            <li>🎁 โพสต์ลุ้นรางวัล Tag เพื่อนเพิ่มคนรู้จัก</li>
                                            <li>💬 Behind-the-scenes แพ็คของ รีวิวจากแม่</li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 rounded-3" style="background:#FFF3E0;border-left:4px solid #FF9800;">
                                        <div class="fw-bold mb-2" style="color:#E65100;">⏰ เวลาโพสต์ที่ดีที่สุด</div>
                                        <ul class="list-unstyled mb-0" style="font-size:.83rem;">
                                            <li>🌅 <strong>07:00–09:00</strong> — แม่ตื่นพาลูกไปโรงเรียน</li>
                                            <li>☀️ <strong>11:30–13:00</strong> — พักกลางวัน เลื่อน feed</li>
                                            <li>🌙 <strong>19:00–22:00</strong> — ช่วง Prime time</li>
                                            <li>📅 วันศุกร์–อาทิตย์ Engagement สูงกว่า 30%</li>
                                            <li>💡 โพสต์อย่างน้อย <strong>4–5 ครั้ง/อาทิตย์</strong></li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 rounded-3" style="background:#F3E5F5;border-left:4px solid #9C27B0;">
                                        <div class="fw-bold mb-2" style="color:#6A1B9A;">🚀 เพิ่มยอดขาย</div>
                                        <ul class="list-unstyled mb-0" style="font-size:.83rem;">
                                            <li>💰 ยิง Ads เฉพาะกลุ่ม "แม่ลูกอ่อน อายุ 22–38"</li>
                                            <li>🤝 จ้าง Nano-influencer (1K–10K followers) ได้ Engagement 3–4× กว่าดารา</li>
                                            <li>💬 ตอบ Comment ภายใน <strong>1 ชั่วโมง</strong> ช่วยให้ Reach เพิ่ม</li>
                                            <li>🔁 Retarget คนที่เคยเข้าเพจแต่ยังไม่ซื้อ</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TikTok -->
                        <div class="tab-pane fade" id="tip-tt">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="p-3 rounded-3" style="background:#f0f0f0;border-left:4px solid #010101;">
                                        <div class="fw-bold mb-2">🎬 Content ที่ viral</div>
                                        <ul class="list-unstyled mb-0" style="font-size:.83rem;">
                                            <li>👶 เด็กใส่ชุด น่ารัก ตลก ใช้เพลง trending</li>
                                            <li>📦 Unboxing สินค้าใหม่ เปิดกล่องจริง</li>
                                            <li>🔄 Before/After เปลี่ยนชุดให้เด็ก</li>
                                            <li>😅 Day-in-life แม่กับลูก ของแท้ไม่ปรุงแต่ง</li>
                                            <li>📊 71% ของ user ซื้อสินค้าระหว่างดูวิดีโอ</li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 rounded-3" style="background:#FFF8E1;border-left:4px solid #FFC107;">
                                        <div class="fw-bold mb-2" style="color:#E65100;">🛒 TikTok Shop Tips</div>
                                        <ul class="list-unstyled mb-0" style="font-size:.83rem;">
                                            <li>🔗 ใส่ Product Link ในทุกวิดีโอ</li>
                                            <li>📡 Live ขายสด อย่างน้อย <strong>2× /อาทิตย์</strong></li>
                                            <li>👥 Thai shoppers ซื้อระหว่าง Live สูงกว่า 3×</li>
                                            <li>🤳 ใช้ TikTok Affiliate ให้ Influencer รีวิว</li>
                                            <li>🔍 ใส่ Keyword ในชื่อสินค้า เช่น "ชุดเด็ก NB"</li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 rounded-3" style="background:#E8F5E9;border-left:4px solid #4CAF50;">
                                        <div class="fw-bold mb-2" style="color:#1B5E20;">📈 Algorithm Tricks</div>
                                        <ul class="list-unstyled mb-0" style="font-size:.83rem;">
                                            <li>⏱️ 3 วินาทีแรกต้องดึงดูด — เปิดด้วยเด็กน่ารัก</li>
                                            <li>🎵 ใช้เพลง trending ใน 7 วันล่าสุดเท่านั้น</li>
                                            <li>📅 โพสต์ <strong>วันละ 1–2 คลิป</strong> ช่วง 18:00–21:00</li>
                                            <li>#️⃣ Hashtag: #ชุดเด็ก #เสื้อผ้าเด็ก #แม่และเด็ก</li>
                                            <li>📊 ค่าคอม TikTok 5% ต้องบวกเข้าราคาขาย</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Instagram -->
                        <div class="tab-pane fade" id="tip-ig">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="p-3 rounded-3" style="background:#FFF0F5;border-left:4px solid #E4405F;">
                                        <div class="fw-bold mb-2" style="color:#E4405F;">🖼️ Visual Strategy</div>
                                        <ul class="list-unstyled mb-0" style="font-size:.83rem;">
                                            <li>🎨 Feed สีพาสเทล โทนขาว/ครีม/ชมพูอ่อน</li>
                                            <li>👶 รูปเด็กใส่จริง ไม่ใช่แค่แบน flat-lay</li>
                                            <li>📐 Reel สั้น 7–15 วิ Carousel รูปหลายชุด</li>
                                            <li>🌅 Stories ทุกวัน: poll / question / countdown</li>
                                            <li>📍 Tag Location เช่น "Bangkok" เพิ่ม Reach</li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 rounded-3" style="background:#E3F2FD;border-left:4px solid #2196F3;">
                                        <div class="fw-bold mb-2" style="color:#0D47A1;">👥 กลุ่มเป้าหมาย IG</div>
                                        <ul class="list-unstyled mb-0" style="font-size:.83rem;">
                                            <li>👩 60.6% เป็นผู้หญิง อายุ 25–34 ปี</li>
                                            <li>💎 กลุ่มนี้ซื้อสินค้าพรีเมียมได้</li>
                                            <li>🤝 Collab กับ "แม่บล็อก" / momfluencer</li>
                                            <li>🌱 เน้น Organic / BPA-free ขายดีมาก</li>
                                            <li>⭐ Review จริงจากแม่ สร้าง Trust ได้สูง</li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 rounded-3" style="background:#F3E5F5;border-left:4px solid #9C27B0;">
                                        <div class="fw-bold mb-2" style="color:#4A148C;">#️⃣ Hashtag แนะนำ</div>
                                        <ul class="list-unstyled mb-0" style="font-size:.83rem;">
                                            <li>#ชุดเด็กแรกเกิด #babyfashionthailand</li>
                                            <li>#แม่และเด็ก #ของใช้เด็ก #เสื้อผ้าเด็ก</li>
                                            <li>#newbornthailand #babyclothes</li>
                                            <li>#ชุดทารก #ชุดเด็กน่ารัก</li>
                                            <li>💡 ใช้ 10–15 Hashtag ต่อโพสต์ ผสมใหญ่-เล็ก</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ภาพรวม -->
                        <div class="tab-pane fade" id="tip-general">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="p-3 rounded-3" style="background:#E8F5E9;border-left:4px solid #4CAF50;">
                                        <div class="fw-bold mb-2" style="color:#1B5E20;">📊 ข้อมูลตลาดไทย 2025–2026</div>
                                        <ul class="list-unstyled mb-0" style="font-size:.83rem;line-height:1.9;">
                                            <li>📱 คนไทยใช้ Social Media <strong>เฉลี่ย 5 ชม./วัน</strong></li>
                                            <li>🎵 TikTok มีผู้ใช้ <strong>44.4 ล้านคน</strong> (57.8% ของประชากร)</li>
                                            <li>🛒 TikTok = แพลตฟอร์ม Social Commerce อันดับ 1 ในไทย</li>
                                            <li>📡 Live Shopping มี Conversion Rate <strong>~7.4%</strong></li>
                                            <li>⭐ 35.6% อ่าน Review ก่อนซื้อทุกครั้ง</li>
                                            <li>🤳 Micro-influencer (1K–10K) ให้ Engagement <strong>3–4×</strong> กว่าดารา</li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 rounded-3" style="background:#FFF3E0;border-left:4px solid #FF9800;">
                                        <div class="fw-bold mb-2" style="color:#E65100;">🎯 กลยุทธ์รวมทุกแพลตฟอร์ม</div>
                                        <ul class="list-unstyled mb-0" style="font-size:.83rem;line-height:1.9;">
                                            <li>1️⃣ <strong>TikTok</strong> — สร้าง Awareness ด้วย video viral</li>
                                            <li>2️⃣ <strong>Facebook</strong> — ปิดการขาย DM + Live ขายสด</li>
                                            <li>3️⃣ <strong>Instagram</strong> — สร้าง Brand Image พรีเมียม</li>
                                            <li>4️⃣ <strong>LINE OA</strong> — แจ้งเตือน Admin รับออเดอร์</li>
                                            <li>📝 Content แบบ "ของแท้ ไม่ปรุงแต่ง" ได้ผลดีกว่าโฆษณา</li>
                                            <li>♻️ ถ่ายวิดีโอ 1 ครั้ง → ตัดเป็น TikTok + Reel + Stories</li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="p-3 rounded-3" style="background:#FDE8E8;border-left:4px solid #E53935;font-size:.82rem;">
                                        ⚠️ <strong>สิ่งที่ห้ามทำ:</strong> ซื้อ Follower / Like ปลอม · โพสต์ไม่สม่ำเสมอ · ไม่ตอบ Comment · ใช้รูป flat-lay อย่างเดียวโดยไม่มีเด็กจริง · ตั้งราคาถูกเกินไปจนไม่น่าเชื่อถือ
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div><!-- end tab-content -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Platform Modal -->
<div class="modal fade" id="pfModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST">
            <input type="hidden" name="save_platform" value="1">
            <input type="hidden" name="platform_id" id="pfId" value="0">
            <input type="hidden" name="icon" id="pfIcon" value="🛒">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="pfModalTitle">➕ เพิ่มแพลตฟอร์ม</h5>
                    <button type="button" class="btn-close" onclick="closePfModal()"></button>
                </div>
                <div class="modal-body">

                    <!-- Quick-select platform presets -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">เลือกแพลตฟอร์ม</label>
                        <div class="d-flex flex-wrap gap-2">
                            <?php
                            $presets = [
                                ['Facebook',  'facebook',  '#1877F2', 'fab fa-facebook-f'],
                                ['Instagram', 'instagram', '#E4405F', 'fab fa-instagram'],
                                ['TikTok',    'tiktok',    '#111111', 'fab fa-tiktok'],
                                ['LINE OA',   'line',      '#06C755', 'fab fa-line'],
                                ['Shopee',    'shopee',    '#EE4D2D', 'fas fa-shopping-bag'],
                                ['Lazada',    'lazada',    '#0F146D', 'fas fa-store'],
                                ['YouTube',   'youtube',   '#FF0000', 'fab fa-youtube'],
                            ];
                            foreach ($presets as [$label, $slug, $color, $fa]):
                            ?>
                            <button type="button" class="btn btn-sm pf-preset-btn"
                                    style="background:<?= $color ?>;color:#fff;border:2px solid transparent;border-radius:10px;padding:6px 12px;"
                                    onclick="selectPreset('<?= $label ?>','<?= $slug ?>','<?= $color ?>','<?= $fa ?>')">
                                <i class="<?= $fa ?> me-1"></i><?= $label ?>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="row g-3">
                        <!-- Icon preview + Name + Color -->
                        <div class="col-12">
                            <label class="form-label fw-semibold">ชื่อแพลตฟอร์ม</label>
                            <div class="input-group">
                                <!-- Live icon preview -->
                                <span class="input-group-text p-0" style="width:46px;justify-content:center;">
                                    <div id="pfIconPreview"
                                         style="width:38px;height:38px;border-radius:8px;background:#666;
                                                display:flex;align-items:center;justify-content:center;">
                                        <i id="pfIconPreviewI" class="fas fa-store" style="color:#fff;font-size:1.1rem;"></i>
                                    </div>
                                </span>
                                <input type="text" name="name" id="pfName" class="form-control"
                                       placeholder="เช่น Facebook เพจร้าน" required
                                       oninput="autoDetectIcon(this.value)">
                                <span class="input-group-text">สี</span>
                                <input type="color" name="color" id="pfColor"
                                       class="form-control form-control-color" style="width:50px;"
                                       value="#666666" oninput="updatePreviewBg(this.value)">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">URL เพจ</label>
                            <input type="url" name="page_url" id="pfUrl" class="form-control" placeholder="https://...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">ชื่อผู้ใช้ / @username</label>
                            <input type="text" name="username" id="pfUsername" class="form-control" placeholder="@username">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">จำนวนผู้ติดตาม</label>
                            <input type="number" name="followers" id="pfFollowers" class="form-control" value="0" min="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">ค่าคอมมิชชั่น (%)</label>
                            <input type="number" name="commission_rate" id="pfCommission" class="form-control" value="0" step="0.01" min="0">
                        </div>
                        <div class="col-12">
                            <label class="form-label">หมายเหตุ</label>
                            <textarea name="notes" id="pfNotes" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input type="checkbox" name="is_active" id="pfActive" class="form-check-input" value="1" checked>
                                <label class="form-check-label" for="pfActive">เปิดใช้งาน</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">✅ บันทึก</button>
                    <button type="button" class="btn btn-outline-secondary" onclick="closePfModal()">ยกเลิก</button>
                </div>
            </div>
        </form>
    </div>
</div>
<script>
const pfModalEl = document.getElementById('pfModal');

// Brand icon map (client-side mirror of PHP $brandIcons)
const BRAND_ICONS = {
    facebook:  ['fab fa-facebook-f',  '#1877F2'],
    instagram: ['fab fa-instagram',   '#E4405F'],
    tiktok:    ['fab fa-tiktok',      '#111111'],
    line:      ['fab fa-line',        '#06C755'],
    shopee:    ['fas fa-shopping-bag','#EE4D2D'],
    lazada:    ['fas fa-store',       '#0F146D'],
    youtube:   ['fab fa-youtube',     '#FF0000'],
    twitter:   ['fab fa-x-twitter',   '#000000'],
};

function slugify(str) {
    return str.toLowerCase().replace(/[^a-z0-9]/g, '');
}

function autoDetectIcon(name) {
    const slug = slugify(name);
    const found = Object.entries(BRAND_ICONS).find(([k]) => slug.includes(k));
    if (found) {
        const [, [fa, color]] = found;
        setIconPreview(fa, color);
        document.getElementById('pfColor').value = color;
        document.getElementById('pfIcon').value = fa;
    }
}

function setIconPreview(fa, color) {
    const box = document.getElementById('pfIconPreview');
    const ico = document.getElementById('pfIconPreviewI');
    if (box) box.style.background = color;
    if (ico) { ico.className = fa + ' '; ico.style.color = '#fff'; }
}

function updatePreviewBg(color) {
    const box = document.getElementById('pfIconPreview');
    if (box) box.style.background = color;
}

function selectPreset(name, slug, color, fa) {
    document.getElementById('pfName').value = name;
    document.getElementById('pfColor').value = color;
    document.getElementById('pfIcon').value = fa;
    setIconPreview(fa, color);
    // highlight selected preset btn
    document.querySelectorAll('.pf-preset-btn').forEach(b => b.style.outline = 'none');
    event.target.closest('.pf-preset-btn').style.outline = '3px solid #fff';
    event.target.closest('.pf-preset-btn').style.outlineOffset = '2px';
}

function closePfModal() {
    try { bootstrap.Modal.getInstance(pfModalEl)?.hide(); } catch(e) {}
    setTimeout(() => {
        pfModalEl.classList.remove('show');
        pfModalEl.style.display = 'none';
        pfModalEl.setAttribute('aria-hidden', 'true');
        pfModalEl.removeAttribute('aria-modal');
        pfModalEl.removeAttribute('role');
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
        document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
    }, 320);
}

function openPfModal(pf) {
    if (pf) {
        document.getElementById('pfModalTitle').textContent = '✏️ แก้ไขแพลตฟอร์ม';
        document.getElementById('pfId').value         = pf.id;
        document.getElementById('pfName').value       = pf.name;
        document.getElementById('pfColor').value      = pf.color || '#666666';
        document.getElementById('pfUrl').value        = pf.page_url || '';
        document.getElementById('pfUsername').value   = pf.username || '';
        document.getElementById('pfFollowers').value  = pf.followers || 0;
        document.getElementById('pfCommission').value = pf.commission_rate || 0;
        document.getElementById('pfNotes').value      = pf.notes || '';
        document.getElementById('pfActive').checked   = pf.is_active == 1;
        // Detect icon from name
        const slug = slugify(pf.name);
        const found = Object.entries(BRAND_ICONS).find(([k]) => slug.includes(k));
        if (found) {
            const [, [fa, color]] = found;
            setIconPreview(fa, pf.color || color);
            document.getElementById('pfIcon').value = fa;
        } else {
            setIconPreview('fas fa-store', pf.color || '#666');
            document.getElementById('pfIcon').value = pf.icon || '🛒';
        }
        updatePreviewBg(pf.color || '#666');
    } else {
        document.getElementById('pfModalTitle').textContent = '➕ เพิ่มแพลตฟอร์ม';
        document.getElementById('pfId').value  = '0';
        document.getElementById('pfName').value = '';
        setIconPreview('fas fa-store', '#666');
        document.getElementById('pfColor').value = '#666666';
    }
    bootstrap.Modal.getOrCreateInstance(pfModalEl).show();
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
