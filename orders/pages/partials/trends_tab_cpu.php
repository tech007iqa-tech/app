<?php
/**
 * Tab 3: CPU Generations Distribution Partial (Processor Dominance & Manufacturer Share)
 */
?>
<!-- Tab 3: CPU Generations Distribution -->
<div id="tab-cpu" class="tab-content">
    <div style="display: flex; flex-direction: column; gap: 24px;">
        <div class="trend-card" style="width: 100%;">
            <h2 style="font-weight: 800; font-size: 1.1rem; margin-top: 0;">💻 CPU Family Dominance</h2>

            <div class="scroll-hint">↔️ Swipe horizontally to view all columns</div>
            <div class="trends-table-container">
                <table class="trends-table" id="table-cpu">
                    <thead>
                        <tr style="background: #0f172a; color: white;">
                            <th onclick="sortTable('table-cpu', 0, 'str')">Processor / CPU Family</th>
                            <th onclick="sortTable('table-cpu', 1, 'num')" style="text-align: right;">Min Price ($)</th>
                            <th onclick="sortTable('table-cpu', 2, 'num')" style="text-align: right;">Max Price ($)</th>
                            <th onclick="sortTable('table-cpu', 3, 'num')" style="text-align: right;">Avg Price ($)</th>
                            <th onclick="sortTable('table-cpu', 4, 'num')" class="sort-desc" style="text-align: center;">Units Sold</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cpu_distribution as $cpu): ?>
                            <?php
                                $cpu_name = $cpu['cpu'] ? $cpu['cpu'] : 'Other Generations';
                                $min_p = $cpu['min_price'] ?? $cpu['avg_price'];
                                $max_p = $cpu['max_price'] ?? $cpu['avg_price'];
                                $search_blob = strtolower($cpu_name . ' ' . $cpu['avg_price'] . ' ' . $min_p . ' ' . $max_p);
                            ?>
                            <tr class="clickable-row" data-search="<?= htmlspecialchars($search_blob) ?>" onclick="openCpuPricingModal('<?= htmlspecialchars($cpu_name) ?>')">
                                <td>⚙️ <strong><?= htmlspecialchars($cpu_name) ?></strong></td>
                                <td data-sort-val="<?= $min_p ?>" style="text-align: right;"><span style="color: #10b981; font-weight: 600;">$<?= number_format($min_p, 2) ?></span></td>
                                <td data-sort-val="<?= $max_p ?>" style="text-align: right;"><span style="color: #3b82f6; font-weight: 600;">$<?= number_format($max_p, 2) ?></span></td>
                                <td data-sort-val="<?= $cpu['avg_price'] ?>" style="text-align: right;"><strong>$<?= number_format($cpu['avg_price'], 2) ?></strong></td>
                                <td data-sort-val="<?= $cpu['total_qty'] ?>" style="text-align: center;"><span class="qty-chip" style="box-shadow: none; font-size: 0.75rem; padding: 4px 10px;"><?= $cpu['total_qty'] ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="trend-card" style="width: 100%;">
            <h2 style="font-weight: 800; font-size: 1.1rem; margin-top: 0;">📊 CPU Manufacturer Share</h2>
            <div style="position: relative; height: 320px; width: 100%;">
                <canvas id="cpuBrandChart"></canvas>
            </div>
        </div>
    </div>
</div>
