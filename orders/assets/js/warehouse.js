/**
 * Warehouse Master Coordinator
 * Central coordinator managing state, sub-module orchestration, and real-time AppSync synchronization.
 */

let __warehouseState = null;
function getWarehouseState() {
    if (__warehouseState) return __warehouseState;
    const el = document.getElementById('warehouse-state');
    __warehouseState = el ? JSON.parse(el.textContent) : {};
    return __warehouseState;
}

// 1. Ensure all warehouse sub-modules are loaded in order before initializing
function loadWarehouseModules(callback) {
    const modules = [
        'assets/js/warehouse/warehouse_gate.js',
        'assets/js/warehouse/warehouse_form.js',
        'assets/js/warehouse/warehouse_spreadsheet.js',
        'assets/js/warehouse/warehouse_bulk.js',
        'assets/js/warehouse/warehouse_modals.js',
        'assets/js/warehouse/warehouse_inventory.js'
    ];

    let pending = 0;
    modules.forEach(src => {
        if (!document.querySelector(`script[src*="${src}"]`)) {
            pending++;
            const script = document.createElement('script');
            script.src = src;
            script.async = false;
            script.onload = () => {
                pending--;
                if (pending === 0 && typeof callback === 'function') callback();
            };
            script.onerror = () => {
                pending--;
                if (pending === 0 && typeof callback === 'function') callback();
            };
            document.head.appendChild(script);
        }
    });

    if (pending === 0 && typeof callback === 'function') {
        callback();
    }
}

// 2. Initialize warehouse components on DOMContentLoaded
function initWarehouseApp() {
    if (typeof initWarehouseDatalists === 'function') initWarehouseDatalists();
    if (typeof initCpuGenChips === 'function') initCpuGenChips();
    if (typeof initGhostSuffixes === 'function') initGhostSuffixes();
    if (typeof initSessionCounter === 'function') initSessionCounter();
    if (typeof initPhotoHoverPreviews === 'function') initPhotoHoverPreviews();
    if (typeof initStickyTableHeaders === 'function') initStickyTableHeaders();

    if (window.location.search.includes('msg=added') && typeof incrementSessionCounter === 'function') {
        incrementSessionCounter();
    }

    // Build search index and bind global search hotkeys
    if (typeof buildWarehouseSearchIndex === 'function') {
        buildWarehouseSearchIndex();
    }

    // Global keyboard shortcuts (Ctrl+K / Cmd+K / Slash to search)
    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
            e.preventDefault();
            const sIn = document.getElementById('wh-search');
            if (sIn) {
                sIn.focus();
                sIn.select();
            }
        } else if (e.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)) {
            e.preventDefault();
            const sIn = document.getElementById('wh-search');
            if (sIn) {
                sIn.focus();
                sIn.select();
            }
        }
    });

    // Re-apply persistent search
    const savedSearch = sessionStorage.getItem('wh_active_search');
    const searchIn = document.getElementById('wh-search');
    const footerIn = document.getElementById('wh-search-footer');
    if (savedSearch && (searchIn || footerIn)) {
        if (searchIn) searchIn.value = savedSearch;
        if (footerIn) footerIn.value = savedSearch;
        const clearBtn = document.getElementById('search-clear-btn');
        if (clearBtn && savedSearch.trim() !== '') clearBtn.classList.add('visible');
        if (typeof filterWarehouse === 'function') filterWarehouse();
    }

    // Immediately strip hash to prevent jumping during search DOM updates
    if (window.location.hash) {
        setTimeout(() => {
            const url = new URL(window.location);
            window.history.replaceState({}, '', url.pathname + url.search);
        }, 100);
    }

    // Save form data to localStorage on submit
    const whForm = document.getElementById('wh-main-form');
    if (whForm) {
        whForm.addEventListener('submit', () => {
            const formData = new FormData(whForm);
            const data = {};
            formData.forEach((value, key) => {
                if (key !== 'item_id' && key !== 'action') {
                    data[key] = value;
                }
            });
            localStorage.setItem('wh_last_entry', JSON.stringify(data));
        });
    }

    // Hide clone button if no data
    const cloneBtn = document.getElementById('btn-clone-last');
    if (cloneBtn && !localStorage.getItem('wh_last_entry')) {
        cloneBtn.style.display = 'none';
    }

    // Restore sort preference
    const savedSort = localStorage.getItem('wh_gate_sort');
    const sortDropdown = document.getElementById('gate-loc-sort');
    if (savedSort && sortDropdown) {
        sortDropdown.value = savedSort;
        if (typeof sortGateLocations === 'function') sortGateLocations();
    }

    // Attach color coding event listeners to select boxes
    ['wh-spec-cpu', 'wh-condition'].forEach(id => {
        const el = document.getElementById(id);
        if (el && typeof updateSelectColors === 'function') {
            el.addEventListener('change', updateSelectColors);
        }
    });
    if (typeof updateSelectColors === 'function') updateSelectColors();

    // Register for real-time synchronization
    if (document.getElementById('inventory-list') && window.AppSync) {
        AppSync.register({
            elementId: 'inventory-list',
            url: window.location.pathname + window.location.search + (window.location.search ? '&ajax=1' : '?ajax=1'),
            onUpdate: () => {
                if (typeof buildWarehouseSearchIndex === 'function') buildWarehouseSearchIndex();
                if (typeof filterWarehouse === 'function') filterWarehouse();

                // Keep selected IDs up-to-date with DOM
                if (typeof selectedIds !== 'undefined') {
                    const currentIds = new Set(Array.from(document.querySelectorAll('#inventory-list .row-select')).map(cb => cb.closest('tr')?.dataset.id));
                    for (let id of selectedIds) {
                        if (!currentIds.has(id)) {
                            selectedIds.delete(id);
                        }
                    }
                    if (typeof updateBulkBar === 'function') updateBulkBar();
                }
            }
        });
    }

    // Initialize Bulk Actions & Spreadsheet mode
    if (typeof initWarehouseBulkActions === 'function') initWarehouseBulkActions();
    if (typeof initWarehouseSpreadsheetEvents === 'function') initWarehouseSpreadsheetEvents();
    if (typeof restoreWarehouseCursorFocus === 'function') restoreWarehouseCursorFocus();

    // Intercept delete forms for smooth animated row removal
    document.addEventListener('submit', async (e) => {
        const form = e.target;
        const actionInput = form.querySelector('input[name="action"]');
        if (actionInput && actionInput.value === 'delete_inventory') {
            if (e.defaultPrevented) return;
            e.preventDefault();

            const formData = new FormData(form);
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) submitBtn.disabled = true;

            try {
                const response = await fetch(form.getAttribute('action') || window.location.href, {
                    method: 'POST',
                    body: formData
                });
                if (response.ok) {
                    const row = form.closest('tr');
                    if (row) {
                        row.style.transition = 'all 0.3s ease';
                        row.style.opacity = '0';
                        row.style.transform = 'translateX(20px)';
                        setTimeout(() => {
                            row.remove();
                            if (window.AppSync && typeof window.AppSync.sync === 'function') {
                                window.AppSync.sync('inventory-list');
                            }
                        }, 300);
                    }
                } else {
                    alert("Failed to delete the item. Please try again.");
                    if (submitBtn) submitBtn.disabled = false;
                }
            } catch (error) {
                console.error("Error deleting item:", error);
                alert("An error occurred. Please try again.");
                if (submitBtn) submitBtn.disabled = false;
            }
        }
    });

    if (typeof toggleGamingFields === 'function') toggleGamingFields();
}

loadWarehouseModules(() => {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initWarehouseApp);
    } else {
        initWarehouseApp();
    }
});
