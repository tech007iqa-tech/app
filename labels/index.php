<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';
?>

<!-- Module Specific CSS -->
<link rel="stylesheet" href="assets/css/dashboard.css?v=<?= filemtime('assets/css/dashboard.css') ?>">

<?php
// Fetch basic stats
$total_inventory = 0;
try {
    $stmt = $pdo_labels->query("SELECT COUNT(id) FROM items WHERE status != 'Sold'");
    $total_inventory = $stmt->fetchColumn();
} catch (PDOException $e) { /* Silent */ }
?>

<div class="panel dashboard-header">
    <h1>Warehouse Control</h1>
    <p>Streamlined management for hardware inventory and professional label generation.</p>
</div>

<!-- MAIN NAVIGATION GRID -->
<div class="action-grid action-grid-container">

    <!-- 1. INVENTORY TRACKER -->
    <a href="labels.php" class="card-link inventory-card hover-scale">
        <?= UI::stat_card("Inventory Tracker", $total_inventory . " Units Active", "inventory-stat") ?>
        <p style="padding: 0 20px; text-align: center; color: var(--text-secondary); font-size: 0.9rem; margin-top: -10px;">Manage, search, and edit all hardware currently in the warehouse.</p>
    </a>

    <!-- 2. PRINT HARDWARE LABEL -->
    <a href="new_label.php" class="card-link print-card hover-scale">
        <?= UI::stat_card("Hardware Labels", "Generate Label", "print-stat") ?>
        <p style="padding: 0 20px; text-align: center; color: var(--text-secondary); font-size: 0.9rem; margin-top: -10px;">Rapid intake and professional .odt thermal label generation.</p>
    </a>
</div>

<!-- QUICK LOCATE (MINIMAL) -->
<div class="panel quick-locate-panel">
    <div class="quick-locate-header">
        <div class="quick-locate-title">
            <h3><span>🔍</span> Quick Locate</h3>
            <p>Find a device's physical location instantly.</p>
        </div>
        <form id="quickSearchForm" class="quick-search-form">
            <input type="text" id="quickSearchId" placeholder="Enter ID, Brand, or Model..." required>
            <button type="submit" class="btn btn-primary">Find</button>
        </form>
    </div>
    <div id="quickSearchResult" style="margin-top: 20px;"></div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        const quickInput  = document.getElementById('quickSearchId');
        const resultDiv   = document.getElementById('quickSearchResult');
        let searchTimer   = null;

        async function performSearch() {
            const query = quickInput.value.trim();
            if (!query) {
                resultDiv.innerHTML = '';
                return;
            }

            resultDiv.innerHTML = '<span style="color:var(--text-secondary); font-size:0.85rem;">🔍 Searching…</span>';

            try {
                const res  = await fetch('api/search_item.php?id=' + encodeURIComponent(query));
                const json = await res.json();

                if (!json.success) {
                    resultDiv.innerHTML = `<div style="padding:15px; background:rgba(220,53,69,0.05); border-radius:8px; color:var(--btn-danger-bg); font-size:0.9rem;">⚠ ${json.error}</div>`;
                    return;
                }

                const results = json.data.results;
                let html = '';

                results.forEach((item, index) => {
                    html += `
                        <div class="search-result-item">
                            <div class="result-info">
                                <h4>#${String(item.id).padStart(5,'0')} — ${item.brand} ${item.model}</h4>
                                <div class="result-meta">
                                    📍 <strong>${item.warehouse_location ?? 'Unassigned'}</strong> | 🧠 ${item.cpu_gen ?? '—'}
                                </div>
                            </div>
                            <div class="result-actions">
                                <a href="hardware_view.php?id=${item.id}" class="btn">View Sheet</a>
                                <button onclick="flashOpenLabel(${item.id}, '${item.brand}', '${item.model}', this)" class="btn btn-open-odt">📂 Open ODT</button>
                            </div>
                        </div>`;
                });

                resultDiv.innerHTML = html;

            } catch (err) {
                resultDiv.innerHTML = '<span style="color:var(--btn-danger-bg); font-size:0.85rem;">⚠ Network error.</span>';
            }
        }

        quickInput.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(performSearch, 300);
        });

        document.getElementById('quickSearchForm').addEventListener('submit', (e) => {
            e.preventDefault();
            clearTimeout(searchTimer);
            performSearch();
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>