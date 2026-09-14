<?php
/**
 * Trends Data Fetching & Analytics Model Partial
 * Queries orders database, performs CPU categorizations, calculates summary statistics, and provides fallbacks.
 */

$filter = $_GET['filter'] ?? 'all';
$date_condition = "";
if ($filter === '30d') {
    $date_condition = "AND orders.created_at >= date('now', '-30 days')";
} elseif ($filter === 'ytd') {
    $date_condition = "AND orders.created_at >= date('now', 'start of year')";
}

// CPU Architecture Classification Helper
if (!function_exists('categorizeCpu')) {
    function categorizeCpu($cpuStr) {
        $cpu = strtolower(trim($cpuStr));
        if (empty($cpu) || $cpu === '—' || $cpu === '-' || $cpu === 'em dash') {
            return 'Apple';
        }

        // Apple Silicon / Apple general
        if (strpos($cpu, 'apple') !== false || strpos($cpu, 'm1') !== false || strpos($cpu, 'm2') !== false || strpos($cpu, 'm3') !== false || strpos($cpu, 'm4') !== false || strpos($cpu, 'silicon') !== false) {
            return 'Apple';
        }

        // AMD Ryzen / AMD general
        if (strpos($cpu, 'ryzen') !== false || strpos($cpu, 'amd') !== false) {
            return 'Ryzen';
        }

        // Core 2 Duo
        if (strpos($cpu, 'core 2') !== false || strpos($cpu, 'core2') !== false || strpos($cpu, 'duo') !== false) {
            return 'Core 2 Duo';
        }

        // Generations check
        $is2nd3rd = (strpos($cpu, '2nd') !== false || strpos($cpu, '3rd') !== false);
        $is4th5th = (strpos($cpu, '4th') !== false || strpos($cpu, '5th') !== false);
        $is6th7th = (strpos($cpu, '6th') !== false || strpos($cpu, '7th') !== false);

        if ($is2nd3rd) return '2nd & 3rd Gen';
        if ($is4th5th) return '4th & 5th Gen';
        if ($is6th7th) return '6th & 7th Gen';

        // Check 8th to 14th Gen explicitly
        $gens = ['8th', '9th', '10th', '11th', '12th', '13th', '14th'];
        foreach ($gens as $gen) {
            if (strpos($cpu, strtolower($gen)) !== false) {
                if (strpos($cpu, 'i3') !== false) return "$gen Gen i3";
                if (strpos($cpu, 'i5') !== false) return "$gen Gen i5";
                if (strpos($cpu, 'i7') !== false || strpos($cpu, 'i9') !== false) return "$gen Gen i7";
                return "$gen Gen i5";
            }
        }

        // Regex check for model numbers (e.g. i5-10300H or i7-8550U)
        if (preg_match('/i(3|5|7|9)-(\d{1,2})\d{3}/', $cpu, $matches)) {
            $tier = 'i' . ($matches[1] == '9' ? '7' : $matches[1]);
            $num = intval($matches[2]);
            if ($num >= 8 && $num <= 14) {
                return $num . 'th Gen ' . $tier;
            }
        }

        // Fallback modern Intel Core i series
        if (strpos($cpu, 'i3') !== false) return '8th Gen i3';
        if (strpos($cpu, 'i5') !== false) return '8th Gen i5';
        if (strpos($cpu, 'i7') !== false || strpos($cpu, 'i9') !== false) return '8th Gen i7';

        return 'Other';
    }
}

try {
    $db = Database::orders();

    // 1. Fetch Sales Velocity (Top Brands/Models) + Inventory Check + Customer Names + Order IDs
    $velocity = Database::queryIntegrated('orders', ['w' => 'warehouse', 'c' => 'customers'], "
        SELECT items.brand, items.model, items.series, items.cpu, items.description, items.notes, SUM(items.quantity) as total_qty, ROUND(AVG(items.unit_price), 2) as avg_price,
               (SELECT SUM(quantity) FROM w.inventory WHERE brand = items.brand AND model = items.model AND status = '') as in_stock,
               (SELECT GROUP_CONCAT(DISTINCT location_code) FROM w.inventory WHERE brand = items.brand AND model = items.model AND status = '') as stock_locations,
               (SELECT SUM(quantity) FROM w.inventory WHERE brand = items.brand AND model = items.model AND status != '') as incoming_stock,
               GROUP_CONCAT(DISTINCT c.customers.company_name) as buyer_names,
               GROUP_CONCAT(DISTINCT items.order_id || '|' || SUBSTR(orders.created_at, 1, 10) || '|' || orders.customer_id) as order_ids
        FROM items
        JOIN orders ON items.order_id = orders.order_id
        LEFT JOIN c.customers ON items.customer_id = c.customers.customer_id
        WHERE orders.status = 'paid' $date_condition
        GROUP BY items.brand, items.model, items.series, items.cpu, items.description, items.notes
        ORDER BY total_qty DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch Pricing Trends over Time
    $price_history = $db->query("
        SELECT strftime('%Y-%m', orders.created_at) as sales_month,
               ROUND(AVG(items.unit_price), 2) as avg_price,
               SUM(items.quantity) as total_qty,
               ROUND(SUM(items.unit_price * items.quantity), 2) as total_valuation
        FROM items
        JOIN orders ON items.order_id = orders.order_id
        WHERE orders.status = 'paid' $date_condition
        GROUP BY sales_month
        ORDER BY sales_month DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // 3. Fetch CPU Architectures Distribution
    $raw_cpu_distribution = $db->query("
        SELECT items.cpu,
               items.quantity,
               items.unit_price
        FROM items
        JOIN orders ON items.order_id = orders.order_id
        WHERE orders.status = 'paid' AND items.cpu IS NOT NULL AND items.unit_price >= 15.00 $date_condition
    ")->fetchAll(PDO::FETCH_ASSOC);

    $categories = [
        'Core 2 Duo'     => ['total_qty' => 0, 'prices' => []],
        '2nd & 3rd Gen'  => ['total_qty' => 0, 'prices' => []],
        '4th & 5th Gen'  => ['total_qty' => 0, 'prices' => []],
        '6th & 7th Gen'  => ['total_qty' => 0, 'prices' => []],
    ];

    $gens = ['8th', '9th', '10th', '11th', '12th', '13th', '14th'];
    $tiers = ['i3', 'i5', 'i7'];
    foreach ($gens as $gen) {
        foreach ($tiers as $tier) {
            $categories["$gen Gen $tier"] = ['total_qty' => 0, 'prices' => []];
        }
    }

    $categories['Apple'] = ['total_qty' => 0, 'prices' => []];
    $categories['Ryzen'] = ['total_qty' => 0, 'prices' => []];

    foreach ($raw_cpu_distribution as $row) {
        $cat = categorizeCpu($row['cpu']);
        if (!isset($categories[$cat])) continue;

        $qty = intval($row['quantity']);
        $price = floatval($row['unit_price']);

        $categories[$cat]['total_qty'] += $qty;
        for ($i = 0; $i < $qty; $i++) {
            $categories[$cat]['prices'][] = $price;
        }
    }

    $cpu_distribution = [];
    foreach ($categories as $name => $data) {
        if ($data['total_qty'] > 0) {
            $prices = $data['prices'];
            $avg = count($prices) > 0 ? array_sum($prices) / count($prices) : 0;
            $min = count($prices) > 0 ? min($prices) : 0;
            $max = count($prices) > 0 ? max($prices) : 0;

            $cpu_distribution[] = [
                'cpu' => $name,
                'total_qty' => $data['total_qty'],
                'avg_price' => round($avg, 2),
                'min_price' => round($min, 2),
                'max_price' => round($max, 2)
            ];
        }
    }

    // 4. Summary metrics
    $totals = $db->query("
        SELECT SUM(items.quantity) as total_qty, COUNT(DISTINCT items.order_id) as total_orders, ROUND(AVG(items.unit_price * items.quantity), 2) as avg_order_val
        FROM items
        JOIN orders ON items.order_id = orders.order_id
        WHERE orders.status = 'paid' $date_condition
    ")->fetch(PDO::FETCH_ASSOC);

    // 5. Customer Insights
    $customer_insights = Database::queryIntegrated('orders', ['c' => 'customers'], "
        SELECT c.customers.company_name,
               COUNT(DISTINCT items.order_id) as total_orders,
               SUM(items.quantity) as total_units_bought,
               MIN(orders.created_at) as first_order_date,
               MAX(orders.created_at) as last_order_date
        FROM items
        JOIN orders ON items.order_id = orders.order_id
        JOIN c.customers ON items.customer_id = c.customers.customer_id
        WHERE orders.status = 'paid' $date_condition
        GROUP BY items.customer_id
        ORDER BY total_units_bought DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // 6. Additional computed metrics
    $top_buyer_name = "None";
    $top_buyer_qty = 0;
    if (!empty($customer_insights)) {
        $top_buyer_name = $customer_insights[0]['company_name'];
        $top_buyer_qty = $customer_insights[0]['total_units_bought'];
    }

    $popular_brand = "None";
    $popular_brand_qty = 0;
    $brand_stats = $db->query("
        SELECT items.brand, SUM(items.quantity) as qty
        FROM items
        JOIN orders ON items.order_id = orders.order_id
        WHERE orders.status = 'paid' $date_condition
        GROUP BY items.brand
        ORDER BY qty DESC
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);
    if ($brand_stats) {
        $popular_brand = $brand_stats['brand'];
        $popular_brand_qty = $brand_stats['qty'];
    }

    $peak_month = "N/A";
    $peak_month_val = 0;
    foreach ($price_history as $hist) {
        $val = $hist['total_valuation'] ?? ($hist['avg_price'] * $hist['total_qty']);
        if ($val > $peak_month_val) {
            $peak_month_val = $val;
            $peak_month = $hist['sales_month'];
        }
    }

    $total_ryzen_sold = 0;
    foreach ($cpu_distribution as $cpu) {
        if ($cpu['cpu'] === 'Ryzen') {
            $total_ryzen_sold = $cpu['total_qty'];
        }
    }

    // 7. Fetch Pricing Matrix Rules
    $conn_wh = Database::warehouse();
    $pricing_rules_raw = $conn_wh->query("SELECT * FROM pricing_rules ORDER BY category ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);

    $pricing_matrix = [];
    $matrix_category_items = [];
    foreach ($pricing_rules_raw as $rule) {
        $cat = $rule['category'];
        $gen = $rule['cpu_gen'];
        $pricing_matrix[$cat][$gen][$rule['grade']] = $rule['price'];
        if (!isset($matrix_category_items[$cat])) {
            $matrix_category_items[$cat] = [];
        }
        if (!in_array($gen, $matrix_category_items[$cat])) {
            $matrix_category_items[$cat][] = $gen;
        }
    }

} catch (Exception $e) {
    $velocity = [];
    $price_history = [];
    $cpu_distribution = [];
    $customer_insights = [];
    $totals = ['total_qty' => 0, 'total_orders' => 0, 'avg_order_val' => 0.00];
    $top_buyer_name = "None";
    $top_buyer_qty = 0;
    $popular_brand = "None";
    $popular_brand_qty = 0;
    $peak_month = "N/A";
    $total_ryzen_sold = 0;
    $pricing_matrix = [];
    $matrix_category_items = [];
}

// 8. Fallback seed mock data if database is empty
$is_using_mock_data = false;
if (empty($velocity)) {
    $is_using_mock_data = true;
    $velocity = [
        ['brand' => 'Apple', 'model' => 'MacBook Air A1932', 'total_qty' => 148, 'avg_price' => 245.00, 'in_stock' => 12, 'stock_locations' => 'A1, B2', 'incoming_stock' => 2, 'buyer_names' => 'Acme Corp, Global Tech', 'order_ids' => 'ORD-993A7|2026-05-10|CUST-ACME, ORD-882B2|2026-04-25|CUST-GLOBAL'],
        ['brand' => 'Lenovo', 'model' => 'ThinkPad T480', 'total_qty' => 112, 'avg_price' => 165.00, 'in_stock' => 8, 'stock_locations' => 'C3', 'incoming_stock' => 0, 'buyer_names' => 'Acme Corp', 'order_ids' => 'ORD-771C3|2026-05-10|CUST-ACME'],
        ['brand' => 'Dell', 'model' => 'Latitude 7490', 'total_qty' => 95, 'avg_price' => 135.00, 'in_stock' => 0, 'stock_locations' => '', 'incoming_stock' => 4, 'buyer_names' => 'Global Tech, Stark Industries', 'order_ids' => 'ORD-993A7|2026-05-10|CUST-ACME, ORD-882B2|2026-04-25|CUST-GLOBAL'],
        ['brand' => 'HP', 'model' => 'EliteBook 840 G5', 'total_qty' => 74, 'avg_price' => 155.00, 'in_stock' => 5, 'stock_locations' => 'D1', 'incoming_stock' => 1, 'buyer_names' => 'Stark Industries', 'order_ids' => 'ORD-882B2|2026-04-25|CUST-GLOBAL'],
        ['brand' => 'Apple', 'model' => 'MacBook Pro A1708', 'total_qty' => 58, 'avg_price' => 220.00, 'in_stock' => 2, 'stock_locations' => 'A3', 'incoming_stock' => 0, 'buyer_names' => 'Acme Corp', 'order_ids' => 'ORD-993A7|2026-05-10|CUST-ACME']
    ];
}

if (empty($price_history)) {
    $price_history = [
        ['sales_month' => '2026-05', 'avg_price' => 210.00, 'total_qty' => 380, 'total_valuation' => 79800.00],
        ['sales_month' => '2026-04', 'avg_price' => 195.00, 'total_qty' => 420, 'total_valuation' => 81900.00],
        ['sales_month' => '2026-03', 'avg_price' => 225.00, 'total_qty' => 310, 'total_valuation' => 69750.00],
        ['sales_month' => '2026-02', 'avg_price' => 180.00, 'total_qty' => 290, 'total_valuation' => 52200.00],
        ['sales_month' => '2026-01', 'avg_price' => 205.00, 'total_qty' => 340, 'total_valuation' => 69700.00],
        ['sales_month' => '2025-12', 'avg_price' => 190.00, 'total_qty' => 450, 'total_valuation' => 85500.00]
    ];
}

if (empty($cpu_distribution)) {
    $cpu_distribution = [
        ['cpu' => '8th Gen+ i5', 'total_qty' => 259, 'avg_price' => 210.00, 'min_price' => 180.00, 'max_price' => 310.00],
        ['cpu' => '8th Gen+ i7', 'total_qty' => 180, 'avg_price' => 260.00, 'min_price' => 200.00, 'max_price' => 390.00],
        ['cpu' => '2nd & 3rd Gen', 'total_qty' => 185, 'avg_price' => 150.00, 'min_price' => 120.00, 'max_price' => 220.00],
        ['cpu' => '4th & 5th Gen', 'total_qty' => 124, 'avg_price' => 185.00, 'min_price' => 140.00, 'max_price' => 280.00],
        ['cpu' => 'Apple', 'total_qty' => 92, 'avg_price' => 295.00, 'min_price' => 250.00, 'max_price' => 450.00],
        ['cpu' => 'Ryzen', 'total_qty' => 64, 'avg_price' => 240.00, 'min_price' => 190.00, 'max_price' => 320.00]
    ];
}

if (empty($customer_insights)) {
    $customer_insights = [
        ['company_name' => 'Acme Corp', 'total_orders' => 12, 'total_units_bought' => 150, 'first_order_date' => '2025-01-15', 'last_order_date' => '2026-05-10'],
        ['company_name' => 'Global Tech', 'total_orders' => 8, 'total_units_bought' => 120, 'first_order_date' => '2025-03-20', 'last_order_date' => '2026-04-25'],
        ['company_name' => 'Stark Industries', 'total_orders' => 5, 'total_units_bought' => 85, 'first_order_date' => '2025-06-11', 'last_order_date' => '2026-05-01']
    ];
}

if (!$totals || $totals['total_qty'] == 0) {
    $totals = ['total_qty' => 487, 'total_orders' => 32, 'avg_order_val' => 2845.50];
}

if ($is_using_mock_data) {
    $top_buyer_name = "Acme Corp";
    $top_buyer_qty = 150;
    $popular_brand = "Apple";
    $popular_brand_qty = 206;
    $peak_month = "2026-04";
    $total_ryzen_sold = 64;
}
