<?php
require_once '../core/database.php';
require_once '../core/auth.php';

header('Content-Type: application/json');

$query = trim($_GET['q'] ?? '');

try {
    $conn = Database::tech();

    if (strlen($query) < 1) {
        // Return last 100 logs if no query
        $stmt = $conn->prepare("SELECT * FROM logs ORDER BY created_at DESC LIMIT 100");
        $stmt->execute();
    } else {
        // Search in series, make, model, or notes
        $stmt = $conn->prepare("SELECT * FROM logs WHERE series LIKE ? OR make LIKE ? OR model LIKE ? ORDER BY created_at DESC LIMIT 50");
        $searchTerm = '%' . $query . '%';
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    }

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $results]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
