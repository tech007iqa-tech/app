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

// Hydrate saved mode & sort on page load
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
});
