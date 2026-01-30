<?php
// ================= SESSION =================
session_name('roncelizz_session');

$cookieDomain = $_SERVER['HTTP_HOST'] ?? '';

session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/',
    'domain' => $cookieDomain,
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================= DATABASE (ENV) =================
define('DB_HOST', getenv('MYSQLHOST') ?: '127.0.0.1');
define('DB_PORT', getenv('MYSQLPORT') ?: 3306);
define('DB_NAME', getenv('MYSQLDATABASE') ?: 'roncelizz_db');
define('DB_USER', getenv('MYSQLUSER') ?: 'root');
define('DB_PASS', getenv('MYSQLPASSWORD') ?: '');
define('DB_CHARSET', 'utf8mb4');

// ================= APP CONFIG =================
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

define('BASE_URL', $protocol . '://' . $host);
define('SITE_NAME', 'Roncelizz');
define('SITE_DESCRIPTION', 'Toko Online Manik-Manik Eksklusif');

// ================= TIMEZONE =================
date_default_timezone_set('Asia/Jakarta');

// ================= MODE =================
define('DEBUG_MODE', getenv('APP_DEBUG') === 'true');

// ================= UPLOAD =================
define('UPLOAD_PATH', __DIR__ . '/../assets/images/products/');
define('MAX_UPLOAD_SIZE', 2 * 1024 * 1024);
define('ALLOWED_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// ================= DATABASE CONNECT =================
try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT .
           ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

} catch (PDOException $e) {
    if (DEBUG_MODE) {
        die("Database Connection Error: " . $e->getMessage());
    }
    die("Database connection failed.");
}

// ================= ERROR REPORT =================
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// ================= HELPERS =================
function redirect($url)
{
    if (preg_match('#^https?://#', $url)) {
        header("Location: $url");
    } else {
        header("Location: " . rtrim(BASE_URL, '/') . '/' . ltrim($url, '/'));
    }
    exit;
}

function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

function isAdmin()
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function formatRupiah($amount)
{
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

function getCurrentDate()
{
    return date('Y-m-d H:i:s');
}

function sanitize($data)
{
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}
