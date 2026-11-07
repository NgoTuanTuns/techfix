<?php
/**
 * config/mail.php
 * Gmail SMTP configuration for order status notification emails.
 * Automatically loads environment variables from the root .env file if present.
 */

if (!function_exists('techfix_load_dotenv')) {
    function techfix_load_dotenv(string $envPath): void
    {
        if (!is_readable($envPath)) {
            return;
        }
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) {
            return;
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }
            [$key, $val] = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val);
            // Strip surrounding single or double quotes if present
            if (strlen($val) >= 2 && (($val[0] === '"' && substr($val, -1) === '"') || ($val[0] === "'" && substr($val, -1) === "'"))) {
                $val = substr($val, 1, -1);
            }
            if ($key !== '' && getenv($key) === false) {
                putenv("{$key}={$val}");
                $_ENV[$key] = $val;
            }
        }
    }
}

techfix_load_dotenv(__DIR__ . '/../.env');

return [
    'smtp_host' => getenv('TECHFIX_SMTP_HOST') ?: 'smtp.gmail.com',
    'smtp_port' => (int)(getenv('TECHFIX_SMTP_PORT') ?: 465), // 465 (SSL) or 587 (TLS)
    'smtp_user' => getenv('TECHFIX_SMTP_USER') ?: '',        // Sender Gmail address
    'smtp_pass' => getenv('TECHFIX_SMTP_PASS') ?: '',        // 16-character Gmail App Password
    'from_name' => getenv('TECHFIX_MAIL_FROM_NAME') ?: 'TechFix — Hệ thống sửa chữa thiết bị',
];
