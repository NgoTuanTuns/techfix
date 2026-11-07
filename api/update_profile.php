<?php
/**
 * api/update_profile.php
 * Requires an authenticated session. Accepts POST: full_name, phone,
 * current_password (optional), new_password (optional), new_password2 (optional),
 * and optional file upload: avatar (image/jpeg, image/png, image/webp, image/gif <= 2MB).
 * Returns JSON: {success: bool, message: string, data?: {full_name, phone, avatar}}
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
$userId = (int)$_SESSION['user_id'];

$fullName        = trim($_POST['full_name'] ?? '');
$phone           = preg_replace('/[\s.\-]/', '', trim($_POST['phone'] ?? ''));
$currentPassword = (string)($_POST['current_password'] ?? '');
$newPassword     = (string)($_POST['new_password'] ?? '');
$newPassword2    = (string)($_POST['new_password2'] ?? '');

$errors = [];
if (mb_strlen($fullName) < 2) {
    $errors[] = 'Vui lòng nhập họ tên đầy đủ';
}
if (!preg_match('/^0[0-9]{9}$/', $phone)) {
    $errors[] = 'Số điện thoại không hợp lệ (10 số, bắt đầu bằng 0)';
}
if ($newPassword !== '' || $newPassword2 !== '') {
    if ($currentPassword === '') {
        $errors[] = 'Vui lòng nhập mật khẩu hiện tại để đổi mật khẩu mới';
    }
    if (strlen($newPassword) < 8) {
        $errors[] = 'Mật khẩu mới phải có ít nhất 8 ký tự';
    }
    if ($newPassword !== $newPassword2) {
        $errors[] = 'Mật khẩu mới nhập lại không khớp';
    }
}

// Validate optional avatar upload
$uploadedAvatarPath = null;
if (!empty($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['avatar'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Tải ảnh đại diện thất bại, vui lòng thử lại';
    } elseif ($file['size'] > 2 * 1024 * 1024) {
        $errors[] = 'Ảnh đại diện không được vượt quá 2MB';
    } else {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        $allowedMimes = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
        ];
        if (!isset($allowedMimes[$mime])) {
            $errors[] = 'Ảnh đại diện chỉ hỗ trợ định dạng JPG, PNG, WEBP hoặc GIF';
        } else {
            $uploadDir = __DIR__ . '/../uploads/avatars';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $ext = $allowedMimes[$mime];
            $filename = 'avatar_u' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $targetPath = $uploadDir . '/' . $filename;
            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                $errors[] = 'Không thể lưu ảnh đại diện lên máy chủ';
            } else {
                $uploadedAvatarPath = 'uploads/avatars/' . $filename;
            }
        }
    }
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode('. ', $errors)]);
    exit;
}

try {
    // Ensure the avatar column exists on older schemas
    try {
        $pdo->exec('ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL AFTER email');
    } catch (Throwable $ignore) {
        // Column already exists
    }

    // Ensure the phone number is not already taken by another user
    $check = $pdo->prepare('SELECT id FROM users WHERE phone = ? AND id != ?');
    $check->execute([$phone, $userId]);
    if ($check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Số điện thoại này đã được dùng bởi tài khoản khác']);
        exit;
    }

    $currentStmt = $pdo->prepare('SELECT password_hash, avatar FROM users WHERE id = ?');
    $currentStmt->execute([$userId]);
    $userRow = $currentStmt->fetch();

    if (!$userRow) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy tài khoản']);
        exit;
    }

    $avatarToSave = $uploadedAvatarPath !== null ? $uploadedAvatarPath : $userRow['avatar'];

    if ($newPassword !== '') {
        // Verify current password before setting a new one
        if (!password_verify($currentPassword, $userRow['password_hash'])) {
            echo json_encode(['success' => false, 'message' => 'Mật khẩu hiện tại không đúng']);
            exit;
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE users SET full_name = ?, phone = ?, avatar = ?, password_hash = ? WHERE id = ?')
            ->execute([$fullName, $phone, $avatarToSave, $newHash, $userId]);
        // Regenerate session ID after password change to keep the authenticated session secure
        session_regenerate_id(true);
    } else {
        $pdo->prepare('UPDATE users SET full_name = ?, phone = ?, avatar = ? WHERE id = ?')
            ->execute([$fullName, $phone, $avatarToSave, $userId]);
    }

    $_SESSION['full_name'] = $fullName;

    echo json_encode([
        'success' => true,
        'message' => 'Cập nhật thông tin thành công',
        'data'    => [
            'full_name' => $fullName,
            'phone'     => $phone,
            'avatar'    => $avatarToSave,
        ],
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log('update_profile.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống, vui lòng thử lại sau']);
}
