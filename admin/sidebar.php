<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

Auth::requireAdmin();
?>

<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-title">Roncelizz</div>
            <div class="sidebar-subtitle">Admin Panel</div>
        </div>

        <div class="user-info">
            <div class="user-avatar">
                <i class="fas fa-user-cog"></i>
            </div>
            <div>
                <div class="user-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></div>
                <div class="user-role">Administrator</div>
            </div>
        </div>

        <ul class="sidebar-nav">
            <li class="nav-item">
                <a href="dashboard.php"
                    class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                    <span class="nav-icon"><i class="fas fa-home"></i></span>
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="products.php"
                    class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : ''; ?>">
                    <span class="nav-icon"><i class="fas fa-gem"></i></span>
                    Produk
                </a>
            </li>
            <li class="nav-item">
                <a href="categories.php"
                    class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : ''; ?>">
                    <span class="nav-icon"><i class="fas fa-tags"></i></span>
                    Kategori
                </a>
            </li>
            <li class="nav-item">
                <a href="requests.php"
                    class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'requests.php' ? 'active' : ''; ?>">
                    <span class="nav-icon"><i class="fas fa-comments"></i></span>
                    Request
                    <?php
                    try {
                        $stmt = $pdo->query("SELECT COUNT(*) as count FROM products WHERE type = 'request' AND request_status = 'pending'");
                        $pendingRequests = $stmt->fetch()['count'];
                        if ($pendingRequests > 0): ?>
                            <span class="badge"><?php echo $pendingRequests; ?></span>
                        <?php endif;
                    } catch (PDOException $e) {
                    }
                    ?>
                </a>
            </li>
            <li class="nav-item">
                <a href="orders.php"
                    class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active' : ''; ?>">
                    <span class="nav-icon"><i class="fas fa-shopping-cart"></i></span>
                    Pesanan
                </a>
            </li>
            <li class="nav-item">
                <a href="payments.php"
                    class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'payments.php' ? 'active' : ''; ?>">
                    <span class="nav-icon"><i class="fas fa-money-bill-wave"></i></span>
                    Pembayaran
                </a>
            </li>
            <li class="nav-item">
                <a href="users.php"
                    class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
                    <span class="nav-icon"><i class="fas fa-users"></i></span>
                    User
                </a>
            </li>
            <li class="nav-item">
                <a href="reports.php"
                    class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                    <span class="nav-icon"><i class="fas fa-chart-bar"></i></span>
                    Laporan
                </a>
            </li>
            <li class="nav-item">
                <a href="/logout.php" class="nav-link" style="color: var(--peach);" onclick="confirmLogout(event)">
                    <span class="nav-icon"><i class="fas fa-sign-out-alt"></i></span>
                    Logout
                </a>
            </li>
        </ul>
    </div>

    <!-- Mobile Menu Toggle Button -->
    <button class="menu-toggle" id="menuToggle">
        <i class="fas fa-bars"></i>
    </button>

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
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../logout.php';
                }
            });
        }
    </script>
</body>

</html>