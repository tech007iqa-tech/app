<?php
/**
 * tech/api/add_storage.php
 * Ingest a machine into Storage / Donor Inventory
 */
if (!headers_sent()) {
    header('Content-Type: application/json');
}
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit();
}

try {
    $conn = Database::tech();

    $brand     = trim($_POST['brand'] ?? '');
    $model_num = trim($_POST['model_num'] ?? '');
    $series    = trim($_POST['series'] ?? '');
    $issue     = trim($_POST['issue'] ?? '');
    $need      = trim($_POST['need'] ?? '');
    $battery   = trim($_POST['battery'] ?? '');
    $screen    = trim($_POST['screen'] ?? '');
    $notes     = trim($_POST['notes'] ?? '');
    $sku       = trim($_POST['sku'] ?? '');
    $tech_id   = $_SESSION['username'] ?? 'tech';
    $date_added = date('Y-m-d'); // Pacific Time

    if (empty($brand) || empty($model_num)) {
        echo json_encode(['success' => false, 'error' => 'Brand and Model # are required.']);
        exit();
    }

    if (empty($issue)) {
        $issue = !empty($need) ? "Needs $need" : "Needs Triage";
    }

    // Auto-generate SKU based on Brand and Series (deterministic warehouse shelf zoning)
    $prefix = 'RM';
    $bLower = strtolower($brand);
    $sLower = strtolower($series . ' ' . $model_num);

    if ($bLower === 'dell') {
        if (strpos($sLower, 'lat') !== false || preg_match('/\b[357]\d{3}\b/', $sLower)) $prefix = 'DL';
        elseif (strpos($sLower, 'prec') !== false || preg_match('/\b(35|55|75|77)\d{2}\b/', $sLower)) $prefix = 'DP';
        elseif (strpos($sLower, 'xps') !== false) $prefix = 'DeX';
        else $prefix = 'DL';
    } elseif ($bLower === 'hp') {
        if (strpos($sLower, 'zbook') !== false || strpos($sLower, 'fury') !== false) $prefix = 'HZ';
        elseif (strpos($sLower, 'pro') !== false) $prefix = 'HpPro';
        elseif (strpos($sLower, 'elite') !== false || preg_match('/\b[86]\d{2}\b/', $sLower)) $prefix = 'HE';
        else $prefix = 'HE';
    } elseif ($bLower === 'lenovo') {
        if (strpos($sLower, 'think') !== false || preg_match('/\b[tplex]\d{2,3}\b/i', $sLower)) $prefix = 'LT';
        else $prefix = 'LO';
    } elseif ($bLower === 'apple') {
        $prefix = 'AP';
    } elseif ($bLower === 'microsoft') {
        $prefix = 'MS';
    }

    // Find highest existing number for this prefix
    $stmt_max = $conn->prepare("SELECT sku FROM storage_units WHERE sku LIKE ?");
    $stmt_max->execute(["$prefix-%"]);
    $existing = $stmt_max->fetchAll(PDO::FETCH_COLUMN);

    $maxNum = 0;
    foreach ($existing as $exSku) {
        if (preg_match('/^' . preg_quote($prefix, '/') . '-(\d+)$/i', $exSku, $m)) {
            $num = (int)$m[1];
            if ($num > $maxNum) $maxNum = $num;
        }
    }
    $sku = $prefix . '-' . ($maxNum + 1);

    $stmt = $conn->prepare("
        INSERT INTO storage_units 
        (sku, date_added, brand, model_num, series, battery, screen, issue, need, notes, status, tech_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'In Storage', ?)
    ");
    $stmt->execute([$sku, $date_added, $brand, $model_num, $series, $battery, $screen, $issue, $need, $notes, $tech_id]);
    $newId = $conn->lastInsertId();

    echo json_encode([
        'success' => true,
        'id'      => (int)$newId,
        'sku'     => $sku,
        'message' => "Unit successfully ingested into storage with SKU $sku."
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
