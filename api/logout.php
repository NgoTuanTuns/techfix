<?php
/**
 * api/logout.php
 * Destroys the active session. Returns JSON: {success: true}
 */

session_start();
$_SESSION = [];
session_destroy();

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => true, 'message' => 'Đã đăng xuất']);
