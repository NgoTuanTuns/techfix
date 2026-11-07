-- Database schema for the TechFix project.
-- Matches the SQL queries across all backend PHP endpoints.
-- Import this script via phpMyAdmin or MariaDB CLI after selecting the techfix database.
-- Note: CREATE TABLE IF NOT EXISTS will not alter existing tables.

CREATE DATABASE IF NOT EXISTS techfix
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE techfix;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(15) NOT NULL,
    email VARCHAR(100) NULL,
    avatar VARCHAR(255) NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('customer', 'technician', 'admin') NOT NULL DEFAULT 'customer',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_phone (phone),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS devices (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    device_type ENUM('laptop', 'phone', 'tablet') NOT NULL,
    brand VARCHAR(50) NOT NULL,
    model VARCHAR(100) NOT NULL,
    serial_number VARCHAR(100) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_devices_user (user_id),
    KEY idx_devices_type (device_type),
    CONSTRAINT fk_devices_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS services (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(60) NOT NULL,
    device_type ENUM('laptop', 'phone', 'tablet') NOT NULL,
    name VARCHAR(150) NOT NULL,
    description VARCHAR(255) NULL,
    base_price DECIMAL(10,0) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_services_slug (slug),
    KEY idx_services_device (device_type),
    KEY idx_services_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS repair_orders (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_code VARCHAR(20) NULL,
    customer_id INT UNSIGNED NOT NULL,
    device_id INT UNSIGNED NOT NULL,
    technician_id INT UNSIGNED NULL,
    issue_description TEXT NOT NULL,
    diagnosis_note TEXT NULL,
    delivery_method VARCHAR(30) NULL,
    delivery_address VARCHAR(255) NULL,
    delivery_phone VARCHAR(15) NULL,
    customer_email VARCHAR(100) NULL,
    preferred_time DATETIME NULL,
    status ENUM('pending_receipt', 'received', 'diagnosing', 'awaiting_approval', 'repairing', 'awaiting_payment', 'completed', 'cancelled')
        NOT NULL DEFAULT 'pending_receipt',
    estimated_cost DECIMAL(10,0) NULL,
    final_cost DECIMAL(10,0) NULL,
    received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_repair_orders_code (order_code),
    KEY idx_orders_status (status),
    KEY idx_orders_customer (customer_id),
    KEY idx_orders_technician (technician_id),
    KEY idx_orders_device (device_id),
    KEY idx_orders_received (received_at),
    CONSTRAINT fk_orders_customer
        FOREIGN KEY (customer_id) REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_orders_device
        FOREIGN KEY (device_id) REFERENCES devices(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_orders_technician
        FOREIGN KEY (technician_id) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_status_history (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id INT UNSIGNED NOT NULL,
    status VARCHAR(30) NOT NULL,
    note VARCHAR(255) NULL,
    changed_by INT UNSIGNED NOT NULL,
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_history_order (order_id),
    KEY idx_history_changed_by (changed_by),
    CONSTRAINT fk_history_order
        FOREIGN KEY (order_id) REFERENCES repair_orders(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_history_user
        FOREIGN KEY (changed_by) REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_services (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id INT UNSIGNED NOT NULL,
    service_id INT UNSIGNED NOT NULL,
    price DECIMAL(10,0) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_ordersvc_order (order_id),
    KEY idx_ordersvc_service (service_id),
    CONSTRAINT fk_ordersvc_order
        FOREIGN KEY (order_id) REFERENCES repair_orders(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_ordersvc_service
        FOREIGN KEY (service_id) REFERENCES services(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_attachments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id INT UNSIGNED NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_attach_order (order_id),
    CONSTRAINT fk_attach_order
        FOREIGN KEY (order_id) REFERENCES repair_orders(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_parts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    compatible_with VARCHAR(150) NULL,
    quantity INT NOT NULL DEFAULT 0,
    unit_cost DECIMAL(10,0) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_parts_used (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id INT UNSIGNED NOT NULL,
    part_id INT UNSIGNED NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_partsused_order (order_id),
    KEY idx_partsused_part (part_id),
    CONSTRAINT fk_partsused_order
        FOREIGN KEY (order_id) REFERENCES repair_orders(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_partsused_part
        FOREIGN KEY (part_id) REFERENCES inventory_parts(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id INT UNSIGNED NOT NULL,
    amount DECIMAL(10,0) NOT NULL,
    method ENUM('cash', 'bank_transfer', 'momo') NOT NULL,
    paid_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_payments_order (order_id),
    KEY idx_payments_paid_at (paid_at),
    CONSTRAINT fk_payments_order
        FOREIGN KEY (order_id) REFERENCES repair_orders(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reviews (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    comment TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_reviews_order (order_id),
    KEY idx_reviews_customer (customer_id),
    CONSTRAINT fk_reviews_order
        FOREIGN KEY (order_id) REFERENCES repair_orders(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_reviews_customer
        FOREIGN KEY (customer_id) REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT chk_reviews_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Schema migration for existing databases (since CREATE TABLE IF NOT EXISTS does not add new columns).
-- IF NOT EXISTS (MariaDB 10.0.2+) ensures idempotency when re-running this script.
ALTER TABLE repair_orders
ADD COLUMN IF NOT EXISTS delivery_address VARCHAR(255) NULL AFTER delivery_method,
ADD COLUMN IF NOT EXISTS delivery_phone VARCHAR(15) NULL AFTER delivery_address,
ADD COLUMN IF NOT EXISTS customer_email VARCHAR(100) NULL AFTER delivery_phone;

ALTER TABLE users
ADD COLUMN IF NOT EXISTS avatar VARCHAR(255) NULL AFTER email;

ALTER TABLE repair_orders
MODIFY COLUMN status ENUM('pending_receipt', 'received', 'diagnosing', 'awaiting_approval', 'repairing', 'awaiting_payment', 'completed', 'cancelled')
    NOT NULL DEFAULT 'pending_receipt';

-- Seed service catalog data. INSERT IGNORE avoids duplicate key errors on existing slugs.
INSERT IGNORE INTO services (slug, device_type, name, base_price) VALUES
('ve-sinh-tan-nhiet', 'laptop', 'Vệ sinh + tra keo tản nhiệt', 99000),
('thay-ban-phim', 'laptop', 'Thay bàn phím laptop', 250000),
('thay-man-hinh-laptop', 'laptop', 'Thay màn hình laptop', 1200000),
('cai-win', 'laptop', 'Cài lại Windows + driver', 150000),
('ep-kinh', 'phone', 'Ép kính mặt trước điện thoại', 350000),
('thay-pin-dt', 'phone', 'Thay pin điện thoại chính hãng', 450000),
('sua-chan-sac', 'phone', 'Sửa lỗi chân sạc', 200000),
('xu-ly-vao-nuoc', 'phone', 'Xử lý máy vào nước, không lên nguồn', 0),
('thay-man-hinh-tablet', 'tablet', 'Thay màn hình cảm ứng tablet', 1500000),
('thay-pin-tablet', 'tablet', 'Thay pin tablet', 550000),
('thay-cong-sac-tablet', 'tablet', 'Thay cổng sạc tablet', 300000),
('sua-loi-phan-mem', 'tablet', 'Sửa lỗi phần mềm / treo logo', 150000);

-- Seed sample inventory parts for admin-inventory.html (INSERT IGNORE preserves existing stock).
INSERT IGNORE INTO inventory_parts (id, name, compatible_with, quantity, unit_cost) VALUES
(1, 'Keo tản nhiệt Arctic MX-4 (ống 4g)', 'Laptop Dell, Asus, HP, Lenovo, MacBook', 15, 65000),
(2, 'Bàn phím laptop Dell Inspiron 15', 'Dell Inspiron 3511 / 3520 / 5510', 6, 180000),
(3, 'Màn hình laptop 15.6" FHD IPS 30 pin', 'Asus VivoBook, Dell, HP, Lenovo 15.6"', 3, 950000),
(4, 'Ổ cứng SSD NVMe Kingston 256GB', 'Laptop hỗ trợ khe M.2 NVMe PCIe', 8, 450000),
(5, 'Pin iPhone 13 dung lượng chuẩn', 'iPhone 13', 2, 320000),
(6, 'Kính mặt trước iPhone 14 Pro Max', 'iPhone 14 Pro Max', 5, 210000),
(7, 'Cụm chân sạc Samsung Type-C', 'Samsung Galaxy A52 / A53 / S21', 0, 95000),
(8, 'Màn hình cảm ứng iPad Gen 9 10.2"', 'iPad Gen 7 / Gen 8 / Gen 9', 4, 1100000);
