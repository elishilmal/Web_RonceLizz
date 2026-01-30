<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

Auth::requireAdmin();

// Get order ID from URL
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$order_id) {
    header('Location: orders.php');
    exit();
}

$message = '';
$messageType = '';
$order = null;
$payments = [];
$user = null;

// Get order details
try {
    $sql = "SELECT o.*, u.username, u.full_name, u.email, u.phone, u.address,
                   p.name as product_name, p.type as product_type, p.description as product_description,
                   p.price as product_price, p.stock as product_stock, p.image_url as product_image,
                   c.name as category_name
            FROM orders o 
            JOIN users u ON o.user_id = u.id 
            JOIN products p ON o.product_id = p.id
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE o.id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();

    if (!$order) {
        $_SESSION['error'] = 'Pesanan tidak ditemukan!';
        header('Location: orders.php');
        exit();
    }

    // Get payment history
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE order_id = ? ORDER BY created_at DESC");
    $stmt->execute([$order_id]);
    $payments = $stmt->fetchAll();

    // Handle status update
    if (isset($_GET['action']) && in_array($_GET['action'], ['processing', 'completed', 'cancelled'])) {
        $action = $_GET['action'];

        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$action, $order_id]);

        // Notification for user
        $statusText = $action == 'processing' ? 'sedang diproses' :
            ($action == 'completed' ? 'telah selesai' : 'dibatalkan');

        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $order['user_id'],
            'Status Pesanan Diperbarui',
            'Pesanan #' . $order['order_code'] . ' ' . $statusText,
            $action == 'completed' ? 'success' : ($action == 'cancelled' ? 'error' : 'info')
        ]);

        $message = "Status pesanan berhasil diperbarui menjadi " . $statusText . "!";
        $messageType = 'success';

        // Refresh order data
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$order_id]);
        $order = $stmt->fetch();
    }

} catch (PDOException $e) {
    $message = "Error: " . $e->getMessage();
    $messageType = 'error';
}

// Function buat status
function getStatusBadge($status)
{
    $statuses = [
        'pending' => ['label' => 'Pending', 'color' => 'warning'],
        'processing' => ['label' => 'Diproses', 'color' => 'info'],
        'completed' => ['label' => 'Selesai', 'color' => 'success'],
        'cancelled' => ['label' => 'Dibatalkan', 'color' => 'danger']
    ];

    return isset($statuses[$status]) ? $statuses[$status] : ['label' => 'Unknown', 'color' => 'secondary'];
}

// Function buat status pembayaran
function getPaymentStatusBadge($status)
{
    $statuses = [
        'pending' => ['label' => 'Menunggu Pembayaran', 'color' => 'warning'],
        'paid' => ['label' => 'Lunas', 'color' => 'success'],
        'cancelled' => ['label' => 'Dibatalkan', 'color' => 'danger'],
        'expired' => ['label' => 'Kadaluarsa', 'color' => 'secondary']
    ];

    return isset($statuses[$status]) ? $statuses[$status] : ['label' => 'Unknown', 'color' => 'secondary'];
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan #<?php echo $order['order_code']; ?> - Roncelizz</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
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
            --warning: #ffc107;
            --info: #17a2b8;
            --success: #28a745;
            --danger: #dc3545;
            --secondary: #6c757d;
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

        /* Dashboard Container */
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 30px;
            background: var(--light);
            min-height: 100vh;
        }

        /* Header */
        .header {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .page-title {
            font-size: 28px;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: var(--white);
            color: var(--dark);
            text-decoration: none;
            border-radius: 8px;
            border: 2px solid var(--border);
            transition: all 0.3s;
            font-weight: 500;
        }

        .back-btn:hover {
            background: var(--light-gray);
            border-color: var(--purple);
            transform: translateY(-2px);
        }

        /* Alert */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
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

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-icon {
            font-size: 20px;
        }

        /* Order Header */
        .order-header {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            border-left: 5px solid var(--purple);
        }

        .order-code-header {
            font-size: 24px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .order-date {
            color: var(--gray);
            font-size: 14px;
            margin-bottom: 15px;
        }

        /* Status Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending {
            background: rgba(255, 193, 7, 0.1);
            color: var(--warning);
            border: 1px solid rgba(255, 193, 7, 0.2);
        }

        .status-processing {
            background: rgba(23, 162, 184, 0.1);
            color: var(--info);
            border: 1px solid rgba(23, 162, 184, 0.2);
        }

        .status-completed {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
            border: 1px solid rgba(40, 167, 69, 0.2);
        }

        .status-cancelled {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
            border: 1px solid rgba(220, 53, 69, 0.2);
        }

        /* Order Content */
        .order-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
        }

        @media (max-width: 992px) {
            .order-content {
                grid-template-columns: 1fr;
            }
        }

        /* Card Styles */
        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border);
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--light-gray);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Product Info */
        .product-info {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .product-image {
            width: 120px;
            height: 120px;
            border-radius: 10px;
            overflow: hidden;
            background: var(--light-gray);
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .product-details {
            flex: 1;
        }

        .product-name {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--dark);
        }

        .product-category {
            color: var(--purple);
            font-size: 14px;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .product-price {
            font-size: 20px;
            font-weight: 700;
            color: var(--pink);
            margin-bottom: 5px;
        }

        .product-stock {
            font-size: 14px;
            color: var(--gray);
        }

        /* Order Summary */
        .order-summary-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid var(--light-gray);
        }

        .order-summary-item:last-child {
            border-bottom: none;
        }

        .order-summary-total {
            font-size: 18px;
            font-weight: 700;
            color: var(--pink);
            margin-top: 10px;
            padding-top: 10px;
            border-top: 2px solid var(--light-gray);
        }

        /* Customer Info */
        .customer-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        @media (max-width: 576px) {
            .customer-info {
                grid-template-columns: 1fr;
            }
        }

        .info-item {
            padding: 10px 0;
        }

        .info-label {
            font-size: 12px;
            color: var(--gray);
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            font-size: 15px;
            font-weight: 500;
            color: var(--dark);
        }

        /* Payment History */
        .payment-history {
            margin-top: 25px;
        }

        .payment-item {
            padding: 15px;
            border: 1px solid var(--border);
            border-radius: 8px;
            margin-bottom: 15px;
            background: var(--light);
        }

        .payment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .payment-amount {
            font-size: 18px;
            font-weight: 700;
            color: var(--pink);
        }

        .payment-method {
            font-size: 14px;
            color: var(--gray);
        }

        .payment-date {
            font-size: 13px;
            color: var(--gray);
        }

        .payment-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .payment-status-pending {
            background: rgba(255, 193, 7, 0.1);
            color: var(--warning);
        }

        .payment-status-paid {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
        }

        .payment-status-cancelled {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
        }

        .payment-status-expired {
            background: rgba(108, 117, 125, 0.1);
            color: var(--secondary);
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
            font-family: 'Poppins', sans-serif;
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

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        .btn-warning {
            background: var(--warning);
            color: white;
        }

        .btn-warning:hover {
            background: #e0a800;
            transform: translateY(-2px);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            background: var(--light);
            border-radius: 12px;
            border: 2px dashed rgba(0, 0, 0, 0.1);
        }

        .empty-icon {
            font-size: 50px;
            color: var(--pink);
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-text {
            color: var(--gray);
            font-size: 16px;
            margin-bottom: 20px;
        }

        /* SweetAlert2 Custom Styles */
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
    </style>
</head>

<body>
    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <h1 class="page-title">
                <i class="fas fa-file-invoice"></i>
                Detail Pesanan
            </h1>
            <a href="orders.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Kembali ke Daftar Pesanan
            </a>
        </div>

        <!-- Alert Messages -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType == 'success' ? 'success' : 'error'; ?>">
                <i
                    class="fas fa-<?php echo $messageType == 'success' ? 'check-circle' : 'exclamation-circle'; ?> alert-icon"></i>
                <span><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($order): ?>
            <!-- Order Header -->
            <div class="order-header">
                <div class="order-code-header">
                    <i class="fas fa-hashtag"></i>
                    <?php echo htmlspecialchars($order['order_code']); ?>
                    <span class="status-badge status-<?php echo $order['status']; ?>">
                        <i class="fas fa-circle"></i>
                        <?php echo getStatusBadge($order['status'])['label']; ?>
                    </span>
                </div>
                <div class="order-date">
                    <i class="far fa-calendar-alt"></i>
                    <?php echo date('d F Y H:i', strtotime($order['created_at'])); ?>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <?php if ($order['status'] == 'pending'): ?>
                        <button type="button" class="btn btn-primary"
                            onclick="confirmAction('processing', '<?php echo $order['order_code']; ?>')">
                            <i class="fas fa-cog"></i> Proses Pesanan
                        </button>
                    <?php endif; ?>

                    <?php if ($order['status'] == 'processing'): ?>
                        <button type="button" class="btn btn-success"
                            onclick="confirmAction('completed', '<?php echo $order['order_code']; ?>')">
                            <i class="fas fa-check"></i> Tandai Selesai
                        </button>
                    <?php endif; ?>

                    <?php if (in_array($order['status'], ['pending', 'processing'])): ?>
                        <button type="button" class="btn btn-danger"
                            onclick="confirmAction('cancelled', '<?php echo $order['order_code']; ?>')">
                            <i class="fas fa-times"></i> Batalkan Pesanan
                        </button>
                    <?php endif; ?>

                    <a href="payments.php?order_id=<?php echo $order_id; ?>" class="btn btn-secondary">
                        <i class="fas fa-money-bill-wave"></i> Lihat Pembayaran
                    </a>
                </div>
            </div>

            <!-- Order Content -->
            <div class="order-content">
                <!-- Left Column: Product and Order Details -->
                <div class="order-left">
                    <!-- Product Information -->
                    <div class="card">
                        <h3 class="card-title">
                            <i class="fas fa-box"></i>
                            Informasi Produk
                        </h3>

                        <div class="product-info">
                            <div class="product-image">
                                <?php
                                // Function to get correct image path
                                function getImagePath($image_url)
                                {
                                    if (empty($image_url))
                                        return false;
                                    if (filter_var($image_url, FILTER_VALIDATE_URL))
                                        return $image_url;

                                    $base_path = '../assets/images/products/';
                                    $image_url = basename($image_url);
                                    $full_path = $base_path . $image_url;

                                    if (file_exists($full_path))
                                        return $full_path;

                                    // Try different extensions
                                    $image_name = pathinfo($image_url, PATHINFO_FILENAME);
                                    $extensions = ['.jpg', '.jpeg', '.png', '.gif', '.webp'];

                                    foreach ($extensions as $ext) {
                                        $test_path = $base_path . $image_name . $ext;
                                        if (file_exists($test_path))
                                            return $test_path;
                                    }

                                    return false;
                                }

                                $imagePath = getImagePath($order['product_image']);
                                ?>
                                <img src="<?php echo $imagePath ? htmlspecialchars($imagePath) : '../assets/images/default-product.jpg'; ?>"
                                    alt="<?php echo htmlspecialchars($order['product_name']); ?>">
                            </div>

                            <div class="product-details">
                                <h4 class="product-name"><?php echo htmlspecialchars($order['product_name']); ?></h4>
                                <div class="product-category">
                                    <i class="fas fa-tag"></i>
                                    <?php echo htmlspecialchars($order['category_name'] ?? 'Tidak ada kategori'); ?>
                                </div>
                                <div class="product-price">
                                    Rp <?php echo number_format($order['product_price'], 0, ',', '.'); ?>
                                </div>
                                <div class="product-stock">
                                    Stok tersedia: <?php echo $order['product_stock']; ?> pcs
                                </div>
                                <div style="margin-top: 10px;">
                                    <small style="color: var(--gray);">
                                        <?php echo $order['product_type'] == 'limited' ? '<i class="fas fa-crown"></i> Limited Edition' : '<i class="fas fa-star"></i> Regular'; ?>
                                    </small>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($order['product_description'])): ?>
                            <div style="margin-top: 20px;">
                                <h4 style="font-size: 14px; color: var(--gray); margin-bottom: 8px;">Deskripsi Produk:</h4>
                                <p style="color: var(--dark); font-size: 14px; line-height: 1.6;">
                                    <?php echo htmlspecialchars($order['product_description']); ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Order Summary -->
                    <div class="card" style="margin-top: 25px;">
                        <h3 class="card-title">
                            <i class="fas fa-receipt"></i>
                            Ringkasan Pesanan
                        </h3>

                        <div class="order-summary-item">
                            <span>Harga Satuan</span>
                            <span>Rp <?php echo number_format($order['unit_price'], 0, ',', '.'); ?></span>
                        </div>

                        <div class="order-summary-item">
                            <span>Jumlah Pesanan</span>
                            <span><?php echo $order['quantity']; ?> pcs</span>
                        </div>

                        <div class="order-summary-item">
                            <span>Subtotal</span>
                            <span>Rp
                                <?php echo number_format($order['unit_price'] * $order['quantity'], 0, ',', '.'); ?></span>
                        </div>

                        <div class="order-summary-item order-summary-total">
                            <span>Total Pembayaran</span>
                            <span>Rp <?php echo number_format($order['total_price'], 0, ',', '.'); ?></span>
                        </div>

                        <?php if (!empty($order['notes'])): ?>
                            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--light-gray);">
                                <h4 style="font-size: 14px; color: var(--gray); margin-bottom: 8px;">Catatan Pesanan:</h4>
                                <p
                                    style="color: var(--dark); font-size: 14px; line-height: 1.6; background: var(--light); padding: 10px; border-radius: 6px;">
                                    <?php echo htmlspecialchars($order['notes']); ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="order-right">
                    <!-- Customer Information -->
                    <div class="card">
                        <h3 class="card-title">
                            <i class="fas fa-user"></i>
                            Informasi Pelanggan
                        </h3>

                        <div class="customer-info">
                            <div class="info-item">
                                <div class="info-label">Nama Lengkap</div>
                                <div class="info-value"><?php echo htmlspecialchars($order['full_name']); ?></div>
                            </div>

                            <div class="info-item">
                                <div class="info-label">Username</div>
                                <div class="info-value">@<?php echo htmlspecialchars($order['username']); ?></div>
                            </div>

                            <div class="info-item">
                                <div class="info-label">Email</div>
                                <div class="info-value"><?php echo htmlspecialchars($order['email']); ?></div>
                            </div>

                            <?php if (!empty($order['phone'])): ?>
                                <div class="info-item">
                                    <div class="info-label">Telepon</div>
                                    <div class="info-value"><?php echo htmlspecialchars($order['phone']); ?></div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($order['address'])): ?>
                                <div class="info-item" style="grid-column: 1 / -1;">
                                    <div class="info-label">Alamat</div>
                                    <div class="info-value"><?php echo htmlspecialchars($order['address']); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Payment History -->
                    <div class="card" style="margin-top: 25px;">
                        <h3 class="card-title">
                            <i class="fas fa-history"></i>
                            Riwayat Pembayaran
                        </h3>

                        <?php if (empty($payments)): ?>
                            <div class="empty-state" style="padding: 20px;">
                                <div class="empty-icon">
                                    <i class="fas fa-money-bill-wave"></i>
                                </div>
                                <h4>Belum ada pembayaran</h4>
                                <p class="empty-text">Belum ada riwayat pembayaran untuk pesanan ini.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($payments as $payment): ?>
                                <?php
                                $paymentStatus = getPaymentStatusBadge($payment['status']);
                                ?>
                                <div class="payment-item">
                                    <div class="payment-header">
                                        <div class="payment-amount">
                                            Rp <?php echo number_format($payment['amount'], 0, ',', '.'); ?>
                                        </div>
                                        <span class="payment-status payment-status-<?php echo $payment['status']; ?>">
                                            <?php echo $paymentStatus['label']; ?>
                                        </span>
                                    </div>

                                    <div class="payment-method">
                                        Metode: <?php echo htmlspecialchars($payment['payment_method'] ?? '-'); ?>
                                    </div>

                                    <?php if (!empty($payment['payment_proof'])): ?>
                                        <div style="margin-top: 8px;">
                                            <small style="color: var(--gray);">Bukti pembayaran tersedia</small>
                                        </div>
                                    <?php endif; ?>

                                    <div class="payment-date">
                                        <i class="far fa-clock"></i>
                                        <?php echo date('d/m/Y H:i', strtotime($payment['created_at'])); ?>
                                    </div>

                                    <?php if (!empty($payment['notes'])): ?>
                                        <div style="margin-top: 8px; padding-top: 8px; border-top: 1px solid rgba(0,0,0,0.1);">
                                            <small
                                                style="color: var(--gray);"><?php echo htmlspecialchars($payment['notes']); ?></small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <div style="text-align: center; margin-top: 15px;">
                            <a href="payments.php?order_id=<?php echo $order_id; ?>" class="btn btn-secondary"
                                style="width: 100%;">
                                <i class="fas fa-external-link-alt"></i> Kelola Pembayaran
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function confirmAction(action, orderCode) {
            const actionText = action === 'processing' ? 'proses' :
                action === 'completed' ? 'selesai' : 'batalkan';

            const actionColor = action === 'processing' ? '#17a2b8' :
                action === 'completed' ? '#28a745' : '#dc3545';

            const actionIcon = action === 'processing' ? 'cog' :
                action === 'completed' ? 'check' : 'times';

            const actionTitle = action === 'processing' ? 'Proses Pesanan' :
                action === 'completed' ? 'Selesaikan Pesanan' : 'Batalkan Pesanan';

            Swal.fire({
                title: actionTitle,
                html: `
                    <div style="text-align: center;">
                        <i class="fas fa-${actionIcon}" style="font-size: 60px; color: ${actionColor}; margin-bottom: 20px;"></i>
                        <p>Anda akan mengubah status pesanan:</p>
                        <p><strong>#${orderCode}</strong></p>
                        <p>Status akan diubah menjadi: <strong>${actionText.toUpperCase()}</strong></p>
                        <p style="color: #666; font-size: 14px; margin-top: 10px;">Apakah Anda yakin?</p>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: actionColor,
                cancelButtonColor: '#6c757d',
                confirmButtonText: `Ya, ${actionText}`,
                cancelButtonText: 'Batal',
                reverseButtons: true,
                customClass: {
                    confirmButton: 'btn',
                    cancelButton: 'btn btn-secondary'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Redirect ke action URL
                    window.location.href = `?id=<?php echo $order_id; ?>&action=${action}`;
                }
            });
        }

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