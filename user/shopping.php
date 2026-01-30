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

// Initialize variables
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$categoryFilter = isset($_GET['category']) ? $_GET['category'] : '';
$categories = [];
$products = [];

// Initialize stats variables
$productCount = 0;
$totalSpending = 0;
$activeCarts = 0;

// Get data
try {
    // Get all categories for filtering
    $stmt = $pdo->prepare("SELECT * FROM categories ORDER BY name");
    $stmt->execute();
    $categories = $stmt->fetchAll();

    // Get all products from database with filtering
    $sql = "
        SELECT 
            p.id,
            p.name,
            p.category_id,
            p.description,
            p.type,
            p.price,
            p.stock,
            p.image_url,
            p.created_at,
            c.name as category_name,
            CASE 
                WHEN p.type = 'limited' THEN 'LIMITED'
                WHEN p.type = 'regular' THEN 'REGULAR'
                ELSE 'OTHER'
            END as product_type
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.deleted_at IS NULL
        AND p.status = 'available'
    ";


    $params = [];

    if (!empty($categoryFilter) && $categoryFilter !== 'all') {
        $sql .= " AND p.category_id = ?";
        $params[] = $categoryFilter;
    }

    if (!empty($searchQuery)) {
        $sql .= " AND (p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?)";
        $searchTerm = "%" . $searchQuery . "%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    $sql .= " ORDER BY 
              CASE WHEN p.type = 'limited' THEN 1 ELSE 2 END,
              p.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    // Calculate stats 
    $productCount = count($products);
    $totalSpending = 0;

    // Try to get cart count if carts table exists
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'carts'");
        $stmt->execute();
        $cartsTableExists = $stmt->fetch();

        if ($cartsTableExists) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM carts WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $activeCarts = $stmt->fetch()['total'] ?? 0;
        }
    } catch (Exception $e) {
        $activeCarts = 0;
    }

} catch (PDOException $e) {
    error_log("Database error in shopping.php: " . $e->getMessage());
    $_SESSION['error'] = 'Terjadi kesalahan saat mengambil data produk. Silakan coba lagi.';
}

// Function to get correct image path
function getProductImage($image_url)
{
    if (empty($image_url)) {
        return false;
    }

    // Cek URL
    if (filter_var($image_url, FILTER_VALIDATE_URL)) {
        return $image_url;
    }

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

    // Jika tidak ditemukan, coba path default
    $default_path = '../assets/images/default-product.jpg';
    if (file_exists($default_path)) {
        return $default_path;
    }

    return false;
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Belanja - Roncelizz</title>
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

        .submenu .menu-link.active {
            background: rgba(255, 255, 255, 0.15);
            border-left: 3px solid var(--peach);
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
            border-left: 5px solid var(--purple);
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
            background: linear-gradient(45deg, rgba(156, 107, 255, 0.05), rgba(255, 107, 147, 0.05));
            border-radius: 50%;
            transform: translate(100px, -100px);
        }

        .welcome-header h1 {
            font-size: 32px;
            color: var(--purple);
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
            background: var(--purple);
        }

        .stat-card:nth-child(2)::before {
            background: var(--pink);
        }

        .stat-card:nth-child(3)::before {
            background: var(--peach);
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

        /* Quick Actions */
        .quick-actions {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .action-btn {
            flex: 1;
            min-width: 200px;
            background: white;
            border-radius: 12px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            text-decoration: none;
            color: var(--dark);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border);
            transition: all 0.3s;
        }

        .action-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            border-color: var(--purple);
        }

        .action-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .action-icon.order {
            background: linear-gradient(135deg, #ff6b93, #ff9c6b);
            color: white;
        }

        .action-icon.request {
            background: linear-gradient(135deg, #9c6bff, #6b6bff);
            color: white;
        }

        .action-icon.history {
            background: linear-gradient(135deg, #6bffb8, #6bffd9);
            color: white;
        }

        .action-text h3 {
            font-size: 16px;
            margin-bottom: 5px;
        }

        .action-text p {
            font-size: 13px;
            color: var(--gray);
        }

        /* Shopping Section */
        .shopping-section {
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
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

        /* Filter and Search */
        .filter-container {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-select {
            padding: 10px 15px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: white;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            color: var(--dark);
            min-width: 180px;
            transition: all 0.3s;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--purple);
            box-shadow: 0 0 0 2px rgba(156, 107, 255, 0.2);
        }

        .search-box {
            position: relative;
            min-width: 250px;
        }

        .search-input {
            width: 100%;
            padding: 10px 15px 10px 40px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            transition: all 0.3s;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--purple);
            box-shadow: 0 0 0 2px rgba(156, 107, 255, 0.2);
        }

        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
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

        .badge-limited {
            background: linear-gradient(45deg, #ff6b93, #ff4757);
        }

        .badge-regular {
            background: linear-gradient(45deg, #9c6bff, #6b6bff);
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
            font-weight: 600;
            background: var(--light);
            padding: 5px 12px;
            border-radius: 12px;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .stock-available {
            color: var(--pink);
        }

        .stock-low {
            color: #ff9c6b;
        }

        .stock-out {
            color: #ff6b6b;
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

        .btn-primary:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
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

        /* Product Count */
        .product-count {
            color: var(--purple);
            font-size: 14px;
            font-weight: 600;
            background: var(--light);
            padding: 5px 15px;
            border-radius: 20px;
            margin-left: 10px;
        }

        /* Error message */
        .error-message {
            background: #fff5f5;
            border: 2px solid #fed7d7;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            color: #c53030;
            text-align: center;
        }

        .error-message i {
            font-size: 40px;
            margin-bottom: 10px;
            color: #e53e3e;
        }

        .retry-btn {
            background: #4299e1;
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
            margin-top: 10px;
            transition: background 0.3s;
        }

        .retry-btn:hover {
            background: #3182ce;
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

            .quick-actions {
                flex-direction: column;
            }

            .action-btn {
                width: 100%;
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

        .cart-notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--purple);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 10px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s ease;
        }

        .cart-notification.show {
            transform: translateY(0);
            opacity: 1;
        }

        /* Loading Spinner */
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
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

        /* Order Form Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background: white;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            transform: translateY(-50px);
            transition: transform 0.3s ease;
        }

        .modal-overlay.active .modal-content {
            transform: translateY(0);
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid var(--light-gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 20px;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            color: var(--gray);
            cursor: pointer;
            transition: color 0.3s;
        }

        .modal-close:hover {
            color: var(--pink);
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            padding: 20px;
            border-top: 1px solid var(--light-gray);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        /* Form Elements dalam Modal */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
        }

        .form-label .required {
            color: var(--pink);
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--pink);
            box-shadow: 0 0 0 3px rgba(255, 107, 147, 0.1);
        }

        .quantity-selector {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .qty-btn {
            width: 40px;
            height: 40px;
            background: var(--light);
            border: 2px solid var(--border);
            border-radius: 5px;
            font-size: 18px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .qty-btn:hover {
            background: var(--pink);
            color: white;
            border-color: var(--pink);
        }

        .qty-input {
            width: 80px;
            text-align: center;
            font-weight: 600;
            border: 2px solid var(--border);
        }

        .qty-input::-webkit-inner-spin-button,
        .qty-input::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .price-summary {
            background: var(--light);
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .price-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .price-total {
            font-weight: 600;
            color: var(--pink);
            font-size: 16px;
            border-top: 1px solid var(--border);
            padding-top: 10px;
            margin-top: 10px;
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
                        <a href="shopping.php" class="menu-link active">
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
            <!-- Welcome Header -->
            <div class="welcome-header">
                <h1>Belanja Produk Roncelizz 💎</h1>
                <p>Temukan manik-manik eksklusif dan produk custom favoritmu. Pilih langsung atau request custom sesuai
                    keinginan!</p>
            </div>

            <!-- Error message -->
            <?php if (isset($_SESSION['error'])): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h3>Terjadi Kesalahan</h3>
                    <p><?php echo htmlspecialchars($_SESSION['error']); ?></p>
                    <a href="shopping.php" class="retry-btn">
                        <i class="fas fa-redo"></i> Coba Lagi
                    </a>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="order.php" class="action-btn">
                    <div class="action-icon order">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="action-text">
                        <h3>Pesan Produk</h3>
                        <p>Pesan produk langsung dari katalog kami</p>
                    </div>
                </a>

                <a href="request.php" class="action-btn">
                    <div class="action-icon request">
                        <i class="fas fa-paint-brush"></i>
                    </div>
                    <div class="action-text">
                        <h3>Request Custom</h3>
                        <p>Request produk custom sesuai keinginanmu</p>
                    </div>
                </a>

                <a href="order_history.php" class="action-btn">
                    <div class="action-icon history">
                        <i class="fas fa-history"></i>
                    </div>
                    <div class="action-text">
                        <h3>Riwayat Pesanan</h3>
                        <p>Lihat status dan riwayat pesananmu</p>
                    </div>
                </a>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $productCount; ?></div>
                    <div class="stat-label">Total Produk</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">Rp <?php echo number_format($totalSpending, 0, ',', '.'); ?></div>
                    <div class="stat-label">Total Pengeluaran</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $activeCarts; ?></div>
                    <div class="stat-label">Keranjang Aktif</div>
                </div>
            </div>

            <!-- Products Section -->
            <div class="shopping-section">
                <div class="section-header">
                    <div>
                        <h2 class="section-title">
                            <i class="fas fa-gem"></i> Katalog Produk
                            <?php if (!empty($products)): ?>
                                <span class="product-count"><?php echo count($products); ?> produk</span>
                            <?php endif; ?>
                        </h2>
                        <p class="section-subtitle">
                            Pilih produk favoritmu dari berbagai kategori
                        </p>
                    </div>

                    <div class="filter-container">
                        <form method="GET" action="" class="filter-form"
                            style="display: flex; gap: 15px; align-items: center;">
                            <select name="category" class="filter-select" onchange="this.form.submit()">
                                <option value="all" <?php echo empty($categoryFilter) || $categoryFilter == 'all' ? 'selected' : ''; ?>>Semua Kategori</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" <?php echo $categoryFilter == $category['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <div class="search-box">
                                <i class="fas fa-search search-icon"></i>
                                <input type="text" name="search" class="search-input" placeholder="Cari produk..."
                                    value="<?php echo htmlspecialchars($searchQuery); ?>">
                            </div>

                            <button type="submit" class="btn btn-secondary" style="padding: 10px 20px;">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                        </form>
                    </div>
                </div>

                <?php if (empty($products)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-box-open"></i>
                        </div>
                        <h3>Belum ada produk yang tersedia</h3>
                        <p class="empty-text">
                            <?php if (!empty($searchQuery)): ?>
                                Tidak ada produk yang sesuai dengan pencarian "<?php echo htmlspecialchars($searchQuery); ?>"
                            <?php elseif (!empty($categoryFilter) && $categoryFilter !== 'all'): ?>
                                Tidak ada produk dalam kategori ini
                            <?php else: ?>
                                Belum ada produk yang ditambahkan. Silakan tambahkan produk terlebih dahulu.
                            <?php endif; ?>
                        </p>
                        <?php if (!empty($searchQuery) || (!empty($categoryFilter) && $categoryFilter !== 'all')): ?>
                            <a href="shopping.php" class="btn btn-primary" style="margin-top: 15px;">
                                <i class="fas fa-times"></i> Hapus Filter
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="product-grid">
                        <?php foreach ($products as $product): ?>
                            <?php
                            $imagePath = getProductImage($product['image_url']);
                            $stockClass = 'stock-available';
                            $stockText = 'Stok: ' . $product['stock'];

                            if ($product['stock'] <= 0) {
                                $stockClass = 'stock-out';
                                $stockText = 'Stok Habis';
                            } elseif ($product['stock'] <= 10) {
                                $stockClass = 'stock-low';
                                $stockText = 'Stok: ' . $product['stock'] . ' (Terbatas)';
                            }
                            ?>
                            <div class="product-card">
                                <?php if ($product['product_type'] === 'LIMITED'): ?>
                                    <span class="product-badge badge-limited">LIMITED</span>
                                <?php else: ?>
                                    <span class="product-badge badge-regular">REGULAR</span>
                                <?php endif; ?>

                                <div class="product-image-container">
                                    <?php if ($imagePath): ?>
                                        <img src="<?php echo htmlspecialchars($imagePath); ?>"
                                            alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image"
                                            loading="lazy"
                                            onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'image-placeholder\'><i class=\'fas fa-gem\'></i><span>Gambar tidak tersedia</span></div>';">
                                    <?php else: ?>
                                        <div class="image-placeholder">
                                            <i class="fas fa-gem"></i>
                                            <span>Gambar produk tidak tersedia</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="product-content">
                                    <h3 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                                    <div class="product-category">
                                        <i class="fas fa-tag"></i>
                                        <?php echo htmlspecialchars($product['category_name'] ?? 'Tidak ada kategori'); ?>
                                    </div>
                                    <p class="product-description">
                                        <?php
                                        $desc = htmlspecialchars($product['description'] ?? 'Tidak ada deskripsi', ENT_QUOTES, 'UTF-8');
                                        echo strlen($desc) > 100 ? substr($desc, 0, 100) . '...' : $desc;
                                        ?>
                                    </p>

                                    <div class="product-meta">
                                        <span class="product-stock <?php echo $stockClass; ?>">
                                            <i class="fas fa-box"></i> <?php echo $stockText; ?>
                                        </span>
                                    </div>

                                    <div class="product-footer">
                                        <div class="product-price">Rp
                                            <?php echo number_format($product['price'], 0, ',', '.'); ?>
                                        </div>
                                        <div class="product-actions">
                                            <a href="order.php?product_id=<?php echo $product['id']; ?>"
                                                class="btn btn-primary <?php echo $product['stock'] <= 0 ? 'disabled' : ''; ?>"
                                                <?php echo $product['stock'] <= 0 ? 'style="pointer-events: none; opacity: 0.5;"' : ''; ?>>
                                                <i class="fas fa-shopping-cart"></i>
                                                <?php echo $product['stock'] <= 0 ? 'Stok Habis' : 'Pesan'; ?>
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

    <!-- Cart Notification -->
    <div id="cartNotification" class="cart-notification">
        <i class="fas fa-check-circle"></i>
        <span>Produk berhasil ditambahkan ke keranjang!</span>
    </div>

    <!-- Order Modal -->
    <div class="modal-overlay" id="orderModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-shopping-cart"></i>
                    <span id="modalProductName">Pesan Produk</span>
                </h3>
                <button type="button" class="modal-close" onclick="closeOrderModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="orderForm" method="POST" action="order_process.php">
                    <input type="hidden" id="productId" name="product_id" value="">
                    <input type="hidden" id="productPrice" name="product_price" value="">

                    <div class="form-group">
                        <label class="form-label">
                            Nama Produk
                        </label>
                        <input type="text" id="modalProductNameInput" class="form-control" readonly>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            Harga Satuan
                        </label>
                        <input type="text" id="modalProductPrice" class="form-control" readonly>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            Stok Tersedia
                        </label>
                        <input type="text" id="modalProductStock" class="form-control" readonly>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            Jumlah <span class="required">*</span>
                        </label>
                        <div class="quantity-selector">
                            <button type="button" class="qty-btn" onclick="changeQuantity(-1)">-</button>
                            <input type="number" id="quantity" name="quantity" class="form-control qty-input" value="1"
                                min="1" max="1">
                            <button type="button" class="qty-btn" onclick="changeQuantity(1)">+</button>
                        </div>
                        <small style="color: var(--gray); display: block; margin-top: 5px;">
                            Maksimal: <span id="maxQuantity">1</span> pcs
                        </small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            Catatan (Opsional)
                        </label>
                        <textarea name="notes" class="form-control" rows="3"
                            placeholder="Tambahkan catatan untuk pesanan ini..."></textarea>
                    </div>

                    <div class="price-summary">
                        <div class="price-item">
                            <span>Harga Satuan:</span>
                            <span id="summaryUnitPrice">Rp 0</span>
                        </div>
                        <div class="price-item">
                            <span>Jumlah:</span>
                            <span id="summaryQuantity">1 pcs</span>
                        </div>
                        <div class="price-item price-total">
                            <span>Total Harga:</span>
                            <span id="summaryTotalPrice">Rp 0</span>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeOrderModal()">
                    <i class="fas fa-times"></i> Batal
                </button>
                <button type="button" class="btn btn-primary" onclick="confirmOrder()">
                    <i class="fas fa-check"></i> Konfirmasi Pesanan
                </button>
            </div>
        </div>
    </div>

    <script>
        // Interaksi untuk shopping page
        document.addEventListener('DOMContentLoaded', function () {
            const menuLinks = document.querySelectorAll('.menu-link');
            const productCards = document.querySelectorAll('.product-card');
            const actionBtns = document.querySelectorAll('.action-btn');

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

            // Action button hover effect
            actionBtns.forEach(btn => {
                btn.addEventListener('mouseenter', function () {
                    this.style.transform = 'translateY(-5px)';
                });

                btn.addEventListener('mouseleave', function () {
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

            // Handle search form submission
            const searchInput = document.querySelector('.search-input');
            const searchForm = document.querySelector('.filter-form');

            if (searchInput) {
                searchInput.addEventListener('keypress', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        searchForm.submit();
                    }
                });
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

        // Close modal when clicking outside
        document.addEventListener('click', function (e) {
            const modal = document.getElementById('orderModal');
            if (e.target === modal) {
                closeOrderModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeOrderModal();
            }
        });
    </script>
</body>

</html>