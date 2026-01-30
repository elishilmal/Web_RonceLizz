<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

Auth::requireLogin();

$user = Auth::getUser();
$message = '';
$error = '';
$orders = [];

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$query = "
    SELECT 
        o.*,
        p.name as product_name,
        p.price,
        DATE_FORMAT(o.created_at, '%d %b %Y %H:%i') as formatted_date,
        (o.unit_price * o.quantity) as total_amount
    FROM orders o
    LEFT JOIN products p ON o.product_id = p.id
    WHERE o.user_id = ?
";

$params = [$_SESSION['user_id']];

// Add status filter
if (!empty($status_filter)) {
    $query .= " AND o.status = ?";
    $params[] = $status_filter;
}

// Add search filter
if (!empty($search)) {
    $query .= " AND (o.order_code LIKE ? OR p.name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

$query .= " ORDER BY o.created_at DESC";

// Get orders
try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
} catch (PDOException $e) {
    $orders = [];
    error_log("Error fetching orders: " . $e->getMessage());
}

// Get order statistics
try {
    // Total orders
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM orders WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $total_orders = $stmt->fetch()['total'];

    // Pending orders
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM orders WHERE user_id = ? AND status = 'pending'");
    $stmt->execute([$_SESSION['user_id']]);
    $pending_orders = $stmt->fetch()['total'];

    // Processing orders 
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM orders WHERE user_id = ? AND status = 'processing'");
    $stmt->execute([$_SESSION['user_id']]);
    $processing_orders = $stmt->fetch()['total'];

    // Completed orders
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM orders WHERE user_id = ? AND status = 'completed'");
    $stmt->execute([$_SESSION['user_id']]);
    $completed_orders = $stmt->fetch()['total'];

    // Cancelled orders
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM orders WHERE user_id = ? AND status = 'cancelled'");
    $stmt->execute([$_SESSION['user_id']]);
    $cancelled_orders = $stmt->fetch()['total'];
} catch (PDOException $e) {
    $total_orders = $pending_orders = $processing_orders = $completed_orders = $cancelled_orders = 0;
    error_log("Error fetching order statistics: " . $e->getMessage());
}

// Function to get status badge class
function getStatusBadge($status)
{
    $classes = [
        'pending' => 'status-pending',
        'processing' => 'status-processing',
        'completed' => 'status-completed',
        'cancelled' => 'status-cancelled'
    ];
    return $classes[$status] ?? 'status-pending';
}

// Function to get status text
function getStatusText($status)
{
    $texts = [
        'pending' => 'Menunggu',
        'processing' => 'Diproses',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan'
    ];
    return $texts[$status] ?? $status;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Pesanan - Roncelizz</title>
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

        /* Stats Cards */
        .stats-cards {
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
            cursor: pointer;
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

        /* Filter Section */
        .filter-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border);
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            align-items: end;
        }

        .filter-group {
            margin-bottom: 0;
        }

        .filter-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
            font-size: 14px;
        }

        .filter-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            transition: all 0.3s;
        }

        .filter-control:focus {
            outline: none;
            border-color: var(--pink);
            box-shadow: 0 0 0 3px rgba(255, 107, 147, 0.1);
        }

        .filter-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%23333' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 12px;
        }

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

        /* Orders List */
        .orders-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
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

        /* Order Cards */
        .orders-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .order-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            border: 1px solid var(--border);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .order-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .order-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 5px;
        }

        .order-code {
            color: var(--purple);
            font-weight: 500;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .order-code i {
            font-size: 12px;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .status-processing {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .order-body {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .order-info {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .info-row {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: var(--gray);
        }

        .info-row i {
            color: var(--pink);
            width: 16px;
            text-align: center;
        }

        .info-label {
            font-weight: 500;
            color: var(--dark);
            min-width: 120px;
        }

        .info-value {
            color: var(--gray);
        }

        .order-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 20px;
            margin-top: 20px;
            border-top: 1px solid var(--light-gray);
            flex-wrap: wrap;
            gap: 15px;
        }

        .order-date {
            color: var(--gray);
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .order-actions {
            display: flex;
            gap: 10px;
        }

        .btn-small {
            padding: 8px 16px;
            font-size: 13px;
        }

        .btn-view {
            background: var(--purple);
            color: white;
        }

        .btn-view:hover {
            background: #7c4dff;
        }

        .btn-cancel {
            background: #dc3545;
            color: white;
        }

        .btn-cancel:hover {
            background: #c82333;
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

        .order-card {
            animation: fadeIn 0.5s ease;
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

            .order-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .order-body {
                grid-template-columns: 1fr;
            }

            .order-footer {
                flex-direction: column;
                align-items: stretch;
            }

            .order-actions {
                width: 100%;
                justify-content: center;
            }

            .btn-small {
                flex: 1;
            }
        }

        @media (max-width: 576px) {
            .stats-cards {
                grid-template-columns: 1fr;
            }

            .filter-grid {
                grid-template-columns: 1fr;
            }

            .btn {
                width: 100%;
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
                                <a href="order_history.php" class="menu-link active">
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
                    <i class="fas fa-history"></i> Riwayat Pesanan
                </h1>
                <p>Kelola dan pantau status pesanan Anda</p>
            </div>

            <!-- Stats Cards -->
            <div class="stats-cards">
                <div class="stat-card" onclick="filterStatus('')">
                    <div class="stat-number"><?php echo $total_orders; ?></div>
                    <div class="stat-label">Total Pesanan</div>
                </div>
                <div class="stat-card" onclick="filterStatus('pending')">
                    <div class="stat-number"><?php echo $pending_orders; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card" onclick="filterStatus('processing')">
                    <div class="stat-number"><?php echo $processing_orders; ?></div>
                    <div class="stat-label">Diproses</div>
                </div>
                <div class="stat-card" onclick="filterStatus('completed')">
                    <div class="stat-number"><?php echo $completed_orders; ?></div>
                    <div class="stat-label">Selesai</div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <form method="GET" action="" class="filter-grid">
                    <div class="filter-group">
                        <label class="filter-label">Cari Pesanan</label>
                        <input type="text" name="search" class="filter-control"
                            value="<?php echo htmlspecialchars($search); ?>"
                            placeholder="Cari berdasarkan kode atau nama produk...">
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Filter Status</label>
                        <select name="status" class="filter-control filter-select">
                            <option value="">Semua Status</option>
                            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending
                            </option>
                            <option value="processing" <?php echo $status_filter == 'processing' ? 'selected' : ''; ?>>
                                Diproses</option>
                            <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>
                                Selesai</option>
                            <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>
                                Dibatalkan</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <button type="submit" class="btn btn-primary" style="height: 46px;">
                            <i class="fas fa-filter"></i> Terapkan Filter
                        </button>
                        <?php if (!empty($search) || !empty($status_filter)): ?>
                            <a href="order_history.php" class="btn btn-outline" style="height: 46px; margin-top: 10px;">
                                <i class="fas fa-times"></i> Reset Filter
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Orders List -->
            <div class="orders-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-clipboard-list"></i> Daftar Pesanan
                    </h2>
                    <p class="section-subtitle">
                        Menampilkan <?php echo count($orders); ?> pesanan
                        <?php if (!empty($status_filter)): ?>
                            dengan status "<?php echo getStatusText($status_filter); ?>"
                        <?php endif; ?>
                    </p>
                </div>

                <?php if (empty($orders)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <h3>Belum ada pesanan</h3>
                        <p class="empty-text">
                            <?php if (!empty($search) || !empty($status_filter)): ?>
                                Tidak ditemukan pesanan dengan filter yang dipilih.
                            <?php else: ?>
                                Anda belum membuat pesanan. Mulai dengan membuat pesanan baru!
                            <?php endif; ?>
                        </p>
                        <a href="order.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Buat Pesanan Baru
                        </a>
                    </div>
                <?php else: ?>
                    <div class="orders-list">
                        <?php foreach ($orders as $order): ?>
                            <div class="order-card">
                                <div class="order-header">
                                    <div>
                                        <h3 class="order-title">
                                            <?php echo htmlspecialchars($order['product_name'] ?? 'Produk tidak tersedia'); ?>
                                        </h3>
                                        <div class="order-code">
                                            <i class="fas fa-hashtag"></i>
                                            <?php echo htmlspecialchars($order['order_code']); ?>
                                        </div>
                                    </div>
                                    <span class="status-badge <?php echo getStatusBadge($order['status']); ?>">
                                        <i class="fas fa-circle"></i>
                                        <?php echo getStatusText($order['status']); ?>
                                    </span>
                                </div>

                                <div class="order-body">
                                    <div class="order-info">
                                        <div class="info-row">
                                            <i class="fas fa-box"></i>
                                            <span class="info-label">Produk:</span>
                                            <span
                                                class="info-value"><?php echo htmlspecialchars($order['product_name'] ?? '-'); ?></span>
                                        </div>
                                        <div class="info-row">
                                            <i class="fas fa-hashtag"></i>
                                            <span class="info-label">Kode Pesanan:</span>
                                            <span
                                                class="info-value"><?php echo htmlspecialchars($order['order_code']); ?></span>
                                        </div>
                                        <div class="info-row">
                                            <i class="fas fa-calendar"></i>
                                            <span class="info-label">Tanggal:</span>
                                            <span class="info-value"><?php echo $order['formatted_date']; ?></span>
                                        </div>
                                    </div>

                                    <div class="order-info">
                                        <div class="info-row">
                                            <i class="fas fa-boxes"></i>
                                            <span class="info-label">Jumlah:</span>
                                            <span class="info-value"><?php echo $order['quantity']; ?> pcs</span>
                                        </div>
                                        <div class="info-row">
                                            <i class="fas fa-money-bill-wave"></i>
                                            <span class="info-label">Harga Satuan:</span>
                                            <span class="info-value">Rp
                                                <?php echo number_format($order['unit_price'], 0, ',', '.'); ?></span>
                                        </div>
                                        <div class="info-row">
                                            <i class="fas fa-calculator"></i>
                                            <span class="info-label">Total:</span>
                                            <span class="info-value" style="font-weight: 600; color: var(--pink);">
                                                Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <?php if (!empty($order['notes'])): ?>
                                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--light-gray);">
                                        <div style="font-weight: 500; color: var(--dark); margin-bottom: 8px; display: block;">
                                            Catatan:
                                        </div>
                                        <div style="color: var(--gray); font-size: 14px; line-height: 1.6;">
                                            <?php echo nl2br(htmlspecialchars($order['notes'])); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="order-footer">
                                    <div class="order-date">
                                        <i class="far fa-clock"></i>
                                        Dibuat: <?php echo $order['formatted_date']; ?>
                                    </div>
                                    <div class="order-actions">
                                        <?php if ($order['status'] == 'pending'): ?>
                                            <button class="btn btn-small btn-cancel"
                                                onclick="cancelOrder(<?php echo $order['id']; ?>)">
                                                <i class="fas fa-times"></i> Batalkan
                                            </button>
                                        <?php endif; ?>
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
        // View order details
        function viewOrder(orderId) {
            alert('Menampilkan detail pesanan ID: ' + orderId);
        }

        // Cancel order
        function cancelOrder(orderId) {
            Swal.fire({
                title: 'Konfirmasi Pembatalan',
                text: 'Apakah Anda yakin ingin membatalkan pesanan ini?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Batalkan',
                cancelButtonText: 'Tidak'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Redirect ke halaman cancel sederhana
                    window.location.href = 'cancel_order.php?id=' + orderId;
                }
            });
        }

        // Filter by clicking stats cards
        function filterStatus(status) {
            const url = new URL(window.location.href);
            if (status) {
                url.searchParams.set('status', status);
            } else {
                url.searchParams.delete('status');
            }
            window.location.href = url.toString();
        }

        // Animate cards on load
        document.addEventListener('DOMContentLoaded', function () {
            const cards = document.querySelectorAll('.order-card');
            cards.forEach((card, index) => {
                card.style.animationDelay = (index * 0.1) + 's';
            });
        });

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
    </script>
</body>

</html>