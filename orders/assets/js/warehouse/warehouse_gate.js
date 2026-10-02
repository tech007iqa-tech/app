/**
 * Warehouse Gate Navigation Module
 * Handles search filtering, sorting, and view-mode toggling for Working Zones and All Locations.
 */

/**
 * Switches the Gate view between "By Working Zone" and "All Locations"
 * @param {'zones'|'all_locations'} mode
 */
function switchGateViewMode(mode) {
    const zonesContainer = document.getElementById('gate-view-zones-container');
    const allLocsContainer = document.getElementById('gate-view-all-locs-container');
    const archContainer = document.getElementById('gate-view-archived-container');
    const btnZones = document.getElementById('btn-gate-view-zones');
    const btnAll = document.getElementById('btn-gate-view-all');
    const btnArch = document.getElementById('btn-gate-view-archived');

    const updateBtn = (btn, isActive) => {
        if (!btn) return;
        if (isActive) {
            btn.classList.add('active');
            btn.style.background = 'white';
            btn.style.color = 'var(--text-main)';
            btn.style.boxShadow = '0 1px 3px rgba(0,0,0,0.1)';
        } else {
            btn.classList.remove('active');
            btn.style.background = 'transparent';
            btn.style.color = '#64748b';
            btn.style.boxShadow = 'none';
        }
    };

    if (mode === 'all_locations') {
        if (zonesContainer) zonesContainer.style.display = 'none';
        if (allLocsContainer) allLocsContainer.style.display = 'block';
        if (archContainer) archContainer.style.display = 'none';

        updateBtn(btnZones, false);
        updateBtn(btnAll, true);
        updateBtn(btnArch, false);
        sessionStorage.setItem('wh_gate_view_mode', 'all_locations');
    } else if (mode === 'archived') {
        if (zonesContainer) zonesContainer.style.display = 'none';
        if (allLocsContainer) allLocsContainer.style.display = 'none';
        if (archContainer) archContainer.style.display = 'block';

        updateBtn(btnZones, false);
        updateBtn(btnAll, false);
        updateBtn(btnArch, true);
        sessionStorage.setItem('wh_gate_view_mode', 'archived');
    } else {
        if (zonesContainer) zonesContainer.style.display = 'block';
        if (allLocsContainer) allLocsContainer.style.display = 'none';
        if (archContainer) archContainer.style.display = 'none';

        updateBtn(btnZones, true);
        updateBtn(btnAll, false);
        updateBtn(btnArch, false);
        sessionStorage.setItem('wh_gate_view_mode', 'zones');
    }

    // Re-filter with current search keyword
    filterGateLocations();
}

/**
 * Gets the active grid element in the Gate view
 * @returns {HTMLElement|null}
 */
function getActiveGateGrid() {
    // 1. Check if inside single zone
    const singleZoneGrid = document.getElementById('gate-loc-grid');
    if (singleZoneGrid && singleZoneGrid.offsetParent !== null) return singleZoneGrid;

    // 2. Check if in All Locations mode
    const allLocsGrid = document.getElementById('gate-all-locs-grid');
    if (allLocsGrid && allLocsGrid.offsetParent !== null) return allLocsGrid;

    // 3. Check if in Archived mode
    const archGrid = document.getElementById('gate-archived-locs-grid');
    if (archGrid && archGrid.offsetParent !== null) return archGrid;

    // 4. Default to Zones grid
    const zonesGrid = document.getElementById('gate-zones-grid');
    if (zonesGrid && zonesGrid.offsetParent !== null) return zonesGrid;

    return singleZoneGrid || allLocsGrid || archGrid || zonesGrid;
}

/**
 * Filters the locations on the Gate page in real-time
 */
function filterGateLocations() {
    const input = document.getElementById('gate-loc-search');
    const noResults = document.getElementById('gate-no-results');
    if (!input) return;

    const filter = input.value.toLowerCase().trim();
    const activeGrid = getActiveGateGrid();
    if (!activeGrid) return;

    const items = activeGrid.getElementsByClassName('gate-loc-item');
    let found = 0;

    for (let i = 0; i < items.length; i++) {
        const item = items[i];
        const wrapper = item.closest('.loc-item-wrapper') || item;
        const locName = item.getAttribute('data-loc-name') || "";
        const zoneName = item.getAttribute('data-zone-name') || "";

        if (locName.includes(filter) || zoneName.includes(filter)) {
            wrapper.style.display = "";
            found++;
        } else {
            wrapper.style.display = "none";
        }
    }

    if (noResults) {
        noResults.style.display = (found === 0 ? "block" : "none");
    }
}

/**
 * Sorts the locations on the Gate page (A-Z, Z-A, status group, count, parent zone)
 */
function sortGateLocations() {
    const sortVal = document.getElementById('gate-loc-sort')?.value || 'asc';
    const grid = getActiveGateGrid();
    if (!grid) return;

    // Persist preference
    localStorage.setItem('wh_gate_sort', sortVal);

    // Add visual feedback
    grid.classList.add('sorting');

    setTimeout(() => {
        // Get all children that are zone items or their wrappers
        const items = Array.from(grid.children);
        const zoneItems = items.filter(el => el.classList.contains('loc-item-wrapper'));
        const newLocItem = items.find(el => el.classList.contains('new_loc') || el.classList.contains('new-loc'));

        const statusPriority = {
            'working': 1,
            'audit': 2,
            'shipping': 3,
            'in-review': 4,
            'warehoused': 5,
            'idle': 6
        };

        zoneItems.sort((a, b) => {
            const itemA = a.querySelector('.gate-loc-item');
            const itemB = b.querySelector('.gate-loc-item');
            if (!itemA || !itemB) return 0;

            const nameA = itemA.getAttribute('data-loc-name') || "";
            const nameB = itemB.getAttribute('data-loc-name') || "";
            const zoneA = itemA.getAttribute('data-zone-name') || "";
            const zoneB = itemB.getAttribute('data-zone-name') || "";
            const countA = parseInt(itemA.getAttribute('data-count') || "0", 10);
            const countB = parseInt(itemB.getAttribute('data-count') || "0", 10);
            const statusA = itemA.getAttribute('data-status') || "idle";
            const statusB = itemB.getAttribute('data-status') || "idle";

            if (sortVal === 'asc') return nameA.localeCompare(nameB, undefined, { numeric: true, sensitivity: 'base' });
            if (sortVal === 'desc') return nameB.localeCompare(nameA, undefined, { numeric: true, sensitivity: 'base' });

            if (sortVal === 'count-desc') return countB - countA || nameA.localeCompare(nameB);
            if (sortVal === 'count-asc') return countA - countB || nameA.localeCompare(nameB);

            if (sortVal === 'zone') {
                return zoneA.localeCompare(zoneB) || nameA.localeCompare(nameB, undefined, { numeric: true });
            }

            if (sortVal === 'status') {
                const prioA = statusPriority[statusA] || 99;
                const prioB = statusPriority[statusB] || 99;
                return prioA - prioB || nameA.localeCompare(nameB);
            }

            return 0;
        });

        // Re-append in order
        zoneItems.forEach(el => grid.appendChild(el));
        if (newLocItem) grid.appendChild(newLocItem);

        grid.classList.remove('sorting');
    }, 200);
}

// Hydrate saved mode & sort on page load, and initialize hold-to-compare events
document.addEventListener('DOMContentLoaded', () => {
    const savedMode = sessionStorage.getItem('wh_gate_view_mode');
    if (savedMode === 'all_locations') {
        switchGateViewMode('all_locations');
    }

    const savedSort = localStorage.getItem('wh_gate_sort');
    const sortSelect = document.getElementById('gate-loc-sort');
    if (savedSort && sortSelect) {
        sortSelect.value = savedSort;
    }

    // Initialize Hold-to-Select and Multi-Zone compare interaction
    initZoneHoldToSelectEvents();
});

/* ==========================================================================
   MULTI-ZONE LIVE COMPARISON & HOLD-TO-SELECT ENGINE
   ========================================================================== */

let zoneCompareMode = false;
let selectedZonesForCompare = new Set();
let activeComparisonZones = [];
let activeComparisonSector = '';
let comparisonDataCache = null;

let holdTimer = null;
let holdActiveCard = null;
let isHoldTriggered = false;
let holdStartX = 0;
let holdStartY = 0;
const ZONE_HOLD_DURATION = 380; // ms to activate compare mode on press-and-hold

/**
 * Initializes hold-click / long-press detection on zone cards
 */
function initZoneHoldToSelectEvents() {
    const zonesGrid = document.getElementById('gate-zones-grid');
    if (!zonesGrid || zonesGrid.dataset.holdEventsBound === 'true') return;
    zonesGrid.dataset.holdEventsBound = 'true';

    // Pointer Down (Mouse or Touch press)
    zonesGrid.addEventListener('pointerdown', (e) => {
        // Ignore if clicking on the rename button or add zone form
        if (e.target.closest('.btn-rename-zone') || e.target.closest('.new-loc')) return;

        const card = e.target.closest('.gate-zone-card');
        if (!card) return;

        // If already in multi-select mode, the regular click handles selection
        if (zoneCompareMode) return;

        holdActiveCard = card;
        holdStartX = e.clientX;
        holdStartY = e.clientY;
        isHoldTriggered = false;

        card.classList.add('zone-hold-arming');

        clearTimeout(holdTimer);
        holdTimer = setTimeout(() => {
            isHoldTriggered = true;
            card.classList.remove('zone-hold-arming');
            card.classList.add('zone-hold-activated');
            setTimeout(() => card.classList.remove('zone-hold-activated'), 300);

            if (navigator.vibrate) {
                try { navigator.vibrate(40); } catch (_) {}
            }

            const zoneName = card.getAttribute('data-zone-name');
            enterZoneCompareMode(zoneName);

            const notifyEngine = window.Notifications || window.IQA_Notify;
            if (notifyEngine && typeof notifyEngine.info === 'function') {
                notifyEngine.info(`Zone Compare Mode Activated ✨ Click other zones to compare.`);
            }
        }, ZONE_HOLD_DURATION);
    });

    // Pointer Move (cancel if dragged/scrolled)
    zonesGrid.addEventListener('pointermove', (e) => {
        if (!holdTimer) return;
        const dist = Math.hypot(e.clientX - holdStartX, e.clientY - holdStartY);
        if (dist > 12) {
            clearTimeout(holdTimer);
            holdTimer = null;
            if (holdActiveCard) {
                holdActiveCard.classList.remove('zone-hold-arming');
                holdActiveCard = null;
            }
        }
    });

    // Pointer Up / Cancel
    const cancelHold = () => {
        if (holdTimer) {
            clearTimeout(holdTimer);
            holdTimer = null;
        }
        if (holdActiveCard) {
            holdActiveCard.classList.remove('zone-hold-arming');
            holdActiveCard = null;
        }
    };
    zonesGrid.addEventListener('pointerup', cancelHold);
    zonesGrid.addEventListener('pointercancel', cancelHold);

    // Click handler on Zone Card
    zonesGrid.addEventListener('click', (e) => {
        // If clicking rename or add new zone, let it work normally
        if (e.target.closest('.btn-rename-zone') || e.target.closest('.new-loc')) return;

        const card = e.target.closest('.gate-zone-card');
        if (!card) return;

        // If hold just triggered compare mode, cancel this immediate navigation click
        if (isHoldTriggered) {
            e.preventDefault();
            e.stopPropagation();
            isHoldTriggered = false;
            return;
        }

        // If in Compare Mode, toggle this zone selection instead of navigating
        if (zoneCompareMode) {
            e.preventDefault();
            e.stopPropagation();
            const zoneName = card.getAttribute('data-zone-name');
            toggleZoneSelection(zoneName);
        }
    });

    // Esc key to exit compare mode
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const compModal = document.getElementById('warehouse-zone-comparison-modal');
            if (compModal && compModal.style.display !== 'none') {
                closeZoneComparisonModal();
            } else if (zoneCompareMode) {
                exitZoneCompareMode();
            }
        }
    });
}

/**
 * Enters multi-zone selection comparison mode
 * @param {string} [initialZone]
 */
function enterZoneCompareMode(initialZone) {
    zoneCompareMode = true;
    const zonesGrid = document.getElementById('gate-zones-grid');
    if (zonesGrid) zonesGrid.classList.add('zone-compare-mode-active');

    const toggleBtn = document.getElementById('btn-gate-toggle-compare');
    if (toggleBtn) {
        toggleBtn.style.background = '#0284c7';
        toggleBtn.style.color = 'white';
        toggleBtn.style.borderColor = '#0284c7';
    }

    if (initialZone) {
        selectedZonesForCompare.add(initialZone);
        syncZoneCardVisual(initialZone, true);
    }

    const dock = document.getElementById('zone-multi-select-dock');
    if (dock) dock.style.display = 'block';

    updateDockVisuals();
}

/**
 * Exits multi-zone selection mode and resets cards
 */
function exitZoneCompareMode() {
    zoneCompareMode = false;
    selectedZonesForCompare.clear();

    const zonesGrid = document.getElementById('gate-zones-grid');
    if (zonesGrid) zonesGrid.classList.remove('zone-compare-mode-active');

    const toggleBtn = document.getElementById('btn-gate-toggle-compare');
    if (toggleBtn) {
        toggleBtn.style.background = '#e0f2fe';
        toggleBtn.style.color = '#0284c7';
        toggleBtn.style.borderColor = '#bae6fd';
    }

    document.querySelectorAll('.zone-card-wrapper').forEach(w => {
        w.classList.remove('zone-compare-selected');
    });

    const dock = document.getElementById('zone-multi-select-dock');
    if (dock) dock.style.display = 'none';
}

/**
 * Toggles compare mode on/off from the header button
 */
function toggleZoneCompareMode() {
    if (zoneCompareMode) {
        exitZoneCompareMode();
    } else {
        enterZoneCompareMode();
    }
}

/**
 * Toggles selection of a specific zone
 * @param {string} zoneName
 */
function toggleZoneSelection(zoneName) {
    if (!zoneName) return;

    if (selectedZonesForCompare.has(zoneName)) {
        selectedZonesForCompare.delete(zoneName);
        syncZoneCardVisual(zoneName, false);
    } else {
        selectedZonesForCompare.add(zoneName);
        syncZoneCardVisual(zoneName, true);
    }

    updateDockVisuals();
}

/**
 * Syncs the visual selected highlight on the zone card in the DOM
 */
function syncZoneCardVisual(zoneName, isSelected) {
    const wrappers = document.querySelectorAll(`.zone-card-wrapper[data-zone="${zoneName}"]`);
    wrappers.forEach(w => {
        if (isSelected) {
            w.classList.add('zone-compare-selected');
        } else {
            w.classList.remove('zone-compare-selected');
        }
    });
}

/**
 * Selects all visible zones for comparison
 */
function selectAllZonesForCompare() {
    document.querySelectorAll('.zone-card-wrapper').forEach(w => {
        const zone = w.getAttribute('data-zone');
        if (zone) {
            selectedZonesForCompare.add(zone);
            w.classList.add('zone-compare-selected');
        }
    });
    updateDockVisuals();
}

/**
 * Updates the floating dock tags, counter, and button state
 */
function updateDockVisuals() {
    const count = selectedZonesForCompare.size;
    const textEl = document.getElementById('dock-selected-zones-text');
    const numEl = document.getElementById('dock-count-num');
    const chipsContainer = document.getElementById('dock-chips-preview');
    const launchBtn = document.getElementById('btn-dock-launch-compare');

    if (textEl) {
        textEl.textContent = count === 1 ? '1 Zone Selected (Select another)' : `${count} Zones Selected`;
    }
    if (numEl) {
        numEl.textContent = count;
    }
    if (launchBtn) {
        launchBtn.disabled = count === 0;
    }

    if (chipsContainer) {
        chipsContainer.innerHTML = '';
        selectedZonesForCompare.forEach(z => {
            const chip = document.createElement('span');
            chip.className = 'dock-zone-chip';
            chip.innerHTML = `<span>${z}</span><button type="button" onclick="event.stopPropagation(); toggleZoneSelection('${z}')" title="Remove">✕</button>`;
            chipsContainer.appendChild(chip);
        });
    }
}

/**
 * Triggers modal launch from the floating dock
 */
function launchComparisonFromDock() {
    if (selectedZonesForCompare.size === 0) {
        alert("Please select at least 1 or 2 zones to compare.");
        return;
    }
    openZoneComparisonModal(Array.from(selectedZonesForCompare));
}

/**
 * Opens the Zone Comparison Window
 * @param {string[]} zonesList
 * @param {string} [sectorFilter]
 */
async function openZoneComparisonModal(zonesList, sectorFilter = '') {
    if (!zonesList || zonesList.length === 0) return;

    activeComparisonZones = [...zonesList];
    activeComparisonSector = sectorFilter;

    const modal = document.getElementById('warehouse-zone-comparison-modal');
    if (!modal) return;
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';

    // Update active sector pill
    document.querySelectorAll('.compare-sector-btn').forEach(btn => {
        if (btn.getAttribute('data-sector') === activeComparisonSector) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });

    renderCompareHeaderChips();
    await fetchAndRenderZoneComparison();
}

/**
 * Closes the Zone Comparison Window
 */
function closeZoneComparisonModal() {
    const modal = document.getElementById('warehouse-zone-comparison-modal');
    if (modal) modal.style.display = 'none';
    document.body.style.overflow = '';
}

/**
 * Renders header zone chips inside the comparison window with remove (✕) triggers
 */
function renderCompareHeaderChips() {
    const container = document.getElementById('zone-compare-chips-container');
    const countBadge = document.getElementById('zone-compare-count-badge');
    if (!container) return;

    if (countBadge) {
        countBadge.textContent = `${activeComparisonZones.length} Zones`;
    }

    container.innerHTML = '';
    activeComparisonZones.forEach(z => {
        const chip = document.createElement('span');
        chip.className = 'dock-zone-chip';
        chip.style.fontSize = '0.8rem';
        chip.style.padding = '4px 10px';
        chip.innerHTML = `
            <span style="font-weight:900;">📍 ${z}</span>
            <button type="button" onclick="handleRemoveZoneFromComparison('${z}')" title="Remove from comparison" style="font-size:0.95rem; margin-left:2px;">✕</button>
        `;
        container.appendChild(chip);
    });
}

/**
 * Switches tab inside comparison modal
 * @param {'matrix'|'columns'|'feed'} tabName
 */
function switchCompareTab(tabName) {
    const tabs = ['matrix', 'columns', 'feed'];
    tabs.forEach(t => {
        const btn = document.getElementById(`tab-btn-compare-${t}`);
        const pane = document.getElementById(`compare-pane-${t}`);
        if (t === tabName) {
            if (btn) btn.classList.add('active');
            if (pane) pane.style.display = 'block';
        } else {
            if (btn) btn.classList.remove('active');
            if (pane) pane.style.display = 'none';
        }
    });
}

/**
 * Sets sector filter for the comparison window and re-fetches
 */
function setCompareSectorFilter(sector) {
    activeComparisonSector = sector;
    document.querySelectorAll('.compare-sector-btn').forEach(btn => {
        if (btn.getAttribute('data-sector') === sector) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });
    fetchAndRenderZoneComparison();
}

/**
 * Dynamically adds a new zone into the active comparison window
 */
function handleAddZoneToComparison(zoneName) {
    if (!zoneName || activeComparisonZones.includes(zoneName)) return;
    activeComparisonZones.push(zoneName);
    selectedZonesForCompare.add(zoneName);
    syncZoneCardVisual(zoneName, true);
    updateDockVisuals();
    renderCompareHeaderChips();
    fetchAndRenderZoneComparison();
}

/**
 * Dynamically removes a zone from the active comparison window
 */
function handleRemoveZoneFromComparison(zoneName) {
    if (activeComparisonZones.length <= 1) {
        if (window.IQA_Notify) {
            window.IQA_Notify.warning("At least one zone must remain in the comparison.");
        } else {
            alert("At least one zone must remain in the comparison.");
        }
        return;
    }
    activeComparisonZones = activeComparisonZones.filter(z => z !== zoneName);
    selectedZonesForCompare.delete(zoneName);
    syncZoneCardVisual(zoneName, false);
    updateDockVisuals();
    renderCompareHeaderChips();
    fetchAndRenderZoneComparison();
}

/**
 * Fetches data from api/compare_zones.php and populates all tabs
 */
async function fetchAndRenderZoneComparison() {
    const loading = document.getElementById('zone-compare-loading');
    if (loading) loading.style.display = 'flex';

    try {
        const queryParams = new URLSearchParams({
            zones: activeComparisonZones.join(','),
            sector: activeComparisonSector
        });

        const res = await fetch(`api/compare_zones.php?${queryParams.toString()}`);
        const data = await res.json();

        if (!data.success) {
            throw new Error(data.error || 'Failed to load comparison data');
        }

        comparisonDataCache = data;

        // Render Tabs
        renderCompareMatrixTab(data);
        renderCompareColumnsTab(data);
        renderCompareFeedTab(data);

    } catch (err) {
        console.error("Comparison fetch failed:", err);
        const notifyEngine = window.Notifications || window.IQA_Notify;
        if (notifyEngine && typeof notifyEngine.error === 'function') {
            notifyEngine.error(err.message || 'Error loading comparison.');
        } else {
            alert(err.message || 'Error loading comparison.');
        }
    } finally {
        if (loading) loading.style.display = 'none';
    }
}

/**
 * Renders Tab 1: Executive KPI & Analytics Matrix
 */
function renderCompareMatrixTab(data) {
    const grid = document.getElementById('zone-compare-cards-grid');
    const overlapBanner = document.getElementById('compare-overlap-banner');
    const overlapDesc = document.getElementById('compare-overlap-desc');
    if (!grid) return;

    grid.innerHTML = '';

    // Overlap models banner
    if (data.summary && data.summary.overlapping_models_count > 0 && overlapBanner) {
        overlapBanner.style.display = 'flex';
        const sampleModels = data.summary.overlapping_models.slice(0, 3).map(m => `"${m.brand} ${m.model}"`).join(', ');
        if (overlapDesc) {
            overlapDesc.textContent = `${data.summary.overlapping_models_count} overlapping models found (e.g. ${sampleModels}). They exist in multiple of your selected zones.`;
        }
    } else if (overlapBanner) {
        overlapBanner.style.display = 'none';
    }

    const zonesData = data.zones_data || {};
    const zoneKeys = Object.keys(zonesData);

    zoneKeys.forEach(zoneKey => {
        const zd = zonesData[zoneKey];
        const card = document.createElement('div');
        card.className = 'zone-kpi-card';

        // Conditions breakdown HTML
        const conds = zd.condition_breakdown || {};
        let condHtml = `
            <div style="display:flex; gap:6px; flex-wrap:wrap; margin-top:4px;">
                <span class="condition-badge cond-b-grade" style="font-size:0.75rem; padding:3px 8px;">B Grade: ${conds['B Grade'] || 0}</span>
                <span class="condition-badge cond-a-grade" style="font-size:0.75rem; padding:3px 8px;">A Grade: ${conds['A Grade'] || 0}</span>
                <span class="condition-badge cond-c-grade" style="font-size:0.75rem; padding:3px 8px;">C Grade: ${conds['C Grade'] || 0}</span>
                <span class="condition-badge cond-no-power" style="font-size:0.75rem; padding:3px 8px;">No Power: ${conds['No Power'] || 0}</span>
                <span class="condition-badge cond-no-post" style="font-size:0.75rem; padding:3px 8px;">No Post: ${conds['No Post'] || 0}</span>
            </div>
        `;

        // Top Brands HTML
        const topBrands = Object.entries(zd.top_brands || {}).map(([b, cnt]) => `<strong>${b}</strong> (${cnt})`).join(', ') || 'No items';

        // Sectors Breakdown HTML
        const sectors = Object.entries(zd.sector_breakdown || {}).map(([s, cnt]) => `<span style="background:rgba(0,0,0,0.06); padding:2px 8px; border-radius:6px; font-size:0.75rem; font-weight:700;">${s}: ${cnt}</span>`).join(' ') || 'None';

        card.innerHTML = `
            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                <div>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span style="font-size:1.3rem;">☷</span>
                        <h3 style="font-weight:900; font-size:1.2rem; margin:0; letter-spacing:-0.02em;">Zone ${zd.name}</h3>
                    </div>
                    <span style="font-size:0.75rem; color:#64748b; font-weight:700;">${zd.total_shelves} Shelves / Locations</span>
                </div>
                <div style="text-align:right;">
                    <div style="font-weight:900; font-size:1.35rem; color:#0284c7; line-height:1;">
                        ${Number(zd.total_units).toLocaleString()} <span style="font-size:0.8rem; font-weight:700; color:#64748b;">Units</span>
                    </div>
                    <div style="font-size:0.8rem; font-weight:800; color:#16a34a; margin-top:2px;">
                        $${Number(zd.total_valuation).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                    </div>
                </div>
            </div>

            <!-- Health Bar -->
            <div style="background:var(--bg-body, #f8fafc); padding:10px 14px; border-radius:12px; border:1px solid var(--border-color, #e2e8f0);">
                <div style="display:flex; justify-content:space-between; font-size:0.75rem; font-weight:800; margin-bottom:6px;">
                    <span>Shelf Statuses</span>
                    <span>Working: ${zd.shelves_breakdown.working} | Audit: ${zd.shelves_breakdown.audit} | Idle: ${zd.shelves_breakdown.idle}</span>
                </div>
                <div style="height:6px; border-radius:10px; background:#e2e8f0; display:flex; overflow:hidden;">
                    <div style="background:#22c55e; width:${zd.total_shelves > 0 ? (zd.shelves_breakdown.working / zd.total_shelves * 100) : 0}%;"></div>
                    <div style="background:#f59e0b; width:${zd.total_shelves > 0 ? (zd.shelves_breakdown.audit / zd.total_shelves * 100) : 0}%;"></div>
                    <div style="background:#94a3b8; width:${zd.total_shelves > 0 ? (zd.shelves_breakdown.idle / zd.total_shelves * 100) : 0}%;"></div>
                </div>
            </div>

            <!-- Condition Breakdown -->
            <div>
                <label style="display:block; font-size:0.7rem; font-weight:800; text-transform:uppercase; color:#64748b; margin-bottom:4px;">Stock Conditions</label>
                ${condHtml}
            </div>

            <!-- Sector Distribution -->
            <div>
                <label style="display:block; font-size:0.7rem; font-weight:800; text-transform:uppercase; color:#64748b; margin-bottom:4px;">Sector Breakdown</label>
                <div style="display:flex; gap:6px; flex-wrap:wrap;">${sectors}</div>
            </div>

            <!-- Top Brands -->
            <div style="font-size:0.78rem; color:#475569; border-top:1px dashed var(--border-color, #e2e8f0); padding-top:10px;">
                <span style="font-weight:800; text-transform:uppercase; font-size:0.7rem; color:#64748b; display:block; margin-bottom:2px;">Top Brands</span>
                ${topBrands}
            </div>
        `;
        grid.appendChild(card);
    });
}

/**
 * Renders Tab 2: Side-by-Side Shelf Columns
 */
function renderCompareColumnsTab(data) {
    const board = document.getElementById('zone-compare-columns-board');
    if (!board) return;

    board.innerHTML = '';
    const zonesData = data.zones_data || {};
    const zoneKeys = Object.keys(zonesData);

    zoneKeys.forEach(zoneKey => {
        const zd = zonesData[zoneKey];
        const col = document.createElement('div');
        col.className = 'zone-column-card';
        col.dataset.zone = zd.name;

        // Group items by shelf location
        const shelfMap = {};
        (zd.locations || []).forEach(loc => {
            shelfMap[loc.location_code] = {
                status: loc.status || 'Working',
                items: []
            };
        });

        (zd.items || []).forEach(it => {
            const loc = it.location_code || 'Unassigned';
            if (!shelfMap[loc]) {
                shelfMap[loc] = { status: 'Working', items: [] };
            }
            shelfMap[loc].items.push(it);
        });

        let shelvesHtml = '';
        Object.keys(shelfMap).forEach(locCode => {
            const sh = shelfMap[locCode];
            const hasItems = sh.items.length > 0;

            let itemsListHtml = '';
            sh.items.forEach(it => {
                const cond = it.specs?.condition || 'B Grade';
                const condClass = 'cond-' + cond.toLowerCase().replace(/\s+/g, '-');
                const itJson = encodeURIComponent(JSON.stringify(it));

                itemsListHtml += `
                    <div class="item-mini-card" data-search="${(it.brand + ' ' + it.model + ' ' + cond).toLowerCase()}">
                        <div style="flex:1; min-width:0;">
                            <div style="font-weight:800; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="${it.brand} ${it.model}">
                                ${it.brand} ${it.model}
                            </div>
                            <div style="display:flex; align-items:center; gap:6px; margin-top:2px;">
                                <span class="condition-badge ${condClass}" style="font-size:0.65rem; padding:1px 5px;">${cond}</span>
                                <span style="font-weight:800; color:#0284c7;">${it.quantity}x</span>
                                <span style="color:#64748b; font-size:0.72rem;">$${it.price}</span>
                            </div>
                        </div>
                        <button type="button" onclick="triggerMigrateFromCompare(decodeURIComponent('${itJson}'))" 
                            style="background:none; border:none; cursor:pointer; font-size:1rem; opacity:0.75; padding:2px;" title="Transfer Stock to another Shelf/Zone">
                            🚚
                        </button>
                    </div>
                `;
            });

            shelvesHtml += `
                <div class="shelf-group-card">
                    <div style="display:flex; justify-content:space-between; align-items:center; font-weight:800; font-size:0.8rem; margin-bottom:4px;">
                        <span>📍 ${locCode}</span>
                        <span style="font-size:0.7rem; color:${hasItems ? '#0284c7' : '#94a3b8'};">${sh.items.length} types</span>
                    </div>
                    ${itemsListHtml || '<div style="font-size:0.72rem; color:#94a3b8; font-style:italic;">Empty shelf</div>'}
                </div>
            `;
        });

        col.innerHTML = `
            <div class="zone-column-header">
                <div>
                    <h4 style="font-weight:900; margin:0; font-size:1rem;">Zone ${zd.name}</h4>
                    <span style="font-size:0.72rem; color:#64748b; font-weight:700;">${zd.total_shelves} Shelves</span>
                </div>
                <div style="text-align:right;">
                    <span style="font-weight:900; color:#0284c7; font-size:0.95rem;">${zd.total_units} Units</span>
                    <div style="font-size:0.75rem; color:#16a34a; font-weight:800;">$${Number(zd.total_valuation).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
                </div>
            </div>
            <div class="zone-column-body">
                ${shelvesHtml}
            </div>
        `;

        board.appendChild(col);
    });
}

/**
 * Renders Tab 3: Unified Cross-Zone Inventory Feed
 */
function renderCompareFeedTab(data) {
    const tbody = document.getElementById('zone-compare-feed-tbody');
    const countEl = document.getElementById('compare-feed-row-count');
    if (!tbody) return;

    tbody.innerHTML = '';
    const zonesData = data.zones_data || {};
    let totalRows = 0;

    Object.keys(zonesData).forEach(zoneKey => {
        const zd = zonesData[zoneKey];
        (zd.items || []).forEach(it => {
            totalRows++;
            const tr = document.createElement('tr');
            const cond = it.specs?.condition || 'B Grade';
            const condClass = 'cond-' + cond.toLowerCase().replace(/\s+/g, '-');
            const itJson = encodeURIComponent(JSON.stringify(it));

            const specsSummary = [
                it.specs?.cpu,
                it.specs?.ram,
                it.specs?.storage,
                it.specs?.notes
            ].filter(Boolean).join(' • ') || '-';

            tr.dataset.search = `${zd.name} ${it.location_code} ${it.brand} ${it.model} ${it.sector} ${cond} ${specsSummary}`.toLowerCase();

            tr.innerHTML = `
                <td class="col-compare-zone">
                    <span class="zone-badge-pill" title="Zone: ${zd.name}">
                        <span class="zone-badge-icon">☷</span>
                        <span class="zone-badge-name">${zd.name}</span>
                    </span>
                </td>
                <td class="col-compare-shelf font-mono font-bold">${it.location_code}</td>
                <td>
                    <div style="font-weight:800; white-space:nowrap;">${it.brand} ${it.model}</div>
                </td>
                <td>
                    <span style="font-size:0.75rem; font-weight:700; color:#64748b;">${it.sector}</span>
                </td>
                <td style="font-size:0.8rem; color:#475569;">${specsSummary}</td>
                <td style="text-align:center;">
                    <span class="condition-badge ${condClass}" style="font-size:0.75rem; padding:3px 8px; white-space:nowrap;">${cond}</span>
                </td>
                <td style="text-align:center; font-weight:800; font-size:0.95rem;">${it.quantity}</td>
                <td style="text-align:right; font-weight:700; white-space:nowrap;">$${Number(it.price).toFixed(2)}</td>
                <td style="text-align:right; font-weight:800; color:#16a34a; white-space:nowrap;">$${Number(it.line_valuation).toFixed(2)}</td>
                <td class="col-compare-actions" style="text-align:center;">
                    <button type="button" onclick="triggerMigrateFromCompare(decodeURIComponent('${itJson}'))" 
                        class="btn-export" style="height:28px; padding:0 10px; font-size:0.75rem; display:inline-flex; align-items:center; gap:5px; white-space:nowrap;" title="Transfer Stock to another shelf or zone">
                        🚚 Move
                    </button>
                </td>
            `;

            tbody.appendChild(tr);
        });
    });

    if (countEl) {
        countEl.textContent = `${totalRows} items across ${Object.keys(zonesData).length} zones`;
    }
}

/**
 * Filter items in Side-by-Side columns board
 */
function filterCompareColumns(query) {
    const q = query.toLowerCase().trim();
    document.querySelectorAll('.zone-column-card .item-mini-card').forEach(card => {
        const text = card.getAttribute('data-search') || '';
        if (q === '' || text.includes(q)) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });
}

/**
 * Filter items in Unified Feed Table
 */
function filterCompareFeedTable(query) {
    const q = query.toLowerCase().trim();
    let visible = 0;
    document.querySelectorAll('#zone-compare-feed-tbody tr').forEach(row => {
        const text = row.getAttribute('data-search') || '';
        if (q === '' || text.includes(q)) {
            row.style.display = '';
            visible++;
        } else {
            row.style.display = 'none';
        }
    });
    const countEl = document.getElementById('compare-feed-row-count');
    if (countEl) countEl.textContent = `${visible} items found`;
}

/**
 * Filters the cross-zone feed to only display common/overlapping models
 */
function filterCompareFeedByOverlap() {
    if (!comparisonDataCache || !comparisonDataCache.summary || !comparisonDataCache.summary.overlapping_models) return;
    const overlapKeys = comparisonDataCache.summary.overlapping_models.map(m => m.model.toLowerCase());
    let visible = 0;
    document.querySelectorAll('#zone-compare-feed-tbody tr').forEach(row => {
        const text = row.getAttribute('data-search') || '';
        const match = overlapKeys.some(k => text.includes(k));
        if (match) {
            row.style.display = '';
            visible++;
        } else {
            row.style.display = 'none';
        }
    });
    const countEl = document.getElementById('compare-feed-row-count');
    if (countEl) countEl.textContent = `${visible} overlapping items shown`;
}

/**
 * Launches the migration modal pre-filled with the item from the comparison window
 */
function triggerMigrateFromCompare(itemJsonStr) {
    try {
        const item = typeof itemJsonStr === 'string' ? JSON.parse(itemJsonStr) : itemJsonStr;
        if (typeof window.openItemMigrationModal === 'function') {
            window.openItemMigrationModal(item);
        } else {
            alert(`Relocate item: ${item.brand} ${item.model} from ${item.location_code}`);
        }
    } catch (e) {
        console.error("Failed to parse item for migration:", e);
    }
}

/**
 * Exports current comparison data to a CSV file
 */
function exportZoneComparisonCSV() {
    if (!comparisonDataCache || !comparisonDataCache.zones_data) {
        alert("No comparison data available to export.");
        return;
    }

    const zonesData = comparisonDataCache.zones_data;
    const rows = [
        ['Zone', 'Location Shelf', 'Brand', 'Model', 'Sector', 'Condition', 'Quantity', 'Unit Price', 'Line Valuation', 'Notes']
    ];

    Object.keys(zonesData).forEach(zk => {
        const zd = zonesData[zk];
        (zd.items || []).forEach(it => {
            rows.push([
                zd.name,
                it.location_code || '',
                it.brand || '',
                it.model || '',
                it.sector || '',
                it.specs?.condition || 'B Grade',
                it.quantity || 1,
                it.price || 0,
                it.line_valuation || 0,
                it.specs?.notes || ''
            ]);
        });
    });

    const csvContent = "data:text/csv;charset=utf-8," 
        + rows.map(e => e.map(val => `"${String(val).replace(/"/g, '""')}"`).join(",")).join("\n");

    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `warehouse_zones_comparison_${activeComparisonZones.join('_')}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

/**
 * Opens print preview formatted for the comparison report
 */
function printZoneComparisonReport() {
    window.print();
}

// Global exports
window.initZoneHoldToSelectEvents = initZoneHoldToSelectEvents;
window.enterZoneCompareMode = enterZoneCompareMode;
window.exitZoneCompareMode = exitZoneCompareMode;
window.toggleZoneCompareMode = toggleZoneCompareMode;
window.toggleZoneSelection = toggleZoneSelection;
window.selectAllZonesForCompare = selectAllZonesForCompare;
window.launchComparisonFromDock = launchComparisonFromDock;
window.openZoneComparisonModal = openZoneComparisonModal;
window.closeZoneComparisonModal = closeZoneComparisonModal;
window.switchCompareTab = switchCompareTab;
window.setCompareSectorFilter = setCompareSectorFilter;
window.handleAddZoneToComparison = handleAddZoneToComparison;
window.handleRemoveZoneFromComparison = handleRemoveZoneFromComparison;
window.filterCompareColumns = filterCompareColumns;
window.filterCompareFeedTable = filterCompareFeedTable;
window.filterCompareFeedByOverlap = filterCompareFeedByOverlap;
window.triggerMigrateFromCompare = triggerMigrateFromCompare;
window.exportZoneComparisonCSV = exportZoneComparisonCSV;
window.printZoneComparisonReport = printZoneComparisonReport;

