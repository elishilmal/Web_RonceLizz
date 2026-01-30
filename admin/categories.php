<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

Auth::requireAdmin();

$message = '';
$messageType = '';
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];

    if ($action == 'add' || $action == 'edit') {
        $name = sanitize($_POST['name']);
        $description = sanitize($_POST['description']);
        $icon = sanitize($_POST['icon']);

        if (empty($name)) {
            $message = "Nama kategori wajib diisi!";
            $messageType = 'error';
        } else {
            try {
                if ($action == 'add') {
                    $checkStmt = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
                    $checkStmt->execute([$name]);

                    if ($checkStmt->rowCount() > 0) {
                        $message = "Kategori dengan nama tersebut sudah ada!";
                        $messageType = 'error';
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO categories (name, description, icon) VALUES (?, ?, ?)");
                        $stmt->execute([$name, $description, $icon]);

                        $message = "Kategori berhasil ditambahkan!";
                        $messageType = 'success';
                        $action = 'list';
                    }
                } else {
                    $id = intval($_POST['id']);

                    $checkStmt = $pdo->prepare("SELECT id FROM categories WHERE name = ? AND id != ?");
                    $checkStmt->execute([$name, $id]);

                    if ($checkStmt->rowCount() > 0) {
                        $message = "Kategori dengan nama tersebut sudah ada!";
                        $messageType = 'error';
                    } else {
                        $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ?, icon = ? WHERE id = ?");
                        $stmt->execute([$name, $description, $icon, $id]);

                        $message = "Kategori berhasil diperbarui!";
                        $messageType = 'success';
                        $action = 'list';
                    }
                }
            } catch (PDOException $e) {
                $message = "Error: " . $e->getMessage();
                $messageType = 'error';
            }
        }
    } elseif ($action == 'delete') {
        $id = intval($_POST['id']);

        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch();

            if ($result['count'] > 0) {
                $message = "Tidak dapat menghapus kategori yang memiliki produk!";
                $messageType = 'error';
            } else {
                $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                $stmt->execute([$id]);

                $message = "Kategori berhasil dihapus!";
                $messageType = 'success';
            }
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = 'error';
        }
    }
}

$category = null;
if ($action == 'edit' && $id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $category = $stmt->fetch();

        if (!$category) {
            $message = "Kategori tidak ditemukan!";
            $messageType = 'error';
            $action = 'list';
        }
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = 'error';
        $action = 'list';
    }
}

$categories = [];
try {
    $stmt = $pdo->query("SELECT c.*, 
                         (SELECT COUNT(*) FROM products WHERE category_id = c.id) as product_count 
                         FROM categories c ORDER BY name");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $message = "Error: " . $e->getMessage();
    $messageType = 'error';
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kategori - Roncelizz</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* SweetAlert2 Custom Styling */
        .swal2-confirm {
            background-color: var(--pink) !important;
            border-color: var(--pink) !important;
        }

        .swal2-cancel {
            background-color: var(--gray) !important;
            border-color: var(--gray) !important;
        }

        /* Categories Grid */
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }

        .category-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: var(--shadow-soft);
            border: 2px solid transparent;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-medium);
            border-color: var(--purple);
        }

        .category-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .category-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--pink), var(--purple));
            border-radius: var(--border-radius-circle);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: white;
            box-shadow: 0 8px 25px rgba(255, 107, 147, 0.3);
            transition: all 0.3s ease;
        }

        .category-card:hover .category-icon {
            transform: scale(1.1) rotate(5deg);
        }

        .category-actions {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }

        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-soft);
        }

        .btn-edit {
            background: var(--mint-light);
            color: var(--mint-dark);
            border: 1px solid var(--mint-medium);
        }

        .btn-delete {
            background: var(--pink-light);
            color: var(--pink-dark);
            border: 1px solid var(--pink-medium);
        }

        .category-name {
            font-size: 22px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 15px;
            line-height: 1.3;
        }

        .category-description {
            color: var(--gray);
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 25px;
            min-height: 48px;
        }

        .category-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: auto;
            padding-top: 20px;
            border-top: 1px solid var(--gray-light);
        }

        .product-count {
            background: linear-gradient(135deg, var(--purple-light), var(--pink-light));
            color: var(--purple-dark);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 3px 10px rgba(156, 107, 255, 0.2);
        }

        /* Icon Grid */
        .icon-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(45px, 1fr));
            gap: 12px;
            margin-top: 15px;
            margin-bottom: 10px;
        }

        .icon-option {
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            background: white;
            border: 2px solid var(--gray-light);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .icon-option:hover {
            transform: scale(1.1);
            border-color: var(--purple);
            background: var(--purple-light);
        }

        .icon-option.selected {
            background: var(--purple);
            color: white;
            border-color: var(--purple);
            transform: scale(1.1);
            box-shadow: 0 5px 15px rgba(156, 107, 255, 0.3);
        }

        .icon-preview {
            font-size: 28px;
            margin-left: 10px;
            vertical-align: middle;
        }

        /* Search and Filter */
        .search-container {
            position: relative;
            margin-bottom: 25px;
        }

        .search-input {
            width: 100%;
            padding: 15px 20px 15px 50px;
            border: 2px solid var(--gray-medium);
            border-radius: 15px;
            font-size: 16px;
            transition: all 0.3s;
            background: white;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--purple);
            box-shadow: 0 0 0 3px rgba(156, 107, 255, 0.1);
        }

        .search-icon {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
            font-size: 18px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow-soft);
            margin-top: 20px;
        }

        .empty-icon {
            font-size: 80px;
            color: var(--pink);
            margin-bottom: 25px;
            opacity: 0.7;
        }

        .empty-text {
            color: var(--gray);
            margin-bottom: 25px;
            font-size: 16px;
            line-height: 1.6;
        }

        /* Form Container */
        .form-container {
            background: white;
            padding: 35px;
            border-radius: 20px;
            box-shadow: var(--shadow-soft);
            border: 2px solid var(--pink-light);
        }

        .form-section {
            margin-bottom: 30px;
        }

        .form-section-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--purple-light);
        }

        /* Form Improvements */
        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            display: block;
            margin-bottom: 10px;
            color: var(--dark);
            font-weight: 500;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-control {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid var(--gray-medium);
            border-radius: 12px;
            font-size: 16px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s;
            background: white;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--purple);
            box-shadow: 0 0 0 3px rgba(156, 107, 255, 0.1);
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        /* Button Styles */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 15px 30px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            font-family: 'Poppins', sans-serif;
        }

        .btn-success {
            background: linear-gradient(135deg, var(--mint), var(--mint-dark));
            color: white;
        }

        .btn-secondary {
            background: linear-gradient(135deg, var(--gray-light), var(--gray-medium));
            color: var(--dark);
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-medium);
        }

        /* Action Buttons Container */
        .action-buttons-container {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .categories-grid {
                grid-template-columns: 1fr;
            }

            .category-card {
                padding: 20px;
            }

            .form-container {
                padding: 25px 20px;
            }

            .icon-grid {
                grid-template-columns: repeat(6, 1fr);
            }

            .action-buttons-container {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .category-icon {
                width: 60px;
                height: 60px;
                font-size: 28px;
            }

            .category-name {
                font-size: 20px;
            }

            .icon-grid {
                grid-template-columns: repeat(4, 1fr);
            }

            .category-actions {
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
                    <?php
                    if ($action == 'add')
                        echo 'Tambah Kategori';
                    elseif ($action == 'edit')
                        echo 'Edit Kategori';
                    else
                        echo 'Kelola Kategori';
                    ?>
                </h1>
                <p class="page-subtitle">
                    <?php
                    if ($action == 'add')
                        echo 'Tambahkan kategori baru untuk mengorganisir produk';
                    elseif ($action == 'edit')
                        echo 'Edit informasi kategori';
                    else
                        echo 'Kelola semua kategori produk Roncelizz';
                    ?>
                </p>
            </div>
            <?php if ($action == 'list'): ?>
                <a href="?action=add" class="btn">
                    <i class="fas fa-plus"></i> Tambah Kategori
                </a>
            <?php else: ?>
                <a href="categories.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            <?php endif; ?>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType == 'success' ? 'success' : 'error'; ?>">
                <strong><?php echo $messageType == 'success' ? '✅ Berhasil!' : '❌ Error!'; ?></strong>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($action == 'add' || $action == 'edit'): ?>
            <!-- Form untuk tambah/edit kategori -->
            <div class="form-container">
                <form method="POST" action="" id="categoryForm">
                    <input type="hidden" name="action" value="<?php echo $action; ?>">
                    <input type="hidden" name="id" value="<?php echo $category['id'] ?? ''; ?>">

                    <div class="form-section">
                        <h3 class="form-section-title">Informasi Kategori</h3>

                        <div class="form-group">
                            <label for="name">
                                <i class="fas fa-tag" style="color: var(--purple);"></i>
                                Nama Kategori *
                            </label>
                            <input type="text" class="form-control" id="name" name="name"
                                value="<?php echo htmlspecialchars($category['name'] ?? ''); ?>"
                                placeholder="Contoh: Manik Kristal, Aksesoris Custom" required>
                        </div>

                        <div class="form-group">
                            <label for="description">
                                <i class="fas fa-align-left" style="color: var(--mint);"></i>
                                Deskripsi Kategori
                            </label>
                            <textarea class="form-control" id="description" name="description" rows="4"
                                placeholder="Deskripsikan kategori ini..."><?php echo htmlspecialchars($category['description'] ?? ''); ?></textarea>
                            <small style="color: var(--gray); display: block; margin-top: 8px;">
                                Deskripsi akan membantu pengguna memahami kategori produk.
                            </small>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3 class="form-section-title">Ikon Kategori</h3>

                        <div class="form-group">
                            <label for="icon">
                                <i class="fas fa-icons" style="color: var(--pink);"></i>
                                Pilih Ikon *
                            </label>
                            <input type="text" class="form-control" id="icon" name="icon"
                                value="<?php echo htmlspecialchars($category['icon'] ?? '💎'); ?>"
                                placeholder="Ketik emoji atau pilih dari bawah" required>

                            <div style="margin-top: 15px; margin-bottom: 10px;">
                                <small style="color: var(--gray);">Klik untuk memilih ikon:</small>
                            </div>

                            <div class="icon-grid" id="iconGrid">
                                <div class="icon-option" data-icon="💎">💎</div>
                                <div class="icon-option" data-icon="✨">✨</div>
                                <div class="icon-option" data-icon="🌸">🌸</div>
                                <div class="icon-option" data-icon="🪵">🪵</div>
                                <div class="icon-option" data-icon="🌈">🌈</div>
                                <div class="icon-option" data-icon="🌿">🌿</div>
                                <div class="icon-option" data-icon="⭐">⭐</div>
                                <div class="icon-option" data-icon="🔮">🔮</div>
                                <div class="icon-option" data-icon="🎀">🎀</div>
                                <div class="icon-option" data-icon="💝">💝</div>
                                <div class="icon-option" data-icon="💍">💍</div>
                                <div class="icon-option" data-icon="📿">📿</div>
                                <div class="icon-option" data-icon="🧵">🧵</div>
                                <div class="icon-option" data-icon="🎨">🎨</div>
                                <div class="icon-option" data-icon="🎁">🎁</div>
                                <div class="icon-option" data-icon="🔴">🔴</div>
                                <div class="icon-option" data-icon="🔵">🔵</div>
                                <div class="icon-option" data-icon="🟢">🟢</div>
                                <div class="icon-option" data-icon="🟡">🟡</div>
                                <div class="icon-option" data-icon="🟣">🟣</div>
                                <div class="icon-option" data-icon="💎">💎</div>
                                <div class="icon-option" data-icon="🔷">🔷</div>
                                <div class="icon-option" data-icon="🔶">🔶</div>
                                <div class="icon-option" data-icon="💠">💠</div>
                                <div class="icon-option" data-icon="🔘">🔘</div>
                            </div>

                            <div
                                style="display: flex; align-items: center; gap: 15px; margin-top: 15px; padding: 15px; background: var(--gray-light); border-radius: 10px;">
                                <span style="font-size: 24px;"
                                    id="iconPreview"><?php echo htmlspecialchars($category['icon'] ?? '💎'); ?></span>
                                <div>
                                    <div style="font-weight: 500; color: var(--dark);">Preview Ikon</div>
                                    <div style="font-size: 13px; color: var(--gray);">Ikon akan ditampilkan seperti ini
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="action-buttons-container">
                        <button type="submit" class="btn btn-success" style="flex: 1;">
                            <i class="fas fa-save"></i> Simpan Kategori
                        </button>
                        <a href="categories.php" class="btn btn-secondary" style="flex: 1;">
                            <i class="fas fa-times"></i> Batal
                        </a>
                    </div>
                </form>
            </div>

        <?php else: ?>
            <!-- Search Box -->
            <div class="search-container">
                <i class="fas fa-search search-icon"></i>
                <input type="text" class="search-input" id="searchInput"
                    placeholder="Cari kategori berdasarkan nama atau deskripsi...">
            </div>

            <!-- Daftar Kategori -->
            <?php if (empty($categories)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📁</div>
                    <h3 style="color: var(--dark); margin-bottom: 15px;">Belum Ada Kategori</h3>
                    <p class="empty-text">
                        Tambahkan kategori pertama untuk mengorganisir produk Anda dengan lebih baik.
                        Kategori membantu pelanggan menemukan produk yang mereka cari.
                    </p>
                    <a href="?action=add" class="btn">
                        <i class="fas fa-plus"></i> Tambah Kategori Pertama
                    </a>
                </div>
            <?php else: ?>
                <div class="categories-grid" id="categoriesGrid">
                    <?php foreach ($categories as $cat): ?>
                        <div class="category-card" data-name="<?php echo htmlspecialchars(strtolower($cat['name'])); ?>"
                            data-description="<?php echo htmlspecialchars(strtolower($cat['description'])); ?>">

                            <div class="category-header">
                                <div class="category-icon">
                                    <?php echo htmlspecialchars($cat['icon']); ?>
                                </div>
                                <div class="category-actions">
                                    <a href="?action=edit&id=<?php echo $cat['id']; ?>" class="action-btn btn-edit"
                                        title="Edit Kategori">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button"
                                        onclick="confirmDelete(<?php echo $cat['id']; ?>, '<?php echo htmlspecialchars(addslashes($cat['name'])); ?>')"
                                        class="action-btn btn-delete" title="Hapus Kategori">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>

                            <h3 class="category-name">
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </h3>

                            <?php if ($cat['description']): ?>
                                <p class="category-description">
                                    <?php echo htmlspecialchars($cat['description']); ?>
                                </p>
                            <?php else: ?>
                                <p class="category-description" style="color: var(--gray-light); font-style: italic;">
                                    <i class="fas fa-info-circle" style="margin-right: 5px;"></i>
                                    Tidak ada deskripsi
                                </p>
                            <?php endif; ?>

                            <div class="category-meta">
                                <span class="product-count">
                                    <i class="fas fa-box" style="font-size: 14px;"></i>
                                    <?php echo $cat['product_count']; ?> produk
                                </span>
                                <a href="products.php?category=<?php echo $cat['id']; ?>" class="btn"
                                    style="padding: 8px 16px; font-size: 14px;">
                                    <i class="fas fa-eye"></i> Lihat Produk
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
        // Icon 
        const iconInput = document.getElementById('icon');
        const iconPreview = document.getElementById('iconPreview');
        const iconGrid = document.getElementById('iconGrid');

        if (iconInput && iconPreview && iconGrid) {
            iconInput.addEventListener('input', function () {
                iconPreview.textContent = this.value || '💎';
            });

            iconGrid.querySelectorAll('.icon-option').forEach(option => {
                option.addEventListener('click', function () {
                    const icon = this.getAttribute('data-icon');
                    iconInput.value = icon;
                    iconPreview.textContent = icon;

                    iconGrid.querySelectorAll('.icon-option').forEach(opt => {
                        opt.classList.remove('selected');
                    });
                    this.classList.add('selected');
                });
            });

            <?php if ($action == 'edit' && isset($category['icon'])): ?>
                document.addEventListener('DOMContentLoaded', function () {
                    const currentIcon = "<?php echo htmlspecialchars($category['icon']); ?>";
                    iconGrid.querySelectorAll('.icon-option').forEach(option => {
                        if (option.getAttribute('data-icon') === currentIcon) {
                            option.classList.add('selected');
                        }
                    });
                });
            <?php endif; ?>
        }

        // Search 
        const searchInput = document.getElementById('searchInput');
        const categoriesGrid = document.getElementById('categoriesGrid');

        if (searchInput && categoriesGrid) {
            searchInput.addEventListener('keyup', function () {
                const searchTerm = this.value.toLowerCase();
                const cards = categoriesGrid.querySelectorAll('.category-card');

                cards.forEach(card => {
                    const name = card.dataset.name || '';
                    const description = card.dataset.description || '';
                    const matchesSearch = !searchTerm ||
                        name.includes(searchTerm) ||
                        description.includes(searchTerm);

                    card.style.display = matchesSearch ? '' : 'none';
                });
            });
        }

        // Delete confirmation
        function confirmDelete(id, name) {
            Swal.fire({
                title: 'Hapus Kategori?',
                html: `Anda yakin ingin menghapus kategori <strong>"${name}"</strong>?<br>
                      <small style="color: #666;">
                          Pastikan kategori ini tidak memiliki produk sebelum dihapus.
                      </small>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ff6b93',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal',
                reverseButtons: true,
                customClass: {
                    confirmButton: 'btn',
                    cancelButton: 'btn btn-secondary'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '';

                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'action';
                    actionInput.value = 'delete';

                    const idInput = document.createElement('input');
                    idInput.type = 'hidden';
                    idInput.name = 'id';
                    idInput.value = id;

                    form.appendChild(actionInput);
                    form.appendChild(idInput);
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        // Form validation
        const categoryForm = document.getElementById('categoryForm');
        if (categoryForm) {
            categoryForm.addEventListener('submit', function (e) {
                const name = document.getElementById('name').value.trim();
                const icon = document.getElementById('icon').value.trim();

                if (!name) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Nama Kategori Wajib!',
                        text: 'Harap isi nama kategori',
                        icon: 'error',
                        confirmButtonColor: '#ff6b93'
                    });
                    return false;
                }

                if (!icon) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Ikon Kategori Wajib!',
                        text: 'Harap pilih ikon untuk kategori',
                        icon: 'error',
                        confirmButtonColor: '#ff6b93'
                    });
                    return false;
                }

                return true;
            });
        }

        // Card
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.focus();
            }
            const nameInput = document.getElementById('name');
            if (nameInput) {
                nameInput.focus();
            }
        });
        document.addEventListener('DOMContentLoaded', function () {
            const cards = document.querySelectorAll('.category-card');
            cards.forEach((card, index) => {
                card.style.animation = `fadeInUp 0.5s ease-out ${index * 0.1}s both`;
                card.style.opacity = '0';
            });
        });

        // CSS animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        `;
        document.head.appendChild(style);
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
    </script>
</body>

</html>