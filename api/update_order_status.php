<?php
/**
 * api/update_order_status.php
 * Requires role = admin or technician. Accepts POST: order_id (required) + any combination of
 *   status, technician_id, estimated_cost, final_cost, note, or action=send_payment_email.
 * Updates only the provided fields so individual table cells can be edited independently.
 * Logs a row in order_status_history whenever status changes.
 * Returns JSON: {success: bool, message: string, data?: array}
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

require_any_role(['admin', 'technician']);
ensure_order_payment_schema($pdo);
$actorId = (int)$_SESSION['user_id'];
$isTechnician = ($_SESSION['role'] ?? '') === 'technician';

$orderId = (int)($_POST['order_id'] ?? 0);
if ($orderId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Thiếu mã đơn cần cập nhật']);
    exit;
}

$allowedStatus = [
    'pending_receipt',
    'received',
    'diagnosing',
    'awaiting_approval',
    'repairing',
    'awaiting_payment',
    'completed',
    'cancelled',
];

$hasStatus      = array_key_exists('status', $_POST) && $_POST['status'] !== '';
$hasTechnician  = array_key_exists('technician_id', $_POST);
$hasEstimated   = array_key_exists('estimated_cost', $_POST) && $_POST['estimated_cost'] !== '';
$hasFinal       = array_key_exists('final_cost', $_POST) && $_POST['final_cost'] !== '';
$isSendMailOnly = ($_POST['action'] ?? '') === 'send_payment_email';

if (!$hasStatus && !$hasTechnician && !$hasEstimated && !$hasFinal && !$isSendMailOnly) {
    echo json_encode(['success' => false, 'message' => 'Không có thay đổi nào để lưu']);
    exit;
}
if ($hasStatus && !in_array($_POST['status'], $allowedStatus, true)) {
    echo json_encode(['success' => false, 'message' => 'Trạng thái không hợp lệ']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Lock the order row until the transaction commits
    $check = $pdo->prepare(
        'SELECT status, delivery_method, technician_id, estimated_cost, final_cost FROM repair_orders WHERE id = ? FOR UPDATE'
    );
    $check->execute([$orderId]);
    $existing = $check->fetch();

    if (!$existing) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy đơn này']);
        exit;
    }

    $currentTechId = $existing['technician_id'] !== null ? (int)$existing['technician_id'] : null;
    $newTechId = null;
    if ($hasTechnician) {
        $newTechId = $_POST['technician_id'] !== '' ? (int)$_POST['technician_id'] : null;
    }

    if ($isTechnician) {
        // Technicians can only self-claim unassigned orders; they cannot reassign orders to others
        if ($hasTechnician && $newTechId !== $currentTechId
            && !($currentTechId === null && $newTechId === $actorId)) {
            $pdo->rollBack();
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Bạn chỉ có thể tự nhận đơn chưa có người phụ trách']);
            exit;
        }
        $ownerAfter = $hasTechnician ? $newTechId : $currentTechId;
        if (($hasStatus || $hasEstimated || $hasFinal || $isSendMailOnly) && $ownerAfter !== $actorId) {
            $pdo->rollBack();
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Hãy nhận đơn về mình trước khi thao tác trên đơn này']);
            exit;
        }
    }

    // Handle manual "Send Email" button action: only permitted when status is 'awaiting_payment'
    if ($isSendMailOnly) {
        if ($existing['status'] !== 'awaiting_payment') {
            $pdo->rollBack();
            echo json_encode([
                'success' => false,
                'message' => 'Chỉ có thể gửi email thông báo khi đơn đang ở trạng thái "Chờ thanh toán"!',
            ]);
            exit;
        }
        $pdo->commit();

        $infoStmt = $pdo->prepare(
            'SELECT ro.order_code, ro.issue_description, ro.estimated_cost, ro.final_cost,
                    COALESCE(ro.customer_email, cu.email) AS customer_email,
                    cu.full_name AS customer_name,
                    d.brand, d.model
             FROM repair_orders ro
             JOIN users cu ON cu.id = ro.customer_id
             JOIN devices d ON d.id = ro.device_id
             WHERE ro.id = ?'
        );
        $infoStmt->execute([$orderId]);
        $orderInfo = $infoStmt->fetch();

        if (!$orderInfo || empty($orderInfo['customer_email'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Đơn hàng này chưa có địa chỉ email khách hàng để gửi thông báo.',
            ]);
            exit;
        }

        $mailResult = send_awaiting_payment_email($orderInfo);
        echo json_encode([
            'success' => true,
            'message' => $mailResult['message'],
            'data'    => ['mail' => $mailResult],
        ]);
        exit;
    }

    // Verify that the assigned user has the 'technician' role
    if ($hasTechnician && $newTechId !== null) {
        $techCheck = $pdo->prepare("SELECT 1 FROM users WHERE id = ? AND role = 'technician'");
        $techCheck->execute([$newTechId]);
        if (!$techCheck->fetch()) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Người được giao không phải kỹ thuật viên']);
            exit;
        }
    }

    // Automatically add the 20,000 VND pickup fee for doorstep delivery orders
    $isDelivery = ($existing['delivery_method'] === 'Nhận máy tận nơi');
    $shippingFee = $isDelivery ? 20000 : 0;

    // Linear status workflow constraints:
    // 0. Status transitions are strictly one-way (forward-only); completed/cancelled orders are terminal.
    // 1. Orders cannot enter post-approval states (repairing, awaiting_payment, completed) until the customer approves the quote.
    // 2. Orders must pass through 'awaiting_payment' before reaching 'completed'.
    $statusStep = [
        'pending_receipt'   => 0,
        'received'          => 1,
        'diagnosing'        => 2,
        'awaiting_approval' => 3,
        'repairing'         => 4,
        'awaiting_payment'  => 5,
        'completed'         => 6,
        'cancelled'         => 7,
    ];

    if ($hasStatus) {
        $newStatus = $_POST['status'];
        $oldStatus = $existing['status'];

        if ($newStatus !== $oldStatus) {
            if (in_array($oldStatus, ['completed', 'cancelled'], true)) {
                $pdo->rollBack();
                echo json_encode([
                    'success' => false,
                    'message' => 'Đơn hàng đã kết thúc (Hoàn tất / Đã huỷ), không thể thay đổi trạng thái!',
                ]);
                exit;
            }

            if (($statusStep[$newStatus] ?? -1) < ($statusStep[$oldStatus] ?? 0)) {
                $pdo->rollBack();
                echo json_encode([
                    'success' => false,
                    'message' => 'Trạng thái đơn hàng đi theo chiều tuyến tính, không thể quay lại trạng thái trước!',
                ]);
                exit;
            }
        }

        $postApprovalStates = ['repairing', 'awaiting_payment', 'completed'];

        if (in_array($newStatus, $postApprovalStates, true)
            && !in_array($existing['status'], $postApprovalStates, true)) {
            $pdo->rollBack();
            echo json_encode([
                'success' => false,
                'message' => 'Khách hàng chưa duyệt giá, không thể chuyển sang Đang sửa chữa, Chờ thanh toán hoặc Hoàn tất!',
            ]);
            exit;
        }

        if ($newStatus === 'completed' && !in_array($existing['status'], ['awaiting_payment', 'completed'], true)) {
            $pdo->rollBack();
            echo json_encode([
                'success' => false,
                'message' => 'Vui lòng chuyển đơn sang trạng thái "Chờ thanh toán" trước khi Hoàn tất!',
            ]);
            exit;
        }

        $effectiveEstimated = $hasEstimated
            ? ((int)$_POST['estimated_cost'] + $shippingFee)
            : (int)($existing['estimated_cost'] ?? 0);
        if ($newStatus === 'awaiting_approval' && $effectiveEstimated <= 0) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Vui lòng nhập giá ước tính trước khi chuyển sang Chờ duyệt giá!']);
            exit;
        }
    }

    $sets = [];
    $params = [];
    $autoFilledFinalCost = null;

    if ($hasStatus) {
        $sets[] = 'status = ?';
        $params[] = $_POST['status'];
        if ($_POST['status'] === 'completed') {
            $sets[] = 'completed_at = NOW()';
        } elseif ($existing['status'] === 'completed') {
            $sets[] = 'completed_at = NULL';
        }

        // Default final_cost to estimated_cost when entering awaiting_payment or completed if final_cost is unset
        if (in_array($_POST['status'], ['awaiting_payment', 'completed'], true)
            && !$hasFinal
            && (int)($existing['final_cost'] ?? 0) <= 0
            && (int)($existing['estimated_cost'] ?? 0) > 0) {
            $autoFilledFinalCost = (int)$existing['estimated_cost'];
            $sets[] = 'final_cost = ?';
            $params[] = $autoFilledFinalCost;
        }
    }
    if ($hasTechnician) {
        $sets[] = 'technician_id = ?';
        $params[] = $newTechId;
    }
    if ($hasEstimated) {
        $sets[] = 'estimated_cost = ?';
        $params[] = (int)$_POST['estimated_cost'] + $shippingFee;
    }
    if ($hasFinal) {
        $sets[] = 'final_cost = ?';
        $params[] = (int)$_POST['final_cost'] + $shippingFee;
    }

    $params[] = $orderId;
    $pdo->prepare('UPDATE repair_orders SET ' . implode(', ', $sets) . ' WHERE id = ?')
        ->execute($params);

    // Upsert payment record when the order reaches 'completed' with a positive final_cost
    $effectiveStatus = $hasStatus ? $_POST['status'] : $existing['status'];
    $effectiveFinal  = $hasFinal
        ? ((int)$_POST['final_cost'] + $shippingFee)
        : ($autoFilledFinalCost !== null ? $autoFilledFinalCost : (int)($existing['final_cost'] ?? 0));

    if ($effectiveStatus === 'completed' && $effectiveFinal > 0) {
        $allowedMethods = ['cash', 'bank_transfer', 'momo'];
        $payMethod = in_array($_POST['payment_method'] ?? '', $allowedMethods, true)
            ? $_POST['payment_method']
            : 'cash';
        $payCheck = $pdo->prepare('SELECT id FROM payments WHERE order_id = ? LIMIT 1');
        $payCheck->execute([$orderId]);
        $payRow = $payCheck->fetch();
        if ($payRow) {
            $pdo->prepare('UPDATE payments SET amount = ?, paid_at = NOW() WHERE id = ?')
                ->execute([$effectiveFinal, $payRow['id']]);
        } else {
            $pdo->prepare('INSERT INTO payments (order_id, amount, method) VALUES (?, ?, ?)')
                ->execute([$orderId, $effectiveFinal, $payMethod]);
        }
    }

    // Record status transition in order_status_history when status changes
    if ($hasStatus && $_POST['status'] !== $existing['status']) {
        $defaultNote = $_POST['status'] === 'awaiting_payment'
            ? 'Đã sửa xong — Chờ khách kiểm tra máy và thanh toán'
            : null;
        $pdo->prepare(
            'INSERT INTO order_status_history (order_id, status, note, changed_by) VALUES (?, ?, ?, ?)'
        )->execute([$orderId, $_POST['status'], trim($_POST['note'] ?? '') ?: $defaultNote, $actorId]);
    }

    $pdo->commit();

    $msg = 'Đã cập nhật đơn';
    if ($hasStatus && $_POST['status'] === 'awaiting_payment' && $existing['status'] !== 'awaiting_payment') {
        $msg = 'Đã chuyển sang Chờ thanh toán. Nút Gửi mail đã được mở khoá để bạn gửi thông báo cho khách.';
    }

    echo json_encode([
        'success' => true,
        'message' => $msg,
        'data'    => [
            'final_cost' => $effectiveFinal > 0 ? $effectiveFinal : null,
        ],
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    error_log('update_order_status.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống, vui lòng thử lại sau']);
}
