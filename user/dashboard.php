<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

Auth::requireLogin();

// Get user data
$user = Auth::getUser();

// Jika tidak ada user, redirect ke login
if (!$user) {
    $_SESSION['error'] = 'Sesi telah berakhir. Silakan login kembali.';
    header('Location: ../login.php');
    exit();
}

// Get user statistics
try {
    // Order count
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM orders WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $orderCount = $stmt->fetch()['total'];

    // Pending orders
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM orders WHERE user_id = ? AND status = 'pending'");
    $stmt->execute([$_SESSION['user_id']]);
    $pendingRequests = $stmt->fetch()['total'];

    // Completed orders
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM orders WHERE user_id = ? AND status = 'completed'");
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch();
    $completedOrders = $result ? $result['total'] : 0;

    // Get limited edition products
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.type = 'limited'
        AND p.stock > 0
        AND p.status = 'available'
        AND p.deleted_at IS NULL
        ORDER BY p.created_at DESC 
        LIMIT 6
    ");
    $stmt->execute();
    $limitedProducts = $stmt->fetchAll();

    // Get regular products
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.type = 'regular'
        AND p.stock > 0
        AND p.status = 'available'
        AND p.deleted_at IS NULL
        ORDER BY p.created_at DESC 
        LIMIT 6
    ");
    $stmt->execute();
    $regularProducts = $stmt->fetchAll();

} catch (PDOException $e) {
    $orderCount = 0;
    $pendingRequests = 0;
    $completedOrders = 0;
    $limitedProducts = [];
    $regularProducts = [];
    // Debug error
    error_log("Database error: " . $e->getMessage());
}

// Function to get correct image path
function getProductImage($image_url)
{
    if (empty($image_url)) {
        return false;
    }

    // Cek apakah URL absolute
    if (filter_var($image_url, FILTER_VALIDATE_URL)) {
        return $image_url;
    }

    // Path untuk assets/images/products
    $base_path = '../assets/images/products/';

    // Hapus directory traversal jika ada
    $image_url = basename($image_url);

    // Cek apakah file ada di lokasi baru
    $full_path = $base_path . $image_url;
    if (file_exists($full_path)) {
        return $full_path;
    }

    // Coba cari dengan ekstensi yang berbeda
    $image_name = pathinfo($image_url, PATHINFO_FILENAME);
    $extensions = ['.jpg', '.jpeg', '.png', '.gif', '.webp'];

    foreach ($extensions as $ext) {
        $test_path = $base_path . $image_name . $ext;
        if (file_exists($test_path)) {
            return $test_path;
        }

        // Coba dengan uppercase extension
        $test_path = $base_path . $image_name . strtoupper($ext);
        if (file_exists($test_path)) {
            return $test_path;
        }
    }

    return false;
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Roncelizz</title>
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
            --danger: #ff4757;
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

        /* Header Welcome */
        .welcome-header {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            border-left: 5px solid var(--pink);
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
            color: var(--pink);
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

        .last-login {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--gray);
            font-size: 14px;
            background: var(--light);
            padding: 8px 15px;
            border-radius: 8px;
            position: relative;
            z-index: 1;
        }

        .last-login i {
            color: var(--purple);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
            border: 1px solid var(--border);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
        }

        .stat-card:nth-child(1)::before {
            background: var(--pink);
        }

        .stat-card:nth-child(2)::before {
            background: var(--peach);
        }

        .stat-card:nth-child(3)::before {
            background: var(--mint);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .stat-number {
            font-size: 36px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 5px;
        }

        .stat-label {
            color: var(--gray);
            font-size: 14px;
            font-weight: 500;
        }

        /* Products Section */
        .products-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border);
        }

        .section-header {
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--light-gray);
        }

        .section-title {
            font-size: 24px;
            color: var(--dark);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-subtitle {
            color: var(--gray);
            font-size: 15px;
        }

        /* Product Grid */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
        }

        .product-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .product-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }

        .product-image-container {
            height: 220px;
            width: 100%;
            overflow: hidden;
            position: relative;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        }

        .product-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform 0.5s ease;
        }

        .product-card:hover .product-image {
            transform: scale(1.05);
        }

        /* Badge Limited */
        .product-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: linear-gradient(45deg, #ff6b93, #ff4757);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            z-index: 10;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            transform: translateZ(0);
            backface-visibility: hidden;
            pointer-events: none;
        }

        .image-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            color: var(--gray);
            padding: 20px;
            text-align: center;
        }

        .image-placeholder i {
            font-size: 40px;
            margin-bottom: 10px;
            color: var(--pink);
        }

        .image-placeholder span {
            font-size: 14px;
            color: var(--gray);
        }

        .product-content {
            padding: 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .product-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 10px;
            line-height: 1.4;
        }

        .product-category {
            color: var(--purple);
            font-size: 13px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .product-description {
            color: var(--gray);
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 15px;
            flex: 1;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
        }

        .product-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding: 10px 0;
            border-top: 1px solid var(--light-gray);
        }

        .product-stock {
            color: var(--pink);
            font-weight: 600;
            background: var(--light);
            padding: 5px 12px;
            border-radius: 12px;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .product-price {
            font-size: 20px;
            font-weight: 700;
            color: var(--pink);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 13px;
            gap: 5px;
            white-space: nowrap;
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

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--light);
            border-radius: 12px;
            border: 2px dashed rgba(0, 0, 0, 0.1);
        }

        .empty-icon {
            font-size: 60px;
            color: var(--pink);
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-text {
            color: var(--gray);
            font-size: 16px;
            margin-bottom: 20px;
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

        /* Responsive Design */
        @media (max-width: 1200px) {
            .product-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
        }

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

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .product-grid {
                grid-template-columns: 1fr;
            }

            .product-actions {
                display: flex;
                flex-direction: column;
                gap: 6px;
            }

            .product-actions .btn {
                min-width: 100px;
                text-align: center;
                padding: 6px 12px;
                font-size: 14px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                border-radius: 8px;
            }

            .product-actions .btn i {
                font-size: 14px;
                width: 18px;
            }

            .product-footer {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 10px;
            }

            .product-price {
                text-align: center;
            }
        }

        @media (max-width: 576px) {
            .welcome-header h1 {
                font-size: 24px;
            }

            .section-title {
                font-size: 20px;
            }

            .menu-items {
                flex-direction: column;
            }

            .menu-item {
                width: 100%;
            }

            .submenu {
                padding-left: 40px;
            }
        }

        /* Animation */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
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
                        <a href="dashboard.php" class="menu-link active">
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
            <div class="welcome-header">
                <h1>Halo, <?php echo htmlspecialchars($user['full_name'] ?? 'Teman'); ?>! 🥰</h1>
                <p>Selamat datang kembali di Roncelizz ❤️ Temukan manik-manik eksklusif favoritmu!</p>
                <div class="last-login">
                    <i class="fas fa-clock"></i> Terakhir login: <?php echo date('d M Y, H:i'); ?>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $orderCount; ?></div>
                    <div class="stat-label">Total Pesanan</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $pendingRequests; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $completedOrders; ?></div>
                    <div class="stat-label">Selesai</div>
                </div>
            </div>

            <!-- Limited Edition Products -->
            <div class="products-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-star"></i> Produk Limited Edition
                    </h2>
                    <p class="section-subtitle">
                        Stok terbatas! Pesan sekarang sebelum kehabisan ⏰
                    </p>
                </div>

                <?php if (empty($limitedProducts)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-box-open"></i>
                        </div>
                        <h3>Belum ada produk limited edition</h3>
                        <p class="empty-text">Tunggu produk limited edition berikutnya ya!</p>
                    </div>
                <?php else: ?>
                    <div class="product-grid">
                        <?php foreach ($limitedProducts as $product): ?>
                            <?php
                            $imagePath = getProductImage($product['image_url']);
                            ?>
                            <div class="product-card">
                                <span class="product-badge">LIMITED</span>
                                <div class="product-image-container">
                                    <?php if ($imagePath): ?>
                                        <img src="<?php echo htmlspecialchars($imagePath); ?>"
                                            alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image"
                                            loading="lazy"
                                            onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'image-placeholder\'><i class=\'fas fa-gem\'></i><span>Gambar tidak tersedia</span></div>';">
                                    <?php else: ?>
                                        <div class="image-placeholder">
                                            <i class="fas fa-gem"></i>
                                            <span>Tidak ada gambar</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="product-content">
                                    <h3 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                                    <div class="product-category">
                                        <i class="fas fa-tag"></i>
                                        <?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?>
                                    </div>
                                    <p class="product-description">
                                        <?php
                                        $desc = htmlspecialchars($product['description'] ?? '', ENT_QUOTES, 'UTF-8');
                                        echo strlen($desc) > 100 ? substr($desc, 0, 100) . '...' : $desc;
                                        ?>
                                    </p>

                                    <div class="product-meta">
                                        <span class="product-stock">
                                            <i class="fas fa-box"></i> Stok: <?php echo $product['stock']; ?>
                                        </span>
                                    </div>

                                    <div class="product-footer">
                                        <div class="product-price">Rp
                                            <?php echo number_format($product['price'], 0, ',', '.'); ?>
                                        </div>
                                        <div class="product-actions">
                                            <a href="order.php?product_id=<?php echo $product['id']; ?>"
                                                class="btn btn-primary">
                                                <i class="fas fa-shopping-cart"></i> Pesan
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Regular Products -->
            <div class="products-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-gem"></i> Produk Regular
                    </h2>
                    <p class="section-subtitle">
                        Produk berkualitas dengan harga terjangkau ✨
                    </p>
                </div>

                <?php if (empty($regularProducts)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-box-open"></i>
                        </div>
                        <h3>Belum ada produk regular</h3>
                        <p class="empty-text">Produk sedang dalam persiapan...</p>
                    </div>
                <?php else: ?>
                    <div class="product-grid">
                        <?php foreach ($regularProducts as $product): ?>
                            <?php
                            $imagePath = getProductImage($product['image_url']);
                            ?>
                            <div class="product-card">
                                <div class="product-image-container">
                                    <?php if ($imagePath): ?>
                                        <img src="<?php echo htmlspecialchars($imagePath); ?>"
                                            alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image"
                                            loading="lazy"
                                            onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'image-placeholder\'><i class=\'fas fa-gem\'></i><span>Gambar tidak tersedia</span></div>';">
                                    <?php else: ?>
                                        <div class="image-placeholder">
                                            <i class="fas fa-gem"></i>
                                            <span>Tidak ada gambar</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="product-content">
                                    <h3 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                                    <div class="product-category">
                                        <i class="fas fa-tag"></i>
                                        <?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?>
                                    </div>
                                    <p class="product-description">
                                        <?php
                                        $desc = htmlspecialchars($product['description'] ?? '', ENT_QUOTES, 'UTF-8');
                                        echo strlen($desc) > 100 ? substr($desc, 0, 100) . '...' : $desc;
                                        ?>
                                    </p>

                                    <div class="product-meta">
                                        <span class="product-stock">
                                            <i class="fas fa-box"></i> Stok: <?php echo $product['stock']; ?>
                                        </span>
                                    </div>

                                    <div class="product-footer">
                                        <div class="product-price">Rp
                                            <?php echo number_format($product['price'], 0, ',', '.'); ?>
                                        </div>
                                        <div class="product-actions">
                                            <a href="order.php?product_id=<?php echo $product['id']; ?>"
                                                class="btn btn-primary">
                                                <i class="fas fa-shopping-cart"></i> Pesan
                                            </a>
                                            <a href="request.php?product_id=<?php echo $product['id']; ?>"
                                                class="btn btn-secondary">
                                                <i class="fas fa-edit"></i> Custom
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Interaksi sederhana untuk menu
        document.addEventListener('DOMContentLoaded', function () {
            const menuLinks = document.querySelectorAll('.menu-link');
            const productCards = document.querySelectorAll('.product-card');

            // Menu active state
            menuLinks.forEach(link => {
                if (link.href === window.location.href) {
                    link.classList.add('active');
                }

                link.addEventListener('click', function (e) {
                    if (!this.classList.contains('active')) {
                        menuLinks.forEach(l => l.classList.remove('active'));
                        this.classList.add('active');
                    }
                });
            });

            // Product card hover effect
            productCards.forEach(card => {
                card.addEventListener('mouseenter', function () {
                    this.style.transform = 'translateY(-8px)';
                });

                card.addEventListener('mouseleave', function () {
                    this.style.transform = 'translateY(0)';
                });
            });

            // Image loading handling
            const images = document.querySelectorAll('.product-image');
            images.forEach(img => {
                img.addEventListener('load', function () {
                    this.classList.add('loaded');
                });
            });

            // Add animation for stat cards
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Add fade-in animation to product cards
            productCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100 + 300);
            });
        });

        // Fungsi konfirmasi logout dengan modal SweetAlert2
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
    </script>
</body>

</html>