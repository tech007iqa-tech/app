<?php
/**
 * Tab 4: Customer Insights Partial (Top B2B Clients by Volume & Recency)
 */
?>
<!-- Tab 4: Customer Insights -->
<div id="tab-customers" class="tab-content">
    <div class="trends-grid">
        <div class="trend-card" style="flex: 1;">
            <h2 style="font-weight: 800; font-size: 1.1rem; margin-top: 0;">🤝 Top B2B Clients by Volume</h2>

            <div class="scroll-hint">↔️ Swipe horizontally to view all columns</div>
            <div class="trends-table-container">
                <table class="trends-table" id="table-customers">
                    <thead>
                        <tr>
                            <th onclick="sortTable('table-customers', 0, 'str')">Client Company</th>
                            <th onclick="sortTable('table-customers', 1, 'num')">Total Orders</th>
                            <th onclick="sortTable('table-customers', 2, 'num')" class="sort-desc">Units Bought</th>
                            <th onclick="sortTable('table-customers', 3, 'date')">First Purchase</th>
                            <th onclick="sortTable('table-customers', 4, 'date')">Last Purchase</th>
                            <th onclick="sortTable('table-customers', 5, 'str')">Activity Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customer_insights as $idx => $cust):
                            $last_order = new DateTime($cust['last_order_date']);
                            $now = new DateTime();
                            $days_since = $now->diff($last_order)->days;
                            $status_text = ($days_since > 60) ? "⚠️ Idle ($days_since days ago)" : "🟢 Active ($days_since days ago)";
                            $search_blob = strtolower($cust['company_name'] . ' ' . $status_text);
                        ?>
                            <tr data-search="<?= htmlspecialchars($search_blob) ?>">
                                <td><strong><?= htmlspecialchars($cust['company_name'] ?? 'Unknown Company') ?></strong></td>
                                <td data-sort-val="<?= $cust['total_orders'] ?>"><?= $cust['total_orders'] ?> orders</td>
                                <td data-sort-val="<?= $cust['total_units_bought'] ?>"><span class="qty-chip" style="box-shadow: none; font-size: 0.75rem; padding: 4px 10px;"><?= $cust['total_units_bought'] ?></span></td>
                                <td data-sort-val="<?= htmlspecialchars($cust['first_order_date']) ?>"><?= substr($cust['first_order_date'], 0, 10) ?></td>
                                <td data-sort-val="<?= htmlspecialchars($cust['last_order_date']) ?>"><?= substr($cust['last_order_date'], 0, 10) ?></td>
                                <td>
                                    <?php if ($days_since > 60): ?>
                                        <span style="font-size: 0.75rem; color: #f59e0b; font-weight: 700;"><?= $status_text ?></span>
                                    <?php else: ?>
                                        <span style="font-size: 0.75rem; color: #10b981; font-weight: 700;"><?= $status_text ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
