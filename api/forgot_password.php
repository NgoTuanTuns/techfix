<?php
/**
 * api/forgot_password.php
 * Accepts POST: email
 * Generates a password reset token valid for 30 minutes and emails a reset link.
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

$email = trim($_POST['email'] ?? '');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Vui lòng nhập địa chỉ email hợp lệ']);
    exit;
}

try {
    ensure_order_payment_schema($pdo);

    $stmt = $pdo->prepare('SELECT id, full_name, email FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    $previewFile = null;
    $mailSent = false;

    if ($user) {
        $token = bin2hex(random_bytes(32));

        $upd = $pdo->prepare('UPDATE users SET reset_token = ?, reset_token_expires_at = DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE id = ?');
        $upd->execute([$token, $user['id']]);

        $mailRes = send_password_reset_email($user['email'], $user['full_name'], $token);
        $mailSent = !empty($mailRes['sent']);
        $previewFile = $mailRes['preview_file'] ?? null;
    }

    $message = 'Nếu email tồn tại trên hệ thống, liên kết đặt lại mật khẩu đã được gửi đến hòm thư của bạn. Vui lòng kiểm tra email (bao gồm cả mục Thư rác / Spam).';
    if ($user && !$mailSent && !empty($mailRes['mode']) && $mailRes['mode'] === 'smtp_error') {
        // If SMTP delivery encountered a temporary network issue
        $message = 'Đã tạo yêu cầu đặt lại mật khẩu. Tuy nhiên máy chủ gửi thư phản hồi chậm hoặc gián đoạn kết nối. Vui lòng thử lại sau giây lát.';
    }

    echo json_encode([
        'success'      => true,
        'message'      => $message,
        'preview_file' => $previewFile,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log('forgot_password.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống, vui lòng thử lại sau']);
}
