<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Mulai output buffering
ob_start();

// Redirect if already logged in
Auth::redirectIfLoggedIn();

$error = '';
$success = isset($_GET['success']) ? $_GET['success'] : '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']) ? true : false;

    if (empty($username) || empty($password)) {
        $error = 'Username dan password harus diisi';
    } elseif (Auth::login($username, $password, $remember)) {
        // Clear output buffer sebelum redirect
        if (ob_get_length()) {
            ob_end_clean();
        }

        // Check if there's a redirect URL
        if (isset($_SESSION['redirect_url'])) {
            $redirect_url = $_SESSION['redirect_url'];
            unset($_SESSION['redirect_url']);
            header("Location: " . $redirect_url);
            exit;
        } else {
            // Redirect berdasarkan role
            if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
                header("Location: admin/dashboard.php");
            } else {
                header("Location: user/dashboard.php");
            }
            exit;
        }
    } else {
        $error = 'Username atau password salah';
    }
}

// Jika tidak ada redirect, lanjutkan output HTML
if (ob_get_length()) {
    ob_end_flush();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Roncelizz</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Dancing+Script:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .login-page {
            min-height: 100vh;
            background: linear-gradient(135deg, var(--pink-accent) 0%, var(--purple-dark) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        .login-page::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 1000px;
            height: 1000px;
            background: radial-gradient(circle, var(--pink-light) 0%, transparent 70%);
            opacity: 0.3;
            animation: float 20s infinite alternate;
        }

        .login-page::after {
            content: '';
            position: absolute;
            bottom: -50%;
            left: -50%;
            width: 800px;
            height: 800px;
            background: radial-gradient(circle, var(--purple-light) 0%, transparent 70%);
            opacity: 0.3;
            animation: float 25s infinite alternate-reverse;
        }

        .login-container {
            display: flex;
            max-width: 1000px;
            width: 100%;
            background: var(--white);
            border-radius: 25px;
            overflow: hidden;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.1);
            position: relative;
            z-index: 1;
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
        }

        /* Left Side - Branding */
        .login-brand {
            flex: 1;
            background: var(--white);
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .login-brand::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none"><path d="M0,0 L100,0 L100,100 Z" fill="rgba(255,255,255,0.1)"/></svg>');
            background-size: cover;
        }

        .brand-content {
            position: relative;
            z-index: 1;
        }

        .brand-logo {
            font-family: 'Gabriola', cursive;
            font-size: 3.5rem;
            margin-bottom: 20px;
            text-align: center;
            color: purple;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }

        .brand-tagline {
            font-size: 1.2rem;
            text-align: center;
            opacity: 0.9;
            margin-bottom: 40px;
            font-weight: 300;
            line-height: 1.6;
        }

        .brand-features {
            margin-top: 40px;
        }

        .feature-item {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding: 15px;
            background: rgba(70, 0, 71, 0.1);
            border-radius: 12px;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(70, 0, 71, 0.2);
            transition: transform 0.3s ease, background 0.3s ease;
        }

        .feature-item:hover {
            transform: translateX(10px);
            background: rgba(70, 0, 71, 0.15);
        }

        .feature-icon {
            width: 45px;
            height: 45px;
            background: rgba(70, 0, 71, 0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 18px;
            flex-shrink: 0;
        }

        .feature-text {
            font-size: 0.95rem;
            line-height: 1.5;
            color: purple;
        }

        /* Right Side - Login Form */
        .login-form-section {
            flex: 1;
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: var(--white);
        }

        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .login-title {
            font-family: 'Cambria', cursive;
            font-size: 2rem;
            color: purple;
            margin-bottom: 10px;
            font-weight: 600;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark);
            font-weight: 500;
            font-size: 0.95rem;
        }

        .form-control {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid var(--gray-medium);
            border-radius: 12px;
            font-size: 16px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
            background: var(--white);
            color: var(--dark);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--purple);
            box-shadow: 0 0 0 3px rgba(156, 107, 255, 0.1);
        }

        .form-control::placeholder {
            color: var(--gray);
            opacity: 0.7;
        }

        /* Password Input Wrapper */
        .password-input-wrapper {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--gray);
            cursor: pointer;
            font-size: 18px;
            padding: 5px;
            transition: color 0.3s ease;
        }

        .password-toggle:hover {
            color: var(--purple);
        }

        .password-input-wrapper .form-control {
            padding-right: 50px;
        }

        /* Login Options */
        .login-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .remember-me {
            display: flex;
            align-items: center;
        }

        .remember-me input[type="checkbox"] {
            width: 18px;
            height: 18px;
            margin-right: 8px;
            accent-color: var(--purple);
            cursor: pointer;
        }

        .remember-me label {
            color: var(--gray);
            font-size: 0.9rem;
            cursor: pointer;
            user-select: none;
        }

        .forgot-link {
            color: var(--purple);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .forgot-link:hover {
            color: var(--purple-dark);
            text-decoration: underline;
        }

        /* Login Button */
        .login-button {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--pink-accent), var(--purple-dark));
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
            margin-bottom: 25px;
            position: relative;
            overflow: hidden;
        }

        .login-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(255, 107, 147, 0.3);
        }

        .login-button:active {
            transform: translateY(0);
        }

        .login-button::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .login-button:hover::after {
            left: 100%;
        }

        /* Register Link */
        .register-section {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid var(--gray-medium);
        }

        .register-text {
            color: var(--gray);
            font-size: 0.95rem;
            margin-bottom: 8px;
        }

        .register-link {
            color: var(--purple);
            text-decoration: none;
            font-weight: 600;
            font-size: 1rem;
            transition: color 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .register-link:hover {
            color: var(--purple-dark);
            text-decoration: underline;
        }

        .register-link::after {
            content: '→';
            font-size: 1.1rem;
            transition: transform 0.3s ease;
        }

        .register-link:hover::after {
            transform: translateX(5px);
        }

        /* Alerts */
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-size: 0.95rem;
            border: 1px solid transparent;
            animation: slideDown 0.3s ease;
        }

        .alert-danger {
            background: linear-gradient(135deg, #ffe6e6, #ffcccc);
            color: #cc0000;
            border-color: #ffb3b3;
        }

        .alert-success {
            background: linear-gradient(135deg, #e6ffe6, #ccffcc);
            color: #00a854;
            border-color: #b3ffb3;
        }

        /* Logout Message */
        .logout-message {
            background: #f8f9fa;
            padding: 15px 20px;
            border-radius: 12px;
            margin-top: 20px;
            font-size: 0.9rem;
            color: var(--gray);
            border: 1px solid #dee2e6;
            text-align: center;
        }

        /* Animations */
        @keyframes float {
            0%, 100% {
                transform: translate(0, 0) rotate(0deg);
            }
            50% {
                transform: translate(20px, 20px) rotate(5deg);
            }
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .login-container {
                max-width: 800px;
            }
            
            .login-brand,
            .login-form-section {
                padding: 50px 40px;
            }
            
            .brand-logo {
                font-size: 3rem;
            }
        }

        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
                max-width: 500px;
            }
            
            .login-brand {
                padding: 40px 30px;
            }
            
            .login-form-section {
                padding: 40px 30px;
            }
            
            .brand-logo {
                font-size: 2.8rem;
            }
            
            .login-title {
                font-size: 1.8rem;
            }
            
            .feature-item {
                padding: 12px;
            }
            
            .feature-icon {
                width: 40px;
                height: 40px;
                font-size: 16px;
            }
        }

        @media (max-width: 480px) {
            .login-page {
                padding: 10px;
            }
            
            .login-container {
                border-radius: 20px;
            }
            
            .login-brand,
            .login-form-section {
                padding: 30px 20px;
            }
            
            .brand-logo {
                font-size: 2.5rem;
            }
            
            .login-title {
                font-size: 1.6rem;
            }
            
            .form-control {
                padding: 12px 15px;
                font-size: 15px;
            }
            
            .login-button {
                padding: 14px;
                font-size: 15px;
            }
            
            .login-options {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .feature-item {
                font-size: 0.9rem;
            }
        }

        /* Utility Classes */
        .text-center {
            text-align: center;
        }

        .mb-1 {
            margin-bottom: 10px;
        }

        .mb-2 {
            margin-bottom: 20px;
        }

        .mb-3 {
            margin-bottom: 30px;
        }

        .mt-1 {
            margin-top: 10px;
        }

        .mt-2 {
            margin-top: 20px;
        }

        .mt-3 {
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <div class="login-page">
        <div class="login-container">
            <!-- Left Side - Branding -->
            <div class="login-brand">
                <div class="brand-content">
                    <h1 class="brand-logo">Roncelizz</h1>
                    <p class="brand-tagline">Selamat datang ^.^</p>
                    
                    <div class="brand-features">
                        <div class="feature-item">
                            <div class="feature-icon">💎</div>
                            <div class="feature-text">Manik-manik premium dengan kualitas terbaik</div>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">🎨</div>
                            <div class="feature-text">Desain custom sesuai dengan gaya Anda</div>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">🚚</div>
                            <div class="feature-text">Pengiriman cepat dan aman ke seluruh Indonesia</div>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">💝</div>
                            <div class="feature-text">Dibuat dengan penuh cinta dan ketelitian</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side - Login Form -->
            <div class="login-form-section">
                <div class="login-header">
                    <h2 class="login-title">LOGIN</h2>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <strong>Login Gagal:</strong> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <strong>Berhasil:</strong> <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger">
                        <strong>Error:</strong> <?php echo htmlspecialchars($_SESSION['error']); ?>
                        <?php unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="loginForm">
                    <div class="form-group">
                        <label for="username" class="form-label">Username atau Email</label>
                        <input type="text" class="form-control" id="username" name="username"
                            placeholder="Masukkan username atau email Anda" required
                            value="<?php echo isset($_COOKIE['remembered_username']) ? htmlspecialchars($_COOKIE['remembered_username']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <div class="password-input-wrapper">
                            <input type="password" class="form-control" id="password" name="password"
                                placeholder="Masukkan password Anda" required>
                            <button type="button" class="password-toggle" id="togglePassword">
                                👁️
                            </button>
                        </div>
                    </div>

                    <div class="login-options">
                        <div class="remember-me">
                            <input type="checkbox" id="remember" name="remember" 
                                <?php echo isset($_COOKIE['remembered_username']) ? 'checked' : ''; ?>>
                            <label for="remember">Ingat saya</label>
                        </div>
                        <a href="forgot_password.php" class="forgot-link">Lupa Password?</a>
                    </div>

                    <button type="submit" class="login-button" id="loginButton">
                        Masuk Sekarang
                    </button>
                </form>

                <div class="register-section">
                    <p class="register-text">Belum memiliki akun?</p>
                    <a href="register.php" class="register-link">Daftar Sekarang</a>
                </div>

                <?php if (isset($_GET['logout']) && $_GET['logout'] == 'success'): ?>
                    <div class="logout-message mt-3">
                        <strong>Anda telah berhasil logout.</strong> Silakan masuk kembali untuk melanjutkan.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Simple form validation
        document.getElementById('loginForm').addEventListener('submit', function (e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            const loginButton = document.getElementById('loginButton');

            if (!username) {
                e.preventDefault();
                alert('Harap masukkan username atau email');
                return false;
            }

            if (!password) {
                e.preventDefault();
                alert('Harap masukkan password');
                return false;
            }

            if (password.length < 6) {
                e.preventDefault();
                alert('Password minimal 6 karakter');
                return false;
            }

            // Loading state
            loginButton.innerHTML = 'Memproses...';
            loginButton.disabled = true;

            return true;
        });

        // Toggle password visibility
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');

        togglePassword.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.innerHTML = type === 'password' ? '👁️' : '🙈';
        });

        // Auto focus on username field
        document.getElementById('username').focus();

        // Remember me functionality
        const rememberCheckbox = document.getElementById('remember');
        const usernameField = document.getElementById('username');

        // If remember me is checked, save username to cookie
        document.getElementById('loginForm').addEventListener('submit', function () {
            if (rememberCheckbox.checked) {
                // Simpan di cookie untuk 30 hari
                const expiryDate = new Date();
                expiryDate.setTime(expiryDate.getTime() + (30 * 24 * 60 * 60 * 1000));
                document.cookie = "remembered_username=" + encodeURIComponent(usernameField.value) + 
                                  "; expires=" + expiryDate.toUTCString() + 
                                  "; path=/; SameSite=Strict";
            } else {
                // Hapus cookie
                document.cookie = "remembered_username=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/";
            }
        });

        // Add animation to form elements on load
        document.addEventListener('DOMContentLoaded', function() {
            const formElements = document.querySelectorAll('.form-control, .login-button');
            formElements.forEach((element, index) => {
                element.style.animation = `slideIn 0.5s ease-out ${index * 0.1}s both`;
            });
        });

        // Handle form resubmission prevention
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>