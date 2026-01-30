<?php
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/config.php';
}

class Auth
{
    private static function startSession()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function requireLogin()
    {
        self::startSession();

        if (!isset($_SESSION['user_id'])) {
            // Simpan URL saat ini untuk redirect setelah login
            if (isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] != '/login.php') {
                $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
            }

            $_SESSION['error'] = 'Silakan login terlebih dahulu';

            // Redirect ke login page
            self::redirect('login.php');
            exit;
        }

        return true;
    }

    public static function requireAdmin()
    {
        self::requireLogin();

        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            $_SESSION['error'] = 'Akses ditolak! Halaman ini hanya untuk admin';
            self::redirect('user/dashboard.php');
            exit;
        }

        return true;
    }

    public static function redirectIfLoggedIn()
    {
        self::startSession();

        if (isset($_SESSION['user_id'])) {
            // Periksa apakah ada redirect_url yang disimpan
            if (isset($_SESSION['redirect_url'])) {
                $redirect_url = $_SESSION['redirect_url'];
                unset($_SESSION['redirect_url']);
                self::redirect($redirect_url);
                exit;
            }

            // Redirect berdasarkan role
            if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
                self::redirect('admin/dashboard.php');
            } else {
                self::redirect('user/dashboard.php');
            }
            exit;
        }
    }

    public static function login($username, $password, $remember = false)
    {
        global $pdo;

        self::startSession();

        try {
            // Clean input
            $username = trim($username);

            // Debug logging
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                error_log("=== LOGIN ATTEMPT ===");
                error_log("Username: " . $username);
                error_log("Session ID before: " . session_id());
            }

            // Cari user berdasarkan username atau email
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();

            if ($user) {
                if (defined('DEBUG_MODE') && DEBUG_MODE) {
                    error_log("User found: " . $user['username']);
                    error_log("User ID: " . $user['id']);
                    error_log("User Role: " . $user['role']);
                }

                // CEK PASSWORD
                $password_valid = false;

                // Password sudah di-hash
                if (password_verify($password, $user['password'])) {
                    $password_valid = true;

                    if (defined('DEBUG_MODE') && DEBUG_MODE) {
                        error_log("Password verification: password_verify() SUCCESS");
                    }

                    // Jika hash perlu di-upgrade/rehash
                    if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                        $new_hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $stmt->execute([$new_hash, $user['id']]);
                    }
                }
                
                // Password masih plain text
                else if ($password === $user['password']) {
                    $password_valid = true;

                    if (defined('DEBUG_MODE') && DEBUG_MODE) {
                        error_log("Password verification: Plain text match SUCCESS");
                    }

                    // Hash password lama untuk keamanan
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashed_password, $user['id']]);
                }

                // Password menggunakan hash MD5 
                else if (md5($password) === $user['password']) {
                    $password_valid = true;

                    if (defined('DEBUG_MODE') && DEBUG_MODE) {
                        error_log("Password verification: MD5 match SUCCESS");
                    }

                    // Konversi ke hash modern
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashed_password, $user['id']]);
                }

                if ($password_valid) {
                    if (defined('DEBUG_MODE') && DEBUG_MODE) {
                        error_log("Setting user session...");
                    }

                    $result = self::setUserSession($user, $remember);

                    if (defined('DEBUG_MODE') && DEBUG_MODE) {
                        error_log("setUserSession returned: " . ($result ? 'TRUE' : 'FALSE'));
                        error_log("Session user_id after: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NOT SET'));
                        error_log("Session role after: " . (isset($_SESSION['role']) ? $_SESSION['role'] : 'NOT SET'));
                        error_log("Session ID after: " . session_id());
                    }

                    return $result;
                }

                // Debug jika password tidak valid
                if (defined('DEBUG_MODE') && DEBUG_MODE) {
                    error_log("Password verification FAILED for user: " . $user['username']);
                }
            } else {
                if (defined('DEBUG_MODE') && DEBUG_MODE) {
                    error_log("User NOT found: " . $username);
                }
            }

            return false;

        } catch (PDOException $e) {
            error_log("Login database error: " . $e->getMessage());
            return false;
        } catch (Exception $e) {
            error_log("Login general error: " . $e->getMessage());
            return false;
        }
    }

    private static function setUserSession($user, $remember)
    {
        self::startSession();

        // Regenerate session ID untuk keamanan
        if (!headers_sent()) {
            session_regenerate_id(true);
        }

        // Set session data
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();

        // Force session write
        session_write_close();
        session_start();

        // Jika remember me dicentang, set cookie
        if ($remember) {
            // Buat token remember me
            $token = bin2hex(random_bytes(32));
            $expires = time() + (30 * 24 * 60 * 60); 

            // Simpan token di database jika tabel ada
            global $pdo;
            try {
                $stmt = $pdo->prepare("INSERT INTO user_tokens (user_id, token, expires_at) VALUES (?, ?, FROM_UNIXTIME(?)) 
                                      ON DUPLICATE KEY UPDATE token = ?, expires_at = FROM_UNIXTIME(?)");
                $stmt->execute([$user['id'], $token, $expires, $token, $expires]);

                // Set cookie
                setcookie('remember_token', $token, $expires, '/', '', false, true);
                setcookie('remember_user', $user['username'], $expires, '/', '', false, true);
            } catch (Exception $e) {
                // Ignore jika tabel tidak ada
                error_log("Remember me error (table might not exist): " . $e->getMessage());
            }
        }

        // Update last login
        global $pdo;
        try {
            $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
        } catch (Exception $e) {
            error_log("Update last login error: " . $e->getMessage());
        }

        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("Session data set:");
            error_log("  user_id: " . $_SESSION['user_id']);
            error_log("  username: " . $_SESSION['username']);
            error_log("  role: " . $_SESSION['role']);
        }

        return true;
    }

    public static function logout()
    {
        self::startSession();

        // Hapus remember me token jika ada
        if (isset($_COOKIE['remember_token'])) {
            global $pdo;
            try {
                $stmt = $pdo->prepare("DELETE FROM user_tokens WHERE token = ?");
                $stmt->execute([$_COOKIE['remember_token']]);
            } catch (Exception $e) {
                // Ignore error
            }

            // Hapus cookie
            setcookie('remember_token', '', time() - 3600, '/');
            setcookie('remember_user', '', time() - 3600, '/');
        }

        // Hapus remembered username cookie (kompatibilitas)
        setcookie('remembered_username', '', time() - 3600, '/');

        // Hapus semua data session
        $_SESSION = array();

        // Hancurkan session
        if (session_id() != "") {
            session_destroy();
        }

        // Hapus cookie session
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        // Redirect ke login page
        self::redirect('login.php?logout=success');
        exit;
    }

    public static function getUser()
    {
        self::startSession();

        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        global $pdo;

        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();

            // Periksa session timeout
            if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 3600)) {
                // Session expired setelah 1 jam
                self::logout();
                return null;
            }

            // Update last activity
            $_SESSION['last_activity'] = time();

            return $user;
        } catch (PDOException $e) {
            error_log("Get user error: " . $e->getMessage());
            return null;
        }
    }

    public static function isAdmin()
    {
        self::startSession();
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }

    public static function isUser()
    {
        self::startSession();
        return isset($_SESSION['role']) && $_SESSION['role'] === 'user';
    }

    public static function getRole()
    {
        self::startSession();
        return isset($_SESSION['role']) ? $_SESSION['role'] : null;
    }

    public static function checkSession()
    {
        self::startSession();

        // Cek remember me terlebih dahulu
        self::checkRememberMe();

        // Cek apakah user login
        if (!isset($_SESSION['user_id'])) {
            return false;
        }

        // Cek session timeout
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 3600)) {
            self::logout();
            return false;
        }

        // Update last activity
        $_SESSION['last_activity'] = time();

        return true;
    }

    private static function redirect($url)
    {
        if (!headers_sent()) {
            header('Location: ' . $url);
            exit;
        } else {
            echo '<script>window.location.href="' . $url . '";</script>';
            echo '<noscript><meta http-equiv="refresh" content="0;url=' . $url . '"></noscript>';
            exit;
        }
    }

}
?>