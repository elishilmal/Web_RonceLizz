<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

Auth::requireAdmin();

$message = '';
$messageType = '';

// Handle payment verification
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = intval($_GET['id']);
    
    try {
        if ($action == 'verify') {
            $stmt = $pdo->prepare("UPDATE payments SET status = 'verified', verified_by = ?, verified_at = NOW() WHERE id = ?");
            $stmt->execute([$_SESSION['user_id'], $id]);
            
            // Get payment info for notification
            $stmt = $pdo->prepare("SELECT p.*, o.user_id, o.order_code FROM payments p JOIN orders o ON p.order_id = o.id WHERE p.id = ?");
            $stmt->execute([$id]);
            $payment = $stmt->fetch();
            
            if ($payment) {
                // Get update order status to processing
                $stmt = $pdo->prepare("UPDATE orders SET status = 'processing', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$payment['order_id']]);
                
                // Get notification for user
                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'success')");
                $stmt->execute([
                    $payment['user_id'],
                    'Pembayaran Diverifikasi',
                    'Pembayaran untuk pesanan #' . $payment['order_code'] . ' telah diverifikasi'
                ]);
            }
            
            $message = "Pembayaran berhasil diverifikasi!";
            $messageType = 'success';
            
        } elseif ($action == 'reject') {
            $stmt = $pdo->prepare("UPDATE payments SET status = 'rejected', verified_by = ?, verified_at = NOW() WHERE id = ?");
            $stmt->execute([$_SESSION['user_id'], $id]);
            
            $message = "Pembayaran berhasil ditolak!";
            $messageType = 'success';
        }
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Handle admin notes
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_note') {
    $id = intval($_POST['id']);
    $verification_notes = htmlspecialchars($_POST['verification_notes'] ?? '');
    
    try {
        $stmt = $pdo->prepare("UPDATE payments SET verification_notes = ? WHERE id = ?");
        $stmt->execute([$verification_notes, $id]);
        
        $message = "Catatan verifikasi berhasil ditambahkan!";
        $messageType = 'success';
        
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Get filter parameters
$filter = $_GET['filter'] ?? 'pending';
$order_id = $_GET['order_id'] ?? 0;

$whereClause = "1=1";
$params = [];

if ($filter !== 'all') {
    $whereClause .= " AND p.status = ?";
    $params[] = $filter;
}

if ($order_id > 0) {
    $whereClause .= " AND p.order_id = ?";
    $params[] = $order_id;
}

// Get payments with filter
try {
    $sql = "SELECT p.*, o.order_code, o.total_price, o.status as order_status,
                   u.username, u.full_name, u.email,
                   v.username as verified_by_name
            FROM payments p 
            JOIN orders o ON p.order_id = o.id 
            JOIN users u ON o.user_id = u.id
            LEFT JOIN users v ON p.verified_by = v.id
            WHERE $whereClause 
            ORDER BY p.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $payments = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $message = "Error: " . $e->getMessage();
    $messageType = 'error';
    $payments = [];
}

// Get statistics
try {
    $stmt = $pdo->query("SELECT 
                         COUNT(*) as total_payments,
                         SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_payments,
                         SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified_payments,
                         SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_payments,
                         SUM(CASE WHEN status = 'verified' THEN amount ELSE 0 END) as total_verified
                         FROM payments");
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
    <title>Verifikasi Pembayaran - Roncelizz</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
        
        /* Custom styles for payments page */
        .payments-container {
            margin-top: 30px;
        }
        
        .payment-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .payment-item {
            background: var(--white);
            border-radius: var(--border-radius-medium);
            padding: 25px;
            box-shadow: var(--shadow-soft);
            border: 2px solid transparent;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .payment-item:hover {
            box-shadow: var(--shadow-medium);
            transform: translateY(-2px);
        }
        
        .payment-item.status-pending {
            border-color: #ffc107;
        }
        
        .payment-item.status-verified {
            border-color: #28a745;
        }
        
        .payment-item.status-rejected {
            border-color: #dc3545;
        }
        
        .payment-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .payment-info {
            flex: 1;
        }
        
        .payment-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .payment-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .status-verified {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .payment-meta {
            display: flex;
            gap: 20px;
            color: var(--gray);
            font-size: 14px;
        }
        
        .payment-meta i {
            margin-right: 5px;
        }
        
        .payment-amount {
            font-size: 24px;
            font-weight: 700;
            color: var(--pink);
        }
        
        .payment-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .detail-item {
            background: var(--light-gray);
            padding: 15px;
            border-radius: 10px;
        }
        
        .detail-label {
            font-size: 12px;
            color: var(--gray);
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .detail-value {
            font-size: 14px;
            color: var(--dark);
            font-weight: 500;
        }
        
        .proof-image {
            margin-top: 10px;
            border-radius: var(--border-radius-small);
            overflow: hidden;
            border: 1px solid var(--border);
        }
        
        .proof-image img {
            width: 100%;
            max-width: 300px;
            height: auto;
            display: block;
            cursor: pointer;
            transition: transform 0.3s;
        }
        
        .proof-image img:hover {
            transform: scale(1.02);
        }
        
        .verification-info {
            background: #e6f7ff;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 3px solid #1890ff;
        }
        
        .verification-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            font-weight: 500;
        }
        
        .verification-content {
            color: var(--dark);
            line-height: 1.6;
            white-space: pre-wrap;
        }
        
        .verified-by {
            margin-top: 10px;
            font-size: 12px;
            color: var(--gray);
            font-style: italic;
        }
        
        .payment-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn-verify {
            background: var(--mint);
            color: white;
        }
        
        .btn-verify:hover {
            background: #45d099;
        }
        
        .btn-reject {
            background: var(--pink);
            color: white;
        }
        
        .btn-reject:hover {
            background: #ff5580;
        }
        
        .btn-note {
            background: var(--purple);
            color: white;
        }
        
        .btn-note:hover {
            background: #7c4dff;
        }
        
        .btn-note.btn-sm {
            padding: 4px 8px;
            font-size: 12px;
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: var(--border-radius-medium);
            padding: 30px;
            max-width: 500px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-hard);
            position: relative;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .modal-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--dark);
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            color: var(--gray);
            cursor: pointer;
            line-height: 1;
        }
        
        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        /* Pulse animation for pending payments */
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(255, 193, 7, 0); }
            100% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0); }
        }
        
        .payment-item.status-pending {
            animation: pulse 2s infinite;
        }
        
        /* Stats specific styles */
        .stat-card.total::before {
            background: var(--purple);
        }
        
        .stat-card.pending::before {
            background: #ffc107;
        }
        
        .stat-card.verified::before {
            background: var(--mint);
        }
        
        .stat-card.amount::before {
            background: var(--pink);
        }
        
        .stat-card.total .stat-icon {
            color: var(--purple);
        }
        
        .stat-card.pending .stat-icon {
            color: #ffc107;
        }
        
        .stat-card.verified .stat-icon {
            color: var(--mint);
        }
        
        .stat-card.amount .stat-icon {
            color: var(--pink);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .payment-header {
                flex-direction: column;
                gap: 10px;
            }
            
            .payment-details {
                grid-template-columns: 1fr;
            }
            
            .payment-meta {
                flex-direction: column;
                gap: 8px;
            }
            
            .payment-actions {
                flex-direction: column;
            }
            
            .payment-actions .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content">
        <button class="menu-toggle" id="menuToggle">
            <i class="fas fa-bars"></i>
        </button>
        
        <div class="header">
            <div>
                <h1 class="page-title">Verifikasi Pembayaran</h1>
                <p class="page-subtitle">Verifikasi bukti pembayaran dari pelanggan</p>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType == 'success' ? 'success' : 'error'; ?>">
                <i class="fas <?php echo $messageType == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card total">
                <div class="stat-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-number"><?php echo $stats['total_payments'] ?? 0; ?></div>
                <div class="stat-label">Total Pembayaran</div>
            </div>
            
            <div class="stat-card pending">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-number"><?php echo $stats['pending_payments'] ?? 0; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            
            <div class="stat-card verified">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-number"><?php echo $stats['verified_payments'] ?? 0; ?></div>
                <div class="stat-label">Terverifikasi</div>
            </div>
            
            <div class="stat-card amount">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-number">Rp <?php echo isset($stats['total_verified']) ? number_format($stats['total_verified'], 0, ',', '.') : '0'; ?></div>
                <div class="stat-label">Total Terverifikasi</div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters">
            <div class="filter-tabs">
                <a href="?filter=pending" class="filter-tab <?php echo $filter == 'pending' ? 'active' : ''; ?>">
                    <i class="fas fa-clock"></i> Pending
                    <?php if ($stats['pending_payments'] ?? 0 > 0): ?>
                        <span class="badge"><?php echo $stats['pending_payments']; ?></span>
                    <?php endif; ?>
                </a>
                <a href="?filter=verified" class="filter-tab <?php echo $filter == 'verified' ? 'active' : ''; ?>">
                    <i class="fas fa-check-circle"></i> Terverifikasi
                </a>
                <a href="?filter=rejected" class="filter-tab <?php echo $filter == 'rejected' ? 'active' : ''; ?>">
                    <i class="fas fa-times-circle"></i> Ditolak
                </a>
                <a href="?filter=all" class="filter-tab <?php echo $filter == 'all' ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i> Semua
                </a>
            </div>
        </div>
        
        <!-- Payments List -->
        <div class="payments-container">
            <?php if (empty($payments)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <h3>Belum ada pembayaran</h3>
                    <p class="empty-text">
                        <?php echo $filter == 'pending' ? 'Tidak ada pembayaran yang perlu diverifikasi' : 'Belum ada pembayaran dengan status ini'; ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="payment-list">
                    <?php foreach ($payments as $payment): ?>
                    <div class="payment-item status-<?php echo $payment['status']; ?>">
                        <div class="payment-header">
                            <div class="payment-info">
                                <div class="payment-title">
                                    Pembayaran untuk Order #<?php echo $payment['order_code']; ?>
                                    <span class="payment-status status-<?php echo $payment['status']; ?>">
                                        <?php echo ucfirst($payment['status']); ?>
                                    </span>
                                </div>
                                <div class="payment-meta">
                                    <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($payment['full_name']); ?></span>
                                    <span><i class="fas fa-calendar"></i> <?php echo date('d/m/Y H:i', strtotime($payment['created_at'])); ?></span>
                                    <span><i class="fas fa-money-bill-wave"></i> <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></span>
                                </div>
                            </div>
                            <div class="payment-amount">
                                Rp <?php echo number_format($payment['amount'], 0, ',', '.'); ?>
                            </div>
                        </div>
                        
                        <div class="payment-details">
                            <div class="detail-item">
                                <div class="detail-label">Metode Pembayaran</div>
                                <div class="detail-value"><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></div>
                                <?php if ($payment['bank_name']): ?>
                                    <div class="detail-label">Bank</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($payment['bank_name']); ?> - <?php echo htmlspecialchars($payment['account_number']); ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="detail-item">
                                <div class="detail-label">Status Pesanan</div>
                                <div class="detail-value">
                                    <?php 
                                    $orderStatus = [
                                        'pending' => 'Menunggu Pembayaran',
                                        'processing' => 'Diproses',
                                        'completed' => 'Selesai',
                                        'cancelled' => 'Dibatalkan'
                                    ];
                                    echo $orderStatus[$payment['order_status']] ?? $payment['order_status'];
                                    ?>
                                </div>
                                <div class="detail-label">Total Pesanan</div>
                                <div class="detail-value">Rp <?php echo number_format($payment['total_price'], 0, ',', '.'); ?></div>
                            </div>
                            
                            <?php if ($payment['payment_date']): ?>
                            <div class="detail-item">
                                <div class="detail-label">Tanggal Pembayaran</div>
                                <div class="detail-value"><?php echo date('d/m/Y', strtotime($payment['payment_date'])); ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($payment['payment_proof']): ?>
                            <div class="detail-item">
                                <div class="detail-label">Bukti Pembayaran</div>
                                <div class="proof-image">
                                    <?php
                                    $dbPath = $payment['payment_proof'];
                                    $filename = basename($dbPath);
                                    
                                    // Build correct path
                                    $correctPath = '../assets/images/payments/' . $filename;
                                    
                                    // Cek file
                                    $fileExists = file_exists($correctPath);
                                    
                                    if ($fileExists): 
                                        $ext = strtolower(pathinfo($correctPath, PATHINFO_EXTENSION));
                                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])): 
                                    ?>
                                            <img src="<?php echo $correctPath; ?>" 
                                                 alt="Bukti Pembayaran" 
                                                 onclick="viewImage('<?php echo $correctPath; ?>')">
                                        <?php elseif ($ext == 'pdf'): ?>
                                            <div style="padding: 30px; text-align: center; background: #f8f9fa; cursor: pointer;" 
                                                 onclick="window.open('<?php echo $correctPath; ?>', '_blank')">
                                                <i class="fas fa-file-pdf" style="font-size: 48px; color: #dc3545;"></i>
                                                <div style="margin-top: 10px; font-weight: 500;">File PDF</div>
                                                <div style="font-size: 12px; color: var(--gray);">Klik untuk melihat</div>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div style="padding: 20px; text-align: center; color: var(--gray); background: #f8f9fa; border-radius: 5px;">
                                            <i class="fas fa-exclamation-triangle" style="font-size: 24px; margin-bottom: 10px; color: #dc3545;"></i>
                                            <div>File tidak ditemukan di server</div>
                                            <div style="font-size: 11px; margin-top: 5px;">
                                                File: <?php echo htmlspecialchars($filename); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($payment['verification_notes'] || $payment['verified_by_name']): ?>
                        <div class="verification-info">
                            <?php if ($payment['verification_notes']): ?>
                                <div class="verification-title">
                                    <span>Catatan Verifikasi:</span>
                                    <?php if ($payment['status'] == 'pending'): ?>
                                        <button class="btn btn-note btn-sm" onclick="editNotes(<?php echo $payment['id']; ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <div class="verification-content">
                                    <?php echo nl2br(htmlspecialchars($payment['verification_notes'])); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($payment['verified_by_name']): ?>
                                <div class="verified-by">
                                    <i class="fas fa-user-check"></i>
                                    Diverifikasi oleh: <?php echo htmlspecialchars($payment['verified_by_name']); ?> 
                                    pada <?php echo date('d/m/Y H:i', strtotime($payment['verified_at'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($payment['status'] == 'pending'): ?>
                        <div class="payment-actions">
                            <a href="?action=verify&id=<?php echo $payment['id']; ?>&filter=<?php echo $filter; ?>" 
                               class="btn btn-verify" 
                               onclick="return confirmVerification(event, 'verify', <?php echo $payment['id']; ?>)">
                                <i class="fas fa-check"></i> Verifikasi
                            </a>
                            <a href="?action=reject&id=<?php echo $payment['id']; ?>&filter=<?php echo $filter; ?>" 
                               class="btn btn-reject"
                               onclick="return confirmVerification(event, 'reject', <?php echo $payment['id']; ?>)">
                                <i class="fas fa-times"></i> Tolak
                            </a>
                            <button class="btn btn-note" onclick="showNotesModal(<?php echo $payment['id']; ?>, '<?php echo addslashes($payment['verification_notes'] ?? ''); ?>')">
                                <i class="fas fa-sticky-note"></i> 
                                <?php echo $payment['verification_notes'] ? 'Edit Catatan' : 'Tambah Catatan'; ?>
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Notes Modal -->
    <div class="modal" id="notesModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Tambah/Edit Catatan Verifikasi</h3>
                <button class="modal-close" onclick="hideNotesModal()">&times;</button>
            </div>
            <form method="POST" action="" id="notesForm">
                <input type="hidden" name="action" value="add_note">
                <input type="hidden" name="id" id="modalPaymentId">
                
                <div class="form-group">
                    <label for="verification_notes" class="form-label">Catatan Verifikasi</label>
                    <textarea class="form-control" id="verification_notes" name="verification_notes" 
                              rows="5" placeholder="Berikan catatan untuk user..."></textarea>
                    <small class="form-text" style="color: var(--gray);">Catatan ini akan dilihat oleh user</small>
                </div>
                
                <div class="modal-actions">
                    <button type="submit" class="btn btn-note">
                        <i class="fas fa-save"></i> Simpan Catatan
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="hideNotesModal()">
                        <i class="fas fa-times"></i> Batal
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Image Viewer Modal -->
    <div class="modal" id="imageModal">
        <div class="modal-content" style="max-width: 90vw; max-height: 90vh; padding: 0; background: transparent; box-shadow: none;">
            <button class="modal-close" onclick="hideImageModal()" 
                    style="position: absolute; top: 10px; right: 10px; background: rgba(0,0,0,0.5); color: white; border: none; width: 40px; height: 40px; border-radius: 50%; z-index: 1001; font-size: 20px;">
                &times;
            </button>
            <img id="modalImage" src="" alt="Preview" 
                 style="width: 100%; height: 100%; object-fit: contain; border-radius: 10px;">
        </div>
    </div>
    
    <script>
        // Toggle mobile menu
        document.getElementById('menuToggle')?.addEventListener('click', function () {
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

        // Notes modal
        const notesModal = document.getElementById('notesModal');
        const notesForm = document.getElementById('notesForm');
        const modalPaymentId = document.getElementById('modalPaymentId');
        const verificationNotes = document.getElementById('verification_notes');
        
        function showNotesModal(paymentId, currentNotes = '') {
            modalPaymentId.value = paymentId;
            verificationNotes.value = currentNotes;
            notesModal.classList.add('active');
            verificationNotes.focus();
        }
        
        function hideNotesModal() {
            notesModal.classList.remove('active');
            verificationNotes.value = '';
        }
        
        function editNotes(paymentId) {
            // Find the payment item
            const paymentItems = document.querySelectorAll('.payment-item');
            paymentItems.forEach(item => {
                const btn = item.querySelector('.btn-note.btn-sm');
                if (btn && btn.onclick.toString().includes(paymentId)) {
                    const notesElement = item.querySelector('.verification-content');
                    const notesContent = notesElement ? notesElement.textContent.trim() : '';
                    showNotesModal(paymentId, notesContent);
                }
            });
        }
        
        // Image viewer
        const imageModal = document.getElementById('imageModal');
        const modalImage = document.getElementById('modalImage');
        
        function viewImage(src) {
            modalImage.src = src;
            imageModal.classList.add('active');
            
            // Handle image loading error
            modalImage.onerror = function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal Memuat Gambar',
                    text: 'Gambar tidak dapat dimuat. Coba refresh halaman atau periksa koneksi.',
                    confirmButtonColor: '#ff6b93',
                }).then(() => {
                    hideImageModal();
                });
            };
        }
        
        function hideImageModal() {
            imageModal.classList.remove('active');
            modalImage.src = '';
        }

        [notesModal, imageModal].forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    if (modal === notesModal) hideNotesModal();
                    if (modal === imageModal) hideImageModal();
                }
            });
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (notesModal.classList.contains('active')) hideNotesModal();
                if (imageModal.classList.contains('active')) hideImageModal();
            }
        });
        
        // Form validation for notes
        notesForm.addEventListener('submit', function(e) {
            const notes = verificationNotes.value.trim();
            
            if (!notes) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Catatan Kosong',
                    text: 'Harap isi catatan verifikasi',
                    confirmButtonColor: '#ff6b93',
                });
                verificationNotes.focus();
                return false;
            }
            
            return true;
        });
        
        // Confirmation for verification/rejection
        function confirmVerification(event, action, paymentId) {
            event.preventDefault();
            
            const actionText = action === 'verify' ? 'verifikasi' : 'tolak';
            const actionTitle = action === 'verify' ? 'Verifikasi' : 'Tolak';
            const icon = action === 'verify' ? 'question' : 'warning';
            
            Swal.fire({
                title: `${actionTitle} Pembayaran`,
                text: `Apakah Anda yakin ingin ${actionText} pembayaran ini?`,
                icon: icon,
                showCancelButton: true,
                confirmButtonColor: action === 'verify' ? '#28a745' : '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: `Ya, ${actionTitle}`,
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `?action=${action}&id=${paymentId}&filter=<?php echo $filter; ?>`;
                }
            });
            
            return false;
        }
        
        // Auto-refresh for pending payments 
        <?php if ($filter == 'pending'): ?>
        setInterval(function() {
            if (!document.hidden) {
                window.location.reload();
            }
        }, 30000); 
        <?php endif; ?>
        
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