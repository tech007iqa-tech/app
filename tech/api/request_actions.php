<?php
require_once '../core/database.php';
require_once '../core/auth.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit();
}

$action = trim($_POST['action'] ?? '');
$id = (int)($_POST['id'] ?? 0);
$username = $_SESSION['username'] ?? '';
$user_role = $_SESSION['role'] ?? '';
$is_admin = (
    strtolower($user_role) === 'admin' || 
    strtolower($username) === 'admin' || 
    strpos(strtolower($user_role), 'admin') !== false
);

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid Log ID.']);
    exit();
}

try {
    $conn = Database::tech();

    // Check if the log belongs to this user (or user is Admin)
    $stmt_check = $conn->prepare("SELECT * FROM logs WHERE id = ?");
    $stmt_check->execute([$id]);
    $log = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$log) {
        echo json_encode(['success' => false, 'error' => 'Log entry not found.']);
        exit();
    }

    if (!$is_admin && $log['tech_id'] !== $username) {
        if ($action === 'request_delete') {
            $stmt_req = $conn->prepare("UPDATE logs SET delete_requested = 1 WHERE id = ?");
            $stmt_req->execute([$id]);
            echo json_encode(['success' => true, 'pending' => true, 'message' => 'Delete request submitted to Admin for approval.']);
            exit();
        } elseif ($action === 'toggle_status') {
            $stmt_req = $conn->prepare("UPDATE logs SET status_change_requested = 'Bad' WHERE id = ?");
            $stmt_req->execute([$id]);
            echo json_encode(['success' => true, 'pending' => true, 'message' => 'Status change request submitted to Admin for approval.']);
            exit();
        } else {
            echo json_encode(['success' => false, 'error' => 'Access Denied: You cannot modify logs belonging to other technicians.']);
            exit();
        }
    }

    // Handle Actions
    if ($action === 'edit') {
        $qty = (int)($_POST['qty'] ?? 1);
        $make = trim($_POST['make'] ?? '');
        $model = trim($_POST['model'] ?? '');
        $series = trim($_POST['series'] ?? '');
        $serial_number = trim($_POST['serial_number'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $delivery_status = trim($_POST['delivery_status'] ?? '');
        $delivery_destination = trim($_POST['delivery_destination'] ?? '');
        $cpu = trim($_POST['cpu'] ?? '');
        $gpu = trim($_POST['gpu'] ?? '');
        $ram = trim($_POST['ram'] ?? '');
        $storage = trim($_POST['storage'] ?? '');
        $battery = trim($_POST['battery'] ?? '');
        $bios_state = trim($_POST['bios_state'] ?? '');
        $os = trim($_POST['os'] ?? '');
        $batch_number = trim($_POST['batch_number'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if (empty($make) || empty($model)) {
            echo json_encode(['success' => false, 'error' => 'Make and Model are required.']);
            exit();
        }

        // ── SYNCHRONIZE WITH LABELS.DB ──
        $conn_labels = Database::labels();
        $notes_words = array_values(array_filter(preg_split('/\s+/', $notes), fn($w) => $w !== ''));
        $notes_clean = implode(' ', array_slice($notes_words, 0, 3));
        $battery_int = (stripos($battery, 'Good') !== false || $battery === '1') ? 1 : (stripos($battery, 'Dead') !== false || $battery === '0' ? 0 : null);

        $current_label_id = (int)($log['label_id'] ?? 0);
        $final_label_id = $current_label_id;

        $other_logs_count = 0;
        if ($current_label_id > 0) {
            $stmt_cnt = $conn->prepare("SELECT COUNT(*) FROM logs WHERE label_id = ? AND id != ?");
            $stmt_cnt->execute([$current_label_id, $id]);
            $other_logs_count = (int)$stmt_cnt->fetchColumn();
        }

        if ($current_label_id > 0 && $other_logs_count === 0) {
            // Only this log uses this label: update the existing master label in labels.db directly!
            $stmt_lbl_up = $conn_labels->prepare("
                UPDATE items SET
                    brand = ?, model = ?, series = ?, serial_number = ?,
                    cpu_specs = ?, ram = ?, storage = ?, gpu = ?,
                    os_version = ?, warehouse_location = ?, work_notes = ?,
                    bios_state = ?, battery = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt_lbl_up->execute([
                $make, $model, $series, $serial_number,
                $cpu, $ram, $storage, $gpu,
                $os, $location, $notes_clean,
                $bios_state, $battery_int, $current_label_id
            ]);
            $final_label_id = $current_label_id;
        } else {
            // Other logs share this label or no label exists: check if a label matches the edited specs or create a new one
            $stmt_spec = $conn_labels->prepare("
                SELECT id FROM items
                WHERE LOWER(TRIM(brand)) = LOWER(TRIM(:brand))
                  AND LOWER(TRIM(model)) = LOWER(TRIM(:model))
                  AND (LOWER(TRIM(COALESCE(series, ''))) = LOWER(TRIM(:series)) OR (:series = '' AND (series IS NULL OR series = '')))
                  AND (LOWER(TRIM(COALESCE(ram, ''))) = LOWER(TRIM(:ram)) OR (:ram = '' AND (ram IS NULL OR ram = '')))
                  AND (LOWER(TRIM(COALESCE(storage, ''))) = LOWER(TRIM(:storage)) OR (:storage = '' AND (storage IS NULL OR storage = '')))
                  AND (LOWER(TRIM(COALESCE(os_version, ''))) = LOWER(TRIM(:os)) OR (:os = '' AND (os_version IS NULL OR os_version = '')))
                ORDER BY id ASC LIMIT 1
            ");
            $stmt_spec->execute([
                ':brand'   => $make,
                ':model'   => $model,
                ':series'  => $series,
                ':ram'     => $ram,
                ':storage' => $storage,
                ':os'      => $os
            ]);
            $matched_id = $stmt_spec->fetchColumn();

            if ($matched_id) {
                $final_label_id = (int)$matched_id;
            } else {
                $stmt_ins = $conn_labels->prepare("
                    INSERT INTO items (
                        brand, model, series, serial_number,
                        cpu_specs, ram, storage, gpu,
                        os_version, warehouse_location, work_notes,
                        bios_state, battery, description, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Refurbished', 'Tested')
                ");
                $stmt_ins->execute([
                    $make, $model, $series, $serial_number,
                    $cpu, $ram, $storage, $gpu,
                    $os, $location, $notes_clean,
                    $bios_state, $battery_int
                ]);
                $final_label_id = (int)$conn_labels->lastInsertId();
            }
        }

        $stmt_up = $conn->prepare("
            UPDATE logs
            SET qty = ?, make = ?, model = ?, series = ?, serial_number = ?, location = ?, delivery_status = ?, delivery_destination = ?, batch_number = ?, cpu = ?, gpu = ?, ram = ?, storage = ?, battery = ?, bios_state = ?, os = ?, notes = ?, label_id = ?, edited = 1
            WHERE id = ?
        ");
        $stmt_up->execute([$qty, $make, $model, $series, $serial_number, $location, $delivery_status, $delivery_destination, $batch_number, $cpu, $gpu, $ram, $storage, $battery, $bios_state, $os, $notes, $final_label_id, $id]);

        echo json_encode(['success' => true, 'message' => 'Log updated successfully.', 'label_id' => $final_label_id]);
        exit();

    } elseif ($action === 'request_delete' || $action === 'admin_delete' || $action === 'delete') {
        if ($is_admin) {
            // Admin deletes immediately
            $stmt_del = $conn->prepare("DELETE FROM logs WHERE id = ?");
            $stmt_del->execute([$id]);
            echo json_encode(['success' => true, 'immediate' => true, 'message' => 'Log entry deleted immediately by Admin.']);
            exit();
        } else {
            // Technician requests delete
            $stmt_req = $conn->prepare("UPDATE logs SET delete_requested = 1 WHERE id = ?");
            $stmt_req->execute([$id]);
            echo json_encode(['success' => true, 'pending' => true, 'message' => 'Delete request submitted to Admin.']);
            exit();
        }

    } elseif ($action === 'approve_delete') {
        if (!$is_admin) {
            echo json_encode(['success' => false, 'error' => 'Access Denied: Admin role required to approve deletion.']);
            exit();
        }
        $stmt_del = $conn->prepare("DELETE FROM logs WHERE id = ?");
        $stmt_del->execute([$id]);
        echo json_encode(['success' => true, 'immediate' => true, 'message' => 'Delete request approved and log entry deleted.']);
        exit();

    } elseif ($action === 'toggle_status') {
        $current_status = $log['status'];

        if ($current_status === 'Bad') {
            // Toggle Bad -> Good is allowed immediately
            $stmt_up = $conn->prepare("UPDATE logs SET status = 'Good', status_change_requested = '' WHERE id = ?");
            $stmt_up->execute([$id]);
            echo json_encode(['success' => true, 'immediate' => true, 'status' => 'Good', 'message' => 'Status changed immediately to Good.']);
            exit();
        } else {
            // Toggle Good -> Bad is limited to 5/day per technician
            if ($is_admin) {
                // Admin has no limit
                $stmt_up = $conn->prepare("UPDATE logs SET status = 'Bad', status_change_requested = '' WHERE id = ?");
                $stmt_up->execute([$id]);
                echo json_encode(['success' => true, 'immediate' => true, 'status' => 'Bad', 'message' => 'Status changed immediately to Bad by Admin.']);
                exit();
            }

            // Check technician daily limit (Pacific Time)
            $tech_id = $log['tech_id'];
            $today = date('Y-m-d');

            $stmt_limit = $conn->prepare("SELECT change_count FROM daily_status_changes WHERE tech_id = ? AND change_date = ?");
            $stmt_limit->execute([$tech_id, $today]);
            $count = $stmt_limit->fetchColumn();

            if ($count === false) {
                // First change today
                $stmt_ins = $conn->prepare("INSERT INTO daily_status_changes (tech_id, change_date, change_count) VALUES (?, ?, 1)");
                $stmt_ins->execute([$tech_id, $today]);

                $stmt_up = $conn->prepare("UPDATE logs SET status = 'Bad', status_change_requested = '' WHERE id = ?");
                $stmt_up->execute([$id]);

                echo json_encode(['success' => true, 'immediate' => true, 'status' => 'Bad', 'message' => 'Status changed to Bad. Daily change count: 1/5.']);
                exit();
            } elseif ((int)$count < 5) {
                // Below daily limit
                $new_count = (int)$count + 1;
                $stmt_up_count = $conn->prepare("UPDATE daily_status_changes SET change_count = ? WHERE tech_id = ? AND change_date = ?");
                $stmt_up_count->execute([$new_count, $tech_id, $today]);

                $stmt_up = $conn->prepare("UPDATE logs SET status = 'Bad', status_change_requested = '' WHERE id = ?");
                $stmt_up->execute([$id]);

                echo json_encode(['success' => true, 'immediate' => true, 'status' => 'Bad', 'message' => "Status changed to Bad. Daily change count: {$new_count}/5."]);
                exit();
            } else {
                // Limit reached: request status change
                $stmt_req = $conn->prepare("UPDATE logs SET status_change_requested = 'Bad' WHERE id = ?");
                $stmt_req->execute([$id]);

                echo json_encode(['success' => true, 'pending' => true, 'message' => 'Daily limit of 5 status changes reached. Request submitted to Admin.']);
                exit();
            }
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Unknown action.']);
        exit();
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
