<?php
require_once '../core/database.php';
require_once '../core/auth.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id = (int)($_POST['id'] ?? 0);
    $adjustment = (int)($_POST['adjustment'] ?? 0);

    if ($id <= 0 || $adjustment === 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid parameters.']);
        exit();
    }

    try {
        $conn = Database::tech();

        // Prevent quantity from dropping below 0
        $stmt = $conn->prepare("UPDATE parts_inventory SET quantity = CASE WHEN quantity + ? < 0 THEN 0 ELSE quantity + ? END, last_updated = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$adjustment, $adjustment, $id]);

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
}
?>
