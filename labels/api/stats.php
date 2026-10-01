<?php
// api/stats.php
// GET: Returns real-time KPI counts for dashboard & inventory metrics
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $total_in_stock = (int)$pdo_labels->query("SELECT COUNT(id) FROM items WHERE status != 'Sold'")->fetchColumn();
    $refurbished    = (int)$pdo_labels->query("SELECT COUNT(id) FROM items WHERE status != 'Sold' AND (description = 'Refurbished' OR status = 'Tested')")->fetchColumn();
    $untested       = (int)$pdo_labels->query("SELECT COUNT(id) FROM items WHERE status != 'Sold' AND description = 'Untested'")->fetchColumn();
    $for_parts      = (int)$pdo_labels->query("SELECT COUNT(id) FROM items WHERE status != 'Sold' AND description = 'For Parts'")->fetchColumn();
    $sold           = (int)$pdo_labels->query("SELECT COUNT(id) FROM items WHERE status = 'Sold'")->fetchColumn();

    send_json_response(true, [
        'in_stock'    => $total_in_stock,
        'refurbished' => $refurbished,
        'untested'    => $untested,
        'for_parts'   => $for_parts,
        'sold'        => $sold,
        'total'       => $total_in_stock + $sold
    ]);

} catch (Exception $e) {
    send_json_response(false, null, $e->getMessage());
}
