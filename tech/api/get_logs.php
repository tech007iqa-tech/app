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

    $today = date('Y-m-d');
    if ($start_date !== '' && $end_date !== '') {
        $conditions[] = "date(created_at, 'localtime') >= :start_date AND date(created_at, 'localtime') <= :end_date";
        $params[':start_date'] = $start_date;
        $params[':end_date'] = $end_date;
    } else {
        // Default to today in Pacific Time
        $conditions[] = "date(created_at, 'localtime') = :today";
        $params[':today'] = $today;
    }

    // 2. Status Filtering
    $status = trim($_GET['status'] ?? '');
    if ($status !== '') {
        $conditions[] = "status = :status";
        $params[':status'] = $status;
    }

    // 3. User Access Control & Tech Filtering
    $user_role = $_SESSION['role'] ?? '';
    $username = $_SESSION['username'] ?? '';
    $is_admin = (
        strtolower($user_role) === 'admin' || 
        strtolower($username) === 'admin' || 
        strpos(strtolower($user_role), 'admin') !== false
    );
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

    // 4. Delivery Status Filtering
    $delivery_filter = trim($_GET['delivery_status'] ?? '');
    if ($delivery_filter !== '') {
        $conditions[] = "delivery_status = :delivery_status";
        $params[':delivery_status'] = $delivery_filter;
    }

    // 5. Search Query (Serial, Make, Model, Batch #)
    $search_query = trim($_GET['q'] ?? '');
    if ($search_query !== '') {
        $conditions[] = "(serial_number LIKE :q OR make LIKE :q OR model LIKE :q OR series LIKE :q OR batch_number LIKE :q)";
        $params[':q'] = '%' . $search_query . '%';
    }

    $where_clause = implode(" AND ", $conditions);
    $query = "SELECT * FROM logs WHERE {$where_clause} ORDER BY created_at DESC";

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. Compute Real-time Today's Technician Productivity Stats (Pacific Time)
    $stmt_stats = $conn->prepare("
        SELECT 
            tech_id,
            COUNT(*) as entry_count,
            SUM(qty) as total_units,
            SUM(CASE WHEN status = 'Good' THEN qty ELSE 0 END) as good_units,
            SUM(CASE WHEN status = 'Bad' THEN qty ELSE 0 END) as bad_units,
            MAX(created_at) as last_activity
        FROM logs
        WHERE date(created_at, 'localtime') = ?
        GROUP BY tech_id
        ORDER BY total_units DESC
    ");
    $stmt_stats->execute([$today]);
    $tech_stats = $stmt_stats->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $logs,
        'tech_stats' => $tech_stats
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
