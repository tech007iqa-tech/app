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
                <span>🔄</span> Shelf Audit & Sync <span id="modal-inv-count-badge" style="background:#e2e8f0; color:#334155; font-size:0.7rem; padding:2px 6px; border-radius:10px; font-weight:800;"><?= count($items) ?></span>
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
                
                <!-- Whole Location Sync / Full Shelf Reconcile Header Banner -->
                <div style="padding:16px 20px; border-radius:16px; background:linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border:1px solid var(--border-color, #cbd5e1); margin-bottom:16px; display:flex; flex-direction:column; gap:12px;">
                    
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                        <div>
                            <div style="font-weight:900; color:var(--text-main, #0f172a); font-size:1rem; display:flex; align-items:center; gap:8px;">
                                <span>⚡</span> Reconcile Shelf <?= htmlspecialchars($selected_loc ?? '') ?>
                                <span style="font-size:0.75rem; background:#0f172a; color:#facc15; font-weight:800; padding:2px 8px; border-radius:6px;"><?= htmlspecialchars($selected_sector) ?></span>
                            </div>
                            <div style="font-size:0.78rem; color:var(--text-secondary, #64748b); margin-top:3px;">
                                Search or check off items physically found on shelf. <strong>All unchecked items will be recorded as SOLD and deleted upon reconciliation.</strong>
                            </div>
                        </div>

                        <!-- Main Reconcile Commit Action Button -->
                        <button type="button" id="btn-main-reconcile-shelf" onclick="promptLocationSyncReconcile()"
                            style="height:42px; padding:0 20px; border-radius:12px; border:none; background:#16a34a; color:white; font-weight:900; font-size:0.88rem; cursor:pointer; display:flex; align-items:center; gap:8px; box-shadow:0 3px 10px rgba(22,163,74,0.35); transition:all 0.15s;">
                            <span>⚡</span> Reconcile Shelf <?= htmlspecialchars($selected_loc ?? '') ?>
                        </button>
                    </div>

                    <!-- Live Audit Metric Counters & Batch Controls -->
                    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; padding-top:10px; border-top:1px solid var(--border-color, #e2e8f0);">
                        <div style="display:flex; align-items:center; gap:12px; font-size:0.8rem; font-weight:800; flex-wrap:wrap;">
                            <div style="display:flex; align-items:center; gap:6px; background:#dcfce7; color:#166534; padding:4px 10px; border-radius:8px; border:1px solid #86efac;">
                                <span>✅ Verified to Keep:</span>
                                <span id="reconcile-stat-verified-count">0</span>
                                <span style="font-weight:600; opacity:0.8;">(<span id="reconcile-stat-verified-units">0</span> units)</span>
                            </div>
                            <div style="display:flex; align-items:center; gap:6px; background:#fee2e2; color:#991b1b; padding:4px 10px; border-radius:8px; border:1px solid #fca5a5;">
                                <span>❌ Missing to Purge:</span>
                                <span id="reconcile-stat-missing-count"><?= count($items) ?></span>
                                <span style="font-weight:600; opacity:0.8;">(<span id="reconcile-stat-missing-units"><?= array_sum(array_column($items, 'quantity')) ?></span> units)</span>
                            </div>
                        </div>

                        <!-- Quick Mass Toggles -->
                        <div style="display:flex; align-items:center; gap:6px;">
                            <button type="button" onclick="massSetReconcileChecks(true)"
                                style="font-size:0.75rem; font-weight:800; padding:4px 10px; border-radius:6px; border:1px solid #cbd5e1; background:var(--bg-body, #ffffff); color:var(--text-main, #0f172a); cursor:pointer;"
                                title="Mark all registered items as physically present">
                                ✓ Check All
                            </button>
                            <button type="button" onclick="massSetReconcileChecks(false)"
                                style="font-size:0.75rem; font-weight:800; padding:4px 10px; border-radius:6px; border:1px solid #cbd5e1; background:var(--bg-body, #ffffff); color:var(--text-secondary, #64748b); cursor:pointer;"
                                title="Reset all checkboxes to unchecked for fresh physical audit">
                                ✕ Uncheck All
                            </button>
                        </div>
                    </div>

                </div>

                <style>
                .reconcile-qty-input::-webkit-outer-spin-button,
                .reconcile-qty-input::-webkit-inner-spin-button {
                    -webkit-appearance: none !important;
                    margin: 0 !important;
                }
                .reconcile-qty-input {
                    -moz-appearance: textfield !important;
                    appearance: textfield !important;
                    color: #0f172a !important;
                    font-size: 1.05rem !important;
                    font-weight: 900 !important;
                    text-align: center !important;
                    border: none !important;
                    background: transparent !important;
                    width: 46px !important;
                    height: 32px !important;
                    line-height: 32px !important;
                    padding: 0 !important;
                    margin: 0 !important;
                    outline: none !important;
                }
                .reconcile-qty-stepper-box {
                    display: inline-flex;
                    align-items: center;
                    background: #f1f5f9;
                    border: 1.5px solid #cbd5e1;
                    border-radius: 10px;
                    padding: 2px 4px;
                    box-shadow: inset 0 1px 2px rgba(0,0,0,0.06);
                }
                .reconcile-step-btn {
                    width: 28px;
                    height: 28px;
                    border-radius: 8px;
                    border: 1px solid #cbd5e1;
                    background: #ffffff;
                    font-weight: 900;
                    font-size: 1.1rem;
                    cursor: pointer;
                    color: #0f172a;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    box-shadow: 0 1px 2px rgba(0,0,0,0.08);
                    transition: all 0.1s ease;
                    user-select: none;
                }
                .reconcile-step-btn:hover {
                    background: #f8fafc;
                    border-color: #94a3b8;
                }
                .reconcile-step-btn:active {
                    transform: scale(0.92);
                }
                </style>

                <!-- Instant Search / Scan Bar with Enter-to-Verify feature -->
                <div style="margin-bottom:14px; display:flex; gap:8px; align-items:center;">
                    <div style="position:relative; flex:1;">
                        <input type="text" id="modal-deplete-filter" 
                            placeholder="Type model, CPU, specs or scan barcode (Press Enter to Verify top match)..." 
                            oninput="filterModalDepleteList(this.value)"
                            onkeydown="handleReconcileSearchKeydown(event)"
                            style="width:100%; height:44px; border-radius:10px; border:1px solid var(--border-color, #cbd5e1); padding:0 12px 0 38px; font-size:0.9rem; font-weight:600; background:var(--bg-body, #ffffff); color:var(--text-main, #0f172a); box-shadow:inset 0 1px 2px rgba(0,0,0,0.05);">
                        <span style="position:absolute; left:12px; top:50%; transform:translateY(-50%); opacity:0.6; font-size:1.05rem;">🔍</span>
                    </div>
                    <button type="button" onclick="verifyTopFilteredItem()"
                        style="height:44px; padding:0 16px; border-radius:10px; border:1px solid #86efac; background:#f0fdf4; color:#166534; font-weight:800; font-size:0.8rem; cursor:pointer; display:flex; align-items:center; gap:6px; white-space:nowrap;">
                        <span>↵</span> Verify Found
                    </button>
                </div>

                <div id="modal-deplete-status" style="display:none; margin-bottom:12px; padding:10px 14px; border-radius:8px; font-size:0.85rem; font-weight:700;"></div>

                <!-- Table Listing with Unchecked Defaults and Verification Controls -->
                <div style="max-height:44vh; overflow-y:auto; border:1px solid var(--border-color, #e2e8f0); border-radius:14px; background:var(--bg-body, #ffffff); margin-bottom:18px;">
                    <table style="width:100%; border-collapse:collapse; text-align:left; font-size:0.85rem;">
                        <thead>
                            <tr style="background:var(--bg-body, #f8fafc); border-bottom:1px solid var(--border-color, #e2e8f0); position:sticky; top:0; z-index:10;">
                                <th style="padding:10px 12px; width:44px; text-align:center;">
                                    <input type="checkbox" id="modal-select-all-deplete" onclick="toggleSelectAllModalDeplete(this)" style="cursor:pointer; width:16px; height:16px;" title="Toggle All Checkboxes">
                                </th>
                                <th style="padding:10px 12px; font-weight:800; color:var(--text-secondary, #64748b);">Item / Hardware Specs</th>
                                <th style="padding:10px; font-weight:800; color:var(--text-secondary, #64748b); text-align:center; width:100px;">Audit Status</th>
                                <th style="padding:10px; font-weight:800; color:var(--text-secondary, #64748b); text-align:center; width:140px;">Verified Count</th>
                                <th style="padding:10px 12px; font-weight:800; color:var(--text-secondary, #64748b); text-align:right; width:50px;"></th>
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
                                    $orig_qty = (int)($it['quantity'] ?? 1);
                                ?>
                                <tr class="modal-deplete-row" 
                                    id="reconcile-row-<?= $it['id'] ?>"
                                    data-id="<?= $it['id'] ?>" 
                                    data-orig-qty="<?= $orig_qty ?>"
                                    data-verified-qty="<?= $orig_qty ?>"
                                    data-search="<?= htmlspecialchars(strtolower($it['brand'] . ' ' . $it['model'] . ' ' . implode(' ', $spec_summary))) ?>"
                                    style="border-bottom:1px solid var(--border-color, #e2e8f0); transition:background 0.15s, transform 0.15s; background:var(--bg-body, #ffffff);">
                                    
                                    <td style="padding:10px 12px; text-align:center;">
                                        <input type="checkbox" class="modal-deplete-checkbox" value="<?= $it['id'] ?>" 
                                            onchange="onReconcileRowCheckChange(this)"
                                            style="cursor:pointer; width:18px; height:18px; accent-color:#16a34a;" title="Check = Verified Physically on Shelf">
                                    </td>

                                    <td style="padding:10px 12px; cursor:pointer;" onclick="toggleRowCheckDirectly(<?= $it['id'] ?>)">
                                        <div style="font-weight:800; color:var(--text-main, #0f172a); font-size:0.9rem;">
                                            <?= htmlspecialchars($it['brand'] . ' ' . $it['model']) ?>
                                        </div>
                                        <div style="font-size:0.75rem; color:var(--text-secondary, #64748b); margin-top:2px;">
                                            <?= htmlspecialchars(implode(' • ', $spec_summary) ?: 'No specs listed') ?>
                                        </div>
                                    </td>

                                    <td style="padding:10px; text-align:center;">
                                        <span class="reconcile-row-status-badge" style="display:inline-block; padding:3px 8px; border-radius:6px; font-weight:800; font-size:0.72rem; background:#fee2e2; color:#991b1b;">
                                            ❌ Missing
                                        </span>
                                    </td>

                                    <td style="padding:10px; text-align:center;">
                                        <div class="reconcile-qty-stepper-box">
                                            <button type="button" class="reconcile-step-btn" onclick="adjustVerifiedQty(<?= $it['id'] ?>, -1)" title="Decrement Verified Qty">−</button>
                                            <input type="number" min="1" max="999" class="reconcile-qty-input" 
                                                value="<?= $orig_qty ?>" 
                                                oninput="onVerifiedQtyInputChange(<?= $it['id'] ?>, this.value)"
                                                onchange="onVerifiedQtyInputChange(<?= $it['id'] ?>, this.value)">
                                            <button type="button" class="reconcile-step-btn" onclick="adjustVerifiedQty(<?= $it['id'] ?>, 1)" title="Increment Verified Qty">+</button>
                                        </div>
                                    </td>

                                    <td style="padding:10px 12px; text-align:right;">
                                        <button type="button" onclick="purgeModalItem(<?= $it['id'] ?>, '<?= htmlspecialchars(addslashes($it['brand'] . ' ' . $it['model'])) ?>')"
                                            style="width:30px; height:30px; display:inline-flex; align-items:center; justify-content:center; border-radius:8px; border:none; background:#fee2e2; color:#b91c1c; font-size:0.85rem; cursor:pointer; transition:all 0.15s;"
                                            onmouseover="this.style.background='#fca5a5'" onmouseout="this.style.background='#fee2e2'" title="Record as Sold & Remove Immediately">
                                            🗑️
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
