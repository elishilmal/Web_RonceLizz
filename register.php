<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Mulai output buffering
ob_start();

// Redirect jika sudah login
Auth::redirectIfLoggedIn();

// Inisialisasi variabel
$error = '';
$success = '';
$full_name = '';
$username = '';
$email = '';
$phone = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = sanitize($_POST['full_name'] ?? '');
    $username = sanitize($_POST['username'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validasi input
    if (empty($full_name) || empty($username) || empty($email) || empty($password)) {
        $error = 'Semua field bertanda * harus diisi';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter';
    } elseif ($password !== $confirm_password) {
        $error = 'Password dan konfirmasi password tidak cocok';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid';
    } else {
        try {
            // Cek apakah username sudah terdaftar
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = 'Username sudah digunakan';
            } else {
                // Cek apakah email sudah terdaftar
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = 'Email sudah terdaftar';
                } else {
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    // Simpan ke database
                    $stmt = $pdo->prepare("INSERT INTO users (full_name, username, email, phone, password, role) 
                                           VALUES (?, ?, ?, ?, ?, 'user')");
                    $stmt->execute([$full_name, $username, $email, $phone, $hashed_password]);

                    // Redirect ke halaman login dengan pesan sukses
                    header("Location: login.php?success=Registrasi berhasil! Silakan login.");
                    exit;
                }
            }
        } catch (PDOException $e) {
            error_log("Registration error: " . $e->getMessage());
            $error = 'Terjadi kesalahan sistem. Silakan coba lagi nanti.';
        }
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
    <title>Daftar - Roncelizz</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Dancing+Script:wght@700&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --pink: #ff6b93;
            --pink-light: #ff9ab5;
            --purple: #9c6bff;
            --purple-light: #c6a5ff;
            --mint: #6bffb8;
            --mint-light: #a5ffd6;
            --white: #ffffff;
            --light: #fff5f7;
            --dark: #333333;
            --gray: #666666;
            --gray-light: #f5f5f5;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #ffeff7 0%, #e6f0ff 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 40px 20px;
            position: relative;
            overflow-y: auto;
        }

        body::before {
            content: '';
            position: fixed;
            top: -50%;
            right: -50%;
            width: 1000px;
            height: 1000px;
            background: radial-gradient(circle, var(--pink-light) 0%, transparent 70%);
            opacity: 0.2;
            animation: float 20s infinite alternate;
            z-index: -1;
        }

        body::after {
            content: '';
            position: fixed;
            bottom: -50%;
            left: -50%;
            width: 800px;
            height: 800px;
            background: radial-gradient(circle, var(--purple-light) 0%, transparent 70%);
            opacity: 0.2;
            animation: float 25s infinite alternate-reverse;
            z-index: -1;
        }

        .register-container {
            max-width: 500px;
            width: 100%;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 25px;
            padding: 40px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.1);
            position: relative;
            z-index: 1;
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            margin: auto;
        }

        .logo {
            font-family: 'Dancing Script', cursive;
            font-size: 48px;
            text-align: center;
            color: var(--pink);
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
        }

        .subtitle {
            text-align: center;
            color: var(--gray);
            margin-bottom: 30px;
            font-size: 15px;
            line-height: 1.5;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark);
            font-weight: 500;
            font-size: 14px;
        }

        label::after {
            content: ' *';
            color: var(--pink);
        }

        .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e6e6e6;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.3s;
            font-family: 'Poppins', sans-serif;
            background: white;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--mint);
            box-shadow: 0 0 0 3px rgba(107, 255, 184, 0.2);
        }

        .btn {
            display: inline-block;
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--mint), var(--purple));
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
            text-decoration: none;
            margin-top: 10px;
            font-family: 'Poppins', sans-serif;
            position: relative;
            overflow: hidden;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(107, 255, 184, 0.3);
        }

        .btn::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .btn:hover::after {
            left: 100%;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-size: 14px;
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

        .register-footer {
            text-align: center;
            margin-top: 25px;
            color: var(--gray);
            font-size: 14px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .register-footer a {
            color: var(--purple);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
        }

        .register-footer a:hover {
            color: var(--pink);
            text-decoration: underline;
        }

        .form-row {
            display: flex;
            gap: 15px;
        }

        .form-row .form-group {
            flex: 1;
        }

        /* Password strength indicator */
        .password-strength {
            height: 4px;
            background: #eee;
            border-radius: 2px;
            margin-top: 5px;
            overflow: hidden;
        }

        .strength-bar {
            height: 100%;
            width: 0%;
            background: #ff4444;
            transition: all 0.3s ease;
            border-radius: 2px;
        }

        .strength-text {
            font-size: 0.85rem;
            margin-top: 5px;
            color: #888;
        }

        .password-match {
            font-size: 0.85rem;
            margin-top: 5px;
        }

        .match-ok {
            color: #00C851;
        }

        .match-error {
            color: #ff4444;
        }

        /* Animations */
        @keyframes float {

            0%,
            100% {
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

        /* Responsive Design */
        @media (max-width: 768px) {
            body {
                padding: 20px 15px;
                align-items: flex-start;
            }

            .register-container {
                margin: 0 auto 40px;
                max-width: 100%;
                padding: 30px 20px;
            }

            .logo {
                font-size: 36px;
            }

            .form-row {
                flex-direction: column;
                gap: 0;
            }

            .btn {
                margin-top: 20px;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 15px 10px;
            }

            .register-container {
                padding: 25px 15px;
                border-radius: 20px;
            }

            .logo {
                font-size: 32px;
            }

            .subtitle {
                font-size: 14px;
            }

            .form-control {
                padding: 12px 14px;
            }

            .btn {
                padding: 14px;
                font-size: 15px;
            }
        }
    </style>
</head>

<body>
    <div class="register-container">
        <h1 class="logo">Roncelizz</h1>
        <p class="subtitle">Bergabunglah dengan komunitas penggemar manik-manik terbaik ✨</p>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($_GET['success']); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="registerForm">
            <div class="form-group">
                <label for="full_name">Nama Lengkap</label>
                <input type="text" class="form-control" id="full_name" name="full_name"
                    placeholder="Masukkan nama lengkap" required value="<?php echo htmlspecialchars($full_name); ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" class="form-control" id="username" name="username" placeholder="Pilih username"
                        required value="<?php echo htmlspecialchars($username); ?>">
                    <div class="username-check" style="font-size: 0.85rem; margin-top: 5px;"></div>
                </div>

                <div class="form-group">
                    <label for="phone">No. Telepon</label>
                    <input type="tel" class="form-control" id="phone" name="phone" placeholder="0812xxxxxxx"
                        value="<?php echo htmlspecialchars($phone); ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" class="form-control" id="email" name="email" placeholder="email@contoh.com" required
                    value="<?php echo htmlspecialchars($email); ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-input-wrapper">
                        <input type="password" class="form-control" id="password" name="password"
                            placeholder="Minimal 6 karakter" required oninput="checkPasswordStrength()">
                    </div>
                    <div class="password-strength">
                        <div class="strength-bar" id="strengthBar"></div>
                    </div>
                    <div class="strength-text" id="strengthText">Kekuatan password</div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Konfirmasi Password</label>
                    <div class="password-input-wrapper">
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                            placeholder="Ketik ulang password" required oninput="checkPasswordMatch()">
                    </div>
                    <div class="password-match" id="passwordMatch"></div>
                </div>
            </div>

            <button type="submit" class="btn">✨ Daftar Sekarang</button>

            <div class="register-footer">
                <p>Sudah punya akun? <a href="login.php">Login di sini</a></p>
            </div>
        </form>
    </div>

    <script>
        // Password strength check
        function checkPasswordStrength() {
            const password = document.getElementById('password').value;
            const strengthBar = document.getElementById('strengthBar');
            const strengthText = document.getElementById('strengthText');

            let strength = 0;
            let color = '#ff4444';
            let text = 'Sangat Lemah';

            // Check length
            if (password.length >= 6) strength += 20;
            if (password.length >= 8) strength += 20;

            // Check for numbers
            if (password.match(/[0-9]/)) strength += 20;

            // Check for lowercase
            if (password.match(/[a-z]/)) strength += 20;

            // Check for uppercase
            if (password.match(/[A-Z]/)) strength += 10;

            // Check for special characters
            if (password.match(/[^a-zA-Z0-9]/)) strength += 10;

            // Set color and text based on strength
            if (strength >= 80) {
                color = '#00C851';
                text = 'Sangat Kuat';
            } else if (strength >= 60) {
                color = '#ffbb33';
                text = 'Kuat';
            } else if (strength >= 40) {
                color = '#ff8800';
                text = 'Sedang';
            } else if (strength >= 20) {
                color = '#ff4444';
                text = 'Lemah';
            }

            strengthBar.style.width = strength + '%';
            strengthBar.style.backgroundColor = color;
            strengthText.textContent = text;
            strengthText.style.color = color;

            checkPasswordMatch();
        }

        // Check password match
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchText = document.getElementById('passwordMatch');

            if (!password || !confirmPassword) {
                matchText.textContent = '';
                matchText.className = 'password-match';
                return;
            }

            if (password === confirmPassword) {
                matchText.textContent = '✅ Password cocok';
                matchText.className = 'password-match match-ok';
            } else {
                matchText.textContent = '❌ Password tidak cocok';
                matchText.className = 'password-match match-error';
            }
        }

        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function (e) {
            const fullName = document.getElementById('full_name').value.trim();
            const username = document.getElementById('username').value.trim();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const submitBtn = this.querySelector('button[type="submit"]');

            // Reset previous errors
            const errorElements = document.querySelectorAll('.form-error');
            errorElements.forEach(el => el.remove());

            let hasError = false;

            // Full name validation
            if (fullName.length < 3) {
                showError('full_name', 'Nama minimal 3 karakter');
                hasError = true;
            }

            // Username validation
            if (username.length < 3) {
                showError('username', 'Username minimal 3 karakter');
                hasError = true;
            } else if (!/^[a-zA-Z0-9_]+$/.test(username)) {
                showError('username', 'Username hanya boleh huruf, angka, dan underscore');
                hasError = true;
            }

            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                showError('email', 'Format email tidak valid');
                hasError = true;
            }

            // Password validation
            if (password.length < 6) {
                showError('password', 'Password minimal 6 karakter');
                hasError = true;
            }

            if (password !== confirmPassword) {
                showError('confirm_password', 'Password tidak cocok');
                hasError = true;
            }

            if (hasError) {
                e.preventDefault();
                return false;
            }

            // Loading state
            submitBtn.innerHTML = '⏳ Mendaftarkan...';
            submitBtn.disabled = true;

            return true;
        });

        // Show error function
        function showError(fieldId, message) {
            const field = document.getElementById(fieldId);
            const errorDiv = document.createElement('div');
            errorDiv.className = 'form-error';
            errorDiv.style.color = '#ff4444';
            errorDiv.style.fontSize = '0.85rem';
            errorDiv.style.marginTop = '5px';
            errorDiv.innerHTML = message;

            field.parentElement.appendChild(errorDiv);
        }

        // Username availability check
        let usernameTimeout;
        document.getElementById('username').addEventListener('input', function () {
            clearTimeout(usernameTimeout);
            const username = this.value.trim();

            if (username.length < 3) return;

            usernameTimeout = setTimeout(() => {
                const checkDiv = this.parentElement.querySelector('.username-check');
                if (checkDiv) {
                    checkDiv.textContent = '';
                }
            }, 500);
        });

        // Email validation on blur
        document.getElementById('email').addEventListener('blur', function () {
            const email = this.value.trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            if (email && !emailRegex.test(email)) {
                showError('email', 'Format email tidak valid');
            }
        });

        document.addEventListener('DOMContentLoaded', function () {
            // Cek jika form tidak terlihat penuh di layar mobile
            if (window.innerWidth <= 768) {
                const form = document.getElementById('registerForm');
                const formRect = form.getBoundingClientRect();
                const viewportHeight = window.innerHeight;

                // Jika form lebih tinggi dari viewport, scroll ke atas form
                if (formRect.height > viewportHeight * 0.8) {
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                }
            }
        });

        // Prevent form resubmission
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>

</html>