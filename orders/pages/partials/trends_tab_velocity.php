<?php
/**
 * Tab 1: Demand Velocity Partial (Best-selling Models & Volume Share)
 */
?>
<!-- Tab 1: Demand Velocity (Best-selling Laptops) -->
<div id="tab-velocity" class="tab-content active">
    <div class="trends-grid" style="display: flex; flex-direction: column;">

        <!-- Interactive Table -->
        <div class="trend-card">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <h2 style="font-weight: 800; font-size: 1.1rem; margin: 0; display: flex; align-items: center; gap: 8px;">
                    🥇 Table
                </h2>
                <div style="display: flex; gap: 15px; align-items: center;">
                    <label for="inStockOnly" style="font-size: 0.8rem; font-weight: 600; display: flex; align-items: center; gap: 4px; cursor: pointer; color: var(--text-main);">
                        <input type="checkbox" id="inStockOnly" class="in-stock-only-checkbox" onchange="filterActiveTable()"> In Stock Only
                    </label>
                </div>
            </div>

            <div class="scroll-hint">↔️ Swipe horizontally to view all columns</div>
            <div class="trends-table-container">
                <table class="trends-table" id="table-velocity" style="width: max-content; margin-right: 0;margin-left:0">
                    <thead>
                        <tr>
                            <th onclick="sortTable('table-velocity', 0, 'num')">
                                <span class="rank-header">Rank</span>
                                <span class="buyer-header" style="display: none;">Customer</span>
                            </th>
                            <th onclick="sortTable('table-velocity', 1, 'str')">Brand</th>
                            <th onclick="sortTable('table-velocity', 2, 'str')">Model</th>
                            <th onclick="sortTable('table-velocity', 3, 'str')">Details</th>
                            <th onclick="sortTable('table-velocity', 4, 'date')">
                                <span class="stock-header">Latest Sold</span>
                                <span class="order-header" style="display: none;">Customer Order</span>
                            </th>
                            <th onclick="sortTable('table-velocity', 5, 'num')">Avg Price</th>
                            <th onclick="sortTable('table-velocity', 6, 'num')" class="sort-desc">Units Sold</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($velocity as $idx => $item): ?>
                            <?php
                                $search_blob = strtolower(
                                    $item['brand'] . ' ' .
                                    $item['model'] . ' ' .
                                    ($item['series'] ?? '') . ' ' .
                                    ($item['cpu'] ?? '') . ' ' .
                                    ($item['description'] ?? '') . ' ' .
                                    ($item['notes'] ?? '') . ' ' .
                                    $item['avg_price'] . ' ' .
                                    ($item['buyer_names'] ?? '') . ' ' .
                                    ($item['order_ids'] ?? '')
                                );
                                $in_stock = $item['in_stock'] ?? 0;
                                $incoming = $item['incoming_stock'] ?? 0;
                                $unique_dates = [];
                                $first_date = '';
                                if (!empty($item['order_ids'])) {
                                    $ords = explode(',', $item['order_ids']);
                                    foreach ($ords as $ord) {
                                        $parts = explode('|', trim($ord));
                                        $o_date = $parts[1] ?? '';
                                        if ($o_date && !in_array($o_date, $unique_dates)) {
                                            $unique_dates[] = $o_date;
                                        }
                                    }
                                }
                                rsort($unique_dates);
                                $first_date = $unique_dates[0] ?? '';
                            ?>
                            <tr data-search="<?= htmlspecialchars($search_blob) ?>" data-instock="<?= $in_stock ?>" data-brand="<?= htmlspecialchars($item['brand'] ?? '') ?>" data-model="<?= htmlspecialchars($item['model'] ?? '') ?>" data-series="<?= htmlspecialchars($item['series'] ?? '') ?>" data-cpu="<?= htmlspecialchars($item['cpu'] ?? '') ?>">
                                <td>
                                    <span class="rank-cell" style="font-weight: 900; color: var(--accent-color);">#<?= $idx + 1 ?></span>
                                    <span class="buyer-cell" style="display: none; font-size: 0.8rem; font-weight: 700; color: var(--accent-color);"><?= htmlspecialchars($item['buyer_names'] ?: '—') ?></span>
                                </td>
                                <td><strong><?= htmlspecialchars($item['brand']) ?></strong></td>
                                <td><?= htmlspecialchars($item['model']) ?></td>
                                <td>
                                    <div style="font-size: 0.8rem; color: var(--text-secondary);">
                                        <?= htmlspecialchars($item['series'] ?? '') ?>
                                        <?= !empty($item['cpu']) ? ' • ' . htmlspecialchars($item['cpu']) : '' ?>
                                        <?= !empty($item['description']) ? ' • ' . htmlspecialchars($item['description']) : '' ?>
                                        <?= !empty($item['notes']) ? ' • ' . htmlspecialchars($item['notes']) : '' ?>
                                    </div>
                                </td>
                                <td data-sort-val="<?= htmlspecialchars($first_date) ?>">
                                    <div class="stock-cell">
                                        <?php
                                        if (!empty($first_date)) {
                                            $current_year = date('Y');
                                            $date_parts = explode('-', $first_date);
                                            $display_date = $first_date;
                                            if (count($date_parts) === 3) {
                                                if ($date_parts[0] === $current_year) {
                                                    $display_date = $date_parts[1] . '-' . $date_parts[2];
                                                }
                                            }
                                            echo htmlspecialchars($display_date);
                                        } else {
                                            echo '—';
                                        }
                                        ?>
                                    </div>
                                    <div class="order-cell" style="display: none; font-size: 0.8rem; font-family: monospace;">
                                        <?php
                                        if (!empty($item['order_ids'])) {
                                            $ords = explode(',', $item['order_ids']);
                                            $rendered = [];
                                            $current_year = date('Y');
                                            foreach ($ords as $ord) {
                                                $parts = explode('|', trim($ord));
                                                $o_id = $parts[0] ?? '';
                                                $o_date = $parts[1] ?? '';
                                                if ($o_id) {
                                                    $display_date = '';
                                                    if ($o_date) {
                                                        $date_parts = explode('-', $o_date);
                                                        $display_date = $o_date;
                                                        if (count($date_parts) === 3) {
                                                            if ($date_parts[0] === $current_year) {
                                                                $display_date = $date_parts[1] . '-' . $date_parts[2];
                                                            }
                                                        }
                                                        $display_date = ' <span style="font-size: 0.7rem; color: var(--text-secondary); font-family: var(--font-main);">(' . htmlspecialchars($display_date) . ')</span>';
                                                    }
                                                    $rendered[] = '<span><a href="#" onclick="openOrderPreviewModal(event, \'' . htmlspecialchars($o_id) . '\')" class="order-preview-link"><code>' . htmlspecialchars($o_id) . '</code></a>' . $display_date . '</span>';
                                                }
                                            }
                                            echo implode(', ', $rendered);
                                        } else {
                                            echo '—';
                                        }
                                        ?>
                                    </div>
                                </td>
                                <td data-sort-val="<?= $item['avg_price'] ?>">$<?= number_format($item['avg_price'], 2) ?></td>
                                <td data-sort-val="<?= $item['total_qty'] ?>"><span class="qty-chip" style="box-shadow: none; font-size: 0.75rem; padding: 4px 10px;"><?= $item['total_qty'] ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- CSS Bar Chart Visualization -->
        <div class="trend-card">
            <h2 style="font-weight: 800; font-size: 1.1rem; margin-top: 0;">📊 Volume Share</h2>
            <?php
            $chart_velocity = array_slice($velocity, 0, 10);
            $max_qty = count($chart_velocity) > 0 ? max(array_column($chart_velocity, 'total_qty')) : 1;
            ?>
            <div class="chart-placeholder" style="margin-top: 10px;">
                <?php foreach ($chart_velocity as $item):
                    $height = ($item['total_qty'] / $max_qty) * 100;
                ?>
                    <div class="bar-container">
                        <div class="chart-bar" style="height: <?= $height ?>%;" title="<?= $item['total_qty'] ?> units"></div>
                        <div class="bar-label" title="<?= htmlspecialchars($item['model']) ?>"><?= htmlspecialchars($item['model']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
