-- ============================================================
-- BabyKawaii — Login security (brute-force protection)
-- ตารางบันทึกการพยายามเข้าสู่ระบบ (ใช้กับ rate-limit ใน login.php)
-- หมายเหตุ: login.php สร้างตารางนี้อัตโนมัติอยู่แล้ว (CREATE TABLE IF NOT EXISTS)
--          ไฟล์นี้มีไว้สำหรับ deploy/เอกสารและการตรวจสอบสคีมา
-- ============================================================

CREATE TABLE IF NOT EXISTS login_attempts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    ip_address   VARCHAR(45) NOT NULL,
    username     VARCHAR(50),
    success      TINYINT(1) DEFAULT 0,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ล้างประวัติเก่ากว่า 1 วัน (รันเป็นระยะหรือใน cron ได้)
-- DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY);
