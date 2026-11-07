<?php
/**
 * api/update_profile.php
 * Yêu cầu đã đăng nhập. Nhận POST: full_name, phone, current_password (optional), new_password (optional)
 * Chỉ đổi mật khẩu khi có cả current_password đúng và new_password hợp lệ.
 * Trả JSON: {success: bool, message: string, data?: {full_name, phone}}
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
$userId = $_SESSION['user_id'];

$fullName        = trim($_POST['full_name'] ?? '');
$phone           = trim($_POST['phone'] ?? '');
$currentPassword = (string)($_POST['current_password'] ?? '');
$newPassword     = (string)($_POST['new_password'] ?? '');

$errors = [];
if (mb_strlen($fullName) < 2) {
    $errors[] = 'Vui lòng nhập họ tên đầy đủ';
}
if (!preg_match('/^0[0-9]{9}$/', $phone)) {
    $errors[] = 'Số điện thoại không hợp lệ (10 số, bắt đầu bằng 0)';
}
if ($newPassword !== '' && strlen($newPassword) < 8) {
    $errors[] = 'Mật khẩu mới phải có ít nhất 8 ký tự';
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode('. ', $errors)]);
    exit;
}

try {
    // Số điện thoại không được trùng với tài khoản khác (trừ chính mình)
    $check = $pdo->prepare('SELECT id FROM users WHERE phone = ? AND id != ?');
    $check->execute([$phone, $userId]);
    if ($check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Số điện thoại này đã được dùng bởi tài khoản khác']);
        exit;
    }

    if ($newPassword !== '') {
        // Đổi mật khẩu bắt buộc phải xác thực đúng mật khẩu hiện tại trước
        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($currentPassword, $row['password_hash'])) {
            echo json_encode(['success' => false, 'message' => 'Mật khẩu hiện tại không đúng']);
            exit;
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE users SET full_name = ?, phone = ?, password_hash = ? WHERE id = ?')
            ->execute([$fullName, $phone, $newHash, $userId]);
    } else {
        $pdo->prepare('UPDATE users SET full_name = ?, phone = ? WHERE id = ?')
            ->execute([$fullName, $phone, $userId]);
    }

    $_SESSION['full_name'] = $fullName;

    echo json_encode([
        'success' => true,
        'message' => 'Cập nhật thông tin thành công',
        'data'    => ['full_name' => $fullName, 'phone' => $phone],
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log('update_profile.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống, vui lòng thử lại sau']);
}
