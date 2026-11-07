<?php
/**
 * api/me.php
 * Returns profile details for the currently authenticated user (used by booking.html,
 * dashboard.html, and admin pages to verify session state on page load).
 * Returns JSON: {success: bool, data?: {id, full_name, phone, email, avatar, role}, message?: string}
 */

session_start();
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

require_login();

try {
    // Ensure the avatar column exists on older database schemas before querying
    try {
        $pdo->exec('ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL AFTER email');
    } catch (Throwable $ignore) {
        // Column already exists
    }

    $stmt = $pdo->prepare('SELECT id, full_name, phone, email, avatar, role FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        // Session references a deleted user record; invalidate session
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
