<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Mulai output buffering
ob_start();

$error = '';
$success = '';
$showForm = true;
$demoMode = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);

    if (empty($email)) {
        $error = 'Email harus diisi';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid';
    } else {
        try {
            // Cek apakah email terdaftar
            $stmt = $pdo->prepare("SELECT id, username, full_name FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // Generate reset token
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

                // Hapus token lama untuk email yang sama
                $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
                $stmt->execute([$email]);

                // Simpan token baru ke database
                $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
                $stmt->execute([$email, $token, $expires]);

                // Simpan token di session untuk demo
                $_SESSION['reset_token_demo'] = $token;
                $_SESSION['reset_email'] = $email;
                $_SESSION['reset_expires'] = $expires;

                // Tampilkan token
                $demoMode = true;
                $success = 'Token reset password telah dibuat.';
                $showForm = false;

            } else {
                $error = 'Email tidak terdaftar dalam sistem.';
            }
        } catch (PDOException $e) {
            error_log("Forgot password error: " . $e->getMessage());
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
    <title>Lupa Password - Roncelizz</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Dancing+Script:wght@700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .forgot-page {
            min-height: 100vh;
            background: linear-gradient(135deg, var(--pink-light) 0%, var(--lavender-light) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        .forgot-page::before {
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

        .forgot-page::after {
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

        .forgot-container {
            max-width: 600px;
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

        /* Header */
        .forgot-header {
            padding: 40px 40px 20px;
            text-align: center;
            background: linear-gradient(135deg, var(--purple-light) 0%, var(--pink-light) 100%);
            color: white;
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

        .forgot-icon {
            font-size: 3.5rem;
            margin-bottom: 20px;
            display: inline-block;
        }

        .forgot-title {
            font-size: 2rem;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .forgot-subtitle {
            font-size: 1rem;
            opacity: 0.9;
            font-weight: 300;
            line-height: 1.6;
            max-width: 400px;
            margin: 0 auto;
        }

        /* Form Section */
        .forgot-form-section {
            padding: 40px;
        }

        /* Demo Token Box */
        .demo-token-box {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            border: 2px dashed var(--purple);
            border-radius: 15px;
            padding: 25px;
            margin: 30px 0;
            text-align: center;
        }

        .demo-token-box h3 {
            color: var(--purple-dark);
            margin-bottom: 15px;
            font-size: 1.3rem;
        }

        .token-display {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin: 15px 0;
            font-family: monospace;
            word-break: break-all;
            border: 1px solid #90caf9;
            font-size: 0.9rem;
            line-height: 1.5;
            color: var(--dark);
        }

        .demo-info {
            background: #fff3e0;
            border-left: 4px solid #ff9800;
            padding: 12px 15px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 0.9rem;
            color: #5d4037;
            text-align: left;
        }

        .demo-info strong {
            color: #e65100;
        }

        .demo-info ul {
            margin: 10px 0 0 20px;
        }

        .demo-info li {
            margin-bottom: 5px;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            justify-content: center;
            margin-top: 25px;
        }

        .action-buttons .btn {
            min-width: 150px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 25px;
            border-radius: 10px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--pink-accent), var(--purple-dark));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(255, 107, 147, 0.3);
        }

        .btn-secondary {
            background: var(--gray-light);
            color: var(--dark);
            border: 1px solid var(--gray-medium);
        }

        .btn-secondary:hover {
            background: var(--gray-medium);
            transform: translateY(-2px);
        }

        .btn-copy {
            background: linear-gradient(135deg, #4caf50, #2e7d32);
            color: white;
            padding: 10px 20px;
            margin-top: 10px;
        }

        .btn-copy:hover {
            background: linear-gradient(135deg, #388e3c, #1b5e20);
        }

        /* Quick Links */
        .quick-links {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .quick-link {
            color: var(--purple);
            text-decoration: none;
            font-size: 0.9rem;
            padding: 8px 15px;
            border-radius: 20px;
            background: var(--gray-light);
            transition: all 0.3s;
        }

        .quick-link:hover {
            background: var(--purple-light);
            color: white;
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

        /* Submit Button */
        .submit-button {
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
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
        }

        .submit-button:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(255, 107, 147, 0.3);
        }

        .submit-button:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        /* Steps */
        .steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            position: relative;
        }

        .steps::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 50px;
            right: 50px;
            height: 2px;
            background: var(--gray-medium);
            z-index: 1;
        }

        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
            flex: 1;
        }

        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--gray-light);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: var(--gray);
            margin-bottom: 10px;
            border: 2px solid var(--gray-medium);
        }

        .step.active .step-number {
            background: linear-gradient(135deg, var(--pink-accent), var(--purple-dark));
            color: white;
            border-color: var(--purple);
        }

        .step-label {
            font-size: 0.85rem;
            color: var(--gray);
            text-align: center;
            max-width: 100px;
        }

        .step.active .step-label {
            color: var(--dark);
            font-weight: 500;
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

        .alert-info {
            background: linear-gradient(135deg, #e6f2ff, #cce0ff);
            color: #0066cc;
            border-color: #99ccff;
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

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.7;
            }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .forgot-container {
                max-width: 500px;
            }

            .forgot-header {
                padding: 30px 20px 15px;
            }

            .forgot-form-section {
                padding: 30px 20px;
            }

            .forgot-title {
                font-size: 1.8rem;
            }

            .forgot-icon {
                font-size: 3rem;
            }

            .steps {
                flex-direction: column;
                gap: 20px;
                align-items: center;
            }

            .steps::before {
                display: none;
            }

            .step {
                flex-direction: row;
                gap: 15px;
                width: 100%;
            }

            .step-label {
                text-align: left;
                max-width: none;
            }

            .action-buttons {
                flex-direction: column;
            }

            .action-buttons .btn {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .forgot-page {
                padding: 10px;
            }

            .forgot-container {
                border-radius: 20px;
            }

            .form-control {
                padding: 12px 15px;
                font-size: 15px;
            }

            .submit-button {
                padding: 14px;
                font-size: 15px;
            }

            .token-display {
                font-size: 0.8rem;
                padding: 10px;
            }
        }

        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid var(--purple);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 10px;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .login-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            text-align: center;
        }

        .animated-red-link {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            padding: 15px 30px;
            background: white;
            color: #ff5252;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 500;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid #ffcdd2;
        }

        .animated-red-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 82, 82, 0.1), transparent);
            transition: left 0.7s;
        }

        .animated-red-link:hover::before {
            left: 100%;
        }

        .animated-red-link:hover {
            background: #ff5252;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(255, 82, 82, 0.3);
        }

        .arrow {
            font-size: 1.3rem;
            font-weight: bold;
            transition: transform 0.3s ease;
        }

        .animated-red-link:hover .arrow {
            transform: translateX(-5px);
        }

        .text-content {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            line-height: 1.3;
        }

        .prompt {
            font-size: 0.85rem;
            opacity: 0.9;
        }

        .action {
            font-size: 1rem;
            font-weight: 600;
            margin-top: 2px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .login-redirect-red {
                padding: 12px 20px;
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>

<body>
    <div class="forgot-page">
        <div class="forgot-container">
            <!-- Header -->
            <div class="forgot-header">
                <div class="forgot-icon">🔐</div>
                <h1 class="forgot-title">Lupa Password</h1>
                <p class="forgot-subtitle">
                    Masukkan email yang terdaftar. Kami akan membuat token reset password untuk Anda.
                </p>
            </div>

            <!-- Form Section -->
            <div class="forgot-form-section">
                <div class="steps">
                    <div class="step <?php echo $showForm ? 'active' : ''; ?>">
                        <div class="step-number">1</div>
                        <div class="step-label">Masukkan Email</div>
                    </div>
                    <div class="step <?php echo !$showForm ? 'active' : ''; ?>">
                        <div class="step-number">2</div>
                        <div class="step-label">Dapatkan Token</div>
                    </div>
                    <div class="step">
                        <div class="step-number">3</div>
                        <div class="step-label">Reset Password</div>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <strong>Error:</strong>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success && $showForm): ?>
                    <div class="alert alert-success">
                        <strong>Berhasil:</strong>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <?php if ($showForm): ?>
                    <!-- Info Box -->
                    <div class="demo-info">
                        <strong>Mode Demo:</strong> Karena ini adalah sistem demo, token reset password akan ditampilkan di
                        halaman ini (tidak dikirim via email).
                        <ul>
                            <li>Token akan disimpan di database</li>
                            <li>Token berlaku selama 1 jam</li>
                            <li>Salin token dan tempel di halaman reset password</li>
                        </ul>
                    </div>

                    <!-- Forgot Password Form -->
                    <form method="POST" action="" id="forgotForm">
                        <div class="form-group">
                            <label for="email" class="form-label">Email Terdaftar</label>
                            <input type="email" class="form-control" id="email" name="email"
                                placeholder="contoh: nama@email.com" required
                                value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>

                        <button type="submit" class="submit-button" id="submitButton">
                            Buat Token Reset Password
                        </button>
                    </form>

                <?php else: ?>
                    <!-- Success State -->
                    <div class="alert alert-success">
                        <strong>Token Berhasil Dibuat!</strong>
                        <?php echo htmlspecialchars($success); ?>
                    </div>

                    <div class="demo-token-box">
                        <h3>📋 Token Reset Password Anda</h3>

                        <div class="token-display" id="tokenDisplay">
                            <?php
                            $token = isset($_SESSION['reset_token_demo']) ? $_SESSION['reset_token_demo'] : '';
                            echo htmlspecialchars($token);
                            ?>
                        </div>

                        <button class="btn btn-copy" onclick="copyToken()">
                            📋 Salin Token
                        </button>

                        <div class="demo-info mt-3">
                            <strong>Cara Menggunakan:</strong>
                            <ol>
                                <li>Salin token di atas</li>
                                <li>Klik tombol "Reset Password Sekarang"</li>
                                <li>Tempel token di halaman reset password</li>
                                <li>Buat password baru Anda</li>
                            </ol>
                            <p><strong>Catatan:</strong> Token berlaku hingga
                                <?php echo date('H:i', strtotime($expires)); ?> (1 jam)
                            </p>
                        </div>
                    </div>

                    <div class="action-buttons">
                        <a href="reset_password.php" class="btn btn-primary">
                            🔄 Reset Password Sekarang
                        </a>
                        <a href="login.php" class="btn btn-secondary">
                            ← Kembali ke Login
                        </a>
                        <button onclick="location.reload()" class="btn btn-secondary">
                            ↻ Buat Token Baru
                        </button>
                    </div>

                    <div class="quick-links">
                        <a href="login.php" class="quick-link">Masuk ke Akun</a>
                        <a href="register.php" class="quick-link">Daftar Akun Baru</a>
                        <a href="index.php" class="quick-link">Beranda</a>
                    </div>
                <?php endif; ?>

                <div class="login-section">
                    <a href="login.php" class="animated-red-link">
                        <span class="arrow">←</span>
                        <span class="text-content">
                            <span class="prompt">Ingat password Anda?</span>
                            <span class="action">Klik untuk Login</span>
                        </span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Form validation
        document.getElementById('forgotForm')?.addEventListener('submit', function (e) {
            const email = document.getElementById('email').value.trim();
            const submitButton = document.getElementById('submitButton');

            if (!email) {
                e.preventDefault();
                showAlert('Email harus diisi', 'danger');
                return false;
            }

            // Validate email format
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                showAlert('Format email tidak valid', 'danger');
                return false;
            }

            // Tampilkan loading state
            submitButton.innerHTML = '⏳ Membuat Token...';
            submitButton.disabled = true;

            return true;
        });

        // Copy token to clipboard
        function copyToken() {
            const tokenElement = document.getElementById('tokenDisplay');
            const token = tokenElement.textContent.trim();

            if (!token) {
                showAlert('Token tidak ditemukan', 'danger');
                return;
            }

            navigator.clipboard.writeText(token).then(() => {
                // Tampilkan feedback
                const copyBtn = event.target;
                const originalText = copyBtn.innerHTML;

                copyBtn.innerHTML = '✅ Tersalin!';
                copyBtn.style.background = 'linear-gradient(135deg, #2e7d32, #1b5e20)';

                setTimeout(() => {
                    copyBtn.innerHTML = originalText;
                    copyBtn.style.background = 'linear-gradient(135deg, #4caf50, #2e7d32)';
                }, 2000);

                // Tampilkan notifikasi kecil
                showAlert('Token berhasil disalin ke clipboard!', 'success');
            }).catch(err => {
                console.error('Gagal menyalin token: ', err);
                showAlert('Gagal menyalin token', 'danger');
            });
        }

        // Show alert function
        function showAlert(message, type) {
            // Remove existing alerts
            const existingAlerts = document.querySelectorAll('.temp-alert');
            existingAlerts.forEach(alert => alert.remove());

            // Create new alert
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} temp-alert`;
            alertDiv.innerHTML = `<strong>${type === 'danger' ? 'Error:' : 'Success:'}</strong> ${message}`;
            alertDiv.style.position = 'fixed';
            alertDiv.style.top = '20px';
            alertDiv.style.right = '20px';
            alertDiv.style.zIndex = '1000';
            alertDiv.style.maxWidth = '300px';
            alertDiv.style.animation = 'slideDown 0.3s ease';

            document.body.appendChild(alertDiv);

            // Auto remove after 3 seconds
            setTimeout(() => {
                alertDiv.style.opacity = '0';
                alertDiv.style.transition = 'opacity 0.3s';
                setTimeout(() => alertDiv.remove(), 300);
            }, 3000);
        }

        // Auto focus on email field
        document.getElementById('email')?.focus();

        // Auto select token on click
        document.getElementById('tokenDisplay')?.addEventListener('click', function () {
            const range = document.createRange();
            range.selectNodeContents(this);
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
        });

        // Handle form resubmission prevention
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>

</html>