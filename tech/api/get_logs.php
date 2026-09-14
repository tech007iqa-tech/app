<?php
require_once '../core/database.php';
require_once '../core/auth.php';

header('Content-Type: application/json');

try {
    $conn = Database::tech();

    $conditions = [];
    $params = [];

    // 1. Date Range Filtering
    $start_date = trim($_GET['start_date'] ?? '');
    $end_date = trim($_GET['end_date'] ?? '');

    if ($start_date !== '' && $end_date !== '') {
        $conditions[] = "date(created_at, 'localtime') >= :start_date AND date(created_at, 'localtime') <= :end_date";
        $params[':start_date'] = $start_date;
        $params[':end_date'] = $end_date;
    } else {
        // Default to today
        $conditions[] = "date(created_at, 'localtime') = date('now', 'localtime')";
    }

    // 2. Status Filtering
    $status = trim($_GET['status'] ?? '');
    if ($status !== '') {
        $conditions[] = "status = :status";
        $params[':status'] = $status;
    }

    // 3. User Access Control & Tech Filtering
    $is_admin = ($_SESSION['role'] === 'Admin');
    if ($is_admin) {
        $tech_filter = trim($_GET['tech_id'] ?? '');
        if ($tech_filter !== '') {
            $conditions[] = "tech_id = :tech_id";
            $params[':tech_id'] = $tech_filter;
        }
    } else {
        // Enforce tech sees only their own work
        $conditions[] = "tech_id = :logged_in_tech";
        $params[':logged_in_tech'] = $_SESSION['username'];
    }

    $where_clause = implode(" AND ", $conditions);
    $query = "SELECT * FROM logs WHERE {$where_clause} ORDER BY created_at DESC";

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $logs]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
