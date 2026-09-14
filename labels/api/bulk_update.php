<?php
// api/bulk_update.php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/hardware_mapping.php';
require_once __DIR__ . '/../../core/Security.php';

$input = json_decode(file_get_contents('php://input'), true);

try {
    // 1. Security Check
    if (!Security::validate($input['csrf_token'] ?? '')) {
        throw new Exception("Security Error: Invalid token.");
    }

    $ids = $input['ids'] ?? [];
    $status = $input['status'] ?? null;
    $location = $input['location'] ?? null;

    if (empty($ids)) {
        throw new Exception("No items selected.");
    }

    if (!$status && !$location) {
        throw new Exception("No changes specified.");
    }

    $F = HW_FIELDS;
    $updates = [];
    $params = [];

    if ($status) {
        // Map UI description to database status if needed,
        // but here we allow setting both Status and Description from the same UI
        $updates[] = "{$F['DESCRIPTION']} = :status, {$F['STATUS']} = 'In Warehouse'";
        $params[':status'] = $status;
    }

    if ($location) {
        $updates[] = "{$F['LOCATION']} = :location";
        $params[':location'] = $location;
    }

    $updateStr = implode(', ', $updates);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $sql = "UPDATE items SET {$updateStr} WHERE id IN ($placeholders)";

    // Merge params (Named for set, Positional for IN)
    // PDO doesn't like mixing named and positional well in some versions,
    // so let's use positional for everything or named for everything.

    // Safer approach: Use named for everything or just rebuild positional.
    $finalParams = [];
    if ($status) $finalParams[] = $status;
    if ($location) $finalParams[] = $location;
    foreach($ids as $id) $finalParams[] = $id;

    $posSql = "UPDATE items SET ";
    $posUpdates = [];
    if ($status) $posUpdates[] = "{$F['DESCRIPTION']} = ?, {$F['STATUS']} = 'In Warehouse'";
    if ($location) $posUpdates[] = "{$F['LOCATION']} = ?";
    $posSql .= implode(', ', $posUpdates) . " WHERE id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")";

    $stmt = $pdo_labels->prepare($posSql);
    $stmt->execute($finalParams);

    // LOG THE AUDIT EVENT
    $summary = "Bulk Update: Modified " . count($ids) . " items" . ($status ? " to $status" : "") . ($location ? " at $location" : "");
    log_audit_event($pdo_audit, 'Inventory', 0, 'BULK_UPDATE', $summary, null, $input);

    echo json_encode([
        'success' => true,
        'count' => count($ids)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
