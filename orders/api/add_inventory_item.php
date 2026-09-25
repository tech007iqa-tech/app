<?php
// orders/api/add_inventory_item.php
require_once __DIR__ . '/../core/ApiResponse.php';
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/Security.php';

ApiResponse::requireAuth();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    ApiResponse::methodNotAllowed();
}

// Read input from $_POST or JSON body
$json_input = ApiResponse::getJsonInput();
$data = !empty($json_input) ? array_merge($_POST, $json_input) : $_POST;

ApiResponse::requireCsrf($data['csrf_token'] ?? null);

try {
    $conn_wh = Database::warehouse();
    $current_user = $_SESSION['username'] ?? 'System';

    $sector = trim($data['sector'] ?? 'Laptops');
    $loc = trim($data['location_code'] ?? '');
    $brand = trim($data['brand'] ?? '');
    $model = trim($data['model'] ?? '');
    $qty = (int)($data['quantity'] ?? 1);
    if ($qty < 1) $qty = 1;
    $price = (float)Security::sanitize_float($data['price'] ?? 0.00);

    if (empty($brand) || empty($model) || empty($loc)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required fields (brand, model, location_code).']);
        exit;
    }

    // Infer or resolve working zone for this location
    $zone = trim($data['zone'] ?? '');
    if (empty($zone)) {
        if (preg_match('/^(?:Zone\s*[-_]?)?([a-zA-Z0-9]+)/iu', $loc, $matches)) {
            $prefix = strtoupper($matches[1]);
            $stmt_check_zone = $conn_wh->prepare("SELECT name FROM working_zones WHERE UPPER(name) = ? OR UPPER(name) LIKE ? OR UPPER(name) LIKE ? LIMIT 1");
            $stmt_check_zone->execute([$prefix, '% ' . $prefix, '%' . $prefix]);
            $found_zone = $stmt_check_zone->fetchColumn();
            if ($found_zone) {
                $zone = $found_zone;
            }
        }
    }

    // Ensure location exists with appropriate working zone
    $stmt_check = $conn_wh->prepare("SELECT working_zone_name FROM locations WHERE location_code = ?");
    $stmt_check->execute([$loc]);
    $existing_wz = $stmt_check->fetchColumn();

    if ($existing_wz === false) {
        $stmt_loc = $conn_wh->prepare("INSERT INTO locations (location_code, status, working_zone_name) VALUES (?, 'Idle', ?)");
        $stmt_loc->execute([$loc, !empty($zone) ? $zone : null]);
    } elseif (empty($existing_wz) && !empty($zone)) {
        $stmt_up_loc = $conn_wh->prepare("UPDATE locations SET working_zone_name = ?, updated_at = CURRENT_TIMESTAMP WHERE location_code = ?");
        $stmt_up_loc->execute([$zone, $loc]);
    }

    // Dynamic Specs mapping based on sector
    $specs = [];
    if ($sector === 'Laptops') {
        $specs = [
            'cpu' => trim($data['cpu'] ?? ''),
            'gpu' => trim($data['gpu'] ?? ''),
            'ram' => trim($data['ram'] ?? ''),
            'storage' => trim($data['storage'] ?? ''),
            'battery' => trim($data['battery'] ?? ''),
            'windows' => trim($data['windows'] ?? ''),
            'series' => trim($data['series'] ?? ''),
            'gen' => trim($data['gen'] ?? ''),
            'bios' => trim($data['bios'] ?? ''),
            'condition' => trim($data['condition'] ?? 'Used'),
            'notes' => trim($data['notes'] ?? '')
        ];
    } elseif ($sector === 'Gaming') {
        $specs = [
            'category' => trim($data['gaming_category'] ?? 'PC'),
            'series' => trim($data['series'] ?? ''),
            'condition' => trim($data['condition'] ?? 'Used'),
            'notes' => trim($data['notes'] ?? ''),
            'ram' => trim($data['ram'] ?? ''),
            'storage' => trim($data['storage'] ?? ''),
            'cpu' => trim($data['cpu'] ?? ''),
            'gpu' => trim($data['gpu'] ?? '')
        ];
    } elseif ($sector === 'Desktops') {
        $specs = [
            'cpu_gen' => trim($data['cpu_gen'] ?? ''),
            'condition' => trim($data['condition'] ?? 'Used'),
            'notes' => trim($data['notes'] ?? '')
        ];
    } else {
        $specs = [
            'type' => trim($data['type'] ?? ''),
            'voltage' => trim($data['voltage'] ?? ''),
            'condition' => trim($data['condition'] ?? 'Used'),
            'notes' => trim($data['notes'] ?? '')
        ];
    }

    $specs_json = json_encode($specs);

    $stmt = $conn_wh->prepare("INSERT INTO inventory (user_owner, sector, location_code, brand, model, specs_json, quantity, price) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt->execute([$current_user, $sector, $loc, $brand, $model, $specs_json, $qty, $price])) {
        $new_id = $conn_wh->lastInsertId();

        // Fetch new total for this sector & location
        $stmt_total = $conn_wh->prepare("SELECT SUM(quantity) FROM inventory WHERE sector = ? AND location_code = ?");
        $stmt_total->execute([$sector, $loc]);
        $new_total = $stmt_total->fetchColumn() ?: 0;

        ApiResponse::success([
            'new_id' => $new_id,
            'new_total' => (int)$new_total
        ], 'Item added to inventory.');
    } else {
        ApiResponse::error("Failed to insert inventory item.", 500);
    }
} catch (Exception $e) {
    ApiResponse::error($e->getMessage(), 500);
}
