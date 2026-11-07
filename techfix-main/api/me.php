<?php
/**
 * api/me.php
 * Trả thông tin tài khoản đang đăng nhập (dùng để các trang như booking.html,
 * dashboard.html kiểm tra đăng nhập khi vừa tải trang).
 * Trả JSON: {success: bool, data?: {id, full_name, phone, role}, message?: string}
 */

session_start();
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

require_login();

try {
    $stmt = $pdo->prepare('SELECT id, full_name, phone, role FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        // Tài khoản trong session không còn tồn tại trong DB (hiếm, nhưng xử lý cho chắc)
        session_destroy();
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Phiên đăng nhập không hợp lệ']);
        exit;
    }

    echo json_encode(['success' => true, 'data' => $user]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log('me.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống, vui lòng thử lại sau']);
}
