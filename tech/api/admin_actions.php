<?php
require_once '../core/database.php';
require_once '../core/auth.php';

header('Content-Type: application/json');

if ($_SESSION['role'] !== 'Admin') {
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
        $notes = trim($_POST['notes'] ?? '');

        if (empty($make) || empty($model)) {
            echo json_encode(['success' => false, 'error' => 'Make and Model are required.']);
            exit();
        }

        $stmt = $conn->prepare("
            UPDATE logs
            SET qty = ?, make = ?, model = ?, series = ?, cpu = ?, gpu = ?, ram = ?, storage = ?, battery = ?, bios_state = ?, notes = ?, edited = 1
            WHERE id = ?
        ");
        $stmt->execute([$qty, $make, $model, $series, $cpu, $gpu, $ram, $storage, $battery, $bios_state, $notes, $id]);
        echo json_encode(['success' => true, 'message' => 'Log updated successfully by Admin.']);
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
