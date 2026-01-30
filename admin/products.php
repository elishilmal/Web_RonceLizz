<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

Auth::requireAdmin();

// Get category filter from URL
$filter_category = $_GET['category'] ?? 0;
if (isset($_GET['category_id'])) {
    $filter_category = $_GET['category_id'];
}

// Get category info if filter is applied
$currentCategory = null;
if ($filter_category > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$filter_category]);
        $currentCategory = $stmt->fetch();
    } catch (PDOException $e) {
        $message = "Gagal memuat kategori: " . $e->getMessage();
        $messageType = 'error';
    }
}

$message = '';
$messageType = '';
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];

    if ($action == 'add' || $action == 'edit') {
        $name = sanitize($_POST['name']);
        $category_id = intval($_POST['category_id']);
        $description = sanitize($_POST['description']);
        $type = sanitize($_POST['type']);

        // Handle price
        $price_input = $_POST['price'];
        $price = floatval(str_replace('.', '', $price_input));

        $stock = intval($_POST['stock']);
        $material = sanitize($_POST['material']);
        $color = sanitize($_POST['color']);
        $size = sanitize($_POST['size']);
        $weight = sanitize($_POST['weight']);
        $status = sanitize($_POST['status']);
        $image_url = sanitize($_POST['image_url']);

        // Handle image upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../assets/images/products/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $fileName = uniqid() . '_' . basename($_FILES['image']['name']);
            $uploadFile = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadFile)) {
                $image_url = 'assets/images/products/' . $fileName;
            }
        }

        // Validate price
        if ($price <= 0) {
            $message = "Harga harus lebih dari 0!";
            $messageType = 'error';
        } elseif ($price < 100) {
            $message = "Harga minimal Rp 100!";
            $messageType = 'error';
        } else {
            if ($action == 'add') {
                try {
                    $stmt = $pdo->prepare("INSERT INTO products (name, category_id, description, type, price, stock, image_url, material, color, size, weight, status) 
                                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $category_id, $description, $type, $price, $stock, $image_url, $material, $color, $size, $weight, $status]);

                    $message = "Produk berhasil ditambahkan!";
                    $messageType = 'success';

                    $redirect_url = 'products.php?';
                    $params = [];

                    if ($filter_category > 0) {
                        $params[] = 'category=' . $filter_category;
                    }

                    $params[] = 'message=' . urlencode($message);
                    $params[] = 'messageType=' . $messageType;

                    $redirect_url .= implode('&', $params);

                    header("Location: " . $redirect_url);
                    exit();

                } catch (PDOException $e) {
                    $message = "Gagal menambahkan produk: " . $e->getMessage();
                    $messageType = 'error';
                }
            } else {
                $id = intval($_POST['id']);

                // Jika tidak ada file gambar baru, pertahankan image_url yang lama
                if (empty($image_url) && isset($_POST['current_image_url'])) {
                    $image_url = $_POST['current_image_url'];
                }

                try {
                    $stmt = $pdo->prepare("UPDATE products SET 
                                          name = ?, category_id = ?, description = ?, type = ?, price = ?, 
                                          stock = ?, image_url = ?, material = ?, color = ?, size = ?, 
                                          weight = ?, status = ?
                                          WHERE id = ? AND deleted_at IS NULL");
                    $stmt->execute([
                        $name,
                        $category_id,
                        $description,
                        $type,
                        $price,
                        $stock,
                        $image_url,
                        $material,
                        $color,
                        $size,
                        $weight,
                        $status,
                        $id
                    ]);

                    $message = "Produk berhasil diperbarui!";
                    $messageType = 'success';

                    $redirect_url = 'products.php?';
                    $params = [];

                    if ($filter_category > 0) {
                        $params[] = 'category=' . $filter_category;
                    }

                    $params[] = 'message=' . urlencode($message);
                    $params[] = 'messageType=' . $messageType;

                    $redirect_url .= implode('&', $params);

                    header("Location: " . $redirect_url);
                    exit();

                } catch (PDOException $e) {
                    $message = "Gagal memperbarui produk: " . $e->getMessage();
                    $messageType = 'error';
                }
            }
        }
    } elseif ($action == 'delete') {
        $id = intval($_POST['id']);

        try {
            // Update deleted_at timestamp
            $stmt = $pdo->prepare("UPDATE products SET deleted_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);

            $message = "Produk berhasil dihapus!";
            $messageType = 'success';

            $redirect_url = 'products.php?';
            $params = [];

            if ($filter_category > 0) {
                $params[] = 'category=' . $filter_category;
            }

            $params[] = 'message=' . urlencode($message);
            $params[] = 'messageType=' . $messageType;

            $redirect_url .= implode('&', $params);

            header("Location: " . $redirect_url);
            exit();

        } catch (PDOException $e) {
            $message = "Gagal menghapus produk: " . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Get message from URL if redirected
if (isset($_GET['message']) && isset($_GET['messageType'])) {
    $message = urldecode($_GET['message']);
    $messageType = $_GET['messageType'];
}

// Get categories for dropdown
$categories = [];
try {
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $message = "Gagal memuat kategori: " . $e->getMessage();
    $messageType = 'error';
}

// Get product for editing
$product = null;
if ($action == 'edit' && $id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$id]);
        $product = $stmt->fetch();

        if (!$product) {
            $message = "Produk tidak ditemukan atau telah dihapus!";
            $messageType = 'error';
            $action = 'list';
        }
    } catch (PDOException $e) {
        $message = "Gagal memuat produk: " . $e->getMessage();
        $messageType = 'error';
        $action = 'list';
    }
}

$total_products = 0;
try {

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM products WHERE deleted_at IS NULL");
    $result = $stmt->fetch();
    $total_products = $result['total'];
} catch (PDOException $e) {

}

// Get products for listing
$products = [];
if ($action == 'list') {
    try {
        // Filter by category if specified
        if ($filter_category > 0) {
            // Filter by specific category
            $sql = "SELECT p.*, c.name as category_name 
                    FROM products p 
                    LEFT JOIN categories c ON p.category_id = c.id 
                    WHERE p.category_id = ? AND p.deleted_at IS NULL
                    ORDER BY p.created_at DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$filter_category]);
        } else {
            // Get all products 
            $sql = "SELECT p.*, c.name as category_name 
                    FROM products p 
                    LEFT JOIN categories c ON p.category_id = c.id 
                    WHERE p.deleted_at IS NULL
                    ORDER BY p.created_at DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
        }

        $products = $stmt->fetchAll();
    } catch (PDOException $e) {
        $message = "Gagal memuat produk: " . $e->getMessage();
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php
        if ($currentCategory && $action == 'list') {
            echo 'Produk Kategori: ' . htmlspecialchars($currentCategory['name']) . ' - Roncelizz';
        } else {
            if ($action == 'add')
                echo 'Tambah Produk - Roncelizz';
            elseif ($action == 'edit')
                echo 'Edit Produk - Roncelizz';
            else
                echo 'Kelola Produk - Roncelizz';
        }
        ?>
    </title>
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
            background-color: var(--gray) !important;
            border-color: var(--gray) !important;
        }

        /* Product Image Styles */
        .product-image-preview {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 10px;
            border: 2px solid var(--gray-medium);
            margin-bottom: 15px;
        }

        .image-upload-container {
            display: flex;
            gap: 20px;
            align-items: flex-start;
            margin-bottom: 20px;
        }

        .image-preview {
            flex-shrink: 0;
        }

        .image-upload-controls {
            flex: 1;
        }

        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            margin-bottom: 10px;
        }

        .file-input-wrapper input[type=file] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
        }

        .file-input-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: var(--purple-light);
            color: var(--purple);
            border: 2px dashed var(--purple);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }

        .file-input-btn:hover {
            background: var(--purple);
            color: white;
        }

        .file-name {
            margin-left: 10px;
            color: var(--gray);
            font-size: 14px;
        }

        /* Product Grid View */
        .view-toggle {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .view-toggle button {
            padding: 8px 16px;
            border: 1px solid var(--gray-medium);
            background: white;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .view-toggle button.active {
            background: var(--purple);
            color: white;
            border-color: var(--purple);
        }

        .view-toggle button:hover:not(.active) {
            background: var(--gray-light);
        }

        /* Grid View */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .product-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: var(--shadow-soft);
            border: 2px solid transparent;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-medium);
            border-color: var(--purple);
        }

        .product-image {
            width: 100%;
            height: 180px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 15px;
        }

        .product-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-available {
            background: var(--mint-light);
            color: var(--mint-dark);
        }

        .badge-out_of_stock {
            background: var(--peach-light);
            color: var(--peach-dark);
        }

        .badge-discontinued {
            background: var(--pink-light);
            color: var(--pink-dark);
        }

        .product-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 8px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-category {
            color: var(--purple);
            font-size: 14px;
            margin-bottom: 10px;
        }

        .product-price {
            font-size: 20px;
            font-weight: 700;
            color: var(--pink);
            margin-bottom: 10px;
        }

        .product-meta {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            font-size: 13px;
            color: var(--gray);
        }

        .product-meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .product-description {
            color: var(--gray);
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-actions {
            display: flex;
            gap: 8px;
            margin-top: 15px;
            border-top: 1px solid var(--gray-light);
            padding-top: 15px;
        }

        .product-action-btn {
            flex: 1;
            padding: 8px 12px;
            border-radius: 8px;
            text-align: center;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-edit-grid {
            background: var(--mint-light);
            color: var(--mint-dark);
            border: 1px solid var(--mint-medium);
        }

        .btn-delete-grid {
            background: var(--pink-light);
            color: var(--pink-dark);
            border: 1px solid var(--pink-medium);
        }

        .btn-view-grid {
            background: var(--purple-light);
            color: var(--purple-dark);
            border: 1px solid var(--purple-medium);
        }

        .product-action-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-soft);
        }
        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-available {
            background: var(--mint-light);
            color: var(--mint-dark);
        }

        .status-out_of_stock {
            background: var(--peach-light);
            color: var(--peach-dark);
        }

        .status-discontinued {
            background: var(--pink-light);
            color: var(--pink-dark);
        }

        /* Form Enhancements */
        .form-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: var(--shadow-soft);
        }

        .form-section-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--purple-light);
        }

        .dimensions-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        /* Category Filter Banner */
        .category-filter-banner {
            background: linear-gradient(135deg, var(--purple-light), var(--pink-light));
            padding: 15px 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .dimensions-row {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .image-upload-container {
                flex-direction: column;
            }
            
            .products-grid {
                grid-template-columns: 1fr;
            }
            
            .category-filter-banner {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <div>
                <h1 class="page-title">
                    <?php
                    if ($action == 'add')
                        echo 'Tambah Produk';
                    elseif ($action == 'edit')
                        echo 'Edit Produk';
                    elseif ($currentCategory)
                        echo 'Produk Kategori: ' . htmlspecialchars($currentCategory['name']);
                    else
                        echo 'Kelola Produk';
                    ?>
                </h1>
                <p class="page-subtitle">
                    <?php
                    if ($action == 'add')
                        echo 'Tambahkan produk baru ke katalog Roncelizz';
                    elseif ($action == 'edit')
                        echo 'Edit informasi produk';
                    elseif ($currentCategory)
                        echo 'Menampilkan produk dalam kategori: ' . htmlspecialchars($currentCategory['name']);
                    else
                        echo 'Kelola semua produk di toko Roncelizz';
                    ?>
                </p>
            </div>
            <?php if ($action == 'list'): ?>
                    <div style="display: flex; gap: 15px; align-items: center;">
                        <?php if ($filter_category > 0): ?>
                                <a href="products.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Hapus Filter
                                </a>
                        <?php endif; ?>
                        <a href="?action=add<?php echo $filter_category ? '&category=' . $filter_category : ''; ?>" class="btn">
                            <i class="fas fa-plus"></i> Tambah Produk
                        </a>
                    </div>
            <?php else: ?>
                    <a href="products.php<?php echo $filter_category ? '?category=' . $filter_category : ''; ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
            <?php endif; ?>
        </div>

        <?php if ($currentCategory && $action == 'list'): ?>
                <div class="category-filter-banner">
                    <div>
                        <h3 style="font-size: 18px; color: var(--purple-dark); margin-bottom: 5px;">
                            <i class="fas fa-filter" style="margin-right: 10px;"></i>
                            Filter Aktif: <strong><?php echo htmlspecialchars($currentCategory['name']); ?></strong>
                        </h3>
                        <p style="color: var(--gray); font-size: 14px;">
                            Menampilkan <?php echo count($products); ?> produk dalam kategori ini
                        </p>
                    </div>
                    <a href="products.php" class="btn btn-secondary" style="padding: 8px 16px; font-size: 14px;">
                        <i class="fas fa-times"></i> Hapus Filter
                    </a>
                </div>
        <?php endif; ?>

        <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>" id="messageAlert">
                    <?php if ($messageType == 'success'): ?>
                            <i class="fas fa-check-circle"></i>
                    <?php else: ?>
                            <i class="fas fa-exclamation-circle"></i>
                    <?php endif; ?>
                    <?php echo htmlspecialchars($message); ?>
                
                    <?php if ($messageType == 'success' && $action == 'add'): ?>
                            <div style="margin-top: 10px;">
                                <button type="button" onclick="resetForm()" class="btn" style="padding: 5px 15px; font-size: 14px;">
                                    <i class="fas fa-plus"></i> Tambah Produk Lagi
                                </button>
                                <a href="products.php" class="btn btn-secondary" style="padding: 5px 15px; font-size: 14px;">
                                    <i class="fas fa-list"></i> Lihat Daftar Produk
                                </a>
                            </div>
                    <?php endif; ?>
                </div>
        <?php endif; ?>

        <?php if ($action == 'add' || $action == 'edit'): ?>
                <!-- Form untuk tambah/edit produk -->
                <div class="form-container">
                    <form method="POST" action="" enctype="multipart/form-data" novalidate id="productForm">
                        <input type="hidden" name="action" value="<?php echo $action; ?>">
                        <input type="hidden" name="id" value="<?php echo $product['id'] ?? ''; ?>">
                        <input type="hidden" name="current_image_url" value="<?php echo $product['image_url'] ?? ''; ?>">

                        <div class="form-section">
                            <h3 class="form-section-title">Informasi Dasar</h3>
                        
                            <div class="image-upload-container">
                                <div class="image-preview">
                                    <?php if (!empty($product['image_url'])): ?>
                                            <img src="../<?php echo htmlspecialchars($product['image_url']); ?>" 
                                                 class="product-image-preview" 
                                                 id="imagePreview"
                                                 alt="Preview">
                                    <?php else: ?>
                                            <img src="../assets/images/default-product.jpg" 
                                                 class="product-image-preview" 
                                                 id="imagePreview"
                                                 alt="Preview">
                                    <?php endif; ?>
                                </div>
                                <div class="image-upload-controls">
                                    <div class="file-input-wrapper">
                                        <button type="button" class="file-input-btn" id="imageUploadBtn">
                                            <i class="fas fa-image"></i> Pilih Gambar
                                        </button>
                                        <input type="file" id="imageInput" name="image" accept="image/*" onchange="previewImage(event)">
                                    </div>
                                    <div class="file-name" id="fileName"><?php echo basename($product['image_url'] ?? ''); ?></div>
                                    <small style="color: var(--gray);">Maksimal 2MB. Format: JPG, PNG, GIF</small>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="name">Nama Produk *</label>
                                <input type="text" class="form-control" id="name" name="name"
                                    value="<?php echo htmlspecialchars($product['name'] ?? ''); ?>" required
                                    placeholder="Contoh: Kalung Manik Kristal">
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="category_id">Kategori *</label>
                                    <select class="form-control" id="category_id" name="category_id" required>
                                        <option value="">Pilih Kategori</option>
                                        <?php foreach ($categories as $category): ?>
                                                <option value="<?php echo $category['id']; ?>" 
                                                    <?php echo (isset($product['category_id']) && $product['category_id'] == $category['id']) ? 'selected' : ''; ?>
                                                    <?php echo (!$product && $filter_category == $category['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($category['name']); ?>
                                                </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="type">Jenis Produk *</label>
                                    <select class="form-control" id="type" name="type" required>
                                        <option value="regular" <?php echo (isset($product['type']) && $product['type'] == 'regular') ? 'selected' : ''; ?>>Regular</option>
                                        <option value="limited" <?php echo (isset($product['type']) && $product['type'] == 'limited') ? 'selected' : ''; ?>>Limited Edition</option>
                                        <option value="custom" <?php echo (isset($product['type']) && $product['type'] == 'custom') ? 'selected' : ''; ?>>Custom Request</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="price">Harga (Rp) *</label>
                                    <div style="position: relative;">
                                        <span style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--gray);">Rp</span>
                                        <input type="number" class="form-control" id="price" name="price"
                                            value="<?php
                                            if (isset($product['price'])) {
                                                echo number_format($product['price'], 0, ',', '.');
                                            } elseif (isset($_POST['price'])) {
                                                echo htmlspecialchars($_POST['price']);
                                            } else {
                                                echo '';
                                            }
                                            ?>" 
                                            min="1" required
                                            placeholder="0"
                                            style="padding-left: 40px;">
                                    </div>
                                    <small style="color: var(--gray); display: block; margin-top: 5px;">Contoh: 25000, 100000, 1500000</small>
                                </div>

                                <div class="form-group">
                                    <label for="stock">Stok *</label>
                                    <input type="number" class="form-control" id="stock" name="stock"
                                        value="<?php echo $product['stock'] ?? '0'; ?>" min="0" required
                                        placeholder="0">
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h3 class="form-section-title">Detail Produk</h3>
                        
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="material">Bahan</label>
                                    <input type="text" class="form-control" id="material" name="material"
                                        value="<?php echo htmlspecialchars($product['material'] ?? ''); ?>"
                                        placeholder="Contoh: Kristal Swarovski, Kayu Jati, Logam Sterling">
                                </div>

                                <div class="form-group">
                                    <label for="color">Warna</label>
                                    <input type="text" class="form-control" id="color" name="color"
                                        value="<?php echo htmlspecialchars($product['color'] ?? ''); ?>" 
                                        placeholder="Contoh: Rose Gold, Silver, Multicolor">
                                </div>
                            </div>

                            <div class="dimensions-row">
                                <div class="form-group">
                                    <label for="size">Ukuran</label>
                                    <input type="text" class="form-control" id="size" name="size"
                                        value="<?php echo htmlspecialchars($product['size'] ?? ''); ?>"
                                        placeholder="Contoh: 40x20mm, S/M/L">
                                </div>

                                <div class="form-group">
                                    <label for="weight">Berat (gram)</label>
                                    <input type="number" class="form-control" id="weight" name="weight"
                                        value="<?php echo $product['weight'] ?? ''; ?>" min="0" step="0.1"
                                        placeholder="0.0">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="status">Status *</label>
                                <select class="form-control" id="status" name="status" required>
                                    <option value="available" <?php echo (isset($product['status']) && $product['status'] == 'available') ? 'selected' : ''; ?>>Tersedia</option>
                                    <option value="out_of_stock" <?php echo (isset($product['status']) && $product['status'] == 'out_of_stock') ? 'selected' : ''; ?>>Habis</option>
                                    <option value="discontinued" <?php echo (isset($product['status']) && $product['status'] == 'discontinued') ? 'selected' : ''; ?>>Tidak Aktif</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="image_url">URL Gambar (Alternatif)</label>
                                <input type="url" class="form-control" id="image_url" name="image_url"
                                    value="<?php echo htmlspecialchars($product['image_url'] ?? ''); ?>"
                                    placeholder="https://example.com/image.jpg">
                                <small style="color: var(--gray);">Masukkan URL jika tidak mengupload file</small>
                            </div>

                            <div class="form-group">
                                <label for="description">Deskripsi *</label>
                                <textarea class="form-control" id="description" name="description" rows="5"
                                    required placeholder="Deskripsikan produk secara detail..."><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <div style="display: flex; gap: 15px; margin-top: 30px;">
                            <button type="submit" class="btn btn-success" style="flex: 1;">
                                <i class="fas fa-save"></i> Simpan Produk
                            </button>
                            <a href="products.php<?php echo $filter_category ? '?category=' . $filter_category : ''; ?>" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Batal
                            </a>
                        </div>
                    </form>
                </div>

        <?php else: ?>
                <!-- Daftar Produk -->
                <div class="search-box">
                    <div style="position: relative;">
                        <!-- <i class="fas fa-search search-icon"></i> -->
                        <input type="text" class="search-input" id="searchInput" placeholder="Cari produk">
                    </div>
                    <div style="margin-top: 15px; display: flex; gap: 10px; flex-wrap: wrap;">
                        <select id="categoryFilter" class="form-control" style="max-width: 200px;">
                            <option value="">Semua Kategori</option>
                            <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>"
                                        <?php echo ($filter_category == $category['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                            <?php endforeach; ?>
                        </select>
                        <select id="statusFilter" class="form-control" style="max-width: 150px;">
                            <option value="">Semua Status</option>
                            <option value="available" <?php echo ($_GET['status'] ?? '') == 'available' ? 'selected' : ''; ?>>Tersedia</option>
                            <option value="out_of_stock" <?php echo ($_GET['status'] ?? '') == 'out_of_stock' ? 'selected' : ''; ?>>Habis</option>
                            <option value="discontinued" <?php echo ($_GET['status'] ?? '') == 'discontinued' ? 'selected' : ''; ?>>Tidak Aktif</option>
                        </select>
                        <select id="typeFilter" class="form-control" style="max-width: 150px;">
                            <option value="">Semua Jenis</option>
                            <option value="regular" <?php echo ($_GET['type'] ?? '') == 'regular' ? 'selected' : ''; ?>>Regular</option>
                            <option value="limited" <?php echo ($_GET['type'] ?? '') == 'limited' ? 'selected' : ''; ?>>Limited</option>
                            <option value="custom" <?php echo ($_GET['type'] ?? '') == 'custom' ? 'selected' : ''; ?>>Custom</option>
                        </select>
                    </div>
                </div>

                <!-- Grid View -->
                <div id="gridView" class="products-grid">
                    <?php if (empty($products)): ?>
                            <div style="grid-column: 1 / -1; text-align: center; padding: 50px; background: white; border-radius: 15px;">
                                <div style="font-size: 80px; color: var(--pink-light); margin-bottom: 20px;">📦</div>
                                <h3 style="color: var(--dark); margin-bottom: 15px;">
                                    <?php echo $currentCategory ? 'Belum Ada Produk di Kategori Ini' : 'Belum Ada Produk'; ?>
                                </h3>
                                <p style="color: var(--gray); margin-bottom: 25px;">
                                    <?php echo $currentCategory
                                        ? 'Tambahkan produk pertama dalam kategori "' . htmlspecialchars($currentCategory['name']) . '"'
                                        : 'Tambahkan produk pertama untuk memulai penjualan'; ?>
                                </p>
                                <a href="?action=add<?php echo $filter_category ? '&category=' . $filter_category : ''; ?>" class="btn">
                                    <i class="fas fa-plus"></i> Tambah Produk
                                </a>
                            </div>
                    <?php else: ?>
                            <?php foreach ($products as $product): ?>
                                    <?php
                                    $statusClass = 'badge-' . $product['status'];
                                    $statusText = $product['status'] == 'available' ? 'Tersedia' :
                                        ($product['status'] == 'out_of_stock' ? 'Habis' : 'Tidak Aktif');
                                    ?>
                                    <div class="product-card" data-category="<?php echo htmlspecialchars($product['category_name']); ?>"
                                         data-status="<?php echo $product['status']; ?>"
                                         data-type="<?php echo $product['type']; ?>">
                                        <div class="product-badge <?php echo $statusClass; ?>">
                                            <?php echo $statusText; ?>
                                        </div>
                            
                                        <?php if (!empty($product['image_url'])): ?>
                                                <img src="../<?php echo htmlspecialchars($product['image_url']); ?>" 
                                                     class="product-image" 
                                                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                     onerror="this.src='../assets/images/default-product.jpg'">
                                        <?php else: ?>
                                                <img src="../assets/images/default-product.jpg" 
                                                     class="product-image" 
                                                     alt="No Image">
                                        <?php endif; ?>
                            
                                        <h3 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                                        <div class="product-category">
                                            <i class="fas fa-tag"></i> <?php echo htmlspecialchars($product['category_name'] ?? 'Tidak ada kategori'); ?>
                                        </div>
                                        <div class="product-price">
                                            Rp <?php echo number_format($product['price'], 0, ',', '.'); ?>
                                        </div>
                            
                                        <div class="product-meta">
                                            <div class="product-meta-item">
                                                <i class="fas fa-box"></i> <?php echo $product['stock']; ?> pcs
                                            </div>
                                            <div class="product-meta-item">
                                                <i class="fas fa-weight"></i> <?php echo $product['weight'] ? $product['weight'] . 'g' : '-'; ?>
                                            </div>
                                            <div class="product-meta-item">
                                                <i class="fas fa-palette"></i> <?php echo $product['color'] ?: '-'; ?>
                                            </div>
                                        </div>
                            
                                        <div class="product-description">
                                            <?php echo htmlspecialchars(substr($product['description'], 0, 100)); ?>...
                                        </div>
                            
                                        <div class="product-actions">
                                            <a href="?action=edit&id=<?php echo $product['id']; ?><?php echo $filter_category ? '&category=' . $filter_category : ''; ?>" 
                                               class="product-action-btn btn-edit-grid">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <form method="POST" action="" onsubmit="return confirmDelete(event, <?php echo $total_products; ?>)" style="flex: 1;">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
                                                <button type="submit" class="product-action-btn btn-delete-grid">
                                                    <i class="fas fa-trash"></i> Hapus
                                                </button>
                                            </form>
                                            <a href="product_detail.php?id=<?php echo $product['id']; ?>" class="product-action-btn btn-view-grid">
                                                <i class="fas fa-info-circle"></i> Detail
                                            </a>
                                        </div>
                                    </div>
                            <?php endforeach; ?>
                    <?php endif; ?>
                </div>
        <?php endif; ?>
    </div>

    <script>
        // Reset form after successful submission
        <?php if ($messageType == 'success' && $action == 'add'): ?>
            document.addEventListener('DOMContentLoaded', function() {
                // Reset form fields setelah sukses
                const form = document.getElementById('productForm');
                if (form) {
                    form.reset();
                
                    // Reset image preview
                    const imagePreview = document.getElementById('imagePreview');
                    if (imagePreview) {
                        imagePreview.src = '../assets/images/default-product.jpg';
                    }
                
                    // Reset file name
                    const fileName = document.getElementById('fileName');
                    if (fileName) {
                        fileName.textContent = '';
                    }
                
                    // Reset category select to filter category
                    const categorySelect = document.getElementById('category_id');
                    const filterCategory = "<?php echo $filter_category; ?>";
                    if (categorySelect && filterCategory) {
                        categorySelect.value = filterCategory;
                    }
                
                    // Focus on name field
                    const nameField = document.getElementById('name');
                    if (nameField) {
                        nameField.focus();
                    }
                }
            });
        <?php endif; ?>

        // Function to reset form manually
        function resetForm() {
            const form = document.getElementById('productForm');
            if (form) {
                form.reset();
                
                // Reset image preview
                const imagePreview = document.getElementById('imagePreview');
                if (imagePreview) {
                    imagePreview.src = '../assets/images/default-product.jpg';
                }
                
                // Reset file name
                const fileName = document.getElementById('fileName');
                if (fileName) {
                    fileName.textContent = '';
                }
                
                // Reset category select to filter category
                const categorySelect = document.getElementById('category_id');
                const filterCategory = "<?php echo $filter_category; ?>";
                if (categorySelect && filterCategory) {
                    categorySelect.value = filterCategory;
                }
                
                // Focus on name field
                const nameField = document.getElementById('name');
                if (nameField) {
                    nameField.focus();
                }
                
                // Hide success message
                const messageAlert = document.getElementById('messageAlert');
                if (messageAlert) {
                    messageAlert.style.display = 'none';
                }
            }
        }

        // View Toggle
        const viewToggle = document.getElementById('viewToggle');
        const gridView = document.getElementById('gridView');
        
        if (viewToggle) {
            viewToggle.addEventListener('click', function(e) {
                if (e.target.tagName === 'BUTTON') {
                    const view = e.target.dataset.view;

                    viewToggle.querySelectorAll('button').forEach(btn => btn.classList.remove('active'));
                    e.target.classList.add('active');

                    if (view === 'grid') {
                        gridView.style.display = 'grid';
                    }
                }
            });
        }

        // Search and Filter
        const searchInput = document.getElementById('searchInput');
        const categoryFilter = document.getElementById('categoryFilter');
        const statusFilter = document.getElementById('statusFilter');
        const typeFilter = document.getElementById('typeFilter');

        function filterProducts() {
            const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
            const selectedCategory = categoryFilter ? categoryFilter.value : '';
            const selectedStatus = statusFilter ? statusFilter.value : '';
            const selectedType = typeFilter ? typeFilter.value : '';

            // Jika memilih kategori dari filter dropdown, redirect ke URL
            if (categoryFilter && selectedCategory) {
                // Cek apakah sudah di halaman kategori yang sama
                const currentCategoryId = "<?php echo $filter_category; ?>";
                if (selectedCategory !== currentCategoryId) {
                    window.location.href = 'products.php?category=' + selectedCategory;
                    return;
                }
            }

            // Filter lokal untuk status dan type
            const gridItems = document.querySelectorAll('#gridView .product-card');
            gridItems.forEach(item => {
                const name = item.querySelector('.product-title').textContent.toLowerCase();
                const category = item.dataset.category.toLowerCase();
                const status = item.dataset.status;
                const type = item.dataset.type;
                
                const matchesSearch = !searchTerm || name.includes(searchTerm);
                const matchesStatus = !selectedStatus || status === selectedStatus;
                const matchesType = !selectedType || type === selectedType;
                
                item.style.display = (matchesSearch && matchesStatus && matchesType) ? '' : 'none';
            });
        }

        // Handle filter changes
        if (searchInput) searchInput.addEventListener('keyup', filterProducts);
        if (statusFilter) statusFilter.addEventListener('change', filterProducts);
        if (typeFilter) typeFilter.addEventListener('change', filterProducts);

        // Handle category filter change
        if (categoryFilter) {
            categoryFilter.addEventListener('change', function() {
                const selectedCategory = this.value;
                if (selectedCategory) {
                    window.location.href = 'products.php?category=' + selectedCategory;
                } else {
                    window.location.href = 'products.php';
                }
            });
        }

        // Image Preview
        function previewImage(event) {
            const input = event.target;
            const preview = document.getElementById('imagePreview');
            const fileName = document.getElementById('fileName');
            
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                };
                
                reader.readAsDataURL(file);
                fileName.textContent = file.name;
            }
        }

        // Price formatting
        const priceInput = document.getElementById('price');
        if (priceInput) {
            // Format awal jika ada nilai
            if (priceInput.value) {
                let value = priceInput.value.replace(/\./g, '');
                if (value) {
                    priceInput.value = parseInt(value).toLocaleString('id-ID');
                }
            }
            
            // Format saat kehilangan fokus
            priceInput.addEventListener('blur', function() {
                let value = parseFloat(this.value.replace(/\./g, ''));
                if (!isNaN(value) && value > 0) {
                    this.value = value.toLocaleString('id-ID');
                }
            });
            
            // Hapus formatting saat fokus untuk memudahkan editing
            priceInput.addEventListener('focus', function() {
                let value = this.value.replace(/\./g, '');
                this.value = value;
            });
        }

        // Delete confirmation
        function confirmDelete(event, totalProducts) {
            event.preventDefault();
            const form = event.target.closest('form');
            
            Swal.fire({
                title: 'Konfirmasi Hapus',
                html: `
                    <div style="text-align: left;">
                        <p>Apakah Anda yakin ingin menghapus produk ini?</p>
                        <div style="background: #d1ecf1; border-left: 4px solid #0c5460; padding: 10px; margin: 10px 0; border-radius: 4px; color: #0c5460;">
                            <strong><i class="fas fa-info-circle"></i> INFORMASI:</strong>
                            <ul style="margin: 5px 0 0 15px;">
                                <li>Produk akan diarsipkan (soft delete)</li>
                                <li>Data order yang terkait tetap aman</li>
                                <li>Produk dapat dipulihkan jika diperlukan</li>
                                <li>Tidak akan muncul di katalog publik</li>
                            </ul>
                        </div>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#ff6b93',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Menghapus...',
                        text: 'Mohon tunggu sebentar',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    form.submit();
                }
            });
            
            return false;
        }

        // Auto refresh every 60 seconds
        setInterval(function() {
            if (document.visibilityState === 'visible') {
                const currentView = viewToggle ? viewToggle.querySelector('.active').dataset.view : 'grid';
                console.log('Auto-refreshing product data...');
            }
        }, 60000);

        // Toggle mobile menu
        document.getElementById('menuToggle')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });

        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                const price = document.getElementById('price');
                const stock = document.getElementById('stock');
                const weight = document.getElementById('weight');

                if (price) {
                    const priceValue = price.value.replace(/\./g, '');
                    if (!priceValue || parseFloat(priceValue) <= 0) {
                        e.preventDefault();
                        Swal.fire('Error', 'Harga harus lebih dari 0', 'error');
                        price.focus();
                        return false;
                    }
                }

                if (stock && parseInt(stock.value) < 0) {
                    e.preventDefault();
                    Swal.fire('Error', 'Stok tidak boleh negatif', 'error');
                    return false;
                }

                if (weight && parseFloat(weight.value) < 0) {
                    e.preventDefault();
                    Swal.fire('Error', 'Berat tidak boleh negatif', 'error');
                    return false;
                }

                return true;
            });
        });
    </script>
</body>
</html>