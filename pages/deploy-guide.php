<?php
$pageTitle = 'คู่มือ Deploy ขึ้น VPS';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid fade-in" style="max-width:960px;">
    <div class="page-header">
        <div>
            <h1 class="page-title">🚀 คู่มือ Deploy ขึ้น VPS จริง</h1>
            <p class="page-subtitle">ขั้นตอนครบตั้งแต่ซื้อ VPS จนถึงระบบทำงานสมบูรณ์</p>
        </div>
        <a href="<?= SITE_URL ?>/pages/integrations.php" class="btn btn-outline-pink btn-sm">
            <i class="fas fa-plug me-1"></i> ตั้งค่า Integration
        </a>
    </div>

    <!-- Progress overview -->
    <div class="row g-3 mb-4">
        <?php
        $steps = [
            ['icon'=>'fa-server',      'label'=>'เช่า VPS',          'color'=>'#9B72CF'],
            ['icon'=>'fa-globe',       'label'=>'ติดตั้ง Web Server', 'color'=>'#FF85A2'],
            ['icon'=>'fa-lock',        'label'=>'ตั้งค่า HTTPS',      'color'=>'#27ae60'],
            ['icon'=>'fa-database',    'label'=>'Upload Database',     'color'=>'#3498db'],
            ['icon'=>'fa-upload',      'label'=>'Upload Code',         'color'=>'#e67e22'],
            ['icon'=>'fa-robot',       'label'=>'ติดตั้ง n8n',        'color'=>'#FF85A2'],
            ['icon'=>'fa-comment-dots','label'=>'ตั้งค่า LINE',       'color'=>'#06C755'],
            ['icon'=>'fa-check-circle','label'=>'ทดสอบทั้งหมด',      'color'=>'#27ae60'],
        ];
        foreach ($steps as $i => $s):
        ?>
        <div class="col-6 col-md-3">
            <div class="card p-3 text-center h-100">
                <div style="width:38px;height:38px;border-radius:50%;background:<?= $s['color'] ?>;display:flex;align-items:center;justify-content:center;margin:0 auto 8px;">
                    <i class="fas <?= $s['icon'] ?>" style="color:#fff;font-size:0.9rem;"></i>
                </div>
                <div style="font-size:0.78rem;font-weight:600;color:#333;">Step <?= $i+1 ?></div>
                <div style="font-size:0.72rem;color:#888;"><?= $s['label'] ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Step 1 -->
    <div class="card mb-4">
        <div class="card-body">
            <h4 class="mb-3">🖥️ Step 1 — เช่า VPS</h4>
            <p class="text-muted mb-3">แนะนำ Provider สำหรับประเทศไทย:</p>
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <div class="card border p-3">
                        <div class="fw-bold">DigitalOcean</div>
                        <div class="text-muted small">$6/เดือน — Droplet 1GB RAM, 25GB SSD</div>
                        <div class="mt-2"><span class="badge bg-success">แนะนำ</span> พื้นฐาน</div>
                        <a href="https://digitalocean.com" target="_blank" class="btn btn-sm btn-outline-primary mt-2">ไปที่เว็บ</a>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border p-3">
                        <div class="fw-bold">Vultr</div>
                        <div class="text-muted small">$5/เดือน — 1 vCPU, 1GB RAM, 25GB SSD</div>
                        <div class="mt-2"><span class="badge bg-info text-dark">ราคาดี</span></div>
                        <a href="https://vultr.com" target="_blank" class="btn btn-sm btn-outline-primary mt-2">ไปที่เว็บ</a>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border p-3">
                        <div class="fw-bold">Contabo</div>
                        <div class="text-muted small">€4/เดือน — 4 vCPU, 4GB RAM, 100GB SSD</div>
                        <div class="mt-2"><span class="badge bg-warning text-dark">ถูกสุด</span></div>
                        <a href="https://contabo.com" target="_blank" class="btn btn-sm btn-outline-primary mt-2">ไปที่เว็บ</a>
                    </div>
                </div>
            </div>
            <div class="alert alert-info">
                <strong>OS ที่แนะนำ:</strong> Ubuntu 22.04 LTS (64-bit) — มีเอกสารและ community มากที่สุด
            </div>
            <p class="mb-0"><strong>Spec ขั้นต่ำ:</strong> 1 vCPU / 1 GB RAM / 20 GB SSD — เพียงพอสำหรับร้านขนาดเล็ก-กลาง</p>
        </div>
    </div>

    <!-- Step 2 -->
    <div class="card mb-4">
        <div class="card-body">
            <h4 class="mb-3">🌐 Step 2 — ติดตั้ง LAMP Stack (Apache + PHP + MySQL)</h4>
            <p class="text-muted mb-3">SSH เข้า VPS แล้วรันคำสั่งต่อไปนี้:</p>

            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <code class="text-muted small">2.1 อัปเดต package</code>
                    <button class="btn btn-xs btn-outline-secondary btn-sm" onclick="copyCode(this)">📋 copy</button>
                </div>
                <pre class="bg-dark text-light p-3 rounded" style="font-size:0.82rem;overflow-x:auto;">sudo apt update && sudo apt upgrade -y</pre>
            </div>

            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <code class="text-muted small">2.2 ติดตั้ง Apache + PHP 8.2 + extensions</code>
                    <button class="btn btn-xs btn-outline-secondary btn-sm" onclick="copyCode(this)">📋 copy</button>
                </div>
                <pre class="bg-dark text-light p-3 rounded" style="font-size:0.82rem;overflow-x:auto;">sudo apt install -y apache2 php8.2 php8.2-mysql php8.2-gd php8.2-curl php8.2-mbstring php8.2-xml php8.2-zip libapache2-mod-php8.2
sudo a2enmod rewrite
sudo systemctl restart apache2</pre>
            </div>

            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <code class="text-muted small">2.3 ติดตั้ง MariaDB</code>
                    <button class="btn btn-xs btn-outline-secondary btn-sm" onclick="copyCode(this)">📋 copy</button>
                </div>
                <pre class="bg-dark text-light p-3 rounded" style="font-size:0.82rem;overflow-x:auto;">sudo apt install -y mariadb-server
sudo mysql_secure_installation
# กด Y ทุกข้อ ตั้ง root password ด้วย</pre>
            </div>

            <div class="mb-0">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <code class="text-muted small">2.4 สร้าง database + user</code>
                    <button class="btn btn-xs btn-outline-secondary btn-sm" onclick="copyCode(this)">📋 copy</button>
                </div>
                <pre class="bg-dark text-light p-3 rounded" style="font-size:0.82rem;overflow-x:auto;">sudo mysql -u root -p
CREATE DATABASE babykawaii_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'bkuser'@'localhost' IDENTIFIED BY 'YOUR_STRONG_PASSWORD';
GRANT ALL PRIVILEGES ON babykawaii_db.* TO 'bkuser'@'localhost';
FLUSH PRIVILEGES;
EXIT;</pre>
            </div>
        </div>
    </div>

    <!-- Step 3 -->
    <div class="card mb-4">
        <div class="card-body">
            <h4 class="mb-3">🔒 Step 3 — ตั้งค่า Domain + HTTPS (Let's Encrypt)</h4>

            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <code class="text-muted small">3.1 ชี้ DNS — ตั้ง A Record ที่ domain registrar</code>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead class="table-light"><tr><th>Type</th><th>Name</th><th>Value</th></tr></thead>
                        <tbody>
                            <tr><td>A</td><td>@</td><td>IP ของ VPS เช่น 123.45.67.89</td></tr>
                            <tr><td>A</td><td>www</td><td>IP ของ VPS เช่น 123.45.67.89</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <code class="text-muted small">3.2 ติดตั้ง Certbot + ขอ SSL certificate</code>
                    <button class="btn btn-xs btn-outline-secondary btn-sm" onclick="copyCode(this)">📋 copy</button>
                </div>
                <pre class="bg-dark text-light p-3 rounded" style="font-size:0.82rem;overflow-x:auto;">sudo apt install -y certbot python3-certbot-apache
sudo certbot --apache -d yourdomain.com -d www.yourdomain.com
# ทำตามขั้นตอน ใส่ email และกด Y ตลอด</pre>
            </div>

            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <code class="text-muted small">3.3 ตั้งค่า Apache VirtualHost</code>
                    <button class="btn btn-xs btn-outline-secondary btn-sm" onclick="copyCode(this)">📋 copy</button>
                </div>
                <pre class="bg-dark text-light p-3 rounded" style="font-size:0.82rem;overflow-x:auto;">sudo nano /etc/apache2/sites-available/babykawaii.conf</pre>
                <pre class="bg-dark text-light p-3 rounded mt-1" style="font-size:0.82rem;overflow-x:auto;">&lt;VirtualHost *:80&gt;
    ServerName yourdomain.com
    ServerAlias www.yourdomain.com
    Redirect permanent / https://yourdomain.com/
&lt;/VirtualHost&gt;

&lt;VirtualHost *:443&gt;
    ServerName yourdomain.com
    DocumentRoot /var/www/babykawaii

    SSLEngine on
    SSLCertificateFile    /etc/letsencrypt/live/yourdomain.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/yourdomain.com/privkey.pem

    &lt;Directory /var/www/babykawaii&gt;
        AllowOverride All
        Require all granted
    &lt;/Directory&gt;

    ErrorLog  ${APACHE_LOG_DIR}/babykawaii-error.log
    CustomLog ${APACHE_LOG_DIR}/babykawaii-access.log combined
&lt;/VirtualHost&gt;</pre>
            </div>

            <div class="mb-0">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <code class="text-muted small">3.4 เปิดใช้งาน site</code>
                    <button class="btn btn-xs btn-outline-secondary btn-sm" onclick="copyCode(this)">📋 copy</button>
                </div>
                <pre class="bg-dark text-light p-3 rounded" style="font-size:0.82rem;overflow-x:auto;">sudo a2ensite babykawaii.conf
sudo a2dissite 000-default.conf
sudo systemctl reload apache2</pre>
            </div>
        </div>
    </div>

    <!-- Step 4 -->
    <div class="card mb-4">
        <div class="card-body">
            <h4 class="mb-3">📤 Step 4 — Upload Code + Database</h4>

            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <code class="text-muted small">4.1 Export database จาก XAMPP local</code>
                    <button class="btn btn-xs btn-outline-secondary btn-sm" onclick="copyCode(this)">📋 copy</button>
                </div>
                <pre class="bg-dark text-light p-3 rounded" style="font-size:0.82rem;overflow-x:auto;"># บน Windows — รันใน Command Prompt
C:\xampp\mysql\bin\mysqldump.exe -u root babykawaii_db > babykawaii_backup.sql</pre>
            </div>

            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <code class="text-muted small">4.2 Upload ผ่าน SCP / FileZilla</code>
                    <button class="btn btn-xs btn-outline-secondary btn-sm" onclick="copyCode(this)">📋 copy</button>
                </div>
                <pre class="bg-dark text-light p-3 rounded" style="font-size:0.82rem;overflow-x:auto;"># SCP (บน Linux/Mac terminal / Git Bash บน Windows)
scp -r /c/xampp/htdocs/BabyKawaii-Admin/ root@YOUR_VPS_IP:/var/www/babykawaii/
scp babykawaii_backup.sql root@YOUR_VPS_IP:/tmp/

# หรือใช้ FileZilla (GUI) ก็ได้ง่ายกว่า</pre>
            </div>

            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <code class="text-muted small">4.3 Import database บน VPS</code>
                    <button class="btn btn-xs btn-outline-secondary btn-sm" onclick="copyCode(this)">📋 copy</button>
                </div>
                <pre class="bg-dark text-light p-3 rounded" style="font-size:0.82rem;overflow-x:auto;">mysql -u bkuser -p babykawaii_db < /tmp/babykawaii_backup.sql</pre>
            </div>

            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <code class="text-muted small">4.4 แก้ config/database.php ให้ชี้ production</code>
                    <button class="btn btn-xs btn-outline-secondary btn-sm" onclick="copyCode(this)">📋 copy</button>
                </div>
                <pre class="bg-dark text-light p-3 rounded" style="font-size:0.82rem;overflow-x:auto;">// บน VPS — แก้ไฟล์ /var/www/babykawaii/config/database.php
define('DB_HOST', 'localhost');
define('DB_USER', 'bkuser');
define('DB_PASS', 'YOUR_STRONG_PASSWORD');
define('DB_NAME', 'babykawaii_db');
define('SITE_URL', 'https://yourdomain.com');</pre>
            </div>

            <div class="mb-0">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <code class="text-muted small">4.5 ตั้งค่า permissions</code>
                    <button class="btn btn-xs btn-outline-secondary btn-sm" onclick="copyCode(this)">📋 copy</button>
                </div>
                <pre class="bg-dark text-light p-3 rounded" style="font-size:0.82rem;overflow-x:auto;">sudo chown -R www-data:www-data /var/www/babykawaii/
sudo find /var/www/babykawaii/ -type d -exec chmod 755 {} \;
sudo find /var/www/babykawaii/ -type f -exec chmod 644 {} \;
# โฟลเดอร์ uploads ต้องเขียนได้
sudo chmod -R 775 /var/www/babykawaii/assets/uploads/</pre>
            </div>
        </div>
    </div>

    <!-- Step 5 — n8n -->
    <div class="card mb-4">
        <div class="card-body">
            <h4 class="mb-3">🤖 Step 5 — ติดตั้ง n8n (Automation)</h4>

            <div class="alert alert-warning mb-3">
                <strong>⚠️ ต้องการ RAM อย่างน้อย 1 GB</strong> — ถ้า VPS มีแค่ 512 MB แนะนำให้ใช้ n8n Cloud แทน (มี Free tier)
            </div>

            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <code class="text-muted small">5.1 ติดตั้ง Node.js + n8n</code>
                    <button class="btn btn-xs btn-outline-secondary btn-sm" onclick="copyCode(this)">📋 copy</button>
                </div>
                <pre class="bg-dark text-light p-3 rounded" style="font-size:0.82rem;overflow-x:auto;">curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
sudo npm install -g n8n
sudo npm install -g pm2</pre>
            </div>

            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <code class="text-muted small">5.2 รัน n8n ด้วย pm2 (auto-restart)</code>
                    <button class="btn btn-xs btn-outline-secondary btn-sm" onclick="copyCode(this)">📋 copy</button>
                </div>
                <pre class="bg-dark text-light p-3 rounded" style="font-size:0.82rem;overflow-x:auto;">N8N_PORT=5678 pm2 start n8n --name n8n
pm2 save
pm2 startup    # ทำให้ start ตอน reboot ด้วย
# ทำตาม command ที่ pm2 แนะนำ</pre>
            </div>

            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <code class="text-muted small">5.3 Reverse proxy n8n ผ่าน Apache</code>
                    <button class="btn btn-xs btn-outline-secondary btn-sm" onclick="copyCode(this)">📋 copy</button>
                </div>
                <pre class="bg-dark text-light p-3 rounded" style="font-size:0.82rem;overflow-x:auto;">sudo a2enmod proxy proxy_http proxy_wstunnel
# เพิ่ม VirtualHost สำหรับ n8n.yourdomain.com (ทำ SSL แยก)
# หรือใช้ path /n8n/ ใน Apache config ก็ได้</pre>
            </div>

            <div class="mb-0">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <code class="text-muted small">5.4 ตั้งค่า n8n URL ใน BabyKawaii Admin</code>
                </div>
                <div class="alert alert-light mb-0">
                    ไปที่ <strong>เชื่อมต่อระบบ</strong> → ใส่ URL n8n เช่น <code>https://n8n.yourdomain.com</code>
                    → กด <strong>ทดสอบ n8n</strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Step 6 — LINE Webhook -->
    <div class="card mb-4">
        <div class="card-body">
            <h4 class="mb-3">💬 Step 6 — ตั้งค่า LINE Webhook</h4>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <div class="card border p-3 h-100">
                        <div class="fw-bold mb-2">📢 LINE Notify</div>
                        <ol class="mb-0 small text-muted" style="padding-left:1.2rem;">
                            <li>เข้า <a href="https://notify-bot.line.me" target="_blank">notify-bot.line.me</a></li>
                            <li>Login ด้วย LINE account</li>
                            <li>คลิก "ออกโทเค็นที่นี่"</li>
                            <li>ตั้งชื่อและเลือก chat/group</li>
                            <li>คัดลอก Token ไปใส่ใน <strong>เชื่อมต่อระบบ</strong></li>
                        </ol>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border p-3 h-100">
                        <div class="fw-bold mb-2">🤖 LINE Messaging API (Chatbot)</div>
                        <ol class="mb-0 small text-muted" style="padding-left:1.2rem;">
                            <li>เข้า <a href="https://developers.line.biz" target="_blank">developers.line.biz</a></li>
                            <li>สร้าง Provider → Create Channel → Messaging API</li>
                            <li>คัดลอก Channel Access Token + Channel Secret</li>
                            <li>ตั้ง Webhook URL: <code>https://yourdomain.com/api/line-webhook.php</code></li>
                            <li>เปิด "Use webhook" และกด Verify</li>
                        </ol>
                    </div>
                </div>
            </div>

            <div class="alert alert-info mb-0">
                <strong>💡 LINE Webhook API:</strong> ระบบมี <code>api/line-webhook.php</code> พร้อมใช้งาน
                รองรับ order status inquiry และ stock check ผ่าน LINE chat
            </div>
        </div>
    </div>

    <!-- Step 7 — Security -->
    <div class="card mb-4">
        <div class="card-body">
            <h4 class="mb-3">🔐 Step 7 — ตั้งค่า Security</h4>

            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <code class="text-muted small">7.1 Firewall</code>
                    <button class="btn btn-xs btn-outline-secondary btn-sm" onclick="copyCode(this)">📋 copy</button>
                </div>
                <pre class="bg-dark text-light p-3 rounded" style="font-size:0.82rem;overflow-x:auto;">sudo ufw allow ssh
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable</pre>
            </div>

            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <code class="text-muted small">7.2 ซ่อน config.php จาก web access</code>
                    <button class="btn btn-xs btn-outline-secondary btn-sm" onclick="copyCode(this)">📋 copy</button>
                </div>
                <pre class="bg-dark text-light p-3 rounded" style="font-size:0.82rem;overflow-x:auto;"># เพิ่มใน /var/www/babykawaii/config/.htaccess
Deny from all</pre>
            </div>

            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <code class="text-muted small">7.3 Generate API Key ใหม่สำหรับ production</code>
                </div>
                <div class="alert alert-warning mb-0">
                    ไปที่ <strong>เชื่อมต่อระบบ</strong> → กด <strong>สร้าง API Key ใหม่</strong>
                    อย่าใช้ key เดียวกับ local development
                </div>
            </div>

            <div class="mb-0">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <code class="text-muted small">7.4 Auto-renew SSL</code>
                    <button class="btn btn-xs btn-outline-secondary btn-sm" onclick="copyCode(this)">📋 copy</button>
                </div>
                <pre class="bg-dark text-light p-3 rounded" style="font-size:0.82rem;overflow-x:auto;">sudo certbot renew --dry-run   # ทดสอบการ renew
# Certbot จะตั้ง cron job auto ให้แล้วอัตโนมัติ</pre>
            </div>
        </div>
    </div>

    <!-- Step 8 — Final Checklist -->
    <div class="card mb-4">
        <div class="card-body">
            <h4 class="mb-3">✅ Step 8 — Final Checklist ก่อน Go Live</h4>
            <div id="checklist">
                <?php
                $checks = [
                    ['เว็บเปิดได้ที่ https://yourdomain.com', 'ทดสอบผ่าน browser'],
                    ['Login เข้าระบบได้', 'ใช้ password ที่ตั้งไว้'],
                    ['เพิ่มสินค้าและ stock ทดสอบ', 'ดูว่าบันทึกได้ปกติ'],
                    ['สร้างออเดอร์ทดสอบ', 'เช็คว่าลด stock ด้วย'],
                    ['พิมพ์ใบส่งของทดสอบ', 'ดูที่ pages/order-print.php'],
                    ['Export CSV ทดสอบ', 'เช็ค encoding ภาษาไทย'],
                    ['LINE Notify ส่งได้', 'ทดสอบในหน้า เชื่อมต่อระบบ'],
                    ['n8n Webhook รับ event ได้', 'ดู n8n execution log'],
                    ['สร้าง admin user สำรอง', 'อย่าใช้ superadmin ตลอด'],
                    ['ตั้ง shipping base rate', 'ในหน้า ตั้งค่าร้าน'],
                    ['ใส่ข้อมูลร้าน', 'ชื่อร้าน, เบอร์โทร, LINE, Facebook'],
                    ['ทดสอบ LINE Chatbot', 'ส่งข้อความ "สถานะ BK..." เช็คคำตอบ'],
                ];
                foreach ($checks as $i => [$label, $hint]):
                ?>
                <div class="d-flex align-items-center gap-3 py-2" style="border-bottom:1px solid #f0f0f0;">
                    <input type="checkbox" id="chk<?= $i ?>" class="form-check-input" style="width:1.1em;height:1.1em;cursor:pointer;" onchange="markDone(this, <?= $i ?>)">
                    <label for="chk<?= $i ?>" class="mb-0" style="cursor:pointer;flex:1;" id="lbl<?= $i ?>">
                        <?= $label ?>
                        <span class="text-muted ms-2" style="font-size:0.78rem;"><?= $hint ?></span>
                    </label>
                    <span id="done<?= $i ?>" style="display:none;color:#27ae60;font-size:0.85rem;">✓ Done</span>
                </div>
                <?php endforeach; ?>
            </div>
            <div id="allDoneMsg" class="alert alert-success mt-3 d-none">
                🎉 <strong>ยินดีด้วย!</strong> ทำ checklist ครบทุกข้อแล้ว — ระบบพร้อม Go Live!
            </div>
        </div>
    </div>

    <!-- Quick Reference -->
    <div class="card mb-5">
        <div class="card-body">
            <h4 class="mb-3">📋 Quick Reference — คำสั่งที่ใช้บ่อย</h4>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="mb-3">
                        <div class="fw-bold small mb-1">🔄 Restart services</div>
                        <pre class="bg-dark text-light p-2 rounded" style="font-size:0.78rem;">sudo systemctl restart apache2
sudo systemctl restart mariadb
pm2 restart n8n</pre>
                    </div>
                    <div class="mb-3">
                        <div class="fw-bold small mb-1">📊 ดู log</div>
                        <pre class="bg-dark text-light p-2 rounded" style="font-size:0.78rem;">sudo tail -f /var/log/apache2/babykawaii-error.log
pm2 logs n8n</pre>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <div class="fw-bold small mb-1">💾 Backup database</div>
                        <pre class="bg-dark text-light p-2 rounded" style="font-size:0.78rem;">mysqldump -u bkuser -p babykawaii_db \
  > ~/backup_$(date +%Y%m%d).sql</pre>
                    </div>
                    <div class="mb-3">
                        <div class="fw-bold small mb-1">🔐 Renew SSL</div>
                        <pre class="bg-dark text-light p-2 rounded" style="font-size:0.78rem;">sudo certbot renew
sudo systemctl reload apache2</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyCode(btn) {
    const pre = btn.closest('div').nextElementSibling || btn.parentElement.nextElementSibling;
    const code = pre ? pre.textContent : '';
    navigator.clipboard.writeText(code.trim()).then(() => {
        const orig = btn.textContent;
        btn.textContent = '✅ copied!';
        setTimeout(() => btn.textContent = orig, 2000);
    });
}

// Checklist persistence with localStorage
const CHECKS = <?= count($checks) ?>;
function loadChecks() {
    for (let i = 0; i < CHECKS; i++) {
        const done = localStorage.getItem('deploy_check_' + i) === '1';
        if (done) {
            document.getElementById('chk' + i).checked = true;
            markDone(document.getElementById('chk' + i), i, false);
        }
    }
}
function markDone(cb, i, save = true) {
    document.getElementById('lbl' + i).style.textDecoration = cb.checked ? 'line-through' : '';
    document.getElementById('lbl' + i).style.opacity = cb.checked ? '0.5' : '1';
    document.getElementById('done' + i).style.display = cb.checked ? '' : 'none';
    if (save) localStorage.setItem('deploy_check_' + i, cb.checked ? '1' : '0');
    // Show congrats if all done
    let allDone = true;
    for (let j = 0; j < CHECKS; j++) {
        if (!document.getElementById('chk' + j).checked) { allDone = false; break; }
    }
    document.getElementById('allDoneMsg').classList.toggle('d-none', !allDone);
}
loadChecks();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
