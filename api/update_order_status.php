<?php
/**
 * api/update_order_status.php
 * Yêu cầu role = admin hoặc technician. Nhận POST: order_id (bắt buộc) + bất kỳ tổ hợp nào trong
 *   status, technician_id, estimated_cost, final_cost, note
 * Chỉ cập nhật những trường thực sự được gửi lên (cho phép sửa từng ô trên bảng admin
 * mà không phải gửi lại toàn bộ đơn).
 * Nếu status thay đổi -> tự ghi thêm 1 dòng vào order_status_history.
 * Phân quyền: admin sửa mọi đơn. Kỹ thuật viên chỉ được nhận đơn chưa ai phụ trách về mình
 *   (technician_id = id của chính mình) và chỉ sửa trạng thái/giá của đơn đang giao cho mình.
 * Giá: estimated_cost/final_cost gửi lên là giá sửa CHƯA gồm phí giao nhận; đơn nhận tận nơi
 *   được cộng thêm phí ở đây, nên client phải gửi giá gốc, không gửi lại giá đã lưu trong DB.
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
$actorId = (int)$_SESSION['user_id'];
$isTechnician = ($_SESSION['role'] ?? '') === 'technician';

$orderId = (int)($_POST['order_id'] ?? 0);
if ($orderId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Thiếu mã đơn cần cập nhật']);
    exit;
}

$allowedStatus = ['pending_receipt', 'received', 'diagnosing', 'awaiting_approval', 'repairing', 'completed', 'cancelled'];

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

    // Lấy trạng thái cũ, hình thức nhận máy và người phụ trách (khoá dòng tới khi commit)
    $check = $pdo->prepare(
        'SELECT status, delivery_method, technician_id FROM repair_orders WHERE id = ? FOR UPDATE'
    );
    $check->execute([$orderId]);
    $existing = $check->fetch();

    if (!$existing) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy đơn này']);
        exit;
    }

    $currentTechId = $existing['technician_id'] !== null ? (int)$existing['technician_id'] : null;
    $newTechId = null;
    if ($hasTechnician) {
        $newTechId = $_POST['technician_id'] !== '' ? (int)$_POST['technician_id'] : null;
    }

    if ($isTechnician) {
        // Kỹ thuật viên chỉ được tự nhận đơn chưa ai phụ trách, không giao/bỏ giao cho người khác
        if ($hasTechnician && $newTechId !== $currentTechId
            && !($currentTechId === null && $newTechId === $actorId)) {
            $pdo->rollBack();
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Bạn chỉ có thể tự nhận đơn chưa có người phụ trách']);
            exit;
        }
        $ownerAfter = $hasTechnician ? $newTechId : $currentTechId;
        if (($hasStatus || $hasEstimated || $hasFinal) && $ownerAfter !== $actorId) {
            $pdo->rollBack();
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Hãy nhận đơn về mình trước khi cập nhật trạng thái hoặc giá']);
            exit;
        }
    }

    // Người được giao phải là tài khoản kỹ thuật viên
    if ($hasTechnician && $newTechId !== null) {
        $techCheck = $pdo->prepare("SELECT 1 FROM users WHERE id = ? AND role = 'technician'");
        $techCheck->execute([$newTechId]);
        if (!$techCheck->fetch()) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Người được giao không phải kỹ thuật viên']);
            exit;
        }
    }

    // RÀNG BUỘC: Nếu đơn đang chờ duyệt giá (awaiting_approval) mà cố chuyển sang repairing hoặc completed thì chặn[cite: 22]
    if ($hasStatus) {
        $newStatus = $_POST['status'];
        if ($existing['status'] === 'awaiting_approval' && in_array($newStatus, ['repairing', 'completed'], true)) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Khách hàng chưa duyệt giá, không thể chuyển sang trạng thái này!']);
            exit;
        }
    }

    // Kiểm tra xem đơn có phải nhận tận nơi không để tự động cộng 20k phí ship vào CSDL
    $isDelivery = ($existing['delivery_method'] === 'Nhận máy tận nơi');
    $shippingFee = $isDelivery ? 20000 : 0;

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
        $sets[] = 'technician_id = ?';
        $params[] = $newTechId;
    }
    if ($hasEstimated) {
        $sets[] = 'estimated_cost = ?';
        // Cộng thêm 20k vào giá ước tính thực tế lưu xuống CSDL nếu là nhận tận nơi
        $params[] = (int)$_POST['estimated_cost'] + $shippingFee;
    }
    if ($hasFinal) {
        $sets[] = 'final_cost = ?';
        // Cộng thêm 20k vào giá cuối thực tế lưu xuống CSDL nếu là nhận tận nơi
        $params[] = (int)$_POST['final_cost'] + $shippingFee;
    }

    $params[] = $orderId;
    $pdo->prepare('UPDATE repair_orders SET ' . implode(', ', $sets) . ' WHERE id = ?')
        ->execute($params);

    // Chỉ ghi lịch sử khi status thực sự thay đổi (để dashboard khách hàng vẽ đúng stepper)[cite: 22]
    if ($hasStatus && $_POST['status'] !== $existing['status']) {
        $pdo->prepare(
            'INSERT INTO order_status_history (order_id, status, note, changed_by) VALUES (?, ?, ?, ?)'
        )->execute([$orderId, $_POST['status'], trim($_POST['note'] ?? '') ?: null, $actorId]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Đã cập nhật đơn']);

} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    error_log('update_order_status.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống, vui lòng thử lại sau']);
}
