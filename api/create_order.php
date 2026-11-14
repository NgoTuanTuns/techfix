<?php
/**
 * api/create_order.php
 * Requires an authenticated session.
 * Accepts POST (multipart/form-data): device, brand, model, issue, method, time,
 *   customer_email (required), delivery_address (optional), delivery_phone (optional),
 *   service (optional), photo (optional file)
 * Returns JSON: {success: bool, message: string, data?: {order_code, order_id}}
 */

session_start();
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/mailer.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Phương thức không được hỗ trợ']);
    exit;
}

require_login();
ensure_order_payment_schema($pdo);
$userId = $_SESSION['user_id'];

$deviceType      = trim($_POST['device'] ?? '');
$brand           = trim($_POST['brand'] ?? '');
$model           = trim($_POST['model'] ?? '');
$issue           = trim($_POST['issue'] ?? '');
$method          = trim($_POST['method'] ?? '');
$time            = trim($_POST['time'] ?? '');   // datetime-local format: 2026-10-09T14:30
$serviceSlug     = trim($_POST['service'] ?? '');
$deliveryAddress = trim($_POST['delivery_address'] ?? '');
$deliveryPhone   = trim($_POST['delivery_phone'] ?? '');
$customerEmail   = trim($_POST['customer_email'] ?? '');

// ---------- Input Validation ----------
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
if ($customerEmail === '' || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Vui lòng nhập địa chỉ Email hợp lệ để nhận thông báo trạng thái đơn hàng';
}

// Doorstep pickup requires delivery address & contact phone, and ignores preferred_time
if ($method === 'Nhận máy tận nơi') {
    $time = '';
    if ($deliveryAddress === '') {
        $errors[] = 'Vui lòng nhập địa chỉ nhận máy tận nơi';
    }
    if ($deliveryPhone === '') {
        $errors[] = 'Vui lòng nhập số điện thoại liên lạc nhận máy';
    }
} elseif ($time !== '') {
    // In-store drop-off must fall within store opening hours (07:30 - 21:00)
    if (!preg_match('/^\d{4}-\d{2}-\d{2}[T ](\d{2}):(\d{2})/', $time, $timeMatches)) {
        $errors[] = 'Định dạng thời gian mong muốn không hợp lệ';
    } else {
        $totalMinutes = ((int)$timeMatches[1]) * 60 + ((int)$timeMatches[2]);
        if ($totalMinutes < (7 * 60 + 30) || $totalMinutes > (21 * 60)) {
            $errors[] = 'Thời gian tiếp nhận tại cửa hàng phải nằm trong giờ làm việc từ 07:30 đến 21:00';
        }
    }
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode('. ', $errors)]);
    exit;
}

// Convert "2026-10-09T14:30" (datetime-local input) -> "2026-10-09 14:30:00" (MySQL DATETIME)
$preferredTime = null;
if ($time !== '') {
    $normalized = str_replace('T', ' ', $time);
    if (strlen($normalized) === 16) { // Append seconds if omitted
        $normalized .= ':00';
    }
    $preferredTime = $normalized;
}

// ---------- Optional Photo Upload ----------
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

// ---------- Create Order in Transaction ----------
try {
    $pdo->beginTransaction();

    // 0. Backfill user email if the account does not have one saved yet
    $updEmail = $pdo->prepare('UPDATE users SET email = ? WHERE id = ? AND (email IS NULL OR email = "")');
    try {
        $updEmail->execute([$customerEmail, $userId]);
    } catch (PDOException $ignoreDup) {
        // Ignore if the email is already bound to another user record
    }

    // 1. Insert device record linked to the current user
    $insDevice = $pdo->prepare(
        'INSERT INTO devices (user_id, device_type, brand, model) VALUES (?, ?, ?, ?)'
    );
    $insDevice->execute([$userId, $deviceType, $brand, $model]);
    $deviceId = (int)$pdo->lastInsertId();

    $servicePrice = null;

    // 2. Insert repair order record including customer_email
    $insOrder = $pdo->prepare(
        'INSERT INTO repair_orders
            (order_code, customer_id, device_id, issue_description, delivery_method, delivery_address, delivery_phone, customer_email, preferred_time, estimated_cost, status)
         VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, NULL, "pending_receipt")'
    );
    $insOrder->execute([
        $userId,
        $deviceId,
        $issue,
        $method ?: null,
        $deliveryAddress ?: null,
        $deliveryPhone ?: null,
        $customerEmail,
        $preferredTime,
    ]);
    $orderId = (int)$pdo->lastInsertId();

    $orderCode = 'TF-' . str_pad((string)$orderId, 4, '0', STR_PAD_LEFT);
    $pdo->prepare('UPDATE repair_orders SET order_code = ? WHERE id = ?')
        ->execute([$orderCode, $orderId]);

    // 3. Link selected service package (if any) and pre-fill estimated_cost
    if ($serviceSlug !== '') {
        // Ensure weekly deal packages exist in the services catalog
        $pdo->exec(
            "INSERT IGNORE INTO services (slug, device_type, name, base_price) VALUES
             ('thay-pin-laptop', 'laptop', 'Thay pin laptop Dell, Asus, HP (Ưu đãi -20%)', 450000),
             ('thay-man-hinh-dt', 'phone', 'Thay màn hình iPhone, Samsung, Oppo (Ưu đãi -20%)', 1050000),
             ('nang-cap-ssd', 'laptop', 'Nâng cấp SSD 256GB cho laptop (Ưu đãi -15%)', 590000)"
        );

        $svc = $pdo->prepare('SELECT id, base_price FROM services WHERE slug = ? AND is_active = 1');
        $svc->execute([$serviceSlug]);
        $serviceRow = $svc->fetch();

        if ($serviceRow) {
            $servicePrice = (float)$serviceRow['base_price'];
            $pdo->prepare('INSERT INTO order_services (order_id, service_id, price) VALUES (?, ?, ?)')
                ->execute([$orderId, $serviceRow['id'], $servicePrice]);

            // Only set estimated_cost when base_price > 0 (0 VND packages require manual diagnosis quote)
            // Add 20,000 VND pickup fee for doorstep delivery to stay consistent with update_order_status.php
            if ($servicePrice > 0) {
                $shippingFee = ($method === 'Nhận máy tận nơi') ? 20000 : 0;
                $pdo->prepare('UPDATE repair_orders SET estimated_cost = ? WHERE id = ?')
                    ->execute([$servicePrice + $shippingFee, $orderId]);
            }
        }
    }

    // 4. Save attachment record if a photo was uploaded
    if ($uploadedPath !== null) {
        $pdo->prepare('INSERT INTO order_attachments (order_id, file_path) VALUES (?, ?)')
            ->execute([$orderId, $uploadedPath]);
    }

    // 5. Record initial status history entry
    $pdo->prepare(
        'INSERT INTO order_status_history (order_id, status, note, changed_by) VALUES (?, "pending_receipt", ?, ?)'
    )->execute([$orderId, 'Khách đặt lịch online (Chưa tiếp nhận máy)', $userId]);

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
