<?php
/**
 * api/get_parts.php
 * Requires role = admin or technician. Returns all spare parts in inventory.
 * Returns JSON: {success: bool, data?: [{id, name, compatible_with, quantity, unit_cost}], message?: string}
 */

session_start();
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

require_any_role(['admin', 'technician']);

try {
    $stmt = $pdo->query(
        'SELECT id, name, compatible_with, quantity, unit_cost
         FROM inventory_parts ORDER BY id asc'
    );
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log('get_parts.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống, vui lòng thử lại sau']);
}
