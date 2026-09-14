<?php
/**
 * Tab 2: Pricing History Partial (ASP Timeline, Valuation Trend & Historical Records)
 */
?>
<!-- Tab 2: Pricing History (Price Curves over past months) -->
<div id="tab-pricing" class="tab-content">
    <!-- Responsive grid for interactive charts -->
    <div class="trends-grid" style="margin-bottom: 20px;">
        <div class="trend-card">
            <h2 style="font-weight: 800; font-size: 1.1rem; margin-top: 0;">📉 Average Selling Price Timeline</h2>
            <div style="position: relative; height: 260px; width: 100%;">
                <canvas id="aspChart"></canvas>
            </div>
        </div>

        <div class="trend-card">
            <h2 style="font-weight: 800; font-size: 1.1rem; margin-top: 0;">📈 Monthly Valuation Trend</h2>
            <div style="position: relative; height: 260px; width: 100%;">
                <canvas id="valuationChart"></canvas>
            </div>
        </div>
    </div>

    <div class="trends-grid">
        <div class="trend-card" style="flex: 1;">
            <h2 style="font-weight: 800; font-size: 1.1rem; margin-top: 0;">📅 Pricing & Valuation History</h2>

            <div class="scroll-hint">↔️ Swipe horizontally to view all columns</div>
            <div class="trends-table-container">
                <table class="trends-table" id="table-pricing">
                    <thead>
                        <tr>
                            <th onclick="sortTable('table-pricing', 0, 'str')" class="sort-desc">Month</th>
                            <th onclick="sortTable('table-pricing', 1, 'num')">Units Moved</th>
                            <th onclick="sortTable('table-pricing', 2, 'num')">Avg Price</th>
                            <th onclick="sortTable('table-pricing', 3, 'num')">Total Valuation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($price_history as $history): ?>
                            <?php
                                $valuation = $history['total_valuation'] ?? ($history['avg_price'] * $history['total_qty']);
                                $search_blob = strtolower($history['sales_month'] . ' ' . $history['avg_price']);
                            ?>
                            <tr data-search="<?= htmlspecialchars($search_blob) ?>">
                                <td>📅 <strong><?= htmlspecialchars($history['sales_month']) ?></strong></td>
                                <td data-sort-val="<?= $history['total_qty'] ?>"><?= $history['total_qty'] ?> units</td>
                                <td data-sort-val="<?= $history['avg_price'] ?>">$<?= number_format($history['avg_price'], 2) ?></td>
                                <td data-sort-val="<?= $valuation ?>" class="stat-value">$<?= number_format($valuation, 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
