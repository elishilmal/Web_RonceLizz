<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

Auth::requireLogin();

$order_id = $_GET['id'] ?? 0;

if ($order_id) {
    try {
        $stmt = $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ? AND user_id = ? AND status = 'pending'");
        $stmt->execute([$order_id, $_SESSION['user_id']]);
        
        $_SESSION['success_message'] = 'Pesanan berhasil dibatalkan';
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Gagal membatalkan pesanan';
    }
}

header('Location: order_history.php');
exit;