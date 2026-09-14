<?php
// orders/api/update_inventory_field.php
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

// Main columns
$main_columns = ['brand', 'model', 'quantity', 'price'];

// Allowed specs keys
$allowed_specs_keys = [
    'cpu', 'gpu', 'ram', 'storage', 'battery', 'windows', 'series', 'gen', 'bios', 'condition', 'notes',
    'gaming_category', 'cpu_gen', 'type', 'voltage'
];

if (!in_array($field, $main_columns) && !in_array($field, $allowed_specs_keys)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid field update request.']);
    exit;
}

try {
    $conn_wh = Database::warehouse();
    $current_user = $_SESSION['username'] ?? 'System';

    if (in_array($field, $main_columns)) {
        if ($field === 'quantity') {
            $value = (int)$value;
        } elseif ($field === 'price') {
            $value = (float)Security::sanitize_float($value);
        } else {
            $value = trim($value);
        }

        $stmt = $conn_wh->prepare("UPDATE inventory SET {$field} = ?, last_updated_by = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$value, $current_user, $item_id]);
    } else {
        // Spec key. Fetch current specs first.
        $stmt_fetch = $conn_wh->prepare("SELECT specs_json FROM inventory WHERE id = ?");
        $stmt_fetch->execute([$item_id]);
        $specs_json_raw = $stmt_fetch->fetchColumn();

        $specs = json_decode($specs_json_raw ?: '{}', true) ?: [];
        $specs[$field] = trim($value);

        $specs_json_updated = json_encode($specs);

        $stmt = $conn_wh->prepare("UPDATE inventory SET specs_json = ?, last_updated_by = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$specs_json_updated, $current_user, $item_id]);
    }

    // Fetch new totals
    $stmt_info = $conn_wh->prepare("SELECT sector, location_code FROM inventory WHERE id = ?");
    $stmt_info->execute([$item_id]);
    $item_info = $stmt_info->fetch(PDO::FETCH_ASSOC);

    $total_units = 0;
    if ($item_info) {
        $stmt_total = $conn_wh->prepare("SELECT SUM(quantity) FROM inventory WHERE sector = ? AND location_code = ?");
        $stmt_total->execute([$item_info['sector'], $item_info['location_code']]);
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
