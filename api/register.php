<?php
/**
 * api/register.php
 * Accepts POST: full_name, phone, email, password, password2
 * Returns JSON: {success: bool, message: string, data?: {...}}
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

$full_name = trim($_POST['full_name'] ?? '');
$phone     = preg_replace('/[\s.\-]/', '', trim($_POST['phone'] ?? ''));
$email     = trim($_POST['email'] ?? '');
$password  = (string)($_POST['password'] ?? '');
$password2 = (string)($_POST['password2'] ?? '');

// ---------- Input Validation ----------
$errors = [];

if ($full_name === '' || mb_strlen($full_name) < 2) {
    $errors[] = 'Vui lòng nhập họ tên đầy đủ';
}

if (!preg_match('/^0[0-9]{9}$/', $phone)) {
    $errors[] = 'Số điện thoại không hợp lệ (10 số, bắt đầu bằng 0)';
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Vui lòng nhập địa chỉ Email hợp lệ';
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

// ---------- Account Creation ----------
try {
    ensure_order_payment_schema($pdo);

    // Ensure phone number is not already registered
    $checkPhone = $pdo->prepare('SELECT id FROM users WHERE phone = ?');
    $checkPhone->execute([$phone]);
    if ($checkPhone->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Số điện thoại này đã được đăng ký']);
        exit;
    }

    // Ensure email is not already registered
    $checkEmail = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $checkEmail->execute([$email]);
    if ($checkEmail->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Địa chỉ Email này đã được đăng ký']);
        exit;
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $activationToken = bin2hex(random_bytes(32));

    $stmt = $pdo->prepare(
        'INSERT INTO users (full_name, phone, email, password_hash, role, is_active, activation_token) ' .
        'VALUES (?, ?, ?, ?, ?, 0, ?)'
    );
    $stmt->execute([$full_name, $phone, $email, $passwordHash, 'customer', $activationToken]);

    $userId = (int)$pdo->lastInsertId();

    // Auto-login after registration and regenerate session ID to prevent session fixation
    session_regenerate_id(true);
    $_SESSION['user_id']   = $userId;
    $_SESSION['role']      = 'customer';
    $_SESSION['full_name'] = $full_name;
    $_SESSION['is_active'] = 0;

    // Send account activation email
    $mailRes = send_activation_email($email, $full_name, $activationToken);

    $msg = 'Đăng ký thành công! Vui lòng kiểm tra email (bao gồm cả mục Thư rác / Spam) để kích hoạt tài khoản.';
    if (!empty($mailRes['preview_file']) && empty($mailRes['sent']) && ($mailRes['mode'] ?? '') === 'preview_saved') {
        $msg .= ' (Bản xem trước email đã được lưu tại ' . $mailRes['preview_file'] . ')';
    }

    echo json_encode([
        'success'      => true,
        'message'      => $msg,
        'preview_file' => $mailRes['preview_file'] ?? null,
        'data'         => [
            'id'        => $userId,
            'full_name' => $full_name,
            'phone'     => $phone,
            'email'     => $email,
            'role'      => 'customer',
            'is_active' => 0,
        ],
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log('register.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống, vui lòng thử lại sau']);
}
