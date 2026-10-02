<?php
require_once '../core/database.php';
include '../core/auth.php';

header('Content-Type: application/json');

$customer_id = trim($_GET['customer_id'] ?? '');
$company_name = trim($_GET['company_name'] ?? '');

if (empty($customer_id) && empty($company_name)) {
    echo json_encode(['success' => false, 'orders' => []]);
    exit();
}

try {
    $db_orders = Database::orders();
    
    // Look up company_name if only customer_id was passed
    if (empty($company_name) && !empty($customer_id)) {
        try {
            $db_cust = Database::customers();
            $stmt_c = $db_cust->prepare("SELECT company_name FROM customers WHERE customer_id = ? LIMIT 1");
            $stmt_c->execute([$customer_id]);
            $company_name = $stmt_c->fetchColumn() ?: '';
        } catch (Exception $e) {}
    }

    $stmt = $db_orders->prepare("
        SELECT orders.order_id, 
               orders.created_at, 
               orders.status,
               orders.total_amount,
               COALESCE(SUM(items.quantity), 0) as total_units,
               ROUND(COALESCE(SUM(items.unit_price * items.quantity), orders.total_amount, 0), 2) as order_total
        FROM orders
        LEFT JOIN items ON orders.order_id = items.order_id
        WHERE orders.customer_id = ? OR (orders.customer_id = ? AND ? != '')
        GROUP BY orders.order_id
        ORDER BY orders.created_at DESC
        LIMIT 30
    ");
    $stmt->execute([$customer_id, $company_name, $company_name]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'customer_id' => $customer_id,
        'company_name' => $company_name,
        'count' => count($orders),
        'orders' => $orders
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
