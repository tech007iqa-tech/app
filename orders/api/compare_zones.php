<?php
/**
 * Warehouse Multi-Zone Comparison API Endpoint
 * Aggregates live inventory, metrics, valuations, conditions, and shelf stock across multiple working zones.
 */
require_once __DIR__ . '/../core/warehouse_db.php';
require_once __DIR__ . '/../core/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) && !isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit;
}

try {
    // 1. Parse parameters (supports GET, POST form, or raw JSON)
    $raw_input = file_get_contents('php://input');
    $json_input = json_decode($raw_input, true);

    $zones_param = $_GET['zones'] ?? $_POST['zones'] ?? ($json_input['zones'] ?? null);
    $sector = trim($_GET['sector'] ?? $_POST['sector'] ?? ($json_input['sector'] ?? ''));

    if (empty($zones_param)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'At least one zone is required for comparison.']);
        exit;
    }

    $zones = [];
    if (is_array($zones_param)) {
        $zones = array_values(array_filter(array_map('trim', $zones_param)));
    } elseif (is_string($zones_param)) {
        $zones = array_values(array_filter(array_map('trim', explode(',', $zones_param))));
    }

    if (empty($zones)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Valid zone names are required.']);
        exit;
    }

    // Limit to reasonable number of zones to compare at once (e.g. max 12)
    $zones = array_slice(array_unique($zones), 0, 12);

    $zones_data = [];
    $total_units_all = 0;
    $total_valuation_all = 0.0;
    $total_shelves_all = 0;
    $all_models_map = []; // model => [zone1, zone2, ...]

    // 2. Fetch data per zone
    foreach ($zones as $zone_name) {
        // A. Fetch shelves / locations for this zone
        $stmt_locs = $conn_wh->prepare("
            SELECT l.location_code, l.status, l.updated_at,
                (SELECT COUNT(*) FROM inventory i WHERE i.location_code = l.location_code) as item_types_count,
                COALESCE((SELECT SUM(quantity) FROM inventory i WHERE i.location_code = l.location_code), 0) as shelf_total_units
            FROM locations l
            WHERE l.working_zone_name = ? AND COALESCE(l.is_archived, 0) = 0
            ORDER BY l.location_code ASC
        ");
        $stmt_locs->execute([$zone_name]);
        $locations = $stmt_locs->fetchAll(PDO::FETCH_ASSOC);

        // B. Fetch inventory items in this zone
        $query_inv = "
            SELECT i.*, l.working_zone_name 
            FROM inventory i
            JOIN locations l ON i.location_code = l.location_code
            WHERE l.working_zone_name = ? AND COALESCE(l.is_archived, 0) = 0
        ";
        $params_inv = [$zone_name];

        if (!empty($sector) && $sector !== 'Master') {
            $query_inv .= " AND i.sector = ?";
            $params_inv[] = $sector;
        }

        $query_inv .= " ORDER BY i.location_code ASC, i.brand ASC, i.model ASC";
        $stmt_inv = $conn_wh->prepare($query_inv);
        $stmt_inv->execute($params_inv);
        $raw_items = $stmt_inv->fetchAll(PDO::FETCH_ASSOC);

        // C. Calculate aggregates
        $zone_units = 0;
        $zone_valuation = 0.0;
        $sector_breakdown = [];
        $condition_breakdown = [
            'B Grade' => 0,
            'A Grade' => 0,
            'C Grade' => 0,
            'No Power' => 0,
            'No Post' => 0,
            'Other' => 0
        ];
        $brands_tally = [];
        $models_tally = [];
        $items_list = [];

        foreach ($raw_items as $item) {
            $qty = (int)($item['quantity'] ?? 1);
            $price = (float)($item['price'] ?? 0.0);
            $val = $qty * $price;
            $zone_units += $qty;
            $zone_valuation += $val;

            $sec = $item['sector'] ?: 'General';
            $sector_breakdown[$sec] = ($sector_breakdown[$sec] ?? 0) + $qty;

            $brand = trim($item['brand'] ?? 'Unknown');
            if ($brand !== '') {
                $brands_tally[$brand] = ($brands_tally[$brand] ?? 0) + $qty;
            }

            $model = trim($item['model'] ?? '');
            if ($model !== '') {
                $models_tally[$model] = ($models_tally[$model] ?? 0) + $qty;
                $key = strtolower($brand . ' ' . $model);
                if (!isset($all_models_map[$key])) {
                    $all_models_map[$key] = [
                        'brand' => $brand,
                        'model' => $model,
                        'zones' => []
                    ];
                }
                if (!in_array($zone_name, $all_models_map[$key]['zones'])) {
                    $all_models_map[$key]['zones'][] = $zone_name;
                }
            }

            $specs = json_decode($item['specs_json'] ?? '{}', true) ?: [];
            $cond = trim($specs['condition'] ?? 'B Grade');
            if (isset($condition_breakdown[$cond])) {
                $condition_breakdown[$cond] += $qty;
            } else {
                $condition_breakdown['Other'] += $qty;
            }

            $item['specs'] = $specs;
            $item['line_valuation'] = round($val, 2);
            $items_list[] = $item;
        }

        // Sort top brands
        arsort($brands_tally);
        $top_brands = array_slice($brands_tally, 0, 5, true);

        // Sort top models
        arsort($models_tally);
        $top_models = array_slice($models_tally, 0, 5, true);

        // Location health count
        $working_shelves = 0;
        $audit_shelves = 0;
        $idle_shelves = 0;
        foreach ($locations as $loc) {
            $st = strtolower($loc['status'] ?? 'working');
            if ($st === 'audit') $audit_shelves++;
            elseif ($st === 'idle') $idle_shelves++;
            else $working_shelves++;
        }

        $total_shelves = count($locations);
        $total_shelves_all += $total_shelves;
        $total_units_all += $zone_units;
        $total_valuation_all += $zone_valuation;

        $zones_data[$zone_name] = [
            'name' => $zone_name,
            'total_shelves' => $total_shelves,
            'shelves_breakdown' => [
                'working' => $working_shelves,
                'audit' => $audit_shelves,
                'idle' => $idle_shelves
            ],
            'total_units' => $zone_units,
            'total_valuation' => round($zone_valuation, 2),
            'avg_unit_price' => $zone_units > 0 ? round($zone_valuation / $zone_units, 2) : 0.0,
            'sector_breakdown' => $sector_breakdown,
            'condition_breakdown' => $condition_breakdown,
            'top_brands' => $top_brands,
            'top_models' => $top_models,
            'locations' => $locations,
            'items' => $items_list
        ];
    }

    // Overlapping models (present in 2 or more of the compared zones)
    $overlapping_models = [];
    foreach ($all_models_map as $info) {
        if (count($info['zones']) >= 2) {
            $overlapping_models[] = $info;
        }
    }

    echo json_encode([
        'success' => true,
        'sector_filter' => $sector,
        'zones_data' => $zones_data,
        'summary' => [
            'zones_count' => count($zones),
            'total_shelves' => $total_shelves_all,
            'total_units' => $total_units_all,
            'total_valuation' => round($total_valuation_all, 2),
            'overlapping_models_count' => count($overlapping_models),
            'overlapping_models' => array_slice($overlapping_models, 0, 15)
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to generate zone comparison: ' . $e->getMessage()
    ]);
}
