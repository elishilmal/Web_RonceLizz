<?php
session_name('roncelizz_session');
session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/',
    'domain' => 'localhost',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'roncelizz_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Application Configuration
define('BASE_URL', 'http://localhost/web_roncelizz/');
define('SITE_NAME', 'Roncelizz');
define('SITE_DESCRIPTION', 'Toko Online Manik-Manik Eksklusif');

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Development Mode
define('DEBUG_MODE', true);

// File Upload
define('UPLOAD_PATH', 'assets/images/products/');
define('MAX_UPLOAD_SIZE', 2 * 1024 * 1024); // 2MB
define('ALLOWED_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// Connect to Database
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

} catch (PDOException $e) {
    if (DEBUG_MODE) {
        die("Database Connection Error: " . $e->getMessage());
    } else {
        die("Database connection failed. Please try again later.");
    }
}

// Error Reporting
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Helper functions
function redirect($url)
{
    // Jika URL tidak dimulai dengan http/https dan tidak dimulai dengan /
    if (strpos($url, 'http') !== 0 && strpos($url, 'https') !== 0 && strpos($url, '/') !== 0) {
        // Tambahkan / di depan
        $url = '/' . $url;
    }

    // Jika URL sudah lengkap
    if (strpos($url, 'http') === 0 || strpos($url, 'https') === 0) {
        header("Location: " . $url);
    } else {
        // Gunakan BASE_URL
        header("Location: " . rtrim(BASE_URL, '/') . $url);
    }
    exit();
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

// Helper function untuk sanitize input
function sanitize($data)
{
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}
?>