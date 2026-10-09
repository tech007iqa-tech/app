<?php
/**
 * labels/api/edit_battery.php
 * Updates an existing battery model profile and stock level.
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

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        throw new Exception("Valid Battery ID is required.");
    }

    // Fetch existing state for audit log
    $stmt = $pdo_labels->prepare("SELECT * FROM batteries WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $old_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$old_data) {
        throw new Exception("Battery record not found.");
    }

    $brand = sanitize_text($_POST['brand'] ?? $old_data['brand']);
    $part_number = strtoupper(sanitize_text($_POST['part_number'] ?? $old_data['part_number']));
    $compatible_models = sanitize_text($_POST['compatible_models'] ?? $old_data['compatible_models']);

    if (empty($brand) || empty($part_number) || empty($compatible_models)) {
        throw new Exception("Brand, Primary Part Number, and Compatible Laptop Models are required.");
    }

    $model_name = sanitize_text($_POST['model_name'] ?? $old_data['model_name']);
    $aliases = sanitize_text($_POST['aliases'] ?? $old_data['aliases']);
    $voltage = sanitize_text($_POST['voltage'] ?? $old_data['voltage']);
    $capacity_wh = sanitize_text($_POST['capacity_wh'] ?? $old_data['capacity_wh']);
    $capacity_mah = sanitize_text($_POST['capacity_mah'] ?? $old_data['capacity_mah']);
    $cell_count = sanitize_text($_POST['cell_count'] ?? $old_data['cell_count']);
    $chemistry = sanitize_text($_POST['chemistry'] ?? $old_data['chemistry']);
    $warehouse_location = sanitize_text($_POST['warehouse_location'] ?? $old_data['warehouse_location']);
    $qty_in_stock = max(0, (int)($_POST['qty_in_stock'] ?? $old_data['qty_in_stock']));
    $condition = sanitize_text($_POST['condition'] ?? $old_data['condition']);
    $connector_type = sanitize_text($_POST['connector_type'] ?? $old_data['connector_type']);
    $notes = sanitize_text($_POST['notes'] ?? $old_data['notes']);
    $status = sanitize_text($_POST['status'] ?? ($qty_in_stock > 0 ? 'Available' : 'Out of Stock'));

    $updateStmt = $pdo_labels->prepare("
        UPDATE batteries SET
            brand = :brand,
            part_number = :part_number,
            model_name = :model_name,
            aliases = :aliases,
            voltage = :voltage,
            capacity_wh = :capacity_wh,
            capacity_mah = :capacity_mah,
            cell_count = :cell_count,
            chemistry = :chemistry,
            compatible_models = :compatible_models,
            warehouse_location = :warehouse_location,
            qty_in_stock = :qty_in_stock,
            condition = :condition,
            connector_type = :connector_type,
            notes = :notes,
            status = :status,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ");

    $updateStmt->execute([
        ':brand'              => $brand,
        ':part_number'        => $part_number,
        ':model_name'         => $model_name,
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
        ':status'             => $status,
        ':id'                 => $id
    ]);

    // Audit Logging
    $summary = "Battery Catalog: Updated {$brand} {$part_number} specs/stock (Qty: {$qty_in_stock}, Loc: {$warehouse_location}).";
    log_audit_event($pdo_audit, 'Battery', $id, 'UPDATED', $summary, $old_data, $_POST);

    send_json_response(true, [
        'id' => $id,
        'message' => "Battery {$part_number} updated successfully."
    ]);

} catch (Exception $e) {
    send_json_response(false, null, $e->getMessage());
}
