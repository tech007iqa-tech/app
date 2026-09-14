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
$new_status = $_POST['new_status'] ?? null;

if (!$ord_id || !$new_status) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit();
}

try {
    $conn = Database::orders();

    $stmt_u = $conn->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
    $stmt_u->execute([$new_status, $ord_id]);

    Audit::log('STATUS_CHANGE', $ord_id, "Status updated to: " . $new_status, 'orders');

    echo json_encode(['status' => 'success', 'message' => 'Order status updated']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
