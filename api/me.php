<?php
/**
 * api/me.php
 * Returns profile details for the currently authenticated user (used by booking.html,
 * dashboard.html, and admin pages to verify session state on page load).
 * Returns JSON: {success: bool, data?: {id, full_name, phone, email, avatar, role, is_active}, message?: string}
 */

session_start();
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/mailer.php';

header('Content-Type: application/json; charset=utf-8');

require_login();

try {
    ensure_order_payment_schema($pdo);

    $stmt = $pdo->prepare('SELECT id, full_name, phone, email, avatar, role, is_active FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        // Session references a deleted user record; invalidate session
        session_destroy();
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Phiên đăng nhập không hợp lệ']);
        exit;
    }

    $user['is_active'] = (int)($user['is_active'] ?? 0);

    // Keep session state synchronized with the latest database record (e.g. if role or active status changed in DB)
    $_SESSION['role']      = $user['role'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['is_active'] = $user['is_active'];

    echo json_encode(['success' => true, 'data' => $user]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log('me.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống, vui lòng thử lại sau']);
}
