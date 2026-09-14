<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $conn = Database::customers();

    // 1. Robust Schema Migration for Leads
    $existing_cols = $conn->query("PRAGMA table_info(customers)")->fetchAll(PDO::FETCH_ASSOC);
    $col_names = array_column($existing_cols, 'name');

    $required_cols = [
        'account_status' => "TEXT DEFAULT 'Lead'",
        'lead_source'    => "TEXT DEFAULT ''",
        'interest'       => "TEXT DEFAULT ''",
        'contact_method'  => "TEXT DEFAULT ''",
        'callback_date'   => "TEXT DEFAULT ''",
        'message_date'    => "TEXT DEFAULT ''"
    ];

    foreach ($required_cols as $col => $definition) {
        if (!in_array($col, $col_names)) {
            $conn->exec("ALTER TABLE customers ADD COLUMN $col $definition");
        }
    }

    // 2. Interaction Logs table (Centralized check)
    $conn->exec("CREATE TABLE IF NOT EXISTS interaction_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        customer_id TEXT NOT NULL,
        contact_date TEXT,
        method TEXT,
        note TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Performance Indexing (Phase 1)
    $conn->exec("CREATE INDEX IF NOT EXISTS idx_interactions_cid ON interaction_logs(customer_id)");

} catch(Exception $e) {
    // Basic error reporting for schema
}

// 3. Robust Data Fetching with Fallback
$all_leads = [];
try {
    // Attempt to attach orders for calculated balances
    Database::attach($conn, 'orders', 'orders_db');

    // Check if the necessary tables exist in the attached DB before querying
    $table_check = $conn->query("SELECT name FROM orders_db.sqlite_master WHERE type='table' AND name='orders'")->fetch();

    if ($table_check) {
        $sql = "
            SELECT c.*,
                (SELECT created_at FROM orders_db.orders o WHERE o.customer_id = c.customer_id ORDER BY o.created_at DESC LIMIT 1) as last_purchase,
                (SELECT order_id FROM orders_db.orders o WHERE o.customer_id = c.customer_id ORDER BY o.created_at DESC LIMIT 1) as last_order_id,
                (SELECT SUM(i.quantity * i.unit_price) FROM orders_db.items i WHERE i.customer_id = c.customer_id) as total_balance
            FROM customers c
            ORDER BY c.callback_date ASC, c.created_at DESC
        ";
    } else {
        throw new Exception("Orders table not found in attached DB");
    }
} catch (Exception $e) {
    // Fallback Query if attachment or cross-db query fails
    $sql = "SELECT *, NULL as last_purchase, NULL as last_order_id, 0 as total_balance FROM customers ORDER BY created_at DESC";
}

try {
    $stmt = $conn->query($sql);
    $all_leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $all_leads = [];
}

// 4. Filter "Call Today" tasks
$today = date('Y-m-d');
$call_today = array_filter($all_leads, function($l) use ($today) {
    return !empty($l['callback_date']) && $l['callback_date'] <= $today && strtolower($l['account_status'] ?? '') !== 'lost' && strtolower($l['account_status'] ?? '') !== 'inactive';
});

// --- LIVEWIRE-STYLE AJAX VIEW HANDLER ---
if (UI::is_ajax()) {
    if (ob_get_level() > 0) ob_clean();

    // 1. Render priority cards section HTML
    ob_start();
    if (count($call_today) > 0) {
        ?>
        <section class="tasks-section" style="margin-bottom: 35px; background: #fff1f2; border: 1px solid #fecdd3; border-radius: 20px; padding: 25px;">
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:20px;">
                <span style="font-size:1.5rem;">☎️</span>
                <h2 style="margin:0; font-size:1.1rem; font-weight:900; color:#9f1239; text-transform:uppercase; letter-spacing:0.05em;">Priority Follow-ups Today</h2>
            </div>
            <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap:15px;">
                <?php foreach($call_today as $task): ?>
                <div class="task-card" onclick='openLeadModal(<?= json_encode($task, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' style="background:white; padding:15px; border-radius:15px; border:1px solid #fecdd3; cursor:pointer; transition:all 0.2s; box-shadow:0 4px 6px -1px rgba(159, 18, 57, 0.05);">
                    <div style="font-weight:800; color:#1e293b;"><?= htmlspecialchars($task['company_name']) ?></div>
                    <div style="font-size:0.75rem; color:#be123c; font-weight:700; margin-top:4px;">📅 Scheduled: <?= htmlspecialchars($task['callback_date']) ?></div>
                    <div style="font-size:0.8rem; color:#64748b; margin-top:8px; line-height:1.4; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;">
                        <?= htmlspecialchars($task['internal_notes'] ?: 'No notes recorded.') ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
    }
    $priority_html = ob_get_clean();

    // 2. Render table rows HTML
    ob_start();
    if (count($all_leads) > 0) {
        foreach ($all_leads as $lead) {
            $status = strtolower($lead['account_status'] ?: 'lead');
            $status_class = "status-" . ($status === 'active customer' ? 'active' : ($status === 'lead' ? 'pending' : ($status === 'lost' ? 'canceled' : 'idle')));
            $search_blob = strtolower($lead['company_name'] . " " . $status . " " . $lead['lead_source'] . " " . $lead['interest'] . " " . $lead['customer_id']);
            ?>
            <tr class="lead-row" data-id="<?= htmlspecialchars($lead['customer_id']) ?>" data-search="<?= htmlspecialchars($search_blob) ?>" data-status="<?= $status ?>" style="border-bottom: 1px solid #f1f5f9; transition: background 0.2s;">
                <td style="padding: 16px;">
                    <div style="font-weight: 800; color: var(--text-main); font-size: 0.95rem;"><?= htmlspecialchars($lead['company_name']) ?></div>
                    <div style="font-size: 0.7rem; color: #94a3b8; font-family: monospace; margin-top: 2px;"><?= htmlspecialchars($lead['customer_id']) ?></div>
                </td>
                <td style="padding: 16px;">
                    <span class="order-badge <?= $status_class ?>" style="min-width: 80px; text-align: center; font-size:0.65rem;">
                        <?= htmlspecialchars($lead['account_status'] ?: 'Lead') ?>
                    </span>
                </td>
                <td style="padding: 16px; font-size: 0.8rem; font-weight:600; color: #64748b;">
                    <?= htmlspecialchars($lead['lead_source'] ?: '-') ?>
                </td>
                <td style="padding: 16px; font-size: 0.8rem; color: #64748b;">
                    <?= htmlspecialchars($lead['interest'] ?: '-') ?>
                </td>
                <td style="padding: 16px;">
                    <?php if ($lead['last_order_id']): ?>
                        <a href="checkout.php?customer_id=<?= urlencode($lead['customer_id']) ?>&order_id=<?= urlencode($lead['last_order_id']) ?>" style="text-decoration: none;">
                            <div style="font-weight: 700; color: var(--accent-color); font-size:0.85rem; font-family:monospace;"><?= htmlspecialchars($lead['last_order_id']) ?></div>
                            <div style="font-size: 0.7rem; color: #94a3b8; margin-top: 2px;"><?= date('M d, Y', strtotime($lead['last_purchase'])) ?></div>
                        </a>
                    <?php else: ?>
                        <span style="color:#cbd5e1;">-</span>
                    <?php endif; ?>
                </td>
                <td style="padding: 16px; font-weight: 800; color: var(--text-main); font-size:0.9rem;">
                    <?= isset($lead['total_balance']) && $lead['total_balance'] > 0 ? '$' . number_format($lead['total_balance'], 2) : '<span style="color:#cbd5e1;">$0.00</span>' ?>
                </td>
                <td style="padding: 16px;">
                    <div style="font-size: 0.85rem; font-weight: 700; color: #475569;">
                        <?= $lead['message_date'] ? date('M d, Y', strtotime($lead['message_date'])) : '-' ?>
                    </div>
                    <div style="font-size: 0.7rem; color: #94a3b8; font-weight:800; text-transform:uppercase; margin-top: 2px;"><?= htmlspecialchars($lead['contact_method'] ?: '') ?></div>
                </td>
                <td style="padding: 16px;">
                    <?php if ($lead['callback_date']):
                        $is_urgent = ($lead['callback_date'] <= date('Y-m-d'));
                    ?>
                        <div style="font-size: 0.85rem; font-weight: 800; color: <?= $is_urgent ? '#be123c' : '#64748b' ?>;">
                            <?= date('M d, Y', strtotime($lead['callback_date'])) ?>
                        </div>
                    <?php else: ?>
                        <span style="color:#cbd5e1;">-</span>
                    <?php endif; ?>
                </td>
                <td style="padding: 16px; max-width: 200px;">
                    <div style="font-size: 0.75rem; color: #64748b; line-height: 1.4; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;">
                        <?= htmlspecialchars($lead['internal_notes'] ?: '-') ?>
                    </div>
                </td>
                <td style="padding: 16px; text-align: right;">
                    <button type="button" class="btn-order-view"
                            onclick='openLeadModal(<?= json_encode($lead, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                            style="padding: 8px 12px; font-size: 0.75rem;">
                        <span>Edit Log</span>
                        📝
                    </button>
                </td>
            </tr>
            <?php
        }
    } else {
        echo '<tr><td colspan="10" style="padding: 60px; text-align: center; color: #94a3b8; font-weight: 600;">No accounts found.</td></tr>';
    }
    $table_html = ob_get_clean();

    header('Content-Type: application/json');
    echo json_encode([
        'priority-section-container' => $priority_html,
        'leads-list' => $table_html
    ]);
    exit();
}
?>

<div class="orders-container">

    <div id="priority-section-container">
        <?php if (count($call_today) > 0): ?>
        <!-- Urgent Follow-ups Section -->
        <section class="tasks-section" style="margin-bottom: 35px; background: #fff1f2; border: 1px solid #fecdd3; border-radius: 20px; padding: 25px;">
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:20px;">
                <span style="font-size:1.5rem;">☎️</span>
                <h2 style="margin:0; font-size:1.1rem; font-weight:900; color:#9f1239; text-transform:uppercase; letter-spacing:0.05em;">Priority Follow-ups Today</h2>
            </div>
            <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap:15px;">
                <?php foreach($call_today as $task): ?>
                <div class="task-card" onclick='openLeadModal(<?= json_encode($task, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' style="background:white; padding:15px; border-radius:15px; border:1px solid #fecdd3; cursor:pointer; transition:all 0.2s; box-shadow:0 4px 6px -1px rgba(159, 18, 57, 0.05);">
                    <div style="font-weight:800; color:#1e293b;"><?= htmlspecialchars($task['company_name']) ?></div>
                    <div style="font-size:0.75rem; color:#be123c; font-weight:700; margin-top:4px;">📅 Scheduled: <?= htmlspecialchars($task['callback_date']) ?></div>
                    <div style="font-size:0.8rem; color:#64748b; margin-top:8px; line-height:1.4; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;">
                        <?= htmlspecialchars($task['internal_notes'] ?: 'No notes recorded.') ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
    </div>

    <!-- Live Search & Filters -->
    <div class="leads-controls-bar">
        <div class="orders-search-wrapper" style="flex:1; margin:0;">
            <i class="search-icon">🔍</i>
            <input type="text" id="lead-search" placeholder="Search by Company, Lead Source, or Status..." aria-label="Search leads" onkeyup="filterLeads()">
        </div>
        <div class="leads-actions-group">
            <button onclick="openAddLeadModal()" class="btn-main" style="margin:0; background:var(--accent-color); color:white; white-space:nowrap; height:42px; padding:0 20px; font-size:0.85rem; border:none; box-shadow:var(--shadow-sm);">
                <span>+ Add New Lead</span>
            </button>
            <div class="orders-tabs leads-tabs-container">
                <a href="#" class="orders-tab-link active" onclick="filterByStatus('all')">All Accounts</a>
                <a href="#" class="orders-tab-link" onclick="filterByStatus('lead')">Leads</a>
                <a href="#" class="orders-tab-link" onclick="filterByStatus('active')">Customers</a>
            </div>
        </div>
    </div>

    <div class="table-container" style="background: white; border-radius: 20px; border: 1px solid var(--border-color); overflow: hidden; box-shadow: var(--shadow-sm); overflow-x: auto;">
        <table class="orders-table" style="width: 100%; border-collapse: collapse; text-align: left; min-width: 1400px;">
            <thead>
                <tr style="background: #1e293b !important;">
                    <th style="background: #1e293b !important; color: white !important; padding: 16px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; border:none;">Customer / Lead</th>
                    <th style="background: #1e293b !important; color: white !important; padding: 16px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; border:none;">Status</th>
                    <th style="background: #1e293b !important; color: white !important; padding: 16px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; border:none;">Source</th>
                    <th style="background: #1e293b !important; color: white !important; padding: 16px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; border:none;">Interest</th>
                    <th style="background: #1e293b !important; color: white !important; padding: 16px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; border:none;">Last Order</th>
                    <th style="background: #1e293b !important; color: white !important; padding: 16px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; border:none;">Balance</th>
                    <th style="background: #1e293b !important; color: white !important; padding: 16px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; border:none;">Last Contact</th>
                    <th style="background: #1e293b !important; color: white !important; padding: 16px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; border:none;">Next Call</th>
                    <th style="background: #1e293b !important; color: white !important; padding: 16px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; border:none;">Notes</th>
                    <th style="background: #1e293b !important; color: white !important; padding: 16px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; text-align: right; border:none;">Actions</th>
                </tr>
            </thead>
            <tbody id="leads-list">
                <?php if (count($all_leads) > 0): ?>
                    <?php foreach ($all_leads as $lead):
                        $status = strtolower($lead['account_status'] ?: 'lead');
                        $status_class = "status-" . ($status === 'active customer' ? 'active' : ($status === 'lead' ? 'pending' : ($status === 'lost' ? 'canceled' : 'idle')));
                        $search_blob = strtolower($lead['company_name'] . " " . $status . " " . $lead['lead_source'] . " " . $lead['interest'] . " " . $lead['customer_id']);
                    ?>
                    <tr class="lead-row" data-id="<?= htmlspecialchars($lead['customer_id']) ?>" data-search="<?= htmlspecialchars($search_blob) ?>" data-status="<?= $status ?>" style="border-bottom: 1px solid #f1f5f9; transition: background 0.2s;">
                        <td style="padding: 16px;">
                            <div style="font-weight: 800; color: var(--text-main); font-size: 0.95rem;"><?= htmlspecialchars($lead['company_name']) ?></div>
                            <div style="font-size: 0.7rem; color: #94a3b8; font-family: monospace; margin-top: 2px;"><?= htmlspecialchars($lead['customer_id']) ?></div>
                        </td>
                        <td style="padding: 16px;">
                            <span class="order-badge <?= $status_class ?>" style="min-width: 80px; text-align: center; font-size:0.65rem;">
                                <?= htmlspecialchars($lead['account_status'] ?: 'Lead') ?>
                            </span>
                        </td>
                        <td style="padding: 16px; font-size: 0.8rem; font-weight:600; color: #64748b;">
                            <?= htmlspecialchars($lead['lead_source'] ?: '-') ?>
                        </td>
                        <td style="padding: 16px; font-size: 0.8rem; color: #64748b;">
                            <?= htmlspecialchars($lead['interest'] ?: '-') ?>
                        </td>
                        <td style="padding: 16px;">
                            <?php if ($lead['last_order_id']): ?>
                                <a href="checkout.php?customer_id=<?= urlencode($lead['customer_id']) ?>&order_id=<?= urlencode($lead['last_order_id']) ?>" style="text-decoration: none;">
                                    <div style="font-weight: 700; color: var(--accent-color); font-size:0.85rem; font-family:monospace;"><?= htmlspecialchars($lead['last_order_id']) ?></div>
                                    <div style="font-size: 0.7rem; color: #94a3b8; margin-top: 2px;"><?= date('M d, Y', strtotime($lead['last_purchase'])) ?></div>
                                </a>
                            <?php else: ?>
                                <span style="color:#cbd5e1;">-</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 16px; font-weight: 800; color: var(--text-main); font-size:0.9rem;">
                            <?= isset($lead['total_balance']) && $lead['total_balance'] > 0 ? '$' . number_format($lead['total_balance'], 2) : '<span style="color:#cbd5e1;">$0.00</span>' ?>
                        </td>
                        <td style="padding: 16px;">
                            <div style="font-size: 0.85rem; font-weight: 700; color: #475569;">
                                <?= $lead['message_date'] ? date('M d, Y', strtotime($lead['message_date'])) : '-' ?>
                            </div>
                            <div style="font-size: 0.7rem; color: #94a3b8; font-weight:800; text-transform:uppercase; margin-top: 2px;"><?= htmlspecialchars($lead['contact_method'] ?: '') ?></div>
                        </td>
                        <td style="padding: 16px;">
                            <?php if ($lead['callback_date']):
                                $is_urgent = ($lead['callback_date'] <= date('Y-m-d'));
                            ?>
                                <div style="font-size: 0.85rem; font-weight: 800; color: <?= $is_urgent ? '#be123c' : '#64748b' ?>;">
                                    <?= date('M d, Y', strtotime($lead['callback_date'])) ?>
                                </div>
                            <?php else: ?>
                                <span style="color:#cbd5e1;">-</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 16px; max-width: 200px;">
                            <div style="font-size: 0.75rem; color: #64748b; line-height: 1.4; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;">
                                <?= htmlspecialchars($lead['internal_notes'] ?: '-') ?>
                            </div>
                        </td>
                        <td style="padding: 16px; text-align: right;">
                            <button type="button" class="btn-order-view"
                                    onclick='openLeadModal(<?= json_encode($lead, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                                    style="padding: 8px 12px; font-size: 0.75rem;">
                                <span>Edit Log</span>
                                📝
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" style="padding: 60px; text-align: center; color: #94a3b8; font-weight: 600;">No accounts found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- CRM Interaction & Edit Modal -->
<div id="leadModal" class="modal-overlay" onclick="if(event.target === this) closeLeadModal()" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); backdrop-filter:blur(4px); z-index:1000; align-items:center; justify-content:center;">
    <div style="background:white; border-radius:24px; width:95%; max-width:1000px; padding:0; box-shadow:var(--shadow-lg); max-height: 90vh; overflow:hidden; display:flex;">

        <!-- Left Side: Lead Profile & Edit -->
        <div style="flex: 1.2; padding: 40px; border-right: 1px solid #f1f5f9; overflow-y: auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 30px;">
                <div>
                    <h2 style="font-weight: 900; font-size: 1.5rem; margin:0; color:var(--text-main);" id="modal-company-name">Company Name</h2>
                    <a id="modal-customer-link" href="#" style="text-decoration:none; display:inline-block;" title="View global batch history">
                        <span id="modal-customer-id" style="font-size: 0.85rem; color: var(--accent-color); font-family: monospace; font-weight:700; cursor:pointer; border-bottom: 1px dashed var(--accent-color);"></span>
                    </a>
                </div>
            </div>

            <form id="lead-form" onsubmit="saveLead(event)">
                <?= UI::csrf_field() ?>
                <input type="hidden" id="lead_customer_id" name="customer_id">

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label for="lead_status" style="font-size:0.7rem; font-weight:800; text-transform:uppercase; color:var(--text-secondary);">Account Status</label>
                        <select id="lead_status" name="account_status" required>
                            <option value="Lead">Lead</option>
                            <option value="Active Customer">Active Customer</option>
                            <option value="Inactive">Inactive</option>
                            <option value="Lost">Lost</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="lead_source" style="font-size:0.7rem; font-weight:800; text-transform:uppercase; color:var(--text-secondary);">Lead Source</label>
                        <input type="text" id="lead_source" name="lead_source" placeholder="Website, Referral, etc.">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="lead_interest" style="font-size:0.7rem; font-weight:800; text-transform:uppercase; color:var(--text-secondary);">Core Interest / Needs</label>
                    <input type="text" id="lead_interest" name="interest" placeholder="e.g. Bulk Laptops, Gaming PCs">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label for="lead_message_date" style="font-size:0.7rem; font-weight:800; text-transform:uppercase; color:var(--text-secondary);">Last Contact Date</label>
                        <input type="date" id="lead_message_date" name="message_date">
                    </div>
                    <div class="form-group">
                        <label for="lead_method" style="font-size:0.7rem; font-weight:800; text-transform:uppercase; color:var(--text-secondary);">Contact Method</label>
                        <input type="text" id="lead_method" name="contact_method" placeholder="Email, Phone, WhatsApp...">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="lead_callback_date" style="font-size:0.7rem; font-weight:800; text-transform:uppercase; color:var(--text-secondary);">Next Follow-Up Call</label>
                    <input type="date" id="lead_callback_date" name="callback_date" style="border-color:var(--accent-color); border-width:2px;">
                </div>

                <div class="form-group" style="margin-bottom: 30px;">
                    <label for="lead_notes" style="font-size:0.7rem; font-weight:800; text-transform:uppercase; color:var(--text-secondary);">General Account Notes</label>
                    <textarea id="lead_notes" name="internal_notes" style="width:100%; min-height:80px;"></textarea>
                </div>

                <div style="background: #f8fafc; padding: 20px; border-radius: 16px; margin-bottom: 30px; border: 1px solid #e2e8f0;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                        <label for="lead_notes" style="font-size:0.7rem; font-weight:900; text-transform:uppercase; color:var(--accent-color);">🆕 Log New Interaction</label>
                        <div style="display:flex; gap:8px;">
                            <button type="button" onclick="quickLog('Phone')" class="btn-order-view" style="font-size:0.6rem; padding:4px 8px; background:white; border:1px solid #cbd5e1;">📞 Call</button>
                            <button type="button" onclick="quickLog('Email')" class="btn-order-view" style="font-size:0.6rem; padding:4px 8px; background:white; border:1px solid #cbd5e1;">📧 Email</button>
                            <button type="button" onclick="quickLog('Message')" class="btn-order-view" style="font-size:0.6rem; padding:4px 8px; background:white; border:1px solid #cbd5e1;">💬 Msg</button>
                        </div>
                    </div>
                    <textarea id="new_interaction_note" name="new_interaction_note" placeholder="Write a summary of your recent call/message here... (Historical)" style="width:100%; min-height:100px; border-color:#cbd5e1;"></textarea>
                </div>

                <button type="submit" id="btn-save-lead" class="btn-main" style="width: 100%; height: 54px;">
                    💾 Save CRM Details
                </button>
            </form>
        </div>

        <!-- Right Side: Interaction History -->
        <div style="flex: 0.8; background: #f8fafc; display: flex; flex-direction: column;">
            <div style="padding: 30px 40px; border-bottom: 1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
                <h3 style="font-weight: 800; font-size: 1rem; margin:0; text-transform:uppercase; letter-spacing:0.05em; color:#64748b;">📜 Timeline Log</h3>
                <button type="button" onclick="closeLeadModal()" style="background:none; border:none; cursor:pointer; font-size:1.5rem; opacity:0.3;">&times;</button>
            </div>
            <div id="interaction-history" style="flex:1; overflow-y:auto; padding: 20px 40px;">
                <!-- Populated via JS -->
                <div style="padding:40px; text-align:center; opacity:0.3;">No history found.</div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Add Lead Modal -->
<div id="addLeadModal" class="modal-overlay" onclick="if(event.target === this) closeAddLeadModal()" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); backdrop-filter:blur(4px); z-index:1001; align-items:center; justify-content:center;">
    <div style="background:white; border-radius:24px; width:95%; max-width:600px; padding:40px; box-shadow:var(--shadow-lg);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 30px;">
            <h2 style="font-weight: 900; font-size: 1.5rem; margin:0; color:var(--text-main);">✨ Quick Register Lead</h2>
            <button type="button" onclick="closeAddLeadModal()" style="background:none; border:none; cursor:pointer; font-size:1.5rem; opacity:0.3;">&times;</button>
        </div>

        <form onsubmit="quickAddLead(event)">
            <?= UI::csrf_field() ?>
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="font-size:0.7rem; font-weight:800; text-transform:uppercase; color:var(--text-secondary);">Company Name</label>
                <input type="text" name="company_name" placeholder="e.g. Acme Corp" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div class="form-group">
                    <label style="font-size:0.7rem; font-weight:800; text-transform:uppercase; color:var(--text-secondary);">Contact Person</label>
                    <input type="text" name="contact_person" placeholder="Name">
                </div>
                <div class="form-group">
                    <label for="lead_source" style="font-size:0.7rem; font-weight:800; text-transform:uppercase; color:var(--text-secondary);">Lead Source</label>
                    <input type="text" name="lead_source" placeholder="Referral, Web, etc.">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div class="form-group">
                    <label style="font-size:0.7rem; font-weight:800; text-transform:uppercase; color:var(--text-secondary);">Email</label>
                    <input type="email" name="email" placeholder="email@company.com">
                </div>
                <div class="form-group">
                    <label style="font-size:0.7rem; font-weight:800; text-transform:uppercase; color:var(--text-secondary);">Phone</label>
                    <input type="text" name="phone" placeholder="+1...">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 30px;">
                <label for="lead_interest" style="font-size:0.7rem; font-weight:800; text-transform:uppercase; color:var(--text-secondary);">Initial Interest / Notes</label>
                <textarea name="interest" placeholder="What are they looking for?" style="width:100%; min-height:80px;"></textarea>
            </div>

            <button type="submit" id="btn-quick-add" class="btn-main" style="width: 100%; height: 54px;">
                🚀 Create Lead Profile
            </button>
        </form>
    </div>
</div>

<script>
    function openAddLeadModal() {
        document.getElementById('addLeadModal').style.display = 'flex';
    }
    function closeAddLeadModal() {
        document.getElementById('addLeadModal').style.display = 'none';
    }

    async function quickAddLead(event) {
        event.preventDefault();
        const form = event.target;
        const btn = document.getElementById('btn-quick-add');
        const originalText = btn.innerText;

        try {
            btn.disabled = true;
            btn.innerText = 'Creating Account...';

            const formData = new FormData(form);
            // Flag as a lead
            formData.append('account_status', 'Lead');
            formData.append('action', 'register_customer'); // Reuse existing registration logic if possible or create new API

            const response = await fetch('api/save_lead.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.status === 'success') {
                btn.style.background = '#22c55e';
                btn.innerText = '✓ Lead Created!';
                setTimeout(() => location.reload(), 800);
            } else {
                throw new Error(data.error || 'Registration failed');
            }
        } catch (err) {
            console.error("Registration failed", err);
            alert("Failed to create lead: " + err.message);
            btn.disabled = false;
            btn.innerText = originalText;
        }
    }
</script>
