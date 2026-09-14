<?php
require_once '../core/database.php';
include '../core/auth.php';

header('Content-Type: application/json');

if (!Security::validate($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Security Error: CSRF Token Invalid']);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit();
}

$ord_id = $_POST['order_id'] ?? null;
$new_cust_id = $_POST['new_customer_id'] ?? null;

if (!$ord_id || !$new_cust_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit();
}

try {
    $conn = Database::orders();

    $conn->beginTransaction();

    // Update orders table
    $stmt_o = $conn->prepare("UPDATE orders SET customer_id = ? WHERE order_id = ?");
    $stmt_o->execute([$new_cust_id, $ord_id]);

    // Update items table (assuming it's in the same DB or handled)
    $stmt_i = $conn->prepare("UPDATE items SET customer_id = ? WHERE order_id = ?");
    $stmt_i->execute([$new_cust_id, $ord_id]);

    $conn->commit();

    Audit::log('TRANSFER_ORDER', $ord_id, "Order transferred to Customer: " . $new_cust_id, 'orders');

    echo json_encode(['status' => 'success', 'message' => 'Order transferred successfully']);
} catch (PDOException $e) {
    if ($conn) $conn->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
