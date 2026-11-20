<?php
require_once '../core/database.php';
require_once '../core/auth.php';

header('Content-Type: application/json');

$user_role = $_SESSION['role'] ?? '';
$username = $_SESSION['username'] ?? '';
$is_admin = (
    strtolower($user_role) === 'admin' || 
    strtolower($username) === 'admin' || 
    strpos(strtolower($user_role), 'admin') !== false
);

if (!$is_admin) {
    echo json_encode(['success' => false, 'error' => 'Access Denied: Admin role required.']);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit();
}

$action = trim($_POST['action'] ?? '');
$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid Log ID.']);
    exit();
}

try {
    $conn = Database::tech();

    // Check if log exists
    $stmt_check = $conn->prepare("SELECT * FROM logs WHERE id = ?");
    $stmt_check->execute([$id]);
    $log = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$log) {
        echo json_encode(['success' => false, 'error' => 'Log entry not found.']);
        exit();
    }

    if ($action === 'approve_delete') {
        $stmt = $conn->prepare("DELETE FROM logs WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Log entry deleted successfully.']);
        exit();

    } elseif ($action === 'reject_delete') {
        $stmt = $conn->prepare("UPDATE logs SET delete_requested = 2 WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Delete request rejected.']);
        exit();

    } elseif ($action === 'approve_status_change') {
        $requested_status = $log['status_change_requested'] ?: 'Bad';
        $stmt = $conn->prepare("UPDATE logs SET status = ?, status_change_requested = '' WHERE id = ?");
        $stmt->execute([$requested_status, $id]);
        echo json_encode(['success' => true, 'message' => 'Status change approved.']);
        exit();

    } elseif ($action === 'reject_status_change') {
        $stmt = $conn->prepare("UPDATE logs SET status_change_requested = '' WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Status change request rejected.']);
        exit();

    } elseif ($action === 'admin_edit') {
        $qty = (int)($_POST['qty'] ?? 1);
        $make = trim($_POST['make'] ?? '');
        $model = trim($_POST['model'] ?? '');
        $series = trim($_POST['series'] ?? '');
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
            $stmt_lbl_up = $conn_labels->prepare("
                UPDATE items SET
                    brand = ?, model = ?, series = ?,
                    cpu_specs = ?, ram = ?, storage = ?, gpu = ?,
                    os_version = ?, work_notes = ?,
                    bios_state = ?, battery = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt_lbl_up->execute([
                $make, $model, $series,
                $cpu, $ram, $storage, $gpu,
                $os, $notes_clean,
                $bios_state, $battery_int, $current_label_id
            ]);
            $final_label_id = $current_label_id;
        } else {
            $stmt_spec = $conn_labels->prepare("
                SELECT id FROM items
                WHERE LOWER(TRIM(brand)) = LOWER(TRIM(:brand))
                  AND LOWER(TRIM(model)) = LOWER(TRIM(:model))
                  AND (LOWER(TRIM(COALESCE(series, ''))) = LOWER(TRIM(:series)) OR (:series = '' AND (series IS NULL OR series = '')))
                  AND (LOWER(TRIM(COALESCE(ram, ''))) = LOWER(TRIM(:ram)) OR (:ram = '' AND (ram IS NULL OR ram = ''))
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
                        brand, model, series,
                        cpu_specs, ram, storage, gpu,
                        os_version, work_notes,
                        bios_state, battery, description, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Refurbished', 'Tested')
                ");
                $stmt_ins->execute([
                    $make, $model, $series,
                    $cpu, $ram, $storage, $gpu,
                    $os, $notes_clean,
                    $bios_state, $battery_int
                ]);
                $final_label_id = (int)$conn_labels->lastInsertId();
            }
        }

        $stmt = $conn->prepare("
            UPDATE logs
            SET qty = ?, make = ?, model = ?, series = ?, cpu = ?, gpu = ?, ram = ?, storage = ?, battery = ?, bios_state = ?, os = ?, batch_number = ?, notes = ?, label_id = ?, edited = 1
            WHERE id = ?
        ");
        $stmt->execute([$qty, $make, $model, $series, $cpu, $gpu, $ram, $storage, $battery, $bios_state, $os, $batch_number, $notes, $final_label_id, $id]);
        echo json_encode(['success' => true, 'message' => 'Log updated successfully by Admin.', 'label_id' => $final_label_id]);
        exit();

    } elseif ($action === 'admin_delete') {
        $stmt = $conn->prepare("DELETE FROM logs WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Log deleted permanently by Admin.']);
        exit();

    } elseif ($action === 'admin_toggle_status') {
        $new_status = ($log['status'] === 'Good') ? 'Bad' : 'Good';
        $stmt = $conn->prepare("UPDATE logs SET status = ?, status_change_requested = '' WHERE id = ?");
        $stmt->execute([$new_status, $id]);
        echo json_encode(['success' => true, 'status' => $new_status, 'message' => 'Status toggled immediately by Admin.']);
        exit();

    } else {
        echo json_encode(['success' => false, 'error' => 'Unknown action.']);
        exit();
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
