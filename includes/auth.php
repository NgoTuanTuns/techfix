<?php
/**
 * includes/auth.php
 * Authentication and role-based access control guards for API endpoints.
 * Note: Call session_start() before invoking these helpers.
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

/** Allow access if the current user holds any of the specified roles. */
function require_any_role(array $roles): void
{
    require_login();
    if (!in_array($_SESSION['role'] ?? '', $roles, true)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Bạn không có quyền truy cập chức năng này']);
        exit;
    }
}
