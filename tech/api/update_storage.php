<?php
/**
 * tech/api/update_storage.php
 * Update or Harvest parts from a Storage / Donor Unit
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

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid Storage Unit ID.']);
        return;
    }

    $stmt_find = $conn->prepare("SELECT * FROM storage_units WHERE id = ?");
    $stmt_find->execute([$id]);
    $unit = $stmt_find->fetch(PDO::FETCH_ASSOC);

    if (!$unit) {
        echo json_encode(['success' => false, 'error' => 'Storage unit not found.']);
        return;
    }

    $action  = trim($_POST['action'] ?? 'update');
    $tech_id = $_SESSION['username'] ?? 'tech';
    $today   = date('Y-m-d');

    if ($action === 'harvest') {
        $part = trim($_POST['part'] ?? ''); // 'Screen', 'Battery', or custom
        $recipient = trim($_POST['recipient'] ?? 'Bench Unit');

        if (empty($part)) {
            echo json_encode(['success' => false, 'error' => 'Part name is required for harvesting.']);
            exit();
        }

        $screen = $unit['screen'];
        $battery = $unit['battery'];
        $notes = $unit['notes'];

        $pLower = strtolower($part);
        if (strpos($pLower, 'screen') !== false) {
            $screen = 'Harvested / Taken';
        } elseif (strpos($pLower, 'bat') !== false) {
            $battery = 'Harvested / Taken';
        }

        $harvestNote = "\n[Harvested $part for $recipient on $today by $tech_id]";
        $notes = trim($notes . $harvestNote);

        $stmt_up = $conn->prepare("UPDATE storage_units SET screen = ?, battery = ?, notes = ? WHERE id = ?");
        $stmt_up->execute([$screen, $battery, $notes, $id]);

        echo json_encode([
            'success' => true,
            'message' => "Successfully recorded harvest of $part from {$unit['sku']}."
        ]);
        return;
    }

    // Standard edit
    $sku       = trim($_POST['sku'] ?? $unit['sku']);
    if (empty($sku)) $sku = $unit['sku'];

    if ($sku !== $unit['sku']) {
        $stmt_check = $conn->prepare("SELECT id FROM storage_units WHERE sku = ? AND id != ?");
        $stmt_check->execute([$sku, $id]);
        if ($stmt_check->fetch()) {
            echo json_encode(['success' => false, 'error' => "SKU '$sku' is already taken by another storage unit."]);
            return;
        }
    }

    $brand     = trim($_POST['brand'] ?? $unit['brand']);
    $model_num = trim($_POST['model_num'] ?? $unit['model_num']);
    $series    = trim($_POST['series'] ?? $unit['series']);
    $battery   = trim($_POST['battery'] ?? $unit['battery']);
    $screen    = trim($_POST['screen'] ?? $unit['screen']);
    $issue     = trim($_POST['issue'] ?? $unit['issue']);
    $need      = trim($_POST['need'] ?? $unit['need']);
    $notes     = trim($_POST['notes'] ?? $unit['notes']);
    $status    = trim($_POST['status'] ?? $unit['status']);

    $stmt_up = $conn->prepare("
        UPDATE storage_units 
        SET sku = ?, brand = ?, model_num = ?, series = ?, battery = ?, screen = ?, issue = ?, need = ?, notes = ?, status = ?
        WHERE id = ?
    ");
    $stmt_up->execute([$sku, $brand, $model_num, $series, $battery, $screen, $issue, $need, $notes, $status, $id]);

    echo json_encode([
        'success' => true,
        'message' => "Storage unit {$unit['sku']} updated successfully."
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
