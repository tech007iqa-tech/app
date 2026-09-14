<?php
require_once '../core/database.php';
include '../core/auth.php';

header('Content-Type: application/json');

$order_id = $_GET['order_id'] ?? null;
if (!$order_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing order_id']);
    exit();
}

try {
    // 1. Try to fetch order details and items from the database
    $stmt = Database::queryIntegrated('orders', ['c' => 'customers'], "
        SELECT o.order_id, o.status, o.created_at, o.customer_id, c.customers.company_name
        FROM orders o
        LEFT JOIN c.customers ON o.customer_id = c.customers.customer_id
        WHERE o.order_id = ?
    ", [$order_id]);

    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    $items = [];

    if ($order) {
        $db = Database::orders();
        $stmt_items = $db->prepare("SELECT * FROM items WHERE order_id = ? ORDER BY id ASC");
        $stmt_items->execute([$order_id]);
        $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // 2. Fall back to mock order data if order ID matches mock IDs (demo mode)
        $mock_orders = [
            'ORD-993A7' => [
                'order' => [
                    'order_id' => 'ORD-993A7',
                    'status' => 'Paid',
                    'created_at' => '2026-05-10 14:30:00',
                    'customer_id' => 'CUST-ACME',
                    'company_name' => 'Acme Corp'
                ],
                'items' => [
                    ['brand' => 'Apple', 'model' => 'MacBook Air A1932', 'series' => 'N/A', 'cpu' => 'Apple M1', 'description' => 'Space Gray', 'quantity' => 2, 'unit_price' => 245.00],
                    ['brand' => 'Dell', 'model' => 'Latitude 7490', 'series' => 'N/A', 'cpu' => 'Intel i5', 'description' => 'Light scratches', 'quantity' => 1, 'unit_price' => 135.00]
                ]
            ],
            'ORD-882B2' => [
                'order' => [
                    'order_id' => 'ORD-882B2',
                    'status' => 'Dispatched',
                    'created_at' => '2026-04-25 09:15:00',
                    'customer_id' => 'CUST-GLOBAL',
                    'company_name' => 'Global Tech'
                ],
                'items' => [
                    ['brand' => 'Apple', 'model' => 'MacBook Air A1932', 'series' => 'N/A', 'cpu' => 'Apple M1', 'description' => 'Space Gray', 'quantity' => 1, 'unit_price' => 245.00],
                    ['brand' => 'Dell', 'model' => 'Latitude 7490', 'series' => 'N/A', 'cpu' => 'Intel i5', 'description' => 'Light scratches', 'quantity' => 2, 'unit_price' => 135.00],
                    ['brand' => 'HP', 'model' => 'EliteBook 840 G5', 'series' => 'N/A', 'cpu' => 'Intel i5', 'description' => 'Good condition', 'quantity' => 3, 'unit_price' => 155.00]
                ]
            ],
            'ORD-771C3' => [
                'order' => [
                    'order_id' => 'ORD-771C3',
                    'status' => 'Pending',
                    'created_at' => '2026-05-10 11:00:00',
                    'customer_id' => 'CUST-ACME',
                    'company_name' => 'Acme Corp'
                ],
                'items' => [
                    ['brand' => 'Lenovo', 'model' => 'ThinkPad T480', 'series' => 'N/A', 'cpu' => 'Intel i5', 'description' => '8GB RAM', 'quantity' => 2, 'unit_price' => 165.00]
                ]
            ]
        ];

        if (isset($mock_orders[$order_id])) {
            $order = $mock_orders[$order_id]['order'];
            $items = $mock_orders[$order_id]['items'];
        }
    }

    if (!$order) {
        http_response_code(404);
        echo json_encode(['error' => 'Order not found']);
        exit();
    }

    echo json_encode([
        'order' => $order,
        'items' => $items
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
