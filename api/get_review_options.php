<?php
/** Returns completed repair orders that have not yet been reviewed by the current customer. */

session_start();
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
require_login();

try {
    $stmt = $pdo->prepare(
        'SELECT ro.id, ro.order_code, d.device_type, d.brand, d.model
         FROM repair_orders ro
         JOIN devices d ON d.id = ro.device_id
         LEFT JOIN reviews r ON r.order_id = ro.id
         WHERE ro.customer_id = ? AND ro.status = "completed" AND r.id IS NULL
         ORDER BY ro.completed_at DESC'
    );
    $stmt->execute([$_SESSION['user_id']]);
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
} catch (PDOException $e) {
    http_response_code(500);
    error_log('get_review_options.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Không thể tải đơn để đánh giá']);
}
