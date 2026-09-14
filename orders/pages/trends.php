<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<?php
/**
 * Trends & Market Analytics Control Center (Main Orchestrator)
 * Modularized view coordinating sales velocity, pricing trends, CPU dominance, B2B Untested Matrix, and Tested Market.
 */

require_once 'core/database.php';

// 1. Process Actions (B2B Untested & Tested Market AJAX Endpoints)
include __DIR__ . '/partials/trends_actions.php';

// 2. Fetch Data & Calculate Trend Metrics
include __DIR__ . '/partials/trends_data.php';
?>

<!-- Trends State Hydration Payload -->
<script id="trends-state" type="application/json">
<?= json_encode([
    'filter'             => $filter,
    'user_role'          => $user_role ?? ($_SESSION['role'] ?? ''),
    'totals'             => $totals,
    'top_buyer_name'     => $top_buyer_name,
    'top_buyer_qty'      => $top_buyer_qty,
    'popular_brand'      => $popular_brand,
    'popular_brand_qty'  => $popular_brand_qty,
    'peak_month'         => $peak_month,
    'total_ryzen_sold'   => $total_ryzen_sold,
    'cpu_distribution'   => $cpu_distribution,
    'price_history'      => $price_history
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
</script>

<div class="trends-container">
    <div class="trends-header">
        <div>
            <h1 style="font-weight: 900; font-size: 1.8rem; margin: 0; display: flex; align-items: center; gap: 10px;">
                📈 Sales & Item Trends Center
            </h1>
            <p class="subtitle" style="margin-top: 4px;">
                Analyzing historical line-items from the database to discover price curves and asset velocities.
                <?php if ($is_using_mock_data): ?>
                    <span style="color: var(--accent-color); font-weight: 700;">(Showing demo data until your first orders are placed)</span>
                <?php endif; ?>
            </p>
        </div>
        <div style="display: flex; gap: 10px; align-items: center;">
            <select id="trends-filter" onchange="window.location.href='?view=trends&filter='+this.value" style="padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-surface); color: var(--text-main); font-weight: 600;">
                <option value="30d" <?= $filter === '30d' ? 'selected' : '' ?>>Last 30 Days</option>
                <option value="ytd" <?= $filter === 'ytd' ? 'selected' : '' ?>>Year to Date</option>
                <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Time</option>
            </select>
        </div>
    </div>

    <!-- 3. Dynamic Summary Cards Board & Configuration -->
    <?php include __DIR__ . '/partials/trends_widgets.php'; ?>

    <!-- Interactive Navigation Tabs -->
    <div class="tab-nav">
        <button type="button" class="tab-btn active" onclick="switchTrendsTab('tab-velocity')">🔥 Model Demand</button>
        <button type="button" class="tab-btn" onclick="switchTrendsTab('tab-pricing')">📊 Pricing Curves</button>
        <button type="button" class="tab-btn" onclick="switchTrendsTab('tab-cpu')">💻 CPU Generations</button>
        <button type="button" class="tab-btn" onclick="switchTrendsTab('tab-customers')">👥 Customer Insights</button>
        <button type="button" class="tab-btn" onclick="switchTrendsTab('tab-matrix')">💵 B2B Untested</button>
        <button type="button" class="tab-btn" onclick="switchTrendsTab('tab-tested')">🎯 Tested Market</button>
    </div>

    <!-- Global Flexible Search Input -->
    <div class="trends-search-wrapper" style="margin-bottom: 20px; display: flex; gap: 10px; align-items: center; background: var(--bg-surface); padding: 10px 15px; border-radius: 8px; border: 1px solid var(--border-color);">
        <input type="text" id="trends-search" class="trends-search-input" placeholder="🔍 Type to filter rows (by model, specs, price, CPU etc)..." oninput="handleSearch(this.value)" style="flex: 1; border: none; background: transparent; color: var(--text-main); font-size: 0.95rem; outline: none;">
        <button type="button" id="clear-search" class="clear-search-btn" onclick="clearSearchInput()" style="display: none; background: transparent; border: none; color: var(--text-secondary); cursor: pointer; font-size: 1.1rem; padding: 0 5px;">✕</button>
    </div>

    <!-- 4. Tab Views -->
    <?php include __DIR__ . '/partials/trends_tab_velocity.php'; ?>
    <?php include __DIR__ . '/partials/trends_tab_pricing.php'; ?>
    <?php include __DIR__ . '/partials/trends_tab_cpu.php'; ?>
    <?php include __DIR__ . '/partials/trends_tab_customers.php'; ?>

    <div id="tab-matrix" class="tab-content">
        <?php include __DIR__ . '/partials/b2b_untested_matrix.php'; ?>
    </div>

    <div id="tab-tested" class="tab-content">
        <?php include __DIR__ . '/partials/tested_market_tab.php'; ?>
    </div>
</div>

<!-- 5. Dialog Modals -->
<?php include __DIR__ . '/partials/trends_modals.php'; ?>
