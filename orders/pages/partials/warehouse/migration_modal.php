<?php
/**
 * Warehouse Item-Level Migration & Location Management Modal Dialog
 * Supports granular single-item split moves, multi-item batch relocations,
 * on-the-fly zone and shelf creation, and automated depleted-shelf cleanup.
 */
?>
<!-- ITEM MIGRATION & RELOCATION MODAL -->
<div id="item-migration-modal" class="modal-overlay no-print"
    style="display:none; position:fixed; inset:0; background:rgba(15, 23, 42, 0.7); backdrop-filter:blur(6px); z-index:2600; align-items:center; justify-content:center; padding:15px; overflow-y:auto;"
    onclick="if(event.target===this) closeItemMigrationModal()">

    <div class="migration-modal-content"
        style="background:var(--bg-card, #ffffff); color:var(--text-main, #0f172a); border-radius:24px; width:100%; max-width:620px; max-height:92vh; display:flex; flex-direction:column; box-shadow:0 25px 50px -12px rgba(0,0,0,0.35); border:1px solid var(--border-color, #e2e8f0); position:relative; overflow:hidden; animation:modalIn 0.25s cubic-bezier(0.16, 1, 0.3, 1);">

        <!-- Modal Header -->
        <div style="padding:18px 24px; border-bottom:1px solid var(--border-color, #e2e8f0); display:flex; justify-content:space-between; align-items:center; background:linear-gradient(135deg, rgba(37, 99, 235, 0.08) 0%, rgba(2, 132, 199, 0.04) 100%);">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="width:42px; height:42px; border-radius:12px; background:#2563eb; color:white; display:flex; align-items:center; justify-content:center; font-size:1.3rem; box-shadow:0 4px 12px rgba(37,99,235,0.35);">
                    🚚
                </div>
                <div>
                    <h2 id="migrate-modal-title" style="font-weight:900; font-size:1.25rem; margin:0; letter-spacing:-0.02em;">
                        Relocate & Migrate Inventory
                    </h2>
                    <p id="migrate-modal-subtitle" style="font-size:0.8rem; color:var(--text-secondary, #64748b); margin:2px 0 0 0;">
                        Transfer stock quantities across shelves and working zones.
                    </p>
                </div>
            </div>
            <button type="button" onclick="closeItemMigrationModal()"
                style="background:none; border:none; width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.3rem; color:var(--text-secondary, #64748b); cursor:pointer; transition:all 0.2s;"
                onmouseover="this.style.background='rgba(0,0,0,0.06)'" onmouseout="this.style.background='none'" title="Close (Esc)">
                ✕
            </button>
        </div>

        <!-- Modal Body (Scrollable) -->
        <div style="padding:22px 24px; overflow-y:auto; flex:1; display:flex; flex-direction:column; gap:18px;">

            <!-- Error Banner -->
            <div id="migrate-error-banner" style="display:none; background:#fee2e2; border:1px solid #fecaca; color:#b91c1c; border-radius:12px; padding:12px 16px; font-size:0.85rem; font-weight:700;">
            </div>

            <!-- Item / Selection Context Box -->
            <div id="migrate-context-box" style="background:var(--bg-body, #f8fafc); border:1px solid var(--border-color, #e2e8f0); border-radius:16px; padding:16px;">
                <!-- Single Item View -->
                <div id="migrate-single-context">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px; gap:10px;">
                        <div>
                            <div id="migrate-item-title" style="font-weight:900; font-size:1.05rem; color:var(--text-main, #0f172a);">
                                Item Make & Model
                            </div>
                            <div id="migrate-item-specs" style="font-size:0.8rem; color:var(--text-secondary, #64748b); margin-top:2px;">
                                -
                            </div>
                        </div>
                        <span id="migrate-item-sector-badge" class="badge" style="background:#0f172a; color:#facc15; font-weight:800; font-size:0.75rem; padding:4px 8px; border-radius:8px;">
                            Sector
                        </span>
                    </div>

                    <div style="display:flex; align-items:center; gap:10px; margin-top:10px; flex-wrap:wrap;">
                        <span style="font-size:0.8rem; background:rgba(37,99,235,0.1); color:#2563eb; padding:4px 10px; border-radius:8px; font-weight:700;">
                            📍 Source Shelf: <strong id="migrate-item-source-loc">-</strong>
                        </span>
                        <span style="font-size:0.8rem; background:rgba(100,116,139,0.1); color:#475569; padding:4px 10px; border-radius:8px; font-weight:700;">
                            📁 Source Zone: <strong id="migrate-item-source-zone">-</strong>
                        </span>
                        <span style="font-size:0.8rem; background:rgba(16,185,129,0.1); color:#059669; padding:4px 10px; border-radius:8px; font-weight:800; margin-left:auto;">
                            Available: <strong id="migrate-item-available-qty">0</strong> Units
                        </span>
                    </div>
                </div>

                <!-- Bulk Selection View -->
                <div id="migrate-bulk-context" style="display:none;">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span style="font-size:1.4rem;">📦</span>
                        <div>
                            <div style="font-weight:900; font-size:1.05rem;">
                                Batch Relocating <span id="migrate-bulk-selected-count" style="color:#2563eb;">0</span> Selected Items
                            </div>
                            <p style="font-size:0.8rem; color:var(--text-secondary, #64748b); margin:2px 0 0 0;">
                                All inventory units from selected rows will be moved to the destination shelf.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Transfer Quantity Selection (Single Item Mode only) -->
            <div id="migrate-qty-group">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <label for="migrate-qty-input" style="font-size:0.75rem; font-weight:800; text-transform:uppercase; color:var(--text-secondary, #64748b); letter-spacing:0.04em;">
                        Quantity to Relocate
                    </label>
                    <span style="font-size:0.75rem; color:var(--text-secondary, #64748b);">
                        Max: <strong id="migrate-qty-max-label">0</strong> Units
                    </span>
                </div>

                <div style="display:flex; gap:10px; align-items:center;">
                    <div style="display:flex; align-items:center; border:2px solid var(--border-color, #cbd5e1); border-radius:12px; overflow:hidden; background:var(--bg-card, #ffffff);">
                        <button type="button" onclick="adjustMigrateQty(-1)"
                            style="width:44px; height:44px; border:none; background:none; font-size:1.2rem; font-weight:900; color:var(--text-main, #0f172a); cursor:pointer; transition:background 0.15s;"
                            onmouseover="this.style.background='rgba(0,0,0,0.06)'" onmouseout="this.style.background='none'">
                            -
                        </button>
                        <input type="number" id="migrate-qty-input" min="1" value="1"
                            style="width:80px; height:44px; border:none; text-align:center; font-size:1.15rem; font-weight:900; color:var(--text-main, #0f172a); background:none; outline:none;"
                            oninput="validateMigrateQtyInput()">
                        <button type="button" onclick="adjustMigrateQty(1)"
                            style="width:44px; height:44px; border:none; background:none; font-size:1.2rem; font-weight:900; color:var(--text-main, #0f172a); cursor:pointer; transition:background 0.15s;"
                            onmouseover="this.style.background='rgba(0,0,0,0.06)'" onmouseout="this.style.background='none'">
                            +
                        </button>
                    </div>

                    <!-- Quick Preset Buttons -->
                    <div style="display:flex; gap:6px; flex-wrap:wrap; flex:1;">
                        <button type="button" class="btn-preset-qty" onclick="setMigrateQtyPreset(1)">1</button>
                        <button type="button" class="btn-preset-qty" onclick="setMigrateQtyPreset(5)">5</button>
                        <button type="button" class="btn-preset-qty" onclick="setMigrateQtyPreset(10)">10</button>
                        <button type="button" class="btn-preset-qty" onclick="setMigrateQtyPreset(25)">25</button>
                        <button type="button" class="btn-preset-qty" onclick="setMigrateQtyPreset(50)">50</button>
                        <button type="button" class="btn-preset-qty active" onclick="setMigrateQtyPreset('all')" id="btn-preset-all">All</button>
                    </div>
                </div>
                <div style="font-size:0.75rem; color:var(--text-secondary, #64748b); margin-top:6px;">
                    💡 Partial moves create a split row at the destination. Matching stock automatically merges.
                </div>
            </div>

            <!-- Destination Selection Form -->
            <div style="border-top:1px dashed var(--border-color, #e2e8f0); padding-top:16px;">
                <div style="font-size:0.75rem; font-weight:800; text-transform:uppercase; color:var(--text-secondary, #64748b); letter-spacing:0.04em; margin-bottom:12px;">
                    Destination Location
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                    <!-- Target Working Zone -->
                    <div class="form-group">
                        <label for="migrate-target-zone" style="display:block; font-size:0.7rem; font-weight:800; text-transform:uppercase; margin-bottom:6px; color:var(--text-secondary, #64748b);">
                            Target Working Zone
                        </label>
                        <select id="migrate-target-zone" onchange="handleMigrateZoneChange(this.value)"
                            style="width:100%; height:44px; border-radius:12px; border:1px solid var(--border-color, #cbd5e1); padding:0 12px; font-weight:700; font-size:0.9rem; background:var(--bg-card, #ffffff); color:var(--text-main, #0f172a);">
                            <option value="">-- Choose Zone --</option>
                            <?php foreach ($working_zones as $wz): 
                                $wz_name = is_array($wz) ? ($wz['name'] ?? '') : (string)$wz;
                                if (empty($wz_name)) continue;
                            ?>
                                <option value="<?= htmlspecialchars($wz_name) ?>">
                                    <?= htmlspecialchars($wz_name) ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="__NEW__" style="color:#2563eb; font-weight:800;">+ Create New Working Zone...</option>
                        </select>
                    </div>

                    <!-- Target Shelf Location -->
                    <div class="form-group">
                        <label for="migrate-target-shelf" style="display:block; font-size:0.7rem; font-weight:800; text-transform:uppercase; margin-bottom:6px; color:var(--text-secondary, #64748b);">
                            Target Shelf
                        </label>
                        <select id="migrate-target-shelf" onchange="handleMigrateShelfChange(this.value)"
                            style="width:100%; height:44px; border-radius:12px; border:1px solid var(--border-color, #cbd5e1); padding:0 12px; font-weight:700; font-size:0.9rem; background:var(--bg-card, #ffffff); color:var(--text-main, #0f172a);">
                            <option value="">-- Choose Shelf --</option>
                        </select>
                    </div>
                </div>

                <!-- Inline New Zone Drawer -->
                <div id="migrate-new-zone-drawer" style="display:none; margin-top:12px; background:rgba(37,99,235,0.05); border:1px dashed #2563eb; border-radius:12px; padding:12px 14px;">
                    <label for="migrate-new-zone-name" style="display:block; font-size:0.7rem; font-weight:800; text-transform:uppercase; color:#2563eb; margin-bottom:4px;">
                        New Working Zone Name
                    </label>
                    <input type="text" id="migrate-new-zone-name" placeholder="e.g. Zone D, Storage West..."
                        style="width:100%; height:40px; border-radius:8px; border:1px solid #cbd5e1; padding:0 12px; font-weight:700; font-size:0.9rem; background:white;">
                </div>

                <!-- Inline New Shelf Drawer -->
                <div id="migrate-new-shelf-drawer" style="display:none; margin-top:12px; background:rgba(16,185,129,0.05); border:1px dashed #10b981; border-radius:12px; padding:12px 14px;">
                    <div style="display:grid; grid-template-columns:2fr 1fr; gap:10px;">
                        <div>
                            <label for="migrate-new-shelf-code" style="display:block; font-size:0.7rem; font-weight:800; text-transform:uppercase; color:#059669; margin-bottom:4px;">
                                New Shelf Location Code
                            </label>
                            <input type="text" id="migrate-new-shelf-code" placeholder="e.g. W1-L5, Shelf-02..."
                                style="width:100%; height:40px; border-radius:8px; border:1px solid #cbd5e1; padding:0 12px; font-weight:800; font-size:0.9rem; background:white; color:#0f172a;">
                        </div>
                        <div>
                            <label for="migrate-new-shelf-status" style="display:block; font-size:0.7rem; font-weight:800; text-transform:uppercase; color:#059669; margin-bottom:4px;">
                                Initial Status
                            </label>
                            <select id="migrate-new-shelf-status"
                                style="width:100%; height:40px; border-radius:8px; border:1px solid #cbd5e1; padding:0 8px; font-weight:700; font-size:0.85rem; background:white;">
                                <option value="Working" selected>Working</option>
                                <option value="Idle">Idle</option>
                                <option value="Warehoused">Warehoused</option>
                                <option value="Audit">Audit</option>
                                <option value="Shipping">Shipping</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Auto-Archive Depleted Shelf Option -->
            <div style="background:var(--bg-body, #f8fafc); border:1px solid var(--border-color, #e2e8f0); border-radius:12px; padding:12px 16px;">
                <label style="display:flex; align-items:flex-start; gap:10px; cursor:pointer; margin:0;">
                    <input type="checkbox" id="migrate-auto-archive-check" checked
                        style="width:18px; height:18px; margin-top:2px; accent-color:#2563eb; cursor:pointer;">
                    <div>
                        <div style="font-weight:800; font-size:0.85rem; color:var(--text-main, #0f172a);">
                            Automatically archive source shelf if migration leaves it completely empty
                        </div>
                        <div style="font-size:0.75rem; color:var(--text-secondary, #64748b); margin-top:2px;">
                            Keeps warehouse navigation tidy while preserving full historical logs and uploaded photos.
                        </div>
                    </div>
                </label>
            </div>

        </div>

        <!-- Modal Footer -->
        <div style="padding:16px 24px; border-top:1px solid var(--border-color, #e2e8f0); display:flex; gap:12px; background:var(--bg-body, #f8fafc);">
            <button type="button" onclick="closeItemMigrationModal()"
                style="flex:1; height:46px; border-radius:12px; border:1px solid var(--border-color, #cbd5e1); background:none; font-weight:800; cursor:pointer; color:var(--text-secondary, #64748b); transition:background 0.15s;"
                onmouseover="this.style.background='rgba(0,0,0,0.04)'" onmouseout="this.style.background='none'">
                Cancel
            </button>
            <button type="button" id="btn-submit-migration" onclick="executeInventoryMigration()"
                style="flex:2; height:46px; border-radius:12px; border:none; background:#2563eb; color:white; font-weight:900; font-size:0.95rem; cursor:pointer; box-shadow:0 4px 12px rgba(37,99,235,0.35); display:flex; align-items:center; justify-content:center; gap:8px; transition:transform 0.15s, background 0.15s;"
                onmouseover="this.style.background='#1d4ed8'; this.style.transform='translateY(-1px)'"
                onmouseout="this.style.background='#2563eb'; this.style.transform='none'">
                <span>Confirm & Relocate Stock ➔</span>
            </button>
        </div>

    </div>
</div>

<!-- DEPLETED SHELF CONFIRMATION MODAL -->
<div id="depleted-shelf-modal" class="modal-overlay no-print"
    style="display:none; position:fixed; inset:0; background:rgba(15, 23, 42, 0.7); backdrop-filter:blur(6px); z-index:2700; align-items:center; justify-content:center; padding:15px;"
    onclick="if(event.target===this) closeDepletedShelfModal()">
    
    <div style="background:var(--bg-card, #ffffff); color:var(--text-main, #0f172a); border-radius:24px; width:100%; max-width:440px; padding:30px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.35); border:1px solid var(--border-color, #e2e8f0); text-align:center; animation:modalIn 0.25s cubic-bezier(0.16, 1, 0.3, 1);">
        <div style="width:54px; height:54px; border-radius:16px; background:#fef3c7; color:#d97706; display:flex; align-items:center; justify-content:center; font-size:1.8rem; margin:0 auto 16px auto;">
            📦
        </div>
        <h3 style="font-weight:900; font-size:1.2rem; margin:0 0 8px 0;">Shelf Completely Emptied</h3>
        <p style="font-size:0.85rem; color:var(--text-secondary, #64748b); margin:0 0 20px 0; line-height:1.5;">
            Shelf <strong id="depleted-shelf-code-display" style="color:#2563eb; font-weight:800;">-</strong> now has <strong>0 remaining items</strong>. Would you like to archive it to keep your active storage grid clean?
        </p>

        <div style="display:flex; flex-direction:column; gap:10px;">
            <button type="button" onclick="confirmArchiveDepletedShelf()"
                style="height:46px; border-radius:12px; border:none; background:#d97706; color:white; font-weight:800; cursor:pointer; box-shadow:0 4px 10px rgba(217,119,6,0.3); font-size:0.9rem;">
                📦 Archive Empty Shelf
            </button>
            <button type="button" onclick="closeDepletedShelfModal()"
                style="height:44px; border-radius:12px; border:1px solid var(--border-color, #cbd5e1); background:none; color:var(--text-secondary, #64748b); font-weight:700; cursor:pointer;">
                Keep as Active Empty Shelf
            </button>
        </div>
    </div>
</div>
