<?php
/**
 * api/register.php
 * Nhận POST: full_name, phone, password, password2
 * Trả JSON: {success: bool, message: string, data?: {...}}
 */

session_start();
require __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Phương thức không được hỗ trợ']);
    exit;
}

$full_name = trim($_POST['full_name'] ?? '');
$phone = preg_replace('/[\s.\-]/', '', trim($_POST['phone'] ?? ''));
$password  = (string)($_POST['password'] ?? '');
$password2 = (string)($_POST['password2'] ?? '');

// ---------- Validate dữ liệu đầu vào ----------
$errors = [];

if ($full_name === '' || mb_strlen($full_name) < 2) {
    $errors[] = 'Vui lòng nhập họ tên đầy đủ';
}

if (!preg_match('/^0[0-9]{9}$/', $phone)) {
    $errors[] = 'Số điện thoại không hợp lệ (10 số, bắt đầu bằng 0)';
}

if (strlen($password) < 8) {
    $errors[] = 'Mật khẩu phải có ít nhất 8 ký tự';
}

if ($password !== $password2) {
    $errors[] = 'Mật khẩu nhập lại không khớp';
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode('. ', $errors)]);
    exit;
}

// ---------- Xử lý ----------
try {
    // Kiểm tra số điện thoại đã tồn tại chưa
    $check = $pdo->prepare('SELECT id FROM users WHERE phone = ?');
    $check->execute([$phone]);
    if ($check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Số điện thoại này đã được đăng ký']);
        exit;
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare(
        'INSERT INTO users (full_name, phone, password_hash, role) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$full_name, $phone, $passwordHash, 'customer']);

    $userId = (int)$pdo->lastInsertId();

    // Tự động đăng nhập sau khi đăng ký thành công
    $_SESSION['user_id']   = $userId;
    $_SESSION['role']      = 'customer';
    $_SESSION['full_name'] = $full_name;

    echo json_encode([
        'success' => true,
        'message' => 'Đăng ký thành công',
        'data'    => [
            'id'        => $userId,
            'full_name' => $full_name,
            'phone'     => $phone,
        ],
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    // Không trả chi tiết lỗi SQL ra ngoài cho client, chỉ ghi log phía server
    error_log('register.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống, vui lòng thử lại sau']);
}