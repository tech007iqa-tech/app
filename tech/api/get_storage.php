<?php
/**
 * tech/api/get_storage.php
 * Filtered JSON endpoint for Storage & Donor Units
 */
if (!headers_sent()) {
    header('Content-Type: application/json');
}
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/auth.php';

try {
    $conn = Database::tech();

    $status      = trim($_GET['status'] ?? 'In Storage');
    $brand       = trim($_GET['brand'] ?? '');
    $need        = trim($_GET['need'] ?? '');
    $search      = trim($_GET['search'] ?? '');
    $donor_model = trim($_GET['donor_model'] ?? '');
    $donor_part  = strtolower(trim($_GET['donor_part'] ?? '')); // 'screen', 'battery', 'any'

    $where = [];
    $params = [];

    $prefix      = trim($_GET['prefix'] ?? '');

    // Status filter
    if ($status !== 'all' && $status !== '') {
        $where[] = "status = ?";
        $params[] = $status;
    }

    // Shelf / Rack Prefix filter (e.g. DL, DP, HE, LT, AP)
    if ($prefix !== '') {
        $where[] = "sku LIKE ?";
        $params[] = "$prefix-%";
    }

    // Brand filter
    if ($brand !== '') {
        $where[] = "brand = ?";
        $params[] = $brand;
    }

    // Need filter
    if ($need !== '') {
        $where[] = "need LIKE ?";
        $params[] = "%$need%";
    }

    // Freeform search
    if ($search !== '') {
        $where[] = "(sku LIKE ? OR brand LIKE ? OR model_num LIKE ? OR series LIKE ? OR issue LIKE ? OR need LIKE ? OR notes LIKE ?)";
        $sTerm = "%$search%";
        for ($i = 0; $i < 7; $i++) {
            $params[] = $sTerm;
        }
    }

    // Donor matching engine
    if ($donor_model !== '') {
        $tokens = array_filter(preg_split('/[\s\-_,\.\/]+/', $donor_model));
        if (!empty($tokens)) {
            $tokenClauses = [];
            foreach ($tokens as $token) {
                // Ignore very short tokens like 'G' or 'x' unless numeric
                if (strlen($token) < 2 && !is_numeric($token)) continue;
                $tokenClauses[] = "(model_num LIKE ? OR series LIKE ? OR notes LIKE ?)";
                $params[] = "%$token%";
                $params[] = "%$token%";
                $params[] = "%$token%";
            }
            if (!empty($tokenClauses)) {
                // Must match at least the key model token
                $where[] = "(" . implode(" OR ", $tokenClauses) . ")";
            }
        }

        // Donor part filtering
        if ($donor_part === 'screen') {
            $where[] = "(screen LIKE '%Good%' OR screen = '✔' OR screen LIKE '%Working%')";
        } elseif ($donor_part === 'battery') {
            $where[] = "(battery LIKE '%Good%' OR battery = '✔' OR battery LIKE '%Working%')";
        } elseif ($donor_part === 'any') {
            $where[] = "((screen LIKE '%Good%' OR screen = '✔') OR (battery LIKE '%Good%' OR battery = '✔'))";
        }
    }

    $whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    $sql = "SELECT id, sku, date_added, brand, model_num, series, battery, screen, issue, need, notes, status, tech_id, created_at 
            FROM storage_units 
            $whereSql 
            ORDER BY id DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $units = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate aggregated high-level stats for dashboard pills
    $statsStmt = $conn->query("
        SELECT 
            COUNT(CASE WHEN status = 'In Storage' THEN 1 END) as total_in_storage,
            COUNT(CASE WHEN status = 'In Storage' AND (need LIKE '%Screen%' OR issue LIKE '%Screen%') THEN 1 END) as needs_screen,
            COUNT(CASE WHEN status = 'In Storage' AND (need LIKE '%Bat%' OR issue LIKE '%Bat%') THEN 1 END) as needs_battery,
            COUNT(CASE WHEN status = 'In Storage' AND (screen LIKE '%Good%' OR screen = '✔') THEN 1 END) as donors_good_screen,
            COUNT(CASE WHEN status = 'In Storage' AND (battery LIKE '%Good%' OR battery = '✔') THEN 1 END) as donors_good_battery,
            COUNT(CASE WHEN status = 'Promoted to Good' THEN 1 END) as promoted_count,
            COUNT(CASE WHEN status = 'Harvested / Scrapped' THEN 1 END) as harvested_count
        FROM storage_units
    ");
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'count'   => count($units),
        'stats'   => $stats,
        'data'    => $units
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Database query failed: ' . $e->getMessage()
    ]);
}
