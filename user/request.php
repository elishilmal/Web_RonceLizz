<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

Auth::requireLogin();

$user = Auth::getUser();
$message = '';
$error = '';

// Get categories for dropdown
try {
    // Query tanpa kondisi status karena kolom status tidak ada
    $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];

    error_log("Gagal memuat kategori: " . $e->getMessage());
}

// Handle request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_name = $_POST['product_name'] ?? '';
    $category_id = $_POST['category_id'] ?? '';
    $description = $_POST['description'] ?? '';
    $material = $_POST['material'] ?? '';
    $color = $_POST['color'] ?? '';
    $quantity = $_POST['quantity'] ?? 1;
    $budget = $_POST['budget'] ?? 0;
    $deadline = $_POST['deadline'] ?? '';
    $notes = $_POST['notes'] ?? '';
    $reference_image = $_FILES['reference_image'] ?? null;

    // Validate required fields
    if (empty($product_name) || empty($category_id) || empty($description)) {
        $error = "Mohon lengkapi semua field yang wajib diisi.";
    } else {
        try {
            // Handle file upload
            $image_path = null;
            if ($reference_image && $reference_image['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../assets/images/requests/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                $file_extension = pathinfo($reference_image['name'], PATHINFO_EXTENSION);
                $file_name = 'request_' . time() . '_' . uniqid() . '.' . $file_extension;
                $target_file = $upload_dir . $file_name;

                // Check file size (max 5MB)
                if ($reference_image['size'] > 5 * 1024 * 1024) {
                    throw new Exception("Ukuran file terlalu besar. Maksimal 5MB.");
                }

                // Check file type
                $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                if (!in_array(strtolower($file_extension), $allowed_types)) {
                    throw new Exception("Format file tidak didukung. Gunakan JPG, PNG, GIF, atau WEBP.");
                }

                if (move_uploaded_file($reference_image['tmp_name'], $target_file)) {
                    $image_path = 'requests/' . $file_name;
                }
            }

            // Generate request code
            $request_code = 'REQ' . date('Ymd') . strtoupper(substr(uniqid(), 7, 6));
            $check_table = $pdo->query("SHOW TABLES LIKE 'requests'")->fetch();

            if (!$check_table) {
                $create_table_sql = "
                CREATE TABLE IF NOT EXISTS requests (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    request_code VARCHAR(50) UNIQUE NOT NULL,
                    user_id INT NOT NULL,
                    product_name VARCHAR(255) NOT NULL,
                    category_id INT,
                    description TEXT NOT NULL,
                    material VARCHAR(255),
                    color VARCHAR(100),
                    quantity INT DEFAULT 1,
                    budget DECIMAL(10,2) DEFAULT 0,
                    deadline DATE NULL,
                    reference_image VARCHAR(255),
                    notes TEXT,
                    status ENUM('pending', 'reviewed', 'approved', 'rejected', 'completed') DEFAULT 'pending',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
                )";
                $pdo->exec($create_table_sql);
            }

            // Insert into requests table
            $stmt = $pdo->prepare("INSERT INTO requests (
                request_code,
                user_id,
                product_name,
                category_id,
                description,
                material,
                color,
                quantity,
                budget,
                deadline,
                reference_image,
                notes,
                status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");

            $stmt->execute([
                $request_code,
                $_SESSION['user_id'],
                $product_name,
                $category_id,
                $description,
                $material,
                $color,
                $quantity,
                $budget,
                $deadline ?: null,
                $image_path,
                $notes
            ]);

            $message = "Request produk berhasil dikirim! Kode Request: <strong>$request_code</strong>. Admin akan meninjau request Anda.";
            $success_request_code = $request_code; // Simpan untuk JS

            // Clear form
            $_POST = array();

        } catch (Exception $e) {
            $error = "Gagal mengirim request: " . $e->getMessage();
            error_log("Request submission error: " . $e->getMessage());
        }
    }
}

// Get user pending requests count
try {
    $check_table = $pdo->query("SHOW TABLES LIKE 'requests'")->fetch();
    if ($check_table) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM requests WHERE user_id = ? AND status = 'pending'");
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch();
        $pendingRequests = $result ? $result['total'] : 0;
    } else {
        $pendingRequests = 0;
    }
} catch (PDOException $e) {
    $pendingRequests = 0;
    error_log("Error getting pending requests: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Produk - Roncelizz</title>
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
        .welcome-header {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            border-left: 5px solid var(--purple);
            position: relative;
            overflow: hidden;
        }

        .welcome-header::before {
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

        .welcome-header h1 {
            font-size: 32px;
            color: var(--purple);
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }

        .welcome-header p {
            color: var(--gray);
            font-size: 16px;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
        }

        .request-stats {
            display: flex;
            gap: 15px;
            margin-top: 15px;
        }

        .stat-badge {
            background: var(--light);
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--dark);
            font-weight: 500;
        }

        .stat-badge i {
            color: var(--pink);
        }

        /* Request Container */
        .request-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 40px;
            margin-bottom: 40px;
        }

        @media (max-width: 992px) {
            .request-container {
                grid-template-columns: 1fr;
            }
        }

        /* Form Section */
        .form-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border);
        }

        .form-header {
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--light-gray);
        }

        .form-title {
            font-size: 24px;
            color: var(--dark);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-subtitle {
            color: var(--gray);
            font-size: 15px;
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

        .form-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%23333' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 12px;
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        /* File Upload */
        .file-upload {
            position: relative;
        }

        .file-input {
            position: absolute;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .file-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 30px;
            border: 2px dashed var(--border);
            border-radius: 8px;
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
            min-height: 150px;
        }

        .file-label:hover {
            border-color: var(--pink);
            background: rgba(255, 107, 147, 0.05);
        }

        .file-label.dragover {
            border-color: var(--purple);
            background: rgba(156, 107, 255, 0.1);
        }

        .file-icon {
            font-size: 40px;
            color: var(--purple);
            margin-bottom: 10px;
        }

        .file-text {
            color: var(--gray);
            margin-bottom: 5px;
        }

        .file-hint {
            font-size: 12px;
            color: var(--gray);
        }

        .file-preview {
            margin-top: 10px;
            text-align: center;
        }

        .preview-image {
            max-width: 100%;
            max-height: 200px;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
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

        /* Info Sidebar */
        .info-sidebar {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border);
        }

        .info-card {
            background: var(--light);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .info-icon {
            font-size: 24px;
            color: var(--pink);
            margin-bottom: 10px;
        }

        .info-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--dark);
        }

        .info-text {
            color: var(--gray);
            font-size: 14px;
            line-height: 1.6;
        }

        .tips-list {
            list-style: none;
            padding: 0;
        }

        .tips-list li {
            padding: 8px 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .tips-list li:last-child {
            border-bottom: none;
        }

        .tip-icon {
            color: var(--mint);
            font-size: 14px;
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

        /* Quantity Selector */
        .quantity-selector {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .qty-btn {
            width: 40px;
            height: 40px;
            background: var(--light);
            border: 2px solid var(--border);
            border-radius: 5px;
            font-size: 18px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .qty-btn:hover {
            background: var(--pink);
            color: white;
            border-color: var(--pink);
        }

        .qty-input {
            width: 60px;
            text-align: center;
            font-weight: 600;
            border: 2px solid var(--border);
        }

        .qty-input::-webkit-inner-spin-button,
        .qty-input::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        /* Price Display */
        .price-display {
            background: var(--light);
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
            display: none;
        }

        .price-display.show {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        .price-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 14px;
        }

        .price-total {
            font-weight: 600;
            color: var(--pink);
            font-size: 16px;
            border-top: 1px solid var(--border);
            padding-top: 8px;
            margin-top: 8px;
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
            .welcome-header h1 {
                font-size: 24px;
            }

            .form-title {
                font-size: 20px;
            }

            .form-section,
            .info-sidebar {
                padding: 20px;
            }

            .request-stats {
                flex-direction: column;
                gap: 10px;
            }
        }

        @media (max-width: 576px) {
            .quantity-selector {
                justify-content: center;
            }

            .btn {
                padding: 10px 15px;
                font-size: 14px;
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
                        <a href="request.php" class="menu-link active">
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
                        <a href="profile.php" class="menu-link">
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
            <!-- Welcome Header -->
            <div class="welcome-header">
                <h1>Request Produk Custom 🎨</h1>
                <p>Desain produk khusus sesuai keinginan Anda. Jelaskan detail produk yang Anda inginkan!</p>

                <div class="request-stats">
                    <div class="stat-badge">
                        <i class="fas fa-clock"></i>
                        <span>Pending: <?php echo $pendingRequests; ?> request</span>
                    </div>
                    <div class="stat-badge">
                        <i class="fas fa-history"></i>
                        <a href="my-requests.php" style="color: inherit; text-decoration: none;">
                            Lihat semua request
                        </a>
                    </div>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle alert-icon"></i>
                    <span><?php echo $message; ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error && !str_contains($error, 'Gagal memuat kategori')): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle alert-icon"></i>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>

            <div class="request-container">
                <!-- Request Form -->
                <div class="form-section">
                    <div class="form-header">
                        <h2 class="form-title">
                            <i class="fas fa-edit"></i> Form Request Custom
                        </h2>
                        <p class="form-subtitle">Isi form berikut untuk mengajukan produk custom</p>
                    </div>

                    <form method="POST" action="" enctype="multipart/form-data" id="requestForm">
                        <div class="form-group">
                            <label class="form-label">
                                Nama Produk <span class="required">*</span>
                            </label>
                            <input type="text" name="product_name" class="form-control"
                                value="<?php echo htmlspecialchars($_POST['product_name'] ?? ''); ?>"
                                placeholder="Contoh: Kalung Manik-manik Mutiara" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Kategori <span class="required">*</span>
                            </label>
                            <select name="category_id" class="form-control form-select" required>
                                <option value="">-- Pilih Kategori --</option>
                                <?php if (!empty($categories)): ?>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" <?php echo ($_POST['category_id'] ?? '') == $category['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="">-- Tidak ada kategori tersedia --</option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Deskripsi Produk <span class="required">*</span>
                            </label>
                            <textarea name="description" class="form-control" rows="4"
                                placeholder="Deskripsikan detail produk yang Anda inginkan: ukuran, bentuk, fungsi, dll..."
                                required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Material yang Diinginkan</label>
                            <input type="text" name="material" class="form-control"
                                value="<?php echo htmlspecialchars($_POST['material'] ?? ''); ?>"
                                placeholder="Contoh: Mutiara, Kristal Swarovski, Emas 14k">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Warna yang Diinginkan</label>
                            <input type="text" name="color" class="form-control"
                                value="<?php echo htmlspecialchars($_POST['color'] ?? ''); ?>"
                                placeholder="Contoh: Emas Rose, Silver, Multicolor">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Jumlah yang Diinginkan</label>
                            <div class="quantity-selector">
                                <button type="button" class="qty-btn" onclick="changeQuantity(-1)">-</button>
                                <input type="number" id="quantity" name="quantity" class="form-control qty-input"
                                    value="<?php echo $_POST['quantity'] ?? 1; ?>" min="1">
                                <button type="button" class="qty-btn" onclick="changeQuantity(1)">+</button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Budget Perkiraan (Rp)</label>
                            <input type="number" id="budget" name="budget" class="form-control"
                                value="<?php echo htmlspecialchars($_POST['budget'] ?? ''); ?>"
                                placeholder="Contoh: 500000" min="0">

                            <div class="price-display" id="priceDisplay">
                                <div class="price-item">
                                    <span>Budget per unit:</span>
                                    <span id="unitPrice">Rp 0</span>
                                </div>
                                <div class="price-item">
                                    <span>Jumlah:</span>
                                    <span id="displayQuantity">1</span>
                                </div>
                                <div class="price-item price-total">
                                    <span>Total Perkiraan:</span>
                                    <span id="totalPrice">Rp 0</span>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Batas Waktu (Opsional)</label>
                            <input type="date" name="deadline" class="form-control"
                                value="<?php echo htmlspecialchars($_POST['deadline'] ?? ''); ?>"
                                min="<?php echo date('Y-m-d'); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Catatan Tambahan (Opsional)</label>
                            <textarea name="notes" class="form-control" rows="3"
                                placeholder="Tambahkan catatan atau spesifikasi lainnya..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Gambar Referensi (Opsional)</label>
                            <div class="file-upload">
                                <input type="file" name="reference_image" id="reference_image" class="file-input"
                                    accept="image/*" onchange="previewImage(this)">
                                <label for="reference_image" class="file-label" id="fileLabel">
                                    <div class="file-icon">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                    </div>
                                    <div class="file-text">Klik atau tarik gambar ke sini</div>
                                    <div class="file-hint">Format: JPG, PNG, GIF, WEBP | Maks: 5MB</div>
                                </label>
                            </div>
                            <div class="file-preview" id="imagePreview"></div>
                        </div>

                        <div class="form-group">
                            <button type="button" class="btn btn-primary" onclick="submitRequestForm()">
                                <i class="fas fa-paper-plane"></i> Kirim Request
                            </button>
                        </div>

                        <a href="dashboard.php" class="btn btn-outline">
                            <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
                        </a>
                    </form>
                </div>

                <!-- Information Sidebar -->
                <div class="info-sidebar">
                    <div class="info-card">
                        <div class="info-icon">
                            <i class="fas fa-lightbulb"></i>
                        </div>
                        <h3 class="info-title">Tips Request yang Baik:</h3>
                        <ul class="tips-list">
                            <li>
                                <i class="fas fa-check tip-icon"></i>
                                <span>Jelaskan produk dengan spesifik dan detail</span>
                            </li>
                            <li>
                                <i class="fas fa-check tip-icon"></i>
                                <span>Sertakan gambar referensi jika ada</span>
                            </li>
                            <li>
                                <i class="fas fa-check tip-icon"></i>
                                <span>Tentukan material dan warna favorit</span>
                            </li>
                            <li>
                                <i class="fas fa-check tip-icon"></i>
                                <span>Berikan budget perkiraan yang realistis</span>
                            </li>
                            <li>
                                <i class="fas fa-check tip-icon"></i>
                                <span>Jelaskan tujuan penggunaan produk</span>
                            </li>
                        </ul>
                    </div>

                    <div class="info-card">
                        <div class="info-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h3 class="info-title">Proses Request:</h3>
                        <div class="info-text">
                            <p><strong>1-2 Hari:</strong> Admin akan meninjau request Anda</p>
                            <p><strong>3-5 Hari:</strong> Diskusi detail dengan Anda via WhatsApp</p>
                            <p><strong>Setelah disetujui:</strong> Konfirmasi pembayaran & produksi</p>
                            <p><strong>Estimasi selesai:</strong> 7-14 hari kerja</p>
                        </div>
                    </div>

                    <div class="info-card">
                        <div class="info-icon">
                            <i class="fas fa-question-circle"></i>
                        </div>
                        <h3 class="info-title">Butuh Bantuan?</h3>
                        <div class="info-text">
                            <p>Hubungi admin untuk konsultasi:</p>
                            <p><i class="fas fa-phone"></i> 0812-3456-7890</p>
                            <p><i class="fas fa-envelope"></i> admin@roncelizz.com</p>
                            <p><i class="fas fa-whatsapp"></i> WhatsApp: 0812-3456-7890</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Change quantity
        function changeQuantity(change) {
            const input = document.getElementById('quantity');
            let current = parseInt(input.value);
            const min = parseInt(input.min);

            current += change;
            if (current < min) current = min;
            input.value = current;
            calculateTotal();
        }

        // Image preview
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            const fileLabel = document.getElementById('fileLabel');

            if (input.files && input.files[0]) {
                const reader = new FileReader();

                reader.onload = function (e) {
                    preview.innerHTML = `
                        <img src="${e.target.result}" class="preview-image" alt="Preview">
                        <p style="color: var(--gray); margin-top: 10px;">${input.files[0].name}</p>
                    `;
                };

                reader.readAsDataURL(input.files[0]);
                fileLabel.classList.add('dragover');
            }
        }

        // Drag and drop functionality
        const fileInput = document.getElementById('reference_image');
        const fileLabel = document.getElementById('fileLabel');

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            fileLabel.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            fileLabel.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            fileLabel.addEventListener(eventName, unhighlight, false);
        });

        function highlight() {
            fileLabel.classList.add('dragover');
        }

        function unhighlight() {
            fileLabel.classList.remove('dragover');
        }

        fileLabel.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;

            if (files.length > 0) {
                fileInput.files = files;
                previewImage(fileInput);
            }
        }

        // Calculate total price
        function calculateTotal() {
            const budgetInput = document.getElementById('budget');
            const quantityInput = document.getElementById('quantity');
            const priceDisplay = document.getElementById('priceDisplay');
            const unitPrice = document.getElementById('unitPrice');
            const displayQuantity = document.getElementById('displayQuantity');
            const totalPrice = document.getElementById('totalPrice');

            const budget = parseFloat(budgetInput.value) || 0;
            const quantity = parseInt(quantityInput.value) || 1;

            if (budget > 0) {
                const total = budget * quantity;

                const formatRupiah = (number) => {
                    return 'Rp ' + number.toLocaleString('id-ID');
                };

                unitPrice.textContent = formatRupiah(budget);
                displayQuantity.textContent = quantity;
                totalPrice.textContent = formatRupiah(total);

                priceDisplay.classList.add('show');
            } else {
                priceDisplay.classList.remove('show');
            }
        }

        // Submit request 
        function submitRequestForm() {
            const productName = document.querySelector('input[name="product_name"]').value;
            const category = document.querySelector('select[name="category_id"]').value;
            const description = document.querySelector('textarea[name="description"]').value;
            const material = document.querySelector('input[name="material"]').value;
            const color = document.querySelector('input[name="color"]').value;
            const quantity = document.querySelector('input[name="quantity"]').value;
            const budget = document.querySelector('input[name="budget"]').value;
            const deadline = document.querySelector('input[name="deadline"]').value;
            const notes = document.querySelector('textarea[name="notes"]').value;

            // Basic validation
            if (!productName.trim()) {
                Swal.fire({
                    title: 'Peringatan!',
                    text: 'Nama produk harus diisi!',
                    icon: 'warning',
                    confirmButtonColor: '#ff6b93'
                });
                return;
            }

            if (!category) {
                Swal.fire({
                    title: 'Peringatan!',
                    text: 'Kategori harus dipilih!',
                    icon: 'warning',
                    confirmButtonColor: '#ff6b93'
                });
                return;
            }

            if (!description.trim()) {
                Swal.fire({
                    title: 'Peringatan!',
                    text: 'Deskripsi produk harus diisi!',
                    icon: 'warning',
                    confirmButtonColor: '#ff6b93'
                });
                return;
            }

            // Validate file size if uploaded
            if (fileInput.files.length > 0) {
                const fileSize = fileInput.files[0].size;
                const maxSize = 5 * 1024 * 1024;

                if (fileSize > maxSize) {
                    Swal.fire({
                        title: 'Peringatan!',
                        text: 'Ukuran file terlalu besar. Maksimal 5MB.',
                        icon: 'warning',
                        confirmButtonColor: '#ff6b93'
                    });
                    return;
                }
            }

            // Show confirmation dialog
            Swal.fire({
                title: 'Konfirmasi Request',
                html: `
                    <div style="text-align: left; font-size: 14px;">
                        <p><strong>Nama Produk:</strong> ${productName}</p>
                        <p><strong>Kategori ID:</strong> ${category}</p>
                        <p><strong>Material:</strong> ${material || '-'}</p>
                        <p><strong>Warna:</strong> ${color || '-'}</p>
                        <p><strong>Jumlah:</strong> ${quantity} pcs</p>
                        <p><strong>Budget:</strong> ${budget ? 'Rp ' + parseInt(budget).toLocaleString('id-ID') : '-'}</p>
                        <p><strong>Deadline:</strong> ${deadline || '-'}</p>
                        <p><strong>Deskripsi:</strong> ${description.substring(0, 100)}${description.length > 100 ? '...' : ''}</p>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#ff6b93',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Kirim Request',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Submit the form
                    document.getElementById('requestForm').submit();
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

        // Initialize price calculation
        document.addEventListener('DOMContentLoaded', function () {
            const budgetInput = document.getElementById('budget');
            const quantityInput = document.getElementById('quantity');

            budgetInput.addEventListener('input', calculateTotal);
            quantityInput.addEventListener('input', calculateTotal);

            // Initial calculation if budget already filled
            calculateTotal();

            // Show success message if request was successful
            <?php if (isset($success_request_code)): ?>
                Swal.fire({
                    title: 'Berhasil!',
                    html: `
                        <div style="text-align: center;">
                            <i class="fas fa-check-circle" style="font-size: 60px; color: #28a745; margin-bottom: 20px;"></i>
                            <p>Request produk berhasil dikirim!</p>
                            <p><strong>Kode Request:</strong> <?php echo $success_request_code; ?></p>
                            <p style="font-size: 14px; color: #666; margin-top: 10px;">Admin akan meninjau request Anda.</p>
                        </div>
                    `,
                    icon: 'success',
                    confirmButtonColor: '#ff6b93',
                    confirmButtonText: 'OK'
                });
            <?php endif; ?>
        });
    </script>
</body>

</html>