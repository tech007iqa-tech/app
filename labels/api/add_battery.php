<?php
/**
 * labels/api/add_battery.php
 * Adds a new battery model & cross-matching profile to the inventory.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    // 0. Security CSRF Check
    if (!Security::validate($_POST['csrf_token'] ?? '')) {
        throw new Exception("Security Error: Invalid form submission.");
    }

    // 1. Validation
    $brand = sanitize_text($_POST['brand'] ?? '');
    $part_number = strtoupper(sanitize_text($_POST['part_number'] ?? ''));
    $compatible_models = sanitize_text($_POST['compatible_models'] ?? '');

    if (empty($brand) || empty($part_number) || empty($compatible_models)) {
        throw new Exception("Brand, Primary Part Number, and Compatible Laptop Models are required.");
    }

    $model_name = sanitize_text($_POST['model_name'] ?? '');
    $aliases = sanitize_text($_POST['aliases'] ?? '');
    $voltage = sanitize_text($_POST['voltage'] ?? '');
    $capacity_wh = sanitize_text($_POST['capacity_wh'] ?? '');
    $capacity_mah = sanitize_text($_POST['capacity_mah'] ?? '');
    $cell_count = sanitize_text($_POST['cell_count'] ?? '');
    $chemistry = sanitize_text($_POST['chemistry'] ?? 'Li-ion');
    $warehouse_location = sanitize_text($_POST['warehouse_location'] ?? 'Unassigned');
    $qty_in_stock = max(0, (int)($_POST['qty_in_stock'] ?? 0));
    $condition = sanitize_text($_POST['condition'] ?? 'Tested OEM 80%+');
    $connector_type = sanitize_text($_POST['connector_type'] ?? '');
    $notes = sanitize_text($_POST['notes'] ?? '');
    $status = sanitize_text($_POST['status'] ?? ($qty_in_stock > 0 ? 'Available' : 'Out of Stock'));

    // Check if duplicate part number exists
    $checkStmt = $pdo_labels->prepare("SELECT id FROM batteries WHERE UPPER(part_number) = :part AND LOWER(brand) = :brand LIMIT 1");
    $checkStmt->execute([
        ':part' => $part_number,
        ':brand' => strtolower($brand)
    ]);
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        throw new Exception("A battery with part number '{$part_number}' already exists for {$brand}. Please edit the existing entry or change part number.");
    }

    $stmt = $pdo_labels->prepare("
        INSERT INTO batteries (
            brand, part_number, model_name, aliases, voltage, capacity_wh,
            capacity_mah, cell_count, chemistry, compatible_models,
            warehouse_location, qty_in_stock, condition, connector_type,
            notes, status, created_at, updated_at
        ) VALUES (
            :brand, :part_number, :model_name, :aliases, :voltage, :capacity_wh,
            :capacity_mah, :cell_count, :chemistry, :compatible_models,
            :warehouse_location, :qty_in_stock, :condition, :connector_type,
            :notes, :status, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
        )
    ");

    $stmt->execute([
        ':brand'              => $brand,
        ':part_number'        => $part_number,
        ':model_name'         => $model_name ?: "{$brand} {$part_number} Battery",
        ':aliases'            => $aliases,
        ':voltage'            => $voltage,
        ':capacity_wh'        => $capacity_wh,
        ':capacity_mah'       => $capacity_mah,
        ':cell_count'         => $cell_count,
        ':chemistry'          => $chemistry,
        ':compatible_models'  => $compatible_models,
        ':warehouse_location' => $warehouse_location,
        ':qty_in_stock'       => $qty_in_stock,
        ':condition'          => $condition,
        ':connector_type'     => $connector_type,
        ':notes'              => $notes,
        ':status'             => $status
    ]);

    $inserted_id = (int)$pdo_labels->lastInsertId();

    // 2. Audit Logging
    $summary = "Battery Catalog: Added {$brand} {$part_number} ({$capacity_wh}) in {$warehouse_location}.";
    log_audit_event($pdo_audit, 'Battery', $inserted_id, 'CREATED', $summary, null, $_POST);

    send_json_response(true, [
        'id' => $inserted_id,
        'message' => "Battery {$part_number} successfully registered."
    ]);

} catch (Exception $e) {
    send_json_response(false, null, $e->getMessage());
}
