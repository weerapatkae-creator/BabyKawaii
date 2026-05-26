-- Calendar v2: media picker + per-platform captions + enhanced status + post types

-- Add new columns to content_calendar (safe: uses IF NOT EXISTS via IGNORE trick)
ALTER TABLE content_calendar
    ADD COLUMN IF NOT EXISTS media_ids      VARCHAR(500)  DEFAULT NULL COMMENT 'comma-separated media IDs from media library',
    ADD COLUMN IF NOT EXISTS captions_json  JSON          DEFAULT NULL COMMENT '{"platform_id": "caption text"}',
    ADD COLUMN IF NOT EXISTS post_type      ENUM('video','product','promo','image','announcement','reel','story','live','post') DEFAULT 'image' AFTER content_type,
    ADD COLUMN IF NOT EXISTS publish_status ENUM('draft','scheduled','publishing','published','failed','cancelled') DEFAULT 'draft' AFTER status,
    ADD COLUMN IF NOT EXISTS publish_errors TEXT          DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS published_platforms VARCHAR(200) DEFAULT NULL COMMENT 'platform IDs that succeeded';

-- Sync publish_status from status for existing rows
UPDATE content_calendar SET publish_status = status WHERE publish_status = 'draft' AND status != 'draft';

-- Add media table if not exists (basic - full media library may already exist)
CREATE TABLE IF NOT EXISTS media_library (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    filename     VARCHAR(300) NOT NULL,
    original_name VARCHAR(300),
    file_type    ENUM('image','video','document','other') DEFAULT 'image',
    mime_type    VARCHAR(100),
    file_size    INT DEFAULT 0,
    width        INT DEFAULT 0,
    height       INT DEFAULT 0,
    duration     INT DEFAULT 0 COMMENT 'video duration in seconds',
    url          VARCHAR(1000) NOT NULL,
    thumbnail_url VARCHAR(1000) DEFAULT NULL,
    r2_key       VARCHAR(500) DEFAULT NULL COMMENT 'Cloudflare R2 object key',
    tags         VARCHAR(500) DEFAULT '',
    uploaded_by  INT DEFAULT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_file_type (file_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
