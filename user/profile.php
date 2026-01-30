<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

Auth::requireLogin();

$user = Auth::getUser();
$message = '';
$error = '';

// Get user statistics
try {
    // Order count
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM orders WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $orderCount = $stmt->fetch()['total'];

    // Request count
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM requests WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $requestCount = $stmt->fetch()['total'];
} catch (PDOException $e) {
    $orderCount = 0;
    $requestCount = 0;
    error_log("Error fetching user statistics: " . $e->getMessage());
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';

    // Validate required fields
    if (empty($full_name) || empty($email)) {
        $error = "Nama lengkap dan email harus diisi.";
    } else {
        try {
            // Check if email already exists (excluding current user)
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $_SESSION['user_id']]);
            if ($stmt->fetch()) {
                throw new Exception("Email sudah digunakan oleh pengguna lain.");
            }

            // Update user profile
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, address = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$full_name, $email, $phone, $address, $_SESSION['user_id']]);

            // Update session data
            $_SESSION['full_name'] = $full_name;
            $_SESSION['email'] = $email;

            // Refresh user data
            $user = Auth::getUser();

            $message = "Profil berhasil diperbarui!";

        } catch (Exception $e) {
            $error = "Gagal memperbarui profil: " . $e->getMessage();
        }
    }
}

// Handle password change
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "Semua field password harus diisi.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Password baru dan konfirmasi password tidak cocok.";
    } elseif (strlen($new_password) < 6) {
        $error = "Password baru minimal 6 karakter.";
    } else {
        try {
            // Verify current password
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $userData = $stmt->fetch();

            if (!$userData || !password_verify($current_password, $userData['password'])) {
                throw new Exception("Password saat ini salah.");
            }
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$hashed_password, $_SESSION['user_id']]);

            $message = "Password berhasil diperbarui!";

        } catch (Exception $e) {
            $error = "Gagal mengubah password: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - Roncelizz</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --pink: #ff6b93;
            --purple: #9c6bff;
            --mint: #6bffb8;
            --peach: #ff9c6b;
            --white: #ffffff;
            --light: #fff5f7;
            --dark: #333333;
            --gray: #666666;
            --light-gray: #f5f5f5;
            --border: #e0e0e0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--light);
            color: var(--dark);
            line-height: 1.6;
        }

        /* Container utama dengan layout sidebar */
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, var(--pink), var(--purple));
            color: white;
            padding: 30px 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            z-index: 1000;
        }

        .logo-area {
            padding: 0 25px 30px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 30px;
        }

        .logo-area h1 {
            font-size: 28px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-info {
            padding: 0 25px 25px;
            margin-bottom: 20px;
        }

        .user-info h2 {
            font-size: 16px;
            font-weight: 500;
            opacity: 0.9;
            margin-bottom: 5px;
        }

        .user-email {
            color: rgba(255, 255, 255, 0.8);
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .user-email i {
            color: var(--mint);
        }

        /* Menu Styles */
        .menu-section {
            padding: 0 20px;
        }

        .menu-title {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 15px;
            color: rgba(255, 255, 255, 0.7);
            padding-left: 5px;
        }

        .menu-items {
            list-style: none;
        }

        .menu-item {
            margin-bottom: 5px;
        }

        .menu-link {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s;
            font-weight: 500;
            font-size: 14px;
        }

        .menu-link:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            transform: translateX(5px);
        }

        .menu-link.active {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border-left: 4px solid var(--mint);
        }

        .menu-link i {
            width: 20px;
            font-size: 16px;
            margin-right: 12px;
            text-align: center;
        }

        .submenu {
            padding-left: 52px;
            margin-top: 5px;
        }

        .submenu .menu-link {
            padding: 8px 15px;
            font-size: 13px;
        }

        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
            background: var(--light);
            min-height: 100vh;
        }

        /* Header */
        .page-header {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            border-left: 5px solid var(--pink);
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: linear-gradient(45deg, rgba(255, 107, 147, 0.05), rgba(156, 107, 255, 0.05));
            border-radius: 50%;
            transform: translate(100px, -100px);
        }

        .page-header h1 {
            font-size: 32px;
            color: var(--pink);
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-header p {
            color: var(--gray);
            font-size: 16px;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
        }

        /* Profile Layout */
        .profile-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 40px;
        }

        @media (max-width: 992px) {
            .profile-layout {
                grid-template-columns: 1fr;
            }
        }

        /* Profile Card */
        .profile-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border);
            text-align: center;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, var(--pink), var(--purple));
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: white;
        }

        .profile-name {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--dark);
        }

        .profile-email {
            color: var(--gray);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .profile-stats {
            display: flex;
            justify-content: space-around;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid var(--light-gray);
        }

        .profile-stat {
            text-align: center;
        }

        .stat-number {
            font-size: 24px;
            font-weight: 700;
            color: var(--purple);
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 12px;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Form Sections */
        .form-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border);
        }

        .section-header {
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--light-gray);
        }

        .section-title {
            font-size: 20px;
            color: var(--dark);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-subtitle {
            color: var(--gray);
            font-size: 14px;
        }

        /* Form Elements */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
        }

        .form-label .required {
            color: var(--pink);
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--pink);
            box-shadow: 0 0 0 3px rgba(255, 107, 147, 0.1);
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        /* Alert Messages */
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: fadeIn 0.5s ease;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-icon {
            font-size: 20px;
        }

        /* Buttons */
        .btn {
            padding: 12px 25px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: 'Poppins', sans-serif;
            width: 100%;
            justify-content: center;
        }

        .btn-primary {
            background: var(--pink);
            color: white;
        }

        .btn-primary:hover {
            background: #ff5580;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 107, 147, 0.3);
        }

        .btn-secondary {
            background: var(--purple);
            color: white;
        }

        .btn-secondary:hover {
            background: #7c4dff;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(156, 107, 255, 0.3);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--pink);
            color: var(--pink);
        }

        .btn-outline:hover {
            background: var(--pink);
            color: white;
        }

        /* Tabs for Profile Sections */
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            border-bottom: 2px solid var(--light-gray);
            padding-bottom: 15px;
        }

        .tab-btn {
            padding: 10px 20px;
            background: transparent;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            color: var(--gray);
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tab-btn:hover {
            color: var(--pink);
            background: rgba(255, 107, 147, 0.05);
        }

        .tab-btn.active {
            color: var(--pink);
            background: rgba(255, 107, 147, 0.1);
            border-bottom: 2px solid var(--pink);
        }

        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .tab-content.active {
            display: block;
        }

        .swal2-confirm {
            background-color: var(--pink) !important;
            border-color: var(--pink) !important;
        }

        .swal2-cancel {
            background-color: #6c757d !important;
            border-color: #6c757d !important;
        }

        .swal2-popup {
            border-radius: 12px !important;
            font-family: 'Poppins', sans-serif !important;
        }

        /* Animation */
        @keyframes fadeIn {
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
        @media (max-width: 992px) {
            .dashboard-container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                padding: 20px 0;
                box-shadow: none;
            }

            .main-content {
                margin-left: 0;
                padding: 20px;
            }
        }

        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 24px;
            }

            .profile-stats {
                flex-direction: column;
                gap: 15px;
            }

            .tabs {
                flex-direction: column;
            }

            .tab-btn {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 576px) {
            .btn {
                padding: 10px 15px;
                font-size: 14px;
            }

            .profile-avatar {
                width: 100px;
                height: 100px;
                font-size: 36px;
            }
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="logo-area">
                <h1>
                    <i class="fas fa-gem"></i> Roncelizz
                </h1>
                <p style="color: rgba(255, 255, 255, 0.8); font-size: 14px; margin-top: 5px;">
                    Dashboard Pelanggan
                </p>
            </div>

            <div class="user-info">
                <h2><?php echo htmlspecialchars($user['full_name'] ?? 'Pengguna'); ?></h2>
                <div class="user-email">
                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email'] ?? 'cus@gmail.com'); ?>
                </div>
            </div>

            <div class="menu-section">
                <div class="menu-title">MENU UTAMA</div>
                <ul class="menu-items">
                    <li class="menu-item">
                        <a href="dashboard.php" class="menu-link">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="request.php" class="menu-link">
                            <i class="fas fa-paint-brush"></i> Request Custom
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="shopping.php" class="menu-link">
                            <i class="fas fa-shopping-cart"></i> Belanja
                        </a>
                        <ul class="submenu">
                            <li class="menu-item">
                                <a href="order.php" class="menu-link">
                                    <i class="fas fa-box"></i> Pesan Produk
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="order_history.php" class="menu-link">
                                    <i class="fas fa-list"></i> Pesanan Saya
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="payment.php" class="menu-link">
                                    <i class="fas fa-money-bill-wave"></i> Bayar Pesanan
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="menu-item">
                        <a href="my-requests.php" class="menu-link">
                            <i class="fas fa-th-large"></i> Riwayat Request
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="profile.php" class="menu-link active">
                            <i class="fas fa-user-cog"></i> Akun Saya
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="#" class="menu-link" onclick="confirmLogout(event)">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <h1>
                    <i class="fas fa-user-circle"></i> Profil Saya
                </h1>
                <p>Kelola informasi akun dan keamanan Anda</p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle alert-icon"></i>
                    <span><?php echo $message; ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle alert-icon"></i>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>

            <div class="profile-layout">
                <!-- Profile Card -->
                <div class="profile-card">
                    <div class="profile-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <h3 class="profile-name"><?php echo htmlspecialchars($user['full_name'] ?? 'Pengguna'); ?></h3>
                    <div class="profile-email">
                        <i class="fas fa-envelope"></i>
                        <?php echo htmlspecialchars($user['email'] ?? 'cus@gmail.com'); ?>
                    </div>

                    <p style="color: var(--gray); font-size: 14px; margin-bottom: 10px;">
                        <i class="fas fa-calendar"></i> Bergabung:
                        <?php echo date('d M Y', strtotime($user['created_at'] ?? date('Y-m-d H:i:s'))); ?>
                    </p>

                    <?php if (!empty($user['phone'])): ?>
                        <p style="color: var(--gray); font-size: 14px; margin-bottom: 10px;">
                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($user['phone']); ?>
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($user['address'])): ?>
                        <p style="color: var(--gray); font-size: 14px; margin-bottom: 20px;">
                            <i class="fas fa-map-marker-alt"></i>
                            <?php echo htmlspecialchars(substr($user['address'], 0, 50)) . (strlen($user['address']) > 50 ? '...' : ''); ?>
                        </p>
                    <?php endif; ?>

                    <div class="profile-stats">
                        <div class="profile-stat">
                            <div class="stat-number"><?php echo $orderCount; ?></div>
                            <div class="stat-label">Pesanan</div>
                        </div>
                        <div class="profile-stat">
                            <div class="stat-number"><?php echo $requestCount; ?></div>
                            <div class="stat-label">Request</div>
                        </div>
                        <div class="profile-stat">
                            <div class="stat-number">
                                <?php echo date('M Y', strtotime($user['created_at'] ?? date('Y-m-d H:i:s'))); ?>
                            </div>
                            <div class="stat-label">Bergabung</div>
                        </div>
                    </div>
                </div>

                <!-- Profile Form -->
                <div class="form-section">
                    <div class="tabs">
                        <button class="tab-btn active" data-tab="profile-tab">
                            <i class="fas fa-user-edit"></i> Edit Profil
                        </button>
                        <button class="tab-btn" data-tab="password-tab">
                            <i class="fas fa-lock"></i> Ubah Password
                        </button>
                    </div>

                    <!-- Profile Edit Tab -->
                    <div class="tab-content active" id="profile-tab">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="fas fa-edit"></i> Informasi Profil
                            </h3>
                            <p class="section-subtitle">Perbarui informasi profil Anda</p>
                        </div>

                        <form method="POST" action="" id="profileForm">
                            <div class="form-group">
                                <label class="form-label">
                                    Nama Lengkap <span class="required">*</span>
                                </label>
                                <input type="text" name="full_name" class="form-control"
                                    value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    Email <span class="required">*</span>
                                </label>
                                <input type="email" name="email" class="form-control"
                                    value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Nomor Telepon</label>
                                <input type="tel" name="phone" class="form-control"
                                    value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                    placeholder="Contoh: 081234567890">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Alamat Lengkap</label>
                                <textarea name="address" class="form-control" rows="4"
                                    placeholder="Alamat lengkap untuk pengiriman..."><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                            </div>

                            <div class="form-group">
                                <button type="button" class="btn btn-primary" onclick="submitProfileForm()">
                                    <i class="fas fa-save"></i> Simpan Perubahan
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Password Change Tab -->
                    <div class="tab-content" id="password-tab">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="fas fa-lock"></i> Keamanan Akun
                            </h3>
                            <p class="section-subtitle">Ubah password untuk keamanan akun Anda</p>
                        </div>

                        <form method="POST" action="" id="passwordForm">
                            <input type="hidden" name="change_password" value="1">

                            <div class="form-group">
                                <label class="form-label">
                                    Password Saat Ini <span class="required">*</span>
                                </label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    Password Baru <span class="required">*</span>
                                </label>
                                <input type="password" name="new_password" class="form-control" required>
                                <small style="color: var(--gray); display: block; margin-top: 5px;">
                                    Minimal 6 karakter
                                </small>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    Konfirmasi Password Baru <span class="required">*</span>
                                </label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>

                            <div class="form-group">
                                <button type="button" class="btn btn-primary" onclick="submitPasswordForm()">
                                    <i class="fas fa-key"></i> Ubah Password
                                </button>
                            </div>
                        </form>

                        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--light-gray);">
                            <h4 style="color: var(--dark); margin-bottom: 10px; font-size: 16px;">
                                <i class="fas fa-info-circle"></i> Tips Keamanan:
                            </h4>
                            <ul style="color: var(--gray); font-size: 14px; line-height: 1.6; padding-left: 20px;">
                                <li>Gunakan kombinasi huruf, angka, dan simbol</li>
                                <li>Jangan gunakan password yang sama dengan akun lain</li>
                                <li>Ubah password secara berkala</li>
                                <li>Jangan bagikan password Anda kepada siapapun</li>
                            </ul>
                        </div>
                    </div>

                    <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--light-gray);">
                        <a href="dashboard.php" class="btn btn-outline">
                            <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tab functionality
        document.querySelectorAll('.tab-btn').forEach(button => {
            button.addEventListener('click', function () {
                // Remove active class from all tabs
                document.querySelectorAll('.tab-btn').forEach(btn => {
                    btn.classList.remove('active');
                });
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.classList.remove('active');
                });
                // Add active class to clicked tab
                this.classList.add('active');
                const tabId = this.getAttribute('data-tab');
                document.getElementById(tabId).classList.add('active');
            });
        });

        // Submit profile
        function submitProfileForm() {
            const fullName = document.querySelector('input[name="full_name"]').value;
            const email = document.querySelector('input[name="email"]').value;
            const phone = document.querySelector('input[name="phone"]').value;
            const address = document.querySelector('textarea[name="address"]').value;

            // Basic validation
            if (!fullName.trim()) {
                Swal.fire({
                    title: 'Peringatan!',
                    text: 'Nama lengkap harus diisi!',
                    icon: 'warning',
                    confirmButtonColor: '#ff6b93'
                });
                return;
            }

            if (!email.trim()) {
                Swal.fire({
                    title: 'Peringatan!',
                    text: 'Email harus diisi!',
                    icon: 'warning',
                    confirmButtonColor: '#ff6b93'
                });
                return;
            }

            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                Swal.fire({
                    title: 'Peringatan!',
                    text: 'Format email tidak valid!',
                    icon: 'warning',
                    confirmButtonColor: '#ff6b93'
                });
                return;
            }

            // Phone validation (optional)
            if (phone && !/^[\d\s\-\+]+$/.test(phone)) {
                Swal.fire({
                    title: 'Peringatan!',
                    text: 'Format nomor telepon tidak valid! Hanya boleh berisi angka, spasi, +, dan -',
                    icon: 'warning',
                    confirmButtonColor: '#ff6b93'
                });
                return;
            }

            // Show confirmation dialog
            Swal.fire({
                title: 'Konfirmasi Perubahan',
                html: `
                    <div style="text-align: left;">
                        <p><strong>Nama:</strong> ${fullName}</p>
                        <p><strong>Email:</strong> ${email}</p>
                        <p><strong>Telepon:</strong> ${phone || '-'}</p>
                        <p><strong>Alamat:</strong> ${address.substring(0, 100)}${address.length > 100 ? '...' : ''}</p>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#ff6b93',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Simpan Perubahan',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Submit the form
                    document.getElementById('profileForm').submit();
                }
            });
        }

        // Submit password
        function submitPasswordForm() {
            const currentPassword = document.querySelector('input[name="current_password"]').value;
            const newPassword = document.querySelector('input[name="new_password"]').value;
            const confirmPassword = document.querySelector('input[name="confirm_password"]').value;

            // Validation
            if (!currentPassword) {
                Swal.fire({
                    title: 'Peringatan!',
                    text: 'Password saat ini harus diisi!',
                    icon: 'warning',
                    confirmButtonColor: '#ff6b93'
                });
                return;
            }

            if (!newPassword) {
                Swal.fire({
                    title: 'Peringatan!',
                    text: 'Password baru harus diisi!',
                    icon: 'warning',
                    confirmButtonColor: '#ff6b93'
                });
                return;
            }

            if (!confirmPassword) {
                Swal.fire({
                    title: 'Peringatan!',
                    text: 'Konfirmasi password harus diisi!',
                    icon: 'warning',
                    confirmButtonColor: '#ff6b93'
                });
                return;
            }

            if (newPassword.length < 6) {
                Swal.fire({
                    title: 'Peringatan!',
                    text: 'Password baru minimal 6 karakter!',
                    icon: 'warning',
                    confirmButtonColor: '#ff6b93'
                });
                return;
            }

            if (newPassword !== confirmPassword) {
                Swal.fire({
                    title: 'Peringatan!',
                    text: 'Password baru dan konfirmasi password tidak cocok!',
                    icon: 'warning',
                    confirmButtonColor: '#ff6b93'
                });
                return;
            }

            // Show confirmation dialog
            Swal.fire({
                title: 'Konfirmasi Ubah Password',
                text: 'Apakah Anda yakin ingin mengubah password?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#ff6b93',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Ubah Password',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Submit the form
                    document.getElementById('passwordForm').submit();
                }
            });
        }

        // Fungsi konfirmasi logout dengan modal 
        function confirmLogout(event) {
            event.preventDefault();

            Swal.fire({
                title: 'Konfirmasi Logout',
                text: 'Apakah Anda yakin ingin logout?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#ff6b93',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Logout',
                cancelButtonText: 'Batal',
                reverseButtons: true,
                customClass: {
                    confirmButton: 'btn btn-pink',
                    cancelButton: 'btn btn-secondary'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../logout.php';
                }
            });
        }

        // Show/hide password (optional feature)
        const passwordInputs = document.querySelectorAll('input[type="password"]');
        passwordInputs.forEach(input => {
            const toggleBtn = document.createElement('button');
            toggleBtn.type = 'button';
            toggleBtn.innerHTML = '<i class="fas fa-eye"></i>';
            toggleBtn.style.cssText = `
                position: absolute;
                right: 10px;
                top: 50%;
                transform: translateY(-50%);
                background: none;
                border: none;
                color: var(--gray);
                cursor: pointer;
            `;

            const wrapper = document.createElement('div');
            wrapper.style.position = 'relative';
            input.parentNode.insertBefore(wrapper, input);
            wrapper.appendChild(input);
            wrapper.appendChild(toggleBtn);

            toggleBtn.addEventListener('click', function () {
                const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                input.setAttribute('type', type);
                this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
            });
        });
    </script>
</body>

</html>