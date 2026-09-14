<?php
/**
 * Warehouse Inventory Bulk Clipboard Import API
 * Direct endpoint for importing spreadsheet clipboard rows into warehouse inventory.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../core/warehouse_db.php';
require_once __DIR__ . '/../core/Security.php';
require_once __DIR__ . '/../pages/partials/import_warehouse/parser_engine.php';

session_start();
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

try {
    if (!Security::validate($input['csrf_token'] ?? '')) {
        throw new Exception("Security Error: Invalid token.");
    }

    $items = $input['items'] ?? [];
    $sector = trim($input['sector'] ?? 'Gaming');
    $defaultLoc = strtoupper(trim($input['location'] ?? 'Inbound'));
    if (empty($defaultLoc)) $defaultLoc = 'Inbound';

    if (empty($items) || !is_array($items)) {
        throw new Exception("No valid items received for import.");
    }

    $current_user = $_SESSION['username'] ?? 'Admin';

    $conn_wh->beginTransaction();
    $importedCount = 0;

    $stmt_insert = $conn_wh->prepare("
        INSERT INTO inventory (user_owner, sector, location_code, brand, model, specs_json, quantity, price, last_updated_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($items as $item) {
        $brand = trim($item['brand'] ?? '');
        $model = trim($item['model'] ?? '');
        $series = trim($item['series'] ?? '');
        $cpu = trim($item['cpu'] ?? '');
        $desc = trim($item['description'] ?? '');
        $notes = trim($item['notes'] ?? '');

        if (empty($brand) && empty($model)) {
            continue;
        }

        // If brand is provided but model is blank
        if (empty($model)) {
            $model = !empty($series) ? $series : 'Console';
        }
        if (empty($brand)) {
            $brand = 'Generic';
        }

        // Determine location
        $loc = !empty($item['location']) ? strtoupper(trim($item['location'])) : $defaultLoc;

        // Auto-create location and map to working zone
        getOrCreateLocation($conn_wh, $loc);

        // Sanitize price and quantity
        $price = Security::sanitize_float($item['price'] ?? 0);
        $qty = Security::sanitize_int($item['quantity'] ?? 1);
        if ($qty <= 0) $qty = 1;

        // Determine condition from description
        $condition = 'Untested';
        if (stripos($desc, 'not working') !== false || stripos($desc, 'for parts') !== false) {
            $condition = 'For parts';
        } elseif (stripos($desc, 'untested') !== false) {
            $condition = 'Untested';
        } elseif (preg_match('/([ABC]\s*Grade|Grade\s*[ABC])/i', $desc, $m)) {
            $condition = trim($m[1]);
        }

        $specs = [
            'series' => $series,
            'cpu' => $cpu,
            'gen' => '',
            'ram' => '',
            'storage' => '',
            'battery' => '',
            'condition' => $condition,
            'notes' => $notes ?: $desc,
            'description' => $desc
        ];

        $specs_json = json_encode($specs);

        $stmt_insert->execute([
            $current_user,
            $sector,
            $loc,
            $brand,
            $model,
            $specs_json,
            $qty,
            $price,
            $current_user
        ]);

        $importedCount++;
    }

    $conn_wh->commit();

    echo json_encode([
        'success' => true,
        'count' => $importedCount,
        'sector' => $sector,
        'message' => "Successfully imported $importedCount items into $sector inventory."
    ]);

} catch (Exception $e) {
    if (isset($conn_wh) && $conn_wh->inTransaction()) {
        $conn_wh->rollBack();
    }
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
