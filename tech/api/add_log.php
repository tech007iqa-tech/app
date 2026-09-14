<?php
require_once '../core/database.php';
require_once '../core/auth.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $tech_id = $_SESSION['username'] ?? 'Unknown';
    $status = $_POST['status'] ?? 'Good';
    $qty = (int)($_POST['qty'] ?? 1);

    // Sanitize basic text fields
    $fields = ['make', 'model', 'series', 'cpu', 'gpu', 'ram', 'storage', 'battery', 'bios_state', 'os', 'notes'];
    $data = [];
    foreach ($fields as $f) {
        $data[$f] = trim($_POST[$f] ?? '');
    }

    if (empty($data['make']) || empty($data['model'])) {
        echo json_encode(['success' => false, 'error' => 'Make and Model are required.']);
        exit();
    }

    try {
        $conn = Database::tech();
        $stmt = $conn->prepare("INSERT INTO logs (tech_id, status, qty, make, model, series, cpu, gpu, ram, storage, battery, bios_state, os, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $params = [
            $tech_id, $status, $qty,
            $data['make'], $data['model'], $data['series'],
            $data['cpu'], $data['gpu'], $data['ram'], $data['storage'],
            $data['battery'], $data['bios_state'], $data['os'], $data['notes']
        ];

        $stmt->execute($params);

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
}
?>
