<?php
/**
 * api/activate_account.php
 * Handles user account activation via token sent in email.
 * Accepts GET or POST with parameter: `token`.
 * If opened directly from browser (GET), redirects to dashboard.html or login.html with an alert query parameter.
 * Otherwise returns JSON.
 */

session_start();
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/mailer.php';

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$isJsonRequest = (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
                 ($_SERVER['REQUEST_METHOD'] === 'POST');

if ($token === '') {
    if ($isJsonRequest) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Mã xác thực không hợp lệ hoặc bị thiếu']);
        exit;
    }
    header('Location: ../public/login.html?activation_error=missing');
    exit;
}

try {
    ensure_order_payment_schema($pdo);

    $stmt = $pdo->prepare('SELECT id, full_name, email, is_active FROM users WHERE activation_token = ?');
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        if ($isJsonRequest) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Mã kích hoạt không đúng hoặc tài khoản đã được kích hoạt trước đó']);
            exit;
        }
        header('Location: ../public/login.html?activation_error=invalid');
        exit;
    }

    // Activate the user
    $upd = $pdo->prepare('UPDATE users SET is_active = 1, activation_token = NULL WHERE id = ?');
    $upd->execute([$user['id']]);

    // If currently logged in as this user, update session
    if (!empty($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$user['id']) {
        $_SESSION['is_active'] = 1;
    }

    if ($isJsonRequest) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'message' => 'Tài khoản của bạn đã được kích hoạt thành công!']);
        exit;
    }

    // Redirect to dashboard if logged in, otherwise to login page
    if (!empty($_SESSION['user_id'])) {
        header('Location: ../public/dashboard.html?activated=1');
    } else {
        header('Location: ../public/login.html?activated=1');
    }
    exit;

} catch (PDOException $e) {
    error_log('activate_account.php error: ' . $e->getMessage());
    if ($isJsonRequest) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống, vui lòng thử lại sau']);
        exit;
    }
    header('Location: ../public/login.html?activation_error=server');
    exit;
}
