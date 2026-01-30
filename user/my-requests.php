<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

Auth::requireLogin();

$user = Auth::getUser();
$message = '';
$error = '';

// Get user ID
$user_id = $_SESSION['user_id'];

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query
$query = "
    SELECT 
        r.*,
        c.name as category_name,
        DATE_FORMAT(r.created_at, '%d %b %Y %H:%i') as formatted_date,
        DATE_FORMAT(r.deadline, '%d %b %Y') as formatted_deadline
    FROM requests r
    LEFT JOIN categories c ON r.category_id = c.id
    WHERE r.user_id = ?
";

$params = [$user_id];

// Add status filter 
if ($status_filter != 'all') {
    if ($status_filter == 'complete') {
        $query .= " AND r.status = 'completed'";
    } else {
        $query .= " AND r.status = ?";
        $params[] = $status_filter;
    }
}

// Add search filter
if (!empty($search)) {
    $query .= " AND (r.product_name LIKE ? OR r.request_code LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

$query .= " ORDER BY r.created_at DESC";

// Get total count
$count_query = "SELECT COUNT(*) as total FROM requests WHERE user_id = ?";
$count_params = [$user_id];

if ($status_filter != 'all') {
    if ($status_filter == 'complete') {
        $count_query .= " AND status = 'completed'";
    } else {
        $count_query .= " AND status = ?";
        $count_params[] = $status_filter;
    }
}

try {
    $stmt = $pdo->prepare($count_query);
    $stmt->execute($count_params);
    $total_requests = $stmt->fetch()['total'];

    // Get requests
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $requests = $stmt->fetchAll();
} catch (PDOException $e) {
    $requests = [];
    $total_requests = 0;
    error_log("Error fetching requests: " . $e->getMessage());
}

// Get request statistics
try {
    // Total requests
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM requests WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $total_all = $stmt->fetch()['total'];

    // Pending requests
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM requests WHERE user_id = ? AND status = 'pending'");
    $stmt->execute([$user_id]);
    $total_pending = $stmt->fetch()['total'];

    // Approved requests
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM requests WHERE user_id = ? AND status = 'approved'");
    $stmt->execute([$user_id]);
    $total_approved = $stmt->fetch()['total'];

    // In Progress requests
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM requests WHERE user_id = ? AND status = 'in_progress'");
    $stmt->execute([$user_id]);
    $total_in_progress = $stmt->fetch()['total'];

    // Completed requests - handle 'complete', 'completed'
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM requests WHERE user_id = ? AND status IN ('completed', 'complete')");
    $stmt->execute([$user_id]);
    $total_completed = $stmt->fetch()['total'];

    // Rejected requests
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM requests WHERE user_id = ? AND status = 'rejected'");
    $stmt->execute([$user_id]);
    $total_rejected = $stmt->fetch()['total'];

    // Cancelled requests
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM requests WHERE user_id = ? AND status = 'cancelled'");
    $stmt->execute([$user_id]);
    $total_cancelled = $stmt->fetch()['total'];
} catch (PDOException $e) {
    $total_all = $total_pending = $total_approved = $total_in_progress = $total_completed = $total_rejected = $total_cancelled = 0;
    error_log("Error fetching request statistics: " . $e->getMessage());
}

// Function to get status badge class
function getStatusBadge($status)
{
    $classes = [
        'pending' => 'status-pending',
        'approved' => 'status-approved',
        'in_progress' => 'status-in_progress',
        'completed' => 'status-completed',
        'complete' => 'status-completed',
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
        'complete' => 'Selesai',
        'rejected' => 'Ditolak',
        'cancelled' => 'Dibatalkan'
    ];
    return $texts[$status] ?? ucfirst($status);
}

// Function to get reference image path
function getReferenceImage($filename)
{
    if (empty($filename))
        return null;

    // Check if filename contains full path
    if (strpos($filename, 'requests/') === 0) {
        $filename = substr($filename, 9);
    }

    $path = '../assets/images/requests/' . basename($filename);
    return file_exists($path) ? $path : null;
}

// Stats array for consistency with admin
$stats = [
    'total' => $total_all,
    'pending' => $total_pending,
    'approved' => $total_approved,
    'in_progress' => $total_in_progress,
    'completed' => $total_completed,
    'rejected' => $total_rejected,
    'cancelled' => $total_cancelled
];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Saya - Roncelizz</title>
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
            border-left: 5px solid var(--purple);
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
            color: var(--purple);
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
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
            border: 2px solid transparent;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 100%;
            min-height: 100px;
        }

        .stat-card.card-btn {
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }

        .stat-card.card-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            border-color: var(--border);
        }

        .stat-card.active {
            border-color: var(--purple);
            background: linear-gradient(135deg, rgba(156, 107, 255, 0.05), rgba(255, 107, 147, 0.05));
        }

        /* Stat info */
        .stat-info {
            flex: 1;
        }

        .stat-number {
            font-size: 36px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 5px;
            line-height: 1;
        }

        .stat-label {
            color: var(--gray);
            font-size: 14px;
            font-weight: 500;
        }

        /* Stat icon di kanan */
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            background: rgba(255, 107, 147, 0.1);
            color: var(--pink);
            margin-left: 15px;
        }

        .stat-icon.pending {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }

        .stat-icon.approved {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        .stat-icon.in-progress {
            background: rgba(67, 97, 238, 0.1);
            color: #4361ee;
        }

        .stat-icon.completed {
            background: rgba(76, 201, 240, 0.1);
            color: #4cc9f0;
        }

        .stat-icon.rejected {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .stat-icon.cancelled {
            background: rgba(108, 117, 125, 0.1);
            color: #6c757d;
        }

        /* Filter Info Section */
        .filter-info {
            background: linear-gradient(135deg, #f8f9ff, #f0f2ff);
            border-radius: 12px;
            padding: 20px 25px;
            margin-bottom: 30px;
            border: 2px dashed var(--border);
        }

        .filter-info-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .filter-info-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .filter-info-label {
            font-size: 14px;
            color: var(--gray);
            font-weight: 500;
        }

        .filter-info-value {
            font-size: 18px;
            color: var(--purple);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-info-value::before {
            content: '';
            display: inline-block;
            width: 10px;
            height: 10px;
            background: var(--purple);
            border-radius: 50%;
        }

        .filter-info-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .request-count {
            background: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            color: var(--dark);
            border: 1px solid var(--border);
        }

        .clear-filter-btn {
            background: var(--pink);
            color: white;
            padding: 8px 15px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .clear-filter-btn:hover {
            background: #ff5580;
            transform: translateY(-2px);
        }

        /* Active indicator untuk stat card */
        .stat-card.active::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--pink), var(--purple));
            border-radius: 2px 2px 0 0;
        }

        /* Hover effect untuk icon */
        .stat-card.card-btn:hover .stat-icon {
            transform: scale(1.05);
            transition: transform 0.3s;
        }

        /* Responsive design */
        @media (max-width: 992px) {
            .stats-cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 576px) {
            .stats-cards {
                grid-template-columns: 1fr;
            }

            .filter-info-content {
                flex-direction: column;
                align-items: flex-start;
            }

            .filter-info-right {
                width: 100%;
                justify-content: space-between;
            }
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

        /* Tabs styling */
        .tabs {
            display: flex;
            overflow-x: auto;
            gap: 5px;
            margin-bottom: 25px;
            padding-bottom: 5px;
            border-bottom: 2px solid var(--light-gray);
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
            text-decoration: none;
            display: flex;
            align-items: center;
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

        /* Requests List */
        .requests-section {
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

        /* Request Cards */
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
            border: 1px solid var(--border);
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
            border-top: 1px solid var(--light-gray);
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
            border-top: 1px solid var(--light-gray);
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

        /* Image Preview */
        .image-preview {
            width: 100px;
            height: 100px;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            transition: transform 0.3s;
        }

        .image-preview:hover {
            transform: scale(1.05);
        }

        .image-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Modal for Image */
        .image-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            max-width: 90%;
            max-height: 90%;
        }

        .modal-content img {
            width: 100%;
            height: auto;
            border-radius: 8px;
        }

        .close-modal {
            position: absolute;
            top: 20px;
            right: 20px;
            color: white;
            font-size: 30px;
            cursor: pointer;
            background: none;
            border: none;
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
                align-items: flex-start;
            }

            .request-body {
                grid-template-columns: 1fr;
            }

            .request-footer {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-section form {
                flex-direction: column;
            }

            .filter-control {
                width: 100%;
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

        .request-card {
            animation: fadeIn 0.5s ease;
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
                        <a href="my-requests.php" class="menu-link active">
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
                    <i class="fas fa-list-check"></i> Request Saya
                </h1>
                <p>Kelola dan pantau status request produk custom Anda</p>
            </div>

            <!-- Stats Cards -->
            <div class="stats-cards">
                <!-- Total Request -->
                <div class="stat-card">
                    <div class="stat-info">
                        <div class="stat-number"><?php echo $stats['total']; ?></div>
                        <div class="stat-label">Total Request</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-list-check"></i>
                    </div>
                </div>

                <!-- Pending -->
                <?php if ($status_filter == 'pending'): ?>
                    <div class="stat-card active">
                        <div class="stat-info">
                            <div class="stat-number"><?php echo $stats['pending']; ?></div>
                            <div class="stat-label">Pending</div>
                        </div>
                        <div class="stat-icon pending">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="my-requests.php?status=pending<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
                        class="stat-card card-btn">
                        <div class="stat-info">
                            <div class="stat-number"><?php echo $stats['pending']; ?></div>
                            <div class="stat-label">Pending</div>
                        </div>
                        <div class="stat-icon pending">
                            <i class="fas fa-clock"></i>
                        </div>
                    </a>
                <?php endif; ?>

                <!-- Disetujui -->
                <?php if ($status_filter == 'approved'): ?>
                    <div class="stat-card active">
                        <div class="stat-info">
                            <div class="stat-number"><?php echo $stats['approved']; ?></div>
                            <div class="stat-label">Disetujui</div>
                        </div>
                        <div class="stat-icon approved">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="my-requests.php?status=approved<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
                        class="stat-card card-btn">
                        <div class="stat-info">
                            <div class="stat-number"><?php echo $stats['approved']; ?></div>
                            <div class="stat-label">Disetujui</div>
                        </div>
                        <div class="stat-icon approved">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </a>
                <?php endif; ?>

                <!-- Dalam Proses -->
                <?php if ($status_filter == 'in_progress'): ?>
                    <div class="stat-card active">
                        <div class="stat-info">
                            <div class="stat-number"><?php echo $stats['in_progress']; ?></div>
                            <div class="stat-label">Dalam Proses</div>
                        </div>
                        <div class="stat-icon in-progress">
                            <i class="fas fa-spinner"></i>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="my-requests.php?status=in_progress<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
                        class="stat-card card-btn">
                        <div class="stat-info">
                            <div class="stat-number"><?php echo $stats['in_progress']; ?></div>
                            <div class="stat-label">Dalam Proses</div>
                        </div>
                        <div class="stat-icon in-progress">
                            <i class="fas fa-spinner"></i>
                        </div>
                    </a>
                <?php endif; ?>

                <!-- Selesai -->
                <?php if ($status_filter == 'completed' || $status_filter == 'complete'): ?>
                    <div class="stat-card active">
                        <div class="stat-info">
                            <div class="stat-number"><?php echo $stats['completed']; ?></div>
                            <div class="stat-label">Selesai</div>
                        </div>
                        <div class="stat-icon completed">
                            <i class="fas fa-check-double"></i>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="my-requests.php?status=completed<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
                        class="stat-card card-btn">
                        <div class="stat-info">
                            <div class="stat-number"><?php echo $stats['completed']; ?></div>
                            <div class="stat-label">Selesai</div>
                        </div>
                        <div class="stat-icon completed">
                            <i class="fas fa-check-double"></i>
                        </div>
                    </a>
                <?php endif; ?>
            </div>

            <!-- Status Filter Info (hanya tampil jika ada filter aktif) -->
            <?php if ($status_filter != 'all' && $status_filter != ''): ?>
                <div class="filter-info">
                    <div class="filter-info-content">
                        <div class="filter-info-left">
                            <div class="filter-info-label">Filter Aktif:</div>
                            <div class="filter-info-value"><?php echo getStatusText($status_filter); ?></div>
                        </div>
                        <div class="filter-info-right">
                            <div class="request-count"><?php echo $total_requests; ?> Request</div>
                            <a href="my-requests.php<?php echo !empty($search) ? '?search=' . urlencode($search) : ''; ?>"
                                class="clear-filter-btn">
                                <i class="fas fa-times"></i> Hapus Filter
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Filter Section -->
            <div class="filter-section">
                <form method="GET" action="" class="filter-grid">
                    <div class="filter-group">
                        <label class="filter-label">Cari Request</label>
                        <input type="text" name="search" class="filter-control"
                            value="<?php echo htmlspecialchars($search); ?>"
                            placeholder="Cari berdasarkan nama atau kode...">
                    </div>

                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">

                    <div class="filter-group">
                        <button type="submit" class="btn btn-primary" style="height: 46px;">
                            <i class="fas fa-filter"></i> Terapkan Filter
                        </button>
                        <?php if (!empty($search) || $status_filter != 'all'): ?>
                            <a href="my-requests.php" class="btn btn-outline" style="height: 46px; margin-top: 10px;">
                                <i class="fas fa-times"></i> Reset Filter
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Requests List -->
            <div class="requests-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-clipboard-list"></i> Daftar Request
                    </h2>
                    <p class="section-subtitle">
                        Menampilkan <?php echo $total_requests; ?> request
                        <?php if (!empty($status_filter) && $status_filter != 'all'): ?>
                            dengan status "<?php echo getStatusText($status_filter); ?>"
                        <?php endif; ?>
                    </p>
                </div>

                <?php if (empty($requests)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-inbox"></i>
                        </div>
                        <h3>Belum ada request</h3>
                        <p class="empty-text">
                            <?php if (!empty($search) || $status_filter != 'all'): ?>
                                Tidak ditemukan request dengan filter yang dipilih.
                            <?php else: ?>
                                Anda belum membuat request produk custom. Mulai dengan membuat request baru!
                            <?php endif; ?>
                        </p>
                        <a href="request.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Buat Request Baru
                        </a>
                    </div>
                <?php else: ?>
                    <div class="requests-list">
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
                                                <span class="info-value"><?php echo $request['formatted_deadline']; ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if (!empty($request['description'])): ?>
                                    <div class="request-description">
                                        <span class="description-label">Deskripsi:</span>
                                        <p class="description-text"><?php echo nl2br(htmlspecialchars($request['description'])); ?>
                                        </p>
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
                                        Dibuat: <?php echo $request['formatted_date']; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Image Modal -->
    <div id="imageModal" class="image-modal">
        <button class="close-modal" onclick="closeImageModal()">&times;</button>
        <div class="modal-content">
            <img id="modalImage" src="" alt="Preview">
        </div>
    </div>

    <script>
        // View image in modal
        function viewImage(imagePath) {
            const modal = document.getElementById('imageModal');
            const modalImg = document.getElementById('modalImage');
            modal.style.display = 'flex';
            modalImg.src = '../assets/images/requests/' + imagePath;
        }

        // Close image modal
        function closeImageModal() {
            document.getElementById('imageModal').style.display = 'none';
        }

        // View request details
        function viewRequest(requestId) {
            alert('Menampilkan detail request ID: ' + requestId);
        }

        // Close modal when clicking outside
        window.addEventListener('click', function (event) {
            const modal = document.getElementById('imageModal');
            if (event.target == modal) {
                closeImageModal();
            }
        });

        // Close modal with ESC key
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeImageModal();
            }
        });

        // Animate cards on load
        document.addEventListener('DOMContentLoaded', function () {
            const cards = document.querySelectorAll('.request-card');
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