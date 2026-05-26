<?php
// =====================================================
// BabyKawaii — Local / Production Config
// =====================================================
// คัดลอกไฟล์นี้เป็น: config/local.php
// แล้วแก้ค่าด้านล่างให้ตรงกับ server ของคุณ
// ⚠️ ห้าม commit ไฟล์ local.php ขึ้น Git
// =====================================================

// ─── Database ─────────────────────────────────────────
define('DB_HOST',    'localhost');          // host ของ MySQL (ปกติ localhost)
define('DB_NAME',    'babykawaii_db');      // ชื่อ database
define('DB_USER',    'babykawaii_user');    // ชื่อ MySQL user (ไม่ควรใช้ root บน production)
define('DB_PASS',    'YOUR_DB_PASSWORD');   // password ที่ตั้งไว้
define('DB_CHARSET', 'utf8mb4');

// ─── Site URL ─────────────────────────────────────────
// ไม่มี trailing slash, ไม่มี /index.php
define('SITE_NAME', 'BabyKawaii Shop');
define('SITE_URL',  'https://yourdomain.com/admin');   // แก้เป็น domain จริง

// ─── Upload paths (ปกติไม่ต้องแก้) ──────────────────
define('UPLOAD_PATH', __DIR__ . '/../assets/uploads/');
define('UPLOAD_URL',  SITE_URL . '/assets/uploads/');
