<?php
// orders/api/add_order_item.php
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

// 1. Validate CSRF
if (!Security::validate($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

try {
    $conn = Database::orders();

    $customer_id = $_POST['customer_id'];
    $order_id = $_POST['order_id'];
    $brand = $_POST['brand'];
    $model = $_POST['model'];
    $series = $_POST['series'] ?? '';
    $cpu = $_POST['cpu'] ?? '';
    $desc = $_POST['description'] ?? '';
    $notes = $_POST['notes'] ?? '';
    $qty = Security::sanitize_float($_POST['quantity']);
    $price = Security::sanitize_float($_POST['unit_price'] ?? 0.00);

    $stmt = $conn->prepare("INSERT INTO items (order_id, customer_id, brand, model, series, cpu, description, notes, quantity, unit_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    if ($stmt->execute([$order_id, $customer_id, $brand, $model, $series, $cpu, $desc, $notes, $qty, $price])) {
        $new_id = $conn->lastInsertId();

        // Update Session for "Repeat Last"
        $_SESSION['last_entry'] = [
            'brand' => $brand,
            'model' => $model,
            'series' => $series,
            'cpu' => $cpu,
            'description' => $desc,
            'notes' => $notes
        ];

        // Fetch new total units
        $stmt_total = $conn->prepare("SELECT SUM(quantity) FROM items WHERE order_id = ?");
        $stmt_total->execute([$order_id]);
        $total_units = $stmt_total->fetchColumn() ?: 0;

        // Generate the HTML for the new row to return to the frontend
        $formatted_price = number_format($price, 2);

        echo json_encode([
            'success' => true,
            'item_id' => $new_id,
            'new_total' => $total_units,
            'last_entry' => $_SESSION['last_entry'],
            'row_html' => "
                <tr class='summary-row flash-new' data-id='{$new_id}' data-desc='".htmlspecialchars($desc)."' data-qty='{$qty}' data-price='{$price}' data-search='".htmlspecialchars(strtolower("$brand $model $series"))."'>
                    <td>
                        <div class='item-primary'>".htmlspecialchars($brand)." ".htmlspecialchars($model)."</div>
                        <div class='item-secondary'>".htmlspecialchars($series)." | ".htmlspecialchars($cpu)."</div>
                        ".(!empty(trim($desc)) ? "<div class='item-description' style='font-size: 0.75rem; color: #64748b; margin-top: 4px;'>".nl2br(htmlspecialchars($desc))."</div>" : "")."
                    </td>
                    <td style='text-align:center; font-weight:700;'>{$qty}</td>
                    <td style='text-align:right;'>\${$formatted_price}</td>
                    <td style='text-align:left; color:#64748b;'>".htmlspecialchars($notes)."</td>
                    <td style='text-align:right;'>
                        <div class='action-buttons'>
                            <button type='button' class='btn-edit' onclick='openEditModal(".json_encode(['id'=>$new_id, 'brand'=>$brand, 'model'=>$model, 'series'=>$series, 'cpu'=>$cpu, 'description'=>$desc, 'notes'=>$notes, 'quantity'=>$qty, 'unit_price'=>$price]).")'>✎</button>
                            <form method='POST' style='display:inline;'>
                                <input type='hidden' name='action' value='delete'>
                                <input type='hidden' name='delete_id' value='{$new_id}'>
                                <input type='hidden' name='csrf_token' value='{$_POST['csrf_token']}'>
                                <button type='submit' class='btn-delete'>🗑</button>
                            </form>
                        </div>
                    </td>
                </tr>"
        ]);
    } else {
        throw new Exception("Failed to insert item");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
