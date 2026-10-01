<?php
// labels/index.php
// Warehouse Control Dashboard & Instant Device Locator
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/header.php';

// Fetch Live Inventory KPIs
$total_inventory = 0;
$refurbished_count = 0;
$untested_count = 0;
$parts_count = 0;
$recent_items = [];

try {
    // 1. Total Active Units
    $stmt = $pdo_labels->query("SELECT COUNT(id) FROM items WHERE status != 'Sold'");
    $total_inventory = (int)$stmt->fetchColumn();

    // 2. Refurbished / Ready
    $stmt = $pdo_labels->query("SELECT COUNT(id) FROM items WHERE status != 'Sold' AND (description = 'Refurbished' OR status = 'Tested')");
    $refurbished_count = (int)$stmt->fetchColumn();

    // 3. Untested Intake
    $stmt = $pdo_labels->query("SELECT COUNT(id) FROM items WHERE status != 'Sold' AND description = 'Untested'");
    $untested_count = (int)$stmt->fetchColumn();

    // 4. For Parts / Salvage
    $stmt = $pdo_labels->query("SELECT COUNT(id) FROM items WHERE status != 'Sold' AND description = 'For Parts'");
    $parts_count = (int)$stmt->fetchColumn();

    // 5. Recent 6 Items for Quick Reprint Shelf
    $stmt = $pdo_labels->query("SELECT * FROM items ORDER BY created_at DESC LIMIT 6");
    $recent_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    // Graceful fallback if database empty
}
?>

<!-- Module Dashboard CSS -->
<link rel="stylesheet" href="assets/css/dashboard.css?v=<?= filemtime(__DIR__ . '/assets/css/dashboard.css') ?>">

<!-- HERO GREETING BANNER -->
<section class="dashboard-hero-panel">
    <div class="hero-welcome-badge">
        <span>⚡</span>
        <span>Warehouse Station Active</span>
    </div>
    <h1 class="dashboard-hero-title">Warehouse Control &amp; Thermal Labels</h1>
    <p class="dashboard-hero-desc">
        Streamlined hardware intake, live 2" × 1" thermal sticker printing, and instant device location tracking.
    </p>
</section>

<!-- LIVE INVENTORY KPI CARDS -->
<section class="stat-grid-4">
    <div class="stat-card">
        <div class="stat-icon-wrapper" style="color: var(--accent-color); background: var(--accent-soft);">📦</div>
        <div class="stat-content">
            <span class="stat-title">In-Stock Units</span>
            <div class="stat-value"><?= number_format($total_inventory) ?></div>
            <div class="stat-subtext">Active in warehouse</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon-wrapper" style="color: #10b981; background: rgba(16, 185, 129, 0.12);">✅</div>
        <div class="stat-content">
            <span class="stat-title">Ready / Tested</span>
            <div class="stat-value"><?= number_format($refurbished_count) ?></div>
            <div class="stat-subtext">Verified working specs</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon-wrapper" style="color: #f59e0b; background: rgba(245, 158, 11, 0.12);">⏳</div>
        <div class="stat-content">
            <span class="stat-title">Untested Intake</span>
            <div class="stat-value"><?= number_format($untested_count) ?></div>
            <div class="stat-subtext">Standard warehouse intake</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon-wrapper" style="color: #ef4444; background: rgba(239, 68, 68, 0.12);">🛠️</div>
        <div class="stat-content">
            <span class="stat-title">For Parts</span>
            <div class="stat-value"><?= number_format($parts_count) ?></div>
            <div class="stat-subtext">Salvage / repair batch</div>
        </div>
    </div>
</section>

<!-- PRIMARY WORKFLOW ACTION TILES -->
<section class="action-cards-grid">
    <!-- Tile 1: Add Item & Print Label -->
    <div class="action-tile-card tile-intake">
        <div>
            <div class="tile-icon-box">🏷️</div>
            <h2 class="tile-title">Rapid Intake &amp; Print</h2>
            <p class="tile-desc">
                Log a new laptop or console in under 30 seconds. Uses smart auto-filling specs, 1-click presets, and generates thermal labels instantly.
            </p>
        </div>
        <a href="new_label.php" class="btn btn-success btn-large" style="width: 100%;">
            <span>➕ Start Rapid Intake</span>
        </a>
    </div>

    <!-- Tile 2: Warehouse Inventory Tracker -->
    <div class="action-tile-card tile-inventory">
        <div>
            <div class="tile-icon-box">📋</div>
            <h2 class="tile-title">Warehouse Inventory</h2>
            <p class="tile-desc">
                Search, filter, inline-edit, and bulk-update locations for all physical hardware. Easily export filtered lists to CSV spreadsheets.
            </p>
        </div>
        <a href="labels.php" class="btn btn-primary btn-large" style="width: 100%;">
            <span>📦 Open Inventory Table</span>
        </a>
    </div>
</section>

<!-- QUICK LOCATE / SCANNER SEARCH -->
<section class="quick-locate-box">
    <div class="quick-locate-header">
        <div class="quick-locate-title">
            <h3><span>🔍</span> Quick Locate &amp; Scanner</h3>
            <p>Scan barcode, or search by Serial Number, Model, Asset ID, or Shelf Location.</p>
        </div>
        <div class="quick-search-wrapper">
            <span class="search-lens-icon">🔎</span>
            <input type="text" id="quickSearchInput" class="quick-search-input-field" placeholder="Type or scan S/N, Model, or ID..." autocomplete="off">
        </div>
    </div>

    <div id="quickSearchResults" class="search-results-shelf">
        <div style="color: var(--text-muted); font-size: 0.88rem; font-style: italic; padding: 10px 0;">
            Type a model (e.g. <code>Latitude 5400</code>), serial number, or item ID above to locate hardware.
        </div>
    </div>
</section>

<!-- RECENT INTAKES & REPRINT SHELF -->
<section class="recent-intakes-panel">
    <div class="flex-between" style="margin-bottom: 20px;">
        <div>
            <h3>⏱️ Recently Labeled Hardware</h3>
            <p style="color: var(--text-secondary); font-size: 0.85rem; margin-top: 2px;">
                Instant 1-click reprint for recently logged warehouse items.
            </p>
        </div>
        <a href="labels.php" class="btn btn-sm btn-secondary">View All Inventory →</a>
    </div>

    <?php if (empty($recent_items)): ?>
        <p style="color: var(--text-muted); font-style: italic; padding: 20px 0;">No items in warehouse yet. Click "Start Rapid Intake" above to log your first hardware unit!</p>
    <?php else: ?>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Device</th>
                        <th>Processor &amp; RAM</th>
                        <th>Location</th>
                        <th>Condition</th>
                        <th>Logged</th>
                        <th style="text-align: right;">Quick Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_items as $item): 
                        $brandModel = htmlspecialchars(($item['brand'] ?? '') . ' ' . ($item['model'] ?? '') . ' ' . ($item['series'] ?? ''));
                        $cpuRam = htmlspecialchars(($item['cpu_specs'] ?: ($item['cpu_gen'] ?: '—')) . ' · ' . ($item['ram'] ?: 'No RAM'));
                        $loc = htmlspecialchars($item['warehouse_location'] ?: 'Unassigned');
                        $cond = htmlspecialchars($item['description'] ?: 'Untested');
                        $badgeClass = $cond === 'Refurbished' ? 'badge-success' : ($cond === 'For Parts' ? 'badge-danger' : 'badge-warning');
                    ?>
                    <tr>
                        <td><strong>#<?= str_pad($item['id'], 5, '0', STR_PAD_LEFT) ?></strong></td>
                        <td>
                            <div style="font-weight: 800;"><?= $brandModel ?></div>
                            <div style="font-size: 0.75rem; color: var(--text-muted); font-family: var(--font-mono);">
                                S/N: <?= htmlspecialchars($item['serial_number'] ?: '—') ?>
                            </div>
                        </td>
                        <td style="color: var(--text-secondary); font-size: 0.85rem;"><?= $cpuRam ?></td>
                        <td><span class="badge badge-neutral">📍 <?= $loc ?></span></td>
                        <td><span class="badge <?= $badgeClass ?>"><?= $cond ?></span></td>
                        <td style="color: var(--text-muted); font-size: 0.8rem;"><?= format_date($item['created_at']) ?></td>
                        <td style="text-align: right;">
                            <div class="action-strip">
                                <button type="button" class="btn btn-sm btn-success" onclick="printThermalLabel(<?= (int)$item['id'] ?>)" title="Thermal Direct Print">
                                    🖨️ Print
                                </button>
                                <button type="button" class="btn btn-sm btn-secondary" onclick="quickViewHardware(<?= (int)$item['id'] ?>)" title="Quick View Specs">
                                    👁️ View
                                </button>
                                <button type="button" class="btn btn-sm btn-dark" onclick="flashOpenLabel(<?= (int)$item['id'] ?>, '<?= addslashes($item['brand']) ?>', '<?= addslashes($item['model']) ?>', this)" title="LibreOffice ODT">
                                    📄 ODT
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<!-- LIVE SEARCH SCRIPT -->
<script>
document.addEventListener("DOMContentLoaded", () => {
    const input = document.getElementById('quickSearchInput');
    const resultsContainer = document.getElementById('quickSearchResults');
    let timer = null;

    async function doSearch() {
        const query = input.value.trim();
        if (!query) {
            resultsContainer.innerHTML = `
                <div style="color: var(--text-muted); font-size: 0.88rem; font-style: italic; padding: 10px 0;">
                    Type a model, serial number, or item ID above to locate hardware.
                </div>`;
            return;
        }

        resultsContainer.innerHTML = '<div style="color: var(--text-secondary); font-size: 0.85rem; padding: 10px 0;">🔍 Searching warehouse records...</div>';

        try {
            const res = await fetch('api/search_item.php?id=' + encodeURIComponent(query));
            const json = await res.json();

            if (!json.success || !json.data || !json.data.results || json.data.results.length === 0) {
                resultsContainer.innerHTML = `
                    <div style="padding: 16px; background: var(--bg-surface-2); border-radius: var(--border-radius-md); color: var(--text-secondary); font-size: 0.9rem;">
                        No matching devices found in warehouse for "<strong>${escapeHtml(query)}</strong>".
                    </div>`;
                return;
            }

            let html = '';
            json.data.results.forEach(item => {
                const title = `${item.brand || ''} ${item.model || ''} ${item.series || ''}`.trim();
                const loc = item.warehouse_location || 'Unassigned';
                const sn = item.serial_number || 'No S/N';
                const cpu = item.cpu_specs || item.cpu_gen || '—';
                const cond = item.description || 'Untested';

                html += `
                    <div class="locate-item-card animate-fade-in">
                        <div class="locate-title-group">
                            <h4>#${String(item.id).padStart(5, '0')} · ${escapeHtml(title)}</h4>
                            <div class="locate-meta-row">
                                <span class="locate-badge-loc">📍 LOC: ${escapeHtml(loc)}</span>
                                <span>🧠 ${escapeHtml(cpu)}</span>
                                <span>🏷️ S/N: <code>${escapeHtml(sn)}</code></span>
                                <span class="badge ${cond === 'Refurbished' ? 'badge-success' : 'badge-neutral'}">${escapeHtml(cond)}</span>
                            </div>
                        </div>
                        <div class="action-strip">
                            <button type="button" class="btn btn-sm btn-success" onclick="printThermalLabel(${item.id})">
                                🖨️ Print Label
                            </button>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="quickViewHardware(${item.id})">
                                👁️ Specs
                            </button>
                            <a href="hardware_view.php?id=${item.id}" class="btn btn-sm btn-secondary">
                                🛠️ Edit
                            </a>
                        </div>
                    </div>
                `;
            });

            resultsContainer.innerHTML = html;

        } catch (err) {
            resultsContainer.innerHTML = '<div style="color: var(--color-danger); font-size: 0.85rem; padding: 10px 0;">Network error connecting to search.</div>';
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    input.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(doSearch, 250);
    });

    // Auto-focus search input if requested via URL hash
    if (window.location.hash === '#search') {
        input.focus();
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>