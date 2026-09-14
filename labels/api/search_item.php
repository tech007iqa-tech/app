<?php
// api/search_item.php
// GET ?id=N : Looks up a single item by ID from labels.sqlite.
header('Content-Type: application/json');
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/hardware_mapping.php';

try {
    $F = HW_FIELDS;
    $raw_query = isset($_GET['id']) ? trim($_GET['id']) : '';
    if ($raw_query === '') {
        throw new Exception('Search query is required.');
    }

    $item = null;
    $results = [];

    // 1. Try EXACT numeric ID lookup first (Deep lookup with order info)
    if (is_numeric($raw_query)) {
        $stmt = $pdo_labels->prepare("SELECT * FROM items WHERE id = :id");
        $stmt->execute([':id' => (int)$raw_query]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // 2. If NO direct ID match found, or if query contains spaces/text, do a FLEXIBLE keyword search
    if (!$item) {
        $keywords = explode(' ', $raw_query);
        $conditions = [];
        $params = [];
        $i = 1;
        foreach ($keywords as $word) {
            $word = trim($word);
            if ($word === '') continue;
            $paramKey = ":q" . $i;
            $conditions[] = "({$F['BRAND']} LIKE $paramKey OR {$F['MODEL']} LIKE $paramKey OR {$F['SERIES']} LIKE $paramKey
                              OR {$F['CPU_GEN']} LIKE $paramKey OR {$F['CPU_SPECS']} LIKE $paramKey
                              OR {$F['CPU_CORES']} LIKE $paramKey OR {$F['CPU_SPEED']} LIKE $paramKey
                              OR {$F['DESCRIPTION']} LIKE $paramKey OR {$F['LOCATION']} LIKE $paramKey
                              OR CAST(id AS TEXT) LIKE $paramKey)";
            $params[$paramKey] = '%' . $word . '%';
            $i++;
        }

        if (!empty($conditions)) {
            $where = "WHERE " . implode(' AND ', $conditions);
            $stmt = $pdo_labels->prepare("SELECT * FROM items $where ORDER BY created_at DESC LIMIT 5");
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } else {
        // We have a single ID match, put it in the results format for consistent processing
        $results = [$item];
    }

    if (empty($results)) {
        throw new Exception('No matching hardware found.');
    }

    send_json_response(true, [
        'results'     => $results,
        'is_single'   => (count($results) === 1)
    ]);

} catch (Exception $e) {
    send_json_response(false, null, $e->getMessage());
}
?>

