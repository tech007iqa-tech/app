<?php
/**
 * New Order Actions & Data Model Partial
 * Handles item deletion, item updating, customer lookup, and order line-items query.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $conn = Database::orders();

    // Handle Form Submission
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        if (!Security::validate($_POST['csrf_token'] ?? '')) {
            die("Security Error: CSRF Token Invalid.");
        }

        if (isset($_POST['action']) && $_POST['action'] === 'delete') {
            $delete_id = $_POST['delete_id'] ?? 0;
            $stmt = $conn->prepare("DELETE FROM items WHERE id = ?");
            if ($stmt->execute([$delete_id])) {
                $_SESSION['notification_msg'] = "Item removed from order. 🗑️";
                $_SESSION['notification_type'] = "success";
            }
        } elseif (isset($_POST['action']) && $_POST['action'] === 'update_item') {
            $update_id = $_POST['update_id'] ?? 0;
            $qty = Security::sanitize_float($_POST['update_qty'] ?? 1);
            $price = Security::sanitize_float($_POST['update_price'] ?? 0.00);
            $brand = $_POST['update_brand'] ?? '';
            $model = $_POST['update_model'] ?? '';
            $series = $_POST['update_series'] ?? '';
            $cpu = trim(($_POST['edit_cpu_series'] ?? '') . ' ' . ($_POST['edit_cpu_gen'] ?? ''));
            $desc = $_POST['update_desc'] ?? '';
            $notes = $_POST['update_notes'] ?? '';

            $stmt = $conn->prepare("UPDATE items SET brand=?, model=?, series=?, cpu=?, description=?, notes=?, quantity=?, unit_price=? WHERE id=?");
            if ($stmt->execute([$brand, $model, $series, $cpu, $desc, $notes, (float) $qty, (float) $price, (int) $update_id])) {
                $_SESSION['notification_msg'] = "Item details updated. 💾";
                $_SESSION['notification_type'] = "success";
            }
        }

        $current_customer = $_GET['customer_id'] ?? null;
        $current_order = $_GET['order_id'] ?? 'ORD-DEFAULT';
        header("Location: index.php?customer_id=" . urlencode($current_customer) . "&order_id=" . urlencode($current_order) . "#summary-list");
        exit();
    }
} catch (PDOException $e) {
    die("Database Connection failed: " . $e->getMessage());
}

$current_customer = $_GET['customer_id'] ?? null;
$current_order = $_GET['order_id'] ?? 'ORD-DEFAULT';

// Fetch customer details
$customer_info = null;
if ($current_customer) {
    try {
        $conn_c = Database::customers();
        $stmt_c = $conn_c->prepare("SELECT company_name, contact_person AS contact_name FROM customers WHERE customer_id = ?");
        $stmt_c->execute([$current_customer]);
        $customer_info = $stmt_c->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Fallback silently if query fails
    }
}

// Fetch current order items
$stmt = $conn->prepare("SELECT * FROM items WHERE order_id = ? AND customer_id = ? ORDER BY
    CASE
        WHEN description LIKE '%Untested%' THEN 1
        WHEN description LIKE '%Tested%' AND description NOT LIKE '%Untested%' THEN 2
        WHEN description LIKE '%Parts%' THEN 3
        ELSE 4
    END ASC,
    id ASC");
$stmt->execute([$current_order, $current_customer]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch total units for this batch
$total_units = 0;
foreach ($items as $item) {
    $total_units += $item['quantity'];
}
