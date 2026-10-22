<?php
/**
 * tech/api/add_log.php
 * Handles Full Technical Sheet intake, hybrid delivery tracking, and optional labels.db synchronization.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/auth.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit();
}

$tech_id = $_SESSION['username'] ?? 'Unknown';
$status = trim($_POST['status'] ?? 'Good');
$qty = max(1, (int)($_POST['qty'] ?? 1));

// Extract and sanitize fields (supporting both tech & labels naming conventions)
$make          = trim($_POST['brand'] ?? $_POST['make'] ?? '');
$model         = trim($_POST['model'] ?? '');
$series        = trim($_POST['series'] ?? '');
$serial_number = trim($_POST['serial_number'] ?? '');

if (empty($make) || empty($model)) {
    echo json_encode(['success' => false, 'error' => 'Manufacturer Brand and Model are required.']);
    exit();
}

$cpu_gen       = trim($_POST['cpu_gen'] ?? '');
$cpu_specs     = trim($_POST['cpu_specs'] ?? '');
$cpu_cores     = trim($_POST['cpu_cores'] ?? '');
$cpu_speed     = trim($_POST['cpu_speed'] ?? '');
$cpu           = trim($_POST['cpu'] ?? '');
if (empty($cpu)) {
    $cpu = trim($cpu_specs . ($cpu_gen ? " ($cpu_gen)" : ''));
}

$gpu           = trim($_POST['gpu'] ?? '');
$ram           = trim($_POST['ram'] ?? '');
$storage       = trim($_POST['storage'] ?? '');
$battery       = trim($_POST['battery'] ?? '');
if ($battery === '1') $battery = 'Included / Good';
if ($battery === '0') $battery = 'Missing / Dead';
$battery_specs = trim($_POST['battery_specs'] ?? '');
$screen_res    = trim($_POST['screen_res'] ?? '');
$bios_state    = trim($_POST['bios_state'] ?? 'Unknown');
$os            = trim($_POST['os_version'] ?? $_POST['os'] ?? '');
$cosmetic_grade= trim($_POST['cosmetic_grade'] ?? '');
$notes         = trim($_POST['work_notes'] ?? $_POST['notes'] ?? '');
$location      = trim($_POST['warehouse_location'] ?? $_POST['location'] ?? '');

// Delivery Tracking Fields
$delivery_status      = trim($_POST['delivery_status'] ?? 'Pending');
$delivery_destination = trim($_POST['delivery_destination'] ?? '');
if (empty($delivery_destination) && !empty($location) && $delivery_status === 'Delivered to Shelf') {
    $delivery_destination = $location;
}
$ready_for_warehouse  = ($delivery_status === 'Delivered to Shelf') ? 1 : 0;
$label_id             = !empty($_POST['label_id']) ? (int)$_POST['label_id'] : null;
$batch_number         = trim($_POST['batch_number'] ?? '');

// Parse multi-serial batch intake if provided
$batch_serials_raw = $_POST['batch_serials'] ?? '';
$batch_serials = [];
if (is_array($batch_serials_raw)) {
    $batch_serials = array_values(array_filter(array_map('trim', $batch_serials_raw)));
} elseif (!empty($batch_serials_raw)) {
    $decoded = json_decode($batch_serials_raw, true);
    if (is_array($decoded)) {
        $batch_serials = array_values(array_filter(array_map('trim', $decoded)));
    } else {
        $parts = preg_split('/[\r\n,]+/', $batch_serials_raw);
        $batch_serials = array_values(array_filter(array_map('trim', $parts)));
    }
}

// If no batch list but single serial is present, make it a single-item list
if (empty($batch_serials)) {
    $batch_serials = [$serial_number];
}

$sync_labels = (isset($_POST['sync_labels']) && $_POST['sync_labels'] === '1') || !empty($_POST['print_action']);

try {
    $conn_tech = Database::tech();
    $conn_labels = Database::labels();

    $battery_int = (stripos($battery, 'Good') !== false || $battery === '1') ? 1 : (stripos($battery, 'Dead') !== false || $battery === '0' ? 0 : null);

    $inserted_log_ids = [];
    $inserted_label_ids = [];

    // ─────────────────────────────────────────────────────────────────────────
    // SMART SPEC COLLISION RESOLVER ("One label per specs", multiple locations)
    // ─────────────────────────────────────────────────────────────────────────
    $smart_label_id = ($label_id !== null && $label_id > 0) ? $label_id : null;
    $existing_label = null;

    if ($smart_label_id !== null) {
        $stmt_verify = $conn_labels->prepare("SELECT * FROM items WHERE id = ? LIMIT 1");
        $stmt_verify->execute([$smart_label_id]);
        $existing_label = $stmt_verify->fetch(PDO::FETCH_ASSOC);
        if (!$existing_label) {
            $smart_label_id = null;
        }
    }

    if ($smart_label_id === null) {
        // Query for an exact spec collision in labels.db
        $stmt_spec = $conn_labels->prepare("
            SELECT * FROM items
            WHERE LOWER(TRIM(brand)) = LOWER(TRIM(:brand))
              AND LOWER(TRIM(model)) = LOWER(TRIM(:model))
              AND (
                    LOWER(TRIM(COALESCE(series, ''))) = LOWER(TRIM(:series))
                    OR (:series = '' AND (series IS NULL OR series = ''))
                  )
              AND (
                    LOWER(TRIM(COALESCE(cpu_specs, ''))) = LOWER(TRIM(:cpu_specs))
                    OR (:cpu_specs = '' AND (cpu_specs IS NULL OR cpu_specs = ''))
                  )
              AND (
                    LOWER(TRIM(COALESCE(ram, ''))) = LOWER(TRIM(:ram))
                    OR (:ram = '' AND (ram IS NULL OR ram = ''))
                  )
              AND (
                    LOWER(TRIM(COALESCE(storage, ''))) = LOWER(TRIM(:storage))
                    OR (:storage = '' AND (storage IS NULL OR storage = ''))
                  )
            ORDER BY id ASC
            LIMIT 1
        ");
        $stmt_spec->execute([
            ':brand'     => $make,
            ':model'     => $model,
            ':series'    => $series,
            ':cpu_specs' => $cpu_specs,
            ':ram'       => $ram,
            ':storage'   => $storage
        ]);
        $existing_label = $stmt_spec->fetch(PDO::FETCH_ASSOC);
        if ($existing_label) {
            $smart_label_id = (int)$existing_label['id'];
        }
    }

    if ($smart_label_id !== null && $existing_label) {
        // Existing label found: Consolidate / merge location without creating duplicate label
        $locs = [];
        if (!empty($existing_label['warehouse_location'])) {
            $parts = array_map('trim', explode(',', $existing_label['warehouse_location']));
            foreach ($parts as $p) {
                if ($p !== '' && !in_array($p, $locs, true)) {
                    $locs[] = $p;
                }
            }
        }
        $updated_loc = false;
        if ($location !== '' && !in_array($location, $locs, true)) {
            $locs[] = $location;
            $updated_loc = true;
        }

        // Enrich any missing fields on master label
        $enrich = [];
        if (empty($existing_label['cpu_gen']) && !empty($cpu_gen)) $enrich['cpu_gen'] = $cpu_gen;
        if (empty($existing_label['cpu_cores']) && !empty($cpu_cores)) $enrich['cpu_cores'] = $cpu_cores;
        if (empty($existing_label['cpu_speed']) && !empty($cpu_speed)) $enrich['cpu_speed'] = $cpu_speed;
        if (empty($existing_label['gpu']) && !empty($gpu)) $enrich['gpu'] = $gpu;
        if (empty($existing_label['screen_res']) && !empty($screen_res)) $enrich['screen_res'] = $screen_res;
        if (empty($existing_label['os_version']) && !empty($os)) $enrich['os_version'] = $os;

        if ($updated_loc || !empty($enrich)) {
            $setParts = [];
            $setParams = [];
            if ($updated_loc) {
                $setParts[] = "warehouse_location = ?";
                $setParams[] = implode(', ', $locs);
            }
            foreach ($enrich as $col => $val) {
                $setParts[] = "$col = ?";
                $setParams[] = $val;
            }
            $setParts[] = "updated_at = CURRENT_TIMESTAMP";
            $setParams[] = $smart_label_id;
            $conn_labels->prepare("UPDATE items SET " . implode(', ', $setParts) . " WHERE id = ?")->execute($setParams);
        }
    } elseif ($smart_label_id === null && $sync_labels) {
        // No matching label exists: create ONE new master label for these specs
        try {
            $stmt_ins_lbl = $conn_labels->prepare("
                INSERT INTO items (
                    brand, model, series, serial_number,
                    cpu_gen, cpu_specs, cpu_cores, cpu_speed,
                    ram, storage, gpu, screen_res,
                    battery, battery_specs, os_version, cosmetic_grade,
                    bios_state, description, warehouse_location, work_notes,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt_ins_lbl->execute([
                $make, $model, $series, $serial_number,
                $cpu_gen, $cpu_specs, $cpu_cores, $cpu_speed,
                $ram, $storage, $gpu, $screen_res,
                $battery_int, $battery_specs, $os, $cosmetic_grade,
                $bios_state, ($status === 'Good' ? 'Refurbished' : 'For Parts'), $location, $notes,
                ($ready_for_warehouse ? 'In Warehouse' : 'Tested')
            ]);
            $smart_label_id = (int)$conn_labels->lastInsertId();
        } catch (Exception $e) {}
    }

    $stmt_log = $conn_tech->prepare("
        INSERT INTO logs (
            tech_id, status, qty,
            make, model, series, serial_number,
            delivery_status, delivery_destination, label_id, batch_number,
            cpu, cpu_gen, cpu_specs, cpu_cores, cpu_speed,
            gpu, ram, storage,
            battery, battery_specs, screen_res, cosmetic_grade,
            bios_state, os, location, notes,
            ready_for_warehouse
        ) VALUES (
            ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?
        )
    ");

    $is_multi_batch = (count($batch_serials) > 1);

    foreach ($batch_serials as $curr_sn) {
        $row_qty = $is_multi_batch ? 1 : $qty;

        $stmt_log->execute([
            $tech_id, $status, $row_qty,
            $make, $model, $series, $curr_sn,
            $delivery_status, $delivery_destination, $smart_label_id, $batch_number,
            $cpu, $cpu_gen, $cpu_specs, $cpu_cores, $cpu_speed,
            $gpu, $ram, $storage,
            $battery, $battery_specs, $screen_res, $cosmetic_grade,
            $bios_state, $os, $location, $notes,
            $ready_for_warehouse
        ]);

        $new_log_id = (int)$conn_tech->lastInsertId();
        $inserted_log_ids[] = $new_log_id;
        if ($smart_label_id) {
            $inserted_label_ids[] = $smart_label_id;
        }
    }

    $primary_log_id = $inserted_log_ids[0] ?? 0;
    $primary_label_id = $inserted_label_ids[0] ?? null;

    echo json_encode([
        'success' => true,
        'log_id' => $primary_log_id,
        'log_ids' => $inserted_log_ids,
        'label_id' => $primary_label_id,
        'label_ids' => $inserted_label_ids,
        'make' => $make,
        'model' => $model,
        'serial_number' => $serial_number,
        'batch_number' => $batch_number,
        'units_logged' => count($inserted_log_ids),
        'delivery_status' => $delivery_status,
        'message' => count($inserted_log_ids) > 1
            ? "Batch of " . count($inserted_log_ids) . " laptops logged successfully."
            : "Hardware log saved successfully."
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
