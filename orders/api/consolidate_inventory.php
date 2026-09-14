<?php
// orders/api/consolidate_inventory.php
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

$sector = $input['sector'] ?? '';
$location_code = $input['location_code'] ?? '';

if (empty($sector) || empty($location_code)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields (sector, location_code).']);
    exit;
}

try {
    $conn_wh = Database::warehouse();
    $current_user = $_SESSION['username'] ?? 'System';

    // Fetch all items for this sector and location
    $stmt = $conn_wh->prepare("SELECT * FROM inventory WHERE sector = ? AND location_code = ? ORDER BY id ASC");
    $stmt->execute([$sector, $location_code]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($items)) {
        echo json_encode(['success' => true, 'message' => 'No items found to consolidate.', 'consolidated_count' => 0]);
        exit;
    }

    // Group items
    $groups = [];
    foreach ($items as $item) {
        $specs = json_decode($item['specs_json'], true) ?: [];
        $notes = strtolower(trim($specs['notes'] ?? ''));

        // Normalize values for comparison
        unset($specs['notes']);
        ksort($specs);
        $normalized_specs = [];
        foreach ($specs as $k => $v) {
            $normalized_specs[$k] = strtolower(trim((string)$v));
        }

        $group_parts = [
            'brand' => strtolower(trim($item['brand'])),
            'model' => strtolower(trim($item['model'])),
            'price' => number_format((float)$item['price'], 4, '.', ''),
            'notes' => $notes,
            'specs' => $normalized_specs
        ];
        $group_key = md5(json_encode($group_parts));

        $groups[$group_key][] = $item;
    }

    $conn_wh->beginTransaction();

    $consolidated_count = 0;
    $updated_rows = [];
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
            $total_qty += (int)$item['quantity'];
        }

        // Update the quantity of the kept item
        $stmt_update = $conn_wh->prepare("UPDATE inventory SET quantity = ?, last_updated_by = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt_update->execute([$total_qty, $current_user, $keep_id]);

        // Delete the duplicate items
        for ($i = 1; $i < count($group_items); $i++) {
            $delete_id = $group_items[$i]['id'];
            $stmt_delete = $conn_wh->prepare("DELETE FROM inventory WHERE id = ?");
            $stmt_delete->execute([$delete_id]);
            $deleted_ids[] = $delete_id;
        }

        $consolidated_count += (count($group_items) - 1);
    }

    $conn_wh->commit();

    // Fetch new totals
    $stmt_total = $conn_wh->prepare("SELECT SUM(quantity) FROM inventory WHERE sector = ? AND location_code = ?");
    $stmt_total->execute([$sector, $location_code]);
    $new_total_qty = $stmt_total->fetchColumn() ?: 0;

    echo json_encode([
        'success' => true,
        'message' => "Consolidated {$consolidated_count} duplicate item(s).",
        'consolidated_count' => $consolidated_count,
        'deleted_ids' => $deleted_ids,
        'new_total_qty' => (int)$new_total_qty
    ]);
} catch (Exception $e) {
    if (isset($conn_wh) && $conn_wh->inTransaction()) {
        $conn_wh->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
