<?php
/**
 * api/approve_quote.php
 * Handles customer quote responses (approve, request re-quote, or reject/cancel order).
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

require_login();
$customerId = $_SESSION['user_id'];

$orderId = (int)($_POST['order_id'] ?? 0);
$action = $_POST['action'] ?? ''; // 'approve', 'request_requote', or 'reject'

if ($orderId <= 0 || !in_array($action, ['approve', 'request_requote', 'reject'], true)) {
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT id, status FROM repair_orders WHERE id = ? AND customer_id = ?');
    $stmt->execute([$orderId, $customerId]);
    $order = $stmt->fetch();

    if (!$order || $order['status'] !== 'awaiting_approval') {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Đơn hàng không khả dụng hoặc không ở trạng thái chờ duyệt giá']);
        exit;
    }

    if ($action === 'approve') {
        $newStatus = 'repairing';
        $note = 'Khách hàng đã duyệt giá, chuyển sang sửa chữa';
    } elseif ($action === 'request_requote') {
        $newStatus = 'diagnosing';
        $note = 'Khách hàng yêu cầu kiểm tra và báo giá lại';
    } else {
        $newStatus = 'cancelled';
        $note = 'Khách hàng đã hủy đơn do không đồng ý mức giá';
    }

    $update = $pdo->prepare('UPDATE repair_orders SET status = ? WHERE id = ?');
    $update->execute([$newStatus, $orderId]);

    $history = $pdo->prepare(
        'INSERT INTO order_status_history (order_id, status, note, changed_by) VALUES (?, ?, ?, ?)'
    );
    $history->execute([$orderId, $newStatus, $note, $customerId]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Đã cập nhật trạng thái đơn hàng']);

} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    error_log('approve_quote.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống, vui lòng thử lại sau']);
}
