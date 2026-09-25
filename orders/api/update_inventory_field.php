<?php
// orders/api/update_inventory_field.php
require_once __DIR__ . '/../core/ApiResponse.php';
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/Security.php';

ApiResponse::requireAuth();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    ApiResponse::methodNotAllowed();
}

// Read JSON / POST input safely
$input = ApiResponse::getJsonInput();
ApiResponse::requireCsrf();

$item_id = isset($input['item_id']) ? (int)$input['item_id'] : 0;
$field = $input['field'] ?? '';
$value = $input['value'] ?? '';

if ($item_id <= 0 || empty($field)) {
    ApiResponse::error('Missing required fields (item_id or field).', 400);
}

// Main columns
$main_columns = ['brand', 'model', 'quantity', 'price', 'location_code'];

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
        } elseif ($field === 'location_code') {
            $value = trim($value);
            if (!empty($value)) {
                $zone = trim($_POST['zone'] ?? '');
                if (empty($zone)) {
                    if (preg_match('/^(?:Zone\s*[-_]?)?([a-zA-Z0-9]+)/iu', $value, $matches)) {
                        $prefix = strtoupper($matches[1]);
                        $stmt_check_zone = $conn_wh->prepare("SELECT name FROM working_zones WHERE UPPER(name) = ? OR UPPER(name) LIKE ? OR UPPER(name) LIKE ? LIMIT 1");
                        $stmt_check_zone->execute([$prefix, '% ' . $prefix, '%' . $prefix]);
                        $found_zone = $stmt_check_zone->fetchColumn();
                        if ($found_zone) {
                            $zone = $found_zone;
                        }
                    }
                }
                $stmt_check = $conn_wh->prepare("SELECT working_zone_name FROM locations WHERE location_code = ?");
                $stmt_check->execute([$value]);
                $existing_wz = $stmt_check->fetchColumn();
                if ($existing_wz === false) {
                    $stmt_loc = $conn_wh->prepare("INSERT INTO locations (location_code, status, working_zone_name) VALUES (?, 'Idle', ?)");
                    $stmt_loc->execute([$value, !empty($zone) ? $zone : null]);
                } elseif (empty($existing_wz) && !empty($zone)) {
                    $stmt_up_loc = $conn_wh->prepare("UPDATE locations SET working_zone_name = ?, updated_at = CURRENT_TIMESTAMP WHERE location_code = ?");
                    $stmt_up_loc->execute([$zone, $value]);
                }
            }
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

    ApiResponse::success([
        'new_total' => $total_units,
        'field' => $field,
        'value' => $value
    ], 'Field updated successfully.');
} catch (Exception $e) {
    ApiResponse::error($e->getMessage(), 500);
}
