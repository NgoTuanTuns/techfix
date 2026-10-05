<?php
/**
 * api/get_orders.php
 * Yêu cầu đã đăng nhập (session). Trả về toàn bộ đơn sửa chữa của khách đó,
 * kèm lịch sử trạng thái để dashboard dựng stepper.
 * Trả JSON: {success: bool, data?: [...], message?: string}
 */

session_start();
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

require_login();

$customerId = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare(
        'SELECT ro.id, ro.order_code, ro.issue_description, ro.status,
                ro.estimated_cost, ro.final_cost, ro.received_at, ro.completed_at,
                d.device_type, d.brand, d.model
         FROM repair_orders ro
         JOIN devices d ON d.id = ro.device_id
         WHERE ro.customer_id = ?
         ORDER BY ro.received_at DESC'
    );
    $stmt->execute([$customerId]);
    $orders = $stmt->fetchAll();

    // Lấy lịch sử trạng thái cho từng đơn (để vẽ stepper trên dashboard)
    $historyStmt = $pdo->prepare(
        'SELECT status, note, changed_at
         FROM order_status_history
         WHERE order_id = ?
         ORDER BY changed_at ASC'
    );

    foreach ($orders as &$order) {
        $historyStmt->execute([$order['id']]);
        $order['history'] = $historyStmt->fetchAll();
    }
    unset($order);

    echo json_encode(['success' => true, 'data' => $orders]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log('get_orders.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống, vui lòng thử lại sau']);
}
