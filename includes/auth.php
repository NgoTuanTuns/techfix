<?php
/**
 * includes/auth.php
 * Gọi require_login() hoặc require_role($role) ở đầu mỗi API cần chặn truy cập.
 * Lưu ý: file gọi hàm này phải session_start() trước đó.
 */

function require_login(): void
{
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập để tiếp tục']);
        exit;
    }
}

function require_role(string $role): void
{
    require_login();
    if (($_SESSION['role'] ?? '') !== $role) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Bạn không có quyền truy cập chức năng này']);
        exit;
    }
}
