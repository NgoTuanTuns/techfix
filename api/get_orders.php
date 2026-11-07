<?php
/**
 * api/get_orders.php
 * Requires an authenticated session.
 * Returns all repair orders belonging to the logged-in customer along with status history for the progress stepper.
 * Returns JSON: {success: bool, data?: [...], message?: string}
 */

session_start();
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/mailer.php';

header('Content-Type: application/json; charset=utf-8');

require_login();
ensure_order_payment_schema($pdo);

$customerId = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare(
        'SELECT ro.id, ro.order_code, ro.issue_description, ro.status,
                ro.estimated_cost, ro.final_cost, ro.received_at, ro.completed_at,
                ro.delivery_method, ro.delivery_address, ro.delivery_phone,
                COALESCE(ro.customer_email, cu.email) AS customer_email,
                d.device_type, d.brand, d.model
         FROM repair_orders ro
         JOIN devices d ON d.id = ro.device_id
         JOIN users cu ON cu.id = ro.customer_id
         WHERE ro.customer_id = ?
         ORDER BY ro.received_at DESC'
    );
    $stmt->execute([$customerId]);
    $orders = $stmt->fetchAll();

    // Fetch status transition history for each order to render the dashboard timeline stepper
    $historyStmt = $pdo->prepare(
        'SELECT status, note, changed_at
         FROM order_status_history
         WHERE order_id = ?
         ORDER BY changed_at ASC'
    );

    foreach ($orders as &$order) {
        $historyStmt->execute([$order['id']]);
        $order['history'] = $historyStmt->fetchAll();
    }
    unset($order);

    echo json_encode(['success' => true, 'data' => $orders]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log('get_orders.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống, vui lòng thử lại sau']);
}
