<?php
/**
 * Trends Dialog Modals Partial
 * Encapsulates the Order Manifest Details popup and the CPU Family Pricing & Sales Breakdown modal.
 */
?>
<!-- Order Preview Modal -->
<div id="orderPreviewModal" class="modal-overlay no-print" onclick="if(event.target === this) closeOrderPreviewModal()" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); backdrop-filter:blur(4px); z-index:1000; align-items:center; justify-content:center;">
    <div class="modal-box" onclick="event.stopPropagation()" style="background:var(--bg-panel); border-radius:20px; width:90%; max-width:650px; padding:25px; box-shadow:var(--shadow-lg); border: 1px solid var(--border-color); display:flex; flex-direction:column; max-height:85vh;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 15px;">
            <div style="display:flex; align-items:center; gap:10px;">
                <span style="font-size: 1.5rem;">📦</span>
                <div>
                    <h3 id="preview-order-id" style="font-weight: 800; font-size: 1.25rem; margin:0; font-family: monospace; color: var(--text-main);">Order</h3>
                    <span id="preview-company-name" style="font-size: 0.85rem; font-weight: 700; color: var(--accent-color);">Account Name</span>
                </div>
            </div>
            <button type="button" onclick="closeOrderPreviewModal()" style="background:none; border:none; cursor:font-size:1.5rem; color:var(--text-secondary); opacity:0.6; transition: opacity 0.2s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.6">&times;</button>
        </div>

        <div id="preview-loading" style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px 0; gap: 15px;">
            <div class="preview-spinner" style="width: 40px; height: 40px; border: 4px solid var(--border-color); border-top-color: var(--accent-color); border-radius: 50%; animation: spin 1s linear infinite;"></div>
            <span style="font-size: 0.9rem; font-weight: 600; color: var(--text-secondary);">Loading manifest details...</span>
        </div>

        <div id="preview-error" style="display:none; text-align: center; padding: 30px 0; color: #ef4444; font-weight: 700;">
            ⚠️ Failed to load order details.
        </div>

        <div id="preview-body" style="display:none; overflow-y:auto; flex:1; padding-right:5px;">
            <div style="display:flex; justify-content:space-between; margin-bottom: 20px; font-size: 0.85rem; background: var(--bg-surface-2); padding: 12px 16px; border-radius: 10px;">
                <div>
                    <span style="color:var(--text-secondary); font-weight: 600;">Status:</span>
                    <span id="preview-status" class="order-badge" style="font-weight: 800; text-transform: uppercase; margin-left: 5px;">Active</span>
                </div>
                <div>
                    <span style="color:var(--text-secondary); font-weight: 600;">Date Created:</span>
                    <span id="preview-date" style="font-weight: 700; color: var(--text-main); margin-left: 5px;">-</span>
                </div>
            </div>

            <table class="preview-table" style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color); font-size: 0.75rem; text-transform: uppercase; color: var(--text-secondary); font-weight: 800;">
                        <th style="padding: 10px 0;">Item Description</th>
                        <th style="padding: 10px 0; text-align: center; width: 60px;">Qty</th>
                        <th style="padding: 10px 0; text-align: right; width: 100px;">Price</th>
                        <th style="padding: 10px 0; text-align: right; width: 100px;">Subtotal</th>
                    </tr>
                </thead>
                <tbody id="preview-items-list">
                    <!-- Items inserted dynamically -->
                </tbody>
            </table>
        </div>

        <div style="margin-top: 20px; border-top: 1px solid var(--border-color); padding-top: 15px; display: flex; justify-content: flex-end; gap: 10px;">
            <a id="preview-full-details-link" href="#" class="btn-main" style="display: inline-flex; align-items: center; gap: 6px; padding: 10px 20px; border-radius: 10px; font-weight: 800; font-size: 0.85rem; text-decoration: none; background: var(--accent-color); color: white;">
                Edit Full Order →
            </a>
            <button type="button" onclick="closeOrderPreviewModal()" class="btn-main dark" style="padding: 10px 20px; border-radius: 10px; font-weight: 800; font-size: 0.85rem; border: none; box-shadow: none;">
                Close
            </button>
        </div>
    </div>
</div>

<!-- CPU Pricing Details Modal -->
<div id="cpuPricingModal" class="modal-overlay no-print" onclick="if(event.target === this) closeCpuPricingModal()" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); backdrop-filter:blur(4px); z-index:1000; align-items:center; justify-content:center;">
    <div class="modal-box" onclick="event.stopPropagation()" style="background:var(--bg-panel); border-radius:20px; width:90%; max-width:800px; padding:25px; box-shadow:var(--shadow-lg); border: 1px solid var(--border-color); display:flex; flex-direction:column; max-height:85vh;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 15px;">
            <div style="display:flex; align-items:center; gap:10px;">
                <span style="font-size: 1.5rem;">💻</span>
                <div>
                    <h3 id="cpu-pricing-title" style="font-weight: 800; font-size: 1.25rem; margin:0; color: var(--text-main);">CPU Family Details</h3>
                    <span style="font-size: 0.85rem; font-weight: 700; color: var(--accent-color);">Pricing, Models & Recent Sales</span>
                </div>
            </div>
            <button type="button" onclick="closeCpuPricingModal()" style="background:none; border:none; cursor:pointer; font-size:1.5rem; color:var(--text-secondary); opacity:0.6; transition: opacity 0.2s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.6">&times;</button>
        </div>

        <div id="cpu-loading" style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px 0; gap: 15px;">
            <div class="preview-spinner" style="width: 40px; height: 40px; border: 4px solid var(--border-color); border-top-color: var(--accent-color); border-radius: 50%; animation: spin 1s linear infinite;"></div>
            <span style="font-size: 0.9rem; font-weight: 600; color: var(--text-secondary);">Loading CPU metrics...</span>
        </div>

        <div id="cpu-error" style="display:none; text-align: center; padding: 30px 0; color: #ef4444; font-weight: 700;">
            ⚠️ Failed to load CPU pricing details.
        </div>

        <div id="cpu-body" style="display:none; overflow-y:auto; flex:1; padding-right:5px;">
            <h4 style="margin-top: 0; margin-bottom: 10px; font-weight: 800; font-size: 0.95rem; color: var(--text-main); text-transform: uppercase; letter-spacing: 0.5px;">Model Pricing Summary</h4>
            <div class="trends-table-container" style="margin-bottom: 25px; max-height: 250px; overflow-y: auto;">
                <table class="trends-table" style="width: 100%; border-collapse: separate; border-spacing: 0; text-align: left; font-size: 0.88rem;">
                    <thead>
                        <tr style="background: #0f172a; color: #f8fafc; font-size: 0.75rem; text-transform: uppercase; font-weight: 800; letter-spacing: 0.5px;">
                            <th style="padding: 12px 10px;">Model / Series</th>
                            <th style="padding: 12px 10px; text-align: center;">Total Units</th>
                            <th style="padding: 12px 10px; text-align: right;">Min Price ($)</th>
                            <th style="padding: 12px 10px; text-align: right;">Max Price ($)</th>
                            <th style="padding: 12px 10px; text-align: right;">Avg Price ($)</th>
                        </tr>
                    </thead>
                    <tbody id="cpu-models-list">
                        <!-- Populated dynamically -->
                    </tbody>
                </table>
            </div>

            <h4 style="margin-bottom: 10px; font-weight: 800; font-size: 0.95rem; color: var(--text-main); text-transform: uppercase; letter-spacing: 0.5px;">Latest Sales Transactions</h4>
            <div class="trends-table-container" style="max-height: 250px; overflow-y: auto;">
                <table class="trends-table" style="width: 100%; border-collapse: separate; border-spacing: 0; text-align: left; font-size: 0.88rem;">
                    <thead>
                        <tr style="background: #0f172a; color: #f8fafc; font-size: 0.75rem; text-transform: uppercase; font-weight: 800; letter-spacing: 0.5px;">
                            <th style="padding: 12px 10px;">Date</th>
                            <th style="padding: 12px 10px;">Client / Account</th>
                            <th style="padding: 12px 10px;">Model / Spec</th>
                            <th style="padding: 12px 10px; text-align: center;">QTY</th>
                            <th style="padding: 12px 10px; text-align: right;">Unit Price ($)</th>
                            <th style="padding: 12px 10px; text-align: right;">Order #</th>
                        </tr>
                    </thead>
                    <tbody id="cpu-sales-list">
                        <!-- Populated dynamically -->
                    </tbody>
                </table>
            </div>
        </div>

        <div style="margin-top: 20px; border-top: 1px solid var(--border-color); padding-top: 15px; display: flex; justify-content: flex-end;">
            <button type="button" onclick="closeCpuPricingModal()" class="btn-main dark" style="padding: 10px 20px; border-radius: 10px; font-weight: 800; font-size: 0.85rem; border: none; box-shadow: none;">
                Close
            </button>
        </div>
    </div>
</div>

<style>
.clickable-row {
    cursor: pointer;
    transition: background-color 0.15s ease, transform 0.1s ease;
}
.clickable-row:hover {
    background-color: var(--bg-surface-2) !important;
}
.clickable-row:active {
    transform: scale(0.995);
}
.order-preview-link {
    color: var(--accent-color);
    text-decoration: none;
    font-weight: 700;
    transition: all 0.15s ease;
}
.order-preview-link:hover {
    text-decoration: underline;
    opacity: 0.8;
}
@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>
