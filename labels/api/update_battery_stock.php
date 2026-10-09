<?php
/**
 * labels/api/update_battery_stock.php
 * Rapid AJAX endpoint for incrementing/decrementing shelf battery pack stock.
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
    $delta = (int)($_POST['delta'] ?? 0);
    $new_qty = isset($_POST['qty']) ? max(0, (int)$_POST['qty']) : null;

    if ($id <= 0) {
        throw new Exception("Valid Battery ID is required.");
    }

    $stmt = $pdo_labels->prepare("SELECT * FROM batteries WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $battery = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$battery) {
        throw new Exception("Battery record not found.");
    }

    $current_qty = (int)$battery['qty_in_stock'];
    if ($new_qty !== null) {
        $final_qty = $new_qty;
    } else {
        $final_qty = max(0, $current_qty + $delta);
    }

    $new_status = $final_qty > 0 ? 'Available' : 'Out of Stock';

    $updStmt = $pdo_labels->prepare("
        UPDATE batteries SET
            qty_in_stock = :qty,
            status = :status,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ");
    $updStmt->execute([
        ':qty' => $final_qty,
        ':status' => $new_status,
        ':id' => $id
    ]);

    // Audit Logging
    $summary = "Battery Stock: Updated {$battery['brand']} {$battery['part_number']} stock from {$current_qty} to {$final_qty}.";
    log_audit_event($pdo_audit, 'Battery', $id, 'UPDATED', $summary, ['qty' => $current_qty], ['qty' => $final_qty]);

    send_json_response(true, [
        'id' => $id,
        'new_qty' => $final_qty,
        'status' => $new_status,
        'message' => "Stock updated to {$final_qty}."
    ]);

} catch (Exception $e) {
    send_json_response(false, null, $e->getMessage());
}
