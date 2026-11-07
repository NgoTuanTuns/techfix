-- Seed (or promote) the default admin account for local development.
-- Run AFTER techfix_schema.sql in phpMyAdmin with the `techfix` database selected.
-- Default credentials: phone 0123456789 / password admin123.
-- For local testing/demo only — change the password before deploying to production.

USE techfix;

INSERT INTO users (full_name, phone, email, password_hash, role)
VALUES (
    'Quản trị viên',
    '0123456789',
    'admin123@gmail.com',
    -- Generated via password_hash('admin123', PASSWORD_DEFAULT)
    '$2y$12$o5iov3ONZdikiNzdSH6WTuMpFfHDFZUzAXgjkqZQbUkExUMuozV4u',
    'admin'
)
ON DUPLICATE KEY UPDATE
    email         = VALUES(email),
    password_hash = VALUES(password_hash),
    role          = 'admin';
