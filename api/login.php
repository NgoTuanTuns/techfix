<?php
/**
 * api/login.php
 * Nhận POST: phone, password
 * Trả JSON: {success: bool, message: string, data?: {id, full_name, role}}
 */

session_start();
require __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Phương thức không được hỗ trợ']);
    exit;
}

$phone    = trim($_POST['phone'] ?? '');
$password = (string)($_POST['password'] ?? '');

if (!preg_match('/^0[0-9]{9}$/', $phone)) {
    echo json_encode(['success' => false, 'message' => 'Số điện thoại không hợp lệ']);
    exit;
}
if ($password === '') {
    echo json_encode(['success' => false, 'message' => 'Vui lòng nhập mật khẩu']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id, full_name, password_hash, role FROM users WHERE phone = ?');
    $stmt->execute([$phone]);
    $user = $stmt->fetch();

    // Không nói rõ "sai SĐT" hay "sai mật khẩu" riêng biệt — tránh lộ thông tin SĐT nào đã đăng ký
    if (!$user || !password_verify($password, $user['password_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Số điện thoại hoặc mật khẩu không đúng']);
        exit;
    }

    $_SESSION['user_id']   = $user['id'];
    $_SESSION['role']      = $user['role'];
    $_SESSION['full_name'] = $user['full_name'];

    echo json_encode([
        'success' => true,
        'message' => 'Đăng nhập thành công',
        'data'    => [
            'id'        => $user['id'],
            'full_name' => $user['full_name'],
            'role'      => $user['role'],
        ],
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log('login.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống, vui lòng thử lại sau']);
}
