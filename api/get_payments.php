<?php
/**
 * api/get_payments.php
 * Yêu cầu đã đăng nhập. Trả lịch sử thanh toán của khách đang đăng nhập.
 * Trả JSON: {success: bool, data?: [...], message?: string}
 */

session_start();
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

require_login();
$userId = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare(
        'SELECT p.id, p.amount, p.method, p.paid_at,
                ro.order_code, d.brand, d.model
         FROM payments p
         JOIN repair_orders ro ON ro.id = p.order_id
         JOIN devices d ON d.id = ro.device_id
         WHERE ro.customer_id = ?
         ORDER BY p.paid_at DESC'
    );
    $stmt->execute([$userId]);
    $payments = $stmt->fetchAll();

    echo json_encode(['success' => true, 'data' => $payments]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log('get_payments.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống, vui lòng thử lại sau']);
}
