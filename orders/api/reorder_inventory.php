<?php
// orders/api/reorder_inventory.php
header('Content-Type: application/json');
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/Security.php';
session_start();

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access.']);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

if (!Security::validate($input['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$order = $input['order'] ?? [];
if (!is_array($order) || empty($order)) {
    echo json_encode(['success' => true, 'updated' => 0]);
    exit;
}

try {
    $conn_wh = Database::warehouse();
    $conn_wh->beginTransaction();

    $stmt = $conn_wh->prepare("UPDATE inventory SET sort_order = ? WHERE id = ?");
    $pos = 1;
    foreach ($order as $id) {
        $clean_id = (int)$id;
        if ($clean_id > 0) {
            $stmt->execute([$pos++, $clean_id]);
        }
    }

    $conn_wh->commit();
    echo json_encode(['success' => true, 'updated' => $pos - 1]);
} catch (Exception $e) {
    if (isset($conn_wh) && $conn_wh->inTransaction()) {
        $conn_wh->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
