-- BabyKawaii: Unified Inbox migration
-- conversations, messages, quick_reply templates

CREATE TABLE IF NOT EXISTS conversations (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    platform_id       INT DEFAULT NULL,
    customer_uid      VARCHAR(200) NOT NULL,
    customer_name     VARCHAR(200) DEFAULT '',
    customer_avatar   VARCHAR(500) DEFAULT '',
    status            ENUM('open','pending','closed') DEFAULT 'open',
    unread_count      INT DEFAULT 0,
    last_message      TEXT,
    last_message_at   DATETIME DEFAULT NULL,
    assigned_to       INT DEFAULT NULL,
    notes             TEXT,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_platform_customer (platform_id, customer_uid),
    INDEX idx_status (status),
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
    sent_by             INT DEFAULT NULL,
    sent_at             DATETIME DEFAULT CURRENT_TIMESTAMP,
    platform_message_id VARCHAR(300) DEFAULT NULL,
    is_read             TINYINT(1) DEFAULT 0,
    INDEX idx_conversation (conversation_id),
    INDEX idx_sent_at (sent_at),
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quick_replies (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    label       VARCHAR(100) NOT NULL,
    content     TEXT NOT NULL,
    sort_order  INT DEFAULT 0,
    is_active   TINYINT(1) DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default quick reply templates
INSERT IGNORE INTO quick_replies (label, content, sort_order) VALUES
('ขอบคุณที่ติดต่อ',   'สวัสดีค่ะ ขอบคุณที่ติดต่อมานะคะ 🌸 มีอะไรให้ช่วยได้บ้างคะ?', 1),
('รอสักครู่',         'รอสักครู่นะคะ กำลังตรวจสอบข้อมูลให้เลยค่ะ 🔍', 2),
('เช็คออเดอร์',       'ขอเลขออเดอร์ด้วยนะคะ เพื่อตรวจสอบสถานะให้ค่ะ 📦', 3),
('จัดส่งแล้ว',        'ออเดอร์ของคุณจัดส่งแล้วนะคะ 🚚 สามารถติดตามพัสดุได้ที่เลขที่แจ้งไปค่ะ', 4),
('สินค้าหมด',        'ขออภัยนะคะ สินค้านี้หมดสต็อกชั่วคราว จะแจ้งให้ทราบทันทีที่มีของค่ะ 🙏', 5),
('ราคาและโปร',        'สามารถดูราคาและโปรโมชั่นล่าสุดได้ที่ [ลิงค์ร้าน] ค่ะ 🏷️', 6);
