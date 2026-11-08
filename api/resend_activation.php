<?php
/**
 * api/resend_activation.php
 * Resends the account activation email to the authenticated user or specified email address.
 * Accepts POST: optional email (if not logged in) or uses $_SESSION['user_id'].
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

$userId = (int)($_SESSION['user_id'] ?? 0);
$email  = trim($_POST['email'] ?? '');

try {
    ensure_order_payment_schema($pdo);

    if ($userId > 0) {
        $stmt = $pdo->prepare('SELECT id, full_name, email, is_active FROM users WHERE id = ?');
        $stmt->execute([$userId]);
    } elseif ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = $pdo->prepare('SELECT id, full_name, email, is_active FROM users WHERE email = ?');
        $stmt->execute([$email]);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Vui lòng cung cấp địa chỉ email hợp lệ']);
        exit;
    }

    $user = $stmt->fetch();
    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy tài khoản']);
        exit;
    }

    if ((int)$user['is_active'] === 1) {
        echo json_encode(['success' => true, 'message' => 'Tài khoản này đã được kích hoạt trước đó rồi']);
        exit;
    }

    if (empty($user['email'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Tài khoản chưa cập nhật địa chỉ email']);
        exit;
    }

    $token = bin2hex(random_bytes(32));
    $upd = $pdo->prepare('UPDATE users SET activation_token = ? WHERE id = ?');
    $upd->execute([$token, $user['id']]);

    $mailRes = send_activation_email($user['email'], $user['full_name'], $token);

    $msg = 'Đã gửi lại email kích hoạt tới ' . $user['email'] . '. Vui lòng kiểm tra hộp thư đến (hoặc thư mục Thư rác / Spam).';
    if (!empty($mailRes['preview_file']) && empty($mailRes['sent']) && ($mailRes['mode'] ?? '') === 'preview_saved') {
        $msg .= ' (Bản xem trước: ' . $mailRes['preview_file'] . ')';
    }

    echo json_encode([
        'success'      => true,
        'message'      => $msg,
        'preview_file' => $mailRes['preview_file'] ?? null,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log('resend_activation.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống, vui lòng thử lại sau']);
}
