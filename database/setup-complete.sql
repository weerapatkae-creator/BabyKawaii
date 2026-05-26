-- =====================================================
-- BabyKawaii Shop — Complete Database Setup
-- รวม setup.sql + migrations ทั้งหมดไว้ที่เดียว
-- ใช้สำหรับ fresh install บน server ใหม่
-- =====================================================
-- วิธีใช้:
--   mysql -u YOUR_USER -p YOUR_DB_NAME < setup-complete.sql
-- =====================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─── Tables ──────────────────────────────────────────

CREATE TABLE IF NOT EXISTS admin_users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(50)  NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    full_name  VARCHAR(100),
    email      VARCHAR(100),
    role       ENUM('superadmin','admin','staff') DEFAULT 'admin',
    is_active  TINYINT(1)   DEFAULT 1,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    slug        VARCHAR(100) NOT NULL UNIQUE,
    icon        VARCHAR(10)  DEFAULT '👶',
    description TEXT,
    sort_order  INT          DEFAULT 0,
    is_active   TINYINT(1)   DEFAULT 1,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    sku               VARCHAR(50)  UNIQUE,
    name              VARCHAR(200) NOT NULL,
    category_id       INT,
    description       TEXT,
    materials         VARCHAR(200),
    care_instructions TEXT,
    cost_price        DECIMAL(10,2) DEFAULT 0,
    selling_price     DECIMAL(10,2) NOT NULL DEFAULT 0,
    main_image        VARCHAR(255),
    images            JSON,
    tags              VARCHAR(255),
    status            ENUM('active','inactive','out_of_stock') DEFAULT 'active',
    is_featured       TINYINT(1)   DEFAULT 0,
    created_at        TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    size       ENUM('Premature','NB','0-3M','3-6M','6-9M','9-12M','12-18M','18-24M','Free Size') NOT NULL,
    color      VARCHAR(50)  DEFAULT 'ไม่ระบุ',
    quantity   INT          DEFAULT 0,
    min_alert  INT          DEFAULT 5,
    cost_price DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_product_size_color (product_id, size, color)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_movements (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    product_id     INT NOT NULL,
    size           VARCHAR(20),
    color          VARCHAR(50),
    movement_type  ENUM('in','out','adjust','return') NOT NULL,
    quantity       INT NOT NULL,
    reason         VARCHAR(200),
    reference_id   INT,
    reference_type VARCHAR(50),
    before_qty     INT,
    after_qty      INT,
    note           TEXT,
    created_by     INT,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS platforms (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    slug            VARCHAR(50)  UNIQUE,
    icon            VARCHAR(10)  DEFAULT '🛒',
    color           VARCHAR(7)   DEFAULT '#666666',
    page_url        VARCHAR(255),
    username        VARCHAR(100),
    followers       INT          DEFAULT 0,
    commission_rate DECIMAL(5,2) DEFAULT 0,
    notes           TEXT,
    is_active       TINYINT(1)   DEFAULT 1,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    order_number      VARCHAR(50)  UNIQUE,
    platform_id       INT,
    platform_order_id VARCHAR(100),
    customer_name     VARCHAR(100),
    customer_phone    VARCHAR(20),
    customer_address  TEXT,
    shipping_method   VARCHAR(50),
    tracking_number   VARCHAR(100),
    subtotal          DECIMAL(10,2) DEFAULT 0,
    shipping_cost     DECIMAL(10,2) DEFAULT 0,
    discount_amount   DECIMAL(10,2) DEFAULT 0,
    total_amount      DECIMAL(10,2) DEFAULT 0,
    payment_method    VARCHAR(50),
    payment_status    ENUM('pending','paid','refunded') DEFAULT 'pending',
    order_status      ENUM('pending','confirmed','packing','shipped','delivered','cancelled','returned') DEFAULT 'pending',
    notes             TEXT,
    order_date        DATETIME  DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (platform_id) REFERENCES platforms(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    order_id     INT NOT NULL,
    product_id   INT,
    product_name VARCHAR(200),
    size         VARCHAR(20),
    color        VARCHAR(50),
    quantity     INT          NOT NULL DEFAULT 1,
    unit_price   DECIMAL(10,2) NOT NULL,
    total_price  DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS media (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    filename       VARCHAR(255) NOT NULL,
    original_name  VARCHAR(255),
    file_type      ENUM('image','video') DEFAULT 'image',
    mime_type      VARCHAR(100),
    file_size      INT,
    file_path      VARCHAR(500),
    thumbnail_path VARCHAR(500),
    title          VARCHAR(200),
    description    TEXT,
    tags           VARCHAR(500),
    platform_ids   VARCHAR(100),
    product_id     INT,
    is_published   TINYINT(1) DEFAULT 0,
    created_at     TIMESTAMP  DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS promotions (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(200) NOT NULL,
    code          VARCHAR(50)  UNIQUE,
    type          ENUM('percent','fixed','free_shipping','bundle','flash_sale') NOT NULL,
    discount_value DECIMAL(10,2) DEFAULT 0,
    min_purchase  DECIMAL(10,2) DEFAULT 0,
    max_discount  DECIMAL(10,2) DEFAULT 0,
    usage_limit   INT  DEFAULT 0,
    usage_count   INT  DEFAULT 0,
    apply_to      ENUM('all','category','product') DEFAULT 'all',
    apply_ids     VARCHAR(500),
    platform_ids  VARCHAR(100),
    start_date    DATETIME,
    end_date      DATETIME,
    description   TEXT,
    banner_image  VARCHAR(255),
    status        ENUM('active','inactive','expired') DEFAULT 'active',
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_calendar (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    title              VARCHAR(200) NOT NULL,
    content_type       ENUM('post','story','reel','video','live','ad') DEFAULT 'post',
    post_type          ENUM('video','product','promo','image','announcement','reel','story','live','post') DEFAULT 'image',
    platform_ids       VARCHAR(100),
    product_id         INT,
    promotion_id       INT,
    caption            TEXT,
    captions_json      JSON         DEFAULT NULL,
    hashtags           TEXT,
    media_ids          VARCHAR(500) DEFAULT NULL,
    scheduled_at       DATETIME,
    published_at       DATETIME,
    status             ENUM('draft','scheduled','published','cancelled') DEFAULT 'draft',
    publish_status     ENUM('draft','scheduled','publishing','published','failed','cancelled') DEFAULT 'draft',
    publish_errors     TEXT         DEFAULT NULL,
    published_platforms VARCHAR(200) DEFAULT NULL,
    notes              TEXT,
    created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id)   REFERENCES products(id)   ON DELETE SET NULL,
    FOREIGN KEY (promotion_id) REFERENCES promotions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS media_library (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    filename       VARCHAR(300) NOT NULL,
    original_name  VARCHAR(300),
    file_type      ENUM('image','video','document','other') DEFAULT 'image',
    mime_type      VARCHAR(100),
    file_size      INT   DEFAULT 0,
    width          INT   DEFAULT 0,
    height         INT   DEFAULT 0,
    duration       INT   DEFAULT 0,
    url            VARCHAR(1000) NOT NULL,
    thumbnail_url  VARCHAR(1000) DEFAULT NULL,
    r2_key         VARCHAR(500)  DEFAULT NULL,
    tags           VARCHAR(500)  DEFAULT '',
    uploaded_by    INT   DEFAULT NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_file_type  (file_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    setting_key   VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_group VARCHAR(50)  DEFAULT 'general',
    updated_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── From migration-customers.sql ────────────────────
CREATE TABLE IF NOT EXISTS customers (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(200) NOT NULL,
    phone         VARCHAR(30)  DEFAULT '',
    email         VARCHAR(200) DEFAULT '',
    line_user_id  VARCHAR(100) DEFAULT '',
    address       TEXT,
    birthday      DATE         DEFAULT NULL,
    notes         TEXT,
    tags          VARCHAR(500) DEFAULT '',
    total_orders  INT          DEFAULT 0,
    total_spent   DECIMAL(12,2) DEFAULT 0.00,
    last_order_at DATETIME     DEFAULT NULL,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_phone (phone),
    INDEX idx_name  (name),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── From migration-inbox.sql ────────────────────────
CREATE TABLE IF NOT EXISTS conversations (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    platform_id      INT          DEFAULT NULL,
    customer_uid     VARCHAR(200) NOT NULL,
    customer_name    VARCHAR(200) DEFAULT '',
    customer_avatar  VARCHAR(500) DEFAULT '',
    status           ENUM('open','pending','closed') DEFAULT 'open',
    unread_count     INT          DEFAULT 0,
    last_message     TEXT,
    last_message_at  DATETIME     DEFAULT NULL,
    assigned_to      INT          DEFAULT NULL,
    notes            TEXT,
    created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_platform_customer (platform_id, customer_uid),
    INDEX idx_status          (status),
    INDEX idx_last_message_at (last_message_at),
    FOREIGN KEY (platform_id) REFERENCES platforms(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS messages (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id     INT NOT NULL,
    direction           ENUM('inbound','outbound') NOT NULL,
    content             TEXT NOT NULL,
    message_type        ENUM('text','image','video','sticker','button','system') DEFAULT 'text',
    media_url           VARCHAR(1000) DEFAULT NULL,
    sent_by             INT           DEFAULT NULL,
    sent_at             DATETIME      DEFAULT CURRENT_TIMESTAMP,
    platform_message_id VARCHAR(300)  DEFAULT NULL,
    is_read             TINYINT(1)    DEFAULT 0,
    INDEX idx_conversation (conversation_id),
    INDEX idx_sent_at      (sent_at),
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quick_replies (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    label      VARCHAR(100) NOT NULL,
    content    TEXT         NOT NULL,
    sort_order INT          DEFAULT 0,
    is_active  TINYINT(1)   DEFAULT 1,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── From migration-n8n.sql ───────────────────────────
CREATE TABLE IF NOT EXISTS line_chat_sessions (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    line_user_id VARCHAR(100) NOT NULL UNIQUE,
    display_name VARCHAR(200),
    state        VARCHAR(50)  DEFAULT 'idle',
    data         JSON,
    updated_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ─── Default Data ─────────────────────────────────────

-- Admin user (password: admin1234)
-- ⚠️ เปลี่ยนรหัสผ่านทันทีหลัง login ครั้งแรก
INSERT IGNORE INTO admin_users (username, password, full_name, role) VALUES
('admin', '$2y$10$l96f.ltbll2ra8DGrEb5kORvfmbkvMEp6HTK7T43PxnxZRUSNFZHG', 'ผู้ดูแลระบบ', 'superadmin');

-- Categories
INSERT IGNORE INTO categories (name, slug, icon, sort_order) VALUES
('บอดี้สูท',           'bodysuit',       '👶', 1),
('ชุดนอน',             'sleepwear',      '😴', 2),
('ชุดเซต',             'sets',           '🎀', 3),
('เสื้อ',              'tops',           '👕', 4),
('กางเกง/กระโปรง',    'bottoms',        '👗', 5),
('หมวก',               'hats',           '🧢', 6),
('ถุงมือ & ถุงเท้า',   'mittens-socks',  '🧤', 7),
('ผ้าห่ม',             'blankets',       '🛏️', 8),
('ชุดว่ายน้ำ',         'swimwear',       '🏊', 9),
('อื่นๆ',              'others',         '🎁', 10);

-- Platforms (ช่องทางรับออเดอร์)
-- LINE OA ไม่อยู่ที่นี่ — ใช้เป็นช่องแจ้งเตือน Admin เท่านั้น (ตั้งค่าใน Settings → เชื่อมต่อระบบ)
INSERT IGNORE INTO platforms (name, slug, icon, color, commission_rate) VALUES
('Facebook Page DM', 'facebook', '📘', '#1877F2', 0.00),
('TikTok Shop',      'tiktok',   '🎵', '#010101', 5.00),
('Instagram DM',     'instagram','📸', '#E4405F', 0.00),
('หน้าร้าน',         'walkin',   '🏪', '#FF6B6B', 0.00);

-- Settings
INSERT IGNORE INTO settings (setting_key, setting_value, setting_group) VALUES
('shop_name',              'BabyKawaii Shop',                        'general'),
('shop_tagline',           'เสื้อผ้าเด็กแรกเกิด คุณภาพดี น่ารักมาก 🌸','general'),
('shop_phone',             '088-888-8888',                           'general'),
('shop_line',              '@babykawaii',                            'general'),
('low_stock_alert',        '5',                                      'inventory'),
('currency',               'THB',                                    'general'),
('shipping_base',          '50',                                     'shipping'),
('shipping_free_threshold','500',                                    'shipping'),
-- Integration (กรอกค่าจริงใน Admin → เชื่อมต่อระบบ)
('api_key',                    '', 'integration'),
('line_channel_access_token',  '', 'integration'),
('line_channel_secret',        '', 'integration'),
('line_admin_user_id',         '', 'integration'),
('n8n_base_url',               '', 'integration'),
('webhook_secret',             '', 'integration');

-- Quick reply defaults
INSERT IGNORE INTO quick_replies (label, content, sort_order) VALUES
('ขอบคุณที่ติดต่อ', 'สวัสดีค่ะ ขอบคุณที่ติดต่อมานะคะ 🌸 มีอะไรให้ช่วยได้บ้างคะ?', 1),
('รอสักครู่',        'รอสักครู่นะคะ กำลังตรวจสอบข้อมูลให้เลยค่ะ 🔍',               2),
('เช็คออเดอร์',      'ขอเลขออเดอร์ด้วยนะคะ เพื่อตรวจสอบสถานะให้ค่ะ 📦',            3),
('จัดส่งแล้ว',       'ออเดอร์ของคุณจัดส่งแล้วนะคะ 🚚 สามารถติดตามพัสดุได้ที่เลขที่แจ้งไปค่ะ', 4),
('สินค้าหมด',       'ขออภัยนะคะ สินค้านี้หมดสต็อกชั่วคราว จะแจ้งให้ทราบทันทีที่มีของค่ะ 🙏', 5),
('ราคาและโปร',       'สามารถดูราคาและโปรโมชั่นล่าสุดได้ที่ [ลิงค์ร้าน] ค่ะ 🏷️',    6);

SELECT 'BabyKawaii setup-complete.sql finished ✅' AS status;
