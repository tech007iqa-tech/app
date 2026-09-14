<?php
// orders/api/manage_location_status.php
header('Content-Type: application/json');
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/Security.php';
session_start();

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access.']);
    exit;
}

$conn_wh = Database::warehouse();

$CANONICAL_DEFAULTS = ['working', 'audit', 'shipping', 'in-review', 'warehoused', 'idle'];

function getStatusPayload($conn_wh, $loc = null) {
    $globals = $conn_wh->query("SELECT MIN(rowid) AS id, name, color, is_default, location_code FROM location_statuses 
        WHERE location_code IS NULL OR location_code = '' OR location_code = 'GLOBAL' 
        GROUP BY name
        ORDER BY is_default DESC, name ASC")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($globals as &$g) {
        $g['is_global'] = true;
    }

    $custom_status = null;
    if (!empty($loc) && $loc !== 'GLOBAL') {
        $stmt_c = $conn_wh->prepare("SELECT rowid AS id, name, color, is_default, location_code FROM location_statuses 
            WHERE location_code = ? ORDER BY rowid DESC LIMIT 1");
        $stmt_c->execute([$loc]);
        $custom_status = $stmt_c->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($custom_status) {
            $custom_status['is_global'] = false;
        }
    }

    // Fetch distinct custom statuses created across other shelves
    $other_custom = [];
    $stmt_oth = $conn_wh->query("
        SELECT MIN(rowid) AS id, name, color, 0 AS is_default, GROUP_CONCAT(location_code, ', ') AS location_codes
        FROM location_statuses
        WHERE location_code IS NOT NULL AND location_code != '' AND location_code != 'GLOBAL'
        GROUP BY name
        ORDER BY name ASC
    ");
    $other_custom = $stmt_oth->fetchAll(PDO::FETCH_ASSOC);
    foreach ($other_custom as &$oc) {
        $oc['is_global'] = false;
    }

    // Build deduplicated combined list for general management view
    $all_distinct = [];
    $seen = [];
    if ($custom_status) {
        $all_distinct[] = $custom_status;
        $seen[strtolower($custom_status['name'])] = true;
    }
    foreach ($globals as $g) {
        if (!isset($seen[strtolower($g['name'])])) {
            $all_distinct[] = $g;
            $seen[strtolower($g['name'])] = true;
        }
    }
    foreach ($other_custom as $oc) {
        if (!isset($seen[strtolower($oc['name'])])) {
            $all_distinct[] = $oc;
            $seen[strtolower($oc['name'])] = true;
        }
    }

    return [
        'global_statuses' => $globals,
        'custom_status' => $custom_status,
        'other_custom_statuses' => $other_custom,
        'statuses' => $all_distinct
    ];
}

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    $loc = $_GET['loc'] ?? null;
    $data = getStatusPayload($conn_wh, $loc);
    echo json_encode(array_merge(['success' => true], $data));
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    if (!Security::validate($input['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }

    $action = $input['action'] ?? '';
    $loc = trim($input['location_code'] ?? '');

    try {
        if ($action === 'save_custom' || $action === 'add') {
            $name = trim($input['name'] ?? '');
            $color = trim($input['color'] ?? '#3b82f6');

            if (empty($name)) {
                http_response_code(400);
                echo json_encode(['error' => 'Status name cannot be empty.']);
                exit;
            }

            if (empty($loc) || $loc === 'GLOBAL') {
                http_response_code(400);
                echo json_encode(['error' => 'A specific location code is required for custom statuses.']);
                exit;
            }

            $conn_wh->beginTransaction();

            // Check if this location already has a custom status
            $stmt_chk = $conn_wh->prepare("SELECT rowid AS id, name FROM location_statuses WHERE location_code = ?");
            $stmt_chk->execute([$loc]);
            $existing_custom = $stmt_chk->fetch(PDO::FETCH_ASSOC);

            if ($existing_custom) {
                // Update existing custom status for this location
                $stmt_up = $conn_wh->prepare("UPDATE location_statuses SET name = ?, color = ? WHERE rowid = ?");
                $stmt_up->execute([$name, $color, $existing_custom['id']]);
            } else {
                // Insert new custom status for this location
                $stmt_ins = $conn_wh->prepare("INSERT INTO location_statuses (name, color, is_default, location_code) VALUES (?, ?, 0, ?)");
                $stmt_ins->execute([$name, $color, $loc]);
            }

            // Set this location's active status to the custom status
            $stmt_loc_up = $conn_wh->prepare("UPDATE locations SET status = ? WHERE location_code = ?");
            $stmt_loc_up->execute([$name, $loc]);

            $conn_wh->commit();

            $data = getStatusPayload($conn_wh, $loc);
            echo json_encode(array_merge([
                'success' => true,
                'message' => "Custom status '{$name}' saved for {$loc} ✨"
            ], $data));
            exit;
        }

        if ($action === 'promote_global') {
            $id = (int)($input['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid status ID.']);
                exit;
            }

            $stmt_cur = $conn_wh->prepare("SELECT rowid AS id, name, color, is_default, location_code FROM location_statuses WHERE rowid = ?");
            $stmt_cur->execute([$id]);
            $current = $stmt_cur->fetch(PDO::FETCH_ASSOC);

            if (!$current) {
                http_response_code(404);
                echo json_encode(['error' => 'Status not found.']);
                exit;
            }

            // Check if a global status with this name already exists
            $stmt_dup = $conn_wh->prepare("SELECT COUNT(*) FROM location_statuses WHERE LOWER(name) = LOWER(?) AND rowid != ? AND (location_code IS NULL OR location_code = '' OR location_code = 'GLOBAL')");
            $stmt_dup->execute([$current['name'], $id]);
            if ($stmt_dup->fetchColumn() > 0) {
                http_response_code(400);
                echo json_encode(['error' => 'A global status with this name already exists.']);
                exit;
            }

            $stmt_up = $conn_wh->prepare("UPDATE location_statuses SET location_code = NULL WHERE rowid = ?");
            $stmt_up->execute([$id]);

            $data = getStatusPayload($conn_wh, $loc);
            echo json_encode(array_merge([
                'success' => true,
                'message' => "Status '{$current['name']}' promoted to Global Status 🌍"
            ], $data));
            exit;
        }

        if ($action === 'edit') {
            $id = (int)($input['id'] ?? 0);
            $new_name = trim($input['name'] ?? '');
            $new_color = trim($input['color'] ?? '#64748b');

            if ($id <= 0 || empty($new_name)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid status ID or empty name.']);
                exit;
            }

            $stmt_cur = $conn_wh->prepare("SELECT rowid AS id, name, color, is_default, location_code FROM location_statuses WHERE rowid = ?");
            $stmt_cur->execute([$id]);
            $current = $stmt_cur->fetch(PDO::FETCH_ASSOC);

            if (!$current) {
                http_response_code(404);
                echo json_encode(['error' => 'Status not found.']);
                exit;
            }

            $old_name = $current['name'];

            $conn_wh->beginTransaction();

            $stmt_up = $conn_wh->prepare("UPDATE location_statuses SET name = ?, color = ? WHERE rowid = ?");
            $stmt_up->execute([$new_name, $new_color, $id]);

            if ($old_name !== $new_name) {
                $stmt_loc_up = $conn_wh->prepare("UPDATE locations SET status = ? WHERE status = ?");
                $stmt_loc_up->execute([$new_name, $old_name]);
            }

            $conn_wh->commit();

            $data = getStatusPayload($conn_wh, $loc);
            echo json_encode(array_merge([
                'success' => true,
                'message' => "Status updated successfully.",
            ], $data));
            exit;
        }

        if ($action === 'delete') {
            $id = (int)($input['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid status ID.']);
                exit;
            }

            $stmt_cur = $conn_wh->prepare("SELECT rowid AS id, name, color, is_default, location_code FROM location_statuses WHERE rowid = ?");
            $stmt_cur->execute([$id]);
            $current = $stmt_cur->fetch(PDO::FETCH_ASSOC);

            if (!$current) {
                http_response_code(404);
                echo json_encode(['error' => 'Status not found.']);
                exit;
            }

            if ((int)$current['is_default'] === 1 || in_array(strtolower($current['name']), $CANONICAL_DEFAULTS)) {
                http_response_code(403);
                echo json_encode(['error' => 'Default system statuses cannot be deleted. You can only edit them.']);
                exit;
            }

            $status_name = $current['name'];

            $conn_wh->beginTransaction();

            // Revert locations using this status to 'Idle'
            $stmt_loc_fb = $conn_wh->prepare("UPDATE locations SET status = 'Idle' WHERE status = ?");
            $stmt_loc_fb->execute([$status_name]);

            // Delete status
            $stmt_del = $conn_wh->prepare("DELETE FROM location_statuses WHERE rowid = ?");
            $stmt_del->execute([$id]);

            $conn_wh->commit();

            $data = getStatusPayload($conn_wh, $loc);
            echo json_encode(array_merge([
                'success' => true,
                'message' => "Custom status '{$status_name}' was deleted.",
            ], $data));
            exit;
        }

        http_response_code(400);
        echo json_encode(['error' => 'Unknown action.']);
    } catch (Exception $e) {
        if (isset($conn_wh) && $conn_wh->inTransaction()) {
            $conn_wh->rollBack();
        }
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
