<?php
// orders/api/update_order_item_field.php
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

$item_id = isset($input['item_id']) ? (int)$input['item_id'] : 0;
$field = $input['field'] ?? '';
$value = $input['value'] ?? '';

if ($item_id <= 0 || empty($field)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields.']);
    exit;
}

// Whitelist allowed fields to prevent SQL injection or editing key identifiers
$allowed_fields = ['brand', 'model', 'series', 'cpu', 'description', 'notes', 'quantity', 'unit_price'];
if (!in_array($field, $allowed_fields)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid field update request.']);
    exit;
}

try {
    $conn = Database::orders();

    // Sanitize value depending on field type
    if ($field === 'quantity') {
        $value = (float)Security::sanitize_float($value);
    } elseif ($field === 'unit_price') {
        $value = (float)Security::sanitize_float($value);
    } else {
        $value = trim($value);
    }

    // Update field
    $sql = "UPDATE items SET {$field} = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$value, $item_id]);

    // Fetch new order total units and cost
    $stmt_info = $conn->prepare("SELECT order_id FROM items WHERE id = ?");
    $stmt_info->execute([$item_id]);
    $order_id = $stmt_info->fetchColumn();

    $total_units = 0;
    if ($order_id) {
        $stmt_total = $conn->prepare("SELECT SUM(quantity) FROM items WHERE order_id = ?");
        $stmt_total->execute([$order_id]);
        $total_units = $stmt_total->fetchColumn() ?: 0;
    }

    echo json_encode([
        'success' => true,
        'new_total' => $total_units,
        'field' => $field,
        'value' => $value
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
