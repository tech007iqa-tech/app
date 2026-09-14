<?php
/**
 * Smart Sold Price Inference API (infer_price.php)
 * Queries past sold laptops and items across orders.db, warehouse.db (sold_items), and pricing rules
 * to infer fair market and suggested intake pricing.
 */

require_once __DIR__ . '/../core/database.php';
include __DIR__ . '/../core/auth.php';

header('Content-Type: application/json');

$brand = trim($_GET['brand'] ?? $_POST['brand'] ?? '');
$model = trim($_GET['model'] ?? $_POST['model'] ?? '');
$cpu = trim($_GET['cpu'] ?? $_POST['cpu'] ?? '');
$sector = trim($_GET['sector'] ?? $_POST['sector'] ?? 'Laptops');

if (empty($brand) && empty($model) && empty($cpu)) {
    echo json_encode([
        'success' => false,
        'message' => 'Provide at least brand, model, or CPU for pricing inference.'
    ]);
    exit();
}

try {
    $conn_orders = Database::orders();
    $conn_wh = Database::warehouse();

    $matched_prices = [];
    $match_type = 'none';

    // 1. Check exact brand & model in orders.db (items)
    if (!empty($brand) && !empty($model)) {
        $stmt = $conn_orders->prepare("
            SELECT unit_price, quantity, created_at 
            FROM items 
            WHERE LOWER(brand) = LOWER(?) AND LOWER(model) = LOWER(?) AND unit_price > 5.00
            ORDER BY id DESC LIMIT 50
        ");
        $stmt->execute([$brand, $model]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($rows)) {
            $match_type = 'exact_model';
            foreach ($rows as $r) {
                $matched_prices[] = (float)$r['unit_price'];
            }
        }
    }

    // 2. Also check warehouse.db (sold_items)
    if (!empty($brand) && !empty($model)) {
        $stmt_wh = $conn_wh->prepare("
            SELECT sold_price, quantity, sold_at 
            FROM sold_items 
            WHERE LOWER(brand) = LOWER(?) AND LOWER(model) = LOWER(?) AND sold_price > 5.00
            ORDER BY id DESC LIMIT 50
        ");
        $stmt_wh->execute([$brand, $model]);
        $rows_wh = $stmt_wh->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($rows_wh)) {
            if ($match_type === 'none') $match_type = 'exact_model';
            foreach ($rows_wh as $r) {
                $matched_prices[] = (float)$r['sold_price'];
            }
        }
    }

    // 3. Fallback: Model token search (e.g. "T480", "7490", "840")
    if (empty($matched_prices) && !empty($model)) {
        // Extract model number / token
        $tokens = preg_split('/\s+/', $model);
        $last_token = end($tokens);
        if (strlen($last_token) >= 3) {
            $stmt_tok = $conn_orders->prepare("
                SELECT unit_price, quantity FROM items 
                WHERE (LOWER(model) LIKE LOWER(?) OR LOWER(description) LIKE LOWER(?)) AND unit_price > 5.00
                ORDER BY id DESC LIMIT 30
            ");
            $like = '%' . $last_token . '%';
            $stmt_tok->execute([$like, $like]);
            $rows_tok = $stmt_tok->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($rows_tok)) {
                $match_type = 'model_family';
                foreach ($rows_tok as $r) {
                    $matched_prices[] = (float)$r['unit_price'];
                }
            }
        }
    }

    // 4. Fallback: CPU Tier & Gen matching in items & tested market rules
    if (empty($matched_prices) && !empty($cpu)) {
        $stmt_cpu = $conn_orders->prepare("
            SELECT unit_price FROM items 
            WHERE LOWER(cpu) LIKE LOWER(?) AND unit_price > 5.00
            ORDER BY id DESC LIMIT 40
        ");
        $stmt_cpu->execute(['%' . $cpu . '%']);
        $rows_cpu = $stmt_cpu->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($rows_cpu)) {
            $match_type = 'cpu_tier';
            foreach ($rows_cpu as $r) {
                $matched_prices[] = (float)$r['unit_price'];
            }
        } else {
            // Check tested market rules
            $stmt_tmr = $conn_wh->prepare("
                SELECT price FROM tested_market_rules 
                WHERE (LOWER(cpu) LIKE LOWER(?) OR LOWER(brand_series) LIKE LOWER(?)) AND price > 5.00
                LIMIT 10
            ");
            $stmt_tmr->execute(['%' . $cpu . '%', '%' . $brand . '%']);
            $rows_tmr = $stmt_tmr->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows_tmr)) {
                $match_type = 'market_rule';
                foreach ($rows_tmr as $r) {
                    $matched_prices[] = (float)$r['price'];
                }
            }
        }
    }

    // 5. Default baseline fallback if nothing found
    if (empty($matched_prices)) {
        // Industry baseline based on sector
        $base = ($sector === 'Gaming') ? 295.00 : (($sector === 'Desktops') ? 120.00 : 145.00);
        echo json_encode([
            'success' => true,
            'has_match' => false,
            'match_type' => 'baseline',
            'confidence' => 'estimated',
            'inferred_price' => $base,
            'avg_price' => $base,
            'min_price' => $base * 0.8,
            'max_price' => $base * 1.3,
            'sales_count' => 0,
            'summary' => 'No prior sales found. Standard sector baseline applied.'
        ]);
        exit();
    }

    // Calculate statistical metrics
    $count = count($matched_prices);
    $sum = array_sum($matched_prices);
    $avg = round($sum / $count, 2);
    $min = min($matched_prices);
    $max = max($matched_prices);
    $last_sold = end($matched_prices);

    // Weighted inferred price (give higher weight to latest transactions)
    $inferred = round(($avg * 0.6) + ($last_sold * 0.4), 2);

    $confidence = ($count >= 5 && $match_type === 'exact_model') ? 'high' : (($count >= 2) ? 'medium' : 'moderate');

    $summary = ($match_type === 'exact_model')
        ? "Matched {$count} past sale(s) for {$brand} {$model} (Avg: \${$avg}, Range: \${$min} - \${$max})"
        : (($match_type === 'model_family')
            ? "Matched {$count} related {$model} family sale(s) (Avg: \${$avg})"
            : "Inferred from {$count} sales with CPU {$cpu} (Avg: \${$avg})");

    echo json_encode([
        'success' => true,
        'has_match' => true,
        'match_type' => $match_type,
        'confidence' => $confidence,
        'inferred_price' => $inferred,
        'avg_price' => $avg,
        'min_price' => $min,
        'max_price' => $max,
        'last_sold_price' => $last_sold,
        'sales_count' => $count,
        'summary' => $summary
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
