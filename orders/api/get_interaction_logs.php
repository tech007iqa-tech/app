<?php
require_once '../core/database.php';
include '../core/auth.php';

header('Content-Type: application/json');

$customer_id = $_GET['customer_id'] ?? null;
if (!$customer_id) {
    echo json_encode([]);
    exit();
}

try {
    $conn = Database::customers();

    $stmt = $conn->prepare("SELECT * FROM interaction_logs WHERE customer_id = ? ORDER BY contact_date DESC, created_at DESC");
    $stmt->execute([$customer_id]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($logs);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
