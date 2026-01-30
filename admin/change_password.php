<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

Auth::requireAdmin();

$id = $_GET['id'] ?? 0;
$message = '';

// Get user info
try {
    $stmt = $pdo->prepare("SELECT id, username, full_name FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();

    if (!$user) {
        header('Location: users.php');
        exit();
    }
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($new_password)) {
        $message = 'error|Password baru tidak boleh kosong!';
    } elseif ($new_password !== $confirm_password) {
        $message = 'error|Konfirmasi password tidak cocok!';
    } elseif (strlen($new_password) < 6) {
        $message = 'error|Password minimal 6 karakter!';
    } else {
        try {
            $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$hashedPassword, $id]);

            $message = 'success|Password berhasil diganti untuk user ' . htmlspecialchars($user['username']) . '!';
        } catch (PDOException $e) {
            $message = 'error|Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ganti Password - Roncelizz Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Form Container */
        .form-container {
            max-width: 500px;
            margin: 0 auto;
        }

        .form-card {
            background: var(--white);
            padding: 40px;
            border-radius: var(--border-radius-medium);
            box-shadow: var(--shadow-medium);
        }

        .form-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .form-title {
            font-size: 2rem;
            color: var(--dark);
            font-weight: 700;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        .form-title i {
            color: var(--purple);
        }

        .form-subtitle {
            color: var(--gray);
            font-size: 1rem;
        }

        .user-info {
            background: linear-gradient(135deg, var(--pink-light), var(--purple-light));
            padding: 20px;
            border-radius: var(--border-radius-medium);
            margin-bottom: 30px;
            text-align: center;
        }

        .user-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--pink), var(--purple));
            border-radius: var(--border-radius-circle);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            color: white;
            font-weight: 600;
            font-size: 1.5rem;
        }

        .user-name {
            font-weight: 600;
            color: var(--dark);
            font-size: 1.2rem;
            margin-bottom: 5px;
        }

        .user-username {
            color: var(--purple);
            font-weight: 500;
        }

        /* Form Groups */
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 25px;
        }

        .form-label {
            font-weight: 500;
            color: var(--dark);
            font-size: 0.95rem;
        }

        .form-label.required::after {
            content: ' *';
            color: var(--pink);
        }

        .form-input {
            padding: 14px 15px;
            border: 2px solid var(--gray-light);
            border-radius: var(--border-radius-medium);
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--pink);
            box-shadow: 0 0 0 3px rgba(255, 107, 147, 0.1);
        }

        .form-input.error {
            border-color: var(--pink);
            background: rgba(255, 107, 147, 0.05);
        }

        .password-strength {
            margin-top: 5px;
            font-size: 0.85rem;
        }

        .strength-weak {
            color: #dc3545;
        }

        .strength-medium {
            color: #ffc107;
        }

        .strength-strong {
            color: #28a745;
        }

        /* Password Requirements */
        .password-requirements {
            background: #f8f9fa;
            padding: 15px;
            border-radius: var(--border-radius-medium);
            margin-bottom: 25px;
            border-left: 4px solid var(--blue);
        }

        .password-requirements h4 {
            color: var(--blue-dark);
            margin-bottom: 10px;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .password-requirements ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .password-requirements li {
            color: var(--gray);
            font-size: 0.85rem;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .password-requirements li i {
            font-size: 0.8rem;
            width: 16px;
        }

        .requirement-met {
            color: #28a745;
        }

        .requirement-not-met {
            color: #dc3545;
        }

        /* Form Actions */
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 2px solid var(--gray-light);
        }

        .form-btn {
            flex: 1;
            padding: 15px 25px;
            border: none;
            border-radius: var(--border-radius-medium);
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
        }

        .form-btn.submit {
            background: linear-gradient(135deg, var(--pink), var(--purple));
            color: white;
        }

        .form-btn.submit:hover {
            background: linear-gradient(135deg, var(--pink-dark), var(--purple-dark));
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 107, 147, 0.3);
        }

        .form-btn.cancel {
            background: var(--gray-light);
            color: var(--dark);
        }

        .form-btn.cancel:hover {
            background: var(--gray);
            color: white;
        }

        /* Message Notification */
        .message-notification {
            padding: 15px 20px;
            border-radius: var(--border-radius-medium);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            animation: slideIn 0.3s ease;
            border-left: 5px solid;
        }

        .message-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border-left-color: #28a745;
            color: #155724;
        }

        .message-error {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            border-left-color: #dc3545;
            color: #721c24;
        }

        .message-close {
            background: none;
            border: none;
            color: inherit;
            cursor: pointer;
            font-size: 1.2rem;
            opacity: 0.7;
            transition: opacity 0.3s;
        }

        .message-close:hover {
            opacity: 1;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .form-card {
                padding: 25px;
            }

            .form-title {
                font-size: 1.7rem;
            }

            .form-actions {
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            .form-card {
                padding: 20px;
            }

            .form-title {
                font-size: 1.5rem;
            }
        }

        /* CSS tombol change password di users.php */
        .btn-change {
            background: var(--yellow-light);
            color: var(--yellow-dark);
        }

        .btn-change:hover {
            background: var(--yellow);
            color: white;
            transform: translateY(-2px);
        }
    </style>
</head>

<body>
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <button class="menu-toggle" id="menuToggle">
            <i class="fas fa-bars"></i>
        </button>

        <div class="form-container">
            <div class="form-card">
                <!-- Message Notification -->
                <?php if ($message):
                    list($type, $text) = explode('|', $message, 2);
                    ?>
                    <div class="message-notification message-<?php echo $type; ?>">
                        <span>
                            <?php echo htmlspecialchars($text); ?>
                        </span>
                        <button class="message-close" onclick="this.parentElement.remove()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                <?php endif; ?>

                <div class="form-header">
                    <h1 class="form-title">
                        <i class="fas fa-lock"></i>
                        Ganti Password
                    </h1>
                    <p class="form-subtitle">
                        Ganti password untuk user berikut
                    </p>
                </div>

                <!-- User Info -->
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($user['full_name'] ?: $user['username'], 0, 1)); ?>
                    </div>
                    <div class="user-name">
                        <?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?>
                    </div>
                    <div class="user-username">@
                        <?php echo htmlspecialchars($user['username']); ?>
                    </div>
                </div>

                <!-- Password Requirements -->
                <div class="password-requirements">
                    <h4><i class="fas fa-info-circle"></i> Persyaratan Password</h4>
                    <ul>
                        <li id="req-length" class="requirement-not-met">
                            <i class="fas fa-times"></i> Minimal 6 karakter
                        </li>
                        <li id="req-match" class="requirement-not-met">
                            <i class="fas fa-times"></i> Password harus cocok
                        </li>
                    </ul>
                </div>

                <form method="POST" action="" id="passwordForm">
                    <div class="form-group">
                        <label class="form-label required">Password Baru</label>
                        <input type="password" name="new_password" id="new_password" class="form-input" required
                            placeholder="Masukkan password baru" onkeyup="checkPasswordStrength()">
                        <div id="password-strength" class="password-strength"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Konfirmasi Password</label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-input" required
                            placeholder="Konfirmasi password baru" onkeyup="checkPasswordMatch()">
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="form-btn submit" id="submitBtn">
                            <i class="fas fa-save"></i> Ganti Password
                        </button>
                        <a href="users.php" class="form-btn cancel">
                            <i class="fas fa-times"></i> Batal
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <script>
            // Toggle mobile menu
            document.getElementById('menuToggle').addEventListener('click', function () {
                document.getElementById('sidebar').classList.toggle('active');
            });

            document.addEventListener('click', function (event) {
                const sidebar = document.getElementById('sidebar');
                const menuToggle = document.getElementById('menuToggle');

                if (window.innerWidth <= 768) {
                    if (!sidebar.contains(event.target) && !menuToggle.contains(event.target) && sidebar.classList.contains('active')) {
                        sidebar.classList.remove('active');
                    }
                }
            });

            // Password strength checker
            function checkPasswordStrength() {
                const password = document.getElementById('new_password').value;
                const strengthText = document.getElementById('password-strength');
                const reqLength = document.getElementById('req-length');

                if (password.length === 0) {
                    strengthText.textContent = '';
                    reqLength.className = 'requirement-not-met';
                    reqLength.innerHTML = '<i class="fas fa-times"></i> Minimal 6 karakter';
                    return;
                }

                if (password.length < 6) {
                    strengthText.textContent = 'Lemah';
                    strengthText.className = 'password-strength strength-weak';
                    reqLength.className = 'requirement-not-met';
                    reqLength.innerHTML = '<i class="fas fa-times"></i> Minimal 6 karakter';
                } else if (password.length < 8) {
                    strengthText.textContent = 'Cukup';
                    strengthText.className = 'password-strength strength-medium';
                    reqLength.className = 'requirement-met';
                    reqLength.innerHTML = '<i class="fas fa-check"></i> Minimal 6 karakter ✓';
                } else {
                    strengthText.textContent = 'Kuat';
                    strengthText.className = 'password-strength strength-strong';
                    reqLength.className = 'requirement-met';
                    reqLength.innerHTML = '<i class="fas fa-check"></i> Minimal 6 karakter ✓';
                }

                checkPasswordMatch();
            }

            // Password match checker
            function checkPasswordMatch() {
                const password = document.getElementById('new_password').value;
                const confirm = document.getElementById('confirm_password').value;
                const reqMatch = document.getElementById('req-match');
                const submitBtn = document.getElementById('submitBtn');

                if (confirm.length === 0) {
                    reqMatch.className = 'requirement-not-met';
                    reqMatch.innerHTML = '<i class="fas fa-times"></i> Password harus cocok';
                    submitBtn.disabled = true;
                    return;
                }

                if (password === confirm && password.length >= 6) {
                    reqMatch.className = 'requirement-met';
                    reqMatch.innerHTML = '<i class="fas fa-check"></i> Password cocok ✓';
                    submitBtn.disabled = false;
                } else {
                    reqMatch.className = 'requirement-not-met';
                    reqMatch.innerHTML = '<i class="fas fa-times"></i> Password harus cocok';
                    submitBtn.disabled = true;
                }
            }

            // Form validation
            document.getElementById('passwordForm').addEventListener('submit', function (e) {
                const password = document.getElementById('new_password').value;
                const confirm = document.getElementById('confirm_password').value;

                if (password.length < 6) {
                    e.preventDefault();
                    alert('Password minimal 6 karakter!');
                    return;
                }

                if (password !== confirm) {
                    e.preventDefault();
                    alert('Konfirmasi password tidak cocok!');
                    return;
                }
            });

            // Auto remove message after 5 seconds
            setTimeout(() => {
                const message = document.querySelector('.message-notification');
                if (message) {
                    message.style.opacity = '0';
                    setTimeout(() => message.remove(), 300);
                }
            }, 5000);
        </script>
    </div>
</body>

</html>