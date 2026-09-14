<?php
require_once '../core/database.php';
require_once '../core/auth.php';

header('Content-Type: application/json');

try {
    $conn = Database::tech();

    // Fetch all parts
    $stmt = $conn->prepare("SELECT * FROM parts_inventory ORDER BY category, part_name ASC");
    $stmt->execute();
    $parts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $parts]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
