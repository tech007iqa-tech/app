<?php
// api/delete_label.php
// POST id= : Permanently deletes a hardware item from labels.sqlite.
// Blocks deletion if the item has been Sold (linked to an order).
header('Content-Type: application/json');
require_once '../includes/db.php';
require_once '../includes/functions.php';

try {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id <= 0) {
        throw new Exception('A valid item ID is required.');
    }

    // Fetch item to verify existence and check status
    $stmt_check = $pdo_labels->prepare("SELECT id, status, brand, model FROM items WHERE id = :id");
    $stmt_check->execute([':id' => $id]);
    $item = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        throw new Exception('Item #' . $id . ' not found.');
    }

    // User requested the ability to delete sold items as well.
    // Restriction removed.

    $stmt = $pdo_labels->prepare("DELETE FROM items WHERE id = :id");
    $stmt->execute([':id' => $id]);

    // LOG THE AUDIT EVENT
    $summary = "Permanent Deletion: " . $item['brand'] . " " . $item['model'] . " (#$id) removed from system.";
    log_audit_event($pdo_audit, 'Label', $id, 'DELETED', $summary, $item, null);

    send_json_response(true, [
        'deleted_id' => $id,
        'message'    => 'Item #' . str_pad($id, 5, '0', STR_PAD_LEFT)
                      . ' (' . $item['brand'] . ' ' . $item['model'] . ') has been removed.'
    ]);

} catch (Exception $e) {
    send_json_response(false, null, $e->getMessage());
}
?>
