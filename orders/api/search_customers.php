<?php
// orders/api/search_customers.php
header('Content-Type: application/json');
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/Security.php';

session_start();
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit;
}

try {
    $conn = Database::customers();
    $q = $_GET['q'] ?? '';

    $sql = "SELECT * FROM customers";
    $params = [];

    if (!empty($q)) {
        $sql .= " WHERE company_name LIKE ? OR contact_person LIKE ? OR email LIKE ? OR phone LIKE ? OR customer_id LIKE ?";
        $term = "%$q%";
        $params = [$term, $term, $term, $term, $term];
    }

    $sql .= " ORDER BY created_at DESC LIMIT 100";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $customers
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
