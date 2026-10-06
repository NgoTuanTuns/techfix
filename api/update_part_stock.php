<?php
/**
 * api/update_part_stock.php
 * Yêu cầu role = admin. Nhận POST: part_id, quantity (số lượng tồn mới, >= 0).
 * Trả JSON: {success: bool, message: string}
 */

session_start();
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Phương thức không được hỗ trợ']);
    exit;
}

require_role('admin');

$partId = (int)($_POST['id'] ?? 0);
$qty    = $_POST['quantity'] ?? '';

if ($partId <= 0 || !ctype_digit((string)$qty)) {
    echo json_encode(['success' => false, 'message' => 'Mã linh kiện hoặc số lượng không hợp lệ']);
    exit;
}

try {
    $check = $pdo->prepare('SELECT id FROM inventory_parts WHERE id = ?');
    $check->execute([$partId]);
    if (!$check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy linh kiện này']);
        exit;
    }

    $pdo->prepare('UPDATE inventory_parts SET quantity = ? WHERE id = ?')->execute([(int)$qty, $partId]);
    echo json_encode(['success' => true, 'message' => 'Đã cập nhật tồn kho']);

} catch (PDOException $e) {
    http_response_code(500);
    error_log('update_part_stock.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống, vui lòng thử lại sau']);
}
