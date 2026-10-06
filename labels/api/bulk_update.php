<?php
// api/bulk_update.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/hardware_mapping.php';
require_once __DIR__ . '/../../core/ApiResponse.php';
require_once __DIR__ . '/../../core/Security.php';

$input = ApiResponse::getJsonInput();

try {
    // 1. Security Check
    ApiResponse::requireCsrf($input['csrf_token'] ?? null);

    $ids = $input['ids'] ?? [];
    $status = $input['status'] ?? null;
    $location = $input['location'] ?? null;

    if (empty($ids)) {
        ApiResponse::error("No items selected.", 400);
    }

    if (!$status && !$location) {
        ApiResponse::error("No changes specified.", 400);
    }

    $F = HW_FIELDS;
    $finalParams = [];
    $posUpdates = [];

    if ($status) {
        $posUpdates[] = "{$F['DESCRIPTION']} = ?, {$F['STATUS']} = 'In Warehouse'";
        $finalParams[] = $status;
    }

    if ($location) {
        $posUpdates[] = "{$F['LOCATION']} = ?";
        $finalParams[] = $location;
    }

    foreach ($ids as $id) {
        $finalParams[] = $id;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $posSql = "UPDATE items SET " . implode(', ', $posUpdates) . " WHERE id IN ($placeholders)";

    $stmt = $pdo_labels->prepare($posSql);
    $stmt->execute($finalParams);

    // LOG THE AUDIT EVENT
    $summary = "Bulk Update: Modified " . count($ids) . " items" . ($status ? " to $status" : "") . ($location ? " at $location" : "");
    if (function_exists('log_audit_event')) {
        log_audit_event($pdo_audit, 'Inventory', 0, 'BULK_UPDATE', $summary, null, $input);
    }

    ApiResponse::success([
        'count' => count($ids)
    ], "Successfully updated " . count($ids) . " items");

} catch (Exception $e) {
    ApiResponse::error($e->getMessage(), 500);
}
