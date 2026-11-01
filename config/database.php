<?php
/**
 * config/database.php
 * Tạo $pdo dùng chung. Thông số mặc định khớp XAMPP; có thể ghi đè bằng biến môi trường
 * TECHFIX_DB_HOST / TECHFIX_DB_NAME / TECHFIX_DB_USER / TECHFIX_DB_PASS khi deploy.
 */

$host    = getenv('TECHFIX_DB_HOST') ?: 'localhost';
$db      = getenv('TECHFIX_DB_NAME') ?: 'techfix';
$user    = getenv('TECHFIX_DB_USER') ?: 'root';
$pass    = getenv('TECHFIX_DB_PASS') ?: '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // Chi tiết lỗi chỉ ghi log phía server; client nhận JSON chung như mọi API khác
    error_log('database.php connection error: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Không kết nối được cơ sở dữ liệu, vui lòng thử lại sau',
    ]);
    exit;
}
