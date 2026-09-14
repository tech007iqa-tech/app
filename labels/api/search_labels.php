<?php
// api/search_labels.php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/hardware_mapping.php';

try {
    $query = $_GET['q'] ?? '';
    $limit = 50;

    $sql = "SELECT * FROM items";
    $params = [];

    if (!empty($query)) {
        $sql .= " WHERE brand LIKE ? OR model LIKE ? OR serial_number LIKE ? OR location LIKE ?";
        $searchTerm = "%$query%";
        $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
    }

    $sql .= " ORDER BY created_at DESC LIMIT $limit";

    $stmt = $pdo_labels->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $items,
        'count' => count($items)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
