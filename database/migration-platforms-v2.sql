-- =====================================================
-- Migration: แก้ไข Platforms
-- LINE OA → ช่องแจ้งเตือน Admin เท่านั้น (ไม่ใช่ช่องขาย)
-- Facebook Page DM / Instagram DM / TikTok Shop (ช่องขายหลัก)
-- =====================================================

-- 1. ลบ Line Official ออกจากช่องทางขาย
DELETE FROM platforms WHERE slug = 'line';

-- 2. อัปเดตชื่อให้ชัดเจนว่าเป็น DM
UPDATE platforms SET name = 'Facebook Page DM' WHERE slug = 'facebook';
UPDATE platforms SET name = 'Instagram DM'     WHERE slug = 'instagram';
UPDATE platforms SET color = '#010101'          WHERE slug = 'tiktok';

-- 3. ใส่ platform ที่ยังไม่มี (ป้องกัน error ถ้า fresh install)
INSERT IGNORE INTO platforms (name, slug, icon, color, commission_rate) VALUES
('Facebook Page DM', 'facebook', '📘', '#1877F2', 0.00),
('TikTok Shop',      'tiktok',   '🎵', '#010101', 5.00),
('Instagram DM',     'instagram','📸', '#E4405F', 0.00),
('หน้าร้าน',         'walkin',   '🏪', '#FF6B6B', 0.00);

-- 4. ย้ายออเดอร์เก่าที่ platform เป็น LINE → null (ไม่ระบุ platform)
UPDATE orders SET platform_id = NULL
WHERE platform_id = (SELECT id FROM platforms WHERE slug = 'line' LIMIT 1);

-- หมายเหตุ: LINE OA ยังใช้งานได้ในหน้า Settings → เชื่อมต่อระบบ
-- line_channel_access_token / line_admin_user_id ยังอยู่ใน settings table
