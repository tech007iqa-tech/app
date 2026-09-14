<?php
require_once '../core/database.php';
require_once '../core/auth.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $part_name = trim($_POST['part_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 0);
    $low_stock_threshold = (int)($_POST['low_stock_threshold'] ?? 5);

    if (empty($part_name)) {
        echo json_encode(['success' => false, 'error' => 'Part name is required.']);
        exit();
    }

    try {
        $conn = Database::tech();
        $stmt = $conn->prepare("INSERT INTO parts_inventory (part_name, category, quantity, low_stock_threshold) VALUES (?, ?, ?, ?)");
        $stmt->execute([$part_name, $category, $quantity, $low_stock_threshold]);

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
}
?>
