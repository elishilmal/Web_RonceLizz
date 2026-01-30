<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

Auth::requireAdmin();

// Get order ID from URL
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($product_id == 0) {
    header("Location: products.php");
    exit();
}

// Get product details with category info
try {
    $stmt = $pdo->prepare("SELECT p.*, c.name as category_name, c.icon as category_icon
                          FROM products p 
                          LEFT JOIN categories c ON p.category_id = c.id 
                          WHERE p.id = ? AND p.deleted_at IS NULL");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

if (!$product) {
    header("Location: products.php");
    exit();
}

// Get order history for this product 
try {
    $stmt = $pdo->prepare("SELECT oi.*, o.order_date, o.status as order_status, 
                          u.name as customer_name, u.email as customer_email
                          FROM order_items oi
                          JOIN orders o ON oi.order_id = o.id
                          JOIN users u ON o.user_id = u.id
                          JOIN products p ON oi.product_id = p.id AND p.deleted_at IS NULL
                          WHERE oi.product_id = ?
                          ORDER BY o.order_date DESC
                          LIMIT 10");
    $stmt->execute([$product_id]);
    $orderHistory = $stmt->fetchAll();
} catch (PDOException $e) {
    $orderHistory = [];
}

// Handle status change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $new_status = sanitize($_POST['status']);
    $stock_change = intval($_POST['stock_change']);

    try {
        // Update product status 
        $stmt = $pdo->prepare("UPDATE products SET status = ? WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$new_status, $product_id]);

        // Update stock if needed
        if ($stock_change != 0) {
            $new_stock = $product['stock'] + $stock_change;
            if ($new_stock < 0)
                $new_stock = 0;

            $stmt = $pdo->prepare("UPDATE products SET stock = ? WHERE id = ? AND deleted_at IS NULL");
            $stmt->execute([$new_stock, $product_id]);

            // Refresh product data
            $stmt = $pdo->prepare("SELECT p.*, c.name as category_name, c.icon as category_icon
                                  FROM products p 
                                  LEFT JOIN categories c ON p.category_id = c.id 
                                  WHERE p.id = ? AND p.deleted_at IS NULL");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();
        }

        $message = "Status produk berhasil diperbarui!";
        $messageType = 'success';
    } catch (PDOException $e) {
        $message = "Gagal memperbarui status: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Handle stock adjustment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['adjust_stock'])) {
    $new_stock = intval($_POST['new_stock']);
    $reason = sanitize($_POST['reason']);

    if ($new_stock < 0) {
        $message = "Stok tidak boleh negatif!";
        $messageType = 'error';
    } else {
        try {
            // Update stock
            $stmt = $pdo->prepare("UPDATE products SET stock = ? WHERE id = ? AND deleted_at IS NULL");
            $stmt->execute([$new_stock, $product_id]);

            // Refresh product data
            $stmt = $pdo->prepare("SELECT p.*, c.name as category_name, c.icon as category_icon
                                  FROM products p 
                                  LEFT JOIN categories c ON p.category_id = c.id 
                                  WHERE p.id = ? AND p.deleted_at IS NULL");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();

            $message = "Stok berhasil disesuaikan!";
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = "Gagal menyesuaikan stok: " . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Handle soft delete
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete') {
    $id = intval($_POST['id']);

    try {
        $stmt = $pdo->prepare("UPDATE products SET deleted_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);

        $message = "Produk berhasil dihapus!";
        $messageType = 'success';

        // Redirect to products page
        header("Location: products.php?message=" . urlencode($message) . "&messageType=" . $messageType);
        exit();

    } catch (PDOException $e) {
        $message = "Gagal menghapus produk: " . $e->getMessage();
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Produk:
        <?php echo htmlspecialchars($product['name']); ?> - Admin Roncelizz
    </title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .product-detail-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-top: 25px;
        }

        @media (max-width: 992px) {
            .product-detail-container {
                grid-template-columns: 1fr;
            }
        }

        .product-image-section {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: var(--shadow-soft);
        }

        .product-info-section {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: var(--shadow-soft);
        }

        .product-main-image {
            width: 100%;
            height: 400px;
            object-fit: contain;
            border-radius: 15px;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 20px;
            margin-bottom: 20px;
            border: 2px solid var(--gray-light);
        }

        .product-gallery {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-top: 15px;
        }

        .gallery-thumb {
            width: 100%;
            height: 80px;
            object-fit: cover;
            border-radius: 10px;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.3s;
        }

        .gallery-thumb:hover,
        .gallery-thumb.active {
            border-color: var(--purple);
            transform: scale(1.05);
        }

        .product-status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-left: 15px;
        }

        .status-available {
            background: linear-gradient(135deg, var(--mint-light), var(--mint-medium));
            color: var(--mint-dark);
        }

        .status-out_of_stock {
            background: linear-gradient(135deg, var(--peach-light), var(--peach-medium));
            color: var(--peach-dark);
        }

        .status-discontinued {
            background: linear-gradient(135deg, var(--pink-light), var(--pink-medium));
            color: var(--pink-dark);
        }

        .product-meta-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 25px;
        }

        .meta-item {
            display: flex;
            flex-direction: column;
            padding: 15px;
            background: var(--gray-light);
            border-radius: 12px;
        }

        .meta-label {
            font-size: 13px;
            color: var(--gray);
            margin-bottom: 5px;
        }

        .meta-value {
            font-size: 16px;
            font-weight: 600;
            color: var(--dark);
        }

        .price-display {
            font-size: 32px;
            font-weight: 700;
            color: var(--pink);
            margin: 20px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .price-unit {
            font-size: 16px;
            color: var(--gray);
            font-weight: 500;
        }

        .category-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: linear-gradient(135deg, var(--purple-light), var(--pink-light));
            color: var(--purple-dark);
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 15px;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .action-btn {
            flex: 1;
            min-width: 150px;
            padding: 15px;
            border-radius: 12px;
            text-align: center;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s;
        }

        .btn-edit {
            background: linear-gradient(135deg, var(--mint), var(--mint-dark));
            color: white;
        }

        .btn-back {
            background: linear-gradient(135deg, var(--gray-medium), var(--gray));
            color: white;
        }

        .btn-delete {
            background: linear-gradient(135deg, var(--pink), var(--pink-dark));
            color: white;
        }

        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-medium);
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
            margin: 30px 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--purple-light);
        }

        .order-history-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow-soft);
        }

        .order-history-table th {
            background: linear-gradient(135deg, var(--purple-light), var(--pink-light));
            color: var(--purple-dark);
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }

        .order-history-table td {
            padding: 15px;
            border-bottom: 1px solid var(--gray-light);
        }

        .order-history-table tr:hover {
            background: var(--gray-light);
        }

        .status-control-panel {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 25px;
            border-radius: 15px;
            margin-top: 25px;
        }

        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            align-items: flex-end;
        }

        .form-group {
            flex: 1;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark);
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--gray-medium);
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--purple);
            box-shadow: 0 0 0 3px rgba(156, 107, 255, 0.1);
        }

        .stock-indicator {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 15px;
        }

        .stock-bar {
            flex: 1;
            height: 10px;
            background: var(--gray-light);
            border-radius: 5px;
            overflow: hidden;
        }

        .stock-fill {
            height: 100%;
            border-radius: 5px;
            transition: width 0.3s ease;
        }

        .stock-low {
            background: linear-gradient(90deg, var(--peach), var(--peach-dark));
        }

        .stock-medium {
            background: linear-gradient(90deg, var(--yellow), var(--yellow-dark));
        }

        .stock-high {
            background: linear-gradient(90deg, var(--mint), var(--mint-dark));
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--gray);
            font-style: italic;
            background: var(--gray-light);
            border-radius: 12px;
            margin-top: 15px;
        }

        @media (max-width: 768px) {
            .product-meta-grid {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
            }

            .action-btn {
                min-width: 100%;
            }

            .form-row {
                flex-direction: column;
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
                    <i class="fas fa-box-open" style="color: var(--purple);"></i>
                    Detail Produk
                </h1>
                <p class="page-subtitle">
                    Informasi lengkap produk
                    <?php echo htmlspecialchars($product['name']); ?>
                </p>
            </div>
            <div style="display: flex; gap: 15px;">
                <a href="products.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali ke Produk
                </a>
                <a href="products.php?action=edit&id=<?php echo $product_id; ?>" class="btn">
                    <i class="fas fa-edit"></i> Edit Produk
                </a>
            </div>
        </div>

        <?php if (isset($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="product-detail-container">
            <!-- Product Images Section -->
            <div class="product-image-section">
                <div>
                    <?php if (!empty($product['image_url'])): ?>
                        <img src="../<?php echo htmlspecialchars($product['image_url']); ?>" class="product-main-image"
                            id="mainImage" alt="<?php echo htmlspecialchars($product['name']); ?>"
                            onerror="this.src='../assets/images/default-product.jpg'">
                    <?php else: ?>
                        <img src="../assets/images/default-product.jpg" class="product-main-image" id="mainImage"
                            alt="No Image">
                    <?php endif; ?>
                </div>

                <div class="product-gallery">
                    <?php if (!empty($product['image_url'])): ?>
                        <img src="../<?php echo htmlspecialchars($product['image_url']); ?>" class="gallery-thumb active"
                            onclick="changeImage(this.src)" alt="Thumbnail 1">
                    <?php endif; ?>
                </div>

                <div style="margin-top: 25px;">
                    <h3 class="section-title">Statistik Produk</h3>
                    <div class="product-meta-grid">
                        <div class="meta-item">
                            <span class="meta-label">Total Dilihat</span>
                            <span class="meta-value">
                                <?php echo $product['view_count'] ?? 0; ?> kali
                            </span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Terjual</span>
                            <span class="meta-value">
                                <?php echo count($orderHistory); ?> item
                            </span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Dibuat</span>
                            <span class="meta-value">
                                <?php echo date('d M Y', strtotime($product['created_at'] ?? 'now')); ?>
                            </span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Diperbarui</span>
                            <span class="meta-value">
                                <?php echo date('d M Y', strtotime($product['updated_at'] ?? 'now')); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Product Info Section -->
            <div class="product-info-section">
                <div
                    style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
                    <h2 style="font-size: 28px; font-weight: 700; color: var(--dark); line-height: 1.3;">
                        <?php echo htmlspecialchars($product['name']); ?>
                        <span class="product-status-badge status-<?php echo $product['status']; ?>">
                            <?php
                            $statusText = [
                                'available' => 'Tersedia',
                                'out_of_stock' => 'Habis',
                                'discontinued' => 'Tidak Aktif'
                            ];
                            echo $statusText[$product['status']] ?? $product['status'];
                            ?>
                        </span>
                    </h2>
                </div>

                <div class="category-badge">
                    <?php if ($product['category_icon']): ?>
                        <span>
                            <?php echo htmlspecialchars($product['category_icon']); ?>
                        </span>
                    <?php else: ?>
                        <i class="fas fa-tag"></i>
                    <?php endif; ?>
                    <?php echo htmlspecialchars($product['category_name'] ?? 'Tidak ada kategori'); ?>
                </div>

                <div class="price-display">
                    Rp
                    <?php echo number_format($product['price'], 0, ',', '.'); ?>
                    <span class="price-unit">/ item</span>
                </div>

                <!-- Stock Information -->
                <div>
                    <h3 class="section-title">Informasi Stok</h3>
                    <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 15px;">
                        <div style="flex: 1;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                <span style="font-weight: 500; color: var(--dark);">Stok Tersedia</span>
                                <span style="font-size: 24px; font-weight: 700; color: var(--pink);">
                                    <?php echo $product['stock']; ?> pcs
                                </span>
                            </div>
                            <div class="stock-indicator">
                                <div class="stock-bar">
                                    <?php
                                    $stock_percentage = min(100, ($product['stock'] / 50) * 100);
                                    $stock_class = 'stock-high';
                                    if ($product['stock'] <= 10)
                                        $stock_class = 'stock-low';
                                    elseif ($product['stock'] <= 25)
                                        $stock_class = 'stock-medium';
                                    ?>
                                    <div class="stock-fill <?php echo $stock_class; ?>"
                                        style="width: <?php echo $stock_percentage; ?>%"></div>
                                </div>
                                <span style="font-size: 12px; color: var(--gray);">
                                    <?php
                                    if ($product['stock'] == 0)
                                        echo 'Stok Habis';
                                    elseif ($product['stock'] <= 10)
                                        echo 'Stok Rendah';
                                    elseif ($product['stock'] <= 25)
                                        echo 'Stok Sedang';
                                    else
                                        echo 'Stok Tinggi';
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Product Details -->
                <div>
                    <h3 class="section-title">Detail Produk</h3>
                    <div class="product-meta-grid">
                        <div class="meta-item">
                            <span class="meta-label">Jenis Produk</span>
                            <span class="meta-value">
                                <?php
                                $typeText = [
                                    'regular' => 'Regular',
                                    'limited' => 'Limited Edition',
                                    'custom' => 'Custom Request'
                                ];
                                echo $typeText[$product['type']] ?? $product['type'];
                                ?>
                            </span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Berat</span>
                            <span class="meta-value">
                                <?php echo $product['weight'] ? $product['weight'] . ' gram' : '-'; ?>
                            </span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Bahan</span>
                            <span class="meta-value">
                                <?php echo $product['material'] ?: '-'; ?>
                            </span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Warna</span>
                            <span class="meta-value">
                                <?php echo $product['color'] ?: '-'; ?>
                            </span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Ukuran</span>
                            <span class="meta-value">
                                <?php echo $product['size'] ?: '-'; ?>
                            </span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">ID Produk</span>
                            <span class="meta-value">#
                                <?php echo str_pad($product['id'], 6, '0', STR_PAD_LEFT); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <div>
                    <h3 class="section-title">Deskripsi</h3>
                    <div style="background: var(--gray-light); padding: 20px; border-radius: 12px; line-height: 1.6;">
                        <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <a href="products.php?action=edit&id=<?php echo $product_id; ?>" class="action-btn btn-edit">
                        <i class="fas fa-edit"></i> Edit Produk
                    </a>
                    <a href="products.php" class="action-btn btn-back">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                    <!-- Form delete dengan soft delete -->
                    <form method="POST" action="" onsubmit="return confirmDelete(event)"
                        style="flex: 1; min-width: 150px;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo $product_id; ?>">
                        <button type="submit" class="action-btn btn-delete" style="width: 100%;">
                            <i class="fas fa-trash"></i> Hapus Produk
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Status and Stock Control Panel -->
        <div class="status-control-panel">
            <h3 class="section-title">Kelola Status & Stok</h3>

            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Ubah Status Produk</label>
                        <select name="status" class="form-control" required>
                            <option value="available" <?php echo $product['status'] == 'available' ? 'selected' : ''; ?>>
                                Tersedia</option>
                            <option value="out_of_stock" <?php echo $product['status'] == 'out_of_stock' ? 'selected' : ''; ?>>Habis</option>
                            <option value="discontinued" <?php echo $product['status'] == 'discontinued' ? 'selected' : ''; ?>>Tidak Aktif</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Penyesuaian Stok (+/-)</label>
                        <input type="number" name="stock_change" class="form-control" placeholder="Contoh: +5 atau -3">
                        <small style="color: var(--gray); display: block; margin-top: 5px;">Kosongkan jika tidak
                            mengubah stok</small>
                    </div>
                    <div class="form-group" style="min-width: 150px;">
                        <button type="submit" name="update_status" class="btn"
                            style="padding: 12px 25px; height: 44px;">
                            <i class="fas fa-sync-alt"></i> Perbarui
                        </button>
                    </div>
                </div>
            </form>

            <form method="POST" action="" style="margin-top: 25px;">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Atur Stok Manual</label>
                        <input type="number" name="new_stock" class="form-control"
                            value="<?php echo $product['stock']; ?>" min="0" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Alasan Penyesuaian</label>
                        <input type="text" name="reason" class="form-control"
                            placeholder="Contoh: Stok opname, retur, tambahan stok" required>
                    </div>
                    <div class="form-group" style="min-width: 150px;">
                        <button type="submit" name="adjust_stock" class="btn btn-success"
                            style="padding: 12px 25px; height: 44px;">
                            <i class="fas fa-check"></i> Simpan
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Order History -->
        <div>
            <h3 class="section-title">Riwayat Pesanan</h3>
            <?php if (empty($orderHistory)): ?>
                <div class="empty-state">
                    <i class="fas fa-history" style="font-size: 40px; margin-bottom: 15px; opacity: 0.5;"></i>
                    <p>Belum ada riwayat pesanan untuk produk ini</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="order-history-table">
                        <thead>
                            <tr>
                                <th>No. Pesanan</th>
                                <th>Pelanggan</th>
                                <th>Tanggal</th>
                                <th>Jumlah</th>
                                <th>Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orderHistory as $order): ?>
                                <tr>
                                    <td>#
                                        <?php echo str_pad($order['order_id'], 6, '0', STR_PAD_LEFT); ?>
                                    </td>
                                    <td>
                                        <div>
                                            <?php echo htmlspecialchars($order['customer_name']); ?>
                                        </div>
                                        <small style="color: var(--gray);">
                                            <?php echo htmlspecialchars($order['customer_email']); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php echo date('d M Y', strtotime($order['order_date'])); ?>
                                    </td>
                                    <td>
                                        <?php echo $order['quantity']; ?> pcs
                                    </td>
                                    <td>Rp
                                        <?php echo number_format($order['price'] * $order['quantity'], 0, ',', '.'); ?>
                                    </td>
                                    <td>
                                        <span style="padding: 5px 10px; border-radius: 15px; font-size: 12px; font-weight: 500;"
                                            class="status-<?php echo $order['order_status']; ?>">
                                            <?php
                                            $orderStatusText = [
                                                'pending' => 'Menunggu',
                                                'processing' => 'Diproses',
                                                'completed' => 'Selesai',
                                                'cancelled' => 'Dibatalkan'
                                            ];
                                            echo $orderStatusText[$order['order_status']] ?? $order['order_status'];
                                            ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Image gallery functionality
        function changeImage(src) {
            document.getElementById('mainImage').src = src;

            // Update active thumbnail
            document.querySelectorAll('.gallery-thumb').forEach(thumb => {
                thumb.classList.remove('active');
            });
            event.target.classList.add('active');
        }

        // Delete confirmation dengan informasi soft delete
        function confirmDelete(event) {
            event.preventDefault();
            const form = event.target.closest('form');

            Swal.fire({
                title: 'Hapus Produk?',
                html: `
                    <div style="text-align: left;">
                        <p>Anda yakin ingin menghapus produk <strong>"<?php echo htmlspecialchars($product['name']); ?>"</strong>?</p>
                        <div style="background: #d1ecf1; border-left: 4px solid #0c5460; padding: 10px; margin: 10px 0; border-radius: 4px; color: #0c5460;">
                            <strong><i class="fas fa-info-circle"></i> INFORMASI:</strong>
                            <ul style="margin: 5px 0 0 15px;">
                                <li>Produk akan diarsipkan (soft delete)</li>
                                <li>Data order yang terkait tetap aman</li>
                                <li>Produk dapat dipulihkan jika diperlukan</li>
                                <li>Tidak akan muncul di katalog publik</li>
                            </ul>
                        </div>
                        <p style="color: #666; font-size: 14px;">
                            Tindakan ini aman dan tidak akan menyebabkan error pada pesanan yang ada.
                        </p>
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

        // Stock change validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function (e) {
                // Skip validation for delete form
                if (this.querySelector('[name="action"][value="delete"]')) {
                    return true;
                }

                const stockChange = this.querySelector('[name="stock_change"]');
                if (stockChange && stockChange.value) {
                    const value = parseInt(stockChange.value);
                    if (isNaN(value)) {
                        e.preventDefault();
                        Swal.fire('Error', 'Masukkan angka yang valid untuk penyesuaian stok', 'error');
                        return false;
                    }
                }

                const newStock = this.querySelector('[name="new_stock"]');
                if (newStock) {
                    const value = parseInt(newStock.value);
                    if (value < 0) {
                        e.preventDefault();
                        Swal.fire('Error', 'Stok tidak boleh negatif', 'error');
                        return false;
                    }
                }

                return true;
            });
        });

        // Auto update stock bar
        const stockFill = document.querySelector('.stock-fill');
        if (stockFill) {
            const stock = <?php echo $product['stock']; ?>;
            const maxStock = 50; 
            const percentage = Math.min(100, (stock / maxStock) * 100);
            stockFill.style.width = percentage + '%';
        }

        // Toggle mobile menu
        const menuToggle = document.getElementById('menuToggle');
        if (menuToggle) {
            menuToggle.addEventListener('click', function () {
                const sidebar = document.getElementById('sidebar');
                if (sidebar) {
                    sidebar.classList.toggle('active');
                }
            });
        }

        // Print product info
        function printProductInfo() {
            window.print();
        }
    </script>
</body>

</html>