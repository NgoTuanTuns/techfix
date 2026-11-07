<?php
/**
 * api/get_all_orders.php
 * Yêu cầu role = admin hoặc technician. Admin nhận toàn bộ đơn sửa chữa;
 * kỹ thuật viên chỉ nhận đơn đang giao cho mình và đơn chưa ai phụ trách.
 * Kèm thông tin khách hàng + kỹ thuật viên phụ trách, để render bảng admin.
 * Trả JSON: {success: bool, data?: [...], message?: string}
 */

session_start();
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

require_any_role(['admin', 'technician']);

$isTechnician = ($_SESSION['role'] ?? '') === 'technician';

try {
   $stmt = $pdo->prepare(
        'SELECT ro.id, ro.order_code, ro.issue_description, ro.status,
                ro.estimated_cost, ro.final_cost, ro.received_at, ro.completed_at,
                ro.delivery_method, ro.delivery_address, ro.delivery_phone,
                d.device_type, d.brand, d.model,
                cu.id AS customer_id, cu.full_name AS customer_name, cu.phone AS customer_phone,
                te.id AS technician_id, te.full_name AS technician_name
         FROM repair_orders ro
         JOIN devices d ON d.id = ro.device_id
         JOIN users cu ON cu.id = ro.customer_id
         LEFT JOIN users te ON te.id = ro.technician_id
         WHERE ? = 0 OR ro.technician_id = ? OR ro.technician_id IS NULL
         ORDER BY ro.received_at DESC'
    );
    $stmt->execute([$isTechnician ? 1 : 0, $_SESSION['user_id']]);
    $orders = $stmt->fetchAll();

    echo json_encode(['success' => true, 'data' => $orders]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log('get_all_orders.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống, vui lòng thử lại sau']);
}
