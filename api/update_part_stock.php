<?php
/**
 * api/update_part_stock.php
 * Yêu cầu role = admin hoặc technician. Nhận POST: id (hoặc part_id), quantity
 * Cập nhật số lượng tồn kho của 1 linh kiện (nút −, +, ✎ trong admin-inventory.html).
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

require_any_role(['admin', 'technician']);

$id       = (int)($_POST['id'] ?? $_POST['part_id'] ?? 0);
$quantity = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT);

if ($id <= 0 || $quantity === false || $quantity < 0) {
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
    exit;
}

try {
    $stmt = $pdo->prepare('UPDATE inventory_parts SET quantity = ? WHERE id = ?');
    $stmt->execute([$quantity, $id]);

    // rowCount() = 0 cũng xảy ra khi số lượng không đổi, nên chỉ coi là lỗi nếu id không tồn tại
    if ($stmt->rowCount() === 0) {
        $exists = $pdo->prepare('SELECT 1 FROM inventory_parts WHERE id = ?');
        $exists->execute([$id]);
        if (!$exists->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy linh kiện này']);
            exit;
        }
    }

    echo json_encode(['success' => true, 'message' => 'Đã cập nhật tồn kho']);
} catch (PDOException $e) {
    http_response_code(500);
    error_log('update_part_stock.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống, vui lòng thử lại sau']);
}
