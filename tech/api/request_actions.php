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
$username = $_SESSION['username'];
$is_admin = ($_SESSION['role'] === 'Admin');

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
        echo json_encode(['success' => false, 'error' => 'Access Denied: You cannot modify logs belonging to other technicians.']);
        exit();
    }

    // Handle Actions
    if ($action === 'edit') {
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
        $notes = trim($_POST['notes'] ?? '');

        if (empty($make) || empty($model)) {
            echo json_encode(['success' => false, 'error' => 'Make and Model are required.']);
            exit();
        }

        $stmt_up = $conn->prepare("
            UPDATE logs
            SET qty = ?, make = ?, model = ?, series = ?, cpu = ?, gpu = ?, ram = ?, storage = ?, battery = ?, bios_state = ?, os = ?, notes = ?, edited = 1
            WHERE id = ?
        ");
        $stmt_up->execute([$qty, $make, $model, $series, $cpu, $gpu, $ram, $storage, $battery, $bios_state, $os, $notes, $id]);

        echo json_encode(['success' => true, 'message' => 'Log updated successfully.']);
        exit();

    } elseif ($action === 'request_delete') {
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

            // Check technician daily limit
            $tech_id = $log['tech_id'];
            $today = date('now', time()); // SQLite will use current date anyway

            $stmt_limit = $conn->prepare("SELECT change_count FROM daily_status_changes WHERE tech_id = ? AND change_date = date('now', 'localtime')");
            $stmt_limit->execute([$tech_id]);
            $count = $stmt_limit->fetchColumn();

            if ($count === false) {
                // First change today
                $stmt_ins = $conn->prepare("INSERT INTO daily_status_changes (tech_id, change_date, change_count) VALUES (?, date('now', 'localtime'), 1)");
                $stmt_ins->execute([$tech_id]);

                $stmt_up = $conn->prepare("UPDATE logs SET status = 'Bad', status_change_requested = '' WHERE id = ?");
                $stmt_up->execute([$id]);

                echo json_encode(['success' => true, 'immediate' => true, 'status' => 'Bad', 'message' => 'Status changed to Bad. Daily change count: 1/5.']);
                exit();
            } elseif ((int)$count < 5) {
                // Below daily limit
                $new_count = (int)$count + 1;
                $stmt_up_count = $conn->prepare("UPDATE daily_status_changes SET change_count = ? WHERE tech_id = ? AND change_date = date('now', 'localtime')");
                $stmt_up_count->execute([$new_count, $tech_id]);

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
