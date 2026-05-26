-- =====================================================================
-- BabyKawaii — n8n / LINE Integration Migration
-- Run ONCE on top of the existing babykawaii_db database:
--   mysql -u root babykawaii_db < migration-n8n.sql
-- =====================================================================

-- ── 1. LINE Chatbot session storage ──────────────────────────────────
CREATE TABLE IF NOT EXISTS line_chat_sessions (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    line_user_id  VARCHAR(100) NOT NULL UNIQUE,
    display_name  VARCHAR(200),
    state         VARCHAR(50)  DEFAULT 'idle',
    data          JSON,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 2. Integration settings ───────────────────────────────────────────
-- These are placeholders. Fill them via Admin → เชื่อมต่อระบบ
INSERT IGNORE INTO settings (setting_key, setting_value, setting_group) VALUES
('api_key',                    '',  'integration'),
('line_notify_token',          '',  'integration'),
('line_channel_access_token',  '',  'integration'),
('line_channel_secret',        '',  'integration'),
('line_admin_user_id',         '',  'integration'),
('n8n_base_url',               '',  'integration'),
('webhook_secret',             '',  'integration');

-- ── 3. Add platform_id for "line" platform in orders (already exists) ─
-- Nothing extra needed — platform slug 'line' already in platforms table

-- Done!
SELECT 'n8n migration complete' AS status;
