<?php
require_once '../core/database.php';
include '../core/auth.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit();
}

try {
    if (!Security::validate($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'Security Error: CSRF Token Invalid']);
        exit();
    }
    $conn = Database::customers();

    $customer_id = $_POST['customer_id'] ?? null;
    $action = $_POST['action'] ?? 'update';

    if ($action === 'register_customer') {
        // Generate new ID for quick registration
        $customer_id = 'CUST-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

        $stmt = $conn->prepare("INSERT INTO customers (
            customer_id, company_name, contact_person, email, phone,
            account_status, lead_source, interest, internal_notes, callback_date, message_date
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->execute([
            $customer_id,
            $_POST['company_name'],
            $_POST['contact_person'] ?? '',
            $_POST['email'] ?? '',
            $_POST['phone'] ?? '',
            $_POST['account_status'] ?? 'Lead',
            $_POST['lead_source'] ?? '',
            $_POST['interest'] ?? '',
            $_POST['interest'] ?? '', // Also putting initial interest in notes
            '', // No callback yet
            date('Y-m-d') // Message date is today
        ]);
    } else {
        if (!$customer_id) throw new Exception("Missing Customer ID for update");

        // Update main customer fields
        $stmt = $conn->prepare("UPDATE customers SET
            account_status = ?, lead_source = ?, interest = ?,
            contact_method = ?, callback_date = ?, message_date = ?, internal_notes = ?
            WHERE customer_id = ?");

        $stmt->execute([
            $_POST['account_status'], $_POST['lead_source'], $_POST['interest'],
            $_POST['contact_method'], $_POST['callback_date'], $_POST['message_date'], $_POST['internal_notes'],
            $customer_id
        ]);
    }

    // 2. Log interaction if a new note is provided
    if (!empty($_POST['new_interaction_note'])) {
        $stmt_log = $conn->prepare("INSERT INTO interaction_logs (customer_id, contact_date, method, note) VALUES (?, ?, ?, ?)");
        $stmt_log->execute([
            $customer_id,
            $_POST['message_date'] ?: date('Y-m-d'),
            $_POST['contact_method'] ?: 'Other',
            $_POST['new_interaction_note']
        ]);
    }

    echo json_encode(['status' => 'success', 'message' => 'CRM details updated']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
