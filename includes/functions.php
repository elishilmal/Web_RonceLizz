<?php
function formatRupiah($number)
{
    return 'Rp ' . number_format($number, 0, ',', '.');
}

function formatDate($date, $format = 'd F Y')
{
    $months = [
        'January' => 'Januari',
        'February' => 'Februari',
        'March' => 'Maret',
        'April' => 'April',
        'May' => 'Mei',
        'June' => 'Juni',
        'July' => 'Juli',
        'August' => 'Agustus',
        'September' => 'September',
        'October' => 'Oktober',
        'November' => 'November',
        'December' => 'Desember'
    ];

    $days = [
        'Monday' => 'Senin',
        'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis',
        'Friday' => 'Jumat',
        'Saturday' => 'Sabtu',
        'Sunday' => 'Minggu'
    ];

    $timestamp = strtotime($date);
    $formatted = date($format, $timestamp);

    foreach ($months as $en => $id) {
        $formatted = str_replace($en, $id, $formatted);
    }

    foreach ($days as $en => $id) {
        $formatted = str_replace($en, $id, $formatted);
    }

    return $formatted;
}

function generateRandomString($length = 10)
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}


function uploadFile($file, $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'], $max_size = 2097152, $upload_dir = '../assets/uploads/')
{
    $errors = [];
    $file_name = '';

    // Cek error
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Error uploading file. Code: ' . $file['error'];
        return ['success' => false, 'errors' => $errors];
    }

    // Cek ukuran file
    if ($file['size'] > $max_size) {
        $errors[] = 'File terlalu besar. Maksimal ' . ($max_size / 1024 / 1024) . 'MB';
    }

    // Cek tipe file
    $file_type = mime_content_type($file['tmp_name']);
    if (!in_array($file_type, $allowed_types)) {
        $errors[] = 'Tipe file tidak diizinkan. Gunakan: ' . implode(', ', $allowed_types);
    }

    // Jika ada error, return
    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }

    // Generate nama file unik
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $file_name = uniqid() . '_' . date('YmdHis') . '.' . $file_extension;
    $target_path = $upload_dir . $file_name;

    // Buat folder jika belum ada
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Upload file
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        return ['success' => true, 'file_name' => $file_name, 'file_path' => $target_path];
    } else {
        return ['success' => false, 'errors' => ['Gagal mengupload file']];
    }
}

function validateEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePhone($phone)
{
    // Format: +62 atau 08
    $pattern = '/^(\+62|62|0)8[1-9][0-9]{6,9}$/';
    return preg_match($pattern, $phone);
}

function getUserIP()
{
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}


function sanitize($input)
{
    if (is_array($input)) {
        foreach ($input as $key => $value) {
            $input[$key] = sanitize($value);
        }
    } else {
        $input = trim($input);
        $input = stripslashes($input);
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }
    return $input;
}

function redirectWithMessage($url, $type, $message)
{
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
    header('Location: ' . $url);
    exit;
}

function displayFlashMessage()
{
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);

        $alert_class = '';
        switch ($message['type']) {
            case 'success':
                $alert_class = 'alert-success';
                $icon = 'check-circle';
                break;
            case 'error':
                $alert_class = 'alert-danger';
                $icon = 'exclamation-circle';
                break;
            case 'warning':
                $alert_class = 'alert-warning';
                $icon = 'exclamation-triangle';
                break;
            case 'info':
                $alert_class = 'alert-info';
                $icon = 'info-circle';
                break;
            default:
                $alert_class = 'alert-info';
                $icon = 'info-circle';
        }

        return '
        <div class="alert ' . $alert_class . ' alert-dismissible fade show" role="alert">
            <i class="fas fa-' . $icon . ' me-2"></i>
            ' . $message['message'] . '
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
    }
    return '';
}

function paginate($total_items, $items_per_page, $current_page, $url_pattern)
{
    $total_pages = ceil($total_items / $items_per_page);

    if ($total_pages <= 1) {
        return '';
    }

    $html = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';

    // Previous button
    if ($current_page > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . sprintf($url_pattern, $current_page - 1) . '">&laquo; Prev</a></li>';
    }

    // Page numbers
    $start_page = max(1, $current_page - 2);
    $end_page = min($total_pages, $start_page + 4);

    for ($i = $start_page; $i <= $end_page; $i++) {
        $active = $i == $current_page ? 'active' : '';
        $html .= '<li class="page-item ' . $active . '"><a class="page-link" href="' . sprintf($url_pattern, $i) . '">' . $i . '</a></li>';
    }

    // Next button
    if ($current_page < $total_pages) {
        $html .= '<li class="page-item"><a class="page-link" href="' . sprintf($url_pattern, $current_page + 1) . '">Next &raquo;</a></li>';
    }

    $html .= '</ul></nav>';

    return $html;
}

function logActivity($user_id, $action, $details = '')
{
    global $pdo;

    try {
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $details, getUserIP()]);
        return true;
    } catch (PDOException $e) {
        error_log("Log activity error: " . $e->getMessage());
        return false;
    }
}


function isJson($string)
{
    json_decode($string);
    return json_last_error() === JSON_ERROR_NONE;
}


function generateOrderNumber()
{
    return 'ORD' . date('Ymd') . strtoupper(generateRandomString(6));
}


function calculateDistance($lat1, $lon1, $lat2, $lon2)
{
    $earth_radius = 6371;

    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return $earth_radius * $c;
}
?>