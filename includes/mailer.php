<?php
/**
 * includes/mailer.php
 * 1. Auto-migrates the `repair_orders` table schema (adds `customer_email` and `awaiting_payment` status)
 *    for seamless compatibility with existing databases.
 * 2. Sends "Awaiting Payment" notification emails over Gmail SMTP using native PHP SSL/TLS sockets
 *    (no Composer dependencies required) and saves an HTML preview copy in `uploads/emails/`.
 */

/**
 * Ensure `repair_orders` has the `customer_email` column and `awaiting_payment` status ENUM value.
 * Also ensure `users` table has columns for email activation and password reset.
 */
function ensure_order_payment_schema(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        // 1. Check if `customer_email` column exists in `repair_orders`
        $colStmt = $pdo->query("SHOW COLUMNS FROM repair_orders LIKE 'customer_email'");
        if (!$colStmt->fetch()) {
            $pdo->exec("ALTER TABLE repair_orders ADD COLUMN customer_email VARCHAR(100) NULL AFTER delivery_phone");
        }

        // 2. Check if `status` ENUM in `repair_orders` includes 'awaiting_payment'
        $statusStmt = $pdo->query("SHOW COLUMNS FROM repair_orders LIKE 'status'");
        $statusCol = $statusStmt->fetch();
        if ($statusCol && isset($statusCol['Type']) && strpos($statusCol['Type'], 'awaiting_payment') === false) {
            $pdo->exec(
                "ALTER TABLE repair_orders MODIFY COLUMN status " .
                "ENUM('pending_receipt', 'received', 'diagnosing', 'awaiting_approval', 'repairing', 'awaiting_payment', 'completed', 'cancelled') " .
                "NOT NULL DEFAULT 'pending_receipt'"
            );
        }

        // 3. Ensure user auth & activation columns exist in `users`
        $userCols = [];
        $uStmt = $pdo->query("SHOW COLUMNS FROM users");
        while ($row = $uStmt->fetch(PDO::FETCH_ASSOC)) {
            $userCols[strtolower($row['Field'])] = true;
        }

        if (empty($userCols['avatar'])) {
            $pdo->exec("ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL AFTER email");
        }
        if (empty($userCols['is_active'])) {
            $pdo->exec("ALTER TABLE users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 0 AFTER role");
        }
        if (empty($userCols['activation_token'])) {
            $pdo->exec("ALTER TABLE users ADD COLUMN activation_token VARCHAR(64) NULL AFTER is_active");
        }
        if (empty($userCols['reset_token'])) {
            $pdo->exec("ALTER TABLE users ADD COLUMN reset_token VARCHAR(64) NULL AFTER activation_token");
        }
        if (empty($userCols['reset_token_expires_at'])) {
            $pdo->exec("ALTER TABLE users ADD COLUMN reset_token_expires_at DATETIME NULL AFTER reset_token");
        }
    } catch (PDOException $e) {
        error_log('ensure_order_payment_schema warning: ' . $e->getMessage());
    }
}

/**
 * Read a multi-line response from the SMTP server socket.
 */
function smtp_read_response($socket): string
{
    $response = '';
    while ($line = fgets($socket, 515)) {
        $response .= $line;
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }
    return $response;
}

/**
 * Send an SMTP command and assert that the response code is in the expected list.
 */
function smtp_command($socket, string $cmd, array $expectedCodes): string
{
    fwrite($socket, $cmd . "\r\n");
    $res = smtp_read_response($socket);
    $code = (int)substr($res, 0, 3);
    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException("SMTP lỗi (lệnh: {$cmd}): " . trim($res));
    }
    return $res;
}

/**
 * Establish an authenticated SMTP socket connection to the mail server.
 * Tries port 587 (TLS/STARTTLS) and port 465 (SSL) with automatic failover.
 *
 * @param string $host
 * @param array<int> $ports
 * @param string $user
 * @param string $pass
 * @param int $timeout Connection timeout in seconds per attempt
 * @return array{socket: resource, port: int}
 */
function smtp_connect_session(string $host, array $ports, string $user, string $pass, int $timeout = 6): array
{
    $errors = [];

    foreach ($ports as $port) {
        $socket = null;
        try {
            $isDirectSsl = ($port === 465);
            $remote = ($isDirectSsl ? 'ssl://' : 'tcp://') . $host . ':' . $port;
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                    'peer_name'         => $host,
                ],
            ]);

            $errno = 0;
            $errstr = '';
            $socket = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
            if (!$socket) {
                throw new RuntimeException("Kết nối tới {$remote} thất bại: {$errstr} ({$errno})");
            }
            stream_set_timeout($socket, 10);

            $greeting = smtp_read_response($socket);
            if ((int)substr($greeting, 0, 3) !== 220) {
                throw new RuntimeException("Greeting lỗi ({$remote}): " . trim($greeting));
            }

            smtp_command($socket, 'EHLO localhost', [250]);

            if ($port === 587) {
                smtp_command($socket, 'STARTTLS', [220]);
                $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                    $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
                }
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                    $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
                }
                $cryptoOk = @stream_socket_enable_crypto($socket, true, $cryptoMethod);
                if (!$cryptoOk) {
                    $cryptoOk = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                }
                if (!$cryptoOk) {
                    throw new RuntimeException('Không thể bật mã hóa STARTTLS trên cổng 587');
                }
                smtp_command($socket, 'EHLO localhost', [250]);
            }

            // Authenticate
            smtp_command($socket, 'AUTH LOGIN', [334]);
            smtp_command($socket, base64_encode($user), [334]);
            smtp_command($socket, base64_encode($pass), [235]);

            return ['socket' => $socket, 'port' => $port];
        } catch (Throwable $e) {
            $errors[] = "[Cổng {$port}] " . $e->getMessage();
            if ($socket && is_resource($socket)) {
                @fwrite($socket, "QUIT\r\n");
                @fclose($socket);
            }
        }
    }

    throw new RuntimeException('Không thể kết nối và xác thực SMTP qua các cổng [' . implode(', ', $ports) . ']: ' . implode('; ', $errors));
}

/**
 * Send an HTML email via Gmail SMTP (SSL/TLS) or save a local HTML preview if SMTP is not configured.
 *
 * @return array{sent: bool, mode: string, message: string, preview_file?: string}
 */
function send_techfix_email(string $toEmail, string $toName, string $subject, string $htmlBody): array
{
    $cfg = require __DIR__ . '/../config/mail.php';
    $host = trim($cfg['smtp_host'] ?? 'smtp.gmail.com');
    $configuredPort = (int)($cfg['smtp_port'] ?? 587);
    $user = trim($cfg['smtp_user'] ?? '');
    $pass = str_replace(' ', '', trim($cfg['smtp_pass'] ?? ''));
    $fromName = $cfg['from_name'] ?? 'TechFix';

    // Prioritize port 587 first as it is standard and not blocked by Vietnamese ISPs, then 465
    $ports = ($configuredPort === 465) ? [587, 465] : array_unique(array_filter([$configuredPort, 587, 465]));

    // Always archive an HTML copy in uploads/emails/ for local inspection and demo purposes
    $previewDir = __DIR__ . '/../uploads/emails/';
    if (!is_dir($previewDir)) {
        @mkdir($previewDir, 0755, true);
    }
    $previewFile = 'uploads/emails/email_' . date('Ymd_His') . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $toEmail) . '.html';
    @file_put_contents(__DIR__ . '/../' . $previewFile, $htmlBody);

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $encodedFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
    $encodedToName = '=?UTF-8?B?' . base64_encode($toName ?: $toEmail) . '?=';

    // If Gmail SMTP credentials are configured, send via SSL/TLS socket
    if ($user !== '' && $pass !== '') {
        try {
            $session = smtp_connect_session($host, $ports, $user, $pass, 6);
            $socket = $session['socket'];
            $activePort = $session['port'];

            smtp_command($socket, "MAIL FROM:<{$user}>", [250]);
            smtp_command($socket, "RCPT TO:<{$toEmail}>", [250, 251]);
            smtp_command($socket, 'DATA', [354]);

            $domain = substr(strrchr($user, "@"), 1) ?: 'techfix.vn';
            $msgId = '<' . time() . '.' . bin2hex(random_bytes(8)) . '@' . $domain . '>';

            $headers = [
                'Date: ' . date('r'),
                "From: {$encodedFromName} <{$user}>",
                "To: {$encodedToName} <{$toEmail}>",
                "Reply-To: {$user}",
                "Subject: {$encodedSubject}",
                "Message-ID: {$msgId}",
                'X-Mailer: TechFix Mailer v1.0',
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'Content-Transfer-Encoding: base64',
            ];

            $data = implode("\r\n", $headers) . "\r\n\r\n"
                  . chunk_split(base64_encode($htmlBody)) . "\r\n.";
            smtp_command($socket, $data, [250]);
            @fwrite($socket, "QUIT\r\n");
            @fclose($socket);

            return [
                'sent'         => true,
                'mode'         => 'smtp',
                'message'      => "Đã gửi email thông báo tới {$toEmail} qua cổng {$activePort}",
                'preview_file' => $previewFile,
            ];
        } catch (Throwable $e) {
            error_log('SMTP send error: ' . $e->getMessage());
            return [
                'sent'         => false,
                'mode'         => 'smtp_error',
                'message'      => "Lỗi gửi SMTP tới {$toEmail} (đã lưu bản xem trước tại {$previewFile}): " . $e->getMessage(),
                'preview_file' => $previewFile,
            ];
        }
    }

    // Fallback when SMTP credentials are not configured: attempt native mail() and return preview file path
    $mailHeaders = implode("\r\n", [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        "From: {$encodedFromName} <no-reply@techfix.local>",
    ]);
    $nativeSent = @mail($toEmail, $encodedSubject, $htmlBody, $mailHeaders);

    return [
        'sent'         => $nativeSent,
        'mode'         => $nativeSent ? 'mail' : 'preview_saved',
        'message'      => $nativeSent
            ? "Đã gửi email thông báo tới {$toEmail}"
            : "Đã lưu nội dung email cho {$toEmail} (điền Gmail App Password trong config/mail.php để gửi thật)",
        'preview_file' => $previewFile,
    ];
}

/**
 * Build and send the notification email when an order reaches "awaiting_payment" status.
 */
function send_awaiting_payment_email(array $orderInfo): array
{
    $toEmail      = trim($orderInfo['customer_email'] ?? '');
    $customerName = htmlspecialchars($orderInfo['customer_name'] ?? 'Quý khách', ENT_QUOTES, 'UTF-8');
    $orderCode    = htmlspecialchars($orderInfo['order_code'] ?? '', ENT_QUOTES, 'UTF-8');
    $deviceLabel  = htmlspecialchars(trim(($orderInfo['brand'] ?? '') . ' ' . ($orderInfo['model'] ?? '')), ENT_QUOTES, 'UTF-8');
    $issue        = htmlspecialchars($orderInfo['issue_description'] ?? '', ENT_QUOTES, 'UTF-8');
    $costNum      = (float)($orderInfo['final_cost'] ?: $orderInfo['estimated_cost'] ?: 0);
    $costText     = $costNum > 0 ? number_format($costNum, 0, ',', '.') . 'đ' : 'Theo báo giá tại quầy';

    $subject = "[TechFix] Đơn #{$orderCode} đã sửa xong — Vui lòng đến kiểm tra máy và thanh toán";

    $htmlBody = <<<HTML
<!doctype html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>{$subject}</title>
</head>
<body style="margin:0;padding:24px;background:#f2f1e6;font-family:'Segoe UI',Arial,sans-serif;color:#1b2b4b;">
  <div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #cbd3d6;box-shadow:0 8px 24px rgba(27,43,75,0.1);">
    <div style="background:linear-gradient(135deg,#1b2b4b,#22415a);padding:24px 28px;color:#ffffff;">
      <div style="font-size:13px;text-transform:uppercase;letter-spacing:1px;color:#f0a35e;font-weight:700;">TechFix — Thông báo đơn sửa chữa</div>
      <h2 style="margin:8px 0 0;font-size:22px;">Máy của bạn đã sửa xong!</h2>
    </div>
    <div style="padding:28px;">
      <p style="margin-top:0;font-size:15px;line-height:1.6;">Xin chào <strong>{$customerName}</strong>,</p>
      <p style="font-size:15px;line-height:1.6;background:#fff4e6;border-left:4px solid #c2540f;padding:14px 16px;border-radius:6px;color:#1b2b4b;">
        <strong>Đơn của bạn đã hoàn tất sửa chữa, vui lòng đến cửa hàng để kiểm tra máy và thanh toán.</strong>
      </p>
      <table style="width:100%;border-collapse:collapse;margin:20px 0;font-size:14px;">
        <tr>
          <td style="padding:10px 0;border-bottom:1px solid #e3e9ec;color:#4a5876;">Mã đơn hàng</td>
          <td style="padding:10px 0;border-bottom:1px solid #e3e9ec;font-weight:700;text-align:right;">#{$orderCode}</td>
        </tr>
        <tr>
          <td style="padding:10px 0;border-bottom:1px solid #e3e9ec;color:#4a5876;">Thiết bị</td>
          <td style="padding:10px 0;border-bottom:1px solid #e3e9ec;font-weight:600;text-align:right;">{$deviceLabel}</td>
        </tr>
        <tr>
          <td style="padding:10px 0;border-bottom:1px solid #e3e9ec;color:#4a5876;">Tình trạng / Yêu cầu</td>
          <td style="padding:10px 0;border-bottom:1px solid #e3e9ec;text-align:right;">{$issue}</td>
        </tr>
        <tr>
          <td style="padding:10px 0;border-bottom:1px solid #e3e9ec;color:#4a5876;">Trạng thái hiện tại</td>
          <td style="padding:10px 0;border-bottom:1px solid #e3e9ec;font-weight:700;color:#c2540f;text-align:right;">Chờ thanh toán</td>
        </tr>
        <tr>
          <td style="padding:12px 0;color:#1b2b4b;font-weight:700;font-size:15px;">Số tiền cần thanh toán</td>
          <td style="padding:12px 0;font-weight:800;color:#c2540f;font-size:18px;text-align:right;">{$costText}</td>
        </tr>
      </table>
      <p style="font-size:14px;line-height:1.6;color:#4a5876;margin-bottom:0;">
        📍 <strong>Địa chỉ cửa hàng:</strong> 109 Đường ABC, Quận 10, TP.HCM<br>
        ☎️ <strong>Hotline hỗ trợ:</strong> 1900 6363 (7:30 – 21:00)<br>
        🛡️ Bảo hành 6 tháng sẽ được kích hoạt ngay sau khi bạn kiểm tra máy và hoàn tất thanh toán tại quầy.
      </p>
    </div>
  </div>
</body>
</html>
HTML;

    return send_techfix_email($toEmail, $orderInfo['customer_name'] ?? 'Quý khách', $subject, $htmlBody);
}

/**
 * Helper to determine project base URL for email links.
 */
function get_project_base_url(): string
{
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    $projectRoot = preg_replace('#/(api|public|includes)(/.*)?$#i', '', $scriptDir);
    return rtrim($protocol . $host . $projectRoot, '/');
}

/**
 * Send an account activation email containing the activation link.
 */
function send_activation_email(string $toEmail, string $fullName, string $token): array
{
    $safeName = htmlspecialchars($fullName ?: 'Quý khách', ENT_QUOTES, 'UTF-8');
    $baseUrl  = get_project_base_url();
    $actUrl   = $baseUrl . '/api/activate_account.php?token=' . urlencode($token);

    $subject = '[TechFix] Kích hoạt tài khoản thành viên của bạn';

    $htmlBody = <<<HTML
<!doctype html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>{$subject}</title>
</head>
<body style="margin:0;padding:24px;background:#f2f1e6;font-family:'Segoe UI',Arial,sans-serif;color:#1b2b4b;">
  <div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #cbd3d6;box-shadow:0 8px 24px rgba(27,43,75,0.1);">
    <div style="background:linear-gradient(135deg,#1b2b4b,#22415a);padding:24px 28px;color:#ffffff;">
      <div style="font-size:13px;text-transform:uppercase;letter-spacing:1px;color:#f0a35e;font-weight:700;">TechFix — Chào mừng thành viên mới</div>
      <h2 style="margin:8px 0 0;font-size:22px;">Kích hoạt tài khoản TechFix</h2>
    </div>
    <div style="padding:28px;">
      <p style="margin-top:0;font-size:15px;line-height:1.6;">Xin chào <strong>{$safeName}</strong>,</p>
      <p style="font-size:15px;line-height:1.6;color:#4a5876;">
        Cảm ơn bạn đã đăng ký tài khoản tại hệ thống sửa chữa thiết bị <strong>TechFix</strong>. Để hoàn tất việc xác thực và kích hoạt đầy đủ quyền hạn cho tài khoản của bạn, vui lòng nhấn vào nút kích hoạt bên dưới:
      </p>
      <div style="text-align:center;margin:30px 0;">
        <a href="{$actUrl}" style="display:inline-block;padding:14px 32px;background:#c2540f;color:#ffffff;text-decoration:none;font-weight:700;font-size:16px;border-radius:10px;box-shadow:0 4px 12px rgba(194,84,15,0.35);">
          ✓ Kích hoạt tài khoản ngay
        </a>
      </div>
      <p style="font-size:13px;line-height:1.6;color:#8a99ad;">
        Hoặc bạn có thể sao chép và dán liên kết sau vào trình duyệt:<br>
        <a href="{$actUrl}" style="color:#c2540f;word-break:break-all;">{$actUrl}</a>
      </p>
      <hr style="border:none;border-top:1px solid #e3e9ec;margin:24px 0;">
      <p style="font-size:13px;line-height:1.6;color:#8a99ad;margin-bottom:0;">
        Nếu bạn không thực hiện đăng ký tài khoản này, vui lòng bỏ qua email này.<br>
        ☎️ Hotline hỗ trợ: 1900 6363 (7:30 – 21:00 mỗi ngày).
      </p>
    </div>
  </div>
</body>
</html>
HTML;

    return send_techfix_email($toEmail, $fullName, $subject, $htmlBody);
}

/**
 * Send a password reset email containing a time-limited reset link.
 */
function send_password_reset_email(string $toEmail, string $fullName, string $token): array
{
    $safeName = htmlspecialchars($fullName ?: 'Quý khách', ENT_QUOTES, 'UTF-8');
    $baseUrl  = get_project_base_url();
    $resetUrl = $baseUrl . '/public/reset-password.html?token=' . urlencode($token);

    $subject = '[TechFix] Yêu cầu đặt lại mật khẩu của bạn';

    $htmlBody = <<<HTML
<!doctype html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>{$subject}</title>
</head>
<body style="margin:0;padding:24px;background:#f2f1e6;font-family:'Segoe UI',Arial,sans-serif;color:#1b2b4b;">
  <div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #cbd3d6;box-shadow:0 8px 24px rgba(27,43,75,0.1);">
    <div style="background:linear-gradient(135deg,#1b2b4b,#22415a);padding:24px 28px;color:#ffffff;">
      <div style="font-size:13px;text-transform:uppercase;letter-spacing:1px;color:#f0a35e;font-weight:700;">TechFix — Khôi phục mật khẩu</div>
      <h2 style="margin:8px 0 0;font-size:22px;">Yêu cầu đặt lại mật khẩu</h2>
    </div>
    <div style="padding:28px;">
      <p style="margin-top:0;font-size:15px;line-height:1.6;">Xin chào <strong>{$safeName}</strong>,</p>
      <p style="font-size:15px;line-height:1.6;color:#4a5876;">
        Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản liên kết với địa chỉ email <strong>{$toEmail}</strong> tại TechFix.
      </p>
      <p style="font-size:14px;line-height:1.6;background:#fff4e6;border-left:4px solid #c2540f;padding:12px 14px;border-radius:6px;color:#1b2b4b;">
        ⏱️ Liên kết này chỉ có hiệu lực trong vòng <strong>30 phút</strong>.
      </p>
      <div style="text-align:center;margin:30px 0;">
        <a href="{$resetUrl}" style="display:inline-block;padding:14px 32px;background:#c2540f;color:#ffffff;text-decoration:none;font-weight:700;font-size:16px;border-radius:10px;box-shadow:0 4px 12px rgba(194,84,15,0.35);">
          🔑 Đặt lại mật khẩu ngay
        </a>
      </div>
      <p style="font-size:13px;line-height:1.6;color:#8a99ad;">
        Hoặc sao chép đường dẫn này vào trình duyệt nếu nút bấm không mở được:<br>
        <a href="{$resetUrl}" style="color:#c2540f;word-break:break-all;">{$resetUrl}</a>
      </p>
      <hr style="border:none;border-top:1px solid #e3e9ec;margin:24px 0;">
      <p style="font-size:13px;line-height:1.6;color:#8a99ad;margin-bottom:0;">
        Nếu bạn không gửi yêu cầu này, vui lòng bỏ qua thư này, mật khẩu cũ của bạn vẫn hoàn toàn an toàn.<br>
        ☎️ Hotline hỗ trợ: 1900 6363.
      </p>
    </div>
  </div>
</body>
</html>
HTML;

    return send_techfix_email($toEmail, $fullName, $subject, $htmlBody);
}
