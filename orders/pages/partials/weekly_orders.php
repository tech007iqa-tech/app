<?php
/**
 * Weekly Orders & Sales Summary Partial
 * Generates week-by-week aggregated unit counts and gross amounts for reporting.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('America/Los_Angeles');

try {
    $conn = Database::orders();
    $conn_c = Database::customers();

    // Attach customers DB for cross-DB company names
    try {
        Database::attach($conn, 'customers', 'customers_db');
        $sql = "
            SELECT 
                o.order_id,
                o.customer_id,
                o.status,
                o.created_at,
                c.company_name,
                COALESCE(SUM(i.quantity), 0) as total_units,
                COALESCE(SUM(i.quantity * i.unit_price), 0.0) as total_amount,
                COUNT(i.id) as item_lines
            FROM orders o
            LEFT JOIN customers_db.customers c ON o.customer_id = c.customer_id
            LEFT JOIN items i ON o.order_id = i.order_id
            GROUP BY o.order_id
            ORDER BY o.created_at DESC
        ";
        $stmt = $conn->query($sql);
        $all_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $sql = "
            SELECT 
                o.order_id,
                o.customer_id,
                o.status,
                o.created_at,
                'Unknown Account' as company_name,
                COALESCE(SUM(i.quantity), 0) as total_units,
                COALESCE(SUM(i.quantity * i.unit_price), 0.0) as total_amount,
                COUNT(i.id) as item_lines
            FROM orders o
            LEFT JOIN items i ON o.order_id = i.order_id
            GROUP BY o.order_id
            ORDER BY o.created_at DESC
        ";
        $stmt = $conn->query($sql);
        $all_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Process orders into weeks
    $weeks_data = [];
    foreach ($all_orders as $ord) {
        $ts = strtotime($ord['created_at']);
        if (!$ts) continue;

        $dow = (int)date('N', $ts); // 1 (Mon) to 7 (Sun)
        $mon_ts = strtotime('-' . ($dow - 1) . ' days', strtotime(date('Y-m-d', $ts)));
        $week_key = date('Y-m-d', $mon_ts);

        if (!isset($weeks_data[$week_key])) {
            $fri_ts = strtotime('+4 days', $mon_ts);
            $sun_ts = strtotime('+6 days', $mon_ts);

            $weeks_data[$week_key] = [
                'week_key'     => $week_key,
                'mon_ts'       => $mon_ts,
                'fri_ts'       => $fri_ts,
                'sun_ts'       => $sun_ts,
                'label'        => date('M j', $mon_ts) . ' - ' . date('M j, Y', $fri_ts),
                'label_month'  => date('F j', $mon_ts) . ' - ' . (date('F', $mon_ts) === date('F', $fri_ts) ? date('j', $fri_ts) : date('F j', $fri_ts)),
                'label_full'   => date('F j', $mon_ts) . ' - ' . date('F j, Y', $fri_ts),
                'year'         => date('Y', $mon_ts),
                'total_units'  => 0,
                'total_amount' => 0.0,
                'batch_count'  => 0,
                'days'         => [
                    1 => ['name' => 'Monday',    'short' => 'Mon', 'day_num' => date('j', $mon_ts),             'date' => date('Y-m-d', $mon_ts),             'units' => 0, 'amount' => 0.0, 'batches' => []],
                    2 => ['name' => 'Tuesday',   'short' => 'Tue', 'day_num' => date('j', $mon_ts + 86400),     'date' => date('Y-m-d', $mon_ts + 86400),     'units' => 0, 'amount' => 0.0, 'batches' => []],
                    3 => ['name' => 'Wednesday', 'short' => 'Wed', 'day_num' => date('j', $mon_ts + 2*86400),   'date' => date('Y-m-d', $mon_ts + 2*86400),   'units' => 0, 'amount' => 0.0, 'batches' => []],
                    4 => ['name' => 'Thursday',  'short' => 'Thu', 'day_num' => date('j', $mon_ts + 3*86400),   'date' => date('Y-m-d', $mon_ts + 3*86400),   'units' => 0, 'amount' => 0.0, 'batches' => []],
                    5 => ['name' => 'Friday',    'short' => 'Fri', 'day_num' => date('j', $mon_ts + 4*86400),   'date' => date('Y-m-d', $mon_ts + 4*86400),   'units' => 0, 'amount' => 0.0, 'batches' => []],
                    6 => ['name' => 'Saturday',  'short' => 'Sat', 'day_num' => date('j', $mon_ts + 5*86400),   'date' => date('Y-m-d', $mon_ts + 5*86400),   'units' => 0, 'amount' => 0.0, 'batches' => []],
                    7 => ['name' => 'Sunday',    'short' => 'Sun', 'day_num' => date('j', $mon_ts + 6*86400),   'date' => date('Y-m-d', $mon_ts + 6*86400),   'units' => 0, 'amount' => 0.0, 'batches' => []],
                ]
            ];
        }

        $qty = (int)$ord['total_units'];
        $amt = (float)$ord['total_amount'];

        $weeks_data[$week_key]['total_units'] += $qty;
        $weeks_data[$week_key]['total_amount'] += $amt;
        $weeks_data[$week_key]['batch_count']++;

        if (isset($weeks_data[$week_key]['days'][$dow])) {
            $weeks_data[$week_key]['days'][$dow]['units'] += $qty;
            $weeks_data[$week_key]['days'][$dow]['amount'] += $amt;
            $weeks_data[$week_key]['days'][$dow]['batches'][] = $ord;
        }
    }

    // Sort weeks descending
    krsort($weeks_data);

    $available_week_keys = array_keys($weeks_data);
    $default_week_key = !empty($available_week_keys) ? $available_week_keys[0] : date('Y-m-d', strtotime('monday this week'));

    // Determine active week
    $selected_week_key = $_GET['week'] ?? $default_week_key;
    if (!isset($weeks_data[$selected_week_key])) {
        // Fallback to closest or create empty week representation
        if (!empty($available_week_keys)) {
            $selected_week_key = $available_week_keys[0];
        } else {
            $mon_ts = strtotime('monday this week');
            $fri_ts = strtotime('+4 days', $mon_ts);
            $selected_week_key = date('Y-m-d', $mon_ts);
            $weeks_data[$selected_week_key] = [
                'week_key'     => $selected_week_key,
                'mon_ts'       => $mon_ts,
                'fri_ts'       => $fri_ts,
                'sun_ts'       => strtotime('+6 days', $mon_ts),
                'label'        => date('M j', $mon_ts) . ' - ' . date('M j, Y', $fri_ts),
                'label_month'  => date('F j', $mon_ts) . ' - ' . date('j', $fri_ts),
                'label_full'   => date('F j', $mon_ts) . ' - ' . date('F j, Y', $fri_ts),
                'year'         => date('Y', $mon_ts),
                'total_units'  => 0,
                'total_amount' => 0.0,
                'batch_count'  => 0,
                'days'         => [
                    1 => ['name' => 'Monday',    'short' => 'Mon', 'day_num' => date('j', $mon_ts),             'date' => date('Y-m-d', $mon_ts),             'units' => 0, 'amount' => 0.0, 'batches' => []],
                    2 => ['name' => 'Tuesday',   'short' => 'Tue', 'day_num' => date('j', $mon_ts + 86400),     'date' => date('Y-m-d', $mon_ts + 86400),     'units' => 0, 'amount' => 0.0, 'batches' => []],
                    3 => ['name' => 'Wednesday', 'short' => 'Wed', 'day_num' => date('j', $mon_ts + 2*86400),   'date' => date('Y-m-d', $mon_ts + 2*86400),   'units' => 0, 'amount' => 0.0, 'batches' => []],
                    4 => ['name' => 'Thursday',  'short' => 'Thu', 'day_num' => date('j', $mon_ts + 3*86400),   'date' => date('Y-m-d', $mon_ts + 3*86400),   'units' => 0, 'amount' => 0.0, 'batches' => []],
                    5 => ['name' => 'Friday',    'short' => 'Fri', 'day_num' => date('j', $mon_ts + 4*86400),   'date' => date('Y-m-d', $mon_ts + 4*86400),   'units' => 0, 'amount' => 0.0, 'batches' => []],
                    6 => ['name' => 'Saturday',  'short' => 'Sat', 'day_num' => date('j', $mon_ts + 5*86400),   'date' => date('Y-m-d', $mon_ts + 5*86400),   'units' => 0, 'amount' => 0.0, 'batches' => []],
                    7 => ['name' => 'Sunday',    'short' => 'Sun', 'day_num' => date('j', $mon_ts + 6*86400),   'date' => date('Y-m-d', $mon_ts + 6*86400),   'units' => 0, 'amount' => 0.0, 'batches' => []],
                ]
            ];
        }
    }

    $active_week = $weeks_data[$selected_week_key];

    // Calculate previous and next week keys in navigation
    $current_week_index = array_search($selected_week_key, $available_week_keys);
    $prev_week_key = ($current_week_index !== false && $current_week_index < count($available_week_keys) - 1) ? $available_week_keys[$current_week_index + 1] : null;
    $next_week_key = ($current_week_index !== false && $current_week_index > 0) ? $available_week_keys[$current_week_index - 1] : null;

    // Summary calculations for active week
    $active_total_units = $active_week['total_units'];
    $active_total_amount = $active_week['total_amount'];
    $active_batches_count = $active_week['batch_count'];
    $active_avg_unit_price = $active_total_units > 0 ? ($active_total_amount / $active_total_units) : 0.0;
    $active_avg_order_value = $active_batches_count > 0 ? ($active_total_amount / $active_batches_count) : 0.0;

    // Prepare chart payloads (last 12 weeks chronologically for trend chart)
    $historical_chart_weeks = array_reverse(array_slice($weeks_data, 0, 12, true));
    $chart_history_labels = [];
    $chart_history_units = [];
    $chart_history_amounts = [];
    foreach ($historical_chart_weeks as $wk => $wdata) {
        $chart_history_labels[] = date('M j', $wdata['mon_ts']);
        $chart_history_units[] = $wdata['total_units'];
        $chart_history_amounts[] = round($wdata['total_amount'], 2);
    }

    // Prepare active week daily chart payload
    $chart_daily_labels = [];
    $chart_daily_units = [];
    $chart_daily_amounts = [];
    for ($d = 1; $d <= 5; $d++) {
        $day_info = $active_week['days'][$d];
        $chart_daily_labels[] = $day_info['short'] . ' (' . $day_info['day_num'] . ')';
        $chart_daily_units[] = $day_info['units'];
        $chart_daily_amounts[] = round($day_info['amount'], 2);
    }

} catch (PDOException $e) {
    echo "<div class='alert-box alert-danger'>Error loading weekly orders: " . htmlspecialchars($e->getMessage()) . "</div>";
    return;
}
?>

<!-- JSON Hydration Payload for JavaScript Interactions & Charting -->
<script id="weekly-orders-state" type="application/json">
<?= json_encode([
    'active_week_key'     => $selected_week_key,
    'label_month'         => $active_week['label_month'],
    'label_full'          => $active_week['label_full'],
    'total_units'         => $active_total_units,
    'total_amount'        => $active_total_amount,
    'batch_count'         => $active_batches_count,
    'days'                => $active_week['days'],
    'daily_chart'         => [
        'labels'  => $chart_daily_labels,
        'units'   => $chart_daily_units,
        'amounts' => $chart_daily_amounts
    ],
    'history_chart'       => [
        'labels'  => $chart_history_labels,
        'units'   => $chart_history_units,
        'amounts' => $chart_history_amounts
    ]
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
</script>

<div class="weekly-orders-wrapper">

    <!-- KPI Metric Cards Banner -->
    <div class="weekly-metrics-grid">
        <div class="metric-card">
            <div class="metric-icon-wrap" style="background: rgba(140, 198, 63, 0.15); color: var(--accent-color);">
                📦
            </div>
            <div class="metric-content">
                <span class="metric-label">Units Added / Sold</span>
                <div class="metric-value"><?= number_format($active_total_units) ?> <span class="metric-unit">items</span></div>
                <span class="metric-sub"><?= $active_total_units > 0 ? round($active_total_units / 5, 1) . ' avg/day (Mon-Fri)' : 'No units recorded' ?></span>
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-icon-wrap" style="background: rgba(59, 130, 246, 0.15); color: #3b82f6;">
                💰
            </div>
            <div class="metric-content">
                <span class="metric-label">Total Sold Amount</span>
                <div class="metric-value">$<?= number_format($active_total_amount, 2) ?></div>
                <span class="metric-sub"><?= $active_total_amount > 0 ? '$' . number_format($active_total_amount / 5, 2) . ' avg/day' : 'No revenue recorded' ?></span>
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-icon-wrap" style="background: rgba(168, 85, 247, 0.15); color: #a855f7;">
                📑
            </div>
            <div class="metric-content">
                <span class="metric-label">Batches Processed</span>
                <div class="metric-value"><?= number_format($active_batches_count) ?> <span class="metric-unit">batches</span></div>
                <span class="metric-sub">Avg $<?= number_format($active_avg_order_value, 2) ?> / batch</span>
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-icon-wrap" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b;">
                🏷️
            </div>
            <div class="metric-content">
                <span class="metric-label">Realized ASP / Unit</span>
                <div class="metric-value">$<?= number_format($active_avg_unit_price, 2) ?></div>
                <span class="metric-sub">Gross realized rate</span>
            </div>
        </div>
    </div>

    <!-- Navigation & Quick Actions Toolbar -->
    <div class="weekly-toolbar-card">
        <div class="weekly-nav-group">
            <button type="button" class="btn-week-nav" <?= $prev_week_key ? "onclick=\"window.location.href='index.php?view=orders&type=weekly&week=" . urlencode($prev_week_key) . "'\"" : 'disabled' ?> title="Previous Week">
                ← Older Week
            </button>

            <select id="weekDropdownSelect" class="week-dropdown-select" onchange="window.location.href='index.php?view=orders&type=weekly&week=' + encodeURIComponent(this.value)">
                <?php foreach ($weeks_data as $wk_key => $wk_val): ?>
                    <option value="<?= htmlspecialchars($wk_key) ?>" <?= $wk_key === $selected_week_key ? 'selected' : '' ?>>
                        <?= htmlspecialchars($wk_val['label']) ?> (<?= number_format($wk_val['total_units']) ?> units • $<?= number_format($wk_val['total_amount']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="button" class="btn-week-nav" <?= $next_week_key ? "onclick=\"window.location.href='index.php?view=orders&type=weekly&week=" . urlencode($next_week_key) . "'\"" : 'disabled' ?> title="Next Week">
                Newer Week →
            </button>

            <?php if ($selected_week_key !== $default_week_key): ?>
                <a href="index.php?view=orders&type=weekly" class="btn-today-pill">
                    Latest Week
                </a>
            <?php endif; ?>
        </div>

        <div class="weekly-actions-group">
            <button type="button" class="btn-action-primary" onclick="copyWeeklyReportTable()" title="Copy formatted table for Excel / Google Sheets">
                <span class="btn-icon">📋</span> Copy for Report
            </button>
            <button type="button" class="btn-action-secondary" onclick="exportWeeklyReportCSV()" title="Download CSV with UTF-8 BOM">
                <span class="btn-icon">📥</span> Export CSV
            </button>
            <button type="button" class="btn-action-secondary" id="toggleDaysBtn" onclick="toggleWeekendDays()" title="Toggle 5-day / 7-day display">
                <span class="btn-icon">📅</span> <span id="toggleDaysLabel">Show 7 Days</span>
            </button>
        </div>
    </div>

    <!-- SPREADSHEET CARD (FAITHFUL MATCH TO SCREENSHOT 2) -->
    <div class="weekly-spreadsheet-container" id="spreadsheetReportBox">
        <div class="spreadsheet-header-banner">
            <div class="spreadsheet-banner-left">
                <span class="spreadsheet-badge">WEEKLY ORDERS REPORT</span>
                <h2 class="spreadsheet-title" id="weeklyReportTitle"><?= htmlspecialchars($active_week['label_month']) ?></h2>
                <span class="spreadsheet-year"><?= htmlspecialchars($active_week['year']) ?></span>
            </div>
            <div class="spreadsheet-banner-right">
                <span class="spreadsheet-meta">1-Click Excel / Google Sheets Ready</span>
                <button type="button" class="btn-quick-copy" onclick="copyWeeklyReportTable()" title="Copy this table">
                    📋 Copy Table
                </button>
            </div>
        </div>

        <div class="spreadsheet-table-responsive">
            <table class="weekly-sheet-table" id="weeklySheetTable">
                <thead>
                    <tr class="sheet-head-row">
                        <th class="sheet-th-metric">Metric</th>
                        <?php for ($d = 1; $d <= 7; $d++): 
                            $day_info = $active_week['days'][$d];
                            $is_weekend = ($d >= 6);
                        ?>
                            <th class="sheet-th-day <?= $is_weekend ? 'col-weekend' : '' ?>" data-day-index="<?= $d ?>" style="<?= $is_weekend ? 'display: none;' : '' ?>">
                                <div class="sheet-day-num"><?= $day_info['day_num'] ?></div>
                                <div class="sheet-day-name"><?= $day_info['name'] ?></div>
                            </th>
                        <?php endfor; ?>
                        <th class="sheet-th-total">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Row 1: Day Names -->
                    <tr class="sheet-row sheet-row-dayname">
                        <td class="sheet-td-label">Day</td>
                        <?php for ($d = 1; $d <= 7; $d++): 
                            $day_info = $active_week['days'][$d];
                            $is_weekend = ($d >= 6);
                        ?>
                            <td class="sheet-td-val sheet-val-dayname <?= $is_weekend ? 'col-weekend' : '' ?>" data-day-index="<?= $d ?>" style="<?= $is_weekend ? 'display: none;' : '' ?>">
                                <?= $day_info['name'] ?>
                            </td>
                        <?php endfor; ?>
                        <td class="sheet-td-val sheet-val-total-label">5-Day Week</td>
                    </tr>

                    <!-- Row 2: Units Sold / Added -->
                    <tr class="sheet-row sheet-row-units">
                        <td class="sheet-td-label font-bold">Units</td>
                        <?php for ($d = 1; $d <= 7; $d++): 
                            $day_info = $active_week['days'][$d];
                            $is_weekend = ($d >= 6);
                            $units_val = $day_info['units'];
                        ?>
                            <td class="sheet-td-val <?= $units_val > 0 ? 'has-data font-bold' : 'is-zero' ?> <?= $is_weekend ? 'col-weekend' : '' ?>" data-day-index="<?= $d ?>" style="<?= $is_weekend ? 'display: none;' : '' ?>" onclick="scrollToDayOrders('<?= $day_info['date'] ?>')">
                                <?= $units_val > 0 ? number_format($units_val) : '-' ?>
                            </td>
                        <?php endfor; ?>
                        <td class="sheet-td-val sheet-total-cell font-bold">
                            <?= $active_total_units > 0 ? number_format($active_total_units) : '-' ?>
                        </td>
                    </tr>

                    <!-- Row 3: Sold Price / Total Amount -->
                    <tr class="sheet-row sheet-row-price">
                        <td class="sheet-td-label font-bold">Sold Price</td>
                        <?php for ($d = 1; $d <= 7; $d++): 
                            $day_info = $active_week['days'][$d];
                            $is_weekend = ($d >= 6);
                            $amt_val = $day_info['amount'];
                        ?>
                            <td class="sheet-td-val <?= $amt_val > 0 ? 'has-data font-bold' : 'is-zero' ?> <?= $is_weekend ? 'col-weekend' : '' ?>" data-day-index="<?= $d ?>" style="<?= $is_weekend ? 'display: none;' : '' ?>" onclick="scrollToDayOrders('<?= $day_info['date'] ?>')">
                                <?= $amt_val > 0 ? '$' . number_format($amt_val, ($amt_val == (int)$amt_val ? 0 : 2)) : '-' ?>
                            </td>
                        <?php endfor; ?>
                        <td class="sheet-td-val sheet-total-cell font-bold highlight-price">
                            <?= $active_total_amount > 0 ? '$' . number_format($active_total_amount, ($active_total_amount == (int)$active_total_amount ? 0 : 2)) : '-' ?>
                        </td>
                    </tr>

                    <!-- Row 4: Batches Count -->
                    <tr class="sheet-row sheet-row-batches">
                        <td class="sheet-td-label">Batches</td>
                        <?php for ($d = 1; $d <= 7; $d++): 
                            $day_info = $active_week['days'][$d];
                            $is_weekend = ($d >= 6);
                            $b_count = count($day_info['batches']);
                        ?>
                            <td class="sheet-td-val <?= $b_count > 0 ? 'has-data' : 'is-zero' ?> <?= $is_weekend ? 'col-weekend' : '' ?>" data-day-index="<?= $d ?>" style="<?= $is_weekend ? 'display: none;' : '' ?>" onclick="scrollToDayOrders('<?= $day_info['date'] ?>')">
                                <?= $b_count > 0 ? $b_count . ' ' . ($b_count === 1 ? 'batch' : 'batches') : '-' ?>
                            </td>
                        <?php endfor; ?>
                        <td class="sheet-td-val sheet-total-cell">
                            <?= $active_batches_count > 0 ? $active_batches_count . ' batches' : '-' ?>
                        </td>
                    </tr>

                    <!-- Row 5: Average Price Per Unit -->
                    <tr class="sheet-row sheet-row-asp">
                        <td class="sheet-td-label">Avg Price / Unit</td>
                        <?php for ($d = 1; $d <= 7; $d++): 
                            $day_info = $active_week['days'][$d];
                            $is_weekend = ($d >= 6);
                            $day_asp = $day_info['units'] > 0 ? ($day_info['amount'] / $day_info['units']) : 0.0;
                        ?>
                            <td class="sheet-td-val <?= $day_asp > 0 ? 'has-data' : 'is-zero' ?> <?= $is_weekend ? 'col-weekend' : '' ?>" data-day-index="<?= $d ?>" style="<?= $is_weekend ? 'display: none;' : '' ?>">
                                <?= $day_asp > 0 ? '$' . number_format($day_asp, 2) : '-' ?>
                            </td>
                        <?php endfor; ?>
                        <td class="sheet-td-val sheet-total-cell">
                            <?= $active_avg_unit_price > 0 ? '$' . number_format($active_avg_unit_price, 2) : '-' ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="spreadsheet-footer-note">
            <span>💡 <strong>Tip:</strong> Click "Copy for Report" to paste directly into Excel or Google Sheets with clean formatting. Click any column to jump to that day's orders.</span>
        </div>
    </div>

    <!-- CHARTS SECTION: Daily Performance & Historical Multi-Week Trends -->
    <div class="weekly-charts-grid">
        <div class="weekly-chart-card">
            <div class="chart-header">
                <div>
                    <h3 class="chart-title">Daily Units & Revenue Breakdown</h3>
                    <p class="chart-subtitle"><?= htmlspecialchars($active_week['label_month']) ?> distribution</p>
                </div>
                <div class="chart-badges">
                    <span class="chart-legend-badge badge-units">Units</span>
                    <span class="chart-legend-badge badge-amount">Revenue ($)</span>
                </div>
            </div>
            <div class="chart-canvas-wrapper" style="position: relative; height: 260px; width: 100%;">
                <canvas id="weeklyDailyChart"></canvas>
            </div>
        </div>

        <div class="weekly-chart-card">
            <div class="chart-header">
                <div>
                    <h3 class="chart-title">12-Week Revenue & Volume Velocity</h3>
                    <p class="chart-subtitle">Historical week-over-week performance</p>
                </div>
                <div class="chart-badges">
                    <span class="chart-legend-badge badge-history">Weekly Total ($)</span>
                </div>
            </div>
            <div class="chart-canvas-wrapper" style="position: relative; height: 260px; width: 100%;">
                <canvas id="weeklyHistoryChart"></canvas>
            </div>
        </div>
    </div>

    <!-- SELECTED WEEK BATCH DRILL-DOWN -->
    <div class="weekly-drilldown-card" id="weeklyBatchDrilldown">
        <div class="drilldown-header">
            <div>
                <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin: 0;">
                    📦 Batches in <?= htmlspecialchars($active_week['label']) ?>
                </h3>
                <p style="font-size: 0.85rem; color: #64748b; margin: 4px 0 0 0;">
                    Detailed order batches and accounts fulfilling during this period.
                </p>
            </div>
            <div style="font-size: 0.85rem; font-weight: 700; color: var(--text-main);">
                <?= $active_batches_count ?> Total Batches
            </div>
        </div>

        <?php if ($active_batches_count > 0): ?>
            <div class="table-container" style="margin-top: 15px; border-radius: 16px; box-shadow: none; border: 1px solid #e2e8f0;">
                <table class="orders-table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #1e293b !important;">
                            <th style="background: #1e293b !important; color: white !important; padding: 14px 20px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase;">Batch ID</th>
                            <th style="background: #1e293b !important; color: white !important; padding: 14px 20px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase;">Customer / Account</th>
                            <th style="background: #1e293b !important; color: white !important; padding: 14px 20px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase;">Date & Time</th>
                            <th style="background: #1e293b !important; color: white !important; padding: 14px 20px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; text-align: right;">Line Items</th>
                            <th style="background: #1e293b !important; color: white !important; padding: 14px 20px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; text-align: right;">Total Units</th>
                            <th style="background: #1e293b !important; color: white !important; padding: 14px 20px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; text-align: right;">Total Amount</th>
                            <th style="background: #1e293b !important; color: white !important; padding: 14px 20px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; text-align: center;">Status</th>
                            <th style="background: #1e293b !important; color: white !important; padding: 14px 20px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        for ($d = 1; $d <= 7; $d++):
                            $day_info = $active_week['days'][$d];
                            if (empty($day_info['batches'])) continue;
                        ?>
                            <tr class="day-group-header-row" id="day-group-<?= $day_info['date'] ?>" style="background: #f8fafc; border-top: 2px solid #e2e8f0; border-bottom: 1px solid #cbd5e1;">
                                <td colspan="8" style="padding: 10px 20px; font-weight: 800; font-size: 0.85rem; color: #334155;">
                                    📅 <?= $day_info['name'] ?>, <?= date('M d, Y', strtotime($day_info['date'])) ?>
                                    <span style="font-weight: 600; color: #64748b; margin-left: 10px;">
                                        (<?= count($day_info['batches']) ?> batches • <?= number_format($day_info['units']) ?> units • $<?= number_format($day_info['amount'], 2) ?>)
                                    </span>
                                </td>
                            </tr>
                            <?php foreach ($day_info['batches'] as $ord): 
                                $b_status = strtolower($ord['status'] ?? 'active');
                                $status_class = "status-" . $b_status;
                            ?>
                                <tr class="order-row" data-id="<?= htmlspecialchars($ord['order_id']) ?>" style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 14px 20px;">
                                        <div style="font-weight: 800; color: var(--text-main); font-size: 0.9rem; font-family: monospace;">
                                            <?= htmlspecialchars($ord['order_id']) ?>
                                        </div>
                                    </td>
                                    <td style="padding: 14px 20px;">
                                        <div style="font-weight: 700; color: var(--text-main); font-size: 0.9rem;">
                                            <?= htmlspecialchars($ord['company_name'] ?: 'Unknown Account') ?>
                                        </div>
                                        <div style="font-size: 0.7rem; color: #94a3b8; font-family: monospace;">
                                            <?= htmlspecialchars($ord['customer_id']) ?>
                                        </div>
                                    </td>
                                    <td style="padding: 14px 20px;">
                                        <div style="font-size: 0.85rem; font-weight: 600; color: #64748b;">
                                            <?= date('M d, Y h:i A', strtotime($ord['created_at'])) ?>
                                        </div>
                                    </td>
                                    <td style="padding: 14px 20px; text-align: right; font-weight: 600; color: #64748b;">
                                        <?= number_format($ord['item_lines']) ?> lines
                                    </td>
                                    <td style="padding: 14px 20px; text-align: right; font-weight: 800; color: var(--text-main);">
                                        <?= number_format($ord['total_units']) ?>
                                    </td>
                                    <td style="padding: 14px 20px; text-align: right; font-weight: 800; color: var(--accent-dark, #65a30d);">
                                        $<?= number_format($ord['total_amount'], 2) ?>
                                    </td>
                                    <td style="padding: 14px 20px; text-align: center;">
                                        <span class="order-badge <?= $status_class ?>" style="min-width: 75px; text-align: center;">
                                            <?= htmlspecialchars($b_status) ?>
                                        </span>
                                    </td>
                                    <td style="padding: 14px 20px; text-align: right;">
                                        <a href="checkout.php?customer_id=<?= urlencode($ord['customer_id']) ?>&order_id=<?= urlencode($ord['order_id']) ?>"
                                           class="btn-order-view"
                                           style="padding: 8px 14px; font-size: 0.8rem;">
                                            <span>Details</span>
                                            <i>→</i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div style="padding: 40px; text-align: center; color: #94a3b8; font-weight: 600;">
                No batches were recorded during this week.
            </div>
        <?php endif; ?>
    </div>

    <!-- MULTI-WEEK HISTORICAL OVERVIEW TABLE -->
    <div class="weekly-history-container">
        <div class="history-header">
            <div>
                <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin: 0;">
                    📈 Week-by-Week Historical Summary (All Weeks)
                </h3>
                <p style="font-size: 0.85rem; color: #64748b; margin: 4px 0 0 0;">
                    Comprehensive archive of weekly item counts, totals, and volume metrics.
                </p>
            </div>
            <button type="button" class="btn-action-secondary" onclick="copyAllWeeksHistoricalTable()">
                📋 Copy All Weeks Table
            </button>
        </div>

        <div class="table-container" style="margin-top: 15px; border-radius: 16px; box-shadow: none; border: 1px solid #e2e8f0;">
            <table class="orders-table history-summary-table" id="allWeeksTable" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #1e293b !important;">
                        <th style="background: #1e293b !important; color: white !important; padding: 14px 20px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase;">Week Period</th>
                        <th style="background: #1e293b !important; color: white !important; padding: 14px 20px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; text-align: center;">Batches</th>
                        <th style="background: #1e293b !important; color: white !important; padding: 14px 20px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; text-align: right;">Total Units Added</th>
                        <th style="background: #1e293b !important; color: white !important; padding: 14px 20px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; text-align: right;">Total Sold Price ($)</th>
                        <th style="background: #1e293b !important; color: white !important; padding: 14px 20px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; text-align: right;">Avg Price / Unit</th>
                        <th style="background: #1e293b !important; color: white !important; padding: 14px 20px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; text-align: right;">Daily Mon-Fri Avg Units</th>
                        <th style="background: #1e293b !important; color: white !important; padding: 14px 20px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($weeks_data as $wk_key => $w): 
                        $is_active_row = ($wk_key === $selected_week_key);
                        $w_asp = $w['total_units'] > 0 ? ($w['total_amount'] / $w['total_units']) : 0.0;
                        $w_daily_avg = $w['total_units'] / 5;
                    ?>
                        <tr class="order-row <?= $is_active_row ? 'selected-week-row' : '' ?>" style="border-bottom: 1px solid #f1f5f9; <?= $is_active_row ? 'background: rgba(140, 198, 63, 0.08);' : '' ?>">
                            <td style="padding: 16px 20px;">
                                <div style="font-weight: 800; color: var(--text-main); font-size: 0.95rem;">
                                    <?= htmlspecialchars($w['label']) ?>
                                    <?php if ($is_active_row): ?>
                                        <span class="active-week-badge">Active View</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td style="padding: 16px 20px; text-align: center; font-weight: 700; color: #64748b;">
                                <?= number_format($w['batch_count']) ?>
                            </td>
                            <td style="padding: 16px 20px; text-align: right; font-weight: 800; color: var(--text-main); font-size: 0.95rem;">
                                <?= number_format($w['total_units']) ?>
                            </td>
                            <td style="padding: 16px 20px; text-align: right; font-weight: 800; color: var(--accent-dark, #65a30d); font-size: 0.95rem;">
                                $<?= number_format($w['total_amount'], 2) ?>
                            </td>
                            <td style="padding: 16px 20px; text-align: right; font-weight: 700; color: #64748b;">
                                $<?= number_format($w_asp, 2) ?>
                            </td>
                            <td style="padding: 16px 20px; text-align: right; font-weight: 700; color: #64748b;">
                                <?= round($w_daily_avg, 1) ?>
                            </td>
                            <td style="padding: 16px 20px; text-align: right;">
                                <div style="display: inline-flex; gap: 8px;">
                                    <button type="button" class="btn-sm-copy" onclick="copySingleWeekSummary('<?= htmlspecialchars($wk_key) ?>')" title="Copy this week's report">
                                        📋
                                    </button>
                                    <a href="index.php?view=orders&type=weekly&week=<?= urlencode($wk_key) ?>" class="btn-sm-view" title="Inspect this week">
                                        👁️ View
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
