<?php
/**
 * labels/api/delete_battery.php
 * Deletes a battery record from the catalog.
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

    $delStmt = $pdo_labels->prepare("DELETE FROM batteries WHERE id = :id");
    $delStmt->execute([':id' => $id]);

    // Audit Logging
    $summary = "Battery Catalog: Deleted battery {$old_data['brand']} {$old_data['part_number']} (ID #{$id}).";
    log_audit_event($pdo_audit, 'Battery', $id, 'DELETED', $summary, $old_data, null);

    send_json_response(true, [
        'id' => $id,
        'message' => "Battery #{$id} deleted successfully."
    ]);

} catch (Exception $e) {
    send_json_response(false, null, $e->getMessage());
}
