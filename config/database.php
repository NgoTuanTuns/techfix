<?php
/**
 * config/database.php
 * Shared PDO database connection.
 * Defaults match standard XAMPP settings; overrides can be set in root .env
 * or via TECHFIX_DB_HOST / TECHFIX_DB_NAME / TECHFIX_DB_USER / TECHFIX_DB_PASS.
 */

date_default_timezone_set('Asia/Ho_Chi_Minh');

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

$host    = getenv('TECHFIX_DB_HOST') ?: 'localhost';
$db      = getenv('TECHFIX_DB_NAME') ?: 'techfix';
$user    = getenv('TECHFIX_DB_USER') ?: 'root';
$pass    = getenv('TECHFIX_DB_PASS') ?: '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // Log detailed error server-side; return a generic JSON response to the client
    error_log('database.php connection error: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Không kết nối được cơ sở dữ liệu, vui lòng thử lại sau',
    ]);
    exit;
}
