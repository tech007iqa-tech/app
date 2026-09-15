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

<!-- Customer CRM Profile & Order History Intelligence Modal (Phase 4) -->
<div id="customerProfileModal" class="modal-overlay no-print" onclick="if(event.target === this) closeCustomerProfileModal()" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); backdrop-filter:blur(5px); z-index:1000; align-items:center; justify-content:center;">
    <div class="modal-box" onclick="event.stopPropagation()" style="background:var(--bg-panel); border-radius:20px; width:90%; max-width:820px; padding:25px; box-shadow:var(--shadow-lg); border: 1px solid var(--border-color); display:flex; flex-direction:column; max-height:88vh;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 15px; flex-wrap: wrap; gap: 10px;">
            <div style="display:flex; align-items:center; gap:12px;">
                <span style="font-size: 1.8rem; background: var(--bg-surface-2); padding: 8px 12px; border-radius: 14px; border: 1px solid var(--border-color);">🏢</span>
                <div>
                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <h3 id="cust-modal-company" style="font-weight: 800; font-size: 1.3rem; margin:0; color: var(--text-main);">Company Profile</h3>
                        <span id="cust-modal-status-badge" style="font-size: 0.72rem; font-weight: 800; padding: 2px 10px; border-radius: 12px; background: #10b981; color: white; text-transform: uppercase;">Active</span>
                    </div>
                    <span id="cust-modal-id" style="font-size: 0.82rem; font-family: monospace; font-weight: 700; color: var(--text-secondary);">CUST-XXXXXXXX</span>
                </div>
            </div>
            <button type="button" onclick="closeCustomerProfileModal()" style="background:none; border:none; cursor:pointer; font-size:1.6rem; color:var(--text-secondary); opacity:0.6; transition: opacity 0.2s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.6">&times;</button>
        </div>

        <div id="cust-modal-loading" style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 50px 0; gap: 15px;">
            <div class="preview-spinner" style="width: 40px; height: 40px; border: 4px solid var(--border-color); border-top-color: var(--accent-color); border-radius: 50%; animation: spin 1s linear infinite;"></div>
            <span style="font-size: 0.9rem; font-weight: 600; color: var(--text-secondary);">Loading customer profile & transaction history...</span>
        </div>

        <div id="cust-modal-error" style="display:none; text-align: center; padding: 30px 0; color: #ef4444; font-weight: 700;">
            ⚠️ Failed to load customer profile.
        </div>

        <div id="cust-modal-body" style="display:none; overflow-y:auto; flex:1; padding-right:5px;">
            <!-- Lifetime Financial Metrics -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 20px;">
                <div style="background: var(--bg-surface-2); padding: 12px 14px; border-radius: 10px; border-left: 3px solid #10b981;">
                    <div style="font-size: 0.72rem; font-weight: 800; color: var(--text-secondary); text-transform: uppercase;">Lifetime Spend</div>
                    <div id="cust-modal-spend" style="font-size: 1.2rem; font-weight: 900; color: var(--text-main); margin-top: 4px;">$0.00</div>
                </div>
                <div style="background: var(--bg-surface-2); padding: 12px 14px; border-radius: 10px; border-left: 3px solid #3b82f6;">
                    <div style="font-size: 0.72rem; font-weight: 800; color: var(--text-secondary); text-transform: uppercase;">Units Liquidated</div>
                    <div id="cust-modal-units" style="font-size: 1.2rem; font-weight: 900; color: var(--text-main); margin-top: 4px;">0 units</div>
                </div>
                <div style="background: var(--bg-surface-2); padding: 12px 14px; border-radius: 10px; border-left: 3px solid #8b5cf6;">
                    <div style="font-size: 0.72rem; font-weight: 800; color: var(--text-secondary); text-transform: uppercase;">Completed Orders</div>
                    <div id="cust-modal-orders" style="font-size: 1.2rem; font-weight: 900; color: var(--text-main); margin-top: 4px;">0 orders</div>
                </div>
                <div style="background: var(--bg-surface-2); padding: 12px 14px; border-radius: 10px; border-left: 3px solid #f59e0b;">
                    <div style="font-size: 0.72rem; font-weight: 800; color: var(--text-secondary); text-transform: uppercase;">Last Purchase</div>
                    <div id="cust-modal-last-date" style="font-size: 1.2rem; font-weight: 900; color: var(--text-main); margin-top: 4px;">—</div>
                </div>
            </div>

            <!-- Contact & CRM Metadata Card -->
            <div style="background: var(--bg-surface-2); padding: 14px 18px; border-radius: 12px; margin-bottom: 22px; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; font-size: 0.85rem;">
                <div>
                    <span style="color: var(--text-secondary); font-weight: 600;">Contact Person:</span>
                    <span id="cust-modal-contact" style="font-weight: 700; color: var(--text-main); margin-left: 5px;">—</span>
                </div>
                <div>
                    <span style="color: var(--text-secondary); font-weight: 600;">Phone:</span>
                    <span id="cust-modal-phone" style="font-weight: 700; color: var(--text-main); margin-left: 5px;">—</span>
                </div>
                <div>
                    <span style="color: var(--text-secondary); font-weight: 600;">Email:</span>
                    <span id="cust-modal-email" style="font-weight: 700; color: var(--text-main); margin-left: 5px;">—</span>
                </div>
                <div>
                    <span style="color: var(--text-secondary); font-weight: 600;">Account Since:</span>
                    <span id="cust-modal-first-date" style="font-weight: 700; color: var(--text-main); margin-left: 5px;">—</span>
                </div>
            </div>

            <!-- Recent Orders Manifest List -->
            <h4 style="margin-top: 0; margin-bottom: 10px; font-weight: 800; font-size: 0.95rem; color: var(--text-main); text-transform: uppercase; letter-spacing: 0.5px;">
                📋 Recent Transaction Manifests
            </h4>
            <div class="trends-table-container" style="margin-bottom: 20px; max-height: 220px; overflow-y: auto;">
                <table class="trends-table" style="width: 100%; border-collapse: separate; border-spacing: 0; text-align: left; font-size: 0.88rem;">
                    <thead>
                        <tr style="background: #0f172a; color: #f8fafc; font-size: 0.75rem; text-transform: uppercase; font-weight: 800; letter-spacing: 0.5px;">
                            <th style="padding: 10px 12px;">Order ID</th>
                            <th style="padding: 10px 12px;">Date</th>
                            <th style="padding: 10px 12px; text-align: center;">Units</th>
                            <th style="padding: 10px 12px; text-align: right;">Valuation</th>
                            <th style="padding: 10px 12px; text-align: center;">Status</th>
                            <th style="padding: 10px 12px; text-align: center;">Manifest</th>
                        </tr>
                    </thead>
                    <tbody id="cust-modal-orders-list">
                        <!-- Populated dynamically -->
                    </tbody>
                </table>
            </div>
        </div>

        <div style="margin-top: 15px; border-top: 1px solid var(--border-color); padding-top: 15px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div style="display: flex; gap: 8px;">
                <a id="cust-modal-crm-btn" href="#" class="btn-main" style="padding: 8px 16px; font-size: 0.82rem; border-radius: 8px; background: #3b82f6; color: white; text-decoration: none; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;">
                    <span>👥</span> View in CRM
                </a>
                <a id="cust-modal-order-btn" href="#" class="btn-main" style="padding: 8px 16px; font-size: 0.82rem; border-radius: 8px; background: #10b981; color: white; text-decoration: none; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;">
                    <span>🛒</span> New Order Batch
                </a>
            </div>
            <button type="button" onclick="closeCustomerProfileModal()" class="btn-main dark" style="padding: 8px 18px; border-radius: 8px; font-weight: 800; font-size: 0.85rem; border: none; box-shadow: none;">
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
.order-preview-link, .customer-profile-link {
    color: var(--accent-color);
    text-decoration: none;
    font-weight: 700;
    transition: all 0.15s ease;
}
.order-preview-link:hover, .customer-profile-link:hover {
    text-decoration: underline;
    opacity: 0.8;
}
@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>
