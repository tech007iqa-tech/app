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
    $new_cust_id = $_POST['new_customer_id'] ?? null;

    if (!$ord_id || !$new_cust_id) {
        ApiResponse::error('Missing required fields', 400);
    }

    $conn = Database::orders();
    $conn->beginTransaction();

    // Update orders table
    $stmt_o = $conn->prepare("UPDATE orders SET customer_id = ? WHERE order_id = ?");
    $stmt_o->execute([$new_cust_id, $ord_id]);

    // Update items table (assuming it's in the same DB or handled)
    $stmt_i = $conn->prepare("UPDATE items SET customer_id = ? WHERE order_id = ?");
    $stmt_i->execute([$new_cust_id, $ord_id]);

    $conn->commit();

    if (class_exists('Audit') && method_exists('Audit', 'log')) {
        Audit::log('TRANSFER_ORDER', $ord_id, "Order transferred to Customer: " . $new_cust_id, 'orders');
    }

    ApiResponse::success([
        'order_id' => $ord_id,
        'customer_id' => $new_cust_id,
        'status' => 'success'
    ], 'Order transferred successfully');
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    ApiResponse::error('Database error: ' . $e->getMessage(), 500);
}
