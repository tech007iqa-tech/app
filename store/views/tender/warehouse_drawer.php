<?php
// views/tender/warehouse_drawer.php
// Slide-over Warehouse Stock Drawer for Store Tenders
require_once __DIR__ . '/../../core/Tender.php';

if (!Tender::isTenderMode()) {
    return;
}

if (!isset($inventory)) {
    require_once __DIR__ . '/../../core/Inventory.php';
    require_once __DIR__ . '/../../core/db.php';
    $inventory = new Inventory($db);
}

$whStockCount = $inventory->getWarehouseStockCount();
?>
<!-- Warehouse Inventory Drawer -->
<div id="warehouseModal" class="wh-modal" style="display: none;">
    <div class="wh-modal-backdrop" onclick="closeWarehouseModal()"></div>
    <div class="wh-modal-dialog">
        <div class="wh-modal-header">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <h2 style="font-size: 1.35rem; font-weight: 700; color: var(--text-color); margin: 0;">
                    📦 Warehouse Inventory
                </h2>
                <span class="wh-counter-badge" id="whModalBadge"><?= $whStockCount ?> Units Available</span>
            </div>
            <button type="button" class="wh-close-btn" onclick="closeWarehouseModal()" aria-label="Close">&times;</button>
        </div>

        <div class="wh-modal-subbar">
            <p style="font-size: 0.85rem; color: var(--light-text); margin: 0 0 0.75rem 0;">
                Live inventory loaded from <strong>warehouse.db</strong>. Customize retail price, quantity, specs, or photo before publishing to the public store.
            </p>
            
            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
                <div class="wh-search-box">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16" style="opacity: 0.6;">
                        <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/>
                    </svg>
                    <input type="text" id="whSearchInput" placeholder="Search models, brands, specs, or shelf..." oninput="debounceWhSearch()">
                </div>
                
                <div class="wh-sector-pills" id="whSectorPills">
                    <button type="button" class="wh-pill active" onclick="setWhSector('all', this)">All</button>
                    <button type="button" class="wh-pill" onclick="setWhSector('laptops', this)">Laptops</button>
                    <button type="button" class="wh-pill" onclick="setWhSector('desktops', this)">Desktops</button>
                    <button type="button" class="wh-pill" onclick="setWhSector('gaming', this)">Gaming</button>
                    <button type="button" class="wh-pill" onclick="setWhSector('servers', this)">Servers</button>
                    <button type="button" class="wh-pill" onclick="setWhSector('parts', this)">Parts</button>
                </div>
            </div>
        </div>

        <div class="wh-modal-body" id="whItemsContainer">
            <div class="wh-grid" id="whGrid">
                <!-- Dynamically populated by warehouse_drawer.js -->
            </div>
            
            <div class="wh-empty-state" id="whEmptyMsg" style="display:none;">
                <p style="font-size: 1.1rem; font-weight: 600;">No warehouse items found.</p>
                <p style="font-size: 0.85rem; color: var(--light-text);">Try adjusting your search query or category filter.</p>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/warehouse_drawer.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    triggerWhFetch();
});
</script>
