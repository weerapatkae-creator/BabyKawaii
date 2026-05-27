-- =====================================================
-- Migration: Platform Accounts (multi-account support)
-- รองรับ Facebook Page หลายเพจ, Instagram หลาย account,
-- TikTok Shop หลาย shop — แต่ละบัญชีมี API ของตัวเอง
-- =====================================================

-- 1. ตาราง platform_accounts
CREATE TABLE IF NOT EXISTS platform_accounts (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    platform_id          INT NOT NULL,
    name                 VARCHAR(100) NOT NULL COMMENT 'ชื่อที่ตั้งให้บัญชีนี้ เช่น BabyKawaii TH',
    account_uid          VARCHAR(200) DEFAULT '' COMMENT 'Page ID / IG Account ID / TikTok Shop ID',
    page_access_token    TEXT         COMMENT 'Meta Page Access Token หรือ TikTok Access Token',
    app_secret           VARCHAR(255) DEFAULT '' COMMENT 'Meta App Secret (ใช้ verify webhook signature)',
    webhook_verify_token VARCHAR(200) DEFAULT '' COMMENT 'Token ที่ตั้งเองสำหรับ Meta webhook verification',
    color                VARCHAR(7)   DEFAULT '#888888' COMMENT 'สีแสดงผลใน Inbox',
    is_active            TINYINT(1)   DEFAULT 1,
    notes                TEXT,
    created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (platform_id) REFERENCES platforms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. เพิ่ม platform_account_id ใน conversations
ALTER TABLE conversations
    ADD COLUMN IF NOT EXISTS platform_account_id INT DEFAULT NULL AFTER platform_id;

ALTER TABLE conversations
    ADD COLUMN IF NOT EXISTS platform_account_name VARCHAR(100) DEFAULT NULL AFTER platform_account_id;

-- 3. อัปเดต Unique Key ให้ครอบคลุม account
ALTER TABLE conversations DROP KEY IF EXISTS uq_platform_customer;
ALTER TABLE conversations
    ADD UNIQUE KEY uq_account_customer (platform_id, platform_account_id, customer_uid);

-- 4. Foreign key
ALTER TABLE conversations
    ADD CONSTRAINT fk_conv_account
    FOREIGN KEY (platform_account_id) REFERENCES platform_accounts(id) ON DELETE SET NULL;
