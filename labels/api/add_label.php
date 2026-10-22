<?php
// api/add_label.php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/hardware_mapping.php';

try {
    // 0. Security Check
    if (!Security::validate($_POST['csrf_token'] ?? '')) {
        throw new Exception("Security Error: Invalid form submission.");
    }

    // 1. Validation & Sanitization
    if (empty($_POST[HW_FIELDS['BRAND']]) || empty($_POST[HW_FIELDS['MODEL']])) {
        throw new Exception("Brand and Model are required.");
    }

    $brand              = sanitize_text($_POST[HW_FIELDS['BRAND']]);
    $model              = sanitize_text($_POST[HW_FIELDS['MODEL']]);
    $series             = sanitize_text($_POST[HW_FIELDS['SERIES']]             ?? null);
    $serial_number      = sanitize_text($_POST[HW_FIELDS['SERIAL_NUMBER']]      ?? null);
    $cpu_gen            = sanitize_text($_POST[HW_FIELDS['CPU_GEN']]            ?? null);
    $cpu_specs          = sanitize_text($_POST[HW_FIELDS['CPU_SPECS']]          ?? null);
    $cpu_cores          = sanitize_text($_POST[HW_FIELDS['CPU_CORES']]          ?? null);
    $cpu_speed          = sanitize_text($_POST[HW_FIELDS['CPU_SPEED']]          ?? null);
    $ram                = sanitize_text($_POST[HW_FIELDS['RAM']]                ?? null);
    $storage            = sanitize_text($_POST[HW_FIELDS['STORAGE']]            ?? null);

    // Technical Sheet Fields
    $gpu                = sanitize_text($_POST[HW_FIELDS['GPU']]                ?? null);
    $screen_res         = sanitize_text($_POST[HW_FIELDS['SCREEN_RES']]         ?? null);
    $battery            = isset($_POST[HW_FIELDS['BATTERY']]) && $_POST[HW_FIELDS['BATTERY']] !== '' ? (int)$_POST[HW_FIELDS['BATTERY']] : null;
    $battery_specs      = sanitize_text($_POST[HW_FIELDS['BATTERY_SPECS']]      ?? null);
    $webcam             = sanitize_text($_POST[HW_FIELDS['WEBCAM']]             ?? null);
    $backlit_kb         = sanitize_text($_POST[HW_FIELDS['BACKLIT_KB']]         ?? null);
    $os_version         = sanitize_text($_POST[HW_FIELDS['OS_VERSION']]         ?? null);
    $cosmetic_grade     = sanitize_text($_POST[HW_FIELDS['COSMETIC_GRADE']]     ?? null);
    $work_notes         = sanitize_text($_POST[HW_FIELDS['WORK_NOTES']]         ?? null);

    $bios_state         = sanitize_text($_POST[HW_FIELDS['BIOS_STATE']]         ?? 'Unknown');
    $description        = sanitize_text($_POST[HW_FIELDS['DESCRIPTION']]        ?? 'Untested');
    $warehouse_location = sanitize_text($_POST[HW_FIELDS['LOCATION']]           ?? null);

    // 2. Check for Spec Collision ("One label per specs", multiple locations)
    $check_stmt = $pdo_labels->prepare("
        SELECT id, warehouse_location FROM items
        WHERE LOWER(TRIM(" . HW_FIELDS['BRAND'] . ")) = LOWER(TRIM(:brand))
        AND LOWER(TRIM(" . HW_FIELDS['MODEL'] . ")) = LOWER(TRIM(:model))
        AND (
            LOWER(TRIM(COALESCE(" . HW_FIELDS['SERIES'] . ", ''))) = LOWER(TRIM(:series))
            OR (:series = '' AND (" . HW_FIELDS['SERIES'] . " IS NULL OR " . HW_FIELDS['SERIES'] . " = ''))
        )
        AND (
            LOWER(TRIM(COALESCE(" . HW_FIELDS['CPU_SPECS'] . ", ''))) = LOWER(TRIM(:cpu_specs))
            OR (:cpu_specs = '' AND (" . HW_FIELDS['CPU_SPECS'] . " IS NULL OR " . HW_FIELDS['CPU_SPECS'] . " = ''))
        )
        AND (
            LOWER(TRIM(COALESCE(" . HW_FIELDS['RAM'] . ", ''))) = LOWER(TRIM(:ram))
            OR (:ram = '' AND (" . HW_FIELDS['RAM'] . " IS NULL OR " . HW_FIELDS['RAM'] . " = ''))
        )
        AND (
            LOWER(TRIM(COALESCE(" . HW_FIELDS['STORAGE'] . ", ''))) = LOWER(TRIM(:storage))
            OR (:storage = '' AND (" . HW_FIELDS['STORAGE'] . " IS NULL OR " . HW_FIELDS['STORAGE'] . " = ''))
        )
        ORDER BY id ASC
        LIMIT 1
    ");

    $check_stmt->execute([
        ':brand'     => $brand,
        ':model'     => $model,
        ':series'    => $series ?? '',
        ':cpu_specs' => $cpu_specs ?? '',
        ':ram'       => $ram ?? '',
        ':storage'   => $storage ?? ''
    ]);

    $existing_item = $check_stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing_item) {
        $inserted_id = (int)$existing_item['id'];
        $is_duplicate = true;

        // Consolidate / merge location if new location specified
        if (!empty($warehouse_location)) {
            $locs = [];
            if (!empty($existing_item['warehouse_location'])) {
                $parts = array_map('trim', explode(',', $existing_item['warehouse_location']));
                foreach ($parts as $p) {
                    if ($p !== '' && !in_array($p, $locs, true)) {
                        $locs[] = $p;
                    }
                }
            }
            if (!in_array($warehouse_location, $locs, true)) {
                $locs[] = $warehouse_location;
                $stmt_up_loc = $pdo_labels->prepare("UPDATE items SET warehouse_location = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt_up_loc->execute([implode(', ', $locs), $inserted_id]);
            }
        }
    } else {
        // 3. Insert new row into labels.sqlite Database
        $is_duplicate = false;
        $stmt = $pdo_labels->prepare("
            INSERT INTO items (
                " . HW_FIELDS['BRAND'] . ",
                " . HW_FIELDS['MODEL'] . ",
                " . HW_FIELDS['SERIES'] . ",
                " . HW_FIELDS['SERIAL_NUMBER'] . ",
                " . HW_FIELDS['CPU_GEN'] . ",
                " . HW_FIELDS['CPU_SPECS'] . ",
                " . HW_FIELDS['CPU_CORES'] . ",
                " . HW_FIELDS['CPU_SPEED'] . ",
                " . HW_FIELDS['RAM'] . ",
                " . HW_FIELDS['STORAGE'] . ",
                " . HW_FIELDS['GPU'] . ",
                " . HW_FIELDS['SCREEN_RES'] . ",
                " . HW_FIELDS['BATTERY'] . ",
                " . HW_FIELDS['BATTERY_SPECS'] . ",
                " . HW_FIELDS['WEBCAM'] . ",
                " . HW_FIELDS['BACKLIT_KB'] . ",
                " . HW_FIELDS['OS_VERSION'] . ",
                " . HW_FIELDS['COSMETIC_GRADE'] . ",
                " . HW_FIELDS['WORK_NOTES'] . ",
                " . HW_FIELDS['BIOS_STATE'] . ",
                " . HW_FIELDS['DESCRIPTION'] . ",
                " . HW_FIELDS['LOCATION'] . ",
                " . HW_FIELDS['STATUS'] . "
            ) VALUES (
                :brand, :model, :series, :sn, :cpu_gen, :cpu_specs, :cpu_cores, :cpu_speed,
                :ram, :storage, :gpu, :screen_res, :battery, :battery_specs, :webcam,
                :backlit_kb, :os_version, :cosmetic_grade, :work_notes,
                :bios_state, :description, :location, 'In Warehouse'
            )
        ");

        $stmt->execute([
            ':brand'          => $brand,
            ':model'          => $model,
            ':series'         => $series,
            ':sn'             => $serial_number,
            ':cpu_gen'        => $cpu_gen,
            ':cpu_specs'      => $cpu_specs,
            ':cpu_cores'      => $cpu_cores,
            ':cpu_speed'      => $cpu_speed,
            ':ram'            => $ram,
            ':storage'        => $storage,
            ':gpu'            => $gpu,
            ':screen_res'     => $screen_res,
            ':battery'        => $battery,
            ':battery_specs'  => $battery_specs,
            ':webcam'         => $webcam,
            ':backlit_kb'     => $backlit_kb,
            ':os_version'     => $os_version,
            ':cosmetic_grade' => $cosmetic_grade,
            ':work_notes'     => $work_notes,
            ':bios_state'     => $bios_state,
            ':description'    => $description,
            ':location'       => $warehouse_location
        ]);

        $inserted_id = $pdo_labels->lastInsertId();
    }

    // 4. LOG THE AUDIT EVENT
    if (!$is_duplicate) {
        $summary = "Intake: Added $brand $model" . ($series ? " ($series)" : "") . " to warehouse.";
        log_audit_event($pdo_audit, 'Label', $inserted_id, 'CREATED', $summary, null, $_POST);
    }

    // 5. Return success to UI
    send_json_response(true, [
        'id' => $inserted_id,
        'is_duplicate' => $is_duplicate
    ]);

} catch (Exception $e) {
    send_json_response(false, null, $e->getMessage());
}
