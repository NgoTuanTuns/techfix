<?php
/**
 * api/update_order_status.php
 * Yêu cầu role = admin. Nhận POST: order_id (bắt buộc) + bất kỳ tổ hợp nào trong
 *   status, technician_id, estimated_cost, final_cost, note
 * Chỉ cập nhật những trường thực sự được gửi lên (cho phép sửa từng ô trên bảng admin
 * mà không phải gửi lại toàn bộ đơn).
 * Nếu status thay đổi -> tự ghi thêm 1 dòng vào order_status_history.
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
$adminId = $_SESSION['user_id'];

$orderId = (int)($_POST['order_id'] ?? 0);
if ($orderId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Thiếu mã đơn cần cập nhật']);
    exit;
}

$allowedStatus = ['received', 'diagnosing', 'awaiting_approval', 'repairing', 'completed', 'cancelled'];

$hasStatus      = array_key_exists('status', $_POST) && $_POST['status'] !== '';
$hasTechnician  = array_key_exists('technician_id', $_POST);
$hasEstimated   = array_key_exists('estimated_cost', $_POST) && $_POST['estimated_cost'] !== '';
$hasFinal       = array_key_exists('final_cost', $_POST) && $_POST['final_cost'] !== '';

if (!$hasStatus && !$hasTechnician && !$hasEstimated && !$hasFinal) {
    echo json_encode(['success' => false, 'message' => 'Không có thay đổi nào để lưu']);
    exit;
}
if ($hasStatus && !in_array($_POST['status'], $allowedStatus, true)) {
    echo json_encode(['success' => false, 'message' => 'Trạng thái không hợp lệ']);
    exit;
}

try {
    $pdo->beginTransaction();

    $check = $pdo->prepare('SELECT status FROM repair_orders WHERE id = ?');
    $check->execute([$orderId]);
    $existing = $check->fetch();

    if (!$existing) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy đơn này']);
        exit;
    }

    $sets = [];
    $params = [];

    if ($hasStatus) {
        $sets[] = 'status = ?';
        $params[] = $_POST['status'];
        if ($_POST['status'] === 'completed') {
            $sets[] = 'completed_at = NOW()';
        }
    }
    if ($hasTechnician) {
        $techId = $_POST['technician_id'] !== '' ? (int)$_POST['technician_id'] : null;
        $sets[] = 'technician_id = ?';
        $params[] = $techId;
    }
    if ($hasEstimated) {
        $sets[] = 'estimated_cost = ?';
        $params[] = (int)$_POST['estimated_cost'];
    }
    if ($hasFinal) {
        $sets[] = 'final_cost = ?';
        $params[] = (int)$_POST['final_cost'];
    }

    $params[] = $orderId;
    $pdo->prepare('UPDATE repair_orders SET ' . implode(', ', $sets) . ' WHERE id = ?')
        ->execute($params);

    // Chỉ ghi lịch sử khi status thực sự thay đổi (để dashboard khách hàng vẽ đúng stepper)
    if ($hasStatus && $_POST['status'] !== $existing['status']) {
        $pdo->prepare(
            'INSERT INTO order_status_history (order_id, status, note, changed_by) VALUES (?, ?, ?, ?)'
        )->execute([$orderId, $_POST['status'], trim($_POST['note'] ?? '') ?: null, $adminId]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Đã cập nhật đơn']);

} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    error_log('update_order_status.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống, vui lòng thử lại sau']);
}
