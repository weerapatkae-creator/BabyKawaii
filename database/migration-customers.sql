-- BabyKawaii: Customer database migration
-- Run once: adds customers table + backfills from order history

CREATE TABLE IF NOT EXISTS customers (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(200) NOT NULL,
    phone         VARCHAR(30)  DEFAULT '',
    email         VARCHAR(200) DEFAULT '',
    line_user_id  VARCHAR(100) DEFAULT '',
    address       TEXT,
    birthday      DATE DEFAULT NULL,
    notes         TEXT,
    tags          VARCHAR(500) DEFAULT '',
    total_orders  INT DEFAULT 0,
    total_spent   DECIMAL(12,2) DEFAULT 0.00,
    last_order_at DATETIME DEFAULT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_phone (phone),
    INDEX idx_name  (name),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backfill from existing orders (de-duped by phone number)
INSERT IGNORE INTO customers (name, phone, address, total_orders, total_spent, last_order_at)
SELECT
    customer_name,
    customer_phone,
    customer_address,
    COUNT(*) AS total_orders,
    SUM(total_amount) AS total_spent,
    MAX(order_date) AS last_order_at
FROM orders
WHERE customer_phone IS NOT NULL AND customer_phone <> ''
GROUP BY customer_phone;

-- Also capture orders without phone (by name only, avoid exact duplicate names)
INSERT IGNORE INTO customers (name, phone, address, total_orders, total_spent, last_order_at)
SELECT
    customer_name,
    '',
    customer_address,
    COUNT(*),
    SUM(total_amount),
    MAX(order_date)
FROM orders
WHERE (customer_phone IS NULL OR customer_phone = '')
  AND customer_name NOT IN (SELECT name FROM customers WHERE phone <> '')
GROUP BY customer_name;
