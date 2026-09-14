<?php
// orders/api/consolidate_order.php
header('Content-Type: application/json');
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/Security.php';
session_start();

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

// Read JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate CSRF
if (!Security::validate($input['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
    exit;
}

$order_id = $input['order_id'] ?? '';
$customer_id = $input['customer_id'] ?? '';

if (empty($order_id) || empty($customer_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields (order_id, customer_id).']);
    exit;
}

try {
    $conn = Database::orders();

    // Fetch all items for this order and customer
    $stmt = $conn->prepare("SELECT * FROM items WHERE order_id = ? AND customer_id = ? ORDER BY id ASC");
    $stmt->execute([$order_id, $customer_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($items)) {
        echo json_encode(['success' => true, 'message' => 'No items found to consolidate.', 'consolidated_count' => 0]);
        exit;
    }

    // Group items
    $groups = [];
    foreach ($items as $item) {
        $group_parts = [
            'brand' => strtolower(trim($item['brand'])),
            'model' => strtolower(trim($item['model'])),
            'series' => preg_replace('/[^a-z0-9]/', '', strtolower(trim($item['series']))),
            'cpu' => preg_replace('/[^a-z0-9]/', '', strtolower(trim($item['cpu']))),
            'description' => strtolower(trim($item['description'])),
            'unit_price' => number_format((float)$item['unit_price'], 4, '.', '')
        ];
        $group_key = md5(json_encode($group_parts));

        $groups[$group_key][] = $item;
    }

    $conn->beginTransaction();

    $consolidated_count = 0;
    $deleted_ids = [];

    foreach ($groups as $group_key => $group_items) {
        if (count($group_items) <= 1) {
            continue; // Nothing to consolidate for this group
        }

        // Keep the first item, merge others into it
        $keep_item = $group_items[0];
        $keep_id = $keep_item['id'];
        $total_qty = 0;

        foreach ($group_items as $item) {
            $total_qty += (float)$item['quantity'];
        }

        // Update the quantity of the kept item
        $stmt_update = $conn->prepare("UPDATE items SET quantity = ? WHERE id = ?");
        $stmt_update->execute([$total_qty, $keep_id]);

        // Delete the duplicate items
        for ($i = 1; $i < count($group_items); $i++) {
            $delete_id = $group_items[$i]['id'];
            $stmt_delete = $conn->prepare("DELETE FROM items WHERE id = ?");
            $stmt_delete->execute([$delete_id]);
            $deleted_ids[] = $delete_id;
        }

        $consolidated_count += (count($group_items) - 1);
    }

    $conn->commit();

    // Fetch new total quantity
    $stmt_total = $conn->prepare("SELECT SUM(quantity) FROM items WHERE order_id = ? AND customer_id = ?");
    $stmt_total->execute([$order_id, $customer_id]);
    $new_total_qty = $stmt_total->fetchColumn() ?: 0;

    echo json_encode([
        'success' => true,
        'message' => "Consolidated {$consolidated_count} duplicate item(s).",
        'consolidated_count' => $consolidated_count,
        'deleted_ids' => $deleted_ids,
        'new_total_qty' => (float)$new_total_qty
    ]);
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
