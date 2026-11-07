<?php
/**
 * api/logout.php
 * Huỷ session hiện tại. Trả JSON: {success: true}
 */

session_start();
$_SESSION = [];
session_destroy();

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => true, 'message' => 'Đã đăng xuất']);
