<?php
// labels/labels.php
// Warehouse Inventory Tracker & Management Page
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/hardware_mapping.php';
require_once __DIR__ . '/includes/header.php';

// Initial server-side load (Limit 200 items)
$inventory = [];
try {
    $stmt = $pdo_labels->query("SELECT * FROM items ORDER BY created_at DESC LIMIT 200");
    $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>

<!-- INVENTORY HEADER & FILTER BAR -->
<div class="panel flex-between" style="margin-bottom: 20px; padding: 20px 28px;">
    <div>
        <h1 style="font-size: 1.6rem; font-weight: 900; margin-bottom: 4px;">📦 Warehouse Inventory Tracker</h1>
        <?= UI::csrf_field() ?>
        <p style="color: var(--text-secondary); font-size: 0.95rem;">
            Real-time hardware stock. Search, inline-edit, bulk update, and print thermal labels.
        </p>
    </div>
    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
        <button type="button" id="btnExportCSV" class="btn btn-secondary" title="Export filtered records to Excel CSV">
            <span>📥 Export CSV</span>
        </button>
        <a href="new_label.php" class="btn btn-success">
            <span>➕ Intake New Hardware</span>
        </a>
    </div>
</div>

<!-- FILTER & SEARCH PANEL -->
<div class="panel" style="padding: 18px 24px; margin-bottom: 20px;">
    <!-- Bulk Action Bar (Visible when items selected) -->
    <div id="bulkActionBar" class="bulk-action-bar-modern" style="display:none;">
        <div class="bulk-count-badge">
            <span id="selectedCount">0</span> items selected
        </div>
        <div class="bulk-controls-group">
            <select id="bulkStatus" class="bulk-select">
                <option value="">-- Change Condition --</option>
                <option value="Refurbished">✅ Refurbished / Tested</option>
                <option value="Untested">⏳ Untested</option>
                <option value="For Parts">🛠️ For Parts / Defect</option>
            </select>
            <input type="text" id="bulkLocation" placeholder="New Location (e.g. Shelf B-2)" class="bulk-input">
            <button type="button" id="applyBulkBtn" class="btn btn-sm btn-success">Apply Changes</button>
            <button type="button" id="cancelBulkBtn" class="btn btn-sm btn-secondary">Deselect All</button>
        </div>
    </div>

    <!-- Filter Pills Row -->
    <div class="filter-pills-shelf">
        <span style="font-size: 0.78rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase;">Filter Status:</span>
        <button type="button" class="filter-status-pill active" data-status="In Warehouse">📦 In Warehouse</button>
        <button type="button" class="filter-status-pill" data-status="Refurbished">✅ Refurbished</button>
        <button type="button" class="filter-status-pill" data-status="Untested">⏳ Untested</button>
        <button type="button" class="filter-status-pill" data-status="For Parts">🛠️ For Parts</button>
        <button type="button" class="filter-status-pill" data-status="Sold">🚚 Sold / Archive</button>
        <button type="button" class="filter-status-pill" data-status="all">🌐 All Records</button>
    </div>

    <!-- Search Bar with Instant Debounce -->
    <div class="search-filter-controls">
        <div class="search-input-box" style="flex: 1; position: relative;">
            <span style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); font-size: 1rem; color: var(--text-muted);">🔎</span>
            <input type="text" id="filterSearch" placeholder="Search by ID, Brand, Model, S/N, Location, or Processor..." autocomplete="off" style="padding-left: 42px; height: 48px; border-radius: var(--border-radius-full);">
            <button type="button" id="clearFilterBtn" class="clear-search-btn" title="Clear Search">✕</button>
        </div>

        <div id="filterMsg" class="filter-results-counter">
            Showing <?= count($inventory) ?> item(s)
        </div>
    </div>
</div>

<!-- INVENTORY DATA TABLE PANEL -->
<div class="panel" style="padding: 0; overflow: hidden;">
    <div class="table-container">
        <table class="data-table" id="inventoryTable">
            <thead>
                <tr>
                    <th style="width: 40px; text-align: center;">
                        <input type="checkbox" id="selectAll" class="row-select" title="Select All">
                    </th>
                    <th>Device &amp; S/N</th>
                    <th>Processor (CPU)</th>
                    <th>RAM &amp; Storage</th>
                    <th>Location</th>
                    <th>Condition</th>
                    <th>Added</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody id="inventoryTableBody">
                <!-- Hydrated via JavaScript buildRow() or window.INITIAL_INVENTORY -->
            </tbody>
        </table>
    </div>
</div>

<!-- DELETE CONFIRMATION MODAL -->
<div id="deleteModal" class="modal-overlay" style="display:none;" onclick="if(event.target===this)closeDeleteModal()">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 style="color:var(--color-danger);">🗑️ Confirm Permanent Delete</h3>
            <button type="button" class="modal-close-btn" onclick="closeDeleteModal()">✕</button>
        </div>
        <div class="modal-body">
            <p id="deleteConfirmText" style="font-size: 0.95rem; line-height: 1.5; color: var(--text-main); margin-bottom: 12px;">
                Are you sure you want to remove this hardware item from inventory?
            </p>
            <div style="padding: 12px; background: var(--color-danger-soft); border-radius: var(--border-radius-sm); color: var(--color-danger); font-size: 0.82rem;">
                ⚠️ This will permanently remove the record from <code>labels.sqlite</code>.
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
            <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Yes, Delete Item</button>
        </div>
    </div>
</div>

<!-- ROW TEMPLATE FOR FAST HYDRATION -->
<template id="inventoryRowTemplate">
    <tr data-id="">
        <td style="text-align: center;">
            <input type="checkbox" class="row-select">
        </td>
        <td>
            <a href="#" class="tpl-link font-bold text-main" style="font-size: 0.95rem;">BRAND MODEL</a>
            <div class="tpl-series text-secondary" style="font-size: 0.78rem;">SERIES</div>
            <div class="tpl-sn" style="font-size: 0.72rem; font-family: var(--font-mono); color: var(--text-muted); margin-top: 2px;">
                S/N: <span class="tpl-sn-text">—</span>
            </div>
        </td>
        <td>
            <div class="tpl-cpu-specs font-bold text-main" style="font-size: 0.85rem;">SPECS</div>
            <div class="tpl-cpu-gen text-secondary" style="font-size: 0.75rem;">GEN</div>
        </td>
        <td>
            <div style="font-size: 0.85rem; font-weight: 700;">
                <span class="tpl-ram">16 GB</span> &nbsp;/&nbsp; <span class="tpl-storage">256 GB</span>
            </div>
            <div class="tpl-battery text-muted" style="font-size: 0.72rem;">Batt: YES</div>
        </td>
        <td>
            <span class="badge badge-neutral tpl-location" style="font-size: 0.75rem; font-weight: 800;">📍 A-1-1</span>
        </td>
        <td>
            <span class="badge tpl-badge">UNTESTED</span>
            <div class="tpl-status-sub" style="font-size: 0.68rem; color: var(--text-muted); margin-top: 2px;">In Warehouse</div>
        </td>
        <td class="tpl-added text-secondary" style="font-size: 0.78rem; white-space: nowrap;">DATE</td>
        <td style="text-align: right; white-space: nowrap;">
            <div class="action-strip">
                <button type="button" class="btn btn-sm btn-success tpl-btn-print" title="Thermal Label Direct Print">🖨️ Print</button>
                <button type="button" class="btn btn-sm btn-secondary tpl-btn-view" title="Quick View Specs">👁️</button>
                <button type="button" class="btn btn-sm btn-secondary tpl-btn-edit" title="Edit Hardware Record">✏️</button>
                <button type="button" class="btn btn-sm btn-danger tpl-btn-del" title="Delete Hardware Record">🗑️</button>
            </div>
        </td>
    </tr>
</template>

<!-- INLINE EDIT ROW TEMPLATE -->
<template id="editRowTemplate">
    <tr class="edit-mode-row" style="background: var(--bg-surface-2) !important;">
        <td></td>
        <td>
            <input type="hidden" name="id">
            <input type="text" class="edit-field" name="<?= HW_FIELDS['BRAND'] ?>" placeholder="Brand" style="margin-bottom:4px; height:34px; font-size:0.85rem;">
            <input type="text" class="edit-field" name="<?= HW_FIELDS['MODEL'] ?>" placeholder="Model" style="margin-bottom:4px; height:34px; font-size:0.85rem;">
            <input type="text" class="edit-field" name="<?= HW_FIELDS['SERIES'] ?>" placeholder="Series" style="margin-bottom:4px; height:34px; font-size:0.85rem;">
            <input type="text" class="edit-field" name="<?= HW_FIELDS['SERIAL_NUMBER'] ?>" placeholder="Serial S/N" style="height:34px; font-size:0.75rem; font-family:var(--font-mono);">
        </td>
        <td>
            <input type="text" class="edit-field" name="<?= HW_FIELDS['CPU_SPECS'] ?>" placeholder="CPU Model" style="margin-bottom:4px; height:34px; font-size:0.85rem;">
            <input type="text" class="edit-field" name="<?= HW_FIELDS['CPU_GEN'] ?>" placeholder="Generation" style="height:34px; font-size:0.85rem;">
        </td>
        <td>
            <input type="text" class="edit-field" name="<?= HW_FIELDS['RAM'] ?>" placeholder="RAM" style="margin-bottom:4px; height:34px; font-size:0.85rem;">
            <input type="text" class="edit-field" name="<?= HW_FIELDS['STORAGE'] ?>" placeholder="Storage" style="height:34px; font-size:0.85rem;">
        </td>
        <td>
            <input type="text" class="edit-field" name="<?= HW_FIELDS['LOCATION'] ?>" placeholder="Location" style="height:34px; font-size:0.85rem;">
        </td>
        <td>
            <select class="edit-field" name="<?= HW_FIELDS['DESCRIPTION'] ?>" style="height:34px; font-size:0.85rem;">
                <option value="Untested">Untested</option>
                <option value="Refurbished">Refurbished</option>
                <option value="For Parts">For Parts</option>
            </select>
        </td>
        <td class="tpl-edit-added" style="font-size:0.75rem; color:var(--text-muted); vertical-align:middle;">DATE</td>
        <td style="text-align: right; vertical-align: middle;">
            <div style="display:flex; flex-direction:column; gap:4px; align-items:flex-end;">
                <button type="button" class="btn btn-sm btn-success save-edit-btn">💾 Save</button>
                <button type="button" class="btn btn-sm btn-secondary cancel-edit-btn">✕ Cancel</button>
            </div>
        </td>
    </tr>
</template>

<style>
.filter-pills-shelf {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 14px;
}

.filter-status-pill {
    padding: 6px 14px;
    border-radius: var(--border-radius-full);
    border: 1px solid var(--border-color);
    background: var(--bg-surface-2);
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.15s ease;
}

.filter-status-pill:hover {
    color: var(--text-main);
    border-color: var(--accent-color);
}

.filter-status-pill.active {
    background: var(--accent-color);
    color: #ffffff;
    border-color: var(--accent-color);
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
}

.search-filter-controls {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
}

.clear-search-btn {
    position: absolute;
    right: 14px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--text-muted);
    cursor: pointer;
    font-size: 0.9rem;
    padding: 4px;
}

.clear-search-btn:hover {
    color: var(--text-main);
}

.filter-results-counter {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--text-secondary);
    white-space: nowrap;
}

/* Bulk Bar Modern */
.bulk-action-bar-modern {
    background: var(--text-main);
    color: var(--bg-page);
    border-radius: var(--border-radius-md);
    padding: 12px 18px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    animation: fadeIn 0.2s ease;
}

.bulk-count-badge {
    font-weight: 800;
    font-size: 0.95rem;
}

.bulk-controls-group {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.bulk-select, .bulk-input {
    height: 36px;
    border-radius: var(--border-radius-sm);
    padding: 0 10px;
    font-size: 0.85rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
    background: var(--bg-panel);
    color: var(--text-main);
}
</style>

<!-- Inject Initial Data -->
<script>
    window.INITIAL_INVENTORY = <?= json_encode($inventory) ?>;
</script>

<!-- Modular Inventory Controller -->
<script src="assets/js/labels.js?v=<?= filemtime(__DIR__ . '/assets/js/labels.js') ?>"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
