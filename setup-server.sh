#!/bin/bash
# BabyKawaii Server Setup Script
# รัน: bash setup-server.sh

echo "🌸 BabyKawaii — Server Setup"
echo "================================"

# ── 1. Fix Migration ─────────────────────────────────────────
echo "📦 Running database migration..."
mysql -u bkuser -p'BabyKawaii@2026!' babykawaii <<SQL
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS platform_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    platform_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    account_uid VARCHAR(200) DEFAULT '',
    page_access_token TEXT,
    app_secret VARCHAR(255) DEFAULT '',
    webhook_verify_token VARCHAR(200) DEFAULT '',
    color VARCHAR(7) DEFAULT '#888888',
    is_active TINYINT(1) DEFAULT 1,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (platform_id) REFERENCES platforms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE conversations ADD COLUMN IF NOT EXISTS platform_account_id INT DEFAULT NULL AFTER platform_id;
ALTER TABLE conversations ADD COLUMN IF NOT EXISTS platform_account_name VARCHAR(100) DEFAULT NULL AFTER platform_account_id;
ALTER TABLE conversations DROP KEY IF EXISTS uq_platform_customer;
ALTER TABLE conversations ADD UNIQUE KEY IF NOT EXISTS uq_account_customer (platform_id, platform_account_id, customer_uid);
ALTER TABLE conversations ADD CONSTRAINT IF NOT EXISTS fk_conv_account FOREIGN KEY (platform_account_id) REFERENCES platform_accounts(id) ON DELETE SET NULL;

SET FOREIGN_KEY_CHECKS=1;
SQL
echo "✅ Migration done"

# ── 2. Permissions & Uploads ─────────────────────────────────
echo "📁 Setting permissions..."
chown -R www-data:www-data /var/www/babykawaii
chmod -R 755 /var/www/babykawaii
mkdir -p /var/www/babykawaii/assets/uploads/products
mkdir -p /var/www/babykawaii/assets/uploads/media
chmod -R 775 /var/www/babykawaii/assets/uploads
echo "✅ Permissions done"

# ── 3. Nginx Config ───────────────────────────────────────────
echo "🌐 Configuring Nginx..."
cat > /etc/nginx/sites-available/babykawaii <<'NGINX'
server {
    listen 80;
    server_name 159.223.52.122;
    root /var/www/babykawaii;
    index index.php index.html;
    client_max_body_size 20M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(git|env) {
        deny all;
    }

    location ~* \.(jpg|jpeg|png|gif|ico|css|js|woff2)$ {
        expires 30d;
        add_header Cache-Control "public";
    }
}
NGINX

ln -sf /etc/nginx/sites-available/babykawaii /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx
echo "✅ Nginx done"

# ── 4. PHP-FPM ────────────────────────────────────────────────
echo "🐘 Restarting PHP-FPM..."
systemctl restart php8.2-fpm
echo "✅ PHP-FPM done"

echo ""
echo "================================"
echo "🎉 Setup สำเร็จทั้งหมด!"
echo "🌸 เปิดได้ที่: http://159.223.52.122"
echo "================================"
