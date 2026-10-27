<?php
/**
 * tech/api/promote_storage.php
 * Promote a repaired Storage Unit to Good (creates Hardware Log) or Retire as Scrapped
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
    $action = trim($_POST['action'] ?? 'promote_to_good');
    $tech_id = $_SESSION['username'] ?? 'tech';
    $today = date('Y-m-d');

    if ($id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid Storage Unit ID.']);
        exit();
    }

    $stmt_find = $conn->prepare("SELECT * FROM storage_units WHERE id = ?");
    $stmt_find->execute([$id]);
    $unit = $stmt_find->fetch(PDO::FETCH_ASSOC);

    if (!$unit) {
        echo json_encode(['success' => false, 'error' => 'Storage unit not found.']);
        exit();
    }

    if ($action === 'retire_scrapped') {
        $reason = trim($_POST['reason'] ?? 'All usable donor parts harvested');
        $newNotes = trim($unit['notes'] . "\n[Retired/Scrapped on $today by $tech_id: $reason]");

        $stmt_up = $conn->prepare("UPDATE storage_units SET status = 'Harvested / Scrapped', notes = ? WHERE id = ?");
        $stmt_up->execute([$newNotes, $id]);

        echo json_encode([
            'success' => true,
            'message' => "Unit {$unit['sku']} marked as Harvested / Scrapped."
        ]);
        return;
    }

    if ($action === 'promote_to_good') {
        $resolution = trim($_POST['resolution_notes'] ?? 'Repaired and QC passed');
        $ram = trim($_POST['ram'] ?? '');
        $storage = trim($_POST['storage'] ?? '');
        $cpu = trim($_POST['cpu'] ?? '');
        $serial = trim($_POST['serial_number'] ?? $unit['sku']);

        // Insert into logs as 'Good'
        $logNotes = "Promoted from Storage [SKU: {$unit['sku']}]. Resolution: $resolution";
        if (!empty($unit['notes'])) {
            $logNotes .= " | Prior Notes: " . $unit['notes'];
        }

        $stmt_log = $conn->prepare("
            INSERT INTO logs (
                tech_id, status, qty, make, model, series, serial_number,
                ram, storage, cpu, battery, delivery_status, ready_for_warehouse, notes
            ) VALUES (
                ?, 'Good', 1, ?, ?, ?, ?,
                ?, ?, ?, 'Included / Good', 'Pending', 0, ?
            )
        ");
        $stmt_log->execute([
            $tech_id,
            $unit['brand'],
            !empty($unit['series']) ? $unit['series'] : $unit['brand'],
            $unit['model_num'],
            $serial,
            $ram,
            $storage,
            $cpu,
            $logNotes
        ]);
        $newLogId = $conn->lastInsertId();

        // Update storage_units status
        $storageNotes = trim($unit['notes'] . "\n[Promoted to Good on $today by $tech_id -> Hardware Log #$newLogId]");
        $stmt_up = $conn->prepare("UPDATE storage_units SET status = 'Promoted to Good', notes = ? WHERE id = ?");
        $stmt_up->execute([$storageNotes, $id]);

        echo json_encode([
            'success' => true,
            'log_id'  => (int)$newLogId,
            'message' => "Unit {$unit['sku']} promoted to Good! Log #$newLogId recorded in Hardware Logs."
        ]);
        return;
    }

    echo json_encode(['success' => false, 'error' => "Unknown action '$action'."]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
