<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

Auth::requireAdmin();

$message = '';
$messageType = '';

// Handle order status update
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = intval($_GET['id']);

    try {
        if (in_array($action, ['processing', 'completed', 'cancelled'])) {
            $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $stmt->execute([$action, $id]);

            // Get order info for notification
            $stmt = $pdo->prepare("SELECT o.*, u.id as user_id FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ?");
            $stmt->execute([$id]);
            $order = $stmt->fetch();

            if ($order) {
                // Get notification for user
                $statusText = $action == 'processing' ? 'sedang diproses' :
                    ($action == 'completed' ? 'telah selesai' : 'dibatalkan');

                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
                $stmt->execute([
                    $order['user_id'],
                    'Status Pesanan Diperbarui',
                    'Pesanan #' . $order['order_code'] . ' ' . $statusText,
                    $action == 'completed' ? 'success' : ($action == 'cancelled' ? 'error' : 'info')
                ]);
            }

            $message = "Status pesanan berhasil diperbarui!";
            $messageType = 'success';
        }
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Get filter parameters
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

$whereClause = "1=1";
$params = [];

if ($filter !== 'all') {
    $whereClause .= " AND o.status = ?";
    $params[] = $filter;
}

if (!empty($search)) {
    $whereClause .= " AND (o.order_code LIKE ? OR u.username LIKE ? OR u.full_name LIKE ? OR p.name LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

try {
    $sql = "SELECT o.*, u.username, u.full_name, u.email, p.name as product_name, p.type as product_type,
                   (SELECT status FROM payments WHERE order_id = o.id ORDER BY id DESC LIMIT 1) as payment_status
            FROM orders o 
            JOIN users u ON o.user_id = u.id 
            JOIN products p ON o.product_id = p.id 
            WHERE $whereClause 
            ORDER BY o.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();

} catch (PDOException $e) {
    $message = "Error: " . $e->getMessage();
    $messageType = 'error';
    $orders = [];
}

// Get statistics
try {
    $stmt = $pdo->query("SELECT 
                         COUNT(*) as total_orders,
                         SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
                         SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_orders,
                         SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
                         SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
                         SUM(CASE WHEN status = 'completed' THEN total_price ELSE 0 END) as total_revenue
                         FROM orders");
    $stats = $stmt->fetch();
} catch (PDOException $e) {
    $stats = [];
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pesanan - Roncelizz</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
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
    </style>
</head>

<body>
    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h1 class="page-title">Kelola Pesanan</h1>
            <p style="color: var(--gray);">Kelola semua pesanan dari pelanggan</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType == 'success' ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card total">
                <div class="stat-number"><?php echo $stats['total_orders'] ?? 0; ?></div>
                <div class="stat-label">Total Pesanan</div>
            </div>

            <div class="stat-card pending">
                <div class="stat-number"><?php echo $stats['pending_orders'] ?? 0; ?></div>
                <div class="stat-label">Pending</div>
            </div>

            <div class="stat-card processing">
                <div class="stat-number"><?php echo $stats['processing_orders'] ?? 0; ?></div>
                <div class="stat-label">Diproses</div>
            </div>

            <div class="stat-card completed">
                <div class="stat-number"><?php echo $stats['completed_orders'] ?? 0; ?></div>
                <div class="stat-label">Selesai</div>
            </div>

            <div class="stat-card revenue">
                <div class="stat-number">Rp
                    <?php echo isset($stats['total_revenue']) ? number_format($stats['total_revenue'], 0, ',', '.') : '0'; ?>
                </div>
                <div class="stat-label">Total Pendapatan</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters">
            <div class="filter-tabs">
                <a href="?filter=all" class="filter-tab <?php echo $filter == 'all' ? 'active' : ''; ?>">
                    Semua
                </a>
                <a href="?filter=pending" class="filter-tab <?php echo $filter == 'pending' ? 'active' : ''; ?>">
                    Pending
                </a>
                <a href="?filter=processing" class="filter-tab <?php echo $filter == 'processing' ? 'active' : ''; ?>">
                    Diproses
                </a>
                <a href="?filter=completed" class="filter-tab <?php echo $filter == 'completed' ? 'active' : ''; ?>">
                    Selesai
                </a>
                <a href="?filter=cancelled" class="filter-tab <?php echo $filter == 'cancelled' ? 'active' : ''; ?>">
                    Dibatalkan
                </a>
            </div>

            <div class="search-box">
                <!-- <i class="fas fa-search search-icon"></i> -->
                <form method="GET" action="" style="display: inline;">
                    <input type="text" name="search" class="search-input" placeholder="Cari produk"
                        value="<?php echo htmlspecialchars($search); ?>">
                    <input type="hidden" name="filter" value="<?php echo $filter; ?>">
                </form>
            </div>
        </div>

        <!-- Orders Table -->
        <div class="orders-container">
            <?php if (empty($orders)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <h3>Belum ada pesanan</h3>
                    <p class="empty-text">
                        <?php echo !empty($search) ? 'Tidak ditemukan pesanan dengan kata kunci "' . htmlspecialchars($search) . '"' : 'Belum ada pesanan yang masuk'; ?>
                    </p>
                </div>
            <?php else: ?>
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>Kode Pesanan</th>
                            <th>Pelanggan</th>
                            <th>Produk</th>
                            <th>Jumlah & Total</th>
                            <th>Tanggal</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>
                                    <div class="order-code">#<?php echo $order['order_code']; ?></div>
                                    <?php if ($order['payment_status']): ?>
                                        <div class="payment-badge payment-<?php echo $order['payment_status']; ?>">
                                            <?php echo ucfirst($order['payment_status']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-weight: 500;"><?php echo htmlspecialchars($order['full_name']); ?></div>
                                    <div class="order-user">@<?php echo htmlspecialchars($order['username']); ?></div>
                                    <div class="order-user"><?php echo htmlspecialchars($order['email']); ?></div>
                                </td>
                                <td>
                                    <div class="order-product"><?php echo htmlspecialchars($order['product_name']); ?></div>
                                    <div class="order-meta">
                                        <?php echo $order['product_type'] == 'limited' ? 'Limited Edition' : 'Regular'; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="order-price">Rp <?php echo number_format($order['total_price'], 0, ',', '.'); ?>
                                    </div>
                                    <div class="order-meta"><?php echo $order['quantity']; ?> pcs × Rp
                                        <?php echo number_format($order['unit_price'], 0, ',', '.'); ?>
                                    </div>
                                </td>
                                <td>
                                    <div><?php echo date('d/m/Y', strtotime($order['created_at'])); ?></div>
                                    <div class="order-date"><?php echo date('H:i', strtotime($order['created_at'])); ?></div>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $order['status']; ?>">
                                        <?php
                                        $statusText = [
                                            'pending' => 'Pending',
                                            'processing' => 'Diproses',
                                            'completed' => 'Selesai',
                                            'cancelled' => 'Dibatalkan'
                                        ];
                                        echo $statusText[$order['status']];
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="order-actions">
                                        <a href="payments.php?order_id=<?php echo $order['id']; ?>" class="btn btn-payment"
                                            title="Lihat Pembayaran">
                                            <i class="fas fa-money-bill-wave"></i>
                                        </a>

                                        <?php if ($order['status'] == 'pending'): ?>
                                            <a href="?action=processing&id=<?php echo $order['id']; ?>&filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>"
                                                class="btn btn-process process-order-btn"
                                                data-order-code="<?php echo $order['order_code']; ?>" data-action="processing"
                                                title="Proses Pesanan">
                                                <i class="fas fa-cog"></i>
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($order['status'] == 'processing'): ?>
                                            <a href="?action=completed&id=<?php echo $order['id']; ?>&filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>"
                                                class="btn btn-complete complete-order-btn"
                                                data-order-code="<?php echo $order['order_code']; ?>" data-action="completed"
                                                title="Selesai">
                                                <i class="fas fa-check"></i>
                                            </a>
                                        <?php endif; ?>

                                        <?php if (in_array($order['status'], ['pending', 'processing'])): ?>
                                            <a href="?action=cancelled&id=<?php echo $order['id']; ?>&filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>"
                                                class="btn btn-cancel cancel-order-btn"
                                                data-order-code="<?php echo $order['order_code']; ?>" data-action="cancelled"
                                                title="Batalkan">
                                                <i class="fas fa-times"></i>
                                            </a>
                                        <?php endif; ?>

                                        <a href="order_detail.php?id=<?php echo $order['id']; ?>" class="btn btn-view"
                                            title="Lihat Detail">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-submit search form on input
        const searchInput = document.querySelector('.search-input');
        let searchTimeout;

        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.form.submit();
            }, 500);
        });

        function confirmAction(event, orderCode, action) {
            event.preventDefault(); 

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
                    window.location.href = event.target.closest('a').href;
                }
            });
        }

        // Event listener untuk tombol proses
        document.querySelectorAll('.process-order-btn').forEach(btn => {
            btn.addEventListener('click', function (e) {
                const orderCode = this.getAttribute('data-order-code');
                confirmAction(e, orderCode, 'processing');
            });
        });

        // Event listener untuk tombol selesai
        document.querySelectorAll('.complete-order-btn').forEach(btn => {
            btn.addEventListener('click', function (e) {
                const orderCode = this.getAttribute('data-order-code');
                confirmAction(e, orderCode, 'completed');
            });
        });

        // Event listener untuk tombol batalkan
        document.querySelectorAll('.cancel-order-btn').forEach(btn => {
            btn.addEventListener('click', function (e) {
                const orderCode = this.getAttribute('data-order-code');
                confirmAction(e, orderCode, 'cancelled');
            });
        });

        // Highlight rows based on status
        document.addEventListener('DOMContentLoaded', function () {
            const rows = document.querySelectorAll('.orders-table tbody tr');
            rows.forEach(row => {
                const statusBadge = row.querySelector('.status-badge');
                if (statusBadge) {
                    const status = statusBadge.className.includes('pending') ? 'pending' :
                        statusBadge.className.includes('processing') ? 'processing' :
                            statusBadge.className.includes('completed') ? 'completed' : 'cancelled';

                    if (status === 'pending') {
                        row.style.borderLeft = '4px solid #ffc107';
                    } else if (status === 'processing') {
                        row.style.borderLeft = '4px solid #17a2b8';
                    }
                }
            });
        });

        // Auto-refresh every minute for pending orders
        setInterval(function () {
            const activeFilter = "<?php echo $filter; ?>";
            if (activeFilter === 'all' || activeFilter === 'pending' || activeFilter === 'processing') {
                // Check if user is active on the page
                if (!document.hidden) {
                    window.location.reload();
                }
            }
        }, 60000);
    </script>

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

        // Auto refresh data every 30 seconds
        setInterval(function () {
            console.log('Auto refresh...');
        }, 30000);
    </script>
</body>

</html>