-- Tạo (hoặc nâng quyền) tài khoản quản trị để chạy thử trên máy local.
-- Chạy SAU techfix_schema.sql, trong phpMyAdmin khi đã chọn database techfix.
-- Đăng nhập bằng SĐT 0123456789, mật khẩu admin123.
-- Chỉ dùng cho môi trường học/demo: đổi mật khẩu trước khi deploy thật,
-- và không nên đưa file này lên repo public.

USE techfix;

INSERT INTO users (full_name, phone, email, password_hash, role)
VALUES (
    'Quản trị viên',
    '0123456789',
    'admin123@gmail.com',
    -- password_hash('admin123', PASSWORD_DEFAULT)
    '$2y$12$o5iov3ONZdikiNzdSH6WTuMpFfHDFZUzAXgjkqZQbUkExUMuozV4u',
    'admin'
)
ON DUPLICATE KEY UPDATE
    email         = VALUES(email),
    password_hash = VALUES(password_hash),
    role          = 'admin';
