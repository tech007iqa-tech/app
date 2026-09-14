<?php
/**
 * Warehouse Inventory Sub-System Modal Dialog
 * Modular component for rapid intake with Smart Sold Price Inference and Whole Location Full-Audit Sync.
 */

// Fetch recent sold items for this location if available
$recent_sold = [];
if (!empty($selected_loc)) {
    try {
        $stmt_s = $conn_wh->prepare("SELECT * FROM sold_items WHERE location_code = ? AND sector = ? ORDER BY id DESC LIMIT 15");
        $stmt_s->execute([$selected_loc, $selected_sector]);
        $recent_sold = $stmt_s->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}
?>
<!-- INVENTORY CONTROL MODAL -->
<div id="warehouse-inventory-modal" class="modal-overlay no-print"
    style="display:none; position:fixed; inset:0; background:rgba(15, 23, 42, 0.7); backdrop-filter:blur(6px); z-index:2500; align-items:center; justify-content:center; padding:15px; overflow-y:auto;"
    onclick="if(event.target===this) closeInventoryModal()">
    
    <div class="inventory-modal-content"
        style="background:var(--bg-card, #ffffff); color:var(--text-main, #0f172a); border-radius:24px; width:100%; max-width:880px; max-height:92vh; display:flex; flex-direction:column; box-shadow:0 25px 50px -12px rgba(0,0,0,0.35); border:1px solid var(--border-color, #e2e8f0); position:relative; overflow:hidden; animation:modalIn 0.25s cubic-bezier(0.16, 1, 0.3, 1);">
        
        <!-- Modal Header -->
        <div style="padding:18px 24px; border-bottom:1px solid var(--border-color, #e2e8f0); display:flex; justify-content:space-between; align-items:center; background:linear-gradient(135deg, rgba(250, 204, 21, 0.15) 0%, rgba(245, 158, 11, 0.08) 100%);">
            <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                <div style="width:42px; height:42px; border-radius:12px; background:#facc15; display:flex; align-items:center; justify-content:center; font-size:1.4rem; box-shadow:0 4px 10px rgba(250,204,21,0.35);">
                    📦
                </div>
                <div>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <h2 style="font-weight:900; font-size:1.25rem; margin:0; letter-spacing:-0.02em;">Warehouse Inventory & Smart Intake</h2>
                        <span class="badge" style="background:#0f172a; color:#facc15; font-weight:800; font-size:0.75rem; padding:3px 8px; border-radius:6px; letter-spacing:0.04em;">
                            📍 <?= htmlspecialchars($selected_loc ?? 'GLOBAL') ?>
                        </span>
                        <span class="badge" style="background:var(--accent-color, #0284c7); color:white; font-weight:700; font-size:0.75rem; padding:3px 8px; border-radius:6px;">
                            <?= htmlspecialchars($selected_sector) ?>
                        </span>
                    </div>
                    <p style="font-size:0.8rem; color:var(--text-secondary, #64748b); margin:2px 0 0 0;">
                        Rapid inbound intake with Smart Inferred Pricing & Location Sync Reconciliation.
                    </p>
                </div>
            </div>
            <button type="button" onclick="closeInventoryModal()" 
                style="background:none; border:none; width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.3rem; color:var(--text-secondary, #64748b); cursor:pointer; transition:all 0.2s;"
                onmouseover="this.style.background='rgba(0,0,0,0.06)'" onmouseout="this.style.background='none'" title="Close (Esc)">
                ✕
            </button>
        </div>

        <!-- Tab Navigation -->
        <div style="display:flex; background:var(--bg-body, #f8fafc); border-bottom:1px solid var(--border-color, #e2e8f0); padding:6px 16px 0 16px; gap:8px;">
            <button type="button" id="tab-btn-intake" class="inv-tab-btn active" onclick="switchInventoryTab('intake')"
                style="padding:12px 20px; font-weight:800; font-size:0.85rem; border:none; background:transparent; border-bottom:3px solid #facc15; color:var(--text-main, #0f172a); cursor:pointer; display:flex; align-items:center; gap:8px; border-top-left-radius:8px; border-top-right-radius:8px; transition:all 0.2s;">
                <span>➕</span> Rapid Inbound Intake
            </button>
            <button type="button" id="tab-btn-deplete" class="inv-tab-btn" onclick="switchInventoryTab('deplete')"
                style="padding:12px 20px; font-weight:700; font-size:0.85rem; border:none; background:transparent; border-bottom:3px solid transparent; color:var(--text-secondary, #64748b); cursor:pointer; display:flex; align-items:center; gap:8px; border-top-left-radius:8px; border-top-right-radius:8px; transition:all 0.2s;">
                <span>🗑️</span> Shelf Audit & Sync <span id="modal-inv-count-badge" style="background:#e2e8f0; color:#334155; font-size:0.7rem; padding:2px 6px; border-radius:10px; font-weight:800;"><?= count($items) ?></span>
            </button>
        </div>

        <!-- Modal Body Container -->
        <div style="padding:20px 24px; overflow-y:auto; flex:1;">
            
            <!-- TAB 1: RAPID INTAKE FORM WITH SMART PRICING -->
            <div id="inv-tab-pane-intake" class="inv-tab-pane" style="display:block;">
                <form id="wh-quick-intake-form" onsubmit="submitQuickIntakeAjax(event)">
                    <?= UI::csrf_field() ?>
                    <input type="hidden" name="action" value="quick_add_inventory">
                    <input type="hidden" name="location_code" value="<?= htmlspecialchars($selected_loc ?? '') ?>">
                    <input type="hidden" name="sector" value="<?= htmlspecialchars($selected_sector) ?>">

                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:14px; margin-bottom:14px;">
                        <div>
                            <label style="display:block; font-size:0.7rem; font-weight:800; text-transform:uppercase; margin-bottom:5px; color:var(--text-secondary, #64748b);">Brand *</label>
                            <input type="text" id="intake-brand" name="brand" required placeholder="e.g. Dell, Lenovo, HP" list="brand-options"
                                oninput="debounceInferPrice()"
                                style="width:100%; height:44px; border-radius:10px; border:1px solid var(--border-color, #cbd5e1); padding:0 12px; font-weight:700; font-size:0.9rem; background:var(--bg-body, #ffffff); color:var(--text-main, #0f172a);">
                        </div>
                        <div>
                            <label style="display:block; font-size:0.7rem; font-weight:800; text-transform:uppercase; margin-bottom:5px; color:var(--text-secondary, #64748b);">Model *</label>
                            <input type="text" id="intake-model" name="model" required placeholder="e.g. Latitude 5490, ThinkPad T480"
                                oninput="debounceInferPrice()"
                                style="width:100%; height:44px; border-radius:10px; border:1px solid var(--border-color, #cbd5e1); padding:0 12px; font-weight:700; font-size:0.9rem; background:var(--bg-body, #ffffff); color:var(--text-main, #0f172a);">
                        </div>
                    </div>

                    <!-- Sector-Specific Specs Dynamic Grid -->
                    <?php if ($selected_sector === 'Laptops'): ?>
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(130px, 1fr)); gap:12px; margin-bottom:14px;">
                        <div>
                            <label style="display:block; font-size:0.7rem; font-weight:800; text-transform:uppercase; margin-bottom:5px; color:var(--text-secondary, #64748b);">Series</label>
                            <input type="text" id="intake-series" name="series" placeholder="e.g. ThinkPad"
                                oninput="debounceInferPrice()"
                                style="width:100%; height:42px; border-radius:8px; border:1px solid var(--border-color, #cbd5e1); padding:0 10px; font-size:0.85rem; background:var(--bg-body, #ffffff); color:var(--text-main, #0f172a);">
                        </div>
                        <div>
                            <label style="display:block; font-size:0.7rem; font-weight:800; text-transform:uppercase; margin-bottom:5px; color:var(--text-secondary, #64748b);">CPU</label>
                            <input type="text" id="intake-cpu" name="cpu" placeholder="i5, i7, Ryzen" list="cpu-options-list"
                                oninput="debounceInferPrice()"
                                style="width:100%; height:42px; border-radius:8px; border:1px solid var(--border-color, #cbd5e1); padding:0 10px; font-size:0.85rem; background:var(--bg-body, #ffffff); color:var(--text-main, #0f172a);">
                        </div>
                        <div>
                            <label style="display:block; font-size:0.7rem; font-weight:800; text-transform:uppercase; margin-bottom:5px; color:var(--text-secondary, #64748b);">Gen</label>
                            <input type="text" name="gen" placeholder="8th, 11th" list="gen-options-list"
                                style="width:100%; height:42px; border-radius:8px; border:1px solid var(--border-color, #cbd5e1); padding:0 10px; font-size:0.85rem; background:var(--bg-body, #ffffff); color:var(--text-main, #0f172a);">
                        </div>
                        <div>
                            <label style="display:block; font-size:0.7rem; font-weight:800; text-transform:uppercase; margin-bottom:5px; color:var(--text-secondary, #64748b);">RAM</label>
                            <input type="text" name="ram" placeholder="16GB"
                                style="width:100%; height:42px; border-radius:8px; border:1px solid var(--border-color, #cbd5e1); padding:0 10px; font-size:0.85rem; background:var(--bg-body, #ffffff); color:var(--text-main, #0f172a);">
                        </div>
                        <div>
                            <label style="display:block; font-size:0.7rem; font-weight:800; text-transform:uppercase; margin-bottom:5px; color:var(--text-secondary, #64748b);">Storage</label>
                            <input type="text" name="storage" placeholder="512GB SSD"
                                style="width:100%; height:42px; border-radius:8px; border:1px solid var(--border-color, #cbd5e1); padding:0 10px; font-size:0.85rem; background:var(--bg-body, #ffffff); color:var(--text-main, #0f172a);">
                        </div>
                        <div>
                            <label style="display:block; font-size:0.7rem; font-weight:800; text-transform:uppercase; margin-bottom:5px; color:var(--text-secondary, #64748b);">Battery</label>
                            <input type="text" name="battery" placeholder="Good, 85%" list="battery-options-list"
                                style="width:100%; height:42px; border-radius:8px; border:1px solid var(--border-color, #cbd5e1); padding:0 10px; font-size:0.85rem; background:var(--bg-body, #ffffff); color:var(--text-main, #0f172a);">
                        </div>
                    </div>
                    <?php elseif ($selected_sector === 'Gaming'): ?>
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(140px, 1fr)); gap:12px; margin-bottom:14px;">
                        <div>
                            <label style="display:block; font-size:0.7rem; font-weight:800; text-transform:uppercase; margin-bottom:5px; color:var(--text-secondary, #64748b);">Category</label>
                            <input type="text" name="gaming_category" placeholder="Consoles/Handheld" list="gaming-cat-list"
                                style="width:100%; height:42px; border-radius:8px; border:1px solid var(--border-color, #cbd5e1); padding:0 10px; font-size:0.85rem; background:var(--bg-body, #ffffff); color:var(--text-main, #0f172a);">
                        </div>
                        <div>
                            <label style="display:block; font-size:0.7rem; font-weight:800; text-transform:uppercase; margin-bottom:5px; color:var(--text-secondary, #64748b);">Series</label>
                            <input type="text" name="series" placeholder="e.g. PS5, Xbox"
                                style="width:100%; height:42px; border-radius:8px; border:1px solid var(--border-color, #cbd5e1); padding:0 10px; font-size:0.85rem; background:var(--bg-body, #ffffff); color:var(--text-main, #0f172a);">
                        </div>
                        <div>
                            <label style="display:block; font-size:0.7rem; font-weight:800; text-transform:uppercase; margin-bottom:5px; color:var(--text-secondary, #64748b);">GPU / Chip</label>
                            <input type="text" name="gpu" placeholder="RTX 4070"
                                style="width:100%; height:42px; border-radius:8px; border:1px solid var(--border-color, #cbd5e1); padding:0 10px; font-size:0.85rem; background:var(--bg-body, #ffffff); color:var(--text-main, #0f172a);">
                        </div>
                        <div>
                            <label style="display:block; font-size:0.7rem; font-weight:800; text-transform:uppercase; margin-bottom:5px; color:var(--text-secondary, #64748b);">Storage</label>
                            <input type="text" name="storage" placeholder="1TB NVMe"
                                style="width:100%; height:42px; border-radius:8px; border:1px solid var(--border-color, #cbd5e1); padding:0 10px; font-size:0.85rem; background:var(--bg-body, #ffffff); color:var(--text-main, #0f172a);">
                        </div>
                    </div>
                    <?php elseif ($selected_sector === 'Desktops'): ?>
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:12px; margin-bottom:14px;">
                        <div>
                            <label style="display:block; font-size:0.7rem; font-weight:800; text-transform:uppercase; margin-bottom:5px; color:var(--text-secondary, #64748b);">CPU Gen / Spec</label>
                            <input type="text" name="cpu_gen" placeholder="i7-10700" list="cpu-gen-options-list"
                                style="width:100%; height:42px; border-radius:8px; border:1px solid var(--border-color, #cbd5e1); padding:0 10px; font-size:0.85rem; background:var(--bg-body, #ffffff); color:var(--text-main, #0f172a);">
                        </div>
                        <div>
                            <label style="display:block; font-size:0.7rem; font-weight:800; text-transform:uppercase; margin-bottom:5px; color:var(--text-secondary, #64748b);">RAM</label>
                            <input type="text" name="ram" placeholder="32GB DDR4"
                                style="width:100%; height:42px; border-radius:8px; border:1px solid var(--border-color, #cbd5e1); padding:0 10px; font-size:0.85rem; background:var(--bg-body, #ffffff); color:var(--text-main, #0f172a);">
                        </div>
                        <div>
                            <label style="display:block; font-size:0.7rem; font-weight:800; text-transform:uppercase; margin-bottom:5px; color:var(--text-secondary, #64748b);">Storage</label>
                            <input type="text" name="storage" placeholder="1TB SSD"
                                style="width:100%; height:42px; border-radius:8px; border:1px solid var(--border-color, #cbd5e1); padding:0 10px; font-size:0.85rem; background:var(--bg-body, #ffffff); color:var(--text-main, #0f172a);">
                        </div>
                    </div>
                    <?php endif; ?>

                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:12px; margin-bottom:14px;">
                        <div>
                            <label style="display:block; font-size:0.7rem; font-weight:800; text-transform:uppercase; margin-bottom:5px; color:var(--text-secondary, #64748b);">Condition</label>
                            <select name="condition" style="width:100%; height:42px; border-radius:8px; border:1px solid var(--border-color, #cbd5e1); padding:0 10px; font-size:0.85rem; background:var(--bg-body, #ffffff); color:var(--text-main, #0f172a); font-weight:700;">
                                <option value="Used">Used</option>
                                <option value="Refurbished">Refurbished</option>
                                <option value="New">New</option>
                                <option value="For Parts">For Parts</option>
                            </select>
                        </div>
                        <div>
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
                                <label style="display:block; font-size:0.7rem; font-weight:800; text-transform:uppercase; color:var(--text-secondary, #64748b);">Unit Price ($)</label>
                                <span id="smart-price-badge-header" style="display:none; font-size:0.65rem; color:#854d0e; font-weight:800; background:#fef08a; padding:1px 6px; border-radius:6px;">Smart Inferred</span>
                            </div>
                            <input type="number" step="0.01" id="intake-price" name="price" value="0.00"
                                style="width:100%; height:42px; border-radius:8px; border:1px solid var(--border-color, #cbd5e1); padding:0 10px; font-size:0.95rem; background:var(--bg-body, #ffffff); color:var(--text-main, #0f172a); font-weight:800;">
                        </div>
                        <div>
                            <label style="display:block; font-size:0.7rem; font-weight:800; text-transform:uppercase; margin-bottom:5px; color:var(--text-secondary, #64748b);">Quantity *</label>
                            <input type="number" step="1" name="quantity" value="1" min="1" required
                                style="width:100%; height:42px; border-radius:8px; border:1px solid var(--border-color, #cbd5e1); padding:0 10px; font-size:1rem; font-weight:800; text-align:center; background:var(--bg-body, #ffffff); color:var(--text-main, #0f172a);">
                        </div>
                    </div>

                    <!-- Smart Suggested Inferred Price Banner -->
                    <div id="smart-price-banner" style="display:none; margin-bottom:14px; padding:10px 14px; background:linear-gradient(135deg, #fef9c3 0%, #fef08a 100%); border:1px solid #facc15; border-radius:10px; color:#713f12; align-items:center; justify-content:space-between; font-size:0.8rem;">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span style="font-size:1.1rem;">💡</span>
                            <div>
                                <div id="smart-price-summary" style="font-weight:800;">Inferred Price: $0.00</div>
                                <div id="smart-price-detail" style="font-size:0.7rem; opacity:0.85;">Based on recent sales database</div>
                            </div>
                        </div>
                        <button type="button" onclick="applyInferredPrice()"
                            style="background:#0f172a; color:#facc15; border:none; padding:6px 14px; border-radius:8px; font-weight:900; font-size:0.75rem; cursor:pointer; box-shadow:0 2px 6px rgba(0,0,0,0.2);">
                            ⚡ Apply $<span id="smart-price-val">0.00</span>
                        </button>
                    </div>

                    <div style="margin-bottom:14px;">
                        <label style="display:block; font-size:0.7rem; font-weight:800; text-transform:uppercase; margin-bottom:5px; color:var(--text-secondary, #64748b);">Notes / Technical Remarks</label>
                        <input type="text" name="notes" placeholder="Optional notes (e.g. Minor scratches, battery tested)"
                            style="width:100%; height:42px; border-radius:8px; border:1px solid var(--border-color, #cbd5e1); padding:0 10px; font-size:0.85rem; background:var(--bg-body, #ffffff); color:var(--text-main, #0f172a);">
                    </div>

                    <div style="display:flex; flex-direction:column; gap:8px; padding:12px 16px; background:var(--bg-body, #f8fafc); border-radius:12px; margin-bottom:18px; border:1px dashed var(--border-color, #cbd5e1);">
                        <label style="display:flex; align-items:center; gap:8px; font-size:0.8rem; font-weight:700; color:var(--text-main, #0f172a); cursor:pointer;">
                            <input type="checkbox" name="auto_consolidate" value="1" checked style="width:18px; height:18px; accent-color:#facc15; cursor:pointer;">
                            <span>⚡ Auto-Consolidate: Increment quantity if identical spec is already on Shelf <?= htmlspecialchars($selected_loc ?? '') ?></span>
                        </label>
                    </div>

                    <div id="quick-intake-status" style="display:none; margin-bottom:14px; padding:10px 14px; border-radius:8px; font-size:0.85rem; font-weight:700;"></div>

                    <div style="display:flex; gap:12px;">
                        <button type="button" onclick="closeInventoryModal()"
                            style="flex:1; height:48px; border-radius:12px; border:1px solid var(--border-color, #cbd5e1); background:none; font-weight:800; cursor:pointer; color:var(--text-secondary, #64748b);">
                            Cancel
                        </button>
                        <button type="submit" id="btn-quick-intake-submit"
                            style="flex:2; height:48px; border-radius:12px; border:none; background:#facc15; color:#0f172a; font-weight:900; font-size:0.95rem; cursor:pointer; box-shadow:0 4px 12px rgba(250,204,21,0.35); display:flex; align-items:center; justify-content:center; gap:8px;">
                            <span>➕</span> Commit to Shelf <?= htmlspecialchars($selected_loc ?? '') ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- TAB 2: SHELF AUDIT, DEPLETION & FULL LOCATION RECONCILIATION SYNC -->
            <div id="inv-tab-pane-deplete" class="inv-tab-pane" style="display:none;">
                
                <!-- Whole Location Sync / Full Shelf Reconcile Banner -->
                <div style="padding:14px 18px; border-radius:14px; background:linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border:1px solid #86efac; margin-bottom:16px; display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
                    <div>
                        <div style="font-weight:900; color:#166534; font-size:0.95rem; display:flex; align-items:center; gap:6px;">
                            <span>🔄</span> Full Location Reconciliation Sync
                        </div>
                        <div style="font-size:0.75rem; color:#15803d; margin-top:2px;">
                            Check off items physically present on <strong>Shelf <?= htmlspecialchars($selected_loc ?? '') ?></strong>. Any unchecked/omitted items will be automatically recorded as <strong>SOLD</strong> and purged.
                        </div>
                    </div>
                    <button type="button" onclick="promptLocationSyncReconcile()"
                        style="height:38px; padding:0 16px; border-radius:10px; border:none; background:#16a34a; color:white; font-weight:900; font-size:0.8rem; cursor:pointer; display:flex; align-items:center; gap:6px; box-shadow:0 2px 6px rgba(22,163,74,0.3); transition:all 0.15s;">
                        <span>⚡</span> Reconcile Shelf <?= htmlspecialchars($selected_loc ?? '') ?>
                    </button>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; gap:10px; flex-wrap:wrap;">
                    <div style="position:relative; flex:1; min-width:200px;">
                        <input type="text" id="modal-deplete-filter" placeholder="Filter shelf items..." onkeyup="filterModalDepleteList(this.value)"
                            style="width:100%; height:38px; border-radius:8px; border:1px solid var(--border-color, #cbd5e1); padding:0 10px 0 32px; font-size:0.85rem; background:var(--bg-body, #ffffff); color:var(--text-main, #0f172a);">
                        <span style="position:absolute; left:10px; top:50%; transform:translateY(-50%); opacity:0.5; font-size:0.85rem;">🔍</span>
                    </div>
                    <div style="display:flex; gap:8px;">
                        <button type="button" onclick="batchPurgeSelectedModalItems()" class="btn-danger-purge"
                            style="height:38px; padding:0 14px; border-radius:8px; border:none; background:#ef4444; color:white; font-weight:800; font-size:0.75rem; cursor:pointer; display:flex; align-items:center; gap:6px; box-shadow:0 2px 6px rgba(239,68,68,0.25);">
                            <span>🗑️</span> Purge Selected (Record as Sold)
                        </button>
                    </div>
                </div>

                <div id="modal-deplete-status" style="display:none; margin-bottom:12px; padding:10px 14px; border-radius:8px; font-size:0.85rem; font-weight:700;"></div>

                <div style="max-height:42vh; overflow-y:auto; border:1px solid var(--border-color, #e2e8f0); border-radius:12px; background:var(--bg-body, #ffffff); margin-bottom:18px;">
                    <table style="width:100%; border-collapse:collapse; text-align:left; font-size:0.85rem;">
                        <thead>
                            <tr style="background:var(--bg-body, #f8fafc); border-bottom:1px solid var(--border-color, #e2e8f0); position:sticky; top:0; z-index:10;">
                                <th style="padding:10px; width:36px; text-align:center;">
                                    <input type="checkbox" id="modal-select-all-deplete" onclick="toggleSelectAllModalDeplete(this)" style="cursor:pointer;" title="Select All">
                                </th>
                                <th style="padding:10px; font-weight:800; color:var(--text-secondary, #64748b);">Item / Hardware Specs</th>
                                <th style="padding:10px; font-weight:800; color:var(--text-secondary, #64748b); text-align:center; width:80px;">Shelf Qty</th>
                                <th style="padding:10px; font-weight:800; color:var(--text-secondary, #64748b); text-align:center; width:120px;">Step Qty</th>
                                <th style="padding:10px; font-weight:800; color:var(--text-secondary, #64748b); text-align:right; width:110px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="modal-deplete-table-body">
                            <?php if (empty($items)): ?>
                                <tr id="modal-empty-row">
                                    <td colspan="5" style="padding:30px; text-align:center; color:var(--text-secondary, #64748b);">
                                        No inventory registered on Shelf <?= htmlspecialchars($selected_loc ?? '') ?>.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($items as $it): 
                                    $sp = json_decode($it['specs_json'] ?? '{}', true) ?: [];
                                    $spec_summary = array_filter([
                                        $sp['cpu'] ?? $sp['cpu_gen'] ?? '',
                                        $sp['ram'] ?? '',
                                        $sp['storage'] ?? '',
                                        $sp['series'] ?? '',
                                        $sp['condition'] ?? '',
                                        $sp['notes'] ?? ''
                                    ]);
                                ?>
                                <tr class="modal-deplete-row" data-id="<?= $it['id'] ?>" data-qty="<?= (int)$it['quantity'] ?>"
                                    data-search="<?= htmlspecialchars(strtolower($it['brand'] . ' ' . $it['model'] . ' ' . implode(' ', $spec_summary))) ?>"
                                    style="border-bottom:1px solid var(--border-color, #e2e8f0); transition:background 0.15s;">
                                    <td style="padding:10px; text-align:center;">
                                        <input type="checkbox" class="modal-deplete-checkbox" value="<?= $it['id'] ?>" checked style="cursor:pointer;" title="Checked = Verified Physically on Shelf">
                                    </td>
                                    <td style="padding:10px;">
                                        <div style="font-weight:800; color:var(--text-main, #0f172a);"><?= htmlspecialchars($it['brand'] . ' ' . $it['model']) ?></div>
                                        <div style="font-size:0.75rem; color:var(--text-secondary, #64748b); margin-top:2px;">
                                            <?= htmlspecialchars(implode(' • ', $spec_summary) ?: 'No specs listed') ?>
                                        </div>
                                    </td>
                                    <td style="padding:10px; text-align:center;">
                                        <span class="modal-item-qty-badge" style="display:inline-block; padding:4px 10px; background:#f1f5f9; border-radius:12px; font-weight:900; font-size:0.85rem; color:#0f172a;">
                                            <?= (int)$it['quantity'] ?>
                                        </span>
                                    </td>
                                    <td style="padding:10px; text-align:center;">
                                        <div style="display:inline-flex; align-items:center; gap:4px;">
                                            <button type="button" onclick="depleteModalItemQty(<?= $it['id'] ?>, -1)" title="Decrement 1 (Record 1 as Sold)"
                                                style="width:30px; height:30px; border-radius:6px; border:1px solid var(--border-color, #cbd5e1); background:var(--bg-body, #ffffff); font-weight:900; cursor:pointer; color:var(--text-main, #0f172a);">
                                                -1
                                            </button>
                                            <button type="button" onclick="depleteModalItemQty(<?= $it['id'] ?>, 1)" title="Increment 1"
                                                style="width:30px; height:30px; border-radius:6px; border:1px solid var(--border-color, #cbd5e1); background:var(--bg-body, #ffffff); font-weight:900; cursor:pointer; color:var(--text-main, #0f172a);">
                                                +1
                                            </button>
                                        </div>
                                    </td>
                                    <td style="padding:10px; text-align:right;">
                                        <button type="button" onclick="purgeModalItem(<?= $it['id'] ?>, '<?= htmlspecialchars(addslashes($it['brand'] . ' ' . $it['model'])) ?>')"
                                            style="padding:6px 10px; border-radius:6px; border:none; background:#fee2e2; color:#b91c1c; font-weight:800; font-size:0.75rem; cursor:pointer; transition:all 0.15s;"
                                            onmouseover="this.style.background='#fca5a5'" onmouseout="this.style.background='#fee2e2'" title="Record as Sold & Remove">
                                            🏷️ Sold / Purge
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Collapsible Recent Sold Records History -->
                <?php if (!empty($recent_sold)): ?>
                <details style="background:var(--bg-body, #f8fafc); border:1px solid var(--border-color, #e2e8f0); border-radius:12px; padding:12px; font-size:0.8rem;">
                    <summary style="font-weight:800; cursor:pointer; color:var(--text-secondary, #64748b); display:flex; justify-content:space-between; align-items:center;">
                        <span>📜 Recently Sold & Reconciled Log (<?= count($recent_sold) ?>)</span>
                        <span style="font-size:0.7rem; background:#e2e8f0; padding:2px 8px; border-radius:10px;"><?= htmlspecialchars($selected_loc ?? '') ?></span>
                    </summary>
                    <div style="margin-top:10px; max-height:160px; overflow-y:auto;">
                        <table style="width:100%; border-collapse:collapse; font-size:0.75rem;">
                            <thead>
                                <tr style="color:var(--text-secondary, #64748b); border-bottom:1px solid #cbd5e1;">
                                    <th style="padding:4px 6px;">Date</th>
                                    <th style="padding:4px 6px;">Item</th>
                                    <th style="padding:4px 6px; text-align:center;">Qty</th>
                                    <th style="padding:4px 6px; text-align:right;">Sold Price</th>
                                    <th style="padding:4px 6px;">Reason / By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_sold as $rs): ?>
                                <tr style="border-bottom:1px solid #f1f5f9;">
                                    <td style="padding:6px; color:#64748b;"><?= htmlspecialchars(substr($rs['sold_at'], 5, 11)) ?></td>
                                    <td style="padding:6px; font-weight:700;"><?= htmlspecialchars($rs['brand'] . ' ' . $rs['model']) ?></td>
                                    <td style="padding:6px; text-align:center; font-weight:800;"><?= (int)$rs['quantity'] ?></td>
                                    <td style="padding:6px; text-align:right; font-weight:800; color:#166534;">$<?= number_format((float)$rs['sold_price'], 2) ?></td>
                                    <td style="padding:6px; color:#64748b;"><?= htmlspecialchars($rs['reason'] ?? 'Sold') ?> (<?= htmlspecialchars($rs['sold_by'] ?? 'User') ?>)</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </details>
                <?php endif; ?>

            </div>

        </div>

    </div>
</div>
