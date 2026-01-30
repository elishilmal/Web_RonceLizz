<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

Auth::requireLogin();

$user = Auth::getUser();
$message = '';
$error = '';
$product = null;

// Get product details if product_id is provided
if (isset($_GET['product_id'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, c.name as category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.id = ?
            AND p.stock > 0
            AND p.deleted_at IS NULL
            AND status = 'available'
        ");

        $stmt->execute([$_GET['product_id']]);
        $product = $stmt->fetch();

        if (!$product) {
            $error = "Produk tidak ditemukan atau tidak tersedia.";
        }
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Handle order submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = $_POST['product_id'] ?? '';
    $quantity = $_POST['quantity'] ?? 1;
    $notes = $_POST['notes'] ?? '';
    $shipping_address = $_POST['shipping_address'] ?? '';
    $payment_method = $_POST['payment_method'] ?? 'transfer';

    if (empty($product_id) || empty($quantity) || empty($notes)) {
        $error = "Mohon lengkapi semua field yang wajib diisi.";
    } else {
        try {
            // Start transaction
            $pdo->beginTransaction();

            // Check product availability
            $stmt = $pdo->prepare("
                SELECT stock, price 
                FROM products 
                WHERE id = ?
                AND deleted_at IS NULL
                AND status = 'available'
                FOR UPDATE
            ");

            $stmt->execute([$product_id]);
            $productCheck = $stmt->fetch();

            if (!$productCheck || $productCheck['stock'] < $quantity) {
                throw new Exception("Stok produk tidak mencukupi.");
            }

            // Generate order code
            $order_code = 'ORD' . date('Ymd') . strtoupper(substr(uniqid(), 7, 6));
            $unit_price = $productCheck['price'];
            $total_price = $unit_price * $quantity;

            // Create order 
            $stmt = $pdo->prepare("INSERT INTO orders (
                order_code,
                user_id, 
                product_id, 
                quantity, 
                unit_price, 
                total_price, 
                notes, 
                status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
            
            $stmt->execute([
                $order_code,
                $_SESSION['user_id'],
                $product_id,
                $quantity,
                $unit_price,
                $total_price,
                $notes
            ]);

            // Update product stock
            $stmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
            $stmt->execute([$quantity, $product_id]);

            // Commit transaction
            $pdo->commit();

            $message = "Pesanan berhasil dibuat! Kode Pesanan: <strong>$order_code</strong>";
            $order_code = $order_code; // Untuk digunakan di JS
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Gagal membuat pesanan: " . $e->getMessage();
        }
    }
}

// Get available products for dropdown
try {
    $stmt = $pdo->query("
        SELECT id, name, price, stock 
        FROM products 
        WHERE stock > 0
        AND deleted_at IS NULL
        AND status = 'available'
        ORDER BY name
    ");

    $products = $stmt->fetchAll();
} catch (PDOException $e) {

}

// Get user order statistics
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM orders WHERE user_id = ? AND status = 'pending'");
    $stmt->execute([$_SESSION['user_id']]);
    $pendingOrders = $stmt->fetch()['total'];
} catch (PDOException $e) {
    $pendingOrders = 0;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesan Produk - Roncelizz</title>
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

        .order-stats {
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

        /* Order Layout */
        .order-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 40px;
        }

        @media (max-width: 992px) {
            .order-layout {
                grid-template-columns: 1fr;
            }
        }

        /* Product Preview */
        .product-preview {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border);
        }

        .preview-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }

        .product-badge {
            background: linear-gradient(45deg, var(--peach), #ffcc00);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .product-image-container {
            height: 250px;
            width: 100%;
            overflow: hidden;
            border-radius: 10px;
            margin-bottom: 20px;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .product-image-container i {
            font-size: 80px;
            color: var(--pink);
            opacity: 0.5;
        }

        .product-name {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--dark);
        }

        .product-category {
            color: var(--purple);
            font-size: 14px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .product-description {
            color: var(--gray);
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .product-details {
            background: var(--light);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .detail-item:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: var(--gray);
        }

        .detail-value {
            font-weight: 500;
        }

        .price-display {
            text-align: center;
            padding: 20px;
            background: linear-gradient(135deg, var(--pink), var(--purple));
            color: white;
            border-radius: 10px;
        }

        .price-label {
            font-size: 14px;
            opacity: 0.9;
        }

        .price-value {
            font-size: 32px;
            font-weight: 600;
        }

        /* Order Form */
        .order-form {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border);
        }

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

        /* Payment Methods */
        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }

        .payment-option {
            padding: 15px;
            border: 2px solid var(--border);
            border-radius: 8px;
            cursor: pointer;
            text-align: center;
            transition: all 0.3s;
            background: white;
        }

        .payment-option:hover {
            border-color: var(--pink);
            background: rgba(255, 107, 147, 0.05);
        }

        .payment-option.selected {
            border-color: var(--pink);
            background: rgba(255, 107, 147, 0.1);
        }

        .payment-icon {
            font-size: 24px;
            margin-bottom: 8px;
            color: var(--purple);
        }

        .payment-name {
            font-weight: 500;
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

            .payment-methods {
                grid-template-columns: 1fr;
            }

            .order-stats {
                flex-direction: column;
                gap: 10px;
            }
        }

        @media (max-width: 576px) {
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
                                <a href="order.php" class="menu-link active">
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
            <!-- Page Header -->
            <div class="page-header">
                <h1>
                    <i class="fas fa-shopping-cart"></i> Pesan Produk
                </h1>
                <p>Pesan produk yang tersedia di katalog Roncelizz</p>
                
                <div class="order-stats">
                    <div class="stat-badge">
                        <i class="fas fa-clock"></i>
                        <span>Pending: <?php echo $pendingOrders; ?> pesanan</span>
                    </div>
                    <div class="stat-badge">
                        <i class="fas fa-history"></i>
                        <a href="order_history.php" style="color: inherit; text-decoration: none;">
                            Lihat semua pesanan
                        </a>
                    </div>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle alert-icon"></i>
                    <span><?php echo $message; ?></span>
                    <br>
                    <small>Silakan cek halaman <a href="order_history.php" style="color: inherit; font-weight: 600;">Riwayat Pesanan</a> untuk melihat detail pesanan.</small>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle alert-icon"></i>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>

            <div class="order-layout">
                <!-- Product Preview -->
                <div class="product-preview">
                    <div class="preview-header">
                        <h2>Detail Produk</h2>
                        <?php if ($product && $product['type'] == 'limited'): ?>
                            <span class="product-badge">LIMITED EDITION</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($product): ?>
                        <div class="product-image-container">
                            <i class="fas fa-gem"></i>
                        </div>

                        <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>

                        <div class="product-category">
                            <i class="fas fa-tag"></i> <?php echo htmlspecialchars($product['category_name'] ?? 'Tidak ada kategori'); ?>
                        </div>

                        <p class="product-description"><?php echo htmlspecialchars($product['description']); ?></p>

                        <div class="product-details">
                            <?php if (!empty($product['material'])): ?>
                                <div class="detail-item">
                                    <span class="detail-label">Material:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($product['material']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($product['color'])): ?>
                                <div class="detail-item">
                                    <span class="detail-label">Warna:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($product['color']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="detail-item">
                                <span class="detail-label">Stok Tersedia:</span>
                                <span class="detail-value"><?php echo $product['stock']; ?> pcs</span>
                            </div>
                            
                            <div class="detail-item">
                                <span class="detail-label">Jenis:</span>
                                <span class="detail-value"><?php echo ucfirst($product['type']); ?></span>
                            </div>
                        </div>

                        <div class="price-display">
                            <div class="price-label">Harga Satuan</div>
                            <div class="price-value">Rp <?php echo number_format($product['price'], 0, ',', '.'); ?></div>
                        </div>
                    <?php else: ?>
                        <div class="product-image-container">
                            <i class="fas fa-box-open"></i>
                        </div>

                        <h3 class="product-name">Pilih Produk</h3>
                        <p class="product-description">Silakan pilih produk dari dropdown di form pesanan.</p>

                        <div class="price-display">
                            <div class="price-label">Harga akan ditampilkan</div>
                            <div class="price-value">-</div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Order Form -->
                <div class="order-form">
                    <form method="POST" action="" id="orderForm">
                        <input type="hidden" name="product_id" id="product_id" value="<?php echo $product['id'] ?? ''; ?>">

                        <div class="form-group">
                            <label class="form-label">
                                Pilih Produk <span class="required">*</span>
                            </label>
                            <select name="product_id_select" id="product_id_select" class="form-control form-select"
                                onchange="updateProductDetails(this.value)" required>
                                <option value="">-- Pilih Produk --</option>
                                <?php if (!empty($products)): ?>
                                    <?php foreach ($products as $prod): ?>
                                        <option value="<?php echo $prod['id']; ?>" 
                                            data-price="<?php echo $prod['price']; ?>"
                                            data-stock="<?php echo $prod['stock']; ?>"
                                            data-name="<?php echo htmlspecialchars($prod['name']); ?>"
                                            <?php echo (isset($product['id']) && $product['id'] == $prod['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($prod['name']); ?> - Rp <?php echo number_format($prod['price'], 0, ',', '.'); ?> (Stok: <?php echo $prod['stock']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="">-- Tidak ada produk tersedia --</option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Jumlah Pesanan <span class="required">*</span>
                            </label>
                            <div class="quantity-selector">
                                <button type="button" class="qty-btn" onclick="changeQuantity(-1)">-</button>
                                <input type="number" id="quantity" name="quantity" class="form-control qty-input"
                                    value="1" min="1" max="<?php echo $product['stock'] ?? 1; ?>"
                                    onchange="calculateTotal()" required>
                                <button type="button" class="qty-btn" onclick="changeQuantity(1)">+</button>
                            </div>
                            <small style="color: var(--gray); display: block; margin-top: 5px;">
                                Stok tersedia: <span id="stock_display"><?php echo $product['stock'] ?? '0'; ?></span> pcs
                            </small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Catatan untuk Penjual <span class="required">*</span>
                            </label>
                            <textarea name="notes" class="form-control" placeholder="Contoh: Alamat pengiriman, warna favorit, ukuran khusus, dll." required><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                            <small style="color: var(--gray);">Sertakan alamat pengiriman dan detail lainnya</small>
                        </div>

                        <div class="form-group">
                            <div class="price-display">
                                <div class="price-label">Total Pembayaran</div>
                                <div class="price-value" id="total_price">Rp 0</div>
                            </div>
                        </div>

                        <div class="form-group">
                            <button type="button" class="btn btn-primary" onclick="submitOrderForm()">
                                <i class="fas fa-paper-plane"></i> Kirim Pesanan
                            </button>
                        </div>

                        <a href="dashboard.php" class="btn btn-outline">
                            <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
                        </a>
                    </form>
                </div>
            </div>

            <!-- Payment Information -->
            <div class="order-form" style="margin-top: 30px;">
                <h2 style="margin-bottom: 20px; color: var(--pink);">
                    <i class="fas fa-info-circle"></i> Informasi Penting
                </h2>

                <div style="background: #f8f9fa; padding: 20px; border-radius: 10px;">
                    <h3 style="color: var(--purple); margin-bottom: 10px;">Cara Pesanan:</h3>
                    <ul style="color: var(--gray); line-height: 1.8;">
                        <li><strong>Alamat Pengiriman:</strong> Silakan sertakan alamat lengkap di kolom "Catatan untuk Penjual"</li>
                        <li><strong>Konfirmasi:</strong> Setelah mengirim pesanan, admin akan menghubungi Anda untuk konfirmasi</li>
                        <li><strong>Pembayaran:</strong> Transfer Bank: BCA 1234567890 a.n. Roncelizz Store</li>
                        <li><strong>Proses:</strong> Pesanan akan diproses setelah pembayaran dikonfirmasi</li>
                        <li><strong>Estimasi:</strong> Waktu pengiriman 3-7 hari kerja setelah konfirmasi</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Update product details when dropdown changes
        function updateProductDetails(productId) {
            if (!productId) {
                document.getElementById('product_id').value = '';
                document.getElementById('stock_display').textContent = '0';
                document.getElementById('quantity').max = 1;
                calculateTotal();
                return;
            }

            const select = document.getElementById('product_id_select');
            const option = select.options[select.selectedIndex];
            const price = option.dataset.price;
            const stock = option.dataset.stock;

            document.getElementById('product_id').value = productId;
            document.getElementById('stock_display').textContent = stock;
            document.getElementById('quantity').max = stock;

            // Update quantity if current quantity exceeds new stock
            const currentQty = parseInt(document.getElementById('quantity').value);
            if (currentQty > stock) {
                document.getElementById('quantity').value = stock;
            }

            calculateTotal();
        }

        // Change quantity
        function changeQuantity(change) {
            const input = document.getElementById('quantity');
            let current = parseInt(input.value);
            const max = parseInt(input.max);
            const min = parseInt(input.min);

            current += change;

            if (current < min) current = min;
            if (current > max) current = max;

            input.value = current;
            calculateTotal();
        }

        // Calculate total price
        function calculateTotal() {
            const select = document.getElementById('product_id_select');
            const option = select.options[select.selectedIndex];
            const price = parseFloat(option.dataset.price || 0);
            const quantity = parseInt(document.getElementById('quantity').value);

            const total = price * quantity;
            document.getElementById('total_price').textContent = 'Rp ' + formatNumber(total);
        }

        // Format number with thousand separators
        function formatNumber(num) {
            return num.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }

        // Submit order 
        function submitOrderForm() {
            const productId = document.getElementById('product_id_select').value;
            const quantity = parseInt(document.getElementById('quantity').value);
            const maxStock = parseInt(document.getElementById('quantity').max);
            const notes = document.querySelector('textarea[name="notes"]').value;
            const totalPrice = document.getElementById('total_price').textContent;
            const productName = document.getElementById('product_id_select').options[document.getElementById('product_id_select').selectedIndex].text;

            if (!productId) {
                Swal.fire({
                    title: 'Peringatan!',
                    text: 'Silakan pilih produk terlebih dahulu!',
                    icon: 'warning',
                    confirmButtonColor: '#ff6b93'
                });
                return;
            }

            if (quantity > maxStock) {
                Swal.fire({
                    title: 'Peringatan!',
                    text: 'Jumlah pesanan melebihi stok yang tersedia!',
                    icon: 'warning',
                    confirmButtonColor: '#ff6b93'
                });
                return;
            }

            if (quantity < 1) {
                Swal.fire({
                    title: 'Peringatan!',
                    text: 'Jumlah pesanan minimal 1!',
                    icon: 'warning',
                    confirmButtonColor: '#ff6b93'
                });
                return;
            }

            if (!notes.trim()) {
                Swal.fire({
                    title: 'Peringatan!',
                    text: 'Silakan isi catatan termasuk alamat pengiriman!',
                    icon: 'warning',
                    confirmButtonColor: '#ff6b93'
                });
                return;
            }

            // Show confirmation dialog
            Swal.fire({
                title: 'Konfirmasi Pesanan',
                html: `
                    <div style="text-align: left;">
                        <p><strong>Produk:</strong> ${productName.split(' - ')[0]}</p>
                        <p><strong>Jumlah:</strong> ${quantity} pcs</p>
                        <p><strong>Total Harga:</strong> ${totalPrice}</p>
                        <p><strong>Catatan:</strong> ${notes.substring(0, 100)}${notes.length > 100 ? '...' : ''}</p>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#ff6b93',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Buat Pesanan',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Submit the form
                    document.getElementById('orderForm').submit();
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

        // Initialize total calculation
        document.addEventListener('DOMContentLoaded', function() {
            calculateTotal();
        });
    </script>
</body>

</html>