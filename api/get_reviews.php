<?php
/** Returns recent public customer reviews for display on the Contact page. */

require __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $colCheck = $pdo->query("SHOW COLUMNS FROM users LIKE 'avatar'");
    if (!$colCheck->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL AFTER phone");
    }

    $stmt = $pdo->query(
        'SELECT r.rating, r.comment, r.created_at,
                u.full_name, u.avatar,
                ro.order_code,
                d.brand, d.model, d.device_type
         FROM reviews r
         JOIN users u ON u.id = r.customer_id
         JOIN repair_orders ro ON ro.id = r.order_id
         LEFT JOIN devices d ON d.id = ro.device_id
         WHERE r.comment IS NOT NULL AND TRIM(r.comment) <> ""
         ORDER BY r.created_at DESC
         LIMIT 12'
    );
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
} catch (PDOException $e) {
    http_response_code(500);
    error_log('get_reviews.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Không thể tải đánh giá lúc này']);
}
