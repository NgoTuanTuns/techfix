<?php
/**
 * api/reset_password.php
 * Accepts POST: token, password, password2
 * Verifies the reset token, updates password hash, and clears the token.
 * Requires the user to log in manually afterwards.
 * Returns JSON: {success: bool, message: string}
 */

session_start();
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/mailer.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Phương thức không được hỗ trợ']);
    exit;
}

$token     = trim($_POST['token'] ?? '');
$password  = (string)($_POST['password'] ?? '');
$password2 = (string)($_POST['password2'] ?? '');

$errors = [];
if ($token === '') {
    $errors[] = 'Mã xác thực không hợp lệ hoặc bị thiếu';
}
if (strlen($password) < 8) {
    $errors[] = 'Mật khẩu mới phải có ít nhất 8 ký tự';
}
if ($password !== $password2) {
    $errors[] = 'Mật khẩu nhập lại không khớp';
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => implode('. ', $errors)]);
    exit;
}

try {
    ensure_order_payment_schema($pdo);

    $stmt = $pdo->prepare('SELECT id, full_name, email, reset_token_expires_at, (reset_token_expires_at > NOW()) AS is_future_sql FROM users WHERE reset_token = ?');
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Liên kết đặt lại mật khẩu không hợp lệ hoặc đã được sử dụng trước đó. Vui lòng gửi lại yêu cầu mới.',
        ]);
        exit;
    }

    $isFutureSql = !empty($user['is_future_sql']);
    $isFuturePhp = false;
    if (!empty($user['reset_token_expires_at'])) {
        $expTime = strtotime($user['reset_token_expires_at']);
        if ($expTime > (time() - 300) || abs(time() - $expTime) < 3600) {
            $isFuturePhp = true;
        }
    }

    if (!$isFutureSql && !$isFuturePhp) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Liên kết đặt lại mật khẩu đã hết hạn (chỉ có hiệu lực trong 30 phút). Vui lòng gửi lại yêu cầu mới.',
        ]);
        exit;
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $upd = $pdo->prepare('UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expires_at = NULL WHERE id = ?');
    $upd->execute([$passwordHash, $user['id']]);

    // Destroy active session so the user must log in manually with the new password
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_unset();
        session_destroy();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Đặt lại mật khẩu thành công! Hãy đăng nhập lại với mật khẩu mới của bạn.',
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log('reset_password.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống, vui lòng thử lại sau']);
}
