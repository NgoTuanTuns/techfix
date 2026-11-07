<?php
/** Saves a customer review for a completed repair order. */

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

$orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
$rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT);
$comment = trim($_POST['comment'] ?? '');

if (!$orderId || !$rating || $rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng chọn đơn và số sao từ 1 đến 5']);
    exit;
}
if (mb_strlen($comment) < 10 || mb_strlen($comment) > 1000) {
    echo json_encode(['success' => false, 'message' => 'Nội dung đánh giá cần có từ 10 đến 1.000 ký tự']);
    exit;
}

try {
    $check = $pdo->prepare(
        'SELECT id FROM repair_orders WHERE id = ? AND customer_id = ? AND status = "completed"'
    );
    $check->execute([$orderId, $_SESSION['user_id']]);
    if (!$check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Bạn chỉ có thể đánh giá đơn đã hoàn tất của mình']);
        exit;
    }

    $insert = $pdo->prepare(
        'INSERT INTO reviews (order_id, customer_id, rating, comment) VALUES (?, ?, ?, ?)'
    );
    $insert->execute([$orderId, $_SESSION['user_id'], $rating, $comment]);
    echo json_encode(['success' => true, 'message' => 'Cảm ơn bạn đã gửi đánh giá!']);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        echo json_encode(['success' => false, 'message' => 'Đơn này đã được đánh giá']);
        exit;
    }
    http_response_code(500);
    error_log('submit_review.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Không thể lưu đánh giá lúc này']);
}
