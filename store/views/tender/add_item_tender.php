<?php
// views/tender/add_item_tender.php
// Quick Manual Custom Store Product Creator for Store Tenders
require_once __DIR__ . '/../../core/Tender.php';

if (!Tender::isTenderMode()) {
    return;
}
$activeCategory = $activeCategory ?? '';
?>
<div class='card add-item-card'>
    <form method='POST' action='tender_action.php' enctype='multipart/form-data' style='display: flex; flex-direction: column; height: 100%;'>
        <input type='hidden' name='action' value='add'>
        
        <button type="button" onclick="openWarehouseModal()" class="wh-quick-open-btn">
            <span>⚡ Post from Warehouse Stock</span>
        </button>

        <div style="display: flex; align-items: center; width: 100%; margin-bottom: 0.75rem;">
            <div style="flex-grow: 1; height: 1px; background: var(--card-border);"></div>
            <span style="padding: 0 0.5rem; font-size: 0.7rem; color: var(--light-text); text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">or add manual custom item</span>
            <div style="flex-grow: 1; height: 1px; background: var(--card-border);"></div>
        </div>

        <div class="edit-inputs-group">
            <input type='text' class="edit-input" name='brand' placeholder='Brand' required>
            <input type='text' class="edit-input" name='model' placeholder='Model' required>
        </div>

        <textarea class="edit-textarea" name='specs_json' placeholder='Specifications / Hardware Notes' rows="3" required></textarea>
        
        <select class="edit-select" name='sector'>
            <option value='Laptops' <?= (stripos($activeCategory, 'laptop') !== false || stripos($activeCategory, 'computer') !== false ? 'selected' : '') ?>>Category: Laptops</option>
            <option value='Desktops' <?= (stripos($activeCategory, 'desktop') !== false ? 'selected' : '') ?>>Category: Desktops</option>
            <option value='Servers' <?= (stripos($activeCategory, 'server') !== false ? 'selected' : '') ?>>Category: Servers</option>
            <option value='Parts' <?= (stripos($activeCategory, 'part') !== false ? 'selected' : '') ?>>Category: Parts</option>
        </select>

        <div style="display: flex; gap: 0.5rem; margin-bottom: 0.75rem;">
            <div class="edit-price-wrapper" style="flex: 1;" title="Retail Price">
                <span style="color: var(--light-text); font-weight: 700; font-size: 0.95rem;">$</span>
                <input type='number' class="price-input" name='price' step='0.01' placeholder='Price' required>
            </div>
            <div class="edit-price-wrapper" style="width: 85px;" title="Initial Quantity">
                <span style="color: var(--light-text); font-size: 0.72rem; font-weight: 800;">QTY</span>
                <input type='number' class="price-input" name='quantity' min='0' step='1' value='1' style="font-size: 0.95rem; text-align: center;" required>
            </div>
        </div>

        <label class="file-upload-btn">
            📷 Choose Photo (Optional)
            <input type='file' name='photo' accept='image/*' style='display:none;' onchange='previewAddPhoto(this)'>
        </label>
        <div id='add-photo-preview'></div>

        <button type='submit' class='buy-btn' style="width: 100%; justify-content: center; margin-top: auto;">
            + Add to Catalog
        </button>
    </form>
</div>
