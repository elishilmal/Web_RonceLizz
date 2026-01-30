<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

Auth::requireAdmin();

$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;
$message = '';
$user = null;

// Initialize user data
$userData = [
    'username' => '',
    'email' => '',
    'full_name' => '',
    'phone' => '',
    'address' => '',
    'role' => 'user'
];

// Load user data for edit
if ($action === 'edit' && $id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        if ($user) {
            $userData = array_merge($userData, $user);
        } else {
            $message = 'error|User tidak ditemukan!';
            header('Location: users.php');
            exit();
        }
    } catch (PDOException $e) {
        $message = 'error|Database error: ' . $e->getMessage();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $full_name = $_POST['full_name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $role = $_POST['role'] ?? 'user';

    try {
        if ($action === 'add') {
            // Password default untuk user baru
            $defaultPassword = 'password123';
            $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);

            // Check if username or email exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);

            if ($stmt->fetch()) {
                $message = 'error|Username atau email sudah digunakan!';
            } else {
                // Insert new user
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, phone, address, role) 
                                     VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$username, $email, $hashedPassword, $full_name, $phone, $address, $role]);

                $message = 'success|User berhasil ditambahkan! Password default: <strong>password123</strong>';
                header('Location: users.php');
                exit();
            }
        } elseif ($action === 'edit' && $id) {
            // Update user
            $stmt = $pdo->prepare("UPDATE users SET 
                                 username = ?, 
                                 email = ?, 
                                 full_name = ?, 
                                 phone = ?, 
                                 address = ?, 
                                 role = ?, 
                                 updated_at = NOW()
                                 WHERE id = ?");
            $stmt->execute([$username, $email, $full_name, $phone, $address, $role, $id]);

            $message = 'success|User berhasil diperbarui!';
            header('Location: users.php');
            exit();
        } elseif ($action === 'reset' && $id) {
            // Reset password ke default
            $defaultPassword = 'password123';
            $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$hashedPassword, $id]);

            $message = 'success|Password berhasil direset ke default! Password: <strong>password123</strong>';
            header('Location: users.php');
            exit();
        }
    } catch (PDOException $e) {
        $message = 'error|Database error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $action === 'add' ? 'Tambah' : 'Edit'; ?> User - Roncelizz Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Form Container */
        .form-container {
            max-width: 800px;
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

        /* Warning Box untuk Reset Password */
        .warning-box {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            border: 2px solid #ffc107;
            border-radius: var(--border-radius-medium);
            padding: 20px;
            margin-bottom: 30px;
            color: #856404;
        }

        .warning-box h4 {
            color: #856404;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .warning-box p {
            margin-bottom: 10px;
            line-height: 1.5;
        }

        .password-display {
            background: white;
            padding: 15px;
            border-radius: var(--border-radius-medium);
            font-family: monospace;
            font-size: 1.2rem;
            font-weight: bold;
            text-align: center;
            margin: 15px 0;
            border: 2px dashed #28a745;
            color: #28a745;
        }

        /* Form Grid */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            margin-bottom: 30px;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
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

        .form-select {
            padding: 14px 15px;
            border: 2px solid var(--gray-light);
            border-radius: var(--border-radius-medium);
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
            background: var(--white);
            cursor: pointer;
            transition: all 0.3s;
        }

        .form-select:focus {
            outline: none;
            border-color: var(--pink);
            box-shadow: 0 0 0 3px rgba(255, 107, 147, 0.1);
        }

        /* Textarea */
        textarea.form-input {
            min-height: 120px;
            resize: vertical;
        }

        /* Form Full Width */
        .form-full {
            grid-column: 1 / -1;
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

        /* Password Info */
        .password-info {
            grid-column: 1 / -1;
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            padding: 15px 20px;
            border-radius: var(--border-radius-medium);
            border-left: 4px solid var(--blue);
            margin-top: 10px;
        }

        .password-info h4 {
            color: var(--blue-dark);
            margin-bottom: 8px;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .password-info p {
            color: var(--blue-dark);
            font-size: 0.9rem;
            line-height: 1.5;
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
                <div class="form-header">
                    <h1 class="form-title">
                        <i class="fas fa-user-edit"></i>
                        <?php echo $action === 'add' ? 'Tambah User Baru' :
                            ($action === 'reset' ? 'Reset Password User' : 'Edit User'); ?>
                    </h1>
                    <p class="form-subtitle">
                        <?php
                        if ($action === 'add') {
                            echo 'Isi form berikut untuk menambahkan user baru';
                        } elseif ($action === 'reset') {
                            echo 'Reset password user ke password default';
                        } else {
                            echo 'Perbarui informasi user di bawah ini';
                        }
                        ?>
                    </p>
                </div>

                <?php if ($action === 'reset'): ?>
                    <!-- Warning Box untuk Reset Password -->
                    <div class="warning-box">
                        <h4><i class="fas fa-exclamation-triangle"></i> Reset Password</h4>
                        <p><strong>User:</strong>
                            <?php echo htmlspecialchars($userData['full_name'] ?: $userData['username']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($userData['email']); ?></p>
                        <p>Password akan direset ke default:</p>
                        <div class="password-display">
                            password123
                        </div>
                        <p><small><i class="fas fa-info-circle"></i> User harus mengganti password setelah login pertama
                                kali.</small></p>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <?php if ($action === 'reset'): ?>
                        <!-- Form hidden untuk reset password -->
                        <input type="hidden" name="reset_confirm" value="1">

                        <div class="form-actions">
                            <button type="submit" class="form-btn submit">
                                <i class="fas fa-key"></i> Reset Password ke Default
                            </button>
                            <a href="users.php" class="form-btn cancel">
                                <i class="fas fa-times"></i> Batal
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- Form untuk add/edit -->
                        <div class="form-grid">
                            <!-- Username -->
                            <div class="form-group">
                                <label class="form-label required">Username</label>
                                <input type="text" name="username" class="form-input"
                                    value="<?php echo htmlspecialchars($userData['username']); ?>" required>
                            </div>

                            <!-- Email -->
                            <div class="form-group">
                                <label class="form-label required">Email</label>
                                <input type="email" name="email" class="form-input"
                                    value="<?php echo htmlspecialchars($userData['email']); ?>" required>
                            </div>

                            <!-- Full Name -->
                            <div class="form-group">
                                <label class="form-label">Nama Lengkap</label>
                                <input type="text" name="full_name" class="form-input"
                                    value="<?php echo htmlspecialchars($userData['full_name']); ?>">
                            </div>

                            <!-- Phone -->
                            <div class="form-group">
                                <label class="form-label">Telepon</label>
                                <input type="tel" name="phone" class="form-input"
                                    value="<?php echo htmlspecialchars($userData['phone']); ?>">
                            </div>

                            <!-- Role -->
                            <div class="form-group">
                                <label class="form-label required">Role</label>
                                <select name="role" class="form-select" required>
                                    <option value="user" <?php echo $userData['role'] === 'user' ? 'selected' : ''; ?>>User
                                    </option>
                                    <option value="admin" <?php echo $userData['role'] === 'admin' ? 'selected' : ''; ?>>Admin
                                    </option>
                                </select>
                            </div>

                            <!-- Address -->
                            <div class="form-group form-full">
                                <label class="form-label">Alamat</label>
                                <textarea name="address" class="form-input"><?php
                                echo $userData['address'] ? htmlspecialchars($userData['address']) : '';
                                ?></textarea>
                            </div>

                            <?php if ($action === 'add'): ?>
                                <div class="password-info form-full">
                                    <h4><i class="fas fa-info-circle"></i> Informasi Password</h4>
                                    <p>Password default untuk user baru: <strong>password123</strong></p>
                                    <p><small>User disarankan mengganti password setelah login pertama kali.</small></p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="form-btn submit">
                                <i class="fas fa-save"></i>
                                <?php echo $action === 'add' ? 'Simpan User Baru' : 'Perbarui User'; ?>
                            </button>
                            <a href="users.php" class="form-btn cancel">
                                <i class="fas fa-times"></i> Batal
                            </a>
                        </div>
                    <?php endif; ?>
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

            <?php if ($action !== 'reset'): ?>
                // Form validation untuk add/edit
                document.querySelector('form').addEventListener('submit', function (e) {
                    const username = document.querySelector('input[name="username"]').value.trim();
                    const email = document.querySelector('input[name="email"]').value.trim();

                    if (!username || !email) {
                        e.preventDefault();
                        alert('Username dan Email harus diisi!');
                    }

                    // Email validation
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (email && !emailRegex.test(email)) {
                        e.preventDefault();
                        alert('Format email tidak valid!');
                    }
                });
            <?php endif; ?>
        </script>
    </div>
</body>

</html>