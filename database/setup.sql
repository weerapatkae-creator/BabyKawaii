-- =====================================================
-- BabyKawaii Shop - Admin System Database
-- สร้างโดย: BabyKawaii Admin Panel
-- =====================================================

CREATE DATABASE IF NOT EXISTS babykawaii_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE babykawaii_db;

-- =====================================================
-- ตาราง: admin_users (ผู้ดูแลระบบ)
-- =====================================================
CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    email VARCHAR(100),
    role ENUM('superadmin','admin','staff') DEFAULT 'admin',
    is_active TINYINT(1) DEFAULT 1,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- ตาราง: categories (หมวดหมู่สินค้า)
-- =====================================================
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    icon VARCHAR(10) DEFAULT '👶',
    description TEXT,
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- ตาราง: products (สินค้า)
-- =====================================================
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(50) UNIQUE,
    name VARCHAR(200) NOT NULL,
    category_id INT,
    description TEXT,
    materials VARCHAR(200),
    care_instructions TEXT,
    cost_price DECIMAL(10,2) DEFAULT 0,
    selling_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    main_image VARCHAR(255),
    images JSON,
    tags VARCHAR(255),
    status ENUM('active','inactive','out_of_stock') DEFAULT 'active',
    is_featured TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================
-- ตาราง: stock (สต็อกสินค้า)
-- =====================================================
CREATE TABLE IF NOT EXISTS stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    size ENUM('Premature','NB','0-3M','3-6M','6-9M','9-12M','12-18M','18-24M','Free Size') NOT NULL,
    color VARCHAR(50) DEFAULT 'ไม่ระบุ',
    quantity INT DEFAULT 0,
    min_alert INT DEFAULT 5,
    cost_price DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_product_size_color (product_id, size, color)
) ENGINE=InnoDB;

-- =====================================================
-- ตาราง: stock_movements (ประวัติการเคลื่อนไหวสต็อก)
-- =====================================================
CREATE TABLE IF NOT EXISTS stock_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    size VARCHAR(20),
    color VARCHAR(50),
    movement_type ENUM('in','out','adjust','return') NOT NULL,
    quantity INT NOT NULL,
    reason VARCHAR(200),
    reference_id INT,
    reference_type VARCHAR(50),
    before_qty INT,
    after_qty INT,
    note TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- ตาราง: platforms (แพลตฟอร์มขาย)
-- =====================================================
CREATE TABLE IF NOT EXISTS platforms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(50) UNIQUE,
    icon VARCHAR(10) DEFAULT '🛒',
    color VARCHAR(7) DEFAULT '#666666',
    page_url VARCHAR(255),
    username VARCHAR(100),
    followers INT DEFAULT 0,
    commission_rate DECIMAL(5,2) DEFAULT 0,
    notes TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- ตาราง: orders (คำสั่งซื้อ)
-- =====================================================
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(50) UNIQUE,
    platform_id INT,
    platform_order_id VARCHAR(100),
    customer_name VARCHAR(100),
    customer_phone VARCHAR(20),
    customer_address TEXT,
    shipping_method VARCHAR(50),
    tracking_number VARCHAR(100),
    subtotal DECIMAL(10,2) DEFAULT 0,
    shipping_cost DECIMAL(10,2) DEFAULT 0,
    discount_amount DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(10,2) DEFAULT 0,
    payment_method VARCHAR(50),
    payment_status ENUM('pending','paid','refunded') DEFAULT 'pending',
    order_status ENUM('pending','confirmed','packing','shipped','delivered','cancelled','returned') DEFAULT 'pending',
    notes TEXT,
    order_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (platform_id) REFERENCES platforms(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================
-- ตาราง: order_items (รายการสินค้าในออเดอร์)
-- =====================================================
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT,
    product_name VARCHAR(200),
    size VARCHAR(20),
    color VARCHAR(50),
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================
-- ตาราง: media (คลังสื่อ)
-- =====================================================
CREATE TABLE IF NOT EXISTS media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255),
    file_type ENUM('image','video') DEFAULT 'image',
    mime_type VARCHAR(100),
    file_size INT,
    file_path VARCHAR(500),
    thumbnail_path VARCHAR(500),
    title VARCHAR(200),
    description TEXT,
    tags VARCHAR(500),
    platform_ids VARCHAR(100),
    product_id INT,
    is_published TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================
-- ตาราง: promotions (โปรโมชั่น)
-- =====================================================
CREATE TABLE IF NOT EXISTS promotions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    code VARCHAR(50) UNIQUE,
    type ENUM('percent','fixed','free_shipping','bundle','flash_sale') NOT NULL,
    discount_value DECIMAL(10,2) DEFAULT 0,
    min_purchase DECIMAL(10,2) DEFAULT 0,
    max_discount DECIMAL(10,2) DEFAULT 0,
    usage_limit INT DEFAULT 0,
    usage_count INT DEFAULT 0,
    apply_to ENUM('all','category','product') DEFAULT 'all',
    apply_ids VARCHAR(500),
    platform_ids VARCHAR(100),
    start_date DATETIME,
    end_date DATETIME,
    description TEXT,
    banner_image VARCHAR(255),
    status ENUM('active','inactive','expired') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- ตาราง: content_calendar (ปฏิทินเนื้อหา)
-- =====================================================
CREATE TABLE IF NOT EXISTS content_calendar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content_type ENUM('post','story','reel','video','live','ad') DEFAULT 'post',
    platform_ids VARCHAR(100),
    product_id INT,
    promotion_id INT,
    caption TEXT,
    hashtags TEXT,
    media_ids VARCHAR(500),
    scheduled_at DATETIME,
    published_at DATETIME,
    status ENUM('draft','scheduled','published','cancelled') DEFAULT 'draft',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    FOREIGN KEY (promotion_id) REFERENCES promotions(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================
-- ตาราง: settings (ตั้งค่าระบบ)
-- =====================================================
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_group VARCHAR(50) DEFAULT 'general',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- ข้อมูลเริ่มต้น (Default Data)
-- =====================================================

-- Admin user (password: admin1234)
INSERT IGNORE INTO admin_users (username, password, full_name, role) VALUES
('admin', '$2y$10$l96f.ltbll2ra8DGrEb5kORvfmbkvMEp6HTK7T43PxnxZRUSNFZHG', 'ผู้ดูแลระบบ', 'superadmin');

-- Categories
INSERT IGNORE INTO categories (name, slug, icon, sort_order) VALUES
('บอดี้สูท', 'bodysuit', '👶', 1),
('ชุดนอน', 'sleepwear', '😴', 2),
('ชุดเซต', 'sets', '🎀', 3),
('เสื้อ', 'tops', '👕', 4),
('กางเกง/กระโปรง', 'bottoms', '👗', 5),
('หมวก', 'hats', '🧢', 6),
('ถุงมือ & ถุงเท้า', 'mittens-socks', '🧤', 7),
('ผ้าห่ม', 'blankets', '🛏️', 8),
('ชุดว่ายน้ำ', 'swimwear', '🏊', 9),
('อื่นๆ', 'others', '🎁', 10);

-- Platforms
INSERT IGNORE INTO platforms (name, slug, icon, color, commission_rate) VALUES
('Facebook Page', 'facebook', '📘', '#1877F2', 0.00),
('TikTok Shop', 'tiktok', '🎵', '#000000', 5.00),
('Instagram', 'instagram', '📸', '#E4405F', 0.00),
('Line Official', 'line', '💬', '#00B900', 0.00),
('หน้าร้าน', 'walkin', '🏪', '#FF6B6B', 0.00);

-- Default products (ตัวอย่าง)
INSERT IGNORE INTO products (sku, name, category_id, description, selling_price, cost_price, status) VALUES
('BK-001', 'บอดี้สูทคอตตอนลายดาว', 1, 'บอดี้สูทผ้าคอตตอน 100% นุ่มสบาย ระบายอากาศได้ดี เหมาะสำหรับทารกแรกเกิด', 299, 120, 'active'),
('BK-002', 'ชุดนอนซิปหน้าลายหมีน้อย', 2, 'ชุดนอนแบบซิปหน้าเปิดง่าย ผ้า Fleece นุ่มอุ่น ลายหมีน้อยน่ารัก', 450, 180, 'active'),
('BK-003', 'ชุดเซต 3 ชิ้น เสื้อ+กางเกง+หมวก', 3, 'เซตคอมพลีทสำหรับทารก ผ้า Cotton อ่อนนุ่ม พิมพ์ลายน่ารัก', 599, 250, 'active');

-- Stock for sample products
INSERT IGNORE INTO stock (product_id, size, color, quantity, min_alert) VALUES
(1, 'NB', 'ขาว', 15, 5),
(1, 'NB', 'ชมพู', 12, 5),
(1, '0-3M', 'ขาว', 20, 5),
(1, '0-3M', 'ชมพู', 18, 5),
(1, '3-6M', 'ขาว', 10, 5),
(1, '3-6M', 'ชมพู', 8, 5),
(2, 'NB', 'เหลือง', 10, 3),
(2, '0-3M', 'เหลือง', 15, 3),
(2, '3-6M', 'เหลือง', 12, 3),
(2, '6-9M', 'เหลือง', 8, 3),
(3, '0-3M', 'ชมพู', 10, 3),
(3, '0-3M', 'ฟ้า', 10, 3),
(3, '3-6M', 'ชมพู', 8, 3),
(3, '3-6M', 'ฟ้า', 8, 3);

-- Settings
INSERT IGNORE INTO settings (setting_key, setting_value, setting_group) VALUES
('shop_name', 'BabyKawaii Shop', 'general'),
('shop_tagline', 'เสื้อผ้าเด็กแรกเกิด คุณภาพดี น่ารักมาก 🌸', 'general'),
('shop_phone', '088-888-8888', 'general'),
('shop_line', '@babykawaii', 'general'),
('low_stock_alert', '5', 'inventory'),
('currency', 'THB', 'general'),
('shipping_base', '50', 'shipping');
