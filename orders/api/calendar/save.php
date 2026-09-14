<?php
/**
 * Save Event Entry - Integrated API
 */

require_once '../../core/database.php';
include '../../core/auth.php';

// Access Control
$user_role = $_SESSION['role'] ?? 'Operator';
if ($user_role !== 'Admin' && $user_role !== 'Front Desk') {
    die("Unauthorized access.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $event_date = $_POST['event_date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];

    // Business Hours Validation (08:00 - 17:00)
    if ($start_time < "08:00" || $start_time > "17:00" || $end_time < "08:00" || $end_time > "17:00") {
        die("Error: Events must be scheduled between 08:00 AM and 05:00 PM.");
    }

    if ($start_time >= $end_time) {
        die("Error: End time must be after the start time.");
    }

    $color = isset($_POST['color']) ? $_POST['color'] : '#38bdf8';
    $event_id = isset($_POST['event_id']) ? $_POST['event_id'] : '';
    $description = isset($_POST['description']) ? $_POST['description'] : '';
    $customer_id = isset($_POST['customer_id']) ? $_POST['customer_id'] : null;

    try {
        $pdo = Database::calendar();
        if ($event_id) {
            $sql = "UPDATE events SET title = ?, description = ?, event_date = ?, start_time = ?, end_time = ?, color = ?, customer_id = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$title, $description, $event_date, $start_time, $end_time, $color, $customer_id, $event_id]);
        } else {
            $sql = "INSERT INTO events (title, description, event_date, start_time, end_time, color, customer_id) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$title, $description, $event_date, $start_time, $end_time, $color, $customer_id]);
        }

        $week_offset = isset($_POST['week_offset']) ? (int)$_POST['week_offset'] : 0;
        $view_type = isset($_POST['view_type']) ? $_POST['view_type'] : 'week';

        // Redirect back to main app calendar view
        header("Location: ../../index.php?view=calendar&view_type=$view_type&week_offset=$week_offset");
        exit;
    } catch (\PDOException $e) {
        die("Error saving event: " . $e->getMessage());
    }
}
?>
