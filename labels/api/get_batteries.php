<?php
/**
 * labels/api/get_batteries.php
 * Real-time Multi-Keyword Cross-Matching Search & Inventory Query Endpoint
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $q = trim($_GET['q'] ?? '');
    $brand = trim($_GET['brand'] ?? 'all');
    $mode = trim($_GET['mode'] ?? 'smart'); // 'smart', 'laptop', 'battery', 'all'
    $status = trim($_GET['status'] ?? 'all');
    $limit = max(1, min(200, (int)($_GET['limit'] ?? 100)));

    $sql = "SELECT * FROM batteries WHERE 1=1";
    $params = [];

    // Filter by Brand
    if ($brand !== 'all' && $brand !== '') {
        $sql .= " AND LOWER(brand) = :brand";
        $params[':brand'] = strtolower($brand);
    }

    // Filter by Status
    if ($status !== 'all' && $status !== '') {
        $sql .= " AND LOWER(status) = :status";
        $params[':status'] = strtolower($status);
    }

    // Multi-Keyword Search Logic
    if ($q !== '') {
        $tokens = preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY);
        $tokenIdx = 0;

        foreach ($tokens as $token) {
            $paramName = ":token_{$tokenIdx}";
            $likeVal = '%' . $token . '%';

            if ($mode === 'laptop') {
                // Focus search exclusively on compatible laptop models
                $sql .= " AND (compatible_models LIKE {$paramName})";
            } elseif ($mode === 'battery') {
                // Focus search exclusively on battery part numbers, aliases, model name, and specs
                $sql .= " AND (part_number LIKE {$paramName} OR aliases LIKE {$paramName} OR model_name LIKE {$paramName} OR capacity_wh LIKE {$paramName} OR voltage LIKE {$paramName})";
            } else {
                // Smart Cross-Match: Search across all technical dimensions
                $sql .= " AND (
                    part_number LIKE {$paramName} OR
                    aliases LIKE {$paramName} OR
                    model_name LIKE {$paramName} OR
                    brand LIKE {$paramName} OR
                    compatible_models LIKE {$paramName} OR
                    warehouse_location LIKE {$paramName} OR
                    voltage LIKE {$paramName} OR
                    capacity_wh LIKE {$paramName} OR
                    notes LIKE {$paramName}
                )";
            }

            $params[$paramName] = $likeVal;
            $tokenIdx++;
        }
    }

    $sql .= " ORDER BY brand ASC, part_number ASC LIMIT :limit";

    $stmt = $pdo_labels->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $batteries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Compute Summary Stats
    $totalCount = count($batteries);
    $totalStock = array_sum(array_column($batteries, 'qty_in_stock'));

    send_json_response(true, [
        'batteries' => $batteries,
        'count' => $totalCount,
        'total_stock' => $totalStock,
        'query' => $q,
        'mode' => $mode
    ]);

} catch (Exception $e) {
    send_json_response(false, null, $e->getMessage());
}
