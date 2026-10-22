<?php
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/auth.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id = (int)($_POST['id'] ?? 0);
    $status_type = trim($_POST['type'] ?? 'shelf'); // 'shelf' or 'order'
    $destination = trim($_POST['destination'] ?? '');

    if ($id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid Log ID.']);
        exit();
    }

    try {
        $conn = Database::tech();

        if ($status_type === 'order') {
            $delivery_status = 'Delivered to Order';
            $stmt = $conn->prepare("UPDATE logs SET delivery_status = ?, delivery_destination = ? WHERE id = ?");
            $stmt->execute([$delivery_status, $destination, $id]);
        } else {
            $delivery_status = 'Delivered to Shelf';
            $stmt = $conn->prepare("UPDATE logs SET ready_for_warehouse = 1, delivery_status = ?, delivery_destination = COALESCE(NULLIF(?, ''), location, 'Warehouse') WHERE id = ?");
            $stmt->execute([$delivery_status, $destination, $id]);
        }

        echo json_encode([
            'success' => true,
            'delivery_status' => $delivery_status,
            'delivery_destination' => $destination,
            'message' => "Item marked as {$delivery_status}."
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
}
