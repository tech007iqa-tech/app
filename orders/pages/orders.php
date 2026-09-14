<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $conn = Database::orders();
    $conn_c = Database::customers();

    // Fetch Filtering parameters
    $show_type = $_GET['type'] ?? 'active'; // 'active' vs 'completed'

    // Fetch all orders with customer details using Cross-DB Join
    $history_statuses = "'paid', 'dispatched', 'finalized', 'canceled'";

    try {
        Database::attach($conn, 'customers', 'customers_db');

        $base_sql = "SELECT o.order_id, o.customer_id, o.status, o.created_at, c.company_name FROM orders o
                     LEFT JOIN customers_db.customers c ON o.customer_id = c.customer_id";

        if ($show_type === 'completed') {
            $sql = "$base_sql WHERE o.status IN ($history_statuses) ORDER BY o.created_at DESC";
        } else {
            $sql = "$base_sql WHERE (o.status NOT IN ($history_statuses) OR o.status IS NULL)
                    OR (o.status = 'finalized' AND o.created_at > datetime('now', '-24 hours'))
                    ORDER BY o.created_at DESC";
        }
    } catch (Exception $e) {
        // Fallback if customers DB cannot be attached
        if ($show_type === 'completed') {
            $sql = "SELECT order_id, customer_id, status, created_at, 'Unknown Account' as company_name FROM orders WHERE status IN ($history_statuses) ORDER BY created_at DESC";
        } else {
            $sql = "SELECT order_id, customer_id, status, created_at, 'Unknown Account' as company_name FROM orders
                    WHERE (status NOT IN ($history_statuses) OR status IS NULL)
                    OR (status = 'finalized' AND created_at > datetime('now', '-24 hours'))
                    ORDER BY created_at DESC";
        }
    }

    $stmt = $conn->query($sql);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch all customers for transfer dropdown
    $stmt_c = $conn_c->query("SELECT customer_id, company_name FROM customers ORDER BY company_name ASC");
    $all_customers = $stmt_c->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Connection failed: " . $e->getMessage());
}

if (UI::is_ajax()) {
    if (ob_get_level() > 0) ob_clean();
    ob_start();
    if (count($orders) > 0) {
        foreach ($orders as $order) {
            $company = $order['company_name'] ?: 'Unknown Account';
            $status = strtolower($order['status']);
            $search_blob = strtolower($order['order_id'] . " " . $company . " " . $order['customer_id']);
            $status_class = "status-" . $status;
            ?>
            <tr class="order-row" data-id="<?= $order['order_id'] ?>" data-search="<?= htmlspecialchars($search_blob) ?>" style="border-bottom: 1px solid #f1f5f9; transition: background 0.2s;">
                <td style="text-align: center;"><input type="checkbox" class="row-select"></td>
                <td style="padding: 20px 24px;">
                    <div style="font-weight: 800; color: var(--text-main); font-size: 0.95rem; font-family: monospace;"><?= htmlspecialchars($order['order_id']) ?></div>
                </td>
                <td style="padding: 20px 24px;">
                    <div style="display:flex; align-items:center; gap: 8px;">
                        <a href="index.php#customer-details" onclick="localStorage.setItem('active_customer_id', '<?= $order['customer_id'] ?>')"><div style="font-weight: 700; color: var(--text-main);"><?= htmlspecialchars($company) ?></div></a>
                        <button type="button" class="btn-transfer-small no-print" onclick="openTransferModal('<?= htmlspecialchars($order['order_id']) ?>', '<?= htmlspecialchars($order['customer_id']) ?>')" title="Transfer" style="background:none; border:none; cursor:pointer; font-size: 0.8rem; opacity:0.3; transition: opacity 0.2s;">⇄</button>
                    </div>
                    <div style="font-size: 0.7rem; color: #94a3b8; font-family: monospace; margin-top: 2px;"><?= htmlspecialchars($order['customer_id']) ?></div>
                </td>
                <td style="padding: 20px 24px;">
                    <div style="font-size: 0.85rem; font-weight: 600; color: #64748b;"><?= date('M d, Y', strtotime($order['created_at'])) ?></div>
                </td>
                <td style="padding: 20px 24px;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <span class="order-badge <?= $status_class ?>" style="min-width: 80px; text-align: center;">
                            <?= htmlspecialchars($status) ?>
                        </span>
                        <div class="select-wrapper" style="position: relative;">
                            <select name="new_status" class="order-status-select"
                                    onchange="updateOrderStatus(this, '<?= $order['order_id'] ?>')"
                                    data-original-value="<?= htmlspecialchars($status) ?>"
                                    style="height: 32px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 0.75rem; font-weight: 700; padding: 0 10px; background: #f8fafc; cursor: pointer;">
                                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>Paid</option>
                                <option value="dispatched" <?= $status === 'dispatched' ? 'selected' : '' ?>>Dispatched</option>
                                <option value="canceled" <?= $status === 'canceled' ? 'selected' : '' ?>>Canceled</option>
                                <option value="finalized" <?= $status === 'finalized' ? 'selected' : '' ?>>Finalized</option>
                            </select>
                        </div>
                    </div>
                </td>
                <td style="padding: 20px 24px; text-align: right;">
                    <a href="checkout.php?customer_id=<?= urlencode($order['customer_id']) ?>&order_id=<?= urlencode($order['order_id']) ?>"
                       class="btn-order-view"
                       style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; background: #f1f5f9; color: #475569; border-radius: 10px; text-decoration: none; font-weight: 800; font-size: 0.85rem; transition: all 0.2s;">
                        <span>Details</span>
                        <i style="font-style: normal; font-size: 1.1rem; line-height: 1;">→</i>
                    </a>
                </td>
            </tr>
            <?php
        }
    } else {
        echo '<tr><td colspan="5" style="padding: 60px; text-align: center; color: #94a3b8; font-weight: 600;">No batches found in this category.</td></tr>';
    }
    $table_html = ob_get_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'orders-list' => $table_html
    ]);
    exit();
}
?>

<!-- Load dedicated registry styles -->


<div class="orders-container">
    <header class="orders-header">
        <div>
            <h1>Global Batch Registry</h1>
            <p class="subtitle orders-subtitle">Review batches and manage fulfillment states across all accounts.</p>
        </div>
            <div class="orders-tabs">
                <a href="index.php?view=orders&type=active" class="orders-tab-link <?= $show_type === 'active' ? 'active' : 'inactive' ?>">Active Batches</a>
                <a href="index.php?view=orders&type=completed" class="orders-tab-link <?= $show_type === 'completed' ? 'active' : 'inactive' ?>">Finalized History</a>
            </div>
    </header>

    <!-- Bulk Action Bar -->
    <div id="bulkActionBar" class="bulk-action-bar" style="display:none; background: var(--accent-gradient); color: white; padding: 15px 25px; border-radius: 16px; margin-bottom: 25px; align-items: center; justify-content: space-between; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
        <div class="bulk-info">
            <span id="selectedCount" style="font-weight: 900;">0</span> items selected
        </div>
        <div class="bulk-actions" style="display: flex; gap: 15px; align-items: center;">
            <select id="bulkStatus" class="filter-select" style="height: 40px; border-radius: 10px; border: none; padding: 0 15px; font-weight: 700; background: white; color: var(--text-main);">
                <option value="">-- Change Status --</option>
                <option value="pending">⏳ Pending</option>
                <option value="paid">💰 Paid</option>
                <option value="dispatched">🚚 Dispatched</option>
                <option value="canceled">❌ Canceled</option>
                <option value="finalized">✅ Finalized</option>
            </select>
            <button id="applyBulkBtn" style="background: white; color: var(--text-main); border: none; padding: 10px 20px; border-radius: 10px; font-weight: 800; cursor: pointer;">Apply to Selected</button>
            <button id="cancelBulkBtn" style="background: none; border: 1px solid rgba(255,255,255,0.3); color: white; padding: 10px 15px; border-radius: 10px; cursor: pointer; font-weight: 700;">Cancel</button>
        </div>
    </div>
    <?= UI::csrf_field() ?>

    <!-- Live Search Input -->
    <div class="orders-search-wrapper" style="margin-bottom: 25px;">
        <i class="search-icon">🔍</i>
        <input type="text" id="order-search" placeholder="Search by Order ID, Company, or Customer ID..." aria-label="Search orders" onkeyup="filterOrders()">
    </div>

    <div class="table-container" style="background: white; border-radius: 20px; border: 1px solid var(--border-color); overflow-x: auto; -webkit-overflow-scrolling: touch; box-shadow: var(--shadow-sm);">
        <table class="orders-table" style="width: 100%; border-collapse: collapse; text-align: left;">
            <thead>
                <tr style="background: #1e293b !important;">
                    <th style="background: #1e293b !important; color: white !important; width: 40px; text-align: center;"><input type="checkbox" id="selectAll"></th>
                    <th style="background: #1e293b !important; color: white !important; padding: 16px 24px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; cursor:pointer; letter-spacing:0.05em; border:none;" onclick="sortOrdersTable(0)">Batch ID ⇅</th>
                    <th style="background: #1e293b !important; color: white !important; padding: 16px 24px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; cursor:pointer; letter-spacing:0.05em; border:none;" onclick="sortOrdersTable(1)">Account / Customer ⇅</th>
                    <th style="background: #1e293b !important; color: white !important; padding: 16px 24px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; cursor:pointer; letter-spacing:0.05em; border:none;" onclick="sortOrdersTable(2)">Date Created ⇅</th>
                    <th style="background: #1e293b !important; color: white !important; padding: 16px 24px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing:0.05em; border:none;">Current Status</th>
                    <th style="background: #1e293b !important; color: white !important; padding: 16px 24px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; text-align: right; letter-spacing:0.05em; border:none;">Actions</th>
                </tr>
            </thead>
            <tbody id="orders-list">
                <?php if (count($orders) > 0): ?>
                    <?php foreach ($orders as $order):
                        $company = $order['company_name'] ?: 'Unknown Account';
                        $status = strtolower($order['status']);
                        $search_blob = strtolower($order['order_id'] . " " . $company . " " . $order['customer_id']);
                        $status_class = "status-" . $status;
                    ?>
                    <tr class="order-row" data-id="<?= $order['order_id'] ?>" data-search="<?= htmlspecialchars($search_blob) ?>" style="border-bottom: 1px solid #f1f5f9; transition: background 0.2s;">
                        <td style="text-align: center;"><input type="checkbox" class="row-select"></td>
                        <td style="padding: 20px 24px;">
                            <div style="font-weight: 800; color: var(--text-main); font-size: 0.95rem; font-family: monospace;"><?= htmlspecialchars($order['order_id']) ?></div>
                        </td>
                        <td style="padding: 20px 24px;">
                            <div style="display:flex; align-items:center; gap: 8px;">
                                <a href="index.php#customer-details" onclick="localStorage.setItem('active_customer_id', '<?= $order['customer_id'] ?>')"><div style="font-weight: 700; color: var(--text-main);"><?= htmlspecialchars($company) ?></div></a>
                                <button type="button" class="btn-transfer-small no-print" onclick="openTransferModal('<?= htmlspecialchars($order['order_id']) ?>', '<?= htmlspecialchars($order['customer_id']) ?>')" title="Transfer" style="background:none; border:none; cursor:pointer; font-size: 0.8rem; opacity:0.3; transition: opacity 0.2s;">⇄</button>
                            </div>
                            <div style="font-size: 0.7rem; color: #94a3b8; font-family: monospace; margin-top: 2px;"><?= htmlspecialchars($order['customer_id']) ?></div>
                        </td>
                        <td style="padding: 20px 24px;">
                            <div style="font-size: 0.85rem; font-weight: 600; color: #64748b;"><?= date('M d, Y', strtotime($order['created_at'])) ?></div>
                        </td>
                        <td style="padding: 20px 24px;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <span class="order-badge <?= $status_class ?>" style="min-width: 80px; text-align: center;">
                                    <?= htmlspecialchars($status) ?>
                                </span>
                                <div class="select-wrapper" style="position: relative;">
                                    <select name="new_status" class="order-status-select"
                                            onchange="updateOrderStatus(this, '<?= $order['order_id'] ?>')"
                                            data-original-value="<?= htmlspecialchars($status) ?>"
                                            style="height: 32px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 0.75rem; font-weight: 700; padding: 0 10px; background: #f8fafc; cursor: pointer;">
                                        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>Paid</option>
                                        <option value="dispatched" <?= $status === 'dispatched' ? 'selected' : '' ?>>Dispatched</option>
                                        <option value="canceled" <?= $status === 'canceled' ? 'selected' : '' ?>>Canceled</option>
                                        <option value="finalized" <?= $status === 'finalized' ? 'selected' : '' ?>>Finalized</option>
                                    </select>
                                </div>
                            </div>
                        </td>
                        <td style="padding: 20px 24px; text-align: right;">
                            <a href="checkout.php?customer_id=<?= urlencode($order['customer_id']) ?>&order_id=<?= urlencode($order['order_id']) ?>"
                               class="btn-order-view"
                               style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; background: #f1f5f9; color: #475569; border-radius: 10px; text-decoration: none; font-weight: 800; font-size: 0.85rem; transition: all 0.2s;">
                                <span>Details</span>
                                <i style="font-style: normal; font-size: 1.1rem; line-height: 1;">→</i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="padding: 60px; text-align: center; color: #94a3b8; font-weight: 600;">No batches found in this category.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Transfer Order Modal -->
<div id="transferOrderModal" class="modal-overlay no-print" onclick="if(event.target === this) closeTransferModal()" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); backdrop-filter:blur(4px); z-index:1000; align-items:center; justify-content:center;">
    <div style="background:white; border-radius:20px; width:90%; max-width:400px; padding:25px; box-shadow:var(--shadow-lg);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
            <h3 style="font-weight: 800; font-size: 1.15rem;">⇄ Transfer Order</h3>
            <button type="button" onclick="closeTransferModal()" style="background:none; border:none; cursor:pointer; font-size:1.5rem; opacity:0.5;">&times;</button>
        </div>
        <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 20px;">Move this batch to a different customer account.</p>

        <form onsubmit="transferOrder(event)">
            <input type="hidden" name="action" value="transfer_order">
            <input type="hidden" name="order_id" id="transfer_order_id">

            <div class="form-group" style="margin-bottom: 20px;">
                <label for="transfer_new_customer_id" style="display:block; font-size:0.7rem; font-weight:800; text-transform:uppercase; margin-bottom:5px; color:var(--text-secondary);">Select Target Customer</label>
                <select name="new_customer_id" id="transfer_new_customer_id" required style="width:100%; height:44px; border-radius:10px; border:1px solid var(--border-color); padding:0 10px; font-weight:600;">
                    <?php foreach($all_customers as $c): ?>
                        <option value="<?= htmlspecialchars($c['customer_id']) ?>">
                            <?= htmlspecialchars($c['company_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn-main" style="width: 100%; border:none; cursor:pointer; height: 48px; background: var(--accent-color); color: white; border-radius: 12px; font-weight: 800;">
                Confirm Transfer
            </button>
        </form>
    </div>
</div>

<script>
    function openTransferModal(orderId, currentCustId) {
        document.getElementById('transfer_order_id').value = orderId;
        document.getElementById('transfer_new_customer_id').value = currentCustId;
        document.getElementById('transferOrderModal').style.display = 'flex';
    }
    function closeTransferModal() {
        document.getElementById('transferOrderModal').style.display = 'none';
    }
</script>

