<?php
/**
 * api/get_technicians.php
 * Yêu cầu role = admin. Trả danh sách kỹ thuật viên để gán vào đơn.
 * Trả JSON: {success: bool, data?: [{id, full_name}]}
 */

session_start();
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

require_role('admin');

try {
    $stmt = $pdo->query(
        "SELECT id, full_name FROM users WHERE role = 'technician' ORDER BY full_name ASC"
    );
    $technicians = $stmt->fetchAll();

    echo json_encode(['success' => true, 'data' => $technicians]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log('get_technicians.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống, vui lòng thử lại sau']);
}
