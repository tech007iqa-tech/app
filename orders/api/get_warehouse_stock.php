<?php
include '../core/warehouse_db.php';
include '../core/auth.php';

header('Content-Type: application/json');

$query = $_GET['q'] ?? '';
$sector = $_GET['sector'] ?? '';

try {
    $words = array_filter(preg_split('/\s+/', trim($query)));

    $sql = "SELECT * FROM inventory WHERE 1=1";
    $params = [];

    if (!empty($sector)) {
        $sql .= " AND sector = ?";
        $params[] = $sector;
    }

    if (!empty($words)) {
        foreach ($words as $word) {
            $sql .= " AND (brand LIKE ? OR model LIKE ? OR location_code LIKE ? OR specs_json LIKE ?)";
            $word_param = "%$word%";
            $params[] = $word_param;
            $params[] = $word_param;
            $params[] = $word_param;
            $params[] = $word_param;
        }
    }
    $sql .= " ORDER BY id DESC LIMIT 50";
    $stmt = $conn_wh->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Unpack JSON specs for the frontend
    foreach ($items as &$item) {
        $item['specs'] = json_decode($item['specs_json'], true);
    }

    echo json_encode($items);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
