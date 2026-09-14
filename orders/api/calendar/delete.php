<?php
/**
 * Delete Event Entry - Integrated API
 */

require_once '../../core/database.php';
include '../../core/auth.php';

// Access Control
$user_role = $_SESSION['role'] ?? 'Operator';
if ($user_role !== 'Admin' && $user_role !== 'Front Desk') {
    die("Unauthorized access.");
}

// CSRF Protection
if (!Security::validate($_GET['csrf_token'] ?? '')) {
    die("Security Error: CSRF token validation failed.");
}

$id = $_GET['id'] ?? null;
$week_offset = $_GET['week_offset'] ?? 0;
$view_type = $_GET['view_type'] ?? 'week';

if ($id) {
    try {
        $pdo = Database::calendar();
        $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
        $stmt->execute([$id]);

        header("Location: ../../index.php?view=calendar&view_type=$view_type&week_offset=$week_offset");
        exit;
    } catch (\PDOException $e) {
        die("Error deleting event: " . $e->getMessage());
    }
} else {
    die("Invalid request.");
}
?>
