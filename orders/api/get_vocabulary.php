<?php
// orders/api/get_vocabulary.php
header('Content-Type: application/json');
require_once __DIR__ . '/../core/database.php';

function clean_brands($brands)
{
    $cleaned = [];

    // Known mapping for standard casing of common brands
    $standard_casing = [
        'dell' => 'Dell',
        'hp' => 'HP',
        'lenovo' => 'Lenovo',
        'apple' => 'Apple',
        'asus' => 'Asus',
        'acer' => 'Acer',
        'microsoft' => 'Microsoft',
        'panasonic' => 'Panasonic',
        'sony' => 'Sony',
        'beelink' => 'Beelink',
        'bytespeed' => 'Bytespeed',
        'gtac' => 'Gtac',
        'minix' => 'Minix',
        'rog' => 'ROG',
        'sei' => 'SEI',
        'msi' => 'MSI',
        'toshiba' => 'Toshiba',
        'samsung' => 'Samsung'
    ];

    foreach ($brands as $brand) {
        $brand = trim($brand);
        if ($brand === '')
            continue;

        // Skip multi-brand groupings or note entries
        if (preg_match('/[,&+|]|\band\b/i', $brand))
            continue;
        if (preg_match('/\b(hp|dell|lenovo|asus|acer)\s+\b(hp|dell|lenovo|asus|acer)/i', $brand))
            continue;

        // Normalize space/punctuation in brand name (e.g. H P -> HP)
        $normalized_key = strtolower(str_replace(' ', '', $brand));
        if ($normalized_key === 'hp' || $normalized_key === 'h.p.') {
            $brand_name = 'HP';
        } elseif (isset($standard_casing[$normalized_key])) {
            $brand_name = $standard_casing[$normalized_key];
        } else {
            // Title case formatting
            $brand_name = ucwords(strtolower($brand));
        }

        // Exclude specific placeholder/generic/unwanted strings
        if (in_array(strtolower($brand_name), ['other', 'network', 'mix', 'rugged no palmrest', 'rugged nopalmrest', 'unknown'])) {
            continue;
        }

        $cleaned[strtolower($brand_name)] = $brand_name;
    }

    return array_values($cleaned);
}

try {
    $vocabulary = [
        'brands' => [],
        'models' => [],
        'cpus' => []
    ];

    // 1. Fetch from Warehouse
    $conn_wh = Database::warehouse();

    $wh_brands = $conn_wh->query("SELECT DISTINCT brand FROM inventory WHERE brand != ''")->fetchAll(PDO::FETCH_COLUMN);
    $wh_models = $conn_wh->query("SELECT DISTINCT model FROM inventory WHERE model != ''")->fetchAll(PDO::FETCH_COLUMN);

    // For CPUs, we need to parse specs_json
    $wh_specs = $conn_wh->query("SELECT specs_json FROM inventory WHERE specs_json != ''")->fetchAll(PDO::FETCH_COLUMN);
    $wh_cpus = [];
    foreach ($wh_specs as $json) {
        $data = json_decode($json, true);
        if (!empty($data['cpu']))
            $wh_cpus[] = $data['cpu'];
        if (!empty($data['cpu_gen']))
            $wh_cpus[] = $data['cpu_gen'];
    }

    // 2. Fetch from Orders/Items (Historical)
    $conn_o = Database::orders();

    $o_brands = $conn_o->query("SELECT DISTINCT brand FROM items WHERE brand != ''")->fetchAll(PDO::FETCH_COLUMN);
    $o_models = $conn_o->query("SELECT DISTINCT model FROM items WHERE model != ''")->fetchAll(PDO::FETCH_COLUMN);
    $o_cpus = $conn_o->query("SELECT DISTINCT cpu FROM items WHERE cpu != ''")->fetchAll(PDO::FETCH_COLUMN);

    // 3. Merge and Unique
    $merged_brands = array_values(array_unique(array_merge($wh_brands, $o_brands)));
    $vocabulary['brands'] = clean_brands($merged_brands);
    $vocabulary['models'] = array_values(array_unique(array_merge($wh_models, $o_models)));
    $vocabulary['cpus'] = array_values(array_unique(array_merge($wh_cpus, $o_cpus)));

    echo json_encode($vocabulary);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
