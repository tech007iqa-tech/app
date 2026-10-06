<?php
/**
 * Tab 1: Demand Velocity Partial (Best-selling Models & Volume Share)
 */
?>
<!-- Tab 1: Demand Velocity (Best-selling Laptops) -->
<div id="tab-velocity" class="tab-content <?= ($active_tab ?? 'tab-velocity') === 'tab-velocity' ? 'active' : '' ?>">
    <div class="trends-grid" style="display: flex; flex-direction: column;">

        <!-- Interactive Table -->
        <div class="trend-card">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <h2 style="font-weight: 800; font-size: 1.1rem; margin: 0; display: flex; align-items: center; gap: 8px;">
                    🥇 Demand Velocity Table
                </h2>
                <div style="display: flex; gap: 15px; align-items: center;">
                    <label for="inStockOnly" style="font-size: 0.8rem; font-weight: 600; display: flex; align-items: center; gap: 4px; cursor: pointer; color: var(--text-main);">
                        <input type="checkbox" id="inStockOnly" class="in-stock-only-checkbox" onchange="filterActiveTable()"> In Stock Only
                    </label>
                    <button type="button" onclick="exportDemandVelocityCSV()" class="btn-main" style="padding: 6px 14px; font-size: 0.8rem; height: auto; border-radius: 20px; background: var(--accent-color); color: white; display: inline-flex; align-items: center; gap: 6px; box-shadow: none; border: none; cursor: pointer; font-weight: 700;">
                        <span>📥</span> Export Demand CSV
                    </button>
                </div>
            </div>

            <div class="scroll-hint">↔️ Swipe horizontally to view all columns</div>
            <div class="trends-table-container">
                <table class="trends-table" id="table-velocity">
                    <thead>
                        <tr>
                            <th onclick="sortTable('table-velocity', 0, 'num')">
                                <span class="rank-header">Rank</span>
                                <span class="buyer-header">Customer</span>
                            </th>
                            <th onclick="sortTable('table-velocity', 1, 'str')">Brand</th>
                            <th onclick="sortTable('table-velocity', 2, 'str')">Model</th>
                            <th onclick="sortTable('table-velocity', 3, 'num')">Avg Price</th>
                            <th onclick="sortTable('table-velocity', 4, 'str')">Details</th>
                            <th onclick="sortTable('table-velocity', 5, 'date')">
                                <span class="stock-header">Latest Sold</span>
                                <span class="order-header">Customer Order</span>
                            </th>
                            <th onclick="sortTable('table-velocity', 6, 'num')" class="sort-desc">Units Sold</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($velocity as $idx => $item): ?>
                            <?php
                                $in_stock = (int)($item['in_stock'] ?? 0);
                                $incoming = (int)($item['incoming_stock'] ?? 0);
                                $unique_dates = [];
                                $clean_order_ids = [];
                                $order_links = [];
                                $current_year = date('Y');

                                if (!empty($item['order_ids'])) {
                                    $ords = explode(',', $item['order_ids']);
                                    foreach ($ords as $ord) {
                                        $parts = explode('|', trim($ord));
                                        $o_id = $parts[0] ?? '';
                                        $o_date = $parts[1] ?? '';
                                        if ($o_date && !in_array($o_date, $unique_dates)) {
                                            $unique_dates[] = $o_date;
                                        }
                                        if ($o_id) {
                                            $clean_order_ids[] = $o_id;
                                            if (count($order_links) < 5) {
                                                $display_date_badge = '';
                                                if ($o_date) {
                                                    $d_parts = explode('-', $o_date);
                                                    $fmt_date = (count($d_parts) === 3 && $d_parts[0] === $current_year) ? ($d_parts[1] . '-' . $d_parts[2]) : $o_date;
                                                    $display_date_badge = ' <span style="font-size: 0.7rem; color: var(--text-secondary); font-family: var(--font-main);">(' . htmlspecialchars($fmt_date) . ')</span>';
                                                }
                                                $order_links[] = '<span><a href="#" onclick="openOrderPreviewModal(event, \'' . htmlspecialchars($o_id) . '\')" class="order-preview-link"><code>' . htmlspecialchars($o_id) . '</code></a>' . $display_date_badge . '</span>';
                                            }
                                        }
                                    }
                                }
                                rsort($unique_dates);
                                $first_date = $unique_dates[0] ?? '';
                                if (count($clean_order_ids) > 5) {
                                    $order_links[] = '<span style="font-size: 0.72rem; color: var(--text-secondary); font-weight: 600;">+' . (count($clean_order_ids) - 5) . ' more</span>';
                                }

                                $search_blob = strtolower(
                                    $item['brand'] . ' ' .
                                    $item['model'] . ' ' .
                                    ($item['series'] ?? '') . ' ' .
                                    ($item['cpu'] ?? '') . ' ' .
                                    ($item['description'] ?? '') . ' ' .
                                    ($item['notes'] ?? '') . ' ' .
                                    $item['avg_price'] . ' ' .
                                    ($item['buyer_names'] ?? '') . ' ' .
                                    implode(' ', array_unique($clean_order_ids))
                                );

                                $display_date = $first_date;
                                if ($first_date) {
                                    $d_parts = explode('-', $first_date);
                                    if (count($d_parts) === 3 && $d_parts[0] === $current_year) {
                                        $display_date = $d_parts[1] . '-' . $d_parts[2];
                                    }
                                }

                                $is_extra_row = ($idx >= 100);
                            ?>
                            <tr class="velocity-row <?= $is_extra_row ? 'velocity-row-extra' : '' ?>"
                                data-search="<?= htmlspecialchars($search_blob) ?>"
                                data-instock="<?= $in_stock ?>"
                                data-brand="<?= htmlspecialchars($item['brand'] ?? '') ?>"
                                data-model="<?= htmlspecialchars($item['model'] ?? '') ?>"
                                data-series="<?= htmlspecialchars($item['series'] ?? '') ?>"
                                data-cpu="<?= htmlspecialchars($item['cpu'] ?? '') ?>"
                                <?= $is_extra_row ? 'style="display: none;"' : '' ?>>
                                <td>
                                    <span class="rank-cell" style="font-weight: 900; color: var(--accent-color);">#<?= $idx + 1 ?></span>
                                    <span class="buyer-cell" style="font-size: 0.8rem; font-weight: 700; color: var(--accent-color);">
                                        <?php
                                        if (!empty($item['buyer_names'])) {
                                            $buyers = array_map('trim', explode(',', $item['buyer_names']));
                                            $buyer_links = [];
                                            foreach ($buyers as $b) {
                                                if (!empty($b)) {
                                                    $buyer_links[] = '<a href="#" onclick="openCustomerProfileModal(event, \'\', \'' . htmlspecialchars(addslashes($b), ENT_QUOTES) . '\')" class="customer-profile-link" style="color: inherit; text-decoration: underline; text-underline-offset: 2px;">' . htmlspecialchars($b) . '</a>';
                                                }
                                            }
                                            echo implode(', ', $buyer_links);
                                        } else {
                                            echo '—';
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td><strong><?= htmlspecialchars($item['brand']) ?></strong></td>
                                <td><?= htmlspecialchars($item['model']) ?></td>
                                <td data-sort-val="<?= $item['avg_price'] ?>">$<?= number_format($item['avg_price'], 2) ?></td>
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
                                        <?= htmlspecialchars($display_date ?: '—') ?>
                                    </div>
                                    <div class="order-cell" style="font-size: 0.8rem; font-family: monospace;">
                                        <?= !empty($order_links) ? implode(', ', $order_links) : '—' ?>
                                    </div>
                                </td>
                                <td data-sort-val="<?= $item['total_qty'] ?>"><span class="qty-chip" style="box-shadow: none; font-size: 0.75rem; padding: 4px 10px;"><?= $item['total_qty'] ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (count($velocity) > 100): ?>
                    <div id="velocity-load-more-container" style="display: flex; justify-content: space-between; align-items: center; padding: 14px 20px; background: var(--bg-surface-2); border-top: 1px solid var(--border-color); font-size: 0.85rem; color: var(--text-secondary); flex-wrap: wrap; gap: 10px;">
                        <span id="velocity-count-label">Showing top <strong>100</strong> of <strong><?= number_format(count($velocity)) ?></strong> laptop models by sales volume</span>
                        <button type="button" id="btn-show-all-velocity" onclick="showAllVelocityRows()" class="btn-main" style="padding: 6px 16px; font-size: 0.8rem; height: auto; border-radius: 12px; background: var(--bg-surface); color: var(--text-main); border: 1px solid var(--border-color); font-weight: 700; cursor: pointer; box-shadow: var(--shadow-sm);">
                            Show All <?= number_format(count($velocity)) ?> Models
                        </button>
                    </div>
                <?php endif; ?>
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
