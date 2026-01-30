<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

Auth::requireAdmin();

try {
    // Total Products
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM products WHERE type != 'request'");
    $totalProducts = $stmt->fetch()['total'];

    // Total Users
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
    $totalUsers = $stmt->fetch()['total'];

    // Total Orders
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM orders");
    $totalOrders = $stmt->fetch()['total'];

    // Pending Requests
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM requests WHERE status = 'pending'");
    $pendingRequests = $stmt->fetch()['total'];

    // Total Revenue
    $stmt = $pdo->query("SELECT SUM(total_price) as total FROM orders WHERE status = 'completed'");
    $totalRevenue = $stmt->fetch()['total'] ?: 0;

    // Recent Orders
    $stmt = $pdo->query("SELECT o.*, u.username, p.name as product_name 
                         FROM orders o 
                         JOIN users u ON o.user_id = u.id 
                         JOIN products p ON o.product_id = p.id 
                         ORDER BY o.created_at DESC LIMIT 5");
    $recentOrders = $stmt->fetchAll();

    // Recent Requests
    $stmt = $pdo->query("SELECT r.*, u.username, c.name as category_name 
                     FROM requests r 
                     JOIN users u ON r.user_id = u.id 
                     LEFT JOIN categories c ON r.category_id = c.id 
                     ORDER BY r.created_at DESC LIMIT 5");
    $recentRequests = $stmt->fetchAll();

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Roncelizz</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* modal SweetAlert2 */
        .swal2-confirm {
            background-color: var(--pink) !important;
            border-color: var(--pink) !important;
        }

        .swal2-cancel {
            background-color: #6c757d !important;
            border-color: #6c757d !important;
        }

        /* DATA GRID & CARDS */
        .data-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .data-card {
            background: var(--white);
            border-radius: var(--border-radius-medium);
            padding: 25px;
            box-shadow: var(--shadow-soft);
            border: 2px solid transparent;
            transition: all 0.3s;
        }

        .data-card:hover {
            box-shadow: var(--shadow-medium);
            border-color: var(--pink-medium);
        }

        .data-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--pink-light);
        }

        .data-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .data-title::before {
            content: '';
            width: 4px;
            height: 20px;
            background: linear-gradient(to bottom, var(--pink), var(--purple));
            border-radius: 2px;
        }

        .view-all {
            color: var(--purple);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            padding: 5px 10px;
            border-radius: 5px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .view-all:hover {
            background: var(--pink-light);
            color: var(--purple-dark);
            transform: translateX(5px);
        }

        .order-item,
        .request-item {
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
            transition: all 0.3s;
        }

        .order-item:last-child,
        .request-item:last-child {
            border-bottom: none;
        }

        .order-item:hover,
        .request-item:hover {
            background: var(--gray-light);
            padding-left: 10px;
            padding-right: 10px;
            margin-left: -10px;
            margin-right: -10px;
            border-radius: 5px;
        }

        .order-code,
        .request-code {
            font-weight: 600;
            color: var(--purple);
            font-size: 0.9rem;
            margin-bottom: 5px;
        }

        .order-user,
        .request-title {
            color: var(--dark);
            font-weight: 500;
            margin-bottom: 5px;
            font-size: 1rem;
        }

        .order-price {
            font-weight: 600;
            color: var(--pink);
            font-size: 1rem;
            margin-bottom: 5px;
        }

        .order-status,
        .request-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .order-status {
            margin-top: 5px;
        }

        /* QUICK ACTIONS */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .action-btn {
            background: var(--white);
            padding: 25px;
            border-radius: var(--border-radius-medium);
            text-decoration: none;
            color: var(--dark);
            box-shadow: var(--shadow-soft);
            transition: all 0.3s ease;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
        }

        .action-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(to right, var(--pink), var(--purple), var(--mint));
        }

        .action-btn:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-medium);
            border-color: var(--pink);
        }

        .action-btn:hover .action-icon {
            transform: scale(1.1) rotate(5deg);
            background: linear-gradient(135deg, var(--pink), var(--purple));
        }

        .action-icon {
            font-size: 28px;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--pink-light), var(--purple-light));
            color: var(--purple);
            border-radius: var(--border-radius-circle);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            border: 2px solid var(--pink-light);
        }

        .action-title {
            font-weig ht: 600;
            margin-bottom: 10px;
            font-size: 1.1rem;
            color: var(--dark);
        }

        .action-desc {
            color: var(--gray);
            font-size: 0.9rem;
            line-height: 1.5;
            max-width: 200px;
            margin: 0 auto;
        }

        /* Request Item Styles */
        .request-item {
            padding: 15px;
            border-bottom: 1px solid var(--light-gray);
            transitio n: background-color 0.3s;
        }

        .request-item:hover {
            background-color: rgba(255, 107, 147, 0.05);
        }

        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margi n-bottom: 10px;
        }

        .request-title {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 5px;
            line-height: 1.3;
        }

        .request-user {
            font-size: 0.85rem;
            color: var(--gray-dark);
            font-style: italic;
        }

        .request-details {
            margin-top: 10px;
        }

        .request-info {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 10px 0;
        }

        .request-info span {
            font-size: 0.8rem;
            color: var(--gray);
            background: #f8f9fa;
            padding: 4px 10px;
            border-radius: 15px;
            display: flex;
            align-ite ms: center;
            gap: 5px;
            border: 1px solid #eee;
        }

        .request-info i {
            font-size: 0.8rem;
            color: var(--purple);
        }

        .request-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px dashed #eee;
        }

        .request-date {
            font-size: 0.8rem;
            color: var(--gray);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .request-date i {
            color: var(--pink);
        }

        /* Status Styles */
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .status-processing {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .status-completed {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .status-in_progress {
            background-color: #cce5ff;
            color: #004085;
            border: 1px solid #b8daff;
        }

        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Responsive design */
        @media (max-width: 1024px) {
            .data-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .data-card {
                padding: 20px;
            }

            .data-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .view-all {
                align-self: flex-end;
            }

            .request-info {
                flex-direction: column;
                gap: 5px;
            }

            .request-footer {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .quick-actions {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 15px;
            }

            .action-btn {
                padding: 20px;
            }

            .request-header {
                flex-direction: column;
            }

            .request-user {
                margin-top: 5px;
            }
        }

        @media (max-width: 480px) {
            .data-card {
                padding: 15px;
            }

            .data-title {
                font-size: 1.1rem;
            }

            .order-item,
            .request-item {
                padding: 12px 0;
            }

            .quick-actions {
                grid-template-columns: 1fr;
            }

            .action-icon {
                width: 50px;
                height: 50px;
                font-size: 24px;
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

        <div class="header">
            <div>
                <h1 class="page-title">Dashboard Admin 💎</h1>
                <p class="page-subtitle">Selamat datang di panel administrasi Roncelizz</p>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card products">
                <div class="stat-icon">
                    <i class="fas fa-gem"></i>
                </div>
                <div class="stat-number"><?php echo $totalProducts; ?></div>
                <div class="stat-label">Total Produk</div>
            </div>

            <div class="stat-card users">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-number"><?php echo $totalUsers; ?></div>
                <div class="stat-label">Total Pengguna</div>
            </div>

            <div class="stat-card orders">
                <div class="stat-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="stat-number"><?php echo $totalOrders; ?></div>
                <div class="stat-label">Total Pesanan</div>
            </div>

            <div class="stat-card revenue">
                <div class="stat-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-number">Rp <?php echo number_format($totalRevenue, 0, ',', '.'); ?></div>
                <div class="stat-label">Total Pendapatan</div>
            </div>
        </div>

        <!-- Recent Data -->
        <div class="data-grid">
            <!-- Recent Orders -->
            <div class="data-card">
                <div class="data-header">
                    <h3 class="data-title">Pesanan Terbaru</h3>
                    <a href="orders.php" class="view-all">Lihat Semua →</a>
                </div>

                <?php if (empty($recentOrders)): ?>
                    <p style="text-align: center; color: var(--gray); padding: 20px;">Belum ada pesanan</p>
                <?php else: ?>
                    <?php foreach ($recentOrders as $order): ?>
                        <div class="order-item">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                <div>
                                    <div class="order-code">#<?php echo $order['order_code']; ?></div>
                                    <div class="order-user"><?php echo htmlspecialchars($order['username']); ?> -
                                        <?php echo htmlspecialchars($order['product_name']); ?>
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <div class="order-price">Rp <?php echo number_format($order['total_price'], 0, ',', '.'); ?>
                                    </div>
                                    <span class="order-status status-<?php echo $order['status']; ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Recent Requests -->
            <div class="data-card">
                <div class="data-header">
                    <h3 class="data-title">Request Terbaru</h3>
                    <a href="requests.php" class="view-all">Lihat Semua →</a>
                </div>

                <?php if (empty($recentRequests)): ?>
                    <p style="text-align: center; color: var(--gray); padding: 20px;">Belum ada request</p>
                <?php else: ?>
                    <?php foreach ($recentRequests as $request): ?>
                        <div class="request-item">
                            <div class="request-header">
                                <div>
                                    <div class="request-code">#<?php echo $request['request_code']; ?></div>
                                    <div class="request-title"><?php echo htmlspecialchars($request['product_name']); ?></div>
                                </div>
                                <div class="request-user">By: <?php echo htmlspecialchars($request['username']); ?></div>
                            </div>

                            <div class="request-details">
                                <div class="request-info">
                                    <span><i class="fas fa-layer-group"></i>
                                        <?php echo htmlspecialchars($request['category_name'] ?: 'Uncategorized'); ?></span>
                                    <span><i class="fas fa-cubes"></i> <?php echo number_format($request['quantity']); ?>
                                        pcs</span>
                                    <span><i class="fas fa-money-bill-wave"></i> Rp
                                        <?php echo number_format($request['budget'], 0, ',', '.'); ?></span>
                                </div>

                                <div class="request-footer">
                                    <span class="request-status status-<?php echo $request['status']; ?>">
                                        <?php echo ucfirst($request['status']); ?>
                                    </span>
                                    <span class="request-date">
                                        <i class="far fa-clock"></i>
                                        <?php echo date('d M Y', strtotime($request['created_at'])); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="products.php?action=add" class="action-btn">
                <div class="action-icon">
                    <i class="fas fa-plus-circle"></i>
                </div>
                <div class="action-title">Tambah Produk</div>
                <div class="action-desc">Tambahkan produk baru ke katalog</div>
            </a>

            <a href="requests.php" class="action-btn">
                <div class="action-icon">
                    <i class="fas fa-comment-dots"></i>
                </div>
                <div class="action-title">Review Request</div>
                <div class="action-desc">Tinjau request produk dari user</div>
            </a>

            <a href="orders.php" class="action-btn">
                <div class="action-icon">
                    <i class="fas fa-clipboard-check"></i>
                </div>
                <div class="action-title">Kelola Pesanan</div>
                <div class="action-desc">Proses pesanan dari pelanggan</div>
            </a>

            <a href="reports.php" class="action-btn">
                <div class="action-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="action-title">Generate Laporan</div>
                <div class="action-desc">Buat laporan penjualan PDF</div>
            </a>
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

            // Auto refresh data every 30 seconds
            setInterval(function () {
                console.log('Auto refresh...');
            }, 30000);

            // Fungsi konfirmasi logout dengan modal
            function confirmLogout(event) {
                event.preventDefault(); // Mencegah link default

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