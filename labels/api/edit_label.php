<?php
// api/edit_label.php
// POST: Updates a hardware item record in labels.sqlite.
header('Content-Type: application/json');
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/hardware_mapping.php';

try {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id <= 0) {
        throw new Exception('A valid item ID is required.');
    }

    // 1. Fetch OLD record BEFORE update for auditing
    $stmt_old = $pdo_labels->prepare("SELECT * FROM items WHERE id = :id");
    $stmt_old->execute([':id' => $id]);
    $old_item = $stmt_old->fetch(PDO::FETCH_ASSOC);

    if (!$old_item) {
        throw new Exception('Item #' . $id . ' not found.');
    }

    // Sanitize all editable fields using mapping
    $brand               = sanitize_text($_POST[HW_FIELDS['BRAND']]              ?? null);
    $model               = sanitize_text($_POST[HW_FIELDS['MODEL']]              ?? null);
    $series              = sanitize_text($_POST[HW_FIELDS['SERIES']]             ?? null);
    $serial_number       = sanitize_text($_POST[HW_FIELDS['SERIAL_NUMBER']]      ?? null);
    $cpu_gen             = sanitize_text($_POST[HW_FIELDS['CPU_GEN']]            ?? null);
    $cpu_specs           = sanitize_text($_POST[HW_FIELDS['CPU_SPECS']]          ?? null);
    $cpu_cores           = sanitize_text($_POST[HW_FIELDS['CPU_CORES']]          ?? null);
    $cpu_speed           = sanitize_text($_POST[HW_FIELDS['CPU_SPEED']]          ?? null);
    $cpu_details         = sanitize_text($_POST[HW_FIELDS['CPU_DETAILS']]        ?? null);
    $ram                 = sanitize_text($_POST[HW_FIELDS['RAM']]                ?? null);
    $storage             = sanitize_text($_POST[HW_FIELDS['STORAGE']]            ?? null);
    $battery             = isset($_POST[HW_FIELDS['BATTERY']]) && $_POST[HW_FIELDS['BATTERY']] !== '' ? (int)$_POST[HW_FIELDS['BATTERY']] : null;
    $battery_specs       = sanitize_text($_POST[HW_FIELDS['BATTERY_SPECS']]      ?? null);
    $gpu                 = sanitize_text($_POST[HW_FIELDS['GPU']]                ?? null);
    $screen_res          = sanitize_text($_POST[HW_FIELDS['SCREEN_RES']]         ?? null);
    $webcam              = sanitize_text($_POST[HW_FIELDS['WEBCAM']]             ?? null);
    $backlit_kb          = sanitize_text($_POST[HW_FIELDS['BACKLIT_KB']]         ?? null);
    $os_version          = sanitize_text($_POST[HW_FIELDS['OS_VERSION']]         ?? null);
    $cosmetic_grade      = sanitize_text($_POST[HW_FIELDS['COSMETIC_GRADE']]     ?? null);
    $work_notes          = sanitize_text($_POST[HW_FIELDS['WORK_NOTES']]         ?? null);
    $bios_state          = sanitize_text($_POST[HW_FIELDS['BIOS_STATE']]         ?? null);
    $description         = sanitize_text($_POST[HW_FIELDS['DESCRIPTION']]        ?? null);
    $warehouse_location  = sanitize_text($_POST[HW_FIELDS['LOCATION']]           ?? null);
    $status              = sanitize_text($_POST[HW_FIELDS['STATUS']]             ?? 'In Warehouse');

    if (!$brand || !$model) {
        throw new Exception('Brand and Model are required.');
    }

    $stmt = $pdo_labels->prepare("
        UPDATE items SET
            " . HW_FIELDS['BRAND'] . "              = :brand,
            " . HW_FIELDS['MODEL'] . "              = :model,
            " . HW_FIELDS['SERIES'] . "             = :series,
            " . HW_FIELDS['SERIAL_NUMBER'] . "      = :sn,
            " . HW_FIELDS['CPU_GEN'] . "            = :cpu_gen,
            " . HW_FIELDS['CPU_SPECS'] . "          = :cpu_specs,
            " . HW_FIELDS['CPU_CORES'] . "          = :cpu_cores,
            " . HW_FIELDS['CPU_SPEED'] . "          = :cpu_speed,
            " . HW_FIELDS['CPU_DETAILS'] . "        = :cpu_details,
            " . HW_FIELDS['RAM'] . "                = :ram,
            " . HW_FIELDS['STORAGE'] . "            = :storage,
            " . HW_FIELDS['BATTERY'] . "            = :battery,
            " . HW_FIELDS['BATTERY_SPECS'] . "      = :battery_specs,
            " . HW_FIELDS['GPU'] . "                = :gpu,
            " . HW_FIELDS['SCREEN_RES'] . "         = :screen_res,
            " . HW_FIELDS['WEBCAM'] . "             = :webcam,
            " . HW_FIELDS['BACKLIT_KB'] . "         = :backlit_kb,
            " . HW_FIELDS['OS_VERSION'] . "         = :os_version,
            " . HW_FIELDS['COSMETIC_GRADE'] . "     = :cosmetic_grade,
            " . HW_FIELDS['WORK_NOTES'] . "         = :work_notes,
            " . HW_FIELDS['BIOS_STATE'] . "         = :bios_state,
            " . HW_FIELDS['DESCRIPTION'] . "        = :description,
            " . HW_FIELDS['LOCATION'] . "           = :warehouse_location,
            " . HW_FIELDS['STATUS'] . "              = :status,
            updated_at                             = CURRENT_TIMESTAMP
        WHERE id = :id
    ");

    $stmt->execute([
        ':brand'              => $brand,
        ':model'              => $model,
        ':series'             => $series,
        ':sn'                 => $serial_number,
        ':cpu_gen'            => $cpu_gen,
        ':cpu_specs'          => $cpu_specs,
        ':cpu_cores'          => $cpu_cores,
        ':cpu_speed'          => $cpu_speed,
        ':cpu_details'        => $cpu_details,
        ':ram'                => $ram,
        ':storage'            => $storage,
        ':battery'            => $battery,
        ':battery_specs'      => $battery_specs,
        ':gpu'                => $gpu,
        ':screen_res'         => $screen_res,
        ':webcam'             => $webcam,
        ':backlit_kb'         => $backlit_kb,
        ':os_version'         => $os_version,
        ':cosmetic_grade'     => $cosmetic_grade,
        ':work_notes'         => $work_notes,
        ':bios_state'         => $bios_state,
        ':description'        => $description,
        ':warehouse_location' => $warehouse_location,
        ':status'             => $status,
        ':id'                 => $id,
    ]);

    // Return the full updated row so the JS can re-render it
    $stmt_fetch = $pdo_labels->prepare("SELECT * FROM items WHERE id = :id");
    $stmt_fetch->execute([':id' => $id]);
    $updated_item = $stmt_fetch->fetch(PDO::FETCH_ASSOC);

    // 2. LOG THE AUDIT EVENT
    $action = 'UPDATED';
    $summary = "Updated details for " . ($updated_item[HW_FIELDS['BRAND']] ?? '') . " " . ($updated_item[HW_FIELDS['MODEL']] ?? '');

    // Status Change Summary
    if (($old_item[HW_FIELDS['STATUS']] ?? '') !== ($updated_item[HW_FIELDS['STATUS']] ?? '')) {
        $action = 'STATUS_CHANGE';
        $summary = "Status Changed: " . ($old_item[HW_FIELDS['STATUS']] ?? '—') . " → " . ($updated_item[HW_FIELDS['STATUS']] ?? '—');
    }
    // Location Change Summary
    else if (($old_item[HW_FIELDS['LOCATION']] ?? '') !== ($updated_item[HW_FIELDS['LOCATION']] ?? '')) {
        $summary = "Moved from " . ($old_item[HW_FIELDS['LOCATION']] ?? 'Unknown') . " to " . ($updated_item[HW_FIELDS['LOCATION']] ?? 'Unknown');
    }

    log_audit_event($pdo_audit, 'Label', $id, $action, $summary, $old_item, $updated_item);

    send_json_response(true, ['item' => $updated_item]);

} catch (Exception $e) {
    send_json_response(false, null, $e->getMessage());
}
?>
