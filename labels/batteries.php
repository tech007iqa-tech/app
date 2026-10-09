<?php
// labels/batteries.php
// Modular Secondary Subsystem: Battery Cross-Matching, Specs & Thermal Labels
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/header.php';

// Initial server-side query for quick server rendering
$initial_batteries = [];
$total_battery_count = 0;
$total_stock_count = 0;

try {
    $stmt = $pdo_labels->query("SELECT * FROM batteries ORDER BY brand ASC, part_number ASC LIMIT 100");
    $initial_batteries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_battery_count = (int)$pdo_labels->query("SELECT COUNT(*) FROM batteries")->fetchColumn();
    $total_stock_count = (int)$pdo_labels->query("SELECT SUM(qty_in_stock) FROM batteries")->fetchColumn();
} catch (Exception $e) {}
?>

<!-- Batteries Subsystem Specialized CSS -->
<link rel="stylesheet" href="assets/css/batteries.css?v=<?= filemtime(__DIR__ . '/assets/css/batteries.css') ?>">

<!-- TOP HEADER & CONTROLS -->
<div class="panel flex-between" style="margin-bottom: 20px; padding: 20px 28px;">
    <div>
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 4px;">
            <h1 style="font-size: 1.6rem; font-weight: 900; margin: 0;">🔋 Battery Cross-Match &amp; Inventory</h1>
            <span class="badge badge-accent" style="font-size: 0.75rem; background: rgba(59, 130, 246, 0.15); color: #3b82f6;">Modular v1.0</span>
        </div>
        <?= UI::csrf_field() ?>
        <p style="color: var(--text-secondary); font-size: 0.95rem;">
            Instant bidirectional matching. Enter a laptop model to find replacement batteries, or a battery part number to find supported laptops.
        </p>
    </div>
    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
        <button type="button" id="btnExportBatteryCSV" class="btn btn-secondary" title="Export current catalog to Excel CSV">
            <span>📥 Export CSV</span>
        </button>
        <button type="button" class="btn btn-primary" onclick="openAddBatteryModal()" title="Register a new battery or add stock">
            <span>➕ Add Battery / Stock</span>
        </button>
    </div>
</div>

<!-- LIVE KPI SUMMARY CARDS -->
<section class="battery-stat-grid">
    <div class="battery-stat-card">
        <div class="battery-stat-icon" style="background: rgba(59, 130, 246, 0.12); color: #3b82f6;">🔋</div>
        <div>
            <div class="battery-stat-val" id="kpiBatteryCount"><?= number_format($total_battery_count) ?></div>
            <div class="battery-stat-lbl">Battery Profiles</div>
        </div>
    </div>

    <div class="battery-stat-card">
        <div class="battery-stat-icon" style="background: rgba(16, 185, 129, 0.12); color: #10b981;">💻</div>
        <div>
            <div class="battery-stat-val" id="kpiModelsSupported">120+</div>
            <div class="battery-stat-lbl">Laptops Supported</div>
        </div>
    </div>

    <div class="battery-stat-card">
        <div class="battery-stat-icon" style="background: rgba(139, 92, 246, 0.12); color: #8b5cf6;">📦</div>
        <div>
            <div class="battery-stat-val" id="kpiTotalStock"><?= number_format($total_stock_count) ?></div>
            <div class="battery-stat-lbl">Packs in Stock</div>
        </div>
    </div>

    <div class="battery-stat-card">
        <div class="battery-stat-icon" style="background: rgba(245, 158, 11, 0.12); color: #f59e0b;">🏷️</div>
        <div>
            <div class="battery-stat-val">2" × 1"</div>
            <div class="battery-stat-lbl">Thermal Printable</div>
        </div>
    </div>
</section>

<!-- SEARCH HERO & CROSS-MATCH CONSOLE -->
<section class="battery-search-hero">
    <!-- Mode Switcher Tabs -->
    <div class="search-mode-tabs">
        <button type="button" class="search-mode-tab active" data-mode="smart">
            <span>🔄</span> Smart Cross-Match
        </button>
        <button type="button" class="search-mode-tab" data-mode="laptop">
            <span>💻</span> Laptop Model ➔ Match Battery
        </button>
        <button type="button" class="search-mode-tab" data-mode="battery">
            <span>🔋</span> Battery Part # ➔ Match Laptops
        </button>
        <button type="button" class="search-mode-tab" data-mode="all">
            <span>🌐</span> Full Catalog &amp; Bins
        </button>
    </div>

    <!-- Huge Touch-First Search Bar -->
    <div class="search-input-huge-wrapper">
        <span class="search-icon-left">🔎</span>
        <input type="text" id="batterySearchInput" class="search-input-huge" placeholder="Type laptop model (e.g. 'Latitude 5400', 'ThinkPad T480') or battery part # (e.g. 'WDX0R', 'CS03XL')..." autocomplete="off" autofocus>
        <button type="button" id="batterySearchClear" class="search-clear-btn" title="Clear Search">✕</button>
    </div>

    <!-- Brand Filter Pills -->
    <div class="brand-filters-shelf">
        <span style="font-size: 0.78rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase;">Brand:</span>
        <button type="button" class="brand-filter-pill active" data-brand="all">All Brands</button>
        <button type="button" class="brand-filter-pill" data-brand="Dell">Dell</button>
        <button type="button" class="brand-filter-pill" data-brand="HP">HP</button>
        <button type="button" class="brand-filter-pill" data-brand="Lenovo">Lenovo</button>
        <button type="button" class="brand-filter-pill" data-brand="Apple">Apple</button>
        <button type="button" class="brand-filter-pill" data-brand="Microsoft">Microsoft</button>
    </div>
</section>

<!-- MATCH HIGHLIGHT BANNER (Dynamic when typing a laptop model) -->
<div id="matchHighlightBanner" class="match-highlight-banner" style="display:none;">
    <div class="match-banner-text" id="matchBannerText">
        <span>🎯 Cross-Match Results:</span>
    </div>
    <div style="font-size:0.85rem; color:var(--text-secondary);">
        💡 Click on any battery's <strong>🖨️ Label</strong> button to print a 2" × 1" thermal sticker.
    </div>
</div>

<!-- RESULTS HEADER -->
<div class="flex-between" style="margin-bottom: 16px; padding: 0 4px;">
    <div id="batteryFilterCounter" style="font-size: 0.88rem; font-weight: 700; color: var(--text-secondary);">
        Showing <?= count($initial_batteries) ?> matching battery profile(s)
    </div>
    <div style="display: flex; gap: 8px;">
        <span style="font-size: 0.82rem; color: var(--text-muted); align-self: center;">
            💡 Tip: Click any laptop model tag to search for it directly
        </span>
    </div>
</div>

<!-- BATTERIES CARDS GRID -->
<div class="battery-cards-grid" id="batteryCardsGrid">
    <!-- Populated via JavaScript by batteries.js -->
</div>

<!-- MODAL: ADD / EDIT BATTERY PROFILE -->
<div id="batteryModal" class="modal-overlay" style="display:none;" onclick="if(event.target===this)closeBatteryModal()">
    <div class="modal-dialog" style="max-width: 680px;">
        <div class="modal-header">
            <h3 id="batteryModalTitle" style="margin:0;">➕ Add Battery Cross-Match Profile</h3>
            <button type="button" class="modal-close-btn" onclick="closeBatteryModal()">✕</button>
        </div>

        <form id="batteryForm" autocomplete="off">
            <input type="hidden" id="batInputId" name="id">

            <div class="modal-body" style="max-height: 75vh; overflow-y: auto; padding: 20px;">
                <div class="form-row-grid" style="display:grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
                    <div>
                        <label class="form-label" style="display:block; font-size:0.82rem; font-weight:800; margin-bottom:4px;">Brand *</label>
                        <select id="batInputBrand" name="brand" class="form-select" required style="width:100%; height:44px; border-radius:8px; border:1px solid var(--border-color); padding:0 12px; background:var(--bg-surface-2); color:var(--text-main); font-weight:700;">
                            <option value="Dell">Dell</option>
                            <option value="HP">HP</option>
                            <option value="Lenovo">Lenovo</option>
                            <option value="Apple">Apple</option>
                            <option value="Microsoft">Microsoft</option>
                            <option value="Acer">Acer</option>
                            <option value="Asus">Asus</option>
                            <option value="Toshiba">Toshiba</option>
                            <option value="Other">Other / Generic</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label" style="display:block; font-size:0.82rem; font-weight:800; margin-bottom:4px;">Primary Part Number *</label>
                        <input type="text" id="batInputPart" name="part_number" class="form-input" placeholder="e.g. WDX0R, CS03XL, 01AV421" required style="width:100%; height:44px; border-radius:8px; border:1px solid var(--border-color); padding:0 12px; background:var(--bg-surface-2); color:var(--text-main); font-weight:700; text-transform:uppercase;">
                    </div>
                </div>

                <div class="form-row-grid" style="display:grid; grid-template-columns: 1fr; margin-bottom: 14px;">
                    <div>
                        <label class="form-label" style="display:block; font-size:0.82rem; font-weight:800; margin-bottom:4px;">Model Name / Description</label>
                        <input type="text" id="batInputName" name="model_name" class="form-input" placeholder="e.g. Dell Type WDX0R 42Wh 3-Cell Battery" style="width:100%; height:44px; border-radius:8px; border:1px solid var(--border-color); padding:0 12px; background:var(--bg-surface-2); color:var(--text-main);">
                    </div>
                </div>

                <div class="form-row-grid" style="display:grid; grid-template-columns: 1fr; margin-bottom: 14px;">
                    <div>
                        <label class="form-label" style="display:block; font-size:0.82rem; font-weight:800; margin-bottom:4px;">Alternative DP/N, FRU, or Spare Part Numbers (Comma Separated)</label>
                        <input type="text" id="batInputAliases" name="aliases" class="form-input" placeholder="e.g. 3CRH3, T2JX4, FC92N, 0WDX0R" style="width:100%; height:44px; border-radius:8px; border:1px solid var(--border-color); padding:0 12px; background:var(--bg-surface-2); color:var(--text-main); font-family:var(--font-mono); font-size:0.85rem;">
                    </div>
                </div>

                <!-- ELECTRICAL SPECS ROW -->
                <div class="form-row-grid" style="display:grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 14px;">
                    <div>
                        <label class="form-label" style="display:block; font-size:0.82rem; font-weight:800; margin-bottom:4px;">Capacity (Wh)</label>
                        <input type="text" id="batInputWh" name="capacity_wh" class="form-input" placeholder="e.g. 42Wh" style="width:100%; height:44px; border-radius:8px; border:1px solid var(--border-color); padding:0 10px; background:var(--bg-surface-2); color:var(--text-main); font-weight:700;">
                    </div>
                    <div>
                        <label class="form-label" style="display:block; font-size:0.82rem; font-weight:800; margin-bottom:4px;">Voltage</label>
                        <input type="text" id="batInputVoltage" name="voltage" class="form-input" placeholder="e.g. 11.4V" style="width:100%; height:44px; border-radius:8px; border:1px solid var(--border-color); padding:0 10px; background:var(--bg-surface-2); color:var(--text-main); font-weight:700;">
                    </div>
                    <div>
                        <label class="form-label" style="display:block; font-size:0.82rem; font-weight:800; margin-bottom:4px;">Cells</label>
                        <input type="text" id="batInputCells" name="cell_count" class="form-input" placeholder="e.g. 3-Cell" style="width:100%; height:44px; border-radius:8px; border:1px solid var(--border-color); padding:0 10px; background:var(--bg-surface-2); color:var(--text-main);">
                    </div>
                    <div>
                        <label class="form-label" style="display:block; font-size:0.82rem; font-weight:800; margin-bottom:4px;">Chemistry</label>
                        <select id="batInputChemistry" name="chemistry" class="form-select" style="width:100%; height:44px; border-radius:8px; border:1px solid var(--border-color); padding:0 8px; background:var(--bg-surface-2); color:var(--text-main);">
                            <option value="Li-ion">Li-ion</option>
                            <option value="Li-Polymer">Li-Polymer</option>
                        </select>
                    </div>
                </div>

                <!-- COMPATIBLE LAPTOPS TEXTAREA -->
                <div style="margin-bottom: 14px;">
                    <label class="form-label" style="display:block; font-size:0.82rem; font-weight:800; margin-bottom:4px;">
                        Compatible Laptop Models * <span style="font-weight:normal; color:var(--text-muted);">(Comma separated list of all supported laptops)</span>
                    </label>
                    <textarea id="batInputModels" name="compatible_models" rows="4" class="form-textarea" placeholder="e.g. Dell Latitude 5400, Dell Latitude 5410, Dell Latitude 5500, Dell Inspiron 7590" required style="width:100%; border-radius:8px; border:1px solid var(--border-color); padding:10px 12px; background:var(--bg-surface-2); color:var(--text-main); font-size:0.9rem; line-height:1.4; resize:vertical;"></textarea>
                </div>

                <!-- WAREHOUSE LOCATION & STOCK -->
                <div class="form-row-grid" style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-bottom: 14px;">
                    <div>
                        <label class="form-label" style="display:block; font-size:0.82rem; font-weight:800; margin-bottom:4px;">Warehouse Bin</label>
                        <input type="text" id="batInputLocation" name="warehouse_location" class="form-input" placeholder="e.g. Bin BAT-A03" style="width:100%; height:44px; border-radius:8px; border:1px solid var(--border-color); padding:0 10px; background:var(--bg-surface-2); color:var(--text-main); font-weight:700;">
                    </div>
                    <div>
                        <label class="form-label" style="display:block; font-size:0.82rem; font-weight:800; margin-bottom:4px;">In-Stock Qty</label>
                        <input type="number" id="batInputQty" name="qty_in_stock" min="0" value="0" class="form-input" style="width:100%; height:44px; border-radius:8px; border:1px solid var(--border-color); padding:0 10px; background:var(--bg-surface-2); color:var(--text-main); font-weight:700;">
                    </div>
                    <div>
                        <label class="form-label" style="display:block; font-size:0.82rem; font-weight:800; margin-bottom:4px;">Condition</label>
                        <select id="batInputCondition" name="condition" class="form-select" style="width:100%; height:44px; border-radius:8px; border:1px solid var(--border-color); padding:0 8px; background:var(--bg-surface-2); color:var(--text-main);">
                            <option value="Tested OEM 80%+">Tested OEM 80%+</option>
                            <option value="Brand New OEM">Brand New OEM</option>
                            <option value="Grade A Refurb">Grade A Refurb</option>
                            <option value="Untested">Untested</option>
                            <option value="Recycle">Recycle</option>
                        </select>
                    </div>
                </div>

                <!-- NOTES -->
                <div>
                    <label class="form-label" style="display:block; font-size:0.82rem; font-weight:800; margin-bottom:4px;">Warehouse / Technician Notes</label>
                    <input type="text" id="batInputNotes" name="notes" class="form-input" placeholder="e.g. Internal ribbon connector. Requires T5 Torx." style="width:100%; height:44px; border-radius:8px; border:1px solid var(--border-color); padding:0 12px; background:var(--bg-surface-2); color:var(--text-main);">
                </div>
            </div>

            <div class="modal-footer" style="display:flex; justify-content:space-between; padding:16px 20px; border-top:1px solid var(--border-color);">
                <button type="button" class="btn btn-secondary" onclick="closeBatteryModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <span>💾 Save Battery Profile</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: BATTERY THERMAL LABEL PRINT CONFIGURATION -->
<div id="batteryPrintModal" class="modal-overlay" style="display:none;" onclick="if(event.target===this)closeBatteryPrintModal()">
    <div class="modal-dialog modal-print-dialog" style="max-width: 480px;">
        <div class="modal-header">
            <div>
                <h3 id="batteryPrintTitle" style="margin:0;">🖨️ Print Battery Label</h3>
                <p style="margin:2px 0 0 0; font-size:0.85rem; color:var(--text-secondary);">2" × 1" Thermal Roll Format</p>
            </div>
            <button type="button" class="modal-close-btn" onclick="closeBatteryPrintModal()">✕</button>
        </div>

        <div class="modal-body" style="padding: 20px;">
            <div style="background:var(--bg-surface-2); border:1px solid var(--border-color); border-radius:var(--border-radius-md); padding:16px; margin-bottom:18px; text-align:center;">
                <div style="font-size:2rem; margin-bottom:6px;">🔋</div>
                <div style="font-weight:800; font-size:1rem; margin-bottom:4px;">High-Density Thermal Barcode Sticker</div>
                <p style="font-size:0.82rem; color:var(--text-secondary); margin:0;">
                    Optimized for 203 DPI and 300 DPI thermal print heads (Zebra, Rollo, Brother, Dymo).
                </p>
            </div>

            <div class="qty-control-group" style="display:flex; align-items:center; justify-content:center; gap:12px; margin-bottom:16px;">
                <label for="batPrintQty" style="font-weight:700; font-size:0.9rem;">Copies:</label>
                <input type="number" id="batPrintQty" value="1" min="1" max="100" class="qty-input-field" style="width:70px; height:44px; text-align:center; font-weight:800; font-size:1.1rem; border-radius:8px; border:1px solid var(--border-color); background:var(--bg-panel); color:var(--text-main);">
            </div>
        </div>

        <div class="modal-footer print-modal-actions" style="display:flex; gap:10px; padding:16px 20px;">
            <button type="button" id="btnBatDirectPrint" class="btn btn-success btn-large" style="flex:1;">
                <span>🖨️ Direct Web Print</span>
            </button>
            <button type="button" id="btnBatOdtPrint" class="btn btn-secondary btn-large" title="Generate OpenDocument Flat XML (.odt) for LibreOffice">
                <span>📄 Windows ODT</span>
            </button>
        </div>
    </div>
</div>

<!-- CLIENT CATALOG & CONTROLLER SCRIPTS -->
<script src="assets/js/battery_catalog.js?v=<?= filemtime(__DIR__ . '/assets/js/battery_catalog.js') ?>"></script>
<script src="assets/js/batteries.js?v=<?= filemtime(__DIR__ . '/assets/js/batteries.js') ?>"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
