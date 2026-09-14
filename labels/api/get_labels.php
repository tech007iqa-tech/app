<?php
// api/get_labels.php
// GET ?q=&status= : Filtered inventory search used by the labels.php filter bar.
header('Content-Type: application/json');
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/hardware_mapping.php';

try {
    $F = HW_FIELDS;
    $q       = isset($_GET['q'])       ? trim($_GET['q'])       : '';
    $status  = isset($_GET['status'])  ? trim($_GET['status'])  : 'all';
    $archive = isset($_GET['archive']) ? (int)$_GET['archive']  : 0;

    $params      = [];
    $conditions  = [];

    // Archive Days filter (e.g. only Sold items within last 90 days)
    if ($archive > 0) {
        $conditions[] = "updated_at >= datetime('now', '-{$archive} days')";
    }

    // Status filter
    if ($status !== 'all' && $status !== '') {
        if ($status === 'In Warehouse') {
            $conditions[] = "{$F['STATUS']} != 'Sold'";
        } else {
            $conditions[] = "{$F['STATUS']} = :status";
            $params[':status'] = $status;
        }
    }

    // Text search filter (Multi-keyword "Flexible Search")
    if ($q !== '') {
        $keywords = explode(' ', $q);
        $i = 1;
        foreach ($keywords as $word) {
            $word = trim($word);
            if ($word === '') continue;

            $paramKey = ":q" . $i;
            $search_term = '%' . $word . '%';

            $conditions[] = "({$F['BRAND']} LIKE $paramKey OR {$F['MODEL']} LIKE $paramKey OR {$F['SERIES']} LIKE $paramKey
                              OR {$F['CPU_GEN']} LIKE $paramKey OR {$F['CPU_SPECS']} LIKE $paramKey
                              OR {$F['CPU_CORES']} LIKE $paramKey OR {$F['CPU_SPEED']} LIKE $paramKey
                              OR {$F['DESCRIPTION']} LIKE $paramKey OR {$F['LOCATION']} LIKE $paramKey
                              OR buyer_name LIKE $paramKey OR buyer_order_num LIKE $paramKey
                              OR CAST(id AS TEXT) LIKE $paramKey)";

            $params[$paramKey] = $search_term;
            $i++;
        }
    }

    $where_clause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

    $stmt = $pdo_labels->prepare("
        SELECT * FROM items
        {$where_clause}
        ORDER BY created_at DESC
        LIMIT 200
    ");
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    send_json_response(true, $items);

} catch (Exception $e) {
    send_json_response(false, null, $e->getMessage());
}
?>

