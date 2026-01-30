<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Cek jika session belum dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = '';
$success = '';
$showForm = true;
$token = $_GET['token'] ?? '';

// Jika token dari GET parameter
if ($token) {
    $_SESSION['reset_token_input'] = $token;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $token = trim($_POST['token']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($token)) {
        $error = 'Token harus diisi';
    } elseif (empty($password)) {
        $error = 'Password baru harus diisi';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter';
    } elseif ($password !== $confirm_password) {
        $error = 'Password konfirmasi tidak cocok';
    } else {
        try {
            // Cek token di database
            $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW()");
            $stmt->execute([$token]);
            $resetRequest = $stmt->fetch();

            if ($resetRequest) {
                // Hash password baru
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                // Update password user
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
                $stmt->execute([$hashedPassword, $resetRequest['email']]);

                // Hapus token yang sudah digunakan
                $stmt = $pdo->prepare("DELETE FROM password_resets WHERE token = ?");
                $stmt->execute([$token]);

                // Clear session token
                unset($_SESSION['reset_token_demo']);
                unset($_SESSION['reset_token_input']);

                $success = 'Password berhasil direset! Silakan login dengan password baru Anda.';
                $showForm = false;
            } else {
                $error = 'Token tidak valid atau sudah kadaluarsa.';
            }
        } catch (PDOException $e) {
            error_log("Reset password error: " . $e->getMessage());
            $error = 'Terjadi kesalahan sistem. Silakan coba lagi nanti.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Roncelizz</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Dancing+Script:wght@700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .reset-page {
            min-height: 100vh;
            background: linear-gradient(135deg, #e6f7ff 0%, #f0e6ff 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        .reset-page::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 1000px;
            height: 1000px;
            background: radial-gradient(circle, rgba(107, 157, 255, 0.1) 0%, transparent 70%);
            opacity: 0.3;
            animation: float 20s infinite alternate;
        }

        .reset-page::after {
            content: '';
            position: absolute;
            bottom: -50%;
            left: -50%;
            width: 800px;
            height: 800px;
            background: radial-gradient(circle, rgba(255, 107, 147, 0.1) 0%, transparent 70%);
            opacity: 0.3;
            animation: float 25s infinite alternate-reverse;
        }

        .reset-container {
            max-width: 500px;
            width: 100%;
            background: white;
            border-radius: 25px;
            overflow: hidden;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.1);
            position: relative;
            z-index: 1;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        /* Header */
        .reset-header {
            padding: 40px 40px 20px;
            text-align: center;
            background: linear-gradient(135deg, #4caf50, #2196f3);
            color: white;
            position: relative;
        }

        .back-link {
            position: absolute;
            top: 20px;
            left: 20px;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            opacity: 0.9;
            transition: opacity 0.3s;
        }

        .back-link:hover {
            opacity: 1;
            text-decoration: underline;
        }

        .reset-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            display: inline-block;
            animation: bounce 2s infinite;
        }

        .reset-title {
            font-size: 2rem;
            margin-bottom: 10px;
            font-weight: 600;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.2);
        }

        .reset-subtitle {
            font-size: 1rem;
            opacity: 0.9;
            font-weight: 300;
            line-height: 1.6;
            max-width: 400px;
            margin: 0 auto;
        }

        /* Form Section */
        .reset-form-section {
            padding: 40px;
        }

        /* Demo Token Note */
        .demo-token-note {
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
            border: 2px solid #4caf50;
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 25px;
            font-size: 0.95rem;
            color: #2e7d32;
            position: relative;
            overflow: hidden;
        }

        .demo-token-note::before {
            content: '📋';
            position: absolute;
            top: 15px;
            left: 15px;
            font-size: 1.2rem;
        }

        .demo-token-note strong {
            color: #1b5e20;
            margin-left: 30px;
            display: block;
            margin-bottom: 5px;
        }

        .demo-token-note code {
            background: white;
            padding: 5px 10px;
            border-radius: 6px;
            font-family: monospace;
            border: 1px solid #a5d6a7;
            display: inline-block;
            margin: 5px 0;
            font-size: 0.9rem;
            word-break: break-all;
            max-width: 100%;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 0.95rem;
        }

        .form-control {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #ddd;
            border-radius: 12px;
            font-size: 16px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
            background: white;
            color: #333;
        }

        .form-control:focus {
            outline: none;
            border-color: #2196f3;
            box-shadow: 0 0 0 3px rgba(33, 150, 243, 0.1);
        }

        .form-control::placeholder {
            color: #888;
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
            color: #888;
            cursor: pointer;
            font-size: 18px;
            padding: 5px;
            transition: color 0.3s ease;
        }

        .password-toggle:hover {
            color: #2196f3;
        }

        .password-input-wrapper .form-control {
            padding-right: 50px;
        }

        /* Submit Button */
        .submit-button {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #4caf50, #2196f3);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
            margin: 10px 0 20px;
            position: relative;
            overflow: hidden;
        }

        .submit-button:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(33, 150, 243, 0.3);
        }

        .submit-button:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .submit-button::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .submit-button:hover::after:not(:disabled) {
            left: 100%;
        }

        /* Login Button */
        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 30px;
            background: linear-gradient(135deg, #4caf50, #2e7d32);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            font-family: 'Poppins', sans-serif;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(76, 175, 80, 0.3);
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
            background: linear-gradient(135deg, #ffebee, #ffcdd2);
            color: #c62828;
            border-color: #ef9a9a;
        }

        .alert-success {
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
            color: #2e7d32;
            border-color: #a5d6a7;
        }

        /* Success Message */
        .success-message {
            text-align: center;
            padding: 20px;
        }

        .success-icon {
            font-size: 4rem;
            color: #4caf50;
            margin-bottom: 20px;
            animation: bounce 1s infinite alternate;
        }

        /* Links */
        .text-center {
            text-align: center;
        }

        .mt-3 {
            margin-top: 15px;
        }

        .mt-4 {
            margin-top: 20px;
        }

        a {
            color: #2196f3;
            text-decoration: none;
            transition: color 0.3s;
        }

        a:hover {
            color: #1976d2;
            text-decoration: underline;
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

        @keyframes bounce {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-10px);
            }
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }

            100% {
                transform: scale(1);
            }
        }

        /* Password Strength Indicator */
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

        /* Responsive Design */
        @media (max-width: 768px) {
            .reset-container {
                max-width: 450px;
            }

            .reset-header {
                padding: 30px 20px 15px;
            }

            .reset-form-section {
                padding: 30px 20px;
            }

            .reset-title {
                font-size: 1.8rem;
            }

            .reset-icon {
                font-size: 3.5rem;
            }

            .demo-token-note {
                padding: 12px 15px;
                font-size: 0.9rem;
            }
        }

        @media (max-width: 480px) {
            .reset-page {
                padding: 10px;
            }

            .reset-container {
                border-radius: 20px;
            }

            .reset-header {
                padding: 25px 15px 10px;
            }

            .reset-form-section {
                padding: 25px 15px;
            }

            .form-control {
                padding: 12px 15px;
                font-size: 15px;
            }

            .submit-button {
                padding: 14px;
                font-size: 15px;
            }

            .btn-primary {
                padding: 12px 25px;
                font-size: 15px;
            }

            .demo-token-note code {
                font-size: 0.8rem;
                padding: 4px 8px;
            }
        }
    </style>
</head>

<body>
    <div class="reset-page">
        <div class="reset-container">
            <!-- Header -->
            <div class="reset-header">
                <a href="forgot-password.php" class="back-link">
                    ← Kembali
                </a>

                <div class="reset-icon">🔑</div>
                <h1 class="reset-title">Reset Password</h1>
                <p class="reset-subtitle">
                    Masukkan token dan buat password baru Anda
                </p>
            </div>

            <!-- Form Section -->
            <div class="reset-form-section">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <!-- Success State -->
                    <div class="success-message">
                        <div class="success-icon">✅</div>
                        <div class="alert alert-success">
                            <strong>Berhasil!</strong> <?php echo htmlspecialchars($success); ?>
                        </div>

                        <div class="mt-4">
                            <a href="login.php" class="btn-primary">
                                <span>🔓 Login dengan Password Baru</span>
                            </a>
                        </div>
                    </div>
                <?php elseif ($showForm): ?>
                    <!-- Demo Token Info -->
                    <?php if (isset($_SESSION['reset_token_demo'])): ?>
                        <div class="demo-token-note">
                            <strong>Mode Demo:</strong> Token Anda:
                            <code><?php echo htmlspecialchars($_SESSION['reset_token_demo']); ?></code>
                            (Sudah diisi otomatis di bawah)
                        </div>
                    <?php endif; ?>

                    <!-- Reset Password Form -->
                    <form method="POST" action="" id="resetForm">
                        <div class="form-group">
                            <label for="token" class="form-label">Token Reset Password</label>
                            <input type="text" class="form-control" id="token" name="token"
                                placeholder="Tempel token dari halaman sebelumnya" value="<?php
                                echo isset($_SESSION['reset_token_demo']) ? htmlspecialchars($_SESSION['reset_token_demo']) :
                                    (isset($_SESSION['reset_token_input']) ? htmlspecialchars($_SESSION['reset_token_input']) : '');
                                ?>" required onfocus="this.select()">
                        </div>

                        <div class="form-group">
                            <label for="password" class="form-label">Password Baru</label>
                            <div class="password-input-wrapper">
                                <input type="password" class="form-control" id="password" name="password"
                                    placeholder="Minimal 6 karakter" required oninput="checkPasswordStrength()">
                                <button type="button" class="password-toggle" onclick="togglePassword('password')">
                                    👁️
                                </button>
                            </div>
                            <div class="password-strength">
                                <div class="strength-bar" id="strengthBar"></div>
                            </div>
                            <div class="strength-text" id="strengthText">Kekuatan password</div>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password" class="form-label">Konfirmasi Password Baru</label>
                            <div class="password-input-wrapper">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                                    placeholder="Ulangi password baru" required>
                                <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                                    👁️
                                </button>
                            </div>
                            <div id="passwordMatch" style="font-size: 0.85rem; margin-top: 5px;"></div>
                        </div>

                        <button type="submit" class="submit-button" id="submitBtn">
                            🔄 Reset Password
                        </button>
                    </form>

                    <!-- Links -->
                    <div class="text-center mt-3">
                        <p>
                            <a href="forgot-password.php">Minta token baru?</a> |
                            <a href="login.php">Kembali ke Login</a>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Toggle password visibility
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const toggleBtn = field.nextElementSibling;

            if (field.type === 'password') {
                field.type = 'text';
                toggleBtn.innerHTML = '🙈';
            } else {
                field.type = 'password';
                toggleBtn.innerHTML = '👁️';
            }
        }

        // Check password strength
        function checkPasswordStrength() {
            const password = document.getElementById('password').value;
            const strengthBar = document.getElementById('strengthBar');
            const strengthText = document.getElementById('strengthText');
            const confirmPassword = document.getElementById('confirm_password');
            const matchText = document.getElementById('passwordMatch');

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

            // Check password match
            if (confirmPassword.value) {
                checkPasswordMatch();
            }
        }

        // Check password match
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchText = document.getElementById('passwordMatch');

            if (!password || !confirmPassword) {
                matchText.textContent = '';
                return;
            }

            if (password === confirmPassword) {
                matchText.textContent = '✅ Password cocok';
                matchText.style.color = '#00C851';
            } else {
                matchText.textContent = '❌ Password tidak cocok';
                matchText.style.color = '#ff4444';
            }
        }

        // Auto-check password match when typing
        document.getElementById('confirm_password').addEventListener('input', checkPasswordMatch);
        document.getElementById('password').addEventListener('input', checkPasswordMatch);

        // Form validation
        document.getElementById('resetForm')?.addEventListener('submit', function (e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const submitBtn = document.getElementById('submitBtn');

            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Password dan konfirmasi password tidak cocok!');
                return false;
            }

            if (password.length < 6) {
                e.preventDefault();
                alert('Password minimal 6 karakter!');
                return false;
            }

            // Show loading state
            submitBtn.innerHTML = '⏳ Memproses...';
            submitBtn.disabled = true;

            return true;
        });

        // Auto-focus on first field
        document.addEventListener('DOMContentLoaded', function () {
            const tokenField = document.getElementById('token');
            if (tokenField && !tokenField.value) {
                tokenField.focus();
            }
        });

        // Prevent form resubmission
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>

</html>