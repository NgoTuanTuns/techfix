-- Seed default accounts (Admin, Technician, and Customer) for local development and evaluation.
-- Run AFTER techfix_schema.sql in phpMyAdmin with the `techfix` database selected.
-- Default password for all seeded accounts: admin123
--   1. Admin:      0123456789 / admin123
--   2. Technician: 0911222333 / admin123
--   3. Customer:   0988777666 / admin123

USE techfix;

INSERT INTO users (full_name, phone, email, password_hash, role, is_active)
VALUES
(
    'Quản trị viên',
    '0123456789',
    'admin123@gmail.com',
    -- Generated via password_hash('admin123', PASSWORD_DEFAULT)
    '$2y$12$o5iov3ONZdikiNzdSH6WTuMpFfHDFZUzAXgjkqZQbUkExUMuozV4u',
    'admin',
    1
),
(
    'Lý Đại Bác',
    '0911222333',
    'tech.lydaibac@techfix.vn',
    '$2y$12$o5iov3ONZdikiNzdSH6WTuMpFfHDFZUzAXgjkqZQbUkExUMuozV4u',
    'technician',
    1
),
(
    'Ngô Tuấn Tú',
    '0988777666',
    'customer.demo@techfix.vn',
    '$2y$12$o5iov3ONZdikiNzdSH6WTuMpFfHDFZUzAXgjkqZQbUkExUMuozV4u',
    'customer',
    1
)
ON DUPLICATE KEY UPDATE
    full_name     = VALUES(full_name),
    email         = VALUES(email),
    password_hash = VALUES(password_hash),
    role          = VALUES(role),
    is_active     = VALUES(is_active);
