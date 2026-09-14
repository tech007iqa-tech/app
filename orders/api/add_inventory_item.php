<?php
// orders/api/add_inventory_item.php
header('Content-Type: application/json');
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/Security.php';
session_start();

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access.']);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Read input from $_POST or JSON body
$raw_input = file_get_contents('php://input');
$json_input = json_decode($raw_input, true);
$data = is_array($json_input) ? array_merge($_POST, $json_input) : $_POST;

if (!Security::validate($data['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

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

    // Ensure location exists
    $stmt_loc = $conn_wh->prepare("INSERT OR IGNORE INTO locations (location_code, status) VALUES (?, 'Idle')");
    $stmt_loc->execute([$loc]);

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

        echo json_encode([
            'success' => true,
            'new_id' => $new_id,
            'new_total' => (int)$new_total
        ]);
    } else {
        throw new Exception("Failed to insert inventory item.");
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
