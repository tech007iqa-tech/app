<?php
/**
 * Warehouse Multi-Zone Comparison Modal Window
 * Displays side-by-side analytics, KPI matrix, shelf columns, and cross-zone inventory feeds.
 */
?>
<!-- MULTI-ZONE COMPARISON MODAL -->
<div id="warehouse-zone-comparison-modal" class="modal-overlay no-print"
    style="display:none; position:fixed; inset:0; background:rgba(15, 23, 42, 0.75); backdrop-filter:blur(8px); z-index:2600; align-items:center; justify-content:center; padding:15px; overflow-y:auto;"
    onclick="if(event.target===this) closeZoneComparisonModal()">

    <div class="zone-comparison-modal-content"
        style="background:var(--bg-card, #ffffff); color:var(--text-main, #0f172a); border-radius:24px; width:96vw; max-width:1440px; height:92vh; display:flex; flex-direction:column; box-shadow:0 25px 60px -15px rgba(0,0,0,0.5); border:1px solid var(--border-color, #e2e8f0); position:relative; overflow:hidden; animation:modalIn 0.25s cubic-bezier(0.16, 1, 0.3, 1);">

        <!-- Modal Header -->
        <div style="padding:16px 24px; border-bottom:1px solid var(--border-color, #e2e8f0); display:flex; justify-content:space-between; align-items:center; background:linear-gradient(135deg, rgba(2, 132, 199, 0.1) 0%, rgba(59, 130, 246, 0.05) 100%); flex-wrap:wrap; gap:12px;">
            <div style="display:flex; align-items:center; gap:14px; flex-wrap:wrap;">
                <div style="width:42px; height:42px; border-radius:12px; background:linear-gradient(135deg, #0284c7 0%, #2563eb 100%); color:white; display:flex; align-items:center; justify-content:center; font-size:1.35rem; box-shadow:0 4px 12px rgba(2,132,199,0.35);">
                    ⚖️
                </div>
                <div>
                    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                        <h2 style="font-weight:900; font-size:1.25rem; margin:0; letter-spacing:-0.02em;">Multi-Zone Live Comparison</h2>
                        <span id="zone-compare-count-badge" class="badge" style="background:#0f172a; color:#38bdf8; font-weight:800; font-size:0.75rem; padding:3px 10px; border-radius:20px;">
                            0 Zones
                        </span>
                    </div>
                    <p style="font-size:0.8rem; color:var(--text-secondary, #64748b); margin:2px 0 0 0;">
                        Simultaneous cross-zone inventory audit, valuations, conditions, and capacity insights.
                    </p>
                </div>
            </div>

            <!-- Controls: Sector Filter & Add Zone Dropdown -->
            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                <!-- Sector Filter Pills -->
                <div class="compare-sector-pills" style="display:inline-flex; background:rgba(0,0,0,0.05); padding:3px; border-radius:10px; border:1px solid var(--border-color, #e2e8f0);">
                    <button type="button" class="compare-sector-btn active" data-sector="" onclick="setCompareSectorFilter('')">All Sectors</button>
                    <button type="button" class="compare-sector-btn" data-sector="Laptops" onclick="setCompareSectorFilter('Laptops')">💻 Laptops</button>
                    <button type="button" class="compare-sector-btn" data-sector="Gaming" onclick="setCompareSectorFilter('Gaming')">🎮 Gaming</button>
                    <button type="button" class="compare-sector-btn" data-sector="Desktops" onclick="setCompareSectorFilter('Desktops')">🖥️ Desktops</button>
                    <button type="button" class="compare-sector-btn" data-sector="Electronics" onclick="setCompareSectorFilter('Electronics')">🔌 Electronics</button>
                </div>

                <!-- Add More Zones Dropdown -->
                <div style="position:relative;">
                    <select id="zone-compare-add-select" onchange="handleAddZoneToComparison(this.value); this.value='';"
                        style="height:36px; border-radius:8px; border:1px solid var(--border-color, #cbd5e1); padding:0 10px; font-weight:700; font-size:0.8rem; background:var(--bg-body, #ffffff); color:var(--text-main, #0f172a); cursor:pointer;">
                        <option value="">＋ Add Zone...</option>
                        <?php if (!empty($working_zones)): ?>
                            <?php foreach ($working_zones as $wz_opt): 
                                $opt_name = is_array($wz_opt) ? $wz_opt['name'] : $wz_opt;
                            ?>
                                <option value="<?= htmlspecialchars($opt_name) ?>"><?= htmlspecialchars($opt_name) ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <button type="button" onclick="closeZoneComparisonModal()" 
                    style="background:none; border:none; width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.3rem; color:var(--text-secondary, #64748b); cursor:pointer; transition:all 0.2s;"
                    onmouseover="this.style.background='rgba(0,0,0,0.06)'" onmouseout="this.style.background='none'" title="Close (Esc)">
                    ✕
                </button>
            </div>
        </div>

        <!-- Selected Zones Tag Bar & Navigation Tabs -->
        <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 24px; background:var(--bg-body, #f8fafc); border-bottom:1px solid var(--border-color, #e2e8f0); flex-wrap:wrap; gap:10px;">
            <!-- Active Compared Zones Chips -->
            <div id="zone-compare-chips-container" style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                <!-- Dynamically populated -->
            </div>

            <!-- Tab Buttons -->
            <div style="display:flex; gap:8px;">
                <button type="button" id="tab-btn-compare-matrix" class="compare-nav-tab active" onclick="switchCompareTab('matrix')">
                    📊 KPI Matrix
                </button>
                <button type="button" id="tab-btn-compare-columns" class="compare-nav-tab" onclick="switchCompareTab('columns')">
                    🗂️ Side-by-Side Shelves
                </button>
                <button type="button" id="tab-btn-compare-feed" class="compare-nav-tab" onclick="switchCompareTab('feed')">
                    📋 Unified Cross-Zone Feed
                </button>
            </div>
        </div>

        <!-- Modal Body Container (Scrollable) -->
        <div style="padding:20px 24px; overflow-y:auto; flex:1; display:flex; flex-direction:column; gap:20px; position:relative;">
            
            <!-- Loading Indicator -->
            <div id="zone-compare-loading" style="display:none; position:absolute; inset:0; background:rgba(255,255,255,0.7); backdrop-filter:blur(3px); z-index:50; align-items:center; justify-content:center; flex-direction:column; gap:12px;">
                <div class="spinner" style="width:36px; height:36px; border:3px solid #e2e8f0; border-top-color:#0284c7; border-radius:50%; animation:spin 0.8s linear infinite;"></div>
                <div style="font-weight:800; color:#0284c7; font-size:0.9rem;">Crunching Cross-Zone Analytics...</div>
            </div>

            <!-- TAB 1: EXECUTIVE KPI & ANALYTICS MATRIX -->
            <div id="compare-pane-matrix" class="compare-tab-pane" style="display:block;">
                <!-- Cross-Zone Overlap Banner (shown if overlapping models exist) -->
                <div id="compare-overlap-banner" style="display:none; margin-bottom:18px; padding:12px 18px; background:linear-gradient(135deg, #fef9c3 0%, #fef08a 100%); border:1px solid #facc15; border-radius:14px; color:#713f12; align-items:center; justify-content:space-between; font-size:0.85rem;">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span style="font-size:1.3rem;">💡</span>
                        <div>
                            <strong id="compare-overlap-title">Common Models Found Across Zones</strong>
                            <div id="compare-overlap-desc" style="font-size:0.75rem; opacity:0.9; margin-top:2px;">
                                Identical models exist across multiple compared zones. Consider consolidating to optimize shelf footprint.
                            </div>
                        </div>
                    </div>
                    <button type="button" onclick="switchCompareTab('feed'); filterCompareFeedByOverlap();"
                        style="background:#0f172a; color:#facc15; border:none; padding:6px 14px; border-radius:8px; font-weight:900; font-size:0.75rem; cursor:pointer;">
                        View Overlaps
                    </button>
                </div>

                <!-- Zone Comparative Metric Cards Grid -->
                <div id="zone-compare-cards-grid" class="zone-compare-cards-grid" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:18px;">
                    <!-- Dynamically populated with zone KPI cards -->
                </div>
            </div>

            <!-- TAB 2: SIDE-BY-SIDE LIVE SHELVES & INVENTORY (KANBAN COLUMNS) -->
            <div id="compare-pane-columns" class="compare-tab-pane" style="display:none; height:100%;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                    <span style="font-size:0.85rem; color:var(--text-secondary, #64748b); font-weight:700;">
                        ↔️ Scroll horizontally to cross-compare all selected zones side-by-side.
                    </span>
                    <input type="text" id="compare-col-filter" placeholder="Filter items in columns... (e.g. Dell, i7)" oninput="filterCompareColumns(this.value)"
                        style="height:34px; border-radius:8px; border:1px solid var(--border-color, #cbd5e1); padding:0 10px; font-size:0.8rem; width:220px;">
                </div>
                <div id="zone-compare-columns-board" class="zone-compare-columns-board" style="display:flex; gap:16px; overflow-x:auto; padding-bottom:15px; align-items:flex-start;">
                    <!-- Dynamically populated with one column per zone -->
                </div>
            </div>

            <!-- TAB 3: UNIFIED CROSS-ZONE INVENTORY TABLE -->
            <div id="compare-pane-feed" class="compare-tab-pane" style="display:none;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <input type="text" id="compare-feed-search" placeholder="Search across all compared zones..." oninput="filterCompareFeedTable(this.value)"
                            style="height:36px; border-radius:10px; border:1px solid var(--border-color, #cbd5e1); padding:0 12px; font-size:0.85rem; width:260px;">
                        <span id="compare-feed-row-count" style="font-size:0.8rem; color:#64748b; font-weight:700;">0 items</span>
                    </div>
                    <div style="display:flex; gap:8px;">
                        <button type="button" onclick="exportZoneComparisonCSV()" class="btn-export" style="height:36px; padding:0 12px; font-size:0.8rem; display:inline-flex; align-items:center; gap:6px;">
                            📊 Export CSV
                        </button>
                        <button type="button" onclick="printZoneComparisonReport()" class="btn-export" style="height:36px; padding:0 12px; font-size:0.8rem; background:#334155; color:white; border:none; display:inline-flex; align-items:center; gap:6px;">
                            🖨️ Print Summary
                        </button>
                    </div>
                </div>

                <div class="inventory-table-container" style="border:1px solid var(--border-color, #e2e8f0); border-radius:14px; overflow:hidden;">
                    <table class="inventory-table" id="zone-compare-feed-table">
                        <thead>
                            <tr>
                                <th class="col-compare-zone">Zone</th>
                                <th class="col-compare-shelf">Shelf</th>
                                <th style="min-width:180px;">Make / Model</th>
                                <th style="min-width:90px;">Sector</th>
                                <th>Specs / Remarks</th>
                                <th style="min-width:110px; text-align:center;">Condition</th>
                                <th style="min-width:70px; text-align:center;">Qty</th>
                                <th style="min-width:90px; text-align:right;">Price</th>
                                <th style="min-width:105px; text-align:right;">Valuation</th>
                                <th class="col-compare-actions" style="text-align:center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="zone-compare-feed-tbody">
                            <!-- Populated dynamically -->
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

    </div>
</div>

<!-- FLOATING MULTI-ZONE SELECTION BOTTOM DOCK -->
<div id="zone-multi-select-dock" class="zone-multi-select-dock no-print" style="display:none;">
    <div class="dock-content">
        <div style="display:flex; align-items:center; gap:10px;">
            <div class="dock-icon">⚖️</div>
            <div>
                <div style="font-weight:900; font-size:0.95rem; line-height:1.2; color:white;">
                    Zone Comparison Selection
                </div>
                <div id="dock-selected-zones-text" style="font-size:0.75rem; color:#93c5fd; font-weight:700;">
                    0 Zones Selected
                </div>
            </div>
        </div>

        <div id="dock-chips-preview" style="display:flex; gap:6px; align-items:center; overflow-x:auto; max-width:400px; padding:2px 0;">
            <!-- Zone tags in dock -->
        </div>

        <div style="display:flex; align-items:center; gap:8px;">
            <button type="button" id="btn-dock-launch-compare" onclick="launchComparisonFromDock()" class="btn-dock-compare">
                📊 Compare Selected (<span id="dock-count-num">0</span>)
            </button>
            <button type="button" onclick="selectAllZonesForCompare()" class="btn-dock-secondary" title="Select all working zones">
                Select All
            </button>
            <button type="button" onclick="exitZoneCompareMode()" class="btn-dock-cancel" title="Exit comparison mode (Esc)">
                ✕ Exit
            </button>
        </div>
    </div>
</div>
