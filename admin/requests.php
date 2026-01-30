<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

Auth::requireAdmin();

// Initialize variables
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';
$dateFilter = isset($_GET['date']) ? $_GET['date'] : '';
$message = '';
$messageType = '';

// Handle actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $requestId = (int) $_GET['id'];

    try {
        switch ($action) {
            case 'approve':
                $stmt = $pdo->prepare("UPDATE requests SET status = 'approved', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$requestId]);
                $message = "Request berhasil disetujui!";
                $messageType = 'success';
                break;

            case 'reject':
                $stmt = $pdo->prepare("UPDATE requests SET status = 'rejected', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$requestId]);
                $message = "Request berhasil ditolak!";
                $messageType = 'success';
                break;

            case 'in_progress':
                $stmt = $pdo->prepare("UPDATE requests SET status = 'in_progress', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$requestId]);
                $message = "Request berhasil dipindah ke 'Dalam Proses'!";
                $messageType = 'success';
                break;

            case 'complete':
                $stmt = $pdo->prepare("UPDATE requests SET status = 'completed', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$requestId]);
                $message = "Request berhasil diselesaikan!";
                $messageType = 'success';
                break;

            case 'cancel':
                $stmt = $pdo->prepare("UPDATE requests SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$requestId]);
                $message = "Request berhasil dibatalkan!";
                $messageType = 'success';
                break;

            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM requests WHERE id = ?");
                $stmt->execute([$requestId]);
                $message = "Request berhasil dihapus!";
                $messageType = 'success';
                break;

            case 'save_notes':
                if (isset($_POST['notes']) && isset($_POST['id'])) {
                    $notes = trim($_POST['notes']);
                    $requestId = (int) $_POST['id'];

                    $stmt = $pdo->prepare("UPDATE requests SET notes = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$notes, $requestId]);
                    $message = "Catatan berhasil disimpan!";
                    $messageType = 'success';
                }
                break;
        }

        if ($action == 'save_notes') {
            header("Location: requests.php?" . http_build_query($_GET));
            exit();
        }

    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Build query for requests
$query = "SELECT 
    r.*, 
    u.full_name, 
    u.email, 
    u.phone, 
    c.name as category_name,
    DATE_FORMAT(r.created_at, '%d %b %Y %H:%i') as formatted_created,
    DATE_FORMAT(r.deadline, '%d %b %Y') as deadline_formatted
    FROM requests r 
    JOIN users u ON r.user_id = u.id 
    LEFT JOIN categories c ON r.category_id = c.id 
    WHERE 1=1";

$params = [];

// Apply search filter
if (!empty($searchQuery)) {
    $query .= " AND (r.request_code LIKE ? OR r.product_name LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
    $searchTerm = "%$searchQuery%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

// Apply status filter
if ($statusFilter != 'all') {
    if ($statusFilter == 'complete') {
        $query .= " AND r.status = 'completed'";
    } else {
        $query .= " AND r.status = ?";
        $params[] = $statusFilter;
    }
}

// Apply date filter
if (!empty($dateFilter)) {
    $dateCondition = '';
    switch ($dateFilter) {
        case 'today':
            $dateCondition = "DATE(r.created_at) = CURDATE()";
            break;
        case 'week':
            $dateCondition = "r.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $dateCondition = "r.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            break;
    }
    if ($dateCondition) {
        $query .= " AND $dateCondition";
    }
}

$query .= " ORDER BY r.created_at DESC";

// Execute query
try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $requests = $stmt->fetchAll();

    // Get total count
    $total_requests = count($requests);

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    $requests = [];
    $total_requests = 0;
}

// Get statistics for all users
$stats = [
    'total' => 0,
    'pending' => 0,
    'approved' => 0,
    'in_progress' => 0,
    'completed' => 0,
    'rejected' => 0,
    'cancelled' => 0
];

try {
    // Get all counts
    $stmt = $pdo->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status IN ('completed', 'complete') THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
        FROM requests");

    $statsData = $stmt->fetch();
    if ($statsData) {
        $stats = $statsData;

        foreach ($stats as $key => $value) {
            $stats[$key] = (int) $value;
        }
    }

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Function to get reference image path
function getReferenceImage($filename)
{
    if (empty($filename))
        return null;

    // Check if filename contains full path
    if (strpos($filename, 'requests/') === 0) {
        $filename = substr($filename, 9);

        $path = '../assets/images/requests/' . basename($filename);
        return file_exists($path) ? $path : null;
    }
}

// Function to get status badge class
function getStatusBadge($status)
{
    $classes = [
        'pending' => 'status-pending',
        'approved' => 'status-approved',
        'in_progress' => 'status-in_progress',
        'completed' => 'status-completed',
        'rejected' => 'status-rejected',
        'cancelled' => 'status-cancelled'
    ];
    return $classes[$status] ?? 'status-pending';
}

// Function to get status text
function getStatusText($status)
{
    $texts = [
        'pending' => 'Menunggu',
        'approved' => 'Disetujui',
        'in_progress' => 'Dalam Proses',
        'completed' => 'Selesai',
        'rejected' => 'Ditolak',
        'cancelled' => 'Dibatalkan'
    ];
    return $texts[$status] ?? ucfirst($status);
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Request - Roncelizz Admin</title>
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

        /* Style untuk stat card */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border-top: 4px solid transparent;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.12);
        }

        .stat-card.total {
            border-top-color: var(--purple);
        }

        .stat-card.pending {
            border-top-color: #ffc107;
        }

        .stat-card.approved {
            border-top-color: #28a745;
        }

        .stat-card.in_progress {
            border-top-color: #4361ee;
        }

        .stat-card.completed {
            border-top-color: #4cc9f0;
        }

        .stat-card.rejected {
            border-top-color: #dc3545;
        }

        .stat-card.cancelled {
            border-top-color: #6c757d;
        }

        .stat-number {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--dark);
        }

        .stat-label {
            font-size: 13px;
            color: var(--gray);
            font-weight: 500;
        }

        /* Tabs styling */
        .tabs {
            display: flex;
            overflow-x: auto;
            gap: 5px;
            margin-bottom: 25px;
            padding-bottom: 5px;
            border-bottom: 2px solid #f0f0f0;
        }

        .tab {
            padding: 10px 20px;
            background: none;
            border: none;
            font-size: 14px;
            font-weight: 500;
            color: var(--gray);
            cursor: pointer;
            transition: all 0.3s;
            white-space: nowrap;
            border-radius: 8px 8px 0 0;
            position: relative;
        }

        .tab:hover {
            background: #f8f9fa;
            color: var(--dark);
        }

        .tab.active {
            color: var(--purple);
            background: #f8f9fa;
        }

        .tab.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--purple);
        }

        .tab-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
            margin-left: 5px;
            color: white;
        }

        .tab.total .tab-badge {
            background: var(--purple);
        }

        .tab.pending .tab-badge {
            background: #ffc107;
        }

        .tab.approved .tab-badge {
            background: #28a745;
        }

        .tab.in_progress .tab-badge {
            background: #4361ee;
        }

        .tab.completed .tab-badge {
            background: #4cc9f0;
        }

        .tab.rejected .tab-badge {
            background: #dc3545;
        }

        .tab.cancelled .tab-badge {
            background: #6c757d;
        }

        /* Filter section */
        .filter-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .search-box {
            position: relative;
            flex: 1;
        }

        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
        }

        .search-input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--purple);
            box-shadow: 0 0 0 3px rgba(156, 107, 255, 0.1);
        }

        .filter-select {
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            background: white;
            font-size: 14px;
            min-width: 150px;
        }

        /* Request list container */
        .requests-container {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .requests-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .request-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            border: 1px solid #f0f0f0;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .request-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .request-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 5px;
        }

        .request-code {
            color: var(--purple);
            font-weight: 500;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .request-code i {
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

        .status-approved {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .status-in_progress {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .status-completed {
            background: #d1f2eb;
            color: #117864;
            border: 1px solid #abebc6;
        }

        .status-rejected {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .status-cancelled {
            background: #f8f9fa;
            color: #495057;
            border: 1px solid #dee2e6;
        }

        .request-body {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .request-info {
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
            min-width: 100px;
        }

        .info-value {
            color: var(--gray);
        }

        .request-description {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #f5f5f5;
        }

        .description-label {
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 8px;
            display: block;
        }

        .description-text {
            color: var(--gray);
            font-size: 14px;
            line-height: 1.6;
        }

        .request-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 20px;
            margin-top: 20px;
            border-top: 1px solid #f5f5f5;
            flex-wrap: wrap;
            gap: 15px;
        }

        .request-date {
            color: var(--gray);
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .request-actions {
            display: flex;
            gap: 10px;
        }

        /* Button styles */
        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            border: none;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-approve {
            background: #28a745;
            color: white;
        }

        .btn-approve:hover {
            background: #218838;
        }

        .btn-reject {
            background: #dc3545;
            color: white;
        }

        .btn-reject:hover {
            background: #c82333;
        }

        .btn-progress {
            background: #4361ee;
            color: white;
        }

        .btn-progress:hover {
            background: #3a56d4;
        }

        .btn-complete {
            background: #4cc9f0;
            color: white;
        }

        .btn-complete:hover {
            background: #3db5d8;
        }

        .btn-cancel {
            background: #6c757d;
            color: white;
        }

        .btn-cancel:hover {
            background: #5a6268;
        }

        .btn-note {
            background: var(--purple);
            color: white;
        }

        .btn-note:hover {
            background: #7c4dff;
        }

        .btn-delete {
            background: #dc3545;
            color: white;
        }

        .btn-delete:hover {
            background: #c82333;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
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
            border-radius: 12px;
            padding: 30px;
            max-width: 500px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
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
            font-size: 18px;
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

        .form-group {
            margin-bottom: 20px;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            resize: vertical;
            min-height: 120px;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--purple);
            box-shadow: 0 0 0 3px rgba(156, 107, 255, 0.1);
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            border: 2px dashed #e0e0e0;
        }

        .empty-icon {
            font-size: 48px;
            color: #e0e0e0;
            margin-bottom: 20px;
        }

        .empty-text {
            color: var(--gray);
            max-width: 400px;
            margin: 0 auto 20px;
        }

        /* Quantity indicator */
        .quantity-indicator {
            display: inline-flex;
            align-items: center;
            background: #e6f7ff;
            color: #0066cc;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin-left: 10px;
            border: 1px solid #b3e0ff;
        }

        .quantity-icon {
            margin-right: 5px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .tabs {
                overflow-x: scroll;
                padding-bottom: 10px;
            }

            .tab {
                padding: 8px 15px;
                font-size: 13px;
            }

            .request-header {
                flex-direction: column;
                gap: 15px;
            }

            .request-body {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .request-footer {
                flex-direction: column;
                align-items: stretch;
            }

            .request-actions {
                width: 100%;
                justify-content: center;
                flex-wrap: wrap;
            }

            .filter-section form {
                flex-direction: column;
            }

            .search-box,
            .filter-select {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .stat-number {
                font-size: 24px;
            }

            .request-card {
                padding: 20px;
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
                <h1 class="page-title">Kelola Request Produk 💎</h1>
                <p class="page-subtitle">Tinjau dan kelola request produk custom dari semua user</p>
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
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total Request</div>
            </div>

            <div class="stat-card pending">
                <div class="stat-number"><?php echo $stats['pending']; ?></div>
                <div class="stat-label">Pending</div>
            </div>

            <div class="stat-card approved">
                <div class="stat-number"><?php echo $stats['approved']; ?></div>
                <div class="stat-label">Disetujui</div>
            </div>

            <div class="stat-card in_progress">
                <div class="stat-number"><?php echo $stats['in_progress']; ?></div>
                <div class="stat-label">Dalam Proses</div>
            </div>

            <div class="stat-card completed">
                <div class="stat-number"><?php echo $stats['completed']; ?></div>
                <div class="stat-label">Selesai</div>
            </div>

            <div class="stat-card rejected">
                <div class="stat-number"><?php echo $stats['rejected']; ?></div>
                <div class="stat-label">Ditolak</div>
            </div>
        </div>

        <!-- Requests Container -->
        <div class="requests-container">
            <!-- Tabs -->
            <div class="tabs">
                <!-- Tab Semua -->
                <a href="requests.php?status=all<?php
                echo !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : '';
                echo $dateFilter ? '&date=' . urlencode($dateFilter) : '';
                ?>" class="tab total <?php echo $statusFilter == 'all' || $statusFilter == '' ? 'active' : ''; ?>">
                    Semua
                    <span class="tab-badge"><?php echo $stats['total']; ?></span>
                </a>

                <!-- Tab Pending -->
                <a href="requests.php?status=pending<?php
                echo !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : '';
                echo $dateFilter ? '&date=' . urlencode($dateFilter) : '';
                ?>" class="tab pending <?php echo $statusFilter == 'pending' ? 'active' : ''; ?>">
                    Pending
                    <span class="tab-badge"><?php echo $stats['pending']; ?></span>
                </a>

                <!-- Tab Disetujui -->
                <a href="requests.php?status=approved<?php
                echo !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : '';
                echo $dateFilter ? '&date=' . urlencode($dateFilter) : '';
                ?>" class="tab approved <?php echo $statusFilter == 'approved' ? 'active' : ''; ?>">
                    Disetujui
                    <span class="tab-badge"><?php echo $stats['approved']; ?></span>
                </a>

                <!-- Tab Dalam Proses -->
                <a href="requests.php?status=in_progress<?php
                echo !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : '';
                echo $dateFilter ? '&date=' . urlencode($dateFilter) : '';
                ?>" class="tab in_progress <?php echo $statusFilter == 'in_progress' ? 'active' : ''; ?>">
                    Dalam Proses
                    <span class="tab-badge"><?php echo $stats['in_progress']; ?></span>
                </a>

                <!-- Tab Selesai -->
                <a href="requests.php?status=completed<?php
                echo !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : '';
                echo $dateFilter ? '&date=' . urlencode($dateFilter) : '';
                ?>"
                    class="tab completed <?php echo $statusFilter == 'completed' || $statusFilter == 'complete' ? 'active' : ''; ?>">
                    Selesai
                    <span class="tab-badge"><?php echo $stats['completed']; ?></span>
                </a>

                <!-- Tab Ditolak -->
                <a href="requests.php?status=rejected<?php
                echo !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : '';
                echo $dateFilter ? '&date=' . urlencode($dateFilter) : '';
                ?>" class="tab rejected <?php echo $statusFilter == 'rejected' ? 'active' : ''; ?>">
                    Ditolak
                    <span class="tab-badge"><?php echo $stats['rejected']; ?></span>
                </a>

                <!-- Tab Dibatalkan -->
                <a href="requests.php?status=cancelled<?php
                echo !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : '';
                echo $dateFilter ? '&date=' . urlencode($dateFilter) : '';
                ?>" class="tab cancelled <?php echo $statusFilter == 'cancelled' ? 'active' : ''; ?>">
                    Dibatalkan
                    <span class="tab-badge"><?php echo $stats['cancelled']; ?></span>
                </a>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <form method="GET" action="" id="filterForm" style="display: flex; gap: 15px; align-items: center;">
                    <div class="search-box">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" name="search" class="search-input"
                            placeholder="Cari request (kode, nama produk, user)..."
                            value="<?php echo htmlspecialchars($searchQuery); ?>">
                    </div>

                    <select name="date" class="filter-select" onchange="document.getElementById('filterForm').submit()">
                        <option value="">Semua Tanggal</option>
                        <option value="today" <?php echo $dateFilter == 'today' ? 'selected' : ''; ?>>Hari Ini</option>
                        <option value="week" <?php echo $dateFilter == 'week' ? 'selected' : ''; ?>>7 Hari Terakhir
                        </option>
                        <option value="month" <?php echo $dateFilter == 'month' ? 'selected' : ''; ?>>30 Hari Terakhir
                        </option>
                    </select>

                    <input type="hidden" name="status" id="statusFilter"
                        value="<?php echo htmlspecialchars($statusFilter); ?>">

                    <button type="submit" class="btn btn-secondary">
                        <i class="fas fa-filter"></i> Filter
                    </button>

                    <?php if (!empty($searchQuery) || !empty($dateFilter) || $statusFilter != 'all'): ?>
                        <a href="requests.php" class="btn" style="background: #6c757d; color: white;">
                            <i class="fas fa-times"></i> Reset
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Section Header -->
            <div class="section-header"
                style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f5f5f5;">
                <h2 class="section-title"
                    style="font-size: 20px; color: var(--dark); margin-bottom: 5px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-clipboard-list"></i> Daftar Request dari Semua User
                </h2>
                <p class="section-subtitle" style="color: var(--gray); font-size: 14px;">
                    Menampilkan <?php echo $total_requests; ?> request
                    <?php if (!empty($statusFilter) && $statusFilter != 'all'): ?>
                        dengan status "<?php echo getStatusText($statusFilter); ?>"
                    <?php endif; ?>
                </p>
            </div>

            <!-- Request List -->
            <div class="requests-list" id="requestList">
                <?php if (empty($requests)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-inbox"></i>
                        </div>
                        <h3>Belum ada request</h3>
                        <p class="empty-text">
                            <?php if (!empty($searchQuery) || !empty($dateFilter) || $statusFilter != 'all'): ?>
                                Tidak ditemukan request dengan filter yang dipilih.
                            <?php else: ?>
                                Belum ada user yang membuat request produk custom.
                            <?php endif; ?>
                        </p>
                        <?php if (!empty($searchQuery) || !empty($dateFilter) || $statusFilter != 'all'): ?>
                            <a href="requests.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Hapus Filter
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($requests as $request): ?>
                        <div class="request-card">
                            <div class="request-header">
                                <div>
                                    <h3 class="request-title">
                                        <?php echo htmlspecialchars($request['product_name']); ?>
                                        <span class="quantity-indicator">
                                            <i class="fas fa-box quantity-icon"></i>
                                            <?php echo $request['quantity']; ?> pcs
                                        </span>
                                    </h3>
                                    <div class="request-code">
                                        <i class="fas fa-hashtag"></i>
                                        <?php echo htmlspecialchars($request['request_code']); ?>
                                        &bull;
                                        <i class="fas fa-user"></i>
                                        <?php echo htmlspecialchars($request['full_name']); ?>
                                    </div>
                                </div>
                                <span class="status-badge <?php echo getStatusBadge($request['status']); ?>">
                                    <i class="fas fa-circle"></i>
                                    <?php echo getStatusText($request['status']); ?>
                                </span>
                            </div>

                            <div class="request-body">
                                <div class="request-info">
                                    <div class="info-row">
                                        <i class="fas fa-tag"></i>
                                        <span class="info-label">Kategori:</span>
                                        <span
                                            class="info-value"><?php echo htmlspecialchars($request['category_name'] ?? 'Tidak ada'); ?></span>
                                    </div>
                                    <div class="info-row">
                                        <i class="fas fa-layer-group"></i>
                                        <span class="info-label">Material:</span>
                                        <span
                                            class="info-value"><?php echo htmlspecialchars($request['material'] ?: '-'); ?></span>
                                    </div>
                                    <div class="info-row">
                                        <i class="fas fa-palette"></i>
                                        <span class="info-label">Warna:</span>
                                        <span
                                            class="info-value"><?php echo htmlspecialchars($request['color'] ?: '-'); ?></span>
                                    </div>
                                </div>

                                <div class="request-info">
                                    <div class="info-row">
                                        <i class="fas fa-box"></i>
                                        <span class="info-label">Jumlah:</span>
                                        <span class="info-value"><?php echo $request['quantity']; ?> pcs</span>
                                    </div>
                                    <div class="info-row">
                                        <i class="fas fa-money-bill-wave"></i>
                                        <span class="info-label">Budget:</span>
                                        <span class="info-value">Rp
                                            <?php echo number_format($request['budget'], 0, ',', '.'); ?></span>
                                    </div>
                                    <?php if ($request['deadline']): ?>
                                        <div class="info-row">
                                            <i class="fas fa-calendar"></i>
                                            <span class="info-label">Deadline:</span>
                                            <span class="info-value"><?php echo $request['deadline_formatted']; ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="request-info">
                                    <div class="info-row">
                                        <i class="fas fa-user"></i>
                                        <span class="info-label">User:</span>
                                        <span class="info-value"><?php echo htmlspecialchars($request['full_name']); ?></span>
                                    </div>
                                    <div class="info-row">
                                        <i class="fas fa-envelope"></i>
                                        <span class="info-label">Email:</span>
                                        <span class="info-value"><?php echo htmlspecialchars($request['email']); ?></span>
                                    </div>
                                    <?php if ($request['phone']): ?>
                                        <div class="info-row">
                                            <i class="fas fa-phone"></i>
                                            <span class="info-label">Telepon:</span>
                                            <span class="info-value"><?php echo htmlspecialchars($request['phone']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if (!empty($request['description'])): ?>
                                <div class="request-description">
                                    <span class="description-label">Deskripsi:</span>
                                    <p class="description-text"><?php echo nl2br(htmlspecialchars($request['description'])); ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if ($request['reference_image']): ?>
                                <?php
                                $imagePath = getReferenceImage($request['reference_image']);
                                if ($imagePath): ?>
                                    <div class="request-description">
                                        <span class="description-label">Gambar Referensi:</span>
                                        <div style="margin-top: 10px;">
                                            <img src="<?php echo htmlspecialchars($imagePath); ?>" alt="Reference Image"
                                                style="max-width: 200px; max-height: 200px; border-radius: 8px; border: 1px solid #e0e0e0;"
                                                onerror="this.onerror=null;this.style.display='none';">
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php if (!empty($request['notes'])): ?>
                                <div class="request-description">
                                    <span class="description-label">Catatan Admin:</span>
                                    <p class="description-text"><?php echo nl2br(htmlspecialchars($request['notes'])); ?></p>
                                </div>
                            <?php endif; ?>

                            <div class="request-footer">
                                <div class="request-date">
                                    <i class="far fa-clock"></i>
                                    Dibuat: <?php echo $request['formatted_created']; ?>
                                </div>

                                <div class="request-actions">
                                    <?php if ($request['status'] == 'pending'): ?>
                                        <button class="btn btn-approve"
                                            onclick="confirmAction('approve', <?php echo $request['id']; ?>, '<?php echo htmlspecialchars($request['request_code']); ?>')">
                                            <i class="fas fa-check"></i> Setujui
                                        </button>
                                        <button class="btn btn-reject"
                                            onclick="confirmAction('reject', <?php echo $request['id']; ?>, '<?php echo htmlspecialchars($request['request_code']); ?>')">
                                            <i class="fas fa-times"></i> Tolak
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($request['status'] == 'approved'): ?>
                                        <button class="btn btn-progress"
                                            onclick="confirmAction('in_progress', <?php echo $request['id']; ?>, '<?php echo htmlspecialchars($request['request_code']); ?>')">
                                            <i class="fas fa-play"></i> Mulai Proses
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($request['status'] == 'in_progress'): ?>
                                        <button class="btn btn-complete"
                                            onclick="confirmAction('complete', <?php echo $request['id']; ?>, '<?php echo htmlspecialchars($request['request_code']); ?>')">
                                            <i class="fas fa-check-circle"></i> Selesai
                                        </button>
                                    <?php endif; ?>

                                    <?php if (in_array($request['status'], ['pending', 'approved', 'in_progress'])): ?>
                                        <button class="btn btn-note"
                                            onclick="showNotesModal(<?php echo $request['id']; ?>, '<?php echo addslashes($request['notes'] ?? ''); ?>')">
                                            <i class="fas fa-sticky-note"></i>
                                            <?php echo $request['notes'] ? 'Edit Catatan' : 'Tambah Catatan'; ?>
                                        </button>

                                        <button class="btn btn-cancel"
                                            onclick="confirmAction('cancel', <?php echo $request['id']; ?>, '<?php echo htmlspecialchars($request['request_code']); ?>')">
                                            <i class="fas fa-ban"></i> Batalkan
                                        </button>
                                    <?php endif; ?>

                                    <button class="btn btn-delete"
                                        onclick="confirmAction('delete', <?php echo $request['id']; ?>, '<?php echo htmlspecialchars($request['request_code']); ?>')">
                                        <i class="fas fa-trash"></i> Hapus
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Notes Modal -->
    <div class="modal" id="notesModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Tambah/Edit Catatan</h3>
                <button class="modal-close" onclick="hideNotesModal()">&times;</button>
            </div>
            <form method="POST" action="" id="notesForm">
                <input type="hidden" name="action" value="save_notes">
                <input type="hidden" name="id" id="modalRequestId">

                <div class="form-group">
                    <label for="notes">Catatan untuk User</label>
                    <textarea class="form-control" id="notes" name="notes"
                        placeholder="Berikan catatan atau instruksi untuk user..." rows="5"></textarea>
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

    <script>
        // Toggle mobile menu
        document.getElementById('menuToggle')?.addEventListener('click', function () {
            document.querySelector('.sidebar').classList.toggle('active');
        });

        // Notes modal
        const notesModal = document.getElementById('notesModal');
        const notesForm = document.getElementById('notesForm');
        const modalRequestId = document.getElementById('modalRequestId');
        const notesTextarea = document.getElementById('notes');

        function showNotesModal(requestId, currentNotes = '') {
            modalRequestId.value = requestId;
            notesTextarea.value = currentNotes;
            notesModal.classList.add('active');
            notesTextarea.focus();
        }

        function hideNotesModal() {
            notesModal.classList.remove('active');
            notesTextarea.value = '';
        }

        // Close modal when clicking outside
        notesModal.addEventListener('click', function (e) {
            if (e.target === notesModal) {
                hideNotesModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && notesModal.classList.contains('active')) {
                hideNotesModal();
            }
        });

        // Form validation
        notesForm.addEventListener('submit', function (e) {
            const notes = notesTextarea.value.trim();

            if (!notes) {
                e.preventDefault();
                Swal.fire({
                    title: 'Peringatan!',
                    text: 'Harap isi catatan sebelum menyimpan.',
                    icon: 'warning',
                    confirmButtonColor: '#9c6bff'
                });
                notesTextarea.focus();
                return false;
            }

            return true;
        });

        // SweetAlert2 confirmation function
        function confirmAction(action, requestId, requestCode) {
            event.preventDefault();

            const actionTexts = {
                'approve': { title: 'Setujui Request', text: 'Setujui request ini?', icon: 'question', color: '#28a745' },
                'reject': { title: 'Tolak Request', text: 'Tolak request ini?', icon: 'question', color: '#dc3545' },
                'in_progress': { title: 'Mulai Proses', text: 'Mulai proses produksi?', icon: 'question', color: '#4361ee' },
                'complete': { title: 'Selesaikan Request', text: 'Tandai request sebagai selesai?', icon: 'question', color: '#4cc9f0' },
                'cancel': { title: 'Batalkan Request', text: 'Batalkan request ini?', icon: 'question', color: '#6c757d' },
                'delete': { title: 'Hapus Request', text: 'Hapus request ini?', icon: 'warning', color: '#dc3545' }
            };

            const actionData = actionTexts[action] || { title: 'Konfirmasi', text: 'Lanjutkan?', icon: 'question', color: '#ff6b93' };

            Swal.fire({
                title: actionData.title,
                html: `
                    <div style="text-align: center;">
                        <i class="fas fa-${action === 'approve' ? 'check' : action === 'reject' ? 'times' : action === 'delete' ? 'trash' : 'question'}" 
                           style="font-size: 60px; color: ${actionData.color}; margin-bottom: 20px;"></i>
                        <p>Request: <strong>#${requestCode}</strong></p>
                        <p>${actionData.text}</p>
                        <p style="color: #666; font-size: 14px; margin-top: 10px;">Tindakan ini tidak dapat dibatalkan</p>
                    </div>
                `,
                icon: actionData.icon,
                showCancelButton: true,
                confirmButtonColor: actionData.color,
                cancelButtonColor: '#6c757d',
                confirmButtonText: `Ya, ${action === 'approve' ? 'Setujui' : action === 'reject' ? 'Tolak' : action === 'delete' ? 'Hapus' : 'Lanjutkan'}`,
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Get current URL parameters
                    const urlParams = new URLSearchParams(window.location.search);
                    const search = urlParams.get('search') || '';
                    const date = urlParams.get('date') || '';
                    const status = urlParams.get('status') || 'all';

                    // Redirect dengan semua parameter yang ada
                    let redirectUrl = `?action=${action}&id=${requestId}`;
                    if (search) redirectUrl += `&search=${encodeURIComponent(search)}`;
                    if (date) redirectUrl += `&date=${encodeURIComponent(date)}`;
                    if (status && status !== 'all') redirectUrl += `&status=${encodeURIComponent(status)}`;

                    window.location.href = redirectUrl;
                }
            });
        }

        // Search 
        const searchInput = document.querySelector('.search-input');
        if (searchInput) {
            let searchTimeout;

            searchInput.addEventListener('input', function () {
                clearTimeout(searchTimeout);

                searchTimeout = setTimeout(() => {
                    if (this.value.length >= 3 || this.value.length === 0) {
                        document.getElementById('filterForm').submit();
                    }
                }, 500);
            });
        }
    </script>
</body>

</html>