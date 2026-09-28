<?php
// views/product_modal.php
// Full Hardware Specifications & Condition Details Modal
?>
<div id="productDetailsModal" class="product-details-modal" style="display: none;" aria-hidden="true" role="dialog" aria-labelledby="pdmTitle">
    <div class="product-details-backdrop" onclick="closeProductModal()"></div>
    <div class="product-details-dialog" role="document">
        <header class="pdm-header">
            <div class="pdm-header-left">
                <span id="pdmCategoryBadge" class="card-origin-badge">Hardware</span>
                <span id="pdmLocationBadge" class="card-origin-badge" style="display:none;"></span>
            </div>
            <button type="button" class="pdm-close-btn" onclick="closeProductModal()" aria-label="Close modal">&times;</button>
        </header>

        <div class="pdm-body">
            <!-- Hero Row: Product Photography & Primary Buy Box -->
            <div class="pdm-hero">
                <div class="pdm-img-wrapper">
                    <img id="pdmImage" src="" alt="Product Photo" class="pdm-image" onerror="this.onerror=null; this.src='images/placeholder.svg';">
                </div>
                <div class="pdm-info">
                    <h2 id="pdmTitle" class="pdm-title"></h2>
                    
                    <div id="pdmChips" class="card-chips" style="margin: 0.75rem 0;"></div>

                    <div class="pdm-price-row">
                        <div class="pdm-price" id="pdmPrice">$0.00</div>
                        <span id="pdmStockBadge" class="wh-qty-badge">Stock: 1</span>
                    </div>

                    <p id="pdmSummary" class="pdm-summary"></p>

                    <form method="POST" action="cart.php" class="pdm-buy-form">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="product_id" id="pdmProductId" value="">
                        <button type="submit" class="buy-btn pdm-buy-btn">Acquire Hardware</button>
                    </form>
                </div>
            </div>

            <!-- Details Content Sections -->
            <div class="pdm-sections">
                <!-- Section 1: System Specifications -->
                <div class="pdm-section" id="pdmSpecsSection">
                    <h3 class="pdm-sec-title">💻 System Specifications</h3>
                    <table class="specs-table" id="pdmSpecsTable">
                        <tbody></tbody>
                    </table>
                </div>

                <!-- Section 2: Condition & Testing Notes -->
                <div class="pdm-section" id="pdmConditionSection" style="display:none;">
                    <h3 class="pdm-sec-title">🔍 Condition &amp; Testing Notes</h3>
                    <div class="condition-report-box" id="pdmConditionBox"></div>
                </div>

                <!-- Section 3: Included Accessories -->
                <div class="pdm-section" id="pdmAccessoriesSection" style="display:none;">
                    <h3 class="pdm-sec-title">📦 Included Accessories</h3>
                    <div class="accessories-box" id="pdmAccessoriesBox"></div>
                </div>

                <!-- Section 4: Terms of Sale -->
                <div class="pdm-section">
                    <h3 class="pdm-sec-title">⚖️ Terms of Sale &amp; Return Policy</h3>
                    <div class="terms-notice-box" id="pdmTermsBox">
                        Sold strictly <strong>AS-IS</strong> for parts, repair, or tech projects pursuant to the <a href="terms.php" target="_blank">IQA Metal Terms of Sale</a>. No returns or exchanges.
                    </div>
                </div>
            </div>
        </div>

        <footer class="pdm-footer">
            <button type="button" class="wh-btn-secondary" onclick="closeProductModal()">Close</button>
            <form method="POST" action="cart.php" style="margin:0;">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" id="pdmFooterProductId" value="">
                <button type="submit" class="buy-btn">Acquire Item</button>
            </form>
        </footer>
    </div>
</div>
