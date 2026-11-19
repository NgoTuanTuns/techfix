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

function refresh_session_role_if_needed(): string
{
    global $pdo;
    if (isset($_SESSION['user_id']) && isset($pdo) && $pdo instanceof PDO) {
        try {
            $stmt = $pdo->prepare('SELECT role, full_name, is_active FROM users WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $row = $stmt->fetch();
            if ($row) {
                $_SESSION['role']      = $row['role'];
                $_SESSION['full_name'] = $row['full_name'];
                $_SESSION['is_active'] = (int)($row['is_active'] ?? 0);
            }
        } catch (Throwable $ignore) {
        }
    }
    return (string)($_SESSION['role'] ?? '');
}

function require_role(string $role): void
{
    require_login();
    $currentRole = $_SESSION['role'] ?? '';
    if ($currentRole !== $role) {
        $currentRole = refresh_session_role_if_needed();
    }
    if ($currentRole !== $role) {
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
    $currentRole = $_SESSION['role'] ?? '';
    if (!in_array($currentRole, $roles, true)) {
        $currentRole = refresh_session_role_if_needed();
    }
    if (!in_array($currentRole, $roles, true)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Bạn không có quyền truy cập chức năng này']);
        exit;
    }
}
