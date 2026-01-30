<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

Auth::requireAdmin();

// Handle actions
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;
$message = '';

// Handle delete
if ($action === 'delete' && $id) {
    try {
        // Check if user exists
        $stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        if ($user) {
            if ($user['id'] == $_SESSION['user_id']) {
                $message = 'error|Anda tidak dapat menghapus akun sendiri!';
            } else if ($user['role'] === 'admin') {
                $message = 'error|Anda tidak dapat menghapus admin lain!';
            } else {
                // Delete user
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'success|User berhasil dihapus!';
            }
        }
    } catch (PDOException $e) {
        $message = 'error|Database error: ' . $e->getMessage();
    }
}

// Handle reset password langsung dari sini
if ($action === 'resetpassword' && $id) {
    try {
        // Check if user exists
        $stmt = $pdo->prepare("SELECT id, username FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        if ($user) {
            // Reset password ke default
            $defaultPassword = 'password123';
            $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$hashedPassword, $id]);

            $message = 'success|Password berhasil direset untuk user ' . htmlspecialchars($user['username']) . '! Password default: <strong>password123</strong>';
        }
    } catch (PDOException $e) {
        $message = 'error|Database error: ' . $e->getMessage();
    }
}

// Get all users
try {
    $search = $_GET['search'] ?? '';
    $role = $_GET['role'] ?? '';

    $query = "SELECT * FROM users WHERE 1=1";
    $params = [];

    if ($search) {
        $query .= " AND (username LIKE ? OR email LIKE ? OR full_name LIKE ?)";
        $searchTerm = "%$search%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    }

    if ($role) {
        $query .= " AND role = ?";
        $params[] = $role;
    }

    $query .= " ORDER BY created_at DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll();

    // Get user statistics
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
    $totalUsers = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'admin'");
    $totalAdmins = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $newUsers = $stmt->fetch()['total'];

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola User - Roncelizz Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .message-notification {
            padding: 15px 20px;
            border-radius: var(--border-radius-medium);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            animation: slideIn 0.3s ease;
            border-left: 5px solid;
        }

        .message-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border-left-color: #28a745;
            color: #155724;
        }

        .message-error {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            border-left-color: #dc3545;
            color: #721c24;
        }

        .message-close {
            background: none;
            border: none;
            color: inherit;
            cursor: pointer;
            font-size: 1.2rem;
            opacity: 0.7;
            transition: opacity 0.3s;
        }

        .message-close:hover {
            opacity: 1;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .page-title {
            font-size: 1.8rem;
            color: var(--dark);
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .page-title i {
            color: var(--purple);
            font-size: 1.5rem;
        }

        .page-subtitle {
            color: var(--gray);
            font-size: 0.95rem;
            margin-top: 5px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--white);
            padding: 25px;
            border-radius: var(--border-radius-medium);
            box-shadow: var(--shadow-soft);
            text-align: center;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-medium);
            border-color: var(--pink-light);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--pink-light), var(--purple-light));
            border-radius: var(--border-radius-circle);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 24px;
            color: var(--purple);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 5px;
        }

        .stat-label {
            color: var(--gray);
            font-size: 0.9rem;
        }

        /* Filters */
        .filters-container {
            background: var(--white);
            padding: 25px;
            border-radius: var(--border-radius-medium);
            box-shadow: var(--shadow-soft);
            margin-bottom: 30px;
        }

        .filters-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filters-title i {
            color: var(--pink);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-label {
            font-weight: 500;
            color: var(--dark);
            font-size: 0.9rem;
        }

        .filter-input {
            padding: 12px 15px;
            border: 2px solid var(--gray-light);
            border-radius: var(--border-radius-medium);
            font-family: 'Poppins', sans-serif;
            font-size: 0.95rem;
            transition: all 0.3s;
        }

        .filter-input:focus {
            outline: none;
            border-color: var(--pink);
            box-shadow: 0 0 0 3px rgba(255, 107, 147, 0.1);
        }

        .filter-select {
            padding: 12px 15px;
            border: 2px solid var(--gray-light);
            border-radius: var(--border-radius-medium);
            font-family: 'Poppins', sans-serif;
            font-size: 0.95rem;
            background: var(--white);
            cursor: pointer;
            transition: all 0.3s;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--pink);
            box-shadow: 0 0 0 3px rgba(255, 107, 147, 0.1);
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }

        .filter-btn {
            padding: 12px 25px;
            border: none;
            border-radius: var(--border-radius-medium);
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-btn.search {
            background: linear-gradient(135deg, var(--pink), var(--purple));
            color: white;
        }

        .filter-btn.search:hover {
            background: linear-gradient(135deg, var(--pink-dark), var(--purple-dark));
            transform: translateY(-2px);
        }

        .filter-btn.reset {
            background: var(--gray-light);
            color: var(--dark);
        }

        .filter-btn.reset:hover {
            background: var(--gray);
            color: var(--white);
        }

        /* Users Table */
        .users-container {
            background: var(--white);
            padding: 30px;
            border-radius: var(--border-radius-medium);
            box-shadow: var(--shadow-soft);
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .table-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--dark);
        }

        .add-btn {
            background: linear-gradient(135deg, var(--pink), var(--purple));
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: var(--border-radius-medium);
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .add-btn:hover {
            background: linear-gradient(135deg, var(--pink-dark), var(--purple-dark));
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 107, 147, 0.3);
        }

        .role-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .role-admin {
            background: linear-gradient(135deg, #ff6b93, #8a2be2);
            color: white;
        }

        .role-user {
            background: linear-gradient(135deg, #4ecdc4, #44a08d);
            color: white;
        }

        .status-active {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }

        .status-inactive {
            background: linear-gradient(135deg, #dc3545, #e83e8c);
            color: white;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: var(--border-radius-small);
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
            white-space: nowrap;
        }

        .btn-edit {
            background: #cce5ff;
            color: #004085;
            border: 1px solid #b8daff;
        }

        .btn-edit:hover {
            background: #b8daff;
            color: #004085;
            transform: translateY(-2px);
        }

        .btn-delete {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .btn-delete:hover {
            background: #f5c6cb;
            color: #721c24;
            transform: translateY(-2px);
        }

        .btn-reset {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .btn-reset:hover {
            background: #ffeaa7;
            color: #856404;
            transform: translateY(-2px);
        }

        .btn-change {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .btn-change:hover {
            background: #bee5eb;
            color: #0c5460;
            transform: translateY(-2px);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--gray-light);
            margin-bottom: 20px;
        }

        .empty-state h3 {
            color: var(--gray);
            font-size: 1.3rem;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: var(--gray);
            max-width: 400px;
            margin: 0 auto 25px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .filters-grid {
                grid-template-columns: 1fr;
            }

            .filter-buttons {
                flex-direction: column;
            }

            .filter-btn {
                width: 100%;
                justify-content: center;
            }

            .users-table {
                display: block;
                overflow-x: auto;
            }

            .table-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }

            .add-btn {
                width: 100%;
                justify-content: center;
            }

            .action-buttons {
                flex-direction: column;
                gap: 5px;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .user-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .users-table th,
            .users-table td {
                padding: 10px;
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

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="page-title"><i class="fas fa-users"></i> Kelola User</h1>
                <p class="page-subtitle">Kelola semua user yang terdaftar di Roncelizz</p>
            </div>
            <a href="user_form.php?action=add" class="add-btn">
                <i class="fas fa-user-plus"></i> Tambah User
            </a>
        </div>

        <!-- Message Notification -->
        <?php if ($message):
            $cleanText = strip_tags($message);
            list($type, $text) = explode('|', $cleanText, 2);
            ?>
            <div class="message-notification message-<?php echo $type; ?>">
                <span><?php echo htmlspecialchars($text); ?></span>
                <button class="message-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-number"><?php echo $totalUsers; ?></div>
                <div class="stat-label">Total User</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="stat-number"><?php echo $totalAdmins; ?></div>
                <div class="stat-label">Total Admin</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-clock"></i>
                </div>
                <div class="stat-number"><?php echo $newUsers; ?></div>
                <div class="stat-label">User Baru (7 Hari)</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-container">
            <h3 class="filters-title"><i class="fas fa-filter"></i> Filter User</h3>
            <form method="GET" action="" class="filters-grid">
                <div class="filter-group">
                    <label class="filter-label">Cari User</label>
                    <input type="text" name="search" class="filter-input" placeholder="Nama, username, atau email..."
                        value="<?php echo htmlspecialchars($search); ?>">
                </div>

                <div class="filter-group">
                    <label class="filter-label">Role</label>
                    <select name="role" class="filter-select">
                        <option value="">Semua Role</option>
                        <option value="user" <?php echo $role === 'user' ? 'selected' : ''; ?>>User</option>
                        <option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>

                <div class="filter-buttons">
                    <button type="submit" class="filter-btn search">
                        <i class="fas fa-search"></i> Cari
                    </button>
                    <a href="users.php" class="filter-btn reset">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Users Table -->
        <div class="users-container">
            <div class="table-header">
                <h3 class="table-title">Daftar User (<?php echo count($users); ?>)</h3>
                <a href="user_form.php?action=add" class="add-btn">
                    <i class="fas fa-user-plus"></i> Tambah User Baru
                </a>
            </div>

            <?php if (empty($users)): ?>
                <div class="empty-state">
                    <i class="fas fa-users-slash"></i>
                    <h3>Tidak ada user ditemukan</h3>
                    <p>Mulai tambahkan user baru untuk mengelola sistem Roncelizz</p>
                    <a href="user_form.php?action=add" class="add-btn" style="display: inline-flex; width: auto;">
                        <i class="fas fa-user-plus"></i> Tambah User Pertama
                    </a>
                </div>
            <?php else: ?>
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th>Tanggal Bergabung</th>
                            <th>Terakhir Update</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user):
                            $initial = strtoupper(substr($user['full_name'] ?: $user['username'], 0, 1));
                            ?>
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <div class="user-avatar"><?php echo $initial; ?></div>
                                        <div class="user-details">
                                            <span
                                                class="user-name"><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></span>
                                            <span class="user-email"><?php echo htmlspecialchars($user['email']); ?></span>
                                            <span
                                                class="user-username">@<?php echo htmlspecialchars($user['username']); ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="role-badge role-<?php echo $user['role']; ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d M Y', strtotime($user['created_at'])); ?></td>
                                <td><?php echo $user['updated_at'] ? date('d M Y', strtotime($user['updated_at'])) : '-'; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="user_form.php?action=edit&id=<?php echo $user['id']; ?>"
                                            class="action-btn btn-edit">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <?php if ($user['id'] != $_SESSION['user_id'] && $user['role'] !== 'admin'): ?>
                                            <button
                                                onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo addslashes($user['full_name'] ?: $user['username']); ?>')"
                                                class="action-btn btn-delete">
                                                <i class="fas fa-trash"></i> Hapus
                                            </button>
                                        <?php endif; ?>
                                        <button
                                            onclick="confirmResetPassword(<?php echo $user['id']; ?>, '<?php echo addslashes($user['full_name'] ?: $user['username']); ?>')"
                                            class="action-btn btn-reset">
                                            <i class="fas fa-key"></i> Reset Password
                                        </button>
                                        <a href="change_password.php?id=<?php echo $user['id']; ?>"
                                            class="action-btn btn-change">
                                            <i class="fas fa-lock"></i> Ganti Password
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
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

            // Confirm delete function
            function confirmDelete(userId, userName) {
                Swal.fire({
                    title: 'Konfirmasi Hapus',
                    html: `Apakah Anda yakin ingin menghapus user <strong>${userName}</strong>?<br>Tindakan ini tidak dapat dibatalkan.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ff6b93',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Ya, Hapus',
                    cancelButtonText: 'Batal',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = `users.php?action=delete&id=${userId}`;
                    }
                });
            }

            // Confirm reset password function
            function confirmResetPassword(userId, userName) {
                Swal.fire({
                    title: 'Reset Password?',
                    html: `Anda akan mereset password untuk user:<br>
                           <strong>${userName}</strong><br><br>
                           Password akan diubah menjadi:<br>
                           <code style="font-size: 1.2rem; background: #f8f9fa; padding: 5px 10px; border-radius: 5px;">password123</code><br><br>
                           <small><i class="fas fa-info-circle"></i> User harus mengganti password setelah login.</small>`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ff6b93',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Ya, Reset Password',
                    cancelButtonText: 'Batal',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = `users.php?action=resetpassword&id=${userId}`;
                    }
                });
            }

            // Auto remove message after 5 seconds
            setTimeout(() => {
                const message = document.querySelector('.message-notification');
                if (message) {
                    message.style.opacity = '0';
                    setTimeout(() => message.remove(), 300);
                }
            }, 5000);
        </script>
    </div>
</body>

</html>