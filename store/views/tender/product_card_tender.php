<?php
// views/tender/product_card_tender.php
// Modular inline product editing card for Store Tenders
// $p is passed in containing product attributes
?>
<article class='card edit-mode-card'>
    <!-- Top Bar: Shelf Origin Badge & Tender Action Toolbar -->
    <div class="card-top-bar">
        <div>
            <?php if (!empty($p['location_code']) && !empty($p['is_warehouse'])): ?>
                <span class="card-origin-badge" title="Imported from Physical Warehouse Shelf">📍 <?= htmlspecialchars($p['location_code']) ?></span>
            <?php else: ?>
                <span class="card-origin-badge custom" title="Custom store item">🏪 Store Custom</span>
            <?php endif; ?>
        </div>

        <div class="card-admin-floating-actions">
            <?php if (!empty($p['is_warehouse'])): ?>
                <form method='POST' action='tender_action.php' onsubmit='return confirm("Unpost this product from the storefront? It will remain safely stored in warehouse inventory.")' style="margin: 0;">
                    <input type='hidden' name='action' value='unpost'>
                    <input type='hidden' name='id' value='<?= htmlspecialchars($p['id']) ?>'>
                    <button type='submit' class="unpost-btn" title="Unpost from Store (Keep in Warehouse)">
                        Unpost
                    </button>
                </form>
            <?php endif; ?>

            <form method='POST' action='tender_action.php' onsubmit='return confirm("Delete this item permanently?")' style="margin: 0;">
                <input type='hidden' name='action' value='delete'>
                <input type='hidden' name='id' value='<?= htmlspecialchars($p['id']) ?>'>
                <button type='submit' class="delete-btn" title="Delete Product Permanently">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="currentColor" viewBox="0 0 16 16">
                      <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/>
                      <path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/>
                    </svg>
                </button>
            </form>
        </div>
    </div>

    <!-- Tender Edit Form -->
    <form method='POST' action='tender_action.php' enctype='multipart/form-data' class="edit-form">
        <input type='hidden' name='action' value='edit'>
        <input type='hidden' name='id' value='<?= htmlspecialchars($p['id']) ?>'>

        <figure class='img-wrapper'>
            <img src='<?= htmlspecialchars($p['image']) ?>' class='card-img' loading='lazy' onerror="this.onerror=null; this.src='images/placeholder.svg';">
            <figcaption>
                <label class="edit-photo-label">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="currentColor" viewBox="0 0 16 16">
                      <path d="M10.5 8.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0z"/>
                      <path d="M2 4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-1.172a2 2 0 0 1-1.414-.586l-.828-.828A2 2 0 0 0 9.172 2H6.828a2 2 0 0 0-1.414.586l-.828.828A2 2 0 0 1 3.172 4H2zm.5 2a.5.5 0 1 1 0-1 .5.5 0 0 1 0 1zm9 2.5a3.5 3.5 0 1 1-7 0 3.5 3.5 0 0 1 7 0z"/>
                    </svg>
                    Update Photo
                    <input type='file' name='photo' accept='image/*' style='display:none;' onchange='previewImage(this)'>
                </label>
            </figcaption>
        </figure>

        <div class="edit-inputs-group">
            <input type='text' class="edit-input" name='brand' value='<?= htmlspecialchars($p['brand']) ?>' placeholder="Brand" required>
            <input type='text' class="edit-input" name='model' value='<?= htmlspecialchars($p['model']) ?>' placeholder="Model" required>
        </div>

        <textarea class="edit-textarea" name='specs_json' rows='3' placeholder="Specifications & Details" required><?= htmlspecialchars($p['raw_specs']) ?></textarea>
        
        <select class="edit-select" name='sector'>
            <option value='Laptops' <?= (stripos($p['category'], 'laptop') !== false ? 'selected' : '') ?>>Category: Laptops</option>
            <option value='Desktops' <?= (stripos($p['category'], 'desktop') !== false ? 'selected' : '') ?>>Category: Desktops</option>
            <option value='Servers' <?= (stripos($p['category'], 'server') !== false ? 'selected' : '') ?>>Category: Servers</option>
            <option value='Parts' <?= (stripos($p['category'], 'part') !== false ? 'selected' : '') ?>>Category: Parts</option>
        </select>

        <footer class='card-footer'>
            <div class='edit-price-wrapper' style="flex: 1;" title="Store Retail Price">
                <span style="color: var(--light-text); font-weight: 700; font-size: 0.95rem;">$</span>
                <input type='number' class="price-input" name='price' step='0.01' value='<?= htmlspecialchars($p['price']) ?>' required> 
            </div>

            <div class='edit-price-wrapper' style="width: 85px;" title="Quantity in Stock">
                <span style="color: var(--light-text); font-size: 0.72rem; font-weight: 800;">QTY</span>
                <input type='number' class="price-input" name='quantity' min="0" step='1' value='<?= htmlspecialchars($p['quantity'] ?? 0) ?>' style="font-size: 0.95rem; text-align: center;" required> 
            </div>

            <button type='submit' class='buy-btn update-btn'>Save</button>
        </footer>
    </form>

    <div style="margin-top: 0.75rem; border-top: 1px dashed var(--card-border); padding-top: 0.65rem;">
        <button type="button" class="view-specs-btn" onclick="openProductModal(<?= (int)$p['id'] ?>)" style="margin-bottom: 0;">
            <span>👁️ Preview Customer Details Modal</span>
            <span class="view-specs-arrow">&rarr;</span>
        </button>
    </div>

    <!-- Encoded JSON payload for zero-latency modal rendering in Tender mode -->
    <script type="application/json" id="product-json-<?= (int)$p['id'] ?>">
        <?= json_encode($p, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
    </script>
</article>
