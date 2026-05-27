-- =====================================================
-- Migration: Missing tables & columns
-- แก้ไข: product_type, bundle_items, business_events
-- =====================================================

-- 1. เพิ่ม product_type column ใน products table
ALTER TABLE products
    ADD COLUMN IF NOT EXISTS product_type ENUM('single','bundle') NOT NULL DEFAULT 'single' AFTER name;

-- 2. สร้าง bundle_items table
CREATE TABLE IF NOT EXISTS bundle_items (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    bundle_id  INT NOT NULL,
    product_id INT NOT NULL,
    size       VARCHAR(50)  DEFAULT '',
    color      VARCHAR(50)  DEFAULT '',
    quantity   INT          DEFAULT 1,
    FOREIGN KEY (bundle_id)  REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. สร้าง business_events table
CREATE TABLE IF NOT EXISTS business_events (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(200) NOT NULL,
    event_type  ENUM('purchase','stock_count','delivery','meeting','task','promotion','other') DEFAULT 'other',
    start_at    DATETIME     NOT NULL,
    end_at      DATETIME     DEFAULT NULL,
    amount      DECIMAL(10,2) DEFAULT NULL,
    notes       TEXT,
    color       VARCHAR(7)   DEFAULT '#9B72CF',
    created_by  INT          DEFAULT NULL,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. ใส่ business event ที่บันทึกไว้ (ซื้อสินค้าเข้าสต็อก 27 พ.ค. 2026)
INSERT IGNORE INTO business_events (title, event_type, start_at, end_at, amount, notes, color, created_by)
SELECT 'ซื้อสินค้าเข้าสต็อก (ล็อตแรก)', 'purchase', '2026-05-27 02:30:00', '2026-05-27 04:30:00', 4600.00, 'ซื้อสินค้าล็อตแรก 8 รายการ รวม 4,600 บาท', '#F97316', 1
WHERE NOT EXISTS (SELECT 1 FROM business_events WHERE start_at = '2026-05-27 02:30:00');
