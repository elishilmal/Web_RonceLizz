    <?php
    require_once '../includes/config.php';
    require_once '../includes/auth.php';

    Auth::requireLogin();

    $user = Auth::getUser();
    $message = '';
    $error = '';

    // Get pending orders that need payment (TIDAK termasuk yang sudah COD pending)
    try {
        $stmt = $pdo->prepare("SELECT o.*, p.name as product_name 
                            FROM orders o 
                            LEFT JOIN products p ON o.product_id = p.id 
                            WHERE o.user_id = ? 
                            AND o.status = 'pending' 
                            AND o.id NOT IN (
                                SELECT order_id FROM payments 
                                WHERE status IN ('verified', 'pending', 'cod_pending')
                            )
                            ORDER BY o.created_at DESC");
        $stmt->execute([$_SESSION['user_id']]);
        $pendingOrders = $stmt->fetchAll();

        // Get payment history with payment details (termasuk COD pending)
        // PERBAIKAN: Tambahkan order_status untuk debugging dan tampilan yang lebih baik
        $stmt = $pdo->prepare("SELECT py.*, o.order_code, o.quantity, o.total_price, o.status as order_status,
                                    p.name as product_name,
                                    py.payment_method, py.bank_name, py.amount, py.status as payment_status,
                                    py.payment_date, py.verification_notes, py.verified_at
                            FROM payments py
                            JOIN orders o ON py.order_id = o.id
                            LEFT JOIN products p ON o.product_id = p.id
                            WHERE o.user_id = ?
                            ORDER BY py.created_at DESC LIMIT 10");
        $stmt->execute([$_SESSION['user_id']]);
        $paymentHistory = $stmt->fetchAll();

        // Calculate totals untuk pending orders
        $totalPending = 0;
        foreach ($pendingOrders as $order) {
            $totalPending += $order['total_price'];
        }
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }

    // Handle payment confirmation
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
        $order_id = $_POST['order_id'] ?? '';
        $payment_method = $_POST['payment_method'] ?? '';
        $bank_name = $_POST['bank_name'] ?? '';
        $account_number = $_POST['account_number'] ?? '';
        $payment_proof = $_FILES['payment_proof'] ?? null;

        // Validasi untuk COD
        $isCOD = ($payment_method === 'cod');

        if (empty($order_id) || empty($payment_method)) {
            $error = "Mohon pilih pesanan dan metode pembayaran.";
        } elseif (!$isCOD && (!$payment_proof || $payment_proof['error'] !== UPLOAD_ERR_OK)) {
            $error = "Mohon upload bukti pembayaran yang valid.";
        } else {
            try {
                // Get order details
                $stmt = $pdo->prepare("SELECT total_price FROM orders WHERE id = ? AND user_id = ? AND status = 'pending'");
                $stmt->execute([$order_id, $_SESSION['user_id']]);
                $order = $stmt->fetch();

                if (!$order) {
                    throw new Exception("Pesanan tidak ditemukan atau sudah diproses.");
                }

                $payment_proof_path = null;

                // Jika bukan COD, proses upload bukti pembayaran
                if (!$isCOD) {
                    // Upload payment proof
                    $upload_dir = '../assets/images/payments/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }

                    $file_extension = pathinfo($payment_proof['name'], PATHINFO_EXTENSION);
                    $file_name = 'payment_' . time() . '_' . uniqid() . '.' . $file_extension;
                    $target_file = $upload_dir . $file_name;

                    // Check file size
                    if ($payment_proof['size'] > 2 * 1024 * 1024) {
                        throw new Exception("Ukuran file terlalu besar. Maksimal 2MB.");
                    }

                    // Check file type
                    $allowed_types = ['jpg', 'jpeg', 'png', 'pdf'];
                    if (!in_array(strtolower($file_extension), $allowed_types)) {
                        throw new Exception("Format file tidak didukung. Gunakan JPG, PNG, atau PDF.");
                    }

                    if (!move_uploaded_file($payment_proof['tmp_name'], $target_file)) {
                        throw new Exception("Gagal mengupload file.");
                    }

                    $payment_proof_path = 'payments/' . $file_name;
                }

                // Status khusus untuk COD
                $payment_status = $isCOD ? 'cod_pending' : 'pending';

                // Mulai transaksi
                $pdo->beginTransaction();

                try {
                    // Update order status berdasarkan metode pembayaran
                    if ($isCOD) {
                        // Untuk COD, ubah status order menjadi 'cod_confirmed'
                        // CATATAN: 'cod_confirmed' BUKAN status yang valid di tabel orders
                        // Status yang valid: 'pending','processing','completed','cancelled'
                        // PERBAIKAN: Ubah ke 'processing' untuk COD yang sudah dikonfirmasi
                        $stmt = $pdo->prepare("UPDATE orders SET status = 'processing' WHERE id = ? AND user_id = ?");
                        $stmt->execute([$order_id, $_SESSION['user_id']]);
                    } else {
                        // Untuk non-COD, status order tetap 'pending' menunggu verifikasi
                        // CATATAN: 'payment_pending' BUKAN status yang valid
                        // Status tetap 'pending' saja
                        $stmt = $pdo->prepare("UPDATE orders SET status = 'pending' WHERE id = ? AND user_id = ?");
                        $stmt->execute([$order_id, $_SESSION['user_id']]);
                    }

                    // Insert payment record
                    $stmt = $pdo->prepare("INSERT INTO payments 
                                        (order_id, payment_method, bank_name, account_number, 
                                        amount, payment_proof, status, created_at) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([
                        $order_id,
                        $payment_method,
                        $bank_name,
                        $account_number,
                        $order['total_price'],
                        $payment_proof_path,
                        $payment_status
                    ]);

                    // Commit transaksi
                    $pdo->commit();

                    if ($isCOD) {
                        $message = "Pesanan COD berhasil dibuat! Status pesanan telah berubah menjadi 'Processing'. Tunggu admin memproses pesanan Anda.";
                    } else {
                        $message = "Bukti pembayaran berhasil diupload! Status pesanan tetap 'Pending'. Admin akan memverifikasi pembayaran Anda.";
                    }

                    // === PERBAIKAN DI SINI: Refresh SEMUA data setelah submit ===
                    
                    // 1. Refresh pending orders
                    $stmt = $pdo->prepare("SELECT o.*, p.name as product_name 
                                        FROM orders o 
                                        LEFT JOIN products p ON o.product_id = p.id 
                                        WHERE o.user_id = ? 
                                        AND o.status = 'pending' 
                                        AND o.id NOT IN (
                                            SELECT order_id FROM payments 
                                            WHERE status IN ('verified', 'pending', 'cod_pending')
                                        )
                                        ORDER BY o.created_at DESC");
                    $stmt->execute([$_SESSION['user_id']]);
                    $pendingOrders = $stmt->fetchAll();

                    // 2. Refresh payment history (SANGAT PENTING untuk COD!)
                    // PERBAIKAN: Pastikan query ini sama dengan query awal
                    $stmt = $pdo->prepare("SELECT py.*, o.order_code, o.quantity, o.total_price, o.status as order_status,
                                                p.name as product_name,
                                                py.payment_method, py.bank_name, py.amount, py.status as payment_status,
                                                py.payment_date, py.verification_notes, py.verified_at
                                        FROM payments py
                                        JOIN orders o ON py.order_id = o.id
                                        LEFT JOIN products p ON o.product_id = p.id
                                        WHERE o.user_id = ?
                                        ORDER BY py.created_at DESC LIMIT 10");
                    $stmt->execute([$_SESSION['user_id']]);
                    $paymentHistory = $stmt->fetchAll();

                    // 3. Recalculate total
                    $totalPending = 0;
                    foreach ($pendingOrders as $order) {
                        $totalPending += $order['total_price'];
                    }

                } catch (Exception $e) {
                    // Rollback jika ada error
                    $pdo->rollBack();
                    throw $e;
                }

            } catch (Exception $e) {
                $error = "Gagal memproses pembayaran: " . $e->getMessage();
            }
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="id">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Pembayaran - Roncelizz</title>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
            rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

            /* Page Header */
            .page-header {
                background: white;
                border-radius: 15px;
                padding: 30px;
                margin-bottom: 30px;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
                border-left: 5px solid var(--pink);
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
                color: var(--pink);
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

            /* Summary Card */
            .summary-card {
                background: linear-gradient(135deg, var(--pink), var(--purple));
                color: white;
                border-radius: 15px;
                padding: 30px;
                margin-bottom: 30px;
                box-shadow: 0 10px 30px rgba(255, 107, 147, 0.3);
            }

            .summary-content {
                display: flex;
                justify-content: space-between;
                align-items: center;
                flex-wrap: wrap;
                gap: 20px;
            }

            .summary-info h2 {
                font-size: 24px;
                margin-bottom: 10px;
            }

            .summary-info p {
                opacity: 0.9;
                font-size: 14px;
            }

            .summary-amount {
                text-align: right;
            }

            .amount-label {
                font-size: 14px;
                opacity: 0.9;
                margin-bottom: 5px;
            }

            .amount-value {
                font-size: 36px;
                font-weight: 600;
            }

            /* Payment Sections */
            .payment-sections {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 30px;
                margin-bottom: 40px;
            }

            @media (max-width: 992px) {
                .payment-sections {
                    grid-template-columns: 1fr;
                }
            }

            .payment-section {
                background: white;
                border-radius: 15px;
                padding: 25px;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
                border: 1px solid var(--border);
            }

            .section-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
                padding-bottom: 15px;
                border-bottom: 2px solid var(--light-gray);
            }

            .section-title {
                font-size: 20px;
                color: var(--dark);
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .section-badge {
                background: var(--peach);
                color: white;
                padding: 3px 10px;
                border-radius: 15px;
                font-size: 12px;
                font-weight: 500;
            }

            /* Order List */
            .order-list {
                list-style: none;
            }

            .order-item {
                padding: 15px;
                border-bottom: 1px solid var(--border);
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                transition: all 0.3s;
            }

            .order-item:hover {
                background: var(--light-gray);
            }

            .order-item:last-child {
                border-bottom: none;
            }

            .order-info {
                flex: 1;
            }

            .order-name {
                font-weight: 500;
                margin-bottom: 5px;
                color: var(--dark);
            }

            .order-meta {
                display: flex;
                gap: 15px;
                font-size: 12px;
                color: var(--gray);
                margin-bottom: 10px;
                flex-wrap: wrap;
            }

            .order-amount {
                text-align: right;
                min-width: 120px;
            }

            .order-total {
                font-weight: 600;
                color: var(--pink);
                font-size: 16px;
                margin-bottom: 5px;
            }

            .order-date {
                font-size: 12px;
                color: var(--gray);
            }

            /* Payment History Details */
            .payment-details {
                background: #f8f9fa;
                border-radius: 8px;
                padding: 12px;
                margin-top: 10px;
                font-size: 13px;
            }

            .payment-detail-row {
                display: flex;
                justify-content: space-between;
                margin-bottom: 6px;
            }

            .payment-detail-label {
                font-weight: 500;
                color: var(--dark);
                min-width: 80px;
            }

            .payment-detail-value {
                color: var(--gray);
                text-align: right;
            }

            /* Status Badges */
            .status-badge {
                display: inline-block;
                padding: 4px 12px;
                border-radius: 20px;
                font-size: 11px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .status-pending {
                background: #fff3cd;
                color: #856404;
                border: 1px solid #ffeaa7;
            }

            .status-verified {
                background: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
            }

            .status-rejected {
                background: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
            }

            .status-cod_pending {
                background: #cce5ff;
                color: #004085;
                border: 1px solid #b8daff;
            }

            /* Payment Methods */
            .payment-methods-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 15px;
                margin-bottom: 25px;
            }

            .payment-method {
                background: var(--light-gray);
                padding: 15px;
                border-radius: 10px;
                border: 2px solid transparent;
                transition: all 0.3s;
                cursor: pointer;
            }

            .payment-method:hover {
                border-color: var(--pink);
                background: white;
            }

            .payment-method.selected {
                border-color: var(--pink);
                background: white;
                box-shadow: 0 5px 15px rgba(255, 107, 147, 0.1);
            }

            .method-icon {
                font-size: 24px;
                color: var(--purple);
                margin-bottom: 10px;
            }

            .method-name {
                font-weight: 500;
                margin-bottom: 5px;
                color: var(--dark);
            }

            .method-details {
                font-size: 12px;
                color: var(--gray);
            }

            /* Upload Section */
            .upload-section {
                background: white;
                border-radius: 15px;
                padding: 30px;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
                margin-top: 30px;
                border: 1px solid var(--border);
            }

            .upload-form {
                margin-top: 20px;
            }

            .form-group {
                margin-bottom: 20px;
            }

            .form-label {
                display: block;
                margin-bottom: 8px;
                font-weight: 500;
                color: var(--dark);
                font-size: 14px;
            }

            .form-control {
                width: 100%;
                padding: 12px 15px;
                border: 2px solid #e0e0e0;
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

            .form-select {
                appearance: none;
                background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%23333' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
                background-repeat: no-repeat;
                background-position: right 15px center;
                background-size: 12px;
                padding-right: 35px;
            }

            /* Payment Details Form */
            .payment-details-form {
                background: #f8f9fa;
                border-radius: 10px;
                padding: 20px;
                margin-bottom: 25px;
            }

            .form-row {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 15px;
                margin-bottom: 15px;
            }

            @media (max-width: 768px) {
                .form-row {
                    grid-template-columns: 1fr;
                }
            }

            /* File Upload */
            .file-upload-area {
                border: 2px dashed #e0e0e0;
                border-radius: 10px;
                padding: 30px;
                text-align: center;
                cursor: pointer;
                transition: all 0.3s;
            }

            .file-upload-area:hover {
                border-color: var(--pink);
                background: rgba(255, 107, 147, 0.05);
            }

            .upload-icon {
                font-size: 40px;
                color: var(--purple);
                margin-bottom: 15px;
            }

            .upload-text {
                color: var(--gray);
                margin-bottom: 5px;
                font-size: 14px;
            }

            .upload-hint {
                font-size: 12px;
                color: var(--gray);
            }

            .file-preview {
                margin-top: 15px;
                text-align: center;
            }

            .preview-image {
                max-width: 200px;
                max-height: 200px;
                border-radius: 8px;
                box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            }

            /* Buttons */
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
                font-size: 14px;
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

            .btn-full {
                width: 100%;
            }

            /* Alert Messages */
            .alert {
                padding: 15px;
                border-radius: 8px;
                margin-bottom: 20px;
                display: flex;
                align-items: center;
                gap: 10px;
                animation: fadeIn 0.5s ease;
            }

            @keyframes fadeIn {
                from {
                    opacity: 0;
                    transform: translateY(-10px);
                }

                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            .alert-success {
                background: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
            }

            .alert-danger {
                background: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
            }

            .alert-icon {
                font-size: 20px;
            }

            /* Empty State */
            .empty-state {
                text-align: center;
                padding: 40px 20px;
                color: var(--gray);
            }

            .empty-icon {
                font-size: 48px;
                margin-bottom: 15px;
                color: #e0e0e0;
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

                .payment-sections {
                    grid-template-columns: 1fr;
                    gap: 20px;
                }

                .order-item {
                    flex-direction: column;
                    gap: 10px;
                }

                .order-amount {
                    text-align: left;
                    width: 100%;
                }

                .order-meta {
                    flex-direction: column;
                    gap: 5px;
                }
            }

            @media (max-width: 576px) {
                .main-content {
                    padding: 15px;
                }

                .page-header {
                    padding: 20px;
                }

                .payment-section {
                    padding: 20px;
                }

                .upload-section {
                    padding: 20px;
                }
            }

            /* Untuk toggle COD */
            .cod-instruction {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 8px;
                margin-top: 10px;
                border-left: 4px solid var(--peach);
            }

            .cod-instruction h4 {
                color: var(--peach);
                margin-bottom: 8px;
                font-size: 14px;
            }

            .cod-instruction p {
                font-size: 13px;
                color: var(--gray);
                margin-bottom: 5px;
            }

            /* Hidden class untuk menyembunyikan elemen */
            .hidden {
                display: none !important;
            }
        </style>
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
                                    <a href="payment.php" class="menu-link active">
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
                            <a href="../logout.php" class="menu-link" onclick="return confirmLogout(event)">
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
                        <i class="fas fa-credit-card"></i> Pembayaran Pesanan
                    </h1>
                    <p>Lakukan pembayaran dan upload bukti pembayaran untuk melanjutkan proses pesanan Anda</p>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle alert-icon"></i>
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle alert-icon"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <!-- Summary Card -->
                <div class="summary-card">
                    <div class="summary-content">
                        <div class="summary-info">
                            <h2>Total Pembayaran Tertunda</h2>
                            <p>Lakukan pembayaran untuk melanjutkan proses pesanan</p>
                        </div>
                        <div class="summary-amount">
                            <div class="amount-label">Total yang harus dibayar</div>
                            <div class="amount-value">Rp <?php echo number_format($totalPending, 0, ',', '.'); ?></div>
                        </div>
                    </div>
                </div>

                <div class="payment-sections">
                    <!-- Pending Payments -->
                    <div class="payment-section">
                        <div class="section-header">
                            <h2 class="section-title">
                                <i class="fas fa-clock"></i> Menunggu Pembayaran
                                <?php if (!empty($pendingOrders)): ?>
                                    <span class="section-badge"><?php echo count($pendingOrders); ?></span>
                                <?php endif; ?>
                            </h2>
                        </div>

                        <?php if (empty($pendingOrders)): ?>
                            <div class="empty-state">
                                <div class="empty-icon">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <h3>Tidak ada pembayaran tertunda</h3>
                                <p style="margin-bottom: 20px;">Semua pesanan Anda sudah dibayar atau sedang diproses.</p>
                                <a href="order.php" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Buat Pesanan Baru
                                </a>
                            </div>
                        <?php else: ?>
                            <ul class="order-list">
                                <?php foreach ($pendingOrders as $order): ?>
                                    <li class="order-item">
                                        <div class="order-info">
                                            <div class="order-name"><?php echo htmlspecialchars($order['product_name']); ?></div>
                                            <div class="order-meta">
                                                <span><i class="fas fa-hashtag"></i>
                                                    <?php echo htmlspecialchars($order['order_code']); ?></span>
                                                <span><i class="fas fa-box"></i> <?php echo $order['quantity']; ?> pcs</span>
                                                <span><i class="far fa-calendar"></i>
                                                    <?php echo date('d M Y', strtotime($order['created_at'])); ?></span>
                                            </div>
                                        </div>
                                        <div class="order-amount">
                                            <div class="order-total">Rp
                                                <?php echo number_format($order['total_price'], 0, ',', '.'); ?>
                                            </div>
                                            <div class="order-date">Menunggu pembayaran</div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <!-- Payment History -->
                    <div class="payment-section">
                        <div class="section-header">
                            <h2 class="section-title">
                                <i class="fas fa-history"></i> Riwayat Pembayaran
                            </h2>
                        </div>

                        <?php if (empty($paymentHistory)): ?>
                            <div class="empty-state">
                                <div class="empty-icon">
                                    <i class="fas fa-receipt"></i>
                                </div>
                                <h3>Belum ada riwayat pembayaran</h3>
                                <p>Setelah Anda melakukan pembayaran, riwayat akan muncul di sini.</p>
                            </div>
                        <?php else: ?>
                            <ul class="order-list">
                                <?php foreach ($paymentHistory as $payment):
                                    $statusClass = 'status-' . str_replace('_', '-', $payment['payment_status']);
                                    $statusText = ucfirst(str_replace('_', ' ', $payment['payment_status']));
                                    ?>
                                    <li class="order-item">
                                        <div class="order-info">
                                            <div class="order-name"><?php echo htmlspecialchars($payment['product_name']); ?></div>
                                            <div class="order-meta">
                                                <span><i class="fas fa-hashtag"></i>
                                                    <?php echo htmlspecialchars($payment['order_code']); ?></span>
                                                <span><i class="fas fa-box"></i> <?php echo $payment['quantity']; ?> pcs</span>
                                            </div>
                                            <div class="payment-details">
                                                <div class="payment-detail-row">
                                                    <span class="payment-detail-label">Metode:</span>
                                                    <span
                                                        class="payment-detail-value"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $payment['payment_method']))); ?></span>
                                                </div>
                                                <?php if ($payment['bank_name']): ?>
                                                    <div class="payment-detail-row">
                                                        <span class="payment-detail-label">Bank:</span>
                                                        <span
                                                            class="payment-detail-value"><?php echo htmlspecialchars($payment['bank_name']); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="payment-detail-row">
                                                    <span class="payment-detail-label">Status:</span>
                                                    <span class="payment-detail-value status-badge <?php echo $statusClass; ?>">
                                                        <?php echo $statusText; ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="order-amount">
                                            <div class="order-total">Rp
                                                <?php echo number_format($payment['amount'], 0, ',', '.'); ?>
                                            </div>
                                            <div class="order-date">
                                                <i class="far fa-clock"></i>
                                                <?php echo date('d M Y', strtotime($payment['payment_date'] ?: $payment['created_at'])); ?>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Payment Instructions -->
                <?php if (!empty($pendingOrders)): ?>
                    <div class="payment-section">
                        <div class="section-header">
                            <h2 class="section-title">
                                <i class="fas fa-university"></i> Instruksi Pembayaran
                            </h2>
                        </div>

                        <div class="payment-methods-grid">
                            <div class="payment-method selected" data-method="bank_transfer">
                                <div class="method-icon">
                                    <i class="fas fa-university"></i>
                                </div>
                                <div class="method-name">Transfer Bank</div>
                                <div class="method-details">
                                    BCA: 1234567890<br>
                                    a.n. Roncelizz Store
                                </div>
                            </div>

                            <div class="payment-method" data-method="ewallet">
                                <div class="method-icon">
                                    <i class="fas fa-wallet"></i>
                                </div>
                                <div class="method-name">E-Wallet</div>
                                <div class="method-details">
                                    DANA/OVO/ShopeePay<br>
                                    0812-3456-7890
                                </div>
                            </div>

                            <div class="payment-method" data-method="cod">
                                <div class="method-icon">
                                    <i class="fas fa-money-bill-wave"></i>
                                </div>
                                <div class="method-name">COD</div>
                                <div class="method-details">
                                    Cash on Delivery<br>
                                    Bayar saat barang diterima
                                </div>
                            </div>
                        </div>

                        <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; margin-top: 20px;">
                            <h3 style="color: var(--purple); margin-bottom: 10px; font-size: 16px;">Cara Pembayaran:</h3>
                            <ol style="color: var(--gray); line-height: 1.8; padding-left: 20px; font-size: 14px;">
                                <li>Pilih metode pembayaran yang diinginkan</li>
                                <li>Untuk Transfer Bank/E-Wallet: Upload bukti pembayaran</li>
                                <li>Untuk COD: Langsung konfirmasi pesanan</li>
                                <li>Admin akan memverifikasi dalam 1x24 jam</li>
                                <li>Pesanan akan diproses setelah pembayaran dikonfirmasi</li>
                            </ol>
                        </div>
                    </div>

                    <!-- Upload Payment Proof -->
                    <div class="upload-section">
                        <div class="section-header">
                            <h2 class="section-title">
                                <i class="fas fa-upload"></i> Konfirmasi Pembayaran
                            </h2>
                        </div>

                        <form method="POST" action="" enctype="multipart/form-data" class="upload-form" id="paymentForm">
                            <div class="form-group">
                                <label class="form-label">Pilih Pesanan yang akan Dibayar *</label>
                                <select name="order_id" class="form-control form-select" required id="orderSelect">
                                    <option value="">-- Pilih Pesanan --</option>
                                    <?php foreach ($pendingOrders as $order): ?>
                                        <option value="<?php echo $order['id']; ?>"
                                            data-amount="<?php echo $order['total_price']; ?>">
                                            Order #<?php echo htmlspecialchars($order['order_code']); ?> -
                                            <?php echo htmlspecialchars($order['product_name']); ?> -
                                            Rp <?php echo number_format($order['total_price'], 0, ',', '.'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Payment Method Selection -->
                            <div class="payment-methods-grid" style="margin-bottom: 20px;">
                                <div class="payment-method selected" data-method="bank_transfer">
                                    <div class="method-icon">
                                        <i class="fas fa-university"></i>
                                    </div>
                                    <div class="method-name">Transfer Bank</div>
                                    <div class="method-details">
                                        BCA: 1234567890<br>
                                        a.n. Roncelizz Store
                                    </div>
                                </div>

                                <div class="payment-method" data-method="ewallet">
                                    <div class="method-icon">
                                        <i class="fas fa-wallet"></i>
                                    </div>
                                    <div class="method-name">E-Wallet</div>
                                    <div class="method-details">
                                        DANA/OVO/ShopeePay<br>
                                        0812-3456-7890
                                    </div>
                                </div>

                                <div class="payment-method" data-method="cod">
                                    <div class="method-icon">
                                        <i class="fas fa-money-bill-wave"></i>
                                    </div>
                                    <div class="method-name">COD</div>
                                    <div class="method-details">
                                        Cash on Delivery<br>
                                        Bayar saat barang diterima
                                    </div>
                                </div>
                            </div>

                            <!-- Hidden input untuk payment method -->
                            <input type="hidden" name="payment_method" id="paymentMethod" value="bank_transfer">

                            <!-- COD Instruction -->
                            <div id="codInstruction" class="cod-instruction hidden">
                                <h4><i class="fas fa-info-circle"></i> Informasi COD</h4>
                                <p>• Anda akan membayar secara langsung saat barang diterima</p>
                                <p>• Pastikan alamat pengiriman sudah benar</p>
                                <p>• Siapkan uang tunai sesuai jumlah pesanan</p>
                                <p>• Admin akan menghubungi Anda untuk konfirmasi</p>
                            </div>

                            <!-- Payment Details Form (hanya untuk non-COD) -->
                            <div id="paymentDetailsForm" class="payment-details-form">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Nama Bank (jika transfer)</label>
                                        <input type="text" name="bank_name" class="form-control" id="bankName"
                                            placeholder="Contoh: BCA, Mandiri, BNI">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Nomor Rekening/Akun</label>
                                        <input type="text" name="account_number" class="form-control" id="accountNumber"
                                            placeholder="Nomor rekening atau akun e-wallet">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Jumlah Dibayar *</label>
                                        <input type="text" name="amount" class="form-control" id="amountInput" readonly
                                            placeholder="Otomatis terisi berdasarkan pesanan">
                                    </div>
                                </div>
                            </div>

                            <!-- File Upload (hanya untuk non-COD) -->
                            <div id="fileUploadSection">
                                <div class="form-group">
                                    <label class="form-label">Upload Bukti Pembayaran *</label>
                                    <div class="file-upload-area" id="uploadArea">
                                        <input type="file" name="payment_proof" id="payment_proof" accept="image/*,.pdf" hidden
                                            onchange="previewFile(this)">
                                        <div class="upload-icon">
                                            <i class="fas fa-cloud-upload-alt"></i>
                                        </div>
                                        <div class="upload-text">Klik untuk upload bukti pembayaran</div>
                                        <div class="upload-hint">Format: JPG, PNG, PDF | Maks: 2MB</div>
                                    </div>
                                    <div class="file-preview" id="filePreview"></div>
                                </div>
                            </div>

                            <button type="submit" name="confirm_payment" class="btn btn-primary btn-full" id="submitButton">
                                <i class="fas fa-paper-plane"></i> <span id="submitText">Kirim Bukti Pembayaran</span>
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <script>
            // Payment method selection
            const paymentMethods = document.querySelectorAll('.payment-method');
            const paymentMethodInput = document.getElementById('paymentMethod');
            const paymentDetailsForm = document.getElementById('paymentDetailsForm');
            const fileUploadSection = document.getElementById('fileUploadSection');
            const codInstruction = document.getElementById('codInstruction');
            const submitButton = document.getElementById('submitButton');
            const submitText = document.getElementById('submitText');
            const bankNameField = document.getElementById('bankName');
            const accountNumberField = document.getElementById('accountNumber');
            const paymentProofField = document.getElementById('payment_proof');

            paymentMethods.forEach(method => {
                method.addEventListener('click', function () {
                    paymentMethods.forEach(m => {
                        m.classList.remove('selected');
                    });
                    this.classList.add('selected');

                    const methodValue = this.getAttribute('data-method');
                    paymentMethodInput.value = methodValue;

                    // Toggle visibility berdasarkan metode pembayaran
                    if (methodValue === 'cod') {
                        paymentDetailsForm.classList.add('hidden');
                        fileUploadSection.classList.add('hidden');
                        codInstruction.classList.remove('hidden');
                        submitText.textContent = 'Konfirmasi Pesanan COD';

                        // Non-aktifkan required untuk field bank dan file upload
                        bankNameField.removeAttribute('required');
                        accountNumberField.removeAttribute('required');
                        paymentProofField.removeAttribute('required');
                    } else {
                        paymentDetailsForm.classList.remove('hidden');
                        fileUploadSection.classList.remove('hidden');
                        codInstruction.classList.add('hidden');
                        submitText.textContent = 'Kirim Bukti Pembayaran';

                        // Aktifkan required untuk field file upload
                        paymentProofField.setAttribute('required', 'required');

                        // Untuk transfer bank, aktifkan required untuk bank name
                        if (methodValue === 'bank_transfer') {
                            bankNameField.setAttribute('required', 'required');
                            accountNumberField.setAttribute('required', 'required');
                        } else {
                            bankNameField.removeAttribute('required');
                            accountNumberField.removeAttribute('required');
                        }
                    }
                });
            });

            // Update amount when order is selected
            const orderSelect = document.getElementById('orderSelect');
            const amountInput = document.getElementById('amountInput');

            orderSelect.addEventListener('change', function () {
                const selectedOption = this.options[this.selectedIndex];
                const amount = selectedOption.getAttribute('data-amount');
                if (amount) {
                    amountInput.value = 'Rp ' + formatRupiah(amount);
                } else {
                    amountInput.value = '';
                }
            });

            // File upload
            const uploadArea = document.getElementById('uploadArea');
            const fileInput = document.getElementById('payment_proof');

            uploadArea.addEventListener('click', function () {
                // Hanya izinkan klik jika bukan COD
                if (paymentMethodInput.value !== 'cod') {
                    fileInput.click();
                }
            });

            function previewFile(input) {
                const preview = document.getElementById('filePreview');

                if (input.files && input.files[0]) {
                    const file = input.files[0];
                    const reader = new FileReader();

                    reader.onload = function (e) {
                        if (file.type.startsWith('image/')) {
                            preview.innerHTML = `
                                <img src="${e.target.result}" class="preview-image" alt="Preview">
                                <p style="color: var(--gray); margin-top: 10px;">${file.name}</p>
                            `;
                        } else if (file.type === 'application/pdf') {
                            preview.innerHTML = `
                                <div style="background: #f0f0f0; padding: 20px; border-radius: 8px; text-align: center;">
                                    <i class="fas fa-file-pdf" style="font-size: 48px; color: #dc3545;"></i>
                                    <p style="color: var(--gray); margin-top: 10px;">${file.name}</p>
                                </div>
                            `;
                        }
                    };

                    reader.readAsDataURL(file);
                }
            }

            // Form validation
            const form = document.getElementById('paymentForm');
            form.addEventListener('submit', function (e) {
                const orderSelect = document.getElementById('orderSelect');
                const paymentMethod = paymentMethodInput.value;
                const fileInput = document.getElementById('payment_proof');
                const isCOD = (paymentMethod === 'cod');

                if (!orderSelect.value) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: 'Peringatan',
                        text: 'Silakan pilih pesanan terlebih dahulu!',
                        confirmButtonColor: '#ff6b93',
                    });
                    orderSelect.focus();
                    return;
                }

                if (!paymentMethod) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: 'Peringatan',
                        text: 'Silakan pilih metode pembayaran!',
                        confirmButtonColor: '#ff6b93',
                    });
                    return;
                }

                // Jika bukan COD, validasi file upload
                if (!isCOD) {
                    if (!fileInput.files.length) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'warning',
                            title: 'Peringatan',
                            text: 'Silakan upload bukti pembayaran!',
                            confirmButtonColor: '#ff6b93',
                        });
                        return;
                    }

                    const file = fileInput.files[0];
                    const maxSize = 2 * 1024 * 1024;

                    if (file.size > maxSize) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Ukuran file terlalu besar. Maksimal 2MB.',
                            confirmButtonColor: '#ff6b93',
                        });
                        return;
                    }

                    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
                    if (!allowedTypes.includes(file.type)) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Format file tidak didukung. Gunakan JPG, PNG, atau PDF.',
                            confirmButtonColor: '#ff6b93',
                        });
                        return;
                    }

                    // Validasi khusus untuk transfer bank
                    if (paymentMethod === 'bank_transfer') {
                        const bankName = document.getElementById('bankName').value;
                        const accountNumber = document.getElementById('accountNumber').value;

                        if (!bankName.trim()) {
                            e.preventDefault();
                            Swal.fire({
                                icon: 'warning',
                                title: 'Peringatan',
                                text: 'Silakan isi nama bank untuk transfer!',
                                confirmButtonColor: '#ff6b93',
                            });
                            bankNameField.focus();
                            return;
                        }

                        if (!accountNumber.trim()) {
                            e.preventDefault();
                            Swal.fire({
                                icon: 'warning',
                                title: 'Peringatan',
                                text: 'Silakan isi nomor rekening!',
                                confirmButtonColor: '#ff6b93',
                            });
                            accountNumberField.focus();
                            return;
                        }
                    }
                }

                // Konfirmasi untuk COD
                if (isCOD) {
                    e.preventDefault();
                    const selectedOrder = orderSelect.options[orderSelect.selectedIndex].text;
                    const amount = orderSelect.options[orderSelect.selectedIndex].getAttribute('data-amount');

                    Swal.fire({
                        title: 'Konfirmasi Pesanan COD',
                        html: `
                            <div style="text-align: left;">
                                <p><strong>Pesanan:</strong> ${selectedOrder.split(' - ')[1]}</p>
                                <p><strong>Total:</strong> Rp ${formatRupiah(amount)}</p>
                                <p><strong>Metode:</strong> Cash on Delivery</p>
                                <hr>
                                <p style="font-size: 14px; color: #666;">
                                    <i class="fas fa-info-circle"></i> Anda akan membayar saat barang diterima
                                </p>
                            </div>
                        `,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#ff6b93',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Ya, Konfirmasi COD',
                        cancelButtonText: 'Batal',
                        reverseButtons: true
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Submit form setelah konfirmasi
                            form.submit();
                        }
                    });
                    return false;
                }
            });

            // Format Rupiah helper
            function formatRupiah(angka) {
                return angka.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            }

            // Set default payment method
            document.querySelector('.payment-method.selected').click();

            // Fungsi konfirmasi logout
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
                return false;
            }
        </script>
    </body>

    </html>