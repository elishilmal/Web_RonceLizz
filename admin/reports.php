<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';

Auth::requireAdmin();

if (!class_exists('FPDF')) {
    require_once '../fpdf/fpdf.php';
}

class PDF extends FPDF
{
    // Page header
    function Header()
    {
        $logoPath = '../assets/images/logo.png';

        // Cek apakah file logo ada dan bisa dibaca
        if (file_exists($logoPath) && is_readable($logoPath)) {
            // Cek apakah file PNG valid
            $imageInfo = @getimagesize($logoPath);
            if ($imageInfo !== false && $imageInfo[2] === IMAGETYPE_PNG) {
                try {
                    $this->Image($logoPath, 10, 6, 30);
                    $this->SetX(45); // Pindah ke kanan setelah logo
                } catch (Exception $e) {
                    // Jika error, skip logo dan set posisi normal
                    error_log("Logo error: " . $e->getMessage());
                    $this->SetX(10);
                }
            } else {
                // File bukan PNG valid
                $this->SetX(10);
            }
        } else {
            // Logo tidak ditemukan, set posisi normal
            $this->SetX(10);
        }

        // Title
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, 'LAPORAN RONCELIZZ', 0, 1, 'C');
        $this->SetFont('Arial', 'I', 12);
        $this->Cell(0, 10, 'Toko Online Manik-Manik', 0, 1, 'C');

        // Line break
        $this->Ln(10);
    }

    // Page footer
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Halaman ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    // Fungsi untuk membersihkan dan memotong teks
    function cleanText($text, $maxLength = 25)
    {
        if (!is_string($text)) {
            return '';
        }

        // Bersihkan teks dari karakter aneh
        $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
        $text = preg_replace('/[^\x20-\x7E\xA0-\xFF]/', '', $text);
        $text = trim($text);

        return $text;
    }

    // Sales table dengan MultiCell untuk wrap teks
    function SalesTable($header, $data)
    {
        $this->SetFillColor(255, 182, 217); 
        $this->SetFont('Arial', 'B', 10);
        $w = array(35, 60, 20, 35, 30);

        for ($i = 0; $i < count($header); $i++)
            $this->Cell($w[$i], 8, $header[$i], 1, 0, 'C', true);
        $this->Ln();

        $this->SetFont('Arial', '', 9);
        foreach ($data as $row) {
            $lineHeight = 6;
            $nbLines = ceil($this->GetStringWidth($row[1]) / ($w[1] - 2));
            $cellHeight = $nbLines * $lineHeight;
            if ($cellHeight < 8)
                $cellHeight = 8;

            $x = $this->GetX();
            $y = $this->GetY();

            $this->Rect($x, $y, $w[0], $cellHeight);
            $this->MultiCell($w[0], $cellHeight, $row[0], 0, 'C');

            $this->SetXY($x + $w[0], $y);
            $this->Rect($x + $w[0], $y, $w[1], $cellHeight);
            $this->MultiCell($w[1], $lineHeight, $row[1], 0, 'C');

            $this->SetXY($x + $w[0] + $w[1], $y);
            $this->Rect($x + $w[0] + $w[1], $y, $w[2], $cellHeight);
            $this->MultiCell($w[2], $cellHeight, $row[2], 0, 'C');

            $this->SetXY($x + $w[0] + $w[1] + $w[2], $y);
            $this->Rect($x + $w[0] + $w[1] + $w[2], $y, $w[3], $cellHeight);
            $this->MultiCell($w[3], $cellHeight, $row[3], 0, 'LR');

            $this->SetXY($x + $w[0] + $w[1] + $w[2] + $w[3], $y);
            $this->Rect($x + $w[0] + $w[1] + $w[2] + $w[3], $y, $w[4], $cellHeight);
            $this->MultiCell($w[4], $cellHeight, $row[4], 0, 'C');

            $this->SetY($y + $cellHeight);
        }
    }

    // Products table
    function ProductsTable($header, $data)
    {
        // Colors, line width and bold font
        $this->SetFillColor(182, 215, 255); 
        $this->SetTextColor(0);
        $this->SetDrawColor(0);
        $this->SetLineWidth(.3);
        $this->SetFont('', 'B');

        // Header
        $w = array(60, 50, 30, 30, 20);
        for ($i = 0; $i < count($header); $i++)
            $this->Cell($w[$i], 7, $header[$i], 1, 0, 'C', true);
        $this->Ln();

        // Color and font restoration
        $this->SetFillColor(229, 242, 255);
        $this->SetTextColor(0);
        $this->SetFont('');

        // Data
        $fill = false;
        foreach ($data as $row) {
            $this->Cell($w[0], 6, $row[0], 'LR', 0, 'C', $fill);
            $this->Cell($w[1], 6, $row[1], 'LR', 0, 'C', $fill);
            $this->Cell($w[2], 6, 'Rp ' . number_format($row[2]), 'LR', 0, 'R', $fill);
            $this->Cell($w[3], 6, $row[3], 'LR', 0, 'C', $fill);
            $this->Cell($w[4], 6, $row[4], 'LR', 0, 'C', $fill);
            $this->Ln();
            $fill = !$fill;
        }

        // Closing line
        $this->Cell(array_sum($w), 0, '', 'T');
    }

    // Users table
    function UsersTable($header, $data)
    {
        // Pengaturan warna header 
        $this->SetFillColor(182, 255, 194); 
        $this->SetTextColor(0);
        $this->SetDrawColor(0);
        $this->SetLineWidth(.3);
        $this->SetFont('Arial', 'B', 10);

        // ID(15), Username(40), Email(50), Nama(50), Role(35)
        $w = array(15, 40, 50, 50, 35);

        for ($i = 0; $i < count($header); $i++)
            $this->Cell($w[$i], 8, $header[$i], 1, 0, 'C', true);
        $this->Ln();

        // Restorasi font untuk isi data
        $this->SetFont('Arial', '', 9);

        foreach ($data as $row) {
            $lineHeight = 6;
            $linesEmail = ceil($this->GetStringWidth($row[2]) / ($w[2] - 2));
            $linesNama = ceil($this->GetStringWidth($row[3]) / ($w[3] - 2));
            $maxLines = max($linesEmail, $linesNama);

            $cellHeight = $maxLines * $lineHeight;
            if ($cellHeight < 8)
                $cellHeight = 8; 

            $x = $this->GetX();
            $y = $this->GetY();

            // Kolom 1: ID
            $this->Rect($x, $y, $w[0], $cellHeight);
            $this->MultiCell($w[0], $cellHeight, $row[0], 0, 'C');

            // Kolom 2: Username
            $this->SetXY($x + $w[0], $y);
            $this->Rect($x + $w[0], $y, $w[1], $cellHeight);
            $this->MultiCell($w[1], $cellHeight, $row[1], 0, 'L');

            // Kolom 3: Email 
            $this->SetXY($x + $w[0] + $w[1], $y);
            $this->Rect($x + $w[0] + $w[1], $y, $w[2], $cellHeight);
            $this->MultiCell($w[2], $lineHeight, $row[2], 0, 'L');

            // Kolom 4: Nama 
            $this->SetXY($x + $w[0] + $w[1] + $w[2], $y);
            $this->Rect($x + $w[0] + $w[1] + $w[2], $y, $w[3], $cellHeight);
            $this->MultiCell($w[3], $lineHeight, $row[3], 0, 'L');

            // Kolom 5: Role
            $this->SetXY($x + $w[0] + $w[1] + $w[2] + $w[3], $y);
            $this->Rect($x + $w[0] + $w[1] + $w[2] + $w[3], $y, $w[4], $cellHeight);
            $this->MultiCell($w[4], $cellHeight, $row[4], 0, 'C');

            // Pindah ke baris baru berdasarkan tinggi kotak tertinggi
            $this->SetY($y + $cellHeight);
        }
    }

    // Requests table dengan MultiCell untuk wrap teks
    function RequestsTable($header, $data)
    {
        // Pengaturan warna header
        $this->SetFillColor(255, 242, 182);
        $this->SetFont('Arial', 'B', 10);
        $w = array(35, 60, 30, 35, 30); 

        for ($i = 0; $i < count($header); $i++)
            $this->Cell($w[$i], 8, $header[$i], 1, 0, 'C', true);
        $this->Ln();

        $this->SetFont('Arial', '', 9);
        foreach ($data as $row) {
            $lineHeight = 6;
            $nbLines = ceil($this->GetStringWidth($row[1]) / ($w[1] - 2));
            $cellHeight = $nbLines * $lineHeight;
            if ($cellHeight < 8)
                $cellHeight = 8; 

            $x = $this->GetX();
            $y = $this->GetY();

            // Kolom 1: Kode
            $this->Rect($x, $y, $w[0], $cellHeight); 
            $this->MultiCell($w[0], $cellHeight, $row[0], 0, 'C');

            // Kolom 2: Produk (Wrap Text)
            $this->SetXY($x + $w[0], $y); 
            $this->Rect($x + $w[0], $y, $w[1], $cellHeight);
            $this->MultiCell($w[1], $lineHeight, $row[1], 0, 'C');

            // Kolom 3: User
            $this->SetXY($x + $w[0] + $w[1], $y);
            $this->Rect($x + $w[0] + $w[1], $y, $w[2], $cellHeight);
            $this->MultiCell($w[2], $cellHeight, $row[2], 0, 'C');

            // Kolom 4: Budget
            $this->SetXY($x + $w[0] + $w[1] + $w[2], $y);
            $this->Rect($x + $w[0] + $w[1] + $w[2], $y, $w[3], $cellHeight);
            $this->MultiCell($w[3], $cellHeight, $row[3], 0, 'LR');

            // Kolom 5: Status
            $this->SetXY($x + $w[0] + $w[1] + $w[2] + $w[3], $y);
            $this->Rect($x + $w[0] + $w[1] + $w[2] + $w[3], $y, $w[4], $cellHeight);
            $this->MultiCell($w[4], $cellHeight, $row[4], 0, 'C');

            // Pindah ke baris baru dengan jarak sesuai tinggi kotak
            $this->SetY($y + $cellHeight);
        }
    }
}

// Generate PDF based on type
if (isset($_GET['type'])) {
    $type = $_GET['type'];

    if ($type == 'sales') {
        // Get sales data
        $stmt = $pdo->query("SELECT o.order_code, p.name, o.quantity, o.total_price, o.status, o.created_at 
                             FROM orders o 
                             JOIN products p ON o.product_id = p.id 
                             ORDER BY o.created_at DESC");
        $orders = $stmt->fetchAll();

        // Create PDF
        $pdf = new PDF();
        $pdf->AliasNbPages();
        $pdf->AddPage();

        // Report info
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, 'LAPORAN PENJUALAN', 0, 1, 'C');
        $pdf->Ln(5);

        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 10, 'Tanggal Generate: ' . date('d/m/Y H:i:s'), 0, 1);
        $pdf->Cell(0, 10, 'Dibuat oleh: ' . ($_SESSION['full_name'] ?? 'Admin'), 0, 1);
        $pdf->Ln(10);

        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, 'Statistik Penjualan:', 0, 1);
        $pdf->SetFont('Arial', '', 10);

        // Total orders
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM orders");
        $total_orders = $stmt->fetch()['total'];
        $pdf->Cell(0, 10, 'Total Pesanan: ' . number_format($total_orders), 0, 1);

        // Total revenue
        $stmt = $pdo->query("SELECT SUM(total_price) as total FROM orders WHERE status IN ('completed', 'processing', 'shipped', 'delivered')");
        $total_revenue = $stmt->fetch()['total'] ?: 0;
        $pdf->Cell(0, 10, 'Total Pendapatan: Rp ' . number_format($total_revenue), 0, 1);

        // Status breakdown
        $pdf->Ln(5);
        $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM orders GROUP BY status");
        $status_counts = $stmt->fetchAll();

        foreach ($status_counts as $status) {
            $pdf->Cell(0, 10, ucfirst($status['status']) . ': ' . number_format($status['count']) . ' pesanan', 0, 1);
        }

        $pdf->Ln(10);

        // Sales table
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, 'Detail Pesanan:', 0, 1);

        // Table header
        $header = array('Kode', 'Produk', 'Qty', 'Total', 'Status');

        // Table data dengan pembersihan teks 
        $data = [];
        foreach ($orders as $order) {
            $productName = $pdf->cleanText($order['name']); 
            $data[] = [
                $order['order_code'],
                $productName,
                $order['quantity'],
                'Rp ' . number_format($order['total_price']),
                ucfirst($order['status'])
            ];
        }

        $pdf->SalesTable($header, $data);

        // Output PDF
        $pdf->Output('D', 'Laporan_Penjualan_Roncelizz_' . date('Ymd_His') . '.pdf');
        exit();

    } elseif ($type == 'products') {
        // Product report
        $stmt = $pdo->query("SELECT p.*, c.name as category_name FROM products p 
                             LEFT JOIN categories c ON p.category_id = c.id 
                             WHERE p.type != 'request' 
                             ORDER BY p.created_at DESC");
        $products = $stmt->fetchAll();

        $pdf = new PDF();
        $pdf->AliasNbPages();
        $pdf->AddPage();

        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, 'LAPORAN PRODUK', 0, 1, 'C');
        $pdf->Ln(5);

        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 10, 'Tanggal Generate: ' . date('d/m/Y H:i:s'), 0, 1);
        $pdf->Ln(10);

        // Statistics
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, 'Statistik Produk:', 0, 1);
        $pdf->SetFont('Arial', '', 10);

        // Total products
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM products WHERE type != 'request'");
        $total_products = $stmt->fetch()['total'];
        $pdf->Cell(0, 10, 'Total Produk: ' . number_format($total_products), 0, 1);

        // Total stock
        $stmt = $pdo->query("SELECT SUM(stock) as total FROM products WHERE type != 'request'");
        $total_stock = $stmt->fetch()['total'] ?: 0;
        $pdf->Cell(0, 10, 'Total Stok: ' . number_format($total_stock), 0, 1);

        // Total value
        $stmt = $pdo->query("SELECT SUM(price * stock) as total FROM products WHERE type != 'request'");
        $total_value = $stmt->fetch()['total'] ?: 0;
        $pdf->Cell(0, 10, 'Total Nilai Stok: Rp ' . number_format($total_value), 0, 1);

        $pdf->Ln(10);

        // Products table
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, 'Daftar Produk:', 0, 1);

        // Table header
        $header = array('Nama Produk', 'Kategori', 'Harga', 'Stok', 'Status');

        // Table data dengan pembersihan teks
        $data = [];
        foreach ($products as $product) {
            $status = ($product['stock'] > 0) ? 'Tersedia' : 'Habis';
            $productName = $pdf->cleanText($product['name']);
            $categoryName = $pdf->cleanText($product['category_name'] ?? '-');

            $data[] = [
                $productName,
                $categoryName,
                $product['price'],
                $product['stock'],
                $status
            ];
        }

        $pdf->ProductsTable($header, $data);

        $pdf->Output('D', 'Laporan_Produk_Roncelizz_' . date('Ymd_His') . '.pdf');
        exit();

    } elseif ($type == 'users') {
        // Users report
        $stmt = $pdo->query("SELECT id, username, email, full_name, role, created_at 
                             FROM users 
                             ORDER BY created_at DESC");
        $users = $stmt->fetchAll();

        $pdf = new PDF();
        $pdf->AliasNbPages();
        $pdf->AddPage();

        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, 'LAPORAN PENGGUNA', 0, 1, 'C');
        $pdf->Ln(5);

        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 10, 'Tanggal Generate: ' . date('d/m/Y H:i:s'), 0, 1);
        $pdf->Ln(10);

        // Statistics
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, 'Statistik Pengguna:', 0, 1);
        $pdf->SetFont('Arial', '', 10);

        // Total users
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
        $total_users = $stmt->fetch()['total'];
        $pdf->Cell(0, 10, 'Total User: ' . number_format($total_users), 0, 1);

        // Total admins
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'admin'");
        $total_admins = $stmt->fetch()['total'];
        $pdf->Cell(0, 10, 'Total Admin: ' . number_format($total_admins), 0, 1);

        // New users (last 30 days)
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $new_users = $stmt->fetch()['total'];
        $pdf->Cell(0, 10, 'User Baru (30 hari): ' . number_format($new_users), 0, 1);

        $pdf->Ln(10);

        // Users table
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, 'Daftar Pengguna:', 0, 1);

        // Table header
        $header = array('ID', 'Username', 'Email', 'Nama', 'Role');

        // Table data dengan pembersihan teks
        $data = [];
        foreach ($users as $user) {
            $data[] = [
                $user['id'],
                $pdf->cleanText($user['username']),
                $pdf->cleanText($user['email']),
                $pdf->cleanText($user['full_name'] ?? '-'),
                ucfirst($user['role'])
            ];
        }
        $pdf->UsersTable($header, $data);

        $pdf->Output('D', 'Laporan_User_Roncelizz_' . date('Ymd_His') . '.pdf');
        exit();

    } elseif ($type == 'requests') {
        // Requests report
        $stmt = $pdo->query("SELECT r.*, u.username, c.name as category_name 
                             FROM requests r 
                             JOIN users u ON r.user_id = u.id 
                             LEFT JOIN categories c ON r.category_id = c.id 
                             ORDER BY r.created_at DESC");
        $requests = $stmt->fetchAll();

        $pdf = new PDF();
        $pdf->AliasNbPages();
        $pdf->AddPage();

        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, 'LAPORAN REQUEST PRODUK', 0, 1, 'C');
        $pdf->Ln(5);

        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 10, 'Tanggal Generate: ' . date('d/m/Y H:i:s'), 0, 1);
        $pdf->Ln(10);

        // Statistics
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, 'Statistik Request:', 0, 1);
        $pdf->SetFont('Arial', '', 10);

        // Total requests
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM requests");
        $total_requests = $stmt->fetch()['total'];
        $pdf->Cell(0, 10, 'Total Request: ' . number_format($total_requests), 0, 1);

        // Total approved budget
        $stmt = $pdo->query("SELECT SUM(budget) as total FROM requests WHERE status = 'approved'");
        $total_budget = $stmt->fetch()['total'] ?: 0;
        $pdf->Cell(0, 10, 'Total Budget Approved: Rp ' . number_format($total_budget), 0, 1);

        // Status breakdown
        $pdf->Ln(5);
        $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM requests GROUP BY status");
        $status_counts = $stmt->fetchAll();

        foreach ($status_counts as $status) {
            $pdf->Cell(0, 10, ucfirst($status['status']) . ': ' . number_format($status['count']) . ' request', 0, 1);
        }

        $pdf->Ln(10);

        // Requests table
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, 'Daftar Request:', 0, 1);

        // Table header
        $header = array('Kode', 'Produk', 'User', 'Budget', 'Status');

        // Table data dengan pembersihan teks
        $data = [];
        foreach ($requests as $request) {
            $productName = $pdf->cleanText($request['product_name']); 
            $userName = $pdf->cleanText($request['username']);

            $data[] = [
                $request['request_code'],
                $productName,
                $userName,
                $request['budget'] ? 'Rp ' . number_format($request['budget']) : '-',
                ucfirst($request['status'])
            ];
        }

        $pdf->RequestsTable($header, $data);

        $pdf->Output('D', 'Laporan_Request_Roncelizz_' . date('Ymd_His') . '.pdf');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan PDF - Roncelizz</title>
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

        /* Report Cards */
        .report-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .report-card {
            background: var(--white);
            border-radius: var(--border-radius-medium);
            padding: 30px;
            box-shadow: var(--shadow-soft);
            border: 2px solid transparent;
            transition: all 0.3s;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .report-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(to right, var(--pink), var(--purple), var(--mint));
        }

        .report-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-medium);
            border-color: var(--pink);
        }

        .report-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--pink-light), var(--purple-light));
            color: white;
            border-radius: var(--border-radius-circle);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2.5rem;
            transition: all 0.3s ease;
            border: 2px solid var(--pink-light);
        }

        .report-card:hover .report-icon {
            transform: scale(1.1) rotate(5deg);
            background: linear-gradient(135deg, var(--pink), var(--purple));
        }

        .report-card h3 {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 15px;
        }

        .report-card p {
            color: var(--gray);
            font-size: 0.95rem;
            line-height: 1.5;
            margin-bottom: 20px;
        }

        .report-card .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: var(--border-radius-small);
            font-weight: 500;
            transition: all 0.3s;
        }

        .report-card .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-soft);
        }

        /* Petunjuk Card */
        .instruction-card {
            background: var(--white);
            border-radius: var(--border-radius-medium);
            padding: 25px;
            box-shadow: var(--shadow-soft);
            border: 2px solid transparent;
            transition: all 0.3s;
        }

        .instruction-card:hover {
            border-color: var(--purple);
        }

        .instruction-card .card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--pink-light);
        }

        .instruction-card .card-header h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
        }

        .instruction-card ol {
            padding-left: 20px;
            margin-bottom: 20px;
        }

        .instruction-card li {
            margin-bottom: 10px;
            line-height: 1.5;
            color: var(--gray-dark);
        }

        .alert {
            background: #e7f3ff;
            border-left: 4px solid var(--purple);
            padding: 15px;
            border-radius: var(--border-radius-small);
            display: flex;
            align-items: flex-start;
            gap: 15px;
            margin-top: 20px;
        }

        .alert i {
            color: var(--purple);
            font-size: 1.2rem;
            margin-top: 2px;
        }

        .alert strong {
            color: var(--dark);
            display: block;
            margin-bottom: 5px;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .report-cards {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .report-card {
                padding: 25px;
            }

            .report-icon {
                width: 70px;
                height: 70px;
                font-size: 2rem;
            }
        }

        @media (max-width: 480px) {
            .report-card {
                padding: 20px;
            }

            .report-icon {
                width: 60px;
                height: 60px;
                font-size: 1.8rem;
            }
        }
    </style>
</head>

<body>
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">

        <div class="header">
            <div>
                <h1 class="page-title"><i class="fas fa-chart-line"></i> Generate Laporan PDF 📊</h1>
                <p class="page-subtitle">Pilih jenis laporan yang ingin di-generate</p>
            </div>
        </div>

        <!-- Report Cards -->
        <div class="report-cards">
            <!-- Sales Report -->
            <div class="report-card">
                <div class="report-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h3>Laporan Penjualan</h3>
                <p>Laporan detail penjualan, pendapatan, dan statistik pesanan. Mencakup semua transaksi yang telah
                    dilakukan.</p>
                <a href="?type=sales" class="btn btn-primary" target="_blank">
                    <i class="fas fa-download"></i> Download PDF
                </a>
            </div>

            <!-- Products Report -->
            <div class="report-card">
                <div class="report-icon">
                    <i class="fas fa-box-open"></i>
                </div>
                <h3>Laporan Produk</h3>
                <p>Daftar semua produk dengan kategori, harga, stok, dan nilai total persediaan.</p>
                <a href="?type=products" class="btn btn-info" target="_blank">
                    <i class="fas fa-download"></i> Download PDF
                </a>
            </div>

            <!-- Users Report -->
            <div class="report-card">
                <div class="report-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3>Laporan User</h3>
                <p>Data pengguna, role, dan statistik registrasi. Cocok untuk analisis demografi pelanggan.</p>
                <a href="?type=users" class="btn btn-info" target="_blank">
                    <i class="fas fa-download"></i> Download PDF
                </a>
            </div>

            <!-- Requests Report -->
            <div class="report-card">
                <div class="report-icon">
                    <i class="fas fa-comment-dots"></i>
                </div>
                <h3>Laporan Request</h3>
                <p>Request produk dari user beserta status, budget, dan detail permintaan.</p>
                <a href="?type=requests" class="btn btn-warning" target="_blank">
                    <i class="fas fa-download"></i> Download PDF
                </a>
            </div>
        </div>

        <!-- Petunjuk Penggunaan -->
        <div class="instruction-card">
            <div class="card-header">
                <i class="fas fa-info-circle" style="color: var(--purple); font-size: 1.3rem;"></i>
                <h3>Petunjuk Penggunaan</h3>
            </div>
            <ol>
                <li><strong>Pilih jenis laporan</strong> yang diinginkan dari kartu di atas</li>
                <li>Klik tombol <strong>"Download PDF"</strong> pada kartu yang dipilih</li>
                <li>Laporan akan otomatis terdownload dalam format PDF</li>
                <li>Data dalam laporan adalah data real-time saat generate dilakukan</li>
                <li>Laporan dapat dicetak atau disimpan untuk dokumentasi dan analisis</li>
            </ol>

            <div class="alert">
                <i class="fas fa-exclamation-circle"></i>
                <div>
                    <strong>Informasi Penting!</strong><br>
                    Pastikan library FPDF sudah terinstall di folder <code>fpdf/</code>.
                    Laporan akan mencakup semua data yang tersimpan di database hingga saat generate dilakukan.
                    Proses download mungkin memerlukan waktu beberapa detik tergantung jumlah data.
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle mobile menu
        document.addEventListener('DOMContentLoaded', function () {
            const menuBtn = document.getElementById('menuToggle');
            const sidebar = document.getElementById('sidebar');

            if (menuBtn && sidebar) {
                menuBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    sidebar.classList.toggle('active');
                });

                document.addEventListener('click', function (event) {
                    if (window.innerWidth <= 768) {
                        if (!sidebar.contains(event.target) &&
                            !menuBtn.contains(event.target) &&
                            sidebar.classList.contains('active')) {
                            sidebar.classList.remove('active');
                        }
                    }
                });

                // Close sidebar on window resize
                window.addEventListener('resize', function () {
                    if (window.innerWidth > 768 && sidebar.classList.contains('active')) {
                        sidebar.classList.remove('active');
                    }
                });

                // Close sidebar with Escape key
                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape' && sidebar.classList.contains('active')) {
                        sidebar.classList.remove('active');
                    }
                });
            }

            // Fungsi konfirmasi logout dengan modal
            const logoutLink = document.querySelector('a[href="../includes/logout.php"]');
            if (logoutLink) {
                logoutLink.addEventListener('click', function (event) {
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
                            window.location.href = '../includes/logout.php';
                        }
                    });
                });
            }
        });
    </script>
</body>

</html>