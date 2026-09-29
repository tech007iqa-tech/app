<?php
/**
 * Granular & Bulk Inventory Migration API Endpoint
 * Handles partial and full item relocations between shelves/zones,
 * item deduplication merging, transaction logging, and depleted location archival.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../core/warehouse_db.php';
require_once __DIR__ . '/../core/Security.php';
require_once __DIR__ . '/../core/Audit.php';

session_start();

// 1. Authentication Check
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

// 2. Parse & Validate Payload
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON input.']);
    exit;
}

if (!Security::validate($input['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid or expired CSRF token.']);
    exit;
}

$target_location = trim($input['target_location'] ?? '');
$target_zone = trim($input['target_zone'] ?? '');
$auto_archive_source = !empty($input['auto_archive_source']);
$current_user = $_SESSION['username'] ?? 'System';

if (empty($target_location)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Target location is required.']);
    exit;
}

// Standardize move instructions into a list of [{ item_id, quantity }]
$move_instructions = [];
if (!empty($input['moves']) && is_array($input['moves'])) {
    foreach ($input['moves'] as $m) {
        if (!empty($m['item_id'])) {
            $move_instructions[] = [
                'item_id' => (int)$m['item_id'],
                'quantity' => isset($m['quantity']) ? (int)$m['quantity'] : null,
                'target_location' => trim($m['target_location'] ?? $target_location),
                'target_zone' => trim($m['target_zone'] ?? $target_zone)
            ];
        }
    }
} elseif (!empty($input['ids']) && is_array($input['ids'])) {
    // Multi-row batch from Bulk Action Bar (full quantity moves)
    foreach ($input['ids'] as $id) {
        $move_instructions[] = [
            'item_id' => (int)$id,
            'quantity' => null, // null means move full quantity
            'target_location' => $target_location,
            'target_zone' => $target_zone
        ];
    }
} elseif (!empty($input['item_id'])) {
    // Single item move
    $move_instructions[] = [
        'item_id' => (int)$input['item_id'],
        'quantity' => isset($input['quantity']) ? (int)$input['quantity'] : null,
        'target_location' => $target_location,
        'target_zone' => $target_zone
    ];
}

if (empty($move_instructions)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No items specified for migration.']);
    exit;
}

try {
    $conn_wh->beginTransaction();

    $source_locations_map = [];
    $total_units_transferred = 0;
    $processed_items_count = 0;

    // Helper: Ensure destination shelf and zone exist and are unarchived
    $prepared_targets = [];
    $ensure_target_location = function($loc_code, $zone_name) use ($conn_wh, &$prepared_targets) {
        if (isset($prepared_targets[$loc_code])) return;

        // Auto-provision working zone if specified and does not exist
        if (!empty($zone_name)) {
            $stmt_wz = $conn_wh->prepare("INSERT OR IGNORE INTO working_zones (name) VALUES (?)");
            $stmt_wz->execute([$zone_name]);
        }

        // Check if location exists
        $stmt_loc = $conn_wh->prepare("SELECT location_code, working_zone_name, is_archived FROM locations WHERE location_code = ?");
        $stmt_loc->execute([$loc_code]);
        $existing = $stmt_loc->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Unarchive if receiving stock
            if (!empty($existing['is_archived'])) {
                $conn_wh->prepare("UPDATE locations SET is_archived = 0, archived_at = NULL, archived_reason = NULL, status = 'Working', updated_at = CURRENT_TIMESTAMP WHERE location_code = ?")->execute([$loc_code]);
            }
            // Update zone if not previously assigned
            if (empty($existing['working_zone_name']) && !empty($zone_name)) {
                $conn_wh->prepare("UPDATE locations SET working_zone_name = ?, updated_at = CURRENT_TIMESTAMP WHERE location_code = ?")->execute([$zone_name, $loc_code]);
            }
        } else {
            // Provision new location
            $stmt_ins = $conn_wh->prepare("INSERT INTO locations (location_code, status, working_zone_name, is_archived, updated_at) VALUES (?, 'Working', ?, 0, CURRENT_TIMESTAMP)");
            $stmt_ins->execute([$loc_code, !empty($zone_name) ? $zone_name : 'General']);
        }

        $prepared_targets[$loc_code] = true;
    };

    // Process each item move
    foreach ($move_instructions as $instruction) {
        $item_id = $instruction['item_id'];
        $item_target_loc = $instruction['target_location'];
        $item_target_zone = $instruction['target_zone'];

        if (empty($item_target_loc)) {
            throw new Exception("Missing target location for item #{$item_id}");
        }

        // Fetch source item with row lock
        $stmt_item = $conn_wh->prepare("SELECT * FROM inventory WHERE id = ?");
        $stmt_item->execute([$item_id]);
        $item = $stmt_item->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            throw new Exception("Inventory item #{$item_id} not found.");
        }

        $current_qty = (int)$item['quantity'];
        $move_qty = ($instruction['quantity'] !== null && $instruction['quantity'] > 0) ? min((int)$instruction['quantity'], $current_qty) : $current_qty;

        if ($move_qty <= 0) {
            throw new Exception("Quantity to move must be greater than zero for item #{$item_id}.");
        }

        $source_loc = $item['location_code'];
        if ($source_loc === $item_target_loc) {
            throw new Exception("Source and target shelf cannot be identical ({$source_loc}).");
        }

        $source_locations_map[$source_loc] = true;

        // Resolve source zone
        $stmt_sz = $conn_wh->prepare("SELECT working_zone_name FROM locations WHERE location_code = ?");
        $stmt_sz->execute([$source_loc]);
        $source_zone = $stmt_sz->fetchColumn() ?: 'General';

        // Ensure destination location exists and is active
        $ensure_target_location($item_target_loc, $item_target_zone);

        // Check if destination shelf already contains an identical item for deduplication
        $stmt_dup = $conn_wh->prepare("
            SELECT id, quantity FROM inventory 
            WHERE location_code = ? 
              AND sector = ? 
              AND brand = ? 
              AND model = ? 
              AND COALESCE(specs_json, '') = COALESCE(?, '') 
              AND id != ?
            LIMIT 1
        ");
        $stmt_dup->execute([
            $item_target_loc,
            $item['sector'],
            $item['brand'],
            $item['model'],
            $item['specs_json'],
            $item_id
        ]);
        $target_match = $stmt_dup->fetch(PDO::FETCH_ASSOC);

        $is_partial = ($move_qty < $current_qty);
        $remaining_source_qty = $current_qty - $move_qty;

        if ($is_partial) {
            // Partial Move: Decrement source row
            $stmt_dec = $conn_wh->prepare("UPDATE inventory SET quantity = quantity - ?, last_updated_by = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt_dec->execute([$move_qty, $current_user, $item_id]);

            if ($target_match) {
                // Merge into existing identical item at destination
                $stmt_inc = $conn_wh->prepare("UPDATE inventory SET quantity = quantity + ?, last_updated_by = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt_inc->execute([$move_qty, $current_user, $target_match['id']]);
            } else {
                // Insert new split item at destination
                $stmt_insert = $conn_wh->prepare("
                    INSERT INTO inventory 
                    (user_owner, sector, location_code, brand, model, specs_json, quantity, status, price, last_updated_by, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ");
                $stmt_insert->execute([
                    $item['user_owner'],
                    $item['sector'],
                    $item_target_loc,
                    $item['brand'],
                    $item['model'],
                    $item['specs_json'],
                    $move_qty,
                    $item['status'] ?? '',
                    $item['price'] ?? 0.00,
                    $current_user
                ]);
            }
        } else {
            // Full Move: Entire stock of this item is moved
            if ($target_match) {
                // Merge into destination item and delete source record
                $stmt_inc = $conn_wh->prepare("UPDATE inventory SET quantity = quantity + ?, last_updated_by = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt_inc->execute([$move_qty, $current_user, $target_match['id']]);

                $stmt_del = $conn_wh->prepare("DELETE FROM inventory WHERE id = ?");
                $stmt_del->execute([$item_id]);
            } else {
                // Reassign location_code of existing source row
                $stmt_reassign = $conn_wh->prepare("UPDATE inventory SET location_code = ?, last_updated_by = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt_reassign->execute([$item_target_loc, $current_user, $item_id]);
            }
        }

        // Record in Relocation Audit Ledger
        $stmt_log = $conn_wh->prepare("
            INSERT INTO inventory_move_logs 
            (inventory_id, source_location, target_location, source_zone, target_zone, quantity_moved, remaining_source_qty, moved_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt_log->execute([
            $item_id,
            $source_loc,
            $item_target_loc,
            $source_zone,
            $item_target_zone,
            $move_qty,
            $remaining_source_qty,
            $current_user
        ]);

        Audit::log('MIGRATE_INVENTORY', $item_id, "Moved {$move_qty} units of {$item['brand']} {$item['model']} from {$source_loc} to {$item_target_loc}", 'warehouse');

        $total_units_transferred += $move_qty;
        $processed_items_count++;
    }

    // 4. Source Locations Balance & Depletion Verification
    $depleted_sources = [];
    $archived_sources = [];

    foreach (array_keys($source_locations_map) as $src_location) {
        $stmt_bal = $conn_wh->prepare("SELECT COUNT(*) AS active_rows, COALESCE(SUM(quantity), 0) AS total_units FROM inventory WHERE location_code = ?");
        $stmt_bal->execute([$src_location]);
        $balance = $stmt_bal->fetch(PDO::FETCH_ASSOC);

        $remaining_units = (int)($balance['total_units'] ?? 0);
        $active_rows = (int)($balance['active_rows'] ?? 0);

        if ($remaining_units === 0 && $active_rows === 0) {
            $depleted_sources[] = $src_location;
            if ($auto_archive_source) {
                $stmt_arch = $conn_wh->prepare("
                    UPDATE locations 
                    SET is_archived = 1, 
                        archived_at = CURRENT_TIMESTAMP, 
                        archived_reason = 'Depleted via Inventory Migration' 
                    WHERE location_code = ?
                ");
                $stmt_arch->execute([$src_location]);
                $archived_sources[] = $src_location;
                Audit::log('ARCHIVE_LOCATION', $src_location, "Auto-archived empty location after inventory migration", 'warehouse');
            }
        }
    }

    $conn_wh->commit();

    echo json_encode([
        'success' => true,
        'moved_items_count' => $processed_items_count,
        'total_units_moved' => $total_units_transferred,
        'source_locations' => array_keys($source_locations_map),
        'depleted_sources' => $depleted_sources,
        'archived_sources' => $archived_sources,
        'target_location' => $target_location,
        'target_zone' => $target_zone
    ]);

} catch (Exception $e) {
    if ($conn_wh->inTransaction()) {
        $conn_wh->rollBack();
    }
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
