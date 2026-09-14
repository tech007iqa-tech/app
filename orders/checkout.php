<?php
require_once 'core/database.php';
require_once __DIR__ . '/core/UI.php';
include 'core/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_GET['customer_id'])) {
    header("Location: index.php");
    exit();
}

$customer_id = $_GET['customer_id'];

try {
    $conn_items = Database::orders();
    $conn_cust = Database::customers();

    // 1. Fetch customer details
    $stmt = $conn_cust->prepare("SELECT * FROM customers WHERE customer_id = ?");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Determine active order ID
    $active_order_id = $_GET['order_id'] ?? $_POST['order_id'] ?? null;
    if (!$active_order_id) {
        $stmt = $conn_items->prepare("SELECT order_id FROM orders WHERE customer_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$customer_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $active_order_id = $row['order_id'] ?? 'ORD-DEFAULT';
    }

    // 3. Fetch order details (specifically for the date)
    $stmt_o = $conn_items->prepare("SELECT * FROM orders WHERE customer_id = ? AND order_id = ?");
    $stmt_o->execute([$customer_id, $active_order_id]);
    $order_data = $stmt_o->fetch(PDO::FETCH_ASSOC);
    $order_display_date = $order_data ? date('M d, Y', strtotime($order_data['created_at'])) : date('M d, Y');

    // --- HANDLE POST ACTIONS ---
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        if (!Security::validate($_POST['csrf_token'] ?? '')) {
            die("Security Error: CSRF Token Invalid.");
        }
    }

    // Handle Order Transfer
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'transfer_order') {
        $order_id = $_POST['order_id'] ?? $active_order_id;
        $new_customer_id = $_POST['new_customer_id'];

        // Update orders table
        $stmt_o = $conn_items->prepare("UPDATE orders SET customer_id = ? WHERE order_id = ?");
        $stmt_o->execute([$new_customer_id, $order_id]);

        // Update items table
        $stmt_i = $conn_items->prepare("UPDATE items SET customer_id = ? WHERE order_id = ?");
        $stmt_i->execute([$new_customer_id, $order_id]);

        header("Location: checkout.php?customer_id=" . urlencode($new_customer_id) . "&order_id=" . urlencode($order_id));
        exit();
    }

    // Handle Full Item Metadata & Finalization
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $order_id = $active_order_id;

        // Check if it's a specific single item update (Modal Save) or bulk save
        if (isset($_POST['action']) && $_POST['action'] === 'save_single_item') {
            $stmt = $conn_items->prepare("UPDATE items SET
                brand = ?, model = ?, series = ?, cpu = ?, description = ?,
                ram = ?, storage = ?, battery = ?,
                quantity = ?, unit_price = ?
                WHERE id = ? AND customer_id = ?");
            $stmt->execute([
                $_POST['brand'],
                $_POST['model'],
                $_POST['series'],
                $_POST['cpu'],
                $_POST['description'],
                $_POST['ram'] ?? '',
                $_POST['storage'] ?? '',
                $_POST['battery'] ?? '',
                (float) $_POST['quantity'],
                (float) $_POST['unit_price'],
                (int) $_POST['item_id'],
                $customer_id
            ]);
            echo json_encode(['status' => 'success']);
            exit();
        }

        // Bulk Save (Original Table Form)
        $prices = $_POST['unit_prices'] ?? [];
        $qtys = $_POST['quantities'] ?? [];

        $stmt = $conn_items->prepare("UPDATE items SET unit_price = ?, quantity = ? WHERE id = ? AND customer_id = ?");
        foreach ($prices as $id => $val) {
            $qty = (float) ($qtys[$id] ?? 0);
            $stmt->execute([(float) $val, $qty, (int) $id, $customer_id]);
        }

        // Update Order Date if provided
        if (isset($_POST['order_date'])) {
            // Use time from existing record or fallback to current time
            $existing_time = $order_data ? date('H:i:s', strtotime($order_data['created_at'])) : date('H:i:s');
            $new_date = $_POST['order_date'] . ' ' . $existing_time;

            $stmt_od = $conn_items->prepare("UPDATE orders SET created_at = ? WHERE order_id = ?");
            $stmt_od->execute([$new_date, $order_id]);
        }

        // 2. Determine if we should also finalize the status
        $is_finalize = (isset($_POST['finalize_order']) && $_POST['finalize_order'] === 'true') ||
            (isset($_POST['finalize_status']) && $_POST['finalize_status'] === 'true');

        if ($is_finalize) {
            $stmt_f = $conn_items->prepare("UPDATE orders SET status = 'finalized' WHERE order_id = ?");
            $stmt_f->execute([$order_id]);
        }

        // 3. Handle Responses
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(['status' => 'success']);
            exit();
        }

        if ($is_finalize) {
            header("Location: index.php?view=orders&type=completed");
        } else {
            header("Location: " . $_SERVER['PHP_SELF'] . "?customer_id=" . urlencode($customer_id) . "&order_id=" . urlencode($order_id));
        }
        exit();
    }

    $stmt = $conn_items->prepare("SELECT * FROM items WHERE customer_id = ? AND order_id = ? ORDER BY id ASC");
    $stmt->execute([$customer_id, $active_order_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch all customers for transfer modal
    $stmt_all_c = $conn_cust->query("SELECT customer_id, company_name FROM customers ORDER BY company_name ASC");
    $all_customers = $stmt_all_c->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Checkout - IQA Metal</title>
    <link rel="stylesheet" href="assets/styles/style.css?v=<?= filemtime('assets/styles/style.css') ?>">
    <link rel="stylesheet" href="assets/styles/checkout.css?v=<?= filemtime('assets/styles/checkout.css') ?>">
    <link rel="icon" type="image/png" href="assets/icon/smart-home-sensor-wifi-black-outline-25276_1024.png">
</head>

<body style="flex-direction: column; background: #f8fafc;">

    <div class="receipt-card">
        <!-- Minimal Print Title -->
        <h1 class="print-only"
            style="text-align: center; margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 10px;">Order
            Manifest</h1>

        <div class="header-success no-print" style="text-align: center; margin-bottom: 30px;">
            <a href="index.php"
                onclick="return confirm('Are you sure you want to leave checkout? Any unsaved changes on this manifest will be lost.')"
                style="text-decoration: none; display: inline-block; outline: none; -webkit-tap-highlight-color: transparent;">
                <div class="icon-check" style="cursor: pointer; transition: transform 0.2s ease, box-shadow 0.2s ease;"
                    onmouseover="this.style.transform='scale(1.1)';" onmouseout="this.style.transform='scale(1)';">✓
                </div>
            </a>
            <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--text-main); margin-bottom: 8px;">Final Batch
                Verification</h1>
            <p class="subtitle">Review quantities and pricing for this manifest before final submission.</p>
            <div
                style="margin-top: 20px; max-width: 500px; margin-left: auto; margin-right: auto; display: flex; flex-direction: column; gap: 10px;">
                <select id="checkout-sort" onchange="sortCheckout()"
                    style="height: 48px; font-size: 0.9rem; padding: 0 15px; border-radius: 12px; border: 1px solid var(--border-color); outline: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05); font-weight: 600;">
                    <option value="default">Default Sort (Untested > Tested > Parts)</option>
                    <option value="original">Original Import Order</option>
                    <option value="qty_desc">Quantity (High-Low)</option>
                    <option value="price_desc">Price (High-Low)</option>
                </select>
                <input type="text" id="manifest-search" aria-label="Search manifest items" onkeyup="filterManifest()"
                    placeholder="Search items..."
                    style="width: 100%; height: 48px; border-radius: 12px; border: 1px solid var(--border-color); font-size: 16px; padding: 0 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            </div>
        </div>

        <form method="POST" id="checkout-form">
            <?= UI::csrf_field() ?>
            <div
                style="border-bottom: 1px dashed var(--border-color); padding-bottom: 20px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: flex-end;">
                <div>
                    <div
                        style="font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 4px;">
                        Billing Account</div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="font-weight: 700; color: var(--text-main); font-size: 1.1rem;">
                            <?= htmlspecialchars($customer['company_name'] ?? 'Account Not Found') ?></div>
                        <button type="button" onclick="openTransferModal()" class="no-print"
                            style="background: #f1f5f9; border: none; padding: 4px 8px; border-radius: 6px; font-size: 0.7rem; font-weight: 700; cursor: pointer; color: #475569;">
                            ⇄ Transfer
                        </button>
                    </div>
                </div>
                <div style="text-align: right;">
                    <div
                        style="font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 4px;">
                        Order Date</div>
                    <div class="no-print">
                        <input type="date" name="order_date"
                            value="<?= date('Y-m-d', strtotime($order_data['created_at'] ?? 'now')) ?>"
                            style="font-weight: 700; color: var(--text-main); font-size: 0.9rem; border: 1px solid var(--border-color); border-radius: 6px; padding: 4px 8px; background: white; cursor: pointer; text-align: right;">
                        <input type="hidden" name="order_id" value="<?= htmlspecialchars($active_order_id) ?>">
                    </div>
                    <div class="print-only" style="font-weight: 700; color: var(--text-main); font-size: 0.9rem;">
                        <?= htmlspecialchars($order_display_date) ?>
                    </div>
                </div>
            </div>

            <input type="hidden" name="action" value="update_items">
            <table class="receipt-table">
                <thead>
                    <tr>
                        <th class="col-desc" style="padding-left: 0;">Item Description</th>
                        <th class="col-qty" style="text-align: center;">Qty</th>
                        <th class="col-price" style="text-align: right;">Price</th>
                        <th class="col-total" style="text-align: right; padding-right: 0;">Subtotal</th>
                    </tr>
                </thead>
                <tbody id="checkout-list">
                    <?php
                    $total_items = 0;
                    $grand_total = 0;
                    if (count($items) > 0):
                        foreach ($items as $index => $item):
                            $qty = (float) $item['quantity'];
                            $price = $item['unit_price'] ?? 0;
                            $subtotal = $qty * $price;
                            $total_items += $qty;
                            $grand_total += $subtotal;
                            ?>
                            <tr class="item-row" data-id="<?= $item['id'] ?>">
                                <td class="col-desc" style="padding-left: 0; cursor: pointer;"
                                    onclick="openEditModal(<?= $index ?>)"
                                    title="Click to edit full metadata (CPU, Series, etc.)">
                                    <div
                                        style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;">
                                        <div class="copyable-text" style="flex: 1;">
                                            <div class="item-brand-model" style="font-weight: 700;"><span
                                                    class="item-brand"><?= htmlspecialchars($item['brand']) ?></span> <span
                                                    class="item-model"><?= htmlspecialchars($item['model']) ?></span></div>
                                            <div class="item-metadata"
                                                style="font-size: 0.825rem; color: var(--text-secondary);"
                                                data-desc="<?= htmlspecialchars($item['description'] ?? '') ?>">
                                                <?= htmlspecialchars($item['series'] ?? '') ?> | <span
                                                    style="color: var(--accent-color); font-weight:800;"><?= htmlspecialchars($item['cpu'] ?? '') ?></span>
                                                | <?= htmlspecialchars($item['description'] ?? '') ?></div>
                                        </div>
                                        <button type="button" class="btn-copy no-print"
                                            onclick="event.stopPropagation(); copyEntry(this)" title="Copy Description"
                                            style="background: none; border: none; cursor: pointer; padding: 4px; font-size: 0.9rem; opacity: 0.4; transition: opacity 0.2s; flex-shrink: 0;">
                                            📋
                                        </button>
                                    </div>
                                </td>
                                <td class="col-qty" style="text-align: center;">
                                    <span class="print-only" style="font-weight: 700;"><?= (float) $qty ?></span>
                                    <input type="number" name="quantities[<?= $item['id'] ?>]" aria-label="Item Quantity"
                                        value="<?= (float) $qty ?>" step="any" min="0" class="qty-input no-print"
                                        oninput="recalculateTotals()"
                                        style="width: 70px; text-align: center; height: 38px; border: 1px solid var(--border-color); border-radius: 8px; font-weight: 700;">
                                </td>
                                <td class="col-price" style="text-align: right;">
                                    <span class="print-only">$<?= number_format($price, 2) ?></span>
                                    <div class="no-print price-input-wrapper"
                                        style="display: flex; align-items: center; justify-content: flex-end; gap: 4px;">
                                        <span style="font-weight: 700;">$</span>
                                        <input type="number" name="unit_prices[<?= $item['id'] ?>]" aria-label="Unit Price"
                                            value="<?= number_format($price, 2, '.', '') ?>" step="0.01" class="price-input"
                                            oninput="recalculateTotals()"
                                            style="width: 90px; text-align: right; height: 38px; padding: 0 10px; border: 1px solid var(--border-color); border-radius: 8px; font-weight: 700;">
                                    </div>
                                </td>
                                <td class="col-total"
                                    style="text-align: right; font-weight: 700; color: var(--text-main); padding-right: 0;">
                                    $<span class="row-subtotal"><?= number_format($subtotal, 2) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4">
                                <div class="empty-state">No items found.</div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <tr class="row-total">
                        <td class="total-label" style="padding-left: 0; font-weight: 800;">Total Amount Due</td>
                        <td class="total-qty" style="text-align: center; font-weight: 800; color: var(--text-main);">
                            <span id="total-qty-display"><?= (float) $total_items ?></span>
                        </td>
                        <td class="total-empty"></td>
                        <td class="total-amount"
                            style="text-align: right; color: var(--accent-color); padding-right: 0; font-weight: 800;">
                            $<span id="grand-total-display"><?= number_format($grand_total, 2) ?></span>
                        </td>
                    </tr>
                </tbody>
            </table>


            <div style="margin-top: 30px; display: flex; flex-direction: column; gap: 12px; align-items: stretch;"
                class="no-print">
                <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                    <button type="submit" name="action" value="update_items" class="btn-main"
                        style="flex: 1.5; min-width: 150px; border: none; cursor: pointer; height: 50px; background: var(--text-main); color: white; border-radius: 12px; font-weight: 800; font-size: 1rem; box-shadow: 0 10px 15px -3px rgba(15, 23, 42, 0.1);">
                        💾 Save Changes
                    </button>
                    <button type="button" onclick="handlePrintManifest()" class="crumb btn-print-action"
                        style="flex: 1; min-width: 120px; justify-content: center; height: 50px; background: #fff; cursor:pointer; font-weight: 700; border-radius: 12px; border: 1px solid var(--border-color);">
                        🖨 Print Manifest
                    </button>
                    <button type="button" onclick="downloadCSV()" class="crumb"
                        style="flex: 1; min-width: 120px; justify-content: center; height: 50px; background: #fff; cursor:pointer; font-weight: 700; border-radius: 12px; border: 1px solid var(--border-color); color: #166534;">
                        📊 CSV Form
                    </button>
                </div>

                <button type="submit" name="finalize_order" value="true" class="btn-main"
                    style="border: none; cursor: pointer; text-decoration:none; display:flex; align-items:center; justify-content:center; background: var(--accent-color); color: white; border-radius: 14px; font-weight: 800; height: 54px; box-shadow: 0 4px 12px rgba(140, 198, 63, 0.2);">
                    ✅ Finalize & Finish Batch
                </button>
            </div>
        </form>

        <script id="checkout-state" type="application/json">
        <?= json_encode([
            'rawItems' => $items,
            'customerName' => $customer['company_name'] ?? 'Account',
            'orderID' => $active_order_id,
            'orderDate' => $order_display_date
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
        </script>
        <script>
            console.log("Manifest Sync Initiated.");
        </script>
        <script src="assets/js/checkout.js?v=<?= filemtime('assets/js/checkout.js') ?>"></script>

        <!-- Final Manifest Approval (Print Exclusive) -->
        <div class="manifest-print-footer print-only">
            <div class="footer-row">
                <span><strong>Manifest Generated:</strong> <?= date('Y-m-d H:i:s') ?></span>
                <span
                    style="flex: 1; text-align: center; border-bottom: 1px solid #000; margin: 0 40px; padding-bottom: 4px;">
                    <strong>Approved By:</strong>
                    <?= htmlspecialchars($_SESSION['display_name'] ?? $_SESSION['username'] ?? '________________________') ?>
                </span>
                <span><strong>Page 1 of 1</strong></span>
            </div>
        </div>
    </div>

    <!-- Edit Modal Structure -->
    <div id="editModal" class="modal-overlay no-print" onclick="if(event.target === this) closeEditModal()">
        <div class="modal-box">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 25px;">
                <h3 style="font-weight: 800; font-size: 1.25rem;">📝 Edit Item</h3>
                <button type="button" onclick="closeEditModal()"
                    style="background:none; border:none; cursor:pointer; font-size:1.5rem; opacity:0.5;">&times;</button>
            </div>

            <input type="hidden" id="modal-item-id">
            <input type="hidden" id="modal-item-index">

            <div class="modal-grid">
                <div class="form-group">
                    <label for="modal-brand">Brand</label>
                    <input type="text" id="modal-brand">
                </div>
                <div class="form-group">
                    <label for="modal-model">Model</label>
                    <input type="text" id="modal-model">
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label for="modal-series">Series / Project ID</label>
                    <input type="text" id="modal-series">
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label for="modal-cpu">CPU / Gen</label>
                    <input type="text" id="modal-cpu">
                </div>
                <div class="form-group">
                    <label for="modal-ram">RAM</label>
                    <input type="text" id="modal-ram" placeholder="e.g. 8GB">
                </div>
                <div class="form-group">
                    <label for="modal-storage">Storage</label>
                    <input type="text" id="modal-storage" placeholder="e.g. 256GB">
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label for="modal-battery">Battery Status</label>
                    <select id="modal-battery"
                        style="width: 100%; height: 48px; border-radius: 12px; border: 1px solid var(--border-color); padding: 0 15px; font-weight: 600;">
                        <option value="">Unknown / Untested</option>
                        <option value="Yes">Yes / Good</option>
                        <option value="No">No / Missing / Dead</option>
                    </select>
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label for="modal-desc">Condition / Comments</label>
                    <textarea id="modal-desc"
                        style="width:100%; min-height:80px; border-radius:10px; border:1px solid #ddd; padding:10px;"></textarea>
                </div>
                <div class="form-group">
                    <label for="modal-qty">Quantity</label>
                    <input type="number" id="modal-qty" step="any" min="0">
                </div>
                <div class="form-group">
                    <label for="modal-price">Unit Price ($)</label>
                    <input type="number" id="modal-price" step="0.01">
                </div>
            </div>

            <div style="margin-top: 30px; display: flex; gap: 15px;">
                <button type="button" onclick="printLabel()" id="btn-modal-print" class="btn-main"
                    title="Generate 2x1 Thermal Label"
                    style="flex: 1; border: 2px solid var(--border-color); cursor:pointer; height: 54px; background: transparent; color: var(--text-main); border-radius: 14px; font-weight: 800;">
                    🖨️ Print Label
                </button>
                <button type="button" onclick="saveItemChanges()" id="btn-modal-save" class="btn-main"
                    style="flex: 1; border:none; cursor:pointer; height: 54px; background: var(--text-main); color: white; border-radius: 14px; font-weight: 800;">
                    Update
                </button>
            </div>
        </div>
    </div>

    <p style="margin-top: 24px; font-size: 0.85rem; color: var(--text-secondary);" class="no-print">
        <a href="index.php?customer_id=<?= urlencode($customer_id) ?>&order_id=<?= urlencode($active_order_id) ?>"
            style="color: var(--accent-color); text-decoration: none; font-weight: 700;">← Back to Order Entry
            (Edit)</a>
    </p>

    <!-- Transfer Order Modal -->
    <div id="transferModal" class="modal-overlay no-print" onclick="if(event.target === this) closeTransferModal()">
        <div class="modal-box" style="max-width: 450px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
                <h3 style="font-weight: 800; font-size: 1.25rem;">⇄ Transfer Global Batch</h3>
                <button type="button" onclick="closeTransferModal()"
                    style="background:none; border:none; cursor:pointer; font-size:1.5rem; opacity:0.5;">&times;</button>
            </div>
            <p style="font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 20px;">Relocate this entire order
                and all its items to a different customer account.</p>

            <form method="POST">
                <?= UI::csrf_field() ?>
                <input type="hidden" name="action" value="transfer_order">
                <input type="hidden" name="order_id" value="<?= htmlspecialchars($active_order_id) ?>">

                <div class="form-group" style="margin-bottom: 25px;">
                    <label for="new_customer_id">Target Customer Account</label>
                    <select name="new_customer_id" id="new_customer_id" required
                        style="width: 100%; height: 48px; border-radius: 12px; border: 1px solid var(--border-color); padding: 0 15px; font-weight: 600;">
                        <?php foreach ($all_customers as $c): ?>
                            <option value="<?= htmlspecialchars($c['customer_id']) ?>" <?= $c['customer_id'] === $customer_id ? 'disabled' : '' ?>>
                                <?= htmlspecialchars($c['company_name']) ?> (<?= htmlspecialchars($c['customer_id']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn-main"
                    style="width: 100%; border:none; cursor:pointer; height: 54px; background: var(--accent-color); color: white; border-radius: 14px; font-weight: 800; box-shadow: 0 4px 12px rgba(140, 198, 63, 0.2);">
                    Confirm Transfer
                </button>
            </form>
        </div>
    </div>

    <script>
        function openTransferModal() {
            document.getElementById('transferModal').classList.add('active');
        }
        function closeTransferModal() {
            document.getElementById('transferModal').classList.remove('active');
        }
    </script>
</body>

</html>