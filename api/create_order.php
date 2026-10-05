<?php
/**
 * api/create_order.php
 * Yêu cầu đã đăng nhập (Hướng A: không cho đặt lịch khi chưa có tài khoản).
 * Nhận POST (multipart/form-data): device, brand, model, issue, method, time,
 *   service (optional), photo (optional file)
 * Trả JSON: {success: bool, message: string, data?: {order_code, order_id}}
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

$deviceType  = trim($_POST['device'] ?? '');
$brand       = trim($_POST['brand'] ?? '');
$model       = trim($_POST['model'] ?? '');
$issue       = trim($_POST['issue'] ?? '');
$method      = trim($_POST['method'] ?? '');
$time        = trim($_POST['time'] ?? '');   // định dạng datetime-local: 2026-10-09T14:30
$serviceSlug = trim($_POST['service'] ?? '');

// ---------- Validate ----------
$errors = [];
$allowedDevices = ['laptop', 'phone', 'tablet'];

if (!in_array($deviceType, $allowedDevices, true)) {
    $errors[] = 'Loại thiết bị không hợp lệ';
}
if ($brand === '') {
    $errors[] = 'Vui lòng chọn hãng thiết bị';
}
if (mb_strlen($issue) < 5) {
    $errors[] = 'Vui lòng mô tả lỗi rõ hơn (ít nhất 5 ký tự)';
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode('. ', $errors)]);
    exit;
}

// Chuyển "2026-10-09T14:30" (input datetime-local) -> "2026-10-09 14:30:00" (MySQL DATETIME)
$preferredTime = null;
if ($time !== '') {
    $normalized = str_replace('T', ' ', $time);
    if (strlen($normalized) === 16) { // thiếu phần giây
        $normalized .= ':00';
    }
    $preferredTime = $normalized;
}

// ---------- Xử lý ảnh đính kèm (không bắt buộc) ----------
$uploadedPath = null;
if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
    $mime = mime_content_type($_FILES['photo']['tmp_name']);

    if (!in_array($mime, $allowedMime, true)) {
        echo json_encode(['success' => false, 'message' => 'Ảnh phải ở định dạng JPG, PNG hoặc WEBP']);
        exit;
    }
    if ($_FILES['photo']['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Ảnh không được vượt quá 5MB']);
        exit;
    }

    $uploadDir = __DIR__ . '/../uploads/orders/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $ext = $mime === 'image/png' ? 'png' : ($mime === 'image/webp' ? 'webp' : 'jpg');
    $fileName = uniqid('order_', true) . '.' . $ext;

    if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $fileName)) {
        $uploadedPath = 'uploads/orders/' . $fileName;
    }
}

// ---------- Xử lý trong 1 transaction ----------
try {
    $pdo->beginTransaction();

    // 1. Tạo device, gắn với user đang đăng nhập
    $insDevice = $pdo->prepare(
        'INSERT INTO devices (user_id, device_type, brand, model) VALUES (?, ?, ?, ?)'
    );
    $insDevice->execute([$userId, $deviceType, $brand, $model]);
    $deviceId = (int)$pdo->lastInsertId();

    // 2. Tạo đơn sửa chữa (order_code tạm, cập nhật lại ngay sau khi có id)
    $insOrder = $pdo->prepare(
        'INSERT INTO repair_orders
            (order_code, customer_id, device_id, issue_description, delivery_method, preferred_time, status)
         VALUES ("", ?, ?, ?, ?, ?, "received")'
    );
    $insOrder->execute([$userId, $deviceId, $issue, $method ?: null, $preferredTime]);
    $orderId = (int)$pdo->lastInsertId();

    $orderCode = 'TF-' . str_pad((string)$orderId, 4, '0', STR_PAD_LEFT);
    $pdo->prepare('UPDATE repair_orders SET order_code = ? WHERE id = ?')
        ->execute([$orderCode, $orderId]);

    // 3. Nếu khách chọn gói đề cử từ trang chủ -> gắn vào order_services
    if ($serviceSlug !== '') {
        $svc = $pdo->prepare('SELECT id, base_price FROM services WHERE slug = ? AND is_active = 1');
        $svc->execute([$serviceSlug]);
        $serviceRow = $svc->fetch();

        if ($serviceRow) {
            $pdo->prepare('INSERT INTO order_services (order_id, service_id, price) VALUES (?, ?, ?)')
                ->execute([$orderId, $serviceRow['id'], $serviceRow['base_price']]);
        }
    }

    // 4. Lưu ảnh đính kèm nếu có
    if ($uploadedPath !== null) {
        $pdo->prepare('INSERT INTO order_attachments (order_id, file_path) VALUES (?, ?)')
            ->execute([$orderId, $uploadedPath]);
    }

    // 5. Ghi lịch sử trạng thái đầu tiên (để dựng stepper trên dashboard)
    $pdo->prepare(
        'INSERT INTO order_status_history (order_id, status, note, changed_by) VALUES (?, "received", ?, ?)'
    )->execute([$orderId, 'Khách đặt lịch online', $userId]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Đặt lịch thành công',
        'data'    => [
            'order_id'   => $orderId,
            'order_code' => $orderCode,
        ],
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    error_log('create_order.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống, vui lòng thử lại sau']);
}
