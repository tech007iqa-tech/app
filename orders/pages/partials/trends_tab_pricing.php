<?php
/**
 * Tab 2: Pricing History Partial (Accounting & Financial Analytics Edition)
 * Executive summary KPIs, ASP timeline, realized valuation trends, and monthly financial ledger.
 */

// 1. Compute Financial Metrics from $price_history
$total_period_valuation = 0.0;
$total_period_units = 0;
$peak_month_name = 'N/A';
$peak_month_valuation = 0.0;

// Chronological sort for MoM analysis
$sorted_history = $price_history;
usort($sorted_history, function($a, $b) {
    return strcmp($a['sales_month'] ?? '', $b['sales_month'] ?? '');
});

$mom_growth_by_month = [];
$prev_val = null;
foreach ($sorted_history as $h) {
    $m = $h['sales_month'] ?? '';
    $val = (float)($h['total_valuation'] ?? ($h['avg_price'] * $h['total_qty']));
    $qty = (int)($h['total_qty'] ?? 0);

    $total_period_valuation += $val;
    $total_period_units += $qty;

    if ($val > $peak_month_valuation) {
        $peak_month_valuation = $val;
        $peak_month_name = $m;
    }

    if ($prev_val !== null && $prev_val > 0) {
        $growth = (($val - $prev_val) / $prev_val) * 100;
        $mom_growth_by_month[$m] = $growth;
    } else {
        $mom_growth_by_month[$m] = null;
    }
    $prev_val = $val;
}

$weighted_asp = $total_period_units > 0 ? ($total_period_valuation / $total_period_units) : 0.00;

// Latest MoM growth rate
$latest_mom_pct = null;
if (count($sorted_history) >= 2) {
    $last_month_item = end($sorted_history);
    $last_month_name = $last_month_item['sales_month'] ?? '';
    $latest_mom_pct = $mom_growth_by_month[$last_month_name] ?? null;
}
?>

<!-- Tab 2: Pricing History (Price Curves over past months) -->
<div id="tab-pricing" class="tab-content">

    <!-- Executive Accounting Summary KPI Board -->
    <div class="financial-kpi-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 24px;">
        <div class="trend-card" style="padding: 16px 20px; border-left: 4px solid #10b981;">
            <div style="font-size: 0.75rem; font-weight: 800; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em; display: flex; align-items: center; gap: 6px;">
                <span>💰</span> Gross Realized Valuation
            </div>
            <div style="font-size: 1.45rem; font-weight: 900; color: var(--text-main); margin-top: 6px;">
                $<?= number_format($total_period_valuation, 2) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 4px;">
                Settled revenue across period
            </div>
        </div>

        <div class="trend-card" style="padding: 16px 20px; border-left: 4px solid #3b82f6;">
            <div style="font-size: 0.75rem; font-weight: 800; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em; display: flex; align-items: center; gap: 6px;">
                <span>🏷️</span> Weighted Average Price (ASP)
            </div>
            <div style="font-size: 1.45rem; font-weight: 900; color: var(--text-main); margin-top: 6px;">
                $<?= number_format($weighted_asp, 2) ?> <span style="font-size: 0.85rem; font-weight: 700; color: var(--text-secondary);">/ unit</span>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 4px;">
                True weighted yield per asset
            </div>
        </div>

        <div class="trend-card" style="padding: 16px 20px; border-left: 4px solid #8b5cf6;">
            <div style="font-size: 0.75rem; font-weight: 800; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em; display: flex; align-items: center; gap: 6px;">
                <span>📦</span> Total Volume Realized
            </div>
            <div style="font-size: 1.45rem; font-weight: 900; color: var(--text-main); margin-top: 6px;">
                <?= number_format($total_period_units) ?> <span style="font-size: 0.85rem; font-weight: 700; color: var(--text-secondary);">units</span>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 4px;">
                Liquidated asset units
            </div>
        </div>

        <div class="trend-card" style="padding: 16px 20px; border-left: 4px solid #f59e0b;">
            <div style="font-size: 0.75rem; font-weight: 800; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em; display: flex; align-items: center; gap: 6px;">
                <span>🏆</span> Peak Sales Month
            </div>
            <div style="font-size: 1.45rem; font-weight: 900; color: var(--text-main); margin-top: 6px;">
                <?= htmlspecialchars($peak_month_name) ?>
            </div>
            <div style="font-size: 0.75rem; color: #f59e0b; font-weight: 700; margin-top: 4px;">
                $<?= number_format($peak_month_valuation, 2) ?> realized
            </div>
        </div>

        <?php if ($latest_mom_pct !== null): ?>
        <div class="trend-card" style="padding: 16px 20px; border-left: 4px solid <?= $latest_mom_pct >= 0 ? '#10b981' : '#ef4444' ?>;">
            <div style="font-size: 0.75rem; font-weight: 800; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em; display: flex; align-items: center; gap: 6px;">
                <span>📊</span> Latest MoM Velocity
            </div>
            <div style="font-size: 1.45rem; font-weight: 900; color: <?= $latest_mom_pct >= 0 ? '#10b981' : '#ef4444' ?>; margin-top: 6px;">
                <?= $latest_mom_pct >= 0 ? '▲ +' : '▼ ' ?><?= number_format($latest_mom_pct, 1) ?>%
            </div>
            <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 4px;">
                vs. prior month settlement
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Chart Mode Switcher & Responsive Graphs -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 10px;">
        <div>
            <span style="font-size: 0.85rem; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 6px;">
                <span>📈</span> Accounting Analytics Visualizations
            </span>
        </div>
        <div class="trends-view-toggle" style="display: inline-flex; background: var(--card-bg, rgba(255,255,255,0.06)); padding: 3px; border-radius: 24px; border: 1px solid var(--border-color, rgba(255,255,255,0.12));">
            <button type="button" id="chartViewSplitBtn" onclick="setPricingChartViewMode('split')" class="active" style="padding: 5px 14px; font-size: 0.78rem; font-weight: 700; border-radius: 20px; border: none; background: #3b82f6; color: #ffffff; cursor: pointer; transition: all 0.2s ease;">
                🔀 Split View
            </button>
            <button type="button" id="chartViewComboBtn" onclick="setPricingChartViewMode('combo')" style="padding: 5px 14px; font-size: 0.78rem; font-weight: 700; border-radius: 20px; border: none; background: transparent; color: var(--text-secondary); cursor: pointer; transition: all 0.2s ease;">
                📊 Dual-Axis Combo
            </button>
        </div>
    </div>

    <!-- 1. Split View Graphs (Side-by-Side ASP + Valuation) -->
    <div id="pricingSplitViewContainer" class="trends-grid" style="margin-bottom: 24px;">
        <div class="trend-card" style="display: flex; flex-direction: column;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                <div>
                    <h2 style="font-weight: 800; font-size: 1.1rem; margin: 0; display: flex; align-items: center; gap: 8px;">
                        📉 Average Realized Unit Price (ASP)
                    </h2>
                    <p style="font-size: 0.8rem; color: var(--text-secondary); margin: 3px 0 0 0;">
                        Monthly weighted average selling price per unit with price elasticity trend
                    </p>
                </div>
            </div>
            <div style="position: relative; height: 280px; width: 100%; flex: 1;">
                <canvas id="aspChart"></canvas>
            </div>
        </div>

        <div class="trend-card" style="display: flex; flex-direction: column;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                <div>
                    <h2 style="font-weight: 800; font-size: 1.1rem; margin: 0; display: flex; align-items: center; gap: 8px;">
                        📈 Monthly Realized Gross Valuation
                    </h2>
                    <p style="font-size: 0.8rem; color: var(--text-secondary); margin: 3px 0 0 0;">
                        Total realized sales revenue and liquidation valuation by settlement month
                    </p>
                </div>
            </div>
            <div style="position: relative; height: 280px; width: 100%; flex: 1;">
                <canvas id="valuationChart"></canvas>
            </div>
        </div>
    </div>

    <!-- 2. Dual-Axis Combo Chart View (Gross Valuation Bars + ASP Trendline) -->
    <div id="pricingComboViewContainer" class="trend-card" style="display: none; margin-bottom: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
            <div>
                <h2 style="font-weight: 800; font-size: 1.15rem; margin: 0; display: flex; align-items: center; gap: 8px;">
                    📊 Valuation & ASP Dual-Axis Performance Model
                </h2>
                <p style="font-size: 0.8rem; color: var(--text-secondary); margin: 3px 0 0 0;">
                    Gross Realized Revenue (Left Axis) correlated with Weighted Unit Price (Right Axis) over settlement months
                </p>
            </div>
            <div style="display: flex; align-items: center; gap: 14px; font-size: 0.8rem; font-weight: 700;">
                <span style="display: inline-flex; align-items: center; gap: 5px; color: #10b981;">
                    <span style="display: inline-block; width: 12px; height: 12px; background: #10b981; border-radius: 3px;"></span> Gross Valuation ($)
                </span>
                <span style="display: inline-flex; align-items: center; gap: 5px; color: #3b82f6;">
                    <span style="display: inline-block; width: 12px; height: 3px; background: #3b82f6; border-radius: 2px;"></span> Realized ASP ($/unit)
                </span>
            </div>
        </div>
        <div style="position: relative; height: 340px; width: 100%;">
            <canvas id="comboPricingChart"></canvas>
        </div>
    </div>

    <!-- Monthly Financial Valuation & Settlement Ledger -->
    <div class="trends-grid">
        <div class="trend-card" style="flex: 1;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                <div>
                    <h2 style="font-weight: 800; font-size: 1.15rem; margin: 0; display: flex; align-items: center; gap: 8px;">
                        📅 Monthly Financial Valuation & Settlement Ledger
                    </h2>
                    <p style="font-size: 0.8rem; color: var(--text-secondary); margin: 3px 0 0 0;">
                        Detailed breakdown of volume, average pricing, realized valuation, and Month-over-Month (MoM) variance.
                    </p>
                </div>
                <div>
                    <button type="button" onclick="exportFinancialLedgerCSV()" class="btn-main" style="padding: 7px 16px; font-size: 0.82rem; height: auto; border-radius: 20px; background: #10b981; color: white; display: inline-flex; align-items: center; gap: 6px; box-shadow: none; border: none; cursor: pointer; font-weight: 700;">
                        <span>📥</span> Export Ledger CSV
                    </button>
                </div>
            </div>

            <div class="scroll-hint">↔️ Swipe horizontally to view all columns</div>
            <div class="trends-table-container">
                <table class="trends-table" id="table-pricing">
                    <thead>
                        <tr>
                            <th onclick="sortTable('table-pricing', 0, 'str')" class="sort-desc" style="cursor: pointer;">
                                Month
                            </th>
                            <th onclick="sortTable('table-pricing', 1, 'num')" style="cursor: pointer;">
                                Units Moved
                            </th>
                            <th onclick="sortTable('table-pricing', 2, 'num')" style="cursor: pointer;">
                                Avg Unit Price
                            </th>
                            <th onclick="sortTable('table-pricing', 3, 'num')" style="cursor: pointer;">
                                Gross Valuation
                            </th>
                            <th onclick="sortTable('table-pricing', 4, 'num')" style="cursor: pointer;">
                                MoM Growth
                            </th>
                            <th onclick="sortTable('table-pricing', 5, 'num')" style="cursor: pointer;">
                                Share of Period
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($price_history as $history): ?>
                            <?php
                                $m_name = $history['sales_month'] ?? '';
                                $val = (float)($history['total_valuation'] ?? ($history['avg_price'] * $history['total_qty']));
                                $qty = (int)($history['total_qty'] ?? 0);
                                $asp = (float)($history['avg_price'] ?? 0);
                                $mom_growth = $mom_growth_by_month[$m_name] ?? null;
                                $share_pct = $total_period_valuation > 0 ? (($val / $total_period_valuation) * 100) : 0.0;
                                $search_blob = strtolower($m_name . ' ' . $asp . ' ' . $qty . ' ' . $val);
                            ?>
                            <tr data-search="<?= htmlspecialchars($search_blob) ?>">
                                <td data-sort-val="<?= htmlspecialchars($m_name) ?>">
                                    📅 <strong><?= htmlspecialchars($m_name) ?></strong>
                                </td>
                                <td data-sort-val="<?= $qty ?>">
                                    <span class="qty-chip" style="background: var(--bg-surface-2); color: var(--text-main); border: 1px solid var(--border-color); box-shadow: none; font-size: 0.8rem; padding: 4px 10px;">
                                        <?= number_format($qty) ?> units
                                    </span>
                                </td>
                                <td data-sort-val="<?= $asp ?>" style="font-weight: 700; color: var(--text-main);">
                                    $<?= number_format($asp, 2) ?>
                                </td>
                                <td data-sort-val="<?= $val ?>" class="stat-value" style="font-weight: 800; color: #10b981; font-size: 0.95rem;">
                                    $<?= number_format($val, 2) ?>
                                </td>
                                <td data-sort-val="<?= $mom_growth !== null ? $mom_growth : -9999 ?>">
                                    <?php if ($mom_growth !== null): ?>
                                        <span style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: 800; background: <?= $mom_growth >= 0 ? 'rgba(16, 185, 129, 0.15)' : 'rgba(239, 68, 68, 0.15)' ?>; color: <?= $mom_growth >= 0 ? '#10b981' : '#ef4444' ?>;">
                                            <?= $mom_growth >= 0 ? '▲ +' : '▼ ' ?><?= number_format($mom_growth, 1) ?>%
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--text-secondary); font-size: 0.8rem;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td data-sort-val="<?= $share_pct ?>">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <div style="flex: 1; height: 6px; background: var(--bg-surface-2); border-radius: 3px; overflow: hidden; max-width: 80px;">
                                            <div style="width: <?= min(100, max(0, $share_pct)) ?>%; height: 100%; background: #3b82f6; border-radius: 3px;"></div>
                                        </div>
                                        <span style="font-size: 0.8rem; font-weight: 700; color: var(--text-secondary); min-width: 45px;">
                                            <?= number_format($share_pct, 1) ?>%
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background: var(--bg-surface-2); font-weight: 800; border-top: 2px solid var(--border-color);">
                            <td style="padding: 14px 20px; text-transform: uppercase; font-size: 0.78rem; letter-spacing: 0.05em; color: var(--text-secondary);">
                                Period Totals / Weighted ASP
                            </td>
                            <td style="padding: 14px 20px; color: var(--text-main);">
                                <?= number_format($total_period_units) ?> units
                            </td>
                            <td style="padding: 14px 20px; color: var(--text-main);">
                                $<?= number_format($weighted_asp, 2) ?>
                            </td>
                            <td style="padding: 14px 20px; color: #10b981; font-size: 1.05rem;">
                                $<?= number_format($total_period_valuation, 2) ?>
                            </td>
                            <td style="padding: 14px 20px; color: var(--text-secondary);">
                                —
                            </td>
                            <td style="padding: 14px 20px; color: var(--text-secondary);">
                                100.0%
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>
