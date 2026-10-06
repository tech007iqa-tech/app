<?php
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/ApiResponse.php';
require_once __DIR__ . '/../../core/Security.php';
include_once __DIR__ . '/../core/auth.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    ApiResponse::methodNotAllowed();
}

try {
    ApiResponse::requireCsrf();

    $ord_id = $_POST['order_id'] ?? null;
    $new_status = $_POST['new_status'] ?? null;

    if (!$ord_id || !$new_status) {
        ApiResponse::error('Missing required fields', 400);
    }

    $conn = Database::orders();

    $stmt_u = $conn->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
    $stmt_u->execute([$new_status, $ord_id]);

    if (class_exists('Audit') && method_exists('Audit', 'log')) {
        Audit::log('STATUS_CHANGE', $ord_id, "Status updated to: " . $new_status, 'orders');
    }

    ApiResponse::success([
        'order_id' => $ord_id,
        'new_status' => $new_status,
        'status' => 'success'
    ], 'Order status updated');
} catch (Exception $e) {
    ApiResponse::error('Database error: ' . $e->getMessage(), 500);
}
